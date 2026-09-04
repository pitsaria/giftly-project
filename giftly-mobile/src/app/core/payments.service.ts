import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
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

  /** Open a PayMongo hosted-checkout URL. Web opens a new tab; native falls back. */
  openCheckout(url: string): void {
    if (!url) return;
    const win = window.open(url, '_blank');
    if (!win) window.location.href = url;
  }
}
