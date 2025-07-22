<?php
session_start();

$errors = [
    'login' => $_SESSION['login_error'] ?? '',
    'register' => $_SESSION['register_error'] ?? ''
];

$activeForm = $_SESSION['active_form'] ?? 'login';

session_unset();

function showError($error) {
    return !empty($error) ? "<p class='error-message'>$error</p>" : '';
}

function isActiveForm($formName, $activeForm) {
    return $formName === $activeForm ? 'active' : '';
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>login</title>
    <link rel="stylesheet" href="/lbm/style.css">
</head>
<body>
    <img class="bg" src="bg1.jpg" alt="library" >

    <div class="container">
        <div class="form-box <?= isActiveForm('login',$activeForm);?>" id="login-form">
            <form action = "login_register.php" method="post">
                <h2>Login</h2>
                <?= showError($errors['login']); ?>
                <input type="mob_no" name="mob_no" placeholder="Mobile No." required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" name="login">Login</button>
                <p> Don't have account? <a href="#" onclick="showForm('register-form')">Register</a></p>
                <p>------------------------------------------------------------</p>
                <p class="comu">Join Our Community</p>
                <br>
                <div class="icon">
                    <ion-icon name="logo-facebook"></ion-icon>
                    <ion-icon name="logo-instagram"></ion-icon>
                    <ion-icon name="share-social-outline"></ion-icon>
                </div>
            </form>
        </div>


        <div class="form-box <?= isActiveForm('register',$activeForm);?>" id="register-form">
            <form action="login_register.php" method="post">
                <h2>Register</h2>
                <?= showError($errors['register']); ?>
                
                <input type="first_name" name="first_name" placeholder="First Name" required>
                <input type="last_name" name="last_name" placeholder="Last Name" required>
                <input type="mob_no" name="mob_no" placeholder="Mobile No." required>
                <input type="email_id" name="email_id" placeholder="Email Id" required>
                <input type="address" name="address" placeholder="Address" required>
                <input type="password" name="password" placeholder="password" required>
                 <select name="role" required>
                    <option value="">---Select Role---</option>
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select> 
                <button type="submit" name="register">Register</button>
                <p> Already have account? <a href="#"  onclick="showForm('login-form')" >login</a></p>
                <p>------------------------------------------------------------</p>
                <p class="comu">Join Our Community</p>
                 <br>
                <div class="icon">
                    <ion-icon name="logo-facebook"></ion-icon>
                    <ion-icon name="logo-instagram"></ion-icon>
                    <ion-icon name="share-social-outline"></ion-icon>
                </div>
            </form>
        </div>



    </div>
    <script src="sc.js"></script>
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

    <!-- INSERT INTO `member_db` (`member_id`, `first_name`, `last_name`, `address`, `mob_no`, `email_id`, 
     `dob`) VALUES ('520101', 'Aditya', 'Anand', 'Pune,Maharastra', '6205636556', 'pathak.addi@gmail.com', 
     '06022003') -->
</body>
</html>