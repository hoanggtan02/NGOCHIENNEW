<?php
$jatbi = $app->getValueData('jatbi');
$setting = $app->getValueData('setting');
return [
    "content" => [
        "item" => [
            'warehouses' => [
                "menu" => $jatbi->lang("Kho hàng"),
                "url" => '/warehouses',
                "icon" => '<i class="ti ti-box"></i>',
                "sub" => [
                    'products' => [
                        "name" => $jatbi->lang("Kho thành phẩm"),
                        "router" => '/warehouses/products',
                        "icon" => '<i class="fas fa-boxes"></i>',
                    ],
                    'list_products_errors' => [
                        "name" => $jatbi->lang("Kho hàng lỗi"),
                        "router" => '/warehouses/list_products_errors',
                        "icon" => '<i class="fas fa-exclamation-triangle"></i>',
                    ],
                    'ingredient' => [
                        "name" => $jatbi->lang("Kho nguyên liệu"),
                        "router" => '/warehouses/ingredient',
                        "icon" => '<i class="fas fa-magic"></i>',
                    ],
                    'categorys' => [
                        "name" => $jatbi->lang("Danh mục"),
                        "router" => '/warehouses/categorys',
                        "icon" => '<i class="fas fa-folder-open"></i>',
                    ],
                    'group' => [
                        "name" => $jatbi->lang("Nhóm sản phẩm"),
                        "router" => '/warehouses/group',
                        "icon" => '<i class="far fa-object-group"></i>',
                    ],
                    'pearl' => [
                        "name" => $jatbi->lang("Loại ngọc"),
                        "router" => '/warehouses/pearl',
                        "icon" => '<i class="far fa-circle"></i>',
                    ],
                    'colors' => [
                        "name" => $jatbi->lang("Màu sắc"),
                        "router" => '/warehouses/colors',
                        "icon" => '<i class="fas fa-palette"></i>',
                    ],
                    'sizes' => [
                        "name" => $jatbi->lang("Li ngọc"),
                        "router" => '/warehouses/sizes',
                        "icon" => '<i class="fas fa-crosshairs"></i>',
                    ],
                    'units' => [
                        "name" => $jatbi->lang("Đơn vị"),
                        "router" => '/warehouses/units',
                        "icon" => '<i class="fas fa-inbox"></i>',
                    ],
                    'default_code' => [
                        "name" => $jatbi->lang("Mã cố định"),
                        "router" => '/warehouses/default_code',
                        "icon" => '<i class="fas fa-balance-scale"></i>',
                    ],

                ],
                "main" => 'false',
                "permission" => [
                    'products' => $jatbi->lang("Kho thành phẩm"),
                    'products.add' => $jatbi->lang("Thêm kho thành phẩm"),
                    'products.edit' => $jatbi->lang("Sửa kho thành phẩm"),
                    'products.error' => $jatbi->lang("Sản phẩm lỗi"),
                    'products.deleted' => $jatbi->lang("Xóa kho thành phẩm"),
                    'products.import' => $jatbi->lang("Nhập hàng kho thành phẩm"),
                    //'products-move'=>$lang['chuyen-hang'].' '.$jatbi->lang("kho-thanh-pham'],

                    'list_products_errors' => $jatbi->lang("Kho hàng lỗi"),
                    'ingredient' => $jatbi->lang("Kho nguyên liệu"),
                    'ingredient.add' => $jatbi->lang("Thêm kho nguyên liệu"),
                    'ingredient.edit' => $jatbi->lang("Sửa kho nguyên liệu"),
                    'ingredient.deleted' => $jatbi->lang("Xóa kho nguyên liệu"),

                    'ingredient-import' => $jatbi->lang("Nhập hàng kho nguyên liệu"),
                    'ingredient-export' => $jatbi->lang("Xuất hàng kho nguyên liệu"),
                    // 'ingredient-excel'=>$lang['nhap-hang'].' '.$jatbi->lang("kho-nguyen-lieu'].' '.'excel',
                    // 'warehouses-excel'=>$lang['nhap-hang'].' '.$jatbi->lang("kho-thanh-pham'].' '.'excel',
                    'ingredient-cancel' => $jatbi->lang("Hủy kho nguyên liệu"),

                    'categorys' => $jatbi->lang("Danh mục"),
                    'categorys.add' => $jatbi->lang("Thêm danh mục"),
                    'categorys.edit' => $jatbi->lang("Sửa danh mục"),
                    'categorys.deleted' => $jatbi->lang("Xóa danh mục"),

                    'group' => $jatbi->lang("Nhóm sản phẩm"),
                    'group.add' => $jatbi->lang("Thêm nhóm sản phẩm"),
                    'group.edit' => $jatbi->lang("Sửa nhóm sản phẩm"),
                    'group.deleted' => $jatbi->lang("Xóa nhóm sản phẩm"),

                    'pearl' => $jatbi->lang("Loại ngọc"),
                    'pearl.add' => $jatbi->lang("Thêm loại ngọc"),
                    'pearl.edit' => $jatbi->lang("Sửa loại ngọc"),
                    'pearl.deleted' => $jatbi->lang("Xóa loại ngọc"),

                    'colors' => $jatbi->lang("Màu sắc"),
                    'colors.add' => $jatbi->lang("Thêm màu sắc"),
                    'colors.edit' => $jatbi->lang("Sửa màu sắc"),
                    'colors.deleted' => $jatbi->lang("Xóa màu sắc"),

                    'sizes' => $jatbi->lang("Li ngọc"),
                    'sizes.add' => $jatbi->lang("Thêm li ngọc"),
                    'sizes.edit' => $jatbi->lang("Sửa li ngọc"),
                    'sizes.deleted' => $jatbi->lang("Xóa li ngọc"),

                    'units' => $jatbi->lang("Đơn vị"),
                    'units.add' => $jatbi->lang("Thêm đơn vị"),
                    'units.edit' => $jatbi->lang("Sửa đơn vị"),
                    'units.deleted' => $jatbi->lang("Xóa đơn vị"),

                    'default_code' => $jatbi->lang("Mã cố định"),
                    'default_code.add' => $jatbi->lang("Thêm mã cố định"),
                    'default_code.edit' => $jatbi->lang("Sửa mã cố định"),
                    'default_code.deleted' => $jatbi->lang("Xóa mã cố định"),

                    'warehouses-import' => $jatbi->lang("Nhập hàng"),
                    'warehouses-import-history' => $jatbi->lang("Lịch sử nhập hàng"),
                    // 'warehouses-move'=>$lang['chuyen-hang'],
                    'warehouses-move-history' => $jatbi->lang("Lich sử chuyển hàng"),
                    // //'warehouses-cancel' => $lang['huy-hang'],
                    // 'warehouses-cancel-history'=>$lang['lich-su-huy-hang'],
                    // 'products.process' => $lang['duyet-huy-hang'],
                    // 'products_amount_status' => $lang['kiem-tra-so-luong'],
                ],
            ],
        ],
    ],
];
