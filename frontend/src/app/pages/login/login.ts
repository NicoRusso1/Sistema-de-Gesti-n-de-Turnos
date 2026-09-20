import { HttpErrorResponse } from '@angular/common/http';
import { ActivatedRoute } from '@angular/router';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../services/auth.service';

@Component({
  selector: 'app-login',
  imports: [ReactiveFormsModule, RouterLink],
  template: `
    <h1>Iniciar sesión</h1>

    @if (registered) {
      <p role="status">Registro exitoso. Ya podés iniciar sesión.</p>
    }

    @if (errorMessage()) {
      <p role="alert">{{ errorMessage() }}</p>
    }

    <form [formGroup]="form" (ngSubmit)="onSubmit()">
      <div>
        <label for="email">Email</label>
        <input id="email" type="email" formControlName="email" />
      </div>

      <div>
        <label for="password">Contraseña</label>
        <input id="password" type="password" formControlName="password" />
      </div>

      <button type="submit" [disabled]="loading()">
        {{ loading() ? 'Ingresando...' : 'Ingresar' }}
      </button>
    </form>

    <p><a routerLink="/register">Crear cuenta</a></p>
  `,
})
export class Login {
  private fb = inject(FormBuilder);
  private authService = inject(AuthService);
  private router = inject(Router);

  protected readonly registered =
    inject(ActivatedRoute).snapshot.queryParamMap.get('registered') === 'true';

  protected readonly errorMessage = signal('');
  protected readonly loading = signal(false);

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
      next: () => {
        this.router.navigate(['/main']);
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