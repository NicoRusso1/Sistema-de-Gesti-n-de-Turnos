import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { SpecialtyService } from '../../services/specialty.service';
@Component({
    selector: 'app-specialty-form',
    imports: [ReactiveFormsModule, RouterLink],
    templateUrl: './specialty-form.html',
})
export class SpecialtyForm implements OnInit {
    private fb = inject(FormBuilder);
    private specialtyService = inject(SpecialtyService);
    private route = inject(ActivatedRoute);
    private router = inject(Router);
    private readonly specialtyId = this.route.snapshot.paramMap.get('id');
    protected readonly isEdit = this.specialtyId !== null;
    protected readonly errorMessage = signal('');
    protected readonly loading = signal(false);
    protected readonly form = this.fb.nonNullable.group({
        name: ['', [Validators.required, Validators.maxLength(255)]],
        description: ['', [Validators.required, Validators.maxLength(255)]],
    });
    ngOnInit(): void {
        if (this.isEdit) {
            this.specialtyService.getById(Number(this.specialtyId)).subscribe({
                next: (item) => {
                    this.form.patchValue({
                        name: item.name,
                        description: item.description ?? '',
                    });
                },
                error: () => this.errorMessage.set('No se pudo cargar la especialidad'),
            });
        }
    }
    onSubmit(): void {
        if (this.form.invalid) {
            this.form.markAllAsTouched();
            return;
        }
        const data = this.form.getRawValue();
        const payload = {
            name: data.name.trim(),
            description: data.description.trim(),
        };
        this.loading.set(true);
        this.errorMessage.set('');
        const request = this.isEdit
            ? this.specialtyService.update(Number(this.specialtyId), payload)
            : this.specialtyService.create(payload);
        request.subscribe({
            next: () => this.router.navigate(['/specialties']),
            error: (err: HttpErrorResponse) => {
                this.loading.set(false);
                const errors = err.error?.errors;
                // 422: nombre repetido o campos faltantes
                if (errors) {
                    this.errorMessage.set((Object.values(errors) as string[][]).flat().join(' '));
                } else {
                    this.errorMessage.set('No se pudo guardar la especialidad');
                }
            },
        });
    }
}