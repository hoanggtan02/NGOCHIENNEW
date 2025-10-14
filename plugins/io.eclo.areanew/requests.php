<?php
$jatbi = $app->getValueData('jatbi');
$setting = $app->getValueData('setting');
return [
    "page" => [
        "name" => $jatbi->lang("Quản trị"),
        "item" => [
            'areas-new' => [
                "menu" => $jatbi->lang("Khu vực mới"),
                "url" => '/areas-new/province/',
                "icon" => '<i class="ti ti-map"></i>',
                "sub" => [
                    'province' => [
                        "name" => $jatbi->lang("Tỉnh thành"),
                        "router" => '/areas-new/province',
                        "icon" => '<i class="fas fa-city"></i>',
                    ],
                    'district' => [
                        "name" => $jatbi->lang("Quận huyện"),
                        "router" => '/areas-new/district',
                        "icon" => '<i class="fas fa-archway"></i>',
                    ],
                    'ward' => [
                        "name" => $jatbi->lang("Phường xã"),
                        "router" => '/areas-new/ward',
                        "icon" => '<i class="fas fa-road"></i>',
                    ],


                ],
                "main" => 'false',
                "permission" => [
                    'province-new' => $jatbi->lang("Tỉnh thành"),
                    'province-new.add' => $jatbi->lang("Thêm Tỉnh thành"),
                    'province-new.edit' => $jatbi->lang("Sửa Tỉnh thành"),
                    'province-new.deleted' => $jatbi->lang("Xóa Tỉnh thành"),
                    'district-new' => $jatbi->lang("Quận huyện"),
                    'district-new.add' => $jatbi->lang("Thêm Quận huyện"),
                    'district-new.edit' => $jatbi->lang("Sửa Quận huyện"),
                    'district-new.deleted' => $jatbi->lang("Xóa Quận huyện"),
                    'ward-new' => $jatbi->lang("Phường xã"),
                    'ward-new.add' => $jatbi->lang("Thêm Phường xã"),
                    'ward-new.edit' => $jatbi->lang("Sửa Phường xã"),
                    'ward-new.deleted' => $jatbi->lang("Xóa Phường xã"),
                ]
            ],

        ],
    ],
];
