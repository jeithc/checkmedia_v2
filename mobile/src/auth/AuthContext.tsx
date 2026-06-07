import React, { createContext, useContext, useEffect, useState, useCallback } from 'react';
import * as Device from 'expo-device';
import * as authApi from '../api/auth';
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
    (async () => {
      const stored = await loadSession();
      if (stored) {
        setToken(stored.token);
        setUser(stored.user);
        setStatus('authenticated');
      } else {
        setStatus('unauthenticated');
      }
    })();
  }, []);

  const signIn = useCallback(async (username: string, password: string) => {
    const deviceName = (Device.deviceName ?? 'mobile').slice(0, 60);
    const res = await authApi.login({ username, password, deviceName });
    await saveSession({ token: res.token, user: res.user });
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
