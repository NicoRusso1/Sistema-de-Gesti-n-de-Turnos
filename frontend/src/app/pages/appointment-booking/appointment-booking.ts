import { CommonModule } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Appointment } from '../../models/appointment';
import { DoctorSlot } from '../../models/doctor-schedule';
import { HealthInsurance } from '../../models/health-insurance';
import { Specialty } from '../../models/specialty';
import { AppointmentService } from '../../services/appointment.service';
import { DoctorScheduleService } from '../../services/doctor-schedule.service';
import { DoctorOption, DoctorSpecialtyService } from '../../services/doctor-specialty.service';
import { HealthInsuranceService } from '../../services/health-insurance.service';
import { SpecialtyService } from '../../services/specialty.service';
import { PaymentService } from '../../services/payment.service';

@Component({
  selector: 'app-appointment-booking',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink],
  templateUrl: './appointment-booking.html',
  styleUrls: ['./appointment-booking.css']
})
export class AppointmentBookingComponent implements OnInit {
  private fb = inject(FormBuilder);
  private specialtyService = inject(SpecialtyService);
  private doctorSpecialtyService = inject(DoctorSpecialtyService);
  private scheduleService = inject(DoctorScheduleService);
  private healthInsuranceService = inject(HealthInsuranceService);
  private appointmentService = inject(AppointmentService);
  private router = inject(Router);
  private paymentService = inject(PaymentService);

  // Estados reactivos con Signals
  protected readonly specialties = signal<Specialty[]>([]);
  protected readonly doctors = signal<DoctorOption[]>([]);
  protected readonly slots = signal<DoctorSlot[]>([]);
  protected readonly healthInsurances = signal<HealthInsurance[]>([]);
  protected readonly paying = signal(false);
  
  protected readonly loadingDoctors = signal(false);
  protected readonly loadingSlots = signal(false);
  protected readonly submitting = signal(false);
  protected readonly errorMessage = signal('');
  protected readonly successAppointment = signal<Appointment | null>(null);

  protected readonly selectedSlot = signal<DoctorSlot | null>(null);

  // GT-37: Rango de fechas permitido (Hoy hasta 2 años en el futuro)
  protected readonly minDate = this.formatDate(new Date());
  protected readonly maxDate = this.calculateMaxDate(2);

  // Monto base predeterminado de la consulta médica
  protected readonly MONTO_BASE_DEFAULT = 15000;

  // Formulario Reactivo
  protected readonly form = this.fb.group({
    specialty_id: [null as number | null, Validators.required],
    doctor_id: [{ value: null as number | null, disabled: true }, Validators.required],
    date: ['', [Validators.required, this.dateRangeValidator.bind(this)]],
    health_insurance_id: [null as number | null],
    affiliate_number: [''],
  });

  // Señal calculada para la obra social seleccionada
  protected readonly selectedInsurance = computed(() => {
    const id = this.form.get('health_insurance_id')?.value;
    if (!id) return null;
    return this.healthInsurances().find(hi => hi.id === Number(id)) ?? null;
  });

  // GT-38 / GT-40: Desglose interactivo en tiempo real
  protected readonly pricing = computed(() => {
    const insurance = this.selectedInsurance();
    const discountPercent = insurance ? Number(insurance.discount_percentage) : 0;
    const montoBase = this.MONTO_BASE_DEFAULT;
    const montoDescuento = Math.round((montoBase * discountPercent) / 100);
    const montoFinal = Math.max(0, montoBase - montoDescuento);

    return {
      montoBase,
      discountPercent,
      montoDescuento,
      montoFinal
    };
  });

  ngOnInit(): void {
    this.loadSpecialties();
    this.loadHealthInsurances();
    this.setupFormSubscriptions();
  }

  private loadSpecialties(): void {
    this.specialtyService.getAll().subscribe({
      next: (list) => this.specialties.set(list),
      error: () => this.errorMessage.set('No se pudieron cargar las especialidades')
    });
  }

  private loadHealthInsurances(): void {
    this.healthInsuranceService.getHealthInsurances({ status: '1' }).subscribe({
      next: (list) => this.healthInsurances.set(list),
      error: () => this.errorMessage.set('No se pudieron cargar las obras sociales')
    });
  }

  private setupFormSubscriptions(): void {
    // Cuando cambia la especialidad, buscamos los médicos que la ejercen
    this.form.get('specialty_id')?.valueChanges.subscribe((specialtyId) => {
      this.doctors.set([]);
      this.slots.set([]);
      this.selectedSlot.set(null);
      this.form.get('doctor_id')?.reset();

      if (specialtyId) {
        this.loadingDoctors.set(true);
        this.doctorSpecialtyService.getDoctorsBySpecialty(Number(specialtyId)).subscribe({
          next: (docs) => {
            this.doctors.set(docs);
            this.form.get('doctor_id')?.enable();
            this.loadingDoctors.set(false);
          },
          error: () => {
            this.loadingDoctors.set(false);
            this.errorMessage.set('Error al cargar médicos de esta especialidad');
          }
        });
      } else {
        this.form.get('doctor_id')?.disable();
      }
    });

    // Cuando cambia el médico o la fecha, consultamos la disponibilidad (slots)
    this.form.get('doctor_id')?.valueChanges.subscribe(() => this.fetchSlots());
    this.form.get('date')?.valueChanges.subscribe(() => this.fetchSlots());

    // GT-40: Si selecciona obra social, el número de afiliado es obligatorio
    this.form.get('health_insurance_id')?.valueChanges.subscribe((insuranceId) => {
      const affiliateCtrl = this.form.get('affiliate_number');
      if (insuranceId) {
        affiliateCtrl?.setValidators([Validators.required, Validators.maxLength(50)]);
      } else {
        affiliateCtrl?.clearValidators();
        affiliateCtrl?.setValue('');
      }
      affiliateCtrl?.updateValueAndValidity();
    });
  }

  // GT-34 / GT-35: Consulta de franjas horarias al Backend
  protected fetchSlots(): void {
    const doctorId = this.form.get('doctor_id')?.value;
    const date = this.form.get('date')?.value;

    this.slots.set([]);
    this.selectedSlot.set(null);

    if (!doctorId || !date || this.form.get('date')?.invalid) {
      return;
    }

    this.loadingSlots.set(true);
    this.errorMessage.set('');

    this.scheduleService.getSlots(Number(doctorId), date).subscribe({
      next: (res) => {
        this.slots.set(res.slots);
        this.loadingSlots.set(false);
      },
      error: (err: HttpErrorResponse) => {
        this.loadingSlots.set(false);
        this.errorMessage.set(err.error?.message || 'Error al obtener horarios del médico');
      }
    });
  }

  protected selectSlot(slot: DoctorSlot): void {
    this.selectedSlot.set(slot);
    this.errorMessage.set('');
  }

  // GT-39: Confirmar Reserva
  protected confirmBooking(): void {
    if (this.form.invalid || !this.selectedSlot()) {
      this.form.markAllAsTouched();
      if (!this.selectedSlot()) {
        this.errorMessage.set('Por favor selecciona un horario disponible.');
      }
      return;
    }

    const val = this.form.getRawValue();
    const scheduledAt = `${val.date} ${this.selectedSlot()?.start_time}`;

    const payload = {
      doctor_id: Number(val.doctor_id),
      specialty_id: val.specialty_id ? Number(val.specialty_id) : null,
      scheduled_at: scheduledAt,
      health_insurance_id: val.health_insurance_id ? Number(val.health_insurance_id) : null,
      affiliate_number: val.affiliate_number || null,
      monto_base: this.pricing().montoBase
    };

    this.submitting.set(true);
    this.errorMessage.set('');

    this.appointmentService.createAppointment(payload).subscribe({
      next: (res) => {
        this.submitting.set(false);
        this.successAppointment.set(res.appointment);
      },
      error: (err: HttpErrorResponse) => {
        this.submitting.set(false);
        if (err.status === 409) {
          this.errorMessage.set('El horario ya fue ocupado. Por favor elige otro.');
          this.fetchSlots();
        } else {
          this.errorMessage.set(err.error?.message || 'Error al registrar el turno.');
        }
      }
    });
  }

  protected payNow(appointment: Appointment): void {
    this.paying.set(true);
    this.errorMessage.set('');

    this.paymentService.startPayment(appointment.id).subscribe({
      next: (res) => {
        this.paying.set(false);
        this.paymentService.rememberCheckoutUrl(res.payment_id, res.checkout_url);
        this.router.navigate(['/payments/result'], {
          queryParams: { external_reference: res.payment_id }
        });
      },
      error: (err: HttpErrorResponse) => {
        this.paying.set(false);
        this.errorMessage.set(err.error?.message || 'No se pudo iniciar el pago.');
      }
    });
  }

  protected resetBooking(): void {
    this.successAppointment.set(null);
    this.selectedSlot.set(null);
    this.slots.set([]);
    this.form.reset();
  }

  // GT-37: Validador de rango de fecha
  private dateRangeValidator(control: { value: string }) {
    if (!control.value) return null;
    if (control.value < this.minDate) {
      return { pastDate: true };
    }
    if (control.value > this.maxDate) {
      return { futureDateExceeded: true };
    }
    return null;
  }

  private formatDate(date: Date): string {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }

  private calculateMaxDate(years: number): string {
    const d = new Date();
    d.setFullYear(d.getFullYear() + years);
    return this.formatDate(d);
  }
}