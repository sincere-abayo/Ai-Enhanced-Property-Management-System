<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Property Management System</title>
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
</head>
<body class="bg-gray-50">
    <!-- Sidebar -->
     <?php include 'admin_sidebar.php'; ?>  
    <!-- Main Content -->
    <div class="ml-64 p-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Welcome back, John!</h2>
                <p class="text-gray-600">Here's what's happening with your properties today</p>
            </div>
            <div class="flex space-x-4">
                <button class="bg-white text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 border border-gray-300">
                    <i class="fas fa-bell mr-2"></i>Notifications
                </button>
                <button class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    <i class="fas fa-plus mr-2"></i>Add Property
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100">
                        <i class="fas fa-building text-blue-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">Total Properties</h3>
                        <p class="text-2xl font-semibold">12</p>
                        <p class="text-sm text-green-500">+2 this month</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100">
                        <i class="fas fa-users text-green-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">Active Tenants</h3>
                        <p class="text-2xl font-semibold">28</p>
                        <p class="text-sm text-green-500">+3 this month</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100">
                        <i class="fas fa-money-bill-wave text-yellow-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">Monthly Income</h3>
                        <p class="text-2xl font-semibold">$45,500</p>
                        <p class="text-sm text-green-500">+12.5% vs last month</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100">
                        <i class="fas fa-exclamation-circle text-red-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">Pending Payments</h3>
                        <p class="text-2xl font-semibold">3</p>
                        <p class="text-sm text-red-500">$2,500 overdue</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Recent Activities -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Recent Activities</h3>
                    <a href="#" class="text-primary text-sm hover:text-blue-700">View All</a>
                </div>
                <div class="space-y-4">
                    <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                        <div class="p-2 rounded-full bg-green-100">
                            <i class="fas fa-check text-green-500"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium">Payment received from Sarah Johnson</p>
                            <p class="text-xs text-gray-500">2 hours ago</p>
                        </div>
                    </div>
                    <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                        <div class="p-2 rounded-full bg-blue-100">
                            <i class="fas fa-file-alt text-blue-500"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium">New lease agreement for Modern Apartment</p>
                            <p class="text-xs text-gray-500">5 hours ago</p>
                        </div>
                    </div>
                    <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                        <div class="p-2 rounded-full bg-yellow-100">
                            <i class="fas fa-tools text-yellow-500"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium">Maintenance request for Studio Apartment</p>
                            <p class="text-xs text-gray-500">1 day ago</p>
                        </div>
                    </div>
                    <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                        <div class="p-2 rounded-full bg-red-100">
                            <i class="fas fa-exclamation-circle text-red-500"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium">Payment overdue from Mike Wilson</p>
                            <p class="text-xs text-gray-500">2 days ago</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upcoming Tasks -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Upcoming Tasks</h3>
                    <a href="#" class="text-primary text-sm hover:text-blue-700">View All</a>
                </div>
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center">
                            <div class="p-2 rounded-full bg-blue-100">
                                <i class="fas fa-calendar text-blue-500"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium">Lease Renewal - Modern Apartment</p>
                                <p class="text-xs text-gray-500">Due in 5 days</p>
                            </div>
                        </div>
                        <button class="text-primary hover:text-blue-700">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center">
                            <div class="p-2 rounded-full bg-yellow-100">
                                <i class="fas fa-tools text-yellow-500"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium">Maintenance Check - Studio Apartment</p>
                                <p class="text-xs text-gray-500">Due in 7 days</p>
                            </div>
                        </div>
                        <button class="text-primary hover:text-blue-700">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center">
                            <div class="p-2 rounded-full bg-green-100">
                                <i class="fas fa-money-bill-wave text-green-500"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium">Rent Collection - All Properties</p>
                                <p class="text-xs text-gray-500">Due in 10 days</p>
                            </div>
                        </div>
                        <button class="text-primary hover:text-blue-700">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="mt-8">
            <h3 class="text-lg font-semibold mb-4">Quick Actions</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <button class="bg-white p-4 rounded-xl shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex flex-col items-center">
                        <div class="p-3 rounded-full bg-blue-100 mb-2">
                            <i class="fas fa-plus text-blue-500 text-xl"></i>
                        </div>
                        <span class="text-sm font-medium">Add Property</span>
                    </div>
                </button>
                <button class="bg-white p-4 rounded-xl shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex flex-col items-center">
                        <div class="p-3 rounded-full bg-green-100 mb-2">
                            <i class="fas fa-user-plus text-green-500 text-xl"></i>
                        </div>
                        <span class="text-sm font-medium">Add Tenant</span>
                    </div>
                </button>
                <button class="bg-white p-4 rounded-xl shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex flex-col items-center">
                        <div class="p-3 rounded-full bg-yellow-100 mb-2">
                            <i class="fas fa-tools text-yellow-500 text-xl"></i>
                        </div>
                        <span class="text-sm font-medium">New Maintenance</span>
                    </div>
                </button>
                <button class="bg-white p-4 rounded-xl shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex flex-col items-center">
                        <div class="p-3 rounded-full bg-purple-100 mb-2">
                            <i class="fas fa-file-invoice-dollar text-purple-500 text-xl"></i>
                        </div>
                        <span class="text-sm font-medium">Record Payment</span>
                    </div>
                </button>
            </div>
        </div>
    </div>
</body>
</html>