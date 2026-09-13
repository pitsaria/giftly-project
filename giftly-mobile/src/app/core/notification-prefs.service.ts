import { Injectable, signal } from '@angular/core';
import { Preferences } from '@capacitor/preferences';

const KEY = 'giftly_notifications_enabled';

// Single Settings switch for every Giftly notification — PushService (order
// status pushes) and NotificationService (on-device cart/delivery/occasion
// reminders) both check this before registering or scheduling anything, and
// both get torn down immediately when it's switched off. Defaults to on so
// existing installs keep getting notified until someone opts out.
@Injectable({ providedIn: 'root' })
export class NotificationPrefsService {
  readonly enabled = signal(true);
  private loaded = false;

  async load(): Promise<boolean> {
    if (!this.loaded) {
      try {
        const { value } = await Preferences.get({ key: KEY });
        if (value !== null) this.enabled.set(value === 'true');
      } catch {
        // No-op — keep the default (on).
      }
      this.loaded = true;
    }
    return this.enabled();
  }

  async set(value: boolean): Promise<void> {
    this.enabled.set(value);
    this.loaded = true;
    try {
      await Preferences.set({ key: KEY, value: String(value) });
    } catch {
      // No-op.
    }
  }
}
