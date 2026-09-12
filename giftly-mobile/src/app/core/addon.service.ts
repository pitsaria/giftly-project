import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { ApiService } from './api.service';
import { Addon } from './models';

// Gift wrapping & checkout add-ons. Mirrors addons_lib.php's checkout picker
// (checkout_selected.php / box_checkout.php on the website).
@Injectable({ providedIn: 'root' })
export class AddonService {
  private api = inject(ApiService);

  async getAll(): Promise<Addon[]> {
    const res = await firstValueFrom(this.api.get<{ addons: Addon[] }>('addons'));
    return res.data.addons;
  }
}
