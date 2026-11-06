import { Component, OnInit, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { HealthInsurance } from '../../models/health-insurance';
import { HealthInsuranceService } from '../../services/health-insurance.service';
import { DialogService } from '../../shared/dialog/dialog.service';

@Component({
  selector: 'app-health-insurances',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './health-insurances.html',
})
export class HealthInsurances implements OnInit {
  private fb = inject(FormBuilder);
  private healthInsuranceService = inject(HealthInsuranceService);
  private dialog = inject(DialogService);

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

  protected async deactivate(item: HealthInsurance): Promise<void> {
    if (!(await this.dialog.confirm({ title: `¿Dar de baja a ${item.name}?`, confirmText: 'Dar de baja', variant: 'danger' }))) {
      return;
    }

    this.healthInsuranceService.deactivateHealthInsurance(item.id).subscribe({
      next: () => this.load(),
      error: () => this.dialog.toast('No se pudo dar de baja la obra social', 'error'),
    });
  }

  protected activate(item: HealthInsurance): void {
    this.healthInsuranceService.activateHealthInsurance(item.id).subscribe({
      next: () => this.load(),
      error: () => this.dialog.toast('No se pudo reactivar la obra social', 'error'),
    });
  }
}