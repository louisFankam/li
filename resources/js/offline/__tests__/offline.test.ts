import Dexie from 'dexie';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { closeUserDatabase, databaseName, getDb, openUserDatabase, PharmaGestDb, type OutboxEvent } from '../db';
import {
    clearDeviceToken,
    enqueueEvent,
    getDeviceToken,
    isSyncLoopActive,
    pendingCount,
    resetSyncSession,
    runSyncNow,
    setDeviceToken,
    startSyncLoop,
    syncState,
} from '../sync';

const USER_A = 900_001;
const USER_B = 900_002;
const MIGRATION_DB = 'pharmagest-migration-test';

function event(id: string): OutboxEvent {
    return {
        client_event_id: id,
        type: 'stock.adjusted',
        pharmacy_id: 1,
        payload: { product_id: 1, delta: 1 },
        occurred_at: new Date().toISOString(),
        schema_version: 1,
        status: 'pending',
        attempts: 0,
        sequence: 1,
        created_at: new Date().toISOString(),
    };
}

describe('Dexie offline lifecycle', () => {
    beforeEach(() => {
        localStorage.clear();
        syncState.pending = 0;
        syncState.lastSync = null;
    });

    afterEach(async () => {
        closeUserDatabase();
        await Dexie.delete(databaseName(USER_A));
        await Dexie.delete(databaseName(USER_B));
        await Dexie.delete(MIGRATION_DB);
    });

    it('isolates outbox and data by user', async () => {
        await openUserDatabase(USER_A);
        await enqueueEvent('stock.adjusted', 1, { product_id: 1, delta: 1 });
        await getDb().meta.put({ key: 'sync_cursor', value: 12 });

        await openUserDatabase(USER_B);
        expect(await getDb().outbox.count()).toBe(0);
        expect(await getDb().meta.get('sync_cursor')).toBeUndefined();

        await openUserDatabase(USER_A);
        expect(await getDb().outbox.count()).toBe(1);
        expect((await getDb().meta.get('sync_cursor'))?.value).toBe(12);
        expect(await pendingCount()).toBe(1);
    });

    it('keeps the outbox when the Dexie schema is upgraded', async () => {
        const legacy = new Dexie(MIGRATION_DB);
        legacy.version(1).stores({
            outbox: 'client_event_id, status, created_at',
        });
        await legacy.open();
        await legacy.table('outbox').add(event('legacy-event'));
        legacy.close();

        const upgraded = new PharmaGestDb(MIGRATION_DB);
        await upgraded.open();

        expect(await upgraded.outbox.count()).toBe(1);
        expect((await upgraded.outbox.toCollection().first())?.client_event_id).toBe('legacy-event');
        upgraded.close();
    });

    it('stores device tokens per user', () => {
        setDeviceToken(USER_A, 'token-a');

        expect(getDeviceToken(USER_A)).toBe('token-a');
        expect(getDeviceToken(USER_B)).toBeNull();

        clearDeviceToken(USER_A);
        expect(getDeviceToken(USER_A)).toBeNull();
    });

    it('keeps a single online listener and stops the loop on reset', async () => {
        const addListener = vi.spyOn(window, 'addEventListener');
        const removeListener = vi.spyOn(window, 'removeEventListener');
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => ({
                ok: true,
                status: 200,
                json: async () => ({
                    server_time: new Date().toISOString(),
                    mode: 'changes',
                    records: [],
                    event_statuses: {},
                    next_cursor: 0,
                    has_more: false,
                }),
            })),
        );
        setDeviceToken(USER_A, 'token-a');

        try {
            await startSyncLoop(USER_A, 1);
            await runSyncNow();
            expect(addListener.mock.calls.filter(([event]) => event === 'online')).toHaveLength(1);
            expect(isSyncLoopActive()).toBe(true);

            await startSyncLoop(USER_A, 1);
            expect(addListener.mock.calls.filter(([event]) => event === 'online')).toHaveLength(1);

            await startSyncLoop(USER_A, 2);
            await runSyncNow();
            expect(removeListener.mock.calls.filter(([event]) => event === 'online')).toHaveLength(1);

            resetSyncSession();
            expect(removeListener.mock.calls.filter(([event]) => event === 'online')).toHaveLength(2);
            expect(isSyncLoopActive()).toBe(false);
        } finally {
            resetSyncSession();
            addListener.mockRestore();
            removeListener.mockRestore();
            vi.unstubAllGlobals();
        }
    });
});
