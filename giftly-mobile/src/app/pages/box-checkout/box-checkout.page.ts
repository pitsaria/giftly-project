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
import { personOutline, giftOutline, cardOutline, cashOutline, lockClosedOutline, createOutline, pricetagOutline, timeOutline, hourglassOutline } from 'ionicons/icons';
import { Address, Box, PromoEval, Addon, Recipient } from '../../core/models';
import { AddressService } from '../../core/address.service';
import { ProfileService } from '../../core/profile.service';
import { BoxService } from '../../core/box.service';
import { OrderService, PaymentMethod } from '../../core/order.service';
import { PaymentsService } from '../../core/payments.service';
import { PromoService } from '../../core/promo.service';
import { AuthService } from '../../core/auth.service';
import { HapticsService } from '../../core/haptics.service';
import { NotificationService } from '../../core/notification.service';
import { AddonService } from '../../core/addon.service';
import { RecipientService } from '../../core/recipient.service';
import { describeError } from '../../core/http-error';
import { formatCardExpiry, formatCardNumber, formatCvc, validateCard } from '../../core/card';
import { phoneDigitsFromStored } from '../../core/phone-format';
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
  private profileSvc = inject(ProfileService);
  private boxSvc = inject(BoxService);
  private orderSvc = inject(OrderService);
  private payments = inject(PaymentsService);
  private promoSvc = inject(PromoService);
  private haptics = inject(HapticsService);
  private notifications = inject(NotificationService);
  private auth = inject(AuthService);
  private addonSvc = inject(AddonService);
  private recipientSvc = inject(RecipientService);
  private router = inject(Router);
  private route = inject(ActivatedRoute);
  private toastCtrl = inject(ToastController);

  readonly box = signal<Box | null>(null);
  readonly addresses = signal<Address[]>([]);
  readonly addons = signal<Addon[]>([]);
  readonly selectedAddonIds = signal<number[]>([]);
  readonly recipients = signal<Recipient[]>([]);
  selectedRecipientId: number | null = null;
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
  static readonly PROCESSING_DAYS = 3;
  static readonly OPEN_TIME = '08:00';
  static readonly CLOSE_TIME = '20:00';
  // The earliest bookable date/time — mirrors checkout_selected.php's own
  // rules: at least 3 days out (processing/prep time) and within the 8
  // AM-8 PM delivery window, both enforced again server-side in placeOrder().
  readonly minDate = new Date(Date.now() + BoxCheckoutPage.PROCESSING_DAYS * 86400000).toISOString().substring(0, 10);
  readonly minTime = BoxCheckoutPage.OPEN_TIME;
  readonly maxTime = BoxCheckoutPage.CLOSE_TIME;
  deliveryDate = this.minDate;
  deliveryTime = BoxCheckoutPage.OPEN_TIME;
  paymentMethod: PaymentMethod = 'cod';
  readonly onlineEnabled = signal(false);
  cardHolder = '';
  cardNumber = '';
  cardExpiry = '';
  cardCvc = '';

  readonly blocked = computed(() => (this.box()?.issues.length ?? 0) > 0);
  readonly shippingFee = computed(() => {
    const ev = this.promoEval();
    if (ev) return ev.shipping_fee;
    const sub = this.box()?.subtotal ?? 0;
    return sub > 0 && sub < 300 ? 50 : 0;
  });
  // The order summary lists each selected add-on by name rather than just
  // the combined total.
  readonly selectedAddonsList = computed(() => {
    const ids = new Set(this.selectedAddonIds());
    return this.addons().filter((a) => ids.has(a.id));
  });
  readonly addonsTotal = computed(() => this.selectedAddonsList().reduce((sum, a) => sum + a.price, 0));
  readonly grandTotal = computed(() => {
    const ev = this.promoEval();
    const base = ev ? ev.total : (this.box()?.subtotal ?? 0) + this.shippingFee() + (this.box()?.box_price ?? 0);
    return base + this.addonsTotal();
  });

  // Promo code (mirrors box_checkout.php's promo box).
  promoCodeInput = '';
  readonly promoEval = signal<PromoEval | null>(null);
  readonly applyingCode = signal(false);

  constructor() {
    addIcons({ personOutline, giftOutline, cardOutline, cashOutline, lockClosedOutline, createOutline, pricetagOutline, timeOutline, hourglassOutline });
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
      const [box, addresses, addons, recipients, profile] = await Promise.all([
        this.boxSvc.getBox(this.boxId),
        this.addressSvc.getAll().catch(() => []),
        this.addonSvc.getAll().catch(() => []),
        this.recipientSvc
          .getAll()
          .then((r) => r.recipients)
          .catch(() => []),
        this.profileSvc.getProfile().catch(() => null),
      ]);
      this.box.set(box);
      this.addresses.set(addresses);
      // Sender is the logged-in user by default — prefill their own number
      // instead of making them retype it every checkout.
      if (profile?.phone) this.senderPhoneDigits = phoneDigitsFromStored(profile.phone);
      this.addons.set(addons);
      this.recipients.set(recipients);
      if (addresses.length) {
        const def = addresses.find((a) => a.is_default) ?? addresses[0];
        this.selectAddress(def.id);
      }
      await this.evaluatePromo('');
    } catch (err) {
      this.error.set(describeError(err));
    } finally {
      this.loading.set(false);
    }
  }

  private async evaluatePromo(code: string): Promise<void> {
    if (!this.boxId) {
      this.promoEval.set(null);
      return;
    }
    const result = await this.promoSvc.evaluate('box', { code, boxId: this.boxId });
    this.promoEval.set(result);
  }

  async applyPromoCode(): Promise<void> {
    const code = this.promoCodeInput.trim();
    if (!code) return;
    this.applyingCode.set(true);
    try {
      await this.evaluatePromo(code);
      if (this.promoEval()?.code_error) {
        await this.toast(this.promoEval()!.code_error);
      }
    } catch (err) {
      await this.toast(describeError(err));
    } finally {
      this.applyingCode.set(false);
    }
  }

  async removePromoCode(): Promise<void> {
    this.promoCodeInput = '';
    this.applyingCode.set(true);
    try {
      await this.evaluatePromo('');
    } catch (err) {
      await this.toast(describeError(err));
    } finally {
      this.applyingCode.set(false);
    }
  }

  absAmount(n: number): number {
    return Math.abs(n);
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

  // Gifts sent straight to a recipient must be paid online — mirrors
  // box_checkout.php's server-side + client-side block on COD.
  setDeliveryType(type: 'me' | 'recipient'): void {
    this.deliveryType = type;
    if (type === 'recipient') {
      this.paymentMethod = this.onlineEnabled() ? 'online' : 'card';
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

  isAddonSelected(id: number): boolean {
    return this.selectedAddonIds().includes(id);
  }

  toggleAddon(id: number): void {
    const current = this.selectedAddonIds();
    this.selectedAddonIds.set(current.includes(id) ? current.filter((x) => x !== id) : [...current, id]);
  }

  // "Saved Person" quick-fill — mirrors box_checkout.php's My Relations dropdown.
  fillFromRecipient(id: number | null): void {
    this.selectedRecipientId = id;
    const r = this.recipients().find((x) => x.id === id);
    if (!r) return;
    this.recipientName = r.name;
    this.recipientPhoneDigits = phoneDigitsFromStored(r.phone);
    this.recipientPhoneTouched = true;
    this.address = r.street || this.address;
    this.city = r.city_line || this.city;
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
    if (this.deliveryDate && this.deliveryDate < this.minDate) {
      missing.push(`Delivery Date (at least ${BoxCheckoutPage.PROCESSING_DAYS} days out)`);
    }
    if (this.deliveryTime) {
      const timeOnly = this.deliveryTime.substring(0, 5);
      if (timeOnly < BoxCheckoutPage.OPEN_TIME || timeOnly > BoxCheckoutPage.CLOSE_TIME) {
        missing.push('Delivery Time (between 8:00 AM and 8:00 PM)');
      }
    }
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
        promo_code: this.promoEval()?.code || undefined,
        addon_ids: this.selectedAddonIds().length ? this.selectedAddonIds() : undefined,
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
      void this.notifications.scheduleDeliveryReminder(
        res.order_id,
        this.deliveryDate,
        this.deliveryTime,
        this.deliveryType === 'recipient' ? this.recipientName : undefined
      );

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
        discountAmount: res.discount || undefined,
        promoCode: res.promo_code || undefined,
        freeItemName: res.free_item || undefined,
      });
      this.haptics.success();
      this.router.navigateByUrl('/order-confirmation');
    } catch (err) {
      this.haptics.error();
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
