import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { DoctorSchedule, DoctorSlot } from '../../models/doctor-schedule';
import { User } from '../../models/user';
import { DoctorScheduleService } from '../../services/doctor-schedule.service';
import { UserService } from '../../services/user.service';

@Component({
  selector: 'app-doctor-schedule',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './doctor-schedule.html',
})
export class DoctorScheduleEditor implements OnInit {
  private fb = inject(FormBuilder);
  private route = inject(ActivatedRoute);
  private userService = inject(UserService);
  private scheduleService = inject(DoctorScheduleService);

  private readonly doctorId = Number(this.route.snapshot.paramMap.get('id'));

  protected readonly days = [
    { value: 1, label: 'Lunes' },
    { value: 2, label: 'Martes' },
    { value: 3, label: 'Miércoles' },
    { value: 4, label: 'Jueves' },
    { value: 5, label: 'Viernes' },
    { value: 6, label: 'Sábado' },
    { value: 7, label: 'Domingo' },
  ];

  protected readonly today = new Date().toLocaleDateString('en-CA');

  protected readonly doctor = signal<User | null>(null);
  protected readonly schedules = signal<DoctorSchedule[]>([]);
  protected readonly editingId = signal<number | null>(null);
  protected readonly errorMessage = signal('');
  protected readonly successMessage = signal('');
  protected readonly slots = signal<DoctorSlot[] | null>(null);
  protected readonly slotsError = signal('');

  protected readonly schedulesByDay = computed(() =>
    this.days.map((day) => ({
      ...day,
      items: this.schedules().filter((schedule) => schedule.day_of_week === day.value),
    }))
  );

  protected readonly form = this.fb.nonNullable.group({
    day_of_week: [1, Validators.required],
    start_time: ['09:00', Validators.required],
    end_time: ['13:00', Validators.required],
  });

  protected readonly slotsForm = this.fb.nonNullable.group({
    date: ['', Validators.required],
  });

  ngOnInit(): void {
    this.userService.getUser(this.doctorId).subscribe({
      next: (user) => {
        this.doctor.set(user);

        if (user.user_type?.name !== 'doctor') {
          this.errorMessage.set('El usuario no es un médico');
          return;
        }

        this.loadSchedules();
      },
      error: () => this.errorMessage.set('No se pudo cargar el médico'),
    });
  }

  protected time(value: string): string {
    return value.slice(0, 5);
  }

  protected onSubmit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const raw = this.form.getRawValue();

    const payload = {
      day_of_week: Number(raw.day_of_week),
      start_time: raw.start_time,
      end_time: raw.end_time,
    };

    this.errorMessage.set('');
    this.successMessage.set('');

    const scheduleId = this.editingId();

    const request = scheduleId !== null
      ? this.scheduleService.updateSchedule(this.doctorId, scheduleId, payload)
      : this.scheduleService.createSchedule(this.doctorId, payload);

    request.subscribe({
      next: () => {
        this.successMessage.set(scheduleId !== null ? 'Franja actualizada correctamente' : 'Franja agregada correctamente');
        this.cancelEdit();
        this.loadSchedules();
      },
      error: (err: HttpErrorResponse) => this.errorMessage.set(this.errorText(err, 'No se pudo guardar la franja')),
    });
  }

  protected edit(schedule: DoctorSchedule): void {
    this.errorMessage.set('');
    this.successMessage.set('');
    this.editingId.set(schedule.id);
    this.form.patchValue({
      day_of_week: schedule.day_of_week,
      start_time: this.time(schedule.start_time),
      end_time: this.time(schedule.end_time),
    });
  }

  protected cancelEdit(): void {
    this.editingId.set(null);
    this.form.reset({ day_of_week: 1, start_time: '09:00', end_time: '13:00' });
  }

  protected remove(schedule: DoctorSchedule): void {
    if (!confirm(`¿Eliminar la franja ${this.time(schedule.start_time)} - ${this.time(schedule.end_time)}?`)) {
      return;
    }

    this.errorMessage.set('');
    this.successMessage.set('');

    this.scheduleService.deleteSchedule(this.doctorId, schedule.id).subscribe({
      next: () => {
        this.successMessage.set('Franja eliminada correctamente');
        this.loadSchedules();
      },
      error: () => this.errorMessage.set('No se pudo eliminar la franja'),
    });
  }

  protected searchSlots(): void {
    if (this.slotsForm.invalid) {
      this.slotsForm.markAllAsTouched();
      return;
    }

    this.slotsError.set('');

    this.scheduleService.getSlots(this.doctorId, this.slotsForm.getRawValue().date).subscribe({
      next: (response) => this.slots.set(response.slots),
      error: (err: HttpErrorResponse) => {
        this.slots.set(null);
        this.slotsError.set(this.errorText(err, 'No se pudieron obtener los turnos'));
      },
    });
  }

  private loadSchedules(): void {
    this.scheduleService.getSchedules(this.doctorId).subscribe({
      next: (data) => this.schedules.set(data),
      error: () => this.errorMessage.set('No se pudo cargar la agenda'),
    });
  }

  private errorText(err: HttpErrorResponse, fallback: string): string {
    const errors = err.error?.errors;

    if (errors) {
      return (Object.values(errors) as string[][]).flat().join(' ');
    }

    return err.error?.message ?? fallback;
  }
}