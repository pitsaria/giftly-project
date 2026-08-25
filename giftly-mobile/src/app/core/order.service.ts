import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { ApiService } from './api.service';
import { Order } from './models';

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
}

@Injectable({ providedIn: 'root' })
export class OrderService {
  private api = inject(ApiService);

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

  async cancelOrder(id: number): Promise<void> {
    await firstValueFrom(this.api.put('orders/cancel', {}, { id }));
  }
}
