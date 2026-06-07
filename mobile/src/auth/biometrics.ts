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
