<?php
session_start();
require 'secrete.php';
error_reporting(1);

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $tel = trim($_POST['tel']);
    $company = trim($_POST['company']);
   
    $password = $_POST['password'];

    // Validate required fields
    if (empty($username) || empty($tel) || empty($company) || empty($password)) {
        $message = "<div class='message error'>Please fill all fields</div>";
    } else {
        // Check if user exists in users table (technician company owners)
        $user_check = $conn->prepare("SELECT id, username, passhash, fname, sname, role,email, company,branch FROM customers WHERE username = ? AND tel = ?  AND company = ?");
        $user_check->bind_param("sss", $username, $tel, $company);
        $user_check->execute();
        $user_result = $user_check->get_result();
        $user_data = $user_result->fetch_assoc();
        
        if ($user_data && password_verify($password, $user_data['passhash'])) {
            // Create session
            $_SESSION['csrftoken'] = bin2hex(random_bytes(32));
            $_SESSION['username'] = $username;
            $_SESSION['company'] = $company;
            $_SESSION['customerbranch'] = $user_data['branch'];
            
            $_SESSION['tel'] = $tel;
            $_SESSION['customerid'] = $user_data['id'];
            $_SESSION['user_type'] = 'Tech Customer';
            $_SESSION['login_time'] = time();
            $_SESSION['customer_full_name'] = $user_data['fname'] . ' ' . $user_data['sname'];
            $_SESSION['role'] = $user_data['role'];
            $_SESSION['customeremail'] = $user_data['email'];
            
            // Update token
            $set_token = $conn->prepare("UPDATE customers SET token = ?, last_login = NOW() WHERE username = ? AND company = ?");
            $set_token->bind_param("sss", $_SESSION['csrftoken'], $username, $company);
            $set_token->execute();
            
            header("Location: tech_customer_dashboard.php");
            exit();
        } else {
            $message = "<div class='message error'>Invalid credentials. Please check your details.</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BTSCH - Tech Customer Login</title>
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --accent: #e74c3c;
            --success: #27ae60;
            --warning: #f39c12;
            --light: #ecf0f1;
            --dark: #2c3e50;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-portal {
            max-width: 1000px;
            width: 100%;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        }
        
        .portal-header {
            background: var(--primary);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .portal-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .portal-subtitle {
            font-size: 1rem;
            opacity: 0.9;
        }
        
        .portal-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 500px;
        }
        
        .login-section {
            padding: 40px;
            background: white;
        }
        
        .portal-links {
            padding: 40px;
            background: #f8f9fa;
            border-left: 1px solid #e9ecef;
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 25px;
            color: var(--primary);
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
        
        input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        input:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background: var(--secondary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .message {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .message.error {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--accent);
            border-left: 4px solid var(--accent);
        }
        
        .portal-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .portal-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-decoration: none;
            color: var(--dark);
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        
        .portal-card:hover {
            border-color: var(--secondary);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-desc {
            font-size: 0.9rem;
            color: #666;
            line-height: 1.4;
        }
        
        .card-badge {
            background: var(--secondary);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .portal-content {
                grid-template-columns: 1fr;
            }
            
            .portal-links {
                border-left: none;
                border-top: 1px solid #e9ecef;
            }
        }
    </style>
</head>
<body>
    <div class="login-portal">
        <div class="portal-header">
            <h1 class="portal-title">BTSCH Portal</h1>
            <p class="portal-subtitle">BrazyTech Supply Chain Hub - Tech Customer Access</p>
        </div>
        
        <div class="portal-content">
            <!-- Login Section -->
            <div class="login-section">
                <h2 class="section-title">🔧 Tech Customer Login</h2>
                <p style="color: #666; margin-bottom: 25px;">Enter your tech customer credentials to access the customer dashboard</p>
                
                <?php if (isset($message)) { echo $message; } ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="tel">Telephone</label>
                        <input type="text" id="tel" name="tel" value="<?php echo isset($_POST['tel']) ? htmlspecialchars($_POST['tel']) : ''; ?>" required placeholder="+254...">
                    </div>
                    
                    <div class="form-group">
                        <label for="company">Company Name</label>
                        <input type="text" id="company" name="company" value="<?php echo isset($_POST['company']) ? htmlspecialchars($_POST['company']) : ''; ?>" required>
                    </div>
                    
                   
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <button type="submit" name="login" class="btn">Login to Customer Dashboard</button>
                    <br><br>
                    <a href="company_login.php"> <button type="button"  class="btn">Main Menu</button></a>
                </form>
                
                <div style="margin-top: 25px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <p style="font-size: 0.9rem; color: #666; margin: 0;">
                        <strong>Note:</strong> This portal is for registered tech Customers service  only.
                    </p>
                </div>
            </div>
            
            <!-- Portal Links Section -->
            <div class="portal-links">
                <h2 class="section-title">Access Other Portals</h2>
                <p style="color: #666; margin-bottom: 25px;">Select your Customer Type to access the appropriate login portal</p>
                
                <div class="portal-grid">
                    <!-- Company Owners -->
                    
                    
                    <!-- Customers -->
                    <div>
                        <h3 style="font-size: 1rem; color: var(--primary); margin-bottom: 10px;">👤 Customers</h3>
                        <div style="display: grid; gap: 10px;">
                            <a href="tech_customer_login.php" class="portal-card">
                                <div class="card-title">👥 Service Customer</div>
                                <div class="card-desc">Access your customer account, service requests and repairs </div>
                            </a>
                             <a href="parts_customer_login.php" class="portal-card">
                                <div class="card-title">👥 Parts & Supplies Customer</div>
                                <div class="card-desc">Access your parts account and parts requests parts and supplies provider</div>
                            </a>
                             <a href="transport_customer_login.php" class="portal-card">
                                <div class="card-title">👥 Transport Customer</div>
                                <div class="card-desc">Access your transport account and transport requests from transport provider</div>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 30px; padding: 20px; background: white; border-radius: 10px; border-left: 4px solid var(--success);">
                    <h4 style="margin-bottom: 10px; color: var(--primary);">New to BTSCH?</h4>
                    <p style="font-size: 0.9rem; color: #666; margin-bottom: 15px;">
                        Register as Customer to join our supply chain network
                    </p>
                    <a href="tech_customer_signup.php" style="display: inline-block; padding: 10px 20px; background: var(--success); color: white; text-decoration: none; border-radius: 6px; font-weight: 600;">
                        Register As A Service Customer.
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>