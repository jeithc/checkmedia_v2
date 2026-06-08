import React from 'react';
import { render, fireEvent, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import AuditFormScreen from '../../app/(app)/audit/new';
import * as criteriaApi from '../../src/api/criteria';
import * as auditsApi from '../../src/api/audits';
import * as AuthCtx from '../../src/auth/AuthContext';
import * as repo from '../../src/offline/queueRepo';
import * as photoStore from '../../src/offline/photoStore';
import * as SyncCtx from '../../src/offline/SyncProvider';
import * as dbMod from '../../src/db';

const mockPush = jest.fn();
const mockBack = jest.fn();
jest.mock('expo-router', () => ({
  router: { push: (...a: unknown[]) => mockPush(...a), back: () => mockBack() },
  useLocalSearchParams: () => ({ spaceId: '5', code: 'ABC' }),
}));

jest.mock('expo-sqlite', () => ({ openDatabaseAsync: jest.fn(async () => ({})) }));

jest.mock('../../src/photos/capture', () => ({
  capturePhoto: jest.fn(async () => ({ uri: 'file://x.jpg', name: 'x.jpg', type: 'image/jpeg' })),
}));

function wrap(ui: React.ReactElement) {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(<QueryClientProvider client={qc}>{ui}</QueryClientProvider>);
}

beforeEach(() => {
  mockPush.mockClear();
  jest.spyOn(dbMod, 'getDb').mockResolvedValue({} as any);
  jest.spyOn(SyncCtx, 'useSync').mockReturnValue({
    pendingCount: 0, syncing: false, items: [], sync: jest.fn(), refresh: jest.fn(),
  });
  jest.spyOn(AuthCtx, 'useAuth').mockReturnValue({
    status: 'authenticated', token: 'tok', user: { id: 1, name: 'A', username: 'a' },
    permissions: {
      can_audit: true, can_audit_structural: false, can_select_audit_type: false,
      can_select_purpose: false, can_do_preventive: false, is_admin: false,
    },
    signIn: jest.fn(), signOut: jest.fn(), unlock: jest.fn(),
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

it('enqueues successfully after taking a photo', async () => {
  const enqueue = jest.spyOn(repo, 'enqueue').mockResolvedValue(1);
  jest.spyOn(photoStore, 'persistPhoto').mockResolvedValue('file:///doc/audit-photos/u-0.jpg');
  const submit = jest.spyOn(auditsApi, 'submitAudit');

  const { findByText, getByText } = await wrap(<AuditFormScreen />);
  await findByText('Estado');
  await fireEvent.press(getByText('Tomar foto'));
  await waitFor(() => expect(getByText(/1 foto/i)).toBeTruthy());
  await fireEvent.press(getByText('Guardar auditoría'));

  await waitFor(() => expect(enqueue).toHaveBeenCalled());
  expect(submit).not.toHaveBeenCalled();
  await waitFor(() => expect(getByText(/guardada/i)).toBeTruthy());
});
