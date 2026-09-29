import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { User } from '../../models/user';
import { UserService } from '../../services/user.service';

@Component({
  selector: 'app-user-permissions',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './user-permissions.html',
})
export class UserPermissions implements OnInit {
  private fb = inject(FormBuilder);
  private userService = inject(UserService);
  private route = inject(ActivatedRoute);

  private readonly userId = Number(this.route.snapshot.paramMap.get('id'));

  protected readonly user = signal<User | null>(null);
  protected readonly errorMessage = signal('');
  protected readonly successMessage = signal('');
  protected readonly loading = signal(false);

  protected readonly form = this.fb.nonNullable.group({
    view_own_appointments: false,
    view_all_appointments: false,
    view_assigned_patients: false,
    cancel_appointments: false,
    edit_schedules: false,
  });

  ngOnInit(): void {
    this.userService.getUser(this.userId).subscribe({
      next: (user) => {
        this.user.set(user);

        if (user.user_type?.name !== 'doctor') {
          this.form.controls.view_assigned_patients.disable();
        }
      },
      error: () => this.errorMessage.set('No se pudo cargar el usuario'),
    });

    this.userService.getPermissions(this.userId).subscribe({
      next: (permissions) => this.form.patchValue(permissions),
      error: () => this.errorMessage.set('No se pudieron cargar los permisos'),
    });
  }

  onSubmit(): void {
    this.loading.set(true);
    this.errorMessage.set('');
    this.successMessage.set('');

    this.userService.updatePermissions(this.userId, this.form.getRawValue()).subscribe({
      next: () => {
        this.loading.set(false);
        this.successMessage.set('Permisos actualizados correctamente');
      },
      error: (err: HttpErrorResponse) => {
        this.loading.set(false);
        const errors = err.error?.errors;

        if (errors) {
          this.errorMessage.set((Object.values(errors) as string[][]).flat().join(' '));
        } else {
          this.errorMessage.set(err.error?.message ?? 'No se pudieron guardar los permisos');
        }
      },
    });
  }
}