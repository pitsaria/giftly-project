import { Routes } from '@angular/router';
import { TabsPage } from './tabs.page';
import { authGuard } from '../core/auth.guard';

export const routes: Routes = [
  {
    path: 'tabs',
    component: TabsPage,
    children: [
      {
        path: 'home',
        loadComponent: () => import('../pages/home/home.page').then((m) => m.HomePage),
      },
      {
        path: 'shop',
        loadComponent: () => import('../pages/shop/shop.page').then((m) => m.ShopPage),
      },
      {
        path: 'cart',
        loadComponent: () => import('../pages/cart/cart.page').then((m) => m.CartPage),
        canActivate: [authGuard],
      },
      {
        path: 'profile',
        loadComponent: () => import('../pages/profile/profile.page').then((m) => m.ProfilePage),
      },
      {
        path: '',
        redirectTo: '/tabs/home',
        pathMatch: 'full',
      },
    ],
  },
  {
    path: '',
    redirectTo: '/tabs/home',
    pathMatch: 'full',
  },
];
