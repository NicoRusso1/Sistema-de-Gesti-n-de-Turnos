import { Component, inject}  from '@angular/core';
import {ActivatedRoute, RouterLink} from '@angular/router';

@Component({
    selector: 'app-login',
    imports: [RouterLink],
    template: `
    <h1>Iniciar sesión</h1>

    @if (registered) {
    <p role="status">Registro exitoso. Ya podés iniciar sesión.</p>
    }

    <p>Pantalla de login en construcción.</p>
    <p><a routerLink="/register">Crear cuenta</a></p>
    `,
})

export class Login {
    protected readonly registered = inject(ActivatedRoute).snapshot.queryParamMap.get('registered') 
    === 'true';
}