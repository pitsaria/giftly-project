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

// Mirrors giftly_project/modal_login.php.
@Component({
  selector: 'app-login',
  templateUrl: 'login.page.html',
  styleUrls: ['login.page.scss'],
  imports: [CommonModule, FormsModule, RouterLink, IonHeader, IonToolbar, IonTitle, IonContent, IonInput, IonButton],
})
export class LoginPage {
  private auth = inject(AuthService);
  private router = inject(Router);
  private toastCtrl = inject(ToastController);

  email = '';
  password = '';
  submitting = false;

  async login(): Promise<void> {
    if (!this.email || !this.password) {
      await this.toast('Please enter your email and password.');
      return;
    }
    this.submitting = true;
    try {
      await this.auth.login(this.email, this.password);
      this.router.navigateByUrl('/tabs/home');
    } catch (err: any) {
      const message = err?.error?.error ?? 'Login failed. Please check your credentials.';
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
