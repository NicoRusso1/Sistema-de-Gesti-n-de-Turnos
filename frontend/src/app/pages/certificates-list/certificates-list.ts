import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { RouterLink } from '@angular/router';
import { API_URL } from '../../config/api';
import { CertificadoItem, CertificadosResponse } from '../../models/certificate.model';

@Component({
  selector: 'app-certificates-list',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './certificates-list.html',
  styleUrls: ['./certificates-list.css']
})
export class CertificatesListComponent implements OnInit {
  private http = inject(HttpClient);

  certificados = signal<CertificadoItem[]>([]);
  loading = signal<boolean>(false);
  errorMessage = signal<string | null>(null);
  downloadingId = signal<number | null>(null);

  ngOnInit(): void {
    this.cargarCertificados();
  }

  cargarCertificados(): void {
    this.loading.set(true);
    this.errorMessage.set(null);

    this.http.get<CertificadosResponse>(`${API_URL}/certificados`).subscribe({
      next: (res) => {
        this.certificados.set(res.data);
        this.loading.set(false);
      },
      error: () => {
        this.errorMessage.set('No se pudieron obtener tus certificados médicos.');
        this.loading.set(false);
      }
    });
  }

  descargarCertificado(item: CertificadoItem): void {
    this.downloadingId.set(item.id);

    this.http.get(`${API_URL}/certificados/${item.id}/download`, {
      responseType: 'blob'
    }).subscribe({
      next: (blob) => {
        this.downloadingId.set(null);
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `Certificado_Turno_${item.turno_id}.pdf`;
        a.click();
        window.URL.revokeObjectURL(url);
      },
      error: () => {
        this.downloadingId.set(null);
        alert('Ocurrió un error al descargar el archivo.');
      }
    });
  }
}