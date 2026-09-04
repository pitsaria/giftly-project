import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
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
  IonSelect,
  IonSelectOption,
  IonSpinner,
  ToastController,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import { personOutline, giftOutline, cardOutline, cashOutline, lockClosedOutline, createOutline } from 'ionicons/icons';
import { Address, Box } from '../../core/models';
import { AddressService } from '../../core/address.service';
import { BoxService } from '../../core/box.service';
import { OrderService, PaymentMethod } from '../../core/order.service';
import { PaymentsService } from '../../core/payments.service';
import { AuthService } from '../../core/auth.service';
import { describeError } from '../../core/http-error';
import { formatCardExpiry, formatCardNumber, formatCvc, validateCard } from '../../core/card';
import { PhPhoneInputComponent } from '../../shared/ph-phone-input/ph-phone-input.component';
import { AddressSearchComponent, AddressParts } from '../../shared/address-search/address-search.component';
import { ImgUrlPipe } from '../../shared/img-url.pipe';

// Mirrors giftly_project/box_checkout.php — checkout for a single saved box.
@Component({
  selector: 'app-box-checkout',
  templateUrl: 'box-checkout.page.html',
  styleUrls: ['box-checkout.page.scss'],
  imports: [
    CommonModule,
    FormsModule,
    RouterLink,
    IonHeader,
    IonToolbar,
    IonTitle,
    IonButtons,
    IonBackButton,
    IonContent,
    IonButton,
    IonIcon,
    IonInput,
    IonSelect,
    IonSelectOption,
    IonSpinner,
    PhPhoneInputComponent,
    AddressSearchComponent,
    ImgUrlPipe,
  ],
})
export class BoxCheckoutPage implements OnInit {
  private addressSvc = inject(AddressService);
  private boxSvc = inject(BoxService);
  private orderSvc = inject(OrderService);
  private payments = inject(PaymentsService);
  private auth = inject(AuthService);
  private router = inject(Router);
  private route = inject(ActivatedRoute);
  private toastCtrl = inject(ToastController);

  readonly box = signal<Box | null>(null);
  readonly addresses = signal<Address[]>([]);
  readonly loading = signal(true);
  readonly error = signal<string | null>(null);
  readonly submitting = signal(false);
  private boxId = 0;

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
  paymentMethod: PaymentMethod = 'cod';
  readonly onlineEnabled = signal(false);
  cardHolder = '';
  cardNumber = '';
  cardExpiry = '';
  cardCvc = '';

  readonly blocked = computed(() => (this.box()?.issues.length ?? 0) > 0);
  readonly shippingFee = computed(() => {
    const sub = this.box()?.subtotal ?? 0;
    return sub > 0 && sub < 300 ? 50 : 0;
  });
  readonly grandTotal = computed(
    () => (this.box()?.subtotal ?? 0) + this.shippingFee() + (this.box()?.box_price ?? 0)
  );

  constructor() {
    addIcons({ personOutline, giftOutline, cardOutline, cashOutline, lockClosedOutline, createOutline });
  }

  async ngOnInit(): Promise<void> {
    this.boxId = Number(this.route.snapshot.queryParamMap.get('box_id')) || 0;
    this.payments.config().then((c) => this.onlineEnabled.set(c.enabled));
    await this.load();
  }

  async load(): Promise<void> {
    this.loading.set(true);
    this.error.set(null);
    try {
      if (!this.boxId) throw new Error('No box selected.');
      const [box, addresses] = await Promise.all([
        this.boxSvc.getBox(this.boxId),
        this.addressSvc.getAll().catch(() => []),
      ]);
      this.box.set(box);
      this.addresses.set(addresses);
      if (addresses.length) {
        const def = addresses.find((a) => a.is_default) ?? addresses[0];
        this.selectAddress(def.id);
      }
    } catch (err) {
      this.error.set(describeError(err));
    } finally {
      this.loading.set(false);
    }
  }

  selectAddress(id: number): void {
    this.addressId = id;
    const found = this.addresses().find((a) => a.id === id);
    if (found) {
      this.address = found.address;
      this.city = `${found.city}${found.province ? ', ' + found.province : ''}`;
    }
  }

  onAddressPicked(d: AddressParts): void {
    if (d.street) this.address = d.street;
    const line = [d.barangay, d.city, d.province || d.region].filter(Boolean).join(', ');
    if (line) this.city = line;
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

  cardStyleLine(): string {
    const b = this.box();
    if (!b) return '';
    if (b.card_style && b.card_style !== 'simple') return `${b.card_style} card`;
    return '';
  }

  async placeOrder(): Promise<void> {
    if (this.blocked()) {
      await this.toast('Some items in this box are unavailable — edit the box first.');
      return;
    }
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

    const senderPhone = `63${this.senderPhoneDigits}`;
    const recipientPhone = this.deliveryType === 'recipient' ? `63${this.recipientPhoneDigits}` : undefined;

    this.submitting.set(true);
    try {
      const res = await this.boxSvc.checkoutBox({
        box_id: this.boxId,
        fullname: this.fullname,
        sender_phone: senderPhone,
        address: this.address,
        city: this.city,
        payment_method: this.paymentMethod,
        delivery_date: this.deliveryDate,
        delivery_time: this.deliveryTime.length === 5 ? `${this.deliveryTime}:00` : this.deliveryTime,
        delivery_type: this.deliveryType,
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
      await this.boxSvc.listBoxes().catch(() => []);

      if (this.paymentMethod === 'online') {
        if (res.checkout_url) {
          this.payments.openCheckout(res.checkout_url);
        } else if (res.pay_error) {
          await this.toast(res.pay_error);
        }
        this.router.navigate(['/payment-waiting'], { queryParams: { order_id: res.order_id } });
        return;
      }

      this.orderSvc.lastOrder.set({
        orderId: res.order_id,
        total: res.grand_total,
        paymentMethod: res.payment,
        deliveryDate: this.deliveryDate,
        deliveryTime: this.deliveryTime,
        address: this.address,
        city: this.city,
        recipientName: this.deliveryType === 'recipient' ? this.recipientName : undefined,
        recipientPhone,
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
