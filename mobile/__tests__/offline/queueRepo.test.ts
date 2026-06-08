import type { Db } from '../../src/db';
import * as repo from '../../src/offline/queueRepo';
import type { NewSubmission } from '../../src/offline/types';

// Minimal in-memory fake covering exactly the statements queueRepo issues.
function fakeDb(): Db {
  const subs: any[] = [];
  const photos: any[] = [];
  let subId = 0;
  let photoId = 0;
  return {
    async execAsync() {},
    async runAsync(sql, ...p) {
      if (sql.startsWith('INSERT INTO submissions')) {
        subId++;
        subs.push({
          id: subId, client_uuid: p[0], space_id: p[1], external_code: p[2],
          audit_type: p[3], purpose: p[4], mode: p[5], observation: p[6],
          values_json: p[7], captured_at: p[8], status: 'queued', attempts: 0,
          permanent: 0, next_attempt_at: 0, last_error: null, server_audit_id: null,
          created_at: p[9],
        });
        return { lastInsertRowId: subId, changes: 1 };
      }
      if (sql.startsWith('INSERT INTO photos')) {
        photoId++;
        photos.push({ id: photoId, submission_id: p[0], local_uri: p[1], captured_at: p[2] });
        return { lastInsertRowId: photoId, changes: 1 };
      }
      if (sql.startsWith('UPDATE submissions SET status')) {
        const s = subs.find((x) => x.id === p[p.length - 1]);
        if (s) { s.status = p[0]; s.attempts = p[1]; s.permanent = p[2]; s.next_attempt_at = p[3]; s.last_error = p[4]; s.server_audit_id = p[5]; }
        return { lastInsertRowId: 0, changes: s ? 1 : 0 };
      }
      if (sql.startsWith('DELETE FROM photos')) {
        for (let i = photos.length - 1; i >= 0; i--) if (photos[i].submission_id === p[0]) photos.splice(i, 1);
        return { lastInsertRowId: 0, changes: 1 };
      }
      return { lastInsertRowId: 0, changes: 0 };
    },
    async getAllAsync(sql, ...p) {
      if (sql.includes('FROM submissions')) {
        let rows = [...subs];
        if (sql.includes("status IN ('queued','failed')")) rows = rows.filter((s) => (s.status === 'queued' || s.status === 'failed') && !s.permanent);
        return rows.map((s) => ({ ...s })) as any;
      }
      if (sql.includes('FROM photos')) return photos.filter((ph) => ph.submission_id === p[0]).map((x) => ({ ...x })) as any;
      return [] as any;
    },
    async getFirstAsync(sql, ...p) {
      if (sql.includes('COUNT(*)')) {
        const n = subs.filter((s) => s.status !== 'synced').length;
        return { n } as any;
      }
      const s = subs.find((x) => x.id === p[0]);
      return (s ? { ...s } : null) as any;
    },
  };
}

const sample: NewSubmission = {
  clientUuid: 'uuid-1', spaceId: 72, externalCode: '770', auditType: 'general',
  purpose: 'audit_only', mode: 'new', observation: 'obs',
  values: { 5: { value: 'bad', comment: 'roto' } }, capturedAt: '2026-06-07T10:00:00.000Z',
  photos: [{ localUri: 'file:///doc/p1.jpg', capturedAt: '2026-06-07T10:00:00.000Z' }],
};

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
