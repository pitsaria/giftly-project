import { Component, EventEmitter, Output, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { Router } from '@angular/router';
import { IonHeader, IonToolbar, IonButtons, IonButton, IonIcon, IonBadge } from '@ionic/angular';
import { addIcons } from 'ionicons';
import { searchOutline, cartOutline, personCircleOutline } from 'ionicons/icons';
import { AuthService } from '../../core/auth.service';
import { CartService } from '../../core/cart.service';

// Shared header reused across every tab page (Home/Shop/Orders/Profile) so the
// brand, search, cart and login/profile entry points stay identical and in sync.
@Component({
  selector: 'app-top-bar',
  templateUrl: 'top-bar.component.html',
  styleUrls: ['top-bar.component.scss'],
  imports: [CommonModule, RouterLink, IonHeader, IonToolbar, IonButtons, IonButton, IonIcon, IonBadge],
})
export class TopBarComponent {
  auth = inject(AuthService);
  cart = inject(CartService);
  private router = inject(Router);

  // Pages other than Shop just want the icon to take them to Shop's search
  // box; the Shop page itself listens to reveal its inline searchbar instead.
  @Output() search = new EventEmitter<void>();

  constructor() {
    addIcons({ searchOutline, cartOutline, personCircleOutline });
  }

  onSearch(): void {
    this.search.emit();
    this.router.navigateByUrl('/tabs/shop');
  }
}
