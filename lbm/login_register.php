<?php
session_start();
require_once 'configure.php';

if (isset($_POST['register'])) {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $address = $_POST['address'];
    $mob_no = $_POST['mob_no'];
    $email_id = $_POST['email_id'];
    $role = $_POST['role'];
    $password = password_hash($_POST[''], PASSWORD_DEFAULT);

    $data ="INSERT INTO member_db (first_name, last_name, address, mob_no, email_id, password, role) 
             VALUES ('$first_name', '$last_name', '$address', '$mob_no', '$email_id', '$password','$role')";

    $checkmob_no = $conn->query("SELECT mob_no FROM member_db WHERE mob_no = '$mob_no'");
    if ($checkmob_no->num_rows > 0) {
        $_SESSION['register_error'] = 'Mobile number is already registered';
        $_SESSION['active_form'] = 'register';
    } else {
        $conn->query($data);
        echo "<script>alert('error')</script>";
        header("Location: login.php");
        exit();
    }
}

if (isset($_POST['login'])) {
    $mob_no = $_POST['mob_no'];
    $password = $_POST['pasword'];

    $result = $conn->query("SELECT * FROM member_db WHERE mob_no = '$mob_no'");
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password,$user['password'])) {
            $_SESSION['name'] = $user['name'];
            $_SESSION['mob_no'] = $user['mob_no'];

            if ($user['role']=== 'admin') {
                header("Location: admin_page.php");
            }
            else{
                header("Location: user_dashboard.php");
            }
            
            exit();
        }
    }
    $_SESSION['login_error'] = 'Incorrect mobile number or DOB';
    $_SESSION['active_form'] = 'login';
    header("Location: login.php");
    exit();
}
?>