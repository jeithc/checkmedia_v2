import React, { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react';
import { AppState } from 'react-native';
import NetInfo from '@react-native-community/netinfo';
import { getDb } from '../db';
import * as repo from './queueRepo';
import { runSync } from './syncEngine';
import { submissionToOutcome } from './submitAdapter';
import { deletePhotos } from './photoStore';
import type { Submission } from './types';

interface SyncCtx {
  pendingCount: number;
  syncing: boolean;
  items: Submission[];
  sync: () => Promise<void>;
  refresh: () => Promise<void>;
}

const Ctx = createContext<SyncCtx | null>(null);

export function useSync(): SyncCtx {
  const v = useContext(Ctx);
  if (!v) throw new Error('useSync must be used within SyncProvider');
  return v;
}

export function SyncProvider({ token, children }: { token: string | null; children: React.ReactNode }) {
  const [pendingCount, setPendingCount] = useState(0);
  const [items, setItems] = useState<Submission[]>([]);
  const [syncing, setSyncing] = useState(false);
  const tokenRef = useRef(token);
  tokenRef.current = token;

  const refresh = useCallback(async () => {
    const db = await getDb();
    setItems(await repo.listAll(db));
    setPendingCount(await repo.pendingCount(db));
  }, []);

  const sync = useCallback(async () => {
    const tok = tokenRef.current;
    if (!tok) return;
    setSyncing(true);
    try {
      const db = await getDb();
      await runSync({
        now: () => Date.now(),
        listClaimable: () => repo.listClaimable(db),
        markUploading: (id, attempts) => repo.markUploading(db, id, attempts),
        markSynced: async (id, sid) => {
          const photos = await repo.photosFor(db, id);
          await repo.markSynced(db, id, sid);
          await deletePhotos(photos.map((p) => p.localUri));
        },
        markConflict: (id, sid, msg) => repo.markConflict(db, id, sid, msg),
        markPermanent: (id, msg) => repo.markPermanent(db, id, msg),
        markTransient: (id, msg, attempts, next) => repo.markTransient(db, id, msg, attempts, next),
        submit: (s) => submissionToOutcome(s, tok),
      });
    } finally {
      setSyncing(false);
      await refresh();
    }
  }, [refresh]);

  // Initial load + sync on mount.
  useEffect(() => { refresh(); sync(); }, [refresh, sync]);

  // Trigger: connectivity offline -> online.
  useEffect(() => {
    const unsub = NetInfo.addEventListener((state) => {
      if (state.isConnected) sync();
    });
    return () => unsub();
  }, [sync]);

  // Trigger: app returns to foreground.
  useEffect(() => {
    const subAS = AppState.addEventListener('change', (s) => {
      if (s === 'active') sync();
    });
    return () => subAS.remove();
  }, [sync]);

  return (
    <Ctx.Provider value={{ pendingCount, syncing, items, sync, refresh }}>
      {children}
    </Ctx.Provider>
  );
}
