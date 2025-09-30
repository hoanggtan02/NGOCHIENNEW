<?php
if (!defined('ECLO'))
    die("Hacking attempt");
use ECLO\App;
$template = __DIR__.'/../templates';
$jatbi = $app->getValueData('jatbi');
$common = $jatbi->getPluginCommon('io.eclo.proposal');
$setting = $app->getValueData('setting');
// Xử lý session và stores
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
        $accStore[0] = "0"; // chỉ thêm 1 lần
    }

    foreach ($stores as $itemStore) {
        $accStore[$itemStore['value']] = $itemStore['value'];
    }
}

$app->group($setting['manager'] . "/accountants", function ($app) use ($jatbi, $setting, $stores, $accStore) {

    $app->router("/accounts-code", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Mã tài khoản");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/accountants/accounts-code.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);

            // Lấy tham số từ DataTables
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : $setting['site_page'] ?? 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'code';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'ASC';
            $status = isset($_POST['status']) ? [$_POST['status'], $_POST['status']] : '';

            // Điều kiện lọc
            $where = [
                "AND" => [
                    "OR" => [
                        "name[~]" => $searchValue ? $app->xss($searchValue) : '%',
                        "code[~]" => $searchValue ? $app->xss($searchValue) : '%',
                    ],
                    "deleted" => 0,
                    "main" => 0,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];
            if ($status !== '') {
                $where["AND"]["status"] = $status;
            }

            $count = $app->count("accountants_code", [
                "AND" => $where['AND'],
            ]);

            $datas = [];
            // Tải trước mã con để tránh N+1 query
            $main_ids = []; // Lưu ID của mã cha để truy vấn mã con
            $app->select("accountants_code", ["id"], $where, function ($data) use (&$main_ids) {
                $main_ids[] = $data['id']; // Thu thập ID mã cha
            });

            // Truy vấn tất cả mã con liên quan
            $all_sub_codes = $app->select("accountants_code", ["id", "code", "name", "notes", "status", "main"], [
                "deleted" => 0,
                "status" => 'A', // Có thể bỏ điều kiện status nếu muốn hiển thị tất cả mã con
                "main" => $main_ids
            ], function ($sub) use (&$SelectMainsById, $app, $jatbi) {
                // Định dạng mã con tương tự mã cha
                $SelectMainsById[$sub['main']][] = [
                    "checkbox" => $app->component("box", ["data" => $sub['id']]),
                    "code" => $sub['code'],
                    "name" => $sub['name'],
                    "notes" => $sub['notes'],
                    "status" => $app->component("status", [
                        "url" => "/accountants/accounts-code-status/" . $sub['id'],
                        "data" => $sub['status'],
                        "permission" => ['accounts-code.edit']
                    ]),
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['accounts-code.edit'],
                                'action' => ['data-url' => '/accountants/accounts-code-edit/' . $sub['id'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'permission' => ['accounts-code.delete'],
                                'action' => ['data-url' => '/accountants/accounts-code-delete?box=' . $sub['id'], 'data-action' => 'modal']
                            ],
                        ]
                    ]),
                    "is_sub" => true, // Đánh dấu là mã con để giao diện có thể xử lý khác biệt (nếu cần)
                    "DT_RowClass" => "sub-row"
                ];
            });

            // Xử lý mã cha và tích hợp mã con
            $datas = [];
            $app->select("accountants_code", ["id", "code", "name", "notes", "status"], $where, function ($data) use (&$datas, $jatbi, $app, $SelectMainsById) {
                // Thêm mã cha vào $datas
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "code" => $data['code'],
                    "name" => $data['name'],
                    "notes" => $data['notes'],
                    "status" => $app->component("status", [
                        "url" => "/accountants/accounts-code-status/" . $data['id'],
                        "data" => $data['status'],
                        "permission" => ['accounts-code.edit']
                    ]),
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['accounts-code.edit'],
                                'action' => ['data-url' => '/accountants/accounts-code-edit/' . $data['id'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'permission' => ['accounts-code.delete'],
                                'action' => ['data-url' => '/accountants/accounts-code-delete?box=' . $data['id'], 'data-action' => 'modal']
                            ],
                        ]
                    ]),
                    "is_sub" => false, // Đánh dấu là mã cha
                    "DT_RowClass" => ""
                ];

                // Thêm mã con (nếu có) vào $datas
                if (!empty($SelectMainsById[$data['id']])) {
                    foreach ($SelectMainsById[$data['id']] as $sub_code) {
                        $datas[] = $sub_code;
                    }
                }
            });

            // Trả về dữ liệu JSON
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas ?? [],
            ]);
        }
    })->setPermissions(['accounts-code']);

    // Route: Thêm mã tài khoản
    $app->router("/accounts-code-add", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Thêm mã tài khoản");

        if ($app->method() === 'GET') {
            // Lấy danh sách mã cha
            $accountants_codes = $app->select("accountants_code", ["code (value)", "name (text)", "code"], ["deleted" => 0, "status" => 'A', "main" => 0]);
            array_unshift($accountants_codes, [
                'value' => '0',
                'text' => $jatbi->lang('Cấp độ chính')
            ]);
            $vars["accountants_codes"] = $accountants_codes;

            echo $app->render($setting['template'] . '/accountants/accounts-code-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $error = [];

            // Kiểm tra các trường bắt buộc
            if ($app->xss($_POST['name']) == '' || $app->xss($_POST['code']) == '') {
                $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")];
            }
            // Kiểm tra mã trùng
            elseif ($app->get("accountants_code", "code", ["code" => $app->xss($_POST['code']), "deleted" => 0])) {
                $error = ["status" => "error", "content" => $jatbi->lang("Mã tài khoản đã tồn tại")];
            }

            if (empty($error)) {
                $insert = [
                    "main" => $app->xss($_POST['main']),
                    "name" => $app->xss($_POST['name']),
                    "code" => $app->xss($_POST['code']),
                    "notes" => $app->xss($_POST['notes']),
                    "status" => $app->xss($_POST['status']),
                ];
                $app->insert("accountants_code", $insert);
                $jatbi->logs('accounts-code', 'add', $insert);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công"), 'url' => $_SERVER['HTTP_REFERER']]);
            } else {
                echo json_encode($error);
            }
        }
    })->setPermissions(['accounts-code.add']);

    // Route: Sửa mã tài khoản
    $app->router("/accounts-code-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Chỉnh sửa mã tài khoản");

        if ($app->method() === 'GET') {
            $vars['data'] = $app->get("accountants_code", "*", ["id" => $vars['id'], "deleted" => 0]);
            if ($vars['data']) {
                // Lấy danh sách mã cha
                $accountants_codes = $app->select("accountants_code", ["code (value)", "name (text)", "code"], ["deleted" => 0, "status" => 'A']);
                array_unshift($accountants_codes, [
                    'value' => '0',
                    'text' => $jatbi->lang('Cấp độ chính')
                ]);
                $vars["accountants_codes"] = $accountants_codes;

                echo $app->render($setting['template'] . '/accountants/accounts-code-post.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $error = [];

            // Kiểm tra các trường bắt buộc
            if ($app->xss($_POST['name']) == '' || $app->xss($_POST['code']) == '') {
                $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")];
            }
            // Kiểm tra mã trùng (ngoại trừ mã của bản ghi hiện tại)
            elseif ($app->get("accountants_code", "code", ["code" => $app->xss($_POST['code']), "deleted" => 0, "id[!]" => $vars['id']])) {
                $error = ["status" => "error", "content" => $jatbi->lang("Mã tài khoản đã tồn tại")];
            }

            if (empty($error)) {
                $update = [
                    "main" => $app->xss($_POST['main']),
                    "name" => $app->xss($_POST['name']),
                    "code" => $app->xss($_POST['code']),
                    "notes" => $app->xss($_POST['notes']),
                    "status" => $app->xss($_POST['status']),
                ];
                $app->update("accountants_code", $update, ["id" => $vars['id']]);
                $jatbi->logs('accounts-code', 'edit', $update);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công"), 'url' => $_SERVER['HTTP_REFERER']]);
            } else {
                echo json_encode($error);
            }
        }
    })->setPermissions(['accounts-code.edit']);

    // Route: Thay đổi trạng thái
    $app->router("/accounts-code-status/{id}", ['POST'], function ($vars) use ($app, $jatbi, $setting) {
        $app->header(['Content-Type' => 'application/json']);
        $data = $app->get("accountants_code", ["id", "status"], ["id" => $vars['id'], "deleted" => 0]);

        if ($data) {
            $status = $data['status'] === 'A' ? 'D' : 'A';
            $app->update("accountants_code", ["status" => $status], ["id" => $data['id']]);
            $jatbi->logs('accounts-code', 'status', ["data" => $data, "status" => $status]);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật trạng thái thành công")]);
        } else {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại")]);
        }
    })->setPermissions(['accounts-code.edit']);

    // Route: Xóa mã tài khoản
    $app->router("/accounts-code-delete", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa mã tài khoản");

        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("accountants_code", "*", ["id" => $boxid, "deleted" => 0]);

            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("accountants_code", ["deleted" => 1], ["id" => $data['id']]);
                }
                $jatbi->logs('accounts-code', 'delete', $datas);
                $jatbi->trash('/accountants-code/restore', "Xóa mã tài khoản: ", ["database" => 'accountants_code', "data" => $boxid]);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xảy ra")]);
            }
        }
    })->setPermissions(['accounts-code.deleted']);

    $app->router("/accountant", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Kế toán");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/accountants/accountant.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);

            // Lấy tham số từ DataTables
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : $setting['site_page'] ?? 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $status = isset($_POST['status']) ? [$_POST['status'], $_POST['status']] : '';

            // Điều kiện lọc
            $where = [
                "AND" => [
                    "OR" => [
                        "name[~]" => $searchValue,
                    ],
                    "deleted" => 0,
                    "status[<>]" => $status,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];

            $count = $app->count("type_accountant", [
                "AND" => $where['AND'],
            ]);

            $datas = [];
            $app->select("type_accountant", ["id", "name", "note", "status"], $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "name" => $data['name'],
                    "notes" => $data['note'],
                    "status" => $app->component("status", [
                        "url" => "/accountants/accountant-status/" . $data['id'],
                        "data" => $data['status'],
                        "permission" => ['accountant.edit']
                    ]),
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'link',
                                'name' => $jatbi->lang("Xem"),
                                'permission' => ['accountant'],
                                'action' => ['href' => '/accountants/accountant-type/' . $data['id'], 'data-pjax' => '']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['accountant.edit'],
                                'action' => ['data-url' => '/accountants/accountant-edit/' . $data['id'], 'data-action' => 'modal']
                            ],
                            // [
                            //     'type' => 'button',
                            //     'name' => $jatbi->lang("Xóa"),
                            //     'permission' => ['accountant.deleted'],
                            //     'action' => ['data-url' => '/accountants/accountant-delete?box=' . $data['id'], 'data-action' => 'modal']
                            // ],
                        ]
                    ]),
                ];
            });

            // Trả về dữ liệu JSON
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas ?? [],
            ]);
        }
    })->setPermissions(['accountant']);

    // Route: Thêm loại kế toán
    // Route: Thêm loại kế toán
    $app->router("/accountant-add", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Thêm loại kế toán");

        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/accountants/accountant-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $error = [];

            if ($app->xss($_POST['name']) === '') {
                $error = ['status' => 'error', 'content' => $jatbi->lang('Vui lòng không để trống')];
            }

            if (empty($error)) {
                $insert = [
                    "name" => $app->xss($_POST['name']),
                    "note" => $app->xss($_POST['notes']),
                    "status" => $app->xss($_POST['status']),
                    "date" => date('Y-m-d H:i:s'),
                ];
                $app->insert("type_accountant", $insert);
                $jatbi->logs('type_accountant', 'add', $insert);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công"), 'url' => $_SERVER['HTTP_REFERER']]);
            } else {
                echo json_encode($error);
            }
        }
    })->setPermissions(['accountant.add']);

    // Route: Sửa loại kế toán
    $app->router("/accountant-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Chỉnh sửa loại kế toán");

        if ($app->method() === 'GET') {
            $vars['data'] = $app->get("type_accountant", "*", ["id" => $vars['id'], "deleted" => 0]);
            if ($vars['data']) {
                echo $app->render($setting['template'] . '/accountants/accountant-post.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $error = [];

            if ($app->xss($_POST['name']) === '') {
                $error = ['status' => 'error', 'content' => $jatbi->lang('Vui lòng không để trống')];
            }

            if (empty($error)) {
                $update = [
                    "name" => $app->xss($_POST['name']),
                    "note" => $app->xss($_POST['notes']),
                    "status" => $app->xss($_POST['status']),
                    "date" => date('Y-m-d H:i:s'),
                ];
                $app->update("type_accountant", $update, ["id" => $vars['id']]);
                $jatbi->logs('type_accountant', 'edit', $update);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công"), 'url' => $_SERVER['HTTP_REFERER']]);
            } else {
                echo json_encode($error);
            }
        }
    })->setPermissions(['accountant.edit']);

    $app->router("/accountant-status/{id}", ['POST'], function ($vars) use ($app, $jatbi, $setting) {
        $app->header(['Content-Type' => 'application/json']);
        $error = [];

        // Lấy bản ghi
        $data = $app->get("type_accountant", ["id", "status"], ["id" => $vars['id'], "deleted" => 0]);

        if (empty($error) && $data) {
            // Chuyển đổi trạng thái
            $status = $data['status'] === 'A' ? 'D' : 'A';
            $app->update("type_accountant", ["status" => $status], ["id" => $data['id']]);

            // Ghi log
            $jatbi->logs('type_accountant', 'status', ["data" => $data, "status" => $status, "updated_at" => date('Y-m-d H:i:s')]);

            echo json_encode([
                'status' => 'success',
                'content' => $jatbi->lang("Cập nhật trạng thái thành công")
            ]);
        } else {
            $error = empty($error) ? ['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại")] : $error;
            echo json_encode($error);
        }
    })->setPermissions(['accountant.edit']);

    // Route: Xóa loại kế toán
    $app->router("/accountant-delete", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa loại kế toán");

        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("type_accountant", "*", ["id" => $boxid, "deleted" => 0]);

            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("type_accountant", ["deleted" => 1], ["id" => $data['id']]);
                }
                $jatbi->logs('type_accountant', 'delete', $datas);
                $jatbi->trash('/accountants/accountant-restore', "Xóa loại kế toán: ", ["database" => 'type_accountant', "data" => $boxid]);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xảy ra")]);
            }
        }
    })->setPermissions(['accountant.deleted']);

    // Route: Danh sách giao dịch kế toán theo loại
    $app->router("/accountant-type/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $accStore, $stores) {
        // Lấy thông tin loại kế toán
        $type = $app->get("type_accountant", ["id", "name", "status"], ["id" => $vars['id'], "deleted" => 0]);
        if (!$type) {
            echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
        }
        $vars['type'] = $type;
        $vars['title'] = $jatbi->lang($type['name']);

        if ($app->method() === 'GET') {
            // Lấy danh sách loại chi tiêu
            $expenditure_types = $setting['expenditure_type'];
            $expenditure_types_formatted = array_map(function ($item) use ($jatbi) {
                return [
                    'value' => $item['id'],
                    'text' => $jatbi->lang($item['name'])
                ];
            }, $expenditure_types);
            array_unshift($expenditure_types_formatted, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars["expenditure_types"] = $expenditure_types_formatted;

            // Lấy danh sách tài khoản kế toán
            $accountants = $app->select("accountants_code", ["id", "name", "code"], ["deleted" => 0, "status" => 'A']);
            $accountants_formatted = array_map(function ($item) {
                return [
                    'value' => $item['code'],
                    'text' => $item['code'] . ' - ' . $item['name']
                ];
            }, $accountants);
            array_unshift($accountants_formatted, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars["accountants"] = $accountants_formatted;

            // Lấy danh sách cửa hàng từ cookie
            array_unshift($stores, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars['stores'] = $stores;

            // Lấy danh sách tài khoản người dùng
            $accounts = $app->select("accounts", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A']);
            array_unshift($accounts, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars['accounts'] = $accounts;

            // Render template expenditure.html
            echo $app->render($setting['template'] . '/accountants/accountant-type.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            // Lấy tham số từ DataTables
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? $app->xss($_POST['search']['value']) : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'date';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'ASC';

            // Lấy tham số lọc
            $expenditure_type = isset($_POST['expenditure_type']) ? $app->xss($_POST['expenditure_type']) : '';
            $debt = isset($_POST['debt']) ? $app->xss($_POST['debt']) : '';
            $has = isset($_POST['has']) ? $app->xss($_POST['has']) : '';
            $user = isset($_POST['user']) ? $app->xss($_POST['user']) : '';
            $store = isset($_POST['stores']) ? $app->xss($_POST['stores']) : $accStore;
            $date_string = isset($_POST['date']) ? $app->xss($_POST['date']) : '';

            // Xử lý khoảng thời gian
            if ($date_string) {
                $date = explode('-', $date_string);
                $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date[0]))));
                $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date[1]))));
            } else {
                $date_from = date('Y-m-01 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }

            // Xây dựng điều kiện where cho truy vấn chính
            $where = [
                "AND" => [
                    "OR" => [
                        "accountant.ballot[~]" => $searchValue ?: '%',
                        "accountant.invoices[~]" => $searchValue ?: '%',
                    ],
                    "accountant.type_accountant" => $type['id'],
                    "accountant.deleted" => 0,
                    "accountant.date[<>]" => [$date_from, $date_to],
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => ["accountant.date" => strtoupper($orderDir)],
            ];

            // Thêm các điều kiện lọc phụ (chỉ khi có giá trị)
            if (!empty($expenditure_type)) {
                $where['AND']['accountant.type'] = $expenditure_type;
            }
            if (!empty($debt)) {
                $where['AND']['accountant.debt'] = $debt;
            }
            if (!empty($has)) {
                $where['AND']['accountant.has'] = $has;
            }
            if (!empty($user)) {
                $where['AND']['accountant.user'] = $user;
            }
            if (!empty($store)) {
                $where['AND']['accountant.stores'] = $store;
            }

            // Đếm tổng số bản ghi
            $count = $app->count("accountant", ["AND" => $where['AND']]);

            // [FIX] Tách riêng JOIN và WHERE cho các truy vấn SUM để tránh lỗi
            $joinForSum = [
                "[>]accountant" => ["accountant_invoices_products.invoices" => "invoices"]
            ];

            $baseWhereForSum = [
                "AND" => [
                    "accountant.type_accountant" => $type['id'],
                    "accountant.deleted" => 0,
                    "accountant_invoices_products.deleted" => 0,
                    "OR" => [
                        "accountant.ballot[~]" => $searchValue ?: '%',
                        "accountant.invoices[~]" => $searchValue ?: '%',
                    ]
                ]
            ];

            if (!empty($debt)) $baseWhereForSum['AND']["accountant.debt"] = $debt;
            if (!empty($has)) $baseWhereForSum['AND']["accountant.has"] = $has;
            if (!empty($user)) $baseWhereForSum['AND']["accountant.user"] = $user;
            if (!empty($store)) $baseWhereForSum['AND']["accountant.stores"] = $store;

            // Tổng đầu kỳ - Thu
            $whereFirstThu = $baseWhereForSum;
            $whereFirstThu['AND']['accountant.type'] = 1;
            $whereFirstThu['AND']['accountant.date[<]'] = $date_from;
            $sumtotalfirstthu = $app->sum("accountant_invoices_products", $joinForSum, "total", $whereFirstThu) ?? 0;

            // Tổng đầu kỳ - Chi
            $whereFirstChi = $baseWhereForSum;
            $whereFirstChi['AND']['accountant.type'] = 2;
            $whereFirstChi['AND']['accountant.date[<]'] = $date_from;
            $sumtotalfirstchi = $app->sum("accountant_invoices_products", $joinForSum, "total", $whereFirstChi) ?? 0;

            // Tổng cuối kỳ - Thu
            $whereLastThu = $baseWhereForSum;
            $whereLastThu['AND']['accountant.type'] = 1;
            $whereLastThu['AND']['accountant.date[<=]'] = $date_to;
            $sumtotallastthu = $app->sum("accountant_invoices_products", $joinForSum, "total", $whereLastThu) ?? 0;

            // Tổng cuối kỳ - Chi
            $whereLastChi = $baseWhereForSum;
            $whereLastChi['AND']['accountant.type'] = 2;
            $whereLastChi['AND']['accountant.date[<=]'] = $date_to;
            $sumtotallastchi = $app->sum("accountant_invoices_products", $joinForSum, "total", $whereLastChi) ?? 0;


            // Tính tổng đầu kỳ
            $total_first = (float)$sumtotalfirstthu - (float)$sumtotalfirstchi;

            // Lấy dữ liệu giao dịch
            $datas = [];
            $total_thu = 0;
            $total_chi = 0;
            $key = 0;
            $app->select("accountant", "*", $where, function ($data) use (&$datas, &$key, &$total_thu, &$total_chi, &$total_first, $app, $jatbi, $setting) {
                $thu = 0;
                $chi = 0;

                $total = $app->sum("accountant_invoices_products", "total", ["invoices" => $data["invoices"], "deleted" => 0]);
                if ($data['type'] == 1) {
                    $thu = (float)$total;
                    $total_thu += $thu;
                } elseif ($data['type'] == 2) {
                    $chi = (float)$total;
                    $total_chi += $chi;
                }

                $doi_tuong_html = '';
                // if ($data['orders'] != 0) {
                //     $order = $app->get("orders", ["id", "code"], ["id" => $data['orders'], "deleted" => 0]);
                //     $doi_tuong_html .= '<p class="mb-0"><a href="#!" class="modal-url" data-url="/invoices/orders-views/' . $order['id'] . '">#' . $setting['ballot_code']['orders'] . '-' . $order['code'] . $order['id'] . '</a></p>';
                // }
                if ($data['invoices'] != 0) {
                    $invoice = $app->get("accountant_invoices", ["id", "code", "invoice"], ["invoice" => $data['invoices'], "deleted" => 0]);
                    $doi_tuong_html .= '<p class="mb-0"><span class="me-1">#' . $setting['ballot_code']['invoices'] . '-' . $invoice['code'] . $invoice['invoice'] . '</span> <a class="pjax-load" href="/accountants/update-acountant-invoice/' . $invoice['id'] . '"><i class="ti ti-edit" aria-hidden="true"></i></a></p>';
                }
                if ($data['customers'] != 0) {
                    $customer = $app->get("customers", ["id", "name"], ["id" => $data['customers'], "deleted" => 0]);
                    $doi_tuong_html .= '<p class="mb-0">' . $customer['name'] . '</p>';
                }
                if ($data['personnels'] != 0) {
                    $personnel = $app->get("personnels", ["id", "name"], ["id" => $data['personnels'], "deleted" => 0]);
                    $doi_tuong_html .= '<p class="mb-0">' . $personnel['name'] . '</p>';
                }
                if ($data['purchase'] != 0) {
                    $purchase = $app->get("purchase", ["id", "code"], ["id" => $data['purchase'], "deleted" => 0]);
                    $doi_tuong_html .= '<p class="mb-0"><a href="#!" class="modal-url" data-url="/purchases/purchase-views/' . $purchase['id'] . '">#' . $setting['ballot_code']['purchase'] . '-' . $purchase['code'] . '</a></p>';
                }
                if ($data['vendor'] != 0) {
                    $vendor = $app->get("vendors", ["id", "name"], ["id" => $data['vendor'], "deleted" => 0]);
                    $doi_tuong_html .= '<p class="mb-0">' . $vendor['name'] . '</p>';
                }

                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "ngay" => '<a href="#!" class="modal-url" data-url="/accountants/accountant-views/' . $data['id'] . '">' . date($setting['site_date'] ?? "d/m/Y", strtotime($data['date'])) . '</a>',
                    "thu" => $data['type'] == 1 ? ($data['ballot'] ?? '') : '',
                    "chi" => $data['type'] == 2 ? ($data['ballot'] ?? '') : '',
                    "dien-giai" => $data['content'] ?? '',
                    "doi-tuong" => $doi_tuong_html,
                    "tai-khoan-no" => $app->get("accountants_code", "code", ["code" => $data['debt']]) ?? '',
                    "tai-khoan-co" => $app->get("accountants_code", "code", ["code" => $data['has']]) ?? '',
                    "thuu" => number_format($thu),
                    "chii" => number_format($chi),
                    "ton" => number_format($thu + $chi),
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['accountant.edit'],
                                'action' => ['data-url' => '/accountants/accountant-type-edit/' . $data['id'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Gửi Viettel"),
                                'permission' => ['accountant.edit'],
                                'action' => ['data-url' => '/accountants/accountant-type-api/' . $data['id'], 'data-action' => 'modal']
                            ],
                        ]
                    ]),
                ];
                $key++;
            });

            // Tính toán tổng
            $totals = [
                "dau-ky-thu" => number_format((float)$sumtotalfirstthu),
                "dau-ky-chi" => number_format((float)$sumtotalfirstchi),
                "dau-ky-ton" => number_format((float)$sumtotalfirstthu - (float)$sumtotalfirstchi),
                "tong-cong-thu" => number_format((float)$total_thu),
                "tong-cong-chi" => number_format((float)$total_chi),
                "tong-cong-ton" => number_format((float)$total_first + (float)$total_thu - (float)$total_chi),
                "cuoi-ky-thu" => number_format((float)$sumtotallastthu),
                "cuoi-ky-chi" => number_format((float)$sumtotallastchi),
                "cuoi-ky-ton" => number_format((float)$sumtotallastthu - (float)$sumtotallastchi),
            ];

            // Trả về JSON cho DataTables
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas ?? [],
                "footerData" => $totals,
            ]);
        }
    })->setPermissions(['accountant']);

    $app->router("/aggregate_cost", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $accStore, $stores) {
        $vars['title'] = $jatbi->lang("Tổng hợp chi phí");

        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/accountants/aggregate_cost.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            $date_string = isset($_POST['date']) ? $app->xss($_POST['date']) : '';

            // Xử lý khoảng thời gian
            if ($date_string) {
                $date = explode('-', $date_string);
                $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date[0]))));
                $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date[1]))));
            } else {
                $date_from = date('Y-01-01 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }

            $store = $accStore;

            $kt6421 = $app->sum("expenditure", "price", [
                "debt" => 6421,
                "deleted" => 0,
                "type" => [1, 2],
                "date[<>]" => [$date_from, $date_to],
                'stores' => $store,
            ]);
            $ks6421 = $app->sum("expenditure", "price", [
                "debt" => 6421,
                "deleted" => 0,
                "type" => [1, 2],
                "date[<]" => $date_from,
                'stores' => $store,
            ]);
            $ktt6421 = $app->sum("expenditure", "price", [
                "debt" => 6421,
                "deleted" => 0,
                "type" => 3,
                "date[<>]" => [$date_from, $date_to],
                'stores' => $store,
            ]);
            $kss6421 = $app->sum("expenditure", "price", [
                "debt" => 6421,
                "deleted" => 0,
                "type" => 3,
                "date[<]" => $date_from,
                'stores' => $store,
            ]);

            $k6421 = (float) $kt6421 - (float) $ktt6421;
            $k6422 = (float) $ks6421 - (float) $kss6421;
            $lk6421 = (float) $k6421 - (float) $k6422;

            $kt6422 = $app->sum("expenditure", "price", [
                "debt" => 6422,
                "deleted" => 0,
                "type" => [1, 2],
                "date[<>]" => [$date_from, $date_to],
                'stores' => $store,
            ]);
            $ks6422 = $app->sum("expenditure", "price", [
                "debt" => 6422,
                "deleted" => 0,
                "type" => [1, 2],
                "date[<]" => $date_from,
                'stores' => $store,
            ]);
            $ktt6422 = $app->sum("expenditure", "price", [
                "debt" => 6422,
                "deleted" => 0,
                "type" => 3,
                "date[<>]" => [$date_from, $date_to],
                'stores' => $store,
            ]);
            $kss6422 = $app->sum("expenditure", "price", [
                "debt" => 6422,
                "deleted" => 0,
                "type" => 3,
                "date[<]" => $date_from,
                'stores' => $store,
            ]);
            $qlt6422 = (float) $kt6422 - (float) $ktt6422;
            $qls6422 = (float) $ks6422 - (float) $kss6422;
            $lk6422 = (float) $qlt6422 - (float) $qls6422;

            $kt154 = $app->sum("expenditure", "price", [
                "debt" => '154',
                "deleted" => 0,
                //"type"=>2,
                "date[<>]" => [$date_from, $date_to],
                'stores' => $store,
            ]);
            $ks154 = $app->sum("expenditure", "price", [
                "debt" => '154',
                "deleted" => 0,
                //"type"=>2,
                "date[<]" => $date_from,
                'stores' => $store,
            ]);
            $lk154 = (float) $kt154 - (float) $ks154;


            $datas = [
                [
                    "tk" => "6421",
                    "loai_chi_phi" => $jatbi->lang('Chi phí bán hàng'),
                    "ky_nay"   => str_replace('-', '', number_format($k6421)),
                    "ky_truoc" => str_replace('-', '', number_format($k6422)),
                    "luy_ke"   => str_replace('-', '', number_format($lk6421)),
                ],
                [
                    "tk" => "6422",
                    "loai_chi_phi" => $jatbi->lang('Chi phí quản lý'),
                    "ky_nay"   => str_replace('-', '', number_format($qlt6422)),
                    "ky_truoc" => str_replace('-', '', number_format($qls6422)),
                    "luy_ke"   => str_replace('-', '', number_format($lk6422)),
                ],
                [
                    "tk" => "154",
                    "loai_chi_phi" => $jatbi->lang('Chi phí sản xuất kinh doanh'),
                    "ky_nay"   => str_replace('-', '', number_format((float) $kt154)),
                    "ky_truoc" => str_replace('-', '', number_format((float) $ks154)),
                    "luy_ke"   => str_replace('-', '', number_format((float) $lk154)),
                ],
            ];

            echo json_encode([
                "draw" => intval($_POST['draw'] ?? 1),
                "data" => $datas,
            ]);
        }
    })->setPermissions(['aggregate_cost']);



    $app->router("/financial_paper", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores, $accStore) {
        $vars['title'] = $jatbi->lang("Chứng từ kế toán");
        if ($app->method() === 'GET') {
            // Thêm dữ liệu cho các bộ lọc
            $accountants = $app->select("accountants_code", ["id ", "name", "code"], ["deleted" => 0, "status" => 'A']);
            $accountants_formatted = array_map(function ($item) {
                return [
                    'value' => $item['code'],
                    'text' => $item['code'] . ' - ' . $item['name']
                ];
            }, $accountants);
            array_unshift($accountants_formatted, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            if(count($stores) > 1){
                array_unshift($stores, [
                    'value' => '',
                    'text' => $jatbi->lang('Tất cả')
                ]);
            }
            $vars['stores'] = $stores;
            $accounts = $app->select("accounts", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A']);
            array_unshift($accounts, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars['accounts'] = $accounts;
            $accountants = $app->select("accountants_code", ["id ", "name", "code"], ["deleted" => 0, "status" => 'A']);
            $accountants_formatted = array_map(function($item) {
                return [
                    'value' => $item['code'],
                    'text' => $item['code'] . ' - ' . $item['name']
                ];
            }, $accountants);
            array_unshift($accountants_formatted, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars["accountants"] = $accountants_formatted;
            echo $app->render($setting['template'] . '/accountants/financial_paper.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);

            // --- 1. Đọc tham số ---
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : $setting['site_page'] ?? 10;
            // $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['column']) ? $_POST['columns'][$_POST['order'][0]['column']]['name'] : 'expenditure.id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

            // Lấy giá trị từ các bộ lọc
            $filter_debt = isset($_POST['debt']) ? $_POST['debt'] : '';
            $filter_has = isset($_POST['has']) ? $_POST['has'] : '';
            $filter_stores = isset($_POST['stores']) ? $app->xss($_POST['stores']) : $accStore;
            $filter_user = isset($_POST['user']) ? $_POST['user'] : '';
            $filter_date = isset($_POST['date']) ? $_POST['date'] : '';

            // --- 2. Xây dựng truy vấn với JOIN ---
            $joins = [
                // "[>]accountants_code (debt_info)" => ["debt" => "id"],
                // "[>]accountants_code (has_info)" => ["has" => "id"],
                "[>]stores" => ["stores" => "id"],
                "[>]accounts" => ["user" => "id"],
            ];

            // --- 3. Xây dựng điều kiện lọc ---
            $where = [
                "AND" => [
                    "expenditure.deleted" => 0,
                    'expenditure.type' => 3,
                ],
            ];

            // if (!empty($searchValue)) {
            //     $where["AND"]["OR"] = [
            //         "expenditure.content[~]" => $searchValue,
            //         "debt_info.name[~]" => $searchValue,
            //         "debt_info.code[~]" => $searchValue,
            //         "has_info.name[~]" => $searchValue,
            //         "has_info.code[~]" => $searchValue,
            //         "stores.name[~]" => $searchValue,
            //         "accounts.name[~]" => $searchValue,
            //     ];
            // }

            // if (!empty($filter_debt)) {
            //     $where["AND"]["expenditure.debt"] = $filter_debt;
            // }
            // if (!empty($filter_has)) {
            //     $where["AND"]["expenditure.has"] = $filter_has;
            // }
            if ($filter_stores != [0]) {
                $where['AND']['expenditure.stores'] = $filter_stores;
            }
            // if (!empty($filter_user)) {
            //     $where["AND"]["expenditure.user"] = $filter_user;
            // }

            if (!empty($filter_date)) {
                $date_parts = explode(' - ', $filter_date);
                if (count($date_parts) == 2) {
                    $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date_parts[0]))));
                    $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date_parts[1]))));
                }
            } else {
                $date_from = date('Y-m-01 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }
            $where['AND']['expenditure.date[<>]'] = [$date_from, $date_to];

            // --- 4. Đếm bản ghi và tính tổng ---
            $count = $app->count("expenditure", [
                "AND" => $where['AND'],
            ]);
            $where_for_totals = $where;
            unset($where_for_totals['LIMIT'], $where_for_totals['ORDER']);
            $total_sum_filtered = $app->sum("expenditure", $joins, "expenditure.price", $where_for_totals);

            // Thêm sắp xếp và phân trang
            $where["ORDER"] = [$orderName => strtoupper($orderDir)];
            $where["LIMIT"] = [$start, $length];

            // --- 5. Lấy dữ liệu ---
            $datas = [];
            $columns = [
                'expenditure.id',
                'expenditure.date',
                'expenditure.price',
                'expenditure.content',
                'expenditure.debt',
                'expenditure.has',
                // 'debt_info.code(debt_code)',
                // 'debt_info.name(debt_name)',
                // 'has_info.code(has_code)',
                // 'has_info.name(has_name)',
                'stores.name(store_name)',
                'accounts.name(user_name)'
            ];

            $app->select("expenditure", $joins, $columns, $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "date" => date('d/m/Y', strtotime($data['date'])),
                    "content" => $data['content'],
                    "debt" => $app->get("accountants_code","code",["code"=>$data['debt']]),
                    "has" => $app->get("accountants_code","code",["code"=>$data['has']]),
                    "price" => number_format($data['price'], 0),
                    "stores" => $data['store_name'],
                    "user" => $data['user_name'],
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['financial_paper.edit'],
                                'action' => ['data-url' => '/accountants/financial_paper-edit/' . $data['id'], 'data-action' => 'modal']
                            ],
                        ]
                    ]),
                ];
            });

            // --- 6. Trả về dữ liệu JSON ---
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas ?? [],
                "footerData" => [
                    "sum_total" => number_format((float) ($total_sum_filtered ?? 0), 0)
                ]
            ]);
        }
    })->setPermissions(['financial_paper']);

    // Route: Thêm chứng từ kế toán
    $app->router("/financial_paper-add", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores, $accStore) {
        $vars['title'] = $jatbi->lang("Thêm chứng từ kế toán");

        if ($app->method() === 'GET') {
            // Lấy danh sách tài khoản kế toán
            $accountants = $app->select("accountants_code", ["id ", "name", "code"], ["deleted" => 0, "status" => 'A']);
            $accountants_formatted = array_map(function($item) {
                return [
                    'value' => $item['code'],
                    'text' => $item['code'] . ' - ' . $item['name']
                ];
            }, $accountants);
            array_unshift($accountants_formatted, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars["accountants"] = $accountants_formatted;

            // Lấy danh sách cửa hàng
            if (count($stores) > 1) {
                array_unshift($stores, [
                    'value' => '',
                    'text' => $jatbi->lang('Tất cả')
                ]);
                $vars['stores'] = $stores;
            } else {
                $vars['stores'] = $app->select("stores", ["id (value)", "name (text)"], ["id" => $accStore, "status" => 'A', "deleted" => 0]);
            }

            // Lấy danh sách nhân viên
            $personnels = $app->select("personnels", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A']);
            $vars["personnels"] = $personnels;

            // Lấy danh sách nhà cung cấp
            $vendors = $app->select("vendors", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A']);
            $vars["vendors"] = $vendors;

            // Danh sách loại chi tiêu
            $expenditure_types = $setting['expenditure_type'];
            $expenditure_types_formatted = array_map(function ($item) use ($jatbi) {
                return [
                    'value' => $item['id'],
                    'text' => $jatbi->lang($item['name'])
                ];
            }, $expenditure_types);
            $vars["expenditure_types"] = $expenditure_types_formatted;

            echo $app->render($setting['template'] . '/accountants/financial_paper-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $error = [];

            if (
                empty($app->xss($_POST['type'])) || empty($app->xss($_POST['debt'])) || empty($app->xss($_POST['has'])) ||
                empty($app->xss($_POST['price'])) || empty($app->xss($_POST['content'])) || empty($app->xss($_POST['date']))
            ) {
                $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")];
            }
            // Kiểm tra cửa hàng
            elseif (count($stores) > 1 && empty($app->xss($_POST['stores']))) {
                $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng chọn cửa hàng")];
            }

            if (empty($error)) {
                $input_stores = count($stores) > 1 ? $app->xss($_POST['stores']) : $app->get("stores", "id", ["id" => $accStore, "status" => 'A', "deleted" => 0]);

                $insert = [
                    "type" => $app->xss($_POST['type']),
                    "debt" => $app->xss($_POST['debt']),
                    "has" => $app->xss($_POST['has']),
                    "price" => str_replace(',', '', $app->xss($_POST['price'])),
                    "content" => $app->xss($_POST['content']),
                    "date" => $app->xss($_POST['date']),
                    "notes" => $app->xss($_POST['notes']),
                    "user" => $app->getSession("accounts")['id'],
                    "date_poster" => date("Y-m-d H:i:s"),
                    "stores" => $input_stores,
                    "ballot" => $app->xss($_POST['ballot'] ?? ''),
                    "customers" => $app->xss($_POST['customers'] ?? ''),
                    "projects" => $app->xss($_POST['projects'] ?? ''),
                    "invoices" => $app->xss($_POST['invoices'] ?? ''),
                    "personnels" => $app->xss($_POST['personnels'] ?? ''),
                    "vendor" => $app->xss($_POST['vendors'] ?? ''),
                    "purchase" => $app->xss($_POST['purchase'] ?? ''),
                ];

                $app->insert("expenditure", $insert);
                $jatbi->logs('financial_paper', 'add', $insert);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công"), 'url' => $_SERVER['HTTP_REFERER']]);
            } else {
                echo json_encode($error);
            }
        }
    })->setPermissions(['financial_paper.add']);

    // Route: Kết chuyển chứng từ (financial_paper-update)
    $app->router("/financial_paper-update", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores, $accStore) {
        $vars['title'] = $jatbi->lang("Kết chuyển 642, 635, 821");

        if ($app->method() === 'GET') {
            // Lấy danh sách tài khoản kế toán
            $accountants = $app->select("accountants_code", ["id ", "name", "code"], ["deleted" => 0, "status" => 'A']);
            $accountants_formatted = array_map(function($item) {
                return [
                    'value' => $item['code'],
                    'text' => $item['code'] . ' - ' . $item['name']
                ];
            }, $accountants);
            array_unshift($accountants_formatted, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars["accountants"] = $accountants_formatted;

            // Lấy danh sách cửa hàng
            if (count($stores) > 1) {
                array_unshift($stores, [
                    'value' => '',
                    'text' => $jatbi->lang('Tất cả')
                ]);
                $vars['stores'] = $stores;
            } else {
                $vars['stores'] = $app->select("stores", ["id (value)", "name (text)"], ["id" => $accStore, "status" => 'A', "deleted" => 0]);
            }

            echo $app->render($setting['template'] . '/accountants/financial_paper-update.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $error = [];

            if (
                empty($app->xss($_POST['debt'])) || empty($app->xss($_POST['has'])) || empty($app->xss($_POST['content'])) ||
                empty($app->xss($_POST['date_from'])) || empty($app->xss($_POST['date_to']))
            ) {
                $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")];
            }
            // Kiểm tra cửa hàng
            elseif (count($stores) > 1 && empty($app->xss($_POST['stores']))) {
                $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng chọn cửa hàng")];
            }

            if (empty($error)) {
                $input_stores = count($stores) > 1 ? $app->xss($_POST['stores']) : $app->get("stores", "id", ["id" => $accStore, "status" => 'A', "deleted" => 0]);

                // Tính tổng giá trị
                $i24 = $app->sum("expenditure", "price", [
                    "type" => [1, 2],
                    "debt" => $app->xss($_POST['has']),
                    "deleted" => 0,
                    "date[<>]" => [$app->xss($_POST['date_from']), $app->xss($_POST['date_to'])],
                    "stores" => $input_stores,
                ]);
                $ii24 = $app->sum("expenditure", "price", [
                    "type" => 3,
                    "debt" => $app->xss($_POST['has']),
                    "deleted" => 0,
                    "date[<>]" => [$app->xss($_POST['date_from']), $app->xss($_POST['date_to'])],
                    "stores" => $input_stores,
                ]);
                $price = abs($i24 - $ii24);

                $insert = [
                    "type" => 3,
                    "debt" => $app->xss($_POST['debt']),
                    "has" => $app->xss($_POST['has']),
                    "price" => $price,
                    "content" => $app->xss($_POST['content']),
                    "date" => date("Y-m-d"),
                    "notes" => $app->xss($_POST['notes']),
                    "user" => $app->getSession("accounts")['id'],
                    "date_poster" => date("Y-m-d H:i:s"),
                    "stores" => $input_stores,
                    "move" => 1,
                ];

                $app->insert("expenditure", $insert);
                $jatbi->logs('financial_paper', 'add', $insert);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công"), 'url' => $_SERVER['HTTP_REFERER']]);
            } else {
                echo json_encode($error);
            }
        }
    })->setPermissions(['financial_paper']);

    // Route: Sửa chứng từ kế toán
    $app->router("/financial_paper-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores, $accStore) {
        $vars['title'] = $jatbi->lang("Chỉnh sửa chứng từ kế toán");

        if ($app->method() === 'GET') {
            $vars['data'] = $app->get("expenditure", "*", ["id" => $vars['id'], "deleted" => 0]);
            if ($vars['data']) {
                // Lấy danh sách tài khoản kế toán
                $accountants = $app->select("accountants_code", ["id ", "name", "code"], ["deleted" => 0, "status" => 'A']);
                $accountants_formatted = array_map(function($item) {
                    return [
                        'value' => $item['code'],
                        'text' => $item['code'] . ' - ' . $item['name']
                    ];
                }, $accountants);
                array_unshift($accountants_formatted, [
                    'value' => '',
                    'text' => $jatbi->lang('Tất cả')
                ]);
                $vars["accountants"] = $accountants_formatted;

                // Lấy danh sách cửa hàng
                if (count($stores) > 1) {
                    array_unshift($stores, [
                        'value' => '',
                        'text' => $jatbi->lang('Tất cả')
                    ]);
                    $vars['stores'] = $stores;
                } else {
                    $vars['stores'] = $app->select("stores", ["id (value)", "name (text)"], ["id" => $accStore, "status" => 'A', "deleted" => 0]);
                }

                // Lấy danh sách nhân viên
                $personnels = $app->select("personnels", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A']);
                $vars["personnels"] = $personnels;

                // Lấy danh sách nhà cung cấp
                $vendors = $app->select("vendors", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A']);
                $vars["vendors"] = $vendors;

                // Danh sách loại chi tiêu
                $expenditure_types = $setting['expenditure_type'];
                $expenditure_types_formatted = array_map(function ($item) use ($jatbi) {
                    return [
                        'value' => $item['id'],
                        'text' => $jatbi->lang($item['name'])
                    ];
                }, $expenditure_types);
                $vars["expenditure_types"] = $expenditure_types_formatted;

                echo $app->render($setting['template'] . '/accountants/financial_paper-post.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $error = [];
            if (
                empty($app->xss($_POST['type'])) || empty($app->xss($_POST['debt'])) || empty($app->xss($_POST['has'])) ||
                empty($app->xss($_POST['price'])) || empty($app->xss($_POST['content'])) || empty($app->xss($_POST['date']))
            ) {
                $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")];
            }
            // Kiểm tra cửa hàng
            elseif (count($stores) > 1 && empty($app->xss($_POST['stores']))) {
                $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng chọn cửa hàng")];
            }

            if (empty($error)) {
                $input_stores = count($stores) > 1 ? $app->xss($_POST['stores']) : $app->get("stores", "id", ["id" => $accStore, "status" => 'A', "deleted" => 0]);

                $update = [
                    "type" => $app->xss($_POST['type']),
                    "debt" => $app->xss($_POST['debt']),
                    "has" => $app->xss($_POST['has']),
                    "price" => str_replace(',', '', $app->xss($_POST['price'])),
                    "content" => $app->xss($_POST['content']),
                    "date" => $app->xss($_POST['date']),
                    "notes" => $app->xss($_POST['notes']),
                    "user" => $app->getSession("accounts")['id'],
                    "date_poster" => date("Y-m-d H:i:s"),
                    "stores" => $input_stores,
                    "ballot" => $app->xss($_POST['ballot'] ?? ''),
                    "customers" => $app->xss($_POST['customers'] ?? ''),
                    "projects" => $app->xss($_POST['projects'] ?? ''),
                    "invoices" => $app->xss($_POST['invoices'] ?? ''),
                    "personnels" => $app->xss($_POST['personnels'] ?? ''),
                    "vendor" => $app->xss($_POST['vendors'] ?? ''),
                    "purchase" => $app->xss($_POST['purchase'] ?? ''),
                ];

                $app->update("expenditure", $update, ["id" => $vars['id']]);
                $jatbi->logs('financial_paper', 'edit', $update);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công"), 'url' => $_SERVER['HTTP_REFERER']]);
            } else {
                echo json_encode($error);
            }
        }
    })->setPermissions(['financial_paper.edit']);

    // Route: Xóa chứng từ kế toán
    $app->router("/financial_paper-delete", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa chứng từ kế toán");

        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("expenditure", "*", ["id" => $boxid, "deleted" => 0]);

            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("expenditure", ["deleted" => 1], ["id" => $data['id']]);
                }
                $jatbi->logs('financial_paper', 'delete', $datas);
                $jatbi->trash('/accountants/financial_paper-restore', "Xóa chứng từ kế toán: ", ["database" => 'expenditure', "data" => $boxid]);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xảy ra")]);
            }
        }
    })->setPermissions(['financial_paper.deleted']);

    // Route: Kết chuyển chứng từ (financial_paper_move)
    $app->router("/financial_paper_move/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores, $accStore) {
        $vars['title'] = $jatbi->lang("Kết chuyển chứng từ");

        if ($app->method() === 'GET') {
            $vars['data'] = $app->get("expenditure", "*", ["id" => $vars['id'], "deleted" => 0]);
            if ($vars['data']) {
                // Lấy danh sách tài khoản kế toán
                $accountants = $app->select("accountants_code", ["code (value)", "name (text)", "code"], ["deleted" => 0, "status" => 'A']);
                $vars["accountants"] = $accountants;

                // Lấy danh sách cửa hàng
                if (count($stores) > 1) {
                    array_unshift($stores, [
                        'value' => '',
                        'text' => $jatbi->lang('Tất cả')
                    ]);
                    $vars['stores'] = $stores;
                } else {
                    $vars['stores'] = $app->select("stores", ["id (value)", "name (text)"], ["id" => $accStore, "status" => 'A', "deleted" => 0]);
                }

                // Lấy danh sách nhân viên
                $personnels = $app->select("personnels", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A']);
                $vars["personnels"] = $personnels;

                // Lấy danh sách nhà cung cấp
                $vendors = $app->select("vendors", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A']);
                $vars["vendors"] = $vendors;

                // Danh sách loại chi tiêu
                $expenditure_types = $setting['expenditure_type'];
                $expenditure_types_formatted = array_map(function ($item) use ($jatbi) {
                    return [
                        'value' => $item['id'],
                        'text' => $jatbi->lang($item['name'])
                    ];
                }, $expenditure_types);
                $vars["expenditure_types"] = $expenditure_types_formatted;

                echo $app->render($setting['template'] . '/accountants/expenditure-post.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $error = [];

            // Kiểm tra token CSRF
            if (!isset($_POST['token']) || $_POST['token'] !== $_SESSION['csrf']['token']) {
                $error = ["status" => "error", "content" => $jatbi->lang("Token không đúng")];
            }
            // Kiểm tra các trường bắt buộc
            elseif (
                empty($app->xss($_POST['type'])) || empty($app->xss($_POST['debt'])) || empty($app->xss($_POST['has'])) ||
                empty($app->xss($_POST['price'])) || empty($app->xss($_POST['content'])) || empty($app->xss($_POST['date']))
            ) {
                $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")];
            }
            // Kiểm tra cửa hàng
            elseif (count($stores) > 1 && empty($app->xss($_POST['stores']))) {
                $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng chọn cửa hàng")];
            }

            if (empty($error)) {
                $input_stores = count($stores) > 1 ? $app->xss($_POST['stores']) : $app->get("stores", "id", ["id" => $accStore, "status" => 'A', "deleted" => 0]);

                $insert = [
                    "type" => $app->xss($_POST['type']),
                    "debt" => $app->xss($_POST['debt']),
                    "has" => $app->xss($_POST['has']),
                    "price" => str_replace(',', '', $app->xss($_POST['price'])),
                    "content" => $app->xss($_POST['content']),
                    "date" => $app->xss($_POST['date']),
                    "notes" => $app->xss($_POST['notes']),
                    "user" => $app->getSession("accounts")['id'],
                    "date_poster" => date("Y-m-d H:i:s"),
                    "stores" => $input_stores,
                    "move" => 1,
                    "ballot" => $app->xss($_POST['ballot'] ?? ''),
                    "customers" => $app->xss($_POST['customers'] ?? ''),
                    "projects" => $app->xss($_POST['projects'] ?? ''),
                    "invoices" => $app->xss($_POST['invoices'] ?? ''),
                    "personnels" => $app->xss($_POST['personnels'] ?? ''),
                    "vendor" => $app->xss($_POST['vendors'] ?? ''),
                    "purchase" => $app->xss($_POST['purchase'] ?? ''),
                ];

                $app->insert("expenditure", $insert);
                $app->update("expenditure", ["move" => 1], ["id" => $vars['id']]);
                $jatbi->logs('financial_paper', 'forward', $insert);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công"), 'url' => $_SERVER['HTTP_REFERER']]);
            } else {
                echo json_encode($error);
            }
        }
    })->setPermissions(['financial_paper_move']);

    // $app->router("/financial_paper_move/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores, $accStore) {
    //     $vars['title'] = $jatbi->lang("Kết chuyển chứng từ");

    //     if ($app->method() === 'GET') {
    //         echo $app->render($setting['template'] . '/accountants/expenditure-post.html', $vars, $jatbi->ajax());
    //     } elseif ($app->method() === 'POST') {
    //         $app->header(['Content-Type' => 'application/json']);
    //         echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công"), 'url' => $_SERVER['HTTP_REFERER']]);
    //     }
    // })->setPermissions(['financial_paper_move']);

})->middleware('login');
