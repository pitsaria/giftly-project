import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { IonHeader, IonToolbar, IonTitle, IonContent, IonIcon, IonButton } from '@ionic/angular';
import { addIcons } from 'ionicons';
import { checkmarkOutline, bagOutline, heartOutline } from 'ionicons/icons';
import { OrderService } from '../../core/order.service';

// Mirrors the inline "Order Placed!" success card in checkout_selected.php.
@Component({
  selector: 'app-order-confirmation',
  templateUrl: 'order-confirmation.page.html',
  styleUrls: ['order-confirmation.page.scss'],
  imports: [CommonModule, IonHeader, IonToolbar, IonTitle, IonContent, IonIcon, IonButton],
})
export class OrderConfirmationPage implements OnInit {
  private orderSvc = inject(OrderService);
  private router = inject(Router);

  order = this.orderSvc.lastOrder();

  constructor() {
    addIcons({ checkmarkOutline, bagOutline, heartOutline });
  }

  ngOnInit(): void {
    // Guards against reaching this screen directly (e.g. a page refresh)
    // without an order having just been placed.
    if (!this.order) {
      this.router.navigateByUrl('/tabs/shop');
    }
  }

  paymentLabel(): string {
    const m = (this.order?.paymentMethod ?? 'cod').toLowerCase();
    if (m === 'cod') return 'Cash on Delivery';
    if (m === 'card') return 'Card';
    if (m === 'gcash') return 'GCash';
    if (m === 'paymaya' || m === 'maya') return 'Maya';
    if (m === 'online') return 'Paid online';
    return 'Paid online';
  }

  viewOrders(): void {
    this.router.navigateByUrl('/tabs/orders');
  }

  continueShopping(): void {
    this.router.navigateByUrl('/tabs/shop');
  }
}
