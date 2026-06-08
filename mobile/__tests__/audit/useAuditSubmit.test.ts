import { buildSubmission } from '../../src/audit/useAuditSubmit';

describe('buildSubmission', () => {
  it('assembles a submission with a generated uuid and capture time', () => {
    const s = buildSubmission({
      spaceId: 5,
      externalCode: '770',
      auditType: 'general',
      purpose: 'audit_only',
      observation: 'obs',
      values: { 7: { value: 'good', comment: '' } },
      photos: [{ uri: 'file://a.jpg', name: 'a.jpg', type: 'image/jpeg' }],
      capturedAt: '2026-06-05T10:00:00.000Z',
    });
    expect(s.spaceId).toBe(5);
    expect(s.mode).toBe('new');
    expect(s.clientUuid).toMatch(/^[0-9a-f-]{36}$/);
    expect(s.capturedAt).toBe('2026-06-05T10:00:00.000Z');
  });
});
