import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { NotificationService } from '../../services/notification.service';
import { AppNotification } from '../../models/notification';

@Component({
  selector: 'app-notifications',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './notifications.html',
  styleUrls: ['./notifications.css']
})
export class NotificationsComponent implements OnInit {
  private notificationService = inject(NotificationService);

  notifications = signal<AppNotification[]>([]);
  loading = signal<boolean>(false);
  sending = signal<boolean>(false);
  messageSuccess = signal<string>('');
  messageError = signal<string>('');

  ngOnInit(): void {
    this.loadAll();
  }

  loadAll(): void {
    this.loading.set(true);
    this.notificationService.getAllNotifications().subscribe({
      next: (res) => {
        this.notifications.set(res.data || res);
        this.loading.set(false);
      },
      error: () => this.loading.set(false)
    });
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
}