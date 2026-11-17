export type AdminActionName =
  | 'grant_owner'
  | 'revoke_owner'
  | 'activate'
  | 'deactivate'
  | 'grant_admin'
  | 'revoke_admin';

export interface AdminActionLogFilters {
  action?: AdminActionName | '';
  affected_admin_id?: number | null;
  date_from?: string;
  date_to?: string;
  per_page?: number;
  page?: number;
}

export interface AdminActionLogEntry {
  id: number;
  date: string;
  action: {
    name: AdminActionName;
    label: string;
  };
  actor: {
    id: number;
    name: string;
  };
  affected: {
    id: number;
    name: string;
  };
}

export interface PaginatedAdminActionLog {
  data: AdminActionLogEntry[];
  links: {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
  };
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
  };
}
