<?php
$jatbi = $app->getValueData('jatbi');
$setting = $app->getValueData('setting');
return [
    "page" => [
        "name" => $jatbi->lang("Quản trị"),
        "item" => [
            'area-new' => [
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
                ]
            ],

        ],
    ],
];
