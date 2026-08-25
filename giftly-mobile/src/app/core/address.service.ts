import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { ApiService } from './api.service';
import { Address } from './models';

export interface NewAddress {
  label: string;
  address: string;
  city: string;
  province: string;
  zip: string;
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

  async remove(id: number): Promise<void> {
    await firstValueFrom(this.api.delete('addresses/single', { id }));
  }
}
