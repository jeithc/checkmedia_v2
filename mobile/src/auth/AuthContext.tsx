import React, { createContext, useContext, useEffect, useState, useCallback } from 'react';
import * as Device from 'expo-device';
import * as authApi from '../api/auth';
import { ApiError } from '../api/errors';
import type { ApiUser, PermissionFlags } from '../api/types';
import { saveSession, loadSession, clearSession, type StoredSession } from './tokenStore';
import { biometricsAvailable, unlockWithBiometrics } from './biometrics';

// 'locked' = a stored session exists but the device requires a biometric
// unlock before exposing it (session reopen). 'unauthenticated' = no session.
type Status = 'loading' | 'locked' | 'unauthenticated' | 'authenticated';

interface AuthState {
  status: Status;
  token: string | null;
  user: ApiUser | null;
  permissions: PermissionFlags | null;
  signIn: (username: string, password: string) => Promise<void>;
  /** Full sign-out: revokes the token server-side and forgets the session. */
  signOut: () => Promise<void>;
  /** Lock the app: keep the stored session but require biometric/password to re-enter. */
  lock: () => Promise<void>;
  /** Prompt biometric unlock for the stored session. Returns true on success. */
  unlock: () => Promise<boolean>;
}

const AuthContext = createContext<AuthState | undefined>(undefined);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [status, setStatus] = useState<Status>('loading');
  const [token, setToken] = useState<string | null>(null);
  const [user, setUser] = useState<ApiUser | null>(null);
  const [permissions, setPermissions] = useState<PermissionFlags | null>(null);
  // Held in memory while status === 'locked'; not exposed until unlocked.
  const [lockedSession, setLockedSession] = useState<StoredSession | null>(null);

  const applyAuthenticated = useCallback((s: StoredSession) => {
    setToken(s.token);
    setUser(s.user);
    setPermissions(s.permissions);
    setStatus('authenticated');
  }, []);

  // Best-effort server refresh of permissions. A 401 means the token is dead
  // -> sign out. Network errors are ignored (keep working offline).
  const refreshMe = useCallback(async (tok: string) => {
    try {
      const fresh = await authApi.me(tok);
      setUser(fresh.user);
      setPermissions(fresh.permissions);
      await saveSession({ token: tok, user: fresh.user, permissions: fresh.permissions });
    } catch (e) {
      if (e instanceof ApiError && e.isUnauthorized) {
        await clearSession();
        setToken(null);
        setUser(null);
        setPermissions(null);
        setLockedSession(null);
        setStatus('unauthenticated');
      }
    }
  }, []);

  useEffect(() => {
    let mounted = true;
    (async () => {
      const stored = await loadSession();
      if (!stored) {
        if (mounted) setStatus('unauthenticated');
        return;
      }
      const bio = await biometricsAvailable();
      if (!mounted) return;
      if (bio) {
        // Require biometric unlock before exposing the session.
        setLockedSession(stored);
        setStatus('locked');
      } else {
        // No biometric hardware/enrollment (e.g. simulator): can't gate, so
        // restore directly and rehydrate permissions.
        applyAuthenticated(stored);
        refreshMe(stored.token);
      }
    })();
    return () => {
      mounted = false;
    };
  }, [applyAuthenticated, refreshMe]);

  const unlock = useCallback(async (): Promise<boolean> => {
    const stored = lockedSession ?? (await loadSession());
    if (!stored) {
      setStatus('unauthenticated');
      return false;
    }
    const ok = await unlockWithBiometrics();
    if (!ok) return false;
    applyAuthenticated(stored);
    setLockedSession(null);
    refreshMe(stored.token);
    return true;
  }, [lockedSession, applyAuthenticated, refreshMe]);

  const lock = useCallback(async () => {
    // Keep the token in storage; just hide it behind the lock screen. The
    // login screen will offer biometric unlock (or password) to re-enter.
    const stored = await loadSession();
    if (!stored) {
      setStatus('unauthenticated');
      return;
    }
    setLockedSession(stored);
    setToken(null);
    setUser(null);
    setPermissions(null);
    setStatus('locked');
  }, []);

  const signIn = useCallback(
    async (username: string, password: string) => {
      const deviceName = (Device.deviceName ?? 'mobile').slice(0, 60);
      const res = await authApi.login({ username, password, deviceName });
      await saveSession({ token: res.token, user: res.user, permissions: res.permissions });
      setLockedSession(null);
      applyAuthenticated({ token: res.token, user: res.user, permissions: res.permissions });
    },
    [applyAuthenticated],
  );

  const signOut = useCallback(async () => {
    if (token) {
      try {
        await authApi.logout(token);
      } catch {
        // ignore network errors on logout
      }
    }
    await clearSession();
    setToken(null);
    setUser(null);
    setPermissions(null);
    setLockedSession(null);
    setStatus('unauthenticated');
  }, [token]);

  return (
    <AuthContext.Provider value={{ status, token, user, permissions, signIn, signOut, lock, unlock }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthState {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}
