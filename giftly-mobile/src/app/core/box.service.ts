import { Injectable, inject, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { ApiService } from './api.service';
import { Box, BoxCardStyle, BoxProduct, BoxSize } from './models';

export interface BoxProductPage {
  products: BoxProduct[];
  pagination: { page: number; limit: number; total: number; total_pages: number };
}

export interface BoxProductQuery {
  size_id: number;
  search?: string;
  category?: number;
  page?: number;
}

export interface SaveBoxPayload {
  box_id?: number;
  size_id: number;
  letter: string;
  card_style: string;
  status: 'saved' | 'in_cart';
  items: { product_id: number; quantity: number }[];
}

export interface BoxCheckoutPayload {
  box_id: number;
  fullname: string;
  sender_phone: string;
  address: string;
  city: string;
  payment_method: 'cod' | 'card';
  delivery_date: string;
  delivery_time: string;
  delivery_type: 'me' | 'recipient';
  recipient_name?: string;
  recipient_phone?: string;
  card_number?: string;
  card_holder?: string;
  card_expiry?: string;
  card_cvc?: string;
}

export interface BoxOrderResult {
  order_id: number;
  grand_total: number;
  payment: string;
  delivery_date: string;
  delivery_time: string;
  address: string;
  recipient: string;
  size_name: string;
}

// Thin wrapper over the api/index.php box/* and boxes/* routes.
// Mirrors build-a-box.php, box_actions.php and box_checkout.php.
@Injectable({ providedIn: 'root' })
export class BoxService {
  private api = inject(ApiService);

  // Badge count for a saved-boxes indicator in Profile.
  readonly boxCount = signal(0);

  async sizes(): Promise<{ sizes: BoxSize[]; cardStyles: BoxCardStyle[] }> {
    const res = await firstValueFrom(
      this.api.get<{ sizes: BoxSize[]; card_styles: BoxCardStyle[] }>('box/sizes')
    );
    return { sizes: res.data.sizes, cardStyles: res.data.card_styles };
  }

  async products(query: BoxProductQuery): Promise<BoxProductPage> {
    const params: Record<string, string | number> = {
      size_id: query.size_id,
      page: query.page ?? 1,
    };
    if (query.search) params['search'] = query.search;
    if (query.category) params['category'] = query.category;
    const res = await firstValueFrom(this.api.get<BoxProductPage>('box/products', params));
    return res.data;
  }

  async listBoxes(): Promise<Box[]> {
    const res = await firstValueFrom(this.api.get<{ boxes: Box[] }>('boxes'));
    this.boxCount.set(res.data.boxes.length);
    return res.data.boxes;
  }

  async getBox(id: number): Promise<Box> {
    const res = await firstValueFrom(this.api.get<Box>('boxes/single', { id }));
    return res.data;
  }

  async saveBox(payload: SaveBoxPayload): Promise<{ box_id: number; box_status: string }> {
    const res = await firstValueFrom(
      this.api.post<{ box_id: number; box_status: string }>('boxes', payload)
    );
    return res.data;
  }

  async deleteBox(id: number): Promise<void> {
    await firstValueFrom(this.api.delete('boxes/single', { id }));
  }

  async checkoutBox(payload: BoxCheckoutPayload): Promise<BoxOrderResult> {
    const res = await firstValueFrom(this.api.post<BoxOrderResult>('boxes/checkout', payload));
    return res.data;
  }
}
