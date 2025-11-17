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
        $accStore[0] = "0"; // chỉ thêm 1 lần
    }

    foreach ($stores as $itemStore) {
        $accStore[$itemStore['value']] = $itemStore['value'];
    }
}
$app->group($setting['manager'] . "/purchases", function ($app) use ($jatbi, $setting, $accStore, $stores, $template) {

    $app->router('/vendors', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {
        $vars['title'] = $jatbi->lang("Nhà cung cấp");

        if ($app->method() === 'GET') {
            $types_db = $app->select("vendors_types", ['id(value)', 'name(text)'], ['deleted' => 0]);

            // Thêm lựa chọn "Tất cả" vào đầu mảng
            $vars['vendorstypesList'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $types_db);
            echo $app->render($template . '/purchases/vendors.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $statusValue = isset($_POST['status']) ? $_POST['status'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $vendorstypesFilter = isset($_POST['vendors_types']) ? $_POST['vendors_types'] : '';

            $where = [
                "AND" => [
                    "OR" => [
                        'vendors.name[~]' => $searchValue,
                        'vendors.phone[~]' => $searchValue,
                        'vendors.email[~]' => $searchValue,
                        'vendors.user[~]' => $searchValue,
                    ],
                    "vendors.status[<>]" => $statusValue,
                    "vendors.deleted" => 0,
                ],

                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)]
            ];


            // Apply status filter
            if ($statusValue != '') {
                $where['AND']['vendors.status'] = $statusValue;
            }


            if ($vendorstypesFilter != '' && $vendorstypesFilter != 'all' && is_numeric($vendorstypesFilter) && $vendorstypesFilter > 0) {
                $where['AND']['vendors.type'] = $vendorstypesFilter;
            }


            $count = $app->count("vendors", [
                "AND" => $where['AND'],
            ]);

            $datas = [];
            $app->select("vendors", [
                '[>]vendors_types' => ['type' => 'id'],
                '[>]accounts' => ['user' => 'id'],
            ], [
                'vendors.id',
                'vendors_types.name(type_name)',
                'accounts.name(user)',
                'vendors.name',
                'vendors.phone',
                'vendors.email',
                'vendors.date',
                'vendors.status'
            ], $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id'] ?? '']),
                    "type" => $data['type_name'] ?? 'Không xác định',
                    "name" => $data['name'] ?? '',
                    "phone" => $data['phone'] ?? '',
                    "email" => $data['email'] ?? '',
                    "user" => $data['user'] ?? '',
                    "date" => $jatbi->datetime($data['date'] ?? ''),
                    "status" => $app->component("status", ["url" => "/purchases/vendors-status/" . $data['id'], "data" => $data['status'], "permission" => ['vendors.edit']]),
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['vendors.edit'],
                                'action' => ['data-url' => '/purchases/vendors-edit/' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                        ]
                    ])
                ];
            });

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas
            ]);
        }
    })->setPermissions(['vendors']);



    // --- ROUTER THÊM NHÀ CUNG CẤP ---
    $app->router('/vendors-add', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {
        $vars['title'] = $jatbi->lang("Thêm Nhà cung cấp");

        if ($app->method() === 'GET') {
            $vars['data'] = ['status' => 'A'];
            $vars['vendorstypesList'] = $app->select("vendors_types", ['id (value)', 'name (text)'], ['status' => 'A', 'deleted' => 0]);
            echo $app->render($template . '/purchases/vendors-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);
            $error = [];
            if (empty(trim($_POST['name'])))
                $error = ["status" => "error", "content" => $jatbi->lang("Tên nhà cung cấp không được trống")];
            if (empty($_POST['type']))
                $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng chọn loại nhà cung cấp")];

            if (empty($error)) {
                $insert = [
                    "type" => $app->xss($_POST['type']),
                    "name" => $app->xss($_POST['name']),
                    "phone" => $app->xss($_POST['phone']),
                    "email" => $app->xss($_POST['email']),
                    "taxcode" => $app->xss($_POST['taxcode']),
                    "fax" => $app->xss($_POST['fax']),
                    "website" => $app->xss($_POST['website']),
                    "address" => $app->xss($_POST['address']),
                    "province" => (int) $app->xss($_POST['province'] ?? ''),
                    "district" => (int) $app->xss($_POST['district'] ?? ''),
                    "ward" => (int) $app->xss($_POST['ward'] ?? ''),
                    "notes" => $app->xss($_POST['notes']),
                    "status" => $app->xss($_POST['status']),
                    "date" => date('Y-m-d H:i:s'),
                    "user" => $app->getSession("accounts")['id'] ?? 0,
                    "follow" => $app->getSession("accounts")['id'] ?? 0,
                    "deleted" => 0,
                ];


                // Thực hiện lệnh insert
                $app->insert("vendors", $insert);
                $jatbi->logs('vendors', 'add', $insert);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Thêm mới thành công")]);
            } else {
                echo json_encode($error);
            }
        }
    })->setPermissions(['vendors.add']);

    // --- ROUTER SỬA NHÀ CUNG CẤP ---
    $app->router('/vendors-edit/{id}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {
        $id = $vars['id'] ?? 0;
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Sửa Nhà cung cấp");

            $data = $app->get("vendors", "*", ["id" => $id, "deleted" => 0]);

            if (!$data) {
                return $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
            }

            $vars['data'] = $data;

            $vars['province'] = $app->select("province", ["id(value)", "name(text)"], ["deleted" => 0, "ORDER" => ["name" => "ASC"]]);

            $vars['district'] = $app->select("district", ["id(value)", "name(text)"], ["deleted" => 0, "province" => $data['province']]);

            $vars['ward'] = $app->select("ward", ["id(value)", "name(text)"], ["deleted" => 0, "district" => $data['district']]);

            $vars['vendorstypesList'] = $app->select("vendors_types", ['id(value)', 'name(text)'], ['status' => 'A', 'deleted' => 0]);

            echo $app->render($template . '/purchases/vendors-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);
            $data = $app->get("vendors", "id", ["id" => $id, "deleted" => 0]);
            if ($data) {
                $error = [];
                if (empty(trim($_POST['name'])))
                    $error = ["status" => "error", "content" => $jatbi->lang("Tên nhà cung cấp không được trống")];
                if (empty($_POST['type']))
                    $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng chọn loại nhà cung cấp")];

                if (empty($error)) {
                    $update = [
                        "type" => $app->xss($_POST['type']),
                        "name" => $app->xss($_POST['name']),
                        "phone" => $app->xss($_POST['phone']),
                        "email" => $app->xss($_POST['email']),
                        "taxcode" => $app->xss($_POST['taxcode']),
                        "fax" => $app->xss($_POST['fax']),
                        "website" => $app->xss($_POST['website']),
                        "address" => $app->xss($_POST['address']),
                        "province" => (int) $app->xss($_POST['province']),
                        "district" => (int) $app->xss($_POST['district']),
                        "ward" => (int) $app->xss($_POST['ward']),
                        "notes" => $app->xss($_POST['notes']),
                        "status" => $app->xss($_POST['status']),
                    ];

                    $app->update("vendors", $update, ["id" => $id]);
                    $jatbi->logs('vendors', 'edit', $update);

                    echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
                } else {
                    echo json_encode($error);
                }
            } else {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
            }
        }
    })->setPermissions(['vendors.edit']);

    $app->router("/vendors-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa Nhà cung cấp");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("vendors", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("vendors", ["deleted" => 1], ["id" => $data['id']]);
                    $name[] = $data['name'];
                }
                $jatbi->logs('vendors', 'vendors-deleted', $datas);
                $jatbi->trash('/vendors/vendors-restore', "Xóa nhà cung cấp: " . implode(', ', $name), ["database" => 'customers', "data" => $boxid]);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['vendors.deleted']);


    $app->router('/vendors-status/{id}', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $id = $vars['id'] ?? 0;
        $data = $app->get("vendors", ["id", "status"], ["id" => $id, "deleted" => 0]);

        if ($data) {
            $new_status = ($data['status'] == 'A') ? 'D' : 'A';
            $update = ["status" => $new_status];
            $app->update("vendors", $update, ["id" => $id]);
            $jatbi->logs('vendors', 'status_change', $update);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật trạng thái thành công"), 'new_status' => $new_status]);
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['vendors.edit']);



    //vendors-types

    $app->router('/vendors-types', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {
        $vars['title'] = $jatbi->lang("Loại nhà cung cấp");

        if ($app->method() === 'GET') {
            echo $app->render($template . '/purchases/vendors-types.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            // --- 1. Đọc tham số ---
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $statusValue = isset($_POST['status']) ? $_POST['status'] : '';
            $orderName = isset($_POST['order'][0]['column']) ? $_POST['columns'][$_POST['order'][0]['column']]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

            // --- 2. Xây dựng điều kiện lọc ---
            $where = [
                "AND" => [
                    "deleted" => 0,
                ],
            ];

            if ($searchValue != '') {
                $where['AND']['OR'] = [
                    'name[~]' => $searchValue,
                    'notes[~]' => $searchValue,
                ];
            }

            if ($statusValue != '') {
                $where['AND']['status'] = $statusValue;
            }

            // --- 3. Đếm bản ghi ---

            $count = $app->count("vendors_types", [
                "AND" => $where['AND'],
            ]);

            // Thêm sắp xếp và phân trang
            $where["ORDER"] = [$orderName => strtoupper($orderDir)];
            $where["LIMIT"] = [$start, $length];

            // --- 4. Lấy dữ liệu ---
            $datas = [];
            $app->select("vendors_types", [
                'id',
                'name',
                'notes',
                'status'
            ], $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => ($app->component("box", ["data" => $data['id'] ?? '']) ?? '<input type="checkbox">'),
                    "name" => ($data['name'] ?? ''),
                    "notes" => ($data['notes'] ?? ''),
                    "status" => ($app->component("status", [
                        "url" => "/purchases/vendors-types-status/" . ($data['id'] ?? ''),
                        "data" => $data['status'] ?? '',
                        "permission" => ['vendors-types.edit']
                    ]) ?? '<span>' . ($data['status'] ?? '') . '</span>'),
                    "action" => ($app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['vendors-types.edit'],
                                'action' => ['data-url' => '/purchases/vendors-types-edit/' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                        ]
                    ]))
                ];
            });

            // --- 5. Trả về JSON ---
            echo json_encode(
                [
                    "draw" => $draw,
                    "recordsTotal" => $count,
                    "recordsFiltered" => $count,
                    "data" => $datas,
                ],
            );
        }
    })->setPermissions(['vendors-types']);

    $app->router('/vendors-types-edit/{id}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {
        // Đặt tiêu đề cho trang
        $vars['title'] = $jatbi->lang("Sửa Loại nhà cung cấp");

        // Xử lý yêu cầu GET (Hiển thị form)
        if ($app->method() === 'GET') {
            // Lấy dữ liệu của mục cần sửa
            $vars['data'] = $app->get("vendors_types", "*", ["id" => $vars['id'], "deleted" => 0]);

            if ($vars['data']) {
                // Nếu tìm thấy, hiển thị form chỉnh sửa (bạn cần tạo file này)
                echo $app->render($template . '/customers/sources-post.html', $vars, $jatbi->ajax());
            } else {
                // Nếu không tìm thấy, hiển thị trang lỗi
                echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
            }
        }
        // Xử lý yêu cầu POST (Cập nhật dữ liệu)
        elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json; charset=utf-8',
            ]);

            // Lấy lại dữ liệu để đảm bảo mục vẫn tồn tại trước khi cập nhật
            $data = $app->get("vendors_types", "*", ["id" => $vars['id'], "deleted" => 0]);

            if ($data) {
                $error = [];
                // Kiểm tra dữ liệu đầu vào (validation), ví dụ: tên không được để trống
                if (empty(trim($_POST['name']))) {
                    $error = ["status" => "error", "content" => $jatbi->lang("Tên loại nhà cung cấp không được để trống")];
                }

                // Nếu không có lỗi
                if (empty($error)) {
                    $update = [
                        "name" => $app->xss($_POST['name']),
                        "notes" => $app->xss($_POST['notes']),
                        "status" => $app->xss($_POST['status']),
                    ];

                    // Cập nhật vào cơ sở dữ liệu
                    $app->update("vendors_types", $update, ["id" => $data['id']]);

                    // Ghi log hành động
                    $jatbi->logs('vendors_types', 'edit', $update);

                    // Trả về thông báo thành công
                    echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
                } else {
                    // Nếu có lỗi, trả về thông báo lỗi
                    echo json_encode($error);
                }
            } else {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu hoặc đã bị xóa")]);
            }
        }
    })->setPermissions(['vendors-types.edit']);

    $app->router("/vendors-types-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa Loại nhà cung cấp");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("vendors_types", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("vendors_types", ["deleted" => 1], ["id" => $data['id']]);
                    $name[] = $data['name'];
                }
                $jatbi->logs('vendors-types', 'vendors-types-deleted', $datas);
                $jatbi->trash('/purchases/vendors-types-deleted', "Xóa loại nhà cung cấp: " . implode(', ', $name), ["database" => 'vendors_types', "data" => $boxid]);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['vendors-types.deleted']);

    $app->router('/vendors-types-add', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {
        // Đặt tiêu đề cho trang
        $vars['title'] = $jatbi->lang("Thêm Loại nhà cung cấp");

        // Xử lý yêu cầu GET (Hiển thị form thêm mới)
        if ($app->method() === 'GET') {
            // Chuẩn bị dữ liệu mặc định cho form
            $vars['data'] = [
                'name' => '',
                'notes' => '',
                'status' => 'A', // Mặc định là 'Hoạt động'
            ];

            // Render ra form thêm mới (tái sử dụng từ form edit)
            echo $app->render($template . '/customers/sources-post.html', $vars, $jatbi->ajax());
        }
        // Xử lý yêu cầu POST (Lưu dữ liệu)
        elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json; charset=utf-8',
            ]);

            $error = [];
            // Kiểm tra dữ liệu (validation), ví dụ: tên không được để trống
            if (empty(trim($_POST['name']))) {
                $error = ["status" => "error", "content" => $jatbi->lang("Tên loại nhà cung cấp không được để trống")];
            }

            // Nếu không có lỗi
            if (empty($error)) {
                $insert = [
                    "name" => $app->xss($_POST['name']),
                    "notes" => $app->xss($_POST['notes']),
                    "status" => $app->xss($_POST['status']),
                    "deleted" => 0,
                ];

                // Thêm vào cơ sở dữ liệu
                $app->insert("vendors_types", $insert);

                // Ghi log hành động
                $jatbi->logs('vendors_types', 'add', $insert);

                // Trả về thông báo thành công
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Thêm mới thành công")]);
            } else {
                // Nếu có lỗi, trả về thông báo lỗi
                echo json_encode($error);
            }
        }
    })->setPermissions(['vendors-types.add']);


    $app->router('/purchase', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $accStore, $stores, $template) {

        $vars['title'] = $jatbi->lang("Đề xuất mua hàng");

        if ($app->method() === 'GET') {

            // 1. Lấy dữ liệu động từ DB
            $vendors_db = $app->select("vendors", ['id(value)', 'name(text)'], ['deleted' => 0]);
            $accounts_db = $app->select("accounts", ['id(value)', 'name(text)'], ['deleted' => 0]);
            // $stores_db = $app->select("stores", ['id(value)', 'name(text)'], ['deleted' => 0]);

            // 2. Thêm lựa chọn "Tất cả" vào đầu mỗi mảng
            $vars['vendorsList'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $vendors_db);
            $vars['accountsList'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $accounts_db);
            array_unshift($stores, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars['stores'] = $stores;
            $vars['Status_invoices'] = [];
            $vars['Status_invoices'][] = ['value' => '', 'text' => $jatbi->lang('Tất cả')];
            foreach ($setting['Status_invoices'] as $id => $status) {
                $vars['Status_invoices'][] = ['value' => $id, 'text' => $status['name']];
            }
            $vars['Status_purchase'] = [];
            $vars['Status_purchase'][] = ['value' => '', 'text' => $jatbi->lang('Tất cả')];
            foreach ($setting['Status_purchase'] as $id => $status) {
                $vars['Status_purchase'][] = ['value' => $id, 'text' => $status['name']];
            }


            echo $app->render($template . '/purchases/purchases.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['column']) ? $_POST['columns'][$_POST['order'][0]['column']]['name'] : 'purchase.id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

            $vendorFilter = isset($_POST['vendor']) ? $_POST['vendor'] : '';
            $userFilter = isset($_POST['user']) ? $_POST['user'] : '';
            $store = isset($_POST['stores']) ? $_POST['stores'] : $accStore;

            $statusFilter = isset($_POST['status']) ? $_POST['status'] : '';
            $statusPayFilter = isset($_POST['status_pay']) ? $_POST['status_pay'] : '';
            $dateFilter = isset($_POST['date']) ? $_POST['date'] : '';

            // Khởi tạo các biến ngày với giá trị null
            $date_from = null;
            $date_to = null;

            // Cập nhật logic lọc theo ngày
            if (!empty($dateFilter)) {
                $date_parts = explode(' - ', $dateFilter);
                if (count($date_parts) == 2) {
                    $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date_parts[0]))));
                    $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date_parts[1]))));
                }
            } else {
                // Mặc định lọc theo ngày hiện tại nếu không có ngày nào được chọn
                $date_from = date('Y-m-d 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }

            // Chỉ thêm điều kiện vào WHERE nếu các biến ngày có giá trị
            if ($date_from && $date_to) {
                $where['AND']['purchase.date_poster[<>]'] = [$date_from, $date_to];
            }

            // Define joins for the query
            $joins = [
                "[>]vendors" => ["vendor" => "id"],
                "[>]accounts" => ["user" => "id"],
                "[>]stores" => ["stores" => "id"]
            ];

            // Khởi tạo điều kiện WHERE
            $where = [
                "AND" => ["purchase.deleted" => 0],
            ];

            // Thêm điều kiện tìm kiếm
            if ($searchValue != '') {
                $where['AND']['OR'] = [
                    'purchase.code[~]' => $searchValue,
                    'vendors.name[~]' => $searchValue,
                    'accounts.name[~]' => $searchValue
                ];
            }

            // Áp dụng các bộ lọc
            if ($vendorFilter != '') {
                $where['AND']['purchase.vendor'] = $vendorFilter;
            }
            if ($userFilter != '') {
                $where['AND']['purchase.user'] = $userFilter;
            }
            if ($store && $store !== '') {
                $where['AND']['purchase.stores'] = is_array($store) ? $store : [$store];
            }
            if ($statusFilter != '') {
                $where['AND']['purchase.status'] = $statusFilter;
            }
            if ($statusPayFilter != '') {
                $where['AND']['purchase.status_pay'] = $statusPayFilter;
            }
            // Cập nhật logic lọc theo ngày
            if (!empty($dateFilter)) {
                $date_parts = explode(' - ', $dateFilter);
                if (count($date_parts) == 2) {
                    $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date_parts[0]))));
                    $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date_parts[1]))));
                }
            } else {
                // Mặc định lọc theo ngày hiện tại nếu không có ngày nào được chọn
                $date_from = date('Y-m-d 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }
            $where['AND']['purchase.date_poster[<>]'] = [$date_from, $date_to];

            // Đếm số bản ghi SAU KHI lọc
            $count = $app->count("purchase", $joins, "purchase.id", $where);

            // Thêm sắp xếp và phân trang
            $where["ORDER"] = [$orderName => strtoupper($orderDir)];
            $where["LIMIT"] = [$start, $length];

            $datas = [];

            $columns = [
                'purchase.id',
                'purchase.code',
                'purchase.total',
                'purchase.minus',
                'purchase.discount_price',
                'purchase.surcharge',
                'purchase.payments',
                'purchase.prepay',
                'purchase.type',
                'purchase.status_pay',
                'purchase.status',
                'purchase.date_poster',
                'vendors.name(vendor_name)',
                'accounts.name(user_name)',
                'stores.name(store_name)'
            ];

            $app->select("purchase", $joins, $columns, $where, function ($data) use (&$datas, $jatbi, $vars, $app, $setting) {
                $status_pay_info = $setting['Status_invoices'][$data['status_pay']] ?? ['name' => 'Không rõ', 'color' => 'dark'];
                $status_purchase_info = $setting['Status_purchase'][$data['status']] ?? ['name' => 'Không rõ', 'color' => 'secondary'];




                $prepayButton = '';

                // Điều kiện hiển thị nút prepay (giống code cũ)
                if ($data['status_pay'] == 2 && ($data['status'] == 3 || $data['status'] == 5)) {
                    $prepayButton = '<button data-action="modal" data-url="/purchases/purchase-prepay/' . $data['id'] . '" 
                            " class="btn btn-eclo-light btn-sm border-0 py-1 px-2 rounded-3" aria-label="' . $jatbi->lang('Trả trước') . '"><i class="ti ti-cash-banknote"></i>
                         </button>';
                }

                $importButton = '';
                if ($data['status'] == 3 && $data['type'] == 1) {
                    // import hàng hóa
                    $importButton = '<a class="btn btn-sm btn-eclo-light pjax-load border-0 py-1 px-2 rounded-3" 
                            href="/warehouses/warehouses-import/purchase/' . $data['id'] . '">
                            <i class="ti ti-file-import"></i></a>';
                } elseif ($data['status'] == 3 && $data['type'] == 2) {
                    // import nguyên liệu
                    $importButton = '<a class="btn btn-sm btn-eclo-light pjax-load border-0 py-1 px-2 rounded-3" 
                            href="/warehouses/ingredient-import/purchase/' . $data['id'] . '">
                            <i class="ti ti-file-import"></i></a>';
                }
                $editButton = '';
                if ($data['status'] == 1 || $data['status'] == 4) {
                    $editButton = '<a class="btn btn-sm btn-eclo-light pjax-load border-0 py-1 px-2 rounded-3" 
                            href="/purchases/purchase-edit/' . $data['id'] . '">
                            <i class="ti ti-edit"></i></a>';
                }

                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "ma_don_hang" => '<button data-action="modal" class="modal-url btn btn-eclo-light btn-sm border-0 py-1 px-2 rounded-3" data-url="/purchases/purchase-views/' . $data['id'] . '">#' . ($setting['ballot_code']['purchase']) . '-' . $data['code'] . '</button>',
                    "nha_cung_cap" => (string) ($data['vendor_name'] ?: 'N/A'),
                    "tong_tien" => number_format($data['total'] ?? 0),
                    "giam_tru" => number_format($data['minus'] ?? 0),
                    "giam_gia" => number_format($data['discount_price'] ?? 0),
                    "phu_thu" => number_format($data['surcharge'] ?? 0),
                    "thanh_toan" => number_format($data['payments'] ?? 0),
                    "da_thanh_toan" => number_format($data['prepay'] ?? 0),
                    "con_lai" => number_format(($data['payments'] ?? 0) - ($data['prepay'] ?? 0)),
                    "trang_thai" => '<span class="fw-bold text-' . $status_pay_info['color'] . '">' . $status_pay_info['name'] . '</span>',
                    "tien_trinh" => '<span class="fw-bold p-1 rounded-3 small btn-' . $status_purchase_info['color'] . '">' . $status_purchase_info['name'] . '</span>',
                    "ngay" => $jatbi->datetime($data['date_poster'] ?? ''),
                    "tai_khoan" => $data['user_name'],
                    "cua_hang" => $data['store_name'],
                    "prepay" => $prepayButton,
                    "import" => $importButton,
                    "edit" => $editButton,
                    "action" => '<button data-action="modal" data-url="/purchases/purchase-views/' . $data['id'] . '" class="btn btn-eclo-light btn-sm border-0 py-1 px-2 rounded-3" aria-label="' . $jatbi->lang('Xem') . '"><i class="ti ti-eye"></i></button>',

                ];
            });

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas ?? []
            ]);
        }
    })->setPermissions(['purchase']);

    $app->router('/purchase-views/{id}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {
        // Lấy danh sách sản phẩm theo purchase ID
        $vars['datas'] = $app->select("purchase_products", ["id", "products", "amount", "price", "ingredient"], [
            "purchase" => $vars['id']
        ]);

        // Lấy chi tiết
        $vars['details'] = $app->select("purchase_details", "*", [
            "purchase" => $vars['id'],
            "deleted" => 0
        ]);

        // Lấy nhật ký
        $vars['logs'] = $app->select("purchase_logs", "*", [
            "purchase" => $vars['id'],
            "deleted" => 0
        ]);
        $var['Status_purchase'] = $setting['Status_purchase'] ?? [];

        // Lấy thông tin đơn hàng
        $purchase = $app->get("purchase", "*", [
            "id" => (int) $vars['id'],
            "deleted" => 0
        ]);

        // Gán thông tin đơn hàng nếu có
        if ($purchase) {
            $purchase['notes'] = $purchase['notes'] ?? '';
            $purchase['amount'] = $purchase['amount'] ?? 0;
            $purchase['price'] = $purchase['price'] ?? 0;
            $purchase['code'] = $purchase['code'] ?? '';
            $purchase['supplier'] = $purchase['supplier'] ?? '';

            $vars['data'] = $purchase;
        }

        // Đảm bảo $colspan tồn tại nếu HTML có dùng
        $vars['colspan'] = isset($vars['colspan']) ? $vars['colspan'] : 7;

        if (!empty($vars['datas'])) {
            echo $app->render($template . '/purchases/purchase-views.html', $vars, $jatbi->ajax());
        } else {
            echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
        }
    })->setPermissions(['purchase']);


    $app->router('/purchase-add', 'GET', function ($vars) use ($accStore, $app, $jatbi, $setting, $stores, $template) {
        $action = 'edit';
        $vars['action'] = $action;
        $vars['title'] = $jatbi->lang("Tạo Đề xuất mua hàng");

        $sessionData = &$_SESSION['purchase'][$action];
        if (empty($sessionData)) {
            $sessionData = ['status' => 1, 'type' => 1, 'date' => date("Y-m-d"), 'stores' => []];
        }
        $vars['data'] = $sessionData;
        $selected_items = [];
        if ($sessionData['type'] == 1)
            $selected_items = $sessionData['products'] ?? [];
        else
            $selected_items = $sessionData['ingredient'] ?? [];
        $vars['selected_items'] = $selected_items;

        $details_map = [];
        if (!empty($selected_items)) {
            if ($sessionData['type'] == 1) {
                $item_ids = array_column($selected_items, 'products');

                $details_db = $app->select(
                    "products",
                    [
                        "[>]units" => ["units" => "id"],
                        "[>]vendors" => ["vendor" => "id"]
                    ],
                    [
                        "products.id",
                        "products.name",
                        "products.images",
                        "units.name(unit_name)",
                        "vendors.name(vendor_name)"
                    ],
                    ["products.id" => $item_ids]
                );
                $details_map = array_column($details_db, null, 'id');
            } else {

                $item_ids = array_column($selected_items, 'ingredient');

                $details_db = $app->select(
                    "ingredient",
                    [
                        "[>]units" => ["units" => "id"]
                    ],
                    [
                        "ingredient.id",
                        "ingredient.code",
                        "ingredient.type",
                        "units.name(unit_name)"
                    ],
                    ["ingredient.id" => $item_ids]
                );
                $details_map = array_column($details_db, null, 'id');
            }
        }
        $vars['details_map'] = $details_map;

        $vars['vendors'] = $app->select("vendors", ["id(value)", "name(text)"], ["deleted" => 0]);
        $vars['stores'] = $stores;
        $store_placeholder = ["value" => "", "text" => $jatbi->lang("Chọn cửa hàng") ?? "--- Chọn kho ---"];
        array_unshift($vars['stores'], $store_placeholder);
        $vendor_placeholder = ["value" => "", "text" => $jatbi->lang("Chọn NCC") ?? "--- Chọn NCC ---"];
        array_unshift($vars['vendors'], $vendor_placeholder);

        $total = 0;
        $minu = 0;
        $surcharge = 0;
        $prepay_req = 0;
        $vars['type_map'] = [
            1 => $jatbi->lang('Đai'),
            2 => $jatbi->lang('Ngọc'),
            3 => 'Khác'
        ];
        foreach ($selected_items as $item) {
            $total += ($item['amount'] * $item['price']);
        }
        foreach ($sessionData['minus'] ?? [] as $minus) {
            $minu += $minus['price'];
        }
        foreach ($sessionData['surcharge'] ?? [] as $s) {
            $surcharge += $s['price'];
        }
        foreach ($sessionData['prepay_req'] ?? [] as $prepay) {
            $prepay_req += $prepay['price'];
        }
        $discount_amount = ($sessionData['discount'] ?? 0 * $total / 100);
        $payment = (($total - $minu - $discount_amount) + $surcharge);
        $vars['total'] = $total;
        $vars['minu'] = $minu;
        $vars['surcharge'] = $surcharge;
        $vars['prepay_req'] = $prepay_req;
        $vars['discount_amount'] = $discount_amount;
        $vars['payment'] = $payment;

        echo $app->render($template . '/purchases/purchase-post.html', $vars);
    })->setPermissions(['purchase.add']);

    $app->router('/purchase-prepay/{id}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {
        $id = (int) ($vars['id'] ?? 0);

        // Lấy thông tin đơn mua hàng một lần duy nhất
        $data = $app->get("purchase", "*", [
            "id" => $id,
            "deleted" => 0,
            "status" => [3, 5]
        ]);

        // Nếu không tìm thấy đơn hàng, trả về lỗi 404
        if (!$data) {
            header("HTTP/1.0 404 Not Found");
            die();
        }

        if ($app->method() === 'GET') {
            // Lấy danh sách sản phẩm và lịch sử thanh toán
            $SelectProducts = $app->select("purchase_products", "*", [
                "purchase" => $data['id'],
                "deleted" => 0
            ]);
            $details = $app->select("purchase_details", "*", [
                "purchase" => $data['id'],
                "deleted" => 0,
                "type" => "prepay"
            ]);

            // Lấy danh sách tài khoản kế toán và định dạng
            // Tạm thời bỏ điều kiện xóa để kiểm tra
            $accountants_raw = $app->select("accountants_code", ["code", "name"], ["deleted" => 0, "status" => "A"]);
            $accountants_options = [['value' => '', 'text' => $jatbi->lang("Chọn tài khoản")]];
            foreach ($accountants_raw as $item) {
                $accountants_options[] = ['value' => $item['code'], 'text' => $item['code'] . ' - ' . $item['name']];
            }

            // Chuẩn bị biến cho template
            $vars['data'] = $data;
            $vars['SelectProducts'] = $SelectProducts;
            $vars['details'] = $details;
            $vars['accountants_options'] = $accountants_options;
            $vars['Status_purchase'] = $setting['Status_purchase'];
            $vars['ballot_code'] = $setting['ballot_code'] ?? ['purchase' => 'PUR'];
            $vars['title'] = $jatbi->lang("Lập phiếu chi") . ' #' . ($vars['ballot_code']['purchase'] ?? 'PUR') . '-' . $data['code'];
            $vars['upload'] = $setting['upload'];
            $vars['app'] = $app; // Truyền đối tượng app vào template

            echo $app->render($template . '/purchases/purchase-prepay.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            // // Xác thực dữ liệu
            // if (!isset($_POST['token']) || $_POST['token'] != $_SESSION['csrf-token']) {
            //     echo json_encode(['status' => 'error', 'content' => $jatbi->lang('token-khong-dung')]);
            //     return;
            // } 
            // 2. Làm sạch dữ liệu từ form
            $price = isset($_POST['price']) ? trim(str_replace(',', '', $_POST['price'])) : '';
            $date = isset($_POST['date']) ? trim($_POST['date']) : '';
            $debt = isset($_POST['debt']) ? trim($_POST['debt']) : '';
            $has = isset($_POST['has']) ? trim($_POST['has']) : '';
            $content = isset($_POST['content']) ? trim($_POST['content']) : '';

            // 3. Kiểm tra dữ liệu bắt buộc
            if ($price === '' || $date === '' || $debt === '' || $has === '') {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Lỗi trống"), 'sound' => $setting['site_sound']]);
                return;
            }

            // 4. Kiểm tra định dạng số tiền
            if (!is_numeric($price) || (float) $price <= 0) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Số tiền không hợp lệ"), 'sound' => $setting['site_sound']]);
                return;
            }

            $price_raw = str_replace(',', '', $_POST['price']);

            // Chèn chi tiết thanh toán
            $prepay_payment = [
                "code" => time(),
                "purchase" => $data['id'],
                "type" => "prepay",
                "price" => $app->xss($price_raw),
                "content" => $app->xss($_POST['content']),
                "date" => date('Y-m-d H:i:s', strtotime($app->xss($_POST['date']) . ' ' . date('H:i:s'))),
                "user" => $app->getSession("accounts")['id'] ?? 0,
                "date_poster" => date('Y-m-d H:i:s'),
            ];
            $app->insert("purchase_details", $prepay_payment);

            // Tạo phiếu chi
            $expenditure = [
                "type" => 2,
                "debt" => $app->xss($_POST['debt']),
                "has" => $app->xss($_POST['has']),
                "price" => '-' . $app->xss($price_raw),
                "content" => $app->xss($_POST['content']),
                "date" => date('Y-m-d H:i:s', strtotime($app->xss($_POST['date']) . ' ' . date('H:i:s'))),
                "vendor" => $app->xss($data['vendor']),
                "user" => $app->getSession("accounts")['id'] ?? 0,
                "date_poster" => date("Y-m-d H:i:s"),
                "stores" => $app->xss($data['stores']),
            ];
            $app->insert("expenditure", $expenditure);

            // Cập nhật số tiền đã trả
            $newPrepayAmount = $prepay_payment['price'] + $data['prepay'];
            $app->update("purchase", ["prepay" => $newPrepayAmount], ['id' => $data['id']]);

            // Cập nhật trạng thái thanh toán
            if ($data['status_pay'] == 2 && $newPrepayAmount >= $data['payments']) {
                $app->update("purchase", ["status_pay" => 1], ['id' => $data['id']]);
            }

            // Ghi nhật ký
            $app->insert("purchase_logs", [
                "purchase" => $data['id'],
                "user" => $app->getSession("accounts")['id'] ?? 0,
                "content" => 'Thanh toán mua hàng: ' . number_format($prepay_payment['price']),
                "status" => $app->xss($data['status']),
                "date" => date('Y-m-d H:i:s'),
            ]);

            // Ghi log hoạt động
            $jatbi->logs('purchase', 'prepay', [$prepay_payment, $expenditure]);


            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('cap-nhat-thanh-cong')]);
        }
    });

    $app->router("/purchase-edit/{id}", ['GET'], function ($vars) use ($app, $jatbi, $setting, $accStore, $stores, $template) {
        $vars['title'] = $jatbi->lang("Sửa đề xuất");
        $action = "edit";
        $vars['action'] = $action;
        $invoices = $app->get("purchase", "*", ["id" => $app->xss($vars['id']), "deleted" => 0]);
        if ($invoices > 1) {
            $Cus = $app->get("vendors", "*", ["id" => $invoices['vendor']]);
            $Pros = $app->select("purchase_products", "*", ["purchase" => $invoices['id'], "deleted" => 0]);
            $Details = $app->select("purchase_details", "*", ["purchase" => $invoices['id'], "deleted" => 0]);
            if (($_SESSION['purchase'][$action]['order'] ?? null) != ($invoices['id'] ?? null)) {
                unset($_SESSION['purchase'][$action]);
                $_SESSION['purchase'][$action]['status'] = $invoices['status'];
                $_SESSION['purchase'][$action]['stores'] = $invoices['stores'];
                $_SESSION['purchase'][$action]['status_pay'] = $invoices['status_pay'];
                $_SESSION['purchase'][$action]['discount'] = $invoices['discount'];
                $_SESSION['purchase'][$action]['code'] = $invoices['code'];
                $_SESSION['purchase'][$action]['content'] = $invoices['content'];
                $_SESSION['purchase'][$action]['type'] = $invoices['type'];
                $_SESSION['purchase'][$action]['date'] = date("Y-m-d", strtotime($invoices['date']));
                $_SESSION['purchase'][$action]['vendor'] = [
                    "id" => $Cus['id'],
                    "name" => $Cus['name'],
                    "phone" => $Cus['phone'],
                    "email" => $Cus['email'],
                ];
                foreach ($Pros as $key => $value) {
                    if ($invoices['type'] == 1) {
                        $GetPro = $app->get("products", "*", ["id" => $value['products'], "deleted" => 0]);
                        $_SESSION['purchase'][$action]['products'][$value['id']] = [
                            "id" => $value['id'],
                            "products" => $value['products'],
                            "images" => $GetPro['images'],
                            "code" => $GetPro['code'],
                            "name" => $GetPro['name'],
                            "categorys" => $value['categorys'],
                            "amount" => $value['amount'],
                            "price" => $value['price'],
                            "units" => $GetPro['units'],
                            "notes" => $value['notes'],
                            "status" => $value['status'],
                        ];
                    } elseif ($invoices['type'] == 2) {
                        $GetIng = $app->get("ingredient", "*", ["id" => $value['ingredient'], "deleted" => 0]);
                        $_SESSION['purchase'][$action]['ingredient'][$value['id']] = [
                            "id" => $value['id'],
                            "ingredient" => $value['ingredient'],
                            "code" => $GetIng['code'],
                            "name" => $GetIng['name_ingredient'],
                            "categorys" => $value['categorys'],
                            "amount" => $value['amount'],
                            "price" => $value['price'],
                            "units" => $GetIng['units'],
                            "notes" => $value['notes'],
                            "status" => $value['status'],
                        ];
                    }
                }
                foreach ($Details as $detail) {
                    $_SESSION['purchase'][$action][$detail['type']][] = [
                        "type"            => $detail['type'],
                        "price"         => $app->xss(str_replace([','], '', $detail['price'])),
                        "content"         => $app->xss($detail['content']),
                        "date"             => $app->xss($detail['date']),
                        "user"             => $detail['user'],
                    ];
                }
                $_SESSION['purchase'][$action]['order'] = $invoices['id'];
            }
            if (count($stores) == 1) {
                $_SESSION['purchase']['edit']['stores'] = ["id" => $app->get("stores", "id", ["id" => $accStore, "status" => 'A', "deleted" => 0, "ORDER" => ["id" => "ASC"]])];
            }
            $data = [
                "type"    => $_SESSION['purchase'][$action]['type'],
                "vendor"    => $_SESSION['purchase'][$action]['vendor'],
                "stores"    => $_SESSION['purchase'][$action]['stores']['id'] ?? "",
                "date" => $_SESSION['purchase'][$action]['date'],
                "discount" => $_SESSION['purchase'][$action]['discount'],
                "content" => $_SESSION['purchase'][$action]['content'],
                "status" => $_SESSION['purchase'][$action]['status'],
            ];
            $getCus = $app->get("vendors", "*", ["id" => $_SESSION['purchase'][$action]['vendor']['id']]);
            if ($data['type'] == 1) {
                $SelectProducts = $_SESSION['purchase'][$action]['products'] ?? [];
                $products = $app->select("products", "*", ["deleted" => 0, "status" => 'A', "type" => $data['type'], "stores" => $data['stores']]);
            }
            if ($data['type'] == 2) {
                $SelectIngredients = $_SESSION['purchase'][$action]['ingredient'] ?? [];
                $ingredient = $app->select("ingredient", "*", ["deleted" => 0, "status" => 'A']);

                $ingredient_format = [];

                $ingredient_format[] = [
                    'value' => '',
                    'text'  => $jatbi->lang('Chọn sản phẩm')
                ];

                foreach ($ingredient as $row) {
                    $typeName = ($row['type'] == 1) ? 'Đai'
                        : (($row['type'] == 2) ? 'Ngọc' : 'Khác');

                    $ingredient_format[] = [
                        'value' => $row['id'],
                        'text'  => $row['code'] . ' - ' . $typeName
                    ];
                }
            }
            $vars['data'] = $data;
            $vars['getCus'] = $getCus;
            $vars['SelectProducts'] = $SelectProducts ?? [];
            $vars['SelectIngredients'] = $SelectIngredients ?? [];
            $vars['products'] = $products ?? [];
            $vars['ingredient'] = $ingredient_format ?? [];
            $type = [
                ["value" => 1, "text" => $jatbi->lang("Đề xuất mua hàng")],
                ["value" => 2, "text" => $jatbi->lang("Đề xuất mua nguyên liệu")],
            ];
            $vars['type'] = $type;
            $vendors = $app->select("vendors", ["id(value)", "name(text)"], ["deleted" => 0, "status" => 'A',]);
            array_unshift($vendors, ["value" => "", "text" => $jatbi->lang("Chọn nhà cung cấp")]);
            $vars['vendors'] = $vendors;
            $storess = $app->select("stores", ["id(value)", "name(text)"], ["deleted" => 0, "status" => 'A', "id" => $accStore]);
            array_unshift($storess, ["value" => "", "text" => $jatbi->lang("Chọn cửa hàng")]);
            $vars['storess'] = $storess;
            echo $app->render($template . '/purchases/purchase-edit.html', $vars);
        } else {
            echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
        }
    })->setPermissions(['purchase.edit']);

    $app->router('/purchase-update/edit/type', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
        $app->header(['Content-Type' => 'application/json']);
        $action = "edit";
        $_SESSION['purchase'][$action]['type'] = $app->xss($_POST['value']);
        unset($_SESSION['purchase'][$action]['products']);
        unset($_SESSION['purchase'][$action]['ingredient']);
        echo json_encode(['status' => 'success', 'content' => "Cập nhật thành công"]);
    });

    $app->router('/purchase-update/edit/vendor', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
        $app->header(['Content-Type' => 'application/json']);
        $action = "edit";
        $data = $app->get("vendors", ["id", "name", "phone", "email"], ["id" => $app->xss($_POST['value'])]);
        if ($data > 1) {
            unset($_SESSION['purchase'][$action]['products']);
            unset($_SESSION['purchase'][$action]['ingredient']);
            $_SESSION['purchase'][$action]['vendor'] = [
                "id" => $data['id'],
                "name" => $data['name'],
                "phone" => $data['phone'],
                "email" => $data['email'],
            ];
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } else {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại")]);
        }
    });

    $app->router('/purchase-update/edit/stores', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
        $app->header(['Content-Type' => 'application/json']);
        $action = "edit";
        $data = $app->get("stores", ["id", "name"], ["id" => $app->xss($_POST['value'])]);
        if ($data > 1) {
            unset($_SESSION['purchase'][$action]['products']);
            unset($_SESSION['purchase'][$action]['ingredient']);
            $_SESSION['purchase'][$action]['stores'] = [
                "id" => $data['id'],
                "name" => $data['name'],
            ];
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } else {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại")]);
        }
    });

    $app->router('/purchase-update/edit/date', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
        $app->header(['Content-Type' => 'application/json']);
        $action = "edit";
        $_SESSION['purchase'][$action]["date"] = $app->xss($_POST['value']);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/purchase-update/edit/discount', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
        $app->header(['Content-Type' => 'application/json']);
        $action = "edit";
        $_SESSION['purchase'][$action]["discount"] = str_replace(',', '', $app->xss($_POST['value']));
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/purchase-update/edit/content', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
        $app->header(['Content-Type' => 'application/json']);
        $action = "edit";
        $_SESSION['purchase'][$action]["content"] = $app->xss($_POST['value']);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/purchase-update/edit/cancel', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/comfirm-modal.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $action = "edit";
            unset($_SESSION['purchase'][$action]);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        }
    });

    $app->router('/purchase-update/edit/products/add/{id}', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
        $app->header(['Content-Type' => 'application/json']);
        $id = $app->xss($vars['id']);
        $action = "edit";

        $data = $app->get("products", "*", [
            "id" => $id,
            "status" => "A",
            "deleted" => 0
        ]);

        if ($data > 1) {
            $_SESSION['purchase'][$action]['products'][] = [
                "products" => $data['id'],
                "amount" => 1,
                "price" => $data['price'],
                "vendor" => $data['vendor'],
                "images" => $data['images'],
                "code" => $data['code'],
                "name" => $data['name'],
                "categorys" => $data['categorys'],
                "units" => $data['units'],
                "notes" => $data['notes'],
            ];

            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } else {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại")]);
        }
    });

    $app->router('/purchase-update/edit/products/{req}/{id}', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
        $app->header(['Content-Type' => 'application/json']);
        $action = "edit";
        $router['4'] = $app->xss($vars['req']);
        $router['5'] = $app->xss($vars['id']);
        if ($router['4'] == 'add') {
            $data = $app->get("products", "*", ["id" => $app->xss($router['5']), "status" => "A", "deleted" => 0,]);
            if ($data > 1) {
                $_SESSION['purchase'][$action]['products'][] = [
                    "products" => $data['id'],
                    "amount" => 1,
                    "price" => $data['price'],
                    "vendor" => $data['vendor'],
                    "images" => $data['images'],
                    "code" => $data['code'],
                    "name" => $data['name'],
                    "categorys" => $data['categorys'],
                    "units" => $data['units'],
                    "notes" => $data['notes'],
                ];
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại")]);
            }
        } elseif ($router['4'] == 'deleted') {
            unset($_SESSION['purchase'][$action]['products'][$router['5']]);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } elseif ($router['4'] == 'price') {
            $_SESSION['purchase'][$action]['products'][$router['5']][$router['4']] = $app->xss(str_replace([','], '', $_POST['value']));
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } else {
            $_SESSION['purchase'][$action]['products'][$router['5']][$router['4']] = $app->xss($_POST['value']);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        }
    });

    $app->router('/purchase-update/edit/details/{req}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {
        $action = "edit";
        if ($app->method() === 'GET') {
            $datas = $_SESSION['purchase'][$action][$vars['req']] ?? [];
            $vars['datas'] = $datas;
            $vars['title'] = $jatbi->lang("Chi tiết thanh toán");
            $vars['req'] = $vars['req'];
            echo $app->render($template . '/purchases/modal/details.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            if (empty($_POST['date'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng chọn ngày")]);
            }
            if ($_POST['date']) {
                $_SESSION['purchase'][$action][$vars['req']][] = [
                    "code"            => strtotime(date('Y-m-d H:i:s')),
                    "type"            => $vars['req'],
                    "price"         => $app->xss(str_replace([','], '', $_POST['price'])),
                    "content"         => $app->xss($_POST['content']),
                    "date"             => date('Y-m-d H:i:s', strtotime($app->xss($_POST['date']))),
                    "user"             => $app->getSession("accounts")['id'] ?? 0,
                ];
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            }
        }
    });

    $app->router('/purchase-update/edit/details-deleted/{req}/{id}', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
        $app->header(['Content-Type' => 'application/json']);
        $action = "edit";
        unset($_SESSION['purchase'][$action][$vars['req']][$vars['id']]);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/purchase-update/edit/ingredient/add/{id}', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
        $app->header(['Content-Type' => 'application/json']);
        $id = $app->xss($vars['id']);
        $action = "edit";
        $data = $app->get("ingredient", "*", ["id" => $id]);
        if ($data > 1) {
            $_SESSION['purchase'][$action]['ingredient'][] = [
                "ingredient" => $data['id'],
                // "price" => '',
                "amount" => 1,
                "vendor" => $data['vendor'],
                "pearl" => $data['pearl'],
                "colors" => $data['colors'],
                "sizes" => $data['sizes'],
                "price" => $data['price'],
                "cost" => $data['cost'],
                "code" => $data['code'],
                "categorys" => $data['categorys'],
                "units" => $data['units'],
                "notes" => $data['notes'],
            ];
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } else {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại")]);
        }
    });

    $app->router('/purchase-update/edit/ingredient/{req}/{id}', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
        $app->header(['Content-Type' => 'application/json']);
        $action = "edit";
        $router['4'] = $app->xss($vars['req']);
        $router['5'] = $app->xss($vars['id']);
        if ($router['4'] == 'deleted') {
            unset($_SESSION['purchase'][$action]['ingredient'][$router['5']]);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } elseif ($router['4'] == 'price') {
            $_SESSION['purchase'][$action]['ingredient'][$router['5']][$router['4']] = $app->xss(str_replace([','], '', $_POST['value']));
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } elseif ($router['4'] == 'amount') {
            $value = $app->xss(str_replace([','], '', $_POST['value']));
            if ($value < 0) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Số lượng không được âm")]);
            } else {
                $_SESSION['purchase'][$action]['ingredient'][$router['5']][$router['4']] = $app->xss(str_replace([','], '', $_POST['value']));
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            }
        } else {
            $_SESSION['purchase'][$action]['ingredient'][$router['5']][$router['4']] = $app->xss($_POST['value']);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        }
    });

    $app->router('/purchase-update/edit/completed', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'GET') {
            $vars['url'] = "/purchases/purchase";
            echo $app->render($setting['template'] . '/common/comfirm-modal.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $action = "edit";
            $error = [];
            $error_warehouses = 'false';
            $total_products = 0;
            $total_ingredient = 0;
            $total_minus = 0;
            $total_surcharge = 0;
            $total_prepay_req = 0;
            $pro_logs = [];
            foreach (($_SESSION['purchase'][$action]['products'] ?? []) as $value) {
                $total_products += $value['amount'] * $value['price'];
                if ($value['amount'] == '' || $value['amount'] == 0) {
                    $error_warehouses = 'true';
                }
            }
            foreach (($_SESSION['purchase'][$action]['ingredient'] ?? []) as $value) {
                $total_ingredient += $value['amount'] * $value['price'];
                if ($value['amount'] == '' || $value['amount'] == 0) {
                    $error_warehouses = 'true';
                }
            }
            foreach (($_SESSION['purchase'][$action]['minus'] ?? []) as $minus) {
                $total_minus += $minus['price'];
            }
            foreach (($_SESSION['purchase'][$action]['prepay_req'] ?? []) as $prepay_req) {
                $total_prepay_req += $prepay_req['price'];
            }
            foreach (($_SESSION['purchase'][$action]['surcharge'] ?? []) as $surcharge) {
                $total_surcharge += $surcharge['price'];
            }
            $discount = ($_SESSION['purchase'][$action]['discount'] * $total_products) / 100;
            $payments = ($total_products - $total_minus - $discount) + $total_surcharge;
            $payments1 = ($total_ingredient - $total_minus - $discount) + $total_surcharge;
            if (
                empty($_SESSION['purchase'][$action]['stores']['id'] ?? '') ||
                empty($_SESSION['purchase'][$action]['vendor']['id'] ?? '') ||
                empty($_SESSION['purchase'][$action]['type'] ?? '') ||
                empty($_SESSION['purchase'][$action]['content'] ?? '')
            ) {
                $error = [
                    "status"  => 'error',
                    "content" => $jatbi->lang("Vui lòng điền đầy đủ thông tin"),
                ];
            } elseif ($error_warehouses == 'true') {
                $error = ['status' => 'error', 'content' => $jatbi->lang("Vui lòng chọn kho hàng")];
            }
            if (count($error) == 0) {
                if ($action == 'add') {
                    $code = strtotime(date("Y-m-d H:i:s"));
                }
                if ($action == 'edit') {
                    $code = $_SESSION['purchase'][$action]['code'];
                    $app->update("purchase_details", ["deleted" => 1], ["purchase" => $_SESSION['purchase'][$action]['order']]);
                    $app->update("purchase_products", ["deleted" => 1], ["purchase" => $_SESSION['purchase'][$action]['order']]);
                }
                $insert = [
                    "type"            => $_SESSION['purchase'][$action]['type'],
                    "vendor"        => $_SESSION['purchase'][$action]['vendor']['id'],
                    "code"            => $code,
                    "date"            => $_SESSION['purchase'][$action]['date'],
                    "total"            => $_SESSION['purchase'][$action]['type'] == 1 ? $total_products : $total_ingredient,
                    "minus"            => $total_minus,
                    "surcharge"        => $total_surcharge,
                    "prepay_req"    => $total_prepay_req,
                    "prepay"        => 0,
                    "discount"        => $_SESSION['purchase'][$action]['discount'],
                    "discount_price" => $discount,
                    "payments"        => $_SESSION['purchase'][$action]['type'] == 1 ? $payments : $payments1,
                    "user"            => $app->getSession("accounts")['id'] ?? 0,
                    "date_poster"    => date("Y-m-d H:i:s"),
                    "status"        => 1,
                    "status_pay"    => $_SESSION['purchase'][$action]['status_pay'] == 0 ? 2 : $_SESSION['purchase'][$action]['status_pay'],
                    "content"        => $_SESSION['purchase'][$action]['content'],
                    "active"        => $jatbi->active(30),
                    "keycode"        => $jatbi->random(6),
                    "stores"         => $_SESSION['purchase'][$action]['stores']['id'],
                ];
                if ($action == "add") {
                    $app->insert("purchase", $insert);
                    $orderId = $app->id();
                    $app->insert("purchase_logs", [
                        "purchase"     => $orderId,
                        "user"        => $app->getSession("accounts")['id'] ?? 0,
                        "content"     => $app->xss('Đề xuất mua hàng'),
                        "status"     => $app->xss($insert['status']),
                        "date"        => date('Y-m-d H:i:s'),
                    ]);
                }
                if ($action == "edit") {
                    $app->update("purchase", $insert, ["id" => $_SESSION['purchase'][$action]['order']]);
                    $orderId = $_SESSION['purchase'][$action]['order'];
                    $app->insert("purchase_logs", [
                        "purchase"     => $orderId,
                        "user"        => $app->getSession("accounts")['id'] ?? 0,
                        "content"     => $app->xss('Sửa đề xuất mua hàng'),
                        "status"     => $app->xss($insert['status']),
                        "date"        => date('Y-m-d H:i:s'),
                    ]);
                }
                if ($_SESSION['purchase'][$action]['type'] == 1) {
                    foreach ($_SESSION['purchase'][$action]['products'] as $key => $value) {
                        $getProducts = $app->get("products", "*", ["id" => $value['products']]);
                        $pro = [
                            "purchase" => $orderId,
                            "vendor" => $value['vendor'],
                            "products" => $value['products'],
                            "categorys" => $value['categorys'],
                            "amount" => $value['amount'],
                            "weight" => $value['weight'],
                            "price" => $value['price'],
                            "total" => $value['amount'] * $value['price'],
                            "date" => date("Y-m-d H:i:s"),
                            "user" => $app->getSession("accounts")['id'] ?? 0,
                            "status" => 1,
                            "active" => $jatbi->active(30),
                        ];
                        $app->insert("purchase_products", $pro);
                        $pro_logs[] = $pro;
                    }
                } else {
                    foreach (($_SESSION['purchase'][$action]['ingredient'] ?? []) as $key => $value) {
                        $getIngredients = $app->get("ingredient", "*", ["id" => $value['ingredient']]);
                        $pro = [
                            "purchase" => $orderId,
                            "vendor" => $value['vendor'],
                            "ingredient" => $value['ingredient'] ?? 0,
                            "categorys" => $value['categorys'] ?? 0,
                            "amount" => $value['amount'] ?? 0,
                            "weight" => $value['weight'] ?? 0,
                            "price" => $value['price'] ?? 0,
                            "total" => $value['amount'] * $value['price'] ?? 0,
                            "date" => date("Y-m-d H:i:s"),
                            "user" => $app->getSession("accounts")['id'] ?? 0,
                            "status" => 1,
                            "active" => $jatbi->active(30),
                        ];
                        $app->insert("purchase_products", $pro);
                        $pro_logs[] = $pro;
                    }
                }
                foreach (($_SESSION['purchase'][$action]['minus'] ?? []) as $minus) {
                    $minus_insert = [
                        "code"        => $prepay['code'] ?? "",
                        "purchase"    => $orderId,
                        "type"        => "minus",
                        "price"     => $app->xss(str_replace([','], '', $minus['price'])),
                        "content"     => $app->xss($minus['content']),
                        "date"         => date('Y-m-d H:i:s', strtotime($app->xss($minus['date']))),
                        "user"        => $minus['user'],
                        "date_poster" => date('Y-m-d H:i:s'),
                    ];
                    $app->insert("purchase_details", $minus_insert);
                }
                foreach (($_SESSION['purchase'][$action]['surcharge'] ?? []) as $surcharge) {
                    $surcharge_insert = [
                        "code"        => $prepay['code'] ?? "",
                        "purchase"    => $orderId,
                        "type"        => "surcharge",
                        "price"     => $app->xss(str_replace([','], '', $surcharge['price'])),
                        "content"     => $app->xss($surcharge['content']),
                        "date"         => date('Y-m-d H:i:s', strtotime($app->xss($surcharge['date']))),
                        "user"        => $surcharge['user'],
                        "date_poster" => date('Y-m-d H:i:s'),
                    ];
                    $app->insert("purchase_details", $surcharge_insert);
                }
                foreach (($_SESSION['purchase'][$action]['prepay_req'] ?? []) as $prepay) {
                    $prepay_insert = [
                        "code"        => $prepay['code'],
                        "purchase"    => $orderId,
                        "type"        => "prepay_req",
                        "price"     => $app->xss(str_replace([','], '', $prepay['price'])),
                        "content"     => $app->xss($prepay['content']),
                        "date"         => date('Y-m-d H:i:s', strtotime($app->xss($prepay['date']))),
                        "user"        => $prepay['user'],
                        "date_poster" => date('Y-m-d H:i:s'),
                    ];
                    $app->insert("purchase_details", $prepay_insert);
                }
                $jatbi->logs('purchase', $action, [$insert, $pro_logs, $_SESSION['purchase'][$action]]);
                unset($_SESSION['purchase'][$action]);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $error['content']]);
            }
        }
    });

    $app->router('/purchase-update/edit/products/deleted/{id}', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
        $app->header(['Content-Type' => 'application/json']);
        $action = "edit";
        $key = $vars['id'];
        unset($_SESSION['purchase'][$action]['products'][$key]);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/purchase-update/edit/products/price/{id}', ['POST'], function ($vars) use ($app, $jatbi, $setting) {

        $app->header(['Content-Type' => 'application/json']);
        $action = "edit";

        $key = $vars['id'];
        $field = 'price';

        $value = $app->xss(str_replace([','], '', $_POST['value']));

        $_SESSION['purchase'][$action]['products'][$key][$field] = $value;

        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/purchase-update/edit/products/amount/{id}', ['POST'], function ($vars) use ($app, $jatbi, $setting) {

        $app->header(['Content-Type' => 'application/json']);
        $action = "edit";

        $key = $vars['id'];
        $field = 'amount';

        $value = $app->xss(str_replace([','], '', $_POST['value']));

        $_SESSION['purchase'][$action]['products'][$key][$field] = $value;

        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/purchase-update/edit/ingredient/deleted/{id}', ['POST'], function ($vars) use ($app, $jatbi, $setting) {

        $app->header(['Content-Type' => 'application/json']);
        $action = "edit";
        $key = $vars['id'];
        unset($_SESSION['purchase'][$action]['ingredient'][$key]);

        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/purchase-update/edit/ingredient/price/{id}', ['POST'], function ($vars) use ($app, $jatbi, $setting) {

        $app->header(['Content-Type' => 'application/json']);
        $action = "edit";

        $key = $vars['id'];
        $field = 'price';

        $value = $app->xss(str_replace([','], '', $_POST['value']));

        $_SESSION['purchase'][$action]['ingredient'][$key][$field] = $value;

        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/purchase-update/edit/ingredient/amount/{id}', ['POST'], function ($vars) use ($app, $jatbi, $setting) {

        $app->header(['Content-Type' => 'application/json']);
        $action = "edit";

        $key = $vars['id']; 
        $field = 'amount'; 
        $value = $app->xss(str_replace([','], '', $_POST['value']));

        if ($value < 0) {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Số lượng không được âm")]);
        } else {
            $_SESSION['purchase'][$action]['ingredient'][$key][$field] = $value;
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        }
    });


    
})->middleware('login');
