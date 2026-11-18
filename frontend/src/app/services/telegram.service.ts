import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { API_URL } from '../config/api';

export interface TelegramStatus {
  linked: boolean;
  available: boolean;
}

@Injectable({
  providedIn: 'root'
})
export class TelegramService {
  private http = inject(HttpClient);
  private apiUrl = `${API_URL}/me/telegram`;

  status(): Observable<TelegramStatus> {
    return this.http.get<TelegramStatus>(this.apiUrl);
  }

  createLink(): Observable<{ url: string }> {
    return this.http.post<{ url: string }>(`${this.apiUrl}/link`, {});
  }

  unlink(): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(this.apiUrl);
  }
}
