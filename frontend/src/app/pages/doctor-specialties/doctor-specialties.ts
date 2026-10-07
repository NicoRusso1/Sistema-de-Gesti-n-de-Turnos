import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { Specialty } from '../../models/specialty';
import { User } from '../../models/user';
import { DoctorSpecialtyService } from '../../services/doctor-specialty.service';
import { SpecialtyService } from '../../services/specialty.service';
import { UserService } from '../../services/user.service';
@Component({
    selector: 'app-doctor-specialties',
    imports: [RouterLink],
    templateUrl: './doctor-specialties.html',
})
export class DoctorSpecialties implements OnInit {
    private userService = inject(UserService);
    private specialtyService = inject(SpecialtyService);
    private doctorSpecialtyService = inject(DoctorSpecialtyService);
    private route = inject(ActivatedRoute);
    private readonly doctorId = Number(this.route.snapshot.paramMap.get('id'));
    protected readonly user = signal<User | null>(null);
    protected readonly specialties = signal<Specialty[]>([]);
    protected readonly selectedIds = signal<number[]>([]);
    protected readonly canEdit = signal(false);
    protected readonly errorMessage = signal('');
    protected readonly successMessage = signal('');
    protected readonly loading = signal(false);
    ngOnInit(): void {
        this.userService.getUser(this.doctorId).subscribe({
            next: (user) => this.user.set(user),
            error: () => this.errorMessage.set('No se pudo cargar el usuario'),
        });
        this.specialtyService.getAll().subscribe({
            next: (list) => this.specialties.set(list),
            error: () => this.errorMessage.set('No se pudieron cargar las especialidades'),
        });
        // Si el usuario no es médico, el backend responde 422 y no se muestra el selector
        this.doctorSpecialtyService.getDoctorSpecialties(this.doctorId).subscribe({
            next: (assigned) => {
                this.selectedIds.set(assigned.map((s) => s.id));
                this.canEdit.set(true);
            },
            error: (err: HttpErrorResponse) => {
                this.errorMessage.set(err.error?.message ?? 'No se pudieron cargar las especialidades del médico');
            },
        });
    }
    protected isSelected(id: number): boolean {
        return this.selectedIds().includes(id);
    }
    // Marcar agrega el id una sola vez; desmarcar lo quita: no puede quedar repetido
    protected toggle(id: number): void {
        this.successMessage.set('');
        this.selectedIds.update((ids) => (ids.includes(id) ? ids.filter((i) => i !== id) : [...ids, id]));
    }
    protected save(): void {
        this.loading.set(true);
        this.errorMessage.set('');
        this.successMessage.set('');
        this.doctorSpecialtyService.syncDoctorSpecialties(this.doctorId, this.selectedIds()).subscribe({
            next: () => {
                this.loading.set(false);
                this.successMessage.set('Especialidades actualizadas correctamente');
            },
            error: (err: HttpErrorResponse) => {
                this.loading.set(false);
                const errors = err.error?.errors;
                if (errors) {
                    this.errorMessage.set((Object.values(errors) as string[][]).flat().join(' '));
                } else {
                    this.errorMessage.set(err.error?.message ?? 'No se pudieron guardar las especialidades');
                }
            },
        });
    }
}