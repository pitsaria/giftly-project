import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { ApiService } from './api.service';

export interface ContactMessage {
  name: string;
  email: string;
  subject: string;
  message: string;
}

// Wraps the api/index.php contact route. Mirrors contact.php's form submit.
@Injectable({ providedIn: 'root' })
export class ContactService {
  private api = inject(ApiService);

  async send(message: ContactMessage): Promise<void> {
    await firstValueFrom(this.api.post('contact', message));
  }
}
