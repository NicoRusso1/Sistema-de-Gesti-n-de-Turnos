import { Component, OnInit, inject, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { AuditService } from '../../services/audit.service';
import { AdminActionLogEntry, AdminActionName } from '../../models/admin-action-log';

@Component({
  selector: 'app-audit-log',
  standalone: true,
  imports: [DatePipe],
  templateUrl: './audit-log.html'
})
export class AuditLogComponent implements OnInit {
  private auditService = inject(AuditService);

  entries = signal<AdminActionLogEntry[]>([]);
  total = signal<number>(0);
  loading = signal<boolean>(false);
  errorMessage = signal<string>('');

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    this.loading.set(true);
    this.errorMessage.set('');
    this.auditService.getAdminActions().subscribe({
      next: (res) => {
        this.entries.set(res.data);
        this.total.set(res.meta.total);
        this.loading.set(false);
      },
      error: (err: HttpErrorResponse) => {
        this.entries.set([]);
        this.total.set(0);
        this.errorMessage.set(err.error?.message || 'No se pudo cargar el historial.');
        this.loading.set(false);
      }
    });
  }

  badgeClass(name: AdminActionName): string {
    if (name === 'grant_owner' || name === 'grant_admin') return 'badge-ok';
    if (name === 'revoke_owner' || name === 'revoke_admin') return 'badge-warn';
    return '';
  }
}
