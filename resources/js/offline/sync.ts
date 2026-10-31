import { db, type OutboxEvent } from './db';
import { route } from 'ziggy-js';

const DEVICE_TOKEN_KEY = 'pharmagest.device_token';
let syncTimer: ReturnType<typeof setInterval> | null = null;
let syncPharmacyId: number | null = null;
let deviceRegistration: Promise<string | null> | null = null;

export function getDeviceToken(): string | null {
    return localStorage.getItem(DEVICE_TOKEN_KEY);
}

export function setDeviceToken(token: string): void {
    localStorage.setItem(DEVICE_TOKEN_KEY, token);
}

/**
 * Jeton CSRF courant : on privilégie le cookie `XSRF-TOKEN` (renouvelé à
 * chaque réponse serveur) et on retombe sur la meta Inertia.
 */
function csrfToken(): string {
    const cookies = document.cookie
        .split(';')
        .map((c) => c.trim())
        .filter((c) => c.startsWith('XSRF-TOKEN='));
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

/**
 * Enregistre le navigateur comme appareil de synchronisation (une seule fois).
 * Sans jeton, le push/pull est refusé par le serveur (401).
 */
export async function ensureDeviceToken(pharmacyId: number | null = syncPharmacyId): Promise<string | null> {
    const existing = getDeviceToken();
    if (existing) return existing;

    if (!deviceRegistration) {
        deviceRegistration = (async () => {
            try {
                const res = await fetch(route('sync.device'), {
                    method: 'POST',
                    headers: jsonHeaders(),
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        name: navigator.userAgent.slice(0, 120),
                        pharmacy_id: pharmacyId,
                    }),
                });

                if (!res.ok) return null;

                const data = (await res.json()) as { device_token?: string };
                if (!data.device_token) return null;

                setDeviceToken(data.device_token);
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
    const id = crypto.randomUUID();
    const event: OutboxEvent = {
        client_event_id: id,
        type,
        pharmacy_id: pharmacyId,
        payload,
        occurred_at: new Date().toISOString(),
        schema_version: 1,
        status: 'pending',
        attempts: 0,
        created_at: new Date().toISOString(),
    };
    await db.outbox.add(event);
    return id;
}

export async function pendingCount(): Promise<number> {
    return db.outbox.where('status').anyOf('pending', 'failed').count();
}

async function pushBatch(): Promise<void> {
    if (!navigator.onLine) return;

    const token = await ensureDeviceToken();
    if (!token) return;

    const events = await db.outbox.where('status').anyOf('pending', 'failed').toArray();
    if (events.length === 0) return;

    const res = await fetch(route('sync.push'), {
        method: 'POST',
        headers: jsonHeaders(),
        body: JSON.stringify({ device_token: token, events }),
        credentials: 'same-origin',
    });

    if (!res.ok) return;

    const data = await res.json();
    for (const r of data.results ?? []) {
        const ev = events.find((e) => e.client_event_id === r.client_event_id);
        if (!ev) continue;
        if (r.status === 'applied' || r.status === 'duplicate') {
            await db.outbox.update(ev.client_event_id, { status: 'sent' });
        } else if (r.status === 'conflict') {
            await db.outbox.update(ev.client_event_id, { status: 'conflict' });
        } else {
            await db.outbox.update(ev.client_event_id, { status: 'failed', attempts: ev.attempts + 1 });
        }
    }
}

export async function pullSnapshot(): Promise<void> {
    if (!navigator.onLine) return;

    const token = await ensureDeviceToken();
    if (!token) return;

    const meta = await db.meta.get('sync_version');
    const since = (meta?.value as number) ?? 0;

    const res = await fetch(`${route('sync.pull')}?device_token=${encodeURIComponent(token)}&since=${since}`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (!res.ok) return;

    const data = await res.json();
    await db.transaction('rw', db.products, db.customers, db.insurers, db.insurance_rules, db.stock_levels, db.meta, async () => {
        if (data.changes?.products) await db.products.bulkPut(data.changes.products);
        if (data.changes?.customers) await db.customers.bulkPut(data.changes.customers);
        if (data.changes?.insurers) await db.insurers.bulkPut(data.changes.insurers);
        if (data.changes?.insurance_rules) await db.insurance_rules.bulkPut(data.changes.insurance_rules);
        if (data.changes?.stock_levels) await db.stock_levels.bulkPut(data.changes.stock_levels);
        await db.meta.put({ key: 'sync_version', value: data.version });
        await db.meta.put({ key: 'last_sync', value: data.server_time });
    });
}

/**
 * Démarre la boucle de synchronisation. Idempotent : les appels suivants
 * (navigation Inertia, remontage du shell) ne créent pas de minuteur en plus.
 */
export function startSyncLoop(pharmacyId: number | null = null): void {
    if (syncTimer !== null) return;

    syncPharmacyId = pharmacyId;

    const run = async () => {
        try {
            await pushBatch();
            await pullSnapshot();
        } catch {
            // hors ligne ou serveur indisponible : on retentera au prochain tick
        }
    };

    void run();
    syncTimer = setInterval(run, 30000);
    window.addEventListener('online', run);
}

export function stopSyncLoop(): void {
    if (syncTimer === null) return;
    clearInterval(syncTimer);
    syncTimer = null;
}
