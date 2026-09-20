import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, tap } from 'rxjs';
import { RegisterData } from '../models/register-data';

interface LoginData {
  email: string;
  password: string;
}

interface LoginResponse {
  message: string;
  token: string;
  patient: {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
  };
}

const TOKEN_KEY = 'auth_token';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private http = inject(HttpClient);
  private apiUrl = 'http://localhost:8000/api';

  register(data: RegisterData): Observable<unknown> {
    return this.http.post(`${this.apiUrl}/register`, data);
  }

  login(data: LoginData): Observable<LoginResponse> {
    return this.http.post<LoginResponse>(`${this.apiUrl}/login`, data).pipe(
      tap((response) => {
        localStorage.setItem(TOKEN_KEY, response.token);
      }),
    );
  }

  logout(): void {
    localStorage.removeItem(TOKEN_KEY);
  }

  getToken(): string | null {
    return localStorage.getItem(TOKEN_KEY);
  }

  isLoggedIn(): boolean {
    return this.getToken() !== null;
  }
}