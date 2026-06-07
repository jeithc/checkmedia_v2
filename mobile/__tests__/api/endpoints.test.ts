import * as client from '../../src/api/client';
import { login, me, logout } from '../../src/api/auth';
import { searchSpace } from '../../src/api/spaces';
import { listCriteria } from '../../src/api/criteria';
import { submitAudit } from '../../src/api/audits';

describe('endpoint functions', () => {
  afterEach(() => jest.restoreAllMocks());

  it('login posts credentials and returns the session', async () => {
    const spy = jest.spyOn(client, 'apiFetch').mockResolvedValue({ token: 't' } as never);
    await login({ username: 'u', password: 'p', deviceName: 'pixel' });
    expect(spy).toHaveBeenCalledWith('/login', {
      method: 'POST',
      json: { username: 'u', password: 'p', device_name: 'pixel' },
    });
  });

  it('searchSpace unwraps the data envelope', async () => {
    jest.spyOn(client, 'apiFetch').mockResolvedValue({ data: { id: 1, external_code: 'A' } } as never);
    const res = await searchSpace('A', 'general', 'tok');
    expect(res).toEqual({ id: 1, external_code: 'A' });
  });

  it('listCriteria unwraps the data array', async () => {
    jest.spyOn(client, 'apiFetch').mockResolvedValue({ data: [{ id: 1, name: 'N', key: 'k' }] } as never);
    const res = await listCriteria('general', 'tok');
    expect(res).toHaveLength(1);
    expect(res[0].key).toBe('k');
  });

  it('submitAudit posts a FormData and unwraps the audit', async () => {
    const spy = jest.spyOn(client, 'apiFetch').mockResolvedValue({ data: { id: 9 } } as never);
    const form = new FormData();
    const res = await submitAudit(form, 'tok');
    expect(res).toEqual({ id: 9 });
    expect(spy).toHaveBeenCalledWith('/audits', { method: 'POST', form, token: 'tok' });
  });

  it('me and logout call their paths', async () => {
    const spy = jest.spyOn(client, 'apiFetch').mockResolvedValue({} as never);
    await me('tok');
    await logout('tok');
    expect(spy).toHaveBeenCalledWith('/me', { token: 'tok' });
    expect(spy).toHaveBeenCalledWith('/logout', { method: 'POST', token: 'tok' });
  });
});
