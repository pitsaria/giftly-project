import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  IonHeader,
  IonToolbar,
  IonTitle,
  IonButtons,
  IonBackButton,
  IonContent,
  IonIcon,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import { sparklesOutline, heartOutline, rocketOutline, giftOutline, cubeOutline, bicycleOutline } from 'ionicons/icons';

interface Card {
  icon: string;
  title: string;
  text: string;
}

// Services — split out of the old single About page's "What we care about"
// section, reframed as service commitments plus a concrete offerings list.
@Component({
  selector: 'app-services',
  templateUrl: 'services.page.html',
  styleUrls: ['services.page.scss'],
  imports: [CommonModule, IonHeader, IonToolbar, IonTitle, IonButtons, IonBackButton, IonContent, IonIcon],
})
export class ServicesPage {
  readonly offerings: Card[] = [
    { icon: 'cube-outline', title: 'Curated gift boxes', text: 'Ready-made boxes for birthdays, anniversaries and every occasion in between.' },
    { icon: 'gift-outline', title: 'Build-a-Box', text: "Pick your own box and fill it item by item, exactly to the recipient's taste." },
    { icon: 'bicycle-outline', title: 'Tracked delivery', text: 'Every order is tracked from checkout to doorstep, delivered to you or straight to the recipient.' },
  ];

  readonly values: Card[] = [
    { icon: 'sparkles-outline', title: 'Thoughtful curation', text: 'Every item is chosen by hand from makers we actually love. Nothing goes in a box just to fill space.' },
    { icon: 'heart-outline', title: 'Handmade with care', text: 'We wrap, tie and hand-write each box in-house — the way you would for someone you love.' },
    { icon: 'rocket-outline', title: 'Delivered with respect', text: "We treat your surprise like it's ours: tracked, protected, and on time for the moment that matters." },
  ];

  constructor() {
    addIcons({ sparklesOutline, heartOutline, rocketOutline, giftOutline, cubeOutline, bicycleOutline });
  }
}
