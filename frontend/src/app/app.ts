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

  // Función para abrir/cerrar menú
  toggleSidebar(): void {
    this.isSidebarOpen = !this.isSidebarOpen;
  }
}
