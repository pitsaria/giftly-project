import { Component, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  IonHeader,
  IonToolbar,
  IonTitle,
  IonButtons,
  IonBackButton,
  IonContent,
  IonRefresher,
  IonRefresherContent,
  IonSegment,
  IonSegmentButton,
  IonLabel,
  IonIcon,
  IonSpinner,
  IonButton,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import { receiptOutline, pricetagOutline, bagHandleOutline, notificationsOffOutline } from 'ionicons/icons';
import { NotificationInboxService, NotificationCategory, NotificationItem } from '../../core/notification-inbox.service';

type CategoryTab = 'all' | NotificationCategory;

interface NotificationGroup {
  label: string;
  items: NotificationItem[];
}

const GROUP_ORDER = ['Today', 'Yesterday', 'This week', 'Earlier'];

const CATEGORY_ICONS: Record<NotificationCategory, string> = {
  order: 'receipt-outline',
  promo: 'pricetag-outline',
  product: 'bag-handle-outline',
};

@Component({
  selector: 'app-notifications',
  templateUrl: 'notifications.page.html',
  styleUrls: ['notifications.page.scss'],
  imports: [
    CommonModule,
    IonHeader,
    IonToolbar,
    IonTitle,
    IonButtons,
    IonBackButton,
    IonContent,
    IonRefresher,
    IonRefresherContent,
    IonSegment,
    IonSegmentButton,
    IonLabel,
    IonIcon,
    IonSpinner,
    IonButton,
  ],
})
export class NotificationsPage {
  private notifSvc = inject(NotificationInboxService);

  readonly tab = signal<CategoryTab>('all');
  readonly items = signal<NotificationItem[]>([]);
  readonly loading = signal(false);
  readonly loadingMore = signal(false);
  readonly hasMore = signal(true);
  private offset = 0;

  readonly hasUnread = computed(() => this.items().some((i) => !i.is_read));

  readonly groups = computed<NotificationGroup[]>(() => {
    const byLabel = new Map<string, NotificationItem[]>();
    for (const item of this.items()) {
      const label = this.groupLabel(item.created_at);
      if (!byLabel.has(label)) byLabel.set(label, []);
      byLabel.get(label)!.push(item);
    }
    return GROUP_ORDER.filter((label) => byLabel.has(label)).map((label) => ({
      label,
      items: byLabel.get(label)!,
    }));
  });

  constructor() {
    addIcons({ receiptOutline, pricetagOutline, bagHandleOutline, notificationsOffOutline });
  }

  async ionViewWillEnter(): Promise<void> {
    await this.reload();
  }

  async ionViewWillLeave(): Promise<void> {
    await this.notifSvc.refreshUnreadCount().catch(() => {});
  }

  async switchTab(value: string | number | undefined): Promise<void> {
    if (!value) return;
    this.tab.set(value as CategoryTab);
    await this.reload();
  }

  async handleRefresh(event: CustomEvent): Promise<void> {
    await this.reload();
    (event.target as HTMLIonRefresherElement).complete();
  }

  async loadMore(): Promise<void> {
    if (this.loadingMore() || !this.hasMore()) return;
    this.loadingMore.set(true);
    try {
      const next = await this.notifSvc.list(this.tab(), this.offset);
      this.items.update((cur) => [...cur, ...next]);
      this.offset += next.length;
      this.hasMore.set(next.length > 0);
    } finally {
      this.loadingMore.set(false);
    }
  }

  async onItemTap(item: NotificationItem): Promise<void> {
    if (item.is_read) return;
    item.is_read = true;
    this.items.update((cur) => [...cur]);
    await this.notifSvc.markRead(item.id).catch(() => {});
  }

  async markAllRead(): Promise<void> {
    if (!this.hasUnread()) return;
    this.items.update((cur) => cur.map((i) => ({ ...i, is_read: true })));
    await this.notifSvc.markAllRead().catch(() => {});
  }

  iconFor(category: NotificationCategory): string {
    return CATEGORY_ICONS[category];
  }

  private groupLabel(iso: string): string {
    const date = new Date(iso.replace(' ', 'T') + 'Z');
    const startOfDay = (d: Date) => new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime();
    const diffDays = Math.floor((startOfDay(new Date()) - startOfDay(date)) / 86400000);
    if (diffDays <= 0) return 'Today';
    if (diffDays === 1) return 'Yesterday';
    if (diffDays < 7) return 'This week';
    return 'Earlier';
  }

  timeAgo(iso: string): string {
    const diffMs = Date.now() - new Date(iso.replace(' ', 'T') + 'Z').getTime();
    const mins = Math.floor(diffMs / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days}d ago`;
    return new Date(iso.replace(' ', 'T') + 'Z').toLocaleDateString();
  }

  private async reload(): Promise<void> {
    this.loading.set(true);
    this.offset = 0;
    this.hasMore.set(true);
    try {
      const first = await this.notifSvc.list(this.tab(), 0);
      this.items.set(first);
      this.offset = first.length;
      this.hasMore.set(first.length > 0);
    } finally {
      this.loading.set(false);
    }
  }
}
