<?php
$jatbi = $app->getValueData('jatbi');
$setting = $app->getValueData('setting');
return [
    "content" => [
        "item" => [
            'reports' => [
                "menu" => $jatbi->lang("Báo cáo"),
                "url" => '/reports',
                "icon" => '<i class="ti ti-chart-bar"></i>',
                "sub" => [
                    'revenue' => [
                        "name" => $jatbi->lang("Doanh thu"),
                        "router" => '/reports/revenue',
                        "icon" => '<i class="ti ti-report-money"></i>',
                    ],
                    'revenue_personnels' => [
                        "name" => $jatbi->lang("Doanh thu nhân viên"),
                        "router" => '/reports/revenue_personnels',
                        "icon" => '<i class="ti ti-users"></i>',
                    ],
                    'liabilities' => [
                        "name" => $jatbi->lang("Công nợ"),
                        "router" => '/reports/liabilities',
                        "icon" => '<i class="ti ti-file-invoice"></i>',
                    ],
                    'purchases-liabilities' => [
                        "name" => $jatbi->lang("Công nợ mua hàng"),
                        "router" => '/reports/purchases-liabilities',
                        "icon" => '<i class="ti ti-receipt-2"></i>',
                    ],
                    'purchases-revenue' => [
                        "name" => $jatbi->lang("Chi phí mua hàng"),
                        "router" => '/reports/purchases-revenue',
                        "icon" => '<i class="ti ti-shopping-cart-x"></i>',
                    ],
                    'inventory' => [
                        "name" => $jatbi->lang("Xuất nhập tồn"),
                        "router" => '/reports/inventory',
                        "icon" => '<i class="ti ti-package"></i>',
                    ],
                    'inventory_ingredient' => [
                        "name" => $jatbi->lang("Xuất nhập tồn nguyên liệu"),
                        "router" => '/reports/inventory_ingredient',
                        "icon" => '<i class="ti ti-flask"></i>',
                    ],
                    'inventory_crafting' => [
                        "name" => $jatbi->lang("Xuất nhập tồn kho chế tác"),
                        "router" => '/reports/inventory_crafting/gold',
                        "icon" => '<i class="ti ti-diamond"></i>',
                    ],
                    'selling_products' => [
                        "name" => $jatbi->lang("Sản phẩm bán chạy"),
                        "router" => '/reports/selling_products',
                        "icon" => '<i class="ti ti-trending-up"></i>',
                    ],
                ],
                "main" => 'false',
                "permission" => [
                    'revenue' => $jatbi->lang("Xem báo cáo doanh thu"),
                    // 'revenue.add' => $jatbi->lang("Thêm báo cáo doanh thu"),
                    // 'revenue.edit' => $jatbi->lang("Sửa báo cáo doanh thu"),
                    // 'revenue.deleted' => $jatbi->lang("Xóa báo cáo doanh thu"),

                    'revenue_personnels' => $jatbi->lang("Xem báo cáo doanh thu nhân viên"),
                    // 'revenue_personnels.add' => $jatbi->lang("Thêm báo cáo doanh thu nhân viên"),
                    // 'revenue_personnels.edit' => $jatbi->lang("Sửa báo cáo doanh thu nhân viên"),
                    // 'revenue_personnels.deleted' => $jatbi->lang("Xóa báo cáo doanh thu nhân viên"),

                    'liabilities' => $jatbi->lang("Xem báo cáo công nợ"),
                    // 'liabilities.add' => $jatbi->lang("Thêm báo cáo công nợ"),
                    // 'liabilities.edit' => $jatbi->lang("Sửa báo cáo công nợ"),
                    // 'liabilities.deleted' => $jatbi->lang("Xóa báo cáo công nợ"),

                    'purchases-liabilities' => $jatbi->lang("Xem báo cáo công nợ mua hàng"),
                    // 'purchases-liabilities.add' => $jatbi->lang("Thêm báo cáo công nợ mua hàng"),
                    // 'purchases-liabilities.edit' => $jatbi->lang("Sửa báo cáo công nợ mua hàng"),
                    // 'purchases-liabilities.deleted' => $jatbi->lang("Xóa báo cáo công nợ mua hàng"),

                    'purchases-revenue' => $jatbi->lang("Xem báo cáo chi phí mua hàng"),
                    // 'purchases-revenue.add' => $jatbi->lang("Thêm báo cáo chi phí mua hàng"),
                    // 'purchases-revenue.edit' => $jatbi->lang("Sửa báo cáo chi phí mua hàng"),
                    // 'purchases-revenue.deleted' => $jatbi->lang("Xóa báo cáo chi phí mua hàng"),

                    'inventory' => $jatbi->lang("Xem báo cáo xuất nhập tồn"),
                    // 'inventory.add' => $jatbi->lang("Thêm báo cáo xuất nhập tồn"),
                    // 'inventory.edit' => $jatbi->lang("Sửa báo cáo xuất nhập tồn"),
                    // 'inventory.deleted' => $jatbi->lang("Xóa báo cáo xuất nhập tồn"),

                    'inventory_ingredient' => $jatbi->lang("Xem báo cáo XNT nguyên liệu"),
                    // 'inventory_ingredient.add' => $jatbi->lang("Thêm báo cáo XNT nguyên liệu"),
                    // 'inventory_ingredient.edit' => $jatbi->lang("Sửa báo cáo XNT nguyên liệu"),
                    // 'inventory_ingredient.deleted' => $jatbi->lang("Xóa báo cáo XNT nguyên liệu"),

                    'inventory_crafting' => $jatbi->lang("Xem báo cáo XNT kho chế tác"),
                    // 'inventory_crafting.add' => $jatbi->lang("Thêm báo cáo XNT kho chế tác"),
                    // 'inventory_crafting.edit' => $jatbi->lang("Sửa báo cáo XNT kho chế tác"),
                    // 'inventory_crafting.deleted' => $jatbi->lang("Xóa báo cáo XNT kho chế tác"),

                    'selling_products' => $jatbi->lang("Xem báo cáo sản phẩm bán chạy"),
                    // 'selling_products.add' => $jatbi->lang("Thêm báo cáo sản phẩm bán chạy"),
                    // 'selling_products.edit' => $jatbi->lang("Sửa báo cáo sản phẩm bán chạy"),
                    // 'selling_products.deleted' => $jatbi->lang("Xóa báo cáo sản phẩm bán chạy"),
                ]
            ],
        ],
    ],
];
