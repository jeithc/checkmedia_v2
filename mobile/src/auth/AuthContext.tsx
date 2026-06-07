import React, { createContext, useContext, useEffect, useState, useCallback } from 'react';
import * as Device from 'expo-device';
import * as authApi from '../api/auth';
import { ApiError } from '../api/errors';
import type { ApiUser, PermissionFlags } from '../api/types';
import { saveSession, loadSession, clearSession } from './tokenStore';

type Status = 'loading' | 'unauthenticated' | 'authenticated';

interface AuthState {
  status: Status;
  token: string | null;
  user: ApiUser | null;
  permissions: PermissionFlags | null;
  signIn: (username: string, password: string) => Promise<void>;
  signOut: () => Promise<void>;
}

const AuthContext = createContext<AuthState | undefined>(undefined);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [status, setStatus] = useState<Status>('loading');
  const [token, setToken] = useState<string | null>(null);
  const [user, setUser] = useState<ApiUser | null>(null);
  const [permissions, setPermissions] = useState<PermissionFlags | null>(null);

  useEffect(() => {
    let mounted = true;
    (async () => {
      const stored = await loadSession();
      if (!stored) {
        if (mounted) setStatus('unauthenticated');
        return;
      }
      // Restore immediately from the stored session so the app is usable
      // offline and audit_type resolves correctly for non-general auditors.
      if (mounted) {
        setToken(stored.token);
        setUser(stored.user);
        setPermissions(stored.permissions);
        setStatus('authenticated');
      }
      // Then refresh from the server in case permissions changed. A 401 means
      // the token is no longer valid -> sign out. Network errors are ignored
      // (we keep the restored session so the auditor can keep working).
      try {
        const fresh = await authApi.me(stored.token);
        if (!mounted) return;
        setUser(fresh.user);
        setPermissions(fresh.permissions);
        await saveSession({ token: stored.token, user: fresh.user, permissions: fresh.permissions });
      } catch (e) {
        if (mounted && e instanceof ApiError && e.isUnauthorized) {
          await clearSession();
          setToken(null);
          setUser(null);
          setPermissions(null);
          setStatus('unauthenticated');
        }
      }
    })();
    return () => {
      mounted = false;
    };
  }, []);

  const signIn = useCallback(async (username: string, password: string) => {
    const deviceName = (Device.deviceName ?? 'mobile').slice(0, 60);
    const res = await authApi.login({ username, password, deviceName });
    await saveSession({ token: res.token, user: res.user, permissions: res.permissions });
    setToken(res.token);
    setUser(res.user);
    setPermissions(res.permissions);
    setStatus('authenticated');
  }, []);

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
    setStatus('unauthenticated');
  }, [token]);

  return (
    <AuthContext.Provider value={{ status, token, user, permissions, signIn, signOut }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthState {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}
