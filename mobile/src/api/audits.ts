import * as client from './client';
import type { Audit } from './types';

export async function submitAudit(form: FormData, token: string) {
  const res = await client.apiFetch<{ data: Audit }>('/audits', { method: 'POST', form, token });
  return res.data;
}
