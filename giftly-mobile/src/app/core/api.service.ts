import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { ApiResponse } from './models';

// Thin wrapper around api/index.php's ?route= router.
@Injectable({ providedIn: 'root' })
export class ApiService {
  private http = inject(HttpClient);
  private base = environment.apiUrl;

  get<T>(route: string, params: Record<string, string | number> = {}): Observable<ApiResponse<T>> {
    let httpParams = new HttpParams().set('route', route);
    for (const key of Object.keys(params)) {
      httpParams = httpParams.set(key, String(params[key]));
    }
    return this.http.get<ApiResponse<T>>(this.base, { params: httpParams });
  }

  post<T>(route: string, body: unknown, params: Record<string, string | number> = {}): Observable<ApiResponse<T>> {
    let httpParams = new HttpParams().set('route', route);
    for (const key of Object.keys(params)) {
      httpParams = httpParams.set(key, String(params[key]));
    }
    return this.http.post<ApiResponse<T>>(this.base, body, { params: httpParams });
  }

  put<T>(route: string, body: unknown, params: Record<string, string | number> = {}): Observable<ApiResponse<T>> {
    let httpParams = new HttpParams().set('route', route);
    for (const key of Object.keys(params)) {
      httpParams = httpParams.set(key, String(params[key]));
    }
    return this.http.put<ApiResponse<T>>(this.base, body, { params: httpParams });
  }

  delete<T>(route: string, params: Record<string, string | number> = {}): Observable<ApiResponse<T>> {
    let httpParams = new HttpParams().set('route', route);
    for (const key of Object.keys(params)) {
      httpParams = httpParams.set(key, String(params[key]));
    }
    return this.http.delete<ApiResponse<T>>(this.base, { params: httpParams });
  }

  postFormData<T>(route: string, formData: FormData, params: Record<string, string | number> = {}): Observable<ApiResponse<T>> {
    let httpParams = new HttpParams().set('route', route);
    for (const key of Object.keys(params)) {
      httpParams = httpParams.set(key, String(params[key]));
    }
    return this.http.post<ApiResponse<T>>(this.base, formData, { params: httpParams });
  }
}
