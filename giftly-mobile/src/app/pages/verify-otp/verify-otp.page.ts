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
  IonButton,
  ToastController,
} from '@ionic/angular';
import { AuthService } from '../../core/auth.service';

// Mirrors the website's email-OTP step (auth_lib.php / verify_otp.php).
// Reached from Login when the server has email configured.
@Component({
  selector: 'app-verify-otp',
  templateUrl: 'verify-otp.page.html',
  styleUrls: ['verify-otp.page.scss'],
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
export class VerifyOtpPage implements OnInit {
  private auth = inject(AuthService);
  private router = inject(Router);
  private route = inject(ActivatedRoute);
  private toastCtrl = inject(ToastController);

  otpRef = '';
  emailMasked = '';
  code = '';
  readonly submitting = signal(false);
  readonly cooldown = signal(0);
  private cooldownTimer: ReturnType<typeof setInterval> | undefined;

  ngOnInit(): void {
    this.otpRef = this.route.snapshot.queryParamMap.get('ref') ?? '';
    this.emailMasked = this.route.snapshot.queryParamMap.get('email') ?? '';
    if (!this.otpRef) {
      this.router.navigateByUrl('/login');
    }
  }

  async verify(): Promise<void> {
    const code = this.code.replace(/\D/g, '');
    if (code.length !== 6) {
      await this.toast('Enter the 6-digit code.');
      return;
    }
    this.submitting.set(true);
    try {
      await this.auth.verifyOtp(this.otpRef, code);
      this.router.navigateByUrl('/tabs/home');
    } catch (err: any) {
      await this.toast(err?.error?.error ?? 'That code is incorrect.');
    } finally {
      this.submitting.set(false);
    }
  }

  async resend(): Promise<void> {
    if (this.cooldown() > 0) return;
    try {
      await this.auth.resendOtp(this.otpRef);
      await this.toast('A new code is on its way.');
      this.startCooldown();
    } catch (err: any) {
      await this.toast(err?.error?.error ?? "Couldn't send a new code. Try again shortly.");
    }
  }

  private startCooldown(): void {
    this.cooldown.set(30);
    clearInterval(this.cooldownTimer);
    this.cooldownTimer = setInterval(() => {
      this.cooldown.update((n) => n - 1);
      if (this.cooldown() <= 0) clearInterval(this.cooldownTimer);
    }, 1000);
  }

  private async toast(message: string): Promise<void> {
    const t = await this.toastCtrl.create({ message, duration: 2200, position: 'bottom' });
    await t.present();
  }
}
