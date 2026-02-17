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
if(isset($_GET['action']) && isset($_GET['invoice_id'])) {
    $invoice_id = intval($_GET['invoice_id']);
    $action = $_GET['action'];
    $company = $_SESSION['company'];
    $branch = $_SESSION['branch'];
    
    switch($action) {
        case 'deactivate':
            // Delete the job service assignment
            $delete_stmt = $conn->prepare("UPDATE tech_job_invoices SET status='unapproved' WHERE id = ? AND company = ? AND branch=?");
            $delete_stmt->bind_param("iss", $invoice_id, $_SESSION['company'], $_SESSION['branch']);
            
            if($delete_stmt->execute()){  // FIXED: Removed semicolon after if statement
                $message = "<div class='message success'>invoice disapproved</div>";
            }
            break;
            
        case 'activate':
            $activate_stmt = $conn->prepare("UPDATE tech_job_invoices SET status='approved' WHERE id = ? AND company = ? AND branch=?");
            $activate_stmt->bind_param("iss", $invoice_id, $_SESSION['company'],$_SESSION['branch']);

            
            if($activate_stmt->execute()) {
                     $message = "<div class='message success'>invoice approved</div>";
            }
            break;
            
        case 'process':
                $invoice_query=$conn->prepare("SELECT id,jobid,item,parts_subtotal,service_subtotal,total FROM tech_job_invoices WHERE id=? AND company=? AND branch=?");
                $invoice_query->bind_param("iss",$invoice_id,$_SESSION['company'],$_SESSION['branch']);
                $invoice_query->execute();
                $invoice_query_result=$invoice_query->get_result();
                $invoice_query_row=$invoice_query_result->fetch_assoc();

                $invoiceid=$invoice_query_row['id'];
                $jobid=$invoice_query_row['jobid'];
                $invoiceitem=$invoice_query_row['item'];
                $invoicepartscharges=$invoice_query_row['parts_subtotal'];
                $invoiceservicecharges=$invoice_query_row['service_subtotal'];
                $invoicetotalcharge=$invoice_query_row['total'];
                
                $amount=$invoicetotalcharge;
                $balance=$amount-$invoicetotalcharge;
                $mode='systemgenerated';
                $paymenttype='full';
                $paymentstatus='fully paid';
                



                $invoice=$conn->prepare("INSERT INTO tech_job_payments (invoiceid,jobid,item,parts_subtotal,service_subtotal,total,paid,balance,mode,paymenttype,company,branch,agent,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $invoice->bind_param("iisiiiiissssss",$invoiceid,$jobid,$invoiceitem,$invoicepartscharges,$invoiceservicecharges,$invoicetotalcharge,$amount,$balance,$mode,$paymenttype,$_SESSION['company'],$_SESSION['branch'],$_SESSION['username'],$paymentstatus);
    
                if($invoice->execute()){
                        
                    $invupt_stmt = $conn->prepare("UPDATE tech_job_invoices SET status='fully paid' WHERE id = ? AND company = ? AND branch=?");
                    $invupt_stmt->bind_param("iss", $invoice_id, $_SESSION['company'], $_SESSION['branch']);
            
                    if($invupt_stmt->execute()){  // FIXED: Removed semicolon after if statement

                         $qtupt_stmt = $conn->prepare("UPDATE tech_job_prequotes SET status='cleared' WHERE id = ? AND company = ? AND branch=?");
                         $qtupt_stmt->bind_param("iss", $jobid, $_SESSION['company'], $_SESSION['branch']);

                        $message = "<div class='message success'>invoice full payment processed succesfully.</div>";
                        
                 } else {
                 $message = "<div class='message error'>Error: Failed to process invoice full payment!</div>";
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

$job_query = $conn->prepare("SELECT id,item,customer_name1,customer_name2,status,serial,branch,issue,created_at,model FROM tech_jobs WHERE company = ? AND branch=?");
$job_query->bind_param("ss", $_SESSION['company'],$_SESSION['branch']);
$job_query->execute();
$job_result = $job_query->get_result();

$invoice = $conn->prepare("SELECT id,jobid,item,status,total,parts_subtotal,service_subtotal FROM tech_job_invoices WHERE company = ? AND branch=? order by created_at desc");  // FIXED: Removed extra comma
$invoice->bind_param("ss", $_SESSION['company'],$_SESSION['branch']);
$invoice->execute();
$invoice_result = $invoice->get_result();

$invoice2 = $conn->prepare("SELECT id,jobid,item,status,total,parts_subtotal,service_subtotal  FROM tech_job_invoices WHERE company = ? AND branch=? AND status !='approved'");  // FIXED: Removed extra comma
$invoice2->bind_param("ss", $_SESSION['company'],$_SESSION['branch']);
$invoice2->execute();
$invoice2_result = $invoice2->get_result();

?>

<!-- Your HTML remains mostly the same, but update the form to show available quantities -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($_SESSION['company']); ?> -invoices Management</title>
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
                <div class="user-role">Invoices Management</div>
            </div>
            <nav>
                <ul>
                   <?php include 'navigation.html';?>
                </ul>
            </nav>
        </aside>

        <article>
            <div class="dashboard-header">
                <h1 class="page-title">invoices Management</h1>
                <div class="action-buttons">
                    <a href="btschtech_prequotes.php"><button class="btn btn-primary">➕ Add Invoice</button></a>
                </div>
            </div>

            <?php echo $message; ?>

            <!-- Roles List Section -->
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">Invoices</h2>
                    <div class="action-buttons">
                        <span class="btn btn-sm" style="background: #f8f9fa;">
                            Total approved: <?php echo $invoice_result->num_rows; ?> Invoices
                            <br>
                        </span>
                         <span class="btn btn-sm" style="background: #f8f9fa;">
                            Total Unapproved: <?php echo $invoice2_result->num_rows; ?> Invoices 
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
                                    <th>Job Number</th>
                                    <th>item</th>
                                    <th>Parts Cost</th>
                                    <th>Service Cost</th>
                                    <th>Bill</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($invoice_result->num_rows > 0): ?>
                                    <?php while($joby = $invoice_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($joby['id']) ; ?></td>
                                            <td><?php echo htmlspecialchars($joby['jobid'] ); ?></td>
                                            <td><?php echo htmlspecialchars($joby['item']); ?></td>
                                            <td><?php echo htmlspecialchars($joby['parts_subtotal']); ?></td>
                                            <td><?php echo htmlspecialchars($joby['service_subtotal']); ?></td>
                                            <td><?php echo htmlspecialchars($joby['total']); ?></td>
                                            
                                            <td>
                                                <?php if($joby['status'] == 'approved'): ?>
                                                    <span class="status-active">● Approved</span>
                                                <?php else: ?>
                                                   <?php if($joby['status'] == 'fully paid'): ?>
                                                    <span class="status-active">● Paid fully</span>
                                                       <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="action-buttons">
                                                
                                               
                                                <?php if($joby['status'] == 'approved' ): ?>
                                                    <a href="?action=process&invoice_id=<?php echo $joby['id']; ?>" class="btn btn-sm btn-warning">Process Full Payment Only</a>
                                                    <a href="?action=deactivate&invoice_id=<?php echo $joby['id']; ?>" class="btn btn-sm btn-warning">unapprove</a>
                                               
                                                    <?php else: ?>
                                                        
                                                   <?php if($joby['status'] == 'fully paid'): ?>
                                                    <span class="status-active">● No actions Here</span>
                                                    <?php else:?>
                                                        <a href="?action=activate&invoice_id=<?php echo $joby['id']; ?>" class="btn btn-sm btn-success">Approve</a>

                                                       <?php endif; ?>
                                                    
                                                <?php endif; ?>

                                                
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; padding: 20px;">
                                            No quotes found. <button onclick="openFormModal('addQuoteModal')" class="btn btn-primary btn-sm">Add your first quote</button>
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