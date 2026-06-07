import React from 'react';
import { render, waitFor, fireEvent } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import SpaceScreen from '../../app/(app)/space/[code]';
import * as spacesApi from '../../src/api/spaces';
import * as AuthCtx from '../../src/auth/AuthContext';

const mockPush = jest.fn();
const mockBack = jest.fn();
jest.mock('expo-router', () => ({
  router: { push: (...a: unknown[]) => mockPush(...a), back: () => mockBack() },
  useLocalSearchParams: () => ({ code: 'ABC' }),
}));

function wrap(ui: React.ReactElement) {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(<QueryClientProvider client={qc}>{ui}</QueryClientProvider>);
}

beforeEach(() => {
  mockPush.mockClear();
  mockBack.mockClear();
  jest.spyOn(AuthCtx, 'useAuth').mockReturnValue({
    status: 'authenticated', token: 'tok', user: { id: 1, name: 'A', username: 'a' },
    permissions: {
      can_audit: true, can_audit_structural: false, can_select_audit_type: false,
      can_select_purpose: false, can_do_preventive: false, is_admin: false,
    },
    signIn: jest.fn(), signOut: jest.fn(),
  });
});
afterEach(() => jest.restoreAllMocks());

it('renders the space and a duplicate warning', async () => {
  jest.spyOn(spacesApi, 'searchSpace').mockResolvedValue({
    id: 3, external_code: 'ABC', type: 'Billboard', duplicate: true, existing_audit_id: 99,
    booking: { id: 1, client_name: 'ACME', contract_code: 'C1', product_name: 'P' },
  });

  const { findByText } = await wrap(<SpaceScreen />);
  expect(await findByText('ABC')).toBeTruthy();
  expect(await findByText(/ACME/)).toBeTruthy();
  expect(await findByText(/ya tiene una auditoría/i)).toBeTruthy();
});

it('navigates to the audit form on Auditar', async () => {
  jest.spyOn(spacesApi, 'searchSpace').mockResolvedValue({
    id: 3, external_code: 'ABC', type: 'Billboard', duplicate: false, existing_audit_id: null, booking: null,
  });

  const { findByText } = await wrap(<SpaceScreen />);
  await fireEvent.press(await findByText('Auditar'));
  await waitFor(() => expect(mockPush).toHaveBeenCalledWith('/(app)/audit/new?spaceId=3&code=ABC'));
});

it('offers Ver/Complementar/Cancelar when the space already has an audit', async () => {
  jest.spyOn(spacesApi, 'searchSpace').mockResolvedValue({
    id: 3, external_code: 'ABC', type: 'Billboard', duplicate: true, existing_audit_id: 99, booking: null,
  });

  const { findByText } = await wrap(<SpaceScreen />);

  await fireEvent.press(await findByText('Ver auditoría'));
  expect(mockPush).toHaveBeenCalledWith('/(app)/audit/99');

  await fireEvent.press(await findByText('Complementar'));
  expect(mockPush).toHaveBeenCalledWith(
    '/(app)/audit/new?spaceId=3&code=ABC&mode=complement&auditId=99',
  );

  await fireEvent.press(await findByText('Cancelar'));
  expect(mockBack).toHaveBeenCalled();
});
