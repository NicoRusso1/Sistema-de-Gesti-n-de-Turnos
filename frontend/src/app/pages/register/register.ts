import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal, AfterViewInit } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { AuthLayout } from '../../shared/auth-layout/auth-layout';
import { EyeIcon } from '../../shared/eye-icon/eye-icon';
import { GOOGLE_CLIENT_ID } from '../../config/api';

declare const google: any;

@Component({
  selector: 'app-register',
  imports: [ReactiveFormsModule, RouterLink, AuthLayout, EyeIcon],
  templateUrl: './register.html',
  styles: [`
    .or-separator {
      display: flex;
      align-items: center;
      gap: 12px;
      margin: 14px 0 10px;
      font-size: 0.85rem;
      color: var(--sigt-texto-suave);
    }
    .or-separator::before,
    .or-separator::after {
      content: '';
      flex: 1;
      height: 1px;
      background: var(--sigt-borde);
    }
    .google-btn-wrapper {
      display: flex;
      justify-content: center;
      width: 100%;
      min-height: 44px;
    }
  `],
})
export class Register implements AfterViewInit {
  private fb = inject(FormBuilder);
  private authService = inject(AuthService);
  private router = inject(Router);

  protected readonly errorMessage = signal('');
  protected readonly loading = signal(false);
  protected readonly showPassword = signal(false);

  protected readonly form = this.fb.nonNullable.group({
    first_name: ['', Validators.required],
    last_name: ['', Validators.required],
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required, Validators.minLength(8)]],
    password_confirmation: ['', Validators.required],
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

    const container = document.getElementById('google-btn-register');
    if (container) {
      google.accounts.id.renderButton(container, {
        theme: 'outline',
        size: 'large',
        text: 'signup_with', // <--- Muestra "Registrarse con Google"
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
        this.errorMessage.set(err.error?.message ?? 'No se pudo crear la cuenta con Google');
      },
    });
  }

  onSubmit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const data = this.form.getRawValue();

    if (data.password !== data.password_confirmation) {
      this.errorMessage.set('Las contraseñas no coinciden');
      return;
    }

    this.loading.set(true);
    this.errorMessage.set('');

    this.authService.register(data).subscribe({
      next: () => {
        this.router.navigate(['/login'], { queryParams: { registered: 'true' } });
      },
      error: (err: HttpErrorResponse) => {
        this.loading.set(false);
        const errors = err.error?.errors;

        if (errors) {
          this.errorMessage.set((Object.values(errors) as string[][]).flat().join(' '));
        } else {
          this.errorMessage.set('No se pudo conectar con el servidor');
        }
      },
    });
  }
}