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
  ModalController,
  ToastController,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import { star, starOutline, starHalf, closeOutline } from 'ionicons/icons';
import { ReviewData } from '../../core/models';
import { ReviewService } from '../../core/review.service';
import { AuthService } from '../../core/auth.service';
import { describeError } from '../../core/http-error';

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
  ],
})
export class ProductReviewsComponent implements OnInit {
  @Input({ required: true }) productId!: number;
  // When true, start with the write form focused (used from order detail).
  @Input() writeMode = false;
  // When true, render as a standalone modal (header + ion-content wrapper).
  @Input() modal = false;

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
