<?php
$jatbi = $app->getValueData('jatbi');
$setting = $app->getValueData('setting');
return [
    "content" => [
        "item" => [
            'recruitment' => [
                "menu" => $jatbi->lang("Tuyển dụng"),
                "url" => '/recruitment',
                "icon" => '<i class="ti ti-search"></i>',
                "sub" => [
                    'job_postings' => [
                        "name" => $jatbi->lang("Tin tuyển dụng"),
                        "router" => '/recruitment/job_postings',
                        "icon" => '<i class="ti ti-user-check"></i>',
                    ],
                    'candidates' => [
                        "name" => $jatbi->lang("Hồ sơ ứng viên"),
                        "router" => '/recruitment/candidates',
                        "icon" => '<i class="ti ti-file-text"></i>',
                    ],
                    'applications' => [
                        "name" => $jatbi->lang("Theo dõi quy trình ứng viên"),
                        "router" => '/recruitment/applications',
                        "icon" => '<i class="ti ti-shield-check"></i>',
                    ],
                    'interviews' => [
                        "name" => $jatbi->lang("Lịch phỏng vấn"),
                        "router" => '/recruitment/interviews',
                        "icon" => '<i class="ti ti-calendar-event"></i>',
                    ],
                ],
                "main" => 'false',
                "permission" => [
                    // Quyền cho Tin tuyển dụng
                    'job_postings' => $jatbi->lang("Xem danh sách tin tuyển dụng"),
                    'job_postings.full' => $jatbi->lang("Xem tất cả danh sách tin tuyển dụng"),
                    'job_postings.add' => $jatbi->lang("Thêm tuyển tin dụng"),
                    'job_postings.edit' => $jatbi->lang("Sửa tuyển tin dụng"),
                    'job_postings.deleted' => $jatbi->lang("Xóa tin tuyển dụng"),

                    // Quyền cho Hồ sơ ứng viên
                    'candidates' => $jatbi->lang("Xem danh sách hồ sơ ứng viên"),
                    'candidates.add' => $jatbi->lang("Thêm hồ sơ ứng viên"),
                    'candidates.edit' => $jatbi->lang("Sửa hồ sơ ứng viên"),
                    'candidates.deleted' => $jatbi->lang("Xóa hồ sơ ứng viên"),

                    // Quyền cho Theo dõi quy trình ứng viên
                    'applications' => $jatbi->lang("Xem quy trình ứng viên"),
                    'applications.add' => $jatbi->lang("Thêm quy trình ứng viên"),
                    'applications.edit' => $jatbi->lang("Sửa quy trình ứng viên"),
                    'applications.deleted' => $jatbi->lang("Xóa quy trình ứng viên"),

                    // Quyền cho Lịch phỏng vấn
                    'interviews' => $jatbi->lang("Xem lịch phỏng vấn"),
                    'interviews.add' => $jatbi->lang("Tạo lịch phỏng vấn"),
                    'interviews.edit' => $jatbi->lang("Sửa lịch phỏng vấn"),
                    'interviews.deleted' => $jatbi->lang("Xóa lịch phỏng vấn"),

                ]
            ],
        ],
    ],
];
