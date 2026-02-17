<?php
require_once 'secrete.php';
session_start();
if(!isset($_SESSION['customerid']) && ($_SESSION['customerbranch'])){
    header("Location:tech_customer_login.php");
}

if($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: dashboard.php');
    exit;
}
$branch=$_SESSION['customerbranch'];
$invoice_id = intval($_POST['invoice_id']);
$amount = floatval($_POST['amount']);
$mode = $conn->real_escape_string($_POST['mode']);
$payment_type = $conn->real_escape_string($_POST['payment_type']);

if($_POST['mode']==='cash'){
    $reference='CASH-'.time().'-'.random_int(1000,9999);
}else{
    $reference=$conn->real_escape_string($_POST['reference']);
}

// Verify invoice belongs to customer
$verify_query = "SELECT i.*, j.customer_id 
                FROM tech_job_invoices i 
                JOIN tech_jobs j ON i.jobid = j.id 
                WHERE i.id = ? AND j.customer_id = ?";
$stmt = $conn->prepare($verify_query);
$stmt->bind_param("ii", $invoice_id, $_SESSION['customerid']);
$stmt->execute();
$verify_result = $stmt->get_result();

if($verify_result->num_rows == 0) {
    die('Invoice not found');
}

$invoice = $verify_result->fetch_assoc();

// Insert payment
$payment_query = "INSERT INTO tech_job_customerportal_payments 
                 (jobid, invoiceid, item, paid,  mode, paymenttype, 
                  company, branch, agent, status,refrence) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?,?, 'pending approval',?)";
$stmt = $conn->prepare($payment_query);

$new_balance = max(0, $invoice['balance'] - $amount);
$stmt->bind_param("iisissssss", 
    $invoice['jobid'],
    $invoice_id,
    $invoice['item'],
    $amount,
    $mode,
    $payment_type,
    $_SESSION['company'],
    $branch,
    $_SESSION['customer_full_name'],
    $reference
);
if ($stmt->execute()){
     header('Location: tech_customer_dashboard.php?payment=success');
}else{
    header('Location: tech_customer_dashboard.php?payment=error');
}

/*if($stmt->execute()) {
    // Update invoice balance
    $update_invoice_query = "UPDATE tech_job_invoices 
                            SET balance = ?, total_paid = total_paid + ? 
                            WHERE id = ?";
    $stmt = $conn->prepare($update_invoice_query);
    $stmt->bind_param("dii", $new_balance, $amount, $invoice_id);
    $stmt->execute();
    
    // If balance is 0, update job status to completed
    if($new_balance == 0) {
        $update_job_query = "UPDATE tech_jobs SET status = 'completed' WHERE id = ?";
        $stmt = $conn->prepare($update_job_query);
        $stmt->bind_param("i", $invoice['jobid']);
        $stmt->execute();
    }
    
    header('Location: dashboard.php?payment=success');
} else {
    header('Location: dashboard.php?payment=error');
}*/
?>