import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { API_URL } from '../config/api';
import { DoctorSchedule, DoctorScheduleFormData, DoctorSlots } from '../models/doctor-schedule';

@Injectable({
  providedIn: 'root'
})
export class DoctorScheduleService {
  private http = inject(HttpClient);

  getSchedules(doctorId: number): Observable<DoctorSchedule[]> {
    return this.http.get<DoctorSchedule[]>(`${API_URL}/doctors/${doctorId}/schedules`);
  }

  createSchedule(doctorId: number, data: DoctorScheduleFormData): Observable<unknown> {
    return this.http.post(`${API_URL}/doctors/${doctorId}/schedules`, data);
  }

  updateSchedule(doctorId: number, scheduleId: number, data: DoctorScheduleFormData): Observable<unknown> {
    return this.http.put(`${API_URL}/doctors/${doctorId}/schedules/${scheduleId}`, data);
  }

  deleteSchedule(doctorId: number, scheduleId: number): Observable<unknown> {
    return this.http.delete(`${API_URL}/doctors/${doctorId}/schedules/${scheduleId}`);
  }

  getSlots(doctorId: number, date: string): Observable<DoctorSlots> {
    const params = new HttpParams().set('date', date);

    return this.http.get<DoctorSlots>(`${API_URL}/doctors/${doctorId}/slots`, { params });
  }
}