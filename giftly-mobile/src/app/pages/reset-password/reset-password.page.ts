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

type Step = 'code' | 'password';

// Steps 2+3 of the website's rebuilt forgot-password flow
// (forgot_password_verify_ajax.php + reset_password_ajax.php / pwd_otp_lib.php):
// verify the emailed 6-digit code, then choose a new password.
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

  readonly step = signal<Step>('code');

  resetRef = '';
  emailMasked = '';
  code = '';
  password = '';
  passwordValid = false;
  confirmPassword = '';
  readonly submitting = signal(false);
  readonly cooldown = signal(0);
  private cooldownTimer: ReturnType<typeof setInterval> | undefined;

  get confirmMismatch(): boolean {
    return this.confirmPassword.length > 0 && this.confirmPassword !== this.password;
  }

  ngOnInit(): void {
    this.resetRef = this.route.snapshot.queryParamMap.get('ref') ?? '';
    this.emailMasked = this.route.snapshot.queryParamMap.get('email') ?? '';
    if (!this.resetRef) {
      this.router.navigateByUrl('/forgot-password');
      return;
    }
    const initialCooldown = Number(this.route.snapshot.queryParamMap.get('cooldown') ?? 60);
    this.startCooldown(initialCooldown);
  }

  async verifyCode(): Promise<void> {
    const code = this.code.replace(/\D/g, '');
    if (code.length !== 6) {
      await this.toast('Enter the 6-digit code.');
      return;
    }
    this.submitting.set(true);
    try {
      await this.auth.verifyResetCode(this.resetRef, code);
      this.step.set('password');
    } catch (err: any) {
      await this.toast(err?.error?.error ?? 'That code is incorrect.');
    } finally {
      this.submitting.set(false);
    }
  }

  async resend(): Promise<void> {
    if (this.cooldown() > 0) return;
    try {
      const { cooldown } = await this.auth.resendResetCode(this.resetRef);
      await this.toast('A new code is on its way.');
      this.startCooldown(cooldown);
    } catch (err: any) {
      await this.toast(err?.error?.error ?? "Couldn't send a new code. Try again shortly.");
    }
  }

  private startCooldown(seconds: number): void {
    this.cooldown.set(seconds);
    clearInterval(this.cooldownTimer);
    this.cooldownTimer = setInterval(() => {
      this.cooldown.update((n) => n - 1);
      if (this.cooldown() <= 0) clearInterval(this.cooldownTimer);
    }, 1000);
  }

  async submit(): Promise<void> {
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
      await this.auth.resetPassword(this.resetRef, this.password, this.confirmPassword);
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
