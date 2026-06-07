import type { AuditType, AuditPurpose, CriterionValue } from '../api/types';

export interface AuditSubmission {
  clientUuid: string;
  spaceId: number;
  auditType: AuditType;
  purpose: AuditPurpose;
  observation: string;
  capturedAt: string; // ISO8601
  mode: 'new' | 'complement';
  values: Record<number, { value: CriterionValue; comment: string }>;
  photos: { uri: string; name?: string; type?: string }[];
}

export function buildAuditFormData(s: AuditSubmission): FormData {
  const fd = new FormData();
  fd.append('client_uuid', s.clientUuid);
  fd.append('space_id', String(s.spaceId));
  fd.append('audit_type', s.auditType);
  fd.append('purpose', s.purpose);
  fd.append('observation', s.observation ?? '');
  fd.append('captured_at', s.capturedAt);
  fd.append('mode', s.mode);

  Object.entries(s.values).forEach(([criterionId, v], i) => {
    fd.append(`values[${i}][criterion_id]`, String(criterionId));
    fd.append(`values[${i}][value]`, v.value);
    fd.append(`values[${i}][comment]`, v.value === 'bad' ? v.comment.trim() : '');
  });

  s.photos.forEach((p, i) => {
    fd.append(`photos[${i}]`, {
      uri: p.uri,
      name: p.name ?? `photo-${i}.jpg`,
      type: p.type ?? 'image/jpeg',
    } as unknown as Blob);
  });

  return fd;
}
