import { CommonModule } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Appointment, AppointmentScope, AssignableSala } from '../../models/appointment';
import { Prescription } from '../../models/prescription';
import { DoctorSlot } from '../../models/doctor-schedule';
import { AppointmentService } from '../../services/appointment.service';
import { AuthService } from '../../services/auth.service';
import { DialogService } from '../../shared/dialog/dialog.service';
import { DoctorScheduleService } from '../../services/doctor-schedule.service';
import { PrescriptionService } from '../../services/prescription.service';

@Component({
  selector: 'app-appointments-list',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink],
  templateUrl: './appointments-list.html',
  styleUrls: ['./appointments-list.css'],
})
export class AppointmentsListComponent implements OnInit {
  private appointmentService = inject(AppointmentService);
  private scheduleService = inject(DoctorScheduleService);
  private prescriptionService = inject(PrescriptionService);
  private authService = inject(AuthService);
  private dialog = inject(DialogService);
  private fb = inject(FormBuilder);

  currentUser = this.authService.user;
  appointments = signal<Appointment[]>([]);
  upcoming = computed(() => {
    const now = Date.now();
    return this.appointments()
      .filter((a) => new Date(a.scheduled_at).getTime() >= now)
      .sort((a, b) => new Date(a.scheduled_at).getTime() - new Date(b.scheduled_at).getTime());
  });
  past = computed(() => {
    const now = Date.now();
    return this.appointments()
      .filter((a) => new Date(a.scheduled_at).getTime() < now)
      .sort((a, b) => new Date(b.scheduled_at).getTime() - new Date(a.scheduled_at).getTime());
  });
  // Alcance de la agenda resuelto por el backend (own / assigned / all)
  scope = signal<AppointmentScope | null>(null);
  loading = signal<boolean>(false);
  errorMessage = signal<string>('');
  successMessage = signal<string>('');

  // Estados de Modales
  cancellingAppointment = signal<Appointment | null>(null);
  reschedulingAppointment = signal<Appointment | null>(null);
  availableSlots = signal<DoctorSlot[]>([]);
  loadingSlots = signal<boolean>(false);
  actionInProgress = signal<boolean>(false);
  assigningSalaAppointment = signal<Appointment | null>(null);
  assignableSalas = signal<AssignableSala[]>([]);
  loadingSalas = signal<boolean>(false);

  // Formulario de Asignación de Sala (GT-43)
  salaForm = this.fb.group({
    sala_id: ['', [Validators.required]],
  });

  // Formulario de Cancelación
  cancelForm = this.fb.group({
    reason: ['', [Validators.maxLength(255)]],
  });

  // Formulario de Reprogramación
  rescheduleForm = this.fb.group({
    date: ['', [Validators.required]],
    slot: ['', [Validators.required]],
  });

  ngOnInit(): void {
    this.fetchAppointments();
  }

  fetchAppointments(): void {
    this.loading.set(true);
    this.errorMessage.set('');
    this.appointmentService.getAppointments().subscribe({
      next: (res) => {
        this.appointments.set(res.data);
        this.scope.set(res.scope);
        this.loading.set(false);
      },
      error: (err: HttpErrorResponse) => {
        this.appointments.set([]);
        this.scope.set(null);
        this.errorMessage.set(
          err.status === 403
            ? 'No tenés permiso para ver la agenda de turnos. Pedile a un administrador que lo active.'
            : 'Error al cargar la lista de turnos.'
        );
        this.loading.set(false);
      },
    });
  }

  title(): string {
    switch (this.scope()) {
      case 'own':
        return 'Mis Turnos';
      case 'assigned':
        return 'Mi Agenda';
      default:
        return 'Agenda de Turnos';
    }
  }

  // El paciente ya sabe que los turnos son suyos y el médico que son de él
  showPatientColumn(): boolean {
    return this.scope() !== 'own';
  }

  showDoctorColumn(): boolean {
    return this.scope() !== 'assigned';
  }

  isAdmin(): boolean {
    const role = this.currentUser()?.role?.name;
    return role === 'Administrator' || role === 'SuperAdmin';
  }

  // Check if the logged‑in user is a doctor (used for certificate buttons)
  isDoctor(): boolean {
    const type = this.currentUser()?.user_type?.name?.toLowerCase();
    return type === 'doctor';
  }

  canBook(): boolean {
    return this.isAdmin() || this.currentUser()?.user_type?.name === 'patient';
  }

  // GT-43: la Secretaria y el Administrador asignan o cambian la sala
  canAssignSala(app: Appointment): boolean {
    const isSecretary = this.currentUser()?.user_type?.name === 'secretary';
    return (this.isAdmin() || isSecretary) && app.status !== 'cancelado';
  }

  esMedicoDelTurno(app: Appointment): boolean {
    const user = this.currentUser();
    return !!user && this.isDoctor() && user.id === app.doctor_id;
  }

  canIssueCert(app: Appointment): boolean {
    return !app.certificado_url && app.status !== 'cancelado' && (this.isAdmin() || this.esMedicoDelTurno(app));
  }

  canDownloadCert(app: Appointment): boolean {
    const user = this.currentUser();
    if (!user || !app.certificado_url || app.status === 'cancelado') return false;
    return this.isAdmin() || user.id === app.patient_id || this.esMedicoDelTurno(app);
  }

  canManage(app: Appointment): boolean {
    const user = this.currentUser();
    if (!user) return false;
    const isOwnerPatient = user.id === app.patient_id;
    return (this.isAdmin() || isOwnerPatient) && app.status !== 'cancelado' && app.status !== 'completado';
  }

  // --- Flujo Asignar Sala ---
  openSalaModal(app: Appointment): void {
    this.salaForm.reset({ sala_id: '' });
    this.assignableSalas.set([]);
    this.assigningSalaAppointment.set(app);
    this.loadingSalas.set(true);
    this.appointmentService.getAssignableSalas(app.id).subscribe({
      next: (salas) => {
        this.assignableSalas.set(salas);
        this.loadingSalas.set(false);
      },
      error: () => {
        this.assignableSalas.set([]);
        this.loadingSalas.set(false);
      },
    });
  }

  closeSalaModal(): void {
    this.assigningSalaAppointment.set(null);
  }

  submitSala(): void {
    const target = this.assigningSalaAppointment();
    const salaId = Number(this.salaForm.value.sala_id);
    if (!target || !salaId) return;

    this.actionInProgress.set(true);
    this.appointmentService.assignSala(target.id, salaId).subscribe({
      next: (res) => {
        this.actionInProgress.set(false);
        this.closeSalaModal();
        this.successMessage.set(res.message);
        this.fetchAppointments();
      },
      error: (err: HttpErrorResponse) => {
        this.actionInProgress.set(false);
        this.errorMessage.set(err.error?.message || 'Error al asignar la sala.');
      },
    });
  }

  // --- Flujo Cancelar ---
  openCancelModal(app: Appointment): void {
    this.cancelForm.reset();
    this.cancellingAppointment.set(app);
  }

  closeCancelModal(): void {
    this.cancellingAppointment.set(null);
  }

  submitCancellation(): void {
    const target = this.cancellingAppointment();
    if (!target) return;

    this.actionInProgress.set(true);
    const reason = this.cancelForm.value.reason || undefined;

    this.appointmentService.cancelAppointment(target.id, reason).subscribe({
      next: (res) => {
        this.actionInProgress.set(false);
        this.closeCancelModal();
        this.successMessage.set(res.message);
        this.fetchAppointments();
      },
      error: (err: HttpErrorResponse) => {
        this.actionInProgress.set(false);
        this.errorMessage.set(err.error?.message || 'Error al cancelar el turno.');
      },
    });
  }

  // --- Flujo Reprogramar ---
  openRescheduleModal(app: Appointment): void {
    this.rescheduleForm.reset();
    this.availableSlots.set([]);
    this.reschedulingAppointment.set(app);
  }

  closeRescheduleModal(): void {
    this.reschedulingAppointment.set(null);
  }

  onRescheduleDateChange(): void {
    const dateVal = this.rescheduleForm.value.date;
    const target = this.reschedulingAppointment();
    if (!dateVal || !target) return;

    this.loadingSlots.set(true);
    this.scheduleService.getSlots(target.doctor_id, dateVal).subscribe({
      next: (res) => {
        this.availableSlots.set(res.slots);
        this.loadingSlots.set(false);
      },
      error: () => {
        this.availableSlots.set([]);
        this.loadingSlots.set(false);
      },
    });
  }

  submitRescheduling(): void {
    const target = this.reschedulingAppointment();
    if (!target || this.rescheduleForm.invalid) return;

    const { date, slot } = this.rescheduleForm.value;
    const newDateTime = `${date} ${slot}`;

    this.actionInProgress.set(true);
    this.appointmentService.rescheduleAppointment(target.id, { scheduled_at: newDateTime }).subscribe({
      next: (res) => {
        this.actionInProgress.set(false);
        this.closeRescheduleModal();
        this.successMessage.set(res.message);
        this.fetchAppointments();
      },
      error: (err: HttpErrorResponse) => {
        this.actionInProgress.set(false);
        this.errorMessage.set(err.error?.message || 'Error al reprogramar el turno.');
      },
    });
  }
    downloadCert(app: Appointment): void {
    this.appointmentService.downloadCertificate(app.id).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `Certificado_Turno_${app.id}.pdf`;
        a.click();
        window.URL.revokeObjectURL(url);
      },
      error: () => this.dialog.toast('Error al descargar el certificado.', 'error')
    });
  }

  async emitirCert(app: Appointment): Promise<void> {
    const obs = await this.dialog.prompt({ title: 'Ingrese observaciones o diagnóstico para el certificado:', confirmText: 'Emitir', multiline: true });
    if (obs === null) return;

    this.appointmentService.issueCertificate(app.id, obs).subscribe({
      next: (res) => {
        this.dialog.toast('Certificado emitido y enviado por correo.', 'success');
        this.fetchAppointments();
      },
      error: (err) => this.dialog.toast(err.error?.message || 'Error al emitir el certificado.', 'error')
    });
  }

  // --- GT-219: finalizar consulta y receta digital ---
  private static readonly ACTIVE_STATUSES = ['reservado', 'abonado', 'scheduled', 'confirmed'];

  canComplete(app: Appointment): boolean {
    const endOfToday = new Date();
    endOfToday.setHours(23, 59, 59, 999);
    return this.esMedicoDelTurno(app)
      && AppointmentsListComponent.ACTIVE_STATUSES.includes(app.status)
      && new Date(app.scheduled_at).getTime() <= endOfToday.getTime();
  }

  canIssuePrescription(app: Appointment): boolean {
    return this.esMedicoDelTurno(app) && app.status === 'completado';
  }

  canViewPrescriptions(app: Appointment): boolean {
    const user = this.currentUser();
    return !!user && !!app.prescriptions?.length
      && (this.isAdmin() || user.id === app.patient_id || this.esMedicoDelTurno(app));
  }

  canRetryPrescription(rx: Prescription): boolean {
    const user = this.currentUser();
    return !!user && this.isDoctor() && rx.estado_emision === 'fallida' && user.id === rx.doctor_id;
  }

  async completeAppointment(app: Appointment): Promise<void> {
    const ok = await this.dialog.confirm({
      title: 'Finalizar consulta',
      message: 'El turno pasa a "completado" y vas a poder emitir la receta. No se puede deshacer.',
      confirmText: 'Finalizar',
    });
    if (!ok) return;

    this.prescriptionService.completeAppointment(app.id).subscribe({
      next: (res) => {
        this.dialog.toast(res.message, 'success');
        this.fetchAppointments();
      },
      error: (err: HttpErrorResponse) => this.dialog.toast(err.error?.message || 'No se pudo finalizar la consulta.', 'error'),
    });
  }

  async issuePrescription(app: Appointment): Promise<void> {
    const content = await this.dialog.prompt({
      title: 'Emitir receta digital',
      message: 'Medicación e indicaciones para el paciente.',
      placeholder: 'Ej.: Amoxicilina 500 mg — 1 comprimido cada 8 hs durante 7 días.',
      confirmText: 'Emitir receta',
      multiline: true,
      required: true,
    });
    if (content === null || !content.trim()) return;

    this.prescriptionService.issue(app.id, content).subscribe({
      next: () => {
        this.dialog.toast('Receta emitida.', 'success');
        this.fetchAppointments();
      },
      error: (err: HttpErrorResponse) => {
        // 502: la receta quedó guardada como fallida, se reintenta desde la lista
        this.dialog.toast(err.error?.message || 'Error al emitir la receta.', 'error');
        this.fetchAppointments();
      },
    });
  }

  retryPrescription(rx: Prescription): void {
    this.prescriptionService.retry(rx.id).subscribe({
      next: () => {
        this.dialog.toast('Receta generada.', 'success');
        this.fetchAppointments();
      },
      error: (err: HttpErrorResponse) => {
        const detalle = err.error?.error ? ` (${err.error.error})` : '';
        this.dialog.toast((err.error?.message || 'No se pudo generar la receta.') + detalle, 'error');
        this.fetchAppointments();
      },
    });
  }

  viewPrescription(rx: Prescription): void {
    // Se abre la pestaña antes de la llamada para que el navegador no la bloquee
    const tab = window.open('', '_blank');
    this.prescriptionService.view(rx.id).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        if (tab) {
          tab.location.href = url;
        } else {
          window.open(url, '_blank');
        }
      },
      error: () => {
        tab?.close();
        this.dialog.toast('No se pudo abrir la receta.', 'error');
      },
    });
  }

  downloadPrescription(rx: Prescription): void {
    this.prescriptionService.download(rx.id).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `Receta_${rx.id}_Turno_${rx.appointment_id}.pdf`;
        a.click();
        window.URL.revokeObjectURL(url);
      },
      error: () => this.dialog.toast('Error al descargar la receta.', 'error'),
    });
  }
}
