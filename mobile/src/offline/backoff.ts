const SCHEDULE = [5_000, 30_000, 120_000, 600_000];

/** Delay before the next attempt given how many attempts already failed. */
export function backoffDelayMs(attempts: number): number {
  const i = Math.min(attempts, SCHEDULE.length - 1);
  return SCHEDULE[i];
}

export function isDue(s: { nextAttemptAt: number }, now: number): boolean {
  return s.nextAttemptAt <= now;
}
