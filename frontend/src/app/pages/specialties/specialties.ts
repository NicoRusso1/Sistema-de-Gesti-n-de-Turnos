import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Specialty } from '../../models/specialty';
import { SpecialtyService } from '../../services/specialty.service';
@Component({
    selector: 'app-specialties',
    imports: [ReactiveFormsModule, RouterLink],
    templateUrl: './specialties.html',
})
export class Specialties implements OnInit {
    private fb = inject(FormBuilder);
    private specialtyService = inject(SpecialtyService);
    protected readonly specialties = signal<Specialty[]>([]);
    protected readonly errorMessage = signal('');
    protected readonly successMessage = signal('');
    protected readonly filterForm = this.fb.nonNullable.group({
        search: '',
    });
    ngOnInit(): void {
        this.load();
    }
    protected load(): void {
        this.errorMessage.set('');
        this.specialtyService.getAll(this.filterForm.getRawValue().search).subscribe({
            next: (data) => this.specialties.set(data),
            error: () => this.errorMessage.set('No se pudieron cargar las especialidades'),
        });
    }
    protected clearFilters(): void {
        this.filterForm.reset({ search: '' });
        this.load();
    }
    protected remove(item: Specialty): void {
        if (!confirm(`¿Eliminar la especialidad ${item.name}?`)) {
            return;
        }
        this.successMessage.set('');
        this.specialtyService.delete(item.id).subscribe({
            next: () => {
                this.successMessage.set(`Se eliminó la especialidad ${item.name}`);
                this.load();
            },
            // 409: el backend la bloquea si tiene médicos o turnos asociados
            error: (err: HttpErrorResponse) => {
                this.errorMessage.set(err.error?.message ?? 'No se pudo eliminar la especialidad');
            },
        });
    }
}