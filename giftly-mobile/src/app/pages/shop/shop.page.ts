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
    if (!this.auth.isLoggedIn()) return;
    await this.wishlist.toggle(product.id);
  }

  async quickAdd(product: Product, ev: Event): Promise<void> {
    ev.stopPropagation();
    if (!this.auth.isLoggedIn() || product.quantity <= 0) return;
    await this.cart.addToCart(product.id, 1);
  }

  async openProduct(product: Product): Promise<void> {
    const modal = await this.modalCtrl.create({
      component: ProductDetailComponent,
      componentProps: { product },
    });
    await modal.present();
  }
}
