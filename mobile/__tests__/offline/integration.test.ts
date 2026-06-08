import * as repo from '../../src/offline/queueRepo';
import { runSync, type SyncDeps, __resetSyncGuard } from '../../src/offline/syncEngine';
import type { SyncOutcome } from '../../src/offline/types';
import { makeFakeDb, sampleNewSubmission } from './_fakeDb';

describe('offline queue end-to-end', () => {
  it('queued offline item syncs after connectivity returns, idempotently', async () => {
    __resetSyncGuard();
    const db = makeFakeDb();
    const id = await repo.enqueue(db, sampleNewSubmission, 0);

    let calls = 0;
    const submit = async (): Promise<SyncOutcome> =>
      ++calls === 1
        ? { kind: 'transient', message: 'net' }
        : { kind: 'synced', serverAuditId: 900 };

    const deps = (now: number): SyncDeps => ({
      now: () => now,
      listClaimable: () => repo.listClaimable(db),
      markUploading: (i, a) => repo.markUploading(db, i, a),
      markSynced: (i, sid) => repo.markSynced(db, i, sid),
      markConflict: (i, sid, m) => repo.markConflict(db, i, sid, m),
      markPermanent: (i, m) => repo.markPermanent(db, i, m),
      markTransient: (i, m, a, n) => repo.markTransient(db, i, m, a, n),
      submit,
    });

    // First run: transient failure -> failed, attempts=1, backoff scheduled in the future.
    await runSync(deps(1000)); __resetSyncGuard();
    let item = (await repo.listAll(db))[0];
    expect(item.status).toBe('failed');
    expect(item.attempts).toBe(1);
    expect(item.nextAttemptAt).toBeGreaterThan(1000);

    // Second run still within backoff window -> item is skipped, stays failed.
    await runSync(deps(1000)); __resetSyncGuard();
    expect((await repo.listAll(db))[0].status).toBe('failed');
    expect(calls).toBe(1); // submit was not retried while not yet due

    // Advance past the backoff: item is due, retried, server accepts -> synced, photos cleared.
    await runSync(deps(item.nextAttemptAt + 1)); __resetSyncGuard();
    item = (await repo.listAll(db))[0];
    expect(item.status).toBe('synced');
    expect(item.serverAuditId).toBe(900);
    expect(await repo.photosFor(db, id)).toHaveLength(0);
  });
});
