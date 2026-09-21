import { Component, effect, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth.service';
import { Occasion, OccasionService } from '../../core/occasion.service';

// Home's "Coming up" strip: the next few gift-worthy dates — official holidays
// (Nager.Date via our backend), gifting observances like Mother's Day, and the
// user's own saved birthdays — each with a countdown and an order-by date.
@Component({
  selector: 'app-occasions-strip',
  templateUrl: 'occasions-strip.component.html',
  styleUrls: ['occasions-strip.component.scss'],
  imports: [CommonModule, RouterLink],
})
export class OccasionsStripComponent {
  private occasionSvc = inject(OccasionService);
  private auth = inject(AuthService);

  readonly occasions = signal<Occasion[]>([]);

  constructor() {
    // Reloads on login/logout so saved-recipient dates appear and disappear.
    effect(() => {
      this.auth.isLoggedIn();
      void this.load();
    });
  }

  countdown(o: Occasion): string {
    if (o.daysAway === 0) return 'Today';
    if (o.daysAway === 1) return 'Tomorrow';
    return `in ${o.daysAway} days`;
  }

  private async load(): Promise<void> {
    this.occasions.set(await this.occasionSvc.upcoming());
  }
}
