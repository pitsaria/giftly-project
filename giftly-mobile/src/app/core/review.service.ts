import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { ApiService } from './api.service';
import { ReviewData } from './models';

// Wraps the api/index.php reviews route.
// Mirrors get_product_reviews.php (read) and submit_review.php (write).
@Injectable({ providedIn: 'root' })
export class ReviewService {
  private api = inject(ApiService);

  async getReviews(productId: number): Promise<ReviewData> {
    const res = await firstValueFrom(this.api.get<ReviewData>('reviews', { product_id: productId }));
    return res.data;
  }

  async submitReview(
    productId: number,
    rating: number,
    comment: string
  ): Promise<{ avg: number; count: number }> {
    const res = await firstValueFrom(
      this.api.post<{ avg: number; count: number }>('reviews', {
        product_id: productId,
        rating,
        comment,
      })
    );
    return res.data;
  }
}
