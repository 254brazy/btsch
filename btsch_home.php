<?php
// landing.php
$page_title = "BTSCH - BrazyTech Supply Chain Hub";
$current_user_type = isset($_GET['user_type']) ? $_GET['user_type'] : 'all';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --accent: #e74c3c;
            --tech-blue: #3498db;
            --parts-orange: #e67e22;
            --transport-green: #27ae60;
            --customer-purple: #9b59b6;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --success: #2ecc71;
            --warning: #f39c12;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: var(--dark);
            line-height: 1.6;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Header & Navigation */
        header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 20px 0;
            border-radius: 0 0 20px 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        /* Logo Styles */
        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo-btsch {
            position: relative;
            width: 80px;
            height: 80px;
        }
        
        .hub-center {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 30px;
            height: 30px;
            background: var(--tech-blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            box-shadow: 0 0 0 2px var(--primary), 0 0 0 4px rgba(52, 152, 219, 0.3);
        }
        
        .gear {
            width: 20px;
            height: 20px;
            background: conic-gradient(from 0deg, #fff 0deg 45deg, transparent 45deg 90deg, #fff 90deg 135deg, transparent 135deg 180deg, #fff 180deg 225deg, transparent 225deg 270deg, #fff 270deg 315deg, transparent 315deg);
            border-radius: 50%;
            animation: rotate 10s linear infinite;
        }
        
        .connection-line {
            position: absolute;
            background: var(--tech-blue);
            height: 2px;
            transform-origin: left center;
        }
        
        .line-1 { width: 30px; top: 50%; left: 50%; transform: rotate(0deg); }
        .line-2 { width: 30px; top: 50%; left: 50%; transform: rotate(120deg); }
        .line-3 { width: 30px; top: 50%; left: 50%; transform: rotate(240deg); }
        
        .connection-point {
            position: absolute;
            width: 20px;
            height: 20px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--tech-blue);
            font-size: 8px;
        }
        
        .point-1 { top: 15px; left: 35px; background: var(--tech-blue); }
        .point-2 { top: 50px; left: 10px; background: var(--parts-orange); }
        .point-3 { top: 50px; left: 50px; background: var(--transport-green); }
        
        .logo-text {
            text-align: left;
        }
        
        .btsch {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--tech-blue), var(--parts-orange), var(--transport-green));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: 1px;
            line-height: 1;
        }
        
        .subtitle {
            font-size: 0.7rem;
            color: var(--dark);
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        
        /* Navigation */
        .nav-links {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .nav-link {
            padding: 10px 20px;
            background: var(--light);
            border-radius: 25px;
            text-decoration: none;
            color: var(--dark);
            font-weight: 600;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        
        .nav-link:hover, .nav-link.active {
            background: var(--secondary);
            color: white;
            transform: translateY(-2px);
        }
        
        /* Hero Section */
        .hero {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .hero h1 {
            font-size: 2.5rem;
            margin-bottom: 20px;
            color: var(--primary);
        }
        
        .hero p {
            font-size: 1.2rem;
            color: #666;
            max-width: 800px;
            margin: 0 auto 30px;
        }
        
        .cta-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 25px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: var(--secondary);
            color: white;
        }
        
        .btn-secondary {
            background: var(--light);
            color: var(--dark);
            border: 2px solid var(--secondary);
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        /* Stakeholders Section */
        .stakeholders {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .stakeholder-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
            border-top: 5px solid;
        }
        
        .stakeholder-card:hover {
            transform: translateY(-5px);
        }
        
        .card-tech { border-color: var(--tech-blue); }
        .card-parts { border-color: var(--parts-orange); }
        .card-transport { border-color: var(--transport-green); }
        .card-customer { border-color: var(--customer-purple); }
        
        .card-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .card-tech .card-icon { color: var(--tech-blue); }
        .card-parts .card-icon { color: var(--parts-orange); }
        .card-transport .card-icon { color: var(--transport-green); }
        .card-customer .card-icon { color: var(--customer-purple); }
        
        .stakeholder-card h3 {
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        .benefits-list {
            list-style: none;
            margin-top: 15px;
        }
        
        .benefits-list li {
            padding: 5px 0;
            padding-left: 20px;
            position: relative;
        }
        
        .benefits-list li:before {
            content: '✓';
            position: absolute;
            left: 0;
            color: var(--success);
            font-weight: bold;
        }
        
        /* How It Works */
        .how-it-works {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
        }
        
        .process-steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .process-step {
            text-align: center;
            padding: 20px;
        }
        
        .step-number {
            width: 50px;
            height: 50px;
            background: var(--secondary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0 auto 15px;
        }
        
        /* Footer */
        footer {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px 20px 0 0;
            padding: 30px;
            text-align: center;
            margin-top: 40px;
        }
        
        .footer-links {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 15px;
            }
            
            .hero h1 {
                font-size: 2rem;
            }
            
            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 300px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container nav-container">
            <div class="logo-section">
                <div class="logo-btsch">
                    <div class="hub-center">
                        <div class="gear"></div>
                    </div>
                    <div class="connection-line line-1"></div>
                    <div class="connection-line line-2"></div>
                    <div class="connection-line line-3"></div>
                    <div class="connection-point point-1">🔧</div>
                    <div class="connection-point point-2">⚙️</div>
                    <div class="connection-point point-3">🚚</div>
                </div>
                <div class="logo-text">
                    <div class="btsch">BTSCH</div>
                    <div class="subtitle">BrazyTech Supply Chain Hub</div>
                </div>
            </div>
            
            <nav class="nav-links">
                <a href="btsch_home.php" class="nav-link <?php echo $current_user_type == 'all' ? 'active' : ''; ?>">All Users</a>
                <a href="company_signup.php" class="nav-link <?php echo $current_user_type == 'technician' ? 'active' : ''; ?>">Technicians</a>
                <a href="spare_provider_signup.php" class="nav-link <?php echo $current_user_type == 'parts' ? 'active' : ''; ?>">Parts Dealers</a>
                <a href="transport_provider_signup.php" class="nav-link <?php echo $current_user_type == 'transport' ? 'active' : ''; ?>">Transport</a>
                <a href="customer_signup.php" class="nav-link <?php echo $current_user_type == 'customer' ? 'active' : ''; ?>">Customers</a>
            </nav>
        </div>
    </header>

    <div class="container">
        <!-- Hero Section -->
        <section class="hero">
            <h1>Complete Industrial Supply Chain Platform</h1>
            <p>Connecting technicians, parts suppliers, transport providers, and customers in one seamless ecosystem for both repairs AND new projects</p>
            <div class="cta-buttons">
                <a href="company_signup.php" class="btn btn-primary">Join as Business</a>
                <a href="customer_signup.php" class="btn btn-secondary">Join as Customer</a>
            </div>
        </section>

        <!-- Stakeholders Section -->
        <section class="how-it-works">
            <h2 style="text-align: center; margin-bottom: 30px;">How BTSCH Works For Everyone</h2>
            
            <div class="stakeholders">
                <!-- Technicians Card -->
                <div class="stakeholder-card card-tech">
                    <div class="card-icon">🔧</div>
                    <h3>Technicians & Service Providers</h3>
                    <p>Streamline your workflow from diagnosis to completion</p>
                    <ul class="benefits-list">
                        <li>Source parts for repairs AND new installations</li>
                        <li>Real-time availability from multiple suppliers</li>
                        <li>Integrated transport coordination</li>
                        <li>Project management tools</li>
                        <li>Customer communication portal</li>
                        <li>Access to specialized components for custom projects</li>
                    </ul>
                </div>

                <!-- Parts Dealers Card -->
                <div class="stakeholder-card card-parts">
                    <div class="card-icon">⚙️</div>
                    <h3>Parts & Materials Suppliers</h3>
                    <p>Maximize your inventory reach and sales</p>
                    <ul class="benefits-list">
                        <li>List repair parts AND construction materials</li>
                        <li>Real-time inventory management</li>
                        <li>Direct connection with active buyers</li>
                        <li>Bulk order opportunities</li>
                        <li>Automated restock alerts</li>
                        <li>Specialized components marketplace</li>
                    </ul>
                </div>

                <!-- Transport Providers Card -->
                <div class="stakeholder-card card-transport">
                    <div class="card-icon">🚚</div>
                    <h3>Transport & Logistics</h3>
                    <p>Optimize your fleet utilization</p>
                    <ul class="benefits-list">
                        <li>Automatic job matching based on location</li>
                        <li>Both urgent repair deliveries and scheduled project shipments</li>
                        <li>Real-time tracking integration</li>
                        <li>Document management</li>
                        <li>Payment processing</li>
                        <li>Specialized equipment transport opportunities</li>
                    </ul>
                </div>

                <!-- Customers Card -->
                <div class="stakeholder-card card-customer">
                    <div class="card-icon">👥</div>
                    <h3>Customers & Project Owners</h3>
                    <p>Complete visibility and control</p>
                    <ul class="benefits-list">
                        <li>Find reliable technicians for repairs AND new projects</li>
                        <li>Track project progress in real-time</li>
                        <li>Compare parts/material options and prices</li>
                        <li>Monitor delivery status</li>
                        <li>Secure payment system</li>
                        <li>Access to specialized services for custom requirements</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Process Flow -->
        <section class="how-it-works">
            <h2 style="text-align: center; margin-bottom: 30px;">The BTSCH Process</h2>
            <div class="process-steps">
                <div class="process-step">
                    <div class="step-number">1</div>
                    <h3>Need Identification</h3>
                    <p>Customer reports issue or starts new project. Technician assesses requirements.</p>
                </div>
                <div class="process-step">
                    <div class="step-number">2</div>
                    <h3>Parts Sourcing</h3>
                    <p>System matches requirements with available parts/materials from suppliers.</p>
                </div>
                <div class="process-step">
                    <div class="step-number">3</div>
                    <h3>Logistics Coordination</h3>
                    <p>Transport providers are automatically matched based on location and capacity.</p>
                </div>
                <div class="process-step">
                    <div class="step-number">4</div>
                    <h3>Execution & Monitoring</h3>
                    <p>Technician completes work while customer tracks progress in real-time.</p>
                </div>
            </div>
        </section>
    </div>

    <footer>
        <div class="container">
            <div class="logo-section" style="justify-content: center; margin-bottom: 20px;">
                <div class="logo-btsch">
                    <div class="hub-center">
                        <div class="gear"></div>
                    </div>
                    <div class="connection-line line-1"></div>
                    <div class="connection-line line-2"></div>
                    <div class="connection-line line-3"></div>
                    <div class="connection-point point-1">🔧</div>
                    <div class="connection-point point-2">⚙️</div>
                    <div class="connection-point point-3">🚚</div>
                </div>
                <div class="logo-text">
                    <div class="btsch">BTSCH</div>
                    <div class="subtitle">Complete Supply Chain Solution</div>
                </div>
            </div>
            
            <div class="footer-links">
                <a href="company_signup.php">Business Sign Up</a>
                <a href="Customer_signup.php">Customer Sign Up</a>
                <a href="about_btsch.php">About Us</a>
                <a href="contacts_btsch.php">Contact</a>
                <a href="privacy_policy_btsch.php">Privacy Policy</a>
            </div>
            
            <p>&copy; <?php echo date('Y'); ?> BrazyTech Supply Chain Hub. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>