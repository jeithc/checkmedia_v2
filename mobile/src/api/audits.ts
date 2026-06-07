import * as upload from './upload';
import type { Audit } from './types';

export async function submitAudit(
  form: FormData,
  token: string,
  onProgress?: (fraction: number) => void,
) {
  const res = await upload.uploadMultipart<{ data: Audit }>('/audits', form, { token, onProgress });
  return res.data;
}
