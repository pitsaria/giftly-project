import { Injectable, signal } from '@angular/core';

export interface GiftContext {
  recipientId: number;
  name: string;
  phone: string;
  street: string;
  cityLine: string;
  occasionLabel?: string;
}

// Mobile equivalent of the website's $_SESSION['gift_context'] (see
// gift_start.php / header.php's "Shopping for X" pill / checkout_selected.php).
// "Send a Gift" on a saved recipient sets this and routes to Shop; Checkout
// reads it to pre-select "Deliver to Recipient" and prefill the fields, then
// clears it once the order is placed.
@Injectable({ providedIn: 'root' })
export class GiftContextService {
  readonly context = signal<GiftContext | null>(null);

  set(ctx: GiftContext): void {
    this.context.set(ctx);
  }

  clear(): void {
    this.context.set(null);
  }
}
