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

  async uploadPicture(file: File): Promise<string> {
    const formData = new FormData();
    formData.append('profile_pic', file);
    const res = await firstValueFrom(this.api.postFormData<{ profile_pic: string }>('profile/picture', formData));
    return res.data.profile_pic;
  }
}
