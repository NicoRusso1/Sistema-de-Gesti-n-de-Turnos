import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { API_URL } from '../config/api';
import { AssignedPatient } from '../models/assigned-patient';
@Injectable({
    providedIn: 'root'
})
export class DoctorPatientService {
    private http = inject(HttpClient);
    getMyPatients(search: string): Observable<AssignedPatient[]> {
        let params = new HttpParams();
        if (search) params = params.set('search', search);
        return this.http.get<AssignedPatient[]>(`${API_URL}/medicos/me/pacientes`, { params });
    }
}