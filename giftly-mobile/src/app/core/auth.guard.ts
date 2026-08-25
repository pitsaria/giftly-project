import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from './auth.service';

// Mirrors the website's `if (!isset($_SESSION['user_id'])) header("Location: login.php")` guard
// used at the top of cart.php / checkout.php / profile.php.
export const authGuard: CanActivateFn = () => {
  const auth = inject(AuthService);
  const router = inject(Router);

  if (auth.isLoggedIn()) {
    return true;
  }

  return router.parseUrl('/login');
};
