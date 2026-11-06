import { Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../services/auth.service';

@Component({
  selector: 'app-forbidden',
  imports: [RouterLink],
  templateUrl: './forbidden.html',
  styleUrl: '../not-found/not-found.css',
})
export class Forbidden {
  protected readonly user = inject(AuthService).user;

  protected back(): void {
    history.back();
  }
}
