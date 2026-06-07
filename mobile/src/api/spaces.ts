import * as client from './client';
import type { SpaceSearchResult, AuditType } from './types';

export async function searchSpace(code: string, type: AuditType, token: string) {
  const res = await client.apiFetch<{ data: SpaceSearchResult }>('/spaces/search', {
    token,
    query: { code, type },
  });
  return res.data;
}
