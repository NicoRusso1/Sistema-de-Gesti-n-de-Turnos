import { CommonModule } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnDestroy, OnInit, inject, signal } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { Payment } from '../../models/payment';
import { PaymentService } from '../../services/payment.service';

type ViewState = 'checking' | 'waiting' | 'approved' | 'rejected' | 'error';

const POLL_INTERVAL_MS = 5000;
const MAX_ATTEMPTS = 60;

const METHOD_LABELS: Record<string, string> = {
  credit: 'Tarjeta de crédito',
  debit: 'Tarjeta de débito',
  transfer: 'Transferencia'
};

@Component({
  selector: 'app-payment-result',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './payment-result.html',
  styleUrls: ['./payment-result.css']
})
export class PaymentResultComponent implements OnInit, OnDestroy {
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private paymentService = inject(PaymentService);

  protected readonly state = signal<ViewState>('checking');
  protected readonly payment = signal<Payment | null>(null);
  protected readonly checkoutUrl = signal<string | null>(null);
  protected readonly errorMessage = signal('');
  protected readonly retrying = signal(false);

  private paymentId = 0;
  private attempts = 0;
  private timer: ReturnType<typeof setTimeout> | null = null;

  ngOnInit(): void {
    this.route.queryParamMap.subscribe((params) => {
      this.stopPolling();
      this.attempts = 0;
      this.paymentId = Number(params.get('external_reference'));
      this.payment.set(null);
      this.errorMessage.set('');

      if (!this.paymentId) {
        this.state.set('error');
        this.errorMessage.set('No se encontró el pago a verificar.');
        return;
      }

      this.checkoutUrl.set(this.paymentService.getCheckoutUrl(this.paymentId));
      this.state.set('checking');
      this.check();
    });
  }

  ngOnDestroy(): void {
    this.stopPolling();
  }

  protected verifyAgain(): void {
    this.attempts = 0;
    this.check();
  }

  protected retry(): void {
    const appointmentId = this.payment()?.appointment_id;

    if (!appointmentId) {
      return;
    }

    this.retrying.set(true);
    this.errorMessage.set('');

    this.paymentService.startPayment(appointmentId).subscribe({
      next: (res) => {
        this.retrying.set(false);
        this.paymentService.rememberCheckoutUrl(res.payment_id, res.checkout_url);
        this.router.navigate(['/payments/result'], {
          queryParams: { external_reference: res.payment_id }
        });
      },
      error: (err: HttpErrorResponse) => {
        this.retrying.set(false);
        this.errorMessage.set(err.error?.message || 'No se pudo iniciar el pago.');
      }
    });
  }

  protected methodLabel(): string {
    const method = this.payment()?.method?.name;
    return method ? METHOD_LABELS[method] : 'MercadoPago';
  }

  private check(): void {
    this.stopPolling();

    this.paymentService.verifyPayment(this.paymentId).subscribe({
      next: (res) => {
        this.payment.set(res.payment);
        const status = res.payment.status.name;

        if (status === 'approved') {
          this.state.set('approved');
        } else if (status === 'rejected') {
          this.state.set('rejected');
        } else {
          this.state.set('waiting');
          this.schedule();
        }
      },
      error: (err: HttpErrorResponse) => {
        this.state.set('error');
        this.errorMessage.set(err.error?.message || 'No se pudo verificar el pago.');
      }
    });
  }

  private schedule(): void {
    this.attempts++;

    if (this.attempts >= MAX_ATTEMPTS) {
      return;
    }

    this.timer = setTimeout(() => this.check(), POLL_INTERVAL_MS);
  }

  private stopPolling(): void {
    if (this.timer) {
      clearTimeout(this.timer);
      this.timer = null;
    }
  }
}