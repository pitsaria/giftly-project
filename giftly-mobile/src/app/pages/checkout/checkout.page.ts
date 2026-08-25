import { Component, OnInit, inject } from '@angular/core';
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
  ToastController,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import { personOutline, giftOutline, cardOutline, cashOutline } from 'ionicons/icons';
import { Address, CartItem } from '../../core/models';
import { AddressService } from '../../core/address.service';
import { CartService } from '../../core/cart.service';
import { OrderService } from '../../core/order.service';
import { AuthService } from '../../core/auth.service';

// Mirrors giftly_project/checkout_selected.php.
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
  ],
})
export class CheckoutPage implements OnInit {
  private addressSvc = inject(AddressService);
  private cart = inject(CartService);
  private orderSvc = inject(OrderService);
  private auth = inject(AuthService);
  private router = inject(Router);
  private toastCtrl = inject(ToastController);

  addresses: Address[] = [];
  cartItems: CartItem[] = [];
  submitting = false;

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
    const [addresses, cart] = await Promise.all([this.addressSvc.getAll(), this.cart.getCart()]);
    this.addresses = addresses;
    const selectedIds = new Set(this.cart.selectedCartIds());
    this.cartItems = cart.items.filter((i) => selectedIds.has(i.cart_id));

    if (this.addresses.length) {
      this.selectAddress(this.addresses[0].id);
    }
  }

  selectAddress(id: number): void {
    this.addressId = id;
    const found = this.addresses.find((a) => a.id === id);
    if (found) {
      this.address = found.address;
      this.city = found.city;
    }
  }

  total(): number {
    return this.cartItems.reduce((sum, i) => sum + i.subtotal, 0);
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
    if (this.cartItems.length === 0) {
      await this.toast('No items selected for checkout.');
      return;
    }

    this.submitting = true;
    try {
      const orderId = await this.orderSvc.createOrder({
        selected_ids: this.cartItems.map((i) => i.cart_id),
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
      this.cart.selectedCartIds.set([]);
      await this.cart.getCart();
      await this.toast('Order placed successfully!');
      this.router.navigateByUrl(`/tabs/profile?tab=orders`);
    } catch {
      await this.toast('Failed to place order. Please try again.');
    } finally {
      this.submitting = false;
    }
  }

  private async toast(message: string): Promise<void> {
    const t = await this.toastCtrl.create({ message, duration: 2200, position: 'bottom' });
    await t.present();
  }
}
