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
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addstaff'])){
    // Set form variables
    $fname = htmlspecialchars(trim($_POST['fname']));
    $sname=htmlspecialchars(trim($_POST['sname']));
    $oname = htmlspecialchars(trim($_POST['oname']));
    $username=htmlspecialchars(trim($_POST['username']));
    $companyid = htmlspecialchars(trim($_POST['companyid']));
    $company = htmlspecialchars(trim($_POST['company']));
    $email=htmlspecialchars(trim($_POST['email']));
    $tel=htmlspecialchars(trim($_POST['tel']));
    $role=htmlspecialchars(trim($_POST['role']));
    $branch=htmlspecialchars(trim($_POST['branch']));
    $password=$_POST['password'];
    $confirm=$_POST['confirm'];
    $status=htmlspecialchars(trim($_POST['status']));
   
    // Validation
    if(empty($fname) || empty($sname)  || empty($companyid) || 
       empty($companyid)||empty($status) || empty($email) || empty($tel) || empty($role) || empty($branch ) || empty($password) || empty($confirm) ||empty($username))
       {
        $message = "<div class='message error'>Please fill all required fields</div>";
    } else {
        // Check if username or email already exists
        // Check existing duplicate username, email, phone number, or company
        $usercheck = $conn->prepare("SELECT username FROM techstaff WHERE username=?");
        $usercheck->bind_param("s", $username);
        $usercheck->execute();
        $userresult = $usercheck->get_result();

        $emailcheck = $conn->prepare("SELECT email FROM techstaff WHERE email=?");
        $emailcheck->bind_param("s", $email);
        $emailcheck->execute();
        $emailresult = $emailcheck->get_result();

        $telcheck = $conn->prepare("SELECT tel FROM techstaff WHERE tel=?");
        $telcheck->bind_param("s", $tel);
        $telcheck->execute();
        $telresult = $telcheck->get_result();

      

        if ($telresult->num_rows > 0) {
            $message = "<div class='message error'>Error: Telephone already registered</div>";
        } elseif ($emailresult->num_rows > 0) {
            $message = "<div class='message error'>Error: Email already registered</div>";
        } elseif ($userresult->num_rows > 0) {
            $message = "<div class='message error'>Error: Username already taken</div>";
        } elseif ($password !== $confirm) {
            $message = "<div class='message error'>Error: Your Passwords Do Not Match</div>";
        } elseif (strlen($password) < 8) {
            $message = "<div class='message error'>Error: Password must be at least 8 characters</div>";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "<div class='message error'>Error: Invalid Email Format</div>";
            
        } else {
              $hashpass = password_hash($password, PASSWORD_BCRYPT);

            $branchid_query = $conn->prepare("SELECT id FROM branches WHERE company = ? AND branch_name = ?");
            $branchid_query->bind_param("ss", $_SESSION['company'],$branch);
            $branchid_query->execute();
            $branchid_result = $branchid_query->get_result();
            $branchid_row=$branchid_result->fetch_assoc();

            $branchid=$branchid_row['id'];

            $roleid_query = $conn->prepare("SELECT id FROM roles WHERE company_id = ? AND role_name = ?");
            $roleid_query->bind_param("ss", $_SESSION['companyid'],$role);
            $roleid_query->execute();
            $roleid_result = $roleid_query->get_result();
            $roleid_row=$roleid_result->fetch_assoc();

            $roleid=$roleid_row['id'];

            // Insert branch
            $insert_staff = $conn->prepare("INSERT INTO techstaff (role_id,created_by,branchid,fname,sname,oname,username,company_id,company,email,tel,role,branch,passhash,status) VALUES (?,?,?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?)");
            $insert_staff->bind_param("isissssisssssss",$roleid, $_SESSION['username'], $branchid,$fname,$sname,$oname,$username,$companyid,$company,$email,$tel,$role,$branch,$hashpass,$status);
            
            if($insert_staff->execute()) {
                $message = "<div class='message success'>Staff added successfully!</div>";
            } else {
                $message = "<div class='message error'>Error adding Staff: " . $conn->error . "</div>";
            }
        }
    }
}

// Handle staff actions (edit, delete, assign)
if(isset($_GET['action']) && isset($_GET['staff_id'])) {
    $staff_id = intval($_GET['staff_id']);
    $action = $_GET['action'];
    
    switch($action) {
        case 'delete':
            $delete_stmt = $conn->prepare("DELETE FROM techstaff WHERE id = ? AND company = ?");
            $delete_stmt->bind_param("is", $staff_id, $_SESSION['company']);
            if($delete_stmt->execute()) {
                $message = "<div class='message success'> Staff deleted successfully</div>";
            } else {
                $message = "<div class='message error'>Error deleting Staff</div>";
            }
            break;
            
        case 'deactivate':
            $deactivate_stmt = $conn->prepare("UPDATE techstaff SET status = 'inactive' WHERE id = ? AND company = ?");
            $deactivate_stmt->bind_param("is", $staff_id, $_SESSION['company']);
            if($deactivate_stmt->execute()) {
                $message = "<div class='message success'>Staff deactivated</div>";
            }
            break;
            
        case 'activate':
            $activate_stmt = $conn->prepare("UPDATE techstaff SET status = 'active' WHERE id = ? AND company = ?");
            $activate_stmt->bind_param("is", $staff_id, $_SESSION['company']);
            if($activate_stmt->execute()) {
                $message = "<div class='message success'>Staff activated</div>";
            }
            break;
    }
}

// Get user data
$user_query = $conn->prepare("SELECT fname, sname, branch, company FROM users WHERE username = ?");
$user_query->bind_param("s", $_SESSION['username']);
$user_query->execute();
$user_result = $user_query->get_result();
$user_data = $user_result->fetch_assoc();

// Get staff for the company
$staff_query = $conn->prepare("SELECT id, fname,sname,tel,email,role,status,created_at,branch FROM techstaff WHERE company = ? ");
$staff_query->bind_param("s", $_SESSION['company']);
$staff_query->execute();
$staff_result = $staff_query->get_result();


// Get inactive staff for the company
$staff2_query = $conn->prepare("SELECT id, fname,sname,tel,email,role,status,created_at FROM techstaff WHERE company = ? AND status = 'inactive'");
$staff2_query->bind_param("s", $_SESSION['company']);
$staff2_query->execute();
$staff2_result = $staff2_query->get_result();


//get roles for the company
$roleselect = $conn->prepare("SELECT id, role_name,role_level FROM roles WHERE company = ? AND status = 'active'");
$roleselect->bind_param("s", $_SESSION['company']);
$roleselect->execute();
$roleselect_result = $roleselect->get_result();


//get branches to assign 
// Get branches for the company
$branch_query = $conn->prepare("SELECT id, branch_name FROM branches WHERE company = ? AND status = 'active'");
$branch_query->bind_param("s", $_SESSION['company']);
$branch_query->execute();
$branch_result = $branch_query->get_result();
$branch_row=$branch_result->fetch_assoc();

$branchselect=$conn->prepare("SELECT branch_name FROM branches WHERE company_id=? AND status='active'");
$branchselect->bind_param("i",$_SESSION['companyid']);
$branchselect->execute();
$branchselect_result=$branchselect->get_result();
                                

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($_SESSION['company']); ?> -Staff Management</title>
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --accent: #e74c3c;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --sidebar-width: 280px;
            --header-height: 70px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: #f8f9fa;
            color: var(--dark);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        /* Header Styles */
        header {
            background: white;
            height: var(--header-height);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 30px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo-btsch {
            position: relative;
            width: 40px;
            height: 40px;
        }
        
        .hub-center {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 15px;
            height: 15px;
            background: var(--secondary);
            border-radius: 50%;
            z-index: 10;
        }
        
        .connection-line {
            position: absolute;
            background: var(--secondary);
            height: 1px;
            transform-origin: left center;
        }
        
        .line-1 { width: 15px; top: 50%; left: 50%; transform: rotate(0deg); }
        .line-2 { width: 15px; top: 50%; left: 50%; transform: rotate(120deg); }
        .line-3 { width: 15px; top: 50%; left: 50%; transform: rotate(240deg); }
        
        .connection-point {
            position: absolute;
            width: 10px;
            height: 10px;
            background: var(--primary);
            border-radius: 50%;
            border: 1px solid var(--secondary);
        }
        
        .point-1 { top: 7px; left: 20px; background: var(--secondary); }
        .point-2 { top: 25px; left: 5px; background: var(--accent); }
        .point-3 { top: 25px; left: 25px; background: var(--success); }
        
        .logo-text {
            text-align: left;
        }
        
        .btsch {
            font-size: 1.2rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--secondary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--secondary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        /* Main Layout */
        main {
            display: flex;
            margin-top: var(--header-height);
            flex: 1;
        }
        
        /* Sidebar Styles */
        aside {
            width: var(--sidebar-width);
            background: var(--primary);
            color: white;
            height: calc(100vh - var(--header-height));
            position: fixed;
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .company-name {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .user-branch {
            font-size: 0.8rem;
            opacity: 0.8;
        }
        
        nav ul {
            list-style: none;
            padding: 0;
        }
        
        nav li {
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        
        nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        nav a:hover, nav a.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left: 4px solid var(--secondary);
        }
        
        /* Content Area */
        article {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 30px;
            background: #f8f9fa;
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            
        }
        
        .page-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark);
        }
        
        /* Message Styles */
        .message {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .message.success {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }
        
        .message.error {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--error);
            border-left: 4px solid var(--error);
        }
        
        /* Content Sections */
        .content-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .section-header {
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
        }
        
        .section-content {
            padding: 25px;
        }
        
        /* Forms */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }
        
        input, select, textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background: var(--secondary);
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .btn-success { background: var(--success); color: white; }
        .btn-warning { background: var(--warning); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        
        /* Tables */
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--dark);
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }
        
        .status-active {
            color: var(--success);
            font-weight: 600;
        }
        
        .status-inactive {
            color: var(--warning);
            font-weight: 600;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
        }
        
        .close {
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }
        
        .form-actions {
            padding: 20px 25px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        /* Footer */
        footer {
            background: var(--dark);
            color: white;
            text-align: center;
            padding: 20px;
            margin-left: var(--sidebar-width);
        }
        
        /* Navigation Button Styles */
        .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--secondary);
            color: var(--secondary);
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .btn-outline:hover {
            background: var(--secondary);
            color: white;
        }
        
        /* Navigation Modal Specific Styles */
        .nav-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 3000; /* Higher than form modals */
            align-items: center;
            justify-content: center;
        }

        .nav-modal-content {
            background: white;
            border-radius: 10px;
            width: 90%;
            max-width: 400px;
            max-height: 80vh;
            overflow: hidden;
        }
        
        .nav-modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--primary);
            color: white;
        }
        
        .nav-modal-header h3 {
            margin: 0;
            color: white;
        }
        
        .nav-close {
            font-size: 1.5rem;
            cursor: pointer;
            color: white;
        }
        
        .nav-modal-body {
            padding: 0;
            max-height: 60vh;
            overflow-y: auto;
        }
        
        .mobile-nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .mobile-nav li {
            border-bottom: 1px solid #eee;
        }
        
        .mobile-nav a {
            display: block;
            padding: 15px 20px;
            text-decoration: none;
            color: var(--dark);
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .mobile-nav a:hover {
            background: var(--light);
            padding-left: 25px;
        }
        
        .mobile-nav a.active {
            background: var(--secondary);
            color: white;
            border-left: 4px solid var(--primary);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            aside {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }
            
            aside.active {
                transform: translateX(0);
            }
            
            article {
                margin-left: 0;
            }
            
            footer {
                margin-left: 0;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            /* Show navigation button only on mobile */
            .header-actions {
                display: flex;
            }
        }
        
        /* Hide navigation button on desktop */
        @media (min-width: 769px) {
            .header-actions {
                display: none;
            }
        }
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
        
        <!-- Navigation Button - Only shows on mobile -->
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
                <div class="user-branch">Staff Management</div>
            </div>
            <nav>
                <ul>
                   <?php include 'navigation.html';?>
                </ul>
            </nav>
        </aside>

        <article>
            <div class="dashboard-header">
                <h1 class="page-title">Staff Management</h1>
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="openFormModal('addstaffModal')">➕ Add Staff</button>
                    <a href="btschtechstaff_branches.php" class="btn btn-warning">🎭 Manage Staff</a>
                </div>
            </div>

            <?php echo $message; ?>

            <!-- branches List Section -->
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">Staff</h2>
                    <div class="action-buttons">
                        <span class="btn btn-sm" style="background: #f8f9fa;">
                            Total Active: <?php echo $staff_result->num_rows; ?> Staff Personnel
                            <br>
                            Total Inactive: <?php echo $staff2_result->num_rows; ?> Staff Personnel
                        </span>
                    </div>
                </div>
                <div class="section-content">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Tel</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    
                                    <th>Branch</th>
                                    <th>status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($staff_result->num_rows > 0): ?>
                                    <?php while($staff = $staff_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($staff['fname']) .' ' . htmlspecialchars($staff['sname']) ; ?></td>
                                            <td><?php echo htmlspecialchars($staff['tel']); ?></td>
                                            <td><?php echo htmlspecialchars($staff['email']); ?></td>
                                            <td><?php echo htmlspecialchars($staff['role']); ?></td>
                                            
                                            <td><?php echo htmlspecialchars($staff['branch']); ?></td>
                                            
                                            <td>
                                                <?php if($staff['status'] == 'active'): ?>
                                                    <span class="status-active">● Active</span>
                                                <?php else: ?>
                                                    <span class="status-inactive">● Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="action-buttons">
                                                <a href="btschtechedit_staff.php?id=<?php echo $staff['id']; ?>" class="btn btn-sm btn-success">Edit</a>
                                                <a href="btschtechedit_staff.php?id=<?php echo $staff['id']; ?>" class="btn btn-sm btn-warning">Assign</a>
                                                <?php if($staff['status'] == 'active'): ?>
                                                    <a href="?action=deactivate&staff_id=<?php echo $staff['id']; ?>" class="btn btn-sm btn-warning">Deactivate</a>
                                                <?php else: ?>
                                                    <a href="?action=activate&staff_id=<?php echo $staff['id']; ?>" class="btn btn-sm btn-success">Activate</a>
                                                <?php endif; ?>
                                                <a href="?action=delete&staff_id=<?php echo $staff['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this Staff Personnel ?')">Delete</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 20px;">
                                            No staff Personnel found. <button onclick="openFormModal('addstaffModal')" class="btn btn-primary btn-sm">Add your first staff</button>
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

    <!-- ============================================ -->
    <!-- ADD branch MODAL - FORM MODAL -->
    <!-- ============================================ -->
    <div id="addstaffModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Staff</h3>
                <span class="close" onclick="closeFormModal('addstaffModal')">&times;</span>
            </div>
            <form method="POST" action="btschtech_staff.php">
                <div class="section-content">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="fname">First Name: *</label>
                            <input type="text" id="firstname" name="fname" required>
                        </div>
                        <div class="form-group">
                            <label for="sname">Second Name:  *</label>
                            <input type="text" id="secondname" name="sname" required>
                        </div>
                        <div class="form-group">
                            <label for="oname">Other Names:  </label>
                            <input type="text" id="ondname" name="oname">
                        </div>
                        <div class="form-group">
                            <label for="uname">Username:  *</label>
                            <input type="text" id="username" name="username" required>
                        </div>
                        <div class="form-group">
                            <label for="tel">Tel:  *</label>
                            <input type="text" id="tel" name="tel" required>
                        </div>
                        <div class="form-group">
                            <label for="email">email:  *</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Password:  *</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm">Confirm Password:  *</label>
                            <input type="password" id="confirm" name="confirm" required>
                        </div>
                        <input type="hidden" id="company" name="company" value="<?php echo $_SESSION['company'];?>">
                        <input type="hidden" id="companyid" name="companyid" value="<?php echo $_SESSION['companyid'];?>" required>
                        <div class="form-group">
                            <label for="status">Status *</label>
                            <select name="status" id="status" required>
                                <option value="">--select--</option>
                                <option value="active">active</option>
                                <option value="inactive">inactive</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="branch">Assign Branch:</label>
                            <select name="branch" id="branch" required>
                                <option value="">Assign Branch</option>
                               <?php if($branchselect_result->num_rows > 0): ?>
                                    <?php while($selectedbranch = $branchselect_result->fetch_assoc()): ?>
                                        <tr>
                                            <option value="<?php echo htmlspecialchars($selectedbranch['branch_name']);?>"><?php echo htmlspecialchars($selectedbranch['branch_name']);?></option>
                                          
                                    <?php endwhile; ?>
                                                   
                                    <?php endif; ?>
                            </select>

                        </div>
                         <div class="form-group">
                            <label for="branch">Assign Role:</label>
                            <select name="role" id="role" required>
                                <option value="">Assign Role</option>
                               <?php if($roleselect_result->num_rows > 0): ?>
                                    <?php while($selectedrole = $roleselect_result->fetch_assoc()): ?>
                                        <tr>
                                            <option value="<?php echo htmlspecialchars($selectedrole['role_name']);?>"><?php echo htmlspecialchars($selectedrole['role_name']);?></option>
                                          
                                    <?php endwhile; ?>
                                                   
                                    <?php endif; ?>
                            </select>

                        </div>
                        
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn" onclick="closeFormModal('addstaffModal')">Cancel</button>
                    <button type="submit" name="addstaff" class="btn btn-primary">Add Staff</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- NAVIGATION MODAL - SEPARATE FROM FORM MODALS -->
    <!-- ============================================ -->
    <div id="navModal" class="nav-modal">
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
    // FORM MODAL FUNCTIONS - For add branch, add staff, etc.
    // ============================================
    
    /**
     * Open form modal (for adding branches, staff, etc.)
     * @param {string} modalId - The ID of the modal to open
     */
    function openFormModal(modalId) {
        console.log('Opening form modal:', modalId);
        document.getElementById(modalId).style.display = 'flex';
    }
    
    /**
     * Close form modal
     * @param {string} modalId - The ID of the modal to close
     */
    function closeFormModal(modalId) {
        console.log('Closing form modal:', modalId);
        document.getElementById(modalId).style.display = 'none';
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
    
    //console.log('Modal functions loaded successfully');
    </script>

</body>
</html>