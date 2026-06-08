import { backoffDelayMs, isDue } from '../../src/offline/backoff';

describe('backoff', () => {
  it('grows exponentially and caps', () => {
    expect(backoffDelayMs(0)).toBe(5_000);
    expect(backoffDelayMs(1)).toBe(30_000);
    expect(backoffDelayMs(2)).toBe(120_000);
    expect(backoffDelayMs(3)).toBe(600_000);
    expect(backoffDelayMs(99)).toBe(600_000); // capped
  });

  it('isDue compares nextAttemptAt to now', () => {
    expect(isDue({ nextAttemptAt: 0 }, 1000)).toBe(true);
    expect(isDue({ nextAttemptAt: 2000 }, 1000)).toBe(false);
    expect(isDue({ nextAttemptAt: 1000 }, 1000)).toBe(true);
  });
});
