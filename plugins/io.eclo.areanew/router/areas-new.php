<?php
if (!defined('ECLO'))
    die("Hacking attempt");

use ECLO\App;

$template = __DIR__ . '/../templates';

$jatbi = $app->getValueData('jatbi');
$common = $jatbi->getPluginCommon('io.eclo.proposal');
$setting = $app->getValueData('setting');
$provinces = $app->select("province", "*", ["deleted" => 0, "status" => 'A',]);
$districts = $app->select("district", "*", ["deleted" => 0, "status" => 'A',]);
$app->group($setting['manager'] . "/areas-new", function ($app) use ($jatbi, $setting,$template) {
    $app->router("/province", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting,$template) {
        $vars['title'] = $jatbi->lang("Tỉnh thành");
        if ($app->method() === 'GET') {
            echo $app->render($template . '/areas/province.html', $vars);
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
                        "province_new.name[~]" => $searchValue,
                    ],
                    "province_new.deleted" => 0,
                    "province_new.status[<>]" => $status,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];

            $count = $app->count("province_new", [
                "AND" => $where['AND'],
            ]);

            // Lấy dữ liệu phiếu giảm giá
            $datas = [];
            $app->select("province_new", ["id", "name", "status"], $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "name" => $data['name'],
                    "status" => $app->component("status", [
                        "url" => "/areas/province-status/" . $data['id'],
                        "data" => $data['status'],
                        "permission" => ['province.edit']
                    ]),
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['province.edit'],
                                'action' => ['data-url' => '/areas/province-edit/' . $data['id'], 'data-action' => 'modal']
                            ],
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
    })->setPermissions(['province']);

    // --- THÊM TỈNH THÀNH ---
    $app->router('/province-add', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting,$template) {
        $vars['title'] = $jatbi->lang("Thêm Tỉnh thành");
        if ($app->method() === 'GET') {
            $vars['data'] = ['status' => 'A'];
            echo $app->render($template . '/areas/province-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            if (empty($_POST['name'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng không để trống")]);
                return;
            }
            $insert = ["name" => $app->xss($_POST['name']), "status" => $app->xss($_POST['status'])];
            $app->insert("province_new", $insert);
            $jatbi->logs('province_new', 'add', $insert);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Thêm mới thành công"), 'url' => 'auto']);
        }
    })->setPermissions(['province.add']);

    // --- SỬA TỈNH THÀNH ---
    $app->router('/province-edit/{id}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting,$template) {
        $id = $vars['id'] ?? 0;
        $vars['title'] = $jatbi->lang("Sửa Tỉnh thành");
        $data = $app->get("province_new", "*", ["id" => $id, "deleted" => 0]);
        if (!$data) {
            echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
            return;
        }

        if ($app->method() === 'GET') {
            $vars['data'] = $data;
            echo $app->render($template . '/areas/province-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            if (empty($_POST['name'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng không để trống")]);
                return;
            }
            $update = ["name" => $app->xss($_POST['name']), "status" => $app->xss($_POST['status'])];
            $app->update("province_new", $update, ["id" => $id]);
            $jatbi->logs('province_new', 'edit', $update);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công"), 'url' => 'auto']);
        }
    })->setPermissions(['province.edit']);

    $app->router("/province-status/{id}", 'POST', function ($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $data = $app->get("province_new", "*", ["id" => $vars['id'], "deleted" => 0]);
        if ($data > 1) {
            if ($data > 1) {
                if ($data['status'] === 'A') {
                    $status = "D";
                } elseif ($data['status'] === 'D') {
                    $status = "A";
                }
                $app->update("province_new", ["status" => $status], ["id" => $data['id']]);
                $jatbi->logs('province_new', 'province-status', $data);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại"),]);
            }
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['province.edit']);


    $app->router("/province-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting,$template) {
        $vars['title'] = $jatbi->lang("Xóa thành phố");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("province_new", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("province_new", ["deleted" => 1], ["id" => $data['id']]);
                    $name[] = $data['name'];
                }
                $jatbi->logs('province_new', 'province-deleted', $datas);
                $jatbi->trash('/areas/province-restore', "Xóa thành phố: " . implode(', ', $name), ["database" => 'sources', "data" => $boxid]);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['province.deleted']);


    $app->router("/district", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting,$template) {
        $vars['title'] = $jatbi->lang("Quận huyện");
        if ($app->method() === 'GET') {
            $provinces_db = $app->select("province_new", ['id(value)', 'name(text)'], ['deleted' => 0, 'status' => 'A']);

            $vars['provinces'] = array_merge(
                [['value' => '', 'text' => $jatbi->lang('Tất cả')]],
                $provinces_db
            );
            echo $app->render($template . '/areas/district.html', $vars);
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
            $province = isset($_POST['province']) ? $_POST['province'] : '';

            // Điều kiện lọc
            $where = [
                "AND" => [
                    "OR" => [
                        "district_new.name[~]" => $searchValue,
                        "province_new.name[~]" => $searchValue,
                    ],
                    "district_new.status[<>]" => $status,
                    "district_new.deleted" => 0,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];

            if (!empty($province)) {
                $where['AND']['district_new.province'] = $province; // lọc theo tỉnh thành
            }
            // Đếm tổng số bản ghi
            $count = $app->count(
                "district_new",
                [
                    "[>]province_new" => ["province" => "id"],
                ],
                'district_new.id',
                [
                    "AND" => $where['AND'],
                ]
            );

            // Lấy dữ liệu phiếu giảm giá
            $datas = [];
            $app->select("district_new", [
                "[>]province_new" => ["province" => "id"],
            ], [
                "district_new.id",
                "district_new.name",
                "district_new.status",
                "province_new.name (province_name)"
            ], $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "name" => $data['name'],
                    "province" => $data['province_name'] ?? '',
                    "status" => $app->component("status", [
                        "url" => "/areas/district-status/" . $data['id'],
                        "data" => $data['status'],
                        "permission" => ['district.edit']
                    ]),
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['district.edit'],
                                'action' => ['data-url' => '/areas/district-edit/' . $data['id'], 'data-action' => 'modal']
                            ],
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
    })->setPermissions(['district']);

    // --- THÊM QUẬN HUYỆN ---
    $app->router('/district-add', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting,$template) {
        $vars['title'] = $jatbi->lang("Thêm Quận huyện");
        if ($app->method() === 'GET') {
            $vars['data'] = ['status' => 'A'];
            $vars['provinces'] = $app->select("province_new", ["id(value)", "name(text)"], ["deleted" => 0, "status" => "A"]);
            echo $app->render($template . '/areas/district-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            if (empty($_POST['name']) || empty($_POST['province'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng không để trống")]);
                return;
            }
            $insert = ["name" => $app->xss($_POST['name']), "province" => $app->xss($_POST['province']), "status" => $app->xss($_POST['status'])];
            $app->insert("district_new", $insert);
            $jatbi->logs('district_new', 'add', $insert);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Thêm mới thành công"), 'url' => 'auto']);
        }
    })->setPermissions(['district.add']);

    // --- SỬA QUẬN HUYỆN ---
    $app->router('/district-edit/{id}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting,$template) {
        $id = $vars['id'] ?? 0;
        $vars['title'] = $jatbi->lang("Sửa Quận huyện");
        $data = $app->get("district_new", "*", ["id" => $id, "deleted" => 0]);
        if (!$data) {
            echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
            return;
        }

        if ($app->method() === 'GET') {
            $vars['data'] = $data;
            $vars['provinces'] = $app->select("province_new", ["id(value)", "name(text)"], ["deleted" => 0, "status" => "A"]);
            echo $app->render($template . '/areas/district-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            if (empty($_POST['name']) || empty($_POST['province'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng không để trống")]);
                return;
            }
            $update = ["name" => $app->xss($_POST['name']), "province" => $app->xss($_POST['province']), "status" => $app->xss($_POST['status'])];
            $app->update("district_new", $update, ["id" => $id]);
            $jatbi->logs('district_new', 'edit', $update);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công"), 'url' => 'auto']);
        }
    })->setPermissions(['district.edit']);

    // --- XÓA QUẬN HUYỆN ---
    $app->router("/district-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting,$template) {
        $vars['title'] = $jatbi->lang("Xóa quận huyện");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("district_new", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("district_new", ["deleted" => 1], ["id" => $data['id']]);
                    $name[] = $data['name'];
                }
                $jatbi->logs('district_new', 'district-deleted', $datas);
                $jatbi->trash('/areas/district-restore', "Xóa thành phố: " . implode(', ', $name), ["database" => 'district', "data" => $boxid]);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['district.deleted']);

    $app->router("/district-status/{id}", 'POST', function ($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $data = $app->get("district_new", "*", ["id" => $vars['id'], "deleted" => 0]);
        if ($data > 1) {
            if ($data > 1) {
                if ($data['status'] === 'A') {
                    $status = "D";
                } elseif ($data['status'] === 'D') {
                    $status = "A";
                }
                $app->update("district_new", ["status" => $status], ["id" => $data['id']]);
                $jatbi->logs('district_new', 'district-status', $data);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại"),]);
            }
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['district.edit']);


    
})->middleware('login');
