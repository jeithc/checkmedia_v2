import { saveSession, loadSession, clearSession } from '../../src/auth/tokenStore';

describe('tokenStore', () => {
  it('round-trips a session', async () => {
    await saveSession({ token: 't', user: { id: 1, name: 'A', username: 'a' } });
    const s = await loadSession();
    expect(s?.token).toBe('t');
    expect(s?.user.username).toBe('a');
  });

  it('clears the session', async () => {
    await saveSession({ token: 't', user: { id: 1, name: 'A', username: 'a' } });
    await clearSession();
    expect(await loadSession()).toBeNull();
  });
});
