import { Injectable, inject, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { ApiService } from './api.service';
import { Order } from './models';

export interface OrderConfirmation {
  orderId: number;
  total: number;
  paymentMethod: string;
  deliveryDate: string;
  deliveryTime: string;
  address: string;
  city: string;
  recipientName?: string;
  recipientPhone?: string;
  giftMessage?: string;
}

export type PaymentMethod = 'cod' | 'card' | 'online';

export interface CreateOrderPayload {
  selected_ids: number[];
  fullname: string;
  address: string;
  city: string;
  payment_method: PaymentMethod;
  delivery_date: string;
  delivery_time: string;
  gift_message?: string;
  sender_phone?: string;
  recipient_name?: string;
  recipient_phone?: string;
  // Card payment only — validated server-side, only last 4 digits are stored.
  card_number?: string;
  card_holder?: string;
  card_expiry?: string;
  card_cvc?: string;
}

export interface OrderPlaced {
  orderId: number;
  // Non-empty when payment_method is 'online' and PayMongo started a session.
  checkoutUrl: string;
  // Non-empty when 'online' but PayMongo couldn't start (order still saved & payable).
  payError: string;
}

export interface PaymentStatus {
  payment_status: 'unpaid' | 'paid' | 'failed';
  status: string;
  checkout_url: string;
}

@Injectable({ providedIn: 'root' })
export class OrderService {
  private api = inject(ApiService);

  // Holds the just-placed order's details for the confirmation screen,
  // mirrors the inline "Order Placed!" card in checkout_selected.php.
  readonly lastOrder = signal<OrderConfirmation | null>(null);

  async getOrders(): Promise<Order[]> {
    const res = await firstValueFrom(this.api.get<{ orders: Order[] }>('orders'));
    return res.data.orders;
  }

  async getOrderDetails(id: number): Promise<Order> {
    const res = await firstValueFrom(this.api.get<Order>('orders/single', { id }));
    return res.data;
  }

  async createOrder(payload: CreateOrderPayload): Promise<OrderPlaced> {
    const res = await firstValueFrom(
      this.api.post<{ order_id: number; checkout_url?: string; pay_error?: string }>('orders', payload)
    );
    return {
      orderId: res.data.order_id,
      checkoutUrl: res.data.checkout_url ?? '',
      payError: res.data.pay_error ?? '',
    };
  }

  /**
   * Poll target after returning from PayMongo. Pass wantUrl for the "Pay now" /
   * "reopen" buttons — it also mints a fresh PayMongo checkout URL (skipped
   * during polling so we don't hit PayMongo every few seconds).
   */
  async paymentStatus(id: number, wantUrl = false): Promise<PaymentStatus> {
    const params: Record<string, string | number> = { id };
    if (wantUrl) params['url'] = 1;
    const res = await firstValueFrom(this.api.get<PaymentStatus>('orders/payment', params));
    return res.data;
  }

  /** Submit a cancellation request (an admin approves it before the order is cancelled). */
  async cancelOrder(id: number, reason = ''): Promise<void> {
    await firstValueFrom(this.api.put('orders/cancel', { reason }, { id }));
  }

  /** Confirm a delivered order arrived — unlocks reviewing its items. */
  async confirmReceived(id: number): Promise<void> {
    await firstValueFrom(this.api.put('orders/received', {}, { id }));
  }
}
