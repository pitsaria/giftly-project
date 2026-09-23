import { Component, EventEmitter, Input, OnChanges, Output, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonIcon, ToastController } from '@ionic/angular';
import { addIcons } from 'ionicons';
import { ticketOutline, chevronDownOutline } from 'ionicons/icons';
import { PromoService } from '../../core/promo.service';
import { Voucher } from '../../core/models';

// Checkout's "Vouchers" list (products + gift-box checkouts). Shows the live
// coded promos the customer can still use, each with Copy / Claim / Apply.
// "Apply" just hands the code back to the page, which runs its normal promo
// flow — claiming only saves the voucher to the account and changes no rules.
@Component({
  selector: 'app-voucher-list',
  templateUrl: 'voucher-list.component.html',
  styleUrls: ['voucher-list.component.scss'],
  imports: [CommonModule, IonIcon],
})
export class VoucherListComponent implements OnChanges {
  @Input() scope: 'products' | 'box' = 'products';
  @Input() subtotal = 0;
  @Input() itemCount?: number;
  @Output() useCode = new EventEmitter<string>();

  private promoSvc = inject(PromoService);
  private toastCtrl = inject(ToastController);

  readonly vouchers = signal<Voucher[]>([]);
  readonly open = signal(false);
  readonly claimedCount = computed(() => this.vouchers().filter((v) => v.claimed).length);
  private lastKey = '';

  constructor() {
    addIcons({ ticketOutline, chevronDownOutline });
  }

  ngOnChanges(): void {
    // Reload when the cart total changes — a voucher can flip between
    // "usable" and "spend PHP X more" as items are added/removed.
    const key = `${this.scope}|${this.subtotal}|${this.itemCount ?? ''}`;
    if (key === this.lastKey) return;
    this.lastKey = key;
    void this.load();
  }

  private async load(): Promise<void> {
    try {
      this.vouchers.set(await this.promoSvc.available(this.scope, this.subtotal, this.itemCount));
    } catch {
      // Non-critical — the plain promo-code box still works.
      this.vouchers.set([]);
    }
  }

  async copy(v: Voucher): Promise<void> {
    try {
      await navigator.clipboard.writeText(v.code);
      await this.toast(`Code ${v.code} copied!`);
    } catch {
      await this.toast(v.code);
    }
  }

  async claim(v: Voucher): Promise<void> {
    try {
      await this.promoSvc.claim(v.id);
      this.vouchers.update((list) => list.map((x) => (x.id === v.id ? { ...x, claimed: true } : x)));
      await this.toast(`Voucher ${v.code} claimed!`);
    } catch (err: any) {
      await this.toast(err?.error?.error ?? "Couldn't claim this voucher. Please try again.");
    }
  }

  private async toast(message: string): Promise<void> {
    const t = await this.toastCtrl.create({ message, duration: 1800, position: 'bottom' });
    await t.present();
  }
}
