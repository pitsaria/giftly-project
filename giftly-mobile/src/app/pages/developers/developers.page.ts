import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonHeader, IonToolbar, IonTitle, IonButtons, IonBackButton, IonContent } from '@ionic/angular';

interface Owner {
  name: string;
  role: string;
  bio: string;
  img: string;
}

// Developers — split out of the old single About page's "The people behind
// Giftly" section, moved verbatim.
@Component({
  selector: 'app-developers',
  templateUrl: 'developers.page.html',
  styleUrls: ['developers.page.scss'],
  imports: [CommonModule, IonHeader, IonToolbar, IonTitle, IonButtons, IonBackButton, IonContent],
})
export class DevelopersPage {
  readonly owners: Owner[] = [
    { name: 'Peatzie Cosino', role: 'Founder & CEO', bio: 'Started Giftly from a kitchen table with a glue gun and a lot of ribbon.', img: 'assets/giftly/cosino.png' },
    { name: 'Angela Castillo', role: 'Head of Design', bio: 'Obsesses over paper weight, palette, and the perfect bow.', img: 'assets/giftly/castillo.jpg' },
    { name: 'Feliciti Gacilla', role: 'Operations & Logistics', bio: 'Makes sure every box arrives on time and in one beautiful piece.', img: 'assets/giftly/gacilla.jpeg' },
    { name: 'Gabriel Edpao', role: 'Head of Curation', bio: 'Hunts down the small-batch makers behind our favourite finds.', img: 'assets/giftly/edpao.JPG' },
    { name: 'Rachelle Dilig', role: 'Customer Happiness', bio: 'The voice on the other end of every message — and every thank-you note.', img: 'assets/giftly/dilig.JPG' },
  ];
}
