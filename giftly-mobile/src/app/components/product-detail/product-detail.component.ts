import { Component, Input, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonButton, IonIcon, IonContent, ModalController, ToastController } from '@ionic/angular';
import { addIcons } from 'ionicons';
import { closeOutline, heart, heartOutline, removeOutline, addOutline, star } from 'ionicons/icons';
import { Product } from '../../core/models';
import { CartService } from '../../core/cart.service';
import { WishlistService } from '../../core/wishlist.service';
import { AuthService } from '../../core/auth.service';
import { ImgUrlPipe } from '../../shared/img-url.pipe';
import { ProductReviewsComponent } from '../product-reviews/product-reviews.component';

// Mirrors giftly_project/add_to_cart_modal.php. Presented as a draggable
// bottom sheet (see shop.page.ts's modalCtrl.create breakpoints) rather than
// a full page, so there's no ion-header here — just a floating close button
// over the product image.
@Component({
  selector: 'app-product-detail',
  templateUrl: 'product-detail.component.html',
  styleUrls: ['product-detail.component.scss'],
  imports: [CommonModule, IonButton, IonIcon, IonContent, ImgUrlPipe, ProductReviewsComponent],
})
export class ProductDetailComponent {
  @Input({ required: true }) product!: Product;

  private modalCtrl = inject(ModalController);
  private toastCtrl = inject(ToastController);
  private cart = inject(CartService);
  private wishlist = inject(WishlistService);
  auth = inject(AuthService);

  readonly quantity = signal(1);
  readonly adding = signal(false);

  constructor() {
    addIcons({ closeOutline, heart, heartOutline, removeOutline, addOutline, star });
  }

  ratingStars(): number[] {
    const avg = Math.round(Number(this.product.avg_rating ?? 0));
    return Array.from({ length: Math.min(5, Math.max(0, avg)) });
  }

  isWishlisted(): boolean {
    return this.wishlist.productIds().has(this.product.id);
  }

  inStock(): boolean {
    return this.product.quantity > 0;
  }

  decrease(): void {
    if (this.quantity() > 1) this.quantity.update((q) => q - 1);
  }

  increase(): void {
    if (this.quantity() < this.product.quantity) this.quantity.update((q) => q + 1);
  }

  async toggleWishlist(): Promise<void> {
    if (!this.auth.isLoggedIn()) {
      await this.presentToast('Please log in to use your wishlist');
      return;
    }
    await this.wishlist.toggle(this.product.id);
  }

  async addToCart(): Promise<void> {
    if (!this.auth.isLoggedIn()) {
      await this.presentToast('Please log in to add items to your cart');
      return;
    }
    this.adding.set(true);
    try {
      await this.cart.addToCart(this.product.id, this.quantity());
      await this.presentToast('Added to cart');
      this.dismiss();
    } catch {
      await this.presentToast('Could not add to cart. Please try again.');
    } finally {
      this.adding.set(false);
    }
  }

  dismiss(): void {
    this.modalCtrl.dismiss();
  }

  private async presentToast(message: string): Promise<void> {
    const toast = await this.toastCtrl.create({ message, duration: 1800, position: 'bottom' });
    await toast.present();
  }
}
