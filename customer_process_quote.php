<?php
require_once 'secrete.php';
session_start();

$customer_id = $_SESSION['customerid'];


if(isset($_GET['action']) && isset($_GET['quote_id'])) {
    $quote_id = intval($_GET['quote_id']);
    $action = $_GET['action'];
    $company = $_SESSION['company'];
    $branch = $_SESSION['customerbranch'];

   

    // Verify the quote belongs to this customer
$verify_query = "SELECT q.id 
                FROM tech_job_prequotes q 
                JOIN tech_jobs j ON q.jobid = j.id 
                WHERE q.id = ? AND j.customer_id = ?";
$stmt = $conn->prepare($verify_query);
$stmt->bind_param("ii", $quote_id, $customer_id);
$stmt->execute();
$verify_result = $stmt->get_result();

if($verify_result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Quote not found']);
    exit;
}
    
    switch($action) {
        case 'decline':
            $deactivate_stmt = $conn->prepare("UPDATE tech_job_prequotes SET status = 'declined' WHERE id = ? AND company = ? AND branch=?");
            $deactivate_stmt->bind_param("iss", $quote_id, $_SESSION['company'],$_SESSION['customerbranch']);

            
            if($deactivate_stmt->execute()){  // FIXED: Removed semicolon after if statement
                $message = "<div class='message success'>quote declined</div>";
            }
            break;
            
        case 'approve':
            $activate_stmt = $conn->prepare("UPDATE tech_job_prequotes SET status = 'approved' WHERE id = ? AND company = ? AND branch=?");
            $activate_stmt->bind_param("iss", $quote_id, $_SESSION['company'],$_SESSION['customerbranch']);

            
            if($activate_stmt->execute()) {

                $invoice_query=$conn->prepare("SELECT id,jobid,item,parts_subtotal,service_subtotal,total FROM tech_job_prequotes WHERE id=? AND company=? AND branch=?");
                $invoice_query->bind_param("iss",$quote_id,$_SESSION['company'],$_SESSION['customerbranch']);
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
                $invoice->bind_param("iisiiissss",$balance, $jobquoteid,$quoteitem,$quotepartscharges,$quoteservicecharges,$quotetotalcharge,$_SESSION['company'],$_SESSION['customerbranch'],$_SESSION['username'],$quotestatus);
    
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
?>