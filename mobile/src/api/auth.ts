import * as client from './client';
import type { LoginResponse, ApiUser, PermissionFlags } from './types';

export function login(input: { username: string; password: string; deviceName: string }) {
  return client.apiFetch<LoginResponse>('/login', {
    method: 'POST',
    json: { username: input.username, password: input.password, device_name: input.deviceName },
  });
}

export function me(token: string) {
  return client.apiFetch<{ user: ApiUser; permissions: PermissionFlags }>('/me', { token });
}

export function logout(token: string) {
  return client.apiFetch<{ ok: boolean }>('/logout', { method: 'POST', token });
}
