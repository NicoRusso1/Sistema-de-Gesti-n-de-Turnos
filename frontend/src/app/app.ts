import { Component, inject } from '@angular/core';
import { RouterLink, RouterOutlet } from '@angular/router';
import { AuthService } from './services/auth.service';

@Component({
  imports: [RouterOutlet, RouterLink],
  selector: 'app-root',
  styleUrl: './app.css',
  templateUrl: './app.html',
})
export class App {
  authService = inject(AuthService);
  user = this.authService.user;

  // Variable para controlar el estado del menú
  isSidebarOpen = true;

  // Función helper para los accesos
  isAdmin(): boolean {
    const role = this.user()?.role?.name;
    return role === 'Administrator' || role === 'SuperAdmin';
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

  canAccessNotifications(): boolean {
    return this.isAdmin() || this.isDoctor();
  }
}
