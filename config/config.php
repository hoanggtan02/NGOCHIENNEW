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
            "url" => "images/personnels/",
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
        "name" => 'Thử việc',
        "id" => 1,
    ],
    "2" => [
        "name" => 'Chính thức lần 1',
        "id" => 2,
    ],
    "3" => [
        "name" => 'Chính thức lần 2',
        "id" => 3,
    ],
    "4" => [
        "name" => 'Xác định thời hạn',
        "id" => 4,
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
        "url" => 'https://test.ngochienpearl.com',
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
        "site_vat" => '10',
    ]
];
