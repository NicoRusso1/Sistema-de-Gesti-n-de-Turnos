import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { PaymentService } from '../../services/payment.service';
import { AuthService } from '../../services/auth.service';
import { Payment } from '../../models/payment';
import { API_URL } from '../../config/api';

@Component({
  selector: 'app-payments-list',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink],
  templateUrl: './payments-list.html',
  styleUrls: ['./payments-list.css']
})
export class PaymentsListComponent implements OnInit {
  private paymentService = inject(PaymentService);
  private authService = inject(AuthService);
  private http = inject(HttpClient);
  private fb = inject(FormBuilder);

  currentUser = this.authService.user;
  payments = signal<Payment[]>([]);
  loading = signal<boolean>(false);
  errorMessage = signal<string>('');
  successMessage = signal<string>('');

  currentPage = signal<number>(1);
  totalPages = signal<number>(1);
  totalRecords = signal<number>(0);

  // Filtros
  filterForm = this.fb.group({
    status: [''],
    fecha_desde: [''],
    fecha_hasta: ['']
  });

  // Estado del Modal de Pago / Desglose integrado
  selectedPayment = signal<Payment | null>(null);
  showPaymentModal = signal<boolean>(false);
  showDetailModal = signal<boolean>(false);
  processingPayment = signal<boolean>(false);
  paymentError = signal<string | null>(null);

  // Formulario del Checkout integrado
  checkoutForm = this.fb.group({
    cardholderName: ['', [Validators.required, Validators.minLength(3)]],
    paymentMethod: ['tarjeta_credito', Validators.required],
    simulateResult: ['aprobado', Validators.required] // Simulación de pasarela para QA
  });

  // Totales de la página
  totalPaid = computed(() => this.sumByStatus('approved'));
  totalPending = computed(() => this.sumByStatus('pending'));

  ngOnInit(): void {
    this.loadPayments();
  }

  loadPayments(page: number = 1): void {
    this.loading.set(true);
    this.errorMessage.set('');

    const formVal = this.filterForm.value;
    const filters = {
      page,
      status: formVal.status || undefined,
      fecha_desde: formVal.fecha_desde || undefined,
      fecha_hasta: formVal.fecha_hasta || undefined
    };

    this.paymentService.getPayments(filters).subscribe({
      next: (res) => {
        this.payments.set(res.data);
        this.currentPage.set(res.current_page);
        this.totalPages.set(res.last_page);
        this.totalRecords.set(res.total);
        this.loading.set(false);
      },
      error: () => {
        this.errorMessage.set('No se pudo cargar el historial de pagos.');
        this.loading.set(false);
      }
    });
  }

  onFilter(): void {
    this.loadPayments(1);
  }

  resetFilters(): void {
    this.filterForm.reset({ status: '', fecha_desde: '', fecha_hasta: '' });
    this.loadPayments(1);
  }

  cambiarPagina(nuevaPagina: number): void {
    if (nuevaPagina >= 1 && nuevaPagina <= this.totalPages()) {
      this.loadPayments(nuevaPagina);
    }
  }

  // Métodos del Modal de Pago
  openCheckoutModal(payment: Payment): void {
    this.selectedPayment.set(payment);
    this.paymentError.set(null);
    this.checkoutForm.reset({
      cardholderName: `${payment.appointment?.patient?.first_name || ''} ${payment.appointment?.patient?.last_name || ''}`.trim(),
      paymentMethod: 'tarjeta_credito',
      simulateResult: 'aprobado'
    });
    this.showPaymentModal.set(true);
  }

  closeCheckoutModal(): void {
    this.showPaymentModal.set(false);
    this.selectedPayment.set(null);
    this.paymentError.set(null);
  }

  // Modal de Detalle / Auditoría
  openDetailModal(payment: Payment): void {
    this.selectedPayment.set(payment);
    this.showDetailModal.set(true);
  }

  closeDetailModal(): void {
    this.showDetailModal.set(false);
    this.selectedPayment.set(null);
  }

  // Confirmar cobro desde el modal
  confirmarPagoEnSitio(): void {
    const payment = this.selectedPayment();
    if (!payment || this.checkoutForm.invalid) {
      this.checkoutForm.markAllAsTouched();
      return;
    }

    this.processingPayment.set(true);
    this.paymentError.set(null);

    const simulation = this.checkoutForm.value.simulateResult;
    const tokenSeguro = simulation === 'aprobado' ? 'tok_visa_valid' : 'tok_rejected';

    const payload = {
      turno_id: payment.appointment_id,
      token_pago: tokenSeguro,
      metodo_pago: this.checkoutForm.value.paymentMethod
    };

    this.http.post<any>(`${API_URL}/pagos`, payload).subscribe({
      next: (res) => {
        this.processingPayment.set(false);
        this.closeCheckoutModal();
        this.successMessage.set(`¡Pago registrado con éxito! Turno #${payment.appointment_id} abonado.`);
        this.loadPayments(this.currentPage()); // Refrescar grilla
        setTimeout(() => this.successMessage.set(''), 4000);
      },
      error: (err) => {
        this.processingPayment.set(false);
        const motivo = err.error?.motivo || err.error?.message || 'Error al procesar el pago.';
        this.paymentError.set(motivo);
      }
    });
  }

  // Helpers para montos y cálculos
  getMontoBase(p: Payment): number {
    return Number(p.appointment?.monto_base || p.amount || 0);
  }

  getDescuento(p: Payment): number {
    return Number(p.appointment?.monto_descuento || 0);
  }

  getMontoFinal(p: Payment): number {
    return Number(p.appointment?.monto_final || p.amount || 0);
  }

  getNombreObraSocial(p: Payment): string {
    const os = p.appointment?.healthInsurance;
    if (os) {
      return `${os.name} (${os.discount_percentage}%)`;
    }
    return 'Particular';
  }

  private sumByStatus(status: string): number {
    return this.payments()
      .filter((p) => p.status.name === status)
      .reduce((acc, p) => acc + Number(this.getMontoFinal(p)), 0);
  }

  getStatusLabel(statusName: string): string {
    switch (statusName) {
      case 'approved': return 'Aprobado';
      case 'rejected': return 'Rechazado';
      default: return 'Pendiente';
    }
  }
}