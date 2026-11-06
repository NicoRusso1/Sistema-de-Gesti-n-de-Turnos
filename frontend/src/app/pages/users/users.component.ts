import { ChangeDetectorRef, Component, OnInit, inject } from '@angular/core';
import { FormBuilder, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { UserService } from '../../services/user.service';
import { DialogService } from '../../shared/dialog/dialog.service';
import { PaginatedUsers, User } from '../../models/user';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { debounceTime } from 'rxjs';
import { rolLabel, tipoLabel } from '../../shared/role-label';

@Component({
  selector: 'app-users',
  standalone: true,
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './users.html'
})
export class UsersComponent implements OnInit {
  private userService = inject(UserService);
  private dialog = inject(DialogService);
  private fb = inject(FormBuilder);
  private cdr = inject(ChangeDetectorRef);

  private auth = inject(AuthService);

  get isSuperAdmin(): boolean {
    return this.auth.user()?.role.name === 'SuperAdmin';
  }

  get canManageAdmins(): boolean {
    const me = this.auth.user();
    return this.isSuperAdmin || (me?.role.name === 'Administrator' && !!me?.is_owner);
  }

  etiqueta(user: User): string {
    if (user.role.name === 'OperationalUser') {
      return user.user_type ? tipoLabel(user.user_type.name) : rolLabel(user.role.name);
    }
    return rolLabel(user.role.name, user.is_owner);
  }

  canRevokeAdmin(user: User): boolean {
    return this.canManageAdmins
      && user.role.name === 'Administrator'
      && user.id !== this.auth.user()?.id
      && (!user.is_owner || this.isSuperAdmin);
  }


  users: User[] = [];
  pagination: PaginatedUsers | null = null;
  filterForm: FormGroup;

  constructor() {
    this.filterForm = this.fb.group({
      search: [''],
      role: [''],
      user_type: [''],
      status: ['']
    });
  }

  ngOnInit(): void {
    this.loadUsers();
  }

  loadUsers(page: number = 1): void {
    const filters = { ...this.filterForm.value, page };
    this.userService.getUsers(filters).subscribe({
      next: (res) => {
        this.pagination = res;
        this.users = res.data;
        this.cdr.markForCheck();
      },
      error: (err) => console.error('Error loading users', err)
    });
  }

  applyFilters(): void {
    this.loadUsers(1);
  }

  clearFilters(): void {
    // Resetea los valores a string vacío para los selects e input
    this.filterForm.reset({ search: '', role: '', user_type: '', status: '' });
    // Recarga la lista sin filtros
    this.loadUsers(1);
  }

  goToPage(page: number): void {
    if (this.pagination && page >= 1 && page <= this.pagination.last_page) {
      this.loadUsers(page);
    }
  }

  async deactivate(user: User): Promise<void> {
    if (!(await this.dialog.confirm({ title: `¿Dar de baja a ${user.first_name} ${user.last_name}?`, confirmText: 'Dar de baja', variant: 'danger' }))) {
      return;
    }

    this.userService.deactivateUser(user.id).subscribe({
      next: () => this.loadUsers(this.pagination?.current_page ?? 1),
      error: () => this.dialog.toast('No se pudo dar de baja al usuario', 'error'),
    });
  }

  activate(user: User): void {
    this.userService.activateUser(user.id).subscribe({
      next: () => this.loadUsers(this.pagination?.current_page ?? 1),
      error: () => this.dialog.toast('No se pudo reactivar al usuario', 'error'),
    });
  }
  async grantAdmin(user: User): Promise<void> {
    if (!(await this.dialog.confirm({ title: `¿Conceder rol de Administrador a ${user.first_name} ${user.last_name}?`, confirmText: 'Conceder' }))) {
      return;
    }

    this.userService.grantAdmin(user.id).subscribe({
      next: () => this.loadUsers(this.pagination?.current_page ?? 1),
      error: (err) => this.dialog.toast(err.error?.message ?? 'No se pudo conceder el rol', 'error'),
    });
  }

  async revokeAdmin(user: User): Promise<void> {
    if (!(await this.dialog.confirm({ title: `¿Revocar rol de Administrador a ${user.first_name} ${user.last_name}?`, confirmText: 'Revocar', variant: 'danger' }))) {
      return;
    }

    this.userService.revokeAdmin(user.id).subscribe({
      next: () => this.loadUsers(this.pagination?.current_page ?? 1),
      error: (err) => this.dialog.toast(err.error?.message ?? 'No se pudo revocar el rol', 'error'),
    });
  }

}