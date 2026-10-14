import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { API_URL } from '../config/api';
import { AppointmentCreatePayload, AppointmentResponse } from '../models/appointment';

@Injectable({
  providedIn: 'root'
})
export class AppointmentService {
  private http = inject(HttpClient);
  private apiUrl = `${API_URL}/appointments`;

  createAppointment(payload: AppointmentCreatePayload): Observable<AppointmentResponse> {
    return this.http.post<AppointmentResponse>(this.apiUrl, payload);
  }
}