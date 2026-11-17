import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { API_URL } from '../config/api';
import { AdminActionLogFilters, PaginatedAdminActionLog } from '../models/admin-action-log';

@Injectable({
  providedIn: 'root'
})
export class AuditService {
  private http = inject(HttpClient);

  getAdminActions(filters: AdminActionLogFilters = {}): Observable<PaginatedAdminActionLog> {
    let params = new HttpParams();
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        params = params.set(key, String(value));
      }
    });
    return this.http.get<PaginatedAdminActionLog>(`${API_URL}/auditoria`, { params });
  }
}
