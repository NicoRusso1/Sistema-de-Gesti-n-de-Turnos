import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { PrescriptionService } from '../../services/prescription.service';
import { DialogService } from '../../shared/dialog/dialog.service';
import { RecetaHistorialItem } from '../../models/prescription';

// GT-220: historial de recetas (paciente: las emitidas a su nombre; médico: las que emitió)
@Component({
  selector: 'app-prescriptions-list',
  standalone: true,
  imports: [DatePipe, RouterLink],
  templateUrl: './prescriptions-list.html',
  styleUrls: ['./prescriptions-list.css'],
})
export class PrescriptionsListComponent implements OnInit {
  private prescriptionService = inject(PrescriptionService);
  private dialog = inject(DialogService);
  private auth = inject(AuthService);

  recetas = signal<RecetaHistorialItem[]>([]);
  loading = signal(false);
  errorMessage = signal<string | null>(null);
  busyId = signal<number | null>(null);

  isDoctor = computed(() => this.auth.user()?.user_type?.name === 'doctor');
  isAdmin = computed(() => {
    const role = this.auth.user()?.role?.name;
    return role === 'Administrator' || role === 'SuperAdmin';
  });

  ngOnInit(): void {
    this.cargar();
  }

  cargar(): void {
    this.loading.set(true);
    this.errorMessage.set(null);

    this.prescriptionService.getHistory().subscribe({
      next: (res) => {
        this.recetas.set(res.data);
        this.loading.set(false);
      },
      error: () => {
        this.errorMessage.set('No se pudo obtener el historial de recetas.');
        this.loading.set(false);
      },
    });
  }

  ver(rx: RecetaHistorialItem): void {
    // Se abre la pestaña antes de la llamada para que el navegador no la bloquee
    const tab = window.open('', '_blank');
    this.busyId.set(rx.id);
    this.prescriptionService.view(rx.id).subscribe({
      next: (blob) => {
        this.busyId.set(null);
        const url = window.URL.createObjectURL(blob);
        if (tab) {
          tab.location.href = url;
        } else {
          window.open(url, '_blank');
        }
      },
      error: () => {
        this.busyId.set(null);
        tab?.close();
        this.dialog.toast('No se pudo abrir la receta.', 'error');
      },
    });
  }

  descargar(rx: RecetaHistorialItem): void {
    this.busyId.set(rx.id);
    this.prescriptionService.download(rx.id).subscribe({
      next: (blob) => {
        this.busyId.set(null);
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `Receta_${rx.id}_Turno_${rx.turno_id}.pdf`;
        a.click();
        window.URL.revokeObjectURL(url);
      },
      error: () => {
        this.busyId.set(null);
        this.dialog.toast('Error al descargar la receta.', 'error');
      },
    });
  }

  estadoLabel(rx: RecetaHistorialItem): string {
    return { emitida: 'Emitida', fallida: 'Falló la generación', pendiente: 'Pendiente' }[rx.estado_emision];
  }
}
