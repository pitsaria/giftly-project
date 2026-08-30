import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import {
  IonHeader,
  IonToolbar,
  IonTitle,
  IonButtons,
  IonBackButton,
  IonContent,
  IonInput,
  IonInputPasswordToggle,
  IonButton,
  ToastController,
} from '@ionic/angular';
import { AuthService } from '../../core/auth.service';

// Mirrors giftly_project/modal_login.php.
@Component({
  selector: 'app-login',
  templateUrl: 'login.page.html',
  styleUrls: ['login.page.scss'],
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
    IonInput,
    IonInputPasswordToggle,
    IonButton,
  ],
})
export class LoginPage {
  private auth = inject(AuthService);
  private router = inject(Router);
  private toastCtrl = inject(ToastController);

  email = '';
  password = '';
  // Signal, not a plain field: mutating a plain field inside an async/await
  // continuation isn't guaranteed to schedule a re-render in this Angular/
  // Ionic version (the button was observed staying stuck on "Logging in..."
  // after the request settled) — a signal write always does.
  readonly submitting = signal(false);

  async login(): Promise<void> {
    if (!this.email || !this.password) {
      await this.toast('Please enter your email and password.');
      return;
    }
    this.submitting.set(true);
    try {
      await this.auth.login(this.email, this.password);
      this.router.navigateByUrl('/tabs/home');
    } catch (err: any) {
      const message = err?.error?.error ?? 'Login failed. Please check your credentials.';
      await this.toast(message);
    } finally {
      this.submitting.set(false);
    }
  }

  private async toast(message: string): Promise<void> {
    const t = await this.toastCtrl.create({ message, duration: 2200, position: 'bottom' });
    await t.present();
  }
}
