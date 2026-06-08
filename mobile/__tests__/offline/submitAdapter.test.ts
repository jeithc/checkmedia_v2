import { submissionToOutcome } from '../../src/offline/submitAdapter';
import { ApiError } from '../../src/api/errors';
import * as auditsApi from '../../src/api/audits';
import type { Submission } from '../../src/offline/types';

jest.mock('../../src/api/audits');

const s: Submission = {
  id: 1, clientUuid: 'u1', spaceId: 72, externalCode: '770', auditType: 'general',
  purpose: 'audit_only', mode: 'new', observation: 'obs',
  values: { 5: { value: 'bad', comment: 'roto' } }, capturedAt: 'x',
  status: 'uploading', attempts: 0, permanent: false, nextAttemptAt: 0, lastError: null,
  serverAuditId: null, createdAt: 0,
  photos: [{ id: 1, submissionId: 1, localUri: 'file:///doc/p.jpg', capturedAt: 'x' }],
};

describe('submitAdapter', () => {
  afterEach(() => jest.resetAllMocks());

  it('returns synced with server id on success', async () => {
    (auditsApi.submitAudit as jest.Mock).mockResolvedValue({ id: 555 });
    const out = await submissionToOutcome(s, 'tok');
    expect(out).toEqual({ kind: 'synced', serverAuditId: 555 });
  });

  it('maps 409 to conflict', async () => {
    (auditsApi.submitAudit as jest.Mock).mockRejectedValue(new ApiError(409, 'dup', { audit_id: 7 }));
    const out = await submissionToOutcome(s, 'tok');
    expect(out.kind).toBe('conflict');
  });

  it('maps 422 to permanent', async () => {
    (auditsApi.submitAudit as jest.Mock).mockRejectedValue(new ApiError(422, 'comentario requerido'));
    const out = await submissionToOutcome(s, 'tok');
    expect(out).toEqual({ kind: 'permanent', message: 'comentario requerido' });
  });

  it('maps network/5xx/401 to transient', async () => {
    (auditsApi.submitAudit as jest.Mock).mockRejectedValue(new ApiError(0, 'sin red'));
    const out = await submissionToOutcome(s, 'tok');
    expect(out.kind).toBe('transient');
  });
});
