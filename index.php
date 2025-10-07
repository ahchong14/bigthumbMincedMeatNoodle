<?php
session_start();
$error = '';
if(isset($_POST['login'])){
    $user = $_POST['username'];
    $pass = $_POST['password'];
    if($user==='myadmin' && $pass==='123'){
        $_SESSION['admin_logged_in'] = true;
        header('Location: order-manage.php');
        exit();
    }else{
        $error = 'Invalid credentials!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;400&display=swap" rel="stylesheet">
    <style>
        body {background: linear-gradient(135deg, #f8fafc 0%, #e0eafc 100%); min-height:100vh; display:flex; align-items:center; justify-content:center; font-family:'Montserrat',Arial,sans-serif;}
        .login-box {background:#fff; border-radius:18px; box-shadow:0 4px 16px 0 rgba(31,38,135,0.10); padding:2.5rem 2rem; max-width:350px; width:100%; text-align:center;}
        .logo {width:70px; margin-bottom:1.2rem;}
        h2 {margin-bottom:1.5rem; color:#2d3a4b;}
        input[type=text],input[type=password]{width:90%;padding:0.7rem 0.5rem;margin:0.5rem 0 1rem 0;border-radius:8px;border:1px solid #ccc;font-size:1rem;}
        .btn-login{background:linear-gradient(90deg,#ffb347 0%,#ffcc33 100%);color:#222;font-weight:bold;border:none;border-radius:8px;padding:0.7rem 2.2rem;font-size:1.1rem;cursor:pointer;box-shadow:0 2px 8px rgba(255,204,51,0.10);transition:background 0.2s,transform 0.2s;}
        .btn-login:hover{background:linear-gradient(90deg,#ffcc33 0%,#ffb347 100%);transform:translateY(-2px) scale(1.04);}
        .error{color:#e74c3c;margin-bottom:1rem;}
    </style>
</head>
<body>
    <div class="login-box">
        <img src="../images/logos.png" alt="logo" class="logo">
        <h2>Admin Login</h2>
        <?php if($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
        <form method="post">
            <input type="text" name="username" placeholder="User ID" required><br>
            <input type="password" name="password" placeholder="Password" required><br>
            <button type="submit" name="login" class="btn-login">Login</button>
        </form>
    </div>
</body>
</html>