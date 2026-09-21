import { Injectable, inject, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { ApiService } from './api.service';

// Server-side notification history (order status / promo / product) — not
// to be confused with NotificationService, which only schedules local
// on-device reminders. Named distinctly to avoid colliding with it.
export type NotificationCategory = 'order' | 'promo' | 'product';

export interface NotificationItem {
  id: number;
  category: NotificationCategory;
  title: string;
  body: string;
  data: Record<string, unknown> | null;
  is_read: boolean;
  read_at: string | null;
  created_at: string;
}

const PAGE_SIZE = 20;

@Injectable({ providedIn: 'root' })
export class NotificationInboxService {
  private api = inject(ApiService);

  // Shared app-wide so the top bar's bell badge reflects the true unread
  // count without every page having to fetch it itself.
  readonly unreadCount = signal(0);

  async refreshUnreadCount(): Promise<void> {
    const res = await firstValueFrom(this.api.get<{ count: number }>('notifications/unread-count'));
    this.unreadCount.set(res.data.count);
  }

  async list(category: NotificationCategory | 'all', offset = 0): Promise<NotificationItem[]> {
    const params: Record<string, string | number> = { limit: PAGE_SIZE, offset };
    if (category !== 'all') params['category'] = category;
    const res = await firstValueFrom(this.api.get<{ notifications: NotificationItem[] }>('notifications', params));
    return res.data.notifications;
  }

  async markRead(id: number): Promise<void> {
    await firstValueFrom(this.api.put('notifications/read', {}, { id }));
    this.unreadCount.set(Math.max(0, this.unreadCount() - 1));
  }

  async markAllRead(): Promise<void> {
    await firstValueFrom(this.api.post('notifications/read-all', {}));
    this.unreadCount.set(0);
  }
}
