import { Injectable, inject, signal } from '@angular/core';
import { Preferences } from '@capacitor/preferences';
import { firstValueFrom } from 'rxjs';
import { ApiService } from './api.service';
import { User } from './models';

const TOKEN_KEY = 'giftly_token';
const USER_KEY = 'giftly_user';

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

  async login(email: string, password: string): Promise<{ user: User }> {
    const res = await firstValueFrom(
      this.api.post<{ token: string; user: User }>('auth/login', { email, password })
    );
    await this.setSession(res.data.token, res.data.user);
    return { user: res.data.user };
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
