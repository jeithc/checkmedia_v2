import { runSync, type SyncDeps } from '../../src/offline/syncEngine';
import type { Submission, SyncOutcome } from '../../src/offline/types';

function sub(over: Partial<Submission> = {}): Submission {
  return {
    id: 1, clientUuid: 'u1', spaceId: 1, externalCode: '1', auditType: 'general',
    purpose: 'audit_only', mode: 'new', observation: '', values: {}, capturedAt: 'x',
    status: 'queued', attempts: 0, permanent: false, nextAttemptAt: 0, lastError: null,
    serverAuditId: null, createdAt: 0, syncedAt: null, photos: [],
    ...over,
  };
}

function makeDeps(items: Submission[], submit: (s: Submission) => Promise<SyncOutcome>) {
  const calls: string[] = [];
  const deps: SyncDeps = {
    now: () => 10_000,
    listClaimable: async () => items,
    markUploading: async (id) => { calls.push(`uploading:${id}`); },
    markSynced: async (id, sid) => { calls.push(`synced:${id}:${sid}`); },
    markConflict: async (id, sid) => { calls.push(`conflict:${id}:${sid}`); },
    markPermanent: async (id) => { calls.push(`permanent:${id}`); },
    markTransient: async (id, _m, attempts, next) => { calls.push(`transient:${id}:${attempts}:${next}`); },
    submit,
  };
  return { deps, calls };
}

afterEach(() => require('../../src/offline/syncEngine').__resetSyncGuard());

describe('syncEngine.runSync', () => {
  it('uploads a due item and marks synced on 201', async () => {
    const { deps, calls } = makeDeps([sub()], async () => ({ kind: 'synced', serverAuditId: 99 }));
    const res = await runSync(deps);
    expect(calls).toEqual(['uploading:1', 'synced:1:99']);
    expect(res.synced).toBe(1);
  });

  it('marks conflict on 409 (no retry)', async () => {
    const { deps, calls } = makeDeps([sub()], async () => ({ kind: 'conflict', serverAuditId: 7, message: 'dup' }));
    await runSync(deps);
    expect(calls).toEqual(['uploading:1', 'conflict:1:7']);
  });

  it('marks permanent on 422 (no retry)', async () => {
    const { deps, calls } = makeDeps([sub()], async () => ({ kind: 'permanent', message: 'comentario' }));
    await runSync(deps);
    expect(calls).toEqual(['uploading:1', 'permanent:1']);
  });

  it('marks transient on network error with incremented attempts + backoff', async () => {
    const { deps, calls } = makeDeps([sub({ attempts: 1 })], async () => ({ kind: 'transient', message: 'net' }));
    await runSync(deps);
    // attempts 1 -> 2, next attempt = now(10_000) + backoff(2)=120_000 = 130_000
    expect(calls).toEqual(['uploading:1', 'transient:1:2:130000']);
  });

  it('skips items not yet due', async () => {
    const { deps, calls } = makeDeps([sub({ nextAttemptAt: 999_999 })], async () => ({ kind: 'synced', serverAuditId: 1 }));
    const res = await runSync(deps);
    expect(calls).toEqual([]);
    expect(res.skipped).toBe(1);
  });

  it('is reentrancy-guarded: concurrent runs do not double-process', async () => {
    let submits = 0;
    const { deps } = makeDeps([sub()], async () => { submits++; return { kind: 'synced', serverAuditId: 1 }; });
    await Promise.all([runSync(deps), runSync(deps)]);
    expect(submits).toBe(1);
  });
});
