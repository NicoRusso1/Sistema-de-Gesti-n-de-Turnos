import { Component, OnInit, inject, signal } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { SpecialtyService } from './services/specialty.service';
import { Specialty } from './models/specialty';

@Component({
  imports: [RouterOutlet],
  selector: 'app-root',
  styleUrl: './app.css',
  templateUrl: './app.html',
})
export class App implements OnInit {
  private specialtyService = inject(SpecialtyService);

  protected readonly specialties = signal<Specialty[]>([]);
  protected readonly error = signal("");

  ngOnInit(): void {
    this.specialtyService.getAll().subscribe({
      next: (data) => { this.specialties.set(data); },
      error: () => { this.error.set("No se pudo conectar con el backend"); }
    });
  }
}
