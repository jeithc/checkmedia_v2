import * as client from './client';
import * as upload from './upload';
import type { Audit, AuditDetail } from './types';

export async function submitAudit(
  form: FormData,
  token: string,
  onProgress?: (fraction: number) => void,
) {
  const res = await upload.uploadMultipart<{ data: Audit }>('/audits', form, { token, onProgress });
  return res.data;
}

export async function getAudit(id: number, token: string) {
  const res = await client.apiFetch<{ data: AuditDetail }>(`/audits/${id}`, { token });
  return res.data;
}
