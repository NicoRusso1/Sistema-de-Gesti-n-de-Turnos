import { Component, OnInit, inject, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
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

  ngOnInit(): void {
    this.auditService.getAdminActions().subscribe({
      next: (res) => this.entries.set(res.data)
    });
  }

  badgeClass(name: AdminActionName): string {
    if (name === 'grant_owner' || name === 'grant_admin') return 'badge-ok';
    if (name === 'revoke_owner' || name === 'revoke_admin') return 'badge-warn';
    return '';
  }
}
