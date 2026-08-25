import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import {
  IonHeader,
  IonToolbar,
  IonTitle,
  IonContent,
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
  locationOutline,
  bagOutline,
  heartOutline,
  heart,
  trashOutline,
  logOutOutline,
} from 'ionicons/icons';
import { Address, Order, Profile, WishlistData } from '../../core/models';
import { AuthService } from '../../core/auth.service';
import { ProfileService } from '../../core/profile.service';
import { AddressService, NewAddress } from '../../core/address.service';
import { OrderService } from '../../core/order.service';
import { WishlistService } from '../../core/wishlist.service';
import { CartService } from '../../core/cart.service';
import { environment } from '../../../environments/environment';

type Tab = 'settings' | 'addresses' | 'orders' | 'wishlist';

// Mirrors giftly_project/profile.php's sidebar-tab structure (Settings /
// Addresses / Order History / Wishlist), condensed into one segmented page.
@Component({
  selector: 'app-profile',
  templateUrl: 'profile.page.html',
  styleUrls: ['profile.page.scss'],
  imports: [
    CommonModule,
    FormsModule,
    RouterLink,
    IonHeader,
    IonToolbar,
    IonTitle,
    IonContent,
    IonSegment,
    IonSegmentButton,
    IonLabel,
    IonIcon,
    IonInput,
    IonButton,
    IonSpinner,
  ],
})
export class ProfilePage implements OnInit {
  auth = inject(AuthService);
  private profileSvc = inject(ProfileService);
  private addressSvc = inject(AddressService);
  private orderSvc = inject(OrderService);
  private wishlistSvc = inject(WishlistService);
  private cart = inject(CartService);
  private router = inject(Router);
  private alertCtrl = inject(AlertController);
  private toastCtrl = inject(ToastController);

  readonly uploadsUrl = environment.uploadsUrl;
  tab: Tab = 'settings';
  loading = false;
  saving = false;
  error: string | null = null;

  profile: Profile | null = null;
  addresses: Address[] = [];
  orders: Order[] = [];
  wishlist: WishlistData | null = null;

  showAddAddress = false;
  newAddress: NewAddress = { label: '', address: '', city: '', province: '', zip: '' };

  currentPassword = '';
  newPassword = '';

  constructor() {
    addIcons({
      personOutline,
      locationOutline,
      bagOutline,
      heartOutline,
      heart,
      trashOutline,
      logOutOutline,
    });
  }

  async ngOnInit(): Promise<void> {
    if (this.auth.isLoggedIn()) {
      await this.loadTab('settings', false);
    }
  }

  async switchTab(value: string | number | undefined): Promise<void> {
    if (!value) return;
    const tab = value as Tab;
    this.tab = tab;
    await this.loadTab(tab, false);
  }

  async retryTab(): Promise<void> {
    await this.loadTab(this.tab, true);
  }

  private async loadTab(tab: Tab, forceReload: boolean): Promise<void> {
    this.loading = true;
    this.error = null;
    try {
      if (tab === 'settings' && (forceReload || !this.profile)) {
        this.profile = await this.profileSvc.getProfile();
      } else if (tab === 'addresses') {
        this.addresses = await this.addressSvc.getAll();
      } else if (tab === 'orders') {
        this.orders = await this.orderSvc.getOrders();
      } else if (tab === 'wishlist') {
        this.wishlist = await this.wishlistSvc.getWishlist();
      }
    } catch {
      this.error = 'Could not load this. The server may still be starting up — please try again.';
    } finally {
      this.loading = false;
    }
  }

  async saveProfile(): Promise<void> {
    if (!this.profile) return;
    this.saving = true;
    try {
      await this.profileSvc.updateProfile({
        firstname: this.profile.firstname,
        lastname: this.profile.lastname,
        email: this.profile.email,
        phone: this.profile.phone,
        current_password: this.currentPassword || undefined,
        new_password: this.newPassword || undefined,
      });
      this.currentPassword = '';
      this.newPassword = '';
      await this.toast('Profile updated successfully!');
    } catch (err: any) {
      await this.toast(err?.error?.error ?? 'Failed to update profile. Please try again.');
    } finally {
      this.saving = false;
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
      this.addresses = await this.addressSvc.getAll();
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
              this.addresses = await this.addressSvc.getAll();
            } catch {
              await this.toast('Could not delete this address. Please try again.');
            }
          },
        },
      ],
    });
    await alert.present();
  }

  async cancelOrder(order: Order): Promise<void> {
    const alert = await this.alertCtrl.create({
      header: 'Cancel this order?',
      buttons: [
        { text: 'No', role: 'cancel' },
        {
          text: 'Yes, cancel',
          role: 'destructive',
          handler: async () => {
            try {
              await this.orderSvc.cancelOrder(order.id);
              this.orders = await this.orderSvc.getOrders();
            } catch {
              await this.toast('Could not cancel this order.');
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
      this.wishlist = await this.wishlistSvc.getWishlist();
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

  async logout(): Promise<void> {
    await this.auth.logout();
    this.profile = null;
    this.router.navigateByUrl('/tabs/home');
  }

  private async toast(message: string): Promise<void> {
    const t = await this.toastCtrl.create({ message, duration: 2000, position: 'bottom' });
    await t.present();
  }
}
