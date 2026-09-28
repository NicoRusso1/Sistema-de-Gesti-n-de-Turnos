export interface AuthUser {
  id: number;
  first_name: string;
  last_name: string;
  is_owner: boolean;
  email: string;
  role: { id: number; name: string };
  user_type: { id: number; name: string } | null;
}

export interface LoginResponse {
  message: string;
  access_token: string;
  token_type: string;
  user: AuthUser;
}
