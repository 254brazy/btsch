<?php
session_start();
require 'secrete.php';

if(!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$jobid = intval($_GET['jobid']);
$company = $_SESSION['company'];
$branch = $_SESSION['branch'];

$response = ['success' => false];

try {
    // Get job details
    $job_query = $conn->prepare("SELECT id, item, model, serial, customer_name1 FROM tech_jobs WHERE id = ? AND company = ? AND branch = ?");
    $job_query->bind_param("iss", $jobid, $company, $branch);
    $job_query->execute();
    $job_result = $job_query->get_result();
    
    if($job_result->num_rows > 0) {
        $response['job'] = $job_result->fetch_assoc();
        
        // Get parts
        $parts_query = $conn->prepare("SELECT part, quantity, price, (price * quantity) as line_total FROM tech_job_parts WHERE jobid = ? AND company = ? AND branch = ? AND status IN ('allocated', 'assigned', 'approved')");
        $parts_query->bind_param("iss", $jobid, $company, $branch);
        $parts_query->execute();
        $parts_result = $parts_query->get_result();
        $response['parts'] = $parts_result->fetch_all(MYSQLI_ASSOC);
        
        // Get services
        $services_query = $conn->prepare("SELECT service, price FROM tech_job_services WHERE company = ? AND branch = ? AND jobid = ? AND status = 'allocated'");
        $services_query->bind_param("ssi", $company, $branch, $jobid);
        $services_query->execute();
        $services_result = $services_query->get_result();
        $response['services'] = $services_result->fetch_all(MYSQLI_ASSOC);
        
        $response['success'] = true;
    } else {
        $response['message'] = 'Job not found';
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>