import { Component, OnInit, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Sala } from '../../models/sala';
import { SalaService } from '../../services/sala.service';

@Component({
  selector: 'app-salas',
  standalone: true,
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './salas.html',
})
export class SalasComponent implements OnInit {
  private fb = inject(FormBuilder);
  private salaService = inject(SalaService);

  protected readonly salas = signal<Sala[]>([]);
  protected readonly errorMessage = signal('');
  protected readonly successMessage = signal('');

  protected readonly filterForm = this.fb.group({
    search: [''],
    estado: [''],
    piso: [''],
  });

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    const raw = this.filterForm.value;
    this.salaService.getSalas({
      search: raw.search ?? '',
      estado: raw.estado ?? '',
      piso: raw.piso ?? '',
    }).subscribe({
      next: (data) => this.salas.set(data),
      error: () => this.errorMessage.set('Error al cargar el listado de salas.'),
    });
  }

  clearFilters(): void {
    this.filterForm.reset({ search: '', estado: '', piso: '' });
    this.load();
  }

  toggleEstado(sala: Sala): void {
    const nuevoEstado = sala.estado === 'disponible' ? 'fuera_de_servicio' : 'disponible';
    this.salaService.cambiarEstado(sala.id, nuevoEstado).subscribe({
      next: () => {
        this.successMessage.set(`Estado de la sala ${sala.numero} actualizado.`);
        this.load();
      },
      error: (err) => this.errorMessage.set(err.error?.message || 'Error al cambiar estado.'),
    });
  }

  eliminar(sala: Sala): void {
    if (!confirm(`¿Desea eliminar la sala ${sala.numero}?`)) return;

    this.salaService.deleteSala(sala.id).subscribe({
      next: () => {
        this.successMessage.set('Sala eliminada correctamente.');
        this.load();
      },
      error: (err) => this.errorMessage.set(err.error?.message || 'Error al eliminar la sala.'),
    });
  }
}