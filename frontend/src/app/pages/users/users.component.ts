import { Component, OnInit, inject } from '@angular/core';
import { FormBuilder, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { UserService } from '../../services/user.service';
import { PaginatedUsers, User } from '../../models/user';

@Component({
  selector: 'app-users',
  standalone: true,
  imports: [ReactiveFormsModule],
  template: `
    <div class="users-container">
      <h2>Listado y Filtros de Usuarios</h2>
      
      <!-- Al presionar Enter en el input se ejecuta ngSubmit -->
      <form [formGroup]="filterForm" (ngSubmit)="applyFilters()" class="filters-card">
        
        <div class="form-group">
          <label>Búsqueda libre:</label>
          <input type="text" formControlName="search" placeholder="Nombre, email o DNI...">
        </div>
        
        <!-- El (change) aplica el filtro inmediatamente al seleccionar una opción -->
        <div class="form-group">
          <label>Rol:</label>
          <select formControlName="role" (change)="applyFilters()">
            <option value="">Todos los Roles</option>
            <option value="SuperAdmin">Super Administrador</option>
            <option value="Administrator">Administrador</option>
            <option value="OperationalUser">Usuario Operativo</option>
          </select>
        </div>

        <div class="form-group">
          <label>Tipo de Usuario:</label>
          <select formControlName="user_type" (change)="applyFilters()">
            <option value="">Todos los Tipos</option>
            <option value="patient">Paciente</option>
            <option value="doctor">Doctor</option>
            <option value="secretary">Secretario/a</option>
          </select>
        </div>

        <div class="form-group">
          <label>Estado:</label>
          <select formControlName="status" (change)="applyFilters()">
            <option value="">Todos</option>
            <option value="1">Activo</option>
            <option value="0">Inactivo</option>
          </select>
        </div>

        <div class="filter-actions">
          <button type="submit" class="btn-primary">Filtrar</button>
          <button type="button" class="btn-secondary" (click)="clearFilters()">Limpiar</button>
        </div>
      </form>

      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Nombre</th>
              <th>DNI</th>
              <th>Email</th>
              <th>Rol / Tipo</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody>
            @for (user of users; track user.id) {
              <tr>
                <td>{{ user.id }}</td>
                <td>{{ user.first_name }} {{ user.last_name }}</td>
                <td>{{ user.personal_data?.national_id || 'N/A' }}</td>
                <td>{{ user.email }}</td>
                <td>
                  <span class="badge role-badge">{{ user.role.name }}</span>
                  @if (user.user_type) {
                    <span class="badge type-badge">{{ user.user_type.name }}</span>
                  }
                </td>
                <td>
                  @if (user.status === 1) { Activo }
                  @else if (user.status === 0) { Inactivo }
                  @else { N/A }
                </td>
              </tr>
            } @empty {
              <tr>
                <td colspan="6" class="text-center">No se encontraron usuarios que coincidan con los filtros.</td>
              </tr>
            }
          </tbody>
        </table>
      </div>

      @if (pagination && pagination.last_page > 1) {
        <div class="pagination">
          <button [disabled]="pagination.current_page === 1" (click)="goToPage(pagination.current_page - 1)">Anterior</button>
          <span>Página {{ pagination.current_page }} de {{ pagination.last_page }}</span>
          <button [disabled]="pagination.current_page === pagination.last_page" (click)="goToPage(pagination.current_page + 1)">Siguiente</button>
        </div>
      }
    </div>
  `,
  styles: [`
    .users-container { padding: 20px; }
    .filters-card { display: flex; flex-wrap: wrap; gap: 15px; background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 20px; align-items: flex-end;}
    .form-group { display: flex; flex-direction: column; flex: 1; min-width: 200px;}
    .form-group label { margin-bottom: 5px; font-weight: bold; font-size: 14px; }
    .form-group input, .form-group select { padding: 10px; border: 1px solid #ccc; border-radius: 4px; outline: none; }
    .form-group input:focus, .form-group select:focus { border-color: #007bff; }
    .filter-actions { display: flex; gap: 10px; }
    .btn-primary { background: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; transition: background 0.2s;}
    .btn-primary:hover { background: #0056b3; }
    .btn-secondary { background: #6c757d; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; transition: background 0.2s;}
    .btn-secondary:hover { background: #5a6268; }
    .table-responsive { overflow-x: auto; }
    .data-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    .data-table th, .data-table td { padding: 12px; border: 1px solid #ddd; text-align: left; }
    .data-table th { background-color: #f4f4f4; }
    .badge { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; display: inline-block; margin-right: 5px;}
    .role-badge { background: #007bff; color: white; }
    .type-badge { background: #28a745; color: white; }
    .text-center { text-align: center; }
    .pagination { display: flex; justify-content: center; align-items: center; gap: 15px; }
    .pagination button { padding: 8px 12px; cursor: pointer; border: 1px solid #ccc; border-radius: 4px; background: #fff;}
    .pagination button:hover:not(:disabled) { background: #f0f0f0; }
    .pagination button:disabled { cursor: not-allowed; opacity: 0.5; }
  `]
})
export class UsersComponent implements OnInit {
  private userService = inject(UserService);
  private fb = inject(FormBuilder);

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
}