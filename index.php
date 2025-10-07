<?php
session_start();
include('lang.php');
// 选择用餐方式
if(isset($_POST['dine_type'])){
    $_SESSION['dine_type'] = $_POST['dine_type'];
    header('Location: menu.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $t['welcome']; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            font-family: 'Montserrat', Arial, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e0eafc 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .main-box {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
            padding: 3rem 2rem 2rem 2rem;
            max-width: 400px;
            width: 100%;
            text-align: center;
        }
        .logo {
            width: 90px;
            margin-bottom: 1.5rem;
        }
        h1 {
            font-size: 2.2rem;
            margin-bottom: 1.2rem;
            color: #2d3a4b;
        }
        .choose-btn {
            font-size: 1.3rem;
            padding: 1rem 2.5rem;
            margin: 1rem 0.5rem;
            border: none;
            border-radius: 8px;
            background: linear-gradient(90deg, #ffb347 0%, #ffcc33 100%);
            color: #222;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(255, 204, 51, 0.15);
            transition: background 0.2s, transform 0.2s;
        }
        .choose-btn:hover {
            background: linear-gradient(90deg, #ffcc33 0%, #ffb347 100%);
            transform: translateY(-2px) scale(1.04);
        }
        .lang-btn {
            margin-top: 2rem;
            font-size: 1.1rem;
        }
        .lang-btn a {
            color: #2d3a4b;
            text-decoration: none;
            margin: 0 0.5rem;
            font-weight: bold;
        }
        .lang-btn a:hover {
            text-decoration: underline;
        }
        .dine-type-select {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin: 2rem 0;
        }
        .dine-type-select form {
            display: inline;
        }
        .dine-type-select button {
            font-size: 1.2rem;
            padding: 1.2rem 2.5rem;
            border-radius: 12px;
            border: none;
            background: #ffe082;
            display: flex;
            align-items: center;
            gap: 0.7rem;
            box-shadow: 0 2px 8px rgba(255, 204, 51, 0.10);
            cursor: pointer;
            transition: background 0.2s;
        }
        .dine-type-select button:hover {
            background: #ffe9b3;
        }
        .dine-type-select button span {
            font-size: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="main-box">
        <img src="images/logos.png" alt="logo" class="logo">
        <h1><?php echo $t['welcome']; ?></h1>
        <div class="dine-type-select">
            <form method="post" action="">
                <input type="hidden" name="dine_type" value="dine_in">
                <button type="submit">
                    <span>🍽</span>
                    <span><?php echo $t['dine_in']; ?></span>
                </button>
            </form>
            <form method="post" action="">
                <input type="hidden" name="dine_type" value="take_away">
                <button type="submit">
                    <span>🛍</span>
                    <span><?php echo $t['take_away']; ?></span>
                </button>
            </form>
        </div>
        <div class="lang-btn">
            <a href="?lang=en">English</a> | <a href="?lang=zh">中文</a>
        </div>
    </div>
</body>
</html>