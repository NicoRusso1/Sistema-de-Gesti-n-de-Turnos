export interface User {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
  phone?: string;
  status: number | null;
  role_id: number;
  user_type_id: number | null;
  role: { id: number; name: string };
  user_type: { id: number; name: string } | null;
  personal_data: { patient_id: number; national_id: string } | null;
  registration_date: string;
}

export interface PaginatedUsers {
  current_page: number;
  data: User[];
  first_page_url: string;
  last_page: number;
  last_page_url: string;
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_page_url: string | null;
  to: number;
  total: number;
}