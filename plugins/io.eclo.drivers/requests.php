<?php
$jatbi = $app->getValueData('jatbi');
$setting = $app->getValueData('setting');
return [
    "content" => [
        "item" => [
            'drivers' => [
                "menu" => $jatbi->lang("Nội bộ"),
                "url" => '/drivers',
                "icon" => '<i class="ti ti-steering-wheel"></i>',
                "sub" => [
                    'invoices' => [
                        "name" => $jatbi->lang("Đơn hàng"),
                        "router" => '/drivers/invoices',
                        "icon" => '<i class="ti ti-file-text"></i>',
                    ],
                    'driver' => [
                        "name" => $jatbi->lang("Thông tin tài xế"),
                        "router" => '/drivers/driver',
                        "icon" => '<i class="ti ti-user-circle"></i>',
                    ],
                    'driver-payment' => [
                        "name" => $jatbi->lang("Thanh toán tài xế"),
                        "router" => '/drivers/driver-payment',
                        "icon" => '<i class="ti ti-receipt"></i>',
                    ],
                    'other_commission_costs' => [
                        "name" => $jatbi->lang("Thanh toán hoa hồng khác"),
                        "router" => '/drivers/other_commission_costs',
                        "icon" => '<i class="ti ti-wallet"></i>',
                    ],
                ],
                "main" => 'false',
                "permission" => [
                    'driver-invoices' => $jatbi->lang("Đơn hàng"),

                    'invoices' => $jatbi->lang("Xem đơn hàng nội bộ"),
                    'invoices.add' => $jatbi->lang("Thêm đơn hàng nội bộ"),
                    'invoices.edit' => $jatbi->lang("Sửa đơn hàng nội bộ"),
                    'invoices.deleted' => $jatbi->lang("Xóa đơn hàng nội bộ"),

                    'driver' => $jatbi->lang("Xem thông tin tài xế"),
                    'driver.add' => $jatbi->lang("Thêm thông tin tài xế"),
                    'driver.edit' => $jatbi->lang("Sửa thông tin tài xế"),
                    'driver.deleted' => $jatbi->lang("Xóa thông tin tài xế"),

                    'driver-payment' => $jatbi->lang("Xem thanh toán tài xế"),
                    'driver-payment.add' => $jatbi->lang("Tạo thanh toán tài xế"),
                    'driver-payment.edit' => $jatbi->lang("Sửa thanh toán tài xế"),
                    'driver-payment.deleted' => $jatbi->lang("Xóa thanh toán tài xế"),
                    'driver-payment.confirm' => $jatbi->lang("Xác nhận thanh toán tài xế"),

                    'other_commission_costs' => $jatbi->lang("Xem thanh toán hoa hồng khác"),
                    'other_commission_costs.add' => $jatbi->lang("Tạo thanh toán hoa hồng khác"),
                    'other_commission_costs.edit' => $jatbi->lang("Sửa thanh toán hoa hồng khác"),
                    'other_commission_costs.deleted' => $jatbi->lang("Xóa thanh toán hoa hồng khác"),
                ]
            ],
        ],
    ],
];
