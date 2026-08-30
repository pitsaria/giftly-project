import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import {
  IonHeader,
  IonToolbar,
  IonTitle,
  IonButtons,
  IonBackButton,
  IonContent,
  IonFooter,
  IonButton,
  IonIcon,
  IonSpinner,
  IonSearchbar,
  IonChip,
  IonLabel,
  IonModal,
  IonTextarea,
  IonInfiniteScroll,
  IonInfiniteScrollContent,
  ModalController,
  ToastController,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import {
  giftOutline,
  addOutline,
  removeOutline,
  closeOutline,
  checkmarkCircle,
  trashOutline,
  bookmarkOutline,
  cartOutline,
  lockClosedOutline,
  createOutline,
  star,
} from 'ionicons/icons';
import { BoxCardStyle, BoxLineItem, BoxProduct, BoxSize, Category } from '../../core/models';
import { BoxService } from '../../core/box.service';
import { ProductService } from '../../core/product.service';
import { CartService } from '../../core/cart.service';
import { AuthService } from '../../core/auth.service';
import { describeError } from '../../core/http-error';
import { ImgUrlPipe } from '../../shared/img-url.pipe';
import { ProductDetailComponent } from '../../components/product-detail/product-detail.component';

interface DraftItem {
  product_id: number;
  name: string;
  price: number;
  image: string;
  qty: number;
  stock: number;
}

// Mirrors giftly_project/build-a-box.php: pick a size, fill it with gifts,
// write a letter, then Save / Add to Cart / Checkout.
@Component({
  selector: 'app-build-a-box',
  templateUrl: 'build-a-box.page.html',
  styleUrls: ['build-a-box.page.scss'],
  imports: [
    CommonModule,
    FormsModule,
    RouterLink,
    IonHeader,
    IonToolbar,
    IonTitle,
    IonButtons,
    IonBackButton,
    IonContent,
    IonFooter,
    IonButton,
    IonIcon,
    IonSpinner,
    IonSearchbar,
    IonChip,
    IonLabel,
    IonModal,
    IonTextarea,
    IonInfiniteScroll,
    IonInfiniteScrollContent,
    ImgUrlPipe,
  ],
})
export class BuildABoxPage implements OnInit {
  auth = inject(AuthService);
  private box = inject(BoxService);
  private productSvc = inject(ProductService);
  private cart = inject(CartService);
  private router = inject(Router);
  private route = inject(ActivatedRoute);
  private modalCtrl = inject(ModalController);
  private toastCtrl = inject(ToastController);

  readonly loading = signal(true);
  readonly error = signal<string | null>(null);
  readonly saving = signal(false);

  readonly sizes = signal<BoxSize[]>([]);
  readonly cardStyles = signal<BoxCardStyle[]>([]);
  readonly categories = signal<Category[]>([]);

  readonly selectedSize = signal<BoxSize | null>(null);
  readonly sizeCollapsed = signal(false);

  readonly products = signal<BoxProduct[]>([]);
  readonly productLoading = signal(false);
  productPage = 1;
  productTotalPages = 1;
  search = '';
  selectedCategory: number | null = null;

  readonly items = signal<DraftItem[]>([]);
  readonly letter = signal('');
  readonly cardStyle = signal('simple');
  readonly letterOpen = signal(false);
  draftLetter = '';
  draftCardStyle = 'simple';

  editBoxId = 0;

  readonly itemCount = computed(() => this.items().reduce((a, i) => a + i.qty, 0));
  readonly maxItems = computed(() => this.selectedSize()?.max_items ?? 0);
  readonly boxFull = computed(() => this.itemCount() >= this.maxItems());
  readonly subtotal = computed(() => this.items().reduce((a, i) => a + i.price * i.qty, 0));
  readonly grandTotal = computed(() => this.subtotal() + (this.selectedSize()?.price ?? 0));
  readonly progressPct = computed(() =>
    this.maxItems() ? Math.min(100, (this.itemCount() / this.maxItems()) * 100) : 0
  );

  constructor() {
    addIcons({
      giftOutline,
      addOutline,
      removeOutline,
      closeOutline,
      checkmarkCircle,
      trashOutline,
      bookmarkOutline,
      cartOutline,
      lockClosedOutline,
      createOutline,
      star,
    });
  }

  async ngOnInit(): Promise<void> {
    if (!this.auth.isLoggedIn()) {
      this.loading.set(false);
      return;
    }
    this.editBoxId = Number(this.route.snapshot.queryParamMap.get('box_id')) || 0;
    await this.loadInit();
  }

  async loadInit(): Promise<void> {
    this.loading.set(true);
    this.error.set(null);
    try {
      const [meta, categories] = await Promise.all([
        this.box.sizes(),
        this.productSvc.getCategories().catch(() => []),
      ]);
      this.sizes.set(meta.sizes);
      this.cardStyles.set(meta.cardStyles);
      this.categories.set(categories);

      if (this.editBoxId) {
        const existing = await this.box.getBox(this.editBoxId);
        const size = meta.sizes.find((s) => s.id === existing.box_size_id) ?? null;
        this.selectedSize.set(size);
        this.sizeCollapsed.set(!!size);
        this.letter.set(existing.letter);
        this.cardStyle.set(existing.card_style || 'simple');
        this.items.set(
          existing.items
            .filter((i: BoxLineItem) => i.unavailable !== 'removed')
            .map((i: BoxLineItem) => ({
              product_id: i.product_id,
              name: i.name,
              price: i.price,
              image: i.image,
              qty: i.quantity,
              stock: i.stock,
            }))
        );
        if (size) await this.loadProducts(true);
      }
    } catch (err) {
      this.error.set(describeError(err));
    } finally {
      this.loading.set(false);
    }
  }

  async chooseSize(size: BoxSize): Promise<void> {
    const prev = this.selectedSize();
    this.selectedSize.set(size);
    this.sizeCollapsed.set(true);
    // Trim overflow if the new box is smaller.
    if (this.itemCount() > size.max_items) {
      let over = this.itemCount() - size.max_items;
      const next = [...this.items()];
      for (let i = next.length - 1; i >= 0 && over > 0; i--) {
        const trim = Math.min(next[i].qty, over);
        next[i].qty -= trim;
        over -= trim;
        if (next[i].qty <= 0) next.splice(i, 1);
      }
      this.items.set(next);
      await this.toast('Some items were removed to fit the smaller box.');
    }
    if (prev?.id !== size.id) {
      this.productPage = 1;
      await this.loadProducts(true);
    }
  }

  changeSize(): void {
    this.sizeCollapsed.set(false);
  }

  async onSearch(): Promise<void> {
    this.productPage = 1;
    await this.loadProducts(true);
  }

  async pickCategory(id: number | null): Promise<void> {
    this.selectedCategory = id;
    this.productPage = 1;
    await this.loadProducts(true);
  }

  async loadProducts(reset = false): Promise<void> {
    const size = this.selectedSize();
    if (!size) return;
    this.productLoading.set(true);
    try {
      const res = await this.box.products({
        size_id: size.id,
        search: this.search || undefined,
        category: this.selectedCategory ?? undefined,
        page: this.productPage,
      });
      this.productTotalPages = res.pagination.total_pages;
      this.products.set(reset ? res.products : [...this.products(), ...res.products]);
    } catch (err) {
      await this.toast(describeError(err));
    } finally {
      this.productLoading.set(false);
    }
  }

  async loadMore(event: any): Promise<void> {
    if (this.productPage < this.productTotalPages) {
      this.productPage++;
      await this.loadProducts(false);
    }
    event.target.complete();
    if (this.productPage >= this.productTotalPages) event.target.disabled = true;
  }

  qtyInBox(productId: number): number {
    return this.items().find((i) => i.product_id === productId)?.qty ?? 0;
  }

  async openProduct(p: BoxProduct): Promise<void> {
    const modal = await this.modalCtrl.create({
      component: ProductDetailComponent,
      componentProps: {
        product: {
          id: p.id,
          name: p.name,
          description: p.description,
          price: String(p.price),
          image: p.image,
          quantity: p.quantity,
          category_id: p.category_id,
          avg_rating: String(p.rating),
          review_count: p.rating_count,
        },
        mode: 'box',
        boxFull: this.boxFull(),
        inBoxQty: this.qtyInBox(p.id),
      },
      breakpoints: [0, 0.75, 0.95],
      initialBreakpoint: 0.75,
    });
    await modal.present();
    const { data } = await modal.onWillDismiss();
    if (data?.addToBox) {
      if (this.qtyInBox(p.id) > 0) await this.inc(p.id);
      else await this.add(p);
    }
  }

  async add(p: BoxProduct): Promise<void> {
    if (this.boxFull()) return this.toast('This box is full.');
    if (p.quantity <= 0) return this.toast('Out of stock.');
    this.items.set([
      ...this.items(),
      { product_id: p.id, name: p.name, price: p.price, image: p.image, qty: 1, stock: p.quantity },
    ]);
  }

  async inc(productId: number): Promise<void> {
    if (this.boxFull()) return this.toast('This box is full.');
    const next = this.items().map((i) => ({ ...i }));
    const it = next.find((i) => i.product_id === productId);
    if (!it) return;
    if (it.qty >= it.stock) return this.toast(`Only ${it.stock} in stock.`);
    it.qty++;
    this.items.set(next);
  }

  dec(productId: number): void {
    const next = this.items()
      .map((i) => ({ ...i }))
      .map((i) => (i.product_id === productId ? { ...i, qty: i.qty - 1 } : i))
      .filter((i) => i.qty > 0);
    this.items.set(next);
  }

  remove(productId: number): void {
    this.items.set(this.items().filter((i) => i.product_id !== productId));
  }

  openLetter(): void {
    this.draftLetter = this.letter();
    this.draftCardStyle = this.cardStyle();
    this.letterOpen.set(true);
  }

  saveLetter(): void {
    this.letter.set(this.draftLetter.trim());
    this.cardStyle.set(this.draftCardStyle);
    this.letterOpen.set(false);
  }

  cardStyleLabel(): string {
    return this.cardStyles().find((s) => s.key === this.cardStyle())?.label ?? 'Simple note';
  }

  cardStyleEmoji(): string {
    return this.cardStyles().find((s) => s.key === this.cardStyle())?.emoji ?? '✉️';
  }

  stars(count: number): number[] {
    return Array.from({ length: Math.min(5, Math.max(0, Math.round(count))) });
  }

  private buildPayload(status: 'saved' | 'in_cart') {
    return {
      box_id: this.editBoxId || undefined,
      size_id: this.selectedSize()!.id,
      letter: this.letter(),
      card_style: this.cardStyle(),
      status,
      items: this.items().map((i) => ({ product_id: i.product_id, quantity: i.qty })),
    };
  }

  async submit(action: 'saved' | 'in_cart' | 'checkout'): Promise<void> {
    if (!this.selectedSize()) return this.toast('Choose a box size first.');
    if (!this.items().length) return this.toast('Add at least one item to your box.');

    this.saving.set(true);
    try {
      if (action === 'checkout') {
        const res = await this.box.saveBox(this.buildPayload('saved'));
        this.router.navigate(['/box-checkout'], { queryParams: { box_id: res.box_id } });
        return;
      }
      const res = await this.box.saveBox(this.buildPayload(action));
      this.editBoxId = res.box_id;
      if (action === 'in_cart') {
        await this.cart.getCart();
        await this.toast('Box added to cart.');
      } else {
        await this.toast('Box saved to your profile.');
      }
      await this.box.listBoxes().catch(() => []);
      this.router.navigate(['/tabs/profile'], { queryParams: { tab: 'boxes' } });
    } catch (err) {
      await this.toast(describeError(err));
    } finally {
      this.saving.set(false);
    }
  }

  private async toast(message: string): Promise<void> {
    const t = await this.toastCtrl.create({ message, duration: 1800, position: 'bottom' });
    await t.present();
  }
}
