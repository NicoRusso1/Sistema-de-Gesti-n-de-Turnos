import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { AuthLayout } from '../../shared/auth-layout/auth-layout';
import { EyeIcon } from '../../shared/eye-icon/eye-icon';

@Component({
  selector: 'app-login',
  imports: [ReactiveFormsModule, RouterLink, AuthLayout, EyeIcon],
  templateUrl: './login.html',
  styleUrl: './login.css',
})
export class Login {
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
