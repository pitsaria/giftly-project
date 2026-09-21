import { Component, effect, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterLink } from '@angular/router';
import {
  IonMenu,
  IonHeader,
  IonToolbar,
  IonTitle,
  IonContent,
  IonList,
  IonListHeader,
  IonItem,
  IonLabel,
  IonIcon,
  IonMenuToggle,
  IonFooter,
  AlertController,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import {
  logOutOutline,
  timeOutline,
  sparklesOutline,
  phonePortraitOutline,
  codeSlashOutline,
  mailOutline,
} from 'ionicons/icons';
import { AuthService } from '../../core/auth.service';
import { ProfileService } from '../../core/profile.service';
import { NotificationInboxService } from '../../core/notification-inbox.service';
import { ImgUrlPipe } from '../../shared/img-url.pipe';

// App-wide side menu (the sidebar the bottom tab bar doesn't cover): opened
// from Home's top bar, it holds account actions plus the info pages split
// out of the old single About page.
@Component({
  selector: 'app-side-menu',
  templateUrl: 'side-menu.component.html',
  styleUrls: ['side-menu.component.scss'],
  imports: [
    CommonModule,
    RouterLink,
    IonMenu,
    IonHeader,
    IonToolbar,
    IonTitle,
    IonContent,
    IonList,
    IonListHeader,
    IonItem,
    IonLabel,
    IonIcon,
    IonMenuToggle,
    IonFooter,
    ImgUrlPipe,
  ],
})
export class SideMenuComponent {
  auth = inject(AuthService);
  profileSvc = inject(ProfileService);
  private notifSvc = inject(NotificationInboxService);
  private router = inject(Router);
  private alertCtrl = inject(AlertController);

  constructor() {
    addIcons({
      logOutOutline,
      timeOutline,
      sparklesOutline,
      phonePortraitOutline,
      codeSlashOutline,
      mailOutline,
    });

    // Fetch once per login so the avatar and notification badge are right
    // even if the user never visits the Profile/Notifications page this
    // session — ProfileService.getProfile() keeps currentPicture in sync
    // after that (see uploadPicture/removePicture), and NotificationsPage
    // resyncs unreadCount itself whenever it's opened/left.
    effect(() => {
      if (this.auth.isLoggedIn()) {
        void this.profileSvc.getProfile().catch(() => {});
        void this.notifSvc.refreshUnreadCount().catch(() => {});
      }
    });
  }

  async logout(): Promise<void> {
    const alert = await this.alertCtrl.create({
      header: 'Log out?',
      message: 'Are you sure you want to log out of your Giftly account?',
      buttons: [
        { text: 'Cancel', role: 'cancel' },
        {
          text: 'Log Out',
          role: 'destructive',
          handler: async () => {
            await this.auth.logout();
            this.router.navigateByUrl('/tabs/home');
          },
        },
      ],
    });
    await alert.present();
  }
}
