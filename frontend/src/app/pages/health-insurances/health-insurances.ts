import { Component, OnInit, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { HealthInsurance } from '../../models/health-insurance';
import { HealthInsuranceService } from '../../services/health-insurance.service';

@Component({
  selector: 'app-health-insurances',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './health-insurances.html',
})
export class HealthInsurances implements OnInit {
  private fb = inject(FormBuilder);
  private healthInsuranceService = inject(HealthInsuranceService);

  protected readonly healthInsurances = signal<HealthInsurance[]>([]);
  protected readonly errorMessage = signal('');

  protected readonly filterForm = this.fb.nonNullable.group({
    search: '',
    status: '',
  });

  ngOnInit(): void {
    this.load();
  }

  protected load(): void {
    this.errorMessage.set('');

    this.healthInsuranceService.getHealthInsurances(this.filterForm.getRawValue()).subscribe({
      next: (data) => this.healthInsurances.set(data),
      error: () => this.errorMessage.set('No se pudieron cargar las obras sociales'),
    });
  }

  protected clearFilters(): void {
    this.filterForm.reset({ search: '', status: '' });
    this.load();
  }

  protected deactivate(item: HealthInsurance): void {
    if (!confirm(`¿Dar de baja a ${item.name}?`)) {
      return;
    }

    this.healthInsuranceService.deactivateHealthInsurance(item.id).subscribe({
      next: () => this.load(),
      error: () => alert('No se pudo dar de baja la obra social'),
    });
  }

  protected activate(item: HealthInsurance): void {
    this.healthInsuranceService.activateHealthInsurance(item.id).subscribe({
      next: () => this.load(),
      error: () => alert('No se pudo reactivar la obra social'),
    });
  }
}