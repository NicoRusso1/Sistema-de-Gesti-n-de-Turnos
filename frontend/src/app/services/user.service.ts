import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { API_URL } from '../config/api';
import { CreateUserData, PaginatedUsers, User, UserFormData, UserPermissions } from '../models/user';


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

    getUser(id: number): Observable<User> {
    return this.http.get<User>(`${API_URL}/users/${id}`);
  }

  createUser(data: CreateUserData): Observable<unknown> {
    return this.http.post(`${API_URL}/users`, data);
  }

  updateUser(id: number, data: UserFormData): Observable<unknown> {
    return this.http.put(`${API_URL}/users/${id}`, data);
  }

  deactivateUser(id: number): Observable<unknown> {
    return this.http.delete(`${API_URL}/users/${id}`);
  }

  activateUser(id: number): Observable<unknown> {
    return this.http.post(`${API_URL}/users/${id}/restore`, {});
  }

    getPermissions(id: number): Observable<UserPermissions> {
    return this.http.get<UserPermissions>(`${API_URL}/users/${id}/permissions`);
  }

  updatePermissions(id: number, data: UserPermissions): Observable<unknown> {
    return this.http.put(`${API_URL}/users/${id}/permissions`, data);
  }

    grantAdmin(id: number): Observable<unknown> {
    return this.http.post(`${API_URL}/users/${id}/admin-role`, {});
  }

  revokeAdmin(id: number): Observable<unknown> {
    return this.http.delete(`${API_URL}/users/${id}/admin-role`);
  }

}