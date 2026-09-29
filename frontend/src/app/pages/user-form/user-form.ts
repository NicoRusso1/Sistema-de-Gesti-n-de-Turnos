import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { UserService } from '../../services/user.service';

@Component({
  selector: 'app-user-form',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './user-form.html',
})
export class UserForm implements OnInit {
  private fb = inject(FormBuilder);
  private userService = inject(UserService);
  private route = inject(ActivatedRoute);
  private router = inject(Router);

  private readonly userId = this.route.snapshot.paramMap.get('id');

  protected readonly isEdit = this.userId !== null;
  protected readonly errorMessage = signal('');
  protected readonly loading = signal(false);

  protected readonly form = this.fb.nonNullable.group({
    first_name: ['', Validators.required],
    last_name: ['', Validators.required],
    email: ['', [Validators.required, Validators.email]],
    phone: [''],
    user_type_id: [1, Validators.required],
    password: [''],
    password_confirmation: [''],
  });

  ngOnInit(): void {
    if (this.isEdit) {
      this.userService.getUser(Number(this.userId)).subscribe({
        next: (user) => {
          this.form.patchValue({
            first_name: user.first_name,
            last_name: user.last_name,
            email: user.email,
            phone: user.phone ?? '',
            user_type_id: user.user_type_id ?? 1,
          });
        },
        error: () => this.errorMessage.set('No se pudo cargar el usuario'),
      });
    } else {
      this.form.controls.password.setValidators([Validators.required, Validators.minLength(8)]);
      this.form.controls.password_confirmation.setValidators([Validators.required]);
      this.form.controls.password.updateValueAndValidity();
      this.form.controls.password_confirmation.updateValueAndValidity();
    }
  }

  onSubmit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const data = this.form.getRawValue();

    if (!this.isEdit && data.password !== data.password_confirmation) {
      this.errorMessage.set('Las contraseñas no coinciden');
      return;
    }

    const profile = {
      first_name: data.first_name,
      last_name: data.last_name,
      email: data.email,
      phone: data.phone,
      user_type_id: Number(data.user_type_id),
    };

    this.loading.set(true);
    this.errorMessage.set('');

    const request = this.isEdit
      ? this.userService.updateUser(Number(this.userId), profile)
      : this.userService.createUser({
          ...profile,
          password: data.password,
          password_confirmation: data.password_confirmation,
        });

    request.subscribe({
      next: () => this.router.navigate(['/users']),
      error: (err: HttpErrorResponse) => {
        this.loading.set(false);
        const errors = err.error?.errors;

        if (errors) {
          this.errorMessage.set((Object.values(errors) as string[][]).flat().join(' '));
        } else {
          this.errorMessage.set('No se pudo guardar el usuario');
        }
      },
    });
  }
}