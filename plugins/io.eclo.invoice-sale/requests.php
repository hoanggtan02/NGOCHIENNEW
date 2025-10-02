<?php
$jatbi = $app->getValueData('jatbi');
$setting = $app->getValueData('setting');
return [
    "main" => [
        "item" => [
            'sales' => [
                "menu" => $jatbi->lang("Bán hàng"),
                "url" => '/invoices/sales/1',
                "icon" => '<i class="ti ti-cash-register text-primary"></i>',
                "main" => 'false',
                "permission" => [
                    'sales' => $jatbi->lang("Bán hàng")
                ]
            ],
        ],
    ],

];
