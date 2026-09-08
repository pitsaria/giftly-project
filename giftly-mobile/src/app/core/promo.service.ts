import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { ApiService } from './api.service';
import { HomePromo, PromoEval } from './models';

export interface PromoEvaluateOptions {
  code?: string;
  selectedIds?: number[];
  boxId?: number;
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
    const res = await firstValueFrom(this.api.post<PromoEval>('promo/evaluate', body));
    return res.data;
  }

  async activePromos(): Promise<HomePromo[]> {
    const res = await firstValueFrom(this.api.get<{ promos: HomePromo[] }>('promos/active'));
    return res.data.promos;
  }
}
