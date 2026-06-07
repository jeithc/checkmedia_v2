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
