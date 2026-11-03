import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { roleLabel } from '../../shared/role-label';
import { AuthService } from '../../services/auth.service';
import { NotificationService } from '../../services/notification.service';
import { AppointmentService } from '../../services/appointment.service';
import { AppNotification } from '../../models/notification';
import { Appointment } from '../../models/appointment';

@Component({
  selector: 'app-home',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './home.html',
  styleUrls: ['./home.css']
})
export class Home implements OnInit {
  private authService = inject(AuthService);
  private notificationService = inject(NotificationService);
  private appointmentService = inject(AppointmentService);

  protected readonly today = new Date();
  protected readonly todayLabel = (() => {
    const text = this.today.toLocaleDateString('es-AR', { weekday: 'long', day: 'numeric', month: 'long' }).replace(',', '');
    return text.charAt(0).toUpperCase() + text.slice(1);
  })();
  protected readonly user = this.authService.user;
  protected readonly nextAppointment = signal<Appointment | null>(null);
  protected readonly loadingAppointment = signal<boolean>(false);
  protected readonly notifications = signal<AppNotification[]>([]);
  protected readonly loadingNotifications = signal<boolean>(false);
  protected readonly agendaAppointments = signal<Appointment[]>([]);
  protected readonly upcomingAppointments = signal<Appointment[]>([]);
  protected readonly loadingAgenda = signal<boolean>(false);
  protected readonly manageLinks = [
    { label: 'Usuarios', path: '/users' },
    { label: 'Salas', path: '/salas' },
    { label: 'Obras sociales', path: '/health-insurances' },
    { label: 'Especialidades', path: '/specialties' }
  ];
  protected readonly isAdmin = computed(() => {
    const role = this.user()?.role?.name;
    return role === 'Administrator' || role === 'SuperAdmin';
  });
  protected readonly isPatient = computed(() => this.user()?.user_type?.name === 'patient');
  protected readonly isDoctor = computed(() => this.user()?.user_type?.name === 'doctor');
  protected readonly canAccessNotifications = computed(() => this.isAdmin() || this.isDoctor());
  protected readonly hasOwnAppointments = computed(() => this.isPatient() || this.isDoctor());
  protected readonly quickLinks = computed(() => {
    const admin = this.isAdmin();
    const patient = this.isPatient();
    const doctor = this.isDoctor();
    const agenda = patient ? 'Mis turnos' : doctor ? 'Mi agenda' : 'Agenda de turnos';
    const links: { label: string; path: string }[] = [];
    if (admin || patient) links.push({ label: 'Reservar un turno', path: '/appointments/new' });
    links.push({ label: agenda, path: '/appointments' });
    if (patient) links.push({ label: 'Mi perfil', path: '/profile' });
    if (doctor) links.push({ label: 'Mis pacientes', path: '/my-patients' });
    if (admin || patient) links.push({ label: admin ? 'Gestión de pagos' : 'Mis pagos', path: '/payments' });
    if (admin || doctor) links.push({ label: 'Notificaciones', path: '/notifications' });
    return links.slice(0, 5);
  });
  protected readonly roleLabel = computed(() => roleLabel(this.user()));
  protected readonly todayCount = computed(() => this.agendaAppointments().length);
  protected readonly doctorNext = computed(() => this.upcomingAppointments()[0] ?? null);
  protected readonly unreadCount = computed(() => this.notifications().filter((n) => !n.leido).length);

  ngOnInit(): void {
    this.loadNextAppointment();
    this.loadMyNotifications();
    if (this.isDoctor()) this.loadAgenda();
  }

  loadAgenda(): void {
    const now = new Date();
    const pad = (n: number) => String(n).padStart(2, '0');
    const date = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
    const active = (a: Appointment) => a.status !== 'cancelado' && a.status !== 'cancelled';
    this.loadingAgenda.set(true);
    this.appointmentService.getAppointments({ date }).subscribe({
      next: (res) => {
        const list = res.data
          .filter(active)
          .sort((a, b) => new Date(a.scheduled_at).getTime() - new Date(b.scheduled_at).getTime());
        this.agendaAppointments.set(list);
        this.loadingAgenda.set(false);
      },
      error: () => this.loadingAgenda.set(false)
    });
    this.appointmentService.getAppointments().subscribe({
      next: (res) => {
        const upcoming = res.data
          .filter((a) => active(a) && new Date(a.scheduled_at).getTime() >= Date.now())
          .sort((a, b) => new Date(a.scheduled_at).getTime() - new Date(b.scheduled_at).getTime());
        this.upcomingAppointments.set(upcoming);
      }
    });
  }

  loadNextAppointment(): void {
    this.loadingAppointment.set(true);
    this.appointmentService.getNextAppointment().subscribe({
      next: (app) => {
        this.nextAppointment.set(app);
        this.loadingAppointment.set(false);
      },
      error: () => this.loadingAppointment.set(false)
    });
  }

  loadMyNotifications(): void {
    this.loadingNotifications.set(true);
    this.notificationService.getMyNotifications().subscribe({
      next: (list) => {
        this.notifications.set(list);
        this.loadingNotifications.set(false);
      },
      error: () => this.loadingNotifications.set(false)
    });
  }

  markRead(notification: AppNotification): void {
    this.notificationService.markAsRead(notification.id).subscribe({
      next: () => {
        this.notifications.update((list) =>
          list.map((n) => (n.id === notification.id ? { ...n, leido: true } : n))
        );
      }
    });
  }

  deleteNotif(notification: AppNotification): void {
    this.notificationService.deleteNotification(notification.id).subscribe({
      next: () => {
        this.notifications.update((list) => list.filter((n) => n.id !== notification.id));
      }
    });
  }

  ticketNumber(id: number): string {
    return 'N.º ' + String(id).padStart(6, '0');
  }

  ago(value: string): string | null {
    const minutes = Math.floor((Date.now() - new Date(value).getTime()) / 60000);
    if (minutes < 0 || minutes >= 1440) return null;
    if (minutes < 1) return 'hace un momento';
    if (minutes < 60) return `hace ${minutes} min`;
    return `hace ${Math.floor(minutes / 60)} h`;
  }
}
