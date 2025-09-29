<?php
    $jatbi = $app->getValueData('jatbi');
    $setting = $app->getValueData('setting');
    return [
        "content" => [
            "item" => [
                'proposal'=>[
                    "menu"=>$jatbi->lang("Đề xuất"),
                    "url"=>'/customers',
                    "icon"=>'<i class="ti ti-calendar-dollar"></i>',
                    "sub"=>[
                        'proposal'      =>[
                            "name"  => $jatbi->lang("Đề xuất"),
                            "router"=> '/proposal',
                            "icon"  => '<i class="ti ti-user"></i>',
                        ],
                        'proposal.statistics'      =>[
                            "name"  => $jatbi->lang("Thống kê"),
                            "router"=> '/proposal/statistics',
                            "icon"  => '<i class="ti ti-user"></i>',
                        ],
                        'proposal.cash-flow'      =>[
                            "name"  => $jatbi->lang("Dòng tiền"),
                            "router"=> '/proposal/cash-flow',
                            "icon"  => '<i class="ti ti-user"></i>',
                        ],
                        'proposal.config'      =>[
                            "name"  => $jatbi->lang("Cấu hình"),
                            "router"=> '/proposal/config',
                            "icon"  => '<i class="ti ti-user"></i>',
                        ],
                    ],
                    "main"=>'false',
                    "permission"=>[
                        'proposal'=> $jatbi->lang("Đề xuất"),
                        'proposal.add'=> $jatbi->lang("Thêm Đề xuất"),
                        'proposal.edit'=> $jatbi->lang("Sửa Đề xuất"),
                        'proposal.deleted'=> $jatbi->lang("Xóa Đề xuất"),
                        'proposal.full'=> $jatbi->lang("Xem toàn bộ Đề xuất"),
                        'proposal.statistics'=> $jatbi->lang("Thống kê"),
                        'proposal.cash-flow'=> $jatbi->lang("Dòng tiền"),
                        'proposal.config'=> $jatbi->lang("Cấu hình"),
                        'proposal.config.add'=> $jatbi->lang("Thêm Cấu hình"),
                        'proposal.config.edit'=> $jatbi->lang("Sửa Cấu hình"),
                        'proposal.config.deleted'=> $jatbi->lang("Xóa Cấu hình"),
                    ]
                ],
            ],
        ],
    ];

?>
