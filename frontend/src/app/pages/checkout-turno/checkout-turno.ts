import { Component, OnInit, inject, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { API_URL } from '../../config/api';
import { PagoResponse } from '../../models/pago.model';

@Component({
  selector: 'app-checkout-turno',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './checkout-turno.html',
  styleUrls: ['./checkout-turno.css']
})
export class CheckoutTurnoComponent implements OnInit {
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private http = inject(HttpClient);
  private fb = inject(FormBuilder);

  turnoId = signal<number | null>(null);
  loading = signal<boolean>(false);
  errorMessage = signal<string | null>(null);
  successMessage = signal<string | null>(null);

  // Desglose de montos calculado
  montoOriginal = signal<number>(15000);
  nombreObraSocial = signal<string | null>(null);
  porcentajeDescuento = signal<number>(0);

  descuentoCalculado = computed(() => {
    return (this.montoOriginal() * this.porcentajeDescuento()) / 100;
  });

  montoFinal = computed(() => {
    return Math.max(0, this.montoOriginal() - this.descuentoCalculado());
  });

  // Formulario reactivo de pago (PCI-DSS: solo simula token seguro)
  checkoutForm = this.fb.group({
    cardholderName: ['', [Validators.required, Validators.minLength(4)]],
    paymentMethod: ['tarjeta_credito', Validators.required],
    simulateResult: ['aprobado', Validators.required] // Para pruebas de QA: aprobado o rechazado
  });

  ngOnInit(): void {
    const id = this.route.snapshot.paramMap.get('id');
    if (id) {
      this.turnoId.set(Number(id));
      this.cargarDetallesTurno(Number(id));
    }
  }

  cargarDetallesTurno(id: number): void {
    this.http.get<any>(`${API_URL}/me/health-insurance`).subscribe({
      next: (res) => {
        if (res?.affiliation?.health_insurance && res.affiliation.health_insurance.status === 1) {
          this.nombreObraSocial.set(res.affiliation.health_insurance.name);
          this.porcentajeDescuento.set(Number(res.affiliation.health_insurance.discount_percentage));
        } else {
          this.nombreObraSocial.set(null);
          this.porcentajeDescuento.set(0);
        }
      },
      error: () => {
        this.nombreObraSocial.set(null);
        this.porcentajeDescuento.set(0);
      }
    });
  }

  confirmarPago(): void {
    if (this.checkoutForm.invalid || !this.turnoId()) {
      this.checkoutForm.markAllAsTouched();
      return;
    }

    this.loading.set(true);
    this.errorMessage.set(null);
    this.successMessage.set(null);

    const simulation = this.checkoutForm.value.simulateResult;
    const tokenSeguro = simulation === 'aprobado' ? 'tok_visa_valid_test' : 'tok_rejected';

    const payload = {
      turno_id: this.turnoId(),
      token_pago: tokenSeguro,
      metodo_pago: this.checkoutForm.value.paymentMethod
    };

    this.http.post<PagoResponse>(`${API_URL}/pagos`, payload).subscribe({
      next: (res) => {
        this.loading.set(false);
        this.successMessage.set(`¡Pago registrado con éxito! Código: ${res.pago.transaccion_id || res.pago.id}`);
        setTimeout(() => this.router.navigate(['/appointments']), 2500);
      },
      error: (err) => {
        this.loading.set(false);
        const errorMsg = err.error?.motivo || err.error?.message || 'Error al procesar el pago.';
        this.errorMessage.set(errorMsg);
      }
    });
  }
}