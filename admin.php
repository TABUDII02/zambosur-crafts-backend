<?php
// 1. Core Headers & Error Reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// CRITICAL: Access-Control-Allow-Origin MUST match your frontend and cannot be "*"
header("Access-Control-Allow-Origin: https://zambosur-crafts.onrender.com");
header("Access-Control-Allow-Credentials: true"); // REQUIRED for sessions/cookies
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, ngrok-skip-browser-warning");

// 2. Handle Preflight (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 3. Environment Setup
require_once 'config.php';
require_once 'admin-auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? '';
$path = trim($path, '/'); 
$input = file_get_contents('php://input');
$data = json_decode($input, true) ?? [];

// --- 4. Public Endpoints ---
// This handles BOTH "admin/login" and "login"
if ($method === 'POST' && ($path === 'admin/login' || $path === 'login')) {
    header('Content-Type: application/json');
    echo json_encode(adminLogin($data['username'] ?? '', $data['password'] ?? ''));
    exit; // STOP HERE so index.php doesn't run
}

// --- 5. Protected Admin Routes ---
if (strpos($path, 'admin') === 0) {
    
    // Check login
    if (!isAdminLoggedIn()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized. Please log in again.']);
        exit;
    }

    $segments = explode('/', $path);

    // If the path is JUST "admin" or "admin/", show a default message or error
    if (count($segments) < 2) {
        http_response_code(400);
        echo json_encode(['error' => 'No admin sub-endpoint specified']);
        exit;
    }

    // ROUTE: admin/products
    if ($segments[1] === 'products') {
        handleProducts($path, $method, $data);
        exit;
    } 

    // ROUTE: admin/orders
    if ($segments[1] === 'orders') {
        if ($path === 'admin/orders/update') {
            updateOrderStatus($data['order_id'] ?? null, $data);
        } else {
            getAdminOrders();
        }
        exit;
    }

    // ROUTE: admin/customers
    if (isset($segments[1]) && $segments[1] === 'customers') {
        if ($path === 'admin/customers/delete') {
            deleteCustomer($data['id'] ?? null);
        } else {
            getAdminCustomers();
        }
        exit;
    }

    // ROUTE: admin/categories
    if (isset($segments[1]) && $segments[1] === 'categories') {
        getAdminCategories();
        exit;
    }
}

// Default 404
http_response_code(404);
echo json_encode(['error' => 'Endpoint not found: ' . $path]);

// --- 6. Route Handler Functions ---

function handleProducts($path, $method, $data) {
    $segments = explode('/', trim($path, '/'));
    $id = isset($segments[2]) ? $segments[2] : null;
    $is_id_numeric = is_numeric($id);

    switch ($method) {
        case 'GET':
            if ($is_id_numeric) getProductById($id);
            else getAdminProducts();
            break;
        case 'POST':
            createProduct(); // Uses $_POST/$_FILES internally
            break;
        case 'PUT':
            if ($is_id_numeric) updateProduct($id, $data);
            else {
                http_response_code(400);
                echo json_encode(['error' => 'ID required for update']);
            }
            break;
        case 'DELETE':
            if ($is_id_numeric) deleteProduct($id);
            else {
                http_response_code(400);
                echo json_encode(['error' => 'ID required for delete']);
            }
            break;
    }
}

// --- 7. Database Handlers (Keeping your logic) ---

function createProduct() {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = (float)($_POST['price'] ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);
    $is_best_seller = (int)($_POST['is_best_seller'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    
    $image_url = 'images/default.jpg'; 
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $upload_dir = '../images/'; 
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $file_name = time() . '_' . basename($_FILES['image']['name']); // Add timestamp to prevent overwrite
        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $file_name)) {
            $image_url = 'images/' . $file_name;
        }
    }

    if (empty($name) || $price <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing fields']);
        return;
    }

    $conn = getDBConnection();
    $stmt = $conn->prepare("INSERT INTO products (name, description, price, image_url, category_id, is_best_seller, quantity) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssdsiii", $name, $description, $price, $image_url, $category_id, $is_best_seller, $quantity);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'id' => $conn->insert_id]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => $stmt->error]);
    }
    $conn->close();
}

function updateProduct($id, $data) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT image_url FROM products WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();

    if (!$current) {
        echo json_encode(['error' => 'Product not found']);
        return;
    }

    $name = $data['name'] ?? '';
    $desc = $data['description'] ?? '';
    $price = $data['price'] ?? 0;
    $cat = $data['category_id'] ?? 1;
    $best = $data['is_best_seller'] ?? 0;
    $qty = $data['quantity'] ?? 0;
    $img = $data['image_url'] ?? $current['image_url'];

    $update = $conn->prepare("UPDATE products SET name=?, description=?, price=?, image_url=?, category_id=?, is_best_seller=?, quantity=? WHERE id=?");
    $update->bind_param("ssdsiiii", $name, $desc, $price, $img, $cat, $best, $qty, $id);
    
    if ($update->execute()) echo json_encode(['success' => true]);
    else echo json_encode(['error' => $update->error]);
    $conn->close();
}

function deleteProduct($id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo json_encode(['success' => $stmt->affected_rows > 0]);
    $conn->close();
}
?>
