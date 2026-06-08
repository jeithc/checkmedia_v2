import React from 'react';
import { render } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import AuditDetailScreen from '../../app/(app)/audit/[id]';
import * as auditsApi from '../../src/api/audits';
import * as AuthCtx from '../../src/auth/AuthContext';

jest.mock('expo-router', () => ({
  useLocalSearchParams: () => ({ id: '99' }),
}));

function wrap(ui: React.ReactElement) {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(<QueryClientProvider client={qc}>{ui}</QueryClientProvider>);
}

beforeEach(() => {
  jest.spyOn(AuthCtx, 'useAuth').mockReturnValue({
    status: 'authenticated', token: 'tok', user: { id: 1, name: 'A', username: 'a' },
    permissions: {
      can_audit: true, can_audit_structural: false, can_select_audit_type: false,
      can_select_purpose: false, can_do_preventive: false, is_admin: false,
    },
    signIn: jest.fn(), signOut: jest.fn(), unlock: jest.fn(),
  });
});
afterEach(() => jest.restoreAllMocks());

it('renders the existing audit values, observation and photo count', async () => {
  jest.spyOn(auditsApi, 'getAudit').mockResolvedValue({
    id: 99, advertising_space_id: 3, year: 2026, week: 23,
    audit_type: 'general', audit_purpose: 'audit_only', general_status: 'bad',
    observation: 'nota previa', audit_date: null, has_open_maintenance: false,
    values: [{ criterion_id: 7, name: 'Ambiental', key: 'amb', value: 'bad', comment: 'roto' }],
    photos: [{ id: 1, url: 'https://x/1.jpg' }, { id: 2, url: 'https://x/2.jpg' }],
  });

  const { findByText } = await wrap(<AuditDetailScreen />);
  expect(await findByText('Ambiental')).toBeTruthy();
  expect(await findByText('roto')).toBeTruthy();
  expect(await findByText('nota previa')).toBeTruthy();
  expect(await findByText(/Fotos \(2\)/)).toBeTruthy();
});
