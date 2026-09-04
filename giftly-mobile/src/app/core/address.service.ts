import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { ApiService } from './api.service';
import { Address } from './models';

export interface NewAddress {
  // Either a preset label or a free-text "Other" label.
  label_choice?: 'Home' | 'Office' | 'Other';
  label_other?: string;
  house_no?: string;
  address: string;
  barangay?: string;
  city: string;
  province: string;
  zip: string;
  make_default?: boolean;
  // Legacy flat form still accepted by the API.
  label?: string;
}

@Injectable({ providedIn: 'root' })
export class AddressService {
  private api = inject(ApiService);

  async getAll(): Promise<Address[]> {
    const res = await firstValueFrom(this.api.get<{ addresses: Address[] }>('addresses'));
    return res.data.addresses;
  }

  async create(address: NewAddress): Promise<number> {
    const res = await firstValueFrom(this.api.post<{ id: number }>('addresses', address));
    return res.data.id;
  }

  async setDefault(id: number): Promise<void> {
    await firstValueFrom(this.api.put('addresses/default', {}, { id }));
  }

  async remove(id: number): Promise<void> {
    await firstValueFrom(this.api.delete('addresses/single', { id }));
  }
}
