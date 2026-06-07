import * as client from './client';
import type { Criterion, AuditType } from './types';

export async function listCriteria(type: AuditType, token: string) {
  const res = await client.apiFetch<{ data: Criterion[] }>('/criteria', { token, query: { type } });
  return res.data;
}
