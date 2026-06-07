import type { CriterionValue } from '../api/types';

export interface AuditFormState {
  photos: { uri: string }[];
  values: Record<number, { value: CriterionValue; comment: string }>;
}

export function validateAudit(state: Pick<AuditFormState, 'photos' | 'values'>): string[] {
  const errors: string[] = [];
  if (state.photos.length === 0) {
    errors.push('Debe registrar al menos una foto.');
  }
  for (const [, v] of Object.entries(state.values)) {
    if (v.value === 'bad' && v.comment.trim() === '') {
      errors.push('Cada ítem marcado como Malo necesita un comentario.');
      break;
    }
  }
  return errors;
}
