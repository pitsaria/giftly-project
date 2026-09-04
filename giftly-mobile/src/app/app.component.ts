import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonApp, IonRouterOutlet } from '@ionic/angular';
import { Capacitor } from '@capacitor/core';
import { StatusBar, Style } from '@capacitor/status-bar';
import { AuthService } from './core/auth.service';

const MIN_SPLASH_MS = 1100;
const FADE_MS = 350;

@Component({
  selector: 'app-root',
  templateUrl: 'app.component.html',
  styleUrls: ['app.component.scss'],
  imports: [CommonModule, IonApp, IonRouterOutlet],
})
export class AppComponent {
  private auth = inject(AuthService);

  readonly showSplash = signal(true);
  readonly splashHiding = signal(false);

  constructor() {
    this.applyNativeChrome();
    this.init();
  }

  // Dark icons/text on the app's light toolbar; no status-bar overlay so
  // content doesn't slide under the clock. No-op on the web build.
  private applyNativeChrome(): void {
    if (!Capacitor.isNativePlatform()) return;
    StatusBar.setStyle({ style: Style.Dark }).catch(() => {});
    StatusBar.setBackgroundColor({ color: '#fcfcfc' }).catch(() => {});
  }

  private async init(): Promise<void> {
    // Restore a saved login (Bearer token) on cold start, mirroring how the
    // website picks the session cookie back up automatically.
    const restore = this.auth.restore();
    const minDelay = new Promise((resolve) => setTimeout(resolve, MIN_SPLASH_MS));
    await Promise.all([restore, minDelay]);

    this.splashHiding.set(true);
    setTimeout(() => this.showSplash.set(false), FADE_MS);
  }
}
