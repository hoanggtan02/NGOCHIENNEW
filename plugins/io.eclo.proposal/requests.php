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
                        'proposal.report-cash-flow'      =>[
                            "name"  => $jatbi->lang("Báo cáo dòng tiền"),
                            "router"=> '/proposal/report-cash-flow',
                            "icon"  => '<i class="ti ti-user"></i>',
                        ],
                        'proposal.overview-cash-flow'      =>[
                            "name"  => $jatbi->lang("Tổng quan dòng tiền"),
                            "router"=> '/proposal/overview-cash-flow',
                            "icon"  => '<i class="ti ti-user"></i>',
                        ],
                        'proposal.cash'      =>[
                            "name"  => $jatbi->lang("Số tiền mặt"),
                            "router"=> '/proposal/cash',
                            "icon"  => '<i class="ti ti-user"></i>',
                        ],
                        'proposal.bank'      =>[
                            "name"  => $jatbi->lang("Sổ ngân hàng"),
                            "router"=> '/proposal/bank',
                            "icon"  => '<i class="ti ti-user"></i>',
                        ],
                        'proposal.cash-bank'      =>[
                            "name"  => $jatbi->lang("Sổ tổng hợp"),
                            "router"=> '/proposal/cash-bank',
                            "icon"  => '<i class="ti ti-user"></i>',
                        ],
                        'proposal.receivable'      =>[
                            "name"  => $jatbi->lang("Phải thu"),
                            "router"=> '/proposal/receivable',
                            "icon"  => '<i class="ti ti-file-invoice"></i>',
                        ],
                        'proposal.payable'      =>[
                            "name"  => $jatbi->lang("Phải trả"),
                            "router"=> '/proposal/payable',
                            "icon"  => '<i class="ti ti-file-invoice"></i>',
                        ],
                        'proposal.journal'      =>[
                            "name"  => $jatbi->lang("Đối soát định khoản"),
                            "router"=> '/proposal/journal',
                            "icon"  => '<i class="ti ti-file-invoice"></i>',
                        ],
                        // 'shifts'    =>[
                        //     "name"  => $jatbi->lang("Kết ca"),
                        //     "router"=> '/shifts/list',
                        //     "icon"  => '<i class="fas fa-universal-access"></i>',
                        // ],
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
                        'proposal.deleted.full'=> $jatbi->lang("Xóa toàn bộ Đề xuất"),
                        'proposal.reality'=> $jatbi->lang("Bút toán Đề xuất"),
                        'proposal.statistics'=> $jatbi->lang("Thống kê"),
                        'proposal.cash-flow'=> $jatbi->lang("Dòng tiền"),
                        'proposal.report-cash-flow'=> $jatbi->lang("Báo cáo dòng tiền"),
                        'proposal.overview-cash-flow'=> $jatbi->lang("Tổng quan dòng tiền"),
                        'proposal.config'=> $jatbi->lang("Cấu hình"),
                        'proposal.config.add'=> $jatbi->lang("Thêm Cấu hình"),
                        'proposal.config.edit'=> $jatbi->lang("Sửa Cấu hình"),
                        'proposal.config.deleted'=> $jatbi->lang("Xóa Cấu hình"),
                        'proposal.cash'=> $jatbi->lang("Sổ tiền mặt"),
                        'proposal.bank'=> $jatbi->lang("Sổ ngân hàng"),
                        'proposal.cash-bank'=> $jatbi->lang("Sổ tổng hợp"),
                        'proposal.receivable'=> $jatbi->lang("Phải thu"),
                        'proposal.payable'=> $jatbi->lang("Phải trả"),
                        'proposal.journal'=> $jatbi->lang("Đối soát định khoản"),
                        // 'shifts'=> $jatbi->lang("Kết ca"),
                        // 'shifts.add'=> $jatbi->lang("Tạo Kết ca"),
                    ]
                ],
            ],
        ],
    ];

?>
