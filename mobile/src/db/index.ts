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
