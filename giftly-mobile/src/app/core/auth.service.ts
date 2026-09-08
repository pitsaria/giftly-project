import { Injectable, inject, signal } from '@angular/core';
import { Preferences } from '@capacitor/preferences';
import { firstValueFrom } from 'rxjs';
import { ApiService } from './api.service';
import { User } from './models';

const TOKEN_KEY = 'giftly_token';
const USER_KEY = 'giftly_user';

// login() either finishes (session set) or hands back an OTP challenge.
export interface OtpChallenge {
  otpRequired: true;
  otpRef: string;
  emailMasked: string;
  cooldown: number;
}
export type LoginResult = { user: User } | OtpChallenge;

export function isOtpChallenge(r: LoginResult): r is OtpChallenge {
  return (r as OtpChallenge).otpRequired === true;
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  private api = inject(ApiService);

  private tokenValue: string | null = null;
  readonly user = signal<User | null>(null);
  readonly isLoggedIn = signal(false);

  getToken(): string | null {
    return this.tokenValue;
  }

  // Called once at app startup to restore a saved session.
  async restore(): Promise<void> {
    const { value: token } = await Preferences.get({ key: TOKEN_KEY });
    const { value: userJson } = await Preferences.get({ key: USER_KEY });
    if (!token) {
      return;
    }
    this.tokenValue = token;
    if (userJson) {
      this.user.set(JSON.parse(userJson));
      this.isLoggedIn.set(true);
    }
    // Confirm the token is still valid server-side, and refresh the user.
    try {
      const res = await firstValueFrom(this.api.get<{ authenticated: boolean; user: User }>('auth/verify'));
      this.user.set(res.data.user);
      this.isLoggedIn.set(true);
      await Preferences.set({ key: USER_KEY, value: JSON.stringify(res.data.user) });
    } catch {
      await this.clearSession();
    }
  }

  async login(email: string, password: string): Promise<LoginResult> {
    const res = await firstValueFrom(
      this.api.post<{
        token?: string;
        user?: User;
        otp_required?: boolean;
        otp_ref?: string;
        email_masked?: string;
        cooldown?: number;
      }>('auth/login', { email, password })
    );
    if (res.data.otp_required) {
      return {
        otpRequired: true,
        otpRef: res.data.otp_ref ?? '',
        emailMasked: res.data.email_masked ?? email,
        cooldown: res.data.cooldown ?? 60,
      };
    }
    await this.setSession(res.data.token!, res.data.user!);
    return { user: res.data.user! };
  }

  async verifyOtp(otpRef: string, code: string): Promise<{ user: User }> {
    const res = await firstValueFrom(
      this.api.post<{ token: string; user: User }>('auth/verify-otp', { otp_ref: otpRef, code })
    );
    await this.setSession(res.data.token, res.data.user);
    return { user: res.data.user };
  }

  async resendOtp(otpRef: string): Promise<{ cooldown: number }> {
    const res = await firstValueFrom(
      this.api.post<{ cooldown?: number }>('auth/resend-otp', { otp_ref: otpRef })
    );
    return { cooldown: res.data.cooldown ?? 60 };
  }

  async register(name: string, email: string, phone: string, password: string, confirmPassword: string): Promise<void> {
    await firstValueFrom(
      this.api.post('auth/register', {
        name,
        email,
        phone,
        password,
        confirm_password: confirmPassword,
      })
    );
  }

  // Step 1 of the email → code → new-password flow (pwd_otp_lib.php). The
  // reset is tracked by a stateless `reset_ref` since the app has no session.
  async forgotPassword(email: string): Promise<{ resetRef: string; emailMasked: string; cooldown: number }> {
    const res = await firstValueFrom(
      this.api.post<{ reset_ref: string; email_masked: string; cooldown: number }>('auth/forgot-password', { email })
    );
    return {
      resetRef: res.data.reset_ref,
      emailMasked: res.data.email_masked,
      cooldown: res.data.cooldown ?? 60,
    };
  }

  // Step 2 — verify the emailed code.
  async verifyResetCode(resetRef: string, code: string): Promise<void> {
    await firstValueFrom(this.api.post('auth/verify-reset-code', { reset_ref: resetRef, code }));
  }

  async resendResetCode(resetRef: string): Promise<{ cooldown: number }> {
    const res = await firstValueFrom(
      this.api.post<{ cooldown?: number }>('auth/verify-reset-code', { reset_ref: resetRef, resend: true })
    );
    return { cooldown: res.data.cooldown ?? 60 };
  }

  // Step 3 — set the new password (requires a code already verified in step 2).
  async resetPassword(resetRef: string, password: string, confirmPassword: string): Promise<void> {
    await firstValueFrom(
      this.api.post('auth/reset-password', { reset_ref: resetRef, password, confirm_password: confirmPassword })
    );
  }

  // The server's Google Web client ID, or '' when Google sign-in is disabled.
  async googleClientId(): Promise<string> {
    try {
      const res = await firstValueFrom(this.api.get<{ client_id: string }>('auth/google'));
      return res.data.client_id ?? '';
    } catch {
      return '';
    }
  }

  // Exchange a Google ID token (from GIS) for a Giftly session.
  async googleLogin(credential: string): Promise<{ user: User }> {
    const res = await firstValueFrom(
      this.api.post<{ token: string; user: User }>('auth/google', { credential })
    );
    await this.setSession(res.data.token, res.data.user);
    return { user: res.data.user };
  }

  async logout(): Promise<void> {
    try {
      await firstValueFrom(this.api.post('auth/logout', {}));
    } catch {
      // Ignore network errors on logout — clear the local session regardless.
    }
    await this.clearSession();
  }

  private async setSession(token: string, user: User): Promise<void> {
    this.tokenValue = token;
    this.user.set(user);
    this.isLoggedIn.set(true);
    await Preferences.set({ key: TOKEN_KEY, value: token });
    await Preferences.set({ key: USER_KEY, value: JSON.stringify(user) });
  }

  private async clearSession(): Promise<void> {
    this.tokenValue = null;
    this.user.set(null);
    this.isLoggedIn.set(false);
    await Preferences.remove({ key: TOKEN_KEY });
    await Preferences.remove({ key: USER_KEY });
  }
}
