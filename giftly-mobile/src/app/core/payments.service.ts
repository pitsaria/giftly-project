import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { Capacitor } from '@capacitor/core';
import { Browser } from '@capacitor/browser';
import { ApiService } from './api.service';

export interface PaymongoConfig {
  enabled: boolean;
  methods: string[];
}

// Whether the server has PayMongo configured (PAYMONGO_SECRET_KEY). The app
// only offers "Pay Online" when this says so — same as the website.
@Injectable({ providedIn: 'root' })
export class PaymentsService {
  private api = inject(ApiService);
  private cached: PaymongoConfig | null = null;

  async config(): Promise<PaymongoConfig> {
    if (this.cached) return this.cached;
    try {
      const res = await firstValueFrom(this.api.get<PaymongoConfig>('paymongo/config'));
      this.cached = { enabled: !!res.data.enabled, methods: res.data.methods ?? [] };
    } catch {
      this.cached = { enabled: false, methods: [] };
    }
    return this.cached;
  }

  /**
   * Open a PayMongo hosted-checkout URL.
   *  - Native (APK / iOS): an in-app browser tab you can close to return to
   *    the app — /payment-waiting keeps polling in the background.
   *  - Web: a new tab, falling back to same-tab navigation if it's blocked.
   */
  openCheckout(url: string): void {
    if (!url) return;
    if (Capacitor.isNativePlatform()) {
      Browser.open({ url }).catch(() => {
        window.location.href = url;
      });
      return;
    }
    const win = window.open(url, '_blank');
    if (!win) window.location.href = url;
  }

  /** Close the in-app browser tab (native only; no-op on web). */
  async closeCheckout(): Promise<void> {
    if (Capacitor.isNativePlatform()) {
      try {
        await Browser.close();
      } catch {
        // already closed
      }
    }
  }

  /**
   * Run `cb` when the in-app browser tab is dismissed (native only). Returns a
   * teardown function. On web the tab close can't be observed, so this is inert.
   */
  onCheckoutClosed(cb: () => void): () => void {
    if (!Capacitor.isNativePlatform()) return () => {};
    const handle = Browser.addListener('browserFinished', cb);
    return () => {
      void handle.then((h) => h.remove());
    };
  }
}
