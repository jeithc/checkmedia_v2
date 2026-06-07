export interface PermissionFlags {
  can_audit: boolean;
  can_audit_structural: boolean;
  can_select_audit_type: boolean;
  can_select_purpose: boolean;
  can_do_preventive: boolean;
  is_admin: boolean;
}

export interface ApiUser {
  id: number;
  name: string;
  username: string;
}

export interface LoginResponse {
  token: string;
  user: ApiUser;
  permissions: PermissionFlags;
}

export interface Booking {
  id: number;
  client_name: string | null;
  contract_code: string | null;
  product_name: string | null;
}

export interface SpaceSearchResult {
  id: number;
  external_code: string;
  type: string | null;
  duplicate: boolean;
  existing_audit_id: number | null;
  booking: Booking | null;
}

export interface Criterion {
  id: number;
  name: string;
  key: string;
}

export type AuditType = 'general' | 'structural';
export type AuditPurpose = 'audit_only' | 'preventive_maintenance';
export type CriterionValue = 'good' | 'bad';

export interface Audit {
  id: number;
  client_uuid: string | null;
  advertising_space_id: number;
  year: number;
  week: number;
  audit_type: AuditType;
  general_status: string;
  audit_date: string | null;
}
