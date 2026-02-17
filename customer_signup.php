
<?php
require 'secrete.php';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    // Variables initialization
    $firstname = trim($_POST['firstname']);
    $secondname = trim($_POST['secondname']);
    $othername = trim($_POST['othernames']);
    $company = mb_strtoupper(trim($_POST['company']));
    $role = trim($_POST['role']);
    $email = trim($_POST['email']);
    $tel = trim($_POST['tel']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm'];
    $location = trim($_POST['location']);
    
    

    // Check empty fields
    if (empty($firstname) || empty($secondname) || empty($company) || empty($role) || empty($email) || 
        empty($tel) || empty($username) || empty($password) || empty($confirm) || empty($location)  ) 
        {
        $message = "<div class='message error'>Error: Please Fill All Fields</div>";
    } else {
        // Check existing duplicate username, email, phone number, or company
        $usercheck = $conn->prepare("SELECT username FROM customers WHERE username=?");
        $usercheck->bind_param("s", $username);
        $usercheck->execute();
        $userresult = $usercheck->get_result();

        $emailcheck = $conn->prepare("SELECT email FROM customers WHERE email=?");
        $emailcheck->bind_param("s", $email);
        $emailcheck->execute();
        $emailresult = $emailcheck->get_result();

        $telcheck = $conn->prepare("SELECT tel FROM customers WHERE tel=?");
        $telcheck->bind_param("s", $tel);
        $telcheck->execute();
        $telresult = $telcheck->get_result();

        $companycheck = $conn->prepare("SELECT company FROM customers WHERE company=?");
        $companycheck->bind_param("s", $company);
        $companycheck->execute();
        $companyresult = $companycheck->get_result();

        if ($telresult->num_rows > 0) {
            $message = "<div class='message error'>Error: Telephone already registered</div>";
        } elseif ($emailresult->num_rows > 0) {
            $message = "<div class='message error'>Error: Email already registered</div>";
        } elseif ($userresult->num_rows > 0) {
            $message = "<div class='message error'>Error: Username already taken</div>";
        } elseif ($companyresult->num_rows > 0) {
            $message = "<div class='message error'>Error: Company name already taken</div>";
        } elseif ($password !== $confirm) {
            $message = "<div class='message error'>Error: Your Passwords Do Not Match</div>";
        } elseif (strlen($password) < 8) {
            $message = "<div class='message error'>Error: Password must be at least 8 characters</div>";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "<div class='message error'>Error: Invalid Email Format</div>";
        } else {
            $passwordtosave = password_hash($password, PASSWORD_BCRYPT);

            $signup = $conn->prepare("INSERT INTO customers (fname, sname, oname, company, role, email, tel, username, passhash, location) VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $signup->bind_param("ssssssssss", $firstname, $secondname, $othername, $company, $role, $email, $tel, $username, $passwordtosave, $location);
            
            if ($signup->execute()) {
                $message = "<div class='message success'>Success: Registration Complete! Proceed to login</div>";
            } else {
                $message = "<div class='message error'>Error: Something went wrong. Please try again later.</div>";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Brazy-Tech Link - Company Sign Up</title>
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --accent: #e74c3c;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --success: #2ecc71;
            --error: #e74c3c;
            --warning: #f39c12;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: var(--dark);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 20px 0;
            text-align: center;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .logo {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .tagline {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .content {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            margin: 30px 0;
        }
        
        .info-section {
            flex: 1;
            min-width: 300px;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        
        .form-section {
            flex: 2;
            min-width: 300px;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        
        h2 {
            color: var(--primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light);
        }
        
        h3 {
            color: var(--secondary);
            margin: 20px 0 15px;
        }
        
        .message {
            padding: 12px 15px;
            border-radius: 5px;
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
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--dark);
        }
        
        input, select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        
        .btn {
            display: inline-block;
            padding: 12px 25px;
            background: var(--secondary);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            text-decoration: none;
        }
        
        .btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .btn-secondary {
            background: var(--primary);
        }
        
        .btn-secondary:hover {
            background: #1a252f;
        }
        
        .btn-accent {
            background: var(--accent);
        }
        
        .btn-accent:hover {
            background: #c0392b;
        }
        
        .action-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        
        .alternative-signup {
            margin-top: 40px;
            text-align: center;
        }
        
        .alternative-signup h3 {
            margin-bottom: 20px;
        }
        
        .signup-options {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 15px;
        }
        
        footer {
            text-align: center;
            margin-top: 40px;
            padding: 20px;
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 15px;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="logo">BrazyTech Supply Chain Hub (BTSCH)</div>
            <div class="tagline">Connecting Technicians, Suppliers,Transporters and Customers</div>
        </div>
    </header>
    
    <div class="container">
        <div class="content">
            <div class="info-section">
                <h2>Welcome To Brazy-Tech Link</h2>
                <h3>What We Do</h3>
                <p>We offer a platform where technicians, suppliers, spare part dealers and customers can run business operations with ease and efficiency. Our platform reduces the time needed to source for parts and helps identify which parts are needed most and when.</p>
                <p>If this is what you're looking for, you're at the right place.</p>
                
                <h3>Benefits</h3>
                <ul>
                    <li>Connect with reliable service providers</li>
                    <li>Streamline your business operations</li>
                    <li>Access to a network of suppliers</li>
                    <li>Reduce downtime with efficient part sourcing</li>
                </ul>
            </div>
            
            <div class="form-section">
                <h2>Sign Up As Customer</h2>
                
                <?php if (isset($message)) { echo $message; } ?>
                
                <form action="customer_signup.php" method="post">
                    <h3>Personal Information</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="firstname">First Name *</label>
                            <input type="text" id="firstname" name="firstname" value="<?php echo isset($_POST['firstname']) ? htmlspecialchars($_POST['firstname']) : ''; ?>" required placeholder="John">
                        </div>
                        
                        <div class="form-group">
                            <label for="secondname">Second Name *</label>
                            <input type="text" id="secondname" name="secondname" value="<?php echo isset($_POST['secondname']) ? htmlspecialchars($_POST['secondname']) : ''; ?>" required placeholder="Doe">
                        </div>
                        
                        <div class="form-group">
                            <label for="othernames">Other Names</label>
                            <input type="text" id="othernames" name="othernames" value="<?php echo isset($_POST['othernames']) ? htmlspecialchars($_POST['othernames']) : ''; ?>" placeholder="Lorem Ipsum">
                        </div>
                         <div class="form-group">
                            <label for="location">Customer Location *</label>
                            <input type="text" id="location" name="location" value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>" required placeholder="Kisumu CBD MegaTOWN Room X">
                        </div>
                        <div class="form-group">
                            <label for="company">Host Company Name *</label>
                            <input type="text" id="company" name="company" value="<?php echo isset($_POST['company']) ? htmlspecialchars($_POST['company']) : ''; ?>" required placeholder="ACEOO LTD">
                        </div>
                        
                       
                        
                        <div class="form-group">
                            <label for="role">Customer Type *</label>
                            <select id="role" name="role" required>
                                <option value="">-- Select Role --</option>
                                <option value="sales" <?php echo (isset($_POST['role']) && $_POST['role'] == 'owner') ? 'selected' : ''; ?>>Sales Customer</option>
                                <option value="service" <?php echo (isset($_POST['role']) && $_POST['role'] == 'ceo') ? 'selected' : ''; ?>>Service Customer</option>
                                <option value="Query" <?php echo (isset($_POST['role']) && $_POST['role'] == 'manager') ? 'selected' : ''; ?>>Enquiry Customer</option>
                               
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required placeholder="someone@domain.com">
                        </div>
                        
                        <div class="form-group">
                            <label for="tel">Telephone *</label>
                            <input type="text" id="tel" name="tel" value="<?php echo isset($_POST['tel']) ? htmlspecialchars($_POST['tel']) : ''; ?>" required placeholder="+254...">
                        </div>
                        
                        <div class="form-group">
                            <label for="username">Username *</label>
                            <input type="text" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password *</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm">Confirm Password *</label>
                            <input type="password" id="confirm" name="confirm" required>
                        </div>
                    </div>
                    
                   
                    
                    <div class="action-buttons">
                        <button type="submit" name="register" class="btn">Register </button>
                        <a href="company_login.php" class="btn btn-secondary">Already Have an Account? Login</a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="alternative-signup">
            <h3>Want to sign up as something else?</h3>
            <div class="signup-options">
                <a href="spare_provider_signup.php" class="btn btn-accent">Spare Parts Provider</a>
                <a href="transport_provider_signup.php" class="btn btn-accent">Transport Agency</a>
                <a href="customer_signup.php" class="btn btn-accent">Service Customer</a>
            </div>
        </div>
    </div>
    
    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?>Powered By Brazy-Technologies . All rights reserved.</p>
        </div>
    </footer>
</body>
</html>

