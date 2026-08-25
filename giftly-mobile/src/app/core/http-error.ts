import { HttpErrorResponse } from '@angular/common/http';
import { TimeoutError } from 'rxjs';

// Turns any error thrown by ApiService into a precise, user-visible string
// (status code + server message) instead of a generic "something went
// wrong" — so a real failure is diagnosable from what's shown on screen.
export function describeError(err: unknown): string {
  if (err instanceof TimeoutError) {
    return 'The server took too long to respond (60s timeout) — it may be waking up from sleep. Please try again.';
  }
  if (err instanceof HttpErrorResponse) {
    if (err.status === 0) {
      return 'Could not reach the server (network error or CORS). Please check your connection and try again.';
    }
    const serverMessage = (err.error && typeof err.error === 'object' && 'error' in err.error)
      ? String((err.error as { error: unknown }).error)
      : err.statusText;
    return `Server error ${err.status}: ${serverMessage}`;
  }
  if (err instanceof Error) {
    return err.message;
  }
  return 'An unexpected error occurred. Please try again.';
}
