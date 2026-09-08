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
import {
  giftOutline,
  addCircle,
  cubeOutline,
  carOutline,
  leafOutline,
  pricetagOutline,
  star,
  arrowForward,
} from 'ionicons/icons';
import { Category, HomePromo, Order, Product } from '../../core/models';
import { ProductService } from '../../core/product.service';
import { CartService } from '../../core/cart.service';
import { OrderService } from '../../core/order.service';
import { PromoService } from '../../core/promo.service';
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
  kicker: string;
  lead: string;
  highlight: string;
  trail?: string;
  subtitle: string;
  image: string;
  gradient: string;
  ctaLabel: string;
  link: string;
  linkParams?: Record<string, string>;
}

interface PromoCardVm {
  headline: string;
  cond: string;
  code: string;
  icon: string;
  className: string;
  // False for the 4 static fallback tiles — no code chip / "applied
  // automatically" chip shown for those, since they aren't real promos.
  isLive: boolean;
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
  private promoSvc = inject(PromoService);
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
      kicker: 'Build-a-Box',
      lead: 'Make every',
      highlight: 'surprise',
      trail: 'more meaningful',
      subtitle: 'Fill a box with hand-picked gifts and a little letter.',
      image: 'assets/giftly/giftbox.png',
      gradient: 'linear-gradient(135deg, #FFD9DE 0%, #ffe9d6 55%, #fff4d8 100%)',
      ctaLabel: 'Build your Box',
      link: '/build-a-box',
    },
    {
      kicker: 'Occasion Boxes',
      lead: 'Ready-made for',
      highlight: 'every moment',
      subtitle: 'Curated boxes for birthdays, weddings and thank-yous.',
      image: 'assets/giftly/occasion_box.png',
      gradient: 'linear-gradient(135deg, #D8E9F7 0%, #eef1fb 55%, #F4ECF7 100%)',
      ctaLabel: 'Shop Occasion Boxes',
      link: '/tabs/shop',
      linkParams: { type: 'occasion_box' },
    },
    {
      kicker: 'Baskets',
      lead: 'Giftly basket',
      highlight: 'delights',
      subtitle: 'Beautifully arranged baskets of premium goodies.',
      image: 'assets/giftly/giftly_basket.png',
      gradient: 'linear-gradient(135deg, #FCE7CE 0%, #f3f0e4 55%, #EBDEF0 100%)',
      ctaLabel: 'Browse Baskets',
      link: '/tabs/shop',
      linkParams: { type: 'basket' },
    },
  ];

  // Mirrors index.php's "Special Promotions" section — live promos from the
  // promo engine when any are active, else the same 4 static fallback cards
  // the website shows when none are.
  private readonly fallbackPromos: PromoCardVm[] = [
    {
      headline: 'Birthday Special',
      cond: 'Get 15% OFF on selected Birthday Boxes and celebration gifts.',
      code: '',
      icon: 'gift-outline',
      className: 'p-birthday',
      isLive: false,
    },
    {
      headline: 'Bundle and Save',
      cond: 'Buy any Giftly Bundle and save up to 20%',
      code: '',
      icon: 'cube-outline',
      className: 'p-bundle',
      isLive: false,
    },
    {
      headline: 'Free Shipping',
      cond: 'Enjoy FREE delivery on orders over ₱1,500.',
      code: '',
      icon: 'car-outline',
      className: 'p-shipping',
      isLive: false,
    },
    {
      headline: 'Seasonal Collection',
      cond: 'Shop exclusive limited-edition gift boxes for holidays',
      code: '',
      icon: 'leaf-outline',
      className: 'p-seasonal',
      isLive: false,
    },
  ];
  private readonly livePromos = signal<HomePromo[]>([]);

  promos(): PromoCardVm[] {
    const live = this.livePromos();
    if (!live.length) return this.fallbackPromos;
    return live.map((p, i) => ({
      headline: p.headline,
      cond: p.cond,
      code: p.code,
      icon: p.icon,
      className: CATEGORY_TILE_CLASSES[i % CATEGORY_TILE_CLASSES.length],
      isLive: true,
    }));
  }

  constructor() {
    addIcons({ giftOutline, addCircle, cubeOutline, carOutline, leafOutline, pricetagOutline, star, arrowForward });
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
    await Promise.all([this.loadFeatured(), this.loadCategories(), this.loadRecentOrder(), this.loadPromos()]);
  }

  async loadPromos(): Promise<void> {
    try {
      this.livePromos.set(await this.promoSvc.activePromos());
    } catch {
      // Non-critical — falls back to the static tiles.
      this.livePromos.set([]);
    }
  }

  async copyPromoCode(code: string): Promise<void> {
    try {
      await navigator.clipboard.writeText(code);
      await this.toast(`Code ${code} copied!`);
    } catch {
      await this.toast(code);
    }
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
    await Promise.all([this.loadFeatured(), this.loadCategories(), this.loadRecentOrder(), this.loadPromos()]);
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

  salePercent(product: Product): number {
    const list = Number(product.list_price ?? 0);
    const now = Number(product.price ?? 0);
    if (list <= 0 || now >= list) return 0;
    return Math.round((1 - now / list) * 100);
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
