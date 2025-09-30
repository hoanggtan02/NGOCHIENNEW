<?php
    $jatbi = $app->getValueData('jatbi');
    $setting = $app->getValueData('setting');
    return [
        "content" => [
            "item" => [
          'stores' => [
                "menu" => $jatbi->lang("Cửa hàng"),
                "url" => '/stores/',
                "icon" => '<i class="ti ti-home"></i>',
                "sub" => [
                    'stores' => [
                        "name" => $jatbi->lang("Cửa hàng"),
                        "router" => '/stores/stores',
                        "icon" => '<i class="fas fa-building"></i>',
                    ],
                    'branch' => [
                        "name" => $jatbi->lang("Quầy hàng"),
                        "router" => '/stores/branch',
                        "icon" => '<i class="fas fa-store-alt"></i>',
                    ],
                    'stores-types' => [
                        "name" => $jatbi->lang("Loại cửa hàng"),
                        "router" => '/stores/stores-types',
                        "icon" => '<i class="fas fa-stream"></i>',
                    ],
                ],
                "main" => 'false',
                "permission" => [
                    'stores' => $jatbi->lang("Cửa hàng"),
                    'stores.add' => $jatbi->lang("Thêm cửa hàng"),
                    'stores.edit' => $jatbi->lang("Sửa cửa hàng"),
                    'stores.deleted' => $jatbi->lang("xóa cửa hàng"),
                    'branch' => $jatbi->lang("Quầy hàng"),
                    'branch.add' => $jatbi->lang("Thêm quầy hàng"),
                    'branch.edit' => $jatbi->lang("sửa quầy hàng"),
                    'branch.deleted' => $jatbi->lang("xóa quầy hàng"),
                    'stores-types' => $jatbi->lang("Loại cửa hàng"),
                    'stores-types.add' => $jatbi->lang("Thêm loại cửa hàng"),
                    'stores-types.edit' => $jatbi->lang("Sửa loại cửa hàng"),
                    'stores-types.deleted' => $jatbi->lang("xóa loại cửa hàng"),
                ]
            ],
            ],
        ],
    ];

?>
