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

if($_SERVER['REQUEST_METHOD']=='POST' && isset($_POST['generatequote'])){
    $jobid=htmlspecialchars(trim($_POST['jobid']));
    $budget=htmlspecialchars(trim($_POST['budget']));
    $status=htmlspecialchars(trim($_POST['status']));
    $taxamount=htmlspecialchars(trim($_POST['tax']));

    if(empty($jobid)|| empty($status)){
         $message = "<div class='message error'>Error: All fields are required!</div>"; 
    }else{

    $partstotal=$conn->prepare("SELECT SUM(total) as total_part_price,item,model,serial,part,quantity FROM tech_job_parts WHERE jobid=? AND company=? AND branch=?");
    $partstotal->bind_param("iss",$jobid,$_SESSION['company'],$_SESSION['branch']);
    $partstotal->execute();
    $partstotal_result=$partstotal->get_result();
    $partstotal_row=$partstotal_result->fetch_assoc();

    $servicestotal=$conn->prepare("SELECT SUM(total) as total_service_price,item,model,serial,quantity FROM tech_job_services WHERE jobid=? AND company=? AND branch=?");
    $servicestotal->bind_param("iss",$jobid,$_SESSION['company'],$_SESSION['branch']);
    $servicestotal->execute();  // FIXED: Changed from $serviecstotal to $servicestotal
    $servicetotal_result=$servicestotal->get_result();  // FIXED: Changed from $servicetotal to $servicestotal
    $servicetotal_row=$servicetotal_result->fetch_assoc();

    $partscharges=$partstotal_row['total_part_price'];
    $servicecharges=$servicetotal_row['total_service_price'];
    $totalchargeraw=$servicecharges + $partscharges;
    

    $company=$_SESSION['company'];
    $branch=$_SESSION['branch'];
    $agent=$_SESSION['username'];
    $item=$partstotal_row['item'] .'-'.$partstotal_row['model'] .'-'. $partstotal_row['serial'];
    
     if(empty($taxamount)){
        $tax=0;
        $totalcharge=$servicecharges + $partscharges;
    }else{
        $tax=($taxamount/100)* $totalchargeraw;
        $totalcharge=$servicecharges + $partscharges+$tax;
    }
    if(empty($budget)){
        $budget=$totalcharge;
    }
    
    $discount=$totalcharge-$budget;
    
    $insertquote=$conn->prepare("INSERT INTO tech_job_prequotes (budget,jobid,item,parts_subtotal,service_subtotal,total,company,branch,agent,tax,discount,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)");
    $insertquote->bind_param("iisiiisssiis",$budget,$jobid,$item,$partscharges,$servicecharges,$totalcharge,$_SESSION['company'],$_SESSION['branch'],$_SESSION['username'],$tax,$discount,$status);
    
    if($insertquote->execute()){
        $message = "<div class='message success'>Job quotation generated successfully!</div>";
    } else {
        $message = "<div class='message error'>Error: Failed to generate quote!</div>";
    }
    
    }
}

// Rest of your existing code for user data, queries, etc.

//Handle staff actions (edit, delete, assign)
if(isset($_GET['action']) && isset($_GET['quote_id'])) {
    $quote_id = intval($_GET['quote_id']);
    $action = $_GET['action'];
    $company = $_SESSION['company'];
    $branch = $_SESSION['branch'];
    
    switch($action) {
        case 'delete':
            // Delete the job service assignment
            $delete_stmt = $conn->prepare("DELETE FROM tech_job_prequotes WHERE id = ? AND company = ? AND branch=?");
            $delete_stmt->bind_param("iss", $quote_id, $_SESSION['company'], $_SESSION['branch']);
            
            if($delete_stmt->execute()){  // FIXED: Removed semicolon after if statement
                $message = "<div class='message success'>quote deleted</div>";
            }
            break;
            
        case 'activate':
            $activate_stmt = $conn->prepare("UPDATE tech_job_prequotes SET status = 'approved' WHERE id = ? AND company = ? AND branch=?");
            $activate_stmt->bind_param("iss", $quote_id, $_SESSION['company'],$_SESSION['branch']);

            
            if($activate_stmt->execute()) {

                $invoice_query=$conn->prepare("SELECT id,jobid,item,parts_subtotal,service_subtotal,total FROM tech_job_prequotes WHERE id=? AND company=? AND branch=?");
                $invoice_query->bind_param("iss",$quote_id,$_SESSION['company'],$_SESSION['branch']);
                $invoice_query->execute();
                $invoice_query_result=$invoice_query->get_result();
                $invoice_query_row=$invoice_query_result->fetch_assoc();

                $jobquoteid=$invoice_query_row['jobid'];
                $quoteitem=$invoice_query_row['item'];
                $quotepartscharges=$invoice_query_row['parts_subtotal'];
                $quoteservicecharges=$invoice_query_row['service_subtotal'];
                $quotetotalcharge=$invoice_query_row['total'];
                $quotestatus='approved';
                $balance=$invoice_query_row['total'];
                



                $invoice=$conn->prepare("INSERT INTO tech_job_invoices (balance, jobid,item,parts_subtotal,service_subtotal,total,company,branch,agent,status) VALUES(?,?,?,?,?,?,?,?,?,?)");
                $invoice->bind_param("iisiiissss",$balance, $jobquoteid,$quoteitem,$quotepartscharges,$quoteservicecharges,$quotetotalcharge,$_SESSION['company'],$_SESSION['branch'],$_SESSION['username'],$quotestatus);
    
                if($invoice->execute()){
                 $message = "<div class='message success'>   quotation approved and invoice processed successfully!</div>";
                 } else {
                 $message = "<div class='message error'>Error: Failed to approve quote!</div>";
                 }
                
            } else {
                $message = "<div class='message error'>Error: Activation failed!</div>"; 
            }
            break;
            
        case 'deactivate':
            $deactivate_stmt = $conn->prepare("UPDATE tech_job_prequotes SET status = 'unapproved' WHERE id = ? AND company = ? AND branch=?");
            $deactivate_stmt->bind_param("iss", $quote_id, $company, $branch);
            
            if($deactivate_stmt->execute()) {
                $message = "<div class='message success'> quote unapproved</div>";   
            } else {
                $message = "<div class='message error'>Error: Deactivation failed!</div>"; 
            }
            break;
    }
}

// Get user data
$user_query = $conn->prepare("SELECT fname, sname, role, branch, company FROM users WHERE username = ?");
$user_query->bind_param("s", $_SESSION['username']);
$user_query->execute();
$user_result = $user_query->get_result();
$user_data = $user_result->fetch_assoc();

$job_query = $conn->prepare("SELECT id,item,customer_name1,customer_name2,status,serial,branch,issue,created_at,model FROM tech_jobs WHERE company = ? AND branch=? order by created_at desc");
$job_query->bind_param("ss", $_SESSION['company'],$_SESSION['branch']);
$job_query->execute();
$job_result = $job_query->get_result();

$job_quote = $conn->prepare("SELECT id,jobid,item,status,total,parts_subtotal,service_subtotal FROM tech_job_prequotes WHERE company = ? AND branch=? order by created_at desc");  // FIXED: Removed extra comma
$job_quote->bind_param("ss", $_SESSION['company'],$_SESSION['branch']);
$job_quote->execute();
$job_quote_result = $job_quote->get_result();

$job_quote2 = $conn->prepare("SELECT id,jobid,item,status,total FROM tech_job_prequotes WHERE company = ? AND branch=? AND status !='approved'");  // FIXED: Removed extra comma
$job_quote2->bind_param("ss", $_SESSION['company'],$_SESSION['branch']);
$job_quote2->execute();
$job_quote2_result = $job_quote2->get_result();

?>

<!-- Your HTML remains mostly the same, but update the form to show available quantities -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($_SESSION['company']); ?> -Quotations Management</title>
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
                <div class="user-role">Quotation Management</div>
            </div>
            <nav>
                <ul>
                   <?php include 'navigation.html';?>
                </ul>
            </nav>
        </aside>

        <article>
            <div class="dashboard-header">
                <h1 class="page-title">Quotations Management</h1>
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="openFormModal('addQuoteModal')">➕ Add Quote</button>
                </div>
            </div>

            <?php echo $message; ?>

            <!-- Roles List Section -->
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">Jobs</h2>
                    <div class="action-buttons">
                        <span class="btn btn-sm" style="background: #f8f9fa;">
                            Total Quoatations: <?php echo $job_quote_result->num_rows; ?> Quotations
                            <br>
                        </span>
                         <span class="btn btn-sm" style="background: #f8f9fa;">
                            Total Unapproved: <?php echo $job_quote2_result->num_rows; ?> Quotations 
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
                                    <th>Parts Price</th>
                                    <th>service price</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($job_quote_result->num_rows > 0): ?>
                                    <?php while($joby = $job_quote_result->fetch_assoc()): ?>
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
                                                    <span class="status-active">● Cleared-No actions</span>
                                                <?php else: ?>
                                                    <span class="status-inactive">● Unapproved</span>
                                                <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="action-buttons">
                                                <a href="btschtechedit_prequotesquotes.php?id=<?php echo $joby['id']; ?>" class="btn btn-sm btn-success">Edit</a>
                                               
                                                <?php if($joby['status'] == 'approved'): ?>
                                                    <a href="?action=deactivate&quote_id=<?php echo $joby['id']; ?>" class="btn btn-sm btn-warning">Unapprove</a>
                                                <?php else: ?>
                                                    <a href="?action=activate&quote_id=<?php echo $joby['id']; ?>" class="btn btn-sm btn-success">Approve</a>
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
   
    <!-- ADD QUOTE MODAL - FORM MODAL -->
    <div id="addQuoteModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Quote</h3>
                <span class="close" onclick="closeFormModal('addQuoteModal')">&times;</span>
            </div>
            <form method="POST" action="btschtech_prequotes.php">
                <div class="section-content">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="jobid">Job *</label>
                            <select name="jobid" id="jobid" required>
                                <option value="">--Select Job--</option>
                                <?php if($job_result->num_rows > 0): ?>
                                    <?php 
                                    // Reset pointer and loop through jobs
                                    $job_result->data_seek(0);
                                    while($jobsy = $job_result->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($jobsy['id']);?>">
                                            <?php echo htmlspecialchars($jobsy['item'] .' ' .$jobsy['model'] .'-'. $jobsy['serial']);?>
                                        </option>    
                                    <?php endwhile; ?>
                                <?php endif;?>
                            </select>
                        </div>
                         <div class="form-group">
                            <label for="status">Status *</label>
                            <select name="status" id="status" required>
                                <option value="">--Select status--</option>
                                <option value="approved">approved</option>  <!-- FIXED: vlaue to value -->
                                <option value="unapproved">Pending</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="status">Customer Budget Price *</label>
                            <input type="number" name="budget" required>
                        </div>
                         <div class="form-group">
                            <label for="status">Tax Percentage *</label>
                            <input type="number" name="tax" required placeholder="0">
                        </div>
                        
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn" onclick="closeFormModal('addQuoteModal')">Cancel</button>  <!-- FIXED: Changed to correct modal ID -->
                    <button type="submit" name="generatequote" class="btn btn-primary">Generate Quote</button>  <!-- FIXED: Changed button name -->
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- NAVIGATION MODAL - SEPARATE FROM FORM MODALS -->
    <!-- ============================================ -->
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

    <!-- ============================================ -->
    <!-- MODAL FUNCTIONS - PROPERLY SEPARATED -->
    <!-- ============================================ -->
    <script>
    // ============================================
    // FORM MODAL FUNCTIONS - For add role, add staff, etc.
    // ============================================
    
    /**
     * Open form modal (for adding roles, staff, etc.)
     * @param {string} modalId - The ID of the modal to open
     */
    function openFormModal(modalId) {
        console.log('Opening form modal:', modalId);
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
        } else {
            console.error('Modal not found:', modalId);
        }
    }
    
    /**
     * Close form modal
     * @param {string} modalId - The ID of the modal to close
     */
    function closeFormModal(modalId) {
        console.log('Closing form modal:', modalId);
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
        }
    }
    
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