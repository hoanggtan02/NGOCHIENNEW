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
$app->group($setting['manager'] . "/areas", function ($app) use ($jatbi, $setting,$template) {
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
                        "province.name[~]" => $searchValue,
                    ],
                    "province.deleted" => 0,
                    "province.status[<>]" => $status,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];

            $count = $app->count("province", [
                "AND" => $where['AND'],
            ]);

            // Lấy dữ liệu phiếu giảm giá
            $datas = [];
            $app->select("province", ["id", "name", "status"], $where, function ($data) use (&$datas, $jatbi, $app) {
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
            $app->insert("province", $insert);
            $jatbi->logs('province', 'add', $insert);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Thêm mới thành công"), 'url' => 'auto']);
        }
    })->setPermissions(['province.add']);

    // --- SỬA TỈNH THÀNH ---
    $app->router('/province-edit/{id}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting,$template) {
        $id = $vars['id'] ?? 0;
        $vars['title'] = $jatbi->lang("Sửa Tỉnh thành");
        $data = $app->get("province", "*", ["id" => $id, "deleted" => 0]);
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
            $app->update("province", $update, ["id" => $id]);
            $jatbi->logs('province', 'edit', $update);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công"), 'url' => 'auto']);
        }
    })->setPermissions(['province.edit']);

    $app->router("/province-status/{id}", 'POST', function ($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $data = $app->get("province", "*", ["id" => $vars['id'], "deleted" => 0]);
        if ($data > 1) {
            if ($data > 1) {
                if ($data['status'] === 'A') {
                    $status = "D";
                } elseif ($data['status'] === 'D') {
                    $status = "A";
                }
                $app->update("province", ["status" => $status], ["id" => $data['id']]);
                $jatbi->logs('province', 'province-status', $data);
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
            $datas = $app->select("province", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("province", ["deleted" => 1], ["id" => $data['id']]);
                    $name[] = $data['name'];
                }
                $jatbi->logs('province', 'province-deleted', $datas);
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
            $provinces_db = $app->select("province", ['id(value)', 'name(text)'], ['deleted' => 0, 'status' => 'A']);

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
                        "district.name[~]" => $searchValue,
                        "province.name[~]" => $searchValue,
                    ],
                    "district.status[<>]" => $status,
                    "district.deleted" => 0,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];

            if (!empty($province)) {
                $where['AND']['district.province'] = $province; // lọc theo tỉnh thành
            }
            // Đếm tổng số bản ghi
            $count = $app->count(
                "district",
                [
                    "[>]province" => ["province" => "id"],
                ],
                'district.id',
                [
                    "AND" => $where['AND'],
                ]
            );

            // Lấy dữ liệu phiếu giảm giá
            $datas = [];
            $app->select("district", [
                "[>]province" => ["province" => "id"],
            ], [
                "district.id",
                "district.name",
                "district.status",
                "province.name (province_name)"
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
            $vars['provinces'] = $app->select("province", ["id(value)", "name(text)"], ["deleted" => 0, "status" => "A"]);
            echo $app->render($template . '/areas/district-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            if (empty($_POST['name']) || empty($_POST['province'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng không để trống")]);
                return;
            }
            $insert = ["name" => $app->xss($_POST['name']), "province" => $app->xss($_POST['province']), "status" => $app->xss($_POST['status'])];
            $app->insert("district", $insert);
            $jatbi->logs('district', 'add', $insert);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Thêm mới thành công"), 'url' => 'auto']);
        }
    })->setPermissions(['district.add']);

    // --- SỬA QUẬN HUYỆN ---
    $app->router('/district-edit/{id}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting,$template) {
        $id = $vars['id'] ?? 0;
        $vars['title'] = $jatbi->lang("Sửa Quận huyện");
        $data = $app->get("district", "*", ["id" => $id, "deleted" => 0]);
        if (!$data) {
            echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
            return;
        }

        if ($app->method() === 'GET') {
            $vars['data'] = $data;
            $vars['provinces'] = $app->select("province", ["id(value)", "name(text)"], ["deleted" => 0, "status" => "A"]);
            echo $app->render($template . '/areas/district-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            if (empty($_POST['name']) || empty($_POST['province'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng không để trống")]);
                return;
            }
            $update = ["name" => $app->xss($_POST['name']), "province" => $app->xss($_POST['province']), "status" => $app->xss($_POST['status'])];
            $app->update("district", $update, ["id" => $id]);
            $jatbi->logs('district', 'edit', $update);
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
            $datas = $app->select("district", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("district", ["deleted" => 1], ["id" => $data['id']]);
                    $name[] = $data['name'];
                }
                $jatbi->logs('district', 'district-deleted', $datas);
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
        $data = $app->get("district", "*", ["id" => $vars['id'], "deleted" => 0]);
        if ($data > 1) {
            if ($data > 1) {
                if ($data['status'] === 'A') {
                    $status = "D";
                } elseif ($data['status'] === 'D') {
                    $status = "A";
                }
                $app->update("district", ["status" => $status], ["id" => $data['id']]);
                $jatbi->logs('district', 'district-status', $data);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại"),]);
            }
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['district.edit']);

    $app->router("/ward", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting,$template) {
        $vars['title'] = $jatbi->lang("Phường xã");
        if ($app->method() === 'GET') {

            $provinces_db = $app->select("province", ['id(value)', 'name(text)'], ['deleted' => 0, 'status' => 'A']);
            $vars['provinces'] = array_merge(
                [['value' => '', 'text' => $jatbi->lang('Tất cả')]],
                $provinces_db
            );
            $districts_db = $app->select("district", ['id(value)', 'name(text)'], [
                'deleted' => 0,
                'status' => 'A',
            ]);

            $vars['districts'] = array_merge(
                [['value' => '', 'text' => $jatbi->lang('Tất cả')]],
                $districts_db
            );

            echo $app->render($template . '/areas/ward.html', $vars);
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
            $district = isset($_POST['district']) ? $_POST['district'] : '';
            $province = isset($_POST['province']) ? $_POST['province'] : '';

            // Điều kiện lọc
            $where = [
                "AND" => [
                    "OR" => [
                        "ward.name[~]" => $searchValue,
                        "province.name[~]" => $searchValue,
                        "district.name[~]" => $searchValue,
                    ],
                    "ward.status[<>]" => $status,
                    "ward.deleted" => 0,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];
            if (!empty($district)) {
                $where['AND']['ward.district'] = $district;
            }
            if (!empty($province)) {
                $where['AND']['ward.province'] = $province;
            }
            // Đếm tổng số bản ghi
            $count = $app->count(
                "ward",
                [
                    "[>]district" => ["district" => "id"],
                    "[>]province" => ["province" => "id"],
                ],
                'ward.id',
                [
                    "AND" => $where['AND'],
                ]
            );

            // Lấy dữ liệu phiếu giảm giá
            $datas = [];
            $app->select(
                "ward",
                [
                    "[>]district" => ["district" => "id"],
                    "[>]province" => ["province" => "id"],
                ],
                [
                    "ward.id",
                    "ward.name",
                    "ward.status",
                    "province.name (province_name)",
                    "district.name (district_name)"
                ],
                $where,
                function ($data) use (&$datas, $jatbi, $app) {
                    $datas[] = [
                        "checkbox" => $app->component("box", ["data" => $data['id']]),
                        "name" => $data['name'],
                        "district" => $data['district_name'] ?? '',
                        "province" => $data['province_name'] ?? '',
                        "status" => $app->component("status", [
                            "url" => "/areas/ward-status/" . $data['id'],
                            "data" => $data['status'],
                            "permission" => ['district.edit']
                        ]),
                        "action" => $app->component("action", [
                            "button" => [
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Sửa"),
                                    'permission' => ['ward.edit'],
                                    'action' => ['data-url' => '/areas/ward-edit/' . $data['id'], 'data-action' => 'modal']
                                ]
                            ]
                        ]),
                    ];
                }
            );

            // Trả về dữ liệu JSON
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas ?? [],
            ]);
        }
    })->setPermissions(['ward']);


    $app->router('/ward-add', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting,$template) {
        $vars['title'] = $jatbi->lang("Thêm Phường/Xã");
        if ($app->method() === 'GET') {
            $vars['data'] = ['status' => 'A'];
            $vars['province'] = $app->select("province", ["id(value)", "name(text)"], ["deleted" => 0, "status" => "A"]);
            $vars['district'] = $app->select("district", ["id(value)", "name(text)"], ["deleted" => 0, "status" => "A"]);
            echo $app->render($template . '/areas/ward-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            if (empty($_POST['name']) || empty($_POST['province']) || empty($_POST['district'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng không để trống")]);
                return;
            }
            $insert = [
                "name" => $app->xss($_POST['name']),
                "province" => $app->xss($_POST['province']),
                "district" => $app->xss($_POST['district']),
                "status" => $app->xss($_POST['status'])
            ];
            $app->insert("ward", $insert);
            $jatbi->logs('ward', 'add', $insert);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Thêm mới thành công"), 'url' => 'auto']);
        }
    })->setPermissions(['ward.add']);

    $app->router('/ward-edit/{id}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting,$template) {
        $id = $vars['id'] ?? 0;
        $vars['title'] = $jatbi->lang("Sửa Phường/Xã");

        $data = $app->get("ward", "*", ["id" => $id, "deleted" => 0]);
        if (!$data) {
            echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
            return;
        }
        if ($app->method() === 'GET') {
            $vars['data'] = $data;
            $vars['province'] = $app->select("province", ["id(value)", "name(text)"], ["deleted" => 0, "status" => "A"]);

            $vars['district'] = [];
            if (!empty($data['province'])) {
                $vars['district'] = $app->select("district", ["id(value)", "name(text)"], [
                    "deleted" => 0,
                    "status" => "A",
                    "province" => $data['province']
                ]);
            }

            echo $app->render($template . '/areas/ward-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            if (empty($_POST['name']) || empty($_POST['province']) || empty($_POST['district'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng không để trống")]);
                return;
            }

            $update = [
                "name" => $app->xss($_POST['name']),
                "province" => $app->xss($_POST['province']),
                "district" => $app->xss($_POST['district']),
                "status" => $app->xss($_POST['status'])
            ];

            $app->update("ward", $update, ["id" => $id]);
            $jatbi->logs('ward', 'edit', $update);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công"), 'url' => 'auto']);
        }
    })->setPermissions(['ward.edit']);


    // --- XÓA QUẬN HUYỆN ---
    $app->router("/ward-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa phường xã");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("ward", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("ward", ["deleted" => 1], ["id" => $data['id']]);
                    $name[] = $data['name'];
                }
                $jatbi->logs('ward', 'ward-deleted', $datas);
                $jatbi->trash('/areas/ward-restore', "Xóa phường xã: " . implode(', ', $name), ["database" => 'ward', "data" => $boxid]);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['ward.deleted']);

    $app->router("/ward-status/{id}", 'POST', function ($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $data = $app->get("ward", "*", ["id" => $vars['id'], "deleted" => 0]);
        if ($data > 1) {
            if ($data > 1) {
                if ($data['status'] === 'A') {
                    $status = "D";
                } elseif ($data['status'] === 'D') {
                    $status = "A";
                }
                $app->update("ward", ["status" => $status], ["id" => $data['id']]);
                $jatbi->logs('ward', 'ward-status', $data);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại"),]);
            }
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['district.edit']);
    
})->middleware('login');
