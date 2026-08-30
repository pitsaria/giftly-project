import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterLink } from '@angular/router';
import {
  IonContent,
  IonButton,
  IonIcon,
  IonSkeletonText,
  IonRefresher,
  IonRefresherContent,
  ModalController,
  ToastController,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import { giftOutline, addCircle, cubeOutline, carOutline, leafOutline, pricetagOutline, star } from 'ionicons/icons';
import { Category, Order, Product } from '../../core/models';
import { ProductService } from '../../core/product.service';
import { CartService } from '../../core/cart.service';
import { OrderService } from '../../core/order.service';
import { AuthService } from '../../core/auth.service';
import { TopBarComponent } from '../../shared/top-bar/top-bar.component';
import { OrderDetailComponent } from '../../components/order-detail/order-detail.component';
import { ImgUrlPipe } from '../../shared/img-url.pipe';

const STATUS_LABEL: Record<Order['status'], string> = {
  pending: 'Processing',
  shipped: 'Shipped',
  delivered: 'Delivered',
  cancelled: 'Cancelled',
};

// Cycled by index — same 4 pastel tones as the Special Promotions tiles
// below, so category chips read as part of the same system.
const CATEGORY_TILE_CLASSES = ['p-birthday', 'p-bundle', 'p-shipping', 'p-seasonal'];

interface HeroSlide {
  // Rendered as `lead <span>highlight</span> trail` so only one phrase
  // ("Surprise") picks up the accent color, matching the website's hero.
  lead: string;
  highlight: string;
  trail?: string;
  subtitle: string;
  image: string;
  gradient: string;
}

interface PromoTile {
  title: string;
  highlight: string;
  desc: string;
  icon: string;
  className: string;
}

@Component({
  selector: 'app-home',
  templateUrl: 'home.page.html',
  styleUrls: ['home.page.scss'],
  imports: [
    CommonModule,
    RouterLink,
    IonContent,
    IonButton,
    IonIcon,
    IonSkeletonText,
    IonRefresher,
    IonRefresherContent,
    TopBarComponent,
    ImgUrlPipe,
  ],
})
export class HomePage implements OnInit {
  private router = inject(Router);
  private productSvc = inject(ProductService);
  private cart = inject(CartService);
  private orderSvc = inject(OrderService);
  private modalCtrl = inject(ModalController);
  private toastCtrl = inject(ToastController);
  auth = inject(AuthService);

  readonly featured = signal<Product[]>([]);
  readonly loadingFeatured = signal(true);
  readonly skeletonRows = Array.from({ length: 4 });

  readonly categories = signal<Category[]>([]);
  readonly loadingCategories = signal(true);
  readonly categorySkeletonRows = Array.from({ length: 4 });

  readonly recentOrder = signal<Order | null>(null);
  readonly loadingRecentOrder = signal(false);

  readonly activeSlide = signal(0);

  // Mirrors the 3 hero slides in index.php's carousel.
  readonly slides: HeroSlide[] = [
    {
      lead: 'Make Every',
      highlight: 'Surprise',
      trail: 'More Meaningful',
      subtitle: 'Create personalized gift boxes or choose curated collections for every occasion.',
      image: 'assets/giftly/giftbox.png',
      gradient: 'linear-gradient(135deg, #FFDBDF 0%, #fff4d8 60%, #ffe9d6 100%)',
    },
    {
      lead: 'Perfect Occasion',
      highlight: 'Gift Boxes',
      subtitle: 'Curated gifts for birthdays, anniversaries, weddings, and every special moment.',
      image: 'assets/giftly/occasion_box.png',
      gradient: 'linear-gradient(135deg, #D6EAF8 0%, #fff1da 60%, #F4ECF7 100%)',
    },
    {
      lead: 'Giftly Basket',
      highlight: 'Delights',
      subtitle: 'Beautifully arranged baskets filled with premium goodies for any celebration.',
      image: 'assets/giftly/giftly_basket.png',
      gradient: 'linear-gradient(135deg, #FDEBD0 0%, #eafaf1 60%, #EBDEF0 100%)',
    },
  ];

  // Mirrors index.php's "Special Promotions" section (static promo copy,
  // same as the website — not tied to any promotions backend).
  readonly promos: PromoTile[] = [
    {
      title: 'Birthday',
      highlight: 'Special',
      desc: 'Get 15% OFF on selected Birthday Boxes and celebration gifts.',
      icon: 'gift-outline',
      className: 'p-birthday',
    },
    {
      title: 'Bundle and',
      highlight: 'Save',
      desc: 'Buy any Giftly Bundle and save up to 20%',
      icon: 'cube-outline',
      className: 'p-bundle',
    },
    {
      title: 'Free',
      highlight: 'Shipping',
      desc: 'Enjoy FREE delivery on orders over ₱1,500.',
      icon: 'car-outline',
      className: 'p-shipping',
    },
    {
      title: 'Seasonal',
      highlight: 'Collection',
      desc: 'Shop exclusive limited-edition gift boxes for holidays',
      icon: 'leaf-outline',
      className: 'p-seasonal',
    },
  ];

  constructor() {
    addIcons({ giftOutline, addCircle, cubeOutline, carOutline, leafOutline, pricetagOutline, star });
  }

  onHeroScroll(ev: Event): void {
    const el = ev.target as HTMLElement;
    if (!el.clientWidth) return;
    this.activeSlide.set(Math.round(el.scrollLeft / el.clientWidth));
  }

  categoryTileClass(index: number): string {
    return CATEGORY_TILE_CLASSES[index % CATEGORY_TILE_CLASSES.length];
  }

  statusLabel(status: Order['status']): string {
    return STATUS_LABEL[status];
  }

  async ngOnInit(): Promise<void> {
    await Promise.all([this.loadFeatured(), this.loadCategories(), this.loadRecentOrder()]);
  }

  async loadFeatured(): Promise<void> {
    this.loadingFeatured.set(true);
    try {
      const result = await this.productSvc.getAll({ page: 1, limit: 8 });
      this.featured.set(result.products);
    } finally {
      this.loadingFeatured.set(false);
    }
  }

  async loadCategories(): Promise<void> {
    this.loadingCategories.set(true);
    try {
      this.categories.set(await this.productSvc.getCategories());
    } finally {
      this.loadingCategories.set(false);
    }
  }

  async loadRecentOrder(): Promise<void> {
    if (!this.auth.isLoggedIn()) {
      this.recentOrder.set(null);
      return;
    }
    this.loadingRecentOrder.set(true);
    try {
      // getOrders() returns newest-first (ORDER BY created_at DESC), so the
      // first row is the one to resume.
      const orders = await this.orderSvc.getOrders();
      this.recentOrder.set(orders[0] ?? null);
    } catch {
      // Non-critical section — fail quietly rather than blocking the rest
      // of the homepage with an error state over one card.
      this.recentOrder.set(null);
    } finally {
      this.loadingRecentOrder.set(false);
    }
  }

  async handleRefresh(event: any): Promise<void> {
    await Promise.all([this.loadFeatured(), this.loadCategories(), this.loadRecentOrder()]);
    event.target.complete();
  }

  goToShop(categoryId?: number): void {
    if (categoryId) {
      this.router.navigate(['/tabs/shop'], { queryParams: { category: categoryId } });
    } else {
      this.router.navigateByUrl('/tabs/shop');
    }
  }

  async trackRecentOrder(): Promise<void> {
    const order = this.recentOrder();
    if (!order) return;
    const modal = await this.modalCtrl.create({
      component: OrderDetailComponent,
      componentProps: { order },
    });
    await modal.present();
    const { data } = await modal.onWillDismiss();
    if (data?.cancelled) {
      await this.loadRecentOrder();
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
}
