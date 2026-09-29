import { Component, OnInit, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { SalaDisponibilidad } from '../../models/sala';
import { Specialty } from '../../models/specialty';
import { SalaService } from '../../services/sala.service';
import { SpecialtyService } from '../../services/specialty.service';

@Component({
  selector: 'app-salas-disponibilidad',
  standalone: true,
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './salas-disponibilidad.html',
})
export class SalasDisponibilidadComponent implements OnInit {
  private fb = inject(FormBuilder);
  private salaService = inject(SalaService);
  private specialtyService = inject(SpecialtyService);

  protected readonly disponibilidad = signal<SalaDisponibilidad[]>([]);
  protected readonly specialties = signal<Specialty[]>([]);
  protected readonly loading = signal(false);

  protected readonly filterForm = this.fb.group({
    fecha: [''],
    franja_horaria: [''],
    especialidad_id: [''],
  });

  ngOnInit(): void {
    this.specialtyService.getAll().subscribe({
      next: (list) => this.specialties.set(list),
      error: () => {},
    });
    this.consultar();
  }

  consultar(): void {
    this.loading.set(true);
    const val = this.filterForm.value;

    this.salaService.getDisponibilidad({
      fecha: val.fecha ?? '',
      franja_horaria: val.franja_horaria ?? '',
      especialidad_id: val.especialidad_id ?? '',
    }).subscribe({
      next: (res) => {
        this.disponibilidad.set(res);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }
}