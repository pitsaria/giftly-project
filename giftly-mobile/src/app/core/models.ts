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
}

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
