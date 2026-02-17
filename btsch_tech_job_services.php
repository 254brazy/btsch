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
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addservice'])){
    // Set form variables
    $jobid=htmlspecialchars(trim($_POST['jobid']));
    $service_id=htmlspecialchars(trim($_POST['service']));
    $quantity=htmlspecialchars($_POST['quantity']);
    $status=htmlspecialchars(trim($_POST['status']));
    
    $company=htmlspecialchars($_SESSION['company']);
    $agent=htmlspecialchars($_SESSION['username']);
    $branch=htmlspecialchars($_SESSION['branch']);

    // Validation
    if(empty($jobid) || empty($service_id) || empty($quantity) || empty($status)){
        $message = "<div class='message error'>Please fill all required fields</div>";
    } else {
        // Get job details
        $job_query = $conn->prepare("SELECT id,item,customer_name1,customer_name2,status,serial,branch,issue,created_at,model,customer_id FROM tech_jobs WHERE company = ? AND branch=? AND id=? ORDER BY created_at DESC");
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

            // Get service details from inventory
            $service_query = $conn->prepare("SELECT id, service_name, description,service_price FROM techservices WHERE company = ? AND branch=? AND id=? AND service_status='active'ORDER BY created_at DESC");
            $service_query->bind_param("ssi", $company, $branch, $service_id);
            $service_query->execute();
            $service_result = $service_query->get_result();
            
            if($service_result->num_rows == 0) {
                $message = "<div class='message error'>service not found</div>";
            } else {
                $service_row = $service_result->fetch_assoc();
                $service_name = htmlspecialchars($service_row['service_name']);
                
                $price=$service_row['service_price'];
                $totalprice=$price*$quantity;

                // Check if service is already assigned to this job with same status
                $check_assignedservice = $conn->prepare("SELECT id FROM tech_job_services WHERE company = ? AND branch=? AND jobid=? AND service=? AND status=?");
                $check_assignedservice->bind_param("ssiss", $company, $branch, $jobid, $service_name, $status);
                $check_assignedservice->execute();
                $assignedservice_result = $check_assignedservice->get_result();

                // Check stock availability for allocation
                
                if($assignedservice_result->num_rows > 0) {
                    $message = "<div class='message error'>This service is already assigned to this job with the same status</div>";
                } else {
                    // Start transaction for data consistency
                    $conn->begin_transaction();
                    
                    try {
                        // Insert job service assignment
                        $insert_jobservice = $conn->prepare("INSERT INTO tech_job_services (total,price,company,branch,agent,customer_id,jobid,item,model,serial,service,quantity,status) VALUES (?,?,?,?, ?, ?, ?,?,?,?,?,?,?)");
                        $insert_jobservice->bind_param("iisssiissssis",$totalprice,$price, $company, $branch, $agent, $customerid, $jobid, $item, $model, $serial, $service_name, $quantity, $status);
                        
                        if($insert_jobservice->execute()) {
                             $conn->commit();
                            $message = "<div class='message success'>Job service assigned successfully!</div>";
                           
                            }
                            
                           else {
                            throw new Exception("Failed to assign job service");
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
           
                
                    // Delete the job service assignment
                    $delete_stmt = $conn->prepare("DELETE FROM tech_job_services WHERE id = ? AND company = ? AND branch=?");
                    $delete_stmt->bind_param("iss", $job_id, $company, $branch);
                    
                    $delete_stmt->execute();
                    if($delete_stmt->execute());{
                         $message = "<div class='message success'>service assignment deleted</div>";
                    }
            break;
            
        case 'activate':
              $activate_stmt = $conn->prepare("UPDATE tech_job_services SET status = 'allocated' WHERE id = ? AND company = ? AND branch=?");
                    $activate_stmt->bind_param("iss", $job_id, $company, $branch);
                    
                    if($activate_stmt->execute()) {
                      $message = "<div class='message success'>service assignment approved</div>";   
                    }
                    else {
                       $message = "<div class='message error'>Error: " . $e->getMessage() . "</div>"; 
                    }
              
            break;
            
        case 'deactivate':
            // Get current assignment details
          
                    $deactivate_stmt = $conn->prepare("UPDATE tech_job_services SET status = 'unapproved' WHERE id = ? AND company = ? AND branch=?");
                    $deactivate_stmt->bind_param("iss", $job_id, $company, $branch);
                    
                    if($deactivate_stmt->execute()) {
                      $message = "<div class='message success'>service assignment unapproved</div>";   
                    }
                    else {
                       $message = "<div class='message error'>Error: " . $e->getMessage() . "</div>"; 
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

$spare_query = $conn->prepare("SELECT id,service_name,description,service_price FROM techservices WHERE company = ? AND branch=? AND service_status='active'ORDER BY created_at DESC");
$spare_query->bind_param("ss", $_SESSION['company'],$_SESSION['branch']);
$spare_query->execute();
$spare_result = $spare_query->get_result();

$jobservices_query = $conn->prepare("SELECT id,item,model,serial,service,quantity,customer_id, created_at,status FROM tech_job_services WHERE company = ? AND branch=? ORDER BY created_at DESC");
$jobservices_query->bind_param("ss", $_SESSION['company'],$_SESSION['branch']);
$jobservices_query->execute();
$jobservices_result = $jobservices_query->get_result();

$jobservices2_query = $conn->prepare("SELECT id,item,model,serial,service,quantity,customer_id,created_at,status FROM tech_job_services WHERE company = ? AND branch=? AND status !='allocated' ORDER BY created_at DESC");
$jobservices2_query->bind_param("ss", $_SESSION['company'],$_SESSION['branch']);
$jobservices2_query->execute();
$jobservices2_result = $jobservices2_query->get_result();
?>

<!-- Your HTML remains mostly the same, but update the form to show available quantities -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($_SESSION['company']); ?> -Assigned services Management</title>
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
                <div class="user-role">Job services Management</div>
            </div>
            <nav>
                <ul>
                   <?php include 'navigation.html';?>
                </ul>
            </nav>
        </aside>

        <article>
            <div class="dashboard-header">
                <h1 class="page-title">Job services Management</h1>
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="openFormModal('addservicesModal')">➕ Assign services</button>
                   
                </div>
            </div>

            <?php echo $message; ?>

            <!-- Roles List Section -->
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">Jobs</h2>
                    <div class="action-buttons">
                        <span class="btn btn-sm" style="background: #f8f9fa;">
                            Total Active allocations: <?php echo $jobservices_result->num_rows; ?> Jobs 
                            <br>
                        </span>
                         <span class="btn btn-sm" style="background: #f8f9fa;">
                            Total Inactive Allocation: <?php echo $jobservices2_result->num_rows; ?> Jobs 
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
                                    <th>model</th>
                                    <th>Serial</th>
                                    <th>service</th>
                                    <th>Assigned On</th>
                                    
                                    <th>status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($jobservices_result->num_rows > 0): ?>
                                    <?php while($joby = $jobservices_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($joby['customer_id']) ; ?></td>
                                            <td><?php echo htmlspecialchars($joby['item'] ); ?></td>
                                            <td><?php echo htmlspecialchars($joby['model']); ?></td>
                                            <td><?php echo htmlspecialchars($joby['serial']); ?></td>
                                            <td><?php echo htmlspecialchars($joby['service']); ?></td>
                                            <td><?php echo htmlspecialchars($joby['created_at']); ?></td>
                                           
                                            <td>
                                                <?php if($joby['status'] == 'allocated'): ?>
                                                    <span class="status-active">● Allocated</span>
                                                <?php else: ?>
                                                    <span class="status-inactive">● Unapproved</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="action-buttons">
                                                <a href="btschtechedit_jobservices.php?id=<?php echo $joby['id']; ?>" class="btn btn-sm btn-success">Edit</a>
                                               
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
                                            No roles found. <button onclick="openFormModal('addservicesModal')" class="btn btn-primary btn-sm">Add your first Job</button>
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
    <!-- ADD service MODAL - FORM MODAL -->
    <!-- ============================================ -->
    <div id="addservicesModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Assign New service</h3>
                <span class="close" onclick="closeFormModal('addservicesModal')">&times;</span>
            </div>
            <form method="POST" action="btsch_tech_job_services.php">
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
                            <label for="service">service *</label>
                            <select name="service" id="service" required onchange="updateQuantityInfo()">
                                <option value="">--Select service--</option>
                                <?php if($spare_result->num_rows > 0): ?>
                                    <?php 
                                    // Reset pointer and loop through spare services
                                    $spare_result->data_seek(0);
                                    while($spareservice = $spare_result->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($spareservice['id']);?>" >
                                                
                                            <?php echo htmlspecialchars($spareservice['service_name']);?>
                                        </option>    
                                    <?php endwhile; ?>
                                <?php endif;?>
                            </select>
                            <small id="quantityInfo" style="color: #666; font-size: 0.8rem; margin-top: 5px; display: block;">
                                Select a service to see selectable options
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
                                <option value="pending">pending</option>
                                
                            </select>
                            <small id="statusInfo" style="color: #666; font-size: 0.8rem; margin-top: 5px; display: block;">
                                "allocated" will approve immediately
                            </small>
                        </div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn" onclick="closeFormModal('addservicesModal')">Cancel</button>
                    <button type="submit" name="addservice" class="btn btn-primary" id="submitBtn">Assign service</button>
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
            if (modalId === 'addservicesModal') {
                resetserviceForm();
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
    // service FORM SPECIFIC FUNCTIONS
    // ============================================
    
    function updateQuantityInfo() {
        const serviceSelect = document.getElementById('service');
        const quantityInfo = document.getElementById('quantityInfo');
        const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
        
        if (selectedOption.value) {
            const availableQty = selectedOption.getAttribute('data-quantity');
            quantityInfo.textContent = `Available quantity: ${availableQty}`;
            quantityInfo.style.color = '#27ae60';
        } else {
            quantityInfo.textContent = 'Select a service to see available quantity';
            quantityInfo.style.color = '#666';
        }
        validateQuantity();
    }

    function validateQuantity() {
        const serviceSelect = document.getElementById('service');
        const quantityInput = document.getElementById('quantity');
        const quantityError = document.getElementById('quantityError');
        const submitBtn = document.getElementById('submitBtn');
        const statusSelect = document.getElementById('status');
        
        const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
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

    function resetserviceForm() {
        // Reset form fields
        document.getElementById('jobid').value = '';
        document.getElementById('service').value = '';
        document.getElementById('quantity').value = '';
        document.getElementById('status').value = '';
        
        // Reset info messages
        document.getElementById('quantityInfo').textContent = 'Select a service to see available quantity';
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
        if (document.getElementById('service')) {
            updateQuantityInfo();
            checkStockRequirement();
        }
    });

    // Simple test function to check if modal is working
    function testModal() {
        console.log('Test function called');
        openFormModal('addservicesModal');
    }
    </script>

</body>
</html>