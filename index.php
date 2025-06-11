<?php

require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Get featured properties
function getFeaturedProperties($limit = 6) {
    global $pdo;
    
    $query = "
        SELECT 
            p.*, 
            u.first_name, 
            u.last_name
        FROM 
            properties p
        JOIN 
            users u ON p.landlord_id = u.user_id
        ORDER BY 
            p.created_at DESC
        LIMIT :limit
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT); // Explicitly bind as integer
    $stmt->execute();
    return $stmt->fetchAll();
}


// Get property types for filter
function getPropertyTypes() {
    global $pdo;
    
    $query = "
        SELECT DISTINCT property_type 
        FROM properties 
        ORDER BY property_type
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Handle property filters
$status = isset($_GET['status']) ? $_GET['status'] : 'vacant';
$propertyType = isset($_GET['property_type']) ? $_GET['property_type'] : 'all';
$priceRange = isset($_GET['price_range']) ? $_GET['price_range'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get filtered properties
function getFilteredProperties($status = 'vacant', $propertyType = 'all', $priceRange = 'all', $search = '') {
    global $pdo;
    
    $query = "
        SELECT 
            p.*, 
            u.first_name, 
            u.last_name
        FROM 
            properties p
        JOIN 
            users u ON p.landlord_id = u.user_id
        WHERE 
            1=1
    ";
    
    $params = [];
    
    // Add filters
    if ($status !== 'all') {
        $query .= " AND p.status = ?";
        $params[] = $status;
    }
    
    if ($propertyType !== 'all') {
        $query .= " AND p.property_type = ?";
        $params[] = $propertyType;
    }
    
    if ($priceRange !== 'all') {
        switch ($priceRange) {
            case '0-500':
                $query .= " AND p.monthly_rent BETWEEN 0 AND 500";
                break;
            case '501-1000':
                $query .= " AND p.monthly_rent BETWEEN 501 AND 1000";
                break;
            case '1001-2000':
                $query .= " AND p.monthly_rent BETWEEN 1001 AND 2000";
                break;
            case '2001+':
                $query .= " AND p.monthly_rent > 2000";
                break;
        }
    }
    
    if (!empty($search)) {
        $query .= " AND (p.property_name LIKE ? OR p.address LIKE ? OR p.city LIKE ? OR p.state LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $query .= " ORDER BY p.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Process contact form
$formSubmitted = false;
$formErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $propertyInterest = isset($_POST['property_id']) ? (int)$_POST['property_id'] : null;
    
    // Validate input
    if (empty($name)) $formErrors[] = "Name is required";
    if (empty($email)) $formErrors[] = "Email is required";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $formErrors[] = "Valid email is required";
    if (empty($message)) $formErrors[] = "Message is required";
    
    if (empty($formErrors)) {
        try {
            // Store inquiry in database
            $stmt = $pdo->prepare("
                INSERT INTO inquiries (
                    name, email, phone, message, property_id, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, NOW()
                )
            ");
            
            $stmt->execute([$name, $email, $phone, $message, $propertyInterest]);
require_once 'messaging.php';
            
// Send notification email to admin
$emailSubject = "New Property Inquiry";
$emailBody = getInquiryEmailTemplate($name, $email, $phone, $message, $propertyInterest);
$plainText = "Name: $name\nEmail: $email\nPhone: $phone\nMessage: $message";

// Get admin email from database or use a default
$adminEmail = 'abayosincere11@gmail.com'; // You might want to get this from a settings table

// Send email using the existing messaging function
$emailSent = sendEmail($adminEmail, $emailSubject, $emailBody, $plainText);

if (!$emailSent) {
    // Log the error but don't show to user
    error_log("Failed to send inquiry notification email to admin");
}
            $formSubmitted = true;
        } catch (PDOException $e) {
            $formErrors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Get properties based on filters
$properties = !empty($_GET) ? getFilteredProperties($status, $propertyType, $priceRange, $search) : getFeaturedProperties();
$propertyTypes = getPropertyTypes();
?>

<!DOCTYPE html>
<html lang="en">


<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property Management System - Find Your Perfect Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    primary: '#1a56db',
                    secondary: '#7e3af2',
                    success: '#0ea5e9',
                }
            }
        }
    }
    </script>
    <style>
    .hero-section {
        background-image: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('https://images.unsplash.com/photo-1560518883-ce09059eeffa?ixlib=rb-1.2.1&auto=format&fit=crop&w=1500&q=80');
        background-size: cover;
        background-position: center;
        height: 70vh;
    }
    @media (max-width: 640px) {
      .hero-section {
        height: 20vh;
      }
    }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-2 sm:px-4 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <h1 class="text-xl sm:text-2xl font-bold text-primary">PropertyPro</h1>
                    </div>
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                        <a href="#"
                            class="border-primary text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Home
                        </a>
                        <a href="#properties"
                            class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Properties
                        </a>
                        <a href="#contact"
                            class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Contact
                        </a>
                    </div>
                </div>
                <div class="hidden sm:ml-6 sm:flex sm:items-center">
                    <a href="login.php"
                        class="text-gray-500 hover:text-gray-700 px-3 py-2 rounded-md text-sm font-medium">
                        Login
                    </a>
                </div>
                <div class="-mr-2 flex items-center sm:hidden">
                    <button type="button"
                        class="mobile-menu-button inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary">
                        <span class="sr-only">Open main menu</span>
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>
        </div>
        <!-- Mobile menu, show/hide based on menu state -->
        <div class="sm:hidden hidden mobile-menu bg-white border-t border-gray-200">
            <div class="pt-2 pb-3 space-y-1">
                <a href="#" class="bg-primary text-white block pl-3 pr-4 py-2 text-base font-medium">
                    Home
                </a>
                <a href="#properties"
                    class="text-gray-500 hover:bg-gray-50 hover:text-gray-700 block pl-3 pr-4 py-2 text-base font-medium">
                    Properties
                </a>
                <a href="#contact"
                    class="text-gray-500 hover:bg-gray-50 hover:text-gray-700 block pl-3 pr-4 py-2 text-base font-medium">
                    Contact
                </a>
                <a href="login.php"
                    class="text-gray-500 hover:bg-gray-50 hover:text-gray-700 block pl-3 pr-4 py-2 text-base font-medium">
                    Login
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section flex items-center justify-center px-2 sm:px-4">
        <div class="text-center text-white px-2 sm:px-4">
            <h1 class="text-2xl sm:text-4xl md:text-5xl font-bold mb-2 sm:mb-4">Find Your Perfect Home</h1>
            <p class="text-base sm:text-xl md:text-2xl mb-4 sm:mb-8">Browse our selection of premium properties available for rent</p>
            <a href="#properties"
                class="bg-primary text-white px-4 sm:px-6 py-2 sm:py-3 rounded-lg text-base sm:text-lg font-medium hover:bg-blue-700 transition duration-300">
                View Properties
            </a>
        </div>
    </section>

    <!-- Property Search Section -->
    <section class="py-12 bg-white" id="properties">
        <div class="max-w-7xl mx-auto px-2 sm:px-6 lg:px-8">
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-6 sm:mb-8 text-center">Available Properties</h2>

            <!-- Search and Filters -->
            <div class="bg-gray-100 rounded-xl p-4 sm:p-6 mb-6 sm:mb-8">
                <form method="GET" action="#properties">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="status"
                                class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                                <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
                                <option value="vacant" <?php echo $status === 'vacant' ? 'selected' : ''; ?>>Vacant
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Property Type</label>
                            <select name="property_type"
                                class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                                <option value="all" <?php echo $propertyType === 'all' ? 'selected' : ''; ?>>All Types
                                </option>
                                <?php foreach ($propertyTypes as $type): ?>
                                <option value="<?php echo $type; ?>"
                                    <?php echo $propertyType === $type ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($type); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Price Range</label>
                            <select name="price_range"
                                class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                                <option value="all" <?php echo $priceRange === 'all' ? 'selected' : ''; ?>>Any Price
                                </option>
                                <option value="0-500" <?php echo $priceRange === '0-500' ? 'selected' : ''; ?>>$0 - $500
                                </option>
                                <option value="501-1000" <?php echo $priceRange === '501-1000' ? 'selected' : ''; ?>>
                                    $501 - $1000</option>
                                <option value="1001-2000" <?php echo $priceRange === '1001-2000' ? 'selected' : ''; ?>>
                                    $1001 - $2000</option>
                                <option value="2001+" <?php echo $priceRange === '2001+' ? 'selected' : ''; ?>>$2001+
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                            <div class="flex">
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                                    placeholder="Search properties..."
                                    class="w-full rounded-l-lg border-gray-300 focus:border-primary focus:ring-primary">
                                <button type="submit"
                                    class="bg-primary text-white px-4 py-2 rounded-r-lg hover:bg-blue-700">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Currency converter</label>
                            <div class="flex">
                                <?php include 'includes/currency_switcher_index.php'; ?>

                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Properties Grid -->
            <div class="grid grid-cols-1 gap-4 sm:gap-6 md:grid-cols-2 lg:grid-cols-3">
                <?php if (empty($properties)): ?>
                <div class="col-span-3 text-center py-8">
                    <p class="text-gray-500">No properties found matching your criteria.</p>
                </div>
                <?php else: ?>
                <?php foreach ($properties as $property): ?>
                <!-- Property Card -->
                <div class="bg-white rounded-xl shadow-md overflow-hidden property-card"
                    data-property-id="<?php echo $property['property_id']; ?>"
                    data-property-name="<?php echo htmlspecialchars($property['property_name']);  ?>"
                    data-property-address="<?php echo htmlspecialchars($property['address'] . ', ' . $property['city'] . ', ' . $property['state'] . ' ' . $property['zip_code']); ?>"
                    data-property-rent="<?php echo number_format($property['monthly_rent'], 2); ?>">
                    <div class="relative">
                        <?php if (!empty($property['image_path'])): ?>
                        <img src="<?php echo htmlspecialchars($property['image_path']); ?>"
                            alt="<?php echo htmlspecialchars($property['property_name']); ?>"
                            class="w-full h-48 object-cover property-image">
                        <?php else: ?>
                        <img src="https://images.unsplash.com/photo-1560518883-ce09059eeffa?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80"
                            alt="Property" class="w-full h-48 object-cover property-image">
                        <?php endif; ?>
                        <div class="absolute top-4 right-4">
                            <?php if ($property['status'] === 'occupied'): ?>
                            <span class="bg-red-500 text-white px-3 py-1 rounded-full text-sm">Occupied</span>
                            <?php elseif ($property['status'] === 'vacant'): ?>
                            <span class="bg-green-500 text-white px-3 py-1 rounded-full text-sm">Available</span>
                            <?php else: ?>
                            <span class="bg-yellow-500 text-white px-3 py-1 rounded-full text-sm">Maintenance</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="p-6">
                        <h3 class="text-xl font-semibold mb-2">
                            <?php echo htmlspecialchars($property['property_name']); ?></h3>
                        <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($property['address']); ?>,
                            <?php echo htmlspecialchars($property['city']); ?></p>
                        <div class="flex justify-between items-center mb-4">
                            <div>
                                <p class="text-sm text-gray-500">Monthly Rent</p>
                                <p class="text-lg font-semibold">
                                    <?php 
                                    require_once 'includes/currency.php';
                                    $currentCurrency = getUserCurrency();
                                    echo convertAndFormat($property['monthly_rent'], 'USD', $currentCurrency); 
                              ?>
                                </p>
                            </div>

                            <div>
                                <p class="text-sm text-gray-500">Property Type</p>
                                <p class="text-lg font-semibold capitalize"><?php echo $property['property_type']; ?>
                                </p>
                            </div>
                        </div>
                        <div class="flex space-x-2">
                            <button
                                class="flex-1 bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-center view-details-btn">
                                View Details
                            </button>
                            <button
                                class="flex-1 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-center inquire-btn">
                                Inquire Now
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-12 bg-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-gray-900 mb-8 text-center">Why Choose Us</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-white p-6 rounded-xl shadow-md text-center">
                    <div class="text-primary text-4xl mb-4">
                        <i class="fas fa-home"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Quality Properties</h3>
                    <p class="text-gray-600">We offer a wide selection of high-quality properties in prime locations to
                        meet your needs.</p>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-md text-center">
                    <div class="text-primary text-4xl mb-4">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Competitive Pricing</h3>
                    <p class="text-gray-600">Our rental rates are competitive and transparent, with no hidden fees or
                        charges.</p>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-md text-center">
                    <div class="text-primary text-4xl mb-4">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Excellent Support</h3>
                    <p class="text-gray-600">Our dedicated team provides responsive support for all your
                        property-related needs.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="py-12 bg-white" id="contact">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-gray-900 mb-8 text-center">Contact Us</h2>

            <?php if ($formSubmitted): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p>Thank you for your inquiry! We will get back to you as soon as possible.</p>
            </div>
            <?php endif; ?>

            <?php if (!empty($formErrors)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <ul class="list-disc list-inside">
                    <?php foreach ($formErrors as $error): ?>
                    <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="bg-gray-100 rounded-xl overflow-hidden">
                <div class="grid grid-cols-1 md:grid-cols-2">
                    <div class="p-8">
                        <h3 class="text-2xl font-semibold mb-4">Get in Touch</h3>
                        <p class="text-gray-600 mb-6">Have questions about our properties or services? Fill out the form
                            and we'll get back to you as soon as possible.</p>

                        <form method="POST" action="#contact" class="space-y-4">
                            <input type="hidden" name="property_id" id="contact_property_id" value="">

                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Your Name</label>
                                <input type="text" id="name" name="name" required
                                    class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            </div>

                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email
                                    Address</label>
                                <input type="email" id="email" name="email" required
                                    class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            </div>

                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone
                                    Number</label>
                                <input type="tel" id="phone" name="phone"
                                    class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary">
                            </div>

                            <div>
                                <label for="property_interest"
                                    class="block text-sm font-medium text-gray-700 mb-1">Property of Interest</label>
                                <input type="text" id="property_interest" name="property_interest" readonly
                                    class="w-full rounded-lg bg-gray-50 border-gray-300">
                            </div>

                            <div>
                                <label for="message"
                                    class="block text-sm font-medium text-gray-700 mb-1">Message</label>
                                <textarea id="message" name="message" rows="4" required
                                    class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring-primary"></textarea>
                            </div>

                            <div>
                                <button type="submit" name="contact_submit"
                                    class="w-full bg-primary text-white px-6 py-3 rounded-lg text-lg font-medium hover:bg-blue-700 transition duration-300">
                                    Send Message
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="bg-primary text-white p-8 flex flex-col justify-center">
                        <h3 class="text-2xl font-semibold mb-6">Contact Information</h3>

                        <div class="space-y-4">
                            <div class="flex items-start">
                                <div class="flex-shrink-0 mt-1">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div class="ml-4">
                                    <p>KN 5 Road</p>
                                    <p>Kigali, Rwanda</p>
                                </div>
                            </div>

                            <div class="flex items-start">
                                <div class="flex-shrink-0 mt-1">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div class="ml-4">
                                    <p>+250 784 828 381</p>
                                </div>
                            </div>

                            <div class="flex items-start">
                                <div class="flex-shrink-0 mt-1">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="ml-4">
                                    <p>info@propertypro.com</p>
                                </div>
                            </div>

                            <div class="flex items-start hidden">
                                <div class="flex-shrink-0 mt-1">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="ml-4 ">
                                    <p>Monday - Friday: 9am - 5pm</p>
                                    <p>Saturday: 10am - 2pm</p>
                                    <p>Sunday: Closed</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-8">
                            <h4 class="text-xl font-semibold mb-4">Follow Us</h4>
                            <div class="flex space-x-4">
                                <a href="#" class="text-white hover:text-gray-200">
                                    <i class="fab fa-facebook-f text-2xl"></i>
                                </a>
                                <a href="#" class="text-white hover:text-gray-200">
                                    <i class="fab fa-twitter text-2xl"></i>
                                </a>
                                <a href="#" class="text-white hover:text-gray-200">
                                    <i class="fab fa-instagram text-2xl"></i>
                                </a>
                                <a href="#" class="text-white hover:text-gray-200">
                                    <i class="fab fa-linkedin-in text-2xl"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <h3 class="text-xl font-semibold mb-4">PropertyPro</h3>
                    <p class="text-gray-400">Your trusted partner in finding the perfect rental property.</p>
                </div>

                <div>
                    <h4 class="text-lg font-semibold mb-4">Quick Links</h4>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-400 hover:text-white">Home</a></li>
                        <li><a href="#properties" class="text-gray-400 hover:text-white">Properties</a></li>
                        <li><a href="#contact" class="text-gray-400 hover:text-white">Contact</a></li>
                        <li><a href="login.php" class="text-gray-400 hover:text-white">Login</a></li>
                        <li><a href="register.php" class="text-gray-400 hover:text-white">Register</a></li>
                    </ul>
                </div>

                <div>
                    <div>
                        <h4 class="text-lg font-semibold mb-4">Property Types</h4>
                        <ul class="space-y-2">
                            <li><a href="?property_type=apartment" class="text-gray-400 hover:text-white">Apartments</a>
                            </li>
                            <li><a href="?property_type=house" class="text-gray-400 hover:text-white">Houses</a></li>
                            <li><a href="?property_type=condo" class="text-gray-400 hover:text-white">Condos</a></li>
                            <li><a href="?property_type=studio" class="text-gray-400 hover:text-white">Studios</a></li>
                            <li><a href="?property_type=commercial"
                                    class="text-gray-400 hover:text-white">Commercial</a></li>
                        </ul>
                    </div>

                    <div>
                        <h4 class="text-lg font-semibold mb-4">Contact Us</h4>
                        <ul class="space-y-2">
                            <li class="flex items-start">
                                <i class="fas fa-map-marker-alt mt-1 mr-2 text-gray-400"></i>
                                <span class="text-gray-400">KN 5 Road Kigali, Rwanda</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-phone mt-1 mr-2 text-gray-400"></i>
                                <span class="text-gray-400">+250 784 828 381</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-envelope mt-1 mr-2 text-gray-400"></i>
                                <span class="text-gray-400">info@propertypro.com</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="border-t border-gray-700 mt-8 pt-8 flex flex-col md:flex-row justify-between items-center">
                    <p class="text-gray-400">&copy; <?php echo date('Y'); ?> PropertyPro. All rights reserved.</p>
                    <div class="flex space-x-4 mt-4 md:mt-0">
                        <a href="#" class="text-gray-400 hover:text-white">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                    </div>
                </div>
            </div>
    </footer>

    <!-- Property Details Modal -->
    <div id="propertyModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 max-w-4xl w-full mx-4 max-h-90vh overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h3 id="modalPropertyName" class="text-2xl font-bold">Property Name</h3>
                <button onclick="closePropertyModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="mb-6">
                <div id="propertyImageGallery" class="relative">
                    <div id="mainPropertyImage" class="w-full h-80 bg-gray-200 rounded-lg overflow-hidden">
                        <img src="" alt="Property" class="w-full h-full object-cover">
                    </div>

                    <div class="flex mt-4 space-x-2 overflow-x-auto pb-2" id="thumbnailContainer">
                        <!-- Thumbnails will be added here dynamically -->
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <h4 class="text-lg font-semibold mb-2">Property Details</h4>
                    <ul class="space-y-2">
                        <li class="flex items-center">
                            <span class="font-medium w-32">Type:</span>
                            <span id="modalPropertyType">-</span>
                        </li>
                        <li class="flex items-center">
                            <span class="font-medium w-32">Bedrooms:</span>
                            <span id="modalBedrooms">-</span>
                        </li>
                        <li class="flex items-center">
                            <span class="font-medium w-32">Bathrooms:</span>
                            <span id="modalBathrooms">-</span>
                        </li>
                        <li class="flex items-center">
                            <span class="font-medium w-32">Square Feet:</span>
                            <span id="modalSquareFeet">-</span>
                        </li>
                        <li class="flex items-center">
                            <span class="font-medium w-32">Monthly Rent:</span>
                            <span id="modalRent" data-currency-value="" data-currency-original="USD">-</span>
                        </li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-lg font-semibold mb-2">Location</h4>
                    <p id="modalAddress" class="mb-4">-</p>

                    <h4 class="text-lg font-semibold mb-2">Status</h4>
                    <p id="modalStatus" class="mb-4">-</p>
                </div>
            </div>

            <div class="mb-6">
                <h4 class="text-lg font-semibold mb-2">Description</h4>
                <p id="modalDescription" class="text-gray-600">-</p>
            </div>

            <div class="flex space-x-4">
                <button id="modalInquireBtn"
                    class="flex-1 bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-center">
                    Inquire About This Property
                </button>
            </div>
        </div>
    </div>

    <script>
    // Mobile menu toggle
    document.querySelector('.mobile-menu-button').addEventListener('click', function() {
        document.querySelector('.mobile-menu').classList.toggle('hidden');
    });

    // Property modal functionality
    const propertyModal = document.getElementById('propertyModal');
    const propertyCards = document.querySelectorAll('.property-card');
    const inquireButtons = document.querySelectorAll('.inquire-btn');
    const viewDetailsButtons = document.querySelectorAll('.view-details-btn');

    // Function to open property modal
    function openPropertyModal(propertyId) {
        // Fetch property details via AJAX
        fetch(`api/get_property.php?id=${propertyId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const property = data.property;
                    const currentCurrency = document.getElementById('currentCurrencySymbol').textContent === '$' ?
                        'USD' : 'RWF';
                    console.log('current currency ', currentCurrency);

                    // Set modal content
                    document.getElementById('modalPropertyName').textContent = property.property_name;
                    document.getElementById('modalPropertyType').textContent = property.property_type.charAt(0)
                        .toUpperCase() + property.property_type.slice(1);
                    document.getElementById('modalBedrooms').textContent = property.bedrooms || '-';
                    document.getElementById('modalBathrooms').textContent = property.bathrooms || '-';
                    document.getElementById('modalSquareFeet').textContent = property.square_feet ?
                        `${property.square_feet} sq ft` : '-';

                    // Set data attributes for currency conversion
                    const modalRent = document.getElementById('modalRent');
                    modalRent.setAttribute('data-currency-value', property.monthly_rent);
                    modalRent.setAttribute('data-currency-original', 'USD');

                    // Format the rent based on selected currency
                    if (currentCurrency === 'RWF') {
                        const rwfAmount = property.monthly_rent * <?php echo USD_TO_RWF_RATE; ?>;
                        modalRent.textContent = `RWF ${Math.round(rwfAmount).toLocaleString()}`;
                    } else {
                        modalRent.textContent = `$${parseFloat(property.monthly_rent).toFixed(2)}`;
                    }
                    document.getElementById('modalAddress').textContent =
                        `${property.address}, ${property.city}, ${property.state} ${property.zip_code}`;
                    document.getElementById('modalStatus').textContent = property.status.charAt(0).toUpperCase() +
                        property.status.slice(1);
                    document.getElementById('modalDescription').textContent = property.description ||
                        'No description available.';

                    // Set main image
                    const mainImageElement = document.querySelector('#mainPropertyImage img');
                    mainImageElement.src = property.image_path ||
                        'https://images.unsplash.com/photo-1560518883-ce09059eeffa?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80';
                    mainImageElement.alt = property.property_name;

                    // Clear existing thumbnails
                    const thumbnailContainer = document.getElementById('thumbnailContainer');
                    thumbnailContainer.innerHTML = '';

                    // Add main image as first thumbnail
                    const mainThumbnail = document.createElement('div');
                    mainThumbnail.className =
                        'w-20 h-20 flex-shrink-0 rounded overflow-hidden cursor-pointer border-2 border-primary';
                    mainThumbnail.innerHTML =
                        `<img src="${property.image_path || 'https://images.unsplash.com/photo-1560518883-ce09059eeffa?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80'}" alt="${property.property_name}" class="w-full h-full object-cover">`;
                    mainThumbnail.addEventListener('click', function() {
                        mainImageElement.src = this.querySelector('img').src;
                    });
                    thumbnailContainer.appendChild(mainThumbnail);

                    // Add additional images if available
                    if (property.additional_images && property.additional_images.length > 0) {
                        property.additional_images.forEach(image => {
                            const thumbnail = document.createElement('div');
                            thumbnail.className =
                                'w-20 h-20 flex-shrink-0 rounded overflow-hidden cursor-pointer border-2 border-transparent hover:border-primary';
                            thumbnail.innerHTML =
                                `<img src="${image.path}" alt="${property.property_name}" class="w-full h-full object-cover">`;
                            thumbnail.addEventListener('click', function() {
                                mainImageElement.src = this.querySelector('img').src;
                                // Update active thumbnail
                                document.querySelectorAll('#thumbnailContainer > div').forEach(
                                    el => {
                                        el.classList.remove('border-primary');
                                        el.classList.add('border-transparent');
                                    });
                                this.classList.remove('border-transparent');
                                this.classList.add('border-primary');
                            });
                            thumbnailContainer.appendChild(thumbnail);
                        });
                    }

                    // Set up inquire button
                    document.getElementById('modalInquireBtn').onclick = function() {
                        closePropertyModal();
                        document.getElementById('contact_property_id').value = property.property_id;
                        document.getElementById('property_interest').value = property.property_name;
                        document.querySelector('a[href="#contact"]').click();
                        document.getElementById('message').focus();
                    };

                    // Show modal
                    propertyModal.classList.remove('hidden');
                    propertyModal.classList.add('flex');
                } else {
                    alert('Error loading property details. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading property details. Please try again.');
            });
    }

    // Function to close property modal
    function closePropertyModal() {
        propertyModal.classList.add('hidden');
        propertyModal.classList.remove('flex');
    }

    // Add event listeners to property cards
    propertyCards.forEach(card => {
        const propertyId = card.dataset.propertyId;

        // View details button
        card.querySelector('.view-details-btn').addEventListener('click', function() {
            openPropertyModal(propertyId);
        });

        // Inquire button
        card.querySelector('.inquire-btn').addEventListener('click', function() {
            document.getElementById('contact_property_id').value = propertyId;
            document.getElementById('property_interest').value = card.dataset.propertyName;
            document.querySelector('a[href="#contact"]').click();
            document.getElementById('message').focus();
        });
    });

    // Close modal when clicking outside
    propertyModal.addEventListener('click', function(e) {
        if (e.target === this) {
            closePropertyModal();
        }
    });

    // Close modal with escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !propertyModal.classList.contains('hidden')) {
            closePropertyModal();
        }
    });
    // USD to RWF conversion rate
    const USD_TO_RWF_RATE = <?php echo USD_TO_RWF_RATE; ?>;

    // Format currency based on currency code
    function formatCurrency(amount, currency) {
        if (currency === 'RWF') {
            return 'RWF ' + Math.round(amount).toLocaleString();
        } else {
            return '$' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }
    }

    // Convert USD to RWF
    function convertUsdToRwf(amount) {
        return amount * USD_TO_RWF_RATE;
    }

    // Update all currency displays on the page
    function updateCurrencyDisplay(currency) {
        // Update property cards
        document.querySelectorAll('.property-card').forEach(card => {
            const rentElement = card.querySelector('.text-lg.font-semibold');
            if (rentElement) {
                const rentUsd = parseFloat(card.dataset.propertyRent.replace(/,/g, ''));
                if (!isNaN(rentUsd)) {
                    if (currency === 'RWF') {
                        rentElement.textContent = formatCurrency(convertUsdToRwf(rentUsd), 'RWF');
                    } else {
                        rentElement.textContent = formatCurrency(rentUsd, 'USD');
                    }
                }
            }
        });
        // Update modal rent if it's open
        const modalRent = document.getElementById('modalRent');
        if (modalRent && !modalRent.classList.contains('hidden')) {
            const rentUsd = parseFloat(modalRent.getAttribute('data-currency-value'));
            if (!isNaN(rentUsd)) {
                if (currency === 'RWF') {
                    modalRent.textContent = formatCurrency(convertUsdToRwf(rentUsd), 'RWF');
                } else {
                    modalRent.textContent = formatCurrency(rentUsd, 'USD');
                }
            }
        }

        // Update all elements with data-currency-value attribute
        document.querySelectorAll('[data-currency-value]').forEach(element => {
            const amount = parseFloat(element.getAttribute('data-currency-value'));
            const fromCurrency = element.getAttribute('data-currency-original') || 'USD';

            if (!isNaN(amount)) {
                if (fromCurrency === 'USD') {
                    if (currency === 'RWF') {
                        element.textContent = formatCurrency(convertUsdToRwf(amount), 'RWF');
                    } else {
                        element.textContent = formatCurrency(amount, 'USD');
                    }
                } else if (fromCurrency === 'RWF') {
                    // Handle RWF to USD conversion if needed
                    if (currency === 'USD') {
                        element.textContent = formatCurrency(amount / USD_TO_RWF_RATE, 'USD');
                    } else {
                        element.textContent = formatCurrency(amount, 'RWF');
                    }
                }
            }
        });
    }
    // Add this to ensure the modal is updated when currency changes
    document.addEventListener('currencyChanged', function(e) {
        const currency = e.detail.currency;
        updateCurrencyDisplay(currency);
    });

    // update the closePropertyModal function to clean up
    function closePropertyModal() {
        propertyModal.classList.add('hidden');
        propertyModal.classList.remove('flex');
        // Clear data attributes to prevent stale data
        const modalRent = document.getElementById('modalRent');
        if (modalRent) {
            modalRent.setAttribute('data-currency-value', '');
            modalRent.textContent = '-';
        }
    }
    // Listen for currency change events
    document.addEventListener('currencyChanged', function(e) {
        updateCurrencyDisplay(e.detail.currency);
    });
    </script>
</body>

</html>