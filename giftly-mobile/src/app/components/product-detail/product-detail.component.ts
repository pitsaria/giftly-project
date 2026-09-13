import { Component, Input, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { IonButton, IonIcon, IonContent, ModalController, ToastController } from '@ionic/angular';
import { addIcons } from 'ionicons';
import { closeOutline, heart, heartOutline, removeOutline, addOutline, star, shareSocialOutline, flashOutline } from 'ionicons/icons';
import { Share } from '@capacitor/share';
import { environment } from '../../../environments/environment';
import { Product } from '../../core/models';
import { CartService } from '../../core/cart.service';
import { WishlistService } from '../../core/wishlist.service';
import { AuthService } from '../../core/auth.service';
import { ProductService } from '../../core/product.service';
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
  private productSvc = inject(ProductService);
  private router = inject(Router);
  auth = inject(AuthService);

  readonly quantity = signal(1);
  readonly adding = signal(false);
  readonly buyingNow = signal(false);

  // Occasion Box color/size — mirrors catalog_grid.php's swatch/size picker:
  // the first option of each is preselected, and picking a size overrides
  // the displayed price with that size's own price.
  readonly selectedColor = signal<string | null>(null);
  readonly selectedSize = signal<string | null>(null);

  constructor() {
    addIcons({ closeOutline, heart, heartOutline, removeOutline, addOutline, star, shareSocialOutline, flashOutline });
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

  // Swaps to the selected color's own photo — mirrors catalog_grid.php's
  // swatch click handler (document.getElementById('catModalImg').src = c.image).
  displayImage(): string {
    const color = this.product.colors?.find((c) => c.color_name === this.selectedColor());
    return color?.image || this.product.image;
  }

  // Same split/trim/filter as catalog_whats_inside_lines() in catalog_lib.php.
  whatsInsideLines(): string[] {
    if (!this.product.whats_inside) return [];
    return this.product.whats_inside
      .split(/\r\n|\r|\n/)
      .map((l) => l.trim())
      .filter((l) => l !== '');
  }

  // Pushes the fresh count into the shared store — read by this sheet's own
  // rating line below and by whatever card opened it (Featured Products,
  // Shop grid), so both update the moment a review is submitted.
  onReviewsSummaryChange(summary: { avg: number; count: number }): void {
    this.productSvc.updateReviewSummary(this.product.id, summary.avg, summary.count);
  }

  displayAvgRating(): string {
    return this.productSvc.reviewSummaryFor(this.product.id)?.avg ?? this.product.avg_rating ?? '0';
  }

  displayReviewCount(): number {
    return this.productSvc.reviewSummaryFor(this.product.id)?.count ?? this.product.review_count ?? 0;
  }

  ratingStars(): number[] {
    const avg = Math.round(Number(this.displayAvgRating()));
    return Array.from({ length: Math.min(5, Math.max(0, avg)) });
  }

  // Opens the OS share sheet with a link back to this product. Wrapped in
  // try/catch like every other native-plugin call in this app (push.service,
  // notification.service) — sharing is a nicety, never allowed to break the
  // sheet if the plugin/OS rejects it (e.g. user cancels the share sheet).
  async share(): Promise<void> {
    try {
      await Share.share({
        title: this.product.name,
        text: `Check out ${this.product.name} on Giftly!`,
        url: `${environment.siteUrl}/shop.php?product=${this.product.id}`,
      });
    } catch {
      // No-op — includes the user simply cancelling the share sheet.
    }
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

  // Adds this exact line (quantity + chosen color/size) to the cart and jumps
  // straight to Checkout with just it selected — skips Cart entirely.
  async buyNow(): Promise<void> {
    if (!this.auth.isLoggedIn()) {
      await this.presentToast('Please log in to checkout');
      return;
    }
    this.buyingNow.set(true);
    try {
      const color = this.selectedColor() ?? undefined;
      const size = this.selectedSize() ?? undefined;
      await this.cart.addToCart(this.product.id, this.quantity(), color, size);
      const cart = await this.cart.getCart();
      const line = cart.items.find(
        (i) => i.id === this.product.id && (i.selected_color ?? '') === (color ?? '') && (i.selected_size ?? '') === (size ?? '')
      );
      if (!line) {
        await this.presentToast('Could not start checkout. Please try again.');
        return;
      }
      this.cart.selectedCartIds.set([line.cart_id]);
      this.modalCtrl.dismiss();
      this.router.navigateByUrl('/checkout');
    } catch {
      await this.presentToast('Could not start checkout. Please try again.');
    } finally {
      this.buyingNow.set(false);
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
