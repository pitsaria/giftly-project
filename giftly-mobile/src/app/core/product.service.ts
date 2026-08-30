import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { ApiService } from './api.service';
import { Category, Product, ProductType } from './models';

export interface ProductPage {
  products: Product[];
  pagination: { page: number; limit: number; total: number; total_pages: number };
}

export interface ProductQuery {
  page?: number;
  limit?: number;
  search?: string;
  category?: number;
  order?: 'asc' | 'desc';
  // 'catalog' (default on the API) = shop items; 'occasion_box' / 'basket' are
  // the curated storefronts. Mirrors occasion-boxes.php / baskets.php.
  type?: ProductType;
}

@Injectable({ providedIn: 'root' })
export class ProductService {
  private api = inject(ApiService);

  async getAll(query: ProductQuery = {}): Promise<ProductPage> {
    const params: Record<string, string | number> = {
      page: query.page ?? 1,
      limit: query.limit ?? 20,
      order: query.order ?? 'asc',
    };
    if (query.search) params['search'] = query.search;
    if (query.category) params['category'] = query.category;
    if (query.type) params['type'] = query.type;

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
