import { Injectable } from '@angular/core';
import { Haptics, ImpactStyle, NotificationType } from '@capacitor/haptics';

// Thin wrapper around @capacitor/haptics — every call is try/caught since the
// web fallback isn't guaranteed on every browser and a missing vibration
// should never break the flow it's attached to.
@Injectable({ providedIn: 'root' })
export class HapticsService {
  async light(): Promise<void> {
    try {
      await Haptics.impact({ style: ImpactStyle.Light });
    } catch {
      // No-op — haptics are a nicety, not a requirement.
    }
  }

  async medium(): Promise<void> {
    try {
      await Haptics.impact({ style: ImpactStyle.Medium });
    } catch {
      // No-op.
    }
  }

  async success(): Promise<void> {
    try {
      await Haptics.notification({ type: NotificationType.Success });
    } catch {
      // No-op.
    }
  }

  async error(): Promise<void> {
    try {
      await Haptics.notification({ type: NotificationType.Error });
    } catch {
      // No-op.
    }
  }
}
