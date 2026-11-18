import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal, AfterViewInit } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { AuthLayout } from '../../shared/auth-layout/auth-layout';
import { EyeIcon } from '../../shared/eye-icon/eye-icon';
import { GOOGLE_CLIENT_ID } from '../../config/api';

declare const google: any;

@Component({
  selector: 'app-login',
  imports: [ReactiveFormsModule, RouterLink, AuthLayout, EyeIcon],
  templateUrl: './login.html',
  styleUrl: './login.css',
})
export class Login implements AfterViewInit {
  private fb = inject(FormBuilder);
  private authService = inject(AuthService);
  private router = inject(Router);

  protected readonly registered =
    inject(ActivatedRoute).snapshot.queryParamMap.get('registered') === 'true';
  protected readonly errorMessage = signal('');
  protected readonly loading = signal(false);
  protected readonly showPassword = signal(false);

  protected readonly form = this.fb.nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', Validators.required],
  });

  ngAfterViewInit(): void {
    this.initGoogleBtn();
  }

  private initGoogleBtn(): void {
    if (typeof google === 'undefined' || !google.accounts) {
      setTimeout(() => this.initGoogleBtn(), 150);
      return;
    }

    google.accounts.id.initialize({
      client_id: GOOGLE_CLIENT_ID,
      callback: (response: any) => this.handleGoogleResponse(response),
    });

    const container = document.getElementById('google-btn-login');
    if (container) {
      google.accounts.id.renderButton(container, {
        theme: 'outline',
        size: 'large',
        text: 'continue_with',
        shape: 'rectangular',
        width: 360,
        locale: 'es',
      });
    }
  }

  private handleGoogleResponse(response: any): void {
    if (!response.credential) {
      this.errorMessage.set('No se pudo obtener la credencial de Google.');
      return;
    }

    this.loading.set(true);
    this.errorMessage.set('');

    this.authService.loginWithGoogle(response.credential).subscribe({
      next: () => this.router.navigate(['/home']),
      error: (err: HttpErrorResponse) => {
        this.loading.set(false);
        this.errorMessage.set(err.error?.message ?? 'No se pudo iniciar sesión con Google');
      },
    });
  }

  onSubmit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.loading.set(true);
    this.errorMessage.set('');

    this.authService.login(this.form.getRawValue()).subscribe({
      next: () => this.router.navigate(['/home']),
      error: (err: HttpErrorResponse) => {
        this.loading.set(false);

        if (err.status === 401) {
          this.errorMessage.set(err.error?.message ?? 'Credenciales incorrectas');
        } else if (err.status === 422) {
          const errors = err.error?.errors;
          this.errorMessage.set(
            errors ? (Object.values(errors) as string[][]).flat().join(' ') : 'Datos inválidos',
          );
        } else if (err.status === 429) {
          this.errorMessage.set('Demasiados intentos. Esperá un minuto e intentá de nuevo.');
        } else {
          this.errorMessage.set('No se pudo conectar con el servidor');
        }
      },
    });
  }
}