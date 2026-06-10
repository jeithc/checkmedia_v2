# iOS (TestFlight) Adaptation Plan

> **For agentic workers:** mostly config + ops. Code phases use checkboxes; account/build phases are user-driven and noted as such.

**Goal:** Run the existing Expo app on iOS and distribute it to the audit team via **TestFlight** (no public App Store listing).

**Why it's light:** The app is React Native / Expo — already cross-platform. expo-camera, expo-image-picker, expo-image-manipulator, expo-sqlite, expo-file-system/legacy, expo-secure-store, expo-local-authentication (Face ID), netinfo all support iOS. The work is iOS config, an Apple Developer account, an EAS iOS build, and TestFlight — NOT a rewrite.

**Decisions (fixed):** No Apple account yet → must enroll ($99/yr, ~24–48h). Distribution = TestFlight internal (≤100 testers in the team, **no Beta App Review**); external testers (≤10k via link) need a one-time light Beta App Review.

---

## Phase 0 — iOS config (code; do now, no account needed)

**Files:** `mobile/app.json`, `mobile/app/_layout.tsx`, `mobile/eas.json`

- [ ] **app.json: add iOS bundle identifier** (required to build). Under `ios`:
  ```json
  "ios": {
    "bundleIdentifier": "com.checkmedia.auditor",
    "supportsTablet": false,
    "infoPlist": {
      "NSCameraUsageDescription": "La app usa la cámara para registrar fotos de la auditoría.",
      "NSFaceIDUsageDescription": "Usa Face ID para desbloquear la sesión.",
      "ITSAppUsesNonExemptEncryption": false
    }
  }
  ```
  (`ITSAppUsesNonExemptEncryption:false` skips the export-compliance prompt on every TestFlight upload — we only use HTTPS.)

- [ ] **StatusBar light** for contrast over the red header. In `app/_layout.tsx` change `<StatusBar style="dark" />` → `<StatusBar style="light" />` (white icons; the top of every screen is the brand-red header on both platforms).

- [ ] **eas.json: iOS auto-increment build number.** In the `production` profile add an `ios` block so TestFlight build numbers bump automatically:
  ```json
  "production": {
    "autoIncrement": true,
    "channel": "production",
    "android": { "buildType": "app-bundle" },
    "ios": { }
  }
  ```
  (`autoIncrement` already at profile level covers iOS buildNumber.)

- [ ] **Validate + commit:** `cd mobile && npx expo config --type public` (no errors). Commit: `build(mobile): iOS config — bundle id, light status bar, encryption flag`.

## Phase 1 — Dev-test on iOS now (no Apple account)

- [ ] **Simulator (fastest on this Mac):** `cd mobile && npx expo start` then press `i` (needs Xcode + iOS Simulator installed). Validates layout, camera (simulator camera is limited — use a real device for photos), biometrics (simulator: Features → Face ID → Enrolled).
- [ ] **Real iPhone via Expo Go:** install Expo Go from App Store, scan the QR from `expo start` (same LAN as the dev server). Test login (biometrics), search, save audit with photos, offline queue.
- [ ] Note: Expo Go runs the JS; native modules (sqlite, camera, securestore, local-auth) are bundled in Expo Go for SDK 56, so this is a faithful test. Anything that only breaks in a standalone build surfaces in Phase 3.

## Phase 2 — Apple Developer account (user; ~24–48h)

- [ ] Enroll at developer.apple.com/programs ($99/yr). Individual is fine for TestFlight; **Organization (needs D-U-N-S)** if it must be under "Efectimedios".
- [ ] In App Store Connect, accept agreements (Paid/Free apps agreement) so builds can be uploaded.
- [ ] This phase blocks Phases 3–4. Phases 0–1 proceed without it.

## Phase 3 — EAS iOS build + credentials (after account)

- [ ] `eas login` (already done for Android).
- [ ] Build: `npx eas-cli build -p ios --profile production`.
  - EAS prompts to log into Apple → it auto-creates the **Distribution certificate**, **App ID** (`com.checkmedia.auditor`) and **provisioning profile**. Let EAS manage them (answer Yes).
  - Produces a `.ipa` for the App Store/TestFlight.
- [ ] (Optional) Simulator build for local install without account: `eas build -p ios --profile preview` with `"ios": { "simulator": true }` added to the preview profile.

## Phase 4 — TestFlight

- [ ] **Create the app in App Store Connect** (apps → +): name "CheckMedia Auditor", bundle id `com.checkmedia.auditor`, SKU `checkmedia-auditor`, primary language Spanish.
- [ ] **Submit the build:** `npx eas-cli submit -p ios --latest` → uploads the `.ipa` to App Store Connect. (EAS asks for the Apple ID / app-specific password or API key; or set up an App Store Connect API key — see follow-ups.)
- [ ] **App Privacy** (App Store Connect → App Privacy): declare data collected — Photos (app functionality), audit data, login credentials; not used for tracking; not shared with third parties. Required before TestFlight external; good practice for internal.
- [ ] **Internal testing (no review):** App Store Connect → TestFlight → Internal Testing → add testers by Apple ID (they must be in Users & Access). Build becomes available in minutes via the **TestFlight app** (testers install TestFlight from the App Store, then the app).
- [ ] **External testing (optional, ≤10k):** create an external group + public link; the **first** build needs a one-time **Beta App Review** (lighter than full review, ~1 day). Useful if auditors aren't in the team account.

## Follow-ups / risks

- **App icon must be opaque (no alpha) on iOS** (1024×1024). The current `assets/icon.png` (red + white logo via sips padColor) is effectively opaque; if App Store Connect rejects for transparency, flatten it (regenerate with a guaranteed-opaque red background). Verify after the first build.
- **App-specific password / ASC API key** for `eas submit`: create an App Store Connect API key (Users & Access → Integrations → App Store Connect API) and point eas.json `submit.production.ios.ascApiKeyPath`/`appleId` so future submits are non-interactive.
- **Camera-only**: we use `launchCameraAsync` (no photo-library pick) → only `NSCameraUsageDescription` is needed; do not add unused privacy strings.
- **Production API**: same `extra.apiUrl = https://v2.auditoriaefectimedios.com` — no change for iOS.
- **OTA**: EAS Update already configured; the same `production` channel serves JS updates to both platforms.

## Out of scope
- Public App Store listing/review (not needed for TestFlight internal).
- iPad-optimized layout (`supportsTablet:false`).
- Apple Push Notifications.
