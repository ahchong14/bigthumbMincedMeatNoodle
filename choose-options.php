<?php
session_start();

// 设置默认时区
date_default_timezone_set('Asia/Kuala_Lumpur');

// 错误报告（开发环境）
error_reporting(E_ALL);
ini_set('display_errors', 1);

$lang = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : 'zh';

// 包含语言文件
if (!file_exists('lang.php')) {
    die("Language file not found");
}
include('lang.php');

if (!isset($lang_data[$lang])) {
    $lang = 'zh'; // 默认中文
}
$t = $lang_data[$lang];

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

// 从数据库获取菜单数据
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
                $menu[$row['id']] = [
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
    'bee_hoon'      => $t['bee_hoon'] ?? 'Bee Hoon',
    'kway_teow'     => $t['kway_teow'] ?? 'Kway Teow',
    'mee_kia'       => $t['mee_kia'] ?? 'Mee Kia',
    'mee_tai_mak'   => $t['mee_tai_mak'] ?? 'Mee Tai Mak',
    'mee_pok'       => $t['mee_pok'] ?? 'Mee Pok',
    'bee_hoon mixed_yellow_noodle' => $t['bee_hoon mixed_yellow_noodle'] ?? 'Bee Hoon + Yellow Noodle',
    'kway_teow mixed_yellow_noodle' => $t['kway_teow mixed_yellow_noodle'] ?? 'Kway Teow + Yellow Noodle',
];

$noodle_images = [
    'yellow_noodle'=>'images/food/yellownoodle.jpg',
    'bee_hoon'=>'images/food/beehoon.jpg',
    'kway_teow'=>'images/food/kwayteow.jpg',
    'mee_kia'=>'images/food/mee_kia.webp',
    'mee_tai_mak'=>'images/food/meetaimak.jpg',
    'mee_pok'=>'images/food/meepok.jpg',
    'bee_hoon mixed_yellow_noodle'=>'images/food/bee_hoon_mixed_yellow_noodle.png',
    'kway_teow mixed_yellow_noodle'=>'images/food/kway_teow_mixed_yellow_noodle.webp',
];

$add_images = [
    'add_noodle'=>'images/icons/add_noodle.png',
    'add_ingredient'=>'images/icons/ingredient.png',
];

$soup_images = [
    'dry'=>'images/icons/dry.png',
    'soup'=>'images/icons/soup.png',
];

$spicy_images = [
    'no'=>'images/icons/no_spicy.png',
    'mild'=>'images/icons/less_spicy.png',
    'normal'=>'images/icons/normals.jpg',
    'medium'=>'images/icons/medium_spicy.png',
    'hot'=>'images/icons/hot_spicy.png',
];

$vinegar_images = [
    'no'=>'images/icons/no_vinegar.png',
    'yes'=>'images/icons/vinegar.png',
];

$taste_images = [
    'light'=>'images/icons/light.png',
    'normal'=>'images/icons/normals.jpg',
    'strong'=>'images/icons/strong.png',
];

$takeaway_options = [
    'self_container' => $t['self_container'] ?? 'Self Container',
    'restaurant_container' => $t['restaurant_container'] ?? 'Restaurant Container',
    'plastic_bag' => $t['plastic_bag'] ?? 'Plastic Bag',
];

// 安全获取参数
$food_id = isset($_GET['food_id']) ? intval($_GET['food_id']) : 0;
$qty = isset($_GET['qty']) ? max(1, intval($_GET['qty'])) : 1;

// 检查菜品是否存在且可用
if (!isset($menu[$food_id]) || $qty <= 0 || !$menu[$food_id]['available']) {
    header('Location: menu.php?lang=' . urlencode($lang));
    exit();
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 清理和验证输入数据
    $data = [
        'id' => $food_id,
        'qty' => $qty,
        'noodleType' => isset($_POST['noodle_type']) ? htmlspecialchars(trim($_POST['noodle_type']), ENT_QUOTES, 'UTF-8') : '',
        'addNoodle' => isset($_POST['add_noodle']),
        'addIngredient' => isset($_POST['add_ingredient']),
        'addPrawn' => isset($_POST['add_prawn']),
        'addPorkRibs' => isset($_POST['add_pork_ribs']),
        'addMeatPlattie' => isset($_POST['add_meat_plattie']),
        'soup_type' => isset($_POST['soup_type']) ? htmlspecialchars(trim($_POST['soup_type']), ENT_QUOTES, 'UTF-8') : '',
        'spicy' => isset($_POST['spicy']) ? htmlspecialchars(trim($_POST['spicy']), ENT_QUOTES, 'UTF-8') : '',
        'vinegar' => isset($_POST['vinegar']) ? htmlspecialchars(trim($_POST['vinegar']), ENT_QUOTES, 'UTF-8') : '',
        'taste' => isset($_POST['taste']) ? htmlspecialchars(trim($_POST['taste']), ENT_QUOTES, 'UTF-8') : '',
        'takeaway_option' => isset($_POST['takeaway_option']) ? htmlspecialchars(trim($_POST['takeaway_option']), ENT_QUOTES, 'UTF-8') : '',
        'remark' => isset($_POST['remark']) ? htmlspecialchars(trim($_POST['remark']), ENT_QUOTES, 'UTF-8') : '',
        'without_pork_liver'   => isset($_POST['without_pork_liver']),
        'without_minced_meat'  => isset($_POST['without_minced_meat']),
        'without_fishball'     => isset($_POST['without_fishball']),
        'without_sliced_meat'  => isset($_POST['without_sliced_meat']),
        'without_fried_shallots' => isset($_POST['without_fried_shallots']),
        'without_ginger_onion' => isset($_POST['without_ginger_onion']),
        'without_ketchup'       => isset($_POST['without_ketchup']),
    ];
    
    header('Content-Type: text/html; charset=utf-8');
    $json_data = json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    echo "<script>
        let cart = JSON.parse(localStorage.getItem('cart')||'[]');
        cart.push($json_data);
        localStorage.setItem('cart', JSON.stringify(cart));
        window.location.href='menu.php?lang=" . urlencode($lang) . "';
    </script>";
    exit;
}

$addon_images = [
    'add_prawn'=>'images/icons/prawn.jpg',
    'add_pork_ribs'=>'images/icons/pork_ribs.jpg',
    'add_meat_plattie'=>'images/icons/meat_plattie.jpg',
];

$no_option = [
    'without_pork_liver'   => $t['without_pork_liver'] ?? 'Without Pork Liver',
    'without_minced_meat'  => $t['without_minced_meat'] ?? 'Without Minced Meat',
    'without_fishball'     => $t['without_fishball'] ?? 'Without Fishball',
    'without_sliced_meat'  => $t['without_sliced_meat'] ?? 'Without Sliced Meat',
    'without_fried_shallots' => $t['without_fried_shallots'] ?? 'Without Fried Shallots',
    'without_ginger_onion'   => $t['without_ginger_onion'] ?? 'Without Ginger Onion',
    'without_ketchup'         => $t['without_ketchup'] ?? 'Without Ketchup',
];

// 鱼丸汤不需要选项，直接加入购物车
if ($menu[$food_id]['db_food_name'] === 'Fishball Soup') {
    $data = [
        'id' => $food_id,
        'qty' => $qty,
        'noodleType' => '',
        'addNoodle' => false,
        'addIngredient' => false,
        'addPrawn' => false,
        'addPorkRibs' => false,
        'addMeatPlattie' => false,
        'soup_type' => '',
        'spicy' => '',
        'vinegar' => '',
        'taste' => '',
        'takeaway_option' => '',
        'remark' => '',
        'without_pork_liver'   => false,
        'without_minced_meat'  => false,
        'without_fishball'     => false,
        'without_sliced_meat'  => false,
        'without_fried_shallots' => false,
        'without_ginger_onion'   => false,
        'without_ketchup'         => false,
    ];
    
    $json_data = json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    echo "<script>
        let cart = JSON.parse(localStorage.getItem('cart')||'[]');
        cart.push($json_data);
        localStorage.setItem('cart', JSON.stringify(cart));
        window.location.href='menu.php?lang=" . urlencode($lang) . "';
    </script>";
    exit;
}

// 关闭数据库连接
if ($conn) {
    mysqli_close($conn);
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ($lang === 'en' ? 'Customize' : '自定义') . ' - ' . htmlspecialchars($menu[$food_id]['title'], ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e0eafc 100%);
            font-family: 'Montserrat', Arial, sans-serif;
            min-height: 100vh;
        }
        .custom-box {
            background: #fff;
            border-radius: 22px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.13);
            max-width: 520px;
            margin: 2.5rem auto 2.5rem auto;
            padding: 2.5rem 2.5rem 2rem 2.5rem;
            position: relative;
        }
        .lang-btn {
            text-align: right;
            margin-bottom: 1.2rem;
        }
        .lang-btn a {
            color: #2d3a4b;
            text-decoration: none;
            margin: 0 0.5rem;
            font-weight: bold;
            font-size: 1.08rem;
        }
        .lang-btn a.active {
            text-decoration: underline;
            color: #ff9800;
        }
        .back-link {
            display: block;
            margin-bottom: 1.2rem;
            color: #2d3a4b;
            text-decoration: none;
            font-size: 1.08rem;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        h2 {
            text-align: center;
            color: #2d3a4b;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            letter-spacing: 1px;
        }
        .menu-img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 16px;
            margin: 0 auto 1.2rem auto;
            display: block;
            box-shadow: 0 4px 16px rgba(31,38,135,0.11);
        }
        .option-group {
            margin-bottom: 2.1rem;
        }
        .option-title {
            font-weight: bold;
            margin-bottom: 0.7rem;
            color: #333;
            font-size: 1.08rem;
            letter-spacing: 0.5px;
        }
        .option-cards {
            display: flex;
            flex-wrap: wrap;
            gap: 1.1rem;
        }
        .option-card {
            background: #f7f7f7;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(31,38,135,0.07);
            padding: 0.8rem 0.8rem 0.6rem 0.8rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
            border: 2px solid transparent;
            transition: border 0.2s, box-shadow 0.2s, background 0.2s, transform 0.18s;
            min-width: 90px;
            max-width: 120px;
            position: relative;
        }
        .option-card.selected, .option-card:hover {
            border: 2.2px solid #ffb347;
            box-shadow: 0 8px 24px rgba(255,204,51,0.13);
            background: #fffbe6;
            transform: translateY(-2px) scale(1.04);
        }
        .option-card img {
            width: 58px; height: 58px; object-fit: cover; border-radius: 10px; margin-bottom: 0.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        }
        .option-card label {
            font-size: 1.04rem;
            color: #222;
            cursor: pointer;
            font-weight: 500;
            text-align: center;
        }
        .option-checkbox {
            margin-top: 0.5rem;
            accent-color: #ffb347;
            width: 18px; height: 18px;
        }
        textarea {
            margin-top:0.5rem;
            width:100%;
            border-radius:8px;
            border:1px solid #ccc;
            padding:0.5rem;
            font-size:1rem;
            background: #f7f7f7;
            min-height: 40px;
        }
        .btn-confirm {
            margin: 2.2rem auto 0 auto; display: block;
            background: linear-gradient(90deg, #ffb347 0%, #ffcc33 100%);
            color: #222; font-weight: bold; border: none; border-radius: 10px;
            padding: 0.8rem 2.5rem; font-size: 1.13rem; cursor: pointer;
            box-shadow: 0 2px 8px rgba(255, 204, 51, 0.10);
            transition: background 0.2s, transform 0.2s;
            letter-spacing: 1px;
        }
        .btn-confirm:hover {
            background: linear-gradient(90deg, #ffcc33 0%, #ffb347 100%);
            transform: translateY(-2px) scale(1.04);
        }
        @media (max-width: 600px) {
            .custom-box {
                padding: 1.2rem 0.5rem 1.2rem 0.5rem;
            }
            .option-cards {
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="custom-box">
        <div class="lang-btn">
            <a href="?food_id=<?php echo $food_id; ?>&qty=<?php echo $qty; ?>&lang=zh"<?php if($lang==='zh') echo ' class="active"'; ?>>中文</a> |
            <a href="?food_id=<?php echo $food_id; ?>&qty=<?php echo $qty; ?>&lang=en"<?php if($lang==='en') echo ' class="active"'; ?>>English</a>
        </div>
        <a href="menu.php?lang=<?php echo urlencode($lang); ?>" class="back-link">&laquo; <?php echo $lang==='en' ? 'Back to Menu' : '返回菜单'; ?></a>
        <img src="<?php echo htmlspecialchars($menu[$food_id]['img'], ENT_QUOTES, 'UTF-8'); ?>" class="menu-img" alt="<?php echo htmlspecialchars($menu[$food_id]['title'], ENT_QUOTES, 'UTF-8'); ?>">
        <h2><?php echo htmlspecialchars($menu[$food_id]['title'], ENT_QUOTES, 'UTF-8'); ?> <span style="font-size:1rem;color:#888;">x<?php echo $qty; ?></span></h2>
        <form method="post" id="customForm" autocomplete="off">
            <div class="option-group">
                <div class="option-title"><?php echo $lang==='en' ? 'Noodle Type' : '面类'; ?></div>
                <div class="option-cards" id="noodle-cards">
                    <?php foreach($noodle_types as $key=>$val): ?>
                    <div class="option-card" data-value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
                        <img src="<?php echo htmlspecialchars($noodle_images[$key] ?? $noodle_images['yellow_noodle'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?>">
                        <label><?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?></label>
                        <input type="radio" name="noodle_type" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" style="display:none;" <?php if($key=='mee_kia') echo 'checked'; ?>>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="option-group">
                <div class="option-title"><?php echo $lang==='en' ? 'Add Noodle/Ingredient' : '加料/加面'; ?></div>
                <div class="option-cards">
                    <div class="option-card" id="add-noodle-card">
                        <img src="<?php echo htmlspecialchars($add_images['add_noodle'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $lang==='en' ? 'Add Noodle' : '加面'; ?>">
                        <label><?php echo $lang==='en' ? 'Add Noodle +$1' : '加面 +$1'; ?></label>
                        <input type="checkbox" name="add_noodle" class="option-checkbox">
                    </div>
                    <div class="option-card" id="add-ingredient-card">
                        <img src="<?php echo htmlspecialchars($add_images['add_ingredient'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $lang==='en' ? 'Add Ingredient' : '加料'; ?>">
                        <label><?php echo $lang==='en' ? 'Add Ingredient +$2' : '加料 +$2'; ?></label>
                        <input type="checkbox" name="add_ingredient" class="option-checkbox">
                    </div>
                </div>
                <!-- 新增加虾/排骨/肉饼 $3 卡片 -->
                <div class="option-cards" style="margin-top:1rem;">
                    <div class="option-card">
                        <img src="<?php echo htmlspecialchars($addon_images['add_prawn'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $lang==='en' ? 'Add Prawn' : '加虾'; ?>">
                        <label><?php echo $lang==='en' ? 'Add Prawn +$3' : '加虾 +$3'; ?></label>
                        <input type="checkbox" name="add_prawn" class="option-checkbox">
                    </div>
                    <div class="option-card">
                        <img src="<?php echo htmlspecialchars($addon_images['add_pork_ribs'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $lang==='en' ? 'Add Pork Ribs' : '加排骨'; ?>">
                        <label><?php echo $lang==='en' ? 'Add Pork Ribs +$3' : '加排骨 +$3'; ?></label>
                        <input type="checkbox" name="add_pork_ribs" class="option-checkbox">
                    </div>
                    <div class="option-card">
                        <img src="<?php echo htmlspecialchars($addon_images['add_meat_plattie'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $lang==='en' ? 'Add Meat Plattie' : '加肉饼'; ?>">
                        <label><?php echo $lang==='en' ? 'Add Meat Plattie +$3' : '加肉饼 +$3'; ?></label>
                        <input type="checkbox" name="add_meat_plattie" class="option-checkbox">
                    </div>
                </div>
            </div>
            <div class="option-group">
                <div class="option-title"><?php echo $lang==='en' ? 'Soup Type' : '干/汤'; ?></div>
                <div class="option-cards" id="soup-cards">
                    <?php foreach($soup_images as $key=>$img): ?>
                    <div class="option-card" data-value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
                        <img src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $key=='dry'?($lang==='en'?'Dry':'干'):($lang==='en'?'Soup':'汤'); ?>">
                        <label><?php echo $key=='dry'?($lang==='en'?'Dry':'干'):($lang==='en'?'Soup':'汤'); ?></label>
                        <input type="radio" name="soup_type" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" style="display:none;" <?php if($key=='dry') echo 'checked'; ?>>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="option-group">
                <div class="option-title"><?php echo $lang==='en' ? 'Spicy' : '辣度'; ?></div>
                <div class="option-cards" id="spicy-cards">
                    <?php
                    $spicy_labels = [
                        'no' => $lang==='en' ? 'No Spicy' : '不辣',
                        'mild' => $lang==='en' ? 'Mild' : '微辣',
                        'normal' => $lang==='en' ? 'Normal' : '正常',
                        'medium' => $lang==='en' ? 'Medium' : '中辣',
                        'hot' => $lang==='en' ? 'Hot' : '重辣'
                    ];
                    foreach($spicy_images as $key=>$img): ?>
                    <div class="option-card" data-value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
                        <img src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($spicy_labels[$key], ENT_QUOTES, 'UTF-8'); ?>">
                        <label><?php echo htmlspecialchars($spicy_labels[$key], ENT_QUOTES, 'UTF-8'); ?></label>
                        <input type="radio" name="spicy" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" style="display:none;" <?php if($key=='normal') echo 'checked'; ?>>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="option-group">
                <div class="option-title"><?php echo $lang==='en' ? 'Vinegar' : '醋'; ?></div>
                <div class="option-cards" id="vinegar-cards">
                    <?php foreach($vinegar_images as $key=>$img): ?>
                    <div class="option-card" data-value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
                        <img src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $key=='yes'?($lang==='en'?'With Vinegar':'要醋'):($lang==='en'?'No Vinegar':'不要醋'); ?>">
                        <label><?php echo $key=='yes'?($lang==='en'?'With Vinegar':'要醋'):($lang==='en'?'No Vinegar':'不要醋'); ?></label>
                        <input type="radio" name="vinegar" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" style="display:none;" <?php if($key=='no') echo 'checked'; ?>>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="option-group">
                <div class="option-title"><?php echo $lang==='en' ? 'Taste' : '口味'; ?></div>
                <div class="option-cards" id="taste-cards">
                    <?php
                    $taste_labels = [
                        'light' => $lang==='en' ? 'Light' : '清淡',
                        'normal' => $lang==='en' ? 'Normal' : '正常',
                        'strong' => $lang==='en' ? 'Strong' : '重口'       
                    ];
                    foreach($taste_images as $key=>$img): ?>
                    <div class="option-card" data-value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
                        <img src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($taste_labels[$key], ENT_QUOTES, 'UTF-8'); ?>">
                        <label><?php echo htmlspecialchars($taste_labels[$key], ENT_QUOTES, 'UTF-8'); ?></label>
                        <input type="radio" name="taste" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" style="display:none;" <?php if($key=='normal') echo 'checked'; ?>>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="option-group">
                <div class="option-title"><?php echo $lang==='en' ? 'Take Away Option' : '打包选项'; ?></div>
                <div class="option-cards" id="takeaway-cards">
                    <?php foreach($takeaway_options as $key=>$val): ?>
                    <div class="option-card" data-value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
                        <label><?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?></label>
                        <input type="radio" name="takeaway_option" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" style="display:none;" <?php if($key=='self_container') echo 'checked'; ?>>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="font-size:0.9rem; color:#666; margin-top:0.5rem;">
                    <?php echo $lang==='en' ? '* Restaurant container: +SGD $0.30/box (Signature Noodle is free)' : '* 餐厅盒子: +SGD $0.30/盒 (招牌面免费)'; ?>
                </div>
            </div>
            <div class="option-group">
                <div class="option-title"><?php echo $lang==='en' ? 'No Options' : '避免选项'; ?></div>
                <div class="option-cards">
                    <?php foreach($no_option as $key=>$val): ?>
                    <div class="option-card">
                        <label><?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?></label>
                        <input type="checkbox" name="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" class="option-checkbox">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="option-group">
                <div class="option-title"><?php echo $lang==='en' ? 'Remark' : '备注'; ?></div>
                <textarea name="remark" rows="2" placeholder="<?php echo $lang==='en' ? 'Any special request...' : '有特殊需求可填写...'; ?>"></textarea>
            </div>
            <button type="submit" class="btn-confirm"><?php echo $lang==='en' ? 'Add to Cart' : '确认添加'; ?></button>
        </form>
    </div>
    <script>
        // 卡片点击高亮并选中radio/checkbox
        function setupCardSelect(groupId, inputType, defaultValue) {
            const group = document.getElementById(groupId);
            if (!group) return;
            
            group.querySelectorAll('.option-card').forEach(card => {
                card.onclick = function() {
                    if(inputType === 'radio') {
                        group.querySelectorAll('.option-card').forEach(c => c.classList.remove('selected'));
                        card.classList.add('selected');
                        const radio = card.querySelector('input[type=radio]');
                        if(radio) radio.checked = true;
                    }
                    else if(inputType === 'checkbox') {
                        card.classList.toggle('selected');
                        const checkbox = card.querySelector('input[type=checkbox]');
                        if(checkbox) checkbox.checked = !checkbox.checked;
                    }
                }
                
                // 设置默认选中状态
                if(inputType === 'radio') {
                    const radio = card.querySelector('input[type=radio]');
                    if(radio && radio.checked) {
                        card.classList.add('selected');
                    }
                }
            });
        }

        // 初始化所有卡片选择
        setupCardSelect('noodle-cards', 'radio', 'mee_kia');
        setupCardSelect('soup-cards', 'radio', 'dry');
        setupCardSelect('spicy-cards', 'radio', 'normal');
        setupCardSelect('vinegar-cards', 'radio', 'no');
        setupCardSelect('taste-cards', 'radio', 'normal');
        setupCardSelect('takeaway-cards', 'radio', 'self_container');

        // 处理复选框卡片点击事件
        document.querySelectorAll('.option-card input[type=checkbox]').forEach(function(checkbox){
            const card = checkbox.closest('.option-card');
            if(card && !card.hasAttribute('data-value')) {
                card.onclick = function(e) {
                    if(e.target.tagName !== 'INPUT') {
                        card.classList.toggle('selected');
                        checkbox.checked = !checkbox.checked;
                    }
                };
            }
        });

        // 防止重复提交
        document.getElementById('customForm').onsubmit = function(){
            const btn = document.querySelector('.btn-confirm');
            btn.disabled = true;
            btn.textContent = '<?php echo $lang==='en' ? 'Adding...' : '添加中...'; ?>';
        };
    </script>
</body>
</html>