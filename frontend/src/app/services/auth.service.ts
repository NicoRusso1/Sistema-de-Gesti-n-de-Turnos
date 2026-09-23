import { HttpClient } from '@angular/common/http';
import { Injectable, inject, signal } from '@angular/core';
import { Observable, tap } from 'rxjs';
import { API_URL } from '../config/api';
import { AuthUser, LoginResponse } from '../models/login-response';
import { RegisterData } from '../models/register-data';

const TOKEN_KEY = 'access_token';
const USER_KEY = 'user';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private http = inject(HttpClient);

  readonly token = signal<string | null>(localStorage.getItem(TOKEN_KEY));
  readonly user = signal<AuthUser | null>(this.readStoredUser());

  register(data: RegisterData): Observable<unknown> {
    return this.http.post(`${API_URL}/register`, data);
  }

  login(credentials: { email: string; password: string }): Observable<LoginResponse> {
    return this.http.post<LoginResponse>(`${API_URL}/login`, credentials).pipe(
      tap((res) => {
        localStorage.setItem(TOKEN_KEY, res.access_token);
        localStorage.setItem(USER_KEY, JSON.stringify(res.user));
        this.token.set(res.access_token);
        this.user.set(res.user);
      }),
    );
  }

  logout(): Observable<unknown> {
    return this.http.post(`${API_URL}/logout`, {}).pipe(tap(() => this.clearSession()));
  }

  clearSession(): void {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
    this.token.set(null);
    this.user.set(null);
  }

  isLoggedIn(): boolean {
    return this.token() !== null;
  }

  private readStoredUser(): AuthUser | null {
    try {
      return JSON.parse(localStorage.getItem(USER_KEY) ?? 'null');
    } catch {
      return null;
    }
  }
    forgotPassword(email: string): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${API_URL}/forgot-password`, { email });
  }

  resetPassword(data: { token: string; password: string; password_confirmation: string }): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${API_URL}/reset-password`, data);
  }
}
