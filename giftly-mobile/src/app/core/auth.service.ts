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
      }>('auth/login', { email, password })
    );
    if (res.data.otp_required) {
      return {
        otpRequired: true,
        otpRef: res.data.otp_ref ?? '',
        emailMasked: res.data.email_masked ?? email,
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

  async resendOtp(otpRef: string): Promise<void> {
    await firstValueFrom(this.api.post('auth/resend-otp', { otp_ref: otpRef }));
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

  // Returns a reset token the caller carries straight into resetPassword() —
  // there's no outbound email configured on the backend (mirrors the
  // website's own forgot_password_ajax.php, which just prints the link
  // instead of emailing it), so the app hands the token to the next screen
  // itself rather than pretending an email was sent.
  async forgotPassword(email: string): Promise<string> {
    const res = await firstValueFrom(this.api.post<{ token: string }>('auth/forgot-password', { email }));
    return res.data.token;
  }

  async resetPassword(token: string, password: string): Promise<void> {
    await firstValueFrom(this.api.post('auth/reset-password', { token, password }));
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
