import { Pipe, PipeTransform } from '@angular/core';
import { environment } from '../../environments/environment';

/**
 * Resolves a stored product/profile image value to a usable <img src>.
 *
 * The backend used to store just a filename that lived under /uploads. Images
 * added through the admin panel now go to Supabase Storage and the full public
 * URL is stored instead. This pipe handles both:
 *
 *   'product_123.png'              -> `${environment.uploadsUrl}/product_123.png`
 *   'https://xyz.supabase.co/...'  -> returned unchanged
 *   '' / null / undefined          -> '' (let the <img> show nothing / its alt)
 *
 * Usage:  <img [src]="product.image | imgUrl" [alt]="product.name" />
 */
@Pipe({ name: 'imgUrl', standalone: true })
export class ImgUrlPipe implements PipeTransform {
  transform(value: string | null | undefined): string {
    const v = (value ?? '').trim();
    if (!v) return '';
    if (/^https?:\/\//i.test(v)) return v;
    return `${environment.uploadsUrl}/${v.replace(/^\/+/, '')}`;
  }
}
