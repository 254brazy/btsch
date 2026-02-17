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

// Handle form submission for adding staff
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addPart'])){
    // Set form variables
    $jobid=htmlspecialchars(trim($_POST['jobid']));
    $part_id=htmlspecialchars(trim($_POST['part']));
    $quantity=htmlspecialchars($_POST['quantity']);
    $status=htmlspecialchars(trim($_POST['status']));
    
    $company=htmlspecialchars($_SESSION['company']);
    $agent=htmlspecialchars($_SESSION['username']);
    $branch=htmlspecialchars($_SESSION['branch']);

    // Validation
    if(empty($jobid) || empty($part_id) || empty($quantity) || empty($status)){
        $message = "<div class='message error'>Please fill all required fields</div>";
    } else {
        // Get job details
        $job_query = $conn->prepare("SELECT id,item,customer_name1,customer_name2,status,serial,branch,issue,created_at,model,customer_id FROM tech_jobs WHERE company = ? AND branch=? AND id=?");
        $job_query->bind_param("sii", $_SESSION['company'],$_SESSION['branch'],$jobid);
        $job_query->execute();
        $job_result = $job_query->get_result();
        
        if($job_result->num_rows == 0) {
            $message = "<div class='message error'>Job not found</div>";
        } else {
            $job_row=$job_result->fetch_assoc();
            $customerid = htmlspecialchars($job_row['customer_id']);
            $item = htmlspecialchars($job_row['item']);
            $model = htmlspecialchars($job_row['model']);
            $serial = htmlspecialchars($job_row['serial']);

            // Get part details from inventory
            $part_query = $conn->prepare("SELECT id, part, description, quantity,price FROM techineventory WHERE company = ? AND branch=? AND id=? AND status='instock'");
            $part_query->bind_param("ssi", $company, $branch, $part_id);
            $part_query->execute();
            $part_result = $part_query->get_result();
            
            if($part_result->num_rows == 0) {
                $message = "<div class='message error'>Part not found or not in stock</div>";
            } else {
                $part_row = $part_result->fetch_assoc();
                $part_name = htmlspecialchars($part_row['part']);
                $available_quantity = $part_row['quantity'];
                $price=$part_row['price'];
                $totalcost=$quantity*$price;
                

                // Check if part is already assigned to this job with same status
                $check_assignedpart = $conn->prepare("SELECT id FROM tech_job_parts WHERE company = ? AND branch=? AND jobid=? AND part=? AND status=?");
                $check_assignedpart->bind_param("ssiss", $company, $branch, $jobid, $part_name, $status);
                $check_assignedpart->execute();
                $assignedpart_result = $check_assignedpart->get_result();

                // Check stock availability for allocation
                if(($status == 'allocated' || $status == 'assigned') && $available_quantity < $quantity) {
                    $message = "<div class='message error'>Insufficient stock available. Only $available_quantity units in stock.</div>";
                } elseif($assignedpart_result->num_rows > 0) {
                    $message = "<div class='message error'>This part is already assigned to this job with the same status</div>";
                } else {
                    // Start transaction for data consistency
                    $conn->begin_transaction();
                    
                    try {
                        // Insert job part assignment
                        $insert_jobpart = $conn->prepare("INSERT INTO tech_job_parts (total,price,company,branch,agent,customer_id,jobid,item,model,serial,part,quantity,status) VALUES (?,?,?,?, ?, ?, ?,?,?,?,?,?,?)");
                        $insert_jobpart->bind_param("iisssiissssis",$totalcost,$price, $company, $branch, $agent, $customerid, $jobid, $item, $model, $serial, $part_name, $quantity, $status);
                        
                        if($insert_jobpart->execute()) {
                            // Update inventory only if status is allocated/assigned
                            if($status == 'allocated' || $status == 'assigned') {
                                $new_quantity = $available_quantity - $quantity;
                                $updatestock = $conn->prepare("UPDATE techineventory SET quantity=? WHERE company=? AND branch=? AND id=?");
                                $updatestock->bind_param("issi", $new_quantity, $company, $branch, $part_id);
                                
                                if(!$updatestock->execute()) {
                                    throw new Exception("Failed to update inventory");
                                }
                            }
                            
                            $conn->commit();
                            $message = "<div class='message success'>Job part assigned successfully!" . (($status == 'allocated' || $status == 'assigned') ? " Stock updated." : "") . "</div>";
                        } else {
                            throw new Exception("Failed to assign job part");
                        }
                    } catch (Exception $e) {
                        $conn->rollback();
                        $message = "<div class='message error'>Error: " . $e->getMessage() . "</div>";
                    }
                }
            }
        }
    }
}

// Handle staff actions (edit, delete, assign)
if(isset($_GET['action']) && isset($_GET['job_id'])) {
    $job_id = intval($_GET['job_id']);
    $action = $_GET['action'];
    $company = $_SESSION['company'];
    $branch = $_SESSION['branch'];
    
    switch($action) {
        case 'delete':
            // Get part details before deletion
            $check_assqt = $conn->prepare("SELECT quantity, part FROM tech_job_parts WHERE company = ? AND branch=? AND id=?");
            $check_assqt->bind_param("ssi", $company, $branch, $job_id);
            $check_assqt->execute();
            $assqt_result = $check_assqt->get_result();
            
            if($assqt_result->num_rows > 0) {
                $assstock = $assqt_result->fetch_assoc();
                $oldqt = $assstock['quantity'];
                $part_name = $assstock['part'];
                $status = $assstock['status'];
                
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Delete the job part assignment
                    $delete_stmt = $conn->prepare("DELETE FROM tech_job_parts WHERE id = ? AND company = ? AND branch=?");
                    $delete_stmt->bind_param("iss", $job_id, $company, $branch);
                    
                    if($delete_stmt->execute()) {
                        // Restore stock only if part was allocated/assigned
                        if($status == 'allocated' || $status == 'assigned') {
                            // Get current stock
                            $check_remqt = $conn->prepare("SELECT quantity FROM techineventory WHERE company = ? AND branch=? AND part=?");
                            $check_remqt->bind_param("sss", $company, $branch, $part_name);
                            $check_remqt->execute();
                            $remqt_result = $check_remqt->get_result();
                            
                            if($remqt_result->num_rows > 0) {
                                $remstock = $remqt_result->fetch_assoc();
                                $remqt = $remstock['quantity'];
                                $newrem = $oldqt + $remqt;
                                
                                $updatestock = $conn->prepare("UPDATE techineventory SET quantity=? WHERE company=? AND branch=? AND part=?");
                                $updatestock->bind_param("isss", $newrem, $company, $branch, $part_name);
                                
                                if(!$updatestock->execute()) {
                                    throw new Exception("Failed to restore stock");
                                }
                            }
                        }
                        
                        $conn->commit();
                        $message = "<div class='message success'>Part assignment deleted" . (($status == 'allocated' || $status == 'assigned') ? " and stock restored" : "") . " successfully!</div>";
                    } else {
                        throw new Exception("Failed to delete assignment");
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    $message = "<div class='message error'>Error: " . $e->getMessage() . "</div>";
                }
            }
            break;
            
        case 'deactivate':
        case 'unapprove':
            // Get current assignment details
            $check_assqt = $conn->prepare("SELECT quantity, part, status FROM tech_job_parts WHERE company = ? AND branch=? AND id=?");
            $check_assqt->bind_param("ssi", $company, $branch, $job_id);
            $check_assqt->execute();
            $assqt_result = $check_assqt->get_result();
            
            if($assqt_result->num_rows > 0) {
                $assstock = $assqt_result->fetch_assoc();
                $oldqt = $assstock['quantity'];
                $part_name = $assstock['part'];
                $current_status = $assstock['status'];
                
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Update status to unapproved
                    $deactivate_stmt = $conn->prepare("UPDATE tech_job_parts SET status = 'unapproved' WHERE id = ? AND company = ? AND branch=?");
                    $deactivate_stmt->bind_param("iss", $job_id, $company, $branch);
                    
                    if($deactivate_stmt->execute()) {
                        // Restore stock if previously allocated/assigned
                        if($current_status == 'allocated' || $current_status == 'assigned') {
                            // Get current stock
                            $check_remqt = $conn->prepare("SELECT quantity FROM techineventory WHERE company = ? AND branch=? AND part=?");
                            $check_remqt->bind_param("sss", $company, $branch, $part_name);
                            $check_remqt->execute();
                            $remqt_result = $check_remqt->get_result();
                            
                            if($remqt_result->num_rows > 0) {
                                $remstock = $remqt_result->fetch_assoc();
                                $remqt = $remstock['quantity'];
                                $newrem = $oldqt + $remqt;
                                
                                $updatestock = $conn->prepare("UPDATE techineventory SET quantity=? WHERE company=? AND branch=? AND part=?");
                                $updatestock->bind_param("isss", $newrem, $company, $branch, $part_name);
                                
                                if(!$updatestock->execute()) {
                                    throw new Exception("Failed to restore stock");
                                }
                            }
                        }
                        
                        $conn->commit();
                        $message = "<div class='message success'>Part assignment unapproved" . (($current_status == 'allocated' || $current_status == 'assigned') ? " and stock restored" : "") . " successfully!</div>";
                    } else {
                        throw new Exception("Failed to update status");
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    $message = "<div class='message error'>Error: " . $e->getMessage() . "</div>";
                }
            }
            break;
            
        case 'reject':
            // Similar to deactivate but with different status
            $check_assqt = $conn->prepare("SELECT quantity, part, status FROM tech_job_parts WHERE company = ? AND branch=? AND id=?");
            $check_assqt->bind_param("ssi", $company, $branch, $job_id);
            $check_assqt->execute();
            $assqt_result = $check_assqt->get_result();
            
            if($assqt_result->num_rows > 0) {
                $assstock = $assqt_result->fetch_assoc();
                $oldqt = $assstock['quantity'];
                $part_name = $assstock['part'];
                $current_status = $assstock['status'];
                
                $conn->begin_transaction();
                
                try {
                    $reject_stmt = $conn->prepare("UPDATE tech_job_parts SET status = 'rejected' WHERE id = ? AND company = ? AND branch=?");
                    $reject_stmt->bind_param("iss", $job_id, $company, $branch);
                    
                    if($reject_stmt->execute()) {
                        // Restore stock if previously allocated/assigned
                        if($current_status == 'allocated' || $current_status == 'assigned') {
                            $check_remqt = $conn->prepare("SELECT quantity FROM techineventory WHERE company = ? AND branch=? AND part=?");
                            $check_remqt->bind_param("sss", $company, $branch, $part_name);
                            $check_remqt->execute();
                            $remqt_result = $check_remqt->get_result();
                            
                            if($remqt_result->num_rows > 0) {
                                $remstock = $remqt_result->fetch_assoc();
                                $remqt = $remstock['quantity'];
                                $newrem = $oldqt + $remqt;
                                
                                $updatestock = $conn->prepare("UPDATE techineventory SET quantity=? WHERE company=? AND branch=? AND part=?");
                                $updatestock->bind_param("isss", $newrem, $company, $branch, $part_name);
                                
                                if(!$updatestock->execute()) {
                                    throw new Exception("Failed to restore stock");
                                }
                            }
                        }
                        
                        $conn->commit();
                        $message = "<div class='message success'>Part allocation rejected" . (($current_status == 'allocated' || $current_status == 'assigned') ? " and stock restored" : "") . " successfully!</div>";
                    } else {
                        throw new Exception("Failed to reject allocation");
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    $message = "<div class='message error'>Error: " . $e->getMessage() . "</div>";
                }
            }
            break;
            
        case 'approved':
        case 'allocate':
            // Check stock availability before approving/allocating
            $check_assqt = $conn->prepare("SELECT quantity, part FROM tech_job_parts WHERE company = ? AND branch=? AND id=?");
            $check_assqt->bind_param("ssi", $company, $branch, $job_id);
            $check_assqt->execute();
            $assqt_result = $check_assqt->get_result();
            
            if($assqt_result->num_rows > 0) {
                $assstock = $assqt_result->fetch_assoc();
                $required_qt = $assstock['quantity'];
                $part_name = $assstock['part'];
                
                // Check current stock
                $check_stock = $conn->prepare("SELECT quantity, FROM techineventory WHERE company = ? AND branch=? AND part=?");
                $check_stock->bind_param("sss", $company, $branch, $part_name);
                $check_stock->execute();
                $stock_result = $check_stock->get_result();
                
                if($stock_result->num_rows > 0) {
                    $stock_row = $stock_result->fetch_assoc();
                    $current_stock = $stock_row['quantity'];
                    
                    if($current_stock < $required_qt) {
                        $message = "<div class='message error'>Cannot approve allocation. Insufficient stock. Available: $current_stock, Required: $required_qt</div>";
                    } else {
                        $conn->begin_transaction();
                        
                        try {
                            // Update status to allocated
                            $approve_stmt = $conn->prepare("UPDATE tech_job_parts SET status = 'allocated' WHERE id = ? AND company = ? AND branch=?");
                            $approve_stmt->bind_param("iss", $job_id, $company, $branch);
                            
                            if($approve_stmt->execute()) {
                                // Deduct from inventory
                                $new_stock = $current_stock - $required_qt;
                                $updatestock = $conn->prepare("UPDATE techineventory SET quantity=? WHERE company=? AND branch=? AND part=?");
                                $updatestock->bind_param("isss", $new_stock, $company, $branch, $part_name);
                                
                                if(!$updatestock->execute()) {
                                    throw new Exception("Failed to update inventory");
                                }
                                
                                $conn->commit();
                                $message = "<div class='message success'>Part allocated and approved successfully! Stock updated.</div>";
                            } else {
                                throw new Exception("Failed to update status");
                            }
                        } catch (Exception $e) {
                            $conn->rollback();
                            $message = "<div class='message error'>Error: " . $e->getMessage() . "</div>";
                        }
                    }
                }
            }
            break;
            
       case 'activate':
    // Get current assignment details
    $check_assqt = $conn->prepare("SELECT quantity, part, status FROM tech_job_parts WHERE company = ? AND branch=? AND id=?");
    $check_assqt->bind_param("ssi", $company, $branch, $job_id);
    $check_assqt->execute();
    $assqt_result = $check_assqt->get_result();
    
    if($assqt_result->num_rows > 0) {
        $assstock = $assqt_result->fetch_assoc();
        $required_qt = $assstock['quantity'];
        $part_name = $assstock['part'];
        $current_status = $assstock['status'];
        
        // Only proceed if not already allocated
        if($current_status != 'allocated') {
            // Check current stock availability
            $check_stock = $conn->prepare("SELECT quantity FROM techineventory WHERE company = ? AND branch=? AND part=?");
            $check_stock->bind_param("sss", $company, $branch, $part_name);
            $check_stock->execute();
            $stock_result = $check_stock->get_result();
            
            if($stock_result->num_rows > 0) {
                $stock_row = $stock_result->fetch_assoc();
                $current_stock = $stock_row['quantity'];
                
                if($current_stock < $required_qt) {
                    $message = "<div class='message error'>Cannot activate allocation. Insufficient stock. Available: $current_stock, Required: $required_qt</div>";
                } else {
                    // Start transaction
                    $conn->begin_transaction();
                    
                    try {
                        // Update status to allocated
                        $activate_stmt = $conn->prepare("UPDATE tech_job_parts SET status = 'allocated' WHERE id = ? AND company = ? AND branch=?");
                        $activate_stmt->bind_param("iss", $job_id, $company, $branch);
                        
                        if($activate_stmt->execute()) {
                            // Deduct from inventory
                            $new_stock = $current_stock - $required_qt;
                            $updatestock = $conn->prepare("UPDATE techineventory SET quantity=? WHERE company=? AND branch=? AND part=?");
                            $updatestock->bind_param("isss", $new_stock, $company, $branch, $part_name);
                            
                            if(!$updatestock->execute()) {
                                throw new Exception("Failed to update inventory");
                            }
                            
                            $conn->commit();
                            $message = "<div class='message success'>Part allocation activated successfully! Stock updated.</div>";
                        } else {
                            throw new Exception("Failed to activate allocation");
                        }
                    } catch (Exception $e) {
                        $conn->rollback();
                        $message = "<div class='message error'>Error: " . $e->getMessage() . "</div>";
                    }
                }
            } else {
                $message = "<div class='message error'>Part not found in inventory</div>";
            }
        } else {
            $message = "<div class='message warning'>Part is already allocated</div>";
        }
    } else {
        $message = "<div class='message error'>Part assignment not found</div>";
    }
    break;
    }
}

// Rest of your existing code for user data, queries, etc.
// ... [keep the rest of your existing code for user queries, etc.]

// Get user data
$user_query = $conn->prepare("SELECT fname, sname, role, branch, company FROM users WHERE username = ?");
$user_query->bind_param("s", $_SESSION['username']);
$user_query->execute();
$user_result = $user_query->get_result();
$user_data = $user_result->fetch_assoc();

// Get branches for the company
$customer_query = $conn->prepare("SELECT id, fname, sname FROM customers WHERE company = ? AND branch=? AND status='active' ");
$customer_query->bind_param("ss", $_SESSION['company'],$_SESSION['branch']);
$customer_query->execute();
$customer_result = $customer_query->get_result();

$job_query = $conn->prepare("SELECT id,item,customer_name1,customer_name2,status,serial,branch,issue,created_at,model FROM tech_jobs WHERE company = ? AND branch=? ORDER BY created_at DESC");
$job_query->bind_param("ss", $_SESSION['company'],$_SESSION['branch']);
$job_query->execute();
$job_result = $job_query->get_result();

$spare_query = $conn->prepare("SELECT id,part,description,quantity,price FROM techineventory WHERE company = ? AND branch=? AND status='instock'");
$spare_query->bind_param("ss", $_SESSION['company'],$_SESSION['branch']);
$spare_query->execute();
$spare_result = $spare_query->get_result();

$jobparts_query = $conn->prepare("SELECT id,item,model,serial,part,quantity,customer_id, created_at,status FROM tech_job_parts WHERE company = ? AND branch=? ");
$jobparts_query->bind_param("ss", $_SESSION['company'],$_SESSION['branch']);
$jobparts_query->execute();
$jobparts_result = $jobparts_query->get_result();

$jobparts2_query = $conn->prepare("SELECT id,item,model,serial,part,quantity,customer_id,created_at,status FROM tech_job_parts WHERE company = ? AND branch=? AND status !='allocated'");
$jobparts2_query->bind_param("ss", $_SESSION['company'],$_SESSION['branch']);
$jobparts2_query->execute();
$jobparts2_result = $jobparts2_query->get_result();
?>

<!-- Your HTML remains mostly the same, but update the form to show available quantities -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($_SESSION['company']); ?> -Assigned parts Management</title>
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
                <div class="user-role">Job Parts Management</div>
            </div>
            <nav>
                <ul>
                   <?php include 'navigation.html';?>
                </ul>
            </nav>
        </aside>

        <article>
            <div class="dashboard-header">
                <h1 class="page-title">Job Parts Management</h1>
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="openFormModal('addPartsModal')">➕ Assign Parts</button>
                   
                </div>
            </div>

            <?php echo $message; ?>

            <!-- Roles List Section -->
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">Jobs</h2>
                    <div class="action-buttons">
                        <span class="btn btn-sm" style="background: #f8f9fa;">
                            Total Active allocations: <?php echo $jobparts_result->num_rows; ?> Jobs 
                            <br>
                        </span>
                         <span class="btn btn-sm" style="background: #f8f9fa;">
                            Total Inactive Allocation: <?php echo $jobparts2_result->num_rows; ?> Jobs 
                            <br>
                        </span>
                       
                    </div>
                </div>
                <div class="section-content">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Owner_id</th>
                                    <th>Item</th>
                                    <th>Model</th>
                                    <th>Serial</th>
                                    <th>Part(s)</th>
                                    <th>Qty</th>
                                    <th>Assigned On</th>
                                
                                    <th>Status</th>
                                     <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($jobparts_result->num_rows > 0): ?>
                                    <?php while($joby = $jobparts_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($joby['customer_id']) ; ?></td>
                                            <td><?php echo htmlspecialchars($joby['item'] ); ?></td>
                                            <td><?php echo htmlspecialchars($joby['model']); ?></td>
                                            <td><?php echo htmlspecialchars($joby['serial']); ?></td>
                                            <td><?php echo htmlspecialchars($joby['part']); ?></td>
                                            <td><?php echo htmlspecialchars($joby['quantity']); ?></td>
                                            <td><?php echo htmlspecialchars($joby['created_at']); ?></td>
                                          <!--   <td><?php echo htmlspecialchars($joby['status']); ?></td> -->
                                           
                                            <td>
                                                <?php if($joby['status'] == 'allocated'): ?>
                                                    <span class="status-active">● Allocated</span>
                                                <?php else: ?>
                                                    <span class="status-inactive">● Unapproved</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="action-buttons">
                                                <a href="btschtechedit_jobparts.php?id=<?php echo $joby['id']; ?>" class="btn btn-sm btn-success">Edit</a>
                                               
                                                <?php if($joby['status'] == 'allocated'): ?>
                                                    <a href="?action=deactivate&job_id=<?php echo $joby['id']; ?>" class="btn btn-sm btn-warning">Unapprove</a>
                                                <?php else: ?>
                                                    <a href="?action=activate&job_id=<?php echo $joby['id']; ?>" class="btn btn-sm btn-success">Allocate</a>
                                                  
                                                <?php endif; ?>
                                              <!--  <a href="?action=delete&job_id=<?php echo $joby['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this job?')">Delete</a>
                                                --></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 20px;">
                                            No roles found. <button onclick="openFormModal('addPartsModal')" class="btn btn-primary btn-sm">Add your first Job</button>
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
   <!-- Your existing HTML head and body structure remains the same until the modal section -->

    <!-- ============================================ -->
    <!-- ADD PART MODAL - FORM MODAL -->
    <!-- ============================================ -->
    <div id="addPartsModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Assign New Part</h3>
                <span class="close" onclick="closeFormModal('addPartsModal')">&times;</span>
            </div>
            <form method="POST" action="btsch_tech_job_parts.php">
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
                            <label for="part">Part *</label>
                            <select name="part" id="part" required onchange="updateQuantityInfo()">
                                <option value="">--Select Part--</option>
                                <?php if($spare_result->num_rows > 0): ?>
                                    <?php 
                                    // Reset pointer and loop through spare parts
                                    $spare_result->data_seek(0);
                                    while($sparepart = $spare_result->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($sparepart['id']);?>" 
                                                data-quantity="<?php echo htmlspecialchars($sparepart['quantity']);?>">
                                            <?php echo htmlspecialchars($sparepart['part'] . ' - ' . $sparepart['description'] . ' (Available: ' . $sparepart['quantity'] . ')');?>
                                        </option>    
                                    <?php endwhile; ?>
                                <?php endif;?>
                            </select>
                            <small id="quantityInfo" style="color: #666; font-size: 0.8rem; margin-top: 5px; display: block;">
                                Select a part to see available quantity
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="quantity">Quantity *</label>
                            <input type="number" id="quantity" name="quantity" min="1" required onchange="validateQuantity()">
                            <small id="quantityError" style="color: #e74c3c; font-size: 0.8rem; margin-top: 5px; display: none;">
                                Quantity exceeds available stock
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status *</label>
                            <select name="status" id="status" required onchange="checkStockRequirement()">
                                <option value="">--select--</option>
                                <option value="allocated">allocated</option>
                                <option value="pending">pending allocation</option>
                                <option value="unapproved">Unapproved</option>
                            </select>
                            <small id="statusInfo" style="color: #666; font-size: 0.8rem; margin-top: 5px; display: block;">
                                "allocated" will deduct from inventory immediately
                            </small>
                        </div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn" onclick="closeFormModal('addPartsModal')">Cancel</button>
                    <button type="submit" name="addPart" class="btn btn-primary" id="submitBtn">Assign Part</button>
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
            // Reset form when opening
            if (modalId === 'addPartsModal') {
                resetPartForm();
            }
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
    // PART FORM SPECIFIC FUNCTIONS
    // ============================================
    
    function updateQuantityInfo() {
        const partSelect = document.getElementById('part');
        const quantityInfo = document.getElementById('quantityInfo');
        const selectedOption = partSelect.options[partSelect.selectedIndex];
        
        if (selectedOption.value) {
            const availableQty = selectedOption.getAttribute('data-quantity');
            quantityInfo.textContent = `Available quantity: ${availableQty}`;
            quantityInfo.style.color = '#27ae60';
        } else {
            quantityInfo.textContent = 'Select a part to see available quantity';
            quantityInfo.style.color = '#666';
        }
        validateQuantity();
    }

    function validateQuantity() {
        const partSelect = document.getElementById('part');
        const quantityInput = document.getElementById('quantity');
        const quantityError = document.getElementById('quantityError');
        const submitBtn = document.getElementById('submitBtn');
        const statusSelect = document.getElementById('status');
        
        const selectedOption = partSelect.options[partSelect.selectedIndex];
        const availableQty = selectedOption.value ? parseInt(selectedOption.getAttribute('data-quantity')) : 0;
        const requestedQty = parseInt(quantityInput.value) || 0;
        const status = statusSelect.value;
        
        // Only validate stock for allocated status
        if ((status === 'allocated' || status === 'assigned') && requestedQty > availableQty) {
            quantityError.style.display = 'block';
            quantityError.textContent = `Insufficient stock! Available: ${availableQty}, Requested: ${requestedQty}`;
            submitBtn.disabled = true;
        } else {
            quantityError.style.display = 'none';
            submitBtn.disabled = false;
        }
    }

    function checkStockRequirement() {
        validateQuantity(); // Re-validate when status changes
        
        const statusInfo = document.getElementById('statusInfo');
        const status = document.getElementById('status').value;
        
        if (status === 'allocated' || status === 'assigned') {
            statusInfo.textContent = 'This status will deduct from inventory immediately';
            statusInfo.style.color = '#e74c3c';
        } else {
            statusInfo.textContent = 'This status will not affect inventory until approved';
            statusInfo.style.color = '#666';
        }
    }

    function resetPartForm() {
        // Reset form fields
        document.getElementById('jobid').value = '';
        document.getElementById('part').value = '';
        document.getElementById('quantity').value = '';
        document.getElementById('status').value = '';
        
        // Reset info messages
        document.getElementById('quantityInfo').textContent = 'Select a part to see available quantity';
        document.getElementById('quantityInfo').style.color = '#666';
        document.getElementById('quantityError').style.display = 'none';
        document.getElementById('statusInfo').textContent = '"allocated" will deduct from inventory immediately';
        document.getElementById('statusInfo').style.color = '#666';
        document.getElementById('submitBtn').disabled = false;
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
        // Initialize form validation if modal elements exist
        if (document.getElementById('part')) {
            updateQuantityInfo();
            checkStockRequirement();
        }
    });

    // Simple test function to check if modal is working
    function testModal() {
        console.log('Test function called');
        openFormModal('addPartsModal');
    }
    </script>

</body>
</html>