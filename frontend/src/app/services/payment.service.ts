import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { API_URL } from '../config/api';
import { PaymentStartResponse, PaymentVerifyResponse } from '../models/payment';
import { HttpParams } from '@angular/common/http';
import { PaginatedPayments, PaymentFilters } from '../models/payment';

@Injectable({
  providedIn: 'root'
})
export class PaymentService {
  private http = inject(HttpClient);

  startPayment(appointmentId: number): Observable<PaymentStartResponse> {
    return this.http.post<PaymentStartResponse>(`${API_URL}/appointments/${appointmentId}/payments`, {});
  }

  verifyPayment(paymentId: number): Observable<PaymentVerifyResponse> {
    return this.http.post<PaymentVerifyResponse>(`${API_URL}/payments/${paymentId}/verify`, {});
  }

  rememberCheckoutUrl(paymentId: number, url: string): void {
    sessionStorage.setItem(`checkout_${paymentId}`, url);
  }

  getCheckoutUrl(paymentId: number): string | null {
    return sessionStorage.getItem(`checkout_${paymentId}`);
  }
  
   getPayments(filters?: PaymentFilters): Observable<PaginatedPayments> {
    let params = new HttpParams();
    if (filters) {
      if (filters.status) params = params.set('status', filters.status);
      if (filters.fecha_desde) params = params.set('fecha_desde', filters.fecha_desde);
      if (filters.fecha_hasta) params = params.set('fecha_hasta', filters.fecha_hasta);
      if (filters.patient_id) params = params.set('patient_id', filters.patient_id.toString());
      if (filters.page) params = params.set('page', filters.page.toString());
      if (filters.per_page) params = params.set('per_page', filters.per_page.toString());
    }
    return this.http.get<PaginatedPayments>(`${API_URL}/pagos`, { params });
  }
}