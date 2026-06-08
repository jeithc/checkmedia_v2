import { backoffDelayMs, isDue } from './backoff';
import type { Submission, SyncOutcome } from './types';

export interface SyncDeps {
  now: () => number;
  listClaimable: () => Promise<Submission[]>;
  markUploading: (id: number, attempts: number) => Promise<void>;
  markSynced: (id: number, serverAuditId: number) => Promise<void>;
  markConflict: (id: number, serverAuditId: number | null, message: string) => Promise<void>;
  markPermanent: (id: number, message: string) => Promise<void>;
  markTransient: (id: number, message: string, attempts: number, nextAttemptAt: number) => Promise<void>;
  submit: (s: Submission) => Promise<SyncOutcome>;
}

export interface SyncResult {
  synced: number;
  conflict: number;
  permanent: number;
  transient: number;
  skipped: number;
}

// Module-level guard so overlapping triggers (NetInfo + foreground + enqueue)
// never run the drain concurrently. A single boolean is correct because there
// is one real queue.
let running = false;

export async function runSync(deps: SyncDeps): Promise<SyncResult> {
  const result: SyncResult = { synced: 0, conflict: 0, permanent: 0, transient: 0, skipped: 0 };
  if (running) return result;
  running = true;
  try {
    const items = await deps.listClaimable();
    const now = deps.now();
    for (const s of items) {
      if (!isDue(s, now)) { result.skipped++; continue; }
      const attempts = s.attempts;
      await deps.markUploading(s.id, attempts);
      let outcome: SyncOutcome;
      try {
        outcome = await deps.submit(s);
      } catch (e) {
        outcome = { kind: 'transient', message: e instanceof Error ? e.message : 'error' };
      }
      switch (outcome.kind) {
        case 'synced':
          await deps.markSynced(s.id, outcome.serverAuditId);
          result.synced++;
          break;
        case 'conflict':
          await deps.markConflict(s.id, outcome.serverAuditId, outcome.message);
          result.conflict++;
          break;
        case 'permanent':
          await deps.markPermanent(s.id, outcome.message);
          result.permanent++;
          break;
        case 'transient': {
          const nextAttempts = attempts + 1;
          await deps.markTransient(s.id, outcome.message, nextAttempts, now + backoffDelayMs(nextAttempts));
          result.transient++;
          break;
        }
      }
    }
    return result;
  } finally {
    running = false;
  }
}

/** Test-only: reset the reentrancy guard between tests. */
export function __resetSyncGuard() {
  running = false;
}
