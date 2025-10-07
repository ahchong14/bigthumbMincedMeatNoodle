<?php
// lang.php - 语言包（请用此文件替换你现在的 lang.php）

// 安全启动 session（如果还没启动）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 所有语言数据
$langs = [
    'en' => [
        'dine_in' => 'Dine In',
        'take_away' => 'Take Away',
        'choose_type' => 'Please select:',
        'welcome' => 'Welcome to Big Thumb Minced Meat Noodle',
        'menu' => 'Menu',
        'signature_noodle' => 'Signature Noodle',
        'minced_meat_noodle' => 'Minced Meat Noodle',
        'fishball_noodle' => 'Fishball Noodle',
        'fishball_minced_noodle' => 'Fishball Minced Meat Noodle',
        'fishball_soup' => 'Fishball Soup',
        'curry_chicken_noodle' => 'Curry Chicken Noodle',
        'curry_fishball_noodle' => 'Curry Fishball Noodle',
        'pork_ribs_noodle' => 'Pork Ribs Noodle',

        'choose_noodle_type' => 'Choose Noodle Type',
        'yellow_noodle' => 'Yellow Noodle',
        'laoshu_fen' => 'Lao Shu Fen',
        'bee_hoon' => 'Bee Hoon',
        'mee_tai_mak' => 'Mee Tai Mak',
        'mee_kia' => 'Mee Kia',
        'mee_pok' => 'Mee Pok',
        'kway_teow' => 'Kway Teow',
        'bee_hoon mixed_yellow_noodle' => 'Bee Hoon Mixed Yellow Noodle',
        'kway_teow mixed_yellow_noodle' => 'Kway Teow Mixed Yellow Noodle',

        'add_noodle' => 'Extra Noodle (+$1)',
        'add_ingredient' => 'Extra Ingredients (+$2)',

        // addons
        'add_prawn' => 'Add Prawn (+$3)',
        'add_pork_ribs' => 'Add Pork Ribs (+$3)',
        'add_meat_patty' => 'Add Meat Patty (+$3)',

        'order_now' => 'Order Now',
        'price' => 'Price',
        'qty' => 'Quantity',
        'place_order' => 'Place Order',
        'total' => 'Total',
        'back_menu' => 'Back to Menu',
        'language' => 'Language',
        'sgd' => 'SGD',
        'clear_cart' => 'Clear',
        'confirm_order' => 'Confirm Order',
        'your_cart' => 'Cart',
        'add_to_cart' => 'Add to Cart',
        'remark' => 'Remark',

                // 免选项
    'without_pork_liver'     => 'No Pork Liver',
    'without_minced_meat'    => 'No Minced Meat',
    'without_fishball'       => 'No Fishball',
    'without_sliced_meat'    => 'No Sliced Meat',
    'without_fried_shallots' => 'No Fried Shallots',
    'without_ginger_onion'   => 'No Ginger Onion',
    'without_ketchup'         => 'No Ketchup',


    'title' => 'Title',
    'price' => 'Price', 
    'description' => 'Description',
    'image' => 'Image',

    'self_container' => 'Self Container',
    'restaurant_container' => 'Restaurant Container',
    'plastic_bag' => 'Plastic Bag',

    ],
    'zh' => [
        'dine_in' => '堂食',
        'take_away' => '外带',
        'choose_type' => '请选择：',
        'welcome' => '欢迎来到大拇指肉脞面',
        'menu' => '菜单',
        'signature_noodle' => '招牌面',
        'minced_meat_noodle' => '肉脞面',
        'fishball_noodle' => '鱼丸面',
        'fishball_minced_noodle' => '鱼丸肉脞面',
        'fishball_soup' => '鱼丸汤',
        'curry_chicken_noodle' => '咖喱鸡面',
        'curry_fishball_noodle' => '咖喱鱼丸面',
        'pork_ribs_noodle' => '排骨面',
        
        'choose_noodle_type' => '选择面类',
        'yellow_noodle' => '黄面',
        'laoshu_fen' => '老鼠粉',
        'bee_hoon' => '米粉',
        'mee_tai_mak' => '米台目',
        'mee_kia' => '面仔',
        'mee_pok' => '面薄',
        'kway_teow' => '果条',
        'bee_hoon mixed_yellow_noodle' => '米粉面',
        'kway_teow mixed_yellow_noodle' => '粿条面',

        'add_noodle' => '加粉（+$1）',
        'add_ingredient' => '加料（+$2）',

        // addons
        'add_prawn' => '加虾（+$3）',
        'add_pork_ribs' => '加排骨（+$3）',
        'add_meat_patty' => '加肉饼（+$3）',

        'order_now' => '立即下单',
        'price' => '价格',
        'qty' => '数量',
        'place_order' => '下单',
        'total' => '总价',
        'back_menu' => '返回菜单',
        'language' => '语言',
        'sgd' => '新币',
        'clear_cart' => '清除',
        'confirm_order' => '确认订单',
        'your_cart' => '购物车',
        'add_to_cart' => '加入购物车',
        'remark' => '备注',
        // 免选项
    'without_pork_liver'     => '免猪肝',
    'without_minced_meat'    => '免肉碎',
    'without_fishball'       => '免鱼丸',
    'without_sliced_meat'    => '免肉片',
    'without_fried_shallots' => '免炸葱',
    'without_ginger_onion'   => '免姜葱',
    'without_ketchup'         => '免番茄酱',


    'title' => '标题',
    'price' => '价格',  
    'description' => '描述',
    'image' => '图片',

    'self_container' => '自带盒子',
    'restaurant_container' => '餐厅盒子',
    'plastic_bag' => '塑料袋',

   
    ]
];

// 选择语言：GET ?lang=zh/en 优先，其次 session 存储，否则默认 en
$lang = 'en';
if (isset($_GET['lang']) && $_GET['lang'] === 'zh') {
    $lang = 'zh';
    $_SESSION['lang'] = $lang;
} elseif (isset($_GET['lang']) && $_GET['lang'] === 'en') {
    $lang = 'en';
    $_SESSION['lang'] = $lang;
} elseif (isset($_SESSION['lang'])) {
    $lang = $_SESSION['lang'];
}

// 容错：如果请求的语言不存在，回退到 'en'
if (!isset($langs[$lang])) {
    $lang = 'en';
}

// 导出两个变量：$t（当前语言映射），$lang_data（所有语言集合，兼容旧代码）
$t = $langs[$lang];
$lang_data = $langs; // 兼容需要 $lang_data 的旧代码
