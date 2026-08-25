import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import {
  IonHeader,
  IonToolbar,
  IonTitle,
  IonContent,
  IonFooter,
  IonCheckbox,
  IonIcon,
  IonButton,
  ToastController,
  AlertController,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import { removeOutline, addOutline, trashOutline, bagHandleOutline } from 'ionicons/icons';
import { CartItem } from '../../core/models';
import { CartService } from '../../core/cart.service';
import { environment } from '../../../environments/environment';

// Mirrors giftly_project/cart.php.
@Component({
  selector: 'app-cart',
  templateUrl: 'cart.page.html',
  styleUrls: ['cart.page.scss'],
  imports: [CommonModule, IonHeader, IonToolbar, IonTitle, IonContent, IonFooter, IonCheckbox, IonIcon, IonButton],
})
export class CartPage implements OnInit {
  private cart = inject(CartService);
  private router = inject(Router);
  private toastCtrl = inject(ToastController);
  private alertCtrl = inject(AlertController);

  readonly uploadsUrl = environment.uploadsUrl;
  items: CartItem[] = [];
  selected = new Set<number>();
  loading = true;

  constructor() {
    addIcons({ removeOutline, addOutline, trashOutline, bagHandleOutline });
  }

  async ngOnInit(): Promise<void> {
    await this.refresh();
  }

  async ionViewWillEnter(): Promise<void> {
    await this.refresh();
  }

  async refresh(): Promise<void> {
    this.loading = true;
    try {
      const cart = await this.cart.getCart();
      this.items = cart.items;
      // Keep previously selected items selected if still in the cart.
      const validIds = new Set(this.items.map((i) => i.cart_id));
      this.selected = new Set([...this.selected].filter((id) => validIds.has(id)));
    } finally {
      this.loading = false;
    }
  }

  toggleSelect(cartId: number): void {
    if (this.selected.has(cartId)) {
      this.selected.delete(cartId);
    } else {
      this.selected.add(cartId);
    }
  }

  allSelected(): boolean {
    return this.items.length > 0 && this.selected.size === this.items.length;
  }

  toggleSelectAll(): void {
    if (this.allSelected()) {
      this.selected.clear();
    } else {
      this.selected = new Set(this.items.map((i) => i.cart_id));
    }
  }

  selectedTotal(): number {
    return this.items
      .filter((i) => this.selected.has(i.cart_id))
      .reduce((sum, i) => sum + i.subtotal, 0);
  }

  async increase(item: CartItem): Promise<void> {
    await this.cart.updateQuantity(item.cart_id, 'increase');
    await this.refresh();
  }

  async decrease(item: CartItem): Promise<void> {
    await this.cart.updateQuantity(item.cart_id, 'decrease');
    await this.refresh();
  }

  async remove(item: CartItem): Promise<void> {
    const alert = await this.alertCtrl.create({
      header: 'Remove item?',
      message: `Remove ${item.name} from your cart?`,
      buttons: [
        { text: 'Cancel', role: 'cancel' },
        {
          text: 'Remove',
          role: 'destructive',
          handler: async () => {
            await this.cart.removeItem(item.cart_id);
            await this.refresh();
          },
        },
      ],
    });
    await alert.present();
  }

  async checkout(): Promise<void> {
    if (this.selected.size === 0) {
      const toast = await this.toastCtrl.create({
        message: 'Select at least one item to checkout',
        duration: 1800,
      });
      await toast.present();
      return;
    }

    const result = await this.cart.verifyStock([...this.selected]);
    if (!result.can_proceed) {
      await this.refresh();
      const toast = await this.toastCtrl.create({
        message: 'Some items had stock changes — please review your cart.',
        duration: 2500,
      });
      await toast.present();
      return;
    }

    this.cart.selectedCartIds.set([...this.selected]);
    this.router.navigateByUrl('/checkout');
  }
}
