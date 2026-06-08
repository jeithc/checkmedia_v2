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
  syncedAt: number | null;
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
