import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { ApiService } from './api.service';
import { AuthService } from './auth.service';
import { RecipientService } from './recipient.service';

// One card in Home's "Coming up" strip.
export interface Occasion {
  key: string;
  name: string;
  emoji: string;
  tagline: string;
  date: Date;
  daysAway: number;
  // Last day to order and still have time to prepare it — null once passed.
  orderBy: Date | null;
  personal: boolean;
  link: string;
  queryParams?: Record<string, string>;
}

interface ApiHoliday {
  date: string;
  name: string;
  local_name: string;
}

interface Def {
  key: string;
  name: string;
  emoji: string;
  tagline: string;
}

// The Contact FAQ promises boxes need at least 3 days to prepare.
const PREP_DAYS = 3;
const MS_DAY = 86_400_000;
// Personal dates only surface once they're close enough to act on.
const PERSONAL_WINDOW_DAYS = 45;

// Public holidays (from Nager.Date, matched on its English name) that people
// actually give gifts for — the API also returns Rizal Day, Day of Valor etc.
const GIFT_HOLIDAYS: Record<string, Omit<Def, 'key' | 'name'>> = {
  'christmas day': { emoji: '🎄', tagline: "The season's biggest gift day" },
  "new year's day": { emoji: '🎆', tagline: 'Kick off the year with something sweet' },
  'chinese new year': { emoji: '🧧', tagline: 'Red envelopes & lucky treats' },
};

// Gifting occasions that aren't public holidays, so Nager.Date doesn't list
// them — computed from their date rules instead.
const OBSERVANCES: (Def & { on: (year: number) => Date })[] = [
  { key: 'valentines', name: "Valentine's Day", emoji: '💝', tagline: 'Make their heart skip a beat', on: (y) => new Date(y, 1, 14) },
  { key: 'mothers-day', name: "Mother's Day", emoji: '🌷', tagline: 'Spoil the woman who spoils you', on: (y) => nthWeekday(y, 4, 0, 2) },
  { key: 'fathers-day', name: "Father's Day", emoji: '👔', tagline: 'Something Dad will actually use', on: (y) => nthWeekday(y, 5, 0, 3) },
];

const PERSONAL_EMOJI: Record<string, string> = { birthday: '🎂', anniversary: '💞', other: '✨' };

function nthWeekday(year: number, month: number, weekday: number, n: number): Date {
  const first = new Date(year, month, 1).getDay();
  return new Date(year, month, 1 + ((weekday - first + 7) % 7) + (n - 1) * 7);
}

function startOfToday(): Date {
  const now = new Date();
  return new Date(now.getFullYear(), now.getMonth(), now.getDate());
}

function parseIsoDate(iso: string): Date {
  const [y, m, d] = iso.slice(0, 10).split('-').map(Number);
  return new Date(y, m - 1, d);
}

@Injectable({ providedIn: 'root' })
export class OccasionService {
  private api = inject(ApiService);
  private auth = inject(AuthService);
  private recipientSvc = inject(RecipientService);

  async upcoming(limit = 5): Promise<Occasion[]> {
    const today = startOfToday();
    const [holidays, personal] = await Promise.all([this.holidayOccasions(today), this.personalOccasions(today)]);
    return [...holidays, ...this.observanceOccasions(today), ...personal]
      .filter((o) => o.daysAway >= 0)
      .sort((a, b) => a.date.getTime() - b.date.getTime())
      .slice(0, limit);
  }

  private make(def: Def, date: Date, today: Date, extra: Partial<Occasion> = {}): Occasion {
    const daysAway = Math.round((date.getTime() - today.getTime()) / MS_DAY);
    const orderBy = new Date(date.getTime() - PREP_DAYS * MS_DAY);
    return {
      ...def,
      date,
      daysAway,
      orderBy: orderBy.getTime() >= today.getTime() ? orderBy : null,
      personal: false,
      link: '/tabs/shop',
      ...extra,
    };
  }

  // Official dates from the backend's /holidays route (Nager.Date). If the
  // backend or the upstream API is down, the strip just shows the rest.
  private async holidayOccasions(today: Date): Promise<Occasion[]> {
    try {
      const res = await firstValueFrom(this.api.get<{ holidays: ApiHoliday[] }>('holidays'));
      const out: Occasion[] = [];
      for (const h of res.data.holidays) {
        const meta = GIFT_HOLIDAYS[h.name.toLowerCase()];
        if (!meta) continue;
        const date = parseIsoDate(h.date);
        out.push(this.make({ key: `${h.name}-${h.date}`, name: h.name, ...meta }, date, today));
      }
      return out;
    } catch {
      return [];
    }
  }

  private observanceOccasions(today: Date): Occasion[] {
    const out: Occasion[] = [];
    for (const year of [today.getFullYear(), today.getFullYear() + 1]) {
      for (const o of OBSERVANCES) {
        out.push(this.make({ key: `${o.key}-${year}`, name: o.name, emoji: o.emoji, tagline: o.tagline }, o.on(year), today));
      }
    }
    return out;
  }

  // Saved recipients' birthdays/anniversaries, for logged-in users only.
  private async personalOccasions(today: Date): Promise<Occasion[]> {
    if (!this.auth.isLoggedIn()) return [];
    try {
      const { upcoming } = await this.recipientSvc.getAll();
      return upcoming
        .filter((u) => u.days_until <= PERSONAL_WINDOW_DAYS)
        .map((u) => {
          const date = new Date(today.getTime() + u.days_until * MS_DAY);
          const what = (u.label || u.occasion_type).trim();
          return this.make(
            {
              key: `personal-${u.occasion_id}`,
              name: `${u.recipient_name}'s ${what.toLowerCase()}`,
              emoji: PERSONAL_EMOJI[u.occasion_type] ?? '✨',
              tagline: u.relationship,
            },
            date,
            today,
            { personal: true, link: '/tabs/profile', queryParams: { tab: 'relations' } }
          );
        });
    } catch {
      return [];
    }
  }
}
