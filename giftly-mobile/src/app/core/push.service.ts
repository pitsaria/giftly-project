import { Injectable, inject } from '@angular/core';
import { Router } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { Capacitor } from '@capacitor/core';
import { PushNotifications } from '@capacitor/push-notifications';
import { ApiService } from './api.service';

// Server-sent push (FCM) — for events that happen while the app may not even
// be open, like an admin marking an order shipped/delivered. Distinct from
// NotificationService's local reminders, which are scheduled entirely
// on-device from data the app already has.
//
// Every call is try/caught: push is a nicety layered on top of the
// status-change email the backend already sends, and a missing permission,
// an unsupported platform, or a flaky registration call must never break
// the login/logout flow it's attached to.
@Injectable({ providedIn: 'root' })
export class PushService {
  private api = inject(ApiService);
  private router = inject(Router);

  private listenersBound = false;
  private lastToken: string | null = null;

  // Call once the user is known to be logged in (app cold start after a
  // restored session, and right after a fresh login) — registration needs
  // the Bearer token to associate the device with this account.
  async init(): Promise<void> {
    if (!Capacitor.isNativePlatform()) return;
    try {
      const current = await PushNotifications.checkPermissions();
      let granted = current.receive === 'granted';
      if (!granted && current.receive !== 'denied') {
        const requested = await PushNotifications.requestPermissions();
        granted = requested.receive === 'granted';
      }
      if (!granted) return;

      this.bindListeners();
      await PushNotifications.register();
    } catch {
      // No-op.
    }
  }

  // Call right before clearing the local session so this device stops
  // receiving pushes meant for the account that just logged out.
  async teardown(): Promise<void> {
    if (!Capacitor.isNativePlatform()) return;
    try {
      if (this.lastToken) {
        await firstValueFrom(this.api.post('push/unregister', { token: this.lastToken }));
      }
      await PushNotifications.unregister();
    } catch {
      // No-op.
    } finally {
      this.lastToken = null;
    }
  }

  private bindListeners(): void {
    if (this.listenersBound) return;
    this.listenersBound = true;

    PushNotifications.addListener('registration', (token) => {
      this.lastToken = token.value;
      void this.registerToken(token.value);
    });

    PushNotifications.addListener('registrationError', () => {
      // No-op — push is a nicety.
    });

    // Tapping the system notification (app backgrounded/closed) lands on the
    // Orders tab — there's no deep link straight into the order-detail sheet,
    // but this gets the customer to the right list in one tap.
    PushNotifications.addListener('pushNotificationActionPerformed', (action) => {
      if (action.notification.data?.type === 'order_status') {
        void this.router.navigate(['/tabs/profile'], { queryParams: { tab: 'orders' } });
      }
    });
  }

  private async registerToken(token: string): Promise<void> {
    try {
      await firstValueFrom(this.api.post('push/register', { token, platform: 'android' }));
    } catch {
      // No-op — will retry next app open/login.
    }
  }
}
