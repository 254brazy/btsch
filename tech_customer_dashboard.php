<?php

// dashboard.php - Main customer dashboard
require_once 'secrete.php';
 // Your existing authentication file

// Get customer ID from session
session_start();

if (!isset($_SESSION['customerid'])){
    header("Location:tech_customer_login.php");

}

$customer_id = $_SESSION['customerid'];

// Fetch customer's active jobs
$jobs_query = "SELECT * FROM tech_jobs WHERE customer_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($jobs_query);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$jobs_result = $stmt->get_result();

// Count jobs by status
$counts_query = "SELECT 
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'inprogress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'waiting-approval' THEN 1 ELSE 0 END) as waiting_approval,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    COUNT(*) as total
    FROM tech_jobs WHERE customer_id = ?";
$stmt = $conn->prepare($counts_query);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$counts_result = $stmt->get_result();
$counts = $counts_result->fetch_assoc();

// Fetch pending quotes
$quotes_query = "SELECT q.*, j.item, j.model 
                 FROM tech_job_prequotes q 
                 JOIN tech_jobs j ON q.jobid = j.id 
                 WHERE j.customer_id = ? AND q.status != 'approved' AND q.status != 'fully paid'
                 ORDER BY q.created_at DESC";
$stmt = $conn->prepare($quotes_query);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$quotes_result = $stmt->get_result();

// Fetch unpaid invoices
$invoices_query = "SELECT i.*, j.item, j.model 
                   FROM tech_job_invoices i 
                   JOIN tech_jobs j ON i.jobid = j.id 
                   WHERE j.customer_id = ?
                   ORDER BY i.created_at DESC";
$stmt = $conn->prepare($invoices_query);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$invoices_result = $stmt->get_result();

// Calculate total due
$total_due_query = "SELECT SUM(i.balance) as total_due 
                    FROM tech_job_invoices i 
                    JOIN tech_jobs j ON i.jobid = j.id 
                    WHERE j.customer_id = ?";
$stmt = $conn->prepare($total_due_query);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$total_due_result = $stmt->get_result();
$total_due = $total_due_result->fetch_assoc()['total_due'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Portal - Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-black: #121212;
            --secondary-black: #1e1e1e;
            --accent-red: #e53935;
            --accent-green: #4caf50;
            --accent-white: #ffffff;
            --text-light: #f5f5f5;
            --text-gray: #b0b0b0;
            --border-color: #333333;
            --card-bg: #1a1a1a;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--primary-black);
            color: var(--text-light);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Header Styles */
        header {
            background-color: var(--secondary-black);
            padding: 15px 0;
            border-bottom: 2px solid var(--accent-red);
            margin-bottom: 30px;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .logo i {
            color: var(--accent-red);
            font-size: 28px;
        }
        
        .logo h1 {
            font-size: 24px;
            font-weight: 700;
        }
        
        .logo span {
            color: var(--accent-green);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background-color: var(--accent-red);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .logout-btn {
            background-color: transparent;
            border: 1px solid var(--accent-red);
            color: var(--accent-red);
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .logout-btn:hover {
            background-color: var(--accent-red);
            color: var(--primary-black);
        }
        
        /* Main Content */
        .dashboard-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 30px;
        }
        
        /* Sidebar */
        .sidebar {
            background-color: var(--card-bg);
            border-radius: 8px;
            padding: 20px;
            height: fit-content;
        }
        
        .sidebar h3 {
            color: var(--accent-green);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .nav-menu {
            list-style: none;
        }
        
        .nav-menu li {
            margin-bottom: 10px;
        }
        
        .nav-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 15px;
            color: var(--text-light);
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .nav-menu a:hover, .nav-menu a.active {
            background-color: var(--secondary-black);
            color: var(--accent-green);
        }
        
        .nav-menu i {
            width: 20px;
            text-align: center;
        }
        
        /* Main Content */
        .main-content {
            background-color: var(--card-bg);
            border-radius: 8px;
            padding: 25px;
        }
        
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .content-header h2 {
            color: var(--accent-white);
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: var(--secondary-black);
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid var(--accent-red);
        }
        
        .stat-card.green-border {
            border-left-color: var(--accent-green);
        }
        
        .stat-card h4 {
            font-size: 14px;
            color: var(--text-gray);
            margin-bottom: 10px;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: var(--accent-white);
        }
        
        /* Table Styles */
        .table-container {
            overflow-x: auto;
            margin-bottom: 30px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            background-color: var(--secondary-black);
            padding: 15px;
            text-align: left;
            color: var(--accent-green);
            font-weight: 600;
        }
        
        .data-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .data-table tr:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-pending {
            background-color: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }
        
        .status-in-progress {
            background-color: rgba(33, 150, 243, 0.2);
            color: #2196f3;
        }
        
        .status-completed {
            background-color: rgba(76, 175, 80, 0.2);
            color: var(--accent-green);
        }
        
        .status-waiting-approval {
            background-color: rgba(233, 30, 99, 0.2);
            color: #e91e63;
        }
        
        .action-btn {
            padding: 8px 15px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            margin-right: 5px;
            text-decoration: none;
            display: inline-block;
        }
        
        .view-btn {
            background-color: var(--secondary-black);
            color: var(--text-light);
            border: 1px solid var(--border-color);
        }
        
        .approve-btn {
            background-color: var(--accent-green);
            color: var(--primary-black);
        }
        
        .decline-btn {
            background-color: var(--accent-red);
            color: var(--primary-black);
        }
        
        .pay-btn {
            background-color: #2196f3;
            color: var(--primary-black);
        }
        
        .view-btn:hover, .approve-btn:hover, .decline-btn:hover, .pay-btn:hover {
            opacity: 0.9;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background-color: var(--card-bg);
            width: 90%;
            max-width: 700px;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .modal-header {
            background-color: var(--secondary-black);
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }
        
        .modal-header h3 {
            color: var(--accent-green);
        }
        
        .close-modal {
            background: none;
            border: none;
            color: var(--text-light);
            font-size: 24px;
            cursor: pointer;
        }
        
        .modal-body {
            padding: 20px;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .job-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .detail-item h4 {
            color: var(--text-gray);
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .detail-item p {
            color: var(--text-light);
            font-weight: 500;
        }
        
        .parts-list, .services-list {
            margin-top: 20px;
        }
        
        .parts-list h4, .services-list h4 {
            color: var(--accent-green);
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .payment-form {
            display: grid;
            gap: 15px;
            margin-top: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 5px;
            color: var(--text-gray);
        }
        
        .form-group input, .form-group select {
            padding: 12px;
            background-color: var(--secondary-black);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            color: var(--text-light);
        }
        
        .submit-payment {
            background-color: var(--accent-green);
            color: var(--primary-black);
            border: none;
            padding: 12px;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
        }
        
        .quote-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 20px;
        }
        
        .tab-btn {
            padding: 12px 20px;
            background: none;
            border: none;
            color: var(--text-gray);
            cursor: pointer;
            font-weight: 600;
            border-bottom: 3px solid transparent;
        }
        
        .tab-btn.active {
            color: var(--accent-green);
            border-bottom-color: var(--accent-green);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Footer */
        footer {
            margin-top: 40px;
            padding: 20px 0;
            text-align: center;
            color: var(--text-gray);
            border-top: 1px solid var(--border-color);
            font-size: 14px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }
            
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .job-details-grid {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            
            .tabs {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-tools"></i>
                    <h1>BTSCH <span>Customer-Portal</span></h1>
                </div>
                <div class="user-info">
                    <div class="user-avatar"><?php echo substr($_SESSION['username'], 0, 2); ?></div>
                    <div>
                        <p><?php echo htmlspecialchars($_SESSION['customer_full_name']); ?></p>
                        <p class="user-email"><?php echo htmlspecialchars($_SESSION['customeremail']); ?></p>
                    </div>
                    <a href="customer_logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Main Content -->
    <div class="container">
        <div class="dashboard-container">
            <!-- Sidebar -->
            <aside class="sidebar">
                <h3>Dashboard Menu</h3>
                <ul class="nav-menu">
                    <li><a href="#jobs" class="active" onclick="showTab('jobs')"><i class="fas fa-tasks"></i> My Jobs</a></li>
                    <li><a href="#quotes" onclick="showTab('quotes')"><i class="fas fa-file-invoice-dollar"></i> Quotes</a></li>
                    <li><a href="#invoices" onclick="showTab('invoices')"><i class="fas fa-receipt"></i>Pending Invoices</a></li>
                    
                    <li><a href="#payments" onclick="showTab('payments')"><i class="fas fa-credit-card"></i> Payments</a></li>
                    <li><a href="#profile" onclick="showTab('profile')"><i class="fas fa-user-circle"></i> My Profile</a></li>
                </ul>
            </aside>
            
            <!-- Main Content Area -->
            <main class="main-content">
                <!-- Tabs -->
                <div class="tabs">
                    <button class="tab-btn active" data-tab="jobs">My Jobs</button>
                    <button class="tab-btn" data-tab="quotes">Quotes</button>
                    <button class="tab-btn" data-tab="invoices">Invoices</button>
                    <button class="tab-btn" data-tab="payments">Payments</button>
                    <button class="tab-btn" data-tab="profile">Profile</button>
                </div>
                
                <!-- Stats Cards -->
                <div class="stats-cards">
                    <div class="stat-card">
                        <h4>Active Jobs</h4>
                        <div class="stat-value"><?php echo ($counts['pending'] + $counts['in_progress'] + $counts['waiting_approval']); ?></div>
                    </div>
                    <div class="stat-card green-border">
                        <h4>Pending Quotes</h4>
                        <div class="stat-value"><?php echo $quotes_result->num_rows; ?></div>
                    </div>
                    <div class="stat-card">
                        <h4>Total Due</h4>
                        <div class="stat-value">Kes<?php echo number_format($total_due, 2); ?></div>
                    </div>
                    <div class="stat-card green-border">
                        <h4>Completed Jobs</h4>
                        <div class="stat-value"><?php echo $counts['completed']; ?></div>
                    </div>
                </div>
                
                <!-- Jobs Tab Content -->
                <div id="jobs-tab" class="tab-content active">
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Job ID</th>
                                    <th>Item</th>
                                    <th>Model</th>
                                    <th>Status</th>
                                    <th>Created Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($job = $jobs_result->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo $job['id']; ?></td>
                                    <td><?php echo htmlspecialchars($job['item']); ?></td>
                                    <td><?php echo htmlspecialchars($job['model']); ?></td>
                                    <td>
                                        <?php 
                                        $status_class = 'status-' . str_replace(' ', '-', $job['status']);
                                        $status_text = ucfirst(str_replace('-', ' ', $job['status']));
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($job['created_at'])); ?></td>
                                    <td>
                                        <button class="action-btn view-btn" onclick="viewJobDetails(<?php echo $job['id']; ?>)">View Details</button>
                                        <?php if($job['status'] == 'unapproved'): ?>
                                            <button class="action-btn approve-btn" onclick="openQuoteModal(<?php echo $job['id']; ?>)">Review Quote</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Quotes Tab Content -->
                <div id="quotes-tab" class="tab-content">
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Quote ID</th>
                                    <th>Job ID</th>
                                    <th>Item</th>
                                    <th>Model</th>
                                    <th>Total Amount</th>
                                    <th>Status</th>
                                    <th>Created Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($quote = $quotes_result->fetch_assoc()): ?>
                                <tr>
                                    <td>QID-<?php echo $quote['id']; ?></td>
                                    <td>JIDS-<?php echo $quote['jobid']; ?></td>
                                    <td><?php echo htmlspecialchars($quote['item']); ?></td>
                                    <td><?php echo htmlspecialchars($quote['model']); ?></td>
                                    <td>Kes <?php echo number_format($quote['total'], 2); ?></td>
                                    <td>
                                        <span class="status-badge status-waiting-approval"><?php echo htmlspecialchars($quote['status']); ?></span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($quote['created_at'])); ?></td>
                                    <td>
                                       
                                       

                                         <?php if($quote['status'] !== 'approved'): ?>
                                                    <a href="customer_process_quote.php?action=approve&quote_id=<?php echo $quote['id']; ?>" class="action-btn approve-btn">Approve</a>
                                                    <a href="customer_process_quote.php?action=decline&quote_id=<?php echo $quote['id']; ?>" class="action-btn decline-btn">Decline</a>
                                         <?php else: ?>
 <button class="action-btn view-btn" onclick="viewQuoteDetails(<?php echo $quote['id']; ?>)">View Quote</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Invoices Tab Content -->
                <div id="invoices-tab" class="tab-content">
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Invoice ID</th>
                                    <th>Job ID</th>
                                    <th>Item</th>
                                    <th>Total Amount</th>
                                    <th>Balance Due</th>
                                    <th>Status</th>
                                    <th>Created Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($invoice = $invoices_result->fetch_assoc()): ?>
                                <?php
                                $status = $invoice['balance'] == 0 ? 'Paid' : 'Unpaid';
                                $status_class = $invoice['balance'] == 0 ? 'status-completed' : 'status-pending';
                                ?>
                                <tr>
                                    <td>#<?php echo $invoice['id']; ?></td>
                                    <td>#<?php echo $invoice['jobid']; ?></td>
                                    <td><?php echo htmlspecialchars($invoice['item']); ?></td>
                                    <td>Kes <?php echo number_format($invoice['total'], 2); ?></td>
                                    <td>Kes <?php echo number_format($invoice['balance'], 2); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $status_class; ?>"><?php echo $status; ?></span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($invoice['created_at'])); ?></td>
                                    <td>
                                        <?php if($invoice['balance'] > 0): ?>
                                            <button class="action-btn pay-btn" onclick="openPaymentModal(<?php echo $invoice['id']; ?>, <?php echo $invoice['balance']; ?>)">Make Payment</button>
                                        <?php endif; ?>
                                        <button class="action-btn view-btn" onclick="viewInvoiceDetails(<?php echo $invoice['id']; ?>)">View</button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Payments Tab Content -->
                <div id="payments-tab" class="tab-content">
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Payment ID</th>
                                    <th>Invoice ID</th>
                                    <th>Amount Paid</th>
                                    <th>Payment Mode</th>
                                    <th>Payment Type</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Fetch payments
                                $payments_query = "SELECT p.*, i.jobid 
                                                  FROM tech_job_payments p 
                                                  JOIN tech_job_invoices i ON p.invoiceid = i.id 
                                                  JOIN tech_jobs j ON i.jobid = j.id 
                                                  WHERE j.customer_id = ? 
                                                  ORDER BY p.created_at DESC";
                                $stmt = $conn->prepare($payments_query);
                                $stmt->bind_param("i", $customer_id);
                                $stmt->execute();
                                $payments_result = $stmt->get_result();
                                
                                while($payment = $payments_result->fetch_assoc()):
                                ?>
                                <tr>
                                    <td>#<?php echo $payment['id']; ?></td>
                                    <td>#<?php echo $payment['invoiceid']; ?></td>
                                    <td>Kes <?php echo number_format($payment['paid'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($payment['mode']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['paymenttype']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($payment['created_at'])); ?></td>
                                    <td>
                                        <span class="status-badge status-completed"><?php echo htmlspecialchars($payment['status']); ?></span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Profile Tab Content -->
                <div id="profile-tab" class="tab-content">
                    <?php
                    // Fetch customer details
                    $profile_query = "SELECT * FROM customers WHERE id = ?";
                    $stmt = $conn->prepare($profile_query);
                    $stmt->bind_param("i", $customer_id);
                    $stmt->execute();
                    $profile_result = $stmt->get_result();
                    $customer_profile = $profile_result->fetch_assoc();
                    ?>
                    
                    <div class="job-details-grid">
                        <div class="detail-item">
                            <h4>First Name</h4>
                            <p><?php echo htmlspecialchars($customer_profile['fname']); ?></p>
                        </div>
                        <div class="detail-item">
                            <h4>Last Name</h4>
                            <p><?php echo htmlspecialchars($customer_profile['sname']); ?></p>
                        </div>
                        <div class="detail-item">
                            <h4>Other Name</h4>
                            <p><?php echo htmlspecialchars($customer_profile['oname'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="detail-item">
                            <h4>Username</h4>
                            <p><?php echo htmlspecialchars($customer_profile['username']); ?></p>
                        </div>
                        <div class="detail-item">
                            <h4>Email</h4>
                            <p><?php echo htmlspecialchars($customer_profile['email']); ?></p>
                        </div>
                        <div class="detail-item">
                            <h4>Phone</h4>
                            <p><?php echo htmlspecialchars($customer_profile['tel']); ?></p>
                        </div>
                        <div class="detail-item">
                            <h4>Location</h4>
                            <p><?php echo htmlspecialchars($customer_profile['location']); ?></p>
                        </div>
                        <div class="detail-item">
                            <h4>Company</h4>
                            <p><?php echo htmlspecialchars($customer_profile['company']); ?></p>
                        </div>
                        <div class="detail-item">
                            <h4>Branch</h4>
                            <p><?php echo htmlspecialchars($customer_profile['branch']); ?></p>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Job Details Modal -->
    <div class="modal" id="job-details-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Job Details</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="job-details-body">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
    
    <!-- Quote Details Modal -->
    <div class="modal" id="quote-details-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Quote Details</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="quote-details-body">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
    
    <!-- Payment Modal -->
    <div class="modal" id="payment-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Make Payment</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="payment-form" method="POST" action="customer_process_payments.php">
                    <input type="hidden" id="payment-invoice-id" name="invoice_id">
                    <input type="hidden" id="payment-job-id" name="job_id">
                    
                    <div class="form-group">
                        <label for="payment-amount">Amount to Pay ($)</label>
                        <input type="number" id="payment-amount" name="amount" min="1" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment-mode">Payment Mode</label>
                        <select id="payment-mode" name="mode" required>
                            <option value="credit_card">Credit Card</option>
                            <option value="debit_card">Debit Card</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cash">Cash</option>
                            <option value="mobile_money">Mobile Money</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment-type">Payment Type</label>
                        <select id="payment-type" name="payment_type" required>
                            <option value="full">Full Payment</option>
                            <option value="partial">Partial Payment</option>
                            <option value="deposit">Deposit</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment-reference">Payment Reference/Transaction ID</label>
                        <input type="text" id="payment-reference" name="reference" placeholder="Enter transaction reference eg. cash, if no refrence" required>
                    </div>
                    
                    <button type="submit" class="submit-payment">Submit Payment For Verification
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer>
        <div class="container">
            <p>&copy; 2023 BTSCH. All rights reserved. | Customer Service Portal v1.0</p>
            <p>Need help? Contact support@btsch.com or call +254 (704) 457-849</p>
        </div>
    </footer>
    
    <script>
        // Tab functionality
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked tab button
            document.querySelector(`.tab-btn[data-tab="${tabName}"]`).classList.add('active');
            
            // Update sidebar active link
            document.querySelectorAll('.nav-menu a').forEach(link => {
                link.classList.remove('active');
            });
            document.querySelector(`.nav-menu a[href="#${tabName}"]`).classList.add('active');
        }
        
        // Tab button click handlers
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                showTab(btn.dataset.tab);
            });
        });
        
        // Modal functions
        function closeModal() {
            document.getElementById('job-details-modal').style.display = 'none';
            document.getElementById('quote-details-modal').style.display = 'none';
            document.getElementById('payment-modal').style.display = 'none';
        }
        
        function viewJobDetails(jobId) {
            fetch(`ajax/get_job_details.php?job_id=${jobId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('job-details-body').innerHTML = data;
                    document.getElementById('job-details-modal').style.display = 'flex';
                });
        }
        
        function viewQuoteDetails(quoteId) {
            fetch(`ajax/get_quote_details.php?quote_id=${quoteId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('quote-details-body').innerHTML = data;
                    document.getElementById('quote-details-modal').style.display = 'flex';
                });
        }
        
        function viewInvoiceDetails(invoiceId) {
            fetch(`ajax/get_invoice_details.php?invoice_id=${invoiceId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('quote-details-body').innerHTML = data;
                    document.getElementById('quote-details-modal').style.display = 'flex';
                });
        }
        
        function openQuoteModal(jobId) {
            viewQuoteDetails(jobId);
        }
        
        function openPaymentModal(invoiceId, amount) {
            document.getElementById('payment-amount').value = amount;
            document.getElementById('payment-invoice-id').value = invoiceId;
            document.getElementById('payment-modal').style.display = 'flex';
        }
        
        function approveQuote(quoteId) {
            if(confirm('Are you sure you want to approve this quote?')) {
                fetch(`ajax/customer_process_quote.php?action=approve&quote_id=${quoteId}`)
                    .then(response => response.json())
                    .then(data => {
                        if(data.success) {
                            alert('Quote approved successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    });
            }
        }
        
        function declineQuote(quoteId) {
            if(confirm('Are you sure you want to decline this quote?')) {
                fetch(`ajax/customer_process_quote.php?action=decline&quote_id=${quoteId}`)
                    .then(response => response.json())
                    .then(data => {
                        if(data.success) {
                            alert('Quote declined.');
                            location.reload();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    });
            }
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            if(e.target.classList.contains('modal')) {
                closeModal();
            }
        });
    </script>
</body>
</html>

