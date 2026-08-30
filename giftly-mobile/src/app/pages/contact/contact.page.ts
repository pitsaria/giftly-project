import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
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
  IonInput,
  IonTextarea,
  IonAccordion,
  IonAccordionGroup,
  IonItem,
  IonLabel,
  ToastController,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import { mailOutline, callOutline, timeOutline, locationOutline, checkmarkCircle, paperPlaneOutline } from 'ionicons/icons';
import { ContactService } from '../../core/contact.service';
import { AuthService } from '../../core/auth.service';
import { describeError } from '../../core/http-error';

interface Faq {
  q: string;
  a: string;
}

// Port of giftly_project/contact.php.
@Component({
  selector: 'app-contact',
  templateUrl: 'contact.page.html',
  styleUrls: ['contact.page.scss'],
  imports: [
    CommonModule,
    FormsModule,
    RouterLink,
    IonHeader,
    IonToolbar,
    IonTitle,
    IonButtons,
    IonBackButton,
    IonContent,
    IonButton,
    IonIcon,
    IonInput,
    IonTextarea,
    IonAccordion,
    IonAccordionGroup,
    IonItem,
    IonLabel,
  ],
})
export class ContactPage {
  private contactSvc = inject(ContactService);
  private auth = inject(AuthService);
  private toastCtrl = inject(ToastController);

  name = this.auth.user()?.name ?? '';
  email = this.auth.user()?.email ?? '';
  subject = '';
  message = '';

  readonly submitting = signal(false);
  readonly sent = signal(false);

  readonly faqs: Faq[] = [
    {
      q: 'How long does delivery take?',
      a: 'Please allow at least 3 days for us to prepare and dispatch your box. Delivery windows are between 8:00 AM and 8:00 PM, and you choose the date at checkout.',
    },
    {
      q: 'Can I send a box straight to someone else?',
      a: 'Yes — at checkout choose "Deliver to Recipient", add their name and number, and write a gift message. Prices are never included in the package.',
    },
    {
      q: 'Do you do custom or bulk orders?',
      a: "We love these. Use the form above with a few details (quantity, occasion, budget, date) and we'll put together a quote.",
    },
    {
      q: 'Something arrived damaged — what now?',
      a: "Message us within 48 hours with your order number and a photo. We'll sort out a replacement or refund.",
    },
  ];

  constructor() {
    addIcons({ mailOutline, callOutline, timeOutline, locationOutline, checkmarkCircle, paperPlaneOutline });
  }

  async send(): Promise<void> {
    if (!this.name.trim()) return this.toast('Please tell us your name.');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.email.trim())) return this.toast('Please enter a valid email address.');
    if (this.message.trim().length < 5) return this.toast('Your message is a little short.');

    this.submitting.set(true);
    try {
      await this.contactSvc.send({
        name: this.name.trim(),
        email: this.email.trim(),
        subject: this.subject.trim(),
        message: this.message.trim(),
      });
      this.sent.set(true);
    } catch (err) {
      await this.toast(describeError(err));
    } finally {
      this.submitting.set(false);
    }
  }

  private async toast(message: string): Promise<void> {
    const t = await this.toastCtrl.create({ message, duration: 2200, position: 'bottom' });
    await t.present();
  }
}
