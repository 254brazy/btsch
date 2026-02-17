<?php
require 'secrete.php';

$user=$conn->prepare("SELECT token FROM users WHERE token=? AND username=?");
$user->bind_param("ss",$_SESSION['csrftoken'],$_SESSION['username']);
$user->execute();
$userresult=$user->get_result();
$userrow=$userresult->fetch_assoc();

$token=$userrow['token'];


if($token !== $_SESSION['csrftoken']){
    header("Location: expired.php");
}elseif($token===null){
    header("Location: company_login.php");
}