import { Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { ApiService } from './api.service';
import { Profile } from './models';

export interface ProfileUpdate {
  firstname?: string;
  lastname?: string;
  email?: string;
  phone?: string;
  current_password?: string;
  new_password?: string;
  // Required alongside new_password — the emailed 6-digit confirmation code.
  pwd_code?: string;
}

@Injectable({ providedIn: 'root' })
export class ProfileService {
  private api = inject(ApiService);

  async getProfile(): Promise<Profile> {
    const res = await firstValueFrom(this.api.get<Profile>('profile'));
    return res.data;
  }

  async updateProfile(update: ProfileUpdate): Promise<void> {
    await firstValueFrom(this.api.put('profile', update));
  }

  async sendPasswordCode(): Promise<{ cooldown: number }> {
    const res = await firstValueFrom(this.api.post<{ cooldown?: number }>('profile/send-pwd-code', {}));
    return { cooldown: res.data.cooldown ?? 60 };
  }

  async uploadPicture(file: File): Promise<string> {
    const formData = new FormData();
    formData.append('profile_pic', file);
    const res = await firstValueFrom(this.api.postFormData<{ profile_pic: string }>('profile/picture', formData));
    return res.data.profile_pic;
  }

  async removePicture(): Promise<void> {
    await firstValueFrom(this.api.delete('profile/picture'));
  }
}
