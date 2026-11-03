import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { PaymentService } from '../../services/payment.service';
import { AuthService } from '../../services/auth.service';
import { Payment } from '../../models/payment';

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
  private fb = inject(FormBuilder);

  currentUser = this.authService.user;
  payments = signal<Payment[]>([]);
  loading = signal<boolean>(false);
  errorMessage = signal<string>('');

  currentPage = signal<number>(1);
  totalPages = signal<number>(1);
  totalRecords = signal<number>(0);

  filterForm = this.fb.group({
    status: [''],
    fecha_desde: [''],
    fecha_hasta: ['']
  });

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

  private sumByStatus(status: string): number {
    return this.payments()
      .filter((p) => p.status.name === status)
      .reduce((acc, p) => acc + Number(p.amount), 0);
  }

  getStatusLabel(statusName: string): string {
    switch (statusName) {
      case 'approved': return 'Aprobado';
      case 'rejected': return 'Rechazado';
      default: return 'Pendiente';
    }
  }
}