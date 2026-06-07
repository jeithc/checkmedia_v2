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
  city: string | null;
  location_name: string | null;
  address: string | null;
  zone: string | null;
  provider: string | null;
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

export interface AuditValueDetail {
  criterion_id: number;
  name: string | null;
  key: string | null;
  value: CriterionValue;
  comment: string | null;
}

export interface AuditPhotoDetail {
  id: number;
  url: string;
}

export interface AuditDetail {
  id: number;
  advertising_space_id: number;
  year: number;
  week: number;
  audit_type: AuditType;
  audit_purpose: string;
  general_status: string;
  observation: string | null;
  audit_date: string | null;
  has_open_maintenance: boolean;
  values: AuditValueDetail[];
  photos: AuditPhotoDetail[];
}
