import { Injectable, inject, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { ApiService } from './api.service';
import { WishlistData } from './models';

@Injectable({ providedIn: 'root' })
export class WishlistService {
  private api = inject(ApiService);

  // Product ids currently wishlisted, so product cards can show a filled heart.
  readonly productIds = signal<Set<number>>(new Set());

  async getWishlist(): Promise<WishlistData> {
    const res = await firstValueFrom(this.api.get<WishlistData>('wishlist'));
    const ids = new Set([...res.data.in_stock, ...res.data.out_of_stock].map((p) => p.id));
    this.productIds.set(ids);
    return res.data;
  }

  async toggle(productId: number): Promise<'added' | 'removed'> {
    const res = await firstValueFrom(
      this.api.post<{ action: 'added' | 'removed' }>('wishlist/toggle', { product_id: productId })
    );
    const ids = new Set(this.productIds());
    if (res.data.action === 'added') {
      ids.add(productId);
    } else {
      ids.delete(productId);
    }
    this.productIds.set(ids);
    return res.data.action;
  }
}
