import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { ApiService } from './api.service';
import { HomePromo, PromoEval, Voucher } from './models';

export interface PromoEvaluateOptions {
  code?: string;
  selectedIds?: number[];
  boxId?: number;
  addonIds?: number[];
}

// Thin wrapper over api/index.php's promo/evaluate + promos/active routes.
// Mirrors promo_apply.php (checkout code box) and index.php's "Special
// Promotions" section.
@Injectable({ providedIn: 'root' })
export class PromoService {
  private api = inject(ApiService);

  async evaluate(scope: 'products' | 'box', opts: PromoEvaluateOptions = {}): Promise<PromoEval> {
    const body: Record<string, unknown> = { scope };
    if (opts.code !== undefined) body['code'] = opts.code;
    if (opts.selectedIds) body['selected_ids'] = opts.selectedIds;
    if (opts.boxId) body['box_id'] = opts.boxId;
    if (opts.addonIds) body['addon_ids'] = opts.addonIds;
    const res = await firstValueFrom(this.api.post<PromoEval>('promo/evaluate', body));
    return res.data;
  }

  async activePromos(): Promise<HomePromo[]> {
    const res = await firstValueFrom(this.api.get<{ promos: HomePromo[] }>('promos/active'));
    return res.data.promos;
  }

  // Saves a coded promo to the customer's vouchers. Claiming is a convenience
  // only — it never changes how a code is validated or redeemed.
  async claim(promoId: number): Promise<void> {
    await firstValueFrom(this.api.post('promos/claim', { promo_id: promoId }));
  }

  // The checkout Vouchers list: live coded promos this customer can still use.
  async available(scope: 'products' | 'box', subtotal = 0, itemCount?: number): Promise<Voucher[]> {
    const params: Record<string, string | number> = { scope, subtotal };
    if (itemCount !== undefined) params['item_count'] = itemCount;
    const res = await firstValueFrom(this.api.get<{ vouchers: Voucher[] }>('promos/available', params));
    return res.data.vouchers;
  }
}
