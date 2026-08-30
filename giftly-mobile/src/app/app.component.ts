import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonApp, IonRouterOutlet } from '@ionic/angular';
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
    this.init();
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
