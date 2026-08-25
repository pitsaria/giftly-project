import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import {
  IonHeader,
  IonToolbar,
  IonTitle,
  IonContent,
  IonSearchbar,
  IonChip,
  IonLabel,
  IonIcon,
  IonInfiniteScroll,
  IonInfiniteScrollContent,
  IonSpinner,
  ModalController,
  ToastController,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import { heart, heartOutline, addCircle } from 'ionicons/icons';
import { Category, Product } from '../../core/models';
import { ProductService } from '../../core/product.service';
import { CartService } from '../../core/cart.service';
import { WishlistService } from '../../core/wishlist.service';
import { AuthService } from '../../core/auth.service';
import { ProductDetailComponent } from '../../components/product-detail/product-detail.component';
import { environment } from '../../../environments/environment';

// Mirrors giftly_project/shop.php: category chips + search + product grid.
@Component({
  selector: 'app-shop',
  templateUrl: 'shop.page.html',
  styleUrls: ['shop.page.scss'],
  imports: [
    CommonModule,
    FormsModule,
    IonHeader,
    IonToolbar,
    IonTitle,
    IonContent,
    IonSearchbar,
    IonChip,
    IonLabel,
    IonIcon,
    IonInfiniteScroll,
    IonInfiniteScrollContent,
    IonSpinner,
  ],
})
export class ShopPage implements OnInit {
  private productSvc = inject(ProductService);
  private cart = inject(CartService);
  wishlist = inject(WishlistService);
  auth = inject(AuthService);
  private modalCtrl = inject(ModalController);
  private toastCtrl = inject(ToastController);

  readonly uploadsUrl = environment.uploadsUrl;

  categories: Category[] = [];
  selectedCategory: number | null = null;
  search = '';
  products: Product[] = [];
  page = 1;
  totalPages = 1;
  loading = false;

  constructor() {
    addIcons({ heart, heartOutline, addCircle });
  }

  async ngOnInit(): Promise<void> {
    this.categories = await this.productSvc.getCategories();
    await this.loadProducts();
    if (this.auth.isLoggedIn()) {
      await this.wishlist.getWishlist();
    }
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
    this.loading = true;
    try {
      const result = await this.productSvc.getAll({
        page: this.page,
        limit: 20,
        search: this.search || undefined,
        category: this.selectedCategory ?? undefined,
      });
      this.products = this.page === 1 ? result.products : [...this.products, ...result.products];
      this.totalPages = result.pagination.total_pages;
    } finally {
      this.loading = false;
    }
  }

  async loadMore(event: any): Promise<void> {
    if (this.page < this.totalPages) {
      this.page++;
      await this.loadProducts();
    }
    event.target.complete();
    if (this.page >= this.totalPages) {
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
    const modal = await this.modalCtrl.create({
      component: ProductDetailComponent,
      componentProps: { product },
    });
    await modal.present();
  }
}
