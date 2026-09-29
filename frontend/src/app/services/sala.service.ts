import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { API_URL } from '../config/api';
import { Sala, SalaDisponibilidad, SalaFormData } from '../models/sala';

@Injectable({
  providedIn: 'root'
})
export class SalaService {
  private http = inject(HttpClient);

  getSalas(filters: { search?: string; estado?: string; piso?: string }): Observable<Sala[]> {
    let params = new HttpParams();
    if (filters.search) params = params.set('search', filters.search);
    if (filters.estado) params = params.set('estado', filters.estado);
    if (filters.piso) params = params.set('piso', filters.piso);

    return this.http.get<Sala[]>(`${API_URL}/salas`, { params });
  }

  getSala(id: number): Observable<Sala> {
    return this.http.get<Sala>(`${API_URL}/salas/${id}`);
  }

  createSala(data: SalaFormData): Observable<{ message: string; sala: Sala }> {
    return this.http.post<{ message: string; sala: Sala }>(`${API_URL}/salas`, data);
  }

  updateSala(id: number, data: SalaFormData): Observable<{ message: string; sala: Sala }> {
    return this.http.put<{ message: string; sala: Sala }>(`${API_URL}/salas/${id}`, data);
  }

  cambiarEstado(id: number, estado: 'disponible' | 'fuera_de_servicio'): Observable<unknown> {
    return this.http.patch(`${API_URL}/salas/${id}/estado`, { estado });
  }

  deleteSala(id: number): Observable<unknown> {
    return this.http.delete(`${API_URL}/salas/${id}`);
  }

  getDisponibilidad(filters: { fecha?: string; franja_horaria?: string; especialidad_id?: string }): Observable<SalaDisponibilidad[]> {
    let params = new HttpParams();
    if (filters.fecha) params = params.set('fecha', filters.fecha);
    if (filters.franja_horaria) params = params.set('franja_horaria', filters.franja_horaria);
    if (filters.especialidad_id) params = params.set('especialidad_id', filters.especialidad_id);

    return this.http.get<SalaDisponibilidad[]>(`${API_URL}/salas/disponibilidad`, { params });
  }
}