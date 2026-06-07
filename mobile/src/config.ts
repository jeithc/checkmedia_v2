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
