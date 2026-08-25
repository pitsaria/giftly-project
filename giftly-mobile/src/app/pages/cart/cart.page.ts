import { Component, OnInit, inject, signal } from '@angular/core';
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
  IonSpinner,
  ToastController,
  AlertController,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import { removeOutline, addOutline, trashOutline, bagHandleOutline } from 'ionicons/icons';
import { CartItem } from '../../core/models';
import { CartService } from '../../core/cart.service';
import { describeError } from '../../core/http-error';
import { environment } from '../../../environments/environment';

// Mirrors giftly_project/cart.php.
// State is held in signals rather than plain fields: signal writes always
// schedule a re-render regardless of zone.js/zoneless configuration, which
// plain property mutation inside an async continuation is not guaranteed to.
@Component({
  selector: 'app-cart',
  templateUrl: 'cart.page.html',
  styleUrls: ['cart.page.scss'],
  imports: [
    CommonModule,
    IonHeader,
    IonToolbar,
    IonTitle,
    IonContent,
    IonFooter,
    IonCheckbox,
    IonIcon,
    IonButton,
    IonSpinner,
  ],
})
export class CartPage implements OnInit {
  private cart = inject(CartService);
  private router = inject(Router);
  private toastCtrl = inject(ToastController);
  private alertCtrl = inject(AlertController);

  readonly uploadsUrl = environment.uploadsUrl;
  readonly items = signal<CartItem[]>([]);
  readonly selected = signal<Set<number>>(new Set());
  readonly loading = signal(true);
  readonly error = signal<string | null>(null);
  readonly slowLoad = signal(false);
  private slowLoadTimer: ReturnType<typeof setTimeout> | undefined;
  private loadToken = 0;

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
    // Guards against two overlapping refresh() calls (e.g. ngOnInit and
    // ionViewWillEnter firing close together) stomping on each other's
    // loading/timer state.
    const token = ++this.loadToken;

    this.loading.set(true);
    this.error.set(null);
    this.slowLoad.set(false);
    clearTimeout(this.slowLoadTimer);
    this.slowLoadTimer = setTimeout(() => {
      if (token === this.loadToken) {
        this.slowLoad.set(true);
      }
    }, 6000);

    try {
      const cart = await this.cart.getCart();
      if (token !== this.loadToken) return;
      this.items.set(cart.items);
      // Keep previously selected items selected if still in the cart.
      const validIds = new Set(cart.items.map((i) => i.cart_id));
      this.selected.update((prev) => new Set([...prev].filter((id) => validIds.has(id))));
    } catch (err) {
      if (token !== this.loadToken) return;
      this.error.set(describeError(err));
    } finally {
      if (token === this.loadToken) {
        clearTimeout(this.slowLoadTimer);
        this.loading.set(false);
      }
    }
  }

  toggleSelect(cartId: number): void {
    this.selected.update((prev) => {
      const next = new Set(prev);
      if (next.has(cartId)) {
        next.delete(cartId);
      } else {
        next.add(cartId);
      }
      return next;
    });
  }

  allSelected(): boolean {
    const items = this.items();
    return items.length > 0 && this.selected().size === items.length;
  }

  toggleSelectAll(): void {
    if (this.allSelected()) {
      this.selected.set(new Set());
    } else {
      this.selected.set(new Set(this.items().map((i) => i.cart_id)));
    }
  }

  selectedTotal(): number {
    const selected = this.selected();
    return this.items()
      .filter((i) => selected.has(i.cart_id))
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
    const selectedIds = [...this.selected()];
    if (selectedIds.length === 0) {
      const toast = await this.toastCtrl.create({
        message: 'Select at least one item to checkout',
        duration: 1800,
      });
      await toast.present();
      return;
    }

    const result = await this.cart.verifyStock(selectedIds);
    if (!result.can_proceed) {
      await this.refresh();
      const toast = await this.toastCtrl.create({
        message: 'Some items had stock changes — please review your cart.',
        duration: 2500,
      });
      await toast.present();
      return;
    }

    this.cart.selectedCartIds.set(selectedIds);
    this.router.navigateByUrl('/checkout');
  }
}
