import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import {
  IonHeader,
  IonToolbar,
  IonTitle,
  IonButtons,
  IonBackButton,
  IonContent,
  IonButton,
  IonIcon,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import { giftOutline } from 'ionicons/icons';

interface Shot {
  img: string;
  alt: string;
}

// Company History — split out of the old single About page. Narrative is
// drafted placeholder copy pending real founding details from the team.
@Component({
  selector: 'app-company-history',
  templateUrl: 'company-history.page.html',
  styleUrls: ['company-history.page.scss'],
  imports: [
    CommonModule,
    RouterLink,
    IonHeader,
    IonToolbar,
    IonTitle,
    IonButtons,
    IonBackButton,
    IonContent,
    IonButton,
    IonIcon,
  ],
})
export class CompanyHistoryPage {
  readonly shots: Shot[] = [
    { img: 'assets/giftly/storefront.jpeg', alt: 'Our storefront' },
    { img: 'assets/giftly/shelves.jpg', alt: 'Curated shelves' },
    { img: 'assets/giftly/wrap.jpeg', alt: 'The wrapping bench' },
    { img: 'assets/giftly/packing.jpg', alt: 'Packing day' },
  ];

  constructor() {
    addIcons({ giftOutline });
  }
}
