import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/auth.service';

export const superAdminGuard: CanActivateFn = () => {
  const auth = inject(AuthService);
  const router = inject(Router);

  if (!auth.isLoggedIn()) {
    return router.createUrlTree(['/login']);
  }

  const role = auth.user()?.role.name;
  if (role === 'SuperAdmin') {
    return true;
  }

  // Denegar acceso a cualquier otro rol (Administrator, Doctor, Patient)
  return router.createUrlTree(['/forbidden']);
};