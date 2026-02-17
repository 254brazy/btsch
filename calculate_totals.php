<?php
session_start();
require 'secrete.php';

if(!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jobid = intval($_POST['jobid']);
    $discount = intval($_POST['discount'] ?? 0);
    $tax = floatval($_POST['tax'] ?? 0);
    $company = $_SESSION['company'];
    $branch = $_SESSION['branch'];
    
    $response = ['success' => false];
    
    try {
        // Calculate parts subtotal
        $parts_query = $conn->prepare("SELECT SUM(price * quantity) as total FROM tech_job_parts WHERE jobid = ? AND company = ? AND branch = ? AND status IN ('allocated', 'assigned', 'approved')");
        $parts_query->bind_param("iss", $jobid, $company, $branch);
        $parts_query->execute();
        $parts_result = $parts_query->get_result();
        $parts_row = $parts_result->fetch_assoc();
        $parts_subtotal = $parts_row['total'] ?? 0;
        
        // Calculate services subtotal
        $services_query = $conn->prepare("SELECT SUM(service_price) as total FROM techservices WHERE company = ? AND branch = ? AND company_id = ? AND service_status = 'active'");
        $services_query->bind_param("ssi", $company, $branch, $jobid);
        $services_query->execute();
        $services_result = $services_query->get_result();
        $services_row = $services_result->fetch_assoc();
        $service_subtotal = $services_row['total'] ?? 0;
        
        // Calculate totals
        $subtotal = $parts_subtotal + $service_subtotal;
        $tax_amount = ($subtotal * $tax) / 100;
        $total_before_discount = $subtotal + $tax_amount;
        $final_total = $total_before_discount - $discount;
        
        $response['success'] = true;
        $response['totals'] = [
            'parts_subtotal' => $parts_subtotal,
            'service_subtotal' => $service_subtotal,
            'subtotal' => $subtotal,
            'tax_amount' => $tax_amount,
            'final_total' => $final_total
        ];
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    echo json_encode($response);
}
?>

