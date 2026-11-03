import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { API_URL } from '../config/api';
import { AppNotification, NotificationFilters } from '../models/notification';

@Injectable({
  providedIn: 'root'
})
export class NotificationService {
  private http = inject(HttpClient);

  getMyNotifications(): Observable<AppNotification[]> {
    return this.http.get<AppNotification[]>(`${API_URL}/me/notifications`);
  }

  markAsRead(id: number): Observable<{ message: string }> {
    return this.http.patch<{ message: string }>(`${API_URL}/notifications/${id}/read`, {});
  }

  getAllNotifications(filters: NotificationFilters = {}, page: number = 1): Observable<any> {
    let params = new HttpParams().set('page', page);
    Object.entries(filters).forEach(([key, value]) => {
      if (value) {
        params = params.set(key, value);
      }
    });
    return this.http.get<any>(`${API_URL}/notifications`, { params });
  }

  triggerSendReminders(): Observable<{ message: string; output: string }> {
    return this.http.post<{ message: string; output: string }>(`${API_URL}/notifications/send-reminders`, {});
  }
    deleteNotification(id: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${API_URL}/notifications/${id}`);
  }
}