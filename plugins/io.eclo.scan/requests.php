<?php
$jatbi = $app->getValueData('jatbi');
$setting = $app->getValueData('setting');
return [
    "content" => [
        "item" => [
            'scan' => [
                "menu" => $jatbi->lang("Vé"),
                "url" => '/scan',
                "icon" => '<i class="ti ti-ticket"></i>',
                "sub" => [
                    'check_ticket' => [
                        "name" => $jatbi->lang("Quét vé"),
                        "router" => '/scan/check_ticket',
                        "icon" => '<i class="ti ti-file-text"></i>',
                    ],
                    'ticket' => [
                        "name" => $jatbi->lang("Vé"),
                        "router" => '/scan/ticket',
                        "icon" => '<i class="ti ti-ticket"></i>',
                    ],
                ],
                "main" => 'false',
                "permission" => [
                    'check_ticket' => $jatbi->lang("Thực hiện quét vé"),

                    'ticket' => $jatbi->lang("Xem danh sách vé"),
                    'ticket.add' => $jatbi->lang("Thêm vé mới"),
                    'ticket.edit' => $jatbi->lang("Sửa thông tin vé"),
                    'ticket.deleted' => $jatbi->lang("Xóa vé"),
                ]
            ],
        ],
    ],
];
