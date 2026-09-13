import { Injectable, inject, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { ApiService } from './api.service';
import { Category, Product, ProductType } from './models';

export interface ReviewSummary {
  avg: string;
  count: number;
}

export interface ProductPage {
  products: Product[];
  pagination: { page: number; limit: number; total: number; total_pages: number };
}

export type ProductSort = 'newest' | 'popular' | 'price_asc' | 'price_desc';

export interface ProductQuery {
  page?: number;
  limit?: number;
  search?: string;
  category?: number;
  order?: 'asc' | 'desc';
  // 'catalog' (default on the API) = shop items; 'occasion_box' / 'basket' are
  // the curated storefronts. Mirrors occasion-boxes.php / baskets.php.
  type?: ProductType;
  // Filters — mirror shop.php's filter bar / ProductService::getAll() on the API.
  minPrice?: number;
  maxPrice?: number;
  minRating?: number;
  onSale?: boolean;
  sort?: ProductSort;
}

@Injectable({ providedIn: 'root' })
export class ProductService {
  private api = inject(ApiService);

  // Live review avg/count per product id, keyed by product id. A product's
  // own avg_rating/review_count fields are a snapshot from whenever it was
  // fetched (e.g. the Featured Products list), so once a review is
  // submitted from the product-detail sheet this store lets every card and
  // sheet showing that product pick up the new numbers immediately —
  // signal writes trigger a re-render regardless of zone.js/zoneless setup,
  // which a plain mutation on the fetched Product object would not.
  private readonly reviewSummaries = signal<Record<number, ReviewSummary>>({});

  updateReviewSummary(productId: number, avg: number, count: number): void {
    this.reviewSummaries.update((map) => ({ ...map, [productId]: { avg: String(avg), count } }));
  }

  reviewSummaryFor(productId: number): ReviewSummary | undefined {
    return this.reviewSummaries()[productId];
  }

  async getAll(query: ProductQuery = {}): Promise<ProductPage> {
    const params: Record<string, string | number> = {
      page: query.page ?? 1,
      limit: query.limit ?? 20,
      order: query.order ?? 'asc',
    };
    if (query.search) params['search'] = query.search;
    if (query.category) params['category'] = query.category;
    if (query.type) params['type'] = query.type;
    if (query.minPrice !== undefined) params['min_price'] = query.minPrice;
    if (query.maxPrice !== undefined) params['max_price'] = query.maxPrice;
    if (query.minRating !== undefined) params['min_rating'] = query.minRating;
    if (query.onSale) params['on_sale'] = 1;
    if (query.sort) params['sort'] = query.sort;

    const res = await firstValueFrom(this.api.get<ProductPage>('products', params));
    return res.data;
  }

  async getOne(id: number): Promise<Product> {
    const res = await firstValueFrom(this.api.get<Product>('products/single', { id }));
    return res.data;
  }

  async getCategories(): Promise<Category[]> {
    const res = await firstValueFrom(this.api.get<{ categories: Category[] }>('categories'));
    return res.data.categories;
  }
}
