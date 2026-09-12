import { Component, Input, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import {
  IonIcon,
  IonTextarea,
  IonButton,
  IonSpinner,
  IonHeader,
  IonToolbar,
  IonTitle,
  IonButtons,
  IonContent,
  IonChip,
  IonLabel,
  ModalController,
  ToastController,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import { star, starOutline, starHalf, closeOutline } from 'ionicons/icons';
import { Review, ReviewData } from '../../core/models';
import { ReviewService } from '../../core/review.service';
import { AuthService } from '../../core/auth.service';
import { describeError } from '../../core/http-error';
import { ImgUrlPipe } from '../../shared/img-url.pipe';

// Mirrors giftly_project/get_product_reviews.php (list) + submit_review.php (write).
// Embedded in the product quick-view sheet and reused per line-item in the
// order-detail sheet once an order is confirmed received.
@Component({
  selector: 'app-product-reviews',
  templateUrl: 'product-reviews.component.html',
  styleUrls: ['product-reviews.component.scss'],
  imports: [
    CommonModule,
    FormsModule,
    IonIcon,
    IonTextarea,
    IonButton,
    IonSpinner,
    IonHeader,
    IonToolbar,
    IonTitle,
    IonButtons,
    IonContent,
    IonChip,
    IonLabel,
    ImgUrlPipe,
  ],
})
export class ProductReviewsComponent implements OnInit {
  @Input({ required: true }) productId!: number;
  // When true, start with the write form focused (used from order detail).
  @Input() writeMode = false;
  // When true, render as a standalone modal (header + ion-content wrapper).
  @Input() modal = false;
  // Shown in the modal header when reviewing from an order line item, so the
  // user can see which product they're reviewing.
  @Input() productName?: string;
  @Input() productImage?: string;

  private reviewSvc = inject(ReviewService);
  auth = inject(AuthService);
  private modalCtrl = inject(ModalController);
  private toastCtrl = inject(ToastController);

  readonly data = signal<ReviewData | null>(null);
  readonly loading = signal(true);
  readonly submitting = signal(false);

  myRating = 0;
  myComment = '';

  readonly fullStars = [1, 2, 3, 4, 5];

  // 0 = All. Filters the rendered list client-side by rounded rating.
  readonly starFilter = signal(0);

  filteredReviews(reviews: Review[]): Review[] {
    const f = this.starFilter();
    return f === 0 ? reviews : reviews.filter((r) => Math.round(r.rating) === f);
  }

  setStarFilter(value: number): void {
    this.starFilter.set(value);
  }

  constructor() {
    addIcons({ star, starOutline, starHalf, closeOutline });
  }

  close(): void {
    this.modalCtrl.dismiss();
  }

  async ngOnInit(): Promise<void> {
    await this.reload();
  }

  async reload(): Promise<void> {
    this.loading.set(true);
    try {
      this.data.set(await this.reviewSvc.getReviews(this.productId));
    } catch {
      this.data.set(null);
    } finally {
      this.loading.set(false);
    }
  }

  starIcon(position: number, rating: number): string {
    if (rating >= position) return 'star';
    if (rating >= position - 0.5) return 'star-half';
    return 'star-outline';
  }

  async submit(): Promise<void> {
    if (this.myRating < 1) {
      await this.toast('Please give a star rating.');
      return;
    }
    this.submitting.set(true);
    try {
      await this.reviewSvc.submitReview(this.productId, this.myRating, this.myComment.trim());
      this.myRating = 0;
      this.myComment = '';
      await this.toast('Thanks for your review!');
      await this.reload();
    } catch (err) {
      await this.toast(describeError(err));
    } finally {
      this.submitting.set(false);
    }
  }

  private async toast(message: string): Promise<void> {
    const t = await this.toastCtrl.create({ message, duration: 2000, position: 'bottom' });
    await t.present();
  }
}
