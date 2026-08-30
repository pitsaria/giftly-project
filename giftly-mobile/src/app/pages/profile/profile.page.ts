import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import {
  IonContent,
  IonRefresher,
  IonRefresherContent,
  IonSegment,
  IonSegmentButton,
  IonLabel,
  IonIcon,
  IonInput,
  IonButton,
  IonSpinner,
  AlertController,
  ToastController,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import {
  personOutline,
  personCircleOutline,
  locationOutline,
  heartOutline,
  heart,
  trashOutline,
  logOutOutline,
  giftOutline,
  createOutline,
  lockClosedOutline,
  informationCircleOutline,
  mailOutline,
  chevronForwardOutline,
} from 'ionicons/icons';
import { Address, Box, Profile, WishlistData } from '../../core/models';
import { describeError } from '../../core/http-error';
import { AuthService } from '../../core/auth.service';
import { ProfileService } from '../../core/profile.service';
import { AddressService, NewAddress } from '../../core/address.service';
import { WishlistService } from '../../core/wishlist.service';
import { CartService } from '../../core/cart.service';
import { BoxService } from '../../core/box.service';
import { TopBarComponent } from '../../shared/top-bar/top-bar.component';
import { ImgUrlPipe } from '../../shared/img-url.pipe';

type Tab = 'settings' | 'addresses' | 'wishlist' | 'boxes';

// Mirrors giftly_project/profile.php's sidebar-tab structure (Settings /
// Addresses / Wishlist), condensed into one segmented page. Order history
// moved out to its own Orders tab (see ../orders/orders.page.ts).
// Fetched state lives in signals — guaranteed to trigger a re-render on
// write, unlike plain fields mutated inside an async continuation.
@Component({
  selector: 'app-profile',
  templateUrl: 'profile.page.html',
  styleUrls: ['profile.page.scss'],
  imports: [
    CommonModule,
    FormsModule,
    RouterLink,
    IonContent,
    IonRefresher,
    IonRefresherContent,
    IonSegment,
    IonSegmentButton,
    IonLabel,
    IonIcon,
    IonInput,
    IonButton,
    IonSpinner,
    TopBarComponent,
    ImgUrlPipe,
  ],
})
export class ProfilePage implements OnInit {
  auth = inject(AuthService);
  private profileSvc = inject(ProfileService);
  private addressSvc = inject(AddressService);
  private wishlistSvc = inject(WishlistService);
  private cart = inject(CartService);
  private boxSvc = inject(BoxService);
  private router = inject(Router);
  private route = inject(ActivatedRoute);
  private alertCtrl = inject(AlertController);
  private toastCtrl = inject(ToastController);

  readonly tab = signal<Tab>('settings');
  readonly loading = signal(false);
  readonly saving = signal(false);
  readonly error = signal<string | null>(null);
  readonly slowLoad = signal(false);
  private slowLoadTimer: ReturnType<typeof setTimeout> | undefined;
  private loadToken = 0;

  readonly profile = signal<Profile | null>(null);
  readonly addresses = signal<Address[]>([]);
  readonly wishlist = signal<WishlistData | null>(null);
  readonly boxes = signal<Box[]>([]);

  showAddAddress = false;
  newAddress: NewAddress = { label: '', address: '', city: '', province: '', zip: '' };

  currentPassword = '';
  newPassword = '';

  constructor() {
    addIcons({
      personOutline,
      personCircleOutline,
      locationOutline,
      heartOutline,
      heart,
      trashOutline,
      logOutOutline,
      giftOutline,
      createOutline,
      lockClosedOutline,
      informationCircleOutline,
      mailOutline,
      chevronForwardOutline,
    });
  }

  async ngOnInit(): Promise<void> {
    this.applyTabQueryParam();
    if (this.auth.isLoggedIn()) {
      await this.loadTab(this.tab(), false);
    }
  }

  // Honour ?tab= (e.g. Build-a-Box navigates here with tab=boxes after saving).
  private applyTabQueryParam(): void {
    const requested = this.route.snapshot.queryParamMap.get('tab') as Tab | null;
    if (requested === 'boxes' || requested === 'addresses' || requested === 'wishlist' || requested === 'settings') {
      this.tab.set(requested);
    }
  }

  // Ionic keeps this page's component instance alive when you switch tabs
  // away and back (IonicRouteStrategy), so ngOnInit only ever runs once —
  // without this, toggling a product's wishlist from Shop/Home and then
  // returning to an already-open Wishlist tab kept showing the stale list
  // from before, only fixed by a full app reload. Force-reloading the
  // active tab on every re-entry (loadTab already guards overlapping calls
  // with its loadToken) keeps it in sync with changes made elsewhere.
  async ionViewWillEnter(): Promise<void> {
    this.applyTabQueryParam();
    if (this.auth.isLoggedIn()) {
      await this.loadTab(this.tab(), true);
    }
  }

  async switchTab(value: string | number | undefined): Promise<void> {
    if (!value) return;
    const tab = value as Tab;
    this.tab.set(tab);
    await this.loadTab(tab, false);
  }

  async retryTab(): Promise<void> {
    await this.loadTab(this.tab(), true);
  }

  async handleRefresh(event: any): Promise<void> {
    if (this.auth.isLoggedIn()) {
      await this.loadTab(this.tab(), true);
    }
    event.target.complete();
  }

  private async loadTab(tab: Tab, forceReload: boolean): Promise<void> {
    const token = ++this.loadToken;

    this.loading.set(true);
    this.error.set(null);
    this.slowLoad.set(false);
    clearTimeout(this.slowLoadTimer);
    this.slowLoadTimer = setTimeout(() => {
      if (token === this.loadToken) {
        this.slowLoad.set(true);
      }
    }, 6000);

    try {
      if (tab === 'settings' && (forceReload || !this.profile())) {
        this.profile.set(await this.profileSvc.getProfile());
      } else if (tab === 'addresses') {
        this.addresses.set(await this.addressSvc.getAll());
      } else if (tab === 'wishlist') {
        this.wishlist.set(await this.wishlistSvc.getWishlist());
      } else if (tab === 'boxes') {
        this.boxes.set(await this.boxSvc.listBoxes());
      }
      // The profile header (avatar/name/email) is shown regardless of which
      // tab is active, so make sure it's loaded even when starting on a
      // different tab.
      if (!this.profile()) {
        this.profile.set(await this.profileSvc.getProfile());
      }
      if (token !== this.loadToken) return;
    } catch (err) {
      if (token !== this.loadToken) return;
      this.error.set(describeError(err));
    } finally {
      if (token === this.loadToken) {
        clearTimeout(this.slowLoadTimer);
        this.loading.set(false);
      }
    }
  }

  async saveProfile(): Promise<void> {
    const profile = this.profile();
    if (!profile) return;
    this.saving.set(true);
    try {
      await this.profileSvc.updateProfile({
        firstname: profile.firstname,
        lastname: profile.lastname,
        email: profile.email,
        phone: profile.phone,
        current_password: this.currentPassword || undefined,
        new_password: this.newPassword || undefined,
      });
      this.currentPassword = '';
      this.newPassword = '';
      await this.toast('Profile updated successfully!');
    } catch (err) {
      await this.toast(describeError(err));
    } finally {
      this.saving.set(false);
    }
  }

  async addAddress(): Promise<void> {
    if (!this.newAddress.address || !this.newAddress.city || !this.newAddress.province || !this.newAddress.zip) {
      await this.toast('Please fill in all address fields.');
      return;
    }
    try {
      await this.addressSvc.create(this.newAddress);
      this.newAddress = { label: '', address: '', city: '', province: '', zip: '' };
      this.showAddAddress = false;
      this.addresses.set(await this.addressSvc.getAll());
    } catch {
      await this.toast('Could not save this address. Please try again.');
    }
  }

  async deleteAddress(id: number): Promise<void> {
    const alert = await this.alertCtrl.create({
      header: 'Delete this address?',
      message: 'This action cannot be undone.',
      buttons: [
        { text: 'Cancel', role: 'cancel' },
        {
          text: 'Delete',
          role: 'destructive',
          handler: async () => {
            try {
              await this.addressSvc.remove(id);
              this.addresses.set(await this.addressSvc.getAll());
            } catch {
              await this.toast('Could not delete this address. Please try again.');
            }
          },
        },
      ],
    });
    await alert.present();
  }

  async toggleWishlist(productId: number): Promise<void> {
    try {
      await this.wishlistSvc.toggle(productId);
      this.wishlist.set(await this.wishlistSvc.getWishlist());
    } catch {
      await this.toast('Could not update your wishlist. Please try again.');
    }
  }

  async addWishlistItemToCart(productId: number): Promise<void> {
    try {
      await this.cart.addToCart(productId, 1);
      await this.toast('Added to cart');
    } catch {
      await this.toast('Could not add to cart. Please try again.');
    }
  }

  editBox(id: number): void {
    this.router.navigate(['/build-a-box'], { queryParams: { box_id: id } });
  }

  checkoutBox(id: number): void {
    this.router.navigate(['/box-checkout'], { queryParams: { box_id: id } });
  }

  async deleteBox(id: number): Promise<void> {
    const alert = await this.alertCtrl.create({
      header: 'Delete this box?',
      message: 'This cannot be undone.',
      buttons: [
        { text: 'Cancel', role: 'cancel' },
        {
          text: 'Delete',
          role: 'destructive',
          handler: async () => {
            try {
              await this.boxSvc.deleteBox(id);
              this.boxes.set(await this.boxSvc.listBoxes());
            } catch {
              await this.toast('Could not delete this box. Please try again.');
            }
          },
        },
      ],
    });
    await alert.present();
  }

  async logout(): Promise<void> {
    await this.auth.logout();
    this.profile.set(null);
    this.router.navigateByUrl('/tabs/home');
  }

  private async toast(message: string): Promise<void> {
    const t = await this.toastCtrl.create({ message, duration: 2000, position: 'bottom' });
    await t.present();
  }
}
