export interface ApiResponse<T> {
  status: 'success' | 'error';
  message: string;
  data: T;
}

export interface User {
  id: number;
  name: string;
  email: string;
  role: 'admin' | 'customer';
}

export interface Product {
  id: number;
  name: string;
  description: string;
  price: string;
  image: string;
  quantity: number;
  category_id: number;
  // Attached by the products list endpoint (published-review aggregates).
  avg_rating?: string;
  review_count?: number;
  product_type?: 'catalog' | 'occasion_box' | 'basket';
  is_active?: boolean;
  unavailable?: boolean;
}

export type ProductType = 'catalog' | 'occasion_box' | 'basket';

export interface Category {
  id: number;
  name: string;
}

export interface CartItem {
  cart_id: number;
  quantity: number;
  id: number;
  name: string;
  description: string;
  price: string;
  image: string;
  category_id: number;
  stock: number;
  subtotal: number;
  // Product was deactivated by the shop while it sat in the cart.
  is_active?: boolean;
  unavailable?: boolean;
}

export interface Cart {
  items: CartItem[];
  total: number;
  item_count: number;
}

export interface Address {
  id: number;
  user_id: number;
  label: string;
  address: string;
  city: string;
  province: string;
  zip: string;
  created_at: string;
  is_default?: boolean;
}

export interface WishlistItem extends Product {
  wishlist_id: number;
  created_at: string;
}

export interface WishlistData {
  in_stock: WishlistItem[];
  out_of_stock: WishlistItem[];
  total: number;
}

export interface Order {
  id: number;
  user_id: number;
  total_amount: string;
  status: 'pending' | 'shipped' | 'delivered' | 'cancelled';
  created_at: string;
  fullname: string;
  address: string;
  city: string;
  recipient_name: string;
  payment_method: string;
  gift_message: string;
  sender_phone: string;
  recipient_phone: string;
  delivery_date: string;
  delivery_time: string;
  items?: OrderItem[];
  // Cancellation-request flow (admin must approve before an order is cancelled)
  cancel_status?: 'none' | 'requested' | 'approved' | 'rejected';
  cancel_reason?: string | null;
  cancel_admin_note?: string | null;
  // Set once the customer confirms the order arrived — unlocks reviewing items.
  received_at?: string | null;
  // Card payments keep only the last 4 digits + cardholder name.
  card_last4?: string | null;
  card_holder?: string | null;
  // Online (PayMongo) payments.
  payment_status?: 'unpaid' | 'paid' | 'failed';
  paid_at?: string | null;
}

export interface OrderItem {
  id: number;
  order_id: number;
  product_id: number;
  quantity: number;
  price: string;
  name: string;
  image: string;
}

export interface Profile {
  firstname: string;
  lastname: string;
  email: string;
  phone: string;
  profile_pic: string | null;
  order_count: number;
  address_count: number;
}

// === Build-a-Box ===

export interface BoxSize {
  id: number;
  code: string;
  name: string;
  max_items: number;
  price: number;
  sort_order?: number;
}

export interface BoxCardStyle {
  key: string;
  label: string;
  emoji: string;
}

export interface BoxProduct {
  id: number;
  name: string;
  description: string;
  price: number;
  image: string;
  quantity: number;
  category_id: number;
  rating: number;
  rating_count: number;
}

export interface BoxLineItem {
  product_id: number;
  name: string;
  price: number;
  image: string;
  quantity: number;
  stock: number;
  unavailable: 'removed' | 'out_of_stock' | 'low_stock' | 'discontinued' | null;
}

export interface Box {
  id: number;
  box_size_id: number;
  size_name: string;
  size_code: string;
  max_items: number;
  box_price: number;
  letter: string;
  card_style: string;
  status: 'saved' | 'in_cart' | 'ordered';
  updated_at: string | null;
  item_count: number;
  subtotal: number;
  total: number;
  issues: string[];
  items: BoxLineItem[];
}

// === Reviews ===

export interface Review {
  id: number;
  user_name: string;
  rating: number;
  comment: string;
  created_at: string;
}

export interface ReviewData {
  avg: number;
  count: number;
  reviews: Review[];
  my_review: Review | null;
  can_review: boolean;
}
