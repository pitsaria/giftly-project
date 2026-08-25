import { Component, inject } from '@angular/core';
import { IonApp, IonRouterOutlet } from '@ionic/angular';
import { AuthService } from './core/auth.service';

@Component({
  selector: 'app-root',
  templateUrl: 'app.component.html',
  imports: [IonApp, IonRouterOutlet],
})
export class AppComponent {
  private auth = inject(AuthService);

  constructor() {
    // Restore a saved login (Bearer token) on cold start, mirroring how the
    // website picks the session cookie back up automatically.
    this.auth.restore();
  }
}
