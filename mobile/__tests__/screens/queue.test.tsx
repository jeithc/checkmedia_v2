import React from 'react';
import { render } from '@testing-library/react-native';
import QueueScreen from '../../app/(app)/queue';
import * as SyncCtx from '../../src/offline/SyncProvider';

jest.mock('expo-router', () => ({
  router: { push: jest.fn(), back: jest.fn() },
}));

jest.mock('../../src/db', () => ({ getDb: jest.fn(async () => ({})) }));
jest.mock('../../src/offline/queueRepo', () => ({ requeue: jest.fn(async () => {}) }));

it('renders one row per submission with its status', async () => {
  jest.spyOn(SyncCtx, 'useSync').mockReturnValue({
    pendingCount: 2, syncing: false, sync: jest.fn(), refresh: jest.fn(),
    items: [
      { id: 1, externalCode: '770', status: 'queued', photos: [{}], attempts: 0, lastError: null } as any,
      { id: 2, externalCode: '881', status: 'failed', photos: [{}, {}], attempts: 2, lastError: 'sin red' } as any,
    ],
  });
  const { getByText } = await render(<QueueScreen />);
  expect(getByText('770')).toBeTruthy();
  expect(getByText(/sin red/)).toBeTruthy();
});
