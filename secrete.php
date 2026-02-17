<?php
$server="localhost";
$user="root";
$password="";
$database="supplychain";

$conn= new mysqli($server,$user,$password,$database);
if($conn->connect_error){
    die (
        "There was a problem Reaching Our Servers, please try again later"
    );
}
?>