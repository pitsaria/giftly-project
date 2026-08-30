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
  IonButton,
  ToastController,
} from '@ionic/angular';
import { AuthService } from '../../core/auth.service';

const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

// Mirrors giftly_project/modal_forgot_password.php + forgot_password_ajax.php.
// The backend has no outbound email configured (the website itself just
// prints the reset link instead of emailing it), so this hands the reset
// token straight to the Reset Password screen rather than pretending an
// email was sent.
@Component({
  selector: 'app-forgot-password',
  templateUrl: 'forgot-password.page.html',
  styleUrls: ['forgot-password.page.scss'],
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
    IonButton,
  ],
})
export class ForgotPasswordPage {
  private auth = inject(AuthService);
  private router = inject(Router);
  private toastCtrl = inject(ToastController);

  email = '';
  // Signal, not a plain field: mutating a plain field inside an async/await
  // continuation isn't guaranteed to schedule a re-render — a signal write
  // always does (see the fix applied across Cart/Profile/Checkout/Login).
  readonly submitting = signal(false);

  get emailValid(): boolean {
    return EMAIL_REGEX.test(this.email);
  }

  async submit(): Promise<void> {
    if (!this.emailValid) {
      await this.toast('Please enter a valid email address.');
      return;
    }

    this.submitting.set(true);
    try {
      const token = await this.auth.forgotPassword(this.email);
      this.router.navigate(['/reset-password'], { queryParams: { token } });
    } catch (err: any) {
      const message = err?.error?.error ?? 'Could not generate a reset code. Please try again.';
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
