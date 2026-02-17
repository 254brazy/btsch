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

$getid=$conn->prepare("SELECT id FROM users WHERE company=? AND username=? AND email=?");
$getid->bind_param("sss",$_SESSION['company'],$_SESSION['username'],$_SESSION['email']);
$getid->execute();
$getidresult=$getid->get_result();
$getidrow=$getidresult->fetch_assoc();


$message = '';
if(isset($_GET['action']) && isset($_GET['invoiceid'])) {
    $invoice_id = intval($_GET['invoiceid']);
    $action = $_GET['action'];
    $company = $_SESSION['company'];
    $branch = $_SESSION['branch'];


$payment_query1=$conn->prepare("SELECT id,jobid,invoiceid,item,paid, mode,paymenttype, refrence, company,branch, agent, status,created_at FROM tech_job_customerportal_payments WHERE  company=? AND branch=?");
$payment_query1->bind_param("ss",$_SESSION['company'],$_SESSION['branch']);
$payment_query1->execute();
$payment_query_result1=$payment_query1->get_result();
$payment_query_row1=$payment_query_result1->fetch_assoc();

$paymentid=$payment_query_row1['id'];
    
    switch($action) {
     
        case 'reject':

            $reject=$conn->prepare("UPDATE tech_job_customerportal_payments SET status='disapproved' WHERE id=?");
            $reject->bind_parm("i",$paymentid);
          
            if($reject->execute()){  // FIXED: Removed semicolon after if statement
                $message = "<div class='message success'>Payment disapproved</div>";
            }
            break;
            
        case 'process':
                $payment_query=$conn->prepare("SELECT id,jobid,invoiceid,item,paid, mode,paymenttype, refrence, company,branch, agent, status,created_at FROM tech_job_customerportal_payments WHERE invoiceid=? AND company=? AND branch=?");
                $payment_query->bind_param("iss",$invoice_id,$_SESSION['company'],$_SESSION['branch']);
                $payment_query->execute();
                $payment_query_result=$payment_query->get_result();
                $payment_query_row=$payment_query_result->fetch_assoc();

                $invoice_query=$conn->prepare("SELECT id,jobid,item,parts_subtotal,service_subtotal,total,balance FROM tech_job_invoices WHERE id=? AND company=? AND branch=?");
                $invoice_query->bind_param("iss",$invoice_id,$_SESSION['company'],$_SESSION['branch']);
                $invoice_query->execute();
                $invoice_query_result=$invoice_query->get_result();
                $invoice_query_row=$invoice_query_result->fetch_assoc();

                $paymentinvoice_id=$payment_query_row['invoiceid'];
                $payment_job_id=$payment_query_row['jobid'];
                $paymentitem=$payment_query_row['item'];
                $paymentpaid=$payment_query_row['paid'];
                $paymentmode=$payment_query_row['mode'];
                $payment_type=$payment_query_row['paymenttype'].'Customer Submitted Copy';
                $payment_refrence=$payment_query_row['refrence'];


                $invoicepartscharges=$invoice_query_row['parts_subtotal'];
                $invoiceservicecharges=$invoice_query_row['service_subtotal'];
                $invoicetotalcharge=$invoice_query_row['total'];
                
                $amount=$invoicetotalcharge;
                $balance=$paymentpaid-$invoicetotalcharge;
                $bill=$invoice_query_row['total'];
                $oldbalance=$invoice_query_row['balance'];

                if($paymentpaid=$oldbalance){
                    $paymentstatus='fully paid';
                }elseif($paymentpaid<$oldbalance){
                    $paymentstatus='partially paid';
                }
                
                



                $validpayment=$conn->prepare("INSERT INTO tech_job_payments (invoiceid,jobid,item,parts_subtotal,service_subtotal,total,paid,balance,mode,paymenttype,company,branch,agent,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $validpayment->bind_param("iisiiiiissssss",$paymentinvoice_id,$payment_job_id,$paymentitem,$invoicepartscharges,$invoiceservicecharges,$invoicetotalcharge,$paymentpaid,$balance,$paymentmode,$payment_type,$_SESSION['company'],$_SESSION['branch'],$_SESSION['username'],$paymentstatus);
    
                if($validpayment->execute()){
                        
                    $invupt_stmt = $conn->prepare("UPDATE tech_job_invoices SET status=? WHERE id = ? AND company = ? AND branch=?");
                    $invupt_stmt->bind_param("siss", $paymentstatus, $invoice_id, $_SESSION['company'], $_SESSION['branch']);

                    
            
                    if($invupt_stmt->execute()){  // FIXED: Removed semicolon after if statement

                         $validpayment_stmt = $conn->prepare("UPDATE tech_job_prequotes SET status=? WHERE id = ? AND company = ? AND branch=?");
                         $validpayment_stmt->bind_param("siss", $paymentstatus,$payment_job_id, $_SESSION['company'], $_SESSION['branch']);

                        $message = "<div class='message success'>quotation status processed succesfully.</div>";
                        
                 } else {
                 $message = "<div class='message error'>Error: Failed to process quotation!</div>";
                 }
           
          
            break;
    }
}
}


// Get user data
$user_query = $conn->prepare("SELECT fname, sname, role, branch, company FROM users WHERE username = ?");
$user_query->bind_param("s", $_SESSION['username']);
$user_query->execute();
$user_result = $user_query->get_result();
$user_data = $user_result->fetch_assoc();

$payment_query2=$conn->prepare("SELECT id,jobid,invoiceid,item,paid, mode,paymenttype,refrence,company,branch, agent, status,created_at FROM tech_job_customerportal_payments WHERE company=? AND branch=?");
$payment_query2->bind_param("ss",$_SESSION['company'],$_SESSION['branch']);
$payment_query2->execute();
$payment_query_result2=$payment_query2->get_result();
$payment_query_row2=$payment_query_result2->fetch_assoc();

$payment_query3=$conn->prepare("SELECT id,jobid,invoiceid,item,paid, mode,paymenttype, refrence, company,branch, agent, status,created_at FROM tech_job_customerportal_payments WHERE status='pending approval' AND company=? AND branch=?");
$payment_query3->bind_param("ss",$_SESSION['company'],$_SESSION['branch']);
$payment_query3->execute();
$payment_query_result3=$payment_query3->get_result();
$payment_query_row3=$payment_query_result3->fetch_assoc();
?>

<!-- Your HTML remains mostly the same, but update the form to show available quantities -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($_SESSION['company']); ?> -Customer Submitted Payments Management</title>
    <link rel="stylesheet" href="universalstyle.css">
</head>
<body>
    <!-- Your existing HTML structure -->
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
        
        <!-- Navigation Button - Only shows on mobile -->
        <div class="header-actions">
            <button type="button" class="btn btn-outline" onclick="openNavModal()">
                📱 Navigation
            </button>
        </div>
        
       <?php include 'userheader.html';?>
        </div>
    </header>

    <main>
        <aside>
            <div class="sidebar-header">
                <div class="company-name"><?php echo htmlspecialchars($_SESSION['company']); ?></div>
                <div class="user-role">Customer Submitted Payments Management</div>
            </div>
            <nav>
                <ul>
                   <?php include 'navigation.html';?>
                </ul>
            </nav>
        </aside>

        <article>
            <div class="dashboard-header">
                <h1 class="page-title">Customer Submitted Payments Management</h1>
                <div class="action-buttons">
                    <a href="btsch_customerportal_payments.php"><button class="btn btn-primary">Refresh Payment Requests</button></a>
                </div>
            </div>

            <?php echo $message; ?>

            <!-- Roles List Section -->
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">Customer Submitted Paymenets</h2>
                    <div class="action-buttons">
                        <span class="btn btn-sm" style="background: #f8f9fa;">
                            Total Unapproved: <?php echo $payment_query_result2->num_rows; ?> Payments
                            <br>
                        </span>
                         <span class="btn btn-sm" style="background: #f8f9fa;">
                            Total Submitted: <?php echo $payment_query_result3->num_rows; ?> Payments 
                            <br>
                        </span>
                    </div>
                </div>
                <div class="section-content">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>id</th>
                                    <th>Invoice Id</th>
                                    <th>item</th>
                                    <th>Amount</th>
                                    <th>Mode</th>
                                    <th>Payment Type</th>
                                    <th>Reference</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                    
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($payment_query_result2->num_rows > 0): ?>
                                    <?php while($joby = $payment_query_result2->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($joby['id']) ; ?></td>
                                            <td><?php echo htmlspecialchars($joby['invoiceid'] ); ?></td>
                                            <td><?php echo htmlspecialchars($joby['item']); ?></td>
                                            <td><?php echo htmlspecialchars($joby['paid']); ?></td>
                                            <td><?php echo htmlspecialchars($joby['mode']); ?></td>
                                            <td><?php echo htmlspecialchars($joby['paymenttype']); ?></td>
                                            <td><?php echo htmlspecialchars($joby['refrence']); ?></td>
                                            <td><?php echo htmlspecialchars($joby['agent']); ?></td>
                                            <td><?php echo htmlspecialchars($joby['created_at']); ?></td>
                                            
                                            
                                            <td>
                                                <?php if($joby['status'] == 'pending approval'): ?>
                                                    <span class="status-active">Pending Approval</span>
                                                <?php else: ?>
                                                   <?php if($joby['status'] == 'approved'): ?>
                                                    <span class="status-active">● Approved</span>
                                                       <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="action-buttons">
                                                
                                               
                                                <?php if($joby['status'] == 'pending approval' ): ?>
                                                    <a href="?action=process&invoiceid=<?php echo $joby['invoiceid']; ?>" class="btn btn-sm btn-warning">Approve</a>
                                                    <a href="?action=reject&invoiceid=<?php echo $joby['invoiceid']; ?>" class="btn btn-sm btn-warning">Disapprove</a>
                                               
                                                    <?php else: ?>
                                                        
                                                   <?php if($joby['status'] == 'approved'): ?>
                                                    <span class="status-active">● Approved</span>
                                                    <?php else:?>
                                                        <a href="?action=reject&invoice_id=<?php echo $joby['invoiceid']; ?>" class="btn btn-sm btn-success">Disapprove</a>

                                                       <?php endif; ?>
                                                    
                                                <?php endif; ?>

                                                
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; padding: 20px;">
                                            No Customer-Submitted Payements found. 
                                            <!--<button onclick="openFormModal('addQuoteModal')" class="btn btn-primary btn-sm">Add your first quote</button>-->
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
   
    <script>
    // ============================================
    // NAVIGATION MODAL FUNCTIONS - Completely separate
    // ============================================
    
    /**
     * Open navigation modal (mobile menu)
     */
    function openNavModal() {
        console.log('Opening navigation modal');
        document.getElementById('navModal').style.display = 'flex';
    }
    
    /**
     * Close navigation modal
     */
    function closeNavModal() {
        console.log('Closing navigation modal');
        document.getElementById('navModal').style.display = 'none';
    }
    
    // ============================================
    // EVENT LISTENERS - Handle clicks outside modals
    // ============================================
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        // Close navigation modal when clicking outside
        if (event.target.classList.contains('nav-modal')) {
            closeNavModal();
        }
        // Close form modals when clicking outside
        else if (event.target.classList.contains('modal')) {
            const openModals = document.querySelectorAll('.modal');
            openModals.forEach(modal => {
                if (modal.style.display === 'flex') {
                    modal.style.display = 'none';
                }
            });
        }
    }
    
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
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Modal functions loaded successfully');
    });
    </script>

</body>
</html>