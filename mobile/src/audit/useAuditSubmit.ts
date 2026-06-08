import 'react-native-get-random-values';
import { v4 as uuidv4 } from 'uuid';
import * as auditsApi from '../api/audits';
import { buildAuditFormData, type AuditSubmission } from './payload';
import type { AuditType, AuditPurpose, CriterionValue } from '../api/types';
import type { NewSubmission } from '../offline/types';

export interface BuildInput {
  spaceId: number;
  externalCode: string;
  auditType: AuditType;
  purpose: AuditPurpose;
  observation: string;
  values: Record<number, { value: CriterionValue; comment: string }>;
  photos: { uri: string; name: string; type: string }[];
  capturedAt: string;
  mode?: 'new' | 'complement';
}

export function buildSubmission(input: BuildInput): AuditSubmission {
  return {
    clientUuid: uuidv4(),
    spaceId: input.spaceId,
    auditType: input.auditType,
    purpose: input.purpose,
    observation: input.observation,
    capturedAt: input.capturedAt,
    mode: input.mode ?? 'new',
    values: input.values,
    photos: input.photos,
  };
}

export function buildNewSubmission(input: BuildInput, persistedPhotoUris: string[]): NewSubmission {
  const base = buildSubmission(input); // gives clientUuid, mode, values, etc.
  return {
    clientUuid: base.clientUuid,
    spaceId: base.spaceId,
    externalCode: input.externalCode,
    auditType: base.auditType,
    purpose: base.purpose,
    mode: base.mode,
    observation: base.observation,
    values: base.values,
    capturedAt: base.capturedAt,
    photos: persistedPhotoUris.map((uri) => ({ localUri: uri, capturedAt: base.capturedAt })),
  };
}

export async function submitBuiltAudit(
  input: BuildInput,
  token: string,
  onProgress?: (fraction: number) => void,
) {
  const submission = buildSubmission(input);
  const form = buildAuditFormData(submission);
  return auditsApi.submitAudit(form, token, onProgress);
}
