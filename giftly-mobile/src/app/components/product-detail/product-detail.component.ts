import { Component, Input, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
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
  imports: [CommonModule, FormsModule, IonButton, IonIcon, IonContent, ImgUrlPipe, ProductReviewsComponent],
})
export class ProductDetailComponent implements OnInit {
  @Input({ required: true }) product!: Product;
  // 'cart' (Shop) shows a qty stepper + Add to Cart. 'box' (Build-a-Box) shows
  // a single "Add to box" button and dismisses with { addToBox: true }.
  @Input() mode: 'cart' | 'box' = 'cart';
  // In box mode, disable adding when the box is already full.
  @Input() boxFull = false;
  @Input() inBoxQty = 0;

  private modalCtrl = inject(ModalController);
  private toastCtrl = inject(ToastController);
  private cart = inject(CartService);
  private wishlist = inject(WishlistService);
  auth = inject(AuthService);

  readonly quantity = signal(1);
  readonly adding = signal(false);

  // Occasion Box color/size — mirrors catalog_grid.php's swatch/size picker:
  // the first option of each is preselected, and picking a size overrides
  // the displayed price with that size's own price.
  readonly selectedColor = signal<string | null>(null);
  readonly selectedSize = signal<string | null>(null);

  constructor() {
    addIcons({ closeOutline, heart, heartOutline, removeOutline, addOutline, star });
  }

  ngOnInit(): void {
    if (this.product.colors?.length) this.selectedColor.set(this.product.colors[0].color_name);
    if (this.product.sizes?.length) this.selectedSize.set(this.product.sizes[0].size_name);
  }

  selectColor(name: string): void {
    this.selectedColor.set(name);
  }

  selectSize(name: string): void {
    this.selectedSize.set(name);
  }

  // The size's own price overrides the product's price once one is chosen.
  displayPrice(): string {
    const size = this.product.sizes?.find((s) => s.size_name === this.selectedSize());
    return size ? size.price : this.product.price;
  }

  ratingStars(): number[] {
    const avg = Math.round(Number(this.product.avg_rating ?? 0));
    return Array.from({ length: Math.min(5, Math.max(0, avg)) });
  }

  isWishlisted(): boolean {
    return this.wishlist.productIds().has(this.product.id);
  }

  justPopped(): boolean {
    return this.wishlist.justToggled() === this.product.id;
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

  // Typed quantity, same clamp as the +/- stepper.
  setQuantity(value: number): void {
    const n = Math.floor(Number(value) || 1);
    this.quantity.set(Math.min(Math.max(n, 1), this.product.quantity));
  }

  async toggleWishlist(): Promise<void> {
    if (!this.auth.isLoggedIn()) {
      await this.presentToast('Please log in to use your wishlist');
      return;
    }
    const action = await this.wishlist.toggle(this.product.id);
    await this.presentToast(action === 'added' ? 'Added to wishlist' : 'Removed from wishlist');
  }

  async addToCart(): Promise<void> {
    if (!this.auth.isLoggedIn()) {
      await this.presentToast('Please log in to add items to your cart');
      return;
    }
    this.adding.set(true);
    try {
      await this.cart.addToCart(this.product.id, this.quantity(), this.selectedColor() ?? undefined, this.selectedSize() ?? undefined);
      await this.presentToast('Added to cart');
      this.dismiss();
    } catch {
      await this.presentToast('Could not add to cart. Please try again.');
    } finally {
      this.adding.set(false);
    }
  }

  addToBox(): void {
    this.modalCtrl.dismiss({ addToBox: true });
  }

  dismiss(): void {
    this.modalCtrl.dismiss();
  }

  private async presentToast(message: string): Promise<void> {
    const toast = await this.toastCtrl.create({ message, duration: 1800, position: 'bottom' });
    await toast.present();
  }
}
