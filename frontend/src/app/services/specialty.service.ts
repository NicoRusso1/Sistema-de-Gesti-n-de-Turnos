import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { Specialty } from '../models/specialty';

@Injectable({ providedIn: 'root'})
export class SpecialtyService {
  private http = inject(HttpClient);
  private apiUrl = 'http://localhost:8000/api/specialties';

  getAll(): Observable<Specialty[]> {
    return this.http.get<Specialty[]>(this.apiUrl);
  }
}