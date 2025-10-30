<?php
if (!defined('ECLO'))
    die("Hacking attempt");

$env = parse_ini_file(__DIR__ . '/../.env');
$api_transports = [
    "1" => [
        "id" => 1,
        "name" => "Không Tích Hợp",
        "code" => 'KTH',
        "logo" => '',
    ],
    "2" => [
        "id" => 2,
        "name" => "Giao Hàng Nhanh",
        "code" => 'GHN',
        "logo" => '',
        "API" => [
            "token" => 'Token',
        ],
    ],
];
$ballot_code = [
    "purchase" => 'ĐXMH',
    "projects" => 'DA',
    "invoices" => 'HĐ',
];
$payment_type = [
    "1" => [
        "name" => 'Bán hàng',
        "id" => 1,
    ],
    "2" => [
        "name" => 'Trả hàng',
        "id" => 2,
    ],
    "3" => [
        "name" => 'Bảo hành',
        "id" => 3,
    ],
];
$expenditure_type = [
    "1" => [
        "name" => "Thu",
        "id" => 1,
    ],
    "2" => [
        "name" => "Chi",
        "id" => 2,
    ],
];
$upload = [
    "images" => [
        "avatar" => [
            "url" => "images/accounts/",
            "thumb_y" => "300",
            "thumb_x" => "300",
        ],
        "products" => [
            "url" => "images/products/",
            "thumb_y" => "300",
            "thumb_x" => "300",
        ],
        "personnels" => [
            "url" => "datas/personnels/",
            "thumb_y" => "500",
            "thumb_x" => "500",
        ],
        "customers" => [
            "url" => "images/customers/",
            "thumb_y" => "500",
            "thumb_x" => "500",
        ],
        "logo" => [
            "url" => "images/logo/",
        ]
    ]
];
$salarys_type_categorys = [
    "1" => [
        "name" => 'Tiền lương',
        "id" => 1,
    ],
    "2" => [
        "name" => 'Phụ cấp',
        "id" => 2,
    ],
    "3" => [
        "name" => 'Tăng ca',
        "id" => 3,
    ],
];
$duration_types = [
    "1" => [
        "name" => 'Giờ',
        "code" => 'hour',
        "id" => 1,
    ],
    "2" => [
        "name" => 'Phút',
        "code" => 'minute',
        "id" => 2,
    ],
    "3" => [
        "name" => 'Ngày',
        "code" => 'day',
        "id" => 3,
    ],
    "4" => [
        "name" => 'Tháng',
        "code" => 'month',
        "id" => 4,
    ],
    "5" => [
        "name" => 'Năm',
        "code" => 'year',
        "id" => 5,
    ],
];
$personnels_contracts = [
    "1" => [
        "name" => 'Xac định thời hạn',
        "id" => 1,
    ],
    "2" => [
        "name" => 'Không xác định thời hạn',
        "id" => 2,
    ],
    "3" => [
        "name" => 'Thử việc',
        "id" => 3,
    ],
];

$Status_invoices = [
    "1" => [
        "id" => 1,
        "main" => 0,
        "name" => 'Đã thanh toán',
        "color" => "success"
    ],
    "2" => [
        "id" => 2,
        "main" => 0,
        "name" => 'Công nợ',
        "color" => "danger"
    ],
];


$Status_purchase = [
    "1" => [
        "id" => 1,
        "main" => 0,
        "name" => "Đề xuất",
        "color" => "warning"
    ],
    // "2"=>[
    // 	"id"=>2,
    // 	"main"=>0,
    // 	"name"=>"Kế toán đã duyệt",
    // 	"color"=>"primary"
    // ],
    "3" => [
        "id" => 3,
        "main" => 0,
        "name" => "Đã duyệt",
        "color" => "success"
    ],
    "4" => [
        "id" => 4,
        "main" => 0,
        "name" => "Không duyệt",
        "color" => "secondary"
    ],
    "5" => [
        "id" => 5,
        "main" => 0,
        "name" => "Đã nhập hàng",
        "color" => "primary"
    ],

];
$type_ticket = [
    "1" => [
        "id" => 1,
        "name" => "Vé vip",
        "price" => 1950000,
    ],
    "2" => [
        "id" => 2,
        "name" => "Vé tiêu chuẩn",
        "price" => 1550000,
    ],
    "3" => [
        "id" => 3,
        "name" => "Vé trải nghiệm",
        "price" => 1250000,
    ],
];  

$ingredient_cookie_config = [
    'name' => 'ingredient_import',
    'path' => '/',
    'expire' => time() + 3600, // Hết hạn sau 1 ngày
];

$Status_warehouser_move = [
    "1"=>[
        "id"=>1,
        "main"=>0,
        "name"=> "Chưa nhận hàng",
        "color"=>"warning"
    ],
    "2"=>[
        "id"=>2,
        "main"=>0,
        "name"=> "Đã nhận hàng",
        "color"=>"primary"
    ],
    "3"=>[
        "id"=>3,
        "main"=>0,
        "name"=> "Trả hàng",
        "color"=>"danger"
    ],
    "4"=>[
        "id"=>4,
        "main"=>0,
        "name"=> "Đã trả hàng",
        "color"=>"success"
    ],
    "5"=>[
        "id"=>5,
        "main"=>0,
        "name"=>"Không nhận hàng",
        "color"=>"danger"
    ],
];


if (!function_exists('get_ingredient_cookie_data')) {
    function get_ingredient_cookie_data($app) {
        // Lấy $setting (là mảng 'app' từ config)
        $setting = $app->getValueData('setting'); 

        // Tự động đọc tên cookie từ config
        $cookie_name = $setting['ingredient_cookie']['name'] ?? 'ingredient_import'; 

        $cookie_json = $app->getCookie($cookie_name); 
        $cookie_data = $cookie_json ? json_decode($cookie_json, true) : null;

        if (!is_array($cookie_data) || !isset($cookie_data['ingredients'])) {
            $cookie_data = ['ingredients' => [], 'content' => ''];
        }
        return $cookie_data;
    }
}


$ingredient_move_cookie_config = [
    'name' => 'ingredient_move', 
    'path' => '/',
    'expire' => time() + 3600, 
];


if (!function_exists('get_ingredient_move_cookie_data')) {
    function get_ingredient_move_cookie_data($app) {
        $setting = $app->getValueData('setting'); 
        
        // Tự động đọc tên cookie từ config
        $cookie_name = $setting['ingredient_move_cookie']['name'] ?? 'ingredient_move'; 
        
        $cookie_json = $app->getCookie($cookie_name); 
        $cookie_data = $cookie_json ? json_decode($cookie_json, true) : null;
        
        // Cấu trúc mặc định cho phiếu chuyển kho
        $defaults = [
            'ingredients' => [],
            'content' => '',
            'store_id' => null,
            'branch_id' => null,
            'date' => date("Y-m-d"),
        ];

        if (!is_array($cookie_data)) {
            $cookie_data = $defaults;
        } else {
            // Đảm bảo tất cả các key đều tồn tại
            $cookie_data = array_merge($defaults, $cookie_data);
        }
        
        return $cookie_data;
    }
}



$ingredient_export_cookie_config = [
    'name' => 'ingredient_export', 
    'path' => '/',
    'expire' => time() + 3600, 
];


if (!function_exists('get_ingredient_export_cookie_data')) {
    function get_ingredient_export_cookie_data($app) {
        $setting = $app->getValueData('setting'); 
        
        // Tự động đọc tên cookie từ config
        $cookie_name = $setting['ingredient_export_cookie']['name'] ?? 'ingredient_export'; 
        
        $cookie_json = $app->getCookie($cookie_name); 
        $cookie_data = $cookie_json ? json_decode($cookie_json, true) : null;
        
        // Cấu trúc mặc định cho phiếu xuất kho
        $defaults = [
            'ingredients' => [],
            'content' => '',
            'group_crafting' => '', 
            'date' => date("Y-m-d"),
        ];

        if (!is_array($cookie_data)) {
            $cookie_data = $defaults;
        } else {
            // Đảm bảo tất cả các key đều tồn tại
            $cookie_data = array_merge($defaults, $cookie_data);
        }
        
        return $cookie_data;
    }
}

$warehouse_ticket_cookie_config = [
    'name' => 'warehouse_ticket', // Tên cookie gốc
    'path' => '/',
    'expire' => time() + 86400, // 1 ngày
];
if (!function_exists('get_warehouse_ticket_cookie_data')) {
    /**
     * Lấy dữ liệu cookie cho một phiếu kho cụ thể (import, move, cancel).
     * Tên cookie thực tế sẽ là warehouse_ticket_import, warehouse_ticket_move,...
     */
    function get_warehouse_ticket_cookie_data($app, $action) { // $action là 'import', 'move', 'cancel'
        $setting = $app->getValueData('setting');
        // Lấy config gốc
        $base_config = $setting['warehouse_ticket_cookie'] ?? ['name' => 'warehouse_ticket'];
        // Tạo tên cookie động
        $cookie_name = $base_config['name'] . '_' . $action;

        $cookie_json = $app->getCookie($cookie_name);
        $cookie_data = $cookie_json ? json_decode($cookie_json, true) : null;

        // Cấu trúc mặc định, bao gồm tất cả các key có thể có
        $defaults = [
            'type' => $action,          // Loại phiếu hiện tại (import/move/cancel)
            'data' => 'products',       // Loại dữ liệu (products/ingredient)
            'date' => date("Y-m-d"),
            'content' => '',
            'stores' => null,           // array ['id', 'name']
            'branch' => null,           // array ['id', 'name']
            'products' => [],           // array [product_id => [details...]]
            'import_type' => '',        // Dùng cho import: 'purchase', 'move', ''
            'purchase' => null,         // ID phiếu mua (cho import)
            'move' => null,             // ID phiếu chuyển (cho import)
            'vendor' => null,           // array vendor info (cho import)
            'stores_receive' => null,   // array cửa hàng nhận (cho move)
            'branch_receive' => null,   // array quầy nhận (cho move)
            'stores_receive_re' => null,// ID cửa hàng nhận (cho import from move) - Giữ key cũ của bạn
            'warehouses' => [],         // array lô hàng (cho cancel) [batch_id => [details...]]
            // Thêm các key khác nếu logic cũ của bạn dùng đến
        ];

        if (!is_array($cookie_data)) {
            // Nếu cookie không tồn tại hoặc lỗi decode, trả về cấu trúc default
            $cookie_data = $defaults;
        } else {
            // Nếu có cookie, hợp nhất với default để đảm bảo mọi key đều tồn tại
            $cookie_data = array_merge($defaults, $cookie_data);
             // Quan trọng: Đảm bảo 'type' luôn là $action hiện tại để tránh nhầm lẫn
             $cookie_data['type'] = $action;
        }
        return $cookie_data;
    }
}


return [
    "db" => [
        'type' => $env['DB_TYPE'] ?? 'mysql',
        'host' => $env['DB_HOST'] ?? 'localhost',
        'database' => $env['DB_DATABASE'] ?? 'default_database',
        'username' => $env['DB_USERNAME'] ?? 'default_user',
        'password' => $env['DB_PASSWORD'] ?? '',
        'charset' => $env['DB_CHARSET'] ?? 'utf8mb4',
        'collation' => $env['DB_COLLATION'] ?? 'utf8mb4_general_ci',
        'port' => (int) ($env['DB_PORT'] ?? 3306),
        'prefix' => $env['DB_PREFIX'] ?? '',
        'logging' => filter_var($env['DB_LOGGING'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
        'error' => constant('PDO::' . ($env['DB_ERROR'] ?? 'ERRMODE_SILENT')),
        // 'error' => PDO::ERRMODE_EXCEPTION,
        'option' => [
            PDO::ATTR_CASE => PDO::CASE_NATURAL,
        ],
        'command' => [
            'SET SQL_MODE=ANSI_QUOTES'
        ]
    ],
    "app" => [
        "url" => 'http://ngochiennew.eclo.io',
        "name" => 'Ngọc Hiền Pearl ERP',
        "page" => 12,
        "manager" => '',
        "template" => '../templates',
        "secret-key" => '19a3d43a4df700dc5d35f6a7a69e5e79d522d91784e66bdaa2fa475731ae0abc31363138323237313233',
        "verifier" => 'emejRcfqO2sFkARMmUy0tvE003Y3i9tyVNwcaE4J7Y7',
        "cookie" => (3600 * 24 * 30) * 12, // 1 năm
        "lang" => $_COOKIE['lang'] ?? 'vi',
        "plugins" => '../plugins',
        "uploads" => '../public/datas',
        "load" => '../datas',
        "api" => $api_transports,
        "type-payment" => $payment_type,
        "upload" => $upload,
        "expenditure_type" => $expenditure_type,
        "ballot_code" => $ballot_code,
        "salarys_type_categorys" => $salarys_type_categorys,
        "duration_types" => $duration_types,
        "personnels_contracts" => $personnels_contracts,
        "status_invoices" => $Status_invoices,
        "Status_invoices" => $Status_invoices,
        "Status_purchase" => $Status_purchase,
        "ticket_types" => $type_ticket,
        "Status_warehouser_move" => $Status_warehouser_move,
        "site_vat" => '10',
        "ingredient_cookie" => $ingredient_cookie_config,
        "ingredient_move_cookie" => $ingredient_move_cookie_config,
        "ingredient_export_cookie" => $ingredient_export_cookie_config,
        "warehouse_ticket_cookie" => $warehouse_ticket_cookie_config,
    ]
];


