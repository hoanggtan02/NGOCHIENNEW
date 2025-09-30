<?php
$jatbi = $app->getValueData('jatbi');
$setting = $app->getValueData('setting');
return [
    "page" => [
        "name" => $jatbi->lang("Quản trị"),
        "item" => [
            'areas' => [
                "menu" => $jatbi->lang("Khu vực"),
                "url" => '/areas/province/',
                "icon" => '<i class="ti ti-map"></i>',
                "sub" => [
                    'province' => [
                        "name" => $jatbi->lang("Tỉnh thành"),
                        "router" => '/areas/province',
                        "icon" => '<i class="fas fa-city"></i>',
                    ],
                    'district' => [
                        "name" => $jatbi->lang("Quận huyện"),
                        "router" => '/areas/district',
                        "icon" => '<i class="fas fa-archway"></i>',
                    ],
                    'ward' => [
                        "name" => $jatbi->lang("Phường xã"),
                        "router" => '/areas/ward',
                        "icon" => '<i class="fas fa-road"></i>',
                    ],


                ],
                "main" => 'false',
                "permission" => [
                    'province' => $jatbi->lang("Tỉnh thành"),
                    'province.add' => $jatbi->lang("Thêm Tỉnh thành"),
                    'province.edit' => $jatbi->lang("Sửa Tỉnh thành"),
                    'province.deleted' => $jatbi->lang("Xóa Tỉnh thành"),
                    'district' => $jatbi->lang("Quận huyện"),
                    'district.add' => $jatbi->lang("Thêm Quận huyện"),
                    'district.edit' => $jatbi->lang("Sửa Quận huyện"),
                    'district.deleted' => $jatbi->lang("Xóa Quận huyện"),
                    'ward' => $jatbi->lang("Phường xã"),
                    'ward.add' => $jatbi->lang("Thêm Phường xã"),
                    'ward.edit' => $jatbi->lang("Sửa Phường xã"),
                    'ward.deleted' => $jatbi->lang("Xóa Phường xã"),
                ]
            ],

        ],
    ],
];
