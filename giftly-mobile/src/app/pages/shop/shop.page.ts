import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import {
  IonToolbar,
  IonContent,
  IonRefresher,
  IonRefresherContent,
  IonSearchbar,
  IonSegment,
  IonSegmentButton,
  IonChip,
  IonLabel,
  IonIcon,
  IonInfiniteScroll,
  IonInfiniteScrollContent,
  IonSpinner,
  IonSkeletonText,
  ModalController,
  ToastController,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import { heart, heartOutline, addCircle, star } from 'ionicons/icons';
import { Category, Product, ProductType } from '../../core/models';
import { ProductService } from '../../core/product.service';
import { CartService } from '../../core/cart.service';
import { WishlistService } from '../../core/wishlist.service';
import { AuthService } from '../../core/auth.service';
import { ProductDetailComponent } from '../../components/product-detail/product-detail.component';
import { TopBarComponent } from '../../shared/top-bar/top-bar.component';
import { ImgUrlPipe } from '../../shared/img-url.pipe';

// Mirrors giftly_project/shop.php: category chips + search + product grid.
@Component({
  selector: 'app-shop',
  templateUrl: 'shop.page.html',
  styleUrls: ['shop.page.scss'],
  imports: [
    CommonModule,
    FormsModule,
    IonToolbar,
    IonContent,
    IonRefresher,
    IonRefresherContent,
    IonSearchbar,
    IonSegment,
    IonSegmentButton,
    IonChip,
    IonLabel,
    IonIcon,
    IonInfiniteScroll,
    IonInfiniteScrollContent,
    IonSpinner,
    IonSkeletonText,
    TopBarComponent,
    ImgUrlPipe,
  ],
})
export class ShopPage implements OnInit {
  private productSvc = inject(ProductService);
  private cart = inject(CartService);
  wishlist = inject(WishlistService);
  auth = inject(AuthService);
  private modalCtrl = inject(ModalController);
  private toastCtrl = inject(ToastController);
  private route = inject(ActivatedRoute);

  // Signals, not plain fields: a signal write always schedules a re-render
  // regardless of zone/zoneless configuration, unlike a plain field mutated
  // inside an async/await continuation (see the fix applied to Cart/Profile/
  // Checkout for the "network succeeds, screen never repaints" bug).
  readonly categories = signal<Category[]>([]);
  readonly products = signal<Product[]>([]);
  readonly totalPages = signal(1);
  readonly loading = signal(false);
  private loadToken = 0;

  selectedCategory: number | null = null;
  search = '';
  searchVisible = false;
  page = 1;
  // 'catalog' = Shop, plus the two curated storefronts. Mirrors the website's
  // Shop / Occasion Boxes / Baskets nav links.
  readonly productType = signal<ProductType>('catalog');
  readonly skeletonRows = Array.from({ length: 6 });

  constructor() {
    addIcons({ heart, heartOutline, addCircle, star });
  }

  readonly segmentTitles: Record<ProductType, string> = {
    catalog: 'Explore Gifts',
    occasion_box: 'Occasion Boxes',
    basket: 'Baskets',
  };

  async selectType(value: string | number | undefined): Promise<void> {
    if (!value) return;
    const type = value as ProductType;
    if (type === this.productType()) return;
    this.productType.set(type);
    this.selectedCategory = null;
    this.search = '';
    this.page = 1;
    await this.loadProducts();
  }

  stars(product: Product): number[] {
    const avg = Math.round(Number(product.avg_rating ?? 0));
    return Array.from({ length: Math.min(5, Math.max(0, avg)) });
  }

  async ngOnInit(): Promise<void> {
    // Covers the categories fetch too, not just loadProducts() below —
    // otherwise the skeleton grid doesn't appear until categories resolves.
    // Swallow a categories failure so we still fall through to
    // loadProducts(), whose own try/finally is what clears `loading`.
    this.loading.set(true);
    try {
      this.categories.set(await this.productSvc.getCategories());
    } catch {
      // Product grid can still load without category chips.
    }

    // Reacts to the ?category= param (set when Home's "Shop by Category"
    // tile is tapped), not just read once — Ionic's tab router keeps this
    // page instance alive across tab switches, so ngOnInit won't re-fire on
    // a repeat visit; this subscription is what picks up a later category
    // selection instead. Its first emission also doubles as the initial load.
    this.route.queryParamMap.subscribe((params) => {
      const raw = params.get('category');
      this.selectedCategory = raw ? Number(raw) : null;
      this.page = 1;
      this.loadProducts();
    });

    if (this.auth.isLoggedIn()) {
      await this.wishlist.getWishlist();
    }
  }

  toggleSearch(): void {
    this.searchVisible = !this.searchVisible;
  }

  async selectCategory(id: number | null): Promise<void> {
    this.selectedCategory = id;
    this.page = 1;
    await this.loadProducts();
  }

  async onSearch(): Promise<void> {
    this.page = 1;
    await this.loadProducts();
  }

  async loadProducts(): Promise<void> {
    const token = ++this.loadToken;
    this.loading.set(true);
    try {
      const result = await this.productSvc.getAll({
        page: this.page,
        limit: 20,
        search: this.search || undefined,
        category: this.selectedCategory ?? undefined,
        type: this.productType(),
      });
      if (token !== this.loadToken) return;
      this.products.set(this.page === 1 ? result.products : [...this.products(), ...result.products]);
      this.totalPages.set(result.pagination.total_pages);
    } finally {
      if (token === this.loadToken) {
        this.loading.set(false);
      }
    }
  }

  async handleRefresh(event: any): Promise<void> {
    this.page = 1;
    await this.loadProducts();
    event.target.complete();
  }

  async loadMore(event: any): Promise<void> {
    if (this.page < this.totalPages()) {
      this.page++;
      await this.loadProducts();
    }
    event.target.complete();
    if (this.page >= this.totalPages()) {
      event.target.disabled = true;
    }
  }

  isWishlisted(product: Product): boolean {
    return this.wishlist.productIds().has(product.id);
  }

  async toggleWishlist(product: Product, ev: Event): Promise<void> {
    ev.stopPropagation();
    if (!this.auth.isLoggedIn()) {
      await this.toast('Please log in to use your wishlist');
      return;
    }
    try {
      await this.wishlist.toggle(product.id);
    } catch {
      await this.toast('Could not update your wishlist. Please try again.');
    }
  }

  async quickAdd(product: Product, ev: Event): Promise<void> {
    ev.stopPropagation();
    if (!this.auth.isLoggedIn()) {
      await this.toast('Please log in to add items to your cart');
      return;
    }
    if (product.quantity <= 0) {
      await this.toast('This item is out of stock');
      return;
    }
    try {
      await this.cart.addToCart(product.id, 1);
      await this.toast(`Added ${product.name} to cart`);
    } catch (err: any) {
      await this.toast(err?.error?.error ?? 'Could not add to cart. Please try again.');
    }
  }

  private async toast(message: string): Promise<void> {
    const t = await this.toastCtrl.create({ message, duration: 1800, position: 'bottom' });
    await t.present();
  }

  async openProduct(product: Product): Promise<void> {
    // Presented as a draggable bottom sheet (handle + breakpoints) instead
    // of a full-screen modal, matching how native shopping apps show a
    // product quick-view.
    const modal = await this.modalCtrl.create({
      component: ProductDetailComponent,
      componentProps: { product },
      breakpoints: [0, 0.75, 0.95],
      initialBreakpoint: 0.75,
    });
    await modal.present();
  }
}
