<?php 
 if(session_status()===PHP_SESSION_NONE){
    session_start();
 }
require 'secrete.php';

$user=$conn->prepare("SELECT fname,sname,company,location,token,tel,email,dealers,reg,id,oname FROM users WHERE token=? AND username=?");
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



?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Dashboard</title>
   
</head>
<body>
<div>
    <div><h3>Welcome, <?php 
    if($token=null || $userrow['fname']===null || $userrow['company']===null){
    header("Location: company_login.php");
}
    echo $userrow['fname'];
    echo   " Company: " ;
     echo $userrow['company'];?></h3> </div>
    
</div>
<aside>
    <nav>
        <ul>
            <div>
                <li><a href="company_branches.php">Branches</a></li>
                <li><a href="staff.php"></a>Staff</li>
                <li><a href="attendance.php">Attendance</a></li>
                <li><a href=""></a></li>
            </div>
        </ul>
    </nav>
</aside>
<div>
    <h3> Edit company registered details</h3>
    <h4>company details</h4>
    <table style="background-color:grey; border:1px; cell-border:1px black;">
        
        <tr style="border:2px black;">
        <th>ID</th>
        <th>First Name</th>
        <th>Second Name</th>
        <th>Other Name</th>
        <th>Company</th>
        <th>Dealers</th>
        <th>Location</th>
        <th>Registration</th>
        <th>Tel</th>
        <th>Email</th>
        
        </tr>
                <td><?php echo $userrow['id'];?></td>
                <td><?php echo$userrow['fname'];?></td>
                <td><?php echo $userrow['sname'];?></td>
                <td><?php echo $userrow['oname'];?></td>
                <td><?php echo $userrow['company'];?></td>
                <td><?php echo $userrow['dealers'];?></td>
                <td><?php echo $userrow['location'];?></td>
                <td><?php echo $userrow['reg'];?></td>
                <td><?php echo $userrow['tel'];?></td>
                <td><?php echo $userrow['email'];?></td>
                
        
        
    </table>
    <a href="edit_company.php?id=<?php echo $userrow['id']?>"><button type="button" name="edit_company"  >edit</button></a>
    <button type="button" name="edit_company"></button>
</div>
<div id="EditModal" style="display:none;">
<h3>editing wil happen here</h3>
</div>


   
</body>
</html>
