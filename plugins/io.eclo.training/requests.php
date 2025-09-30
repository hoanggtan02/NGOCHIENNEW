<?php
$jatbi = $app->getValueData('jatbi');
$setting = $app->getValueData('setting');
return [
    "content" => [
        "item" => [
            'training' => [
                "menu" => $jatbi->lang(key: "Đào tạo"),
                "url" => '/training',
                "icon" => '<i class="ti ti-school"></i>',
                "sub" => [
                    'courses' => [
                        "name" => $jatbi->lang("Quản lý khóa học"),
                        "router" => '/training/courses',
                        "icon" => '<i class="ti ti-book-2"></i>',
                    ],
                    'training_sessions' => [
                        "name" => $jatbi->lang("Quản lý lớp học"),
                        "router" => '/training/training-sessions',
                        "icon" => '<i class="ti ti-calendar-event"></i>',
                    ],
                    // 'enrollments' => [
                    //     "name" => $jatbi->lang("Quản lý ghi danh"),
                    //     "router" => '/training/employee-enrollments',
                    //     "icon" => '<i class="ti ti-user-check"></i>',
                    // ],
                ],
                "main" => 'false',
                "permission" => [
                    'courses' => $jatbi->lang("Danh sách khóa học"),
                    'courses.add' => $jatbi->lang("Thêm khóa học mới"),
                    'courses.edit' => $jatbi->lang("Sửa thông tin khóa học"),
                    'courses.delete' => $jatbi->lang("Xóa khóa học"),

                    'enrollments' => $jatbi->lang("Danh sách ghi danh"),
                    'enrollments.add' => $jatbi->lang("Ghi danh nhân viên"),
                    'enrollments.edit' => $jatbi->lang("Sửa thông tin ghi danh"),
                    'enrollments.delete' => $jatbi->lang("Xóa ghi danh"),

                    'training_sessions' => $jatbi->lang("Danh sách lớp học"),
                    'training_sessions.add' => $jatbi->lang("Tạo lớp học mới"),
                    'training_sessions.edit' => $jatbi->lang("Sửa thông tin lớp học"),
                    'training_sessions.delete' => $jatbi->lang("Xóa lớp học"),

                ]
            ],
        ],
    ],
];
