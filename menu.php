<?php
session_start();

// 设置默认时区
date_default_timezone_set('Asia/Kuala_Lumpur');

// 错误报告（开发环境）
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 检查就餐类型是否设置
if (!isset($_SESSION['dine_type'])) {
    header('Location: index.php');
    exit();
}

// 包含语言文件
if (!file_exists('lang.php')) {
    die("Language file not found");
}
include('lang.php');

// 确保$t变量已定义
if (!isset($t)) {
    $t = $lang_data['zh'] ?? []; // 默认使用中文
}

// 数据库连接处理
$conn = null;
$menu = [];
$config_path = dirname(__DIR__) . '/config/constants.php';

try {
    if (file_exists($config_path)) {
        include($config_path);
    } else {
        // 默认配置
        $db_host = "localhost";
        $db_user = "root";
        $db_pass = "";
        $db_name = "bigfingerMee";
    }

    // 尝试建立数据库连接
    if (isset($db_host) && isset($db_user)) {
        $conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
        if (!$conn) {
            throw new Exception("Database connection failed: " . mysqli_connect_error());
        }
        mysqli_set_charset($conn, "utf8");
    }
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    // 不终止执行，使用空菜单继续
}

// 从数据库获取菜单和可用性状态
if ($conn) {
    try {
        $sql = "SELECT fa.id, fa.food_name, fa.is_available, fa.unavailable_reason, 
                       CASE 
                         WHEN fa.food_name = 'Signature Noodle' THEN 9.8
                         WHEN fa.food_name = 'Fishball Noodle' THEN 4.5
                         WHEN fa.food_name = 'Minced Meat Noodle' THEN 4.5
                         WHEN fa.food_name = 'Fishball Minced Meat Noodle' THEN 5.5
                         WHEN fa.food_name = 'Fishball Soup' THEN 4.5
                         WHEN fa.food_name = 'Curry Chicken Noodle' THEN 5.0
                         WHEN fa.food_name = 'Curry Fishball Noodle' THEN 5.0
                         WHEN fa.food_name = 'Pork Ribs Noodle' THEN 6.0
                       END as price,
                       CASE 
                         WHEN fa.food_name = 'Signature Noodle' THEN 'images/food/signature_noodle.jpg'
                         WHEN fa.food_name = 'Fishball Noodle' THEN 'images/food/fishball_noodles.png'
                         WHEN fa.food_name = 'Minced Meat Noodle' THEN 'images/food/minced_meat_noodle.png'
                         WHEN fa.food_name = 'Fishball Minced Meat Noodle' THEN 'images/food/Fishball_Minced_Meat_Noodle.png'
                         WHEN fa.food_name = 'Fishball Soup' THEN 'images/food/fishball_soup.png'
                         WHEN fa.food_name = 'Curry Chicken Noodle' THEN 'images/food/curry_chicken_noodle.jpg'
                         WHEN fa.food_name = 'Curry Fishball Noodle' THEN 'images/food/curry_fish_ball_noodle.jpg'
                         WHEN fa.food_name = 'Pork Ribs Noodle' THEN 'images/food/pork_ribs_noodle.jpg'
                       END as img
                FROM food_availability fa
                ORDER BY fa.id";
        
        $result = mysqli_query($conn, $sql);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $menu[] = [
                    'id' => $row['id'],
                    'db_food_name' => $row['food_name'],
                    'title' => getTranslatedTitle($row['food_name'], $t),
                    'price' => $row['price'],
                    'img' => $row['img'],
                    'available' => (bool)$row['is_available'],
                    'unavailable_reason' => $row['unavailable_reason']
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Menu query error: " . $e->getMessage());
    }
}

// 标题翻译映射函数
function getTranslatedTitle($db_food_name, $t) {
    $mapping = [
        'Signature Noodle' => $t['signature_noodle'] ?? 'Signature Noodle',
        'Fishball Noodle' => $t['fishball_noodle'] ?? 'Fishball Noodle',
        'Minced Meat Noodle' => $t['minced_meat_noodle'] ?? 'Minced Meat Noodle',
        'Fishball Minced Meat Noodle' => $t['fishball_minced_noodle'] ?? 'Fishball Minced Meat Noodle',
        'Fishball Soup' => $t['fishball_soup'] ?? 'Fishball Soup',
        'Curry Chicken Noodle' => $t['curry_chicken_noodle'] ?? 'Curry Chicken Noodle',
        'Curry Fishball Noodle' => $t['curry_fishball_noodle'] ?? 'Curry Fishball Noodle',
        'Pork Ribs Noodle' => $t['pork_ribs_noodle'] ?? 'Pork Ribs Noodle'
    ];
    return $mapping[$db_food_name] ?? $db_food_name;
}

// 定义选项数据
$noodle_types = [
    'yellow_noodle' => $t['yellow_noodle'] ?? 'Yellow Noodle',
    'laoshu_fen' => $t['laoshu_fen'] ?? 'Laoshu Fen',
    'mee_kia' => $t['mee_kia'] ?? 'Mee Kia',
    'mee_pok' => $t['mee_pok'] ?? 'Mee Pok',
    'kway_teow' => $t['kway_teow'] ?? 'Kway Teow',
    'bee_hoon mixed_yellow_noodle' => $t['bee_hoon mixed_yellow_noodle'] ?? 'Bee Hoon + Yellow Noodle',
    'kway_teow mixed_yellow_noodle' => $t['kway_teow mixed_yellow_noodle'] ?? 'Kway Teow + Yellow Noodle',
];

// 添加打包选项翻译
$takeaway_options = [
    'self_container' => $t['self_container'] ?? 'Self Container',
    'restaurant_container' => $t['restaurant_container'] ?? 'Restaurant Container',
    'plastic_bag' => $t['plastic_bag'] ?? 'Plastic Bag'
];

$is_takeaway = isset($_SESSION['dine_type']) && $_SESSION['dine_type'] === 'take_away';
$takeaway_fee = 0.30;

// 获取当前语言
$current_lang = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : 'zh';

// 获取商品可用性数据
$food_availability = [];
if ($conn) {
    try {
        $sql_availability = "SELECT * FROM food_availability ORDER BY food_name";
        $res_availability = mysqli_query($conn, $sql_availability);
        if ($res_availability) {
            while ($row = mysqli_fetch_assoc($res_availability)) {
                $food_availability[$row['food_name']] = $row;
            }
        }
    } catch (Exception $e) {
        error_log("Food availability query error: " . $e->getMessage());
    }
}

// 关闭数据库连接
if ($conn) {
    mysqli_close($conn);
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_lang, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($t['menu'] ?? 'Menu', ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars($t['welcome'] ?? 'Welcome', ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* 保持原有的CSS样式不变 */
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Montserrat', Arial, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e0eafc 100%);
        }
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.2rem 2rem 0.5rem 2rem;
        }
        .logo {
            width: 70px;
        }
        .lang-btn {
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
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        h1 {
            text-align: center;
            color: #2d3a4b;
            margin-bottom: 2rem;
        }
        .menu-list {
            display: flex;
            flex-wrap: wrap;
            gap: 2.5rem;
            justify-content: center;
        }
        .menu-item {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 4px 16px 0 rgba(31, 38, 135, 0.10);
            width: 270px;
            padding: 1.2rem 1.2rem 1.5rem 1.2rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: box-shadow 0.2s, transform 0.2s;
            position: relative;
        }
        .menu-item:hover {
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.18);
            transform: translateY(-4px) scale(1.03);
        }
        .menu-img {
            width: 130px;
            height: 130px;
            object-fit: cover;
            border-radius: 12px;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .menu-title {
            font-size: 1.2rem;
            font-weight: bold;
            color: #222;
            margin-bottom: 0.5rem;
            text-align: center;
        }
        .menu-price {
            color: #ff9800;
            font-size: 1.1rem;
            margin-bottom: 0.7rem;
        }
        form {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .order-btn {
            margin-top: 0.7rem;
            background: linear-gradient(90deg, #ffb347 0%, #ffcc33 100%);
            color: #222;
            font-weight: bold;
            border: none;
            border-radius: 8px;
            padding: 0.7rem 2.2rem;
            font-size: 1.1rem;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(255, 204, 51, 0.10);
            transition: background 0.2s, transform 0.2s;
        }
        .order-btn:hover {
            background: linear-gradient(90deg, #ffcc33 0%, #ffb347 100%);
            transform: translateY(-2px) scale(1.04);
        }
        .back-link {
            display: block;
            margin: 2rem auto 0 auto;
            text-align: center;
            color: #2d3a4b;
            text-decoration: none;
            font-size: 1.1rem;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .qty-control {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0.2rem 0;
        }
        .qty-btn {
            width: 28px;
            height: 28px;
            border: none;
            background: #ffcc33;
            color: #222;
            font-size: 1.2rem;
            border-radius: 50%;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.2s;
        }
        .qty-btn:active {
            background: #ffb347;
        }
        .qty-num {
            width: 36px;
            text-align: center;
            font-size: 1.1rem;
            border: none;
            background: transparent;
        }
        .menu-item.unavailable {
            opacity: 0.7;
            position: relative;
            cursor: not-allowed;
        }
        .menu-item.unavailable::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.8);
            z-index: 1;
        }
        .unavailable-badge {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #e74c3c;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: bold;
            z-index: 2;
            text-align: center;
        }
        .unavailable-reason {
            position: absolute;
            bottom: 10px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(231, 76, 60, 0.9);
            color: white;
            padding: 0.3rem 0.7rem;
            border-radius: 5px;
            font-size: 0.9rem;
            z-index: 2;
            white-space: nowrap;
            max-width: 90%;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .menu-item.unavailable .order-btn,
        .menu-item.unavailable .qty-control {
            pointer-events: none;
            opacity: 0.5;
        }
        /* 购物车样式保持不变 */
        .cart-sidebar {
            position: fixed;
            top: 90px;
            right: 0;
            width: 320px;
            max-width: 90vw;
            background: #fff;
            box-shadow: -4px 0 32px rgba(31,38,135,0.15);
            border-radius: 20px 0 0 20px;
            padding: 1.2rem 1.2rem 1.5rem 1.2rem;
            z-index: 100;
            min-height: 180px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            transition: width 0.2s, right 0.2s, box-shadow 0.2s;
            border: 1px solid #f3e7c9;
        }
        .cart-sidebar.collapsed {
            width: 48px;
            min-width: 48px;
            padding: 0.5rem 0.2rem;
            overflow: visible;
            align-items: center;
            box-shadow: -2px 0 8px rgba(31,38,135,0.07);
        }
        .cart-sidebar.collapsed > *:not(.cart-toggle-btn) {
            display: none !important;
        }
        .cart-toggle-btn {
            position: absolute;
            left: -36px;
            top: 18px;
            width: 36px;
            height: 36px;
            background: #ffe082;
            border-radius: 50%;
            border: none;
            color: #222;
            font-size: 1.3rem;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(255,204,51,0.10);
            z-index: 101;
            transition: left 0.2s, background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .cart-toggle-btn:hover {
            background: #ffe9b3;
        }
        .cart-sidebar.collapsed .cart-toggle-btn {
            left: 6px;
            top: 8px;
        }
        .cart-title {
            font-size: 1.15rem;
            font-weight: bold;
            margin-bottom: 0.7rem;
            color: #2d3a4b;
            width: 100%;
        }
        .cart-list {
            flex: 1;
            overflow-y: auto;
            margin-bottom: 0.7rem;
            width: 100%;
            max-height: 50vh;
        }
        .cart-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 0.3rem;
            transition: background 0.15s;
            border-radius: 10px;
        }
        .cart-item:hover {
            background: #f9f6ee;
        }
        .cart-item-title {
            flex: 1;
            font-size: 1rem;
        }
        .cart-item-qty {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        .cart-total {
            font-weight: bold;
            color: #ff9800;
            margin-bottom: 0.7rem;
            text-align: right;
            width: 100%;
        }
        .cart-actions {
            display: flex;
            gap: 0.7rem;
            justify-content: flex-end;
            width: 100%;
        }
        .cart-btn {
            background: #ffe082;
            color: #222;
            border: none;
            border-radius: 10px;
            padding: 0.5rem 1.2rem;
            font-size: 1rem;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.2s, box-shadow 0.2s;
            box-shadow: 0 2px 8px rgba(255,204,51,0.07);
            min-width: 120px;
            text-align: center;
        }
        .cart-btn:hover {
            background: #ffecb3;
            box-shadow: 0 4px 16px rgba(255,204,51,0.13);
        }
        .cart-empty-msg {
            text-align: center;
            color: #888;
            font-style: italic;
            padding: 1rem;
        }
        @media (max-width: 900px) {
            .cart-sidebar {
                width: 98vw;
                left: 0;
                right: 0;
                border-radius: 0 0 20px 20px;
                top: unset;
                bottom: 0;
                margin: 0 auto;
                align-items: stretch;
            }
            .cart-toggle-btn {
                left: unset;
                right: 18px;
                top: -36px;
            }
            .cart-sidebar.collapsed .cart-toggle-btn {
                left: unset;
                right: 8px;
                top: 8px;
            }
        }
        @media (max-width: 600px) {
            .cart-sidebar {
                padding: 0.7rem 0.5rem 1rem 0.5rem;
            }
            .menu-list {
                gap: 1.5rem;
            }
            .menu-item {
                width: 250px;
            }
        }
    </style>
</head>
<body>
    <div class="topbar">
        <img src="images/logos.png" alt="Logo" class="logo">
        <div style="display:flex;align-items:center;gap:1.5rem;">
            <div class="lang-btn">
                <a href="?lang=en">English</a> | <a href="?lang=zh">中文</a>
            </div>
            <a href="admin/index.php" class="order-btn" style="padding:0.4rem 1.2rem;font-size:1rem;">Admin</a>
        </div>
    </div>
    <div class="container">
        <h1><?php echo htmlspecialchars($t['menu'] ?? 'Menu', ENT_QUOTES, 'UTF-8'); ?></h1>
        <div class="menu-list" id="menu-list">
        <?php foreach($menu as $item): ?>
<div class="menu-item <?php echo !$item['available'] ? 'unavailable' : ''; ?>" data-id="<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>">
    <?php if(!$item['available']): ?>
        <div class="unavailable-badge"><?php echo htmlspecialchars($t['not_available'] ?? 'Not Available', ENT_QUOTES, 'UTF-8'); ?></div>
        <?php if(!empty($item['unavailable_reason'])): ?>
            <div class="unavailable-reason" title="<?php echo htmlspecialchars($item['unavailable_reason'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($item['unavailable_reason'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
    <?php endif; ?>
    <img src="<?php echo htmlspecialchars($item['img'], ENT_QUOTES, 'UTF-8'); ?>" class="menu-img" alt="<?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy">
    <div class="menu-title"><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="menu-price"><?php echo htmlspecialchars($t['price'] ?? 'Price', ENT_QUOTES, 'UTF-8'); ?>: SGD $<?php echo number_format($item['price'],2); ?></div>
    <form onsubmit="return false;">
        <div class="qty-control">
            <button type="button" class="qty-btn" onclick="changeQty(<?php echo $item['id']; ?>, -1)">-</button>
            <span class="qty-num" id="qty-<?php echo $item['id']; ?>">0</span>
            <button type="button" class="qty-btn" onclick="changeQty(<?php echo $item['id']; ?>, 1)">+</button>
        </div>
        <button type="button" class="order-btn" onclick="goChooseOptions(<?php echo $item['id']; ?>)">
            <span class="add-btn-text"><?php echo htmlspecialchars($t['customize'] ?? 'Add', ENT_QUOTES, 'UTF-8'); ?></span>
        </button>
    </form>
</div>
<?php endforeach; ?>
        </div>
        <a href="index.php" class="back-link">&laquo; <?php echo htmlspecialchars($t['back_menu'] ?? 'Back to Menu', ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <!-- 右侧浮窗购物车 -->
    <div class="cart-sidebar collapsed" id="cart-sidebar">
        <button class="cart-toggle-btn" id="cart-toggle-btn" title="<?php echo htmlspecialchars($t['toggle_cart'] ?? 'Toggle Cart', ENT_QUOTES, 'UTF-8'); ?>" onclick="toggleCartSidebar()">&#9776;</button>
        <div class="cart-title"><?php echo htmlspecialchars($t['your_cart'] ?? 'Your Cart', ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="cart-list" id="cart-list">
            <div class="cart-empty-msg"><?php echo htmlspecialchars($t['cart_empty'] ?? 'Cart is empty', ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <div class="cart-total" id="cart-total"></div>
        <?php if ($is_takeaway): ?>
        <div style="color:#e67e22;font-size:0.98rem;margin-bottom:0.5rem;">
            * <?php echo htmlspecialchars($t['takeaway_fee_note'] ?? 'Take away: +SGD $0.30/box (only for restaurant container, Signature Noodle is free)', ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <?php endif; ?>
        <div class="cart-actions">
            <button class="cart-btn" onclick="clearCart()"><?php echo htmlspecialchars($t['clear_cart'] ?? 'Clear Cart', ENT_QUOTES, 'UTF-8'); ?></button>
            <form id="cart-form" method="post" action="place-order.php" style="display:inline;">
                <input type="hidden" name="cart_data" id="cart-data-input">
                <button type="button" class="cart-btn" onclick="submitOrder()" id="submit-btn"><?php echo htmlspecialchars($t['confirm_order'] ?? 'Confirm Order', ENT_QUOTES, 'UTF-8'); ?></button>
            </form>
        </div>
    </div>
    <script>
    // 菜单数据
    const menu = <?php echo json_encode($menu, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;
    const noodleTypes = <?php echo json_encode($noodle_types, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;
    const takeawayOptions = <?php echo json_encode($takeaway_options, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;
    let cart = [];
    let submitting = false;

    // 精确计算价格的函数
    function calculateItemPrice(item, menuItem) {
        // 基础价格
        let price = parseFloat(menuItem.price) || 0;
        
        // 附加项价格
        if (item.addNoodle) price += 1.0;
        if (item.addIngredient) price += 2.0;
        if (item.addPrawn) price += 3.0;
        if (item.addPorkRibs) price += 3.0;
        if (item.addMeatPlattie) price += 3.0;
        
        // 返回精确到2位小数的价格
        return parseFloat(price.toFixed(2));
    }

    // 计算打包费
    function calculateTakeawayFee(item, menuItem, qty) {
        const takeawayFee = <?php echo $is_takeaway ? $takeaway_fee : 0; ?>;
        let itemTakeawayFee = 0;
        
        if (takeawayFee > 0 && 
            item.takeaway_option === 'restaurant_container' && 
            menuItem.db_food_name !== 'Signature Noodle') {
            itemTakeawayFee = takeawayFee * qty;
        }
        
        return parseFloat(itemTakeawayFee.toFixed(2));
    }

    function changeQty(id, delta) {
        const menuItem = document.querySelector(`.menu-item[data-id="${id}"]`);
        if(menuItem.classList.contains('unavailable')) {
            alert('<?php echo htmlspecialchars($t['item_not_available'] ?? "This item is currently not available", ENT_QUOTES, 'UTF-8'); ?>');
            return;
        }
        
        const qtySpan = document.getElementById('qty-' + id);
        let qty = parseInt(qtySpan.textContent) || 0;
        qty += delta;
        if (qty < 0) qty = 0;
        qtySpan.textContent = qty;
    }

    function goChooseOptions(id) {
        const menuItem = document.querySelector(`.menu-item[data-id="${id}"]`);
        if(menuItem.classList.contains('unavailable')) {
            alert('<?php echo htmlspecialchars($t['item_not_available'] ?? "This item is currently not available", ENT_QUOTES, 'UTF-8'); ?>');
            return;
        }
        
        const qty = parseInt(document.getElementById('qty-' + id).textContent) || 0;
        if (qty <= 0) {
            alert('<?php echo htmlspecialchars($t['please_select_qty'] ?? "Please select quantity", ENT_QUOTES, 'UTF-8'); ?>');
            return;
        }
        window.location.href = 'choose-options.php?food_id=' + id + '&qty=' + qty + '&lang=' + '<?php echo urlencode($current_lang); ?>';
    }

    function addToCart(itemData) {
        const existingIndex = cart.findIndex(item => 
            item.id === itemData.id &&
            item.noodleType === itemData.noodleType &&
            item.addNoodle === itemData.addNoodle &&
            item.addIngredient === itemData.addIngredient &&
            item.addPrawn === itemData.addPrawn &&
            item.addPorkRibs === itemData.addPorkRibs &&
            item.addMeatPlattie === itemData.addMeatPlattie &&
            item.soup_type === itemData.soup_type &&
            item.spicy === itemData.spicy &&
            item.vinegar === itemData.vinegar &&
            item.taste === itemData.taste &&
            item.takeaway_option === itemData.takeaway_option &&
            item.without_pork_liver === itemData.without_pork_liver &&
            item.without_minced_meat === itemData.without_minced_meat &&
            item.without_fishball === itemData.without_fishball &&
            item.without_sliced_meat === itemData.without_sliced_meat &&
            item.without_fried_shallots === itemData.without_fried_shallots &&
            item.without_ginger_onion === itemData.without_ginger_onion &&
            item.without_ketchup === itemData.without_ketchup &&
            item.remark === itemData.remark
        );
        
        if (existingIndex !== -1) {
            cart[existingIndex].qty += itemData.qty;
        } else {
            cart.push(itemData);
        }
        
        saveCart();
        renderCart();
        document.getElementById('qty-' + itemData.id).textContent = '0';
    }

    function renderCart() {
        const cartList = document.getElementById('cart-list');
        let total = 0;
        let totalTakeawayFee = 0;

        if (cart.length === 0) {
            cartList.innerHTML = '<div class="cart-empty-msg"><?php echo htmlspecialchars($t["cart_empty"] ?? "Cart is empty", ENT_QUOTES, 'UTF-8'); ?></div>';
            document.getElementById('cart-total').textContent = '';
            document.getElementById('cart-sidebar').classList.add('collapsed');
            return;
        }
        
        cartList.innerHTML = '';
        
        cart.forEach((item, idx) => {
            const menuItem = menu.find(m => m.id == item.id);
            if(!menuItem) return;

            // 使用新的价格计算函数
            const price = calculateItemPrice(item, menuItem);
            const itemTakeawayFee = calculateTakeawayFee(item, menuItem, item.qty);
            const itemTotal = (price * item.qty) + itemTakeawayFee;
            
            total += price * item.qty;
            totalTakeawayFee += itemTakeawayFee;

            let detail = [];
            if(item.noodleType && noodleTypes[item.noodleType]) detail.push(noodleTypes[item.noodleType]);
            if(item.addNoodle) detail.push('<?php echo htmlspecialchars($t["add_noodle"] ?? "Add Noodle", ENT_QUOTES, 'UTF-8'); ?>');
            if(item.addIngredient) detail.push('<?php echo htmlspecialchars($t["add_ingredient"] ?? "Add Ingredient", ENT_QUOTES, 'UTF-8'); ?>');
            if(item.addPrawn) detail.push('<?php echo htmlspecialchars($t["add_prawn"] ?? "Add Prawn", ENT_QUOTES, 'UTF-8'); ?>');
            if(item.addPorkRibs) detail.push('<?php echo htmlspecialchars($t["add_pork_ribs"] ?? "Add Pork Ribs", ENT_QUOTES, 'UTF-8'); ?>');
            if(item.addMeatPlattie) detail.push('<?php echo htmlspecialchars($t["add_meat_plattie"] ?? "Add Meat Plattie", ENT_QUOTES, 'UTF-8'); ?>');
            if(item.soup_type) detail.push(item.soup_type === 'dry' ? '<?php echo htmlspecialchars($t["dry"] ?? "Dry", ENT_QUOTES, 'UTF-8'); ?>' : '<?php echo htmlspecialchars($t["soup"] ?? "Soup", ENT_QUOTES, 'UTF-8'); ?>');
            if(item.spicy) {
                let spicyMap = {
                    no: '<?php echo htmlspecialchars($t["not_spicy"] ?? "Not Spicy", ENT_QUOTES, 'UTF-8'); ?>',
                    mild: '<?php echo htmlspecialchars($t["mild_spicy"] ?? "Mild Spicy", ENT_QUOTES, 'UTF-8'); ?>',
                    normal: '<?php echo htmlspecialchars($t["normal_spicy"] ?? "Normal Spicy", ENT_QUOTES, 'UTF-8'); ?>',
                    medium: '<?php echo htmlspecialchars($t["medium_spicy"] ?? "Medium Spicy", ENT_QUOTES, 'UTF-8'); ?>',
                    hot: '<?php echo htmlspecialchars($t["hot_spicy"] ?? "Hot Spicy", ENT_QUOTES, 'UTF-8'); ?>'
                };
                detail.push(spicyMap[item.spicy] || item.spicy);
            }
            if(item.vinegar) detail.push(item.vinegar === 'yes' ? '<?php echo htmlspecialchars($t["with_vinegar"] ?? "With Vinegar", ENT_QUOTES, 'UTF-8'); ?>' : '<?php echo htmlspecialchars($t["without_vinegar"] ?? "Without Vinegar", ENT_QUOTES, 'UTF-8'); ?>');
            if(item.taste) {
                let tasteMap = {
                    light: '<?php echo htmlspecialchars($t["light_taste"] ?? "Light Taste", ENT_QUOTES, 'UTF-8'); ?>',
                    normal: '<?php echo htmlspecialchars($t["normal_taste"] ?? "Normal Taste", ENT_QUOTES, 'UTF-8'); ?>',
                    strong: '<?php echo htmlspecialchars($t["strong_taste"] ?? "Strong Taste", ENT_QUOTES, 'UTF-8'); ?>'
                };
                detail.push(tasteMap[item.taste] || item.taste);
            }
            
            // 显示打包选项
            if(item.takeaway_option && takeawayOptions[item.takeaway_option]) {
                detail.push(takeawayOptions[item.takeaway_option]);
            }
            
            let noOptions = [];
            if(item.without_pork_liver) noOptions.push('<?php echo htmlspecialchars($t["without_pork_liver"] ?? "Without Pork Liver", ENT_QUOTES, 'UTF-8'); ?>');
            if(item.without_minced_meat) noOptions.push('<?php echo htmlspecialchars($t["without_minced_meat"] ?? "Without Minced Meat", ENT_QUOTES, 'UTF-8'); ?>');
            if(item.without_fishball) noOptions.push('<?php echo htmlspecialchars($t["without_fishball"] ?? "Without Fishball", ENT_QUOTES, 'UTF-8'); ?>');
            if(item.without_sliced_meat) noOptions.push('<?php echo htmlspecialchars($t["without_sliced_meat"] ?? "Without Sliced Meat", ENT_QUOTES, 'UTF-8'); ?>');
            if(item.without_fried_shallots) noOptions.push('<?php echo htmlspecialchars($t["without_fried_shallots"] ?? "Without Fried Shallots", ENT_QUOTES, 'UTF-8'); ?>');
            if(item.without_ginger_onion) noOptions.push('<?php echo htmlspecialchars($t["without_ginger_onion"] ?? "Without Ginger Onion", ENT_QUOTES, 'UTF-8'); ?>');
            if(item.without_ketchup) noOptions.push('<?php echo htmlspecialchars($t["without_ketchup"] ?? "Without Ketchup", ENT_QUOTES, 'UTF-8'); ?>');
            
            if(noOptions.length > 0) {
                detail = detail.concat(noOptions);
            }
            
            if(item.remark) detail.push('<?php echo htmlspecialchars($t["remark"] ?? "Remark", ENT_QUOTES, 'UTF-8'); ?>:' + item.remark);

            cartList.innerHTML += `
            <div class="cart-item">
                <div class="cart-item-title">
                    ${menuItem.title} <br>
                    <small>${detail.join(' / ')}</small>
                    ${(itemTakeawayFee > 0) ? `<br><span style='color:#e67e22;font-size:0.95em;'>+SGD $${itemTakeawayFee.toFixed(2)} (<?php echo htmlspecialchars($t["take_away"] ?? "Take away", ENT_QUOTES, 'UTF-8'); ?>)</span>` : ''}
                    ${(menuItem.db_food_name === 'Signature Noodle' && item.takeaway_option === 'restaurant_container') ? `<br><span style='color:#27ae60;font-size:0.95em;'><?php echo htmlspecialchars($t["no_takeaway_fee"] ?? "No takeaway fee", ENT_QUOTES, 'UTF-8'); ?></span>` : ''}
                </div>
                <div class="cart-item-qty">
                    <button class="qty-btn" onclick="cartChangeQty(${idx}, -1)">-</button>
                    <span>${item.qty}</span>
                    <button class="qty-btn" onclick="cartChangeQty(${idx}, 1)">+</button>
                    <button class="qty-btn" style="background:#eee;color:#888;" onclick="removeCartItem(${idx})">&times;</button>
                </div>
                <div style="width:60px;text-align:right;">SGD $${itemTotal.toFixed(2)}</div>
            </div>
            `;
        });

        let grandTotal = total + totalTakeawayFee;
        document.getElementById('cart-total').textContent = '<?php echo htmlspecialchars($t["total"] ?? "Total", ENT_QUOTES, 'UTF-8'); ?>: SGD $' + grandTotal.toFixed(2);
        document.getElementById('cart-sidebar').classList.remove('collapsed');
    }

    function cartChangeQty(idx, delta) {
        cart[idx].qty += delta;
        if(cart[idx].qty <= 0) {
            cart.splice(idx,1);
        }
        saveCart();
        renderCart();
    }
    
    function removeCartItem(idx) {
        cart.splice(idx,1);
        saveCart();
        renderCart();
    }
    
    function clearCart() {
        if (confirm('<?php echo htmlspecialchars($t["confirm_clear_cart"] ?? "Are you sure you want to clear the cart?", ENT_QUOTES, 'UTF-8'); ?>')) {
            cart = [];
            saveCart();
            renderCart();
        }
    }
    
    function toggleCartSidebar() {
        var sidebar = document.getElementById('cart-sidebar');
        sidebar.classList.toggle('collapsed');
    }
    
    function submitOrder() {
        if(submitting) return;
        if(cart.length===0) {
            alert('<?php echo htmlspecialchars($t["cart_empty"] ?? "Cart is empty", ENT_QUOTES, 'UTF-8'); ?>');
            return;
        }
        submitting = true;
        document.getElementById('submit-btn').disabled = true;
        document.getElementById('cart-data-input').value = JSON.stringify(cart);
        localStorage.removeItem('cart');
        document.getElementById('cart-form').submit();
    }
    
    function saveCart() {
        localStorage.setItem('cart', JSON.stringify(cart));
    }
    
    function loadCart() {
        const saved = localStorage.getItem('cart');
        if (saved) {
            try {
                cart = JSON.parse(saved);
            } catch(e) {
                console.error('Error parsing cart data:', e);
                cart = [];
            }
        }
        renderCart();
    }
    
    window.onload = function() {
        loadCart();
    };
</script>
</body>
</html>