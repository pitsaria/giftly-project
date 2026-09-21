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

interface Chapter {
  title: string;
  text: string;
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
  readonly chapters: Chapter[] = [
    {
      title: 'The kitchen table',
      text: 'It started with a glue gun, a roll of ribbon, and a handful of orders from friends who kept asking where we got our wrapping done.',
    },
    {
      title: 'A studio of our own',
      text: 'Word spread faster than we expected. The kitchen table became a small studio — pictured below — where every box is still cut, folded and tied by hand.',
    },
    {
      title: 'Giftly, the app',
      text: 'As orders outgrew paper and pen, we built the Giftly app so customers could build their own box, follow it from checkout to doorstep, and never forget an occasion again.',
    },
    {
      title: 'Still small, same promise',
      text: "We're still a small team, and the conviction hasn't changed: the way a gift arrives is part of the gift.",
    },
  ];

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
