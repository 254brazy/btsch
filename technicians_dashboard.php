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

// Get user data
$user_query = $conn->prepare("SELECT fname, sname, role, company FROM users WHERE username = ?");
$user_query->bind_param("s", $_SESSION['username']);
$user_query->execute();
$user_result = $user_query->get_result();
$user_data = $user_result->fetch_assoc();

// Get staff count for dashboard
$staff_count_query = $conn->prepare("SELECT COUNT(*) as total_staff FROM users WHERE company = ?");
$staff_count_query->bind_param("s", $_SESSION['company']);
$staff_count_query->execute();
$staff_count_result = $staff_count_query->get_result();
$staff_count = $staff_count_result->fetch_assoc()['total_staff'];

// Get branches count
$branches_query = $conn->prepare("SELECT COUNT(*) as total_branches FROM branches WHERE company = ?");
$branches_query->bind_param("s", $_SESSION['company']);
$branches_query->execute();
$branches_result = $branches_query->get_result();
$branches_count = $branches_result->fetch_assoc()['total_branches'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($_SESSION['company']); ?> - Technician Dashboard</title>
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
        
        .user-role {
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
        
        nav i {
            width: 20px;
            text-align: center;
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
            justify-content: between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .page-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark);
        }
        
        /* Dashboard Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid var(--secondary);
        }
        
        .stat-card.purple { border-left-color: #9b59b6; }
        .stat-card.green { border-left-color: var(--success); }
        .stat-card.orange { border-left-color: var(--warning); }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-weight: 500;
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
            justify-content: between;
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
        
        .btn-success { background: var(--success); color: white; }
        .btn-warning { background: var(--warning); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        
        /* Footer */
        footer {
            background: var(--dark);
            color: white;
            text-align: center;
            padding: 20px;
            margin-left: var(--sidebar-width);
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
            
            .stats-grid {
                grid-template-columns: 1fr;
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
        
        <?php include 'userheader.html';?>
    </header>

    <main>
        <aside>
            <div class="sidebar-header">
                <div class="company-name"><?php echo htmlspecialchars($_SESSION['company']); ?></div>
                <div class="user-role">Technician Dashboard</div>
            </div>
            <nav>
                <ul>
                   <?php include 'navigation.html';?>
                </ul>
            </nav>
        </aside>

        <article>
            <div class="dashboard-header">
                <h1 class="page-title">Dashboard Overview</h1>
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="showModal('addStaffModal')">➕ Add Staff</button>
                    <button class="btn btn-primary" onclick="showModal('addBranchModal')">🏢 Add Branch</button>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $staff_count; ?></div>
                    <div class="stat-label">Total Staff</div>
                </div>
                <div class="stat-card purple">
                    <div class="stat-number"><?php echo $branches_count; ?></div>
                    <div class="stat-label">Branches</div>
                </div>
                <div class="stat-card green">
                    <div class="stat-number">12</div>
                    <div class="stat-label">Active Jobs</div>
                </div>
                <div class="stat-card orange">
                    <div class="stat-number">5</div>
                    <div class="stat-label">Pending Quotes</div>
                </div>
            </div>

            <!-- Staff Management Section -->
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">👥 Staff Management</h2>
                    <button class="btn btn-primary" onclick="showModal('addStaffModal')">Add New Staff</button>
                </div>
                <div class="section-content">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Branch</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>John Doe</td>
                                    <td>Senior Technician</td>
                                    <td>Main Branch</td>
                                    <td>john@company.com</td>
                                    <td><span style="color: var(--success);">● Active</span></td>
                                    <td class="action-buttons">
                                        <button class="btn btn-sm btn-success">Edit</button>
                                        <button class="btn btn-sm btn-warning">Assign</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Jane Smith</td>
                                    <td>Service Manager</td>
                                    <td>West Branch</td>
                                    <td>jane@company.com</td>
                                    <td><span style="color: var(--success);">● Active</span></td>
                                    <td class="action-buttons">
                                        <button class="btn btn-sm btn-success">Edit</button>
                                        <button class="btn btn-sm btn-warning">Assign</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Branch Management Section -->
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">🏢 Branch Management</h2>
                    <button class="btn btn-primary" onclick="showModal('addBranchModal')">Add New Branch</button>
                </div>
                <div class="section-content">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Branch Name</th>
                                    <th>Location</th>
                                    <th>Manager</th>
                                    <th>Staff Count</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Main Branch</td>
                                    <td>Nairobi CBD</td>
                                    <td>John Doe</td>
                                    <td>15</td>
                                    <td><span style="color: var(--success);">● Active</span></td>
                                    <td class="action-buttons">
                                        <button class="btn btn-sm btn-success">Edit</button>
                                        <button class="btn btn-sm btn-warning">Manage</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>West Branch</td>
                                    <td>Westlands</td>
                                    <td>Jane Smith</td>
                                    <td>8</td>
                                    <td><span style="color: var(--success);">● Active</span></td>
                                    <td class="action-buttons">
                                        <button class="btn btn-sm btn-success">Edit</button>
                                        <button class="btn btn-sm btn-warning">Manage</button>
                                    </td>
                                </tr>
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

    <!-- Add Staff Modal -->
    <div id="addStaffModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Staff Member</h3>
                <span class="close" onclick="closeModal('addStaffModal')">&times;</span>
            </div>
            <form id="addStaffForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="firstName">First Name *</label>
                        <input type="text" id="firstName" name="firstName" required>
                    </div>
                    <div class="form-group">
                        <label for="lastName">Last Name *</label>
                        <input type="text" id="lastName" name="lastName" required>
                    </div>
                    <div class="form-group">
                        <label for="staffEmail">Email *</label>
                        <input type="email" id="staffEmail" name="staffEmail" required>
                    </div>
                    <div class="form-group">
                        <label for="staffPhone">Phone *</label>
                        <input type="tel" id="staffPhone" name="staffPhone" required>
                    </div>
                    <div class="form-group">
                        <label for="staffRole">Role *</label>
                        <select id="staffRole" name="staffRole" required>
                            <option value="">Select Role</option>
                            <option value="technician">Technician</option>
                            <option value="manager">Manager</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="assistant">Assistant</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="staffBranch">Assign to Branch *</label>
                        <select id="staffBranch" name="staffBranch" required>
                            <option value="">Select Branch</option>
                            <option value="main">Main Branch</option>
                            <option value="west">West Branch</option>
                        </select>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn" onclick="closeModal('addStaffModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Staff</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Branch Modal -->
    <div id="addBranchModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Branch</h3>
                <span class="close" onclick="closeModal('addBranchModal')">&times;</span>
            </div>
            <form id="addBranchForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="branchName">Branch Name *</label>
                        <input type="text" id="branchName" name="branchName" required>
                    </div>
                    <div class="form-group">
                        <label for="branchLocation">Location *</label>
                        <input type="text" id="branchLocation" name="branchLocation" required>
                    </div>
                    <div class="form-group">
                        <label for="branchAddress">Full Address</label>
                        <textarea id="branchAddress" name="branchAddress" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="branchManager">Branch Manager</label>
                        <select id="branchManager" name="branchManager">
                            <option value="">Select Manager</option>
                            <option value="1">John Doe</option>
                            <option value="2">Jane Smith</option>
                        </select>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn" onclick="closeModal('addBranchModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Branch</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .modal {
        
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
        }
        
        .modal-content {
            margin:3x;
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
            justify-content: between;
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
    </style>

    <script>
        function showModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // Form submissions
        document.getElementById('addStaffForm').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Staff member added successfully!');
            closeModal('addStaffModal');
            this.reset();
        });
        
        document.getElementById('addBranchForm').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Branch added successfully!');
            closeModal('addBranchModal');
            this.reset();
        });
    </script>
</body>
</html>