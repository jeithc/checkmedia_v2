import { resolveAuditOptions } from '../../src/audit/auditType';
import { validateAudit } from '../../src/audit/validation';
import { buildAuditFormData } from '../../src/audit/payload';
import type { PermissionFlags } from '../../src/api/types';

const perms = (over: Partial<PermissionFlags> = {}): PermissionFlags => ({
  can_audit: true,
  can_audit_structural: false,
  can_select_audit_type: false,
  can_select_purpose: false,
  can_do_preventive: false,
  is_admin: false,
  ...over,
});

describe('resolveAuditOptions', () => {
  it('forces general for a general-only auditor', () => {
    const o = resolveAuditOptions(perms());
    expect(o.types).toEqual(['general']);
    expect(o.defaultType).toBe('general');
    expect(o.canChooseType).toBe(false);
  });

  it('forces structural for a structural-only auditor', () => {
    const o = resolveAuditOptions(perms({ can_audit: false, can_audit_structural: true }));
    expect(o.types).toEqual(['structural']);
    expect(o.defaultType).toBe('structural');
  });

  it('offers both when can_select_audit_type', () => {
    const o = resolveAuditOptions(perms({ can_audit_structural: true, can_select_audit_type: true }));
    expect(o.types).toEqual(['general', 'structural']);
    expect(o.canChooseType).toBe(true);
  });

  it('offers preventive only when can_do_preventive', () => {
    expect(resolveAuditOptions(perms()).purposes).toEqual(['audit_only']);
    expect(resolveAuditOptions(perms({ can_do_preventive: true })).purposes).toEqual([
      'audit_only',
      'preventive_maintenance',
    ]);
  });
});

describe('validateAudit', () => {
  const base = {
    photos: [{ uri: 'file://a.jpg' }],
    values: { 1: { value: 'good' as const, comment: '' } },
  };

  it('passes a good audit with a photo', () => {
    expect(validateAudit(base).length).toBe(0);
  });

  it('requires at least one photo', () => {
    expect(validateAudit({ ...base, photos: [] })).toContain('Debe registrar al menos una foto.');
  });

  it('requires a comment when a value is bad', () => {
    const errs = validateAudit({ ...base, values: { 1: { value: 'bad', comment: '  ' } } });
    expect(errs.some((e) => e.includes('comentario'))).toBe(true);
  });
});

describe('buildAuditFormData', () => {
  it('serializes all fields and photos into FormData', () => {
    const fd = buildAuditFormData({
      clientUuid: '11111111-1111-1111-1111-111111111111',
      spaceId: 5,
      auditType: 'general',
      purpose: 'audit_only',
      observation: 'obs',
      capturedAt: '2026-06-05T10:00:00.000Z',
      mode: 'new',
      values: { 7: { value: 'bad', comment: 'roto' } },
      photos: [{ uri: 'file://a.jpg' }],
    });
    expect(fd.get('client_uuid')).toBe('11111111-1111-1111-1111-111111111111');
    expect(fd.get('space_id')).toBe('5');
    expect(fd.get('audit_type')).toBe('general');
    expect(fd.get('mode')).toBe('new');
    expect(fd.get('values[0][criterion_id]')).toBe('7');
    expect(fd.get('values[0][value]')).toBe('bad');
    expect(fd.get('values[0][comment]')).toBe('roto');
    expect(fd.get('photos[0]')).not.toBeNull();
  });
});
