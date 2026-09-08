import { Injectable, inject, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { ApiService } from './api.service';
import { HapticsService } from './haptics.service';
import { WishlistData } from './models';

@Injectable({ providedIn: 'root' })
export class WishlistService {
  private api = inject(ApiService);
  private haptics = inject(HapticsService);

  // Product ids currently wishlisted, so product cards can show a filled heart.
  readonly productIds = signal<Set<number>>(new Set());

  // Briefly holds the id just toggled, so the heart icon can "pop" wherever
  // it's shown (shop, product detail, profile) instead of just flipping state.
  readonly justToggled = signal<number | null>(null);
  private justToggledTimer: ReturnType<typeof setTimeout> | undefined;

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
    this.haptics.light();
    this.justToggled.set(productId);
    clearTimeout(this.justToggledTimer);
    this.justToggledTimer = setTimeout(() => this.justToggled.set(null), 350);
    return res.data.action;
  }
}
