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
import { giftOutline, sparklesOutline, heartOutline, rocketOutline } from 'ionicons/icons';

interface Owner {
  name: string;
  role: string;
  bio: string;
}

interface Value {
  icon: string;
  title: string;
  text: string;
}

// Static port of giftly_project/about.php.
@Component({
  selector: 'app-about',
  templateUrl: 'about.page.html',
  styleUrls: ['about.page.scss'],
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
export class AboutPage {
  readonly owners: Owner[] = [
    { name: 'Peatzie Cosino', role: 'Founder & CEO', bio: 'Started Giftly from a kitchen table with a glue gun and a lot of ribbon.' },
    { name: 'Angela Castillo', role: 'Head of Design', bio: 'Obsesses over paper weight, palette, and the perfect bow.' },
    { name: 'Feliciti Gacilla', role: 'Operations & Logistics', bio: 'Makes sure every box arrives on time and in one beautiful piece.' },
    { name: 'Gabriel Edpao', role: 'Head of Curation', bio: 'Hunts down the small-batch makers behind our favourite finds.' },
    { name: 'Rachelle Dilig', role: 'Customer Happiness', bio: 'The voice on the other end of every message — and every thank-you note.' },
  ];

  readonly values: Value[] = [
    { icon: 'sparkles-outline', title: 'Thoughtful curation', text: 'Every item is chosen by hand from makers we actually love. Nothing goes in a box just to fill space.' },
    { icon: 'heart-outline', title: 'Handmade with care', text: 'We wrap, tie and hand-write each box in-house — the way you would for someone you love.' },
    { icon: 'rocket-outline', title: 'Delivered with respect', text: "We treat your surprise like it's ours: tracked, protected, and on time for the moment that matters." },
  ];

  constructor() {
    addIcons({ giftOutline, sparklesOutline, heartOutline, rocketOutline });
  }

  initials(name: string): string {
    const parts = name.trim().split(/\s+/);
    return (parts[0][0] + (parts[1]?.[0] ?? '')).toUpperCase();
  }
}
