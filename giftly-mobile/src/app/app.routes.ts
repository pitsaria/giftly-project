import { Routes } from '@angular/router';
import { authGuard } from './core/auth.guard';

export const routes: Routes = [
  {
    path: '',
    loadChildren: () => import('./tabs/tabs.routes').then((m) => m.routes),
  },
  {
    path: 'login',
    loadComponent: () => import('./pages/login/login.page').then((m) => m.LoginPage),
  },
  {
    path: 'register',
    loadComponent: () => import('./pages/register/register.page').then((m) => m.RegisterPage),
  },
  {
    path: 'forgot-password',
    loadComponent: () =>
      import('./pages/forgot-password/forgot-password.page').then((m) => m.ForgotPasswordPage),
  },
  {
    path: 'reset-password',
    loadComponent: () => import('./pages/reset-password/reset-password.page').then((m) => m.ResetPasswordPage),
  },
  {
    path: 'verify-otp',
    loadComponent: () => import('./pages/verify-otp/verify-otp.page').then((m) => m.VerifyOtpPage),
  },
  {
    path: 'cart',
    loadComponent: () => import('./pages/cart/cart.page').then((m) => m.CartPage),
    canActivate: [authGuard],
  },
  {
    path: 'checkout',
    loadComponent: () => import('./pages/checkout/checkout.page').then((m) => m.CheckoutPage),
    canActivate: [authGuard],
  },
  {
    path: 'order-confirmation',
    loadComponent: () =>
      import('./pages/order-confirmation/order-confirmation.page').then((m) => m.OrderConfirmationPage),
    canActivate: [authGuard],
  },
  {
    path: 'payment-waiting',
    loadComponent: () =>
      import('./pages/payment-waiting/payment-waiting.page').then((m) => m.PaymentWaitingPage),
    canActivate: [authGuard],
  },
  {
    path: 'build-a-box',
    loadComponent: () => import('./pages/build-a-box/build-a-box.page').then((m) => m.BuildABoxPage),
  },
  {
    path: 'box-checkout',
    loadComponent: () => import('./pages/box-checkout/box-checkout.page').then((m) => m.BoxCheckoutPage),
    canActivate: [authGuard],
  },
  {
    path: 'company-history',
    loadComponent: () =>
      import('./pages/company-history/company-history.page').then((m) => m.CompanyHistoryPage),
  },
  {
    path: 'services',
    loadComponent: () => import('./pages/services/services.page').then((m) => m.ServicesPage),
  },
  {
    path: 'about-app',
    loadComponent: () => import('./pages/about-app/about-app.page').then((m) => m.AboutAppPage),
  },
  {
    path: 'developers',
    loadComponent: () => import('./pages/developers/developers.page').then((m) => m.DevelopersPage),
  },
  {
    path: 'contact',
    loadComponent: () => import('./pages/contact/contact.page').then((m) => m.ContactPage),
  },
];
