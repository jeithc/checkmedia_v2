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

jest.mock('expo-device', () => ({ deviceName: 'test-device' }));

jest.mock('react-native-get-random-values', () => ({}));

jest.mock('react-native-safe-area-context', () =>
  require('react-native-safe-area-context/jest/mock').default,
);

jest.mock('@expo/vector-icons', () => {
  const React = require('react');
  const { View } = require('react-native');
  const makeIcon = () => (props: any) => React.createElement(View, props);
  return {
    Ionicons: makeIcon(),
    MaterialIcons: makeIcon(),
    MaterialCommunityIcons: makeIcon(),
    FontAwesome: makeIcon(),
    Feather: makeIcon(),
  };
}, { virtual: true });

// --- expo-file-system: documentDirectory + copy/delete are no-ops returning paths ---
jest.mock('expo-file-system', () => ({
  documentDirectory: 'file:///doc/',
  copyAsync: jest.fn(async () => {}),
  deleteAsync: jest.fn(async () => {}),
  makeDirectoryAsync: jest.fn(async () => {}),
  getInfoAsync: jest.fn(async () => ({ exists: true })),
}));

// --- netinfo: default connected; tests can override addEventListener/fetch ---
jest.mock('@react-native-community/netinfo', () => ({
  __esModule: true,
  default: {
    addEventListener: jest.fn(() => () => {}),
    fetch: jest.fn(async () => ({ isConnected: true, isInternetReachable: true })),
  },
}));
