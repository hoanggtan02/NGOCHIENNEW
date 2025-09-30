<?php
if (!defined('ECLO'))
    die("Hacking attempt");

use ECLO\App;

$template = __DIR__ . '/../templates';
$jatbi = $app->getValueData('jatbi');
$common = $jatbi->getPluginCommon('io.eclo.proposal');
$setting = $app->getValueData('setting');

$stores_json = $app->getCookie('stores') ?? json_encode([]);
$stores = json_decode($stores_json, true);
$session = $app->getSession("accounts");
$accStore = [];
if (isset($session['id'])) {
    $account = $app->get("accounts", "*", [
        "id" => $session['id'],
        "deleted" => 0,
        "status" => "A",
    ]);
    if ($account['stores'] == '') {
        $accStore[0] = "0";
    }

    foreach ($stores as $itemStore) {
        $accStore[$itemStore['value']] = $itemStore['value'];
    }
}
$app->group($setting['manager'] . "/training", function ($app) use ($jatbi, $setting, $stores, $accStore,$template) {

    $app->router('/courses', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting,$template) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Danh sách Khóa học");
            echo $app->render($template . '/courses/courses.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            // Lấy tham số từ DataTables
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : $setting['site_page'] ?? 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $status = isset($_POST['status']) ? [$_POST['status'], $_POST['status']] : '';

            $where = [
                "AND" => [
                    "OR" => [
                        "courses.course_name[~]" => $searchValue,
                    ],
                    "courses.deleted" => 0,
                    "courses.status[<>]" => $status,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];

            $count = $app->count("courses", [
                "AND" => $where['AND'],
            ]);


            $datas = [];
            $app->select("courses", "*", $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "code" => $data['code'] ?? "",
                    "course_name" => $data['course_name'] ?? "",
                    "description" => $data['description'] ?? "",
                    "duration" => $data['duration'] ?? 0,
                    "status" => $app->component("status", [
                        "url" => "/training/courses-status/" . $data['id'],
                        "data" => $data['status'],
                        "permission" => ['courses.edit']
                    ]),
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['courses.edit'],
                                'action' => ['data-url' => '/training/courses-edit/' . $data['id'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'permission' => ['courses.delete'],
                                'action' => ['data-url' => '/training/courses-delete?box=' . $data['id'], 'data-action' => 'modal']
                            ],
                        ]
                    ]),
                ];
            });

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas
            ]);
        }
    })->setPermissions(['courses']);

    $app->router('/courses-add', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting,$template) {
        $vars['title'] = $jatbi->lang("Thêm Khóa học");

        if ($app->method() === 'GET') {
            echo $app->render($template . '/courses/courses-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $post = array_map([$app, 'xss'], $_POST);

            if (empty($post['course_name']) || empty($post['duration'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng điền đủ thông tin bắt buộc")]);
                return;
            }

            $insert_data = [
                "code" => $post['code'],
                "course_name" => $post['course_name'],
                "description" => $post['description'],
                "duration" => $post['duration'],
                "status" => $post['status']
            ];
            $app->insert("courses", $insert_data);
            $jatbi->logs('courses', 'add', $insert_data);

            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Thêm mới thành công')]);
        }
    })->setPermissions(['courses.add']);

    $app->router('/courses-edit/{id}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting,$template) {
        $id = (int) ($vars['id'] ?? 0);
        $vars['title'] = $jatbi->lang("Sửa Khóa học");

        $data = $app->get("courses", "*", ["id" => $id, "deleted" => 0]);
        if (!$data)
            return $app->render($template . '/error.html', $vars, $jatbi->ajax());
        $vars['data'] = $data;

        if ($app->method() === 'GET') {
            echo $app->render($template . '/courses/courses-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $post = array_map([$app, 'xss'], $_POST);

            if (empty($post['course_name']) || empty($post['duration'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng điền đủ thông tin bắt buộc")]);
                return;
            }

            $update_data = [
                "code" => $post['code'],
                "course_name" => $post['course_name'],
                "description" => $post['description'],
                "duration" => $post['duration']
            ];
            $app->update("courses", $update_data, ["id" => $id]);
            $jatbi->logs('courses', 'edit', $update_data, $id);

            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
        }
    })->setPermissions(['courses.edit']);

    $app->router('/courses-delete', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting,$template) {

        $vars['title'] = $jatbi->lang("Xóa Khóa học");

        if ($app->method() === 'GET') {
            echo $app->render($template . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $box_ids = explode(',', $app->xss($_GET['box'] ?? ''));
            if (empty(array_filter($box_ids))) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng chọn dữ liệu cần xóa")]);
                return;
            }

            $datas = $app->select("courses", "*", ["id" => $box_ids]);
            if (count($datas) > 0) {
                $app->update("courses", ["deleted" => 1], ["id" => $box_ids]);
                $jatbi->logs('courses', 'delete', $datas);
                $jatbi->trash('/courses/restore', "Xóa khóa học: " . implode(', ', array_column($datas, 'course_name')), ["database" => 'courses', "data" => $box_ids]);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xảy ra hoặc không tìm thấy dữ liệu")]);
            }
        }
    })->setPermissions(['courses.delete']);

    $app->router("/courses-status/{id}", 'POST', function ($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $data = $app->get("courses", "*", ["id" => $vars['id'], "deleted" => 0]);
        if ($data > 1) {
            if ($data > 1) {
                if ($data['status'] === 'A') {
                    $status = "D";
                } elseif ($data['status'] === 'D') {
                    $status = "A";
                }
                $app->update("courses", ["status" => $status], ["id" => $data['id']]);
                $jatbi->logs('courses', 'courses-status', $data);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại"),]);
            }
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['courses.edit']);

    $app->router('/training-sessions', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting,$template) {

        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Danh sách Lớp học");
            $vars['courses'] = $app->select("courses", ["id(value)", "course_name(text)"], ["deleted" => 0, "status" => 'A']);
            echo $app->render($template . '/training/training_sessions.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';

            $orderName = isset($_POST['order'][0]['column']) ? $_POST['columns'][$_POST['order'][0]['column']]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'ASC';

            $status = isset($_POST['status']) ? [$_POST['status'], $_POST['status']] : '';
            $filter_course = $_POST['course'] ?? '';


            $joins = ["[>]courses" => ["course_id" => "id"]];

            $where = [
                "AND" => [
                    "OR" => [
                        // "courses.course_name[~]" => $searchValue,
                        "training_sessions.trainer[~]" => $searchValue,
                        // "training_sessions.location[~]" => $searchValue
                    ],
                    "training_sessions.deleted" => 0,
                    "training_sessions.status[<>]" => $status,
                ],
            ];


            if (!empty($filter_course)) {
                $where['AND']['courses.id'] = $filter_course;
            }

            $count = $app->count("training_sessions", [
                "AND" => $where['AND'],
            ]);

            $columns = [
                "training_sessions.id",
                "training_sessions.trainer",
                "training_sessions.code_class",
                "training_sessions.start_date",
                "training_sessions.end_date",
                "training_sessions.location",
                "training_sessions.status",
                "courses.course_name(c_name)",
            ];

            $where["ORDER"] = [$orderName => strtoupper($orderDir)];
            $where["LIMIT"] = [$start, $length];

            $datas = [];
            $app->select("training_sessions", $joins, $columns, $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "code_class" => $data['code_class'],
                    "course_name" => $data['c_name'],
                    "trainer" => $data['trainer'],
                    "start_date" => date('d/m/Y H:i', strtotime($data['start_date'])),
                    "end_date" => date('d/m/Y H:i', strtotime($data['end_date'])),
                    "location" => $data['location'],
                    "status" => $app->component("status", [
                        "url" => "/training/training-sessions-status/" . $data['id'],
                        "data" => $data['status'],
                        "permission" => ['training_sessions.edit']
                    ]),
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['courses.edit'],
                                'action' => ['data-url' => '/training/training-sessions-edit/' . $data['id'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'permission' => ['courses.delete'],
                                'action' => ['data-url' => '/training/training-sessions-delete?box=' . $data['id'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'link',
                                'name' => $jatbi->lang("Xem"),
                                'permission' => ['training_sessions'],
                                'action' => ['href' => '/training/training-sessions/enrollments/' . $data['id'], 'data-pjax' => '']
                            ],

                        ]
                    ]),
                ];
            });
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas,
            ]);
        }
    })->setPermissions(['training_sessions']);

    $app->router('/training-sessions-add', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting,$template) {
        $vars['title'] = $jatbi->lang("Thêm Lớp học");

        if ($app->method() === 'GET') {
            $vars['courses'] = $app->select("courses", ["id(value)", "course_name(text)"], ["deleted" => 0, "status" => 'A']);
            echo $app->render($template . '/training/training_sessions-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $post = array_map([$app, 'xss'], $_POST);

            if (empty($post['course_id']) || empty($post['start_date'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng điền đủ thông tin bắt buộc")]);
                return;
            }

            $insert_data = [
                "course_id" => $post['course_id'],
                "trainer" => $post['trainer'],
                "start_date" => $post['start_date'],
                "end_date" => $post['end_date'],
                "location" => $post['location'],
                "status" => $post['status']
            ];
            $app->insert("training_sessions", $insert_data);
            $jatbi->logs('training_sessions', 'add', $insert_data);

            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Thêm mới thành công')]);
        }
    })->setPermissions(['training_sessions.add']);

    $app->router('/training-sessions-edit/{id}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting,$template) {
        $id = (int) ($vars['id'] ?? 0);
        $vars['title'] = $jatbi->lang("Sửa Lớp học");

        $data = $app->get("training_sessions", "*", ["id" => $id, "deleted" => 0]);
        if (!$data) {
            return $app->render($template . '/error.html', $vars, $jatbi->ajax());
        }
        $vars['data'] = $data;

        if ($app->method() === 'GET') {
            $vars['courses'] = $app->select("courses", ["id(value)", "course_name(text)"], ["deleted" => 0, "status" => 'A']);
            echo $app->render($template . '/training/training_sessions-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $post = array_map([$app, 'xss'], $_POST);

            if (empty($post['course_id']) || empty($post['start_date'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng điền đủ thông tin bắt buộc")]);
                return;
            }

            $update_data = [
                "course_id" => $post['course_id'],
                "trainer" => $post['trainer'],
                "start_date" => $post['start_date'],
                "end_date" => $post['end_date'],
                "location" => $post['location'],
                "status" => $post['status']
            ];

            $app->update("training_sessions", $update_data, ["id" => $id]);
            $jatbi->logs('training_sessions', 'edit', $update_data, $id);

            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
        }
    })->setPermissions(['training_sessions.edit']);

    $app->router('/training-sessions-delete', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting,$template) {

        $vars['title'] = $jatbi->lang("Xóa lớp học");

        if ($app->method() === 'GET') {
            echo $app->render($template . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $box_ids = explode(',', $app->xss($_GET['box'] ?? ''));
            if (empty(array_filter($box_ids))) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng chọn dữ liệu cần xóa")]);
                return;
            }

            $datas = $app->select("training_sessions", "*", ["id" => $box_ids]);
            if (count($datas) > 0) {
                $app->update("training_sessions", ["deleted" => 1], ["id" => $box_ids]);
                $jatbi->logs('training_sessions', 'delete', $datas);
                $jatbi->trash('/training_sessions/restore', "Xóa lớp học: " . implode(', ', array_column($datas, 'id')), ["database" => 'training_sessions', "data" => $box_ids]);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xảy ra hoặc không tìm thấy dữ liệu")]);
            }
        }
    })->setPermissions(['training_sessions.delete']);

    $app->router("/training-sessions-status/{id}", 'POST', function ($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $data = $app->get("training_sessions", "*", ["id" => $vars['id'], "deleted" => 0]);
        if ($data > 1) {
            if ($data > 1) {
                if ($data['status'] === 'A') {
                    $status = "D";
                } elseif ($data['status'] === 'D') {
                    $status = "A";
                }
                $app->update("training_sessions", ["status" => $status], ["id" => $data['id']]);
                $jatbi->logs('training_sessions', 'training_sessions-status', $data);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại"),]);
            }
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['training_sessions.edit']);

    $app->router('/training-sessions/enrollments/{id}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting,$template) {
        $session_id = (int) ($vars['id'] ?? 0);

        $session_data = $app->get("training_sessions", ["[>]courses" => ["course_id" => "id"]], [
            "training_sessions.id",
            "courses.course_name",
            "training_sessions.start_date"
        ], ["training_sessions.id" => $session_id]);

        if (!$session_data) {
            return $app->render($template . '/error.html', $vars);
        }

        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Lớp:") . $session_data['course_name'];
            $vars['session_data'] = $session_data;
            echo $app->render($template . '/training/enrollment_list.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);
            $draw = $_POST['draw'] ?? 0;
            $start = $_POST['start'] ?? 0;
            $length = $_POST['length'] ?? 10;

            $joins = [
                "[>]personnels(p)" => ["employee_id" => "id"]
            ];

            $where = [
                "AND" => [
                    "employee_enrollments.deleted" => 0,
                    "employee_enrollments.session_id" => $session_id
                ]
            ];

            $count = $app->count("employee_enrollments", $joins, "employee_enrollments.id", $where);

            $where["LIMIT"] = [$start, $length];

            $columns = [
                "employee_enrollments.id",
                "employee_enrollments.status",
                "employee_enrollments.result",
                "p.name(employee_name)",
                "p.code(employee_code)"
            ];

            $datas = [];
            $app->select("employee_enrollments", $joins, $columns, $where, function ($data) use (&$datas, $jatbi, $app) {

                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "employee_name" => $data['employee_code'] . ' - ' . $data['employee_name'],
                    "status" => $data['status'],
                    "result" => $data['result'],
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['enrollments.edit'],
                                'action' => ['data-url' => '/training/employee-enrollments-edit/' . $data['id'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'permission' => ['enrollments.delete'],
                                'action' => ['data-url' => '/training/employee-enrollments/delete?box=' . $data['id'], 'data-action' => 'modal']
                            ],
                        ]
                    ]),
                ];
            });

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas
            ]);
        }
    })->setPermissions(['enrollments']);

    $app->router('/employee-enrollments-add', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting,$template) {
        $vars['title'] = $jatbi->lang("Ghi danh Nhân viên");

        if ($app->method() === 'GET') {
            if (isset($_GET['session_id'])) {
                $vars['data']['session_id'] = (int) $_GET['session_id'];
            }

            $empty_option = [['value' => '', 'text' => $jatbi->lang('')]];

            $sessions_db = $app->select("training_sessions", ["[>]courses" => ["course_id" => "id"]], ["training_sessions.id(value)", "text" => Medoo\Medoo::raw("CONCAT(courses.course_name, ' (', DATE_FORMAT(training_sessions.start_date, '%d/%m/%Y'), ')')")], ["training_sessions.deleted" => 0]);
            $vars['sessions'] = array_merge($empty_option, $sessions_db);

            $employees_db = $app->select(
                "personnels",
                ["id", "code", "name"],
                ["deleted" => 0, "status" => 'A']
            );

            $formatted_employees = array_map(function ($employee) {
                return [
                    'value' => $employee['id'],
                    'text' => $employee['code'] . ' - ' . $employee['name']
                ];
            }, $employees_db);

            $vars['employees'] = array_merge($empty_option, $formatted_employees);

            echo $app->render($template . '/training/enrollment-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $post = array_map([$app, 'xss'], $_POST);

            if (empty($post['employee_id']) || empty($post['session_id'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng chọn Nhân viên và Lớp học")]);
                return;
            }

            $is_existed = $app->count("employee_enrollments", [
                "employee_id" => $post['employee_id'],
                "session_id" => $post['session_id'],
                "deleted" => 0
            ]);

            if ($is_existed > 0) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Nhân viên này đã được ghi danh vào lớp học.")]);
                return;
            }

            $insert_data = [
                "employee_id" => $post['employee_id'],
                "session_id" => $post['session_id'],
                "status" => $post['status'],
                "result" => $post['result']
            ];
            $app->insert("employee_enrollments", $insert_data);
            $jatbi->logs('employee_enrollments', 'add', $insert_data);

            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Thêm mới thành công')]);
        }
    })->setPermissions(['enrollments.add']);

    $app->router('/employee-enrollments-edit/{id}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting,$template) {
        $id = (int) ($vars['id'] ?? 0);
        $vars['title'] = $jatbi->lang("Cập nhật Ghi danh");

        $data = $app->get("employee_enrollments", "*", ["id" => $id, "deleted" => 0]);
        if (!$data)
            return $app->render($template . '/error.html', $vars, $jatbi->ajax());
        $vars['data'] = $data;

        if ($app->method() === 'GET') {
            $vars['employees'] = $app->select("personnels", ["id(value)", "name(text)"], ["deleted" => 0, "status" => 'A']);
            $vars['sessions'] = $app->select("training_sessions", ["[>]courses" => ["course_id" => "id"]], ["training_sessions.id(value)", "text" => Medoo\Medoo::raw("CONCAT(courses.course_name, ' (', DATE_FORMAT(training_sessions.start_date, '%d/%m/%Y'), ')')")], ["training_sessions.deleted" => 0]);
            echo $app->render($template . '/training/enrollment-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $post = array_map([$app, 'xss'], $_POST);

            if (empty($post['employee_id']) || empty($post['session_id'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng chọn Nhân viên và Lớp học")]);
                return;
            }

            $update_data = [
                "employee_id" => $post['employee_id'],
                "session_id" => $post['session_id'],
                "status" => $post['status'],
                "result" => $post['result']
            ];
            $app->update("employee_enrollments", $update_data, ["id" => $id]);
            $jatbi->logs('employee_enrollments', 'edit', $update_data, $id);

            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
        }
    })->setPermissions(['enrollments.edit']);

    $app->router('/employee-enrollments/delete', ['GET', 'POST'], function ($vars) use ($app, $jatbi,$template,$setting) {
        $vars['title'] = $jatbi->lang("Xóa Ghi danh");

        if ($app->method() === 'GET') {
            echo $app->render($template . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $box_ids = explode(',', $app->xss($_GET['box'] ?? ''));
            if (empty(array_filter($box_ids))) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng chọn dữ liệu cần xóa")]);
                return;
            }

            $datas = $app->select("employee_enrollments", "*", ["id" => $box_ids, "deleted" => 0]);
            if (count($datas) > 0) {
                $app->update("employee_enrollments", ["deleted" => 1], ["id" => $box_ids]);
                $jatbi->logs('employee_enrollments', 'delete', $datas);
                $jatbi->trash('/employee-enrollments/restore', "Xóa ghi danh", ["database" => 'employee_enrollments', "data" => $box_ids]);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xảy ra hoặc không tìm thấy dữ liệu")]);
            }
        }
    })->setPermissions(['enrollments.delete']);
    
})->middleware('login');
