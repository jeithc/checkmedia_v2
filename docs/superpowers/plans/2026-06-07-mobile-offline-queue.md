# Mobile Offline Submission Queue Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let field auditors save audits offline (instantly, no network) into a persistent SQLite queue that auto-syncs to `POST /api/audits` when connectivity returns, with idempotent retry, conflict/validation handling, and a visible queue.

**Architecture:** "Guardar" writes the submission + resized photos to SQLite (status `queued`) and copies photos to `documentDirectory` so they survive restarts — no network touched. A sync engine drains the queue sequentially (mark `uploading` → multipart POST → branch on 201/409/422/401/transient with exponential backoff). The engine is a pure function with an injected repository and submit function (unit-testable without SQLite/timers). Triggers: NetInfo offline→online, app foreground, and after enqueue. A `SyncProvider` exposes the pending count (home badge) and a manual "retry". Reuses the existing `buildAuditFormData` + `submitAudit`/`uploadMultipart` chain and `ApiError`.

**Tech Stack:** Expo SDK 56, React 19, TypeScript, `expo-sqlite`, `expo-file-system`, `@react-native-community/netinfo`, Jest + React Native Testing Library.

---

## File Structure

**New:**
- `mobile/src/db/index.ts` — open the SQLite DB and run schema migrations once.
- `mobile/src/offline/types.ts` — `Submission`, `PhotoRecord`, status unions, `SyncOutcome`.
- `mobile/src/offline/queueRepo.ts` — CRUD over `submissions`/`photos` (enqueue, list, claimable, status transitions, counts).
- `mobile/src/offline/photoStore.ts` — persist a resized photo into `documentDirectory`; delete a submission's photos on success.
- `mobile/src/offline/backoff.ts` — pure backoff schedule + `isDue` helper.
- `mobile/src/offline/syncEngine.ts` — pure `runSync({ repo, submit, now })` draining loop.
- `mobile/src/offline/SyncProvider.tsx` — context: `pendingCount`, `sync()`, `syncing`; wires NetInfo/AppState triggers.
- `mobile/app/(app)/queue.tsx` — queue screen (status per item, manual retry, conflict actions).

**Modified:**
- `mobile/package.json` / `package-lock.json` — add deps.
- `mobile/jest.setup.ts` — mock `expo-sqlite`, `expo-file-system`, `@react-native-community/netinfo`.
- `mobile/src/audit/useAuditSubmit.ts` — add `buildSubmissionRecord()` that produces the persisted shape (reusing `buildSubmission`).
- `mobile/app/(app)/audit/new.tsx` — `save()` enqueues to the queue (status `queued`) instead of awaiting a network POST; navigates home immediately; triggers a sync attempt.
- `mobile/app/(app)/home.tsx` — "N pendientes" badge linking to `/queue`.
- `mobile/app/(app)/_layout.tsx` — wrap the authenticated stack in `SyncProvider`; register `queue` route is automatic (file-based).

---

## Task 1: Dependencies + jest mocks

**Files:**
- Modify: `mobile/package.json`, `mobile/package-lock.json`
- Modify: `mobile/jest.setup.ts`

- [ ] **Step 1: Install SDK-compatible native modules**

Run (this resolves SDK 56-correct versions):
```bash
cd mobile && npx expo install expo-sqlite expo-file-system @react-native-community/netinfo
```
Expected: three packages added; `npx tsc --noEmit` still clean.

- [ ] **Step 2: Add jest mocks**

Append to `mobile/jest.setup.ts`. The `expo-sqlite` mock is an in-memory engine sufficient for our queries (it supports the exact SQL shapes the repo uses — see Task 4; keep it dumb: store rows in arrays, interpret only the statements we issue).

```ts
// --- expo-file-system: documentDirectory + copy/delete are no-ops returning paths ---
jest.mock('expo-file-system', () => ({
  documentDirectory: 'file:///doc/',
  copyAsync: jest.fn(async () => {}),
  deleteAsync: jest.fn(async () => {}),
  makeDirectoryAsync: jest.fn(async () => {}),
  getInfoAsync: jest.fn(async () => ({ exists: true })),
}));

// --- netinfo: default connected; tests can override addEventListener/fetch ---
jest.mock('@react-native-community/netinfo', () => ({
  __esModule: true,
  default: {
    addEventListener: jest.fn(() => () => {}),
    fetch: jest.fn(async () => ({ isConnected: true, isInternetReachable: true })),
  },
}));
```

Note: `expo-sqlite` is NOT globally mocked. The queueRepo test (Task 4) provides a local in-memory fake via dependency injection, so no module-level SQLite mock is needed. The syncEngine (Task 7) is injected and never imports SQLite.

- [ ] **Step 3: Verify**

Run: `cd mobile && npx tsc --noEmit && npm test`
Expected: tsc clean; existing 42 tests still pass (mocks are additive).

- [ ] **Step 4: Commit**

```bash
git add mobile/package.json mobile/package-lock.json mobile/jest.setup.ts
git commit -m "build(mobile): add expo-sqlite, expo-file-system, netinfo for offline queue"
```

---

## Task 2: Offline types

**Files:**
- Create: `mobile/src/offline/types.ts`

- [ ] **Step 1: Write the types**

```ts
import type { AuditType, AuditPurpose, CriterionValue } from '../api/types';

export type SubmissionStatus =
  | 'queued'      // waiting to send
  | 'uploading'   // in flight
  | 'synced'      // server accepted (201)
  | 'conflict'    // 409 — server already has an audit for this space/week/type
  | 'failed';     // 422 permanent OR transient awaiting retry (see attempts/last_error)

export interface PhotoRecord {
  id: number;
  submissionId: number;
  localUri: string;
  capturedAt: string;
}

export interface Submission {
  id: number;
  clientUuid: string;
  spaceId: number;
  externalCode: string;
  auditType: AuditType;
  purpose: AuditPurpose;
  mode: 'new' | 'complement';
  observation: string;
  values: Record<number, { value: CriterionValue; comment: string }>;
  capturedAt: string;
  status: SubmissionStatus;
  attempts: number;
  permanent: boolean;        // true => never auto-retry (422/409)
  nextAttemptAt: number;     // epoch ms; 0 = due now
  lastError: string | null;
  serverAuditId: number | null;
  createdAt: number;
  photos: PhotoRecord[];
}

/** New record to persist (no id/timestamps yet). */
export interface NewSubmission {
  clientUuid: string;
  spaceId: number;
  externalCode: string;
  auditType: AuditType;
  purpose: AuditPurpose;
  mode: 'new' | 'complement';
  observation: string;
  values: Record<number, { value: CriterionValue; comment: string }>;
  capturedAt: string;
  photos: { localUri: string; capturedAt: string }[];
}

export type SyncOutcome =
  | { kind: 'synced'; serverAuditId: number }
  | { kind: 'conflict'; serverAuditId: number | null; message: string }
  | { kind: 'permanent'; message: string }   // 422
  | { kind: 'transient'; message: string };   // network/5xx/401
```

- [ ] **Step 2: Verify**

Run: `cd mobile && npx tsc --noEmit`
Expected: clean.

- [ ] **Step 3: Commit**

```bash
git add mobile/src/offline/types.ts
git commit -m "feat(mobile): offline queue domain types"
```

---

## Task 3: Backoff schedule (pure)

**Files:**
- Create: `mobile/src/offline/backoff.ts`
- Test: `mobile/__tests__/offline/backoff.test.ts`

- [ ] **Step 1: Write the failing test**

```ts
import { backoffDelayMs, isDue } from '../../src/offline/backoff';

describe('backoff', () => {
  it('grows exponentially and caps', () => {
    expect(backoffDelayMs(0)).toBe(5_000);
    expect(backoffDelayMs(1)).toBe(30_000);
    expect(backoffDelayMs(2)).toBe(120_000);
    expect(backoffDelayMs(3)).toBe(600_000);
    expect(backoffDelayMs(99)).toBe(600_000); // capped
  });

  it('isDue compares nextAttemptAt to now', () => {
    expect(isDue({ nextAttemptAt: 0 }, 1000)).toBe(true);
    expect(isDue({ nextAttemptAt: 2000 }, 1000)).toBe(false);
    expect(isDue({ nextAttemptAt: 1000 }, 1000)).toBe(true);
  });
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd mobile && npx jest __tests__/offline/backoff.test.ts`
Expected: FAIL (module not found).

- [ ] **Step 3: Implement**

```ts
const SCHEDULE = [5_000, 30_000, 120_000, 600_000];

/** Delay before the next attempt given how many attempts already failed. */
export function backoffDelayMs(attempts: number): number {
  const i = Math.min(attempts, SCHEDULE.length - 1);
  return SCHEDULE[i];
}

export function isDue(s: { nextAttemptAt: number }, now: number): boolean {
  return s.nextAttemptAt <= now;
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `cd mobile && npx jest __tests__/offline/backoff.test.ts`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add mobile/src/offline/backoff.ts mobile/__tests__/offline/backoff.test.ts
git commit -m "feat(mobile): exponential backoff schedule for sync retries"
```

---

## Task 4: Queue repository (SQLite)

**Files:**
- Create: `mobile/src/db/index.ts`
- Create: `mobile/src/offline/queueRepo.ts`
- Test: `mobile/__tests__/offline/queueRepo.test.ts`

The repo takes an injected `Db` handle (a minimal subset of `expo-sqlite`'s `SQLiteDatabase`). `db/index.ts` provides the real singleton; tests pass an in-memory fake. This keeps the repo unit-testable without mocking SQLite globally.

- [ ] **Step 1: Define the Db port and DB opener**

`mobile/src/db/index.ts`:
```ts
import * as SQLite from 'expo-sqlite';

/** Minimal async SQLite surface the repo depends on (injectable for tests). */
export interface Db {
  execAsync(sql: string): Promise<void>;
  runAsync(sql: string, ...params: (string | number | null)[]): Promise<{ lastInsertRowId: number; changes: number }>;
  getAllAsync<T = any>(sql: string, ...params: (string | number | null)[]): Promise<T[]>;
  getFirstAsync<T = any>(sql: string, ...params: (string | number | null)[]): Promise<T | null>;
}

const SCHEMA = `
PRAGMA journal_mode = WAL;
CREATE TABLE IF NOT EXISTS submissions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  client_uuid TEXT NOT NULL UNIQUE,
  space_id INTEGER NOT NULL,
  external_code TEXT NOT NULL,
  audit_type TEXT NOT NULL,
  purpose TEXT NOT NULL,
  mode TEXT NOT NULL,
  observation TEXT NOT NULL DEFAULT '',
  values_json TEXT NOT NULL,
  captured_at TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'queued',
  attempts INTEGER NOT NULL DEFAULT 0,
  permanent INTEGER NOT NULL DEFAULT 0,
  next_attempt_at INTEGER NOT NULL DEFAULT 0,
  last_error TEXT,
  server_audit_id INTEGER,
  created_at INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS photos (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  submission_id INTEGER NOT NULL,
  local_uri TEXT NOT NULL,
  captured_at TEXT NOT NULL,
  FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
);
`;

let _db: Db | null = null;

export async function getDb(): Promise<Db> {
  if (_db) return _db;
  const db = await SQLite.openDatabaseAsync('checkmedia.db');
  await db.execAsync(SCHEMA);
  _db = db as unknown as Db;
  return _db;
}
```

- [ ] **Step 2: Write the failing test (with an in-memory fake Db)**

`mobile/__tests__/offline/queueRepo.test.ts`:
```ts
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
```

- [ ] **Step 3: Run to verify it fails**

Run: `cd mobile && npx jest __tests__/offline/queueRepo.test.ts`
Expected: FAIL (queueRepo not implemented).

- [ ] **Step 4: Implement the repo**

`mobile/src/offline/queueRepo.ts`:
```ts
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
  await update(db, id, 'synced', 0, 0, 0, null, serverAuditId);
  await db.runAsync(`DELETE FROM photos WHERE submission_id = ?`, id);
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
```

- [ ] **Step 5: Run to verify it passes**

Run: `cd mobile && npx jest __tests__/offline/queueRepo.test.ts`
Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
git add mobile/src/db/index.ts mobile/src/offline/queueRepo.ts mobile/__tests__/offline/queueRepo.test.ts
git commit -m "feat(mobile): SQLite-backed offline submission queue repository"
```

---

## Task 5: Photo persistence

**Files:**
- Create: `mobile/src/offline/photoStore.ts`
- Test: `mobile/__tests__/offline/photoStore.test.ts`

Resized photos from `expo-image-manipulator` land in the cache directory (evictable). For offline durability, copy them into `documentDirectory/audit-photos/` before enqueue.

- [ ] **Step 1: Write the failing test**

```ts
import * as FileSystem from 'expo-file-system';
import { persistPhoto, deletePhotos } from '../../src/offline/photoStore';

describe('photoStore', () => {
  beforeEach(() => jest.clearAllMocks());

  it('copies a photo into documentDirectory and returns the new uri', async () => {
    const out = await persistPhoto('file:///cache/tmp123.jpg', 'uuid-1', 0);
    expect(out).toBe('file:///doc/audit-photos/uuid-1-0.jpg');
    expect(FileSystem.makeDirectoryAsync).toHaveBeenCalled();
    expect(FileSystem.copyAsync).toHaveBeenCalledWith({
      from: 'file:///cache/tmp123.jpg',
      to: 'file:///doc/audit-photos/uuid-1-0.jpg',
    });
  });

  it('deletes given uris, ignoring missing files', async () => {
    (FileSystem.deleteAsync as jest.Mock).mockRejectedValueOnce(new Error('missing'));
    await expect(deletePhotos(['file:///doc/a.jpg', 'file:///doc/b.jpg'])).resolves.toBeUndefined();
    expect(FileSystem.deleteAsync).toHaveBeenCalledTimes(2);
  });
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd mobile && npx jest __tests__/offline/photoStore.test.ts`
Expected: FAIL.

- [ ] **Step 3: Implement**

```ts
import * as FileSystem from 'expo-file-system';

const DIR = `${FileSystem.documentDirectory}audit-photos/`;

async function ensureDir() {
  await FileSystem.makeDirectoryAsync(DIR, { intermediates: true }).catch(() => {});
}

/** Copy a (resized) photo into durable storage. Returns the persistent uri. */
export async function persistPhoto(srcUri: string, clientUuid: string, index: number): Promise<string> {
  await ensureDir();
  const to = `${DIR}${clientUuid}-${index}.jpg`;
  await FileSystem.copyAsync({ from: srcUri, to });
  return to;
}

/** Best-effort delete; ignores already-missing files. */
export async function deletePhotos(uris: string[]): Promise<void> {
  await Promise.all(
    uris.map((uri) => FileSystem.deleteAsync(uri, { idempotent: true }).catch(() => {})),
  );
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `cd mobile && npx jest __tests__/offline/photoStore.test.ts`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add mobile/src/offline/photoStore.ts mobile/__tests__/offline/photoStore.test.ts
git commit -m "feat(mobile): persist resized audit photos to documentDirectory"
```

---

## Task 6: Sync engine (pure draining loop)

**Files:**
- Create: `mobile/src/offline/syncEngine.ts`
- Test: `mobile/__tests__/offline/syncEngine.test.ts`

The engine depends on an injected `repo`-like object and a `submit(submission) => Promise<SyncOutcome>` function. It processes claimable items sequentially, respecting `isDue`. No SQLite, no timers, no FormData — those are wired at the edges (Task 8). This makes every branch deterministically testable.

- [ ] **Step 1: Write the failing test**

```ts
import { runSync, type SyncDeps } from '../../src/offline/syncEngine';
import type { Submission, SyncOutcome } from '../../src/offline/types';

function sub(over: Partial<Submission> = {}): Submission {
  return {
    id: 1, clientUuid: 'u1', spaceId: 1, externalCode: '1', auditType: 'general',
    purpose: 'audit_only', mode: 'new', observation: '', values: {}, capturedAt: 'x',
    status: 'queued', attempts: 0, permanent: false, nextAttemptAt: 0, lastError: null,
    serverAuditId: null, createdAt: 0, photos: [],
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
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd mobile && npx jest __tests__/offline/syncEngine.test.ts`
Expected: FAIL.

- [ ] **Step 3: Implement**

```ts
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
// never run the drain concurrently. Keyed by the deps object's submit identity
// is overkill; a single boolean is correct because there is one real queue.
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
```

Note for the reentrancy test: since `running` is module-level, add `afterEach(() => require('../../src/offline/syncEngine').__resetSyncGuard())` to the test file, and for the concurrent test the two `runSync` calls share the guard within one tick — the second sees `running === true` and returns early. Keep the `__resetSyncGuard` call out of the concurrent test itself.

- [ ] **Step 4: Run to verify it passes**

Run: `cd mobile && npx jest __tests__/offline/syncEngine.test.ts`
Expected: PASS (6 tests).

- [ ] **Step 5: Commit**

```bash
git add mobile/src/offline/syncEngine.ts mobile/__tests__/offline/syncEngine.test.ts
git commit -m "feat(mobile): pure sync engine draining the offline queue"
```

---

## Task 7: Submit adapter (Submission → API outcome)

**Files:**
- Create: `mobile/src/offline/submitAdapter.ts`
- Test: `mobile/__tests__/offline/submitAdapter.test.ts`

Bridges a stored `Submission` to the existing `buildAuditFormData` + `submitAudit` chain and maps `ApiError` to a `SyncOutcome`.

- [ ] **Step 1: Write the failing test**

```ts
import { submissionToOutcome } from '../../src/offline/submitAdapter';
import { ApiError } from '../../src/api/errors';
import * as auditsApi from '../../src/api/audits';
import type { Submission } from '../../src/offline/types';

jest.mock('../../src/api/audits');

const s: Submission = {
  id: 1, clientUuid: 'u1', spaceId: 72, externalCode: '770', auditType: 'general',
  purpose: 'audit_only', mode: 'new', observation: 'obs',
  values: { 5: { value: 'bad', comment: 'roto' } }, capturedAt: 'x',
  status: 'uploading', attempts: 0, permanent: false, nextAttemptAt: 0, lastError: null,
  serverAuditId: null, createdAt: 0,
  photos: [{ id: 1, submissionId: 1, localUri: 'file:///doc/p.jpg', capturedAt: 'x' }],
};

describe('submitAdapter', () => {
  afterEach(() => jest.resetAllMocks());

  it('returns synced with server id on success', async () => {
    (auditsApi.submitAudit as jest.Mock).mockResolvedValue({ id: 555 });
    const out = await submissionToOutcome(s, 'tok');
    expect(out).toEqual({ kind: 'synced', serverAuditId: 555 });
  });

  it('maps 409 to conflict', async () => {
    (auditsApi.submitAudit as jest.Mock).mockRejectedValue(new ApiError(409, 'dup', { audit_id: 7 }));
    const out = await submissionToOutcome(s, 'tok');
    expect(out.kind).toBe('conflict');
  });

  it('maps 422 to permanent', async () => {
    (auditsApi.submitAudit as jest.Mock).mockRejectedValue(new ApiError(422, 'comentario requerido'));
    const out = await submissionToOutcome(s, 'tok');
    expect(out).toEqual({ kind: 'permanent', message: 'comentario requerido' });
  });

  it('maps network/5xx/401 to transient', async () => {
    (auditsApi.submitAudit as jest.Mock).mockRejectedValue(new ApiError(0, 'sin red'));
    const out = await submissionToOutcome(s, 'tok');
    expect(out.kind).toBe('transient');
  });
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd mobile && npx jest __tests__/offline/submitAdapter.test.ts`
Expected: FAIL.

- [ ] **Step 3: Implement** (verify the actual `ApiError` shape first — read `mobile/src/api/errors.ts`; it exposes `status`, `isConflict`, `isValidation`, `isNetwork`. Use those flags rather than raw status where available.)

```ts
import * as auditsApi from '../api/audits';
import { buildAuditFormData } from '../audit/payload';
import { ApiError } from '../api/errors';
import type { Submission, SyncOutcome } from './types';

export async function submissionToOutcome(s: Submission, token: string): Promise<SyncOutcome> {
  const form = buildAuditFormData({
    clientUuid: s.clientUuid,
    spaceId: s.spaceId,
    auditType: s.auditType,
    purpose: s.purpose,
    observation: s.observation,
    capturedAt: s.capturedAt,
    mode: s.mode,
    values: s.values,
    photos: s.photos.map((p) => ({ uri: p.localUri, name: `photo-${p.id}.jpg`, type: 'image/jpeg' })),
  });

  try {
    const audit = await auditsApi.submitAudit(form, token);
    return { kind: 'synced', serverAuditId: audit.id };
  } catch (e) {
    if (e instanceof ApiError) {
      if (e.isConflict) {
        const existingId = (e.body as { audit_id?: number } | null)?.audit_id ?? null;
        return { kind: 'conflict', serverAuditId: existingId, message: e.message };
      }
      if (e.isValidation) return { kind: 'permanent', message: e.message };
      // network (status 0), 401, 5xx, timeouts
      return { kind: 'transient', message: e.message };
    }
    return { kind: 'transient', message: e instanceof Error ? e.message : 'error' };
  }
}
```

(If `ApiError` lacks `isConflict`/`isValidation`/`isNetwork` or a `body` field, adapt to its real properties — read the file. Do not invent fields.)

- [ ] **Step 4: Run to verify it passes**

Run: `cd mobile && npx jest __tests__/offline/submitAdapter.test.ts`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add mobile/src/offline/submitAdapter.ts mobile/__tests__/offline/submitAdapter.test.ts
git commit -m "feat(mobile): adapter mapping a stored submission to a sync outcome"
```

---

## Task 8: SyncProvider (wires repo + engine + triggers)

**Files:**
- Create: `mobile/src/offline/SyncProvider.tsx`
- Test: `mobile/__tests__/offline/SyncProvider.test.tsx`

Provides `{ pendingCount, syncing, sync, items, refresh }` to the app, builds the real `SyncDeps` from `getDb()`/`queueRepo`/`submitAdapter`, and registers triggers: NetInfo offline→online, AppState → active, and a `sync()` call exposed for "after enqueue".

- [ ] **Step 1: Write the failing test** (focus on contract: provides a context, runs a sync on mount when connected, exposes pendingCount)

```tsx
import React from 'react';
import { render, waitFor } from '@testing-library/react-native';
import { Text } from 'react-native';
import { SyncProvider, useSync } from '../../src/offline/SyncProvider';
import * as repo from '../../src/offline/queueRepo';
import * as engine from '../../src/offline/syncEngine';
import * as db from '../../src/db';

jest.mock('../../src/db');
jest.spyOn(db, 'getDb').mockResolvedValue({} as any);

function Probe() {
  const { pendingCount } = useSync();
  return <Text testID="count">{pendingCount}</Text>;
}

describe('SyncProvider', () => {
  afterEach(() => jest.restoreAllMocks());

  it('exposes pendingCount and triggers a sync on mount', async () => {
    jest.spyOn(repo, 'pendingCount').mockResolvedValue(3);
    const runSpy = jest.spyOn(engine, 'runSync').mockResolvedValue({ synced: 0, conflict: 0, permanent: 0, transient: 0, skipped: 0 });

    const { getByTestId } = await render(
      <SyncProvider token="tok"><Probe /></SyncProvider>,
    );

    await waitFor(() => expect(getByTestId('count').props.children).toBe(3));
    await waitFor(() => expect(runSpy).toHaveBeenCalled());
  });
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd mobile && npx jest __tests__/offline/SyncProvider.test.tsx`
Expected: FAIL.

- [ ] **Step 3: Implement**

```tsx
import React, { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react';
import { AppState } from 'react-native';
import NetInfo from '@react-native-community/netinfo';
import { getDb } from '../db';
import * as repo from './queueRepo';
import { runSync } from './syncEngine';
import { submissionToOutcome } from './submitAdapter';
import { deletePhotos } from './photoStore';
import type { Submission } from './types';

interface SyncCtx {
  pendingCount: number;
  syncing: boolean;
  items: Submission[];
  sync: () => Promise<void>;
  refresh: () => Promise<void>;
}

const Ctx = createContext<SyncCtx | null>(null);

export function useSync(): SyncCtx {
  const v = useContext(Ctx);
  if (!v) throw new Error('useSync must be used within SyncProvider');
  return v;
}

export function SyncProvider({ token, children }: { token: string | null; children: React.ReactNode }) {
  const [pendingCount, setPendingCount] = useState(0);
  const [items, setItems] = useState<Submission[]>([]);
  const [syncing, setSyncing] = useState(false);
  const tokenRef = useRef(token);
  tokenRef.current = token;

  const refresh = useCallback(async () => {
    const db = await getDb();
    setItems(await repo.listAll(db));
    setPendingCount(await repo.pendingCount(db));
  }, []);

  const sync = useCallback(async () => {
    const tok = tokenRef.current;
    if (!tok) return;
    setSyncing(true);
    try {
      const db = await getDb();
      await runSync({
        now: () => Date.now(),
        listClaimable: () => repo.listClaimable(db),
        markUploading: (id, attempts) => repo.markUploading(db, id, attempts),
        markSynced: async (id, sid) => {
          const photos = await repo.photosFor(db, id);
          await repo.markSynced(db, id, sid);
          await deletePhotos(photos.map((p) => p.localUri));
        },
        markConflict: (id, sid, msg) => repo.markConflict(db, id, sid, msg),
        markPermanent: (id, msg) => repo.markPermanent(db, id, msg),
        markTransient: (id, msg, attempts, next) => repo.markTransient(db, id, msg, attempts, next),
        submit: (s) => submissionToOutcome(s, tok),
      });
    } finally {
      setSyncing(false);
      await refresh();
    }
  }, [refresh]);

  // Initial load + sync on mount.
  useEffect(() => { refresh(); sync(); }, [refresh, sync]);

  // Trigger: connectivity offline -> online.
  useEffect(() => {
    const unsub = NetInfo.addEventListener((state) => {
      if (state.isConnected) sync();
    });
    return () => unsub();
  }, [sync]);

  // Trigger: app returns to foreground.
  useEffect(() => {
    const subAS = AppState.addEventListener('change', (s) => {
      if (s === 'active') sync();
    });
    return () => subAS.remove();
  }, [sync]);

  return (
    <Ctx.Provider value={{ pendingCount, syncing, items, sync, refresh }}>
      {children}
    </Ctx.Provider>
  );
}
```

(Fix the `useRef` import casing — it is `useRef` from `react`. The destructured import line above must read `useCallback, useContext, useEffect, useRef, useState`.)

- [ ] **Step 4: Run to verify it passes**

Run: `cd mobile && npx jest __tests__/offline/SyncProvider.test.tsx`
Expected: PASS.

- [ ] **Step 5: Wire the provider into the authenticated layout**

In `mobile/app/(app)/_layout.tsx`, read it first. Keep the auth-guard Redirect. Pull the token from `useAuth()` and wrap the `<Stack>` in `<SyncProvider token={token}>`. Do not change `headerShown: false` or other options.

- [ ] **Step 6: Verify**

Run: `cd mobile && npx tsc --noEmit && npm test`
Expected: clean; all suites pass.

- [ ] **Step 7: Commit**

```bash
git add mobile/src/offline/SyncProvider.tsx mobile/__tests__/offline/SyncProvider.test.tsx "mobile/app/(app)/_layout.tsx"
git commit -m "feat(mobile): SyncProvider wiring queue, engine and connectivity triggers"
```

---

## Task 9: Enqueue on save (audit form) + photo persistence in capture

**Files:**
- Modify: `mobile/src/audit/useAuditSubmit.ts`
- Modify: `mobile/app/(app)/audit/new.tsx`
- Test: `mobile/__tests__/screens/auditFormEnqueue.test.tsx`

`save()` must STOP doing a network POST. Instead: persist each photo to `documentDirectory`, build a `NewSubmission`, `enqueue` it (status `queued`), fire-and-forget `sync()`, and navigate home immediately.

- [ ] **Step 1: Add `buildNewSubmission` to useAuditSubmit.ts**

Read the file first. Add (reusing the existing `buildSubmission` for the clientUuid + shaping):
```ts
import type { NewSubmission } from '../offline/types';

export function buildNewSubmission(input: BuildInput, persistedPhotoUris: string[]): NewSubmission {
  const base = buildSubmission(input); // gives clientUuid, mode, values, etc.
  return {
    clientUuid: base.clientUuid,
    spaceId: base.spaceId,
    externalCode: input.externalCode,
    auditType: base.auditType,
    purpose: base.purpose,
    mode: base.mode,
    observation: base.observation,
    values: base.values,
    capturedAt: base.capturedAt,
    photos: persistedPhotoUris.map((uri) => ({ localUri: uri, capturedAt: base.capturedAt })),
  };
}
```
Add `externalCode: string` to `BuildInput`. (It is needed for the queue display. The audit form already receives `code` as a route param.)

- [ ] **Step 2: Write the failing test** (enqueue, no network, navigates home)

```tsx
import React from 'react';
import { render, fireEvent, waitFor } from '@testing-library/react-native';
import AuditFormScreen from '../../app/(app)/audit/new';
import * as repo from '../../src/offline/queueRepo';
import * as photoStore from '../../src/offline/photoStore';
// ... mock useAuth, useLocalSearchParams (spaceId/code), criteria query, SyncProvider's useSync, router

it('saving writes to the queue without a network call and goes home', async () => {
  const enqueue = jest.spyOn(repo, 'enqueue').mockResolvedValue(1);
  jest.spyOn(photoStore, 'persistPhoto').mockResolvedValue('file:///doc/audit-photos/u-0.jpg');
  // render with a captured photo + at least the criteria answered, press "Guardar auditoría"
  // assert: enqueue called once; submitAudit NOT called; router.push('/(app)/home') called
});
```
(Implement the mocks following the existing `auditForm.test.tsx` patterns — read that file to mirror its mock setup for `useAuth`, `useLocalSearchParams`, the criteria `useQuery`, and `expo-router`. The screen now also calls `useSync()`, so wrap in a stub provider or mock `useSync` to return `{ sync: jest.fn(), pendingCount: 0, ... }`.)

- [ ] **Step 3: Run to verify it fails**

Run: `cd mobile && npx jest __tests__/screens/auditFormEnqueue.test.tsx`
Expected: FAIL.

- [ ] **Step 4: Rewrite `save()` in new.tsx**

Replace the body of `save()` (keep validation incl. the relaxed-photo-when-complementing rule). New flow:
```ts
const { sync } = useSync();
// ...
const save = async () => {
  // ... existing validation building fullValues + errs ...
  if (errs.length > 0) { setErrors(errs); return; }

  setBusy(true);
  try {
    const db = await getDb();
    // 1. persist photos to durable storage
    const persisted: string[] = [];
    for (let i = 0; i < photos.length; i++) {
      persisted.push(await persistPhoto(photos[i].uri, clientUuidRef.current, i));
    }
    // 2. enqueue
    const submission = buildNewSubmission(
      {
        spaceId: Number(spaceId), externalCode: String(code), auditType, purpose,
        observation, values: fullValues, photos, capturedAt: capturedAt ?? new Date().toISOString(),
        mode: isComplement ? 'complement' : 'new',
      },
      persisted,
    );
    await repo.enqueue(db, submission, Date.now());
    setDone(true);
    // 3. fire-and-forget sync; do not block UX
    sync();
    setTimeout(() => router.push('/(app)/home'), 600);
  } catch (e) {
    setErrors([e instanceof Error ? e.message : 'No se pudo guardar.']);
  } finally {
    setBusy(false);
  }
};
```
Notes:
- Generate the `clientUuid` once per form instance (e.g. a `useRef(uuidv4())` or have `buildNewSubmission` own it via `buildSubmission`). Ensure the SAME uuid is used for `persistPhoto` filenames and the enqueued record — simplest: generate the submission first (it owns the uuid), then persist photos using `submission.clientUuid`, then enqueue. Reorder accordingly.
- Remove the direct `submitBuiltAudit`/`onProgress`/upload-progress UI from this screen (progress now lives in the queue, not the form). Keep the success chip ("Auditoría guardada.").
- Keep ALL other logic (complement preload, lockedBad, photo capture/remove, type/purpose selectors) intact.

- [ ] **Step 5: Persist on capture (optional refinement) — leave capture in cache, persist at save**

Keep `capturePhoto()` returning cache uris; persistence happens in `save()` (above). This avoids orphaned files when a user cancels a form. No change to `capture.ts` needed.

- [ ] **Step 6: Run tests**

Run: `cd mobile && npx jest __tests__/screens/auditFormEnqueue.test.tsx && npx jest __tests__/screens/auditForm.test.tsx`
Expected: new test PASS; update the OLD `auditForm.test.tsx` if it asserted a network submit — change it to assert enqueue (mirror the new behavior). Keep its selector-gating assertions.

- [ ] **Step 7: Full verify**

Run: `cd mobile && npx tsc --noEmit && npm test`
Expected: all green.

- [ ] **Step 8: Commit**

```bash
git add mobile/src/audit/useAuditSubmit.ts "mobile/app/(app)/audit/new.tsx" mobile/__tests__/screens/auditFormEnqueue.test.tsx mobile/__tests__/screens/auditForm.test.tsx
git commit -m "feat(mobile): audit form saves to the offline queue instead of a blocking upload"
```

---

## Task 10: Queue screen + home badge

**Files:**
- Create: `mobile/app/(app)/queue.tsx`
- Modify: `mobile/app/(app)/home.tsx`
- Test: `mobile/__tests__/screens/queue.test.tsx`

- [ ] **Step 1: Write the failing test for the queue screen**

```tsx
import React from 'react';
import { render } from '@testing-library/react-native';
import QueueScreen from '../../app/(app)/queue';
import * as SyncCtx from '../../src/offline/SyncProvider';

it('renders one row per submission with its status', async () => {
  jest.spyOn(SyncCtx, 'useSync').mockReturnValue({
    pendingCount: 2, syncing: false, sync: jest.fn(), refresh: jest.fn(),
    items: [
      { id: 1, externalCode: '770', status: 'queued', photos: [{}], attempts: 0, lastError: null } as any,
      { id: 2, externalCode: '881', status: 'failed', photos: [{}, {}], attempts: 2, lastError: 'sin red' } as any,
    ],
  });
  const { getByText } = await render(<QueueScreen />);
  expect(getByText('770')).toBeTruthy();
  expect(getByText(/sin red/)).toBeTruthy();
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd mobile && npx jest __tests__/screens/queue.test.tsx`
Expected: FAIL.

- [ ] **Step 3: Implement the queue screen**

Use the existing primitives (Screen, AppHeader, Card, Badge, Button). For each `item` in `useSync().items` render a Card: external code (title), a status Badge (`queued`→neutral "En cola", `uploading`→primary "Enviando", `synced`→good "Enviado ✓", `failed`→warning "Error" + `lastError`, `conflict`→danger "Duplicado"), photo count, attempts. For `failed`/`conflict`, a "Reintentar" Button calling `repo.requeue(db, id)` then `sync()` (via a small handler that uses `getDb()`); for `conflict`, also a "Ver auditoría" Button linking to `/(app)/audit/{serverAuditId}` when present. A header "Sincronizar ahora" action calls `sync()`. Pull-to-refresh calls `refresh()`. Keep colors/spacing from theme. Use `<AppHeader onBack={() => router.back()} title="Envíos" />` via the Screen `header` prop.

- [ ] **Step 4: Add the badge to home.tsx**

Read it first. Add, near the header, a pressable "N pendientes" Badge (only when `pendingCount > 0`) navigating to `/(app)/queue`:
```tsx
const { pendingCount } = useSync();
// in the AppHeader right slot or just under it:
{pendingCount > 0 && (
  <Pressable onPress={() => router.push('/(app)/queue')}>
    <Badge label={`${pendingCount} pendientes`} tone="warning" icon="cloud-upload-outline" />
  </Pressable>
)}
```
Preserve all existing home logic/strings/testIDs.

- [ ] **Step 5: Run tests**

Run: `cd mobile && npx jest __tests__/screens/queue.test.tsx __tests__/screens/home.test.tsx`
Expected: PASS (update home.test.tsx only if it breaks on the new badge; the badge is gated on `pendingCount > 0` so a mock with 0 shows nothing).

- [ ] **Step 6: Full verify**

Run: `cd mobile && npx tsc --noEmit && npm test`
Expected: all green.

- [ ] **Step 7: Commit**

```bash
git add "mobile/app/(app)/queue.tsx" "mobile/app/(app)/home.tsx" mobile/__tests__/screens/queue.test.tsx
git commit -m "feat(mobile): offline queue screen and home pending badge"
```

---

## Task 11: End-to-end integration test

**Files:**
- Test: `mobile/__tests__/offline/integration.test.ts`

- [ ] **Step 1: Write the test** (enqueue offline → engine runs → synced, photos deleted)

Drive the real `queueRepo` (with the in-memory fake Db from Task 4, exported as a shared test helper or duplicated) + the real `syncEngine`, with a stubbed `submit` that returns `transient` on the first call and `synced` on the second. Assert: after first run the item is `failed` with attempts=1 and `nextAttemptAt > now`; after advancing `now` past the backoff and running again, it is `synced` and `photosFor` is empty.

```ts
// Pseudocode skeleton — implement with the fake Db helper.
import * as repo from '../../src/offline/queueRepo';
import { runSync, __resetSyncGuard } from '../../src/offline/syncEngine';

it('queued offline item syncs after connectivity returns, idempotently', async () => {
  __resetSyncGuard();
  const db = makeFakeDb();
  const id = await repo.enqueue(db, sampleNewSubmission, 0);

  let calls = 0;
  const submit = async () => (++calls === 1
    ? { kind: 'transient', message: 'net' } as const
    : { kind: 'synced', serverAuditId: 900 } as const);

  const deps = (now: number) => ({
    now: () => now,
    listClaimable: () => repo.listClaimable(db),
    markUploading: (i: number, a: number) => repo.markUploading(db, i, a),
    markSynced: (i: number, sid: number) => repo.markSynced(db, i, sid),
    markConflict: (i: number, sid: number | null, m: string) => repo.markConflict(db, i, sid, m),
    markPermanent: (i: number, m: string) => repo.markPermanent(db, i, m),
    markTransient: (i: number, m: string, a: number, n: number) => repo.markTransient(db, i, m, a, n),
    submit,
  });

  await runSync(deps(1000)); __resetSyncGuard();
  let item = (await repo.listAll(db))[0];
  expect(item.status).toBe('failed');
  expect(item.attempts).toBe(1);

  await runSync(deps(1000)); __resetSyncGuard(); // still within backoff -> skipped
  expect((await repo.listAll(db))[0].status).toBe('failed');

  await runSync(deps(item.nextAttemptAt + 1)); __resetSyncGuard();
  item = (await repo.listAll(db))[0];
  expect(item.status).toBe('synced');
  expect(item.serverAuditId).toBe(900);
  expect(await repo.photosFor(db, id)).toHaveLength(0);
});
```

Extract the `makeFakeDb()` and `sampleNewSubmission` into a small shared helper `mobile/__tests__/offline/_fakeDb.ts` and import it in both Task 4 and this test (DRY).

- [ ] **Step 2: Run**

Run: `cd mobile && npx jest __tests__/offline/integration.test.ts`
Expected: PASS.

- [ ] **Step 3: Full verify + commit**

```bash
cd mobile && npx tsc --noEmit && npm test
git add mobile/__tests__/offline/integration.test.ts mobile/__tests__/offline/_fakeDb.ts mobile/__tests__/offline/queueRepo.test.ts
git commit -m "test(mobile): end-to-end offline enqueue-then-sync integration"
```

---

## Self-Review Notes (resolved)

- **Spec coverage:** SQLite tables (Task 2/4), photo persistence to documentDirectory (Task 5), enqueue without network (Task 9), sequential drain with 201/409/422/401/transient + backoff (Task 6/7), triggers NetInfo/foreground/after-enqueue (Task 8), `/queue` screen + home badge (Task 10), idempotency (same `client_uuid` reuses the server's existing audit — handled server-side already; the client never duplicates because a synced item is removed from the claimable set). Background task (`expo-task-manager`) is intentionally deferred — the spec marks it best-effort and the three foreground triggers are the primary path; note this as a documented omission, not silent.
- **Type consistency:** `Submission`/`NewSubmission`/`SyncOutcome`/`SyncDeps` names are used identically across Tasks 2/4/6/7/8. `BuildInput` gains `externalCode` (Task 9) and `payload.AuditSubmission` is unchanged (the adapter builds it inline).
- **No placeholders:** every code step is concrete. The two screen tests (Task 9 step 2, Task 10) reference "mirror the existing mock setup" — that is a real, existing file (`auditForm.test.tsx`) the implementer must read, not a placeholder.

## Out of scope (per spec / YAGNI)
- `expo-task-manager` periodic background sync (best-effort; deferred).
- Photo chunking for very large submissions.
- Offline space search / preloaded catalog.
- iOS, push notifications.
