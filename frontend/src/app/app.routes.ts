import { Routes } from '@angular/router';
import { authGuard } from './guards/auth.guard';
import { adminGuard } from './guards/admin.guard';
import { doctorGuard } from './guards/doctor.guard';
import { NotFound } from './pages/not-found/not-found';
import { Forbidden } from './pages/forbidden/forbidden';
import { Home } from './pages/home/home';
import { Login } from './pages/login/login';
import { Register } from './pages/register/register';
import { ForgotPassword } from './pages/forgot-password/forgot-password';
import { ResetPassword } from './pages/reset-password/reset-password';

export const routes: Routes = [
  { path: '', redirectTo: 'home', pathMatch: 'full' },
  { path: 'register', title: 'Crear cuenta | SIGT', component: Register },
  { path: 'login', title: 'Ingresar | SIGT', component: Login },
  { path: 'forgot-password', title: 'Recuperar acceso | SIGT', component: ForgotPassword },
  { path: 'reset-password', title: 'Nueva contraseña | SIGT', component: ResetPassword },
  { path: 'home', title: 'Inicio | SIGT', component: Home, canActivate: [authGuard] },

  {
    path: 'users/new', title: 'Nuevo usuario | SIGT',
    loadComponent: () => import('./pages/user-form/user-form').then(m => m.UserForm),
    canActivate: [adminGuard]
  },
  {
    path: 'users/:id/edit', title: 'Editar usuario | SIGT',
    loadComponent: () => import('./pages/user-form/user-form').then(m => m.UserForm),
    canActivate: [adminGuard]
  },

  {
    path: 'users/:id/permissions', title: 'Permisos de usuario | SIGT',
    loadComponent: () => import('./pages/user-permissions/user-permissions').then(m => m.UserPermissions),
    canActivate: [adminGuard]
  },

  {
    path: 'users/:id/schedule', title: 'Agenda del médico | SIGT',
    loadComponent: () => import('./pages/doctor-schedule/doctor-schedule').then(m => m.DoctorScheduleEditor),
    canActivate: [adminGuard]
  },

  // ESPECIALIDADES DE UN MÉDICO
  {
    path: 'users/:id/specialties', title: 'Especialidades del médico | SIGT',
    loadComponent: () => import('./pages/doctor-specialties/doctor-specialties').then(m => m.DoctorSpecialties),
    canActivate: [adminGuard]
  },

  {
    path: 'users', title: 'Usuarios | SIGT',
    loadComponent: () => import('./pages/users/users.component').then(m => m.UsersComponent),
    canActivate: [adminGuard]
  },

  {
    path: 'health-insurances/new', title: 'Nueva obra social | SIGT',
    loadComponent: () => import('./pages/health-insurance-form/health-insurance-form').then(m => m.HealthInsuranceForm),
    canActivate: [adminGuard]
  },

  {
    path: 'health-insurances/:id/edit', title: 'Editar obra social | SIGT',
    loadComponent: () => import('./pages/health-insurance-form/health-insurance-form').then(m => m.HealthInsuranceForm),
    canActivate: [adminGuard]
  },
  {
    path: 'health-insurances', title: 'Obras sociales | SIGT',
    loadComponent: () => import('./pages/health-insurances/health-insurances').then(m => m.HealthInsurances),
    canActivate: [adminGuard]
  },
  // RUTAS DEL MÓDULO DE SALAS
  {
    path: 'salas/disponibilidad', title: 'Disponibilidad de salas | SIGT',
    loadComponent: () => import('./pages/salas-disponibilidad/salas-disponibilidad').then(m => m.SalasDisponibilidadComponent),
    canActivate: [adminGuard]
  },
  {
    path: 'salas/new', title: 'Nueva sala | SIGT',
    loadComponent: () => import('./pages/sala-form/sala-form').then(m => m.SalaFormComponent),
    canActivate: [adminGuard]
  },
  {
    path: 'salas/:id/edit', title: 'Editar sala | SIGT',
    loadComponent: () => import('./pages/sala-form/sala-form').then(m => m.SalaFormComponent),
    canActivate: [adminGuard]
  },
  {
    path: 'salas', title: 'Salas | SIGT',
    loadComponent: () => import('./pages/salas/salas').then(m => m.SalasComponent),
    canActivate: [adminGuard]
  },

  // PACIENTES ASIGNADOS AL MÉDICO
  {
    path: 'my-patients', title: 'Mis pacientes | SIGT',
    loadComponent: () => import('./pages/my-patients/my-patients').then(m => m.MyPatients),
    canActivate: [doctorGuard]
  },
  // ABM DE ESPECIALIDADES
  {
    path: 'specialties/new', title: 'Nueva especialidad | SIGT',
    loadComponent: () => import('./pages/specialty-form/specialty-form').then(m => m.SpecialtyForm),
    canActivate: [adminGuard]
  },
  {
    path: 'specialties/:id/edit', title: 'Editar especialidad | SIGT',
    loadComponent: () => import('./pages/specialty-form/specialty-form').then(m => m.SpecialtyForm),
    canActivate: [adminGuard]
  },
  {
    path: 'specialties', title: 'Especialidades | SIGT',
    loadComponent: () => import('./pages/specialties/specialties').then(m => m.Specialties),
    canActivate: [adminGuard]
  },
  {
    path: 'appointments/new', title: 'Reservar turno | SIGT',
    loadComponent: () => import('./pages/appointment-booking/appointment-booking').then(m => m.AppointmentBookingComponent),
    canActivate: [authGuard]
  },
    {
    path: 'appointments', title: 'Mis turnos | SIGT',
    loadComponent: () => import('./pages/appointments-list/appointments-list').then(m => m.AppointmentsListComponent),
    canActivate: [authGuard]
  },

    {
    path: 'payments/result', title: 'Resultado del pago | SIGT',
    loadComponent: () => import('./pages/payment-result/payment-result').then(m => m.PaymentResultComponent),
    canActivate: [authGuard]
  },

    {
    path: 'profile', title: 'Mi perfil | SIGT',
    loadComponent: () => import('./pages/profile/profile').then(m => m.ProfileComponent),
    canActivate: [authGuard]
  },
  {
    path: 'payments', title: 'Pagos | SIGT',
    loadComponent: () => import('./pages/payments-list/payments-list').then(m => m.PaymentsListComponent),
    canActivate: [authGuard]
  },
  {
    path: 'notifications', title: 'Notificaciones | SIGT',
    loadComponent: () => import('./pages/notifications/notifications').then(m => m.NotificationsComponent),
    canActivate: [authGuard]
  },
  { path: 'forbidden', title: 'Acceso denegado | SIGT', component: Forbidden },
  { path: '**', title: 'Página no encontrada | SIGT', component: NotFound },
];
