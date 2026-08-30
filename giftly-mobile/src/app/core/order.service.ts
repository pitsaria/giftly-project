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

export interface CreateOrderPayload {
  selected_ids: number[];
  fullname: string;
  address: string;
  city: string;
  payment_method: string;
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

  async createOrder(payload: CreateOrderPayload): Promise<number> {
    const res = await firstValueFrom(this.api.post<{ order_id: number }>('orders', payload));
    return res.data.order_id;
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
