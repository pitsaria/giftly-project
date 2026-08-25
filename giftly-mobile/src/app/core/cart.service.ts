import { Injectable, inject, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { ApiService } from './api.service';
import { Cart } from './models';

@Injectable({ providedIn: 'root' })
export class CartService {
  private api = inject(ApiService);

  // Badge count for the tab bar, mirrors the site's cart icon.
  readonly itemCount = signal(0);

  // cart_ids the user selected on the Cart page, carried over to Checkout —
  // mirrors cart.php's "select items to checkout" flow.
  readonly selectedCartIds = signal<number[]>([]);

  async getCart(): Promise<Cart> {
    const res = await firstValueFrom(this.api.get<Cart>('cart'));
    this.itemCount.set(res.data.item_count);
    return res.data;
  }

  async addToCart(productId: number, quantity = 1): Promise<void> {
    await firstValueFrom(this.api.post('cart', { product_id: productId, quantity }));
    await this.getCart();
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
