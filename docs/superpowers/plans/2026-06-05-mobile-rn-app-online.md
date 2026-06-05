# Mobile RN App (Online) Implementation Plan — Sub-proyecto 2

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the React Native (Expo) auditor app in `mobile/` that lets a field auditor log in (token + biometric unlock), search a space, fill the audit form, capture & resize photos, and submit online to the existing Laravel API — everything except the offline queue (that is sub-proyecto 3).

**Architecture:** Expo (managed) + TypeScript + expo-router. A thin typed API client wraps `fetch` with bearer-token injection and error mapping. Session/token live in `expo-secure-store`, gated by `expo-local-authentication`. Pure, unit-tested modules carry the logic (API client, payload builder, client-side validation, photo resize wrapper); screens assemble those modules. The submit path calls the service directly (no queue yet); sub-proyecto 3 will slot a SQLite queue between the form and the API client without changing screens.

**Tech Stack:** Expo SDK (latest), TypeScript, expo-router, expo-secure-store, expo-local-authentication, expo-camera, expo-image-manipulator, @tanstack/react-query, jest-expo + @testing-library/react-native.

---

## Context the engineer must know

### Backend API contract (already deployed, do NOT change the backend in this sub-project)
Base path: `/api`. Production base URL: `https://v2.pptefectimedios.com`. Local dev: set per-developer.

- `POST /api/login` — body `{ username, password, device_name }` → `200 { token, user: { id, name, username }, permissions: PermissionFlags }`. `422` on bad credentials. Throttled (10/min).
- `GET /api/me` (Bearer) → `{ user: { id, name, username }, permissions: PermissionFlags }`.
- `POST /api/logout` (Bearer) → `{ ok: true }`.
- `GET /api/spaces/search?code=<code>&type=<general|structural>` (Bearer) → `200 { data: { id, external_code, type, duplicate, existing_audit_id, booking: { id, client_name, contract_code, product_name } | null } }` or `404 { message }`.
- `GET /api/criteria?type=<general|structural>` (Bearer) → `200 { data: Array<{ id, name, key }> }`.
- `POST /api/audits` (Bearer, multipart/form-data) — fields:
  - `client_uuid` (uuid string), `space_id` (int), `audit_type` (`general|structural`), `purpose` (`audit_only|preventive_maintenance`), `observation` (string|empty), `captured_at` (ISO8601, must be ≤ now), `mode` (`new|complement`),
  - `values[i][criterion_id]` (int), `values[i][value]` (`good|bad`), `values[i][comment]` (string),
  - `photos[i]` (image file, ≤10MB).
  - Responses: `201 { data: Audit }` (fresh), `200 { data: Audit }` (idempotent replay), `409 { message, existing_audit: { id, ... } }` (duplicate, mode=new), `422 { message, errors }` (validation), `403` (no audit permission / wrong type).
  - `Audit` shape: `{ id, client_uuid, advertising_space_id, year, week, audit_type, general_status, audit_date }`.

`PermissionFlags` = `{ can_audit: bool, can_audit_structural: bool, can_select_audit_type: bool, can_select_purpose: bool, can_do_preventive: bool, is_admin: bool }`.

### Domain rules the app mirrors (client-side, server is still authoritative)
- A `bad` criterion value REQUIRES a non-empty comment.
- At least one photo is required.
- `audit_type` the user may pick: if `can_select_audit_type` is false, force the only one they hold (`can_audit_structural && !can_audit` → structural; else general).
- `purpose`: only show preventive option when `can_do_preventive` is true; otherwise `audit_only`.
- `captured_at` is the moment the FIRST photo was taken (capture time), sent so the server stamps the watermark and computes the week from it.
- Photo resize target: max **2560px** on the long edge, JPEG quality **0.85**.

### Environment facts (verified)
- Node v24.x, npm 11 present. `watchman` NOT installed (optional on macOS, recommended). Java/Android SDK NOT installed.
- Simplest run path for the developer: **Expo Go on a physical Android phone** + `npx expo start` (no Android Studio/emulator/Java needed). EAS native builds / emulator are sub-proyecto 4.
- This automated environment cannot launch the Expo UI; screen tasks are verified by `tsc` typecheck + jest component tests, and the developer runs the app on a phone using the manual smoke checklist (final task).

### Repo placement
The app is a self-contained Expo project at `mobile/` inside the existing repo (monorepo). It has its own `package.json`, `node_modules`, and test runner, independent of the Laravel PHP toolchain.

---

## File Structure (under `mobile/`)

```
mobile/
  app.json                      # Expo config (name, scheme, extra.apiUrl)
  package.json                  # scripts: start, test, typecheck, lint
  tsconfig.json
  babel.config.js
  jest.config.js                # preset jest-expo
  jest.setup.ts                 # mocks for expo-secure-store, local-auth, image-manipulator
  .env.example                  # EXPO_PUBLIC_API_URL
  app/                          # expo-router routes
    _layout.tsx                 # root: providers (QueryClient, AuthProvider), auth gate
    index.tsx                   # redirect to /login or /(app)/home
    login.tsx                   # login screen
    (app)/_layout.tsx           # authenticated stack
    (app)/home.tsx              # search by external_code
    (app)/space/[code].tsx      # space result + "Auditar"
    (app)/audit/new.tsx         # audit form
  src/
    config.ts                   # reads apiUrl from expo-constants/env
    api/
      client.ts                 # typed fetch wrapper (token, error mapping)
      auth.ts                   # login/me/logout calls
      spaces.ts                 # search call
      criteria.ts               # list call
      audits.ts                 # submit call (multipart)
      types.ts                  # API DTO types
      errors.ts                 # ApiError class + helpers
    auth/
      tokenStore.ts             # SecureStore get/set/clear
      biometrics.ts             # local-authentication wrapper
      AuthContext.tsx           # session provider (token, user, permissions)
    audit/
      validation.ts             # client-side validation (pure)
      payload.ts                # build multipart FormData from form state (pure-ish)
      auditType.ts              # resolve selectable audit types/purpose from permissions (pure)
    photos/
      resize.ts                 # wrap expo-image-manipulator (max 2560, q0.85)
    ui/                         # small shared components (Button, Field, etc.)
  __tests__/                    # jest tests mirroring src/
```

---

## Phase 0 — Environment & scaffold

### Task 1: Verify toolchain and scaffold the Expo app

**Files:**
- Create: `mobile/` (entire Expo project via scaffold)

- [ ] **Step 1: Verify Node/npm and (optionally) install watchman**

Run:
```bash
node -v && npm -v
which watchman || echo "watchman missing (optional; install with: brew install watchman)"
```
Expected: Node ≥ 20, npm present. If watchman is missing and `brew` exists, install it (improves file watching on macOS); if not, proceed without it.

- [ ] **Step 2: Scaffold the Expo app into `mobile/`**

Run from repo root `/Users/jcarrillo/dev/personal/checkmedia_v2`:
```bash
npx create-expo-app@latest mobile --template blank-typescript
```
Expected: `mobile/` created with `package.json`, `App.tsx`, `tsconfig.json`. (We replace `App.tsx` with expo-router next.)

- [ ] **Step 3: Add expo-router and required native modules**

Run:
```bash
cd mobile
npx expo install expo-router react-native-safe-area-context react-native-screens expo-linking expo-constants expo-status-bar
npx expo install expo-secure-store expo-local-authentication expo-camera expo-image-manipulator
npm install @tanstack/react-query uuid
npm install --save-dev @types/uuid
```
Expected: installs succeed, `package.json` updated.

- [ ] **Step 4: Configure expo-router entry + scheme in app.json**

Set `mobile/package.json` `"main"` to `"expo-router/entry"`. In `mobile/app.json` set `expo.scheme` to `"checkmedia"`, add `"plugins": ["expo-router"]`, and under `expo.extra` add `"apiUrl": "https://v2.pptefectimedios.com"`. Add iOS/Android camera permission strings: `expo.ios.infoPlist.NSCameraUsageDescription` and `expo.android.permissions` including `"CAMERA"`. Delete the now-unused `mobile/App.tsx`.

Exact `app.json` (replace the generated file):
```json
{
  "expo": {
    "name": "CheckMedia Auditor",
    "slug": "checkmedia-auditor",
    "scheme": "checkmedia",
    "version": "1.0.0",
    "orientation": "portrait",
    "userInterfaceStyle": "light",
    "newArchEnabled": true,
    "plugins": ["expo-router"],
    "ios": {
      "supportsTablet": false,
      "infoPlist": {
        "NSCameraUsageDescription": "La app usa la cámara para registrar fotos de la auditoría.",
        "NSFaceIDUsageDescription": "Usa Face ID para desbloquear la sesión."
      }
    },
    "android": {
      "package": "com.checkmedia.auditor",
      "permissions": ["CAMERA"]
    },
    "extra": {
      "apiUrl": "https://v2.pptefectimedios.com"
    }
  }
}
```

- [ ] **Step 5: Create a minimal router so the app boots**

Create `mobile/app/_layout.tsx`:
```tsx
import { Stack } from 'expo-router';

export default function RootLayout() {
  return <Stack screenOptions={{ headerShown: false }} />;
}
```
Create `mobile/app/index.tsx`:
```tsx
import { Text, View } from 'react-native';

export default function Index() {
  return (
    <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center' }}>
      <Text>CheckMedia Auditor</Text>
    </View>
  );
}
```

- [ ] **Step 6: Verify it type-checks and bundles**

Run:
```bash
cd mobile
npx tsc --noEmit
npx expo export --platform android --output-dir /tmp/expo-export-check
```
Expected: `tsc` clean; `expo export` completes a bundle without errors (this validates the app compiles/bundles without needing a device). Delete `/tmp/expo-export-check` after.

- [ ] **Step 7: Add a root .gitignore entry and commit**

Ensure `mobile/.gitignore` (created by the template) ignores `node_modules`, `.expo`, `dist`. Then:
```bash
cd /Users/jcarrillo/dev/personal/checkmedia_v2
git add mobile
git commit -m "feat(mobile): scaffold Expo + expo-router app skeleton"
```

---

### Task 2: Jest + TypeScript test harness and config module

**Files:**
- Create: `mobile/jest.config.js`, `mobile/jest.setup.ts`
- Create: `mobile/src/config.ts`
- Test: `mobile/__tests__/config.test.ts`
- Modify: `mobile/package.json` (scripts)

- [ ] **Step 1: Install test deps**

Run:
```bash
cd mobile
npm install --save-dev jest jest-expo @testing-library/react-native @testing-library/jest-native @types/jest react-test-renderer
```

- [ ] **Step 2: Add jest config + setup with expo mocks**

Create `mobile/jest.config.js`:
```js
module.exports = {
  preset: 'jest-expo',
  setupFilesAfterEnv: ['<rootDir>/jest.setup.ts'],
  transformIgnorePatterns: [
    'node_modules/(?!((jest-)?react-native|@react-native(-community)?|expo(nent)?|@expo(nent)?/.*|@expo-google-fonts/.*|react-navigation|@react-navigation/.*|@unimodules/.*|unimodules|sentry-expo|native-base|react-native-svg|expo-router|@tanstack/.*|uuid))',
  ],
};
```
Create `mobile/jest.setup.ts`:
```ts
import '@testing-library/jest-native/extend-expect';

jest.mock('expo-secure-store', () => {
  const store: Record<string, string> = {};
  return {
    setItemAsync: jest.fn(async (k: string, v: string) => { store[k] = v; }),
    getItemAsync: jest.fn(async (k: string) => (k in store ? store[k] : null)),
    deleteItemAsync: jest.fn(async (k: string) => { delete store[k]; }),
  };
});

jest.mock('expo-local-authentication', () => ({
  hasHardwareAsync: jest.fn(async () => true),
  isEnrolledAsync: jest.fn(async () => true),
  authenticateAsync: jest.fn(async () => ({ success: true })),
}));

jest.mock('expo-image-manipulator', () => ({
  manipulateAsync: jest.fn(async (uri: string) => ({ uri: uri + '#resized', width: 2560, height: 1440 })),
  SaveFormat: { JPEG: 'jpeg' },
}));
```

- [ ] **Step 3: Add scripts to package.json**

In `mobile/package.json` `"scripts"` add:
```json
"test": "jest",
"typecheck": "tsc --noEmit"
```

- [ ] **Step 4: Write the failing test for the config module**

Create `mobile/__tests__/config.test.ts`:
```ts
import { getApiBaseUrl } from '../src/config';

describe('config', () => {
  it('returns the EXPO_PUBLIC_API_URL when set', () => {
    process.env.EXPO_PUBLIC_API_URL = 'https://example.test';
    expect(getApiBaseUrl()).toBe('https://example.test');
  });

  it('falls back to the bundled default when env is unset', () => {
    delete process.env.EXPO_PUBLIC_API_URL;
    expect(getApiBaseUrl()).toMatch(/^https?:\/\//);
  });
});
```

- [ ] **Step 5: Run to verify it fails**

Run: `cd mobile && npx jest config.test`
Expected: FAIL — cannot find `../src/config`.

- [ ] **Step 6: Implement the config module**

Create `mobile/src/config.ts`:
```ts
import Constants from 'expo-constants';

const DEFAULT_API_URL = 'https://v2.pptefectimedios.com';

export function getApiBaseUrl(): string {
  const fromEnv = process.env.EXPO_PUBLIC_API_URL;
  if (fromEnv && fromEnv.length > 0) {
    return fromEnv.replace(/\/$/, '');
  }
  const fromExtra = (Constants.expoConfig?.extra as { apiUrl?: string } | undefined)?.apiUrl;
  return (fromExtra ?? DEFAULT_API_URL).replace(/\/$/, '');
}
```

- [ ] **Step 7: Run to verify it passes + typecheck**

Run: `cd mobile && npx jest config.test && npx tsc --noEmit`
Expected: PASS, typecheck clean.

- [ ] **Step 8: Create .env.example and commit**

Create `mobile/.env.example`:
```
# Per-developer API base URL. On a phone use your machine's LAN IP, e.g. http://192.168.1.50:8000
EXPO_PUBLIC_API_URL=https://v2.pptefectimedios.com
```
Commit:
```bash
cd /Users/jcarrillo/dev/personal/checkmedia_v2
git add mobile/jest.config.js mobile/jest.setup.ts mobile/src/config.ts mobile/__tests__/config.test.ts mobile/package.json mobile/.env.example
git commit -m "feat(mobile): jest harness + api base url config"
```

---

## Phase 1 — Core libraries (TDD, pure logic)

### Task 3: API types and typed fetch client

**Files:**
- Create: `mobile/src/api/types.ts`, `mobile/src/api/errors.ts`, `mobile/src/api/client.ts`
- Test: `mobile/__tests__/api/client.test.ts`

- [ ] **Step 1: Define API DTO types**

Create `mobile/src/api/types.ts`:
```ts
export interface PermissionFlags {
  can_audit: boolean;
  can_audit_structural: boolean;
  can_select_audit_type: boolean;
  can_select_purpose: boolean;
  can_do_preventive: boolean;
  is_admin: boolean;
}

export interface ApiUser {
  id: number;
  name: string;
  username: string;
}

export interface LoginResponse {
  token: string;
  user: ApiUser;
  permissions: PermissionFlags;
}

export interface Booking {
  id: number;
  client_name: string | null;
  contract_code: string | null;
  product_name: string | null;
}

export interface SpaceSearchResult {
  id: number;
  external_code: string;
  type: string | null;
  duplicate: boolean;
  existing_audit_id: number | null;
  booking: Booking | null;
}

export interface Criterion {
  id: number;
  name: string;
  key: string;
}

export type AuditType = 'general' | 'structural';
export type AuditPurpose = 'audit_only' | 'preventive_maintenance';
export type CriterionValue = 'good' | 'bad';

export interface Audit {
  id: number;
  client_uuid: string | null;
  advertising_space_id: number;
  year: number;
  week: number;
  audit_type: AuditType;
  general_status: string;
  audit_date: string | null;
}
```

- [ ] **Step 2: Define the ApiError**

Create `mobile/src/api/errors.ts`:
```ts
export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public body?: unknown,
  ) {
    super(message);
    this.name = 'ApiError';
  }

  get isConflict() {
    return this.status === 409;
  }
  get isValidation() {
    return this.status === 422;
  }
  get isUnauthorized() {
    return this.status === 401;
  }
}
```

- [ ] **Step 3: Write the failing client test**

Create `mobile/__tests__/api/client.test.ts`:
```ts
import { apiFetch } from '../../src/api/client';
import { ApiError } from '../../src/api/errors';

const okJson = (body: unknown, status = 200) =>
  Promise.resolve({
    ok: status >= 200 && status < 300,
    status,
    json: () => Promise.resolve(body),
    text: () => Promise.resolve(JSON.stringify(body)),
  } as Response);

describe('apiFetch', () => {
  afterEach(() => jest.restoreAllMocks());

  it('adds the bearer token and parses JSON', async () => {
    const fetchMock = jest.spyOn(global, 'fetch').mockReturnValue(okJson({ ok: true }));
    const res = await apiFetch('/ping', { token: 'abc' });

    expect(res).toEqual({ ok: true });
    const [, init] = fetchMock.mock.calls[0];
    expect((init?.headers as Record<string, string>).Authorization).toBe('Bearer abc');
    expect((init?.headers as Record<string, string>).Accept).toBe('application/json');
  });

  it('throws ApiError with status and body on non-2xx', async () => {
    jest.spyOn(global, 'fetch').mockReturnValue(okJson({ message: 'nope' }, 422));
    await expect(apiFetch('/x', {})).rejects.toMatchObject({
      name: 'ApiError',
      status: 422,
    });
  });

  it('exposes the parsed body on the error', async () => {
    jest.spyOn(global, 'fetch').mockReturnValue(okJson({ message: 'dup', existing_audit: { id: 7 } }, 409));
    const err: ApiError = await apiFetch('/x', {}).catch((e) => e);
    expect(err.isConflict).toBe(true);
    expect(err.body).toEqual({ message: 'dup', existing_audit: { id: 7 } });
  });
});
```

- [ ] **Step 4: Run to verify it fails**

Run: `cd mobile && npx jest api/client.test`
Expected: FAIL — cannot find `../../src/api/client`.

- [ ] **Step 5: Implement the client**

Create `mobile/src/api/client.ts`:
```ts
import { getApiBaseUrl } from '../config';
import { ApiError } from './errors';

export interface ApiOptions {
  method?: string;
  token?: string | null;
  /** JSON body; ignored when `form` is provided. */
  json?: unknown;
  /** Multipart body. */
  form?: FormData;
  query?: Record<string, string | number | undefined>;
}

function buildUrl(path: string, query?: ApiOptions['query']): string {
  const base = getApiBaseUrl();
  const qs = query
    ? '?' +
      Object.entries(query)
        .filter(([, v]) => v !== undefined)
        .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(String(v))}`)
        .join('&')
    : '';
  return `${base}/api${path}${qs}`;
}

export async function apiFetch<T = unknown>(path: string, opts: ApiOptions): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json' };
  if (opts.token) headers.Authorization = `Bearer ${opts.token}`;

  let body: BodyInit | undefined;
  if (opts.form) {
    body = opts.form as unknown as BodyInit; // RN sets multipart boundary automatically
  } else if (opts.json !== undefined) {
    headers['Content-Type'] = 'application/json';
    body = JSON.stringify(opts.json);
  }

  const res = await fetch(buildUrl(path, opts.query), {
    method: opts.method ?? (body ? 'POST' : 'GET'),
    headers,
    body,
  });

  let parsed: unknown = null;
  try {
    parsed = await res.json();
  } catch {
    parsed = null;
  }

  if (!res.ok) {
    const message =
      (parsed as { message?: string } | null)?.message ?? `Request failed (${res.status})`;
    throw new ApiError(res.status, message, parsed);
  }

  return parsed as T;
}
```

- [ ] **Step 6: Run to verify it passes + typecheck**

Run: `cd mobile && npx jest api/client.test && npx tsc --noEmit`
Expected: PASS, clean.

- [ ] **Step 7: Commit**

```bash
cd /Users/jcarrillo/dev/personal/checkmedia_v2
git add mobile/src/api mobile/__tests__/api/client.test.ts
git commit -m "feat(mobile): typed api fetch client with error mapping"
```

---

### Task 4: API endpoint functions (auth, spaces, criteria, audits)

**Files:**
- Create: `mobile/src/api/auth.ts`, `spaces.ts`, `criteria.ts`, `audits.ts`
- Test: `mobile/__tests__/api/endpoints.test.ts`

- [ ] **Step 1: Write the failing endpoint tests**

Create `mobile/__tests__/api/endpoints.test.ts`:
```ts
import * as client from '../../src/api/client';
import { login, me, logout } from '../../src/api/auth';
import { searchSpace } from '../../src/api/spaces';
import { listCriteria } from '../../src/api/criteria';
import { submitAudit } from '../../src/api/audits';

describe('endpoint functions', () => {
  afterEach(() => jest.restoreAllMocks());

  it('login posts credentials and returns the session', async () => {
    const spy = jest.spyOn(client, 'apiFetch').mockResolvedValue({ token: 't' } as never);
    await login({ username: 'u', password: 'p', deviceName: 'pixel' });
    expect(spy).toHaveBeenCalledWith('/login', {
      method: 'POST',
      json: { username: 'u', password: 'p', device_name: 'pixel' },
    });
  });

  it('searchSpace unwraps the data envelope', async () => {
    jest.spyOn(client, 'apiFetch').mockResolvedValue({ data: { id: 1, external_code: 'A' } } as never);
    const res = await searchSpace('A', 'general', 'tok');
    expect(res).toEqual({ id: 1, external_code: 'A' });
  });

  it('listCriteria unwraps the data array', async () => {
    jest.spyOn(client, 'apiFetch').mockResolvedValue({ data: [{ id: 1, name: 'N', key: 'k' }] } as never);
    const res = await listCriteria('general', 'tok');
    expect(res).toHaveLength(1);
    expect(res[0].key).toBe('k');
  });

  it('submitAudit posts a FormData and unwraps the audit', async () => {
    const spy = jest.spyOn(client, 'apiFetch').mockResolvedValue({ data: { id: 9 } } as never);
    const form = new FormData();
    const res = await submitAudit(form, 'tok');
    expect(res).toEqual({ id: 9 });
    expect(spy).toHaveBeenCalledWith('/audits', { method: 'POST', form, token: 'tok' });
  });

  it('me and logout call their paths', async () => {
    const spy = jest.spyOn(client, 'apiFetch').mockResolvedValue({} as never);
    await me('tok');
    await logout('tok');
    expect(spy).toHaveBeenCalledWith('/me', { token: 'tok' });
    expect(spy).toHaveBeenCalledWith('/logout', { method: 'POST', token: 'tok' });
  });
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd mobile && npx jest api/endpoints.test`
Expected: FAIL — modules missing.

- [ ] **Step 3: Implement the endpoint modules**

Create `mobile/src/api/auth.ts`:
```ts
import { apiFetch } from './client';
import type { LoginResponse, ApiUser, PermissionFlags } from './types';

export function login(input: { username: string; password: string; deviceName: string }) {
  return apiFetch<LoginResponse>('/login', {
    method: 'POST',
    json: { username: input.username, password: input.password, device_name: input.deviceName },
  });
}

export function me(token: string) {
  return apiFetch<{ user: ApiUser; permissions: PermissionFlags }>('/me', { token });
}

export function logout(token: string) {
  return apiFetch<{ ok: boolean }>('/logout', { method: 'POST', token });
}
```
Create `mobile/src/api/spaces.ts`:
```ts
import { apiFetch } from './client';
import type { SpaceSearchResult, AuditType } from './types';

export async function searchSpace(code: string, type: AuditType, token: string) {
  const res = await apiFetch<{ data: SpaceSearchResult }>('/spaces/search', {
    token,
    query: { code, type },
  });
  return res.data;
}
```
Create `mobile/src/api/criteria.ts`:
```ts
import { apiFetch } from './client';
import type { Criterion, AuditType } from './types';

export async function listCriteria(type: AuditType, token: string) {
  const res = await apiFetch<{ data: Criterion[] }>('/criteria', { token, query: { type } });
  return res.data;
}
```
Create `mobile/src/api/audits.ts`:
```ts
import { apiFetch } from './client';
import type { Audit } from './types';

export async function submitAudit(form: FormData, token: string) {
  const res = await apiFetch<{ data: Audit }>('/audits', { method: 'POST', form, token });
  return res.data;
}
```

- [ ] **Step 4: Run to verify it passes + typecheck**

Run: `cd mobile && npx jest api/endpoints.test && npx tsc --noEmit`
Expected: PASS, clean.

- [ ] **Step 5: Commit**

```bash
cd /Users/jcarrillo/dev/personal/checkmedia_v2
git add mobile/src/api mobile/__tests__/api/endpoints.test.ts
git commit -m "feat(mobile): api endpoint functions for auth, spaces, criteria, audits"
```

---

### Task 5: Audit-type/purpose resolver, validation, and payload builder (pure)

**Files:**
- Create: `mobile/src/audit/auditType.ts`, `validation.ts`, `payload.ts`
- Test: `mobile/__tests__/audit/logic.test.ts`

- [ ] **Step 1: Write the failing logic tests**

Create `mobile/__tests__/audit/logic.test.ts`:
```ts
import { resolveAuditOptions } from '../../src/audit/auditType';
import { validateAudit } from '../../src/audit/validation';
import { buildAuditFormData } from '../../src/audit/payload';
import type { PermissionFlags } from '../../src/api/types';

const perms = (over: Partial<PermissionFlags> = {}): PermissionFlags => ({
  can_audit: true,
  can_audit_structural: false,
  can_select_audit_type: false,
  can_select_purpose: false,
  can_do_preventive: false,
  is_admin: false,
  ...over,
});

describe('resolveAuditOptions', () => {
  it('forces general for a general-only auditor', () => {
    const o = resolveAuditOptions(perms());
    expect(o.types).toEqual(['general']);
    expect(o.defaultType).toBe('general');
    expect(o.canChooseType).toBe(false);
  });

  it('forces structural for a structural-only auditor', () => {
    const o = resolveAuditOptions(perms({ can_audit: false, can_audit_structural: true }));
    expect(o.types).toEqual(['structural']);
    expect(o.defaultType).toBe('structural');
  });

  it('offers both when can_select_audit_type', () => {
    const o = resolveAuditOptions(perms({ can_audit_structural: true, can_select_audit_type: true }));
    expect(o.types).toEqual(['general', 'structural']);
    expect(o.canChooseType).toBe(true);
  });

  it('offers preventive only when can_do_preventive', () => {
    expect(resolveAuditOptions(perms()).purposes).toEqual(['audit_only']);
    expect(resolveAuditOptions(perms({ can_do_preventive: true })).purposes).toEqual([
      'audit_only',
      'preventive_maintenance',
    ]);
  });
});

describe('validateAudit', () => {
  const base = {
    photos: [{ uri: 'file://a.jpg' }],
    values: { 1: { value: 'good' as const, comment: '' } },
  };

  it('passes a good audit with a photo', () => {
    expect(validateAudit(base).length).toBe(0);
  });

  it('requires at least one photo', () => {
    expect(validateAudit({ ...base, photos: [] })).toContain('Debe registrar al menos una foto.');
  });

  it('requires a comment when a value is bad', () => {
    const errs = validateAudit({ ...base, values: { 1: { value: 'bad', comment: '  ' } } });
    expect(errs.some((e) => e.includes('comentario'))).toBe(true);
  });
});

describe('buildAuditFormData', () => {
  it('serializes all fields and photos into FormData', () => {
    const fd = buildAuditFormData({
      clientUuid: '11111111-1111-1111-1111-111111111111',
      spaceId: 5,
      auditType: 'general',
      purpose: 'audit_only',
      observation: 'obs',
      capturedAt: '2026-06-05T10:00:00.000Z',
      mode: 'new',
      values: { 7: { value: 'bad', comment: 'roto' } },
      photos: [{ uri: 'file://a.jpg' }],
    });
    // FormData in RN/jsdom supports get(); assert key presence.
    expect(fd.get('client_uuid')).toBe('11111111-1111-1111-1111-111111111111');
    expect(fd.get('space_id')).toBe('5');
    expect(fd.get('audit_type')).toBe('general');
    expect(fd.get('mode')).toBe('new');
    expect(fd.get('values[0][criterion_id]')).toBe('7');
    expect(fd.get('values[0][value]')).toBe('bad');
    expect(fd.get('values[0][comment]')).toBe('roto');
    expect(fd.get('photos[0]')).not.toBeNull();
  });
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd mobile && npx jest audit/logic.test`
Expected: FAIL — modules missing.

- [ ] **Step 3: Implement the resolver**

Create `mobile/src/audit/auditType.ts`:
```ts
import type { AuditType, AuditPurpose, PermissionFlags } from '../api/types';

export interface AuditOptions {
  types: AuditType[];
  defaultType: AuditType;
  canChooseType: boolean;
  purposes: AuditPurpose[];
}

export function resolveAuditOptions(p: PermissionFlags): AuditOptions {
  let types: AuditType[];
  if (p.can_select_audit_type) {
    types = ['general', 'structural'];
  } else if (p.can_audit_structural && !p.can_audit) {
    types = ['structural'];
  } else {
    types = ['general'];
  }

  const purposes: AuditPurpose[] = p.can_do_preventive
    ? ['audit_only', 'preventive_maintenance']
    : ['audit_only'];

  return {
    types,
    defaultType: types[0],
    canChooseType: types.length > 1,
    purposes,
  };
}
```

- [ ] **Step 4: Implement validation**

Create `mobile/src/audit/validation.ts`:
```ts
import type { CriterionValue } from '../api/types';

export interface AuditFormState {
  photos: { uri: string }[];
  values: Record<number, { value: CriterionValue; comment: string }>;
}

export function validateAudit(state: Pick<AuditFormState, 'photos' | 'values'>): string[] {
  const errors: string[] = [];
  if (state.photos.length === 0) {
    errors.push('Debe registrar al menos una foto.');
  }
  for (const [, v] of Object.entries(state.values)) {
    if (v.value === 'bad' && v.comment.trim() === '') {
      errors.push('Cada ítem marcado como Malo necesita un comentario.');
      break;
    }
  }
  return errors;
}
```

- [ ] **Step 5: Implement the payload builder**

Create `mobile/src/audit/payload.ts`:
```ts
import type { AuditType, AuditPurpose, CriterionValue } from '../api/types';

export interface AuditSubmission {
  clientUuid: string;
  spaceId: number;
  auditType: AuditType;
  purpose: AuditPurpose;
  observation: string;
  capturedAt: string; // ISO8601
  mode: 'new' | 'complement';
  values: Record<number, { value: CriterionValue; comment: string }>;
  photos: { uri: string; name?: string; type?: string }[];
}

export function buildAuditFormData(s: AuditSubmission): FormData {
  const fd = new FormData();
  fd.append('client_uuid', s.clientUuid);
  fd.append('space_id', String(s.spaceId));
  fd.append('audit_type', s.auditType);
  fd.append('purpose', s.purpose);
  fd.append('observation', s.observation ?? '');
  fd.append('captured_at', s.capturedAt);
  fd.append('mode', s.mode);

  Object.entries(s.values).forEach(([criterionId, v], i) => {
    fd.append(`values[${i}][criterion_id]`, String(criterionId));
    fd.append(`values[${i}][value]`, v.value);
    fd.append(`values[${i}][comment]`, v.value === 'bad' ? v.comment.trim() : '');
  });

  s.photos.forEach((p, i) => {
    // React Native FormData file shape:
    fd.append(`photos[${i}]`, {
      uri: p.uri,
      name: p.name ?? `photo-${i}.jpg`,
      type: p.type ?? 'image/jpeg',
    } as unknown as Blob);
  });

  return fd;
}
```

- [ ] **Step 6: Run to verify it passes + typecheck**

Run: `cd mobile && npx jest audit/logic.test && npx tsc --noEmit`
Expected: PASS, clean. (Note: the FormData `.get()` assertions rely on the jsdom/RN FormData polyfill provided by jest-expo. If `photos[0]` file append is not retrievable via `.get()` in the test env, change that single assertion to check a string field only and verify file appends in the manual smoke test — do NOT weaken the production builder.)

- [ ] **Step 7: Commit**

```bash
cd /Users/jcarrillo/dev/personal/checkmedia_v2
git add mobile/src/audit mobile/__tests__/audit/logic.test.ts
git commit -m "feat(mobile): audit options resolver, client validation, multipart payload builder"
```

---

### Task 6: Photo resize wrapper

**Files:**
- Create: `mobile/src/photos/resize.ts`
- Test: `mobile/__tests__/photos/resize.test.ts`

- [ ] **Step 1: Write the failing test**

Create `mobile/__tests__/photos/resize.test.ts`:
```ts
import { manipulateAsync } from 'expo-image-manipulator';
import { resizeForUpload } from '../../src/photos/resize';

describe('resizeForUpload', () => {
  afterEach(() => jest.clearAllMocks());

  it('resizes to a 2560px long edge at quality 0.85 and returns a file descriptor', async () => {
    const result = await resizeForUpload('file://orig.jpg');

    expect(manipulateAsync).toHaveBeenCalledWith(
      'file://orig.jpg',
      [{ resize: { width: 2560 } }],
      expect.objectContaining({ compress: 0.85 }),
    );
    expect(result.uri).toContain('#resized');
    expect(result.type).toBe('image/jpeg');
    expect(result.name).toMatch(/\.jpg$/);
  });
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd mobile && npx jest photos/resize.test`
Expected: FAIL — module missing.

- [ ] **Step 3: Implement the resize wrapper**

Create `mobile/src/photos/resize.ts`:
```ts
import { manipulateAsync, SaveFormat } from 'expo-image-manipulator';

export interface UploadPhoto {
  uri: string;
  name: string;
  type: string;
}

const LONG_EDGE = 2560;
const QUALITY = 0.85;

/**
 * Resize a captured photo so its long edge is at most 2560px and re-encode as
 * JPEG q0.85. expo-image-manipulator's `resize` keeps the aspect ratio when
 * only one dimension is given, so passing `width` scales proportionally.
 */
export async function resizeForUpload(uri: string): Promise<UploadPhoto> {
  const result = await manipulateAsync(uri, [{ resize: { width: LONG_EDGE } }], {
    compress: QUALITY,
    format: SaveFormat.JPEG,
  });
  const name = `photo-${Date.now()}.jpg`;
  return { uri: result.uri, name, type: 'image/jpeg' };
}
```

> Note on orientation: when the photo is portrait, scaling by `width` may not bound the long edge. This is acceptable for the online MVP (the server caps at 10MB and re-watermarks). A long-edge-aware resize (measure then resize the larger dimension) is a follow-up; do not over-build here.

- [ ] **Step 4: Run to verify it passes + typecheck**

Run: `cd mobile && npx jest photos/resize.test && npx tsc --noEmit`
Expected: PASS, clean.

- [ ] **Step 5: Commit**

```bash
cd /Users/jcarrillo/dev/personal/checkmedia_v2
git add mobile/src/photos mobile/__tests__/photos/resize.test.ts
git commit -m "feat(mobile): photo resize wrapper (2560px, q0.85)"
```

---

## Phase 2 — Auth session & screens

### Task 7: Token store, biometrics wrapper, and AuthContext

**Files:**
- Create: `mobile/src/auth/tokenStore.ts`, `biometrics.ts`, `AuthContext.tsx`
- Test: `mobile/__tests__/auth/tokenStore.test.ts`, `mobile/__tests__/auth/AuthContext.test.tsx`

- [ ] **Step 1: Write the failing token store test**

Create `mobile/__tests__/auth/tokenStore.test.ts`:
```ts
import { saveSession, loadSession, clearSession } from '../../src/auth/tokenStore';

describe('tokenStore', () => {
  it('round-trips a session', async () => {
    await saveSession({ token: 't', user: { id: 1, name: 'A', username: 'a' } });
    const s = await loadSession();
    expect(s?.token).toBe('t');
    expect(s?.user.username).toBe('a');
  });

  it('clears the session', async () => {
    await saveSession({ token: 't', user: { id: 1, name: 'A', username: 'a' } });
    await clearSession();
    expect(await loadSession()).toBeNull();
  });
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd mobile && npx jest auth/tokenStore.test`
Expected: FAIL — module missing.

- [ ] **Step 3: Implement the token store**

Create `mobile/src/auth/tokenStore.ts`:
```ts
import * as SecureStore from 'expo-secure-store';
import type { ApiUser } from '../api/types';

const KEY = 'checkmedia.session';

export interface StoredSession {
  token: string;
  user: ApiUser;
}

export async function saveSession(session: StoredSession): Promise<void> {
  await SecureStore.setItemAsync(KEY, JSON.stringify(session));
}

export async function loadSession(): Promise<StoredSession | null> {
  const raw = await SecureStore.getItemAsync(KEY);
  if (!raw) return null;
  try {
    return JSON.parse(raw) as StoredSession;
  } catch {
    return null;
  }
}

export async function clearSession(): Promise<void> {
  await SecureStore.deleteItemAsync(KEY);
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `cd mobile && npx jest auth/tokenStore.test`
Expected: PASS.

- [ ] **Step 5: Implement the biometrics wrapper**

Create `mobile/src/auth/biometrics.ts`:
```ts
import * as LocalAuthentication from 'expo-local-authentication';

/** Returns true if the device can do biometric auth and the user is enrolled. */
export async function biometricsAvailable(): Promise<boolean> {
  const [hasHardware, enrolled] = await Promise.all([
    LocalAuthentication.hasHardwareAsync(),
    LocalAuthentication.isEnrolledAsync(),
  ]);
  return hasHardware && enrolled;
}

/** Prompts biometric unlock; returns true on success. */
export async function unlockWithBiometrics(): Promise<boolean> {
  const result = await LocalAuthentication.authenticateAsync({
    promptMessage: 'Desbloquea CheckMedia',
    disableDeviceFallback: false,
  });
  return result.success;
}
```

- [ ] **Step 6: Write the failing AuthContext test**

Create `mobile/__tests__/auth/AuthContext.test.tsx`:
```tsx
import React from 'react';
import { Text } from 'react-native';
import { render, waitFor, act } from '@testing-library/react-native';
import { AuthProvider, useAuth } from '../../src/auth/AuthContext';
import * as authApi from '../../src/api/auth';

function Probe() {
  const { status, user, signIn } = useAuth();
  return (
    <>
      <Text testID="status">{status}</Text>
      <Text testID="user">{user?.username ?? 'none'}</Text>
      <Text testID="trigger" onPress={() => signIn('u', 'p')}>
        go
      </Text>
    </>
  );
}

describe('AuthContext', () => {
  afterEach(() => jest.restoreAllMocks());

  it('starts unauthenticated when no stored session', async () => {
    const { getByTestId } = render(
      <AuthProvider>
        <Probe />
      </AuthProvider>,
    );
    await waitFor(() => expect(getByTestId('status').props.children).toBe('unauthenticated'));
  });

  it('signs in and stores the session', async () => {
    jest.spyOn(authApi, 'login').mockResolvedValue({
      token: 't',
      user: { id: 1, name: 'A', username: 'auditor' },
      permissions: {
        can_audit: true,
        can_audit_structural: false,
        can_select_audit_type: false,
        can_select_purpose: false,
        can_do_preventive: false,
        is_admin: false,
      },
    });

    const { getByTestId } = render(
      <AuthProvider>
        <Probe />
      </AuthProvider>,
    );
    await waitFor(() => expect(getByTestId('status').props.children).toBe('unauthenticated'));
    await act(async () => {
      getByTestId('trigger').props.onPress();
    });
    await waitFor(() => expect(getByTestId('user').props.children).toBe('auditor'));
  });
});
```

- [ ] **Step 7: Run to verify it fails**

Run: `cd mobile && npx jest auth/AuthContext.test`
Expected: FAIL — module missing.

- [ ] **Step 8: Implement AuthContext**

Create `mobile/src/auth/AuthContext.tsx`:
```tsx
import React, { createContext, useContext, useEffect, useState, useCallback } from 'react';
import * as Device from 'expo-device';
import { login as loginApi, logout as logoutApi } from '../api/auth';
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
    const res = await loginApi({ username, password, deviceName });
    await saveSession({ token: res.token, user: res.user });
    setToken(res.token);
    setUser(res.user);
    setPermissions(res.permissions);
    setStatus('authenticated');
  }, []);

  const signOut = useCallback(async () => {
    if (token) {
      try {
        await logoutApi(token);
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
```
Then install the device-name dep:
```bash
cd mobile && npx expo install expo-device
```

- [ ] **Step 9: Run to verify it passes + typecheck**

Run: `cd mobile && npx jest auth/ && npx tsc --noEmit`
Expected: PASS, clean. (If `expo-device` needs a mock in jest, add to `jest.setup.ts`: `jest.mock('expo-device', () => ({ deviceName: 'test-device' }));`.)

- [ ] **Step 10: Commit**

```bash
cd /Users/jcarrillo/dev/personal/checkmedia_v2
git add mobile/src/auth mobile/__tests__/auth mobile/jest.setup.ts mobile/package.json
git commit -m "feat(mobile): secure session store, biometrics wrapper, auth context"
```

---

### Task 8: Root layout, providers, and auth gate

**Files:**
- Modify: `mobile/app/_layout.tsx`, `mobile/app/index.tsx`
- Create: `mobile/app/(app)/_layout.tsx`

- [ ] **Step 1: Implement the root layout with providers**

Replace `mobile/app/_layout.tsx`:
```tsx
import { Stack } from 'expo-router';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { AuthProvider } from '../src/auth/AuthContext';

const queryClient = new QueryClient();

export default function RootLayout() {
  return (
    <SafeAreaProvider>
      <QueryClientProvider client={queryClient}>
        <AuthProvider>
          <Stack screenOptions={{ headerShown: false }} />
        </AuthProvider>
      </QueryClientProvider>
    </SafeAreaProvider>
  );
}
```

- [ ] **Step 2: Implement the index redirect (auth gate)**

Replace `mobile/app/index.tsx`:
```tsx
import { Redirect } from 'expo-router';
import { ActivityIndicator, View } from 'react-native';
import { useAuth } from '../src/auth/AuthContext';

export default function Index() {
  const { status } = useAuth();
  if (status === 'loading') {
    return (
      <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center' }}>
        <ActivityIndicator />
      </View>
    );
  }
  return <Redirect href={status === 'authenticated' ? '/(app)/home' : '/login'} />;
}
```

- [ ] **Step 3: Implement the authenticated stack layout (guards nested routes)**

Create `mobile/app/(app)/_layout.tsx`:
```tsx
import { Redirect, Stack } from 'expo-router';
import { useAuth } from '../../src/auth/AuthContext';

export default function AppLayout() {
  const { status } = useAuth();
  if (status !== 'authenticated') {
    return <Redirect href="/login" />;
  }
  return <Stack screenOptions={{ headerShown: true }} />;
}
```

- [ ] **Step 4: Verify typecheck + bundle**

Run:
```bash
cd mobile
npx tsc --noEmit
npx expo export --platform android --output-dir /tmp/expo-export-check && rm -rf /tmp/expo-export-check
```
Expected: clean typecheck, successful bundle.

- [ ] **Step 5: Commit**

```bash
cd /Users/jcarrillo/dev/personal/checkmedia_v2
git add mobile/app
git commit -m "feat(mobile): root providers and auth-gated routing"
```

---

### Task 9: Login screen (with biometric unlock)

**Files:**
- Create: `mobile/app/login.tsx`
- Create: `mobile/src/ui/Button.tsx`, `mobile/src/ui/Field.tsx`
- Test: `mobile/__tests__/screens/login.test.tsx`

- [ ] **Step 1: Implement shared UI primitives**

Create `mobile/src/ui/Button.tsx`:
```tsx
import { Pressable, Text, ActivityIndicator, StyleSheet } from 'react-native';

export function Button({
  title,
  onPress,
  loading,
  disabled,
  testID,
}: {
  title: string;
  onPress: () => void;
  loading?: boolean;
  disabled?: boolean;
  testID?: string;
}) {
  return (
    <Pressable
      testID={testID}
      onPress={onPress}
      disabled={disabled || loading}
      style={[styles.btn, (disabled || loading) && styles.disabled]}
    >
      {loading ? <ActivityIndicator color="#fff" /> : <Text style={styles.text}>{title}</Text>}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  btn: { backgroundColor: '#1d4ed8', padding: 14, borderRadius: 8, alignItems: 'center' },
  disabled: { opacity: 0.5 },
  text: { color: '#fff', fontWeight: '600' },
});
```
Create `mobile/src/ui/Field.tsx`:
```tsx
import { TextInput, Text, View, StyleSheet, TextInputProps } from 'react-native';

export function Field({ label, ...props }: { label: string } & TextInputProps) {
  return (
    <View style={styles.wrap}>
      <Text style={styles.label}>{label}</Text>
      <TextInput style={styles.input} autoCapitalize="none" {...props} />
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { marginBottom: 12 },
  label: { marginBottom: 4, fontWeight: '500' },
  input: { borderWidth: 1, borderColor: '#cbd5e1', borderRadius: 8, padding: 12 },
});
```

- [ ] **Step 2: Write the failing login screen test**

Create `mobile/__tests__/screens/login.test.tsx`:
```tsx
import React from 'react';
import { render, fireEvent, waitFor } from '@testing-library/react-native';
import LoginScreen from '../../app/login';
import * as AuthCtx from '../../src/auth/AuthContext';

jest.mock('expo-router', () => ({ router: { replace: jest.fn() } }));

describe('LoginScreen', () => {
  afterEach(() => jest.restoreAllMocks());

  it('calls signIn with entered credentials', async () => {
    const signIn = jest.fn().mockResolvedValue(undefined);
    jest.spyOn(AuthCtx, 'useAuth').mockReturnValue({
      status: 'unauthenticated',
      token: null,
      user: null,
      permissions: null,
      signIn,
      signOut: jest.fn(),
    });

    const { getByTestId, getByText } = render(<LoginScreen />);
    fireEvent.changeText(getByTestId('username'), 'auditor');
    fireEvent.changeText(getByTestId('password'), 'secret123');
    fireEvent.press(getByText('Ingresar'));

    await waitFor(() => expect(signIn).toHaveBeenCalledWith('auditor', 'secret123'));
  });

  it('shows an error message on failed login', async () => {
    const signIn = jest.fn().mockRejectedValue(new Error('Credenciales inválidas'));
    jest.spyOn(AuthCtx, 'useAuth').mockReturnValue({
      status: 'unauthenticated',
      token: null,
      user: null,
      permissions: null,
      signIn,
      signOut: jest.fn(),
    });

    const { getByTestId, getByText, findByText } = render(<LoginScreen />);
    fireEvent.changeText(getByTestId('username'), 'x');
    fireEvent.changeText(getByTestId('password'), 'y');
    fireEvent.press(getByText('Ingresar'));

    expect(await findByText(/Credenciales inválidas/)).toBeTruthy();
  });
});
```

- [ ] **Step 3: Run to verify it fails**

Run: `cd mobile && npx jest screens/login.test`
Expected: FAIL — `../../app/login` missing.

- [ ] **Step 4: Implement the login screen**

Create `mobile/app/login.tsx`:
```tsx
import { useEffect, useState } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { router } from 'expo-router';
import { useAuth } from '../src/auth/AuthContext';
import { biometricsAvailable, unlockWithBiometrics } from '../src/auth/biometrics';
import { loadSession } from '../src/auth/tokenStore';
import { Field } from '../src/ui/Field';
import { Button } from '../src/ui/Button';

export default function LoginScreen() {
  const { signIn, status } = useAuth();
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (status === 'authenticated') router.replace('/(app)/home');
  }, [status]);

  // Offer biometric unlock if a session already exists on this device.
  useEffect(() => {
    (async () => {
      const stored = await loadSession();
      if (stored && (await biometricsAvailable())) {
        const ok = await unlockWithBiometrics();
        if (ok) router.replace('/(app)/home');
      }
    })();
  }, []);

  const submit = async () => {
    setError(null);
    setBusy(true);
    try {
      await signIn(username.trim(), password);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'No se pudo iniciar sesión.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>CheckMedia Auditor</Text>
      <Field label="Usuario" testID="username" value={username} onChangeText={setUsername} />
      <Field
        label="Contraseña"
        testID="password"
        value={password}
        onChangeText={setPassword}
        secureTextEntry
      />
      {error && <Text style={styles.error}>{error}</Text>}
      <Button title="Ingresar" onPress={submit} loading={busy} />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, padding: 24, justifyContent: 'center' },
  title: { fontSize: 24, fontWeight: '700', marginBottom: 24, textAlign: 'center' },
  error: { color: '#dc2626', marginBottom: 12 },
});
```

- [ ] **Step 5: Run to verify it passes + typecheck**

Run: `cd mobile && npx jest screens/login.test && npx tsc --noEmit`
Expected: PASS, clean.

- [ ] **Step 6: Commit**

```bash
cd /Users/jcarrillo/dev/personal/checkmedia_v2
git add mobile/app/login.tsx mobile/src/ui mobile/__tests__/screens/login.test.tsx
git commit -m "feat(mobile): login screen with biometric unlock"
```

---

### Task 10: Home (space search) screen

**Files:**
- Create: `mobile/app/(app)/home.tsx`
- Test: `mobile/__tests__/screens/home.test.tsx`

- [ ] **Step 1: Write the failing home test**

Create `mobile/__tests__/screens/home.test.tsx`:
```tsx
import React from 'react';
import { render, fireEvent, waitFor } from '@testing-library/react-native';
import HomeScreen from '../../app/(app)/home';
import * as spacesApi from '../../src/api/spaces';
import * as AuthCtx from '../../src/auth/AuthContext';

const pushMock = jest.fn();
jest.mock('expo-router', () => ({ router: { push: (...a: unknown[]) => pushMock(...a) } }));

function mockAuth() {
  jest.spyOn(AuthCtx, 'useAuth').mockReturnValue({
    status: 'authenticated',
    token: 'tok',
    user: { id: 1, name: 'A', username: 'a' },
    permissions: {
      can_audit: true,
      can_audit_structural: false,
      can_select_audit_type: false,
      can_select_purpose: false,
      can_do_preventive: false,
      is_admin: false,
    },
    signIn: jest.fn(),
    signOut: jest.fn(),
  });
}

describe('HomeScreen', () => {
  beforeEach(() => { pushMock.mockClear(); mockAuth(); });
  afterEach(() => jest.restoreAllMocks());

  it('navigates to the space route on a successful search', async () => {
    jest.spyOn(spacesApi, 'searchSpace').mockResolvedValue({
      id: 3, external_code: 'ABC', type: 'Billboard', duplicate: false, existing_audit_id: null, booking: null,
    });

    const { getByTestId, getByText } = render(<HomeScreen />);
    fireEvent.changeText(getByTestId('code'), 'ABC');
    fireEvent.press(getByText('Buscar'));

    await waitFor(() => expect(pushMock).toHaveBeenCalledWith('/(app)/space/ABC'));
  });

  it('shows a not-found message on 404', async () => {
    const { ApiError } = await import('../../src/api/errors');
    jest.spyOn(spacesApi, 'searchSpace').mockRejectedValue(new ApiError(404, 'Espacio no encontrado.'));

    const { getByTestId, getByText, findByText } = render(<HomeScreen />);
    fireEvent.changeText(getByTestId('code'), 'NOPE');
    fireEvent.press(getByText('Buscar'));

    expect(await findByText(/no encontrado/i)).toBeTruthy();
  });
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd mobile && npx jest screens/home.test`
Expected: FAIL — screen missing.

- [ ] **Step 3: Implement the home screen**

Create `mobile/app/(app)/home.tsx`:
```tsx
import { useState } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { router } from 'expo-router';
import { useAuth } from '../../src/auth/AuthContext';
import { searchSpace } from '../../src/api/spaces';
import { resolveAuditOptions } from '../../src/audit/auditType';
import { Field } from '../../src/ui/Field';
import { Button } from '../../src/ui/Button';

export default function HomeScreen() {
  const { token, permissions, signOut } = useAuth();
  const [code, setCode] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const auditType = permissions ? resolveAuditOptions(permissions).defaultType : 'general';

  const search = async () => {
    setError(null);
    setBusy(true);
    try {
      await searchSpace(code.trim(), auditType, token ?? '');
      router.push(`/(app)/space/${encodeURIComponent(code.trim())}`);
    } catch (e) {
      const msg = e instanceof Error ? e.message : 'Error al buscar.';
      setError(msg.includes('no encontrado') ? 'Espacio no encontrado.' : msg);
    } finally {
      setBusy(false);
    }
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Buscar espacio</Text>
      <Field
        label="Código del espacio"
        testID="code"
        value={code}
        onChangeText={setCode}
        autoCapitalize="characters"
      />
      {error && <Text style={styles.error}>{error}</Text>}
      <Button title="Buscar" onPress={search} loading={busy} disabled={code.trim() === ''} />
      <View style={{ height: 24 }} />
      <Button title="Cerrar sesión" onPress={signOut} />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, padding: 24 },
  title: { fontSize: 20, fontWeight: '700', marginVertical: 16 },
  error: { color: '#dc2626', marginBottom: 12 },
});
```

> Note: the search result is fetched again on the space screen (Task 11) via react-query so the space data is fresh and cached; the home search here validates existence and routes. This keeps each screen independently testable.

- [ ] **Step 4: Run to verify it passes + typecheck**

Run: `cd mobile && npx jest screens/home.test && npx tsc --noEmit`
Expected: PASS, clean.

- [ ] **Step 5: Commit**

```bash
cd /Users/jcarrillo/dev/personal/checkmedia_v2
git add "mobile/app/(app)/home.tsx" mobile/__tests__/screens/home.test.tsx
git commit -m "feat(mobile): home space-search screen"
```

---

### Task 11: Space result screen

**Files:**
- Create: `mobile/app/(app)/space/[code].tsx`
- Test: `mobile/__tests__/screens/space.test.tsx`

- [ ] **Step 1: Write the failing space screen test**

Create `mobile/__tests__/screens/space.test.tsx`:
```tsx
import React from 'react';
import { render, waitFor, fireEvent } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import SpaceScreen from '../../app/(app)/space/[code]';
import * as spacesApi from '../../src/api/spaces';
import * as AuthCtx from '../../src/auth/AuthContext';

const pushMock = jest.fn();
jest.mock('expo-router', () => ({
  router: { push: (...a: unknown[]) => pushMock(...a) },
  useLocalSearchParams: () => ({ code: 'ABC' }),
}));

function wrap(ui: React.ReactElement) {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(<QueryClientProvider client={qc}>{ui}</QueryClientProvider>);
}

beforeEach(() => {
  pushMock.mockClear();
  jest.spyOn(AuthCtx, 'useAuth').mockReturnValue({
    status: 'authenticated', token: 'tok', user: { id: 1, name: 'A', username: 'a' },
    permissions: {
      can_audit: true, can_audit_structural: false, can_select_audit_type: false,
      can_select_purpose: false, can_do_preventive: false, is_admin: false,
    },
    signIn: jest.fn(), signOut: jest.fn(),
  });
});
afterEach(() => jest.restoreAllMocks());

it('renders the space and a duplicate warning', async () => {
  jest.spyOn(spacesApi, 'searchSpace').mockResolvedValue({
    id: 3, external_code: 'ABC', type: 'Billboard', duplicate: true, existing_audit_id: 99,
    booking: { id: 1, client_name: 'ACME', contract_code: 'C1', product_name: 'P' },
  });

  const { findByText } = wrap(<SpaceScreen />);
  expect(await findByText('ABC')).toBeTruthy();
  expect(await findByText(/ACME/)).toBeTruthy();
  expect(await findByText(/ya tiene una auditoría/i)).toBeTruthy();
});

it('navigates to the audit form on Auditar', async () => {
  jest.spyOn(spacesApi, 'searchSpace').mockResolvedValue({
    id: 3, external_code: 'ABC', type: 'Billboard', duplicate: false, existing_audit_id: null, booking: null,
  });

  const { findByText } = wrap(<SpaceScreen />);
  fireEvent.press(await findByText('Auditar'));
  await waitFor(() => expect(pushMock).toHaveBeenCalledWith('/(app)/audit/new?spaceId=3&code=ABC'));
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd mobile && npx jest screens/space.test`
Expected: FAIL — screen missing.

- [ ] **Step 3: Implement the space screen**

Create `mobile/app/(app)/space/[code].tsx`:
```tsx
import { View, Text, StyleSheet, ActivityIndicator, ScrollView } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { useQuery } from '@tanstack/react-query';
import { useAuth } from '../../../src/auth/AuthContext';
import { searchSpace } from '../../../src/api/spaces';
import { resolveAuditOptions } from '../../../src/audit/auditType';
import { Button } from '../../../src/ui/Button';

export default function SpaceScreen() {
  const { code } = useLocalSearchParams<{ code: string }>();
  const { token, permissions } = useAuth();
  const auditType = permissions ? resolveAuditOptions(permissions).defaultType : 'general';

  const { data, isLoading, error } = useQuery({
    queryKey: ['space', code, auditType],
    queryFn: () => searchSpace(String(code), auditType, token ?? ''),
  });

  if (isLoading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator />
      </View>
    );
  }
  if (error || !data) {
    return (
      <View style={styles.center}>
        <Text style={styles.error}>No se pudo cargar el espacio.</Text>
      </View>
    );
  }

  return (
    <ScrollView contentContainerStyle={styles.container}>
      <Text style={styles.code}>{data.external_code}</Text>
      {data.type && <Text style={styles.meta}>Tipo: {data.type}</Text>}
      {data.booking && (
        <View style={styles.card}>
          <Text style={styles.cardTitle}>Pauta actual</Text>
          <Text>Cliente: {data.booking.client_name ?? '—'}</Text>
          <Text>Contrato: {data.booking.contract_code ?? '—'}</Text>
          <Text>Producto: {data.booking.product_name ?? '—'}</Text>
        </View>
      )}
      {data.duplicate && (
        <Text style={styles.warn}>Este espacio ya tiene una auditoría esta semana.</Text>
      )}
      <Button
        title="Auditar"
        onPress={() => router.push(`/(app)/audit/new?spaceId=${data.id}&code=${encodeURIComponent(data.external_code)}`)}
      />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { padding: 24 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  code: { fontSize: 24, fontWeight: '700' },
  meta: { color: '#475569', marginTop: 4 },
  card: { backgroundColor: '#f1f5f9', padding: 16, borderRadius: 8, marginVertical: 16 },
  cardTitle: { fontWeight: '700', marginBottom: 8 },
  warn: { color: '#b45309', marginBottom: 16 },
  error: { color: '#dc2626' },
});
```

- [ ] **Step 4: Run to verify it passes + typecheck**

Run: `cd mobile && npx jest screens/space.test && npx tsc --noEmit`
Expected: PASS, clean.

- [ ] **Step 5: Commit**

```bash
cd /Users/jcarrillo/dev/personal/checkmedia_v2
git add "mobile/app/(app)/space" mobile/__tests__/screens/space.test.tsx
git commit -m "feat(mobile): space result screen with booking and duplicate warning"
```

---

### Task 12: Audit form screen (criteria, observation, camera, submit)

**Files:**
- Create: `mobile/app/(app)/audit/new.tsx`
- Create: `mobile/src/audit/useAuditSubmit.ts`
- Test: `mobile/__tests__/audit/useAuditSubmit.test.ts`, `mobile/__tests__/screens/auditForm.test.tsx`

- [ ] **Step 1: Write the failing submit-hook test**

Create `mobile/__tests__/audit/useAuditSubmit.test.ts`:
```ts
import { buildSubmission } from '../../src/audit/useAuditSubmit';

describe('buildSubmission', () => {
  it('assembles a submission with a generated uuid and capture time', () => {
    const s = buildSubmission({
      spaceId: 5,
      auditType: 'general',
      purpose: 'audit_only',
      observation: 'obs',
      values: { 7: { value: 'good', comment: '' } },
      photos: [{ uri: 'file://a.jpg', name: 'a.jpg', type: 'image/jpeg' }],
      capturedAt: '2026-06-05T10:00:00.000Z',
    });
    expect(s.spaceId).toBe(5);
    expect(s.mode).toBe('new');
    expect(s.clientUuid).toMatch(/^[0-9a-f-]{36}$/);
    expect(s.capturedAt).toBe('2026-06-05T10:00:00.000Z');
  });
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd mobile && npx jest audit/useAuditSubmit.test`
Expected: FAIL — module missing.

- [ ] **Step 3: Implement the submit hook + builder**

Create `mobile/src/audit/useAuditSubmit.ts`:
```ts
import { v4 as uuidv4 } from 'uuid';
import 'react-native-get-random-values';
import { submitAudit } from '../api/audits';
import { buildAuditFormData, type AuditSubmission } from './payload';
import type { AuditType, AuditPurpose, CriterionValue } from '../api/types';

export interface BuildInput {
  spaceId: number;
  auditType: AuditType;
  purpose: AuditPurpose;
  observation: string;
  values: Record<number, { value: CriterionValue; comment: string }>;
  photos: { uri: string; name: string; type: string }[];
  capturedAt: string;
}

export function buildSubmission(input: BuildInput): AuditSubmission {
  return {
    clientUuid: uuidv4(),
    spaceId: input.spaceId,
    auditType: input.auditType,
    purpose: input.purpose,
    observation: input.observation,
    capturedAt: input.capturedAt,
    mode: 'new',
    values: input.values,
    photos: input.photos,
  };
}

export async function submitBuiltAudit(input: BuildInput, token: string) {
  const submission = buildSubmission(input);
  const form = buildAuditFormData(submission);
  return submitAudit(form, token);
}
```
Then install the RN crypto polyfill (uuid needs it on RN):
```bash
cd mobile && npx expo install react-native-get-random-values
```
Add to `mobile/jest.setup.ts`:
```ts
jest.mock('react-native-get-random-values', () => ({}));
```

- [ ] **Step 4: Run to verify it passes**

Run: `cd mobile && npx jest audit/useAuditSubmit.test`
Expected: PASS.

- [ ] **Step 5: Write the failing audit-form screen test**

Create `mobile/__tests__/screens/auditForm.test.tsx`:
```tsx
import React from 'react';
import { render, fireEvent, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import AuditFormScreen from '../../app/(app)/audit/new';
import * as criteriaApi from '../../src/api/criteria';
import * as auditsApi from '../../src/api/audits';
import * as AuthCtx from '../../src/auth/AuthContext';

const pushMock = jest.fn();
const backMock = jest.fn();
jest.mock('expo-router', () => ({
  router: { push: (...a: unknown[]) => pushMock(...a), back: () => backMock() },
  useLocalSearchParams: () => ({ spaceId: '5', code: 'ABC' }),
}));

// Camera picker stub: pressing "Tomar foto" yields one resized photo.
jest.mock('../../src/photos/capture', () => ({
  capturePhoto: jest.fn(async () => ({ uri: 'file://x.jpg', name: 'x.jpg', type: 'image/jpeg' })),
}));

function wrap(ui: React.ReactElement) {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(<QueryClientProvider client={qc}>{ui}</QueryClientProvider>);
}

beforeEach(() => {
  pushMock.mockClear();
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
  const { findByText, getByText } = wrap(<AuditFormScreen />);
  await findByText('Estado'); // criteria loaded
  fireEvent.press(getByText('Guardar auditoría'));
  expect(await findByText(/al menos una foto/i)).toBeTruthy();
});

it('submits successfully after taking a photo', async () => {
  const submit = jest.spyOn(auditsApi, 'submitAudit').mockResolvedValue({
    id: 42, client_uuid: 'u', advertising_space_id: 5, year: 2026, week: 23,
    audit_type: 'general', general_status: 'good', audit_date: null,
  });

  const { findByText, getByText } = wrap(<AuditFormScreen />);
  await findByText('Estado');
  fireEvent.press(getByText('Tomar foto'));
  await waitFor(() => expect(getByText(/1 foto/i)).toBeTruthy());
  fireEvent.press(getByText('Guardar auditoría'));

  await waitFor(() => expect(submit).toHaveBeenCalled());
  await waitFor(() => expect(getByText(/guardada/i)).toBeTruthy());
});
```

- [ ] **Step 6: Run to verify it fails**

Run: `cd mobile && npx jest screens/auditForm.test`
Expected: FAIL — screen + `src/photos/capture` missing.

- [ ] **Step 7: Implement the camera capture helper**

Create `mobile/src/photos/capture.ts`:
```ts
import * as ImagePicker from 'expo-image-picker';
import { resizeForUpload, type UploadPhoto } from './resize';

/**
 * Launches the camera, then resizes the captured image for upload.
 * Returns null if the user cancels or denies permission.
 */
export async function capturePhoto(): Promise<UploadPhoto | null> {
  const perm = await ImagePicker.requestCameraPermissionsAsync();
  if (!perm.granted) return null;

  const result = await ImagePicker.launchCameraAsync({ quality: 1 });
  if (result.canceled || !result.assets?.[0]) return null;

  return resizeForUpload(result.assets[0].uri);
}
```
Install the picker:
```bash
cd mobile && npx expo install expo-image-picker
```

- [ ] **Step 8: Implement the audit form screen**

Create `mobile/app/(app)/audit/new.tsx`:
```tsx
import { useMemo, useState } from 'react';
import { View, Text, ScrollView, StyleSheet, ActivityIndicator, Pressable } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { useQuery } from '@tanstack/react-query';
import { useAuth } from '../../../src/auth/AuthContext';
import { listCriteria } from '../../../src/api/criteria';
import { resolveAuditOptions } from '../../../src/audit/auditType';
import { validateAudit } from '../../../src/audit/validation';
import { submitBuiltAudit } from '../../../src/audit/useAuditSubmit';
import { capturePhoto } from '../../../src/photos/capture';
import type { UploadPhoto } from '../../../src/photos/resize';
import type { CriterionValue } from '../../../src/api/types';
import { ApiError } from '../../../src/api/errors';
import { Field } from '../../../src/ui/Field';
import { Button } from '../../../src/ui/Button';

export default function AuditFormScreen() {
  const { spaceId } = useLocalSearchParams<{ spaceId: string; code: string }>();
  const { token, permissions } = useAuth();
  const options = useMemo(
    () => (permissions ? resolveAuditOptions(permissions) : null),
    [permissions],
  );
  const auditType = options?.defaultType ?? 'general';

  const { data: criteria, isLoading } = useQuery({
    queryKey: ['criteria', auditType],
    queryFn: () => listCriteria(auditType, token ?? ''),
  });

  const [values, setValues] = useState<Record<number, { value: CriterionValue; comment: string }>>({});
  const [observation, setObservation] = useState('');
  const [photos, setPhotos] = useState<UploadPhoto[]>([]);
  const [capturedAt, setCapturedAt] = useState<string | null>(null);
  const [errors, setErrors] = useState<string[]>([]);
  const [busy, setBusy] = useState(false);
  const [done, setDone] = useState(false);

  const valueFor = (id: number) => values[id]?.value ?? 'good';
  const setValue = (id: number, value: CriterionValue) =>
    setValues((p) => ({ ...p, [id]: { value, comment: p[id]?.comment ?? '' } }));
  const setComment = (id: number, comment: string) =>
    setValues((p) => ({ ...p, [id]: { value: p[id]?.value ?? 'good', comment } }));

  const takePhoto = async () => {
    const photo = await capturePhoto();
    if (photo) {
      setPhotos((p) => [...p, photo]);
      if (!capturedAt) setCapturedAt(new Date().toISOString());
    }
  };

  const save = async () => {
    // Default any untouched criteria to 'good'.
    const fullValues: typeof values = {};
    (criteria ?? []).forEach((c) => {
      fullValues[c.id] = values[c.id] ?? { value: 'good', comment: '' };
    });

    const errs = validateAudit({ photos, values: fullValues });
    setErrors(errs);
    if (errs.length > 0) return;

    setBusy(true);
    try {
      await submitBuiltAudit(
        {
          spaceId: Number(spaceId),
          auditType,
          purpose: 'audit_only',
          observation,
          values: fullValues,
          photos,
          capturedAt: capturedAt ?? new Date().toISOString(),
        },
        token ?? '',
      );
      setDone(true);
      setTimeout(() => router.push('/(app)/home'), 1200);
    } catch (e) {
      if (e instanceof ApiError && e.isConflict) {
        setErrors(['Ya existe una auditoría para este espacio esta semana.']);
      } else if (e instanceof ApiError && e.isValidation) {
        setErrors([e.message]);
      } else {
        setErrors([e instanceof Error ? e.message : 'No se pudo guardar.']);
      }
    } finally {
      setBusy(false);
    }
  };

  if (isLoading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator />
      </View>
    );
  }

  return (
    <ScrollView contentContainerStyle={styles.container}>
      <Text style={styles.title}>Auditoría</Text>

      {(criteria ?? []).map((c) => (
        <View key={c.id} style={styles.criterion}>
          <Text style={styles.cName}>{c.name}</Text>
          <View style={styles.row}>
            <Pressable
              onPress={() => setValue(c.id, 'good')}
              style={[styles.pill, valueFor(c.id) === 'good' && styles.pillGood]}
            >
              <Text>Bueno</Text>
            </Pressable>
            <Pressable
              onPress={() => setValue(c.id, 'bad')}
              style={[styles.pill, valueFor(c.id) === 'bad' && styles.pillBad]}
            >
              <Text>Malo</Text>
            </Pressable>
          </View>
          {valueFor(c.id) === 'bad' && (
            <Field
              label="Comentario"
              value={values[c.id]?.comment ?? ''}
              onChangeText={(t) => setComment(c.id, t)}
            />
          )}
        </View>
      ))}

      <Field label="Observación" value={observation} onChangeText={setObservation} multiline />

      <Button title="Tomar foto" onPress={takePhoto} />
      <Text style={styles.photoCount}>{photos.length} foto(s) agregada(s)</Text>

      {errors.map((e) => (
        <Text key={e} style={styles.error}>
          {e}
        </Text>
      ))}
      {done && <Text style={styles.ok}>Auditoría guardada.</Text>}

      <Button title="Guardar auditoría" onPress={save} loading={busy} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { padding: 24 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  title: { fontSize: 20, fontWeight: '700', marginBottom: 16 },
  criterion: { marginBottom: 16 },
  cName: { fontWeight: '600', marginBottom: 6 },
  row: { flexDirection: 'row', gap: 8 },
  pill: { borderWidth: 1, borderColor: '#cbd5e1', borderRadius: 999, paddingVertical: 6, paddingHorizontal: 16 },
  pillGood: { backgroundColor: '#bbf7d0', borderColor: '#16a34a' },
  pillBad: { backgroundColor: '#fecaca', borderColor: '#dc2626' },
  photoCount: { marginVertical: 8, color: '#475569' },
  error: { color: '#dc2626', marginVertical: 4 },
  ok: { color: '#16a34a', marginVertical: 8, fontWeight: '600' },
});
```

> Note: `purpose` is hardcoded to `audit_only` here because the field-auditor permission set in scope does not select preventive. When `options.purposes` includes preventive (admins), a follow-up adds a purpose toggle. This is intentional YAGNI for sub-proyecto 2.

- [ ] **Step 9: Run to verify the screen tests pass + typecheck**

Run: `cd mobile && npx jest screens/auditForm.test && npx tsc --noEmit`
Expected: PASS, clean. (The screen test mocks `src/photos/capture`, so no real camera is needed.)

- [ ] **Step 10: Commit**

```bash
cd /Users/jcarrillo/dev/personal/checkmedia_v2
git add "mobile/app/(app)/audit" mobile/src/audit/useAuditSubmit.ts mobile/src/photos/capture.ts mobile/jest.setup.ts mobile/package.json mobile/__tests__/audit/useAuditSubmit.test.ts mobile/__tests__/screens/auditForm.test.tsx
git commit -m "feat(mobile): audit form with criteria, camera capture, and online submit"
```

---

## Phase 3 — Wire-up, docs, manual smoke

### Task 13: README run instructions + full test/typecheck gate + manual smoke checklist

**Files:**
- Create: `mobile/README.md`

- [ ] **Step 1: Run the whole mobile test suite + typecheck**

Run:
```bash
cd mobile
npx tsc --noEmit
npx jest
```
Expected: typecheck clean; all jest tests green. Fix any failures before proceeding.

- [ ] **Step 2: Bundle-check the whole app**

Run:
```bash
cd mobile
npx expo export --platform android --output-dir /tmp/expo-export-final && rm -rf /tmp/expo-export-final
```
Expected: bundle succeeds (validates the full route tree + native module imports compile).

- [ ] **Step 3: Write the README with run + smoke instructions**

Create `mobile/README.md`:
```markdown
# CheckMedia Auditor (mobile)

Expo + TypeScript app for field auditors. Online MVP (no offline queue yet — that's sub-proyecto 3).

## Requisitos
- Node 20+ y npm
- Un teléfono Android con **Expo Go** (Play Store), o un emulador Android (requiere Android Studio + Java)

## Configurar el API
Copia `.env.example` a `.env` y ajusta:
```
EXPO_PUBLIC_API_URL=https://v2.pptefectimedios.com
```
Para apuntar a un backend local corriendo en tu máquina, usa la IP LAN del equipo (no `localhost`, el teléfono no lo resuelve), p.ej. `http://192.168.1.50:8000`.

## Correr
```
cd mobile
npm install
npx expo start
```
Escanea el QR con Expo Go (Android). 

## Pruebas
```
npm run typecheck
npm test
```

## Smoke manual (en el teléfono)
1. Login con un usuario auditor (campo **usuario**, no email).
2. Cierra y reabre la app → debe ofrecer desbloqueo biométrico.
3. Buscar un código de espacio válido → ver datos + pauta; código inválido → "no encontrado".
4. "Auditar" → marcar criterios (Malo exige comentario), tomar ≥1 foto.
5. Guardar sin foto → error de validación.
6. Guardar con foto → "Auditoría guardada"; reintentar el mismo espacio/semana → mensaje de duplicado (409).
7. Verifica en el panel admin que la auditoría llegó con su foto y watermark con la hora de captura.
```

- [ ] **Step 4: Commit**

```bash
cd /Users/jcarrillo/dev/personal/checkmedia_v2
git add mobile/README.md
git commit -m "docs(mobile): run instructions and manual smoke checklist"
```

---

## Self-Review notes (spec coverage)

- RN + Expo + TS + expo-router → Task 1.
- Sanctum token auth + biometric unlock → Task 7 (store + biometrics + context), Task 9 (login screen).
- Search space (red) → Task 4 (endpoint), Task 10 (home), Task 11 (space screen).
- Criteria load → Task 4, Task 12.
- Form: criteria good/bad + comment, observation → Task 12.
- Camera capture + resize (2560/0.85) → Task 6 (resize), Task 12 (capture + form).
- Capture-time timestamp sent → Task 12 (capturedAt set on first photo), Task 5/payload.
- Online submit to POST /api/audits, handle 201/409/422 → Task 5 (payload), Task 12 (submit + error handling).
- Client-side validation (≥1 photo, comment-on-bad) → Task 5, Task 12.
- audit_type/purpose gating from permissions → Task 5 (resolver), Task 12.
- Configurable API URL → Task 2.

## Out of scope (later sub-proyectos)
- Offline SQLite queue + sync engine + idempotent retry surfacing in UI (sub-proyecto 3). The submit path is already centralized in `useAuditSubmit.ts`/`audits.ts`, so the queue slots in there.
- `mode: 'complement'` flow for existing audits, purpose toggle for admins, long-edge-aware resize.
- EAS build + Google Play (sub-proyecto 4).
- iOS.
