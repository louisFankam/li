import { reactive } from 'vue';
import { route } from 'ziggy-js';
import {
    closeUserDatabase,
    getDb,
    nextOutboxSequence,
    openUserDatabase,
    type OfflineCustomer,
    type OfflineInsuranceRule,
    type OfflineInsurer,
    type OfflineProduct,
    type OfflineStockLevel,
    type OutboxEvent,
} from './db';

const DEVICE_TOKEN_PREFIX = 'pharmagest.device_token.';
const SYNC_INTERVAL = 30_000;
const MAX_BACKOFF = 300_000;

export const syncState = reactive({
    pending: 0,
    lastSync: null as string | null,
    syncing: false,
    online: typeof navigator === 'undefined' ? true : navigator.onLine,
    error: null as string | null,
    userId: null as number | null,
    pharmacyId: null as number | null,
});

let timer: ReturnType<typeof setTimeout> | null = null;
let activeUserId: number | null = null;
let activePharmacyId: number | null = null;
let running = false;
let failureCount = 0;
let tickPromise: Promise<void> | null = null;
let onlineHandler: (() => void) | null = null;
let deviceRegistration: Promise<string | null> | null = null;

export function deviceTokenKey(userId: number): string {
    return `${DEVICE_TOKEN_PREFIX}${userId}`;
}

export function getDeviceToken(userId: number): string | null {
    return localStorage.getItem(deviceTokenKey(userId));
}

export function setDeviceToken(userId: number, token: string): void {
    localStorage.setItem(deviceTokenKey(userId), token);
}

export function clearDeviceToken(userId: number): void {
    localStorage.removeItem(deviceTokenKey(userId));
}

function csrfToken(): string {
    const cookies = document.cookie
        .split(';')
        .map((cookie) => cookie.trim())
        .filter((cookie) => cookie.startsWith('XSRF-TOKEN='));
    const fromCookie = cookies.length > 0 ? decodeURIComponent(cookies[0].split('=').slice(1).join('=')) : '';
    const fromMeta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

    return fromCookie || fromMeta;
}

function jsonHeaders(): Record<string, string> {
    return {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': csrfToken(),
    };
}

export async function ensureDeviceToken(userId: number, pharmacyId: number | null = activePharmacyId): Promise<string | null> {
    const existing = getDeviceToken(userId);
    if (existing) return existing;

    if (deviceRegistration === null) {
        deviceRegistration = (async () => {
            try {
                const response = await fetch(route('sync.device'), {
                    method: 'POST',
                    headers: jsonHeaders(),
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        name: navigator.userAgent.slice(0, 120),
                        pharmacy_id: pharmacyId,
                    }),
                });

                if (!response.ok) return null;

                const data = (await response.json()) as { device_token?: string };
                if (!data.device_token) return null;

                setDeviceToken(userId, data.device_token);
                return data.device_token;
            } catch {
                return null;
            } finally {
                deviceRegistration = null;
            }
        })();
    }

    return deviceRegistration;
}

export async function enqueueEvent(type: string, pharmacyId: number | null, payload: Record<string, unknown>): Promise<string> {
    const database = getDb();
    const id = crypto.randomUUID();

    await database.transaction('rw', [database.outbox, database.meta], async () => {
        const sequence = await nextOutboxSequence(database);
        const event: OutboxEvent = {
            client_event_id: id,
            type,
            pharmacy_id: pharmacyId,
            payload,
            occurred_at: new Date().toISOString(),
            schema_version: 1,
            status: 'pending',
            attempts: 0,
            sequence,
            created_at: new Date().toISOString(),
        };

        await database.outbox.add(event);
    });

    syncState.pending += 1;

    return id;
}

export async function pendingCount(): Promise<number> {
    return getDb().outbox.where('status').anyOf('pending', 'failed').count();
}

export async function refreshSyncState(): Promise<void> {
    try {
        syncState.pending = await pendingCount();
        const meta = await getDb().meta.get('last_sync');
        syncState.lastSync = typeof meta?.value === 'string' ? meta.value : null;
        syncState.online = typeof navigator === 'undefined' ? true : navigator.onLine;
    } catch {
        syncState.pending = 0;
    }
}

async function pushBatch(): Promise<void> {
    if (!navigator.onLine) return;

    const database = getDb();
    const events = (await database.outbox.where('status').anyOf('pending', 'failed').toArray())
        .sort((a, b) => (a.sequence ?? 0) - (b.sequence ?? 0))
        .slice(0, 50);
    if (events.length === 0) return;

    const token = await ensureDeviceToken(activeUserId ?? 0);
    if (!token) return;

    const response = await fetch(route('sync.push'), {
        method: 'POST',
        headers: jsonHeaders(),
        body: JSON.stringify({ device_token: token, events }),
        credentials: 'same-origin',
    });

    if (response.status === 401 && activeUserId !== null) {
        clearDeviceToken(activeUserId);
    }

    if (!response.ok) {
        throw new Error(`Push de synchronisation refusé (${response.status}).`);
    }

    const data = await response.json();
    for (const result of data.results ?? []) {
        const event = events.find((candidate) => candidate.client_event_id === result.client_event_id);
        if (!event) continue;

        const clientRequestId = event.payload?.client_request_id;

        if (result.status === 'applied' || result.status === 'duplicate') {
            await database.outbox.update(event.client_event_id, { status: 'sent' });

            if (event.type === 'sale.created' && typeof clientRequestId === 'string') {
                await database.local_sales.update(clientRequestId, {
                    status: 'synced',
                    server_sale_id: result.sale?.id ?? null,
                    sale_number: result.sale?.sale_number ?? undefined,
                    error: null,
                });
            }
        } else if (result.status === 'conflict') {
            await database.outbox.update(event.client_event_id, { status: 'conflict' });

            if (event.type === 'sale.created' && typeof clientRequestId === 'string') {
                await database.local_sales.update(clientRequestId, {
                    status: 'conflict',
                    error: result.error ?? 'Conflit de synchronisation.',
                });
            }
        } else if (result.status === 'rejected') {
            await database.outbox.update(event.client_event_id, { status: 'rejected' });

            if (event.type === 'sale.created' && typeof clientRequestId === 'string') {
                await database.local_sales.update(clientRequestId, {
                    status: 'rejected',
                    error: result.error ?? 'Vente rejetée par le serveur.',
                });
            }
        } else {
            await database.outbox.update(event.client_event_id, {
                status: 'failed',
                attempts: event.attempts + 1,
            });
        }
    }
}

type SyncRecord = {
    entity_type: 'product' | 'customer' | 'insurer' | 'insurance_rule' | 'stock_level';
    operation: 'upsert' | 'delete';
    pharmacy_id: number | null;
    payload: Record<string, any>;
};

type PullResponse = {
    server_time: string;
    mode: 'snapshot' | 'changes';
    records?: SyncRecord[];
    event_statuses?: Record<string, string>;
    settings?: { discounts?: { absolute_max_percent?: number } };
    next_cursor: number;
    has_more: boolean;
};

async function applyRecords(records: SyncRecord[]): Promise<void> {
    const database = getDb();

    await database.transaction(
        'rw',
        [database.products, database.customers, database.insurers, database.insurance_rules, database.stock_levels],
        async () => {
            for (const record of records) {
                const remove = record.operation === 'delete';

                switch (record.entity_type) {
                    case 'product':
                        if (remove) await database.products.delete(Number(record.payload.id));
                        else await database.products.put(record.payload as OfflineProduct);
                        break;
                    case 'customer':
                        if (remove) await database.customers.delete(Number(record.payload.id));
                        else await database.customers.put(record.payload as OfflineCustomer);
                        break;
                    case 'insurer':
                        if (remove) await database.insurers.delete(Number(record.payload.id));
                        else await database.insurers.put(record.payload as OfflineInsurer);
                        break;
                    case 'insurance_rule':
                        if (remove) await database.insurance_rules.delete(Number(record.payload.id));
                        else await database.insurance_rules.put(record.payload as OfflineInsuranceRule);
                        break;
                    case 'stock_level':
                        if (remove) {
                            await database.stock_levels.delete([Number(record.payload.pharmacy_id), Number(record.payload.product_id)]);
                        } else {
                            await database.stock_levels.put(record.payload as OfflineStockLevel);
                        }
                        break;
                }
            }
        },
    );
}

async function applyEventStatuses(statuses: Record<string, string>): Promise<void> {
    const database = getDb();

    for (const [clientEventId, serverStatus] of Object.entries(statuses)) {
        if (serverStatus === 'applied' || serverStatus === 'duplicate') {
            await database.outbox.update(clientEventId, { status: 'sent' });
        } else if (serverStatus === 'rejected' || serverStatus === 'cancelled') {
            await database.outbox.update(clientEventId, { status: 'rejected' });
        } else if (serverStatus === 'conflict') {
            await database.outbox.update(clientEventId, { status: 'conflict' });
        } else if (serverStatus === 'failed') {
            await database.outbox.update(clientEventId, { status: 'failed' });
        }
    }
}

export async function pullSnapshot(): Promise<void> {
    if (!navigator.onLine) return;

    const database = getDb();
    const token = await ensureDeviceToken(activeUserId ?? 0);
    if (!token) return;

    const meta = await database.meta.get('sync_cursor');
    let cursor = (meta?.value as number) ?? 0;
    let guard = 0;

    do {
        const unresolved = await database.outbox.where('status').anyOf('conflict', 'failed').limit(20).toArray();
        const params = new URLSearchParams({ since: String(cursor), limit: '200' });

        if (unresolved.length > 0) {
            params.set('events', unresolved.map((event) => event.client_event_id).join(','));
        }

        const response = await fetch(`${route('sync.pull')}?${params.toString()}`, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Device-Token': token,
            },
        });

        if (response.status === 401 && activeUserId !== null) {
            clearDeviceToken(activeUserId);
        }

        if (!response.ok) {
            throw new Error(`Pull de synchronisation refusé (${response.status}).`);
        }

        const data = (await response.json()) as PullResponse;
        await applyRecords(data.records ?? []);
        await applyEventStatuses(data.event_statuses ?? {});

        cursor = Number(data.next_cursor ?? cursor);
        await database.meta.put({ key: 'sync_cursor', value: cursor });
        await database.meta.put({ key: 'last_sync', value: data.server_time });

        if (data.settings?.discounts) {
            await database.meta.put({ key: 'discount_settings', value: data.settings.discounts });
        }

        if (!data.has_more) break;
        guard += 1;
    } while (guard < 100);
}

type NavigatorWithLocks = Navigator & {
    locks?: {
        request(name: string, options: { ifAvailable: boolean }, callback: (lock: unknown) => Promise<void>): Promise<void>;
    };
};

async function withSyncLock(task: () => Promise<void>): Promise<void> {
    const locks = typeof navigator === 'undefined' ? undefined : (navigator as NavigatorWithLocks).locks;

    if (!locks) {
        await task();
        return;
    }

    await locks.request('pharmagest-sync', { ifAvailable: true }, async (lock) => {
        if (lock) await task();
    });
}

function scheduleNext(): void {
    if (!running) return;
    if (timer !== null) clearTimeout(timer);

    const delay = failureCount === 0 ? SYNC_INTERVAL : Math.min(MAX_BACKOFF, SYNC_INTERVAL * 2 ** Math.min(failureCount, 4));
    timer = setTimeout(() => void tick(), delay);
}

async function tick(): Promise<void> {
    if (!running) return;

    if (!navigator.onLine) {
        syncState.online = false;
        scheduleNext();
        return;
    }

    if (tickPromise !== null) return tickPromise;

    const promise = (async () => {
        await withSyncLock(async () => {
            syncState.syncing = true;
            syncState.online = true;

            try {
                await pushBatch();
                await pullSnapshot();
                failureCount = 0;
                syncState.error = null;
            } catch (error) {
                failureCount += 1;
                syncState.error = error instanceof Error ? error.message : 'Synchronisation impossible.';
            } finally {
                await refreshSyncState();
                syncState.syncing = false;
            }
        });
    })();

    tickPromise = promise.finally(() => {
        tickPromise = null;
        scheduleNext();
    });

    return tickPromise;
}

export async function runSyncNow(): Promise<void> {
    await tick();
}

export function isSyncLoopActive(): boolean {
    return running;
}

export async function startSyncLoop(userId: number, pharmacyId: number | null = null): Promise<void> {
    if (running && activeUserId === userId && activePharmacyId === pharmacyId) {
        return;
    }

    // Laisser le cycle en cours se terminer avant de fermer sa base Dexie.
    if (tickPromise !== null) {
        await tickPromise.catch(() => undefined);
    }

    stopSyncLoop();
    await openUserDatabase(userId);

    running = true;
    activeUserId = userId;
    activePharmacyId = pharmacyId;
    syncState.userId = userId;
    syncState.pharmacyId = pharmacyId;
    syncState.lastSync = null;
    syncState.error = null;
    failureCount = 0;

    await refreshSyncState();

    onlineHandler = () => {
        syncState.online = true;
        void tick();
    };
    window.addEventListener('online', onlineHandler);

    void tick();
}

export function stopSyncLoop(): void {
    running = false;

    if (timer !== null) {
        clearTimeout(timer);
        timer = null;
    }

    if (onlineHandler !== null) {
        window.removeEventListener('online', onlineHandler);
        onlineHandler = null;
    }

    syncState.syncing = false;
    activeUserId = null;
    activePharmacyId = null;
}

export function resetSyncSession(): void {
    stopSyncLoop();
    closeUserDatabase();
    syncState.pending = 0;
    syncState.lastSync = null;
    syncState.userId = null;
    syncState.pharmacyId = null;
    syncState.error = null;
}
