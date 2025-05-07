<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require tenant role
requireRole('tenant');

// Get user information
$userId = $_SESSION['user_id'];

// Check if payment ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid payment ID";
    header("Location: payments.php");
    exit;
}

$paymentId = (int)$_GET['id'];

// Get payment details
$stmt = $pdo->prepare("
    SELECT p.*, l.tenant_id, l.property_id, l.start_date, l.end_date, l.monthly_rent,
           pr.property_name, pr.address, pr.city, pr.state, pr.zip_code,
           u.first_name, u.last_name, u.email,
           o.first_name AS owner_first_name, o.last_name AS owner_last_name,
           o.email AS owner_email, o.phone AS owner_phone,
           un.unit_number
    FROM payments p
    JOIN leases l ON p.lease_id = l.lease_id
    JOIN properties pr ON l.property_id = pr.property_id
    JOIN users u ON l.tenant_id = u.user_id
    JOIN users o ON pr.landlord_id = o.user_id
    LEFT JOIN units un ON l.unit_id = un.unit_id
    WHERE p.payment_id = ? AND l.tenant_id = ?
");

$stmt->execute([$paymentId, $userId]);
$payment = $stmt->fetch();

// Verify payment exists and belongs to this tenant
if (!$payment) {
    $_SESSION['error'] = "Payment not found or you don't have permission to view it";
    header("Location: payments.php");
    exit;
}

// Format currency function
function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

// Generate receipt number
$receiptNumber = 'RCP-' . str_pad($payment['payment_id'], 6, '0', STR_PAD_LEFT);

// Format payment date
$paymentDate = date('F j, Y', strtotime($payment['payment_date']));

// Get property address
$propertyAddress = $payment['address'] . ', ' . $payment['city'] . ', ' . $payment['state'] . ' ' . $payment['zip_code'];
if ($payment['unit_number']) {
    $propertyAddress = 'Unit ' . $payment['unit_number'] . ', ' . $propertyAddress;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - Tenant Portal</title>
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
        @media print {
            .no-print {
                display: none;
            }
            body {
                padding: 0;
                margin: 0;
            }
            .print-container {
                max-width: 100%;
                box-shadow: none;
                border: none;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Sidebar -->
    <div class="no-print">
        <?php include 'tenant_sidebar.php'; ?>
    </div>

    <!-- Main Content -->
    <div class="ml-64 p-8 no-print">
        <!-- Header with Back Button -->
        <div class="flex items-center mb-8">
            <a href="payments.php" class="mr-4 text-gray-600 hover:text-gray-900">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Payment Receipt</h2>
                <p class="text-gray-600">Receipt #<?php echo $receiptNumber; ?></p>
            </div>
            <div class="ml-auto">
    <button onclick="window.print()" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700 mr-2">
        <i class="fas fa-print mr-2"></i>Print Receipt
    </button>
    <button onclick="downloadReceiptAsPDF()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
        <i class="fas fa-download mr-2"></i>Download PDF
    </button>
</div>

        </div>
    </div>

    <!-- Receipt Content (Printable) -->
    <div class="mx-auto p-8 print-container max-w-3xl">
        <div class="bg-white rounded-xl shadow-md p-8 mb-8">
            <!-- Receipt Header -->
            <div class="flex justify-between items-center border-b pb-6 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Payment Receipt</h1>
                    <p class="text-gray-600">Receipt #<?php echo $receiptNumber; ?></p>
                </div>
                <div class="text-right">
                    <h2 class="text-xl font-semibold"><?php echo htmlspecialchars($payment['property_name']); ?></h2>
                    <p class="text-gray-600"><?php echo htmlspecialchars($propertyAddress); ?></p>
                </div>
            </div>

            <!-- Receipt Details -->
            <div class="grid grid-cols-2 gap-6 mb-8">
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-1">From</h3>
                    <p class="font-medium"><?php echo htmlspecialchars($payment['owner_first_name'] . ' ' . $payment['owner_last_name']); ?></p>
                    <p><?php echo htmlspecialchars($payment['owner_email']); ?></p>
                    <p><?php echo htmlspecialchars($payment['owner_phone']); ?></p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-1">To</h3>
                    <p class="font-medium"><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></p>
                    <p><?php echo htmlspecialchars($payment['email']); ?></p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-1">Payment Date</h3>
                    <p><?php echo $paymentDate; ?></p>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-1">Payment Method</h3>
                    <p><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></p>
                </div>
            </div>

            <!-- Payment Details -->
            <div class="border rounded-lg overflow-hidden mb-8">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    <?php 
                                    $paymentType = ucfirst(str_replace('_', ' ', $payment['payment_type']));
                                    echo $paymentType . ' Payment';
                                    ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    For period: <?php echo date('M Y', strtotime($payment['payment_date'])); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <?php echo formatCurrency($payment['amount']); ?>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td class="px-6 py-3 text-right text-sm font-medium">Total</td>
                            <td class="px-6 py-3 text-right text-sm font-medium"><?php echo formatCurrency($payment['amount']); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Notes -->
            <?php if (!empty($payment['notes'])): ?>
            <div class="mb-8">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Notes</h3>
                <p class="text-sm text-gray-600 bg-gray-50 p-4 rounded-lg"><?php echo nl2br(htmlspecialchars($payment['notes'])); ?></p>
            </div>
            <?php endif; ?>

            <!-- Thank You Message -->
            <div class="text-center border-t pt-6">
                <p class="text-gray-600">Thank you for your payment!</p>
                <p class="text-sm text-gray-500 mt-1">This receipt was automatically generated and is valid without a signature.</p>
            </div>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
    function downloadReceiptAsPDF() {
        // Show loading indicator
        const loadingIndicator = document.createElement('div');
        loadingIndicator.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        loadingIndicator.innerHTML = '<div class="bg-white p-4 rounded-lg"><p class="text-gray-800">Generating PDF...</p></div>';
        document.body.appendChild(loadingIndicator);
        
        // Get the receipt container
        const receiptElement = document.querySelector('.print-container');
        
        // Set options for html2canvas
        const options = {
            scale: 2, // Higher scale for better quality
            useCORS: true,
            allowTaint: true,
            scrollX: 0,
            scrollY: 0
        };
        
        // Use html2canvas to capture the receipt as an image
        html2canvas(receiptElement, options).then(canvas => {
            // Create a new jsPDF instance
            const doc = new jspdf.jsPDF({
                orientation: 'portrait',
                unit: 'mm',
                format: 'a4'
            });
            
            // Calculate dimensions to fit the receipt on the page
            const imgData = canvas.toDataURL('image/png');
            const pageWidth = doc.internal.pageSize.getWidth();
            const pageHeight = doc.internal.pageSize.getHeight();
            
            const canvasWidth = canvas.width;
            const canvasHeight = canvas.height;
            
            // Calculate the scale to fit the width of the page
            const scale = pageWidth / canvasWidth;
            const scaledHeight = canvasHeight * scale;
            
            // If the scaled height is greater than the page height, adjust the scale
            const finalScale = scaledHeight > pageHeight ? pageHeight / scaledHeight * scale : scale;
            
            const finalWidth = canvasWidth * finalScale;
            const finalHeight = canvasHeight * finalScale;
            
            // Add the image to the PDF
            doc.addImage(imgData, 'PNG', 
                (pageWidth - finalWidth) / 2, // Center horizontally
                10, // Top margin
                finalWidth, 
                finalHeight
            );
            
            // Save the PDF with a meaningful filename
            const receiptNumber = '<?php echo $receiptNumber; ?>';
            doc.save('Payment_Receipt_' + receiptNumber + '.pdf');
            
            // Remove loading indicator
            document.body.removeChild(loadingIndicator);
        });
    }
</script>

</body>
</html>