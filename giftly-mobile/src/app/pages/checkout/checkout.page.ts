import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import {
  IonHeader,
  IonToolbar,
  IonTitle,
  IonButtons,
  IonBackButton,
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
import { personOutline, giftOutline, cardOutline, cashOutline, lockClosedOutline } from 'ionicons/icons';
import { Address, CartItem } from '../../core/models';
import { AddressService } from '../../core/address.service';
import { CartService } from '../../core/cart.service';
import { OrderService, PaymentMethod } from '../../core/order.service';
import { PaymentsService } from '../../core/payments.service';
import { AuthService } from '../../core/auth.service';
import { describeError } from '../../core/http-error';
import { formatCardExpiry, formatCardNumber, formatCvc, validateCard } from '../../core/card';
import { PhPhoneInputComponent } from '../../shared/ph-phone-input/ph-phone-input.component';

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
    IonButtons,
    IonBackButton,
    IonContent,
    IonButton,
    IonIcon,
    IonInput,
    IonTextarea,
    IonSelect,
    IonSelectOption,
    IonSpinner,
    PhPhoneInputComponent,
  ],
})
export class CheckoutPage implements OnInit {
  private addressSvc = inject(AddressService);
  private cart = inject(CartService);
  private orderSvc = inject(OrderService);
  private payments = inject(PaymentsService);
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
  senderPhoneDigits = '';
  senderPhoneTouched = false;
  deliveryType: 'me' | 'recipient' = 'me';
  recipientName = '';
  recipientPhoneDigits = '';
  recipientPhoneTouched = false;
  deliveryDate = new Date(Date.now() + 3 * 86400000).toISOString().substring(0, 10);
  deliveryTime = '08:00';
  giftMessage = '';
  paymentMethod: PaymentMethod = 'cod';
  cardHolder = '';
  cardNumber = '';
  cardExpiry = '';
  cardCvc = '';
  readonly onlineEnabled = signal(false);

  constructor() {
    addIcons({ personOutline, giftOutline, cardOutline, cashOutline, lockClosedOutline });
  }

  async ngOnInit(): Promise<void> {
    this.payments.config().then((c) => this.onlineEnabled.set(c.enabled));
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
        const def = addresses.find((a) => a.is_default) ?? addresses[0];
        this.selectAddress(def.id);
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

  onCardNumberInput(): void {
    this.cardNumber = formatCardNumber(this.cardNumber);
  }

  onCardExpiryInput(): void {
    this.cardExpiry = formatCardExpiry(this.cardExpiry);
  }

  onCardCvcInput(): void {
    this.cardCvc = formatCvc(this.cardCvc);
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
    // Every field is required except the gift message, mirroring
    // checkout_selected.php's `required` attributes on the website.
    this.senderPhoneTouched = true;
    if (this.deliveryType === 'recipient') this.recipientPhoneTouched = true;

    const missing: string[] = [];
    if (!this.fullname.trim()) missing.push('Full Name');
    if (!this.address.trim()) missing.push('Street Address');
    if (!this.city.trim()) missing.push('City');
    if (!/^\d{10}$/.test(this.senderPhoneDigits)) missing.push('Sender Phone (10 digits after +63)');
    if (!this.deliveryDate) missing.push('Delivery Date');
    if (!this.deliveryTime) missing.push('Delivery Time');
    if (this.deliveryType === 'recipient') {
      if (!this.recipientName.trim()) missing.push('Recipient Name');
      if (!/^\d{10}$/.test(this.recipientPhoneDigits)) missing.push('Recipient Phone (10 digits after +63)');
    }
    if (missing.length) {
      await this.toast(`Please fill in: ${missing.join(', ')}.`);
      return;
    }

    if (this.paymentMethod === 'card') {
      const cardError = validateCard({
        cardHolder: this.cardHolder,
        cardNumber: this.cardNumber,
        cardExpiry: this.cardExpiry,
        cardCvc: this.cardCvc,
      });
      if (cardError) {
        await this.toast(cardError);
        return;
      }
    }

    const cartItems = this.cartItems();
    if (cartItems.length === 0 || this.total() <= 0) {
      await this.toast('No items selected for checkout.');
      return;
    }
    if (cartItems.some((i) => i.unavailable || i.is_active === false)) {
      await this.toast('Remove the unavailable item(s) from your cart before checking out.');
      return;
    }

    const senderPhone = `63${this.senderPhoneDigits}`;
    const recipientPhone = this.deliveryType === 'recipient' ? `63${this.recipientPhoneDigits}` : undefined;

    this.submitting.set(true);
    try {
      const placed = await this.orderSvc.createOrder({
        selected_ids: cartItems.map((i) => i.cart_id),
        fullname: this.fullname,
        address: this.address,
        city: this.city,
        payment_method: this.paymentMethod,
        delivery_date: this.deliveryDate,
        delivery_time: this.deliveryTime.length === 5 ? `${this.deliveryTime}:00` : this.deliveryTime,
        gift_message: this.giftMessage,
        sender_phone: senderPhone,
        recipient_name: this.deliveryType === 'recipient' ? this.recipientName : undefined,
        recipient_phone: recipientPhone,
        ...(this.paymentMethod === 'card'
          ? {
              card_number: this.cardNumber,
              card_holder: this.cardHolder,
              card_expiry: this.cardExpiry,
              card_cvc: this.cardCvc,
            }
          : {}),
      });

      this.cart.selectedCartIds.set([]);
      await this.cart.getCart();

      if (this.paymentMethod === 'online') {
        if (placed.checkoutUrl) {
          this.payments.openCheckout(placed.checkoutUrl);
        } else if (placed.payError) {
          await this.toast(placed.payError);
        }
        this.router.navigate(['/payment-waiting'], { queryParams: { order_id: placed.orderId } });
        return;
      }

      this.orderSvc.lastOrder.set({
        orderId: placed.orderId,
        total: this.grandTotal(),
        paymentMethod: this.paymentMethod,
        deliveryDate: this.deliveryDate,
        deliveryTime: this.deliveryTime,
        address: this.address,
        city: this.city,
        recipientName: this.deliveryType === 'recipient' ? this.recipientName : undefined,
        recipientPhone,
        giftMessage: this.giftMessage || undefined,
      });
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
