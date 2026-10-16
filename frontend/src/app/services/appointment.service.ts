import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { API_URL } from '../config/api';
import {
  AppointmentCreatePayload,
  AppointmentResponse,
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

  getAppointments(filters: { status?: string; date?: string; doctor_id?: number } = {}): Observable<any> {
    let params = new HttpParams();
    Object.entries(filters).forEach(([key, val]) => {
      if (val !== undefined && val !== null && val !== '') {
        params = params.set(key, String(val));
      }
    });
    return this.http.get<any>(this.endpoint, { params });
  }

  cancelAppointment(id: number, reason?: string): Observable<AppointmentResponse> {
    return this.http.patch<AppointmentResponse>(`${this.endpoint}/${id}/cancel`, {
      reason,
    });
  }

  rescheduleAppointment(id: number, payload: { scheduled_at: string; sala_id?: number | null }): Observable<AppointmentResponse> {
    return this.http.patch<AppointmentResponse>(`${this.endpoint}/${id}/reschedule`, payload);
  }
}