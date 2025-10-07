<?php
session_start();
if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in']!==true){
    header('Location: index.php');
    exit();
}

include('../config/constants.php');
// 订单管理页面

// 创建必要的表（如果不存在）
$create_daily_stats = "CREATE TABLE IF NOT EXISTS `daily_stats` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `date` DATE NOT NULL UNIQUE,
  `order_count` INT DEFAULT 0,
  `total_sales` DECIMAL(10,2) DEFAULT 0,
  `completed_orders` INT DEFAULT 0,
  `cancelled_orders` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

$create_food_availability = "CREATE TABLE IF NOT EXISTS `food_availability` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `food_name` VARCHAR(128) NOT NULL UNIQUE,
  `is_available` TINYINT(1) DEFAULT 1,
  `unavailable_reason` TEXT DEFAULT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

// 分别执行每个CREATE TABLE语句
mysqli_query($conn, $create_daily_stats);
mysqli_query($conn, $create_food_availability);

// 处理商品可用性更新
if(isset($_POST['update_availability'])){
    $food_name = mysqli_real_escape_string($conn, $_POST['food_name']);
    $is_available = intval($_POST['is_available']);
    $reason = mysqli_real_escape_string($conn, $_POST['unavailable_reason']);
    
    $sql_availability = "INSERT INTO food_availability (food_name, is_available, unavailable_reason) 
                        VALUES ('$food_name', $is_available, '$reason') 
                        ON DUPLICATE KEY UPDATE 
                        is_available = $is_available, 
                        unavailable_reason = '$reason'";
    mysqli_query($conn, $sql_availability);
    echo "<script>location.href=location.href;</script>";
    exit;
}

// 处理批量状态更新
if(isset($_POST['bulk_update_status']) && isset($_POST['bulk_status'])){
    $bulk_status = mysqli_real_escape_string($conn, $_POST['bulk_status']);
    $order_ids = isset($_POST['order_ids']) ? $_POST['order_ids'] : [];
    
    if(!empty($order_ids) && is_array($order_ids)){
        $order_ids_str = implode(',', array_map('intval', $order_ids));
        $sql_bulk_update = "UPDATE orders SET status='$bulk_status' WHERE id IN ($order_ids_str)";
        if(mysqli_query($conn, $sql_bulk_update)){
            // 更新统计信息
            $sql_update_stats = "UPDATE daily_stats SET 
                                 order_count = (SELECT COUNT(*) FROM orders WHERE DATE(order_date)='$filter_date'),
                                 total_sales = (SELECT IFNULL(SUM(total),0) FROM orders WHERE DATE(order_date)='$filter_date' AND status != 'Cancelled'),
                                 completed_orders = (SELECT COUNT(*) FROM orders WHERE DATE(order_date)='$filter_date' AND status='Completed'),
                                 cancelled_orders = (SELECT COUNT(*) FROM orders WHERE DATE(order_date)='$filter_date' AND status='Cancelled')
                                 WHERE date='$filter_date'";
            mysqli_query($conn, $sql_update_stats);
        }
    }
    echo "<script>location.href=location.href;</script>";
    exit;
}

// 获取日期参数，默认今日（马来西亚时间+8）
date_default_timezone_set('Asia/Kuala_Lumpur');
$today = date('Y-m-d');
$filter_date = isset($_GET['date']) && $_GET['date'] ? $_GET['date'] : $today;

// 获取当前订单数量，用于检测新订单 - 放在正确的位置（在$filter_date和$conn定义后）
$current_order_count = 0;
$sql_count = "SELECT COUNT(*) as count FROM orders WHERE DATE(order_date)='$filter_date'";
$res_count = mysqli_query($conn, $sql_count);
if($res_count) {
    $row = mysqli_fetch_assoc($res_count);
    $current_order_count = $row['count'];
}

// 将当前订单数量存储到session中，用于下次比较
if(!isset($_SESSION['last_order_count'])) {
    $_SESSION['last_order_count'] = $current_order_count;
}

// 处理订单计数更新
if(isset($_POST['update_order_count'])) {
    $_SESSION['last_order_count'] = $current_order_count;
    // 防止重复提交
    echo "<script>location.href=location.href;</script>";
    exit;
}

// 订单状态更新
if(isset($_POST['update_status'])){
    $order_id = intval($_POST['order_id']);
    $status = $_POST['status'];

    // 获取订单信息
    $sql_order_info = "SELECT total, DATE(order_date) as order_date FROM orders WHERE id=$order_id";
    $res_order_info = mysqli_query($conn, $sql_order_info);
    $order_info = mysqli_fetch_assoc($res_order_info);
    $order_total = $order_info['total'];
    $order_date  = $order_info['order_date'];

    // 更新订单状态
    $sqlu = "UPDATE orders SET status='".mysqli_real_escape_string($conn, $status)."' WHERE id=$order_id";
    if(mysqli_query($conn, $sqlu)) {
        // 每次状态变更后，重新计算该订单日期的统计数据
        $sql_update_stats = "UPDATE daily_stats SET 
                             order_count = (SELECT COUNT(*) FROM orders WHERE DATE(order_date)='$order_date'),
                             total_sales = (SELECT IFNULL(SUM(total),0) FROM orders WHERE DATE(order_date)='$order_date' AND status != 'Cancelled'),
                             completed_orders = (SELECT COUNT(*) FROM orders WHERE DATE(order_date)='$order_date' AND status='Completed'),
                             cancelled_orders = (SELECT COUNT(*) FROM orders WHERE DATE(order_date)='$order_date' AND status='Cancelled')
                             WHERE date='$order_date'";
        mysqli_query($conn, $sql_update_stats);
    }

    echo "<script>location.href=location.href;</script>";
    exit;
}

// 创建或更新每日统计记录
$sql_check_stats = "SELECT * FROM daily_stats WHERE date='$filter_date'";
$res_check_stats = mysqli_query($conn, $sql_check_stats);
if(mysqli_num_rows($res_check_stats) == 0) {
    // 如果当日统计不存在，创建它
    $sql_create_stats = "INSERT INTO daily_stats (date, order_count, total_sales, completed_orders, cancelled_orders) 
                         SELECT '$filter_date', COUNT(*), SUM(total), 
                         SUM(CASE WHEN status='Completed' THEN 1 ELSE 0 END),
                         SUM(CASE WHEN status='Cancelled' THEN 1 ELSE 0 END)
                         FROM orders WHERE DATE(order_date)='$filter_date'";
    mysqli_query($conn, $sql_create_stats);
} else {
    // 更新统计信息
    $sql_update_stats = "UPDATE daily_stats SET 
                         order_count = (SELECT COUNT(*) FROM orders WHERE DATE(order_date)='$filter_date'),
                         total_sales = (SELECT SUM(total) FROM orders WHERE DATE(order_date)='$filter_date' AND status != 'Cancelled'),
                         completed_orders = (SELECT COUNT(*) FROM orders WHERE DATE(order_date)='$filter_date' AND status='Completed'),
                         cancelled_orders = (SELECT COUNT(*) FROM orders WHERE DATE(order_date)='$filter_date' AND status='Cancelled')
                         WHERE date='$filter_date'";
    mysqli_query($conn, $sql_update_stats);
}

// 统计报表 - 从daily_stats表获取
$sql_stats = "SELECT * FROM daily_stats WHERE date='$filter_date'";
$res_stats = mysqli_query($conn, $sql_stats);
$stats = mysqli_fetch_assoc($res_stats);

if(!$stats) {
    // 如果没有统计记录，创建默认值
    $stats = [
        'order_count' => 0,
        'total_sales' => 0,
        'completed_orders' => 0,
        'cancelled_orders' => 0
    ];
}

// 菜品销量
$sql_items = "SELECT food, SUM(qty) as qty FROM order_items WHERE order_id IN 
             (SELECT id FROM orders WHERE DATE(order_date)='$filter_date' AND status != 'Cancelled') 
             GROUP BY food ORDER BY qty DESC";
$res_items = mysqli_query($conn, $sql_items);
$food_stats = [];
while($row = mysqli_fetch_assoc($res_items)){
    $food_stats[] = $row;
}

// 获取filter参数
$order_no = isset($_GET['order_no']) ? trim($_GET['order_no']) : '';
$phone = isset($_GET['phone']) ? trim($_GET['phone']) : '';
$food_search = isset($_GET['food']) ? trim($_GET['food']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$dine_type_filter = isset($_GET['dine_type']) ? trim($_GET['dine_type']) : '';
$date_filter = $filter_date;

// 分页参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 25;
$offset = ($page - 1) * $per_page;

// 获取商品可用性数据
$sql_availability = "SELECT * FROM food_availability ORDER BY food_name";
$res_availability = mysqli_query($conn, $sql_availability);
$food_availability = [];
while($row = mysqli_fetch_assoc($res_availability)){
    $food_availability[$row['food_name']] = $row;
}

// 构建订单主表SQL
$sql_orders = "SELECT * FROM orders WHERE 1=1";
if($order_no) $sql_orders .= " AND order_no LIKE '%".mysqli_real_escape_string($conn, $order_no)."%'";

if($phone) $sql_orders .= " AND phone LIKE '%".mysqli_real_escape_string($conn, $phone)."%'";

if($status_filter) $sql_orders .= " AND status='".mysqli_real_escape_string($conn, $status_filter)."'";
if($dine_type_filter) $sql_orders .= " AND dine_type='".mysqli_real_escape_string($conn, $dine_type_filter)."'";
if($date_filter) $sql_orders .= " AND DATE(order_date)='".mysqli_real_escape_string($conn, $date_filter)."'";

// 获取总数用于分页
$sql_count_orders = $sql_orders;
$res_count_orders = mysqli_query($conn, $sql_count_orders);
$total_orders = mysqli_num_rows($res_count_orders);
$total_pages = ceil($total_orders / $per_page);

// 添加分页限制
$sql_orders .= " ORDER BY id DESC LIMIT $offset, $per_page";
$res_orders = mysqli_query($conn, $sql_orders);

// 导出CSV逻辑提前，避免底部输出
if(isset($_GET['export'])){
    $filename = "orders_".date("YmdHis").".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="'.$filename.'";');
    $output = fopen('php://output', 'w');
    fputcsv($output, array('Order No', 'Dine Type', 'Phone', 'Total (SGD)', 'Status', 'Order Date', 'Items'));
    $sql_export = "SELECT * FROM orders WHERE DATE(order_date)='$filter_date' ORDER BY id DESC";
    $res_export = mysqli_query($conn, $sql_export);
    while($order = mysqli_fetch_assoc($res_export)){
        $order_id = $order['id'];
        $sql_items = "SELECT * FROM order_items WHERE order_id=$order_id";
        $res_items = mysqli_query($conn, $sql_items);
        $items = [];
        while($item = mysqli_fetch_assoc($res_items)){
            $desc = $item['food'].' x'.$item['qty'].' ('.$item['noodle_type'];
            if($item['add_noodle']) $desc .= ' +Add Noodle';
            if($item['add_ingredient']) $desc .= ' +Add Ingredient';
            $desc .= ')';
            $items[] = $desc;
        }
        fputcsv($output, [
            $order['order_no'],
            $order['dine_type'],
            $order['phone'],
            $order['total'],
            $order['status'],
            $order['order_date'],
            implode("; ", $items)
        ]);
    }
    fclose($output);
    exit();
}

// 打印报表功能
if(isset($_GET['print_report'])) {
    // 设置打印样式
    $print_mode = true;
} else {
    $print_mode = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/admin.css">

    <!-- 音频元素用于播放提醒音 -->
    <audio id="notification-sound" preload="auto">
        <source src="bigfingerMee/sounds/notification.mp3" type="audio/mpeg">
    </audio>

    <script>
    // 页面自动刷新函数
    function autoRefresh() {
        setTimeout(function() {
            // 检查是否有筛选条件，如果有则保留
            const urlParams = new URLSearchParams(window.location.search);
            if(urlParams.toString()) {
                window.location.href = window.location.pathname + '?' + urlParams.toString();
            } else {
                window.location.reload();
            }
        }, 10000); // 每5秒刷新一次
    }

    // 检查是否有新订单
        // 页面自动刷新函数
    function autoRefresh() {
        setTimeout(function() {
            // 检查是否有筛选条件，如果有则保留
            const urlParams = new URLSearchParams(window.location.search);
            if(urlParams.toString()) {
                window.location.href = window.location.pathname + '?' + urlParams.toString();
            } else {
                window.location.reload();
            }
        }, 10000); // 每10秒刷新一次
    }

    // 检查是否有新订单
    function checkNewOrders() {
        const currentCount = <?php echo $current_order_count; ?>;
        const lastCount = <?php echo isset($_SESSION['last_order_count']) ? $_SESSION['last_order_count'] : 0; ?>;
        
        if(currentCount > lastCount) {
            // 播放提醒音效 - 使用Web Audio API生成清脆的提示音
            playNewOrderSound();
            
            // 显示通知徽章
            const badge = document.createElement('div');
            badge.className = 'notification-badge';
            badge.innerHTML = currentCount - lastCount;
            document.body.appendChild(badge);
            
            // 5秒后移除徽章
            setTimeout(() => {
                if(document.body.contains(badge)) {
                    document.body.removeChild(badge);
                }
            }, 5000);
            
            // 使用表单提交更新session计数
            const form = document.createElement('form');
            form.method = 'post';
            form.action = '';
            form.style.display = 'none';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'update_order_count';
            input.value = '1';
            form.appendChild(input);
            
            document.body.appendChild(form);
            form.submit();
        }
    }

    // 播放新订单提示音效
    function playNewOrderSound() {
        try {
            // 创建音频上下文
            var AudioContext = window.AudioContext || window.webkitAudioContext;
            var audioCtx = new AudioContext();
            
            // 创建振荡器
            var oscillator = audioCtx.createOscillator();
            var gainNode = audioCtx.createGain();
            
            // 设置音效参数 - 清脆的提示音
            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(880, audioCtx.currentTime); // A5
            oscillator.frequency.setValueAtTime(1318.51, audioCtx.currentTime + 0.1); // E6
            oscillator.frequency.setValueAtTime(1760, audioCtx.currentTime + 0.2); // A6
            
            // 设置音量包络 - 快速衰减
            gainNode.gain.setValueAtTime(0.3, audioCtx.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.5);
            
            // 连接节点
            oscillator.connect(gainNode);
            gainNode.connect(audioCtx.destination);
            
            // 播放音效
            oscillator.start();
            oscillator.stop(audioCtx.currentTime + 0.5);
            
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

    // 页面加载完成后执行
    document.addEventListener('DOMContentLoaded', function() {
        // 确保页面完全加载后再启动自动刷新
        setTimeout(function() {
            autoRefresh(); // 启动自动刷新
            checkNewOrders(); // 检查新订单
            
            // 设置定时器持续检查新订单
            setInterval(checkNewOrders, 10000); // 每5秒检查一次新订单
        }, 1000);
    });
    </script>

    <style>
        <?php if($print_mode): ?>
        /* 打印样式 */
        body {
            margin: 0;
            padding: 15px;
            font-family: Arial, sans-serif;
            background: #fff;
            font-size: 12px;
        }
        .container {
            max-width: 100%;
            margin: 0;
            background: #fff;
            border: none;
            box-shadow: none;
            padding: 0;
        }
        .no-print {
            display: none !important;
        }
        .print-only {
            display: block !important;
        }
        table {
            width: 100%;
            font-size: 10px;
        }
        th, td {
            padding: 4px 3px;
        }
        h1 {
            font-size: 18px;
            margin-bottom: 10px;
        }
        .dashboard-row {
            margin-bottom: 10px;
        }
        .dashboard-summary, .chart-box {
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
        <?php else: ?>
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Montserrat', Arial, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e0eafc 100%);
        }
        .container {
            max-width: 1100px;
            margin: 2rem auto;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 4px 16px 0 rgba(31, 38, 135, 0.10);
            padding: 2rem 2rem 2rem 2rem;
        }
        h1 {
            text-align: center;
            color: #2d3a4b;
            margin-bottom: 2rem;
        }
        .dashboard-row {
            display: flex;
            gap: 2rem;
            align-items: flex-start;
            margin-bottom: 2rem;
        }
        .dashboard-summary {
            flex: 1;
            min-width: 220px;
        }
        .dashboard-charts {
            flex: 2;
            display: flex;
            gap: 1.5rem;
            justify-content: flex-end;
        }
        .chart-box {
            background: #f8fafc;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(31,38,135,0.07);
            padding: 1rem 1.2rem;
            min-width: 220px;
            max-width: 320px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .chart-box canvas {
            max-width: 220px !important;
            max-height: 180px !important;
        }
        <?php endif; ?>
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }
        th, td {
            padding: 0.7rem 0.5rem;
            border: 1px solid #e0e0e0;
            text-align: center;
        }
        th {
            background: #f7f7f7;
            color: #222;
        }
        td:nth-child(7), td:nth-child(8) {
            text-align: right;
        }
        .badge {
            padding: 0.3em 1em;
            border-radius: 12px;
            color: #fff;
            font-weight: bold;
            font-size: 0.98em;
        }
        .badge-pending { background: #f39c12; }
        .badge-inprogress { background: #3498db; }
        .badge-completed { background: #2ecc71; }
        .badge-cancelled { background: #e74c3c; }
        .btn-update {
            background: #ffb347;
            color: #222;
            border: none;
            border-radius: 8px;
            padding: 0.4rem 1.2rem;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            margin: 0 0.2rem;
            transition: background 0.2s;
        }
        .btn-update:hover {
            background: #ffcc33;
        }
        .btn-action {
            background: #2ecc71;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.3rem 1rem;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            margin: 0 0.2rem;
            transition: background 0.2s;
        }
        .btn-action.cancel { background: #e74c3c; }
        .btn-action:hover { opacity: 0.85; }
        .order-detail {
            text-align: left;
            font-size: 0.98em;
            color: #555;
        }
        .search-bar {
            margin-bottom: 1.2rem;
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        .search-bar input, .search-bar select {
            padding: 0.3rem 0.7rem;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 1rem;
        }
        .filter-label { font-weight: bold; margin-right: 0.3rem; }
        .order-item-detail { color: #555; font-size: 0.97em; }
        .btn-export {
            background: #2ecc71;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.4rem 1.2rem;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            margin-left: 1rem;
        }
        .btn-print {
            background: #3498db;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.4rem 1.2rem;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            margin-left: 0.5rem;
        }
        .btn-export:hover {
            background: #27ae60;
        }
        .btn-print:hover {
            background: #2980b9;
        }
        .pagination {
            margin: 1.5rem 0 0 0;
            text-align: center;
        }
        .pagination a {
            display: inline-block;
            padding: 0.4rem 0.9rem;
            margin: 0 0.2rem;
            background: #eee;
            color: #222;
            border-radius: 6px;
            text-decoration: none;
        }
        .pagination a.active {
            background: #ffcc33;
            color: #222;
            font-weight: bold;
        }
        .order-detail-modal {
            display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.25); align-items: center; justify-content: center;
        }
        .order-detail-content {
            background: #fff; border-radius: 16px; padding: 2rem 2.5rem; text-align: left; font-size: 1.1rem; box-shadow: 0 4px 32px rgba(0,0,0,0.18);
            min-width: 320px;
        }
        .close-modal {
            float: right; font-size: 1.5rem; color: #e74c3c; cursor: pointer;
        }
        .print-only {
            display: none;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: #f8fafc;
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .stat-card h3 {
            margin: 0;
            font-size: 1rem;
            color: #555;
        }
        .stat-card .value {
            font-size: 1.8rem;
            font-weight: bold;
            color: #2d3a4b;
            margin: 0.5rem 0;
        }
        .stat-card .label {
            font-size: 0.9rem;
            color: #777;
        }
        .notification-badge {
            position: fixed;
            top: 10px;
            right: 10px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            z-index: 1000;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        /* 控制按钮样式 */
        .control-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            justify-content: center;
        }
        .btn-dashboard, .btn-food-manage, .btn-bulk-actions {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 0.8rem 1.5rem;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .btn-dashboard:hover, .btn-food-manage:hover, .btn-bulk-actions:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        /* 浮窗样式 */
        .dashboard-modal, .food-management-modal, .bulk-actions-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }
        .dashboard-content, .food-management-content, .bulk-actions-content {
            background: white;
            border-radius: 20px;
            max-width: 90%;
            max-height: 90%;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .dashboard-header, .food-management-header, .bulk-actions-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem 2rem;
            border-radius: 20px 20px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .close-dashboard, .close-food-management, .close-bulk-actions {
            background: none;
            border: none;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            padding: 0;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s;
        }
        .close-dashboard:hover, .close-food-management:hover, .close-bulk-actions:hover {
            background: rgba(255,255,255,0.2);
        }
        .dashboard-body, .food-management-body, .bulk-actions-body {
            padding: 2rem;
        }
        .charts-section {
            display: flex;
            gap: 2rem;
            margin-top: 2rem;
            justify-content: center;
        }
        
        /* 商品管理样式 */
        .food-list {
            max-height: 400px;
            overflow-y: auto;
        }
        .food-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            margin-bottom: 1rem;
            background: #f8f9fa;
        }
        .food-name {
            font-weight: bold;
            font-size: 1.1rem;
            color: #2d3a4b;
        }
        .food-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .availability-form {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .availability-form label {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            cursor: pointer;
        }
        .availability-form input[type="text"] {
            padding: 0.3rem 0.5rem;
            border: 1px solid #ccc;
            border-radius: 5px;
            width: 200px;
        }
        .availability-form button {
            background: #2ecc71;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 0.4rem 1rem;
            cursor: pointer;
        }
        .availability-form button:hover {
            background: #27ae60;
        }
        
        /* 批量操作样式 */
        .bulk-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }
        .bulk-action-item {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            border: 1px solid #e0e0e0;
        }
        .bulk-action-item h4 {
            margin: 0 0 1rem 0;
            color: #2d3a4b;
        }
        .bulk-action-item button, .bulk-action-item select {
            margin: 0.3rem;
            padding: 0.5rem 1rem;
            border: 1px solid #ccc;
            border-radius: 5px;
            cursor: pointer;
        }
        .bulk-action-item button {
            background: #3498db;
            color: white;
            border: none;
        }
        .bulk-action-item button:hover {
            background: #2980b9;
        }
        
        /* 表格容器样式 */
        .table-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .table-header {
            background: #f8f9fa;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e0e0e0;
        }
        .table-info {
            font-weight: bold;
            color: #2d3a4b;
        }
        .table-actions button {
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 0.4rem 1rem;
            margin-left: 0.5rem;
            cursor: pointer;
        }
        .table-actions button:hover {
            background: #5a6268;
        }
        
        /* 分页样式 */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            padding: 1.5rem;
            background: #f8f9fa;
        }
        .page-link {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: white;
            color: #2d3a4b;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s;
        }
        .page-link:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }
        .page-link.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        @media (max-width: 1100px) {
            .dashboard-row {
                flex-direction: column;
                gap: 1rem;
            }
            .dashboard-charts {
                flex-direction: row;
                justify-content: flex-start;
            }
        }
        @media (max-width: 700px) {
            .dashboard-charts {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            .chart-box {
                max-width: 100%;
                width: 100%;
            }
            .search-bar {
                flex-direction: column;
                align-items: flex-start;
            }
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
            body {
                margin: 0;
                padding: 15px;
                font-family: Arial, sans-serif;
                background: #fff;
                font-size: 12px;
            }
            .container {
                max-width: 100%;
                margin: 0;
                background: #fff;
                border: none;
                box-shadow: none;
                padding: 0;
            }
            table {
                width: 100%;
                font-size: 10px;
            }
            th, td {
                padding: 4px 3px;
            }
            h1 {
                font-size: 18px;
                margin-bottom: 10px;
            }
            .dashboard-row {
                margin-bottom: 10px;
            }
            .dashboard-summary, .chart-box {
                margin-bottom: 10px;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Order Management <?php if($print_mode) echo " - Daily Report ($filter_date)"; ?></h1>
        
        <!-- 控制按钮区域 -->
        <div class="control-buttons no-print">
            <button class="btn-dashboard" onclick="toggleDashboard()">📊 Dashboard</button>
            <button class="btn-food-manage" onclick="toggleFoodManagement()">🍽️ Food Management</button>
            <button class="btn-bulk-actions" onclick="toggleBulkActions()">⚡ Bulk Actions</button>
        </div>
        
        <!-- 报表浮窗 -->
        <div class="dashboard-modal" id="dashboard-modal">
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <h3>📊 Daily Dashboard (<?php echo $filter_date; ?>)</h3>
                    <button class="close-dashboard" onclick="toggleDashboard()">&times;</button>
                </div>
                <div class="dashboard-body">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h3>Total Orders</h3>
                            <div class="value"><?php echo $stats['order_count'] ?? 0; ?></div>
                            <div class="label">All orders placed</div>
                        </div>
                        <div class="stat-card">
                            <h3>Total Sales</h3>
                            <div class="value">SGD $<?php echo number_format($stats['total_sales'] ?? 0, 2); ?></div>
                            <div class="label">Net revenue</div>
                        </div>
                        <div class="stat-card">
                            <h3>Completed</h3>
                            <div class="value"><?php echo $stats['completed_orders'] ?? 0; ?></div>
                            <div class="label">Successful orders</div>
                        </div>
                        <div class="stat-card">
                            <h3>Cancelled</h3>
                            <div class="value"><?php echo $stats['cancelled_orders'] ?? 0; ?></div>
                            <div class="label">Cancelled orders</div>
                        </div>
                    </div>
                    <div class="charts-section">
                        <div class="chart-box">
                            <div style="font-size:1rem;font-weight:bold;margin-bottom:0.5rem;">Food Sales (Pie)</div>
                            <canvas id="foodChartPie"></canvas>
                        </div>
                        <div class="chart-box">
                            <div style="font-size:1rem;font-weight:bold;margin-bottom:0.5rem;">Food Sales (Bar)</div>
                            <canvas id="foodChartBar"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 商品管理浮窗 -->
        <div class="food-management-modal" id="food-management-modal">
            <div class="food-management-content">
                <div class="food-management-header">
                    <h3>🍽️ Food Availability Management</h3>
                    <button class="close-food-management" onclick="toggleFoodManagement()">&times;</button>
                </div>
                <div class="food-management-body">
                    <div class="food-list">
                        <?php
                        // 获取所有已订购的商品
                        $sql_all_foods = "SELECT DISTINCT food FROM order_items ORDER BY food";
                        $res_all_foods = mysqli_query($conn, $sql_all_foods);
                        while($food_row = mysqli_fetch_assoc($res_all_foods)){
                            $food_name = $food_row['food'];
                            $availability = isset($food_availability[$food_name]) ? $food_availability[$food_name] : ['is_available' => 1, 'unavailable_reason' => ''];
                            $is_available = $availability['is_available'];
                            $reason = $availability['unavailable_reason'];
                        ?>
                        <div class="food-item">
                            <div class="food-name"><?php echo htmlspecialchars($food_name); ?></div>
                            <div class="food-controls">
                                <form method="post" class="availability-form">
                                    <input type="hidden" name="food_name" value="<?php echo htmlspecialchars($food_name); ?>">
                                    <label>
                                        <input type="radio" name="is_available" value="1" <?php echo $is_available ? 'checked' : ''; ?>>
                                        Available
                                    </label>
                                    <label>
                                        <input type="radio" name="is_available" value="0" <?php echo !$is_available ? 'checked' : ''; ?>>
                                        Not Available
                                    </label>
                                    <input type="text" name="unavailable_reason" placeholder="Reason (if not available)" 
                                           value="<?php echo htmlspecialchars($reason); ?>" 
                                           style="display: <?php echo !$is_available ? 'block' : 'none'; ?>;">
                                    <button type="submit" name="update_availability">Update</button>
                                </form>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 批量操作浮窗 -->
        <div class="bulk-actions-modal" id="bulk-actions-modal">
            <div class="bulk-actions-content">
                <div class="bulk-actions-header">
                    <h3>⚡ Bulk Actions</h3>
                    <button class="close-bulk-actions" onclick="toggleBulkActions()">&times;</button>
                </div>
                <div class="bulk-actions-body">
                    <div class="bulk-actions-grid">
                        <div class="bulk-action-item">
                            <h4>Status Updates</h4>
                            <form method="post" id="bulk-status-form">
                                <select name="bulk_status">
                                    <option value="Pending">Set to Pending</option>
                                    <option value="In Progress">Set to In Progress</option>
                                    <option value="Completed">Set to Completed</option>
                                    <option value="Cancelled">Set to Cancelled</option>
                                </select>
                                <button type="button" onclick="submitBulkStatus()">Update Selected</button>
                            </form>
                        </div>
                        <div class="bulk-action-item">
                            <h4>Export Options</h4>
                            <button onclick="exportSelected()">Export Selected</button>
                            <button onclick="exportAll()">Export All</button>
                        </div>
                        <div class="bulk-action-item">
                            <h4>Quick Filters</h4>
                            <button onclick="filterToday()">Today's Orders</button>
                            <button onclick="filterPending()">Pending Only</button>
                            <button onclick="filterCompleted()">Completed Only</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 搜索/筛选 -->
        <form method="get" class="search-bar no-print" style="flex-wrap:wrap;gap:0.7rem 1rem;">
            <span class="filter-label">Order No:</span>
            <input type="text" name="order_no" value="<?php echo htmlspecialchars($order_no); ?>" placeholder="Order No">
            <span class="filter-label">Phone:</span>
            <input type="text" name="phone" value="<?php echo htmlspecialchars($phone); ?>" placeholder="Phone">
            <span class="filter-label">Food:</span>
            <input type="text" name="food" value="<?php echo htmlspecialchars($food_search); ?>" placeholder="Food Name">
            <span class="filter-label">Status:</span>
            <select name="status">
                <option value="">All</option>
                <option value="Pending" <?php if($status_filter=='Pending') echo 'selected'; ?>>Pending</option>
                <option value="In Progress" <?php if($status_filter=='In Progress') echo 'selected'; ?>>In Progress</option>
                <option value="Completed" <?php if($status_filter=='Completed') echo 'selected'; ?>>Completed</option>
                <option value="Cancelled" <?php if($status_filter=='Cancelled') echo 'selected'; ?>>Cancelled</option>
            </select>
            <span class="filter-label">Dine Type:</span>
            <select name="dine_type">
                <option value="">All</option>
                <option value="dine_in" <?php if($dine_type_filter=='dine_in') echo 'selected'; ?>>Dine In</option>
                <option value="take_away" <?php if($dine_type_filter=='take_away') echo 'selected'; ?>>Take Away</option>
            </select>
            <span class="filter-label">Date:</span>
            <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
            <button class="btn-update" type="submit">Filter</button>
            <button class="btn-export" type="submit" name="export" value="1">Export CSV</button>
            <button class="btn-print" type="submit" name='print_report' value='1'>Print Report</button>
            <?php if($print_mode): ?>
                <button class="btn-print" type="button" onclick="window.print()">Print Now</button>
            <?php endif; ?>
        </form>
        
        <!-- 订单表格 -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-info">
                    Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $per_page, $total_orders); ?> of <?php echo $total_orders; ?> orders
                </div>
                <div class="table-actions no-print">
                    <button onclick="selectAll()">Select All</button>
                    <button onclick="selectNone()">Select None</button>
                </div>
            </div>
            <table>
                <tr>
                    <th class="no-print"><input type="checkbox" id="select-all" onchange="toggleAll(this)"></th>
                    <th>Order No</th>
                    <th>Dine Type</th>
                    <th>Phone</th>
                    <th>Total (SGD)</th>
                    <th>Status</th>
                    <th>Order Date</th>
                    <th>Items</th>
                    <th class="no-print">Action</th>
                </tr>
            <?php
            if($res_orders && mysqli_num_rows($res_orders)>0){
                while($order = mysqli_fetch_assoc($res_orders)){
                    $order_id = $order['id'];
                    $order_no = $order['order_no'];
                    $dine_type = $order['dine_type'];
                    $phone = $order['phone'];
                    $total = $order['total'];
                    $status = $order['status'];
                    $order_date = $order['order_date'];
                    // 查询明细
                    $sql_items = "SELECT * FROM order_items WHERE order_id=$order_id";
                    if($food_search) {
                        $sql_items .= " AND food LIKE '%".mysqli_real_escape_string($conn, $food_search)."%'";
                    }
                    $res_items = mysqli_query($conn, $sql_items);
                    $items_html = '';
                    while($item = mysqli_fetch_assoc($res_items)){
                        $items_html .= '<div class="order-item-detail">';
                        $items_html .= htmlspecialchars($item['food']).' x'.$item['qty'].' ('.htmlspecialchars($item['noodle_type']);
                        if($item['add_noodle']) $items_html .= ' +Add Noodle';
                        if($item['add_ingredient']) $items_html .= ' +Add Ingredient';
                        if($item['add_prawn']) $items_html .= ' +Add Prawn';
                        if($item['add_pork_ribs']) $items_html .= ' +Add Pork Ribs';
                        if($item['add_meat_plattie']) $items_html .= ' +Add Meat Plattie';
                        // 展示所有自定义内容
                        if(!empty($item['custom'])) {
                            $custom = json_decode($item['custom'], true);
                            if(is_array($custom)) {
                                if(!empty($custom['soup_type'])) $items_html .= ', '.($custom['soup_type']=='dry'?'干':'汤');
                                if(!empty($custom['spicy'])) {
                                    $spicy_map = ['no'=>'不辣','mild'=>'微辣','normal'=>'正常','medium'=>'中辣','hot'=>'重辣'];
                                    $items_html .= ', '.($spicy_map[$custom['spicy']] ?? $custom['spicy']);
                                }
                                if(isset($custom['vinegar'])) $items_html .= ', '.($custom['vinegar']=='yes'?'要醋':'不要醋');
                                if(!empty($custom['taste'])) {
                                    $taste_map = ['light'=>'清淡','normal'=>'正常','strong'=>'重口'];
                                    $items_html .= ', '.($taste_map[$custom['taste']] ?? $custom['taste']);
                                }
                                if(!empty($custom['remark'])) $items_html .= ', 备注: '.htmlspecialchars($custom['remark']);
                                if(empty($item['add_prawn']) && !empty($custom['addPrawn'])) $items_html .= ' +Add Prawn';
                                if(empty($item['add_pork_ribs']) && !empty($custom['addPorkRibs'])) $items_html .= ' +Add Pork Ribs';
                                if(empty($item['add_meat_plattie']) && !empty($custom['addMeatPlattie'])) $items_html .= ' +Add Meat Plattie';
                            }
                        }
                        $items_html .= ')</div>';
                    }
                    // 状态badge
                    $badge = 'badge-pending';
                    if($status=='In Progress') $badge = 'badge-inprogress';
                    if($status=='Completed') $badge = 'badge-completed';
                    if($status=='Cancelled') $badge = 'badge-cancelled';
                    echo "<tr>";
                    echo "<td class='no-print'><input type='checkbox' name='order_ids[]' value='$order_id' class='order-checkbox'></td>";
                    echo "<td>$order_no</td>";
                    echo "<td>".ucfirst(str_replace('_',' ',$dine_type))."</td>";
                    echo "<td>$phone</td>";
                    echo "<td style='text-align:right;'>".number_format($total,2)."</td>";
                    echo "<td><span class='badge $badge'>$status</span></td>";
                    echo "<td>$order_date</td>";
                    echo "<td style='text-align:left;'>$items_html</td>";
                    echo "<td class='no-print'>";
                    // 一键完成/取消
                    if($status!='Completed' && $status!='Cancelled') {
                        echo "<form method='post' style='display:inline;'><input type='hidden' name='order_id' value='$order_id'><input type='hidden' name='status' value='Completed'><button class='btn-action' name='update_status'>完成</button></form>";
                        echo "<form method='post' style='display:inline;'><input type='hidden' name='order_id' value='$order_id'><input type='hidden' name='status' value='Cancelled'><button class='btn-action cancel' name='update_status'>取消</button></form>";
                    } else {
                        echo "-";
                    }
                    echo "</td>";
                    echo "</tr>";
                }
            }else{
                echo "<tr><td colspan='9'>No orders found.</td></tr>";
            }
            ?>
            </table>
            
            <!-- 分页导航 -->
            <?php if($total_pages > 1): ?>
            <div class="pagination no-print">
                <?php
                $current_url = $_SERVER['REQUEST_URI'];
                $url_parts = parse_url($current_url);
                parse_str($url_parts['query'] ?? '', $query_params);
                
                // 上一页
                if($page > 1):
                    $query_params['page'] = $page - 1;
                    $prev_url = $url_parts['path'] . '?' . http_build_query($query_params);
                ?>
                <a href="<?php echo $prev_url; ?>" class="page-link">« Previous</a>
                <?php endif; ?>
                
                <?php
                // 页码
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for($i = $start_page; $i <= $end_page; $i++):
                    $query_params['page'] = $i;
                    $page_url = $url_parts['path'] . '?' . http_build_query($query_params);
                    $active_class = ($i == $page) ? 'active' : '';
                ?>
                <a href="<?php echo $page_url; ?>" class="page-link <?php echo $active_class; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                
                <?php
                // 下一页
                if($page < $total_pages):
                    $query_params['page'] = $page + 1;
                    $next_url = $url_parts['path'] . '?' . http_build_query($query_params);
                ?>
                <a href="<?php echo $next_url; ?>" class="page-link">Next »</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Chart.js 饼图+柱状图 -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            const foodStats = <?php echo json_encode($food_stats); ?>;
            const labels = foodStats.map(i => i.food);
            const data = foodStats.map(i => Number(i.qty));
            
            // 只在非打印模式下渲染图表
            if(!<?php echo $print_mode ? 'true' : 'false'; ?>) {
                // Pie Chart
                new Chart(document.getElementById('foodChartPie').getContext('2d'), {
                    type: 'pie',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Food Sales',
                            data: data,
                            backgroundColor: [
                                '#ffb347','#ffcc33','#2ecc71','#3498db','#e74c3c','#9b59b6','#f39c12'
                            ]
                        }]
                    },
                    options: {
                        plugins: { legend: { position: 'bottom' } },
                        responsive: false,
                        maintainAspectRatio: false
                    }
                });
                // Bar Chart
                new Chart(document.getElementById('foodChartBar').getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Food Sales',
                            data: data,
                            backgroundColor: [
                                '#ffb347','#ffcc33','#2ecc71','#3498db','#e74c3c','#9b59b6','#f39c12'
                            ]
                        }]
                    },
                    options: {
                        plugins: { legend: { display: false } },
                        responsive: false,
                        maintainAspectRatio: false,
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });
            }
        </script>
        
        <!-- 订单详情弹窗 -->
        <div class="order-detail-modal" id="order-detail-modal">
            <div class="order-detail-content" id="order-detail-content">
                <span class="close-modal" onclick="closeDetail()">&times;</span>
                <div id="order-detail-body"></div>
            </div>
        </div>
        
        <!-- 打印报表时的额外信息 -->
        <?php if($print_mode): ?>
        <div class="print-only">
            <h3>Daily Sales Report - <?php echo $filter_date; ?></h3>
            <p>Generated on: <?php echo date('Y-m-d H:i:s'); ?></p>
            <p>Total Orders: <?php echo $stats['order_count'] ?? 0; ?></p>
            <p>Total Sales: SGD $<?php echo number_format($stats['total_sales'] ?? 0, 2); ?></p>
            <p>Completed Orders: <?php echo $stats['completed_orders'] ?? 0; ?></p>
            <p>Cancelled Orders: <?php echo $stats['cancelled_orders'] ?? 0; ?></p>
            
            <h4>Top Selling Items</h4>
            <table>
                <tr>
                    <th>Food Item</th>
                    <th>Quantity</th>
                </tr>
                <?php foreach($food_stats as $item): ?>
                <tr>
                    <td><?php echo $item['food']; ?></td>
                    <td><?php echo $item['qty']; ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        // 浮窗控制函数
        function toggleDashboard() {
            const modal = document.getElementById('dashboard-modal');
            modal.style.display = modal.style.display === 'flex' ? 'none' : 'flex';
        }
        
        function toggleFoodManagement() {
            const modal = document.getElementById('food-management-modal');
            modal.style.display = modal.style.display === 'flex' ? 'none' : 'flex';
        }
        
        function toggleBulkActions() {
            const modal = document.getElementById('bulk-actions-modal');
            modal.style.display = modal.style.display === 'flex' ? 'none' : 'flex';
        }
        
        // 全选/取消全选功能
        function toggleAll(box) {
            var cbs = document.querySelectorAll('input[type=checkbox][name="order_ids[]"]');
            cbs.forEach(function(cb) { cb.checked = box.checked; });
        }
        
        function selectAll() {
            var cbs = document.querySelectorAll('input[type=checkbox][name="order_ids[]"]');
            cbs.forEach(function(cb) { cb.checked = true; });
            document.getElementById('select-all').checked = true;
        }
        
        function selectNone() {
            var cbs = document.querySelectorAll('input[type=checkbox][name="order_ids[]"]');
            cbs.forEach(function(cb) { cb.checked = false; });
            document.getElementById('select-all').checked = false;
        }
        
        // 商品可用性表单处理
        function toggleReasonInput(radio) {
            const form = radio.closest('form');
            const reasonInput = form.querySelector('input[name="unavailable_reason"]');
            if (radio.value === '0') {
                reasonInput.style.display = 'block';
                reasonInput.required = true;
            } else {
                reasonInput.style.display = 'none';
                reasonInput.required = false;
            }
        }
        
        // 批量操作功能
        function submitBulkStatus() {
            const selected = document.querySelectorAll('input[name="order_ids[]"]:checked');
            if (selected.length === 0) {
                alert('Please select orders to update');
                return;
            }
            
            const form = document.getElementById('bulk-status-form');
            const orderIds = Array.from(selected).map(cb => cb.value);
            
            // 添加隐藏的订单ID字段
            orderIds.forEach(function(id) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'order_ids[]';
                input.value = id;
                form.appendChild(input);
            });
            
            form.submit();
        }
        
        function exportSelected() {
            const selected = document.querySelectorAll('input[name="order_ids[]"]:checked');
            if (selected.length === 0) {
                alert('Please select orders to export');
                return;
            }
            const orderIds = Array.from(selected).map(cb => cb.value).join(',');
            window.location.href = '?export=1&order_ids=' + orderIds;
        }
        
        function exportAll() {
            window.location.href = '?export=1';
        }
        
        function filterToday() {
            const today = new Date().toISOString().split('T')[0];
            window.location.href = '?date=' + today;
        }
        
        function filterPending() {
            window.location.href = '?status=Pending';
        }
        
        function filterCompleted() {
            window.location.href = '?status=Completed';
        }
        
        // 订单详情功能
        function showDetail(row) {
            var html = '';
            for(var k in row) {
                html += '<b>' + k + ':</b> ' + row[k] + '<br>';
            }
            document.getElementById('order-detail-body').innerHTML = html;
            document.getElementById('order-detail-modal').style.display = 'flex';
        }
        
        function closeDetail() {
            document.getElementById('order-detail-modal').style.display = 'none';
        }
        
        // 点击浮窗外部关闭
        window.onclick = function(event) {
            const dashboardModal = document.getElementById('dashboard-modal');
            const foodModal = document.getElementById('food-management-modal');
            const bulkModal = document.getElementById('bulk-actions-modal');
            
            if (event.target === dashboardModal) {
                dashboardModal.style.display = 'none';
            }
            if (event.target === foodModal) {
                foodModal.style.display = 'none';
            }
            if (event.target === bulkModal) {
                bulkModal.style.display = 'none';
            }
        }
        
        // 自动打印功能
        <?php if($print_mode): ?>
        window.onload = function() {
            window.print();
        }
        <?php endif; ?>
        
        // 初始化商品可用性表单
        document.addEventListener('DOMContentLoaded', function() {
            const radioButtons = document.querySelectorAll('input[name="is_available"]');
            radioButtons.forEach(function(radio) {
                radio.addEventListener('change', function() {
                    toggleReasonInput(this);
                });
                // 初始化状态
                toggleReasonInput(radio);
            });
        });
    </script>
</body>
</html>