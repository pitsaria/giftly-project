import { Injectable } from '@angular/core';
import { LocalNotifications } from '@capacitor/local-notifications';
import { UpcomingOccasion } from './models';

// Fixed id bands so scheduling the "same" reminder twice (e.g. adding a
// second item to the cart, or re-syncing occasions) overwrites the pending
// notification instead of stacking duplicates.
const CART_REMINDER_ID = 900001;
const DELIVERY_REMINDER_BASE = 910000;
const OCCASION_REMINDER_BASE = 920000;

// How long an item sits untouched in the cart before we nudge the user.
const CART_REMINDER_DELAY_MS = 3 * 60 * 60 * 1000;
// How many days ahead of an occasion to give the heads-up.
const OCCASION_LEAD_DAYS = 2;
const OCCASION_REMINDER_HOUR = 9;

// Thin wrapper around @capacitor/local-notifications — every call is
// try/caught since notifications are a nicety and the plugin's web fallback
// doesn't support exact scheduling, so this must never break the flow it's
// attached to (adding to cart, placing an order, syncing recipients).
@Injectable({ providedIn: 'root' })
export class NotificationService {
  private permissionChecked = false;
  private permissionGranted = false;

  private async ensurePermission(): Promise<boolean> {
    if (this.permissionChecked) return this.permissionGranted;
    try {
      const current = await LocalNotifications.checkPermissions();
      let granted = current.display === 'granted';
      if (!granted && current.display !== 'denied') {
        const requested = await LocalNotifications.requestPermissions();
        granted = requested.display === 'granted';
      }
      this.permissionGranted = granted;
    } catch {
      this.permissionGranted = false;
    } finally {
      this.permissionChecked = true;
    }
    return this.permissionGranted;
  }

  // === Cart abandonment ===

  async scheduleCartReminder(itemCount: number): Promise<void> {
    if (itemCount <= 0 || !(await this.ensurePermission())) return;
    try {
      await LocalNotifications.schedule({
        notifications: [
          {
            id: CART_REMINDER_ID,
            title: 'You left something in your cart 🎁',
            body:
              itemCount === 1
                ? "There's an item waiting in your cart — complete your order before it sells out."
                : `There are ${itemCount} items waiting in your cart — complete your order before they sell out.`,
            schedule: { at: new Date(Date.now() + CART_REMINDER_DELAY_MS), allowWhileIdle: true },
          },
        ],
      });
    } catch {
      // No-op — reminder is a nicety.
    }
  }

  async cancelCartReminder(): Promise<void> {
    try {
      await LocalNotifications.cancel({ notifications: [{ id: CART_REMINDER_ID }] });
    } catch {
      // No-op.
    }
  }

  // === Order delivery reminder ===

  async scheduleDeliveryReminder(
    orderId: number,
    deliveryDate: string,
    deliveryTime: string,
    recipientName?: string
  ): Promise<void> {
    const at = this.parseDeliveryDateTime(deliveryDate, deliveryTime);
    if (!at || at.getTime() <= Date.now() || !(await this.ensurePermission())) return;
    try {
      await LocalNotifications.schedule({
        notifications: [
          {
            id: DELIVERY_REMINDER_BASE + orderId,
            title: 'Your gift is arriving today! 🎉',
            body: recipientName
              ? `Order #GLY-${orderId} for ${recipientName} is scheduled for delivery today.`
              : `Order #GLY-${orderId} is scheduled for delivery today.`,
            schedule: { at, allowWhileIdle: true },
          },
        ],
      });
    } catch {
      // No-op.
    }
  }

  async cancelDeliveryReminder(orderId: number): Promise<void> {
    try {
      await LocalNotifications.cancel({ notifications: [{ id: DELIVERY_REMINDER_BASE + orderId }] });
    } catch {
      // No-op.
    }
  }

  // Combines the checkout page's separate date ('YYYY-MM-DD') and time
  // ('HH:mm' or 'HH:mm:ss') fields into one Date, or null if unparsable.
  private parseDeliveryDateTime(date: string, time: string): Date | null {
    const parts = `${date}T${time.length === 5 ? `${time}:00` : time}`;
    const d = new Date(parts);
    return isNaN(d.getTime()) ? null : d;
  }

  // === Occasion reminders (birthdays, anniversaries, etc.) ===

  // Re-syncs every scheduled occasion reminder against the current list —
  // call whenever recipients/occasions are (re)loaded. Occasions no longer
  // present (removed, or since passed and not recurring) are left to expire
  // naturally; there's no "list all pending ids" API to diff against.
  async scheduleOccasionReminders(occasions: UpcomingOccasion[]): Promise<void> {
    if (!occasions.length || !(await this.ensurePermission())) return;
    const notifications = occasions
      .map((o) => this.buildOccasionNotification(o))
      .filter((n): n is NonNullable<typeof n> => n !== null);
    if (!notifications.length) return;
    try {
      await LocalNotifications.schedule({ notifications });
    } catch {
      // No-op.
    }
  }

  async cancelOccasionReminder(occasionId: number): Promise<void> {
    try {
      await LocalNotifications.cancel({ notifications: [{ id: OCCASION_REMINDER_BASE + occasionId }] });
    } catch {
      // No-op.
    }
  }

  private buildOccasionNotification(o: UpcomingOccasion) {
    const target = new Date();
    target.setHours(OCCASION_REMINDER_HOUR, 0, 0, 0);
    target.setDate(target.getDate() + Math.max(0, o.days_until - OCCASION_LEAD_DAYS));
    // The lead time can land in the past relative to "now" (e.g. the
    // occasion is today or tomorrow) — fire almost immediately instead of
    // silently dropping the reminder.
    const at = target.getTime() > Date.now() ? target : new Date(Date.now() + 30_000);

    const kind = o.occasion_type === 'other' ? o.label || 'occasion' : o.occasion_type;
    return {
      id: OCCASION_REMINDER_BASE + o.occasion_id,
      title: `Upcoming ${kind} 🎂`,
      body:
        o.days_until <= 1
          ? `${o.recipient_name}'s ${kind} is ${o.days_until === 0 ? 'today' : 'tomorrow'} — send a gift before it's too late!`
          : `${o.recipient_name}'s ${kind} is coming up in ${o.days_until} days — plan a gift now.`,
      schedule: { at, allowWhileIdle: true },
    };
  }
}
