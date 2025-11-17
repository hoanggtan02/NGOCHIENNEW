<?php

$jatbi = $app->getValueData('jatbi');
$setting = $app->getValueData('setting');
return [
    "content" => [
        "item" => [
            'purchases' => [
                "menu" => $jatbi->lang("Mua hàng"),
                "url" => '/purchases',
                "icon" => '<i class="ti ti-shopping-cart"></i>',
                "sub" => [
                    'purchase' => [
                        "name" => $jatbi->lang("Đề xuất mua hàng"),
                        "router" => '/purchases/purchase',
                        "icon" => '<i class="ti ti-file-text"></i>',
                    ],
                    'vendors' => [
                        "name" => $jatbi->lang("Nhà cung cấp"),
                        "router" => '/purchases/vendors',
                        "icon" => '<i class="ti ti-truck-delivery"></i>',
                    ],
                    'vendors-types' => [
                        "name" => $jatbi->lang("Loại nhà cung cấp"),
                        "router" => '/purchases/vendors-types',
                        "icon" => '<i class="ti ti-tags"></i>',
                    ],
                ],
                "main" => 'false',
                "permission" => [
                    'purchase' => $jatbi->lang("Xem đề xuất mua hàng"),
                    'purchase.add' => $jatbi->lang("Thêm đề xuất mua hàng"),
                    'purchase.edit' => $jatbi->lang("Sửa đề xuất mua hàng"),
                    'purchase.deleted' => $jatbi->lang("Xóa đề xuất mua hàng"),
                    'purchase.approve' => $jatbi->lang("Duyệt đề xuất mua hàng"),

                    'vendors' => $jatbi->lang("Xem nhà cung cấp"),
                    'vendors.add' => $jatbi->lang("Thêm nhà cung cấp"),
                    'vendors.edit' => $jatbi->lang("Sửa nhà cung cấp"),
                    'vendors.deleted' => $jatbi->lang("Xóa nhà cung cấp"),

                    'vendors-types' => $jatbi->lang("Xem loại nhà cung cấp"),
                    'vendors-types.add' => $jatbi->lang("Thêm loại nhà cung cấp"),
                    'vendors-types.edit' => $jatbi->lang("Sửa loại nhà cung cấp"),
                    'vendors-types.deleted' => $jatbi->lang("Xóa loại nhà cung cấp"),
                ]
            ],
        ],
    ],
];
