import React from 'react';
import { render, fireEvent, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import AuditFormScreen from '../../app/(app)/audit/new';
import * as criteriaApi from '../../src/api/criteria';
import * as auditsApi from '../../src/api/audits';
import * as AuthCtx from '../../src/auth/AuthContext';

const mockPush = jest.fn();
const mockBack = jest.fn();
jest.mock('expo-router', () => ({
  router: { push: (...a: unknown[]) => mockPush(...a), back: () => mockBack() },
  useLocalSearchParams: () => ({ spaceId: '5', code: 'ABC' }),
}));

jest.mock('../../src/photos/capture', () => ({
  capturePhoto: jest.fn(async () => ({ uri: 'file://x.jpg', name: 'x.jpg', type: 'image/jpeg' })),
}));

function wrap(ui: React.ReactElement) {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(<QueryClientProvider client={qc}>{ui}</QueryClientProvider>);
}

beforeEach(() => {
  mockPush.mockClear();
  jest.spyOn(AuthCtx, 'useAuth').mockReturnValue({
    status: 'authenticated', token: 'tok', user: { id: 1, name: 'A', username: 'a' },
    permissions: {
      can_audit: true, can_audit_structural: false, can_select_audit_type: false,
      can_select_purpose: false, can_do_preventive: false, is_admin: false,
    },
    signIn: jest.fn(), signOut: jest.fn(),
  });
  jest.spyOn(criteriaApi, 'listCriteria').mockResolvedValue([
    { id: 7, name: 'Estado', key: 'state' },
  ]);
});
afterEach(() => jest.restoreAllMocks());

it('blocks submit without a photo and shows a validation error', async () => {
  const { findByText, getByText } = await wrap(<AuditFormScreen />);
  await findByText('Estado');
  await fireEvent.press(getByText('Guardar auditoría'));
  expect(await findByText(/al menos una foto/i)).toBeTruthy();
});

it('submits successfully after taking a photo', async () => {
  const submit = jest.spyOn(auditsApi, 'submitAudit').mockResolvedValue({
    id: 42, client_uuid: 'u', advertising_space_id: 5, year: 2026, week: 23,
    audit_type: 'general', general_status: 'good', audit_date: null,
  });

  const { findByText, getByText } = await wrap(<AuditFormScreen />);
  await findByText('Estado');
  await fireEvent.press(getByText('Tomar foto'));
  await waitFor(() => expect(getByText(/1 foto/i)).toBeTruthy());
  await fireEvent.press(getByText('Guardar auditoría'));

  await waitFor(() => expect(submit).toHaveBeenCalled());
  await waitFor(() => expect(getByText(/guardada/i)).toBeTruthy());
});
