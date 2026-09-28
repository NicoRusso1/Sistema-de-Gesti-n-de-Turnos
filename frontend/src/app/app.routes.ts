import { Routes } from '@angular/router';
import { authGuard } from './guards/auth.guard';
import { adminGuard } from './guards/admin.guard';
import { Home } from './pages/home/home';
import { Login } from './pages/login/login';
import { Register } from './pages/register/register';
import { ForgotPassword } from './pages/forgot-password/forgot-password';
import { ResetPassword } from './pages/reset-password/reset-password';

export const routes: Routes = [
  { path: '', redirectTo: 'home', pathMatch: 'full' },
  { path: 'register', component: Register },
  { path: 'login', component: Login },
  { path: 'forgot-password', component: ForgotPassword },
  { path: 'reset-password', component: ResetPassword },
  { path: 'home', component: Home, canActivate: [authGuard] },

    {
    path: 'users/new',
    loadComponent: () => import('./pages/user-form/user-form').then(m => m.UserForm),
    canActivate: [adminGuard]
  },
  {
    path: 'users/:id/edit',
    loadComponent: () => import('./pages/user-form/user-form').then(m => m.UserForm),
    canActivate: [adminGuard]
  },
  
    {
    path: 'users/:id/permissions',
    loadComponent: () => import('./pages/user-permissions/user-permissions').then(m => m.UserPermissions),
    canActivate: [adminGuard]
  },

  { 
    path: 'users', 
    loadComponent: () => import('./pages/users/users.component').then(m => m.UsersComponent),
    canActivate: [adminGuard]
  },
];
