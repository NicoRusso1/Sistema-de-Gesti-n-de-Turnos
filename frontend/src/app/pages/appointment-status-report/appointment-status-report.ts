import { Component, OnDestroy, OnInit, inject, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Subscription } from 'rxjs';
import { API_URL } from '../../config/api';
import { ReporteTurnosPorEstado } from '../../models/appointment-status-report.model';

// GT-67: reporte de turnos por estado (Administrador / Super Administrador)
@Component({
  selector: 'app-appointment-status-report',
  standalone: true,
  imports: [DatePipe, ReactiveFormsModule],
  templateUrl: './appointment-status-report.html',
  styleUrls: ['./appointment-status-report.css'],
})
export class AppointmentStatusReportComponent implements OnInit, OnDestroy {
  private http = inject(HttpClient);
  private fb = inject(FormBuilder);

  // Intervalo del refresco automático
  readonly REFRESH_SECONDS = 30;

  report = signal<ReporteTurnosPorEstado | null>(null);
  loading = signal(false);
  refreshing = signal(false);
  errorMessage = signal<string | null>(null);
  autoRefresh = signal(true);

  // Por defecto: el mes en curso
  filterForm = this.fb.group({
    fecha_desde: [this.toDateInput(new Date(new Date().getFullYear(), new Date().getMonth(), 1))],
    fecha_hasta: [this.toDateInput(new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0))],
    specialty_id: [''],
    doctor_id: [''],
  });

  private timer: ReturnType<typeof setInterval> | null = null;
  private request?: Subscription;

  ngOnInit(): void {
    this.cargar();
    this.startTimer();
  }

  ngOnDestroy(): void {
    this.stopTimer();
    this.request?.unsubscribe();
  }

  aplicarFiltros(): void {
    this.cargar();
  }

  limpiarFiltros(): void {
    this.filterForm.reset({ fecha_desde: '', fecha_hasta: '', specialty_id: '', doctor_id: '' });
    this.cargar();
  }

  toggleAutoRefresh(): void {
    this.autoRefresh.update(v => !v);
    this.autoRefresh() ? this.startTimer() : this.stopTimer();
  }

  // silent: refresco automático, no reemplaza el contenido por el esqueleto de carga
  cargar(silent = false): void {
    const { fecha_desde, fecha_hasta } = this.filterForm.value;
    if (fecha_desde && fecha_hasta && fecha_hasta < fecha_desde) {
      this.errorMessage.set('La fecha "hasta" no puede ser anterior a la fecha "desde".');
      return;
    }

    silent ? this.refreshing.set(true) : this.loading.set(true);
    if (!silent) this.errorMessage.set(null);

    this.request?.unsubscribe();
    this.request = this.http
      .get<ReporteTurnosPorEstado>(`${API_URL}/reportes/turnos-por-estado`, { params: this.buildParams() })
      .subscribe({
        next: (data) => {
          this.report.set(data);
          this.errorMessage.set(null);
          this.loading.set(false);
          this.refreshing.set(false);
        },
        error: (err) => {
          const errors = err.error?.errors as Record<string, string[]> | undefined;
          this.errorMessage.set(
            errors ? Object.values(errors).flat()[0] : 'No se pudo cargar el reporte de turnos.'
          );
          this.loading.set(false);
          this.refreshing.set(false);
        },
      });
  }

  distribucionLabel(r: ReporteTurnosPorEstado): string {
    return 'Distribución: ' + r.estados.map(e => `${e.etiqueta} ${e.porcentaje}%`).join(', ');
  }

  private buildParams(): HttpParams {
    let params = new HttpParams();
    for (const [key, value] of Object.entries(this.filterForm.value)) {
      if (value) params = params.set(key, String(value));
    }
    return params;
  }

  private startTimer(): void {
    this.stopTimer();
    this.timer = setInterval(() => {
      // No se consulta al backend si la pestaña no está visible
      if (document.visibilityState === 'visible' && !this.loading()) {
        this.cargar(true);
      }
    }, this.REFRESH_SECONDS * 1000);
  }

  private stopTimer(): void {
    if (this.timer) {
      clearInterval(this.timer);
      this.timer = null;
    }
  }

  private toDateInput(d: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  }
}
