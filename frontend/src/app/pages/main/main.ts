import { HttpClient } from '@angular/common/http';
import { Component, OnInit, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService } from '../../services/auth.service';

interface CurrentUser {
  first_name: string;
  last_name: string;
  email: string;
}

@Component({
  selector: 'app-main',
  template: `
    <h1>Vista principal</h1>

    @if (user()) {
      <p>Hola, {{ user()!.first_name }} {{ user()!.last_name }} ({{ user()!.email }})</p>
    } @else {
      <p>Cargando datos del usuario...</p>
    }

    <button (click)="onLogout()">Cerrar sesión</button>
  `,
})
export class Main implements OnInit {
  private http = inject(HttpClient);
  private authService = inject(AuthService);
  private router = inject(Router);

  protected readonly user = signal<CurrentUser | null>(null);

  ngOnInit(): void {
    this.http
      .get<CurrentUser>('http://localhost:8000/api/user', {
        headers: { Authorization: `Bearer ${this.authService.getToken()}` },
      })
      .subscribe({
        next: (data) => this.user.set(data),
        error: () => this.router.navigate(['/login']),
      });
  }

  onLogout(): void {
    this.authService.logout();
    this.router.navigate(['/login']);
  }
}
