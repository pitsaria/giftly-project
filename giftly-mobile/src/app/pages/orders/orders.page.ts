import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import {
  IonContent,
  IonRefresher,
  IonRefresherContent,
  IonChip,
  IonLabel,
  IonIcon,
  IonButton,
  IonSkeletonText,
  ModalController,
} from '@ionic/angular';
import { Router } from '@angular/router';
import { ToastController } from '@ionic/angular';
import { addIcons } from 'ionicons';
import { giftOutline } from 'ionicons/icons';
import { Order } from '../../core/models';
import { OrderService } from '../../core/order.service';
import { PaymentsService } from '../../core/payments.service';
import { AuthService } from '../../core/auth.service';
import { describeError } from '../../core/http-error';
import { TopBarComponent } from '../../shared/top-bar/top-bar.component';
import { OrderDetailComponent } from '../../components/order-detail/order-detail.component';

type StatusFilter = 'all' | 'pending' | 'shipped' | 'delivered' | 'cancelled';

const STATUS_LABEL: Record<Order['status'], string> = {
  pending: 'Processing',
  shipped: 'Shipped',
  delivered: 'Delivered',
  cancelled: 'Cancelled',
};

// Own tab (was a segment nested inside Profile) so it matches the app's
// Home / Shop / Orders / Profile navigation.
@Component({
  selector: 'app-orders',
  templateUrl: 'orders.page.html',
  styleUrls: ['orders.page.scss'],
  imports: [
    CommonModule,
    RouterLink,
    IonContent,
    IonRefresher,
    IonRefresherContent,
    IonChip,
    IonLabel,
    IonIcon,
    IonButton,
    IonSkeletonText,
    TopBarComponent,
  ],
})
export class OrdersPage implements OnInit {
  private orderSvc = inject(OrderService);
  private payments = inject(PaymentsService);
  private modalCtrl = inject(ModalController);
  private router = inject(Router);
  private toastCtrl = inject(ToastController);
  auth = inject(AuthService);

  constructor() {
    addIcons({ giftOutline });
  }

  readonly orders = signal<Order[]>([]);
  readonly paying = signal<number | null>(null);
  readonly filter = signal<StatusFilter>('all');
  readonly loading = signal(false);
  readonly error = signal<string | null>(null);
  readonly slowLoad = signal(false);
  private slowLoadTimer: ReturnType<typeof setTimeout> | undefined;
  private loadToken = 0;
  readonly skeletonRows = Array.from({ length: 3 });

  readonly filtered = computed(() => {
    const f = this.filter();
    return f === 'all' ? this.orders() : this.orders().filter((o) => o.status === f);
  });

  async ngOnInit(): Promise<void> {
    if (this.auth.isLoggedIn()) {
      await this.load();
    }
  }

  async ionViewWillEnter(): Promise<void> {
    if (this.auth.isLoggedIn()) {
      await this.load();
    }
  }

  setFilter(value: StatusFilter): void {
    this.filter.set(value);
  }

  statusLabel(status: Order['status']): string {
    return STATUS_LABEL[status];
  }

  private isOnline(o: Order): boolean {
    return (o.payment_method ?? 'cod') !== 'cod';
  }

  needsPayment(o: Order): boolean {
    return (
      this.isOnline(o) &&
      o.status !== 'cancelled' &&
      (o.payment_status ?? 'unpaid') === 'unpaid'
    );
  }

  paymentLabel(o: Order): string {
    if (!this.isOnline(o)) return 'Cash on delivery';
    const ps = o.payment_status ?? 'unpaid';
    if (ps === 'paid') return 'Paid online';
    if (ps === 'failed') return 'Payment failed';
    return 'Awaiting payment';
  }

  paymentClass(o: Order): string {
    if (!this.isOnline(o)) return 'pay-cod';
    const ps = o.payment_status ?? 'unpaid';
    return ps === 'paid' ? 'pay-paid' : ps === 'failed' ? 'pay-failed' : 'pay-unpaid';
  }

  async payNow(o: Order): Promise<void> {
    this.paying.set(o.id);
    try {
      const res = await this.orderSvc.paymentStatus(o.id, true);
      if (res.payment_status === 'paid') {
        await this.load();
        return;
      }
      if (res.checkout_url) {
        this.payments.openCheckout(res.checkout_url);
      }
      this.router.navigate(['/payment-waiting'], { queryParams: { order_id: o.id } });
    } catch (err) {
      const t = await this.toastCtrl.create({ message: describeError(err), duration: 2500 });
      await t.present();
    } finally {
      this.paying.set(null);
    }
  }

  async load(): Promise<void> {
    const token = ++this.loadToken;

    this.loading.set(true);
    this.error.set(null);
    this.slowLoad.set(false);
    clearTimeout(this.slowLoadTimer);
    this.slowLoadTimer = setTimeout(() => {
      if (token === this.loadToken) {
        this.slowLoad.set(true);
      }
    }, 6000);

    try {
      const orders = await this.orderSvc.getOrders();
      if (token !== this.loadToken) return;
      this.orders.set(orders);
    } catch (err) {
      if (token !== this.loadToken) return;
      this.error.set(describeError(err));
    } finally {
      if (token === this.loadToken) {
        clearTimeout(this.slowLoadTimer);
        this.loading.set(false);
      }
    }
  }

  async handleRefresh(event: any): Promise<void> {
    await this.load();
    event.target.complete();
  }

  async trackOrder(order: Order): Promise<void> {
    const modal = await this.modalCtrl.create({
      component: OrderDetailComponent,
      componentProps: { order },
    });
    await modal.present();
    const { data } = await modal.onWillDismiss();
    if (data?.cancelled || data?.cancelRequested) {
      await this.load();
    }
  }
}
