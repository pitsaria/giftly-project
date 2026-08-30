// Shared client-side card-field helpers for checkout and box checkout.
// Mirrors the validation in giftly_project/checkout_selected.php's card block —
// the server re-validates and only ever stores the last 4 digits + holder name.

export interface CardFields {
  cardHolder: string;
  cardNumber: string;
  cardExpiry: string;
  cardCvc: string;
}

/** Group digits in blocks of four as the user types. */
export function formatCardNumber(value: string): string {
  return value
    .replace(/\D/g, '')
    .slice(0, 19)
    .replace(/(.{4})/g, '$1 ')
    .trim();
}

/** MM/YY as the user types. */
export function formatCardExpiry(value: string): string {
  const v = value.replace(/\D/g, '').slice(0, 4);
  return v.length > 2 ? `${v.slice(0, 2)}/${v.slice(2)}` : v;
}

export function formatCvc(value: string): string {
  return value.replace(/\D/g, '').slice(0, 4);
}

/** Returns an error string, or null when the card fields are valid. */
export function validateCard(fields: CardFields): string | null {
  const digits = fields.cardNumber.replace(/\D/g, '');
  if (!fields.cardHolder.trim()) return 'Please enter the name on the card.';
  if (digits.length < 13 || digits.length > 19) return 'Please enter a valid card number.';
  const m = fields.cardExpiry.trim().match(/^(0[1-9]|1[0-2])\s*\/\s*([0-9]{2})$/);
  if (!m) return 'Card expiry must be in MM/YY format.';
  const expYear = 2000 + Number(m[2]);
  const expMonth = Number(m[1]);
  const now = new Date();
  if (expYear < now.getFullYear() || (expYear === now.getFullYear() && expMonth < now.getMonth() + 1)) {
    return 'That card has expired.';
  }
  const cvc = fields.cardCvc.replace(/\D/g, '');
  if (cvc.length < 3 || cvc.length > 4) return 'Please enter a valid CVC.';
  return null;
}
