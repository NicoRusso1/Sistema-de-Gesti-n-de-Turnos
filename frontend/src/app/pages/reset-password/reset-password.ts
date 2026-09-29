import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, OnInit, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AuthService } from '../../services/auth.service';

@Component({
  selector: 'app-reset-password',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './reset-password.html',
})
export class ResetPassword implements OnInit {
  private fb = inject(FormBuilder);
  private authService = inject(AuthService);
  private route = inject(ActivatedRoute);
  private router = inject(Router);

  protected readonly token = signal<string>('');
  protected readonly errorMessage = signal('');
  protected readonly successMessage = signal('');
  protected readonly loading = signal(false);

  protected readonly form = this.fb.nonNullable.group({
    password: ['', [Validators.required, Validators.minLength(8)]],
    password_confirmation: ['', Validators.required],
  });

  ngOnInit(): void {
    const tokenParam = this.route.snapshot.queryParamMap.get('token');
    if (!tokenParam) {
      this.errorMessage.set('Enlace inválido: no se encontró ningún token en la URL.');
    } else {
      this.token.set(tokenParam);
    }
  }

  onSubmit(): void {
    if (this.form.invalid || !this.token()) {
      this.form.markAllAsTouched();
      return;
    }

    const { password, password_confirmation } = this.form.getRawValue();

    if (password !== password_confirmation) {
      this.errorMessage.set('Las contraseñas no coinciden');
      return;
    }

    this.loading.set(true);
    this.errorMessage.set('');
    this.successMessage.set('');

    this.authService.resetPassword({
      token: this.token(),
      password,
      password_confirmation,
    }).subscribe({
      next: (res) => {
        this.loading.set(false);
        this.successMessage.set(res.message);
        setTimeout(() => {
          this.router.navigate(['/login']);
        }, 2500);
      },
      error: (err: HttpErrorResponse) => {
        this.loading.set(false);
        if (err.status === 400 || err.status === 404) {
          this.errorMessage.set(err.error?.message ?? 'El token es inválido o ha expirado');
        } else if (err.status === 422) {
          const errors = err.error?.errors;
          this.errorMessage.set(
            errors ? (Object.values(errors) as string[][]).flat().join(' ') : 'Datos inválidos',
          );
        } else {
          this.errorMessage.set('No se pudo conectar con el servidor');
        }
      },
    });
  }
}