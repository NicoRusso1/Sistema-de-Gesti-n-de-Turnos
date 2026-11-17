import { Component, OnInit, inject, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors } from '@angular/forms';
import { EMPTY, expand, reduce } from 'rxjs';
import { AuditService } from '../../services/audit.service';
import { UserService } from '../../services/user.service';
import { User } from '../../models/user';
import { AdminActionLogEntry, AdminActionLogFilters, AdminActionName } from '../../models/admin-action-log';

const ACTION_OPTIONS: { value: AdminActionName; label: string }[] = [
  { value: 'grant_admin', label: 'Otorgó rol de administrador' },
  { value: 'revoke_admin', label: 'Revocó rol de administrador' },
  { value: 'grant_owner', label: 'Otorgó propiedad' },
  { value: 'revoke_owner', label: 'Revocó propiedad' },
  { value: 'activate', label: 'Activó cuenta' },
  { value: 'deactivate', label: 'Desactivó cuenta' }
];

const FILTER_FIELDS = ['action', 'affected_admin_id', 'date_from', 'date_to'] as const;
type FilterField = typeof FILTER_FIELDS[number];

function dateRangeValidator(group: AbstractControl): ValidationErrors | null {
  const from = group.get('date_from')?.value;
  const to = group.get('date_to')?.value;
  return from && to && from > to ? { dateRange: true } : null;
}

@Component({
  selector: 'app-audit-log',
  standalone: true,
  imports: [DatePipe, ReactiveFormsModule],
  templateUrl: './audit-log.html'
})
export class AuditLogComponent implements OnInit {
  private auditService = inject(AuditService);
  private userService = inject(UserService);
  private fb = inject(FormBuilder);

  readonly actionOptions = ACTION_OPTIONS;

  filterForm = this.fb.nonNullable.group(
    {
      action: '',
      affected_admin_id: '',
      date_from: '',
      date_to: ''
    },
    { validators: dateRangeValidator }
  );

  entries = signal<AdminActionLogEntry[]>([]);
  total = signal<number>(0);
  currentPage = signal<number>(1);
  loading = signal<boolean>(false);
  errorMessage = signal<string>('');
  admins = signal<User[]>([]);
  submitted = signal<boolean>(false);
  serverErrors = signal<Partial<Record<FilterField, string>>>({});

  ngOnInit(): void {
    this.loadAdmins();
    this.load();
  }

  loadAdmins(): void {
    this.userService.getUsers({ role: 'Administrator', page: 1 }).pipe(
      expand((res) => res.current_page < res.last_page
        ? this.userService.getUsers({ role: 'Administrator', page: res.current_page + 1 })
        : EMPTY),
      reduce((acc, res) => acc.concat(res.data), [] as User[])
    ).subscribe({
      next: (users) => this.admins.set(users),
      error: () => this.admins.set([])
    });
  }

  dateRangeInvalid(): boolean {
    return this.submitted() && this.filterForm.hasError('dateRange');
  }

  fieldError(field: FilterField): string {
    return this.serverErrors()[field] ?? '';
  }

  load(page: number = 1): void {
    this.loading.set(true);
    this.errorMessage.set('');
    this.serverErrors.set({});

    const value = this.filterForm.getRawValue();
    const filters: AdminActionLogFilters = {
      page,
      action: value.action as AdminActionName | '',
      affected_admin_id: value.affected_admin_id ? Number(value.affected_admin_id) : null,
      date_from: value.date_from,
      date_to: value.date_to
    };

    this.auditService.getAdminActions(filters).subscribe({
      next: (res) => {
        this.entries.set(res.data);
        this.total.set(res.meta.total);
        this.currentPage.set(res.meta.current_page);
        this.loading.set(false);
      },
      error: (err: HttpErrorResponse) => {
        this.entries.set([]);
        this.total.set(0);
        const errors = err.status === 422 ? err.error?.errors : null;
        if (errors) {
          const mapped: Partial<Record<FilterField, string>> = {};
          FILTER_FIELDS.forEach((field) => {
            if (errors[field]?.length) mapped[field] = errors[field][0];
          });
          this.serverErrors.set(mapped);
          if (Object.keys(mapped).length === 0) {
            this.errorMessage.set(err.error?.message || 'No se pudo cargar el historial.');
          }
        } else {
          this.errorMessage.set(err.error?.message || 'No se pudo cargar el historial.');
        }
        this.loading.set(false);
      }
    });
  }

  onFilter(): void {
    this.submitted.set(true);
    if (this.filterForm.invalid) return;
    this.load(1);
  }

  resetFilters(): void {
    this.filterForm.reset();
    this.submitted.set(false);
    this.load(1);
  }

  badgeClass(name: AdminActionName): string {
    if (name === 'grant_owner' || name === 'grant_admin') return 'badge-ok';
    if (name === 'revoke_owner' || name === 'revoke_admin') return 'badge-warn';
    return '';
  }
}
