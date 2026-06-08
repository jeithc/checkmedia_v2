import React from 'react';
import { render, waitFor } from '@testing-library/react-native';
import { Text } from 'react-native';
import { SyncProvider, useSync } from '../../src/offline/SyncProvider';
import * as repo from '../../src/offline/queueRepo';
import * as engine from '../../src/offline/syncEngine';
import * as db from '../../src/db';

jest.mock('expo-sqlite', () => ({ openDatabaseAsync: jest.fn(async () => ({})) }));
jest.mock('../../src/db');
jest.spyOn(db, 'getDb').mockResolvedValue({} as any);

function Probe() {
  const { pendingCount } = useSync();
  return <Text testID="count">{pendingCount}</Text>;
}

describe('SyncProvider', () => {
  afterEach(() => jest.restoreAllMocks());

  it('exposes pendingCount and triggers a sync on mount', async () => {
    jest.spyOn(repo, 'pendingCount').mockResolvedValue(3);
    jest.spyOn(repo, 'listAll').mockResolvedValue([]);
    const runSpy = jest.spyOn(engine, 'runSync').mockResolvedValue({ synced: 0, conflict: 0, permanent: 0, transient: 0, skipped: 0 });

    const { getByTestId } = await render(
      <SyncProvider token="tok"><Probe /></SyncProvider>,
    );

    await waitFor(() => expect(getByTestId('count').props.children).toBe(3));
    await waitFor(() => expect(runSpy).toHaveBeenCalled());
  });
});
