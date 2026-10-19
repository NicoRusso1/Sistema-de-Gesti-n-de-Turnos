import { CommonModule } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Appointment, AppointmentScope, AssignableSala } from '../../models/appointment';
import { DoctorSlot } from '../../models/doctor-schedule';
import { AppointmentService } from '../../services/appointment.service';
import { AuthService } from '../../services/auth.service';
import { DoctorScheduleService } from '../../services/doctor-schedule.service';

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
  private authService = inject(AuthService);
  private fb = inject(FormBuilder);

  currentUser = this.authService.user;
  appointments = signal<Appointment[]>([]);
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

  canBook(): boolean {
    return this.isAdmin() || this.currentUser()?.user_type?.name === 'patient';
  }

  // GT-43: la Secretaria y el Administrador asignan o cambian la sala
  canAssignSala(app: Appointment): boolean {
    const isSecretary = this.currentUser()?.user_type?.name === 'secretary';
    return (this.isAdmin() || isSecretary) && app.status !== 'cancelado';
  }

  canManage(app: Appointment): boolean {
    const user = this.currentUser();
    if (!user) return false;
    const isOwnerPatient = user.id === app.patient_id;
    return (this.isAdmin() || isOwnerPatient) && app.status !== 'cancelado';
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
}