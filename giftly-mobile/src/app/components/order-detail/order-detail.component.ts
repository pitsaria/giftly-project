import { Component, Input, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  IonHeader,
  IonToolbar,
  IonTitle,
  IonButtons,
  IonButton,
  IonIcon,
  IonContent,
  IonSpinner,
  ModalController,
  AlertController,
  ToastController,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import { closeOutline, starOutline } from 'ionicons/icons';
import { Order, OrderItem } from '../../core/models';
import { OrderService } from '../../core/order.service';
import { ImgUrlPipe } from '../../shared/img-url.pipe';
import { ProductReviewsComponent } from '../product-reviews/product-reviews.component';

// "Track Order" detail sheet — fetches the full order (with line items) since
// the orders list endpoint only returns the order header, not its items.
@Component({
  selector: 'app-order-detail',
  templateUrl: 'order-detail.component.html',
  styleUrls: ['order-detail.component.scss'],
  imports: [CommonModule, IonHeader, IonToolbar, IonTitle, IonButtons, IonButton, IonIcon, IonContent, IonSpinner, ImgUrlPipe],
})
export class OrderDetailComponent implements OnInit {
  @Input({ required: true }) order!: Order;

  private orderSvc = inject(OrderService);
  private modalCtrl = inject(ModalController);
  private alertCtrl = inject(AlertController);
  private toastCtrl = inject(ToastController);

  readonly detail = signal<Order | null>(null);
  readonly loading = signal(true);
  readonly cancelling = signal(false);
  readonly confirming = signal(false);

  constructor() {
    addIcons({ closeOutline, starOutline });
  }

  canConfirmReceived(): boolean {
    const d = this.detail();
    return !!d && d.status === 'delivered' && !d.received_at;
  }

  async confirmReceived(): Promise<void> {
    const d = this.detail();
    if (!d) return;
    this.confirming.set(true);
    try {
      await this.orderSvc.confirmReceived(d.id);
      this.detail.set(await this.orderSvc.getOrderDetails(d.id));
      const t = await this.toastCtrl.create({
        message: 'Thanks for confirming! You can now review the items you received.',
        duration: 2500,
      });
      await t.present();
    } catch {
      const t = await this.toastCtrl.create({ message: 'Could not update this order.', duration: 2000 });
      await t.present();
    } finally {
      this.confirming.set(false);
    }
  }

  async openReview(item: OrderItem): Promise<void> {
    const modal = await this.modalCtrl.create({
      component: ProductReviewsComponent,
      componentProps: { productId: item.product_id, writeMode: true, modal: true },
    });
    await modal.present();
  }

  async ngOnInit(): Promise<void> {
    try {
      this.detail.set(await this.orderSvc.getOrderDetails(this.order.id));
    } finally {
      this.loading.set(false);
    }
  }

  private readonly cancelReasons = [
    'Changed my mind',
    'Ordered by mistake',
    'Found a better price elsewhere',
    'Delivery is too slow / date too far out',
    'Wrong item, quantity, or details',
    'Financial reasons',
    'Other',
  ];

  async cancelOrder(): Promise<void> {
    const pick = await this.alertCtrl.create({
      header: 'Request cancellation',
      subHeader: 'An admin reviews and approves it. Your order stays active until then.',
      inputs: this.cancelReasons.map((r, i) => ({
        type: 'radio' as const,
        label: r,
        value: r,
        checked: i === 0,
      })),
      buttons: [
        { text: 'Keep order', role: 'cancel' },
        { text: 'Continue', role: 'confirm' },
      ],
    });
    await pick.present();
    const { role, data } = await pick.onDidDismiss();
    if (role !== 'confirm') return;

    let reason: string = data?.values || this.cancelReasons[0];
    if (reason === 'Other') {
      const other = await this.alertCtrl.create({
        header: 'Tell us why',
        inputs: [{ type: 'textarea' as const, name: 'text', placeholder: 'Your reason' }],
        buttons: [
          { text: 'Back', role: 'cancel' },
          { text: 'Submit', role: 'confirm' },
        ],
      });
      await other.present();
      const res = await other.onDidDismiss();
      if (res.role !== 'confirm' || !res.data?.values?.text?.trim()) return;
      reason = res.data.values.text.trim();
    }

    this.cancelling.set(true);
    try {
      await this.orderSvc.cancelOrder(this.order.id, reason);
      const t = await this.toastCtrl.create({
        message: 'Cancellation request sent — waiting for admin approval.',
        duration: 2500,
      });
      await t.present();
      this.modalCtrl.dismiss({ cancelRequested: true });
    } catch {
      const t = await this.toastCtrl.create({ message: 'Could not send your request.', duration: 2000 });
      await t.present();
    } finally {
      this.cancelling.set(false);
    }
  }

  dismiss(): void {
    this.modalCtrl.dismiss();
  }
}
