<?php
$jatbi = $app->getValueData('jatbi');
$setting = $app->getValueData('setting');
return [
    "content" => [
        "item" => [
            'invoices' => [
                "menu" => $jatbi->lang("Hóa đơn"),
                "url" => '/invoices',
                "icon" => '<i class="ti ti-receipt-2"></i>',
                "sub" => [
                    'sales' => [
                        "name" => $jatbi->lang("Bán hàng"),
                        "router" => '/invoices/sales/1',
                        "icon" => '<i class="ti ti-shopping-cart-plus"></i>',
                    ],
                    'invoices' => [
                        "name" => $jatbi->lang("Đơn hàng"),
                        "router" => '/invoices/invoices',
                        "icon" => '<i class="ti ti-file-invoice"></i>',
                    ],
                    'insurance' => [
                        "name" => $jatbi->lang("Bảo hành"),
                        "router" => '/invoices/insurance',
                        "icon" => '<i class="ti ti-shield-check"></i>',
                    ],
                    'returns' => [
                        "name" => $jatbi->lang("Trả hàng"),
                        "router" => '/invoices/returns',
                        "icon" => '<i class="ti ti-refresh-dot"></i>',
                    ],
                    'invoices-cancel' => [
                        "name" => $jatbi->lang("Hóa đơn đã hủy"),
                        "router" => '/invoices/invoices-cancel',
                        "icon" => '<i class="ti ti-file-x"></i>',
                    ],
                    'invoices-commission' => [
                        "name" => $jatbi->lang("Hoa hồng nhân viên"),
                        "router" => '/invoices/invoices-commission',
                        "icon" => '<i class="ti ti-award"></i>',
                    ],
                    'license_sale' => [
                        "name" => $jatbi->lang("Chứng từ bán hàng"),
                        "router" => '/invoices/license_sale',
                        "icon" => '<i class="ti ti-certificate"></i>',
                    ],
                ],
                "main" => 'false',
                "permission" => [
                    // Quyền cho trang Bán hàng (POS)
                    'sales' => $jatbi->lang("Truy cập trang bán hàng"),

                    // Quyền cho Đơn hàng
                    'invoices' => $jatbi->lang("Xem danh sách đơn hàng"),
                    'invoices.add' => $jatbi->lang("Tạo đơn hàng"),
                    'invoices.delete' => $jatbi->lang("Xóa đơn hàng"),
                    'invoices_codegroup.edit' => $jatbi->lang("Sửa mã đoàn của đơn hàng"),
                    'invoices_payments.edit' => $jatbi->lang("Sửa hình thức thanh toán"),
                    'invoices_notes.edit' => $jatbi->lang("Sửa ghi chú của đơn hàng"),
                    'invoices_additional_information.edit' => $jatbi->lang("Sửa thông tin bổ sung"),
                    'invoices_customers.edit' => $jatbi->lang("Sửa khách hàng của đơn hàng"),
                    'invoices_personnels.edit' => $jatbi->lang("Sửa nhân viên bán hàng"),
                    'invoices.cancel.req' => $jatbi->lang("Yêu cầu hủy đơn hàng"),
                    'invoices.cancel.confirm' => $jatbi->lang("Xác nhận hủy đơn hàng"),
                    'lock' => $jatbi->lang("Khóa hoặc mở khóa"),

                    // Quyền cho Hóa đơn hủy
                    'invoices-cancel' => $jatbi->lang("Xem danh sách đơn hàng đã hủy"),

                    // Quyền cho Bảo hành
                    'insurance' => $jatbi->lang("Xem danh sách bảo hành"),
                    'insurance.add' => $jatbi->lang("Tạo phiếu bảo hành"),
                    'insurance.edit' => $jatbi->lang("Sửa phiếu bảo hành"),
                    'insurance.delete' => $jatbi->lang("Xóa phiếu bảo hành"),

                    // Quyền cho Trả hàng
                    'returns' => $jatbi->lang("Xem danh sách trả hàng"),
                    'returns.add' => $jatbi->lang("Tạo phiếu trả hàng"),
                    'returns.edit' => $jatbi->lang("Sửa phiếu trả hàng"),
                    'returns.delete' => $jatbi->lang("Xóa phiếu trả hàng"),

                    // Quyền cho Hoa hồng & Chứng từ
                    'invoices-commission' => $jatbi->lang("Xem hoa hồng nhân viên"),
                    'license_sale' => $jatbi->lang("Xem và in chứng từ bán hàng"),
                ]
            ],
        ],
    ],
];
