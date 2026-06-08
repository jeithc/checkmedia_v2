/** Humanized relative time, e.g. "hace 5 min", "hace 2 h", "ayer". */
export function humanize(ms: number, now: number = Date.now()): string {
  if (!Number.isFinite(ms)) return '';
  const diff = Math.max(0, now - ms);
  const min = Math.floor(diff / 60000);
  if (min < 1) return 'hace un momento';
  if (min < 60) return `hace ${min} min`;
  const h = Math.floor(min / 60);
  if (h < 24) return `hace ${h} h`;
  const days = Math.floor(h / 24);
  if (days === 1) return 'ayer';
  if (days < 7) return `hace ${days} días`;
  return fullDate(ms);
}

/** Absolute, locale-formatted date+time. */
export function fullDate(ms: number): string {
  if (!Number.isFinite(ms)) return '';
  return new Date(ms).toLocaleString();
}

/** Parse an ISO8601 string to epoch ms (NaN-safe). */
export function isoToMs(iso: string | null | undefined): number {
  if (!iso) return NaN;
  return Date.parse(iso);
}
