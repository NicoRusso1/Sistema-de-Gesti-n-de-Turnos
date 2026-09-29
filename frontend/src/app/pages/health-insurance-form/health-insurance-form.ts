import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { HealthInsuranceService } from '../../services/health-insurance.service';

@Component({
  selector: 'app-health-insurance-form',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './health-insurance-form.html',
})
export class HealthInsuranceForm implements OnInit {
  private fb = inject(FormBuilder);
  private healthInsuranceService = inject(HealthInsuranceService);
  private route = inject(ActivatedRoute);
  private router = inject(Router);

  private readonly healthInsuranceId = this.route.snapshot.paramMap.get('id');

  protected readonly isEdit = this.healthInsuranceId !== null;
  protected readonly errorMessage = signal('');
  protected readonly loading = signal(false);

  protected readonly form = this.fb.nonNullable.group({
    name: ['', Validators.required],
    discount_percentage: [0, [Validators.required, Validators.min(0), Validators.max(100)]],
    status: [1, Validators.required],
  });

  ngOnInit(): void {
    if (this.isEdit) {
      this.healthInsuranceService.getHealthInsurance(Number(this.healthInsuranceId)).subscribe({
        next: (item) => {
          this.form.patchValue({
            name: item.name,
            discount_percentage: Number(item.discount_percentage),
            status: item.status,
          });
        },
        error: () => this.errorMessage.set('No se pudo cargar la obra social'),
      });
    }
  }

  onSubmit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const data = this.form.getRawValue();

    const payload = {
      name: data.name,
      discount_percentage: Number(data.discount_percentage),
      status: Number(data.status),
    };

    this.loading.set(true);
    this.errorMessage.set('');

    const request = this.isEdit
      ? this.healthInsuranceService.updateHealthInsurance(Number(this.healthInsuranceId), payload)
      : this.healthInsuranceService.createHealthInsurance(payload);

    request.subscribe({
      next: () => this.router.navigate(['/health-insurances']),
      error: (err: HttpErrorResponse) => {
        this.loading.set(false);
        const errors = err.error?.errors;

        if (errors) {
          this.errorMessage.set((Object.values(errors) as string[][]).flat().join(' '));
        } else {
          this.errorMessage.set('No se pudo guardar la obra social');
        }
      },
    });
  }
}