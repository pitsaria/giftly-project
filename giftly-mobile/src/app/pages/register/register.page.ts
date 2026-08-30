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
import { PhPhoneInputComponent } from '../../shared/ph-phone-input/ph-phone-input.component';
import { PasswordStrengthInputComponent } from '../../shared/password-strength-input/password-strength-input.component';

const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

// Mirrors giftly_project/modal_register.php / register.php, including its
// client-side rules: email format check (validateField('email')), 8+ char
// password with a letter/number/special char (validatePassword(), shared
// via app-password-strength-input), and a PH mobile check
// (validateField('phone')) — adapted to the app's own format: fixed +63 +
// 10 digits, shared with Checkout via app-ph-phone-input.
@Component({
  selector: 'app-register',
  templateUrl: 'register.page.html',
  styleUrls: ['register.page.scss'],
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
    PhPhoneInputComponent,
    PasswordStrengthInputComponent,
  ],
})
export class RegisterPage {
  private auth = inject(AuthService);
  private router = inject(Router);
  private toastCtrl = inject(ToastController);

  firstname = '';
  lastname = '';
  email = '';
  emailTouched = false;
  phoneDigits = '';
  phoneTouched = false;
  // Signal, not a plain field: mutating a plain field inside an async/await
  // continuation isn't guaranteed to schedule a re-render (the button was
  // observed staying stuck on "Creating account..." after an error response
  // settled) — a signal write always does.
  readonly submitting = signal(false);

  password = '';
  passwordValid = false;
  confirmPassword = '';

  get emailValid(): boolean {
    return EMAIL_REGEX.test(this.email);
  }

  get phoneValid(): boolean {
    return /^\d{10}$/.test(this.phoneDigits);
  }

  get confirmMismatch(): boolean {
    return this.confirmPassword.length > 0 && this.confirmPassword !== this.password;
  }

  async register(): Promise<void> {
    if (!this.firstname.trim() || !this.lastname.trim()) {
      await this.toast('Please fill in your first and last name.');
      return;
    }
    this.emailTouched = true;
    if (!this.emailValid) {
      await this.toast('Please enter a valid email address.');
      return;
    }
    this.phoneTouched = true;
    if (!this.phoneValid) {
      await this.toast('Please enter a valid 10-digit mobile number after +63.');
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
      const fullname = `${this.firstname} ${this.lastname}`.trim();
      const phone = `63${this.phoneDigits}`;
      await this.auth.register(fullname, this.email, phone, this.password, this.confirmPassword);
      // Matches register.php: registration succeeds, then the user logs in separately.
      await this.toast('Registration successful! Please log in.');
      this.router.navigateByUrl('/login');
    } catch (err: any) {
      const message = err?.error?.error ?? 'Registration failed. Please try again.';
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
