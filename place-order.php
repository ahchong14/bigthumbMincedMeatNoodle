<?php
include('config/constants.php');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('lang.php');
if(!isset($_SESSION['dine_type'])){
    header('location: index.php');
    exit();
}

// 确保$t变量已定义
if(!isset($t)) {
    $t = $lang_data['zh']; // 默认使用中文
}

// 菜单数据 - 与menu.php保持一致
$menu = [];
if($conn) {
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
    if($result) {
        while($row = mysqli_fetch_assoc($result)) {
            $menu[$row['id']] = [  // 使用ID作为数组键，便于查找
                'id' => $row['id'],
                'db_food_name' => $row['food_name'],
                'title' => getTranslatedTitle($row['food_name'], $t),
                'price' => $row['price'],
                'img' => $row['img'],
                'available' => $row['is_available'],
                'unavailable_reason' => $row['unavailable_reason']
            ];
        }
    }
}

// 标题翻译映射函数 - 与menu.php保持一致
function getTranslatedTitle($db_food_name, $t) {
    $mapping = [
        'Signature Noodle' => $t['signature_noodle'],
        'Fishball Noodle' => $t['fishball_noodle'],
        'Minced Meat Noodle' => $t['minced_meat_noodle'],
        'Fishball Minced Meat Noodle' => $t['fishball_minced_noodle'],
        'Fishball Soup' => $t['fishball_soup'],
        'Curry Chicken Noodle' => $t['curry_chicken_noodle'],
        'Curry Fishball Noodle' => $t['curry_fishball_noodle'],
        'Pork Ribs Noodle' => $t['pork_ribs_noodle']
    ];
    return $mapping[$db_food_name] ?? $db_food_name;
}

$noodle_types = [
    'yellow_noodle'=>$t['yellow_noodle'],
    'laoshu_fen'=>$t['laoshu_fen'],
    'mee_kia'=>$t['mee_kia'],
    'mee_pok'=>$t['mee_pok'],
    'kway_teow'=>$t['kway_teow'],
    'bee_hoon mixed_yellow_noodle' => $t['bee_hoon mixed_yellow_noodle'],
    'kway_teow mixed_yellow_noodle' => $t['kway_teow mixed_yellow_noodle'],
];

// 打包选项翻译
$takeaway_options = [
    'self_container' => $t['self_container'] ?? 'Self Container',
    'restaurant_container' => $t['restaurant_container'] ?? 'Restaurant Container',
    'plastic_bag' => $t['plastic_bag'] ?? 'Plastic Bag'
];

// 获取购物车数据
$cart = [];
if (isset($_POST['cart_data'])) {
    $cart = json_decode($_POST['cart_data'], true);
}
$show_confirm = !isset($_POST['final_submit']);
$order_no = null;
$order_id = null;
$success_msg = '';
$total_all = 0;
$is_takeaway = isset($_SESSION['dine_type']) && $_SESSION['dine_type'] === 'take_away';
$takeaway_fee = 0.30;

if(isset($_POST['final_submit']) && !empty($cart)){
    $order_date = date('Y-m-d H:i:s');
    $status = 'Pending';
    $dine_type = $_SESSION['dine_type'];
    $phone = isset($_POST['phone']) ? mysqli_real_escape_string($conn, $_POST['phone']) : '';
    
    // 计算总价
    foreach($cart as $item){
        $food_id = intval($item['id']);
        $qty = intval($item['qty']);
        if(!isset($menu[$food_id]) || $qty<=0) continue;
        $price = $menu[$food_id]['price'];
        if(!empty($item['addNoodle'])) $price += 1;
        if(!empty($item['addIngredient'])) $price += 2;
        if(!empty($item['addPrawn'])) $price += 3;
        if(!empty($item['addPorkRibs'])) $price += 3;
        if(!empty($item['addMeatPlattie'])) $price += 3;
        
        // 计算打包费 - 只有选择餐厅盒子才收费，且招牌面免费
        $item_takeaway_fee = 0;
        if ($is_takeaway && isset($item['takeaway_option']) && $item['takeaway_option'] === 'restaurant_container' && $menu[$food_id]['db_food_name'] !== 'Signature Noodle') {
            $item_takeaway_fee = $takeaway_fee * $qty;
        }
        $total_all += ($price * $qty) + $item_takeaway_fee;
    }

    // 生成订单号
    $order_no = 'OD' . date('YmdHis') . rand(10,99);

    // 插入 orders 主表
    $sql_order = "INSERT INTO orders (order_no, dine_type, phone, total, status, order_date) VALUES ('$order_no', '$dine_type', '$phone', $total_all, '$status', '$order_date')";
    $res_order = mysqli_query($conn, $sql_order);

    if($res_order){
        $order_id = mysqli_insert_id($conn);

        // 插入 order_items
        foreach($cart as $item){
            $food_id = intval($item['id']);
            $qty = intval($item['qty']);
            if(!isset($menu[$food_id]) || $qty<=0) continue;

            $noodle_type = $item['noodleType'] ?? '';
            $add_noodle = !empty($item['addNoodle']) ? 1 : 0;
            $add_ingredient = !empty($item['addIngredient']) ? 1 : 0;
            $add_prawn = !empty($item['addPrawn']) ? 1 : 0;
            $add_pork_ribs = !empty($item['addPorkRibs']) ? 1 : 0;
            $add_meat_plattie = !empty($item['addMeatPlattie']) ? 1 : 0;
            $takeaway_option = $item['takeaway_option'] ?? '';

            $food = $menu[$food_id]['title'];
            $price = $menu[$food_id]['price'];
            if($add_noodle) $price += 1;
            if($add_ingredient) $price += 2;
            if($add_prawn) $price += 3;
            if($add_pork_ribs) $price += 3;
            if($add_meat_plattie) $price += 3;

            // 计算打包费
            $item_takeaway_fee = 0;
            if ($is_takeaway && $takeaway_option === 'restaurant_container' && $menu[$food_id]['db_food_name'] !== 'Signature Noodle') {
                $item_takeaway_fee = $takeaway_fee * $qty;
            }
            $item_total = ($price * $qty) + $item_takeaway_fee;

            // store full custom item JSON into 'custom' column
            $custom_json = mysqli_real_escape_string($conn, json_encode($item, JSON_UNESCAPED_UNICODE));

            $sql_item = "INSERT INTO order_items 
                        (order_id, food, noodle_type, add_noodle, add_ingredient, add_prawn, add_pork_ribs, add_meat_plattie, takeaway_option, qty, price, total, custom) 
                        VALUES 
                        ($order_id, '$food', '$noodle_type', $add_noodle, $add_ingredient, $add_prawn, $add_pork_ribs, $add_meat_plattie, '$takeaway_option', $qty, $price, $item_total, '$custom_json')";
            mysqli_query($conn, $sql_item);
        }

        $show_confirm = false;
        $success_msg = '<div class="success" style="text-align:center;font-size:1.2rem;margin:1.5rem 0;">
                            Order placed successfully!<br>
                            Your Order No: <b>#'.$order_no.'</b><br>
                            Total: SGD $'.number_format($total_all,2).'<br>
                            Thank you for your order!
                        </div>';
    
    }else{
        $success_msg = '<div class="error" style="text-align:center;font-size:1.2rem;margin:1.5rem 0;">
                            Failed to place order. Please try again.
                        </div>';
    }
}

// 获取当前语言
$current_lang = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : 'zh';
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $t['place_order']; ?> - <?php echo $t['welcome']; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Montserrat', Arial, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e0eafc 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .order-box {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 4px 16px 0 rgba(31, 38, 135, 0.10);
            max-width: 500px;
            margin: 2rem auto;
            padding: 2rem 2rem 1.5rem 2rem;
        }
        .order-img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 12px;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        h2 {
            text-align: center;
            color: #2d3a4b;
            margin-bottom: 1.2rem;
        }
        table.tbl-30 {
            width: 100%;
            font-size: 1.05rem;
            border-collapse: collapse;
            margin-bottom: 1.2rem;
        }
        table.tbl-30 th, table.tbl-30 td {
            border: 1px solid #e0e0e0;
            padding: 0.5rem 0.2rem;
            text-align: left;
        }
        table.tbl-30 th {
            background: #f7f7f7;
            color: #222;
            font-weight: bold;
            text-align: center;
        }
        table.tbl-30 td:nth-child(2), table.tbl-30 td:nth-child(3), table.tbl-30 td:nth-child(4) {
            text-align: right;
        }
        .btn-confirm {
            margin: 1.5rem auto 0 auto;
            display: block;
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
        .btn-confirm:hover {
            background: linear-gradient(90deg, #ffcc33 0%, #ffb347 100%);
            transform: translateY(-2px) scale(1.04);
        }
        .modal {
            display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.25); align-items: center; justify-content: center;
        }
        .modal-content {
            background: #fff; border-radius: 16px; padding: 2rem 2.5rem; text-align: center; font-size: 1.2rem; box-shadow: 0 4px 32px rgba(0,0,0,0.18);
        }
        .takeaway-fee-note {
            color: #e67e22;
            font-size: 0.98rem;
            margin-bottom: 0.5rem;
        }
        .phone-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        @media (max-width: 600px) {
            .order-box {
                padding: 1rem 0.5rem 1rem 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="order-box">
        <h2><?php echo $t['place_order']; ?></h2>
        <?php if($is_takeaway): ?>
        <div class="takeaway-fee-note">
            * <?php echo $t['takeaway_fee_note'] ?? 'Take away: +SGD $0.30/box (only for restaurant container, Signature Noodle is free)'; ?>
        </div>
        <?php endif; ?>
        <?php if($show_confirm && $cart): ?>
        <form method="POST">
            <input type="hidden" name="cart_data" value="<?php echo htmlspecialchars(json_encode($cart)); ?>">
            <table class="tbl-30">
                <tr>
                    <th><?php echo $t['menu']; ?></th>
                    <th><?php echo $t['qty']; ?></th>
                    <th><?php echo $t['price']; ?></th>
                    <th><?php echo $t['total']; ?></th>
                </tr>
                <?php
                $total_all = 0;
                $total_takeaway_fee = 0;
                foreach($cart as $item):
                    $food_id = intval($item['id']);
                    $qty = intval($item['qty']);
                    $noodle_type = $item['noodleType'] ?? '';
                    $add_noodle = !empty($item['addNoodle']);
                    $add_ingredient = !empty($item['addIngredient']);
                    $add_prawn = !empty($item['addPrawn']);
                    $add_pork_ribs = !empty($item['addPorkRibs']);
                    $add_meat_plattie = !empty($item['addMeatPlattie']);
                    $takeaway_option = $item['takeaway_option'] ?? '';
                    if(!isset($menu[$food_id]) || $qty<=0) continue;
                    $food = $menu[$food_id]['title'];
                    $price = $menu[$food_id]['price'];
                    if($add_noodle) $price += 1;
                    if($add_ingredient) $price += 2;
                    if($add_prawn) $price += 3;
                    if($add_pork_ribs) $price += 3;
                    if($add_meat_plattie) $price += 3;
                    
                    // 计算打包费
                    $item_takeaway_fee = 0;
                    if ($is_takeaway && $takeaway_option === 'restaurant_container' && $menu[$food_id]['db_food_name'] !== 'Signature Noodle') {
                        $item_takeaway_fee = $takeaway_fee * $qty;
                    }
                    $item_total = $price * $qty + $item_takeaway_fee;
                    $total_all += $price * $qty;
                    $total_takeaway_fee += $item_takeaway_fee;
                ?>
                <tr>
                    <td>
                        <?php echo htmlspecialchars($food); ?><br>
                        <small>
                            <?php echo isset($noodle_types[$noodle_type]) ? $noodle_types[$noodle_type] : ''; ?>
                            <?php if($add_noodle) echo ' +'.$t['add_noodle']; ?>
                            <?php if($add_ingredient) echo ' +'.$t['add_ingredient']; ?>
                            <?php if($add_prawn) echo ' +'.$t['add_prawn']; ?>
                            <?php if($add_pork_ribs) echo ' +'.$t['add_pork_ribs']; ?>
                            <?php if($add_meat_plattie) echo ' +'.$t['add_meat_plattie']; ?>
                            <?php 
                            // 显示打包选项
                            if($takeaway_option && isset($takeaway_options[$takeaway_option])) {
                                echo '<br>' . $takeaway_options[$takeaway_option];
                            }
                            ?>
                            <?php if($is_takeaway && $takeaway_option === 'restaurant_container' && $menu[$food_id]['db_food_name'] !== 'Signature Noodle') echo "<br><span style='color:#e67e22;'>+SGD $".number_format($takeaway_fee,2)." x $qty (".$t['take_away'].")</span>"; ?>
                            <?php if($is_takeaway && $menu[$food_id]['db_food_name'] === 'Signature Noodle' && $takeaway_option === 'restaurant_container') echo "<br><span style='color:#27ae60;'>".$t['no_takeaway_fee']."</span>"; ?>
                        </small>
                    </td>
                    <td><?php echo $qty; ?></td>
                    <td>SGD $<?php echo number_format($price,2); ?></td>
                    <td>SGD $<?php echo number_format($item_total,2); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if($is_takeaway && $total_takeaway_fee > 0): ?>
                <tr>
                    <td colspan="3" style="text-align:right;color:#e67e22;"><b><?php echo $t['takeaway_fee'] ?? 'Take away fee'; ?></b></td>
                    <td style="color:#e67e22;"><b>SGD $<?php echo number_format($total_takeaway_fee,2); ?></b></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td colspan="3" style="text-align:right;"><b><?php echo $t['total']; ?></b></td>
                    <td><b>SGD $<?php echo number_format($total_all + $total_takeaway_fee,2); ?></b></td>
                </tr>
                <tr>
                    <td><?php echo $t['phone'] ?? 'Phone'; ?></td>
                    <td colspan="3"><input type="text" name="phone" class="phone-input" placeholder="<?php echo $t['phone_placeholder'] ?? 'Optional, for contact'; ?>"></td>
                </tr>
            </table>
            <button type="submit" name="final_submit" class="btn-confirm"><?php echo $t['submit_order'] ?? 'Confirm'; ?></button>
        </form>
        <?php elseif(!$cart): ?>
            <div style="text-align:center;color:#e74c3c;"><?php echo $t['no_order_data'] ?? 'No order data.'; ?></div>
        <?php else: ?>
            <div id="order-modal" class="modal" style="display:flex;">
                <div class="modal-content">
                    <div style="font-size:2.2rem;color:#2ecc71;margin-bottom:1rem;">&#10003;</div>
                    <?php echo $success_msg; ?>
                </div>
            </div>
            
             <script>
                // 使用Web Audio API创建适合的成功音效 - 清脆的"叮"声
                function playSuccessSound() {
                    try {
                        // 创建音频上下文
                        var AudioContext = window.AudioContext || window.webkitAudioContext;
                        var audioCtx = new AudioContext();
                        
                        // 创建振荡器
                        var oscillator = audioCtx.createOscillator();
                        var gainNode = audioCtx.createGain();
                        
                        // 设置音效参数 - 更清脆的铃声效果
                        oscillator.type = 'sine';
                        oscillator.frequency.setValueAtTime(1318.51, audioCtx.currentTime); // E6
                        oscillator.frequency.setValueAtTime(1567.98, audioCtx.currentTime + 0.1); // G6
                        oscillator.frequency.setValueAtTime(1975.53, audioCtx.currentTime + 0.2); // B6
                        
                        // 设置音量包络 - 快速衰减，产生清脆感
                        gainNode.gain.setValueAtTime(0.4, audioCtx.currentTime);
                        gainNode.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.8);
                        
                        // 连接节点
                        oscillator.connect(gainNode);
                        gainNode.connect(audioCtx.destination);
                        
                        // 播放音效
                        oscillator.start();
                        oscillator.stop(audioCtx.currentTime + 0.8);
                        
                    } catch (error) {
                        console.log('音效播放失败:', error);
                        // 如果Web Audio API不可用，尝试使用简单的HTML5音频
                        try {
                            // 创建一个简单的beep声作为备用
                            var beepSound = new Audio();
                            beepSound.src = 'data:audio/wav;base64,UklGRigAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQQAAAAAAA';
                            beepSound.play();
                        } catch (e) {
                            console.log('备用音效也播放失败:', e);
                        }
                    }
                }
                
                // 当页面加载完成时播放音效
                document.addEventListener('DOMContentLoaded', function() {
                    var modal = document.getElementById('order-modal');
                    if (modal && modal.style.display === 'flex') {
                        playSuccessSound();
                        
                        // 3秒后自动重定向到首页
                        setTimeout(function() {
                            window.location.href = "index.php";
                        }, 3000);
                    }
                });
            </script>
        <?php endif; ?>
    </div>
</body>
</html>