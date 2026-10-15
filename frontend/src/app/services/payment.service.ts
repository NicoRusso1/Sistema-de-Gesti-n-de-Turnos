import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { API_URL } from '../config/api';
import { PaymentStartResponse, PaymentVerifyResponse } from '../models/payment';

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
}