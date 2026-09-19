import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { RegisterData } from '../models/register-data';

@Injectable({ providedIn: 'root'})
export class AuthService {
  private http = inject(HttpClient);
  private apiUrl = 'http://localhost:8000/api';

    register(data: RegisterData): Observable<unknown> {
        return this.http.post(`${this.apiUrl}/register`, data);
    }
}    