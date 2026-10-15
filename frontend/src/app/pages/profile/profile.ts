import { CommonModule } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { HealthInsurance } from '../../models/health-insurance';
import { PatientHealthInsurance } from '../../models/patient-health-insurance';
import { AuthService } from '../../services/auth.service';
import { HealthInsuranceService } from '../../services/health-insurance.service';
import { PatientHealthInsuranceService } from '../../services/patient-health-insurance.service';

@Component({
  selector: 'app-profile',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './profile.html',
  styleUrls: ['./profile.css']
})
export class ProfileComponent implements OnInit {
  private fb = inject(FormBuilder);
  private authService = inject(AuthService);
  private healthInsuranceService = inject(HealthInsuranceService);
  private affiliationService = inject(PatientHealthInsuranceService);

  protected readonly user = this.authService.user;
  protected readonly catalog = signal<HealthInsurance[]>([]);
  protected readonly affiliation = signal<PatientHealthInsurance | null>(null);
  protected readonly loading = signal(true);
  protected readonly editing = signal(false);
  protected readonly saving = signal(false);
  protected readonly deleting = signal(false);
  protected readonly successMessage = signal('');
  protected readonly errorMessage = signal('');

  protected readonly form = this.fb.group({
    health_insurance_id: [null as number | null, Validators.required],
    member_number: ['', [Validators.required, Validators.maxLength(50)]],
  });

  ngOnInit(): void {
    this.healthInsuranceService.getHealthInsurances({ status: '1' }).subscribe({
      next: (list) => this.catalog.set(list),
      error: () => this.errorMessage.set('No se pudo cargar el listado de obras sociales.')
    });

    this.affiliationService.get().subscribe({
      next: (res) => {
        this.affiliation.set(res.affiliation);
        this.loading.set(false);
      },
      error: () => {
        this.loading.set(false);
        this.errorMessage.set('No se pudo cargar tu obra social.');
      }
    });
  }

  protected isInactive(): boolean {
    return this.affiliation()?.health_insurance?.status === 0;
  }

  protected startEditing(): void {
    const current = this.affiliation();

    this.form.reset({
      health_insurance_id: current && !this.isInactive() ? current.health_insurance_id : null,
      member_number: current?.member_number ?? ''
    });

    this.successMessage.set('');
    this.errorMessage.set('');
    this.editing.set(true);
  }

  protected cancelEditing(): void {
    this.editing.set(false);
    this.errorMessage.set('');
  }

  protected save(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const value = this.form.getRawValue();

    this.saving.set(true);
    this.errorMessage.set('');
    this.successMessage.set('');

    this.affiliationService.save({
      health_insurance_id: Number(value.health_insurance_id),
      member_number: value.member_number ?? ''
    }).subscribe({
      next: (res) => {
        this.saving.set(false);
        this.affiliation.set(res.affiliation);
        this.editing.set(false);
        this.successMessage.set(res.message);
      },
      error: (err: HttpErrorResponse) => {
        this.saving.set(false);
        this.errorMessage.set(this.firstError(err, 'No se pudo guardar la obra social.'));
      }
    });
  }

  protected remove(): void {
    if (!confirm('¿Querés eliminar tu obra social?')) {
      return;
    }

    this.deleting.set(true);
    this.errorMessage.set('');
    this.successMessage.set('');

    this.affiliationService.remove().subscribe({
      next: (res) => {
        this.deleting.set(false);
        this.affiliation.set(null);
        this.editing.set(false);
        this.form.reset({ health_insurance_id: null, member_number: '' });
        this.successMessage.set(res.message);
      },
      error: (err: HttpErrorResponse) => {
        this.deleting.set(false);
        this.errorMessage.set(err.error?.message || 'No se pudo eliminar la obra social.');
      }
    });
  }

  private firstError(err: HttpErrorResponse, fallback: string): string {
    const errors = err.error?.errors as Record<string, string[]> | undefined;
    const first = errors ? Object.values(errors)[0]?.[0] : null;
    return first || err.error?.message || fallback;
  }
}