import { saveSession, loadSession, clearSession } from '../../src/auth/tokenStore';
import type { PermissionFlags } from '../../src/api/types';

const perms: PermissionFlags = {
  can_audit: true,
  can_audit_structural: true,
  can_select_audit_type: true,
  can_select_purpose: false,
  can_do_preventive: false,
  is_admin: false,
};

describe('tokenStore', () => {
  it('round-trips a session including permissions', async () => {
    await saveSession({ token: 't', user: { id: 1, name: 'A', username: 'a' }, permissions: perms });
    const s = await loadSession();
    expect(s?.token).toBe('t');
    expect(s?.user.username).toBe('a');
    expect(s?.permissions.can_audit_structural).toBe(true);
  });

  it('clears the session', async () => {
    await saveSession({ token: 't', user: { id: 1, name: 'A', username: 'a' }, permissions: perms });
    await clearSession();
    expect(await loadSession()).toBeNull();
  });
});
