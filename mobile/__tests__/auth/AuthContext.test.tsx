import React from 'react';
import { Text } from 'react-native';
import { render, waitFor, act } from '@testing-library/react-native';
import { AuthProvider, useAuth } from '../../src/auth/AuthContext';
import * as authApi from '../../src/api/auth';

function Probe() {
  const { status, user, signIn } = useAuth();
  return (
    <>
      <Text testID="status">{status}</Text>
      <Text testID="user">{user?.username ?? 'none'}</Text>
      <Text testID="trigger" onPress={() => signIn('u', 'p')}>go</Text>
    </>
  );
}

describe('AuthContext', () => {
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
});
