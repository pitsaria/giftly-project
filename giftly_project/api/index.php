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
            $auth->logout();
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
            $order->cancelOrder($_GET['id'], $headers);
        } else {
            sendError('Missing order ID', 400);
        }
        break;

    default:
        sendError('Invalid API endpoint', 404);
        break;
}
?>