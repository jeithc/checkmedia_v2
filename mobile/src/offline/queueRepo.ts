import type { Db } from '../db';
import type { Submission, NewSubmission, PhotoRecord } from './types';

function rowToSubmission(r: any, photos: PhotoRecord[]): Submission {
  return {
    id: r.id,
    clientUuid: r.client_uuid,
    spaceId: r.space_id,
    externalCode: r.external_code,
    auditType: r.audit_type,
    purpose: r.purpose,
    mode: r.mode,
    observation: r.observation ?? '',
    values: JSON.parse(r.values_json),
    capturedAt: r.captured_at,
    status: r.status,
    attempts: r.attempts,
    permanent: !!r.permanent,
    nextAttemptAt: r.next_attempt_at,
    lastError: r.last_error ?? null,
    serverAuditId: r.server_audit_id ?? null,
    createdAt: r.created_at,
    photos,
  };
}

export async function enqueue(db: Db, s: NewSubmission, now: number): Promise<number> {
  const res = await db.runAsync(
    `INSERT INTO submissions
       (client_uuid, space_id, external_code, audit_type, purpose, mode, observation, values_json, captured_at, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
    s.clientUuid, s.spaceId, s.externalCode, s.auditType, s.purpose, s.mode,
    s.observation ?? '', JSON.stringify(s.values), s.capturedAt, now,
  );
  const id = res.lastInsertRowId;
  for (const p of s.photos) {
    await db.runAsync(
      `INSERT INTO photos (submission_id, local_uri, captured_at) VALUES (?, ?, ?)`,
      id, p.localUri, p.capturedAt,
    );
  }
  return id;
}

export async function photosFor(db: Db, submissionId: number): Promise<PhotoRecord[]> {
  const rows = await db.getAllAsync<any>(
    `SELECT id, submission_id, local_uri, captured_at FROM photos WHERE submission_id = ?`,
    submissionId,
  );
  return rows.map((r) => ({ id: r.id, submissionId: r.submission_id, localUri: r.local_uri, capturedAt: r.captured_at }));
}

async function hydrate(db: Db, rows: any[]): Promise<Submission[]> {
  const out: Submission[] = [];
  for (const r of rows) out.push(rowToSubmission(r, await photosFor(db, r.id)));
  return out;
}

/** Submissions eligible for an automatic send attempt: queued or transient-failed, not permanent. */
export async function listClaimable(db: Db): Promise<Submission[]> {
  const rows = await db.getAllAsync<any>(
    `SELECT * FROM submissions WHERE status IN ('queued','failed') AND permanent = 0 ORDER BY created_at ASC`,
  );
  return hydrate(db, rows);
}

export async function listAll(db: Db): Promise<Submission[]> {
  const rows = await db.getAllAsync<any>(`SELECT * FROM submissions ORDER BY created_at DESC`);
  return hydrate(db, rows);
}

export async function getById(db: Db, id: number): Promise<Submission | null> {
  const r = await db.getFirstAsync<any>(`SELECT * FROM submissions WHERE id = ?`, id);
  return r ? rowToSubmission(r, await photosFor(db, id)) : null;
}

export async function pendingCount(db: Db): Promise<number> {
  const r = await db.getFirstAsync<{ n: number }>(
    `SELECT COUNT(*) as n FROM submissions WHERE status != 'synced'`,
  );
  return r?.n ?? 0;
}

function update(
  db: Db, id: number, status: string, attempts: number, permanent: number,
  nextAttemptAt: number, lastError: string | null, serverAuditId: number | null,
) {
  return db.runAsync(
    `UPDATE submissions SET status = ?, attempts = ?, permanent = ?, next_attempt_at = ?, last_error = ?, server_audit_id = ? WHERE id = ?`,
    status, attempts, permanent, nextAttemptAt, lastError, serverAuditId, id,
  );
}

export async function markUploading(db: Db, id: number, attempts: number) {
  await update(db, id, 'uploading', attempts, 0, 0, null, null);
}

export async function markSynced(db: Db, id: number, serverAuditId: number) {
  // Keep the photo ROWS for history (so the queue can still show "2 fotos"
  // after sync); only the on-disk files are deleted by the caller.
  await update(db, id, 'synced', 0, 0, 0, null, serverAuditId);
}

/**
 * Recover submissions orphaned in 'uploading' (a prior sync attempt crashed
 * after marking uploading but before reaching a terminal state). They are not
 * claimable while 'uploading', so reset them to 'queued'. Safe to call at the
 * start of a sync run (the reentrancy guard ensures no real upload is in flight).
 */
export async function resetStaleUploading(db: Db): Promise<void> {
  await db.runAsync(`UPDATE submissions SET status = 'queued' WHERE status = 'uploading'`);
}

export async function markConflict(db: Db, id: number, serverAuditId: number | null, message: string) {
  await update(db, id, 'conflict', 0, 1, 0, message, serverAuditId);
}

export async function markPermanent(db: Db, id: number, message: string) {
  await update(db, id, 'failed', 0, 1, 0, message, null);
}

export async function markTransient(db: Db, id: number, message: string, attempts: number, nextAttemptAt: number) {
  await update(db, id, 'failed', attempts, 0, nextAttemptAt, message, null);
}

/** Force a permanent/conflict item back into the queue for a manual retry. */
export async function requeue(db: Db, id: number) {
  await update(db, id, 'queued', 0, 0, 0, null, null);
}
