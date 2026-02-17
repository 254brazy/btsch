<?php
if(session_status()===PHP_SESSION_NONE){
    session_start();
}
require 'secrete.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: company_login.php");
    exit();
}

$message = '';

if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['generatepayment'])){
    $jobid = htmlspecialchars(trim($_POST['jobid']));
    $approveddiscount = floatval(htmlspecialchars(trim($_POST['approveddiscount'])));
    $paymenttype = htmlspecialchars(trim($_POST['type']));
    $mode = htmlspecialchars(trim($_POST['mode']));
    $paymentAmount = floatval(htmlspecialchars(trim($_POST['amount'])));
    
    // Validate inputs
    if(empty($jobid) || empty($paymentAmount) || empty($paymenttype) || empty($mode)){
        $message = "<div class='message error'>Error: All fields are required!</div>"; 
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Get invoice details WITH LOCK for update
            $invoiceQuery = $conn->prepare("SELECT id, item, parts_subtotal, service_subtotal, total, balance, discount_applied, total_paid FROM tech_job_invoices WHERE jobid=? AND company=? AND branch=? FOR UPDATE");
            $invoiceQuery->bind_param("iss", $jobid, $_SESSION['company'], $_SESSION['branch']);
            $invoiceQuery->execute();
            $invoiceResult = $invoiceQuery->get_result();
            
            if($invoiceResult->num_rows == 0){
                throw new Exception("No invoice found for this job");
            }
            
            $invoice = $invoiceResult->fetch_assoc();
            $invoiceid = $invoice['id'];
            $invoiceitem = $invoice['item'];
            $invoicepartscharges = $invoice['parts_subtotal'];
            $invoiceservicecharges = $invoice['service_subtotal'];
            $invoicetotal = $invoice['total'];
            $currentBalance = $invoice['balance'];
            $discountApplied = $invoice['discount_applied'] ?? 0;
            $totalPaid = $invoice['total_paid'] ?? 0;
            
            // Get requested discount from prequote
            $prequoteQuery = $conn->prepare("SELECT discount FROM tech_job_prequotes WHERE jobid=? AND company=? AND branch=?");
            $prequoteQuery->bind_param("iss", $jobid, $_SESSION['company'], $_SESSION['branch']);
            $prequoteQuery->execute();
            $prequoteResult = $prequoteQuery->get_result();
            $prequote = $prequoteResult->fetch_assoc();
            $requestedDiscount = $prequote['discount'] ?? 0;
            
            // Calculate effective discount for this payment
            $effectiveDiscount = 0;
            
            // Only apply discount if approved discount is within limits
            if($approveddiscount > 0) {
                // Validate discount
                $maxDiscount = $currentBalance; // Cannot discount more than what's owed
                if($requestedDiscount > 0) {
                    $maxDiscount = min($maxDiscount, $requestedDiscount); // Cannot exceed requested
                }
                
                if($approveddiscount > $maxDiscount) {
                    throw new Exception("Discount cannot exceed KES " . $maxDiscount);
                }
                
                // Check if discount would make payment amount zero or negative
                if($approveddiscount >= $paymentAmount) {
                    // If discount equals or exceeds payment amount, adjust
                    if($approveddiscount > $paymentAmount) {
                        throw new Exception("Discount cannot exceed payment amount");
                    }
                    // If discount equals payment amount, mark as fully paid with discount
                    $effectiveDiscount = $approveddiscount;
                    $netPayment = 0;
                } else {
                    $effectiveDiscount = $approveddiscount;
                    $netPayment = $paymentAmount - $effectiveDiscount;
                }
            } else {
                $netPayment = $paymentAmount;
            }
            
            // Calculate new balance
            $newBalance = $currentBalance - $paymentAmount;
            
            // If discount was applied, adjust the balance
            if($effectiveDiscount > 0) {
                // The discount reduces the amount owed
                $newBalance = $currentBalance - ($paymentAmount + $effectiveDiscount);
            }
            
            // Ensure balance doesn't go negative
            if($newBalance < 0) {
                throw new Exception("Payment exceeds outstanding balance");
            }
            
            // Determine status
            if($newBalance == 0) {
                $status = 'fully paid';
            } else {
                $status = 'partially paid';
            }
            
            // Calculate total discount applied so far
            $totalDiscountApplied = $discountApplied + $effectiveDiscount;
            
            // Insert payment record
            $paymentInsert = $conn->prepare("INSERT INTO tech_job_payments (invoiceid, jobid, item, parts_subtotal, service_subtotal, total, paid, discount_given, balance, mode, paymenttype, company, branch, agent, status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $paymentInsert->bind_param("iisiiiiiissssss", $invoiceid, $jobid, $invoiceitem, $invoicepartscharges, $invoiceservicecharges, $invoicetotal, $paymentAmount, $effectiveDiscount, $newBalance, $mode, $paymenttype, $_SESSION['company'], $_SESSION['branch'], $_SESSION['username'], $status);
            
            if(!$paymentInsert->execute()){
                throw new Exception("Failed to record payment");
            }
            
            $paymentId = $conn->insert_id;
            
            // Update invoice with new balance and total discount
            $invoiceUpdate = $conn->prepare("UPDATE tech_job_invoices SET balance=?, discount_applied=?, total_paid=total_paid+?, status=? WHERE id=?");
            $invoiceUpdate->bind_param("iiisi", $newBalance, $totalDiscountApplied, $paymentAmount, $status, $invoiceid);
            
            if(!$invoiceUpdate->execute()){
                throw new Exception("Failed to update invoice");
            }
            
            // If fully paid, update prequote too
            if($newBalance == 0) {
                $prequoteUpdate = $conn->prepare("UPDATE tech_job_prequotes SET status='fully paid' WHERE jobid=?");
                $prequoteUpdate->bind_param("i", $jobid);
                $prequoteUpdate->execute();
            }
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['last_payment_id'] = $paymentId;
            
            // Success message
            $successMsg = "Payment of KES " . number_format($paymentAmount) . " processed successfully.";
            if($effectiveDiscount > 0) {
                $successMsg .= " Discount of KES " . number_format($effectiveDiscount) . " applied.";
            }
            if($newBalance > 0) {
                $successMsg .= " Remaining balance: KES " . number_format($newBalance);
            } else {
                $successMsg .= " Invoice fully paid!";
            }
            
            $message = "<div class='message success'>" . $successMsg . " <a href='btschtech_reciept.php?id=" . $paymentId . "' target='_blank'>View Receipt</a></div>";
            
        } catch (Exception $e) {
            $conn->rollback();
            $message = "<div class='message error'>Error: " . $e->getMessage() . "</div>";
        }
    }
}

// Get user data
$user_query = $conn->prepare("SELECT fname, sname, role, branch, company FROM users WHERE username = ?");
$user_query->bind_param("s", $_SESSION['username']);
$user_query->execute();
$user_result = $user_query->get_result();
$user_data = $user_result->fetch_assoc();

// Get invoices with outstanding balances
$invoicesQuery = $conn->prepare("SELECT i.*, 
                                (SELECT discount FROM tech_job_prequotes p WHERE p.jobid = i.jobid LIMIT 1) as requested_discount,
                                (SELECT COUNT(*) FROM tech_job_payments WHERE invoiceid = i.id) as payment_count
                                FROM tech_job_invoices i 
                                WHERE i.company=? AND i.branch=? 
                                ORDER BY i.created_at DESC");
$invoicesQuery->bind_param("ss", $_SESSION['company'], $_SESSION['branch']);
$invoicesQuery->execute();
$invoicesResult = $invoicesQuery->get_result();

// Get payment types
$paytypeQuery = $conn->prepare("SELECT id, type FROM tech_job_paymenttypes WHERE company=? AND branch=? AND status='active'");
$paytypeQuery->bind_param("ss", $_SESSION['company'], $_SESSION['branch']);
$paytypeQuery->execute();
$paytypeResult = $paytypeQuery->get_result();

// Get payment methods
$paymethodQuery = $conn->prepare("SELECT id, method FROM tech_job_paymentmethods WHERE company=? AND branch=? AND status='active'");
$paymethodQuery->bind_param("ss", $_SESSION['company'], $_SESSION['branch']);
$paymethodQuery->execute();
$paymethodResult = $paymethodQuery->get_result();

// Calculate statistics
$statsQuery = $conn->prepare("SELECT 
    COUNT(*) as total_invoices,
    SUM(CASE WHEN balance > 0 THEN 1 ELSE 0 END) as pending_invoices,
    SUM(balance) as total_outstanding,
    SUM(total_paid) as total_collected,
    SUM(discount_applied) as total_discounts
    FROM tech_job_invoices WHERE company=? AND branch=?");
$statsQuery->bind_param("ss", $_SESSION['company'], $_SESSION['branch']);
$statsQuery->execute();
$statsResult = $statsQuery->get_result();
$stats = $statsResult->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($_SESSION['company']); ?> - Payments Management</title>
    <link rel="stylesheet" href="universalstyle.css">
    <style>
        .discount-info {
            background-color: #e8f5e9;
            border: 1px solid #c8e6c9;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .invoice-summary {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e9ecef;
        }
        .summary-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .summary-label {
            font-weight: 600;
            color: #495057;
        }
        .summary-value {
            color: #212529;
            text-align: right;
        }
        .discount-applied {
            color: #28a745;
            font-weight: bold;
        }
        .discount-requested {
            color: #ff9800;
        }
        .amount-due {
            font-size: 1.1em;
            font-weight: bold;
            color: #dc3545;
        }
        .payment-amount {
            color: #2196f3;
            font-weight: bold;
        }
        .net-amount {
            color: #4caf50;
            font-weight: bold;
        }
        .calculation-area {
            background: #f1f8e9;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border: 1px solid #dcedc8;
        }
        .calculation-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            padding: 5px 0;
        }
        .calculation-row.total {
            border-top: 2px solid #4caf50;
            margin-top: 10px;
            padding-top: 10px;
            font-weight: bold;
        }
        .stat-card {
            flex: 1;
            min-width: 200px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 10px;
        }
        .stats-container {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .stat-value {
            font-size: 1.5em;
            font-weight: bold;
            margin-top: 5px;
        }
        .stat-collected { color: #28a745; }
        .stat-outstanding { color: #dc3545; }
        .stat-discounts { color: #ff9800; }
        .stat-total { color: #2196f3; }
    </style>
</head>
<body>
    <header>
        <div class="logo-section">
            <div class="logo-btsch">
                <div class="hub-center"></div>
                <div class="connection-line line-1"></div>
                <div class="connection-line line-2"></div>
                <div class="connection-line line-3"></div>
                <div class="connection-point point-1"></div>
                <div class="connection-point point-2"></div>
                <div class="connection-point point-3"></div>
            </div>
            <div class="logo-text">
                <div class="btsch">BTSCH</div>
            </div>
        </div>
        
        <div class="header-actions">
            <button type="button" class="btn btn-outline" onclick="openNavModal()">
                📱 Navigation
            </button>
        </div>
        
       <?php include 'userheader.html';?>
    </header>

    <main>
        <aside>
            <div class="sidebar-header">
                <div class="company-name"><?php echo htmlspecialchars($_SESSION['company']); ?></div>
                <div class="user-role">Payment Management</div>
            </div>
            <nav>
                <ul>
                   <?php include 'navigation.html';?>
                </ul>
            </nav>
        </aside>

        <article>
            <div class="dashboard-header">
                <h1 class="page-title">Payment Management</h1>
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="openFormModal('addPaymentModal')">➕ Add Payment</button>
                </div>
            </div>

            <?php echo $message; ?>

            <!-- Statistics Section -->
            <div class="stats-container">
                <div class="stat-card">
                    <h3>Total Invoices</h3>
                    <div class="stat-value stat-total"><?php echo $stats['total_invoices'] ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Collected</h3>
                    <div class="stat-value stat-collected">KES <?php echo number_format($stats['total_collected'] ?? 0); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Outstanding</h3>
                    <div class="stat-value stat-outstanding">KES <?php echo number_format($stats['total_outstanding'] ?? 0); ?></div>
                </div>
                <div class="stat-card">
                    <h3>Discounts Given</h3>
                    <div class="stat-value stat-discounts">KES <?php echo number_format($stats['total_discounts'] ?? 0); ?></div>
                </div>
            </div>

            <!-- Invoices List Section -->
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">Invoices</h2>
                </div>
                <div class="section-content">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Job ID</th>
                                    <th>Item</th>
                                    <th>Original Total</th>
                                    <th>Discounts</th>
                                    <th>Paid</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                    <th>Payments</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($invoicesResult->num_rows > 0): 
                                    while($invoice = $invoicesResult->fetch_assoc()): 
                                        $invoiceTotal = $invoice['total'];
                                        $discountApplied = $invoice['discount_applied'] ?? 0;
                                        $totalPaid = $invoice['total_paid'] ?? 0;
                                        $balance = $invoice['balance'];
                                        $requestedDiscount = $invoice['requested_discount'] ?? 0;
                                        $paymentCount = $invoice['payment_count'] ?? 0;
                                ?>
                                    <tr>
                                        <td>INV-<?php echo str_pad($invoice['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                        <td><?php echo $invoice['jobid']; ?></td>
                                        <td><?php echo htmlspecialchars($invoice['item']); ?></td>
                                        <td>KES <?php echo number_format($invoiceTotal); ?></td>
                                        <td>
                                            <?php if($discountApplied > 0): ?>
                                                <span class="discount-applied">-KES <?php echo number_format($discountApplied); ?></span>
                                                <?php if($requestedDiscount > $discountApplied): ?>
                                                    <br><small class="discount-requested">(Requested: KES <?php echo number_format($requestedDiscount); ?>)</small>
                                                <?php endif; ?>
                                            <?php elseif($requestedDiscount > 0): ?>
                                                <span class="discount-requested">KES <?php echo number_format($requestedDiscount); ?> requested</span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>KES <?php echo number_format($totalPaid); ?></td>
                                        <td>
                                            <?php if($balance > 0): ?>
                                                <span class="amount-due">KES <?php echo number_format($balance); ?></span>
                                            <?php else: ?>
                                                <span style="color: #28a745;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if($balance == 0): ?>
                                                <span style="color: #28a745; font-weight: bold;">● Fully Paid</span>
                                            <?php elseif($totalPaid > 0): ?>
                                                <span style="color: #ff9800; font-weight: bold;">● Partially Paid</span>
                                            <?php else: ?>
                                                <span style="color: #dc3545; font-weight: bold;">● Unpaid</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $paymentCount; ?> payment(s)</td>
                                        <td class="action-buttons">
                                            <?php if($balance > 0): ?>
                                                <button class="btn btn-sm btn-primary" onclick="addPaymentToInvoice(<?php echo $invoice['jobid']; ?>, <?php echo $balance; ?>)">Add Payment</button>
                                            <?php endif; ?>
                                            <a href="invoice_details.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-info">View</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="10" style="text-align: center; padding: 20px;">
                                            No invoices found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </article>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($_SESSION['company']); ?> - BTSCH Platform. All rights reserved.</p>
    </footer>
   
    <!-- ADD PAYMENT MODAL -->
    <div id="addPaymentModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Process Payment</h3>
                <span class="close" onclick="closeFormModal('addPaymentModal')">&times;</span>
            </div>
            <form method="POST" action="btsch_tech_payments.php" id="paymentForm">
                <div class="section-content">
                    <div id="invoiceSummary" class="invoice-summary" style="display: none;">
                        <!-- Invoice summary will appear here -->
                    </div>
                    
                    <div id="discountInfo" class="discount-info" style="display: none;">
                        <!-- Discount info will appear here -->
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="jobid">Select Invoice *</label>
                            <select name="jobid" id="jobid" required onchange="loadInvoiceDetails(this.value)">
                                <option value="">--Select Invoice--</option>
                                <?php 
                                $invoicesResult->data_seek(0);
                                while($invoice = $invoicesResult->fetch_assoc()): 
                                    if($invoice['balance'] > 0): // Only show invoices with balance
                                ?>
                                    <option value="<?php echo $invoice['jobid']; ?>"
                                            data-invoice-id="<?php echo $invoice['id']; ?>"
                                            data-total="<?php echo $invoice['total']; ?>"
                                            data-balance="<?php echo $invoice['balance']; ?>"
                                            data-discount-applied="<?php echo $invoice['discount_applied'] ?? 0; ?>"
                                            data-total-paid="<?php echo $invoice['total_paid'] ?? 0; ?>"
                                            data-requested-discount="<?php echo $invoice['requested_discount'] ?? 0; ?>"
                                            data-item="<?php echo htmlspecialchars($invoice['item']); ?>">
                                        INV-<?php echo str_pad($invoice['id'], 6, '0', STR_PAD_LEFT); ?> - <?php echo htmlspecialchars($invoice['item']); ?> (Balance: KES <?php echo number_format($invoice['balance']); ?>)
                                    </option>
                                <?php 
                                    endif;
                                endwhile; 
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="amount">Payment Amount *</label>
                            <div class="amount-input-group">
                                <input type="number" name="amount" id="amount" required 
                                       placeholder="Enter amount" min="1" step="1" oninput="calculateNetAmount()">
                                <span>KES</span>
                            </div>
                            <small>Maximum: <span id="maxAmount">0</span> KES</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="approveddiscount">Discount to Apply</label>
                            <div class="amount-input-group">
                                <input type="number" name="approveddiscount" id="approveddiscount" 
                                       placeholder="0" min="0" step="1" oninput="calculateNetAmount()">
                                <span>KES</span>
                            </div>
                            <small>Available: <span id="availableDiscount">0</span> KES</small>
                        </div>
                        
                        <div id="calculationArea" class="calculation-area" style="display: none;">
                            <div class="calculation-row">
                                <span>Payment Amount:</span>
                                <span id="calcPaymentAmount" class="payment-amount">0</span>
                            </div>
                            <div class="calculation-row">
                                <span>Discount Applied:</span>
                                <span id="calcDiscount" class="discount-applied">0</span>
                            </div>
                            <div class="calculation-row total">
                                <span>Effective Payment:</span>
                                <span id="calcNetAmount" class="net-amount">0</span>
                            </div>
                            <div class="calculation-row">
                                <span>New Balance:</span>
                                <span id="calcNewBalance" class="amount-due">0</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="type">Payment Type *</label>
                            <select name="type" id="paymenttype" required>
                                <option value="">--Select Type--</option>
                                <?php 
                                $paytypeResult->data_seek(0);
                                while($type = $paytypeResult->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($type['type']); ?>">
                                        <?php echo htmlspecialchars($type['type']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="mode">Payment Mode *</label>
                            <select name="mode" id="paymentmode" required>
                                <option value="">--Select Mode--</option>
                                <?php 
                                $paymethodResult->data_seek(0);
                                while($method = $paymethodResult->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($method['method']); ?>">
                                        <?php echo htmlspecialchars($method['method']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn" onclick="closeFormModal('addPaymentModal')">Cancel</button>
                    <button type="submit" name="generatepayment" class="btn btn-primary">Process Payment</button>
                </div>
            </form>
        </div>
    </div>

    <!-- NAVIGATION MODAL -->
    <div id="navModal" class="nav-modal" style="display: none;">
        <div class="nav-modal-content">
            <div class="nav-modal-header">
                <h3>🏠 Navigation Menu</h3>
                <span class="nav-close" onclick="closeNavModal()">&times;</span>
            </div>
            <div class="nav-modal-body">
                <nav class="mobile-nav">
                    <ul>
                        <?php include 'navigation.html';?>
                    </ul>
                </nav>
            </div>
        </div>
    </div>

    <script>
    let currentInvoice = {
        id: 0,
        jobid: 0,
        total: 0,
        balance: 0,
        discountApplied: 0,
        totalPaid: 0,
        requestedDiscount: 0,
        item: ''
    };
    
    function loadInvoiceDetails(jobId) {
        const select = document.getElementById('jobid');
        const selectedOption = select.options[select.selectedIndex];
        
        if(selectedOption.value) {
            currentInvoice = {
                id: parseInt(selectedOption.getAttribute('data-invoice-id')),
                jobid: selectedOption.value,
                total: parseFloat(selectedOption.getAttribute('data-total')),
                balance: parseFloat(selectedOption.getAttribute('data-balance')),
                discountApplied: parseFloat(selectedOption.getAttribute('data-discount-applied')),
                totalPaid: parseFloat(selectedOption.getAttribute('data-total-paid')),
                requestedDiscount: parseFloat(selectedOption.getAttribute('data-requested-discount')),
                item: selectedOption.getAttribute('data-item')
            };
            
            // Update form fields
            document.getElementById('maxAmount').textContent = currentInvoice.balance.toLocaleString();
            document.getElementById('amount').max = currentInvoice.balance;
            document.getElementById('amount').value = currentInvoice.balance; // Pre-fill with full balance
            
            // Calculate remaining discount that can be applied
            let remainingDiscount = 0;
            if(currentInvoice.requestedDiscount > 0) {
                remainingDiscount = currentInvoice.requestedDiscount - currentInvoice.discountApplied;
                if(remainingDiscount < 0) remainingDiscount = 0;
            }
            document.getElementById('availableDiscount').textContent = remainingDiscount.toLocaleString();
            document.getElementById('approveddiscount').max = Math.min(remainingDiscount, currentInvoice.balance);
            
            // Show invoice summary
            updateInvoiceSummary();
            
            // Show calculation area
            document.getElementById('calculationArea').style.display = 'block';
            
            // Calculate initial values
            calculateNetAmount();
            
        } else {
            document.getElementById('invoiceSummary').style.display = 'none';
            document.getElementById('discountInfo').style.display = 'none';
            document.getElementById('calculationArea').style.display = 'none';
        }
    }
    
    function updateInvoiceSummary() {
        const summaryDiv = document.getElementById('invoiceSummary');
        
        let summaryHtml = `
            <div class="summary-item">
                <span class="summary-label">Invoice:</span>
                <span class="summary-value">INV-${currentInvoice.id.toString().padStart(6, '0')}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Item:</span>
                <span class="summary-value">${currentInvoice.item}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Original Total:</span>
                <span class="summary-value">KES ${currentInvoice.total.toLocaleString()}</span>
            </div>`;
        
        if(currentInvoice.discountApplied > 0) {
            summaryHtml += `
                <div class="summary-item">
                    <span class="summary-label">Discounts Applied:</span>
                    <span class="summary-value discount-applied">-KES ${currentInvoice.discountApplied.toLocaleString()}</span>
                </div>`;
        }
        
        if(currentInvoice.requestedDiscount > currentInvoice.discountApplied) {
            summaryHtml += `
                <div class="summary-item">
                    <span class="summary-label">Discount Available:</span>
                    <span class="summary-value discount-requested">KES ${(currentInvoice.requestedDiscount - currentInvoice.discountApplied).toLocaleString()}</span>
                </div>`;
        }
        
        summaryHtml += `
            <div class="summary-item">
                <span class="summary-label">Total Paid:</span>
                <span class="summary-value">KES ${currentInvoice.totalPaid.toLocaleString()}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Current Balance:</span>
                <span class="summary-value amount-due">KES ${currentInvoice.balance.toLocaleString()}</span>
            </div>`;
        
        summaryDiv.innerHTML = summaryHtml;
        summaryDiv.style.display = 'block';
    }
    
    function calculateNetAmount() {
        const paymentAmount = parseFloat(document.getElementById('amount').value) || 0;
        const discount = parseFloat(document.getElementById('approveddiscount').value) || 0;
        
        // Validate payment amount
        if(paymentAmount > currentInvoice.balance) {
            alert('Payment amount cannot exceed current balance of KES ' + currentInvoice.balance.toLocaleString());
            document.getElementById('amount').value = currentInvoice.balance;
            calculateNetAmount();
            return;
        }
        
        // Validate discount
        const maxDiscount = Math.min(
            currentInvoice.requestedDiscount - currentInvoice.discountApplied,
            currentInvoice.balance,
            paymentAmount // Discount cannot exceed payment amount
        );
        
        if(discount > maxDiscount) {
            alert('Discount cannot exceed ' + maxDiscount.toLocaleString() + ' KES');
            document.getElementById('approveddiscount').value = maxDiscount;
            calculateNetAmount();
            return;
        }
        
        // Calculate net amount and new balance
        const effectivePayment = paymentAmount - discount;
        const newBalance = currentInvoice.balance - paymentAmount - discount;
        
        // Update calculation display
        document.getElementById('calcPaymentAmount').textContent = 'KES ' + paymentAmount.toLocaleString();
        document.getElementById('calcDiscount').textContent = '-KES ' + discount.toLocaleString();
        document.getElementById('calcNetAmount').textContent = 'KES ' + effectivePayment.toLocaleString();
        
        if(newBalance <= 0) {
            document.getElementById('calcNewBalance').textContent = 'KES 0 (Fully Paid)';
            document.getElementById('calcNewBalance').style.color = '#28a745';
        } else {
            document.getElementById('calcNewBalance').textContent = 'KES ' + newBalance.toLocaleString();
            document.getElementById('calcNewBalance').style.color = '#dc3545';
        }
    }
    
    function addPaymentToInvoice(jobId, balance) {
        // Set the job ID in the form
        document.getElementById('jobid').value = jobId;
        
        // Trigger the change event
        const event = new Event('change');
        document.getElementById('jobid').dispatchEvent(event);
        
        // Set the amount to the remaining balance
        document.getElementById('amount').value = balance;
        
        // Calculate net amount
        calculateNetAmount();
        
        // Open the modal
        openFormModal('addPaymentModal');
    }
    
    // Modal functions
    function openFormModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
        }
    }
    
    function closeFormModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
            // Reset form
            document.getElementById('paymentForm').reset();
            document.getElementById('invoiceSummary').style.display = 'none';
            document.getElementById('discountInfo').style.display = 'none';
            document.getElementById('calculationArea').style.display = 'none';
            currentInvoice = {
                id: 0, jobid: 0, total: 0, balance: 0,
                discountApplied: 0, totalPaid: 0, requestedDiscount: 0, item: ''
            };
        }
    }
    
    function openNavModal() {
        document.getElementById('navModal').style.display = 'flex';
    }
    
    function closeNavModal() {
        document.getElementById('navModal').style.display = 'none';
    }
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        if (event.target.classList.contains('nav-modal')) {
            closeNavModal();
        } else if (event.target.classList.contains('modal')) {
            const openModals = document.querySelectorAll('.modal');
            openModals.forEach(modal => {
                if (modal.style.display === 'flex') {
                    modal.style.display = 'none';
                }
            });
        }
    };
    
    // Close modals with escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeNavModal();
            const openModals = document.querySelectorAll('.modal');
            openModals.forEach(modal => {
                modal.style.display = 'none';
            });
        }
    });
    
    // Form validation
    document.getElementById('paymentForm').addEventListener('submit', function(e) {
        const amount = parseFloat(document.getElementById('amount').value) || 0;
        const discount = parseFloat(document.getElementById('approveddiscount').value) || 0;
        
        if(amount <= 0) {
            e.preventDefault();
            alert('Payment amount must be greater than 0');
            return false;
        }
        
        if(amount > currentInvoice.balance) {
            e.preventDefault();
            alert('Payment amount cannot exceed current balance');
            return false;
        }
        
        // Validate discount
        const maxDiscount = Math.min(
            currentInvoice.requestedDiscount - currentInvoice.discountApplied,
            currentInvoice.balance,
            amount
        );
        
        if(discount > maxDiscount) {
            e.preventDefault();
            alert('Discount cannot exceed maximum allowed amount');
            return false;
        }
        
        return true;
    });
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Payment system loaded');
    });
    </script>
</body>
</html>