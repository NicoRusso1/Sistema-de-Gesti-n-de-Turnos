import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { NotificationService } from '../../services/notification.service';
import { AuthService } from '../../services/auth.service';
import { AppNotification, NotificationFilters } from '../../models/notification';

@Component({
  selector: 'app-notifications',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './notifications.html',
  styleUrls: ['./notifications.css']
})
export class NotificationsComponent implements OnInit {
  private notificationService = inject(NotificationService);
  private authService = inject(AuthService);

  notifications = signal<AppNotification[]>([]);
  filters: NotificationFilters = { estado: '', tipo: '', desde: '', hasta: '', paciente: '' };
  tipos = ['cancelacion', 'reprogramacion', 'recordatorio', 'confirmacion', 'certificado'];
  loading = signal<boolean>(false);
  sending = signal<boolean>(false);
  messageSuccess = signal<string>('');
  messageError = signal<string>('');
  retrying = signal<number | null>(null);

  ngOnInit(): void {
    this.loadAll();
  }

  loadAll(): void {
    this.loading.set(true);
    this.notificationService.getAllNotifications(this.filters).subscribe({
      next: (res) => {
        this.notifications.set(res.data || res);
        this.loading.set(false);
      },
      error: () => {
        this.loading.set(false);
        this.messageError.set('No se pudo cargar el historial de notificaciones.');
      }
    });
  }

  clearFilters(): void {
    this.filters = { estado: '', tipo: '', desde: '', hasta: '', paciente: '' };
    this.loadAll();
  }

  onTriggerReminders(): void {
    this.sending.set(true);
    this.messageSuccess.set('');
    this.messageError.set('');

    this.notificationService.triggerSendReminders().subscribe({
      next: (res) => {
        this.sending.set(false);
        this.messageSuccess.set(res.output || 'Recordatorios procesados con éxito.');
        this.loadAll();
      },
      error: () => {
        this.sending.set(false);
        this.messageError.set('Ocurrió un error al disparar los recordatorios.');
      }
    });
  }

  isAdmin(): boolean {
    const role = this.authService.user()?.role?.name;
    return role === 'Administrator' || role === 'SuperAdmin';
  }

  // GT-69: la factura no se emitió o el mail no salió, el admin lo puede reintentar
  canRetryInvoice(n: AppNotification): boolean {
    return this.isAdmin() && n.tipo === 'confirmacion' && n.estado_envio === 'fallida' && !!n.appointment_id;
  }

  onRetryInvoice(n: AppNotification): void {
    if (!n.appointment_id) return;

    this.retrying.set(n.id);
    this.messageSuccess.set('');
    this.messageError.set('');

    this.notificationService.retryInvoice(n.appointment_id).subscribe({
      next: (res) => {
        this.retrying.set(null);
        this.messageSuccess.set(res.message);
        this.loadAll();
      },
      error: (err) => {
        this.retrying.set(null);
        const detalle = err.error?.error ? `: ${err.error.error}` : '.';
        this.messageError.set((err.error?.message || 'No se pudo reintentar el envío de la factura') + detalle);
        this.loadAll();
      }
    });
  }
}
