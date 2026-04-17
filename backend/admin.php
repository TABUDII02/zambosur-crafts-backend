<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Headers: Content-Type, ngrok-skip-browser-warning");

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}
// 1. Get the path and clean it
$path = $_SERVER['PATH_INFO'] ?? '';
$path = trim($path, '/'); 

// 2. Break the path into pieces: ['admin', 'products', '91']
$segments = explode('/', $path);

// 3. Routing Logic
if (isset($segments[0]) && $segments[0] === 'admin') {
    if (isset($segments[1]) && $segments[1] === 'products') {
        
        // Check if there is an ID at the end: admin/products/{id}
        $id = isset($segments[2]) ? $segments[2] : null;

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if ($id && is_numeric($id)) {
                getProductById($id); // This will now trigger for ID 91
            } else {
                getAdminProducts(); // This triggers for the whole list
            }
        } elseif ($method === 'PUT' && $id) {
            updateProduct($id, $data);
        } elseif ($method === 'DELETE' && $id) {
            deleteProduct($id);
        }
        exit; // Ensure the script stops here
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once 'config.php';
require_once 'admin-auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = file_get_contents('php://input');
$data = json_decode($input, true) ?? [];

$path = $_SERVER['PATH_INFO'] ?? $_SERVER['REQUEST_URI'] ?? '';
$path = str_replace('/zambosur_craft/backend/index.php', '', $path);
$path = trim($path, '/');

// --- 1. Public Endpoints ---
if ($method === 'POST' && ($path === 'admin/login' || $path === 'login')) {
    echo json_encode(adminLogin($data['username'] ?? '', $data['password'] ?? ''));
    exit;
}

if ($path === 'categories' && $method === 'GET') {
    getAdminCategories();
    exit;
}

// --- 2. Admin Route Handling ---
// We check the path FIRST, then check if logged in inside the specific routes 
// OR check login here for all admin/ routes.

if (strpos($path, 'admin/') === 0) {
    if (!isAdminLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized. Please log in again.']);
        exit;
    }

    if (strpos($path, 'admin/products') !== false) {
        handleProducts($path, $method, $data);
        exit;
    } 
    // CHANGE THIS: Use strpos instead of === for orders
    elseif (strpos($path, 'admin/orders') !== false) {
    if ($path === 'admin/orders/update') {
        $id = $data['order_id'] ?? null; 
        
        updateOrderStatus($id, $data);
    } else {
        getAdminOrders();
    }
    exit;
    }
    elseif (strpos($path, 'admin/customers') !== false) {
        if ($path === 'admin/customers/delete') {
            $id = $data['id'] ?? null;
            deleteCustomer($id);
        } else {
            getAdminCustomers();
        }
        exit;
    }
    elseif ($path === 'admin/categories') {
        getAdminCategories();
        exit;
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found: ' . $path]);
        exit;
    }
}
// --- 3. Route Handlers ---

function handleProducts($path, $method = null, $data = null) {
    // 1. Force $method to have a value even if the argument was empty
    // This fixes the "Undefined variable" error on lines 36 and 38
    if ($method === null) {
        $method = $_SERVER['REQUEST_METHOD'];
    }

    // 2. Extract the ID
    $parts = explode('/', trim($path, '/'));
    $id = end($parts);
    $is_id_numeric = is_numeric($id);

    // 3. Ensure $data is populated for PUT/POST
    if ($data === null) {
        $data = json_decode(file_get_contents('php://input'), true);
    }
    // 3. Routing
    switch ($method) {
        case 'GET':
            if ($is_id_numeric) {
                getProductById($id);
            } else {
                getAdminProducts();
            }
            break;

        case 'POST':
            createProduct($data);
            break;

        case 'PUT':
            if ($is_id_numeric) {
                // Ensure data isn't null before calling update
                if (!empty($data)) {
                    updateProduct($id, $data);
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'No data provided for update']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Numeric ID required for update']);
            }
            break;

        case 'DELETE':
            if ($is_id_numeric) {
                deleteProduct($id);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'ID required for deletion']);
            }
            break;

        case 'OPTIONS':
            http_response_code(200);
            exit;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
}

// --- 4. Database Fetching Functions ---

function createProduct() { // No need to pass $data
    // 1. Pull data directly from $_POST (where FormData lives)
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = (float)($_POST['price'] ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);
    $is_best_seller = (int)($_POST['is_best_seller'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    
    // 2. Handle the Image (Functional version)
    $image_url = 'images/default.jpg'; // Default if no image is uploaded

    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        // Define where you want to save the file
        // Use ../ if your index.php is inside an 'api' or 'backend' folder
        $upload_dir = '../images/'; 
        
        // Create the folder if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_name = basename($_FILES['image']['name']);
        $target_file = $upload_dir . $file_name;

        // Actually move the file from temporary storage to your images folder
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            // This is the path that will be saved in the database
            $image_url = 'images/' . $file_name;
        } else {
            // Optional: log error if move fails
            error_log("Failed to move uploaded file.");
        }
    }

    // 3. Validation Check
    if (empty($name) || $price <= 0 || $category_id <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'Missing required fields',
            'received' => $_POST // This debugs exactly what arrived in Pagadian
        ]);
        return;
    }

    $conn = getDBConnection();
    $query = "INSERT INTO products (name, description, price, image_url, category_id, is_best_seller, quantity) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
              
    $stmt = $conn->prepare($query);
    
    // Ensure types match: s (string), d (double/float), i (integer)
    $stmt->bind_param("ssdsiii", $name, $description, $price, $image_url, $category_id, $is_best_seller, $quantity);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Product created successfully', 
            'id' => $conn->insert_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }
    
    $stmt->close();
    $conn->close();
}
function updateProduct($id, $data) {
    $conn = getDBConnection();
    
    // 1. Fetch the CURRENT product data first to get the existing image
    $currentStmt = $conn->prepare("SELECT image_url FROM products WHERE id = ?");
    $currentStmt->bind_param("i", $id);
    $currentStmt->execute();
    $result = $currentStmt->get_result();
    $currentProduct = $result->fetch_assoc();
    $currentStmt->close();

    if (!$currentProduct) {
        echo json_encode(['success' => false, 'error' => 'Product not found']);
        return;
    }

    // 2. Fallbacks
    $name = $data['name'] ?? '';
    $desc = $data['description'] ?? '';
    $price = $data['price'] ?? 0;
    $cat = $data['category_id'] ?? 1;
    $best = $data['is_best_seller'] ?? 0;
    $qty = $data['quantity'] ?? 0;

    // 3. IMAGE LOGIC: If $data['image_url'] is empty, use the existing one from the DB
    $img = (!empty($data['image_url'])) ? $data['image_url'] : $currentProduct['image_url'];

    // 4. Update the database
    $stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, price = ?, image_url = ?, category_id = ?, is_best_seller = ?, quantity = ? WHERE id = ?");
    
    $stmt->bind_param("ssdsiiii", $name, $desc, $price, $img, $cat, $best, $qty, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => $stmt->error]);
    }
    
    $stmt->close();
    $conn->close();
    exit;
}

function deleteProduct($id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['message' => 'Product deleted']);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
    }
    $conn->close();
}