
<?php
if (!defined('ECLO')) die("Hacking attempt");
use ECLO\App;
$template = __DIR__.'/../templates';
$jatbi = $app->getValueData('jatbi');
$common = $jatbi->getPluginCommon('io.eclo.proposal');
$setting = $app->getValueData('setting');
$app->group($setting['manager'] . "/stores", function ($app) use ($jatbi, $setting,  $template) {

$app->router('/stores', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {
    $jatbi->permission('stores');
    $vars['title'] = $jatbi->lang("Cửa hàng");

    if ($app->method() === 'GET') {
        $storetype = $app->select("stores_types", ['id (value)', 'name (text)'], ['status' => 'A', 'deleted' => 0]);
        $vars['storestypesList']= array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $storetype);
        echo $app->render($template . '/stores/stores.html', $vars);
    } elseif ($app->method() === 'POST') {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);

        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
        $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
        $statusValue = isset($_POST['status']) ? $_POST['status'] : '';
        $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
        $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
        $storestypesFilter = isset($_POST['stores_types']) ? $_POST['stores_types'] : '';

        $where = [
            "AND" => [
                "stores.deleted" => 0,
            ],
            "LIMIT" => [$start, $length],
            "ORDER" => [$orderName => strtoupper($orderDir)]
        ];

        // Apply search filter
        if ($searchValue != '') {
            $where['AND']['OR'] = [
                'stores.name[~]' => $searchValue,
                'stores.code[~]' => $searchValue,
                'stores.phone[~]' => $searchValue,
                'stores.email[~]' => $searchValue,
                'stores.address[~]' => $searchValue,
            ];
        }

        // Apply status filter
        if ($statusValue != '') {
            $where['AND']['stores.status'] = $statusValue;
        }

        // Apply stores_types filter only if valid
        if ($storestypesFilter != '' && $storestypesFilter != 'all' && is_numeric($storestypesFilter) && $storestypesFilter > 0) {
            $where['AND']['stores.type'] = $storestypesFilter;
        }

        // Count query with filters
        $countWhere = [
            "AND" => array_merge(
                ["stores.deleted" => 0],
                $searchValue != '' ? [
                    "OR" => [
                        'stores.name[~]' => $searchValue,
                        'stores.code[~]' => $searchValue,
                        'stores.phone[~]' => $searchValue,
                        'stores.email[~]' => $searchValue,
                        'stores.address[~]' => $searchValue,
                    ]
                ] : [],
                $statusValue != '' ? ["stores.status" => $statusValue] : [],
                $storestypesFilter != '' && $storestypesFilter != 'all' && is_numeric($storestypesFilter) && $storestypesFilter > 0 ? ["stores.type" => $storestypesFilter] : []
            )
        ];
        $count = $app->count("stores", $countWhere);

        $datas = [];
        $app->select("stores", [
            '[>]stores_types' => ['type' => 'id']
        ], [
            'stores.id',
            'stores_types.name(type_name)',
            'stores.code',
            'stores.name',
            'stores.phone',
            'stores.email',
            'stores.address',
            'stores.date',
            'stores.status'
        ], $where, function ($data) use (&$datas, $jatbi, $app) {
            $datas[] = [
                "checkbox" => (string) ($app->component("box", ["data" => $data['id'] ?? '']) ?? '<input type="checkbox">'),
                "type" => (string) ($data['type_name'] ?? 'Không xác định'),
                "code" => (string) ($data['code'] ?? ''),
                "name" => (string) ($data['name'] ?? ''),
                "phone" => (string) ($data['phone'] ?? ''),
                "email" => (string) ($data['email'] ?? ''),
                "address" => (string) ($data['address'] ?? ''),
                "date" => $jatbi->datetime($data['date'] ?? ''),
                "status" => (string) ($app->component("status", [
                    "url" => "/stores/stores-status/" . ($data['id'] ?? ''),
                    "data" => $data['status'] ?? '',
                    "permission" => ['stores.edit']
                ]) ?? '<span>' . ($data['status'] ?? '') . '</span>'),
                "action" => (string) ($app->component("action", [
                    "button" => [
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Sửa"),
                            'permission' => ['stores.edit'],
                            'action' => ['data-url' => '/stores/stores-edit/' . ($data['id'] ?? ''), 'data-action' => 'modal']
                        ],
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Xóa"),
                            'permission' => ['stores.deleted'],
                            'action' => ['data-url' => '/stores/stores-deleted?box=' . ($data['id'] ?? ''), 'data-action' => 'modal']
                        ],
                    ]
                ]) )
            ];
        });

        echo json_encode([
            "draw" => $draw,
            "recordsTotal" => $count,
            "recordsFiltered" => $count,
            "data" => $datas ?? []
        ]);
    }
})->setPermissions(['stores']);

$app->router("/stores-add", ['GET', 'POST'], function($vars) use ($app, $jatbi, $setting, $template) {
    $vars['title'] = $jatbi->lang("Thêm cửa hàng");

    if ($app->method() === 'GET') {
        $vars['data'] = []; // Không có dữ liệu ban đầu cho thêm mới
        $vars['store_types'] = $app->select("stores_types", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A"]);
        echo $app->render($template. '/stores/stores-post.html', $vars, $jatbi->ajax());
    } elseif ($app->method() === 'POST') {
        $app->header([
            'Content-Type' => 'application/json',
        ]);

        $error = [];
        if ($app->xss($_POST['name']) == '' || $app->xss($_POST['type']) == '') {
            $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")];
        }

        if (empty($error)) {
            $insert = [
                "type" => $app->xss($_POST['type']),
                "name" => $app->xss($_POST['name']),
                "phone" => $app->xss($_POST['phone']),
                "email" => $app->xss($_POST['email']),
                "code" => $app->xss($_POST['code']),
                "address" => $app->xss($_POST['address']),
                "notes" => $app->xss($_POST['notes']),
                "status" => $app->xss($_POST['status']),
                "mst" => $app->xss($_POST['mst']),
                "name_company" => $app->xss($_POST['name_company']),
                "password" => $app->xss($_POST['password']),
                "invoiceSeries" => $app->xss($_POST['invoiceSeries']),
                "templateCode" => $app->xss($_POST['templateCode']),
                "date" => date("Y-m-d H:i:s"),
            ];
            $app->insert("stores", $insert);
            $jatbi->logs('stores', 'add', $insert);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Thêm thành công"), "url" => "/stores"]);
        } else {
            echo json_encode($error);
        }
    }
})->setPermissions(['stores.add']);

 



$app->router("/stores-edit/{id}", ['GET', 'POST'], function($vars) use ($app, $jatbi, $setting, $template) {
    $vars['title'] = $jatbi->lang("Sửa cửa hàng");

    if ($app->method() === 'GET') {
        $vars['data'] = $app->select("stores", [
            "id",
            "type",
            "name",
            "phone",
            "email",
            "code",
            "address",
            "notes",
            "status",
            "mst",
            "name_company",
            "password",
            "invoiceSeries",
            "templateCode",
            "date"
        ], [
            "AND" => [
                "id" => $vars['id'],
                "deleted" => 0
            ],
            "LIMIT" => 1
        ]);

        if (!empty($vars['data'])) {
            $vars['data'] = $vars['data'][0];
            $vars['store_types'] = $app->select("stores_types", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A"]);
            echo $app->render($template. '/stores/stores-post.html', $vars, $jatbi->ajax());
        } else {
            echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
        }
    } elseif ($app->method() === 'POST') {
        $app->header([
            'Content-Type' => 'application/json',
        ]);

        $data = $app->select("stores", [
            "id",
            "type",
            "name",
            "phone",
            "email",
            "code",
            "address",
            "notes",
            "status",
            "mst",
            "name_company",
            "password",
            "invoiceSeries",
            "templateCode",
            "date"
        ], [
            "AND" => [
                "id" => $vars['id'],
                "deleted" => 0
            ],
            "LIMIT" => 1
        ]);

        if (!empty($data)) {
            $data = $data[0];
            $error = [];
            if ($app->xss($_POST['name']) == '' || $app->xss($_POST['type']) == '') {
                $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")];
            }

            if (empty($error)) {
                $insert = [
                    "type" => $app->xss($_POST['type']),
                    "name" => $app->xss($_POST['name']),
                    "phone" => $app->xss($_POST['phone']),
                    "email" => $app->xss($_POST['email']),
                    "code" => $app->xss($_POST['code']),
                    "address" => $app->xss($_POST['address']),
                    "notes" => $app->xss($_POST['notes']),
                    "status" => $app->xss($_POST['status']),
                    "mst" => $app->xss($_POST['mst']),
                    "name_company" => $app->xss($_POST['name_company']),
                    "password" => $app->xss($_POST['password']),
                    "invoiceSeries" => $app->xss($_POST['invoiceSeries']),
                    "templateCode" => $app->xss($_POST['templateCode']),
                    "date" => date("Y-m-d H:i:s"),
                ];
                $app->update("stores", $insert, ["id" => $data['id']]);
                $jatbi->logs('stores', 'edit', $insert);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công"), "url" => $_SERVER['HTTP_REFERER']]);
            } else {
                echo json_encode($error);
            }
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    }
})->setPermissions(['stores.edit']);

 $app->router("/stores-status/{id}", 'POST', function ($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $data = $app->get("stores", "*", ["id" => $vars['id'], "deleted" => 0]);
        if ($data > 1) {
            if ($data > 1) {
                if ($data['status'] === 'A') {
                    $status = "D";
                } elseif ($data['status'] === 'D') {
                    $status = "A";
                }
                $app->update("stores", ["status" => $status], ["id" => $data['id']]);
                $jatbi->logs('stores', 'stores-stores-status', $data);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại"),]);
            }
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['stores.edit']);


$app->router("/stores-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Xóa cửa hàng");

    if ($app->method() === 'GET') {
        echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
    } elseif ($app->method() === 'POST') {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $boxid = explode(',', $app->xss($_GET['box']));
        $datas = $app->select("stores", "*", ["id" => $boxid, "deleted" => 0]);
        if (count($datas) > 0) {
            foreach ($datas as $data) {
                $app->update("stores", ["deleted" => 1], ["id" => $data['id']]);
                $name[] = $data['name'];
            }
            $jatbi->logs('stores', 'stores-deleted', $datas);
            $jatbi->trash('/stores/stores-restore', "Xóa cửa hàng: " . implode(', ', $name), ["database" => 'stores', "data" => $boxid]);
            echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
        } else {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xảy ra")]);
        }
    }
})->setPermissions(['stores.deleted']);






//branch
$app->router('/branch', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {
    $jatbi->permission('branch');
    $vars['title'] = $jatbi->lang("Quầy hàng");

    if ($app->method() === 'GET') {
        $store = $app->select("stores", ['id (value)', 'name(text)'], ['status' => 'A', 'deleted' => 0]);
        $vars['storesList']= array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $store);
        echo $app->render($template. '/stores/branch.html', $vars);
    } elseif ($app->method() === 'POST') {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);

        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
        $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
        $statusValue = isset($_POST['status']) ? $_POST['status'] : '';
        $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
        $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
        $storeFilter = isset($_POST['stores']) ? $_POST['stores'] : ''; 

        $where = [
            "AND" => [
                "branch.deleted" => 0,
            ],
            "LIMIT" => [$start, $length],
            "ORDER" => [$orderName => strtoupper($orderDir)]
        ];

        if ($searchValue != '') {
            $where['AND']['OR'] = [
                'branch.name[~]' => $searchValue,
                'branch.code[~]' => $searchValue,
                'stores.name[~]' => $searchValue,
            ];
        }

        if ($statusValue != '') {
            $where['AND']['branch.status'] = $statusValue;
        }

        if ($storeFilter != '' && $storeFilter != 'all') { 
            $where['AND']['branch.stores'] = $storeFilter; 
        }

        $countWhere = [
            "AND" => array_merge(
                ["branch.deleted" => 0],
                $searchValue != '' ? ["OR" => [
                    'branch.name[~]' => $searchValue,
                    'branch.code[~]' => $searchValue,
                    'stores.name[~]' => $searchValue,
                ]] : [],
                $statusValue != '' ? ["branch.status" => $statusValue] : [],
                $storeFilter != '' && $storeFilter != 'all' ? ["branch.stores" => $storeFilter] : []
            )
        ];
        $count = $app->count("branch", $countWhere);

        $datas = [];
        $app->select("branch", [
            '[>]stores' => ['stores' => 'id']
        ], [
            'branch.id',
            'branch.code',
            'branch.name',
            'stores.name(store_name)',
            'branch.status'
        ], $where, function ($data) use (&$datas, $jatbi, $app) {
            $datas[] = [
                "checkbox" => (string) ($app->component("box", ["data" => $data['id'] ?? '']) ?? '<input type="checkbox">'),
                "type" => (string) ($data['store_name'] ?? 'Không xác định'),
                "code" => (string) ($data['code'] ?? ''),
                "name" => (string) ($data['name'] ?? ''),

                 "status" => $app->component("status",["url"=>"/stores/branch-status/".$data['id'],"data"=>$data['status'],"permission"=>['branch.edit']]),
                "action" => (string) ($app->component("action", [
                    "button" => [
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Sửa"),
                            'permission' => ['branch.edit'],
                            'action' => ['data-url' => '/stores/branch-edit/' . ($data['id'] ?? ''), 'data-action' => 'modal']
                        ],
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Xóa"),
                            'permission' => ['branch.deleted'],
                            'action' => ['data-url' => '/stores/branch-deleted?box=' . ($data['id'] ?? ''), 'data-action' => 'modal']
                        ],
                    ]
                ]))
            ];
        });

        echo json_encode(
            [
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas
            ],

        );
    }
})->setPermissions(['branch']);


$app->router("/branch-add", ['GET', 'POST'], function($vars) use ($app, $jatbi, $setting,$template) {
    $vars['title'] = $jatbi->lang("Thêm quầy hàng");

    if ($app->method() === 'GET') {
        $vars['data'] = []; 
        $vars['stores'] = $app->select("stores", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A"]);
        echo $app->render($template. '/stores/branch-post.html', $vars, $jatbi->ajax());
    } elseif ($app->method() === 'POST') {
        $app->header([
            'Content-Type' => 'application/json',
        ]);

        $error = [];
        if ($app->xss($_POST['name']) == '' || $app->xss($_POST['stores']) == '') {
            $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")];
        }

        if (empty($error)) {
            $insert = [
                "stores" => $app->xss($_POST['stores']),
                "name" => $app->xss($_POST['name']),
                "code" => $app->xss($_POST['code']),
                "notes" => $app->xss($_POST['notes']),
                "status" => $app->xss($_POST['status']),
            ];
            $app->insert("branch", $insert);
            $jatbi->logs('branch', 'add', $insert);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Thêm thành công"),"url" => $_SERVER['HTTP_REFERER']]);
        } else {
            echo json_encode($error);
        }
    }
})->setPermissions(['branch.add']);

$app->router("/branch-edit/{id}", ['GET', 'POST'], function($vars) use ($app, $jatbi, $setting, $template) {
    $vars['title'] = $jatbi->lang("Sửa quầy hàng");

    if ($app->method() === 'GET') {
        $vars['data'] = $app->select("branch", [
            "id",
            "stores",
            "name",
            "code",
            "notes",
            "status"
        ], [
            "AND" => [
                "id" => $vars['id'],
                "deleted" => 0
            ],
            "LIMIT" => 1
        ]);

        if (!empty($vars['data'])) {
            $vars['data'] = $vars['data'][0];
            $vars['stores'] = $app->select("stores", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A"]);
            echo $app->render($template. '/stores/branch-post.html', $vars, $jatbi->ajax());
        } else {
            echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
        }
    } elseif ($app->method() === 'POST') {
        $app->header([
            'Content-Type' => 'application/json',
        ]);

        $data = $app->select("branch", [
            "id",
            "stores",
            "name",
            "code",
            "notes",
            "status"
        ], [
            "AND" => [
                "id" => $vars['id'],
                "deleted" => 0
            ],
            "LIMIT" => 1
        ]);

        if (!empty($data)) {
            $data = $data[0];
            $error = [];
            if ($app->xss($_POST['name']) == '' || $app->xss($_POST['stores']) == '') {
                $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")];
            }

            if (empty($error)) {
                $insert = [
                    "stores" => $app->xss($_POST['stores']),
                    "name" => $app->xss($_POST['name']),
                    "code" => $app->xss($_POST['code']),
                    "notes" => $app->xss($_POST['notes']),
                    "status" => $app->xss($_POST['status']),
                ];
                $app->update("branch", $insert, ["id" => $data['id']]);
                $jatbi->logs('branch', 'edit', $insert);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công"), "url" => $_SERVER['HTTP_REFERER']]);
            } else {
                echo json_encode($error);
            }
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    }
})->setPermissions(['branch.edit']);

$app->router("/branch-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Xóa quầy hàng");

    if ($app->method() === 'GET') {
        echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
    } elseif ($app->method() === 'POST') {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $boxid = explode(',', $app->xss($_GET['box']));
        $datas = $app->select("branch", "*", ["id" => $boxid, "deleted" => 0]);
        if (count($datas) > 0) {
            foreach ($datas as $data) {
                $app->update("branch", ["deleted" => 1], ["id" => $data['id']]);
                $name[] = $data['name'];
            }
            $jatbi->logs('branch', 'branch-deleted', $datas);
            $jatbi->trash('/stores/branch-restore', "Xóa cửa hàng: " . implode(', ', $name), ["database" => 'stores', "data" => $boxid]);
            echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
        } else {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xảy ra")]);
        }
    }
})->setPermissions(['branch.deleted']);

 $app->router("/branch-status/{id}", 'POST', function ($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $data = $app->get("branch", "*", ["id" => $vars['id'], "deleted" => 0]);
        if ($data > 1) {
            if ($data > 1) {
                if ($data['status'] === 'A') {
                    $status = "D";
                } elseif ($data['status'] === 'D') {
                    $status = "A";
                }
                $app->update("branch", ["status" => $status], ["id" => $data['id']]);
                $jatbi->logs('branch', 'stores-branch-status', $data);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại"),]);
            }
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['branch.edit']);




//stores_types

    $app->router('/stores-types', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {
        $jatbi->permission('stores-types');
        $vars['title'] = $jatbi->lang("Loại cửa hàng");

        if ($app->method() === 'GET') {
            echo $app->render($template. '/stores/stores-types.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $statusValue = isset($_POST['status']) ? $_POST['status'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

            $where = [
                "AND" => [
                    "stores_types.deleted" => 0,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)]
            ];

            if ($searchValue != '') {
                $where['AND']['OR'] = [
                    'stores_types.name[~]' => $searchValue,
                    'stores_types.notes[~]' => $searchValue,
                ];
            }

            if ($statusValue != '') {
                $where['AND']['stores_types.status'] = $statusValue;
            }

            $countWhere = [
                "AND" => array_merge(
                    ["stores_types.deleted" => 0],
                    $searchValue != '' ? ["OR" => [
                        'stores_types.name[~]' => $searchValue,
                        'stores_types.notes[~]' => $searchValue,
                    ]] : [],
                    $statusValue != '' ? ["stores_types.status" => $statusValue] : []
                )
            ];
            $count = $app->count("stores_types", $countWhere);

            $datas = [];
            $app->select("stores_types", [
                'stores_types.id',
                'stores_types.name',
                'stores_types.notes',
                'stores_types.status'
            ], $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => (string) ($app->component("box", ["data" => $data['id'] ?? '']) ?? '<input type="checkbox">'),
                    "type" => (string) ($data['name'] ?? ''),
                    "notes" => (string) ($data['notes'] ?? ''),
                   "status" => $app->component("status", ["url" => "/stores/stores-types-status/" . $data['id'], "data" => $data['status'], "permission" => ['stores-types.edit']]),
                    "action" =>  ($app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['stores-types.edit'],
                                'action' => ['data-url' => '/stores/stores-types-edit/' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'permission' => ['stores-types.deleted'],
                                'action' => ['data-url' => '/stores/stores-types-deleted?box=' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                        ]
                    ]))
                ];
            });

            echo json_encode(
                [
                    "draw" => $draw,
                    "recordsTotal" => $count,
                    "recordsFiltered" => $count,
                    "data" => $datas ?? []
                ],

            );

        }
    })->setPermissions(['stores-types']);
    


$app->router("/stores-types-add", ['GET', 'POST'], function($vars) use ($app, $jatbi, $setting, $template) {
    $vars['title'] = $jatbi->lang("Thêm loại cửa hàng");

    if ($app->method() === 'GET') {
        $vars['data'] = []; // Không có dữ liệu ban đầu cho thêm mới
        echo $app->render($template. '/stores/stores-type-post.html', $vars, $jatbi->ajax());
    } elseif ($app->method() === 'POST') {
        $app->header([
            'Content-Type' => 'application/json',
        ]);

        $error = [];
        if ($app->xss($_POST['name']) == '') {
            $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống tên")];
        }

        if (empty($error)) {
            $insert = [
                "name" => $app->xss($_POST['name']),
                "notes" => $app->xss($_POST['notes']),
                "status" => $app->xss($_POST['status']),
            ];
            $app->insert("stores_types", $insert);
            $jatbi->logs('stores_types', 'add', $insert);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Thêm thành công"), "url" => "/stores-types"]);
        } else {
            echo json_encode($error);
        }
    }
})->setPermissions(['stores-types.add']);


    //sửa stores_types
    $app->router("/stores-types-edit/{id}", ['GET', 'POST'], function($vars) use ($app, $jatbi, $setting, $template) {
    $vars['title'] = $jatbi->lang("Sửa loại cửa hàng");

    if ($app->method() === 'GET') {
        $vars['data'] = $app->select("stores_types", [
            "id",
            "name",
            "notes",
            "status"
        ], [
            "AND" => [
                "id" => $vars['id'],
                "deleted" => 0
            ],
            "LIMIT" => 1
        ]);

        if (!empty($vars['data'])) {
            $vars['data'] = $vars['data'][0];
            echo $app->render($template. '/stores/stores-type-post.html', $vars, $jatbi->ajax());
        } else {
            echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
        }
    } elseif ($app->method() === 'POST') {
        $app->header([
            'Content-Type' => 'application/json',
        ]);

        $data = $app->select("stores_types", [
            "id",
            "name",
            "notes",
            "status"
        ], [
            "AND" => [
                "id" => $vars['id'],
                "deleted" => 0
            ],
            "LIMIT" => 1
        ]);

        if (!empty($data)) {
            $data = $data[0];
            $error = [];
            if ($app->xss($_POST['name']) == '') {
                $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")];
            }

            if (empty($error)) {
                $insert = [
                    "name" => $app->xss($_POST['name']),
                    "notes" => $app->xss($_POST['notes']),
                    "status" => $app->xss($_POST['status']),
                ];
                $app->update("stores_types", $insert, ["id" => $data['id']]);
                $jatbi->logs('stores-types', 'edit', $insert);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công"), "url" => $_SERVER['HTTP_REFERER']]);
            } else {
                echo json_encode($error);
            }
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    }



})->setPermissions(['stores-types.edit']);


$app->router("/stores-types-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
    $vars['title'] = $jatbi->lang("Xóa loại cửa hàng");

    if ($app->method() === 'GET') {
        echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
    } elseif ($app->method() === 'POST') {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $boxid = explode(',', $app->xss($_GET['box']));
        $datas = $app->select("stores_types", "*", ["id" => $boxid, "deleted" => 0]);
        if (count($datas) > 0) {
            foreach ($datas as $data) {
                $app->update("stores_types", ["deleted" => 1], ["id" => $data['id']]);
                $name[] = $data['name'];
            }
            $jatbi->logs('stores-types', 'stores-types-deleted', $datas);
            $jatbi->trash('/stores/stores-types-restore', "Xóa cửa hàng: " . implode(', ', $name), ["database" => 'stores', "data" => $boxid]);
            echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
        } else {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xảy ra")]);
        }
    }
})->setPermissions(['stores-types.deleted']);


$app->router("/stores-types-status/{id}", 'POST', function ($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $data = $app->get("stores_types", "*", ["id" => $vars['id'], "deleted" => 0]);
        if ($data > 1) {
            if ($data > 1) {
                if ($data['status'] === 'A') {
                    $status = "D";
                } elseif ($data['status'] === 'D') {
                    $status = "A";
                }
                $app->update("stores_types", ["status" => $status], ["id" => $data['id']]);
                $jatbi->logs('stores-types', 'stores-types-status', $data);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại"),]);
            }
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['stores-types.edit']);

})->middleware('login');

