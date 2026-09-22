import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/auth.service';

export const adminGuard: CanActivateFn = () => {
  const auth = inject(AuthService);
  const router = inject(Router);

  if (!auth.isLoggedIn()) {
    return router.createUrlTree(['/login']);
  }

  const userRole = auth.user()?.role.name;
  if (userRole === 'Administrator' || userRole === 'SuperAdmin') {
    return true;
  }

  return router.createUrlTree(['/home']);
};