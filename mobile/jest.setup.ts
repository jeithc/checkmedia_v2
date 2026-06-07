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
