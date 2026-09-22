import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { API_URL } from '../config/api';
import { PaginatedUsers } from '../models/user';

@Injectable({
  providedIn: 'root'
})
export class UserService {
  private http = inject(HttpClient);

  getUsers(filters: {
    role?: string;
    user_type?: string;
    status?: string;
    search?: string;
    page?: number;
  }): Observable<PaginatedUsers> {
    let params = new HttpParams();
    
    if (filters.role) params = params.set('role', filters.role);
    if (filters.user_type) params = params.set('user_type', filters.user_type);
    if (filters.status) params = params.set('status', filters.status);
    if (filters.search) params = params.set('search', filters.search);
    if (filters.page) params = params.set('page', filters.page.toString());

    return this.http.get<PaginatedUsers>(`${API_URL}/users`, { params });
  }
}