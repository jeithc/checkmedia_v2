import React from 'react';
import { render, fireEvent, waitFor } from '@testing-library/react-native';
import HomeScreen from '../../app/(app)/home';
import * as spacesApi from '../../src/api/spaces';
import * as AuthCtx from '../../src/auth/AuthContext';
import * as SyncCtx from '../../src/offline/SyncProvider';
import { ApiError } from '../../src/api/errors';

const mockPush = jest.fn();
jest.mock('expo-router', () => ({ router: { push: (...a: unknown[]) => mockPush(...a) } }));
jest.mock('expo-sqlite', () => ({ openDatabaseAsync: jest.fn(async () => ({})) }));

function mockAuth() {
  jest.spyOn(AuthCtx, 'useAuth').mockReturnValue({
    status: 'authenticated', token: 'tok', user: { id: 1, name: 'A', username: 'a' },
    permissions: {
      can_audit: true, can_audit_structural: false, can_select_audit_type: false,
      can_select_purpose: false, can_do_preventive: false, is_admin: false,
    },
    signIn: jest.fn(), signOut: jest.fn(), lock: jest.fn(), unlock: jest.fn(),
  });
}

function mockSync() {
  jest.spyOn(SyncCtx, 'useSync').mockReturnValue({
    pendingCount: 0, syncing: false, items: [], sync: jest.fn(), refresh: jest.fn(),
  });
}

describe('HomeScreen', () => {
  beforeEach(() => { mockPush.mockClear(); mockAuth(); mockSync(); });
  afterEach(() => jest.restoreAllMocks());

  it('navigates to the space route on a successful search', async () => {
    jest.spyOn(spacesApi, 'searchSpace').mockResolvedValue({
      id: 3, external_code: 'ABC', type: 'Billboard', duplicate: false, existing_audit_id: null, booking: null,
      city: null, location_name: null, address: null, zone: null, provider: null,
    });

    const { getByTestId, getByText } = await render(<HomeScreen />);
    await fireEvent.changeText(getByTestId('code'), 'ABC');
    await fireEvent.press(getByText('Buscar'));

    await waitFor(() => expect(mockPush).toHaveBeenCalledWith('/(app)/space/ABC'));
  });

  it('shows a not-found message on 404', async () => {
    jest.spyOn(spacesApi, 'searchSpace').mockRejectedValue(new ApiError(404, 'Espacio no encontrado.'));

    const { getByTestId, getByText, findByText } = await render(<HomeScreen />);
    await fireEvent.changeText(getByTestId('code'), 'NOPE');
    await fireEvent.press(getByText('Buscar'));

    expect(await findByText(/no encontrado/i)).toBeTruthy();
  });
});
