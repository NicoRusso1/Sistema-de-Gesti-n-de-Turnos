import { Routes } from '@angular/router';
import { authGuard } from './guards/auth.guard';
import { adminGuard } from './guards/admin.guard';
import { doctorGuard } from './guards/doctor.guard';
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
    path: 'users/:id/schedule',
    loadComponent: () => import('./pages/doctor-schedule/doctor-schedule').then(m => m.DoctorScheduleEditor),
    canActivate: [adminGuard]
  },

  {
    path: 'users',
    loadComponent: () => import('./pages/users/users.component').then(m => m.UsersComponent),
    canActivate: [adminGuard]
  },

  {
    path: 'health-insurances/new',
    loadComponent: () => import('./pages/health-insurance-form/health-insurance-form').then(m => m.HealthInsuranceForm),
    canActivate: [adminGuard]
  },

  {
    path: 'health-insurances/:id/edit',
    loadComponent: () => import('./pages/health-insurance-form/health-insurance-form').then(m => m.HealthInsuranceForm),
    canActivate: [adminGuard]
  },
  {
    path: 'health-insurances',
    loadComponent: () => import('./pages/health-insurances/health-insurances').then(m => m.HealthInsurances),
    canActivate: [adminGuard]
  },
  // RUTAS DEL MÓDULO DE SALAS
  {
    path: 'salas/disponibilidad',
    loadComponent: () => import('./pages/salas-disponibilidad/salas-disponibilidad').then(m => m.SalasDisponibilidadComponent),
    canActivate: [adminGuard]
  },
  {
    path: 'salas/new',
    loadComponent: () => import('./pages/sala-form/sala-form').then(m => m.SalaFormComponent),
    canActivate: [adminGuard]
  },
  {
    path: 'salas/:id/edit',
    loadComponent: () => import('./pages/sala-form/sala-form').then(m => m.SalaFormComponent),
    canActivate: [adminGuard]
  },
  {
    path: 'salas',
    loadComponent: () => import('./pages/salas/salas').then(m => m.SalasComponent),
    canActivate: [adminGuard]
  },

  // PACIENTES ASIGNADOS AL MÉDICO
  {
    path: 'my-patients',
    loadComponent: () => import('./pages/my-patients/my-patients').then(m => m.MyPatients),
    canActivate: [doctorGuard]
  },
  // ABM DE ESPECIALIDADES
  {
    path: 'specialties/new',
    loadComponent: () => import('./pages/specialty-form/specialty-form').then(m => m.SpecialtyForm),
    canActivate: [adminGuard]
  },
  {
    path: 'specialties/:id/edit',
    loadComponent: () => import('./pages/specialty-form/specialty-form').then(m => m.SpecialtyForm),
    canActivate: [adminGuard]
  },
  {
    path: 'specialties',
    loadComponent: () => import('./pages/specialties/specialties').then(m => m.Specialties),
    canActivate: [adminGuard]
  },
];
