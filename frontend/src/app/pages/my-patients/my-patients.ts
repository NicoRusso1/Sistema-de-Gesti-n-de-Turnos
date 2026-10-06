import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { AssignedPatient } from '../../models/assigned-patient';
import { DoctorPatientService } from '../../services/doctor-patient.service';
@Component({
    selector: 'app-my-patients',
    imports: [ReactiveFormsModule],
    templateUrl: './my-patients.html',
})
export class MyPatients implements OnInit {
    private fb = inject(FormBuilder);
    private doctorPatientService = inject(DoctorPatientService);
    protected readonly patients = signal<AssignedPatient[]>([]);
    protected readonly errorMessage = signal('');
    protected readonly loading = signal(false);
    protected readonly filterForm = this.fb.nonNullable.group({
        search: '',
    });
    ngOnInit(): void {
        this.load();
    }
    protected load(): void {
        this.errorMessage.set('');
        this.loading.set(true);
        this.doctorPatientService.getMyPatients(this.filterForm.getRawValue().search).subscribe({
            next: (data) => {
                this.patients.set(data);
                this.loading.set(false);
            },
            error: (err: HttpErrorResponse) => {
                this.patients.set([]);
                this.loading.set(false);
                this.errorMessage.set(
                    err.status === 403
                        ? 'No tenés el permiso para ver pacientes asignados. Pedile a un administrador que lo active.'
                        : 'No se pudieron cargar tus pacientes'
                );
            },
        });
    }
    protected clearFilters(): void {
        this.filterForm.reset({ search: '' });
        this.load();
    }
}