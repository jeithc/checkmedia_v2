import React from 'react';
import { Text } from 'react-native';
import { render, waitFor, act } from '@testing-library/react-native';
import { AuthProvider, useAuth } from '../../src/auth/AuthContext';
import * as authApi from '../../src/api/auth';
import { saveSession, clearSession } from '../../src/auth/tokenStore';
import type { PermissionFlags } from '../../src/api/types';

const perms = (over: Partial<PermissionFlags> = {}): PermissionFlags => ({
  can_audit: true,
  can_audit_structural: false,
  can_select_audit_type: false,
  can_select_purpose: false,
  can_do_preventive: false,
  is_admin: false,
  ...over,
});

function Probe() {
  const { status, user, permissions, signIn, signOut, unlock } = useAuth();
  return (
    <>
      <Text testID="status">{status}</Text>
      <Text testID="user">{user?.username ?? 'none'}</Text>
      <Text testID="structural">{permissions ? String(permissions.can_audit_structural) : 'none'}</Text>
      <Text testID="trigger" onPress={() => signIn('u', 'p')}>go</Text>
      <Text testID="signout" onPress={() => signOut()}>out</Text>
      <Text testID="unlock" onPress={() => unlock()}>unlock</Text>
    </>
  );
}

describe('AuthContext', () => {
  beforeEach(async () => {
    await clearSession();
  });
  afterEach(() => jest.restoreAllMocks());

  it('starts unauthenticated when no stored session', async () => {
    const { getByTestId } = await render(<AuthProvider><Probe /></AuthProvider>);
    await waitFor(() => expect(getByTestId('status').props.children).toBe('unauthenticated'));
  });

  it('signs in and stores the session', async () => {
    jest.spyOn(authApi, 'login').mockResolvedValue({
      token: 't',
      user: { id: 1, name: 'A', username: 'auditor' },
      permissions: {
        can_audit: true, can_audit_structural: false, can_select_audit_type: false,
        can_select_purpose: false, can_do_preventive: false, is_admin: false,
      },
    });

    const { getByTestId } = await render(<AuthProvider><Probe /></AuthProvider>);
    await waitFor(() => expect(getByTestId('status').props.children).toBe('unauthenticated'));
    await act(async () => { getByTestId('trigger').props.onPress(); });
    await waitFor(() => expect(getByTestId('user').props.children).toBe('auditor'));
  });

  it('locks a stored session and unlocks it via biometrics, rehydrating permissions', async () => {
    await saveSession({
      token: 'stored-tok',
      user: { id: 2, name: 'B', username: 'storeduser' },
      permissions: perms({ can_audit_structural: true }),
    });
    const meSpy = jest.spyOn(authApi, 'me').mockResolvedValue({
      user: { id: 2, name: 'B', username: 'storeduser' },
      permissions: perms({ can_audit_structural: true }),
    });

    const { getByTestId } = await render(<AuthProvider><Probe /></AuthProvider>);

    // Biometrics available (jest.setup mocks hasHardware/enrolled true) => locked,
    // not yet exposing the session.
    await waitFor(() => expect(getByTestId('status').props.children).toBe('locked'));
    expect(getByTestId('user').props.children).toBe('none');

    // Biometric unlock succeeds (authenticateAsync mocked to { success: true }).
    await act(async () => { getByTestId('unlock').props.onPress(); });

    await waitFor(() => expect(getByTestId('status').props.children).toBe('authenticated'));
    expect(getByTestId('structural').props.children).toBe('true');
    await waitFor(() => expect(meSpy).toHaveBeenCalledWith('stored-tok'));
  });

  it('signs out and returns to unauthenticated even if logout throws', async () => {
    jest.spyOn(authApi, 'login').mockResolvedValue({
      token: 't',
      user: { id: 1, name: 'A', username: 'auditor' },
      permissions: perms(),
    });
    jest.spyOn(authApi, 'logout').mockRejectedValue(new Error('offline'));

    const { getByTestId } = await render(<AuthProvider><Probe /></AuthProvider>);
    await waitFor(() => expect(getByTestId('status').props.children).toBe('unauthenticated'));
    await act(async () => { getByTestId('trigger').props.onPress(); });
    await waitFor(() => expect(getByTestId('status').props.children).toBe('authenticated'));
    await act(async () => { getByTestId('signout').props.onPress(); });
    await waitFor(() => expect(getByTestId('status').props.children).toBe('unauthenticated'));
    expect(getByTestId('user').props.children).toBe('none');
  });
});
