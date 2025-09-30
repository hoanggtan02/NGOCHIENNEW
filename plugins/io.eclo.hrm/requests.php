<?php
$jatbi = $app->getValueData('jatbi');
$setting = $app->getValueData('setting');
return [
    "content" => [
        "item" => [
            'hrm' => [
                "menu" => $jatbi->lang("Nhân sự"),
                "url" => '/hrm',
                "icon" => '<i class="ti ti-users"></i>',
                "sub" => [
                    'personnels' => [
                        "name" => $jatbi->lang("Nhân viên"),
                        "router" => '/hrm/personnels',
                        "icon" => '<i class="ti ti-user-check"></i>',
                    ],
                    'contract' => [
                        "name" => $jatbi->lang("Hợp đồng lao động"),
                        "router" => '/hrm/contract',
                        "icon" => '<i class="ti ti-file-text"></i>',
                    ],
                    'insurrance' => [
                        "name" => $jatbi->lang("Bảo hiểm"),
                        "router" => '/hrm/insurrance',
                        "icon" => '<i class="ti ti-shield-check"></i>',
                    ],
                    'rosters' => [
                        "name" => $jatbi->lang("Bảng phân công"),
                        "router" => '/hrm/rosters',
                        "icon" => '<i class="ti ti-calendar-event"></i>',
                    ],
                    'offices' => [
                        "name" => $jatbi->lang("Phòng ban"),
                        "router" => '/hrm/offices',
                        "icon" => '<i class="ti ti-building-community"></i>',
                    ],
                    'salary-categorys' => [
                        "name" => $jatbi->lang("Danh mục tiền lương"),
                        "router" => '/hrm/salary-categorys',
                        "icon" => '<i class="ti ti-cash"></i>',
                    ],
                    'timework' => [
                        "name" => $jatbi->lang("Thời gian làm việc"),
                        "router" => '/hrm/timework',
                        "icon" => '<i class="ti ti-clock"></i>',
                    ],
                    'furlough-categorys' => [
                        "name" => $jatbi->lang("Danh mục nghỉ phép"),
                        "router" => '/hrm/furlough-categorys',
                        "icon" => '<i class="ti ti-clock"></i>',
                    ],
                    'furlough' => [
                        "name" => $jatbi->lang("Nghỉ phép"),
                        "router" => '/hrm/furlough',
                        "icon" => '<i class="ti ti-clock"></i>',
                    ],
                    'holiday' => [
                        "name" => $jatbi->lang("Ngày lễ"),
                        "router" => '/hrm/holiday',
                        "icon" => '<i class="ti ti-clock"></i>',
                    ],
                    'reward-discipline' => [
                        "name" => $jatbi->lang("Khen thưởng kỉ luật"),
                        "router" => '/hrm/reward-discipline',
                        "icon" => '<i class="ti ti-clock"></i>',
                    ],
                    'time-late' => [
                        "name" => $jatbi->lang("Đi trễ về sớm"),
                        "router" => '/hrm/time-late',
                        "icon" => '<i class="ti ti-clock"></i>',
                    ],
                    'salary-advance' => [
                        "name" => $jatbi->lang("Ứng lương"),
                        "router" => '/hrm/salary-advance',
                        "icon" => '<i class="ti ti-clock"></i>',
                    ],
                    'timekeeping' => [
                        "name" => $jatbi->lang("Chấm công"),
                        "router" => '/hrm/timekeeping',
                        "icon" => '<i class="ti ti-clock"></i>',
                    ],
                    'salary' => [
                        "name" => $jatbi->lang("Tính lương"),
                        "router" => '/hrm/salary',
                        "icon" => '<i class="ti ti-clock"></i>',
                    ],
                    'uniforms_items' => [
                        "name" => $jatbi->lang("Đồng phục"),
                        "router" => '/hrm/uniforms-items',
                        "icon" => '<i class="ti ti-clock"></i>',
                    ],
                    'uniforms_allocations' => [
                        "name" => $jatbi->lang("Cấp phát Đồng phục"),
                        "router" => '/hrm/uniforms-allocations',
                        "icon" => '<i class="ti ti-clock"></i>',
                    ],
                    'reports' => [
                        "name" => $jatbi->lang("Báo cáo nhân sự"),
                        "router" => '/hrm/reports',
                        "icon" => '<i class="ti ti-clock"></i>',
                    ],


                ],
                "main" => 'false',
                "permission" => [
                    'reports' => $jatbi->lang("Báo cáo nhân sự"),

                    // Quyền cho Danh mục Đồng phục
                    'uniforms_items' => $jatbi->lang("Xem danh mục đồng phục"),
                    'uniforms_items.add' => $jatbi->lang("Thêm mới đồng phục"),
                    'uniforms_items.edit' => $jatbi->lang("Sửa thông tin đồng phục"),
                    'uniforms_items.delete' => $jatbi->lang("Xóa đồng phục"),

                    // Quyền cho Cấp phát Đồng phục
                    'uniforms_allocations' => $jatbi->lang("Xem lịch sử cấp phát"),
                    'uniforms_allocations.add' => $jatbi->lang("Tạo phiếu cấp phát"),
                    'uniforms_allocations.edit' => $jatbi->lang("Sửa phiếu cấp phát"),
                    'uniforms_allocations.delete' => $jatbi->lang("Xóa phiếu cấp phát"),
                    // Quyền cho Nhân viên
                    'personnels' => $jatbi->lang("Xem danh sách nhân viên"),
                    'personnels.add' => $jatbi->lang("Thêm nhân viên"),
                    'personnels.edit' => $jatbi->lang("Sửa nhân viên"),
                    'personnels.deleted' => $jatbi->lang("Xóa nhân viên"),

                    // Quyền cho Hợp đồng
                    'contract' => $jatbi->lang("Xem danh sách hợp đồng"),
                    'contract.add' => $jatbi->lang("Thêm hợp đồng"),
                    'contract.edit' => $jatbi->lang("Sửa hợp đồng"),
                    'contract.deleted' => $jatbi->lang("Xóa hợp đồng"),

                    // Quyền cho Bảo hiểm
                    'insurrance' => $jatbi->lang("Xem thông tin bảo hiểm"),
                    'insurrance.add' => $jatbi->lang("Thêm thông tin bảo hiểm"),
                    'insurrance.edit' => $jatbi->lang("Sửa thông tin bảo hiểm"),
                    'insurrance.deleted' => $jatbi->lang("Xóa thông tin bảo hiểm"),

                    // Quyền cho Bảng phân công
                    'rosters' => $jatbi->lang("Xem bảng phân công"),
                    'rosters.add' => $jatbi->lang("Tạo bảng phân công"),
                    'rosters.edit' => $jatbi->lang("Sửa bảng phân công"),
                    'rosters.deleted' => $jatbi->lang("Xóa bảng phân công"),

                    // Quyền cho Phòng ban
                    'offices' => $jatbi->lang("Xem danh sách phòng ban"),
                    'offices.add' => $jatbi->lang("Thêm phòng ban"),
                    'offices.edit' => $jatbi->lang("Sửa phòng ban"),
                    'offices.deleted' => $jatbi->lang("Xóa phòng ban"),

                    // Quyền cho Danh mục tiền lương
                    // 'salary-categorys' => $jatbi->lang("Xem danh mục tiền lương"),
                    // 'salary-categorys.add' => $jatbi->lang("Thêm danh mục tiền lương"),
                    // 'salary-categorys.edit' => $jatbi->lang("Sửa danh mục tiền lương"),
                    // 'salary-categorys.deleted' => $jatbi->lang("Xóa danh mục tiền lương"),

                    // Quyền cho Thời gian làm việc
                    'timework' => $jatbi->lang("Xem thời gian làm việc"),
                    'timework.add' => $jatbi->lang("Thêm thời gian làm việc"),
                    'timework.edit' => $jatbi->lang("Sửa thời gian làm việc"),
                    'timework.deleted' => $jatbi->lang("Xóa thời gian làm việc"),

                    // Quyền cho Danh mục nghỉ phép
                    'furlough-categorys' => $jatbi->lang("Danh mục nghỉ phép"),
                    'furlough-categorys.add' => $jatbi->lang("Thêm Danh mục nghỉ phép"),
                    'furlough-categorys.edit' => $jatbi->lang("Sửa Danh mục nghỉ phép"),
                    'furlough-categorys.deleted' => $jatbi->lang("Xóa Danh mục nghỉ phép"),

                    // Quyền cho Nghỉ phép
                    'furlough' => $jatbi->lang("Nghỉ phép"),
                    'furlough.add' => $jatbi->lang("Thêm Nghỉ phép"),
                    'furlough.edit' => $jatbi->lang("Sửa Nghỉ phép"),
                    'furlough.deleted' => $jatbi->lang("Xóa Nghỉ phép"),

                    // Quyền cho Ngày lễ
                    // 'holiday' => $jatbi->lang("Ngày lễ"),
                    // 'holiday.add' => $jatbi->lang("Thêm Ngày lễ"),
                    // 'holiday.edit' => $jatbi->lang("Sửa Ngày lễ"),
                    // 'holiday.deleted' => $jatbi->lang("Xóa Ngày lễ"),

                    // Quyền cho Xem thưởng và kỉ luật
                    'reward-discipline' => $jatbi->lang("Khen thưởng kỉ luật"),
                    'reward-discipline.add' => $jatbi->lang("Thêm Khen thưởng kỉ luật"),
                    'reward-discipline.edit' => $jatbi->lang("Sửa Khen thưởng kỉ luật"),
                    'reward-discipline.deleted' => $jatbi->lang("Xóa Khen thưởng kỉ luật"),

                    // Quyền cho Đi trễ về sớm
                    // 'time-late' => $jatbi->lang("Đi trễ về sớm"),
                    // 'time-late.add' => $jatbi->lang("Thêm Đi trễ về sớm"),
                    // 'time-late.edit' => $jatbi->lang("Sửa Đi trễ về sớm"),
                    // 'time-late.deleted' => $jatbi->lang("Xóa Đi trễ về sớm"),    

                    // 'salary-advance' => $jatbi->lang("Ứng lương"),
                    // 'salary-advance.add' => $jatbi->lang("Thêm Ứng lương"),
                    // 'salary-advance.edit' => $jatbi->lang("Sửa Ứng lương"),
                    // 'salary-advance.deleted' => $jatbi->lang("Xóa Ứng lương"),

                    'timekeeping' => $jatbi->lang("Chấm công"),
                    'timekeeping.add' => $jatbi->lang("Thêm Chấm công"),
                    'timekeeping.edit' => $jatbi->lang("Sửa Chấm công"),
                    'timekeeping.deleted' => $jatbi->lang("Xóa Chấm công"),

                    // 'salary' => $jatbi->lang("Tính lương"),
                ]
            ],
        ],
    ],
];
