<?php
    if (!defined('ECLO')) die("Hacking attempt");
    $requests = [
        "main"=>[
            "name"=>$jatbi->lang("Chính"),
            "item"=>[
                '/'=>[
                    "menu"=>$jatbi->lang("Trang chủ"),
                    "url"=>'/',
                    "icon"=>'<i class="ti ti-dashboard"></i>',
                    "main"=>'true',
                ],
                'files'=>[
                    "menu"=>$jatbi->lang("Dữ liệu"),
                    "url"=>'/files',
                    "icon"=>'<i class="ti ti-server-bolt text-success"></i>',
                    "main"=>'true',
                ],
            ],
        ],
        "content" => [
            "name" => $jatbi->lang("Nội dung"),
            "item"=>[
                'customers'=>[
                    "menu"=>$jatbi->lang("Khách hàng & NCC"),
                    "url"=>'/customers',
                    "icon"=>'<i class="ti ti-users-group "></i>',
                    "sub"=>[
                        'customers'      =>[
                            "name"  => $jatbi->lang("Khách hàng"),
                            "router"=> '/customers',
                            "icon"  => '<i class="ti ti-user"></i>',
                        ],
                        'customers.vendors'      =>[
                            "name"  => $jatbi->lang("Nhà cung cấp"),
                            "router"=> '/customers/vendors',
                            "icon"  => '<i class="ti ti-user"></i>',
                        ],
                        'customers.overview'    =>[
                            "name"  => $jatbi->lang("Tổng quan"),
                            "router"=> '/customers/overview',
                            "icon"  => '<i class="fas fa-universal-access"></i>',
                        ],
                        'customers.config'    =>[
                            "name"  => $jatbi->lang("Cấu hình"),
                            "router"=> '/customers/config',
                            "icon"  => '<i class="fas fa-universal-access"></i>',
                        ],
                    ],
                    "main"=>'false',
                    "permission"=>[
                        'customers'=> $jatbi->lang("khách hàng"),
                        'customers.add' => $jatbi->lang("Thêm khách hàng"),
                        'customers.edit' => $jatbi->lang("Sửa khách hàng"),
                        'customers.deleted' => $jatbi->lang("Xóa khách hàng"),
                        'customers.vendors'=> $jatbi->lang("Nhà cung cấp"),
                        'customers.vendors.add' => $jatbi->lang("Thêm Nhà cung cấp"),
                        'customers.vendors.edit' => $jatbi->lang("Sửa Nhà cung cấp"),
                        'customers.vendors.deleted' => $jatbi->lang("Xóa Nhà cung cấp"),
                        'customers.birthday'=> $jatbi->lang("Sinh nhật khách hàng"),
                        'customers.overview'=> $jatbi->lang("Tổng quan khách hàng"),
                        'customers.config'=> $jatbi->lang("Cấu hình khách hàng"),
                        'customers.config.add' => $jatbi->lang("Thêm Cấu hình khách hàng"),
                        'customers.config.edit' => $jatbi->lang("Sửa Cấu hình khách hàng"),
                        'customers.config.deleted' => $jatbi->lang("Xóa Cấu hình khách hàng"),
                    ]
                ],
            ],
        ],
        "page"=>[
            "name"=>$jatbi->lang("Quản trị"),
            "item"=>[
                'users'=>[
                    "menu"=>$jatbi->lang("Người dùng"),
                    "url"=>'/users',
                    "icon"=>'<i class="ti ti-user "></i>',
                    "sub"=>[
                        'accounts'      =>[
                            "name"  => $jatbi->lang("Tài khoản"),
                            "router"=> '/users/accounts',
                            "icon"  => '<i class="ti ti-user"></i>',
                        ],
                        'permission'    =>[
                            "name"  => $jatbi->lang("Nhóm quyền"),
                            "router"=> '/users/permission',
                            "icon"  => '<i class="fas fa-universal-access"></i>',
                        ],
                    ],
                    "main"=>'false',
                    "permission"=>[
                        'accounts'=> $jatbi->lang("Tài khoản"),
                        'accounts.add' => $jatbi->lang("Thêm tài khoản"),
                        'accounts.edit' => $jatbi->lang("Sửa tài khoản"),
                        'accounts.deleted' => $jatbi->lang("Xóa tài khoản"),
                        'accounts.brands' => $jatbi->lang("Chi nhánh tài khoản"),
                        'accounts.notification.pos' => $jatbi->lang("Nhận thông báo đặt bàn"),
                        'permission'=> $jatbi->lang("Nhóm quyền"),
                        'permission.add' => $jatbi->lang("Thêm Nhóm quyền"),
                        'permission.edit' => $jatbi->lang("Sửa Nhóm quyền"),
                        'permission.deleted' => $jatbi->lang("Xóa Nhóm quyền"),
                    ]
                ],
                'admin'=>[
                    "menu"=>$jatbi->lang("Quản trị"),
                    "url"=>'/admin',
                    "icon"=>'<i class="ti ti-settings "></i>',
                    "sub"=>[
                        'plugins'   => [
                            "name"  => $jatbi->lang("Tiện ích mở rộng"),
                            "router"    => '/admin/plugins',
                            "icon"  => '<i class="fas fa-plugin"></i>',
                        ],
                        'blockip'   => [
                            "name"  => $jatbi->lang("Chặn truy cập"),
                            "router"    => '/admin/blockip',
                            "icon"  => '<i class="fas fa-ban"></i>',
                        ],
                        'trash'  => [
                            "name"  => $jatbi->lang("Thùng rác"),
                            "router"    => '/admin/trash',
                            "icon"  => '<i class="fa fa-list-alt"></i>',
                        ],
                        'logs'  => [
                            "name"  => $jatbi->lang("Nhật ký"),
                            "router"    => '/admin/logs',
                            "icon"  => '<i class="fa fa-list-alt"></i>',
                        ],
                        'config'    => [
                            "name"  => $jatbi->lang("Cấu hình"),
                            "router"    => '/admin/config',
                            "icon"  => '<i class="fa fa-cog"></i>',
                            "action"   => 'modal',
                        ],
                    ],
                    "main"=>'false',
                    "permission"=>[
                        'plugins'       =>$jatbi->lang("Mở rộng"),
                        'plugins.add'   =>$jatbi->lang("Thêm Mở rộng"),
                        'plugins.edit'  =>$jatbi->lang("Sửa Mở rộng"),
                        'plugins.deleted'=>$jatbi->lang("Xóa Mở rộng"),
                        'blockip'       =>$jatbi->lang("Chặn truy cập"),
                        'blockip.add'   =>$jatbi->lang("Thêm Chặn truy cập"),
                        'blockip.edit'  =>$jatbi->lang("Sửa Chặn truy cập"),
                        'blockip.deleted'=>$jatbi->lang("Xóa Chặn truy cập"),
                        'config'        =>$jatbi->lang("Cấu hình"),
                        'logs'          =>$jatbi->lang("Nhật ký"),
                        'trash'          =>$jatbi->lang("Thùng rác"),
                    ]
                ],
            ],
        ],
    ];
?>