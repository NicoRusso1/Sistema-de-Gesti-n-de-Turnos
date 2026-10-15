import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { API_URL } from '../config/api';
import {
  PatientHealthInsuranceResponse,
  PatientHealthInsuranceSaveResponse
} from '../models/patient-health-insurance';

@Injectable({
  providedIn: 'root'
})
export class PatientHealthInsuranceService {
  private http = inject(HttpClient);
  private apiUrl = `${API_URL}/me/health-insurance`;

  get(): Observable<PatientHealthInsuranceResponse> {
    return this.http.get<PatientHealthInsuranceResponse>(this.apiUrl);
  }

  save(payload: { health_insurance_id: number; member_number: string }): Observable<PatientHealthInsuranceSaveResponse> {
    return this.http.put<PatientHealthInsuranceSaveResponse>(this.apiUrl, payload);
  }

  remove(): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(this.apiUrl);
  }
}