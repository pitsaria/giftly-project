import { Component, OnDestroy, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { IonHeader, IonToolbar, IonTitle, IonContent, IonButton, IonIcon, IonSpinner } from '@ionic/angular';
import { addIcons } from 'ionicons';
import { checkmarkCircle, timeOutline, closeCircle, refreshOutline, openOutline } from 'ionicons/icons';
import { OrderService } from '../../core/order.service';
import { PaymentsService } from '../../core/payments.service';

type Phase = 'waiting' | 'paid' | 'failed' | 'timeout';

// Shown after handing off to PayMongo's hosted page. The webhook is the source
// of truth (paymongo_webhook.php); this screen just polls the order until it
// flips to paid, or lets the customer retry / bail to the Orders tab.
@Component({
  selector: 'app-payment-waiting',
  templateUrl: 'payment-waiting.page.html',
  styleUrls: ['payment-waiting.page.scss'],
  imports: [CommonModule, IonHeader, IonToolbar, IonTitle, IonContent, IonButton, IonIcon, IonSpinner],
})
export class PaymentWaitingPage implements OnInit, OnDestroy {
  private orderSvc = inject(OrderService);
  private payments = inject(PaymentsService);
  private router = inject(Router);
  private route = inject(ActivatedRoute);

  orderId = 0;
  readonly phase = signal<Phase>('waiting');
  readonly checking = signal(false);
  private pollTimer: ReturnType<typeof setInterval> | undefined;
  private attempts = 0;
  private readonly maxAttempts = 40; // ~2 min at 3s

  constructor() {
    addIcons({ checkmarkCircle, timeOutline, closeCircle, refreshOutline, openOutline });
  }

  ngOnInit(): void {
    this.orderId = Number(this.route.snapshot.queryParamMap.get('order_id')) || 0;
    if (!this.orderId) {
      this.router.navigateByUrl('/tabs/orders');
      return;
    }
    this.check();
    this.pollTimer = setInterval(() => {
      this.attempts++;
      if (this.attempts > this.maxAttempts) {
        this.phase.set('timeout');
        this.stopPolling();
        return;
      }
      if (this.phase() === 'waiting') this.check();
    }, 3000);
  }

  ngOnDestroy(): void {
    this.stopPolling();
  }

  private stopPolling(): void {
    clearInterval(this.pollTimer);
  }

  async check(): Promise<void> {
    if (this.checking()) return;
    this.checking.set(true);
    try {
      const res = await this.orderSvc.paymentStatus(this.orderId);
      if (res.payment_status === 'paid') {
        this.phase.set('paid');
        this.stopPolling();
        try {
          const o = await this.orderSvc.getOrderDetails(this.orderId);
          this.orderSvc.lastOrder.set({
            orderId: o.id,
            total: Number(o.total_amount) || 0,
            paymentMethod: o.payment_method || 'online',
            deliveryDate: o.delivery_date,
            deliveryTime: o.delivery_time,
            address: o.address,
            city: o.city,
            recipientName: o.recipient_name || undefined,
            recipientPhone: o.recipient_phone || undefined,
            giftMessage: o.gift_message || undefined,
          });
        } catch {
          this.orderSvc.lastOrder.set({
            orderId: this.orderId,
            total: 0,
            paymentMethod: 'online',
            deliveryDate: '',
            deliveryTime: '',
            address: '',
            city: '',
          });
        }
        setTimeout(() => this.router.navigateByUrl('/order-confirmation'), 900);
      } else if (res.payment_status === 'failed' || res.status === 'cancelled') {
        this.phase.set('failed');
        this.stopPolling();
      }
    } catch {
      // keep polling; transient
    } finally {
      this.checking.set(false);
    }
  }

  async reopen(): Promise<void> {
    try {
      const res = await this.orderSvc.paymentStatus(this.orderId, true);
      if (res.payment_status === 'paid') {
        this.phase.set('paid');
        setTimeout(() => this.router.navigateByUrl('/order-confirmation'), 600);
        return;
      }
      if (res.checkout_url) {
        this.payments.openCheckout(res.checkout_url);
        this.phase.set('waiting');
        this.attempts = 0;
      }
    } catch {
      // ignore
    }
  }

  goToOrders(): void {
    this.router.navigateByUrl('/tabs/orders');
  }
}
