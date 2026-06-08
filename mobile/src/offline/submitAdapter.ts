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
