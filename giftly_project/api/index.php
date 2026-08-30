<?php
// api/index.php

require_once 'config/database.php';

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_GET['route']) ? $_GET['route'] : '';
$headers = getallheaders();
$input = json_decode(file_get_contents('php://input'), true);

// Simple router
switch($path) {
    // === AUTHENTICATION SERVICE ===
    case 'auth/login':
        require_once 'services/AuthService.php';
        $auth = new AuthService($conn);
        if ($method == 'POST') {
            $auth->login($input);
        } else {
            sendError('Method not allowed', 405);
        }
        break;
        
    case 'auth/register':
        require_once 'services/AuthService.php';
        $auth = new AuthService($conn);
        if ($method == 'POST') {
            $auth->register($input);
        } else {
            sendError('Method not allowed', 405);
        }
        break;
        
    case 'auth/logout':
        require_once 'services/AuthService.php';
        $auth = new AuthService($conn);
        if ($method == 'POST') {
            $auth->logout($headers);
        } else {
            sendError('Method not allowed', 405);
        }
        break;
        
    case 'auth/verify':
        require_once 'services/AuthService.php';
        $auth = new AuthService($conn);
        if ($method == 'GET') {
            $auth->verify($headers);
        } else {
            sendError('Method not allowed', 405);
        }
        break;

    case 'auth/forgot-password':
        require_once 'services/AuthService.php';
        $auth = new AuthService($conn);
        if ($method == 'POST') {
            $auth->forgotPassword($input);
        } else {
            sendError('Method not allowed', 405);
        }
        break;

    case 'auth/reset-password':
        require_once 'services/AuthService.php';
        $auth = new AuthService($conn);
        if ($method == 'POST') {
            $auth->resetPassword($input);
        } else {
            sendError('Method not allowed', 405);
        }
        break;

    case 'auth/google':
        require_once 'services/AuthService.php';
        $auth = new AuthService($conn);
        if ($method == 'POST') {
            $auth->googleLogin($input);
        } elseif ($method == 'GET') {
            $auth->googleConfig();
        } else {
            sendError('Method not allowed', 405);
        }
        break;

    // === PRODUCT SERVICE ===
    case 'products':
        require_once 'services/ProductService.php';
        $product = new ProductService($conn);
        if ($method == 'GET') {
            $product->getAll($_GET);
        } elseif ($method == 'POST') {
            $product->create($input, $headers);
        } else {
            sendError('Method not allowed', 405);
        }
        break;
        
    case 'products/single':
        require_once 'services/ProductService.php';
        $product = new ProductService($conn);
        if ($method == 'GET' && isset($_GET['id'])) {
            $product->getOne($_GET['id']);
        } elseif ($method == 'PUT' && isset($_GET['id'])) {
            $product->update($_GET['id'], $input, $headers);
        } elseif ($method == 'DELETE' && isset($_GET['id'])) {
            $product->delete($_GET['id'], $headers);
        } else {
            sendError('Missing product ID or method not allowed', 400);
        }
        break;

    case 'categories':
        require_once 'services/ProductService.php';
        $product = new ProductService($conn);
        if ($method == 'GET') {
            $product->getCategories();
        } else {
            sendError('Method not allowed', 405);
        }
        break;

    // === CART SERVICE ===
    case 'cart':
        require_once 'services/CartService.php';
        $cart = new CartService($conn);
        if ($method == 'GET') {
            $cart->getCart($headers);
        } elseif ($method == 'POST') {
            $cart->addToCart($input, $headers);
        } else {
            sendError('Method not allowed', 405);
        }
        break;
        
    case 'cart/update':
        require_once 'services/CartService.php';
        $cart = new CartService($conn);
        if ($method == 'PUT') {
            $cart->updateQuantity($input, $headers);
        } else {
            sendError('Method not allowed', 405);
        }
        break;
        
    case 'cart/remove':
        require_once 'services/CartService.php';
        $cart = new CartService($conn);
        if ($method == 'DELETE') {
            $cart->removeItem($_GET, $headers);
        } else {
            sendError('Method not allowed', 405);
        }
        break;

        // 🚀 ADD THIS NEW ROUTE
case 'cart/verify-stock':
    require_once 'services/CartService.php';
    $cart = new CartService($conn);
    if ($method == 'POST') {
        $cart->verifyStock($input, $headers);
    } else {
        sendError('Method not allowed', 405);
    }
    break;

    // 🚀 NEW: CART STOCK VERIFICATION
    case 'cart/verify-stock':
        require_once 'services/CartService.php';
        $cart = new CartService($conn);
        if ($method == 'POST') {
            $cart->verifyStock($input, $headers);
        } else {
            sendError('Method not allowed', 405);
        }
        break;

    // === ORDER SERVICE ===
    case 'orders':
        require_once 'services/OrderService.php';
        $order = new OrderService($conn);
        if ($method == 'GET') {
            $order->getOrders($headers, $_GET);
        } elseif ($method == 'POST') {
            $order->createOrder($input, $headers);
        } else {
            sendError('Method not allowed', 405);
        }
        break;
        
    case 'orders/single':
        require_once 'services/OrderService.php';
        $order = new OrderService($conn);
        if ($method == 'GET' && isset($_GET['id'])) {
            $order->getOrderDetails($_GET['id'], $headers);
        } else {
            sendError('Missing order ID', 400);
        }
        break;
        
    case 'orders/cancel':
        require_once 'services/OrderService.php';
        $order = new OrderService($conn);
        if ($method == 'PUT' && isset($_GET['id'])) {
            $order->cancelOrder($_GET['id'], $input, $headers);
        } else {
            sendError('Missing order ID', 400);
        }
        break;

    case 'orders/received':
        require_once 'services/OrderService.php';
        $order = new OrderService($conn);
        if ($method == 'PUT' && isset($_GET['id'])) {
            $order->markReceived($_GET['id'], $headers);
        } else {
            sendError('Missing order ID', 400);
        }
        break;

    // === BUILD-A-BOX SERVICE ===
    case 'box/sizes':
        require_once 'services/BoxService.php';
        $box = new BoxService($conn);
        if ($method == 'GET') {
            $box->sizes();
        } else {
            sendError('Method not allowed', 405);
        }
        break;

    case 'box/products':
        require_once 'services/BoxService.php';
        $box = new BoxService($conn);
        if ($method == 'GET') {
            $box->products($_GET);
        } else {
            sendError('Method not allowed', 405);
        }
        break;

    case 'boxes':
        require_once 'services/BoxService.php';
        $box = new BoxService($conn);
        if ($method == 'GET') {
            $box->listBoxes($headers);
        } elseif ($method == 'POST') {
            $box->save($input, $headers);
        } else {
            sendError('Method not allowed', 405);
        }
        break;

    case 'boxes/single':
        require_once 'services/BoxService.php';
        $box = new BoxService($conn);
        if ($method == 'GET' && isset($_GET['id'])) {
            $box->getBox($_GET['id'], $headers);
        } elseif ($method == 'DELETE' && isset($_GET['id'])) {
            $box->deleteBox($_GET['id'], $headers);
        } else {
            sendError('Missing box ID or method not allowed', 400);
        }
        break;

    case 'boxes/checkout':
        require_once 'services/BoxService.php';
        $box = new BoxService($conn);
        if ($method == 'POST') {
            $box->checkout($input, $headers);
        } else {
            sendError('Method not allowed', 405);
        }
        break;

    // === REVIEW SERVICE ===
    case 'reviews':
        require_once 'services/ReviewService.php';
        $review = new ReviewService($conn);
        if ($method == 'GET' && isset($_GET['product_id'])) {
            $review->get($_GET['product_id'], $headers);
        } elseif ($method == 'POST') {
            $review->create($input, $headers);
        } else {
            sendError('Missing product ID or method not allowed', 400);
        }
        break;

    // === CONTACT SERVICE ===
    case 'contact':
        require_once 'services/ContactService.php';
        $contact = new ContactService($conn);
        if ($method == 'POST') {
            $contact->create($input);
        } else {
            sendError('Method not allowed', 405);
        }
        break;

    // === WISHLIST SERVICE ===
    case 'wishlist':
        require_once 'services/WishlistService.php';
        $wishlist = new WishlistService($conn);
        if ($method == 'GET') {
            $wishlist->getWishlist($headers);
        } else {
            sendError('Method not allowed', 405);
        }
        break;

    case 'wishlist/toggle':
        require_once 'services/WishlistService.php';
        $wishlist = new WishlistService($conn);
        if ($method == 'POST') {
            $wishlist->toggle($input, $headers);
        } else {
            sendError('Method not allowed', 405);
        }
        break;

    // === ADDRESS SERVICE ===
    case 'addresses':
        require_once 'services/AddressService.php';
        $addressSvc = new AddressService($conn);
        if ($method == 'GET') {
            $addressSvc->getAll($headers);
        } elseif ($method == 'POST') {
            $addressSvc->create($input, $headers);
        } else {
            sendError('Method not allowed', 405);
        }
        break;

    case 'addresses/single':
        require_once 'services/AddressService.php';
        $addressSvc = new AddressService($conn);
        if ($method == 'DELETE' && isset($_GET['id'])) {
            $addressSvc->delete($_GET['id'], $headers);
        } else {
            sendError('Missing address ID or method not allowed', 400);
        }
        break;

    // === PROFILE SERVICE ===
    case 'profile':
        require_once 'services/ProfileService.php';
        $profileSvc = new ProfileService($conn);
        if ($method == 'GET') {
            $profileSvc->getProfile($headers);
        } elseif ($method == 'PUT') {
            $profileSvc->updateProfile($input, $headers);
        } else {
            sendError('Method not allowed', 405);
        }
        break;

    case 'profile/picture':
        require_once 'services/ProfileService.php';
        $profileSvc = new ProfileService($conn);
        if ($method == 'POST') {
            $profileSvc->uploadPicture($headers);
        } else {
            sendError('Method not allowed', 405);
        }
        break;

    default:
        sendError('Invalid API endpoint', 404);
        break;
}
?>