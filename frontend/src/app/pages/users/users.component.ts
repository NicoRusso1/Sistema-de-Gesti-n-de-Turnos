import { ChangeDetectorRef, Component, OnInit, inject } from '@angular/core';
import { FormBuilder, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { UserService } from '../../services/user.service';
import { PaginatedUsers, User } from '../../models/user';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { debounceTime } from 'rxjs';

@Component({
  selector: 'app-users',
  standalone: true,
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './users.html'
})
export class UsersComponent implements OnInit {
  private userService = inject(UserService);
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

  deactivate(user: User): void {
    if (!confirm(`¿Dar de baja a ${user.first_name} ${user.last_name}?`)) {
      return;
    }

    this.userService.deactivateUser(user.id).subscribe({
      next: () => this.loadUsers(this.pagination?.current_page ?? 1),
      error: () => alert('No se pudo dar de baja al usuario'),
    });
  }

  activate(user: User): void {
    this.userService.activateUser(user.id).subscribe({
      next: () => this.loadUsers(this.pagination?.current_page ?? 1),
      error: () => alert('No se pudo reactivar al usuario'),
    });
  }
  grantAdmin(user: User): void {
    if (!confirm(`¿Conceder rol de Administrador a ${user.first_name} ${user.last_name}?`)) {
      return;
    }

    this.userService.grantAdmin(user.id).subscribe({
      next: () => this.loadUsers(this.pagination?.current_page ?? 1),
      error: (err) => alert(err.error?.message ?? 'No se pudo conceder el rol'),
    });
  }

  revokeAdmin(user: User): void {
    if (!confirm(`¿Revocar rol de Administrador a ${user.first_name} ${user.last_name}?`)) {
      return;
    }

    this.userService.revokeAdmin(user.id).subscribe({
      next: () => this.loadUsers(this.pagination?.current_page ?? 1),
      error: (err) => alert(err.error?.message ?? 'No se pudo revocar el rol'),
    });
  }

}