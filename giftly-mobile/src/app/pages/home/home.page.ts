import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import {
  IonContent,
  IonHeader,
  IonToolbar,
  IonTitle,
  IonButton,
  IonIcon,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import { giftOutline, arrowForwardOutline } from 'ionicons/icons';

interface HeroSlide {
  title: string;
  highlight: string;
  subtitle: string;
  image: string;
  gradient: string;
}

@Component({
  selector: 'app-home',
  templateUrl: 'home.page.html',
  styleUrls: ['home.page.scss'],
  imports: [CommonModule, IonContent, IonHeader, IonToolbar, IonTitle, IonButton, IonIcon],
})
export class HomePage {
  // Mirrors the 3 hero slides in index.php's carousel.
  readonly slides: HeroSlide[] = [
    {
      title: 'Make Every Surprise',
      highlight: 'More Meaningful',
      subtitle: 'Create personalized gift boxes or choose curated collections for every occasion.',
      image: 'assets/giftly/giftbox.png',
      gradient: 'linear-gradient(225deg, #FFDBDF 0%, #fff4d8 20%, #ffffff 60%, #E2D5F1 150%)',
    },
    {
      title: 'Perfect Occasion',
      highlight: 'Gift Boxes',
      subtitle: 'Curated gifts for birthdays, anniversaries, weddings, and every special moment.',
      image: 'assets/giftly/occasion_box.png',
      gradient: 'linear-gradient(225deg, #D6EAF8 0%, #fff1da 20%, #ffffff 60%, #F4ECF7 150%)',
    },
    {
      title: 'Giftly Basket',
      highlight: 'Delights',
      subtitle: 'Beautifully arranged baskets filled with premium goodies for any celebration.',
      image: 'assets/giftly/giftly_basket.png',
      gradient: 'linear-gradient(225deg, #FDEBD0 0%, #eafaf1 20%, #ffffff 60%, #EBDEF0 150%)',
    },
  ];

  constructor(private router: Router) {
    addIcons({ giftOutline, arrowForwardOutline });
  }

  goToShop(): void {
    this.router.navigateByUrl('/tabs/shop');
  }
}
