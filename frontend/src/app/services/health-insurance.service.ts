import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { API_URL } from '../config/api';
import { HealthInsurance, HealthInsuranceFormData } from '../models/health-insurance';

@Injectable({
  providedIn: 'root'
})
export class HealthInsuranceService {
  private http = inject(HttpClient);

  getHealthInsurances(filters: { search?: string; status?: string }): Observable<HealthInsurance[]> {
    let params = new HttpParams();

    if (filters.search) params = params.set('search', filters.search);
    if (filters.status) params = params.set('status', filters.status);

    return this.http.get<HealthInsurance[]>(`${API_URL}/health-insurances`, { params });
  }

  getHealthInsurance(id: number): Observable<HealthInsurance> {
    return this.http.get<HealthInsurance>(`${API_URL}/health-insurances/${id}`);
  }

  createHealthInsurance(data: HealthInsuranceFormData): Observable<unknown> {
    return this.http.post(`${API_URL}/health-insurances`, data);
  }

  updateHealthInsurance(id: number, data: HealthInsuranceFormData): Observable<unknown> {
    return this.http.put(`${API_URL}/health-insurances/${id}`, data);
  }

  deactivateHealthInsurance(id: number): Observable<unknown> {
    return this.http.delete(`${API_URL}/health-insurances/${id}`);
  }

  activateHealthInsurance(id: number): Observable<unknown> {
    return this.http.post(`${API_URL}/health-insurances/${id}/restore`, {});
  }
}