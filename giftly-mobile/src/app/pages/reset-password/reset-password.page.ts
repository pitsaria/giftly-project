import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
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
import { PasswordStrengthInputComponent } from '../../shared/password-strength-input/password-strength-input.component';

// Mirrors giftly_project/modal_reset_password.php + reset_password_ajax.php.
// The token normally arrives pre-filled from Forgot Password's response, but
// stays editable in case it needs to be pasted in manually.
@Component({
  selector: 'app-reset-password',
  templateUrl: 'reset-password.page.html',
  styleUrls: ['reset-password.page.scss'],
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
    PasswordStrengthInputComponent,
  ],
})
export class ResetPasswordPage implements OnInit {
  private auth = inject(AuthService);
  private router = inject(Router);
  private route = inject(ActivatedRoute);
  private toastCtrl = inject(ToastController);

  token = '';
  password = '';
  passwordValid = false;
  confirmPassword = '';
  // Signal, not a plain field: mutating a plain field inside an async/await
  // continuation isn't guaranteed to schedule a re-render — a signal write
  // always does (see the fix applied across Cart/Profile/Checkout/Login).
  readonly submitting = signal(false);

  get confirmMismatch(): boolean {
    return this.confirmPassword.length > 0 && this.confirmPassword !== this.password;
  }

  ngOnInit(): void {
    this.token = this.route.snapshot.queryParamMap.get('token') ?? '';
  }

  async submit(): Promise<void> {
    if (!this.token.trim()) {
      await this.toast('Please enter your reset code.');
      return;
    }
    if (!this.passwordValid) {
      await this.toast('Password must be at least 8 characters and include a letter, a number, and a special character.');
      return;
    }
    if (this.confirmMismatch || !this.confirmPassword) {
      await this.toast('Passwords do not match.');
      return;
    }

    this.submitting.set(true);
    try {
      await this.auth.resetPassword(this.token.trim(), this.password);
      await this.toast('Password reset successfully! Please log in.');
      this.router.navigateByUrl('/login');
    } catch (err: any) {
      const message = err?.error?.error ?? 'Could not reset your password. Please try again.';
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
