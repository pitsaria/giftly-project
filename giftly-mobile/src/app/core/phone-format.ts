/**
 * Derives the 10-digit form app-ph-phone-input expects (no leading 0 / +63)
 * from a freely-typed stored phone number, e.g. from a saved recipient
 * ("09171234567", "+639171234567", "9171234567" all -> "9171234567").
 */
export function phoneDigitsFromStored(phone: string | null | undefined): string {
  const digits = (phone ?? '').replace(/\D/g, '');
  return digits.slice(-10);
}
