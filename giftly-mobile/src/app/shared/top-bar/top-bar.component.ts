import { Component, EventEmitter, Input, Output, effect, inject, signal } from '@angular/core';
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

  // Home sets this true while its full-bleed hero is under the bar, so the
  // toolbar drops its background and hairline and floats over the artwork;
  // Home flips it back to false once the page is scrolled past the hero.
  @Input() transparent = false;

  // Bumps the cart badge whenever the count changes, instead of it just
  // silently updating — a small nudge that something was actually added.
  readonly bumping = signal(false);
  private bumpTimer: ReturnType<typeof setTimeout> | undefined;

  constructor() {
    addIcons({ searchOutline, cartOutline, personCircleOutline });

    let prevCount = this.cart.itemCount();
    effect(() => {
      const count = this.cart.itemCount();
      if (count !== prevCount) {
        prevCount = count;
        this.bumping.set(false);
        // Force a reflow so re-adding the class restarts the animation even
        // if it fires again before the previous bump finished.
        requestAnimationFrame(() => this.bumping.set(true));
        clearTimeout(this.bumpTimer);
        this.bumpTimer = setTimeout(() => this.bumping.set(false), 400);
      }
    });
  }

  onSearch(): void {
    this.search.emit();
    this.router.navigateByUrl('/tabs/shop');
  }
}
