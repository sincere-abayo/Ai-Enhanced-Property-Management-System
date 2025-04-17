<?php
require_once 'db_connect.php';

/**
 * Get property count for a landlord
 */
function getPropertyCount($landlordId) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM properties WHERE landlord_id = ?');
    $stmt->execute([$landlordId]);
    return $stmt->fetch()['total'];
}

/**
 * Get new properties added this month for a landlord
 */
function getNewPropertiesThisMonth($landlordId) {
    global $pdo;
    $stmt = $pdo->prepare('
        SELECT COUNT(*) as total 
        FROM properties 
        WHERE landlord_id = ? 
        AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
    ');
    $stmt->execute([$landlordId]);
    return $stmt->fetch()['total'];
}

/**
 * Get tenant count for a landlord
 */
function getTenantCount($landlordId) {
    global $pdo;
    $stmt = $pdo->prepare('
        SELECT COUNT(DISTINCT tenant_id) as total 
        FROM leases 
        JOIN properties ON leases.property_id = properties.property_id 
        WHERE properties.landlord_id = ? AND leases.status = "active"
    ');
    $stmt->execute([$landlordId]);
    return $stmt->fetch()['total'];
}

/**
 * Get new tenants added this month for a landlord
 */
function getNewTenantsThisMonth($landlordId) {
    global $pdo;
    $stmt = $pdo->prepare('
        SELECT COUNT(DISTINCT tenant_id) as total 
        FROM leases 
        JOIN properties ON leases.property_id = properties.property_id 
        WHERE properties.landlord_id = ? 
        AND MONTH(leases.created_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(leases.created_at) = YEAR(CURRENT_DATE())
    ');
    $stmt->execute([$landlordId]);
    return $stmt->fetch()['total'];
}

/**
 * Get monthly income for a landlord
 */
function getMonthlyIncome($landlordId) {
    global $pdo;
    $stmt = $pdo->prepare('
        SELECT SUM(leases.monthly_rent) as total 
        FROM leases 
        JOIN properties ON leases.property_id = properties.property_id 
        WHERE properties.landlord_id = ? AND leases.status = "active"
    ');
    $stmt->execute([$landlordId]);
    $result = $stmt->fetch()['total'];
    return $result ? $result : 0;
}


/**
 * Get income percentage change compared to last month
 */
function getIncomePercentageChange($landlordId) {
    global $pdo;
    
    // Current month income
    $stmt = $pdo->prepare('
        SELECT SUM(amount) as total 
        FROM payments p
        JOIN leases l ON p.lease_id = l.lease_id
        JOIN properties pr ON l.property_id = pr.property_id
        WHERE pr.landlord_id = ? 
        AND MONTH(p.payment_date) = MONTH(CURRENT_DATE()) 
        AND YEAR(p.payment_date) = YEAR(CURRENT_DATE())
    ');
    $stmt->execute([$landlordId]);
    $currentMonthIncome = $stmt->fetch()['total'] ?: 0;
    
    // Last month income
    $stmt = $pdo->prepare('
        SELECT SUM(amount) as total 
        FROM payments p
        JOIN leases l ON p.lease_id = l.lease_id
        JOIN properties pr ON l.property_id = pr.property_id
        WHERE pr.landlord_id = ? 
        AND MONTH(p.payment_date) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) 
        AND YEAR(p.payment_date) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
    ');
    $stmt->execute([$landlordId]);
    $lastMonthIncome = $stmt->fetch()['total'] ?: 0;
    
    if ($lastMonthIncome == 0) {
        return 100; // If last month was 0, we consider it a 100% increase
    }
    
    return round((($currentMonthIncome - $lastMonthIncome) / $lastMonthIncome) * 100, 1);
}

/**
 * Get pending payments information
 */
function getPendingPayments($landlordId) {
    global $pdo;
    $stmt = $pdo->prepare('
        SELECT COUNT(*) as total, SUM(l.monthly_rent) as amount
        FROM leases l
        JOIN properties p ON l.property_id = p.property_id
        LEFT JOIN (
            SELECT lease_id, MAX(payment_date) as last_payment_date
            FROM payments
            GROUP BY lease_id
        ) pm ON l.lease_id = pm.lease_id
        WHERE p.landlord_id = ? 
        AND l.status = "active" 
        AND (
            pm.last_payment_date IS NULL 
            OR pm.last_payment_date < DATE_FORMAT(CURRENT_DATE() - INTERVAL (l.payment_due_day - 1) DAY, "%Y-%m-01")
        )
    ');
    $stmt->execute([$landlordId]);
    $result = $stmt->fetch();
    return [
        'total' => $result['total'] ?: 0,
        'amount' => $result['amount'] ?: 0
    ];
}

/**
 * Get recent activities
 */function getRecentActivities($landlordId, $limit = 5) {
    global $pdo;
    $stmt = $pdo->prepare('
        (SELECT 
            "payment" as type, 
            p.payment_date as date, 
            CONCAT("Payment received from ", u.first_name, " ", u.last_name) as description,
            p.amount as amount,
            pr.property_name as property_name
        FROM payments p
        JOIN leases l ON p.lease_id = l.lease_id
        JOIN properties pr ON l.property_id = pr.property_id
        JOIN users u ON l.tenant_id = u.user_id
        WHERE pr.landlord_id = ?
        ORDER BY p.payment_date DESC
        LIMIT 3)
        
        UNION
        
        (SELECT 
            "maintenance" as type, 
            m.created_at as date, 
            CONCAT("Maintenance request for ", pr.property_name, ": ", m.title) as description,
            m.estimated_cost as amount,
            pr.property_name as property_name
        FROM maintenance_requests m
        JOIN properties pr ON m.property_id = pr.property_id
        WHERE pr.landlord_id = ?
        ORDER BY m.created_at DESC
        LIMIT 3)
        
        UNION
        
        (SELECT 
            "lease" as type, 
            l.created_at as date, 
            CONCAT("New lease for ", pr.property_name, " with ", u.first_name, " ", u.last_name) as description,
            l.monthly_rent as amount,
            pr.property_name as property_name
        FROM leases l
        JOIN properties pr ON l.property_id = pr.property_id
        JOIN users u ON l.tenant_id = u.user_id
        WHERE pr.landlord_id = ?
        ORDER BY l.created_at DESC
        LIMIT 3)
        
        ORDER BY date DESC
        LIMIT ' . (int)$limit
    );
    $stmt->execute([$landlordId, $landlordId, $landlordId]);
    return $stmt->fetchAll();
}


/**
 * Get upcoming tasks
 */
function getUpcomingTasks($landlordId, $limit = 5) {
    global $pdo;
    $stmt = $pdo->prepare('
        (SELECT 
            "lease" as type, 
            l.end_date as date, 
            CONCAT("Lease Renewal - ", pr.property_name) as description,
            u.first_name as tenant_first_name,
            u.last_name as tenant_last_name,
            pr.property_name as property_name
        FROM leases l
        JOIN properties pr ON l.property_id = pr.property_id
        JOIN users u ON l.tenant_id = u.user_id
        WHERE pr.landlord_id = ? 
        AND l.end_date BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY)
        AND l.status = "active"
        ORDER BY l.end_date ASC
        LIMIT 3)
        
        UNION
        
        (SELECT 
            "maintenance" as type, 
            m.created_at as date, 
            CONCAT("Maintenance Check - ", pr.property_name) as description,
            u.first_name as tenant_first_name,
            u.last_name as tenant_last_name,
            pr.property_name as property_name
        FROM maintenance_requests m
        JOIN properties pr ON m.property_id = pr.property_id
        JOIN users u ON m.tenant_id = u.user_id
        WHERE pr.landlord_id = ? 
        AND m.status IN ("pending", "assigned")
        ORDER BY 
            CASE m.priority
                WHEN "emergency" THEN 1
                WHEN "high" THEN 2
                WHEN "medium" THEN 3
                WHEN "low" THEN 4
            END,
            m.created_at ASC
        LIMIT 3)
        
        UNION
        
        (SELECT 
            "payment" as type, 
            DATE_ADD(LAST_DAY(CURRENT_DATE()), INTERVAL 1 DAY) as date, 
            "Rent Collection - All Properties" as description,
            "" as tenant_first_name,
            "" as tenant_last_name,
            "All Properties" as property_name
        FROM dual
        LIMIT 1)
        
        ORDER BY date ASC
        LIMIT ' . (int)$limit
    );
    $stmt->execute([$landlordId, $landlordId]);
    return $stmt->fetchAll();
}

/**
 * Format relative time (e.g., "2 hours ago")
 */
function getRelativeTime($dateString) {
    $date = new DateTime($dateString);
    $now = new DateTime();
    $diff = $now->diff($date);
    
    if ($diff->y > 0) {
        return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    }
    if ($diff->m > 0) {
        return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    }
    if ($diff->d > 0) {
        if ($diff->d == 1) {
            return 'Yesterday';
        }
        return $diff->d . ' days ago';
    }
    if ($diff->h > 0) {
        return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    }
    if ($diff->i > 0) {
        return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    }
    
    return 'Just now';
}

/**
 * Format due date (e.g., "Due in 5 days")
 */
function getDueText($dateString) {
    $date = new DateTime($dateString);
    $now = new DateTime();
    $diff = $now->diff($date);
    
    if ($diff->invert) {
        if ($diff->d == 0) {
            return 'Due today';
        }
        return 'Overdue by ' . $diff->d . ' day' . ($diff->d > 1 ? 's' : '');
    } else {
        if ($diff->d == 0) {
            return 'Due today';
        }
        return 'Due in ' . $diff->d . ' day' . ($diff->d > 1 ? 's' : '');
    }
}
function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->d > 0) {
        return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    }
    
    if ($diff->h > 0) {
        return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    }
    
    if ($diff->i > 0) {
        return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    }
    
    return 'Just now';
}
?>
