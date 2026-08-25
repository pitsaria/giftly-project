import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, timeout } from 'rxjs';
import { environment } from '../../environments/environment';
import { ApiResponse } from './models';

// Render's free tier spins the backend down after inactivity — the first
// request after a while can take 50+ seconds to wake it back up. Give
// requests generous room for that before treating them as failed, so a cold
// start shows a retryable error instead of leaving the UI stuck forever.
const REQUEST_TIMEOUT_MS = 60_000;

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
    return this.http.get<ApiResponse<T>>(this.base, { params: httpParams }).pipe(timeout(REQUEST_TIMEOUT_MS));
  }

  post<T>(route: string, body: unknown, params: Record<string, string | number> = {}): Observable<ApiResponse<T>> {
    let httpParams = new HttpParams().set('route', route);
    for (const key of Object.keys(params)) {
      httpParams = httpParams.set(key, String(params[key]));
    }
    return this.http.post<ApiResponse<T>>(this.base, body, { params: httpParams }).pipe(timeout(REQUEST_TIMEOUT_MS));
  }

  put<T>(route: string, body: unknown, params: Record<string, string | number> = {}): Observable<ApiResponse<T>> {
    let httpParams = new HttpParams().set('route', route);
    for (const key of Object.keys(params)) {
      httpParams = httpParams.set(key, String(params[key]));
    }
    return this.http.put<ApiResponse<T>>(this.base, body, { params: httpParams }).pipe(timeout(REQUEST_TIMEOUT_MS));
  }

  delete<T>(route: string, params: Record<string, string | number> = {}): Observable<ApiResponse<T>> {
    let httpParams = new HttpParams().set('route', route);
    for (const key of Object.keys(params)) {
      httpParams = httpParams.set(key, String(params[key]));
    }
    return this.http.delete<ApiResponse<T>>(this.base, { params: httpParams }).pipe(timeout(REQUEST_TIMEOUT_MS));
  }

  postFormData<T>(route: string, formData: FormData, params: Record<string, string | number> = {}): Observable<ApiResponse<T>> {
    let httpParams = new HttpParams().set('route', route);
    for (const key of Object.keys(params)) {
      httpParams = httpParams.set(key, String(params[key]));
    }
    return this.http.post<ApiResponse<T>>(this.base, formData, { params: httpParams }).pipe(timeout(REQUEST_TIMEOUT_MS));
  }
}
