import 'react-native-get-random-values';
import { v4 as uuidv4 } from 'uuid';
import * as auditsApi from '../api/audits';
import { buildAuditFormData, type AuditSubmission } from './payload';
import type { AuditType, AuditPurpose, CriterionValue } from '../api/types';

export interface BuildInput {
  spaceId: number;
  auditType: AuditType;
  purpose: AuditPurpose;
  observation: string;
  values: Record<number, { value: CriterionValue; comment: string }>;
  photos: { uri: string; name: string; type: string }[];
  capturedAt: string;
}

export function buildSubmission(input: BuildInput): AuditSubmission {
  return {
    clientUuid: uuidv4(),
    spaceId: input.spaceId,
    auditType: input.auditType,
    purpose: input.purpose,
    observation: input.observation,
    capturedAt: input.capturedAt,
    mode: 'new',
    values: input.values,
    photos: input.photos,
  };
}

export async function submitBuiltAudit(input: BuildInput, token: string) {
  const submission = buildSubmission(input);
  const form = buildAuditFormData(submission);
  return auditsApi.submitAudit(form, token);
}
