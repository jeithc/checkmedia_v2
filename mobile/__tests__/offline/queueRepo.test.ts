import * as repo from '../../src/offline/queueRepo';
import { makeFakeDb as fakeDb, sampleNewSubmission as sample } from './_fakeDb';

describe('queueRepo', () => {
  it('enqueues a submission with its photos and lists it as claimable', async () => {
    const db = fakeDb();
    const id = await repo.enqueue(db, sample, 1000);
    expect(id).toBe(1);

    const claimable = await repo.listClaimable(db);
    expect(claimable).toHaveLength(1);
    expect(claimable[0].clientUuid).toBe('uuid-1');
    expect(claimable[0].values[5].value).toBe('bad');
    expect(claimable[0].photos[0].localUri).toBe('file:///doc/p1.jpg');
    expect(claimable[0].status).toBe('queued');
  });

  it('marks synced and removes photos', async () => {
    const db = fakeDb();
    const id = await repo.enqueue(db, sample, 1000);
    await repo.markSynced(db, id, 555);
    const all = await repo.listAll(db);
    expect(all[0].status).toBe('synced');
    expect(all[0].serverAuditId).toBe(555);
    expect(await repo.photosFor(db, id)).toHaveLength(0);
  });

  it('marks transient failure (retryable) and permanent failure (not claimable)', async () => {
    const db = fakeDb();
    const id = await repo.enqueue(db, sample, 1000);

    await repo.markTransient(db, id, 'network', 1, 50_000);
    let claimable = await repo.listClaimable(db);
    expect(claimable[0].attempts).toBe(1);
    expect(claimable[0].status).toBe('failed');

    await repo.markPermanent(db, id, 'comentario requerido');
    claimable = await repo.listClaimable(db);
    expect(claimable).toHaveLength(0); // permanent excluded
  });

  it('counts unsynced', async () => {
    const db = fakeDb();
    await repo.enqueue(db, sample, 1000);
    await repo.enqueue(db, { ...sample, clientUuid: 'uuid-2' }, 1001);
    expect(await repo.pendingCount(db)).toBe(2);
  });
});
