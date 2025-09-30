<?php
    $jatbi = $app->getValueData('jatbi');
    $setting = $app->getValueData('setting');
    return [
        "content" => [
            "item" => [
                'accountants' => [
                    "menu" => $jatbi->lang("Kế toán"),
                    "url" => '/accountants',
                    "icon" => '<i class="ti ti-calculator"></i>',
                    "sub" => [
                        'expenditure' => [
                            "name" => $jatbi->lang("Thu chi"),
                            "router" => '/accountants/expenditure',
                            "icon" => '<i class="ti ti-arrows-exchange"></i>',
                        ],
                        'expenditure_report' => [
                            "name" => $jatbi->lang("Quỹ tiền mặt"),
                            "router" => '/accountants/expenditure_report',
                            "icon" => '<i class="ti ti-wallet"></i>',
                        ],
                        'deposit_book' => [
                            "name" => $jatbi->lang("Số tiền gửi"),
                            "router" => '/accountants/deposit_book',
                            "icon" => '<i class="ti ti-building-bank"></i>',
                        ],
                        'accounts-code' => [
                            "name" => $jatbi->lang("Mã tài khoản"),
                            "router" => '/accountants/accounts-code',
                            "icon" => '<i class="ti ti-list-numbers"></i>',
                        ],
                        'accountant' => [
                            "name" => $jatbi->lang("Kế toán"),
                            "router" => '/accountants/accountant',
                            "icon" => '<i class="ti ti-notebook"></i>',
                        ],
                        'financial_paper' => [
                            "name" => $jatbi->lang("Chứng từ kế toán"),
                            "router" => '/accountants/financial_paper',
                            "icon" => '<i class="ti ti-file-text"></i>',
                        ],
                        'accounts_receivable' => [
                            "name" => $jatbi->lang("Chi tiết công nợ phải thu"),
                            "router" => '/accountants/accounts_receivable',
                            "icon" => '<i class="ti ti-file-analytics"></i>',
                        ],
                        'subsidiary_ledger' => [
                            "name" => $jatbi->lang("Sổ chi tiết tài khoản"),
                            "router" => '/accountants/subsidiary_ledger',
                            "icon" => '<i class="ti ti-book-2"></i>',
                        ],
                        'income_statement' => [
                            "name" => $jatbi->lang("Báo cáo kết quả kinh doanh"),
                            "router" => '/accountants/income_statement',
                            "icon" => '<i class="ti ti-report-analytics"></i>',
                        ],
                        'aggregate_cost' => [
                            "name" => $jatbi->lang("Tổng hợp chi phí"),
                            "router" => '/accountants/aggregate_cost',
                            "icon" => '<i class="ti ti-sum"></i>',
                        ],
                        'inventory_table' => [
                            "name" => $jatbi->lang("Bản kiểm kê"),
                            "router" => '/accountants/inventory_table',
                            "icon" => '<i class="ti ti-clipboard-list"></i>',
                        ],
                    ],
                    "main" => 'false',
                    "permission" => [
                        'expenditure' => $jatbi->lang("Xem thu chi"),
                        'expenditure.add' => $jatbi->lang("Thêm phiếu thu chi"),
                        'expenditure.edit' => $jatbi->lang("Sửa phiếu thu chi"),
                        'expenditure.deleted' => $jatbi->lang("Xóa phiếu thu chi"),
                        'expenditure.move' => $jatbi->lang("Chuyển dữ liệu kế toán"),

                        'expenditure_report' => $jatbi->lang("Xem báo cáo quỹ tiền mặt"),
                        'expenditure_report.add' => $jatbi->lang("Thêm báo cáo quỹ tiền mặt"),
                        'expenditure_report.edit' => $jatbi->lang("Sửa báo cáo quỹ tiền mặt"),
                        'expenditure_report.deleted' => $jatbi->lang("Xóa báo cáo quỹ tiền mặt"),

                        'deposit_book' => $jatbi->lang("Xem sổ tiền gửi"),
                        'deposit_book.add' => $jatbi->lang("Thêm sổ tiền gửi"),
                        'deposit_book.edit' => $jatbi->lang("Sửa sổ tiền gửi"),
                        'deposit_book.deleted' => $jatbi->lang("Xóa sổ tiền gửi"),

                        'accounts-code' => $jatbi->lang("Xem mã tài khoản"),
                        'accounts-code.add' => $jatbi->lang("Thêm mã tài khoản"),
                        'accounts-code.edit' => $jatbi->lang("Sửa mã tài khoản"),
                        'accounts-code.deleted' => $jatbi->lang("Xóa mã tài khoản"),

                        'accountant' => $jatbi->lang("Xem hạch toán"),
                        'accountant.add' => $jatbi->lang("Thêm hạch toán"),
                        'accountant.edit' => $jatbi->lang("Sửa hạch toán"),
                        'accountant.deleted' => $jatbi->lang("Xóa hạch toán"),

                        'financial_paper' => $jatbi->lang("Xem chứng từ kế toán"),
                        'financial_paper.add' => $jatbi->lang("Thêm chứng từ kế toán"),
                        'financial_paper.edit' => $jatbi->lang("Sửa chứng từ kế toán"),
                        'financial_paper.deleted' => $jatbi->lang("Xóa chứng từ kế toán"),

                        'accounts_receivable' => $jatbi->lang("Xem công nợ phải thu"),
                        'accounts_receivable.add' => $jatbi->lang("Thêm công nợ phải thu"),
                        'accounts_receivable.edit' => $jatbi->lang("Sửa công nợ phải thu"),
                        'accounts_receivable.deleted' => $jatbi->lang("Xóa công nợ phải thu"),

                        'subsidiary_ledger' => $jatbi->lang("Xem sổ chi tiết tài khoản"),
                        'subsidiary_ledger.add' => $jatbi->lang("Thêm sổ chi tiết tài khoản"),
                        'subsidiary_ledger.edit' => $jatbi->lang("Sửa sổ chi tiết tài khoản"),
                        'subsidiary_ledger.deleted' => $jatbi->lang("Xóa sổ chi tiết tài khoản"),

                        'income_statement' => $jatbi->lang("Xem báo cáo KQKD"),
                        'income_statement.add' => $jatbi->lang("Thêm báo cáo KQKD"),
                        'income_statement.edit' => $jatbi->lang("Sửa báo cáo KQKD"),
                        'income_statement.deleted' => $jatbi->lang("Xóa báo cáo KQKD"),

                        'aggregate_cost' => $jatbi->lang("Xem tổng hợp chi phí"),
                        'aggregate_cost.add' => $jatbi->lang("Thêm tổng hợp chi phí"),
                        'aggregate_cost.edit' => $jatbi->lang("Sửa tổng hợp chi phí"),
                        'aggregate_cost.deleted' => $jatbi->lang("Xóa tổng hợp chi phí"),

                        'inventory_table' => $jatbi->lang("Xem bảng kiểm kê"),
                        'inventory_table.add' => $jatbi->lang("Thêm bảng kiểm kê"),
                        'inventory_table.edit' => $jatbi->lang("Sửa bảng kiểm kê"),
                        'inventory_table.deleted' => $jatbi->lang("Xóa bảng kiểm kê"),
                    ]
                ],
            ],
        ],
    ];

?>
