import { Injectable, inject, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { ApiService } from './api.service';
import { HapticsService } from './haptics.service';
import { Cart } from './models';

@Injectable({ providedIn: 'root' })
export class CartService {
  private api = inject(ApiService);
  private haptics = inject(HapticsService);

  // Badge count for the tab bar, mirrors the site's cart icon.
  readonly itemCount = signal(0);

  // cart_ids the user selected on the Cart page, carried over to Checkout —
  // mirrors cart.php's "select items to checkout" flow.
  readonly selectedCartIds = signal<number[]>([]);

  // Briefly holds the id of the product just added, so a quick-add button can
  // morph into a checkmark for a moment instead of just showing a toast.
  readonly justAddedId = signal<number | null>(null);
  private justAddedTimer: ReturnType<typeof setTimeout> | undefined;

  async getCart(): Promise<Cart> {
    const res = await firstValueFrom(this.api.get<Cart>('cart'));
    this.itemCount.set(res.data.item_count);
    return res.data;
  }

  async addToCart(productId: number, quantity = 1, color?: string, size?: string): Promise<void> {
    const body: Record<string, unknown> = { product_id: productId, quantity };
    if (color) body['color'] = color;
    if (size) body['size'] = size;
    await firstValueFrom(this.api.post('cart', body));
    await this.getCart();
    this.haptics.light();
    this.justAddedId.set(productId);
    clearTimeout(this.justAddedTimer);
    this.justAddedTimer = setTimeout(() => this.justAddedId.set(null), 1200);
  }

  async updateQuantity(cartId: number, action: 'increase' | 'decrease'): Promise<void> {
    await firstValueFrom(this.api.put('cart/update', { cart_id: cartId, action }));
    await this.getCart();
  }

  async removeItem(cartId: number): Promise<void> {
    await firstValueFrom(this.api.delete('cart/remove', { id: cartId }));
    await this.getCart();
  }

  async verifyStock(cartIds: number[]): Promise<{ can_proceed: boolean; has_issues: boolean; issues: unknown[] }> {
    const res = await firstValueFrom(
      this.api.post<{ can_proceed: boolean; has_issues: boolean; issues: unknown[] }>('cart/verify-stock', {
        cart_ids: cartIds,
      })
    );
    return res.data;
  }
}
