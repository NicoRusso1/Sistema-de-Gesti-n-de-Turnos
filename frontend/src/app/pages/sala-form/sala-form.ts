import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { Specialty } from '../../models/specialty';
import { SalaService } from '../../services/sala.service';
import { SpecialtyService } from '../../services/specialty.service';

@Component({
  selector: 'app-sala-form',
  standalone: true,
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './sala-form.html',
})
export class SalaFormComponent implements OnInit {
  private fb = inject(FormBuilder);
  private salaService = inject(SalaService);
  private specialtyService = inject(SpecialtyService);
  private route = inject(ActivatedRoute);
  private router = inject(Router);

  private readonly salaId = this.route.snapshot.paramMap.get('id');

  protected readonly isEdit = this.salaId !== null;
  protected readonly errorMessage = signal('');
  protected readonly loading = signal(false);
  protected readonly specialties = signal<Specialty[]>([]);

  protected readonly form = this.fb.nonNullable.group({
    numero: ['', [Validators.required, Validators.maxLength(50)]],
    piso: [1, [Validators.required, Validators.min(0)]],
    especialidad_id: [''], // nullable
    estado: ['disponible' as 'disponible' | 'fuera_de_servicio', Validators.required],
  });

  ngOnInit(): void {
    // Cargar especialidades para el select opcional
    this.specialtyService.getAll().subscribe({
      next: (list) => this.specialties.set(list),
      error: () => {},
    });

    if (this.isEdit) {
      this.salaService.getSala(Number(this.salaId)).subscribe({
        next: (item) => {
          this.form.patchValue({
            numero: item.numero,
            piso: item.piso,
            especialidad_id: item.especialidad_id ? String(item.especialidad_id) : '',
            estado: item.estado,
          });
        },
        error: () => this.errorMessage.set('No se pudo cargar la sala solicitada.'),
      });
    }
  }

  onSubmit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const val = this.form.getRawValue();

    // Sanitización clave: Si especialidad_id viene vacío, enviar null explícito a la BDD
    const payload = {
      numero: val.numero.trim(),
      piso: Number(val.piso),
      especialidad_id: val.especialidad_id ? Number(val.especialidad_id) : null,
      estado: val.estado,
    };

    this.loading.set(true);
    this.errorMessage.set('');

    const request = this.isEdit
      ? this.salaService.updateSala(Number(this.salaId), payload)
      : this.salaService.createSala(payload);

    request.subscribe({
      next: () => this.router.navigate(['/salas']),
      error: (err: HttpErrorResponse) => {
        this.loading.set(false);
        const errors = err.error?.errors;
        if (errors) {
          this.errorMessage.set((Object.values(errors) as string[][]).flat().join(' '));
        } else {
          this.errorMessage.set(err.error?.message || 'Error al persistir la sala en la base de datos.');
        }
      },
    });
  }
}