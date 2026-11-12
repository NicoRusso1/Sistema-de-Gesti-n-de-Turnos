import { Component, inject } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService } from './services/auth.service';
import { roleLabel } from './shared/role-label';
import { DialogHost } from './shared/dialog/dialog-host';

@Component({
  imports: [RouterOutlet, RouterLink, RouterLinkActive, DialogHost],
  selector: 'app-root',
  styleUrl: './app.css',
  templateUrl: './app.html',
})
export class App {
  authService = inject(AuthService);
  private router = inject(Router);
  user = this.authService.user;

  // Variable para controlar el estado del menú
  isSidebarOpen = typeof window === 'undefined' || window.innerWidth > 900;

  // Función helper para los accesos
  isAdmin(): boolean {
    const role = this.user()?.role?.name;
    return role === 'Administrator' || role === 'SuperAdmin';
  }
  isSuperAdmin(): boolean {
    return this.user()?.role?.name === 'SuperAdmin';
  }
  isDoctor(): boolean {
    return this.user()?.user_type?.name === 'doctor';
  }

  isPatient(): boolean {
    return this.user()?.user_type?.name === 'patient';
  }

  agendaLabel(): string {
    if (this.isAdmin()) return 'Agenda de Turnos';
    if (this.isPatient()) return 'Mis Turnos';
    if (this.isDoctor()) return 'Mi Agenda';
    return 'Agenda de Turnos';
  }

  canBookAppointments(): boolean {
    return this.isAdmin() || this.isPatient();
  }

  // Función para abrir/cerrar menú
  toggleSidebar(): void {
    this.isSidebarOpen = !this.isSidebarOpen;
  }
    canViewPayments(): boolean {
    return this.isAdmin() || this.isPatient();
  }

  paymentsLabel(): string {
    return this.isAdmin() ? 'Gestión de Pagos' : 'Mis Pagos';
  }

  canViewCertificates(): boolean {
    return this.isAdmin() || this.isPatient();
  }
  certificatesLabel(): string {
    return this.isAdmin() ? 'Certificados Emitidos' : 'Mis Certificados';
  }
  
  canAccessNotifications(): boolean {
    return this.isAdmin() || this.isDoctor();
  }

  closeOnMobile(): void {
    if (window.innerWidth <= 900) {
      this.isSidebarOpen = false;
    }
  }

  roleLabel(): string {
    return roleLabel(this.user());
  }

  initials(): string {
    const u = this.user();
    return `${u?.first_name?.[0] ?? ''}${u?.last_name?.[0] ?? ''}`.toUpperCase();
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
}
