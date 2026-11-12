import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { HttpClient, HttpParams } from '@angular/common/http';
import { API_URL } from '../../config/api';
import { MetricasGlobales } from '../../models/metrics.model';

@Component({
  selector: 'app-global-metrics',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './global-metrics.html',
  styleUrls: ['./global-metrics.css']
})
export class GlobalMetricsComponent implements OnInit {
  private http = inject(HttpClient);
  private fb = inject(FormBuilder);

  metrics = signal<MetricasGlobales | null>(null);
  loading = signal<boolean>(false);
  exporting = signal<boolean>(false);
  errorMessage = signal<string | null>(null);

  // Filtro de fechas (por defecto: últimos 30 días)
  filterForm = this.fb.group({
    fecha_desde: [this.calcularFechaDiasAtras(30)],
    fecha_hasta: [new Date().toISOString().substring(0, 10)]
  });

  ngOnInit(): void {
    this.cargarMetricas();
  }

  cargarMetricas(): void {
    this.loading.set(true);
    this.errorMessage.set(null);

    const { fecha_desde, fecha_hasta } = this.filterForm.value;
    let params = new HttpParams();
    if (fecha_desde) params = params.set('fecha_desde', fecha_desde);
    if (fecha_hasta) params = params.set('fecha_hasta', fecha_hasta);

    this.http.get<MetricasGlobales>(`${API_URL}/metricas/globales`, { params }).subscribe({
      next: (data) => {
        this.metrics.set(data);
        this.loading.set(false);
      },
      error: () => {
        this.errorMessage.set('No se pudieron cargar las métricas del sistema.');
        this.loading.set(false);
      }
    });
  }

  descargarReporte(formato: 'pdf' | 'csv'): void {
    this.exporting.set(true);
    const { fecha_desde, fecha_hasta } = this.filterForm.value;
    let params = new HttpParams().set('formato', formato);
    if (fecha_desde) params = params.set('fecha_desde', fecha_desde);
    if (fecha_hasta) params = params.set('fecha_hasta', fecha_hasta);

    this.http.get(`${API_URL}/metricas/globales/export`, {
      params,
      responseType: 'blob'
    }).subscribe({
      next: (blob) => {
        this.exporting.set(false);
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `reporte-metricas-${fecha_desde}_al_${fecha_hasta}.${formato}`;
        a.click();
        window.URL.revokeObjectURL(url);
      },
      error: () => {
        this.exporting.set(false);
        alert('Ocurrió un error al exportar el reporte.');
      }
    });
  }

  setPeriodoRapido(dias: number): void {
    this.filterForm.patchValue({
      fecha_desde: this.calcularFechaDiasAtras(dias),
      fecha_hasta: new Date().toISOString().substring(0, 10)
    });
    this.cargarMetricas();
  }

  private calcularFechaDiasAtras(dias: number): string {
    const d = new Date();
    d.setDate(d.getDate() - dias);
    return d.toISOString().substring(0, 10);
  }
}