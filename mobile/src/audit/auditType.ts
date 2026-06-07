import type { AuditType, AuditPurpose, PermissionFlags } from '../api/types';

export interface AuditOptions {
  types: AuditType[];
  defaultType: AuditType;
  canChooseType: boolean;
  purposes: AuditPurpose[];
}

export function resolveAuditOptions(p: PermissionFlags): AuditOptions {
  let types: AuditType[];
  if (p.can_select_audit_type) {
    types = ['general', 'structural'];
  } else if (p.can_audit_structural && !p.can_audit) {
    types = ['structural'];
  } else {
    types = ['general'];
  }

  const purposes: AuditPurpose[] = p.can_do_preventive
    ? ['audit_only', 'preventive_maintenance']
    : ['audit_only'];

  return {
    types,
    defaultType: types[0],
    canChooseType: types.length > 1,
    purposes,
  };
}
