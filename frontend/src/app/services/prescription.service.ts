import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { API_URL } from '../config/api';
import { Prescription, PrescriptionResponse } from '../models/prescription';

@Injectable({
  providedIn: 'root',
})
export class PrescriptionService {
  private http = inject(HttpClient);

  completeAppointment(appointmentId: number): Observable<{ message: string; status: string }> {
    return this.http.patch<{ message: string; status: string }>(`${API_URL}/appointments/${appointmentId}/complete`, {});
  }

  getByAppointment(appointmentId: number): Observable<Prescription[]> {
    return this.http.get<Prescription[]>(`${API_URL}/appointments/${appointmentId}/prescriptions`);
  }

  issue(appointmentId: number, content: string): Observable<PrescriptionResponse> {
    return this.http.post<PrescriptionResponse>(`${API_URL}/appointments/${appointmentId}/prescriptions`, { content });
  }

  retry(prescriptionId: number): Observable<PrescriptionResponse> {
    return this.http.post<PrescriptionResponse>(`${API_URL}/prescriptions/${prescriptionId}/retry`, {});
  }

  view(prescriptionId: number): Observable<Blob> {
    return this.http.get(`${API_URL}/prescriptions/${prescriptionId}/view`, { responseType: 'blob' });
  }

  download(prescriptionId: number): Observable<Blob> {
    return this.http.get(`${API_URL}/prescriptions/${prescriptionId}/download`, { responseType: 'blob' });
  }
}
