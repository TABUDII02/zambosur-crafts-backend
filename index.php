<?php

/** ZamboSur Crafts PHP Backend API **/

session_start();

header("Cross-Origin-Opener-Policy: same-origin-allow-popups");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Headers: Content-Type, ngrok-skip-browser-warning");





if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit; 
}


require_once 'config.php';
$conn = getDBConnection();
require_once 'admin-auth.php'; 
require_once 'admin.php'; // Ensure your product functions are included!

// Only load PHPMailer if the folder exists to prevent crashing
if (file_exists(__DIR__ . '/vendor/PHPMailer/src/PHPMailer.php')) {
    require_once __DIR__ . '/vendor/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/vendor/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/vendor/PHPMailer/src/SMTP.php';
    
    // Import namespaces
    header("X-Mailer-Status: Loaded"); 
} else {
    // This allows the rest of the app to run even if mailer is missing
    error_log("PHPMailer folder not found in /vendor/");
}

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$input = file_get_contents('php://input');
$data = json_decode($input, true) ?? [];

// 2. Reliable Path Detection
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME'];

// This removes the script name (index.php) and the folder path automatically
$path = str_replace([$script_name, dirname($script_name)], '', $request_uri);

// Remove query strings (?id=123) if they exist
$path = explode('?', $path)[0];

// Clean up 'api/' and slashes
$path = str_replace('api/', '', $path);
$path = trim($path, '/');

// 3. Create Segments for Routing
$segments = explode('/', $path);


/// 4. THE ROUTER


// --- ADMIN ROUTES ---
if (isset($segments[0]) && trim($segments[0]) === 'admin') {
    
    // Normalize the module name
    $module = isset($segments[1]) ? strtolower(trim($segments[1])) : '';

    if ($module === 'orders') {
        if (isset($segments[2]) && $segments[2] === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            // update logic here
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            getAdminOrders();
            exit;
        }
    } 
    
    // Use ELSEIF to ensure only one module runs
    elseif ($module === 'products') {
        handleProducts($path, $_SERVER['REQUEST_METHOD'], $data); 
        exit;
    }

    // THE CUSTOMER GATEWAY
    elseif ($module === 'customers') {
        // Check for DELETE action
        if (isset($segments[2]) && $segments[2] === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            // Ensure deleteCustomer() is defined and takes the ID
            $data = json_decode(file_get_contents("php://input"), true) ?? [];
            deleteCustomer((int)($data['id'] ?? 0)); 
            exit;
        }

        // Handle the GET request for the table
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            getAdminCustomers(); // This calls your function above
            exit;
        }
    }

    // If the code reaches here, it means $module was not 'orders', 'products', or 'customers'
    header('Content-Type: application/json');
    echo json_encode(["error" => "Endpoint not found: admin/" . $module]);
    exit;
}

// --- CUSTOMER AUTH ROUTES ---
$data = json_decode(file_get_contents("php://input"), true) ?? [];
if (isset($segments[0]) && $segments[0] === 'auth') {


    // 1. Handle Signup (The missing part)
    if (isset($segments[1]) && $segments[1] === 'signup') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            handleSignup($data); 
        } else {
            echo json_encode(['error' => 'Method not allowed']);
        }
        exit;
    }

    if (isset($segments[1]) && $segments[1] === 'login') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            handleSignin(); // This must match the name of your PHP function
        } else {
            echo json_encode(['error' => 'Method not allowed']);
        }
        exit;
    }

    // Handle Social Login
    if (isset($segments[1]) && $segments[1] === 'social-login') {
        handleSocialLogin($data); 
        exit;
    }

    // ADD THIS: Handle Profile Fetching
    if (isset($segments[1]) && $segments[1] === 'profile') {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            getCustomerProfile(); // This is the function we fixed earlier
        }
        exit;
    }

    // Handle Logout
    if (isset($segments[1]) && $segments[1] === 'logout') {
        session_start();
        session_destroy();
        echo json_encode(['success' => true]);
        exit;
    }
}

// --- USER ACTIONS (Cart, Wishlist, Saved Items) ---
if (isset($segments[0]) && $segments[0] === 'user') {
    $data = json_decode(file_get_contents("php://input"), true);
    $productId = $data['product_id'] ?? null;

    // --- 1. CART ---
    if (isset($segments[1]) && $segments[1] === 'cart') {
        if (isset($segments[2]) && $segments[2] === 'add' && $method === 'POST') {
            handleUserAction('cart', $productId);
            exit;
        }
        if (isset($segments[2]) && $segments[2] === 'all' && $method === 'GET') {
            getUserCart();
            exit;
        }
    }

    if (isset($segments[2]) && $segments[2] === 'count' && $method === 'GET') {
            $u_id = $_SESSION['user_id'] ?? null;
            if ($u_id) {
                // Fix: Include your actual configuration file
                require_once 'config.php'; 
                $conn = getDBConnection();

                // Check if $conn exists after requiring config.php
                if (isset($conn) && $conn !== null) {
                    $sql = "SELECT SUM(quantity) as total FROM cart WHERE customer_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $u_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    
                    echo json_encode([
                        'success' => true, 
                        'count' => (int)($row['total'] ?? 0)
                    ]);
                } else {
                    // Fallback if $conn is still not found
                    echo json_encode(['success' => false, 'error' => 'Database connection variable not found']);
                }
            } else {
                echo json_encode(['success' => true, 'count' => 0]);
            }
            exit;
    }
    if (isset($segments[2]) && $segments[2] === 'update-qty' && $method === 'POST') {
            $u_id = $_SESSION['user_id'] ?? null;
            $data = json_decode(file_get_contents("php://input"), true);
            
            $p_id = $data['product_id'] ?? null;
            $change = $data['change'] ?? null; // +1 or -1

            if ($u_id && $p_id && $change !== null) {
                require_once 'config.php';
                $conn = getDBConnection();

                // 1. Get the current quantity first
                $checkSql = "SELECT quantity FROM cart WHERE customer_id = ? AND product_id = ?";
                $stmtCheck = $conn->prepare($checkSql);
                $stmtCheck->bind_param("ii", $u_id, $p_id);
                $stmtCheck->execute();
                $result = $stmtCheck->get_result();
                $row = $result->fetch_assoc();

                if ($row) {
                    $new_qty = $row['quantity'] + $change;

                    // 2. If new quantity is 0 or less, we should probably delete the item
                    if ($new_qty <= 0) {
                        $sql = "DELETE FROM cart WHERE customer_id = ? AND product_id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ii", $u_id, $p_id);
                    } else {
                        // Otherwise, update to the new total
                        $sql = "UPDATE cart SET quantity = ? WHERE customer_id = ? AND product_id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("iii", $new_qty, $u_id, $p_id);
                    }

                    if ($stmt->execute()) {
                        echo json_encode(['success' => true, 'message' => 'Cart updated']);
                    } else {
                        echo json_encode(['success' => false, 'error' => $conn->error]);
                    }
                } else {
                    echo json_encode(['success' => false, 'error' => 'Product not found in cart']);
                }
                $conn->close();
            } else {
                echo json_encode(['success' => false, 'error' => 'Missing required data']);
            }
            exit;
        }
    

    // --- 2. WISHLIST & SAVED ---
    if (isset($segments[1]) && $segments[1] === 'wishlist' || $segments[1] === 'save') {
        // Adding to wishlist
        if (isset($segments[2]) && $segments[2] === 'add' && $method === 'POST') {
            $table = ($segments[1] === 'wishlist') ? 'wishlist' : 'saved_items';
            handleUserAction($table, $productId);
            exit;
        }
        // ✅ FETCHING ALL (This was what you were missing!)
        if (isset($segments[2]) && $segments[2] === 'all' && $method === 'GET') {
            getUserWishlistAndSaved(); 
            exit;
        }

        // ✅ ADD THIS: REMOVE ITEM
    if (isset($segments[2]) && $segments[2] === 'remove' && $method === 'POST') {
        // Read the JSON body to get the product_id and source
        $data = json_decode(file_get_contents("php://input"), true);
        $p_id = $data['product_id'] ?? null;
        $source = $data['source'] ?? 'wishlist'; // default to wishlist

        if ($p_id) {
            handleRemoveFromWishlist($p_id, $source);
        } else {
            echo json_encode(['success' => false, 'error' => 'Product ID missing']);
        }
        exit;
    }
    }

    // --- 3. ADDRESSES ---
    if (isset($segments[1]) && $segments[1] === 'addresses') {
        if (isset($segments[2]) && $segments[2] === 'add' && $method === 'POST') {
            saveCustomerAddress($data); 
            exit;
        }
        // ✅ Fixed: This now only runs for user/addresses/all
        if (isset($segments[2]) && $segments[2] === 'all' && $method === 'GET') {
            getCustomerAddresses();
            exit;
        }
    }

    // --- 4. ORDERS ---
    if (isset($segments[1]) && $segments[1] === 'order' || $segments[1] === 'orders') {
        if (isset($segments[2]) && $segments[2] === 'create' && $method === 'POST') {
            createOrder($data); 
            exit;
        }
        if (isset($segments[2]) && $segments[2] === 'all' && $method === 'GET') {
            getOrderHistory();
            exit;
        }
    }

    // --- 5. PROFILE ---
    if (isset($segments[1]) && $segments[1] === 'profile' && isset($segments[2]) && $segments[2] === 'update') {
        updateCustomerProfile();
        exit;
    }
}
// --- PUBLIC PRODUCT ROUTES ---
if (isset($segments[0]) && $segments[0] === 'products') {
    
    // 1. Handle "products/best-sellers"
    if (isset($segments[1]) && $segments[1] === 'best-sellers') {
        getBestSellers();
        exit;
    }

    // 2. Handle "products/{id}" (e.g., products/91)
    if (isset($segments[1]) && is_numeric($segments[1])) {
        $productId = intval($segments[1]);
        getProductById($productId);
        exit;
    }

    // 3. Handle "products" (all products)
    if (!isset($segments[1])) {
        getAllProducts();
        exit;
    }
}
// --- CHATBOT ROUTE ---
if (isset($segments[0]) && $segments[0] === 'chat') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // This is where the magic happens
        $data = json_decode(file_get_contents("php://input"), true);
        handleChatbotResponse($data['message'] ?? '');
        exit;
    } else {
        // If someone visits via browser (GET), show a clearer message
        header('Content-Type: application/json');
        echo json_encode(["message" => "Chatbot is active. Please use POST to communicate."]);
        exit;
    }
}


// 5. THE SAFETY NET: If no code above has 'exit', this runs.
// This prevents the "Empty Response" error.
http_response_code(404);
echo json_encode([
    'error' => 'Endpoint not found',
    'debug' => [
        'path' => $path,
        'method' => $method,
        'segments' => $segments
    ]
]);
exit;

switch (true) {
    // --- CUSTOMER AUTH ---
    case ($path === 'auth/signup'):
        if ($method === 'POST') handleSignup();
        exit;
        break;

    case ($path === 'auth/signin'):
        if ($method === 'POST') handleSignin();
        exit;
        break;



    case ($path === 'auth/social-login'):
        require_once 'auth.php'; 
        handleSocialLogin($data);
        exit; // Always exit after handling to prevent falling to default
        break;

    case ($path === 'auth/logout'):
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Logged out']);
        exit;
        break;

    // Fix 2: Ensure profile uses the comparison format
    case ($path === 'auth/profile'):
        if ($method === 'GET') {
            getCustomerProfile();
            exit; 
        }
        break;

    // --- PUBLIC PRODUCT ROUTES ---
    case ($path === 'products'):
        if ($method === 'GET') getAllProducts();
        break;

    case ($path === 'products/best-sellers'):
        if ($method === 'GET') getBestSellers();
        break;

    case (preg_match('/^products\/(\d+)$/', $path, $matches)):
        if ($method === 'GET') getProductById($matches[1]);
        break;

    case ($path === 'categories'):
        if ($method === 'GET') getAllCategories();
        break;

    // --- ADMIN ROUTES ---
    case ($path === 'admin/login'):
        require_once 'admin.php'; 
        break;

    case ($path === 'admin/products'):
        if ($method === 'GET') getAdminProducts();
        elseif ($method === 'POST') addProduct();
        break;

    case (preg_match('/^admin\/products\/(\d+)$/', $path, $matches)):
        if ($method === 'DELETE') deleteProduct($matches[1]);
        break;

    case ($path === 'admin/categories'):
        getAdminCategories();
        break;

    case ($path === 'admin/orders'):
        getAdminOrders();
        break;

    case ($path === 'admin/customers'):
        getAdminCustomers();
        break;

    case ($path === 'admin/customers/delete'):
        deleteCustomer();
        break;

    // Inside your API router (the switch/if-else block for paths)
    case ($path === 'admin/orders/update'):
        if ($method === 'POST') {
            // 1. Get the JSON data sent from JS
            $data = json_decode(file_get_contents("php://input"), true);
            $order_id = $data['order_id'] ?? null;
            $status = $data['status'] ?? null;

            if (!$order_id || !$status) {
                echo json_encode(['success' => false, 'error' => 'Missing data']);
                exit;
            }

            $conn = getDBConnection();
            
            // 2. Update the status in the orders table
            $sql = "UPDATE orders SET status = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $status, $order_id);

            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => $conn->error]);
            }
            $conn->close();
        }
        break;

    // --- USER PROFILE & SECURITY ---
    case ($path === 'user/security/update-password'):
        if ($method === 'POST') updatePassword();
        break;

    case ($path === 'user/profile/update'):
        if ($method === 'POST') updateCustomerProfile();
        break;

    // --- CART ACTIONS ---
    case ($path === 'user/cart/all'):
        if ($method === 'GET') getUserCart();
        break;

    case ($path === 'user/cart/add'):
        if ($method === 'POST') handleUserAction('cart');
        break;

    case ($path === 'user/cart/sync'):
        if ($method === 'POST') syncLocalStorageToDB();
        break;

    case ($path === 'user/cart/update-qty'):
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $customer_id = $_SESSION['user_id']; 
            $product_id = $data['product_id'];
            $change = $data['change']; 

            $conn = getDBConnection();
            $sql = "UPDATE cart SET quantity = quantity + ? 
                    WHERE customer_id = ? AND product_id = ? AND (quantity + ?) > 0";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiii", $change, $customer_id, $product_id, $change);
            
            echo json_encode(['success' => $stmt->execute()]);
            $conn->close();
        }
        break;

    case ($path === 'user/cart/remove'):
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $customer_id = $_SESSION['user_id'];
            $product_id = $data['product_id'];
            
            $conn = getDBConnection();
            $stmt = $conn->prepare("DELETE FROM cart WHERE customer_id = ? AND product_id = ?");
            $stmt->bind_param("ii", $customer_id, $product_id);
            echo json_encode(['success' => $stmt->execute()]);
            $conn->close();
        }
        break;

        // --- CART ACTIONS ---
    case ($path === 'user/cart/count'):
        if ($method === 'GET') {
            // Check if user is logged in before giving the count
            if (isset($_SESSION['user_id'])) {
                require_once 'cart_functions.php'; // Or wherever your cart logic is
                getCartCount($_SESSION['user_id']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            }
            exit;
        }
        break;

    // --- WISHLIST & SAVED ITEMS ---
    case ($path === 'user/save/add'):
        if ($method === 'POST') handleUserAction('saved_items');
        break;

    case ($path === 'user/wishlist/add'):
        if ($method === 'POST') handleUserAction('wishlist');
        break;

    case ($path === 'user/wishlist/all'):
        if ($method === 'GET') getUserWishlistAndSaved();
        break;

    case ($path === 'user/wishlist/remove'):
        if ($method === 'POST') handleRemoveAction('wishlist');
        break;

    case ($path === 'user/save/remove'):
        if ($method === 'POST') handleRemoveAction('saved_items');
        break;

    // --- ADDRESSES ---
    case ($path === 'user/addresses/all'):
        if ($method === 'GET') getCustomerAddresses();
        break;

    case ($path === 'user/addresses/add'):
        if ($method === 'POST') saveCustomerAddress();
        break;

    case ($path === 'user/addresses/default'):
        if ($method === 'GET') getDefaultAddress();
        break;

    // --- ORDERS ---
    case ($path === 'user/order/create'):
        if ($method === 'POST') createOrder();
        break;

    case ($path === 'user/orders/all'):
        if ($method === 'GET') getOrderHistory();
        break;

    default:
        http_response_code(404);
        echo json_encode([
            'error' => 'Endpoint not found: ' . $path,
            'debug' => [
                'path' => $path,
                'method' => $method
            ]
        ]);
        break;
}

// --- Logic Functions ---

function getAdminProducts() {
    $conn = getDBConnection();
    
    // 1. Set the header specifically for JSON
    header('Content-Type: application/json');

    $sql = "SELECT 
                p.id, 
                p.name, 
                p.description, 
                p.price, 
                p.image_url, 
                p.is_best_seller, 
                p.quantity AS stock, 
                c.name AS category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            ORDER BY p.id DESC";
            
    $result = $conn->query($sql);
    
    $products = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Ensure numeric values are actually numbers, not strings
            $row['price'] = (float)$row['price'];
            $row['stock'] = (int)$row['stock'];
            $products[] = $row;
        }
    } else {
        // 2. If the query fails, log the error but send an empty array 
        // to prevent the JS .forEach() crash.
        error_log("Database Query Failed: " . $conn->error);
    }
    
    // 3. Always clear the buffer to prevent accidental whitespace/errors
    if (ob_get_length()) ob_clean(); 

    echo json_encode($products);
    $conn->close();
    exit; // Crucial: stop execution so no extra text is added
}

function getAdminCategories() {
    $conn = getDBConnection();
    $result = $conn->query("SELECT * FROM categories ORDER BY name ASC");
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    $conn->close();
}


function getAdminOrders() {
    $conn = getDBConnection();
    
    $sql = "SELECT o.*, c.first_name as customer_name 
            FROM orders o 
            LEFT JOIN customers c ON o.customer_id = c.id 
            ORDER BY o.created_at DESC";

    $result = $conn->query($sql);
    $orders = [];
    while($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }

    echo json_encode($orders);
    $conn->close();
}
function getAdminUsers() {
    $conn = getDBConnection();
    $result = $conn->query("SELECT id, username, created_at FROM admins");
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    $conn->close();
}

// --- API Handler Functions ---

/**
 * Handle Contact Form Submission
 */
function handleContactForm() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $firstName = $data['firstName'] ?? '';
    $lastName = $data['lastName'] ?? '';
    $email = $data['email'] ?? '';
    $message = $data['message'] ?? '';
    
    if (empty($firstName) || empty($email) || empty($message)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }
    
    $conn = getDBConnection();
    $stmt = $conn->prepare("INSERT INTO contact_messages (first_name, last_name, email, message) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $firstName, $lastName, $email, $message);
    
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Success! Data stored.']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Error saving message: ' . $stmt->error]);
    }
    
    $stmt->close();
    $conn->close();
}

/**
 * Get All Products with Category
 */
function getAllProducts() {
    $conn = getDBConnection();
    
    $sql = "SELECT p.*, c.name AS category_name 
            FROM products p 
            JOIN categories c ON p.category_id = c.id";
    
    $result = $conn->query($sql);
    
    if ($result) {
        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
        echo json_encode($products);
    } else {
        http_response_code(500);
        echo json_encode(['error' => $conn->error]);
    }
    
    $conn->close();
}

/**
 * Get Best Seller Products
 */
function getBestSellers() {
    $conn = getDBConnection();
    
    // Using 1 instead of TRUE is safer for MySQL tinyint columns
    $sql = "SELECT * FROM products WHERE is_best_seller = 1";
    $result = $conn->query($sql);
    
    header('Content-Type: application/json'); // Tell the browser it's JSON

    if ($result) {
        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
        echo json_encode($products);
    } else {
        http_response_code(500);
        echo json_encode(['error' => $conn->error]);
    }
    
    $conn->close();
    exit; // IMPORTANT: Stop the script here so the router doesn't add more output
}

/**
 * Get Product by ID
 */
function getProductById($id) {
    $conn = getDBConnection();
    if (!$conn) { die("DB Connection Failed"); } // Check 1

    $id = (int)$id;
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    if (!$stmt) { die("Prepare Failed: " . $conn->error); } // Check 2

    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) { die("Execute Failed: " . $stmt->error); } // Check 3

    $result = $stmt->get_result();
    $product = $result->fetch_assoc();

    if ($product) {
        header('Content-Type: application/json');
        echo json_encode($product);
    } else {
        echo "No product found for ID: " . $id;
    }
    exit; 
}
/**
 * Get All Categories
 */
function getAllCategories() {
    $conn = getDBConnection();
    
    $sql = "SELECT * FROM categories";
    $result = $conn->query($sql);
    
    if ($result) {
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        echo json_encode($categories);
    } else {
        http_response_code(500);
        echo json_encode(['error' => $conn->error]);
    }
    
    $conn->close();
}

/**
 * Add New Product
 */
function addProduct() {
    // 1. Switch from php://input to $_POST for FormData compatibility
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = (float)($_POST['price'] ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);
    $is_best_seller = (int)($_POST['is_best_seller'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0); // Included since we added this column earlier
    
    // 2. Handle the Image File Upload
    $image_url = 'images/default.jpg'; 

    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        // __DIR__ is the 'backend' folder. 
        // We go UP one level, then into 'images'
        $upload_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR;
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_name = time() . '_' . basename($_FILES['image']['name']);
        $target_path = $upload_dir . $file_name;

        // CRITICAL: Check if move works
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
            $image_url = 'images/' . $file_name; 
        } else {
            // This error will show up in your response if it fails
            echo json_encode(['success' => false, 'error' => 'Failed to move file to: ' . $target_path]);
            return;
        }
    }

    // 3. Validation
    if (empty($name) || empty($description) || $price <= 0 || $category_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing or invalid required fields', 'debug' => $_POST]);
        return;
    }

    $conn = getDBConnection();
    // Added 'quantity' to the SQL query
    $stmt = $conn->prepare("INSERT INTO products (name, description, price, image_url, category_id, is_best_seller, quantity) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    // Updated bind_param: s(name), s(desc), d(price), s(url), i(cat), i(best), i(qty)
    $stmt->bind_param("ssdsiii", $name, $description, $price, $image_url, $category_id, $is_best_seller, $quantity);
    
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Product added successfully', 'id' => $stmt->insert_id]);
        echo "<script>window.location.href = 'dashboard.html';</script>"; // Redirect after successful addition
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Error adding product: ' . $stmt->error]);
    }
    
    $stmt->close();
    $conn->close();
}

/**
 * Get Customer Profile by Email
 */
function getCustomerByEmail($email) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT id, first_name, last_name, email, phone_number, address FROM customers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Customer not found']);
    }
    
    $stmt->close();
    $conn->close();
}

/**
 * Handle Customer Registration (Sign Up)
 */
function handleSignup() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $fullname = trim($data['fullname'] ?? '');
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';
    
    // Validate required fields
    if (empty($fullname) || empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'All fields are required']);
        return;
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email format']);
        return;
    }
    
    // Validate password length
    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['error' => 'Password must be at least 6 characters long']);
        return;
    }
    
    // Sanitize inputs
    $fullname = htmlspecialchars($fullname, ENT_QUOTES, 'UTF-8');
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    
    // Split fullname into first/last name
    $names = explode(' ', $fullname, 2);
    $firstName = trim($names[0]);
    $lastName = isset($names[1]) ? trim($names[1]) : '';
    
    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    
    $conn = getDBConnection();
    
    // Ensure mysqli throws exceptions so we can catch them
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $stmt = $conn->prepare("INSERT INTO customers (first_name, last_name, email, password_hash) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $firstName, $lastName, $email, $hashedPassword);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Account created successfully!']);
        }
    } catch (mysqli_sql_exception $e) {
        // Check if the error code is 1062 (Duplicate Entry)
        if ($e->getCode() === 1062) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'That email is already registered.']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
    } finally {
        if (isset($stmt)) $stmt->close();
        $conn->close();
    }
}


/**
 * Handle Customer Login (Sign In)
 */
function handleSignin() {

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';
    
    // Validate required fields
    if (empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'Email and password are required']);
        return;
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email format']);
        return;
    }
    
    // Sanitize email
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, first_name, last_name, email, password_hash FROM customers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
    if (password_verify($password, $row['password_hash'])) {
        
        // --- ADD THIS LINE ---
        $_SESSION['user_id'] = $row['id']; 
        // ---------------------

        echo json_encode([
            'message' => 'Login successful',
            'user' => [
                'id' => $row['id'],
                'name' => $row['first_name']
            ]
        ]);
    } else {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid password']);
        }
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Account not found']);
    }
    
    $stmt->close();
    $conn->close();
}

function getCustomerProfile() {
    if (session_status() === PHP_SESSION_NONE) session_start();

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        return;
    }

    $user_id = $_SESSION['user_id'];
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT first_name, last_name, email, phone_number, address, created_at FROM customers WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            "success" => true,
            "data" => [
                "full_name" => $row['first_name'] . " " . $row['last_name'],
                "first_name" => $row['first_name'],
                "last_name" => $row['last_name'],
                "email" => $row['email'],
                "phone" => $row['phone_number'],
                "address" => $row['address'],
                "joined" => $row['created_at']
            ]
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "User record missing"]);
    }
    $stmt->close();
    $conn->close();
}

function handleUserAction($table) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Please login first']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    
    // Safety check: ensure product_id exists
    if (!isset($data['product_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing product ID']);
        return;
    }

    $product_id = (int)$data['product_id'];
    $quantity = isset($data['quantity']) ? (int)$data['quantity'] : 1;
    $user_id = (int)$_SESSION['user_id'];

    $conn = getDBConnection();

    if ($table === 'cart') {
        // This requires the UNIQUE KEY we added in Step 1
        $stmt = $conn->prepare("INSERT INTO cart (customer_id, product_id, quantity) 
                                VALUES (?, ?, ?) 
                                ON DUPLICATE KEY UPDATE quantity = quantity + ?");
        $stmt->bind_param("iiii", $user_id, $product_id, $quantity, $quantity);
    } else {
        // INSERT IGNORE prevents errors if the item is already in wishlist/saved_items
        $stmt = $conn->prepare("INSERT IGNORE INTO $table (customer_id, product_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $product_id);
    }

    if ($stmt->execute()) {
        // Return a cleaner success message
        $displayNames = ['cart' => 'Cart', 'wishlist' => 'Wishlist', 'saved_items' => 'Saved Items'];
        $folderName = $displayNames[$table] ?? 'List';
        
        echo json_encode([
            'success' => true, 
            'message' => "Item successfully added to your $folderName"
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $conn->error]);
    }
    
    $stmt->close();
    $conn->close();
}

function syncLocalStorageToDB() {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $cartItems = $data['cart'] ?? [];
    $user_id = $_SESSION['user_id'];
    $conn = getDBConnection();

    // Prepare statement to avoid SQL injection
    $stmt = $conn->prepare("INSERT INTO cart (customer_id, product_id, quantity) VALUES (?, ?, ?) 
                            ON DUPLICATE KEY UPDATE quantity = quantity + ?");

    foreach ($cartItems as $item) {
        $p_id = $item['product_id'];
        $qty = $item['quantity'];
        $stmt->bind_param("iiii", $user_id, $p_id, $qty, $qty);
        $stmt->execute();
    }

    echo json_encode(['success' => true, 'message' => 'Cart merged successfully']);
    $conn->close();
}

function getUserWishlistAndSaved() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        return;
    }

    $user_id = $_SESSION['user_id'];
    $conn = getDBConnection();

    $sql = "SELECT p.*, 'wishlist' as source FROM products p 
            JOIN wishlist w ON p.id = w.product_id WHERE w.customer_id = ?
            UNION
            SELECT p.*, 'saved' as source FROM products p 
            JOIN saved_items s ON p.id = s.product_id WHERE s.customer_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while($row = $result->fetch_assoc()) {
        $items[] = $row;
    }

    // This MUST match what your JS expects (data.items)
    echo json_encode(['success' => true, 'items' => $items]);
    
    $stmt->close();
    $conn->close();
}

function handleRemoveAction($table) {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $product_id = $data['product_id'];
    $user_id = $_SESSION['user_id'];

    $conn = getDBConnection();
    $stmt = $conn->prepare("DELETE FROM $table WHERE customer_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $product_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    $conn->close();
}

function getUserCart() {
    ob_clean();
    header('Content-Type: application/json');

    $id_from_session = $_SESSION['user_id'] ?? null;
    $conn = getDBConnection();

    // Change 'user_id' to 'customer_id' in the WHERE clause
    $sql = "SELECT c.id AS cart_id, c.product_id, c.quantity, 
                   p.name AS product_name, p.price, p.image_url 
            FROM cart c 
            LEFT JOIN products p ON c.product_id = p.id 
            WHERE c.customer_id = ?"; // <-- CHECK THIS NAME
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_from_session);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }

    echo json_encode(['success' => true, 'items' => $items]);
    $conn->close();
    exit;
}

function createOrder() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    $customer_id = $_SESSION['user_id'] ?? null;
    if (!$customer_id) {
        echo json_encode(['success' => false, 'error' => 'User not logged in']);
        exit;
    }

    $conn = getDBConnection();
    $conn->begin_transaction();

    try {
        // Inside createOrder function:
        $data = json_decode(file_get_contents("php://input"), true);

        $total_amount     = $data['total_amount'] ?? 0;
        $shipping_address = $data['address'] ?? 'No Address';
        $contact_number   = $data['contact'] ?? 'No Contact'; // Matches JS 'contact'
        $payment_method   = $data['payment_method'] ?? 'COD';
        $shipping_type    = $data['shipping_type'] ?? 'Delivery'; // Matches JS 'shipping_type'
        $tracking_number  = "ZS-" . strtoupper(substr(uniqid(), -8)); // Cleaner tracking ID

        // Ensure your SQL statement follows this order:
        $sql = "INSERT INTO orders (
                    customer_id, total_amount, status, shipping_address, 
                    contact_number, payment_method, shipping_type, tracking_number
                ) VALUES (?, ?, 'Pending', ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("idsssss", 
            $customer_id, 
            $total_amount, 
            $shipping_address, 
            $contact_number, 
            $payment_method, 
            $shipping_type, 
            $tracking_number
        );
        
        $stmt->execute();
        $order_id = $conn->insert_id;

        // 2. Move items from Cart to Order Items
        $moveItemsSql = "INSERT INTO order_items (order_id, product_id, quantity, price_at_purchase)
                         SELECT ?, c.product_id, c.quantity, p.price
                         FROM cart c
                         JOIN products p ON c.product_id = p.id
                         WHERE c.customer_id = ?";
        
        $moveStmt = $conn->prepare($moveItemsSql);
        $moveStmt->bind_param("ii", $order_id, $customer_id);
        $moveStmt->execute();

        // 3. Clear the user's cart
        $clearCart = $conn->prepare("DELETE FROM cart WHERE customer_id = ?");
        $clearCart->bind_param("i", $customer_id);
        $clearCart->execute();

        $conn->commit();
        echo json_encode(['success' => true, 'order_id' => $order_id]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Database Error: ' . $e->getMessage()]);
    }

    $conn->close();
    exit;
}
function getOrderHistory() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $customer_id = $_SESSION['user_id'] ?? null;

    if (!$customer_id) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $conn = getDBConnection();
    
    // Join orders with items and products to get names and images
    $sql = "SELECT o.id, o.total_amount, o.status, o.created_at, 
                   oi.quantity, oi.price_at_purchase, 
                   p.name, p.image_url 
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            JOIN products p ON oi.product_id = p.id
            WHERE o.customer_id = ?
            ORDER BY o.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $oid = $row['id'];
        if (!isset($orders[$oid])) {
            $orders[$oid] = [
                'order_id' => $oid,
                'total' => $row['total_amount'],
                'status' => $row['status'],
                'date' => $row['created_at'],
                'items' => []
            ];
        }
        $orders[$oid]['items'][] = [
            'product_name' => $row['name'],
            'quantity' => $row['quantity'],
            'price' => $row['price_at_purchase'],
            'image_url' => $row['image_url']
        ];
    }

    echo json_encode(['success' => true, 'orders' => array_values($orders)]);
    $conn->close();
}

function updatePassword() {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $current_pwd = $data['current_password'];
    $new_pwd = $data['new_password'];
    $user_id = $_SESSION['user_id'];

    $conn = getDBConnection();

    // 1. Get the current hashed password from the DB
    $stmt = $conn->prepare("SELECT password_hash FROM customers WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // 2. Verify current password
    if (!$user || !password_verify($current_pwd, $user['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        return;
    }

    // 3. Hash and update new password
    $hashed_new_pwd = password_hash($new_pwd, PASSWORD_DEFAULT);
    $updateStmt = $conn->prepare("UPDATE customers SET password_hash = ? WHERE id = ?");
    $updateStmt->bind_param("si", $hashed_new_pwd, $user_id);

    if ($updateStmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }

    $conn->close();
}

function updateCustomerProfile() {
    if (session_status() === PHP_SESSION_NONE) session_start();

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = $_SESSION['user_id'];
    
    // Use ?? to avoid 'Undefined index' warnings
    $phone = $data['phone'] ?? '';
    $address = $data['address'] ?? '';

    $conn = getDBConnection();

    // Ensure your table column is exactly 'phone_number'
    $stmt = $conn->prepare("UPDATE customers SET phone_number = ?, address = ? WHERE id = ?");
    $stmt->bind_param("ssi", $phone, $address, $user_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Profile updated!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save: ' . $conn->error]);
    }

    $conn->close();
}

function saveCustomerAddress() {
    ob_clean();
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = $_SESSION['user_id'];
    $conn = getDBConnection();

    // 1. Reset defaults if this new one is set as default
    if ($data['is_default'] == 1) {
        $reset = $conn->prepare("UPDATE customer_addresses SET is_default = 0 WHERE customer_id = ?");
        $reset->bind_param("i", $user_id);
        $reset->execute();
    }

    // 2. Comprehensive INSERT statement
    $sql = "INSERT INTO customer_addresses 
            (customer_id, label, receiver_name, phone, street, barangay, city, province, zip_code, address_type, is_default) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    
    // "isssssssssi" means: 1 int, 9 strings, 1 int
    $stmt->bind_param("isssssssssi", 
        $user_id, 
        $data['label'], 
        $data['receiver'], 
        $data['phone'], 
        $data['street'], 
        $data['barangay'], 
        $data['city'], 
        $data['province'], 
        $data['zip'], 
        $data['address_type'], 
        $data['is_default']
    );

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }

    $conn->close();
    exit;
}

function getCustomerAddresses() {
    ob_clean();
    header('Content-Type: application/json');

    $user_id = $_SESSION['user_id'] ?? 0;
    $conn = getDBConnection();

    // Select all the new detailed columns
    $stmt = $conn->prepare("SELECT id, label, receiver_name, phone, street, barangay, city, province, zip_code, address_type, is_default FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $addresses = [];
    while ($row = $result->fetch_assoc()) {
        $addresses[] = $row;
    }

    echo json_encode(['success' => true, 'addresses' => $addresses]);
    $conn->close();
    exit;
}

function updateCartQuantity() {
    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = $_SESSION['user_id'];
    $product_id = $data['product_id'];
    $change = $data['change']; // +1 or -1

    $conn = getDBConnection();
    // Update quantity but don't allow it to go below 1
    $stmt = $conn->prepare("UPDATE cart SET quantity = quantity + ? WHERE user_id = ? AND product_id = ? AND (quantity + ?) > 0");
    $stmt->bind_param("iiii", $change, $user_id, $product_id, $change);
    
    echo json_encode(['success' => $stmt->execute()]);
    $conn->close();
}

function getDefaultAddress() {
    $user_id = $_SESSION['user_id'];
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT city FROM customer_addresses WHERE customer_id = ? AND is_default = 1 LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    echo json_encode(['success' => true, 'city' => $result['city'] ?? null]);
    $conn->close();
}

// Change $customer_id to $order_id to be clear what we are updating
function updateOrderStatus($order_id, $data) { 
    $conn = getDBConnection();
    
    // Pull the string out of the array
    $new_status = is_array($data) ? ($data['status'] ?? 'Pending') : $data;
    
    // 1. Prepare the statement
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => $conn->error]);
        return;
    }

    // FIX: Use $order_id here (it matches the function argument)
    $stmt->bind_param("si", $new_status, $order_id);
    
    // 2. Execute and respond
    if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        // Trigger email for any valid status change
        sendEmailNotification($order_id, $new_status);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => true, 'message' => 'No changes made.']);
    }
    }
    
    $stmt->close(); 
    $conn->close();
    exit; 
}

function sendEmailNotification($order_id, $status) {
    require_once __DIR__ . '/vendor/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/vendor/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/vendor/PHPMailer/src/SMTP.php';
    
    $conn = getDBConnection();
    
    // 1. Fetch Order & Customer Details (Already includes tracking_number from your SQL)
    $stmt = $conn->prepare("
        SELECT c.email, CONCAT(c.first_name, ' ', c.last_name) AS full_name, 
                o.total_amount, o.payment_method, o.shipping_address, o.tracking_number
        FROM orders o
        JOIN customers c ON o.customer_id = c.id 
        WHERE o.id = ?
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();

    if (!$order || empty($order['email'])) return;

    // --- TRACKING NUMBER HTML LOGIC ---
    $tracking_html = "";
    if (!empty($order['tracking_number']) && ($status === 'Shipped' || $status === 'Delivered')) {
        $tracking_html = "
            <div style='margin-top: 15px; padding: 10px; background-color: #e8f4fd; border-left: 4px solid #3498db; border-radius: 4px;'>
                <strong style='color: #2c3e50;'>Tracking Number:</strong> 
                <span style='font-family: monospace; font-size: 1.1em; color: #e67e22;'>{$order['tracking_number']}</span>
            </div>";
    }

    // 2. Fetch Items & Product Names
    $items_stmt = $conn->prepare("
        SELECT p.name AS product_name, oi.quantity, oi.price_at_purchase 
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $items_stmt->bind_param("i", $order_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();

    $items_list_html = "";
    while ($item = $items_result->fetch_assoc()) {
        $subtotal = number_format($item['quantity'] * $item['price_at_purchase'], 2);
        $items_list_html .= "
            <tr>
                <td style='padding: 10px; border-bottom: 1px solid #eee;'>{$item['product_name']} (x{$item['quantity']})</td>
                <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right;'>₱$subtotal</td>
            </tr>";
    }

    // 3. Status logic
    $status_color = ($status === 'Canceled') ? '#e74c3c' : '#2ecc71';
    $status_texts = [
        'Processing' => "Artisans are currently preparing your items.",
        'Shipped'    => "Your order is on its way! Keep your phone nearby for the courier's call.",
        'Delivered'  => "Your order has been delivered. Thank you for supporting Zamboanga del Sur crafts!",
        'Canceled'   => "Your order has been canceled. If this was an error, please contact us."
    ];
    $msg = $status_texts[$status] ?? "Your order status has been updated to: $status";

    // 4. Send Email
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'anggot.johnmark29@gmail.com'; 
        $mail->Password   = 'hpzrvpuchkgpaere'; 
        $mail->SMTPSecure = 'tls'; 
        $mail->Port       = 587;

        $mail->setFrom('anggot.johnmark29@gmail.com', 'ZamboSur Crafts');
        $mail->addAddress($order['email'], $order['full_name']);

        $mail->isHTML(true);
        $mail->Subject = "Order #$order_id Update: $status Tracking #{$order['tracking_number']}";
        
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: auto; border: 1px solid #ddd; padding: 20px; border-radius: 8px;'>
                <div style='text-align: center; margin-bottom: 20px;'>
                    <h1 style='color: #2c3e50; margin: 0;'>ZamboSur Crafts</h1>
                    <p style='color: #7f8c8d; font-size: 14px;'>Handcrafted with Pride</p>
                </div>

                <div style='background-color: $status_color; color: white; padding: 12px; text-align: center; border-radius: 4px; margin-bottom: 20px;'>
                    <strong style='font-size: 18px;'>Order Status: $status</strong>
                </div>
                
                <p>Hi <strong>{$order['full_name']}</strong>,</p>
                <p>$msg</p>

                $tracking_html <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                    <tr style='background-color: #f8f9fa;'>
                        <th style='padding: 10px; text-align: left; border-bottom: 2px solid #eee;'>Item</th>
                        <th style='padding: 10px; text-align: right; border-bottom: 2px solid #eee;'>Subtotal</th>
                    </tr>
                    $items_list_html
                    <tr>
                        <td style='padding: 15px 10px; font-weight: bold;'>Total Amount</td>
                        <td style='padding: 15px 10px; text-align: right; font-weight: bold; color: #2c3e50; font-size: 1.2em;'>₱" . number_format($order['total_amount'], 2) . "</td>
                    </tr>
                </table>

                <div style='background-color: #fcfcfc; border: 1px solid #eee; padding: 15px; border-radius: 4px; font-size: 0.9em;'>
                    <strong style='color: #2c3e50;'>Shipping Address:</strong><br>
                    {$order['shipping_address']}<br><br>
                    <strong style='color: #2c3e50;'>Payment Method:</strong> {$order['payment_method']}
                </div>

                <p style='text-align: center; font-size: 11px; color: #95a5a6; margin-top: 40px;'>
                    This is an automated message. For inquiries, please contact our support team.<br>
                    © 2026 ZamboSur Crafts, Zamboanga del Sur.
                </p>
            </div>";

        $mail->send();
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
    }
}
// --- ADD THIS AT THE BOTTOM OF index.php ---

function handleSocialLogin($data) {
   if (session_status() === PHP_SESSION_NONE) session_start();

    $email = $data['email'] ?? '';
    $fullname = $data['fullname'] ?? $data['name'] ?? 'Google User';

    if (empty($email)) {
        echo json_encode(['success' => false, 'error' => 'No email provided']);
        exit;
    }

    $conn = getDBConnection(); // Make sure this helper function exists!
    
    // Check if customer exists
    $stmt = $conn->prepare("SELECT id, first_name FROM customers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (session_status() === PHP_SESSION_NONE) session_start();

    if ($user) {
        // Log in existing user
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['first_name'];
        echo json_encode(['success' => true, 'user' => ['id' => $user['id'], 'name' => $user['first_name']]]);
    } else {
        // Register and log in new user
        $names = explode(' ', $fullname, 2);
        $fName = $names[0];
        $lName = $names[1] ?? '';
        $dummyPass = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);

        $ins = $conn->prepare("INSERT INTO customers (first_name, last_name, email, password_hash) VALUES (?, ?, ?, ?)");
        $ins->bind_param("ssss", $fName, $lName, $email, $dummyPass);
        
        if ($ins->execute()) {
            $newId = $conn->insert_id;
            $_SESSION['user_id'] = $newId;
            echo json_encode(['success' => true, 'user' => ['id' => $newId, 'name' => $fName]]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database Insert Failed']);
        }
    }
    $conn->close();
    exit; // Stop execution after sending JSON
}

function handleRemoveFromWishlist($productId, $source) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        return;
    }

    $conn = getDBConnection();
    
    // Determine which table to delete from
    $table = ($source === 'saved') ? 'saved_items' : 'wishlist';
    
    // IMPORTANT: Match your table column (customer_id)
    $stmt = $conn->prepare("DELETE FROM $table WHERE customer_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $userId, $productId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Item removed']);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    
    $stmt->close();
    $conn->close();
}

function getCartCount($userId) {
    global $conn; // Use your existing database connection variable

    try {
        // We SUM the quantity so if someone buys 2 malongs, the badge shows '2'
        $query = "SELECT SUM(quantity) as total FROM cart WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $count = $row['total'] ? (int)$row['total'] : 0;

        echo json_encode([
            'success' => true,
            'count' => $count
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function getAdminCustomers() {
    global $conn;

    if (!$conn) {
        header('Content-Type: application/json');
        echo json_encode(["error" => "Database connection is null."]);
        exit;
    }
    
    try {
        // 1. Removed the extra comma after ca.phone
        // 2. Used CONCAT to merge address fields into one string
        $sql = "SELECT 
                    c.id, 
                    c.first_name, 
                    c.last_name, 
                    ca.phone AS phone,
                    CONCAT(ca.street, ', ', ca.barangay, ', ', ca.city, ', ', ca.province) AS address 
                FROM customers c
                LEFT JOIN customer_addresses ca ON c.id = ca.customer_id
                ORDER BY c.id DESC";

        $result = $conn->query($sql);
        
        if (!$result) {
            throw new Exception($conn->error);
        }
        
        $customers = $result->fetch_all(MYSQLI_ASSOC);

        header('Content-Type: application/json');
        echo json_encode($customers);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
    exit;
}

function deleteCustomer() {
    global $conn;
    
    // 1. Get the JSON data from the request body
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;

    if (!$id) {
        echo json_encode(["success" => false, "error" => "Missing Customer ID"]);
        exit;
    }

    try {
        // 2. Execute the DELETE query
        $stmt = $conn->prepare("DELETE FROM customers WHERE id = ?");
        $result = $stmt->execute([$id]);

        if ($result) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "error" => "Failed to delete record"]);
        }
    } catch (PDOException $e) {
        // If the customer is linked to orders, this might fail unless you use Cascade
        echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
    }
    exit;
}

function handleChatbotResponse($message) {
    global $conn;
    
    // Start session to check if user is logged in
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $apiKey = getenv('GROQ_API_KEY');
    $url = "https://api.groq.com/openai/v1/chat/completions";

    // 1. Check Login Status
    // Replace 'customer_id' with whatever key you use in your login script
    $isLoggedIn = isset($_SESSION['customer_id']);
    $customerName = $isLoggedIn ? $_SESSION['first_name'] : "";

    // 2. Dynamic Login Instruction
    $loginInstruction = $isLoggedIn 
        ? "The customer is currently LOGGED IN as $customerName. They are allowed to place orders." 
        : "The customer is NOT logged in. If they ask to place an order, tell them to log in first at the login page.";

        // --- START ORDER TRACKING LOGIC ---
    $orderData = "";
    // This regex now correctly captures letters and numbers (alphanumeric)
    if (preg_match('/ZS-[\w\d]+/', $message, $matches)) {
        $foundId = $matches[0];
        
        // CHANGE: Search 'tracking_number' instead of 'id' 
        // because $foundId contains the full string "ZS-..."
        $stmt = $conn->prepare("SELECT status, tracking_number, shipping_type FROM orders WHERE tracking_number = ? OR id = ?");
        
        $stmt->bind_param("si", $foundId, $foundId); // Bind the same variable for both parameters
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $orderData = " [SYSTEM INFO: Order $foundId is '{$row['status']}'. Courier: {$row['shipping_type']}. Tracking #: {$row['tracking_number']}]";
        } else {
            $orderData = " [SYSTEM INFO: Order $foundId not found in our database records.]";
        }
    }

    // 3. Updated Context including the login status
    $context = "You are the Customer Support Lead for ZamboSur Crafts. 
                - Status: $loginInstruction
                - Location: San Francisco, Pagadian City, Zamboanga del Sur.
                - Contact: 09955138368 | anggot.johnmark29@gmail.com
                - Policies: Shipping within Zamboanga Peninsula. 7-day returns.
                - Order Tracking: $orderData
                - Goal: Be brief (max 2 sentences). Use the SYSTEM INFO above if provided.
                - If the customer is logged in and wants to order, guide them to the products.";

    $data = [
        "model" => "llama-3.1-8b-instant",
        "messages" => [
            ["role" => "system", "content" => $context],
            ["role" => "user", "content" => $message]
        ],
        "temperature" => 0.5
    ];

    // ... (rest of your cURL code remains the same)
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);

    if (isset($result['choices'][0]['message']['content'])) {
        $reply = $result['choices'][0]['message']['content'];
    } else {
        $reply = "I'm sorry, I'm having trouble processing that right now.";
    }

    header('Content-Type: application/json');
    echo json_encode(['reply' => $reply]);
    exit;
}