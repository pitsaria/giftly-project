import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import {
  IonHeader,
  IonToolbar,
  IonTitle,
  IonContent,
  IonInput,
  IonButton,
  ToastController,
} from '@ionic/angular';
import { AuthService } from '../../core/auth.service';

// Mirrors giftly_project/modal_register.php / register.php.
@Component({
  selector: 'app-register',
  templateUrl: 'register.page.html',
  styleUrls: ['register.page.scss'],
  imports: [CommonModule, FormsModule, RouterLink, IonHeader, IonToolbar, IonTitle, IonContent, IonInput, IonButton],
})
export class RegisterPage {
  private auth = inject(AuthService);
  private router = inject(Router);
  private toastCtrl = inject(ToastController);

  firstname = '';
  lastname = '';
  email = '';
  phone = '';
  password = '';
  confirmPassword = '';
  submitting = false;

  async register(): Promise<void> {
    if (!this.firstname || !this.email || !this.password) {
      await this.toast('Name, email, and password are required.');
      return;
    }
    if (this.password !== this.confirmPassword) {
      await this.toast('Passwords do not match.');
      return;
    }

    this.submitting = true;
    try {
      const fullname = `${this.firstname} ${this.lastname}`.trim();
      await this.auth.register(fullname, this.email, this.phone, this.password, this.confirmPassword);
      // Matches register.php: registration succeeds, then the user logs in separately.
      await this.toast('Registration successful! Please log in.');
      this.router.navigateByUrl('/login');
    } catch (err: any) {
      const message = err?.error?.error ?? 'Registration failed. Please try again.';
      await this.toast(message);
    } finally {
      this.submitting = false;
    }
  }

  private async toast(message: string): Promise<void> {
    const t = await this.toastCtrl.create({ message, duration: 2200, position: 'bottom' });
    await t.present();
  }
}
