import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterLink } from '@angular/router';
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
  private router = inject(Router);

  protected readonly user = this.authService.user;
  protected readonly nextAppointment = signal<Appointment | null>(null);
  protected readonly loadingAppointment = signal<boolean>(false);
  protected readonly notifications = signal<AppNotification[]>([]);
  protected readonly loadingNotifications = signal<boolean>(false);

  ngOnInit(): void {
    this.loadNextAppointment();
    this.loadMyNotifications();
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

  logout(): void {
    this.authService.logout().subscribe({
      next: () => this.router.navigate(['/login']),
      error: () => {
        this.authService.clearSession();
        this.router.navigate(['/login']);
      },
    });
  }
    deleteNotif(notification: AppNotification): void {
    this.notificationService.deleteNotification(notification.id).subscribe({
      next: () => {
        this.notifications.update((list) => list.filter((n) => n.id !== notification.id));
      }
    });
  }
}