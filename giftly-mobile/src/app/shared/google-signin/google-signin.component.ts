import {
  AfterViewInit,
  Component,
  ElementRef,
  EventEmitter,
  Output,
  ViewChild,
  inject,
  signal,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { AuthService } from '../../core/auth.service';

// "Continue with Google" button, mirroring giftly_project/google_signin.php.
// Loads Google Identity Services, renders Google's own button, and emits the
// signed ID token (`credential`). Renders nothing when the server has no
// GOOGLE_CLIENT_ID configured or when GIS can't load — same graceful
// degradation as the website.
const GIS_SRC = 'https://accounts.google.com/gsi/client';

@Component({
  selector: 'app-google-signin',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="g-wrap" [hidden]="!ready()">
      <div class="g-divider" *ngIf="ready()"><span>or</span></div>
      <div #btn class="g-slot"></div>
    </div>
  `,
  styles: [
    `
      .g-wrap {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
        margin: 16px 0 4px;
      }
      .g-divider {
        width: 100%;
        text-align: center;
        border-bottom: 1px solid #eee;
        line-height: 0.1em;
        margin: 4px 0 8px;
      }
      .g-divider span {
        background: var(--ion-background-color, #fcfcfc);
        padding: 0 12px;
        color: #999;
        font-size: 12px;
      }
      .g-slot {
        min-height: 40px;
      }
    `,
  ],
})
export class GoogleSigninComponent implements AfterViewInit {
  @ViewChild('btn') btn!: ElementRef<HTMLDivElement>;
  @Output() credential = new EventEmitter<string>();

  private auth = inject(AuthService);
  readonly ready = signal(false);

  async ngAfterViewInit(): Promise<void> {
    const clientId = await this.auth.googleClientId();
    if (!clientId) return;

    try {
      await this.loadGis();
      const google = (window as unknown as { google?: any }).google;
      if (!google?.accounts?.id) return;

      google.accounts.id.initialize({
        client_id: clientId,
        callback: (resp: { credential?: string }) => {
          if (resp?.credential) this.credential.emit(resp.credential);
        },
      });
      google.accounts.id.renderButton(this.btn.nativeElement, {
        theme: 'outline',
        size: 'large',
        text: 'continue_with',
        shape: 'pill',
        logo_alignment: 'center',
        width: 300,
      });
      this.ready.set(true);
    } catch {
      // GIS failed to load (offline, blocked, native WebView) — stay hidden.
    }
  }

  private loadGis(): Promise<void> {
    const w = window as unknown as { __gisLoading?: Promise<void> };
    if (w.__gisLoading) return w.__gisLoading;
    w.__gisLoading = new Promise<void>((resolve, reject) => {
      if (document.querySelector(`script[src="${GIS_SRC}"]`)) {
        resolve();
        return;
      }
      const s = document.createElement('script');
      s.src = GIS_SRC;
      s.async = true;
      s.defer = true;
      s.onload = () => resolve();
      s.onerror = () => reject(new Error('GIS failed to load'));
      document.head.appendChild(s);
    });
    return w.__gisLoading;
  }
}
