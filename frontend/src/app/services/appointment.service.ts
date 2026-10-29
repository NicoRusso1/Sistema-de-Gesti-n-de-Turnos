import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { API_URL } from '../config/api';
import {
  Appointment,
  AppointmentCreatePayload,
  AppointmentFilters,
  AppointmentResponse,
  AssignableSala,
  PaginatedAppointments,
} from '../models/appointment';

@Injectable({
  providedIn: 'root',
})
export class AppointmentService {
  private http = inject(HttpClient);
  private endpoint = `${API_URL}/appointments`;

  createAppointment(payload: AppointmentCreatePayload): Observable<AppointmentResponse> {
    return this.http.post<AppointmentResponse>(this.endpoint, payload);
  }

  getAppointments(filters: AppointmentFilters = {}): Observable<PaginatedAppointments> {
    let params = new HttpParams();
    Object.entries(filters).forEach(([key, val]) => {
      if (val !== undefined && val !== null && val !== '') {
        params = params.set(key, String(val));
      }
    });
    return this.http.get<PaginatedAppointments>(this.endpoint, { params });
  }

  cancelAppointment(id: number, reason?: string): Observable<AppointmentResponse> {
    return this.http.patch<AppointmentResponse>(`${this.endpoint}/${id}/cancel`, {
      reason,
    });
  }

  rescheduleAppointment(id: number, payload: { scheduled_at: string; sala_id?: number | null }): Observable<AppointmentResponse> {
    return this.http.patch<AppointmentResponse>(`${this.endpoint}/${id}/reschedule`, payload);
  }

  getAssignableSalas(id: number): Observable<AssignableSala[]> {
    return this.http.get<AssignableSala[]>(`${this.endpoint}/${id}/available-salas`);
  }

  assignSala(id: number, salaId: number): Observable<AppointmentResponse> {
    return this.http.patch<AppointmentResponse>(`${this.endpoint}/${id}/sala`, { sala_id: salaId });
  }

  getNextAppointment(): Observable<Appointment | null> {
    return this.http.get<Appointment | null>(`${API_URL}/me/next-appointment`);
  }

    issueCertificate(id: number, observaciones?: string): Observable<any> {
    return this.http.post<any>(`${this.endpoint}/${id}/certificate`, { observaciones });
  }

  downloadCertificate(id: number): Observable<Blob> {
    return this.http.get(`${this.endpoint}/${id}/certificate/download`, {
      responseType: 'blob'
    });
  }
}