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
import {
  bagHandleOutline,
  cubeOutline,
  cardOutline,
  receiptOutline,
  peopleOutline,
  notificationsOutline,
} from 'ionicons/icons';

interface Feature {
  icon: string;
  title: string;
  text: string;
}

// About the App — new page split out of the old single About page; covers
// what the mobile app itself lets you do, as opposed to Company History /
// Services, which are about the brand.
@Component({
  selector: 'app-about-app',
  templateUrl: 'about-app.page.html',
  styleUrls: ['about-app.page.scss'],
  imports: [CommonModule, IonHeader, IonToolbar, IonTitle, IonButtons, IonBackButton, IonContent, IonIcon],
})
export class AboutAppPage {
  readonly features: Feature[] = [
    { icon: 'bag-handle-outline', title: 'Browse & shop', text: 'Explore curated products by category, save favourites, and check out in a few taps.' },
    { icon: 'cube-outline', title: 'Build-a-Box', text: "Assemble your own gift box item by item, exactly the way you'd wrap it yourself." },
    { icon: 'card-outline', title: 'Easy checkout', text: 'Pay your way and choose delivery to yourself or straight to the recipient, gift message included.' },
    { icon: 'receipt-outline', title: 'Order tracking', text: 'Follow every order from confirmation to doorstep, right from the Orders tab.' },
    { icon: 'people-outline', title: 'Saved recipients', text: 'Save the people you gift most, with their birthdays and occasions, so a repeat gift is one tap away.' },
    { icon: 'notifications-outline', title: 'Reminders that help', text: "Get a nudge before an occasion, and a push notification the moment your order's status changes." },
  ];

  constructor() {
    addIcons({ bagHandleOutline, cubeOutline, cardOutline, receiptOutline, peopleOutline, notificationsOutline });
  }
}
