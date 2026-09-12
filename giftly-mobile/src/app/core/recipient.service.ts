import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { ApiService } from './api.service';
import { Recipient, UpcomingOccasion } from './models';

export interface NewRecipient {
  name: string;
  relationship?: string;
  phone?: string;
  email?: string;
  house_no?: string;
  street?: string;
  city_line?: string;
  zip?: string;
  notes?: string;
  // Optional first occasion, added alongside the recipient.
  occasion_type?: 'birthday' | 'anniversary' | 'other';
  occasion_label?: string;
  occasion_date?: string;
}

export interface NewOccasion {
  recipient_id: number;
  occasion_type: 'birthday' | 'anniversary' | 'other';
  label?: string;
  occasion_date: string;
}

// "My Relations" — saved people to gift + their occasion reminders. Mirrors
// profile_relations.php / recipients_lib.php.
@Injectable({ providedIn: 'root' })
export class RecipientService {
  private api = inject(ApiService);

  async getAll(): Promise<{ recipients: Recipient[]; upcoming: UpcomingOccasion[] }> {
    const res = await firstValueFrom(
      this.api.get<{ recipients: Recipient[]; upcoming: UpcomingOccasion[] }>('recipients')
    );
    return res.data;
  }

  async create(data: NewRecipient): Promise<number> {
    const res = await firstValueFrom(this.api.post<{ id: number }>('recipients', data));
    return res.data.id;
  }

  async update(id: number, data: NewRecipient): Promise<void> {
    await firstValueFrom(this.api.put('recipients/single', data, { id }));
  }

  async remove(id: number): Promise<void> {
    await firstValueFrom(this.api.delete('recipients/single', { id }));
  }

  async addOccasion(data: NewOccasion): Promise<void> {
    await firstValueFrom(this.api.post('recipients/occasions', data));
  }

  async removeOccasion(id: number): Promise<void> {
    await firstValueFrom(this.api.delete('recipients/occasions', { id }));
  }
}
