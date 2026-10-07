import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { API_URL } from '../config/api';
import { Specialty } from '../models/specialty';
export interface DoctorOption {
    id: number;
    first_name: string;
    last_name: string;
}
@Injectable({ providedIn: 'root' })
export class DoctorSpecialtyService {
    private http = inject(HttpClient);
    getDoctorSpecialties(doctorId: number): Observable<Specialty[]> {
        return this.http.get<Specialty[]>(`${API_URL}/doctors/${doctorId}/specialties`);
    }
    // Reemplaza todas las especialidades del médico por las seleccionadas
    syncDoctorSpecialties(doctorId: number, specialtyIds: number[]): Observable<unknown> {
        return this.http.put(`${API_URL}/doctors/${doctorId}/specialties`, {
            specialty_ids: specialtyIds,
        });
    }
    // Para el flujo de reserva: médicos activos asignados a una especialidad
    getDoctorsBySpecialty(specialtyId: number): Observable<DoctorOption[]> {
        return this.http.get<DoctorOption[]>(`${API_URL}/specialties/${specialtyId}/doctors`);
    }
}