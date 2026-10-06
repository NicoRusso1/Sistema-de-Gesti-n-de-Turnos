import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { API_URL } from '../config/api';
import { Specialty, SpecialtyFormData } from '../models/specialty';
@Injectable({ providedIn: 'root' })
export class SpecialtyService {
  private http = inject(HttpClient);
  private apiUrl = `${API_URL}/specialties`;
  // Público: es el listado que usa el flujo de reserva de turnos
  getAll(search = ''): Observable<Specialty[]> {
    let params = new HttpParams();
    if (search) params = params.set('search', search);
    return this.http.get<Specialty[]>(this.apiUrl, { params });
  }
  getById(id: number): Observable<Specialty> {
    return this.http.get<Specialty>(`${this.apiUrl}/${id}`);
  }
  create(data: SpecialtyFormData): Observable<unknown> {
    return this.http.post(this.apiUrl, data);
  }
  update(id: number, data: SpecialtyFormData): Observable<unknown> {
    return this.http.put(`${this.apiUrl}/${id}`, data);
  }
  delete(id: number): Observable<unknown> {
    return this.http.delete(`${this.apiUrl}/${id}`);
  }
}