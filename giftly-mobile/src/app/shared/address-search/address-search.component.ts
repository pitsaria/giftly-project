import { Component, EventEmitter, Output, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { IonInput, IonIcon, IonSpinner } from '@ionic/angular';
import { addIcons } from 'ionicons';
import { locationOutline } from 'ionicons/icons';
import { Subject, debounceTime, switchMap, catchError, of } from 'rxjs';

export interface AddressParts {
  street: string;
  barangay: string;
  city: string;
  province: string;
  region: string;
  zip: string;
}

interface PhotonProps {
  name?: string;
  street?: string;
  housenumber?: string;
  locality?: string;
  suburb?: string;
  quarter?: string;
  neighbourhood?: string;
  district?: string;
  city?: string;
  town?: string;
  municipality?: string;
  county?: string;
  state?: string;
  postcode?: string;
  countrycode?: string;
  type?: string;
}

// Photon (photon.komoot.io) address autocomplete — free OpenStreetMap geocoder,
// no key, CORS-open. Mirrors giftly_project/maps_address.js. On pick it emits
// the parsed address parts; the parent fills its own fields.
@Component({
  selector: 'app-address-search',
  templateUrl: 'address-search.component.html',
  styleUrls: ['address-search.component.scss'],
  imports: [CommonModule, FormsModule, IonInput, IonIcon, IonSpinner],
})
export class AddressSearchComponent {
  @Output() picked = new EventEmitter<AddressParts>();

  private http = inject(HttpClient);

  query = '';
  readonly results = signal<PhotonProps[]>([]);
  readonly loading = signal(false);
  readonly open = signal(false);

  private readonly PH_BBOX = '116.7,4.5,127.0,21.2';
  private search$ = new Subject<string>();

  constructor() {
    addIcons({ locationOutline });
    this.search$
      .pipe(
        debounceTime(300),
        switchMap((q) => {
          if (q.trim().length < 3) {
            this.results.set([]);
            this.open.set(false);
            return of(null);
          }
          this.loading.set(true);
          const url =
            `https://photon.komoot.io/api/?limit=7&lang=en&bbox=${this.PH_BBOX}` +
            `&q=${encodeURIComponent(q.trim())}`;
          return this.http.get<{ features?: { properties: PhotonProps }[] }>(url).pipe(
            catchError(() => of(null))
          );
        })
      )
      .subscribe((res) => {
        this.loading.set(false);
        if (!res) return;
        const feats = (res.features ?? [])
          .map((f) => f.properties)
          .filter((p) => p.countrycode === 'PH');
        this.results.set(feats);
        this.open.set(true);
      });
  }

  onInput(): void {
    this.search$.next(this.query);
  }

  mainLine(p: PhotonProps): string {
    const parts = [p.name, p.street].filter((v, i, a) => v && a.indexOf(v) === i);
    return parts.join(', ') || p.street || p.name || p.locality || 'Address';
  }

  subLine(p: PhotonProps): string {
    return [p.locality, p.city || p.county, p.state, p.postcode].filter(Boolean).join(', ');
  }

  pick(p: PhotonProps): void {
    const parsed = this.parse(p);
    this.query = [parsed.street, parsed.barangay, parsed.city].filter(Boolean).join(', ');
    this.open.set(false);
    this.picked.emit(parsed);
  }

  private parse(pr: PhotonProps): AddressParts {
    const street =
      [pr.housenumber, pr.street].filter(Boolean).join(' ') ||
      (pr.type === 'street' ? pr.name ?? '' : '') ||
      pr.name ||
      '';
    const barangay = pr.locality || pr.suburb || pr.quarter || pr.neighbourhood || '';
    let city = pr.city || pr.town || pr.municipality || '';
    const county = pr.county || '';
    const region = pr.state || '';
    const province = /district/i.test(county) ? region : county || region;
    const zip = pr.postcode || '';
    if (!city && barangay && county && !/district/i.test(county)) city = county;
    return { street, barangay, city, province, region, zip };
  }
}
