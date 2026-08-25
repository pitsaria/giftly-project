import { Component, EnvironmentInjector, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonTabs, IonTabBar, IonTabButton, IonIcon, IonLabel, IonBadge } from '@ionic/angular';
import { addIcons } from 'ionicons';
import { homeOutline, bagOutline, cartOutline, personOutline } from 'ionicons/icons';
import { AuthService } from '../core/auth.service';
import { CartService } from '../core/cart.service';

@Component({
  selector: 'app-tabs',
  templateUrl: 'tabs.page.html',
  styleUrls: ['tabs.page.scss'],
  imports: [CommonModule, IonTabs, IonTabBar, IonTabButton, IonIcon, IonLabel, IonBadge],
})
export class TabsPage implements OnInit {
  public environmentInjector = inject(EnvironmentInjector);
  auth = inject(AuthService);
  cart = inject(CartService);

  constructor() {
    addIcons({ homeOutline, bagOutline, cartOutline, personOutline });
  }

  async ngOnInit(): Promise<void> {
    if (this.auth.isLoggedIn()) {
      await this.cart.getCart();
    }
  }
}
