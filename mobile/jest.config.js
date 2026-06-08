module.exports = {
  preset: 'jest-expo',
  setupFilesAfterEnv: ['<rootDir>/jest.setup.ts'],
  // Underscore-prefixed files under __tests__ are shared helpers/fixtures, not suites.
  testPathIgnorePatterns: ['/node_modules/', '/__tests__/.*/_[^/]+$'],
  transformIgnorePatterns: [
    'node_modules/(?!((jest-)?react-native|@react-native(-community)?|expo(nent)?|@expo(nent)?/.*|@expo-google-fonts/.*|react-navigation|@react-navigation/.*|@unimodules/.*|unimodules|sentry-expo|native-base|react-native-svg|expo-router|@tanstack/.*|uuid))',
  ],
};
