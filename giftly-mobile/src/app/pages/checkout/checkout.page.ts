import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import {
  IonHeader,
  IonToolbar,
  IonTitle,
  IonContent,
  IonButton,
  IonIcon,
  IonInput,
  IonTextarea,
  IonSelect,
  IonSelectOption,
  IonSpinner,
  ToastController,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import { personOutline, giftOutline, cardOutline, cashOutline } from 'ionicons/icons';
import { Address, CartItem } from '../../core/models';
import { AddressService } from '../../core/address.service';
import { CartService } from '../../core/cart.service';
import { OrderService } from '../../core/order.service';
import { AuthService } from '../../core/auth.service';
import { describeError } from '../../core/http-error';

// Mirrors giftly_project/checkout_selected.php.
// Fetched state lives in signals — guaranteed to trigger a re-render on
// write, unlike plain fields mutated inside an async continuation.
@Component({
  selector: 'app-checkout',
  templateUrl: 'checkout.page.html',
  styleUrls: ['checkout.page.scss'],
  imports: [
    CommonModule,
    FormsModule,
    IonHeader,
    IonToolbar,
    IonTitle,
    IonContent,
    IonButton,
    IonIcon,
    IonInput,
    IonTextarea,
    IonSelect,
    IonSelectOption,
    IonSpinner,
  ],
})
export class CheckoutPage implements OnInit {
  private addressSvc = inject(AddressService);
  private cart = inject(CartService);
  private orderSvc = inject(OrderService);
  private auth = inject(AuthService);
  private router = inject(Router);
  private toastCtrl = inject(ToastController);

  readonly addresses = signal<Address[]>([]);
  readonly cartItems = signal<CartItem[]>([]);
  readonly submitting = signal(false);
  readonly loading = signal(true);
  readonly error = signal<string | null>(null);
  readonly slowLoad = signal(false);
  private slowLoadTimer: ReturnType<typeof setTimeout> | undefined;
  private loadToken = 0;

  fullname = this.auth.user()?.name ?? '';
  addressId: number | null = null;
  address = '';
  city = '';
  senderPhone = '';
  deliveryType: 'me' | 'recipient' = 'me';
  recipientName = '';
  recipientPhone = '';
  deliveryDate = new Date(Date.now() + 3 * 86400000).toISOString().substring(0, 10);
  deliveryTime = '08:00';
  giftMessage = '';
  paymentMethod: 'cod' | 'card' = 'cod';

  constructor() {
    addIcons({ personOutline, giftOutline, cardOutline, cashOutline });
  }

  async ngOnInit(): Promise<void> {
    await this.load();
  }

  async load(): Promise<void> {
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
      const [addresses, cart] = await Promise.all([this.addressSvc.getAll(), this.cart.getCart()]);
      if (token !== this.loadToken) return;

      this.addresses.set(addresses);
      const selectedIds = new Set(this.cart.selectedCartIds());
      this.cartItems.set(cart.items.filter((i) => selectedIds.has(i.cart_id)));

      if (addresses.length) {
        this.selectAddress(addresses[0].id);
      }
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

  selectAddress(id: number): void {
    this.addressId = id;
    const found = this.addresses().find((a) => a.id === id);
    if (found) {
      this.address = found.address;
      this.city = found.city;
    }
  }

  total(): number {
    return this.cartItems().reduce((sum, i) => sum + i.subtotal, 0);
  }

  shippingFee(): number {
    const t = this.total();
    return t > 0 && t < 300 ? 50 : 0;
  }

  grandTotal(): number {
    return this.total() + this.shippingFee();
  }

  async placeOrder(): Promise<void> {
    if (!this.address || !this.city || !this.fullname) {
      await this.toast('Please fill in your name and delivery address.');
      return;
    }
    const cartItems = this.cartItems();
    if (cartItems.length === 0) {
      await this.toast('No items selected for checkout.');
      return;
    }

    this.submitting.set(true);
    try {
      const orderId = await this.orderSvc.createOrder({
        selected_ids: cartItems.map((i) => i.cart_id),
        fullname: this.fullname,
        address: this.address,
        city: this.city,
        payment_method: this.paymentMethod,
        delivery_date: this.deliveryDate,
        delivery_time: this.deliveryTime.length === 5 ? `${this.deliveryTime}:00` : this.deliveryTime,
        gift_message: this.giftMessage,
        sender_phone: this.senderPhone,
        recipient_name: this.deliveryType === 'recipient' ? this.recipientName : undefined,
        recipient_phone: this.deliveryType === 'recipient' ? this.recipientPhone : undefined,
      });
      this.orderSvc.lastOrder.set({
        orderId,
        total: this.grandTotal(),
        paymentMethod: this.paymentMethod,
        deliveryDate: this.deliveryDate,
        deliveryTime: this.deliveryTime,
        address: this.address,
        city: this.city,
        recipientName: this.deliveryType === 'recipient' ? this.recipientName : undefined,
        recipientPhone: this.deliveryType === 'recipient' ? this.recipientPhone : undefined,
        giftMessage: this.giftMessage || undefined,
      });
      this.cart.selectedCartIds.set([]);
      await this.cart.getCart();
      this.router.navigateByUrl('/order-confirmation');
    } catch (err) {
      await this.toast(describeError(err));
    } finally {
      this.submitting.set(false);
    }
  }

  private async toast(message: string): Promise<void> {
    const t = await this.toastCtrl.create({ message, duration: 2200, position: 'bottom' });
    await t.present();
  }
}
