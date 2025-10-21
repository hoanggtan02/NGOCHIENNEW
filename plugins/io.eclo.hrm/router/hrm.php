<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;



if (!defined(constant_name: 'ECLO'))
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
$app->group($setting['manager'] . "/hrm", function ($app) use ($jatbi, $setting, $accStore, $stores, $template) {

    $app->router('/personnels', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $accStore, $template) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Nhân Viên");
            $vars['offices'] = $app->select("offices", ['id(value)', 'name(text)'], ['status' => 'A', 'deleted' => 0, "ORDER" => ["name" => "ASC"]]);
            $status_type = [
                [
                    'value' => '',
                    'text' => $jatbi->lang('Tất cả'),
                ],
                [
                    'value' => 0,
                    'text' => $jatbi->lang('Còn làm'),
                ],
                [
                    'value' => 1,
                    'text' => $jatbi->lang('Nghỉ làm'),
                ],
            ];
            $vars['status_type'] = $status_type;
            echo $app->render($template . '/hrm/personnels.html', $vars);
        }
        if ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : $setting['site_page'] ?? 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'personnels.id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $status = (isset($_POST['status']) && in_array($_POST['status'], ['A', 'D'])) ? [$_POST['status'], $_POST['status']] : '';
            $offices = isset($_POST['offices']) ? $_POST['offices'] : '';
            $status_type = isset($_POST['status_type']) ? $_POST['status_type'] : '';

            $joins = [
                "[>]offices" => ["office" => "id"],
                "[>]stores" => ["stores" => "id"],
            ];

            $where = [
                "AND" => [
                    "OR" => [
                        'personnels.code[~]' => $searchValue,
                        'personnels.name[~]' => $searchValue,
                        'personnels.phone[~]' => $searchValue,
                        'personnels.email[~]' => $searchValue,
                        'personnels.address[~]' => $searchValue,
                        'personnels.idcode[~]' => $searchValue,
                    ],
                    'personnels.status[<>]' => $status,
                    'personnels.stores' => $accStore,
                    'personnels.deleted' => 0,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];

            if (!empty($offices)) {
                $where['AND']['offices.id'] = $offices;
            }

            if (!empty($status_type)) {
                $where['AND']['personnels.status_type'] = $status_type;
            }
            $count = $app->count("personnels", $joins, ['personnels.id'], $where['AND']);
            $datas = [];

            $app->select("personnels", $joins, [
                'personnels.id',
                'personnels.code',
                'personnels.office',
                'personnels.stores',
                'personnels.name',
                'personnels.phone',
                'personnels.email',
                'personnels.date',
                'personnels.status',
                'personnels.status_type',
                'offices.name(office_name)',
                'stores.name(store_name)',
            ], $where, function ($data) use (&$datas, $jatbi, $app, $offices) {
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "code" => $data['code'],
                    "office_name" => $data['office_name'],
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'email' => $data['email'],
                    'date' => date("d/m/Y", strtotime($data['date'])),
                    "status" => $app->component("status", [
                        "url" => "/hrm/personnels-status/" . $data['id'],
                        "data" => $data['status'],
                        "permission" => ['personnels.edit']
                    ]),
                    "store_name" => $data['store_name'],
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'link',
                                'name' => $jatbi->lang("Xem"),
                                'permission' => ['personnels'],
                                'action' => ['href' => '/hrm/personnels-detail/' . $data['id']]
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Gương mặt"),
                                'permission' => ['personnels.edit'],
                                'action' => ['data-url' => '/hrm/personnels-face/' . $data['id'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['personnels.edit'],
                                'action' => ['data-url' => '/hrm/personnels-edit/' . $data['id'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'permission' => ['personnels.deleted'],
                                'action' => ['data-url' => '/hrm/personnels-deleted?box=' . $data['id'], 'data-action' => 'modal']
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
    })->setPermissions(['personnels']);

    $app->router("/personnels-status/{id}", 'POST', function ($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $data = $app->get("personnels", "*", ["id" => $vars['id'], "deleted" => 0]);
        if ($data > 1) {
            if ($data['status'] === 'A') {
                $status = "D";
            } elseif ($data['status'] === 'D') {
                $status = "A";
            }
            $app->update("personnels", ["status" => $status], ["id" => $data['id']]);
            $jatbi->logs('hrm', 'personnels-status', $data);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['personnels.edit']);

    $app->router("/personnels-add", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $vars['title'] = $jatbi->lang("Thêm Nhân viên");
        if ($app->method() === 'GET') {
            // ... (Phần GET không thay đổi)
            $vars['stores'] = array_merge( [["value" => "", "text" => $jatbi->lang("Chọn")]], $app->select("stores", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "ORDER" => ["name" => "ASC"]]) );
            $vars['offices'] = array_merge( [["value" => "", "text" => $jatbi->lang("Chọn")]], $app->select("offices", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "ORDER" => ["name" => "ASC"]]) );
            $vars['gender'] = [ ["value" => "", "text" => "Chọn"], ["value" => 0, "text" => "Nữ"], ["value" => 1, "text" => "Nam"], ];
            $vars['idtype'] = [ ["value" => "", "text" => "Chọn"], ["value" => 1, "text" => "CMND"], ["value" => 2, "text" => "CCCD"], ["value" => 3, "text" => "CCCD gắn chíp"], ["value" => 4, "text" => "Hộ chiếu"], ];
            $vars['data'] = [ "status" => 'A', ];
            $vars['province'] = array_merge( [["value" => "", "text" => $jatbi->lang("Chọn")]], $app->select("province", ["id(value)", "name(text)"], ["deleted" => 0, "ORDER" => ["name" => "ASC"]]) );
            $vars['province_new'] = array_merge( [["value" => "", "text" => $jatbi->lang("Chọn")]], $app->select("province_new", ["id(value)", "name(text)"], ["deleted" => 0, "status" => "A", "ORDER" => ["name" => "ASC"]]) );
            echo $app->render($template . '/hrm/personnels-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            // ===== FIX: TOÀN BỘ LOGIC POST ĐÃ ĐƯỢC VIẾT LẠI =====
            $app->header(['Content-Type' => 'application/json']);

            if (empty($_POST['name']) || empty($_POST['phone']) || empty($_POST['stores'])) {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống các trường bắt buộc")]);
                return;
            }

            $is_same_address = isset($_POST['same_address']) && $_POST['same_address'] == 1;

            $insert_data = [
                "code" => $app->xss($_POST['code']),
                "name" => $app->xss($_POST['name']),
                "phone" => $app->xss($_POST['phone']),
                "email" => $app->xss($_POST['email']),
                "address" => $app->xss($_POST['address']),
                "province" => $app->xss($_POST['province'] ?? 0),
                "district" => $app->xss($_POST['district'] ?? 0),
                "ward" => $app->xss($_POST['ward'] ?? 0),
                "address-new" => $app->xss($_POST['address-new']),
                "province-new" => $app->xss($_POST['province-new'] ?? 0),
                "ward-new" => $app->xss($_POST['ward-new'] ?? 0),
                "nationality" => $app->xss($_POST['nationality']),
                "nation" => $app->xss($_POST['nation']),
                "idtype" => $app->xss($_POST['idtype']),
                "idcode" => $app->xss($_POST['idcode']),
                "iddate" => $app->xss($_POST['iddate']),
                "idplace" => $app->xss($_POST['idplace']),
                "same_address" => $is_same_address ? 1 : 0,
                "birthday" => $app->xss($_POST['birthday']),
                "gender" => !empty($_POST['gender']) ? $app->xss($_POST['gender']) : NULL,
                "notes" => $app->xss($_POST['notes']),
                "status" => $app->xss($_POST['status']),
                "date" => date("Y-m-d H:i:s"),
                "user" => $app->getSession("accounts")['id'] ?? null,
                "stores" => $app->xss($_POST['stores']),
                "office" => $app->xss($_POST['office']),
            ];

            if ($is_same_address) {
                $insert_data['permanent_address'] = $insert_data['address'];
                $insert_data['permanent_province'] = $insert_data['province'];
                $insert_data['permanent_district'] = $insert_data['district'];
                $insert_data['permanent_ward'] = $insert_data['ward'];
                $insert_data['permanent_address_new'] = $insert_data['address-new'];
                $insert_data['permanent_province_new'] = $insert_data['province-new'];
                $insert_data['permanent_ward_new'] = $insert_data['ward-new'];
            } else {
                $insert_data['permanent_address'] = $app->xss($_POST['permanent_address']);
                $insert_data['permanent_province'] = $app->xss($_POST['permanent_province'] ?? 0);
                $insert_data['permanent_district'] = $app->xss($_POST['permanent_district'] ?? 0);
                $insert_data['permanent_ward'] = $app->xss($_POST['permanent_ward'] ?? 0);
                $insert_data['permanent_address_new'] = $app->xss($_POST['permanent_address_new']);
                $insert_data['permanent_province_new'] = $app->xss($_POST['permanent_province_new'] ?? 0);
                $insert_data['permanent_ward_new'] = $app->xss($_POST['permanent_ward_new'] ?? 0);
            }

            $app->insert("personnels", $insert_data);
            $jatbi->logs('hrm', 'personnels-add', [$insert_data]);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Thêm mới thành công")]);
        }
    })->setPermissions(['personnels.add']);

    // Route sửa nhân viên
    $app->router("/personnels-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $vars['title'] = $jatbi->lang("Sửa Nhân viên");
        if ($app->method() === 'GET') {
            $vars['data'] = $app->get("personnels", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($vars['data'])) {
                // --- Dữ liệu chung không đổi ---
                $vars['stores'] = array_merge([["value" => "", "text" => $jatbi->lang("Chọn")]], $app->select("stores", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "ORDER" => ["name" => "ASC"]]));
                $vars['offices'] = array_merge([["value" => "", "text" => $jatbi->lang("Chọn")]], $app->select("offices", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "ORDER" => ["name" => "ASC"]]));
                $vars['gender'] = [["value" => "", "text" => "Chọn"], ["value" => 0, "text" => "Nữ"], ["value" => 1, "text" => "Nam"]];
                $vars['idtype'] = [["value" => "", "text" => "Chọn"], ["value" => 1, "text" => "CMND"], ["value" => 2, "text" => "CCCD"], ["value" => 3, "text" => "CCCD gắn chíp"], ["value" => 4, "text" => "Hộ chiếu"]];
                
                // --- Tải danh sách Tỉnh/Thành phố cho cả hai hệ thống ---
                $vars['province'] = array_merge([["value" => "", "text" => $jatbi->lang("Chọn")]], $app->select("province", ["id(value)", "name(text)"], ["deleted" => 0, "ORDER" => ["name" => "ASC"]]));
                $vars['province_new'] = array_merge([["value" => "", "text" => $jatbi->lang("Chọn")]], $app->select("province_new", ["id(value)", "name(text)"], ["deleted" => 0, "status" => "A", "ORDER" => ["name" => "ASC"]]));

                // --- Tải danh sách cho ĐỊA CHỈ LIÊN LẠC (Hệ thống cũ) ---
                $vars['district'] = $app->select("district", ["id(value)", "name(text)"], ["deleted" => 0, "province" => $vars['data']['province'], "ORDER" => ["name" => "ASC"]]);
                $vars['ward'] = $app->select("ward", ["id(value)", "name(text)"], ["deleted" => 0, "district" => $vars['data']['district'], "ORDER" => ["name" => "ASC"]]);

                // --- Tải danh sách cho ĐỊA CHỈ THƯỜNG TRÚ (Hệ thống cũ) ---
                $vars['permanent_district'] = $app->select("district", ["id(value)", "name(text)"], ["deleted" => 0, "province" => $vars['data']['permanent_province'], "ORDER" => ["name" => "ASC"]]);
                $vars['permanent_ward'] = $app->select("ward", ["id(value)", "name(text)"], ["deleted" => 0, "district" => $vars['data']['permanent_district'], "ORDER" => ["name" => "ASC"]]);
                
                // ===== FIX: Sửa lại logic tải dữ liệu cho HỆ THỐNG MỚI =====
                // Sửa tên biến từ 'ward-new' thành 'ward_new'
                // Truy vấn này sẽ lấy danh sách các mục (có vẻ là quận/huyện) dựa trên tỉnh/thành phố mới đã lưu
                $vars['ward_new'] = $app->select("district_new", ["id(value)", "name(text)"], ["deleted" => 0, "province" => $vars['data']['province-new'], "ORDER" => ["name" => "ASC"]]);
                // Tương tự, nếu bạn có địa chỉ thường trú mới, cũng cần tải ở đây
                // Ví dụ:
                // $vars['permanent_ward_new'] = $app->select("district_new", ["id(value)", "name(text)"], ["deleted" => 0, "province" => $vars['data']['permanent_province_new'], "ORDER" => ["name" => "ASC"]]);
                // ==========================================================

                echo $app->render($template . '/hrm/personnels-post.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            // ===== FIX: TOÀN BỘ LOGIC POST ĐÃ ĐƯỢC VIẾT LẠI =====
            $app->header(['Content-Type' => 'application/json']);
            $data = $app->get("personnels", "*", ["id" => $vars['id'], "deleted" => 0]);

            if (!empty($data)) {
                if ($app->xss($_POST['name']) == '' || $app->xss($_POST['phone']) == '' || $app->xss($_POST['stores']) == '') {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
                } else {
                    // if($app->xss($_POST['same_address'])==1){
                    // 	$permanent_address = $app->xss($_POST['address']);
                    // 	$permanent_province = $app->xss($_POST['province']);
                    // 	$permanent_district = $app->xss($_POST['district']);
                    // 	$permanent_ward = $app->xss($_POST['ward']);
                    // }
                    // else {
                    // 	$permanent_address = $app->xss($_POST['permanent_address']);
                    // 	$permanent_province = $app->xss($_POST['permanent_province']);
                    // 	$permanent_district = $app->xss($_POST['permanent_district']);
                    // 	$permanent_ward = $app->xss($_POST['permanent_ward']);
                    // }
                    $insert = [
                        // "active" 	=> $jatbi->random(32),
                        "code" => $app->xss($_POST['code']),
                        "name" => $app->xss($_POST['name']),
                        "phone" => $app->xss($_POST['phone']),
                        "email" => $app->xss($_POST['email']),
                        "address" => $app->xss($_POST['address']),
                        "province" => $app->xss($_POST['province'] ?? 0),
                        "district" => $app->xss($_POST['district'] ?? 0),
                        "ward" => $app->xss($_POST['ward'] ?? 0),
                        "nationality" => $app->xss($_POST['nationality']),
                        "nation" => $app->xss($_POST['nation']),
                        "idtype" => $app->xss($_POST['idtype']),
                        "idcode" => $app->xss($_POST['idcode']),
                        "iddate" => $app->xss($_POST['iddate']),
                        "idplace" => $app->xss($_POST['idplace']),
                        // "same_address"	=> $app->xss($_POST['same_address']),
                        // "permanent_address" 	=> $permanent_address,
                        // "permanent_province" 	=> $permanent_province,
                        // "permanent_district" 	=> $permanent_district,
                        // "permanent_ward" 		=> $permanent_ward,
                        "birthday" => $app->xss($_POST['birthday']),
                        "gender" => $app->xss(!empty($_POST['gender']) ? $_POST['gender'] : -1),
                        "notes" => $app->xss($_POST['notes']),
                        "status" => $app->xss($_POST['status']),
                        // "date" => date("Y-m-d H:i:s"),
                        "user" => $app->getSession("accounts")['id'] ?? null,
                        "stores" => $app->xss($_POST['stores']),
                        "office" => $app->xss($_POST['office']),
                    ];
                    $app->update("personnels", $insert, ["id" => $data['id']]);
                    $jatbi->logs('hrm', 'personnels-edit', [$insert]);
                    echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
                }
            } else {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
                return;
            }

            if (empty($_POST['name']) || empty($_POST['phone']) || empty($_POST['stores'])) {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống các trường bắt buộc")]);
                return;
            }
            
            $is_same_address = isset($_POST['same_address']) && $_POST['same_address'] == 1;

            $update_data = [
                "code" => $app->xss($_POST['code']),
                "name" => $app->xss($_POST['name']),
                "phone" => $app->xss($_POST['phone']),
                "email" => $app->xss($_POST['email']),
                "address" => $app->xss($_POST['address']),
                "province" => $app->xss($_POST['province']),
                "district" => $app->xss($_POST['district']),
                "ward" => $app->xss($_POST['ward']),
                "address-new" => $app->xss($_POST['address-new']),
                "province-new" => $app->xss($_POST['province-new'] ?? 0),
                "ward-new" => $app->xss($_POST['ward-new'] ?? 0),
                "nationality" => $app->xss($_POST['nationality']),
                "nation" => $app->xss($_POST['nation']),
                "idtype" => $app->xss($_POST['idtype']),
                "idcode" => $app->xss($_POST['idcode']),
                "iddate" => $app->xss($_POST['iddate']),
                "idplace" => $app->xss($_POST['idplace']),
                "same_address" => $is_same_address ? 1 : 0,
                "birthday" => $app->xss($_POST['birthday']),
                "gender" => !empty($_POST['gender']) ? $app->xss($_POST['gender']) : NULL,
                "notes" => $app->xss($_POST['notes']),
                "status" => $app->xss($_POST['status']),
                "user" => $app->getSession("accounts")['id'] ?? null,
                "stores" => $app->xss($_POST['stores']),
                "office" => $app->xss($_POST['office']),
            ];

            if ($is_same_address) {
                $update_data['permanent_address'] = $update_data['address'];
                $update_data['permanent_province'] = $update_data['province'];
                $update_data['permanent_district'] = $update_data['district'];
                $update_data['permanent_ward'] = $update_data['ward'];
                $update_data['permanent_address_new'] = $update_data['address-new'];
                $update_data['permanent_province_new'] = $update_data['province-new'];
                $update_data['permanent_ward_new'] = $update_data['ward-new'];
            } else {
                $update_data['permanent_address'] = $app->xss($_POST['permanent_address']);
                $update_data['permanent_province'] = $app->xss($_POST['permanent_province'] ?? 0);
                $update_data['permanent_district'] = $app->xss($_POST['permanent_district'] ?? 0);
                $update_data['permanent_ward'] = $app->xss($_POST['permanent_ward'] ?? 0);
                $update_data['permanent_address_new'] = $app->xss($_POST['permanent_address_new']);
                $update_data['permanent_province_new'] = $app->xss($_POST['permanent_province_new'] ?? 0);
                $update_data['permanent_ward_new'] = $app->xss($_POST['permanent_ward_new'] ?? 0);
            }

            $app->update("personnels", $update_data, ["id" => $data['id']]);
            $jatbi->logs('hrm', 'personnels-edit', [$update_data]);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        }
    })->setPermissions(['personnels.edit']);

    $app->router("/personnels-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("personnels", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("personnels", ["deleted" => 1], ["id" => $data['id']]);
                }
                $jatbi->logs('hrm', 'personnels-deleted', $datas);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['personnels.deleted']);

    $app->router('/personnels-detail/{id}', 'GET', function ($vars) use ($app, $jatbi, $template) {
        if ($app->method() === 'GET') {
            $vars['data'] = $app->get("personnels", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($vars['data'])) {
                $vars['title'] = $jatbi->lang("Nhân viên: ") . $vars['data']['name'];
                $vars['contracts'] = $app->select(
                    "personnels_contract",
                    [
                        "[>]offices" => ["offices" => "id"],
                    ],
                    [
                        "personnels_contract.id",
                        "personnels_contract.code",
                        "personnels_contract.type",
                        "personnels_contract.salary",
                        "personnels_contract.duration",
                        "personnels_contract.date_contract",
                        "personnels_contract.workday",
                        "offices.name(office_name)"
                    ],
                    [
                        "personnels_contract.personnels" => $vars['data']['id'],
                        "personnels_contract.deleted" => 0
                    ]
                );

                $vars['insurrances'] = $app->select("personnels_insurrance", "*", ["personnels" => $vars['data']['id'], "deleted" => 0]);
                echo $app->render($template . '/hrm/personnels-detail.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
            }
        }
    })->setPermissions(['personnels']);

    $app->router('/personnels-face/{id}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $setting) {

        if ($app->method() === 'GET') {
            $vars['data'] = $app->get("personnels", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($vars['data'])) {
                $vars['title'] = $jatbi->lang("Cập nhật Face ID: ") . $vars['data']['name'];
                $vars['decided'] = $app->select("hrm_decided", ["name(text)", "id(value)"], ["deleted" => 0]);
                echo $app->render($template . '/hrm/personnels-face.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
            }
        }

        if ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            $personnel = $app->get("personnels", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (empty($personnel)) {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy nhân viên")]);
                return;
            }

            $errorMessages = [];
            $successMessages = [];
            $photo = '';
            $isNewPhotoUploaded = false;

            $devices = isset($_POST['decided']) ? $_POST['decided'] : '';
            if (empty($devices) || !is_array($devices)) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng chọn thiết bị")]);
                return;
            }

            if (isset($_FILES['images']) && $_FILES['images']['error'] === UPLOAD_ERR_OK) {
                $handle = $app->upload($_FILES['images']);

                $path_upload = $setting['uploads'] . '/personnels/';
                $path_thumb = $path_upload . 'thumb/';

                if (!is_dir($path_upload))
                    mkdir($path_upload, 0755, true);
                if (!is_dir($path_thumb))
                    mkdir($path_thumb, 0755, true);

                $new_file_id = $jatbi->active();

                if ($handle->uploaded) {
                    $handle->allowed = ['image/*'];
                    $handle->file_max_size = 10485760;
                    $handle->file_new_name_body = $new_file_id;
                    $handle->Process($path_upload);

                    if ($handle->processed) {
                        $handle->image_resize = true;
                        $handle->image_ratio_crop = true;
                        $handle->image_y = $setting['upload']['images']["personnels"]['thumb_y'];
                        $handle->image_x = $setting['upload']['images']["personnels"]['thumb_x'];
                        $handle->file_new_name_body = $new_file_id;
                        $handle->Process($path_thumb);

                        if ($handle->processed) {
                            $photo = $handle->file_dst_name;
                            $isNewPhotoUploaded = true;
                            $handle->clean();
                        } else {
                            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Tạo ảnh thumb thất bại: ") . $handle->error]);
                            return;
                        }
                    } else {
                        echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Upload ảnh thất bại: ") . $handle->error]);
                        return;
                    }
                }
            } elseif ($personnel['face'] == 0) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng chọn hình ảnh để đăng ký")]);
                return;
            }


            $deviceKeys = [];

            foreach ($devices as $deviceId) {
                $deviceConfig = $app->get("hrm_decided", "*", ["id" => $deviceId]);
                if (!$deviceConfig)
                    continue;

                $deviceApiPayload = [
                    'deviceKey' => $deviceConfig['decided'],
                    'secret' => $deviceConfig['password'],
                ];

                if ($personnel['face'] == 0) {
                    $createPayload = array_merge($deviceApiPayload, [
                        'sn' => $personnel['id'],
                        'name' => $personnel['name'],
                        'type' => 1,
                    ]);
                    $createResponse = $jatbi->callCameraApi('person/create', $createPayload);

                    if (empty($createResponse['success'])) {
                        $errorMessages[] = "Thiết bị '{$deviceConfig['name']}': Lỗi tạo NV - " . ($createResponse['msg'] ?? 'Không rõ');
                        continue;
                    }

                    $imageUrl = $setting['url'] . "/" . $setting['upload']['images']['personnels']['url'] . $photo;

                    $mergePayload = array_merge($deviceApiPayload, [
                        'personSn' => $personnel['id'],
                        'imgUrl' => $imageUrl,
                        'easy' => 1,
                    ]);
                    $mergeResponse = $jatbi->callCameraApi('face/merge', $mergePayload);

                    if (empty($mergeResponse['success'])) {
                        $errorMessages[] = "Thiết bị '{$deviceConfig['name']}': Lỗi thêm mặt - " . ($mergeResponse['msg'] ?? 'Không rõ');
                    } else {
                        $successMessages[] = "Thiết bị '{$deviceConfig['name']}': Đăng ký thành công.";
                        $deviceKeys[] = $deviceConfig['decided'];
                    }
                } else {
                    if ($app->xss($_POST['update'] ?? 0) == 1) {
                        $updatePayload = array_merge($deviceApiPayload, [
                            'sn' => $personnel['id'],
                            'name' => $personnel['code'],
                            'type' => 1,
                        ]);
                        $updateResponse = $jatbi->callCameraApi('person/update', $updatePayload);
                        if (empty($updateResponse['success'])) {
                            $errorMessages[] = "Thiết bị '{$deviceConfig['name']}': Lỗi cập nhật - " . ($updateResponse['msg'] ?? 'Không rõ');
                        } else {
                            $successMessages[] = "Thiết bị '{$deviceConfig['name']}': Cập nhật thông tin thành công.";
                        }
                    }

                    if ($isNewPhotoUploaded) {
                        $imageUrl = $setting['url'] . "/" . $setting['upload']['images']['personnels']['url'] . $photo;
                        $mergePayload = array_merge($deviceApiPayload, [
                            'personSn' => $personnel['id'],
                            'imgUrl' => $imageUrl,
                            'easy' => 1,
                        ]);
                        $mergeResponse = $jatbi->callCameraApi('face/merge', $mergePayload);
                        if (empty($mergeResponse['success'])) {
                            $errorMessages[] = "Thiết bị '{$deviceConfig['name']}': Lỗi cập nhật mặt - " . ($mergeResponse['msg'] ?? 'Không rõ');
                        } else {
                            $successMessages[] = "Thiết bị '{$deviceConfig['name']}': Cập nhật khuôn mặt thành công.";
                        }
                    }
                }
            }

            if (empty($errorMessages)) {
                $updateData = [];
                if ($personnel['face'] == 0) {
                    $updateData = [
                        "face" => 1,
                        "images" => $photo,
                    ];
                } elseif ($isNewPhotoUploaded) {
                    $updateData = ["images" => $photo];
                }

                if (!empty($updateData)) {
                    $app->update("personnels", $updateData, ["id" => $personnel['id']]);
                }

                $jatbi->logs('personnels', 'add-face', $personnel['id']);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Thao tác thành công!"), 'details' => $successMessages]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xảy ra trong quá trình xử lý"), 'details' => $errorMessages]);
            }
        }
    })->setPermissions(['personnels.edit']);

    //offices
    $app->router("/offices", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {
        $jatbi->permission('offices');
        $vars['title'] = $jatbi->lang("Phòng ban");

        if ($app->method() === 'GET') {
            echo $app->render($template . '/hrm/offices.html', $vars);
        }
        if ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : $setting['site_page'] ?? 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'offices.id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $status = (isset($_POST['status']) && in_array($_POST['status'], ['A', 'D'])) ? [$_POST['status'], $_POST['status']] : '';

            $where = [
                "AND" => [
                    "OR" => [
                        'offices.code[~]' => $searchValue,
                        'offices.name[~]' => $searchValue,
                    ],
                    'offices.status[<>]' => $status,
                    'offices.deleted' => 0,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];

            $count = $app->count("offices", ['offices.id'], $where['AND']);

            $datas = [];
            $app->select("offices", [
                'offices.id',
                'offices.code',
                'offices.name',
                'offices.notes',
                'offices.status',
            ], $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "code" => $data['code'],
                    "name" => $data['name'],
                    "notes" => $data['notes'],
                    "status" => $app->component("status", [
                        "url" => "/hrm/offices-status/" . $data['id'],
                        "data" => $data['status'],
                        "permission" => ['offices.edit']
                    ]),
                    "action" => ($app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['offices.edit'],
                                'action' => ['data-url' => '/hrm/offices-edit/' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'permission' => ['offices.deleted'],
                                'action' => ['data-url' => '/hrm/offices-deleted?box=' . ($data['id'] ?? ''), 'data-action' => 'modal']
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
                ]
            );
        }
    })->setPermissions(['offices']);

    $app->router("/offices-status/{id}", 'POST', function ($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $data = $app->get("offices", "*", ["id" => $vars['id'], "deleted" => 0]);
        if ($data > 1) {
            if ($data['status'] === 'A') {
                $status = "D";
            } elseif ($data['status'] === 'D') {
                $status = "A";
            }
            $app->update("offices", ["status" => $status], ["id" => $data['id']]);
            $jatbi->logs('hrm', 'offices-status', $data);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['offices.edit']);

    $app->router("/offices-add", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        if ($app->method() === 'GET') {
            $vars['data'] = [
                "status" => 'A',
            ];
            $vars['title'] = $jatbi->lang("Thêm Phòng ban");
            echo $app->render($template . '/hrm/offices-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            if ($app->xss($_POST['name']) == '') {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
            } else {
                $insert = [
                    "code" => $app->xss($_POST['code']),
                    "name" => $app->xss($_POST['name']),
                    "notes" => $app->xss($_POST['notes']),
                    "status" => $app->xss($_POST['status']),
                ];
                $app->insert("offices", $insert);
                $jatbi->logs('hrm', 'offices-add', [$insert]);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            }
        }
    })->setPermissions(['offices.add']);

    $app->router("/offices-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $vars['title'] = $jatbi->lang("Sửa Phòng ban");
        if ($app->method() === 'GET') {
            $vars['data'] = $app->get("offices", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($vars['data'])) {
                echo $app->render($template . '/hrm/offices-post.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $data = $app->get("offices", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($data)) {
                if ($app->xss($_POST['code']) == '' || $app->xss($_POST['name']) == '' || $app->xss($_POST['status']) == '') {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
                } else {
                    $insert = [
                        "code" => $app->xss($_POST['code']),
                        "name" => $app->xss($_POST['name']),
                        "notes" => $app->xss($_POST['notes']),
                        "status" => $app->xss($_POST['status']),
                    ];
                    $app->update("offices", $insert, ["id" => $data['id']]);
                    $jatbi->logs('hrm', 'offices-edit', [$insert]);
                    echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
                }
            } else {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
            }
        }
    })->setPermissions(['offices.edit']);

    $app->router("/offices-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa Phòng ban");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("offices", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("offices", ["deleted" => 1], ["id" => $data['id']]);
                }
                $jatbi->logs('hrm', 'offices-deleted', $datas);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['offices.deleted']);

    $app->router('/personnels-salary-logs/{id}', 'GET', function ($vars) use ($app, $jatbi, $template) {
        if ($app->method() === 'GET') {
            $vars['data'] = $app->get("personnels", "*", ["id" => $vars['id']]);
            $vars['title'] = $jatbi->lang("Nhân viên: ") . $vars['data']['name'];
            if (!empty($vars['data'])) {
                $salarys = [];
                $allowances = [];
                $vars['dataSalary'] = $app->get("personnels_contract_salary", "*", ["deleted" => 0, "personnels" => $vars['data']['id']]);
                $app->select("personnels_contract_salary_details", [
                    "[>]salary_categorys" => ["content" => "id"],
                ], [
                    'salary_categorys.name(content)',
                    'personnels_contract_salary_details.price',
                    'personnels_contract_salary_details.notes',
                    "personnels_contract_salary_details.type",
                ], [
                    "personnels_contract_salary_details.personnels" => $vars['data']['id'],
                    "personnels_contract_salary_details.deleted" => 0
                ], function ($salary) use (&$salarys, &$allowances, $jatbi, $app) {
                    if ($salary['type'] == 1) {
                        $salarys[] = [
                            'content' => $salary['content'],
                            'price' => number_format($salary['price'], 0, ',', '.'),
                            'notes' => $salary['notes'],
                        ];
                    } elseif ($salary['type'] == 2) {
                        $allowances[] = [
                            'content' => $salary['content'],
                            'price' => number_format($salary['price'], 0, ',', '.'),
                            'notes' => $salary['notes'],
                        ];
                    }
                });

                $vars['salarys'] = $salarys;
                $vars['allowances'] = $allowances;
                echo $app->render($template . '/hrm/personnels-salary-logs.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
            }
        }
    })->setPermissions(['personnels']);

    //timeworke
    $app->router('/timework', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Thời gian làm việc");
            echo $app->render($template . '/hrm/timework.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : $setting['site_page'] ?? 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $status = (isset($_POST['status']) && in_array($_POST['status'], ['A', 'D'])) ? [$_POST['status'], $_POST['status']] : '';

            $where = [
                "AND" => [
                    "OR" => [
                        'name[~]' => $searchValue,
                    ],
                    'deleted' => 0,
                    "status[<>]" => $status,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];

            $count = $app->count("timework", ['id'], $where['AND']);
            $days = [
                1 => 'T2',
                2 => 'T3',
                3 => 'T4',
                4 => 'T5',
                5 => 'T6',
                6 => 'T7',
                7 => 'CN'
            ];
            $datas = [];

            $app->select("timework", [
                'id',
                'name',
                'notes',
                'status',
            ], $where, function ($data) use (&$datas, $jatbi, $app, $days) {
                $details = $app->select("timework_details", "*", ["timework" => $data['id']]);
                $schedule_html = '';
                if ($details) {
                    foreach ($details as $detail) {
                        $schedule_html .= '<p class="mb-1"><strong>' . $days[$detail['week']] . ':</strong> ';
                        if ($detail['off'] == 0) {
                            $schedule_html .= date("H:i", strtotime($detail['time_from'])) . ' - ' . date("H:i", strtotime($detail['time_to']));
                        } else {
                            $schedule_html .= '<span class="text-muted">' . $jatbi->lang("Nghỉ") . '</span>';
                        }
                        $schedule_html .= '</p>';
                    }
                }


                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "name" => $data['name'],
                    "schedule" => $schedule_html,
                    "notes" => $data['notes'],
                    "status" => $app->component("status", [
                        "url" => "/hrm/timework-status/" . $data['id'],
                        "data" => $data['status'],
                        "permission" => ['timework.edit']
                    ]),
                    "action" => ($app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['timework.edit'],
                                'action' => ['data-url' => '/hrm/timework-edit/' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'permission' => ['timework.deleted'],
                                'action' => ['data-url' => '/hrm/timework-deleted?box=' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                        ]
                    ]))
                ];
            });

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas,
            ]);
        }
    })->setPermissions(['timework']);

    $app->router("/timework-status/{id}", 'POST', function ($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $data = $app->get("timework", "*", ["id" => $vars['id'], "deleted" => 0]);
        if ($data > 1) {
            if ($data['status'] === 'A') {
                $status = "D";
            } elseif ($data['status'] === 'D') {
                $status = "A";
            }
            $app->update("timework", ["status" => $status], ["id" => $data['id']]);
            $jatbi->logs('hrm', 'timework-status', $data);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['timework.edit']);

    $app->router("/timework-add", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $vars['title'] = $jatbi->lang("Thêm Thời gian làm việc");
        if ($app->method() === 'GET') {
            $vars['data'] = [
                "status" => 'A',
            ];
            echo $app->render($template . '/hrm/timework-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            if ($app->xss($_POST['name']) == '' || $app->xss($_POST['status']) == '') {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
            } else {
                $insert = [
                    "name" => $app->xss($_POST['name']),
                    "notes" => $app->xss($_POST['notes']),
                    "status" => $app->xss($_POST['status']),
                    "user" => $app->getSession("accounts")['id'] ?? null,
                    "date" => date("Y-m-d H:i:s"),
                ];
                $app->insert("timework", $insert);
                $getID = $app->id();
                for ($i = 1; $i <= 7; $i++) {
                    $details = [
                        "timework" => $getID,
                        "week" => $i,
                        "time_from" => $app->xss($_POST['time_from' . $i]),
                        "time_to" => $app->xss($_POST['time_to' . $i]),
                        "notes" => $app->xss($_POST['notes' . $i]),
                        "off" => $app->xss(isset($_POST['off' . $i]) ? '1' : '0'),
                        "user" => $app->getSession("accounts")['id'] ?? null,
                        "date" => date("Y-m-d H:i:s"),
                    ];
                    $details_logs[] = [
                        "timework" => $getID,
                        "week" => $i,
                        "time_from" => $app->xss($_POST['time_from' . $i]),
                        "time_to" => $app->xss($_POST['time_to' . $i]),
                        "notes" => $app->xss($_POST['notes' . $i]),
                        "off" => $app->xss(isset($_POST['off' . $i]) ? '1' : '0'),
                        "user" => $app->getSession("accounts")['id'] ?? null,
                        "date" => date("Y-m-d H:i:s"),
                    ];
                    $app->insert("timework_details", $details);
                }
                $jatbi->logs('hrm', 'timework-add', [$insert, $details_logs]);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            }
        }
    })->setPermissions(['timework.add']);

    $app->router("/timework-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $vars['title'] = $jatbi->lang("Sửa Thời gian làm việc");
        if ($app->method() === 'GET') {
            $vars['data'] = $app->get("timework", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($vars['data'])) {
                $vars['data_details'] = $app->select("timework_details", "*", ["timework" => $vars['data']['id'], "deleted" => 0]);
                echo $app->render($template . '/hrm/timework-post.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $data = $app->get("timework", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($data)) {
                $data_details = $app->select("timework_details", "*", ["timework" => $data['id'], "deleted" => 0]);
                if ($app->xss($_POST['name']) == '' || $app->xss($_POST['status']) == '') {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
                } else {
                    $insert = [
                        "name" => $app->xss($_POST['name']),
                        "notes" => $app->xss($_POST['notes']),
                        "status" => $app->xss($_POST['status']),
                        "user" => $app->getSession("accounts")['id'] ?? null,
                        "date" => date("Y-m-d H:i:s"),
                    ];
                    $app->update("timework", $insert, ["id" => $data['id']]);
                    for ($i = 1; $i <= 7; $i++) {
                        $details = [
                            "week" => $i,
                            "time_from" => $app->xss($_POST['time_from' . $i]),
                            "time_to" => $app->xss($_POST['time_to' . $i]),
                            "notes" => $app->xss($_POST['notes' . $i]),
                            "off" => $app->xss(isset($_POST['off' . $i]) ? '1' : '0'),
                            "user" => $app->getSession("accounts")['id'] ?? null,
                            "date" => date("Y-m-d H:i:s"),
                        ];
                        $details_logs[] = [
                            "week" => $i,
                            "time_from" => $app->xss($_POST['time_from' . $i]),
                            "time_to" => $app->xss($_POST['time_to' . $i]),
                            "notes" => $app->xss($_POST['notes' . $i]),
                            "off" => $app->xss(isset($_POST['off' . $i]) ? '1' : '0'),
                            "user" => $app->getSession("accounts")['id'] ?? null,
                            "date" => date("Y-m-d H:i:s"),
                        ];
                        $app->update("timework_details", $details, ["id" => $data_details[$i - 1]['id']]);
                    }
                    $jatbi->logs('hrm', 'timework-update', [$insert, $details_logs]);
                    echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
                }
            } else {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
            }
        }
    })->setPermissions(['timework.edit']);

    $app->router("/timework-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        }
        if ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("timework", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("timework", ["deleted" => 1], ["id" => $data['id']]);
                }
                $jatbi->logs('hrm', 'timework-deleted', $datas);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['timework.deleted']);

    //salary-categorys
    $app->router('/salary-categorys', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {
        $vars['title'] = $jatbi->lang("Danh mục tiền lương");

        if ($app->method() === 'GET') {
            echo $app->render($template . '/hrm/salary-categorys.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : $setting['site_page'] ?? 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $status = (isset($_POST['status']) && in_array($_POST['status'], ['A', 'D'])) ? [$_POST['status'], $_POST['status']] : '';

            $where = [
                "AND" => [
                    "OR" => [
                        'name[~]' => $searchValue,
                    ],
                    'deleted' => 0,
                    "status[<>]" => $status,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];


            $count = $app->count("salary_categorys", $where['AND']);
            $datas = [];

            $app->select("salary_categorys", [
                'id',
                'name',
                'duration',
                'type',
                'price',
                'notes',
                'status',
            ], $where, function ($data) use (&$datas, $jatbi, $app, $setting) {

                // $type_name = $setting['salarys_type_categorys'][$data['type']]['name'];
                // $duration_name = $setting['duration_types'][$data['duration']]['name'];
                // $price_formatted = number_format($data['price']) . ($duration_name ? ' / ' . $duration_name : '');

                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "type" => $data['type'] == 1 ? 'Tiền lương' : (
                        $data['type'] == 2 ? 'Phụ cấp' : (
                            $data['type'] == 3 ? 'Tăng ca' : 'Không xác định')),
                    "name" => $data['name'],
                    "price" => number_format($data['price']) . " / " . ($data['duration'] == 1 ? 'Giờ' : (
                        $data['duration'] == 3 ? 'Ngày' : (
                            $data['duration'] == 4 ? 'Tháng' : 'Không xác định'))),
                    "notes" => $data['notes'],
                    "status" => $app->component("status", [
                        "url" => "/hrm/salary-categorys-status/" . $data['id'],
                        "data" => $data['status'],
                        "permission" => ['salary-categorys.edit']
                    ]),
                    "action" => ($app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['salary-categorys.edit'],
                                'action' => ['data-url' => '/hrm/salary-categorys-edit/' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'permission' => ['salary-categorys.deleted'],
                                'action' => ['data-url' => '/hrm/salary-categorys-deleted?box=' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                        ]
                    ]))
                ];
            });

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas,
            ]);
        }
    })->setPermissions(['salary-categorys']);

    $app->router("/salary-categorys-status/{id}", 'POST', function ($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $data = $app->get("salary_categorys", "*", ["id" => $vars['id'], "deleted" => 0]);
        if ($data > 1) {
            if ($data['status'] === 'A') {
                $status = "D";
            } elseif ($data['status'] === 'D') {
                $status = "A";
            }
            $app->update("salary_categorys", ["status" => $status], ["id" => $data['id']]);
            $jatbi->logs('hrm', 'salary-categorys-status', $data);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['salary-categorys.edit']);

    $app->router("/salary-categorys-add", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $vars['title'] = $jatbi->lang("Thêm Danh mục tiền lương");
        if ($app->method() === 'GET') {
            $vars['salarys_type_categorys'] = [
                ["value" => "", "text" => "Chọn"],
                ["value" => 1, "text" => "Tiền lương"],
                ["value" => 2, "text" => "Phụ cấp"],
                ["value" => 3, "text" => "Tăng ca"],
            ];
            $vars['duration_types'] = [
                ["value" => "", "text" => "Chọn"],
                ["value" => 1, "text" => "Giờ"],
                ["value" => 3, "text" => "Ngày"],
                ["value" => 4, "text" => "Tháng"],
            ];
            $vars['data'] = [
                "status" => 'A',
            ];
            echo $app->render($template . '/hrm/salary-categorys-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            if ($app->xss($_POST['name']) == '' || $app->xss($_POST['type']) == '' || $app->xss($_POST['price']) == '' || $app->xss($_POST['duration']) == '') {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
            } else {
                $insert = [
                    "name" => $app->xss($_POST['name']),
                    "type" => $app->xss($_POST['type']),
                    "price" => $app->xss(str_replace([','], '', $_POST['price'])),
                    "duration" => $app->xss($_POST['duration']),
                    "notes" => $app->xss($_POST['notes']),
                    "status" => $app->xss($_POST['status']),
                    "user" => $app->getSession("accounts")['id'] ?? null,
                    "date" => date("Y-m-d H:i:s"),
                ];
                $app->insert("salary_categorys", $insert);
                $jatbi->logs('hrm', 'salary-categorys-add', [$insert]);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            }
        }
    })->setPermissions(['salary-categorys.add']);

    $app->router("/salary-categorys-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $vars['title'] = $jatbi->lang("Sửa Danh mục tiền lương");
        if ($app->method() === 'GET') {
            $vars['data'] = $app->get("salary_categorys", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($vars['data'])) {
                $vars['salarys_type_categorys'] = [
                    ["value" => "", "text" => "Chọn"],
                    ["value" => 1, "text" => "Tiền lương"],
                    ["value" => 2, "text" => "Phụ cấp"],
                    ["value" => 3, "text" => "Tăng ca"],
                ];
                $vars['duration_types'] = [
                    ["value" => "", "text" => "Chọn"],
                    ["value" => 1, "text" => "Giờ"],
                    ["value" => 3, "text" => "Ngày"],
                    ["value" => 4, "text" => "Tháng"],
                ];
                echo $app->render($template . '/hrm/salary-categorys-post.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $data = $app->get("personnels_insurrance", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($data)) {
                if ($app->xss($_POST['name']) == '' || $app->xss($_POST['type']) == '' || $app->xss($_POST['price']) == '' || $app->xss($_POST['duration']) == '') {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
                } else {
                    $insert = [
                        "name" => $app->xss($_POST['name']),
                        "type" => $app->xss($_POST['type']),
                        "price" => $app->xss(str_replace([','], '', $_POST['price'])),
                        "duration" => $app->xss($_POST['duration']),
                        "notes" => $app->xss($_POST['notes']),
                        "status" => $app->xss($_POST['status']),
                        "user" => $app->getSession("accounts")['id'] ?? null,
                        "date" => date("Y-m-d H:i:s"),
                    ];
                    $app->update("salary_categorys", $insert, ["id" => $data['id']]);
                    $jatbi->logs('hrm', 'salary-categorys-edit', [$insert]);
                    echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
                }
            } else {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
            }
        }
    })->setPermissions(['salary-categorys.edit']);

    $app->router("/salary-categorys-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("salary_categorys", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("salary_categorys", ["deleted" => 1], ["id" => $data['id']]);
                }
                $jatbi->logs('hrm', 'salary-categorys-deleted', $datas);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['salary-categorys.deleted']);

    //Bang phan cong 
    $app->router('/rosters', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores, $template, $accStore) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Bảng phân công");
            $vars['personnels'] = $app->select("personnels", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "stores" => $accStore, "ORDER" => ["name" => "ASC"]]);
            $vars['timeworks'] = $app->select("timework", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "ORDER" => ["name" => "ASC"]]);
            // $vars['timeworks'] = $app->select("timework", ["id", "name"], ["deleted" => 0, "status" => 'A']);
            echo $app->render($template . '/hrm/rosters.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : $setting['site_page'] ?? 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'rosters.id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $personnels = isset($_POST['personnels']) ? $_POST['personnels'] : '';
            $timeworks = isset($_POST['timeworks']) ? $_POST['timeworks'] : '';
            $store_ids = array_column($stores, column_key: 'value');

            $joins = [
                "[>]personnels" => ["personnels" => "id"],
                "[>]timework" => ["timework" => "id"],
            ];
            $where = [
                "AND" => [
                    "OR" => [
                        'personnels.name[~]' => $searchValue,
                        'timework.name[~]' => $searchValue,
                    ],
                    'personnels.stores' => $store_ids,
                    'rosters.deleted' => 0,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];

            // Áp dụng các bộ lọc tùy chọn
            if (!empty($personnels)) {
                $where['AND']['personnels.id'] = $personnels;
            }
            if (!empty($timeworks)) {
                $where['AND']['timework.id'] = $timeworks;
            }

            $count = $app->count("rosters", $joins, ['rosters.id '], $where['AND']);
            $datas = [];

            $app->select("rosters", $joins, [
                'rosters.id ',
                'personnels.name(personnel_name)',
                'rosters.date',
                'timework.name(timework_name)',
                'rosters.notes',
            ], $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "personnel_name" => $data['personnel_name'],
                    "timework_name" => $data['timework_name'],
                    'date' => date("d/m/Y", strtotime($data['date'])),
                    "notes" => $data['notes'],
                    "action" => ($app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['rosters.edit'],
                                'action' => ['data-url' => '/hrm/rosters-edit/' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'permission' => ['rosters.deleted'],
                                'action' => ['data-url' => '/hrm/rosters-deleted?box=' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                        ]
                    ]))
                ];
            });

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas,
            ]);
        }
    })->setPermissions(['rosters']);

    $app->router('/rosters-excel', 'GET', function ($vars) use ($app, $stores) {
        try {
            $month_year = $_GET['month_year'] ?? date('Y-m');
            $officeF = $_GET['office'] ?? '';
            $store_ids = array_column($stores, 'value');


            $year = date('Y', strtotime($month_year));
            $month = date('m', strtotime($month_year));
            $totalDays = date("t", strtotime($month_year));
            $lastDayOfMonth = "$year-$month-$totalDays";

            $wherePersonnel = [
                "deleted" => 0,
                "status" => 'A',
                "stores" => $store_ids,
            ];
            if (!empty($officeF))
                $wherePersonnel['office'] = $officeF;

            $personnel_list = $app->select("personnels", ["id", "name"], ["ORDER" => ["name" => "ASC"]] + $wherePersonnel);
            if (empty($personnel_list))
                exit("Không có nhân viên nào thỏa mãn điều kiện.");

            $personnel_ids = array_column($personnel_list, 'id');

            $all_rosters = $app->select("rosters", [
                "personnels",
                "timework",
                "date"
            ], [
                "personnels" => $personnel_ids,
                "deleted" => 0,
                "date[<=]" => $lastDayOfMonth,
                "ORDER" => ["personnels" => "ASC", "date" => "ASC"]
            ]);

            $all_timework_details = $app->select("timework_details", [
                "id",
                "timework",
                "week",
                "time_from",
                "time_to",
                "off"
            ]);


            $roster_schedule_map = [];
            foreach ($all_rosters as $roster) {
                $roster_schedule_map[$roster['personnels']][$roster['date']] = $roster['timework'];
            }

            $timework_details_map = [];
            foreach ($all_timework_details as $detail) {
                $timework_details_map[$detail['timework']][$detail['week']] = $detail;
            }

            $roster_map = [];

            foreach ($personnel_ids as $pid) {
                $current_timework_id = null;

                if (isset($roster_schedule_map[$pid])) {
                    foreach ($roster_schedule_map[$pid] as $date => $timework_id) {
                        $current_timework_id = $timework_id;
                    }
                }

                for ($day = 1; $day <= $totalDays; $day++) {
                    $current_date_str = sprintf('%s-%s-%02d', $year, $month, $day);

                    if (isset($roster_schedule_map[$pid][$current_date_str])) {
                        $current_timework_id = $roster_schedule_map[$pid][$current_date_str];
                    }
                    if ($current_timework_id === null) {
                        $roster_map[$pid][$current_date_str] = '';
                        continue;
                    }

                    $week_day = date('N', strtotime($current_date_str));

                    if (isset($timework_details_map[$current_timework_id][$week_day])) {
                        $detail = $timework_details_map[$current_timework_id][$week_day];

                        if ($detail['off'] == 1) {
                            $roster_map[$pid][$current_date_str] = 'OFF';
                        } elseif (!empty($detail['time_from']) && !empty($detail['time_to']) && $detail['time_from'] != '00:00:00') {
                            $hours = (strtotime($detail['time_to']) - strtotime($detail['time_from'])) / 3600;
                            $cong = round($hours / 8, 2);
                            $roster_map[$pid][$current_date_str] = $cong;
                        } else {
                            $roster_map[$pid][$current_date_str] = '';
                        }
                    } else {
                        $roster_map[$pid][$current_date_str] = 'OFF';
                    }
                }
            }


            // --- TẠO EXCEL ---
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle("LichLamViec_Thang_{$month}_{$year}");

            // Tiêu đề
            $sheet->setCellValue('A1', "LỊCH LÀM VIỆC THÁNG $month.$year");
            $mergeToCol = Coordinate::stringFromColumnIndex(3 + $totalDays + 3);
            $sheet->mergeCells("A1:$mergeToCol" . '1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF99');

            // Header
            $sheet->setCellValue('A2', 'STT')->mergeCells('A2:A3');
            $sheet->setCellValue('B2', 'Họ và tên')->mergeCells('B2:B3');

            $days_of_week_map = ['1' => 'Thứ Hai', '2' => 'Thứ Ba', '3' => 'Thứ Tư', '4' => 'Thứ Năm', '5' => 'Thứ Sáu', '6' => 'Thứ Bảy', '7' => 'Chủ Nhật'];
            foreach (range(1, $totalDays) as $day) {
                $colLetter = Coordinate::stringFromColumnIndex(2 + $day);

                $sheet->setCellValue($colLetter . '2', str_pad($day, 2, '0', STR_PAD_LEFT));
                $week_day = date('N', strtotime("$year-$month-$day"));
                $sheet->setCellValue($colLetter . '3', $days_of_week_map[$week_day]);

                $sheet->getStyle($colLetter . '3')->getAlignment()->setTextRotation(90);
                $sheet->getStyle($colLetter . '3')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
            }

            $lastDayColIndex = 2 + $totalDays;
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($lastDayColIndex + 1) . '2', 'CÔNG')->mergeCells(Coordinate::stringFromColumnIndex($lastDayColIndex + 1) . '2:' . Coordinate::stringFromColumnIndex($lastDayColIndex + 1) . '3');
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($lastDayColIndex + 2) . '2', 'GHI CHÚ')->mergeCells(Coordinate::stringFromColumnIndex($lastDayColIndex + 2) . '2:' . Coordinate::stringFromColumnIndex($lastDayColIndex + 2) . '3');
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($lastDayColIndex + 3) . '2', 'TỔNG NGÀY NGHỈ')->mergeCells(Coordinate::stringFromColumnIndex($lastDayColIndex + 3) . '2:' . Coordinate::stringFromColumnIndex($lastDayColIndex + 3) . '3');

            // Style header
            $headerRange = 'A2:' . $sheet->getHighestColumn() . '3';
            $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFA500');
            $sheet->getStyle($headerRange)->getFont()->setBold(true);
            $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

            // Dữ liệu
            $rowIndex = 4;
            foreach ($personnel_list as $index => $personnel) {
                $pid = $personnel['id'];
                $sheet->setCellValue('A' . $rowIndex, $index + 1);
                $sheet->setCellValue('B' . $rowIndex, $personnel['name']);

                $totalWork = 0;
                $offCount = 0;

                foreach (range(1, $totalDays) as $day) {
                    $current_date_str = "$year-$month-" . str_pad($day, 2, '0', STR_PAD_LEFT);
                    $value = $roster_map[$pid][$current_date_str] ?? '';

                    if ($value === 'OFF') {
                        $offCount++;
                    } elseif (is_numeric($value)) {
                        $totalWork += $value;
                    }

                    $colLetter = Coordinate::stringFromColumnIndex(2 + $day);
                    $sheet->setCellValue($colLetter . $rowIndex, $value);
                }

                // Công / Ghi chú / Ngày nghỉ
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($lastDayColIndex + 1) . $rowIndex, $totalWork);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($lastDayColIndex + 2) . $rowIndex, '');
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($lastDayColIndex + 3) . $rowIndex, $offCount);

                $rowIndex++;
            }

            // Viền bảng
            $lastColLetter = $sheet->getHighestColumn();
            $sheet->getStyle('A2:' . $lastColLetter . ($rowIndex - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            // Độ rộng & chiều cao
            $sheet->getColumnDimension('A')->setAutoSize(true);
            $sheet->getColumnDimension('B')->setWidth(30);
            foreach (range(1, $totalDays) as $day) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex(2 + $day))->setWidth(5);
            }
            foreach (range(1, 3) as $i) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($lastDayColIndex + $i))->setAutoSize(true);
            }
            $sheet->getRowDimension(2)->setRowHeight(20);
            $sheet->getRowDimension(3)->setRowHeight(60);

            // Xuất file
            ob_end_clean();
            $filename = "BangPhanCong_Thang_{$month}_{$year}.xlsx";
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        } catch (Exception $e) {
            ob_end_clean();
            http_response_code(500);
            exit("Lỗi: " . $e->getMessage());
        }
    })->setPermissions(['rosters']);

    $app->router("/rosters-add", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $accStore) {
        $vars['title'] = $jatbi->lang("Thêm Bảng phân công");
        if ($app->method() === 'GET') {
            $vars['personnels'] = $app->select("personnels", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "stores" => $accStore, "ORDER" => ["name" => "ASC"]]);
            $vars['timework'] = array_merge(
                [["value" => "", "text" => $jatbi->lang("Chọn")]],
                $app->select("timework", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "ORDER" => ["name" => "ASC"]])
            );
            echo $app->render($template . '/hrm/rosters-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            if ($app->xss($_POST['personnels']) == '' || $app->xss($_POST['date']) == '' || $app->xss($_POST['timework']) == '') {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
            } else {
                $insert = [
                    "personnels" => $app->xss($_POST['personnels']),
                    "timework" => $app->xss($_POST['timework']),
                    "date" => $app->xss($_POST['date']),
                    "notes" => $app->xss($_POST['notes']),
                    "user" => $app->getSession("accounts")['id'] ?? null,
                    "date_poster" => date("Y-m-d H:i:s"),
                ];
                $app->insert("rosters", $insert);
                $jatbi->logs('hrm', 'rosters-add', [$insert]);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            }
        }
    })->setPermissions(['rosters.add']);

    $app->router("/rosters-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $accStore) {
        $vars['title'] = $jatbi->lang("Sửa Bảng phân công");
        if ($app->method() === 'GET') {
            $vars['data'] = $app->get("rosters", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($vars['data'])) {
                $vars['personnels'] = $app->select("personnels", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "stores" => $accStore, "ORDER" => ["name" => "ASC"]]);
                $vars['timework'] = $app->select("timework", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "ORDER" => ["name" => "ASC"]]);
                echo $app->render($template . '/hrm/rosters-post.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $data = $app->get("rosters", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($data)) {
                if ($app->xss($_POST['personnels']) == '' || $app->xss($_POST['date']) == '' || $app->xss($_POST['timework']) == '') {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
                } else {
                    $insert = [
                        "personnels" => $app->xss($_POST['personnels']),
                        "timework" => $app->xss($_POST['timework']),
                        "date" => $app->xss($_POST['date']),
                        "notes" => $app->xss($_POST['notes']),
                        "user" => $app->getSession("accounts")['id'] ?? null,
                        "date_poster" => date("Y-m-d H:i:s"),
                    ];
                    $app->update("rosters", $insert, ["id" => $data['id']]);
                    $jatbi->logs('hrm', 'rosters-edit', [$insert]);
                    echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
                }
            } else {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
            }
        }
    })->setPermissions(['rosters.edit']);

    $app->router("/rosters-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa Bảng phân công");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("rosters", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("rosters", ["deleted" => 1], ["id" => $data['id']]);
                }
                $jatbi->logs('hrm', 'rosters-deleted', $datas);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['rosters.deleted']);

    // hop dong lao dong 
    $app->router('/contract', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $setting, $accStore) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Hợp đồng lao động");
            $vars['personnels'] = $app->select("personnels", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "stores" => $accStore, "ORDER" => ["name" => "ASC"]]);
            $vars['offices'] = $app->select("offices", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "ORDER" => ["name" => "ASC"]]);
            $vars['contract_type'] = [
                ["value" => 1, "text" => $jatbi->lang("Thử việc")],
                ["value" => 2, "text" => $jatbi->lang("Chính thức lần 1")],
                ["value" => 3, "text" => $jatbi->lang("Chính thức lần 2")],
                ["value" => 4, "text" => $jatbi->lang("Không xác định thời hạn")],
            ];
            echo $app->render($template . '/hrm/contract.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : $setting['site_page'] ?? 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'personnels_contract.id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $personnels = isset($_POST['personnels']) ? $_POST['personnels'] : '';
            $type = isset($_POST['type']) ? $_POST['type'] : '';
            $offices = isset($_POST['offices']) ? $_POST['offices'] : '';

            $joins = [
                "[>]personnels" => ["personnels" => "id"],
                "[>]offices" => ["offices" => "id"],
            ];
            $where = [
                "AND" => [
                    "OR" => [
                        'personnels.name[~]' => $searchValue,
                        'personnels_contract.code[~]' => $searchValue,
                    ],
                    "personnels_contract.deleted" => 0,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];

            if (!empty($personnels))
                $where['AND']['personnels.id'] = $personnels;
            if (!empty($type))
                $where['AND']['personnels_contract.type'] = $type;
            if (!empty($offices))
                $where['AND']['offices.id'] = $offices;

            $count = $app->count("personnels_contract", $joins, ['personnels_contract.id'], $where['AND']);
            $datas = [];

            $app->select("personnels_contract", $joins, [
                'personnels_contract.id',
                'personnels_contract.code',
                'personnels_contract.salary',
                'personnels_contract.duration',
                'personnels_contract.workday',
                'personnels_contract.type',
                'personnels_contract.date_contract',
                'personnels_contract.date_end',
                'personnels.name(personnel_name)',
                'offices.name(office_name)'
            ], $where, function ($data) use (&$datas, $jatbi, $app, $setting) {

                $start_date = date("Y-m-d", strtotime($data['date_contract']));
                $end_date = date("Y-m-d", strtotime($start_date . " +" . $data['duration'] . " month"));
                $diff_seconds = strtotime($end_date) - strtotime(date("Y-m-d"));
                $remaining_days = round($diff_seconds / (60 * 60 * 24));

                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "personnel_name" => $data['personnel_name'] ?? "",
                    "office_name" => $data['office_name'] ?? "",
                    "type" => $setting['personnels_contracts'][$data['type']]['name'] ?? "",
                    "code" => $data['code'] ?? "",
                    "salary" => number_format($data['salary'] ?? 0),
                    "duration" => ($data['duration'] . " " . $jatbi->lang("Tháng")),
                    "date_end" => $jatbi->count_date(date("Y-m-d"), date("Y-m-d", strtotime(date("Y-m-d", strtotime($data['date_contract'])) . " +" . $data['duration'] . " month"))),
                    'workday' => date("d/m/Y", timestamp: strtotime(datetime: $data['workday'])),
                    "action" => ($app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xem"),
                                'permission' => ['contract'],
                                'action' => ['data-url' => '/hrm/contract-view/' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['contract.edit'],
                                'action' => ['data-url' => '/hrm/contract-edit/' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'permission' => ['contract.deleted'],
                                'action' => ['data-url' => '/hrm/contract-deleted?box=' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("In"),
                                'permission' => ['contract'],
                                'action' => ['data-url' => '/hrm/contract-print/' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                        ]
                    ]))
                ];
            });

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas,
            ]);
        }
    })->setPermissions(['contract']);

    $app->router('/contract-excel', 'GET', function ($vars) use ($app, $setting, $stores) {
        try {
            $store_ids = array_column($stores, 'value');
            $personnels_filter = $_GET['personnels'] ?? '';
            $type_filter = $_GET['type'] ?? '';
            $offices_filter = $_GET['offices'] ?? '';

            $joins = [
                "[><]personnels(p)" => ["pc.personnels" => "id"],
                "[>]offices(o)" => ["p.office" => "id"],
                // "[>]branch(b)" => ["p.stores" => "id"],
                "[>]personnels_insurrance(pi)" => ["p.id" => "personnels"],
                "[>]ward(w)" => ["p.ward" => "id"],
                "[>]district(d)" => ["p.district" => "id"],
                "[>]province(pv)" => ["p.province" => "id"]
            ];

            $where = ["AND" => ["pc.deleted" => 0, "p.deleted" => 0, "p.stores" => $store_ids], "ORDER" => ["p.name" => "ASC"]];
            if (!empty($personnels_filter))
                $where['AND']['p.id'] = $personnels_filter;
            if (!empty($type_filter))
                $where['AND']['pc.type'] = $type_filter;
            if (!empty($offices_filter))
                $where['AND']['p.office'] = $offices_filter;

            $columns = [
                "p.code(ma_nv)",
                "p.name(ho_ten)",
                "p.birthday(ngay_sinh)",
                "p.gender(gioi_tinh)",
                "p.nation(dan_toc)",
                "dia_chi" => $app->raw("CONCAT_WS(', ', p.address, w.name, d.name, pv.name)"),
                "p.phone(sdt)",
                "p.idcode(cccd)",
                "p.iddate(ngay_cap_cccd)",
                "p.idplace(noi_cap_cccd)",
                "o.name(phong_ban)",
                // "b.name(phong_ban)",
                "pc.code(so_hop_dong)",
                "pc.type(loai_hd)",
                "pc.date_contract(ngay_ky_hd)",
                "pc.date_end(ngay_het_han_hd)",
                "pc.salary(muc_luong)",
                "pi.social_date(ngay_dong_bhxh)"
            ];

            $employee_data = $app->select("personnels_contract(pc)", $joins, $columns, $where);

            if (empty($employee_data))
                exit("Không có dữ liệu hợp đồng thỏa mãn điều kiện.");

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('SoTheoDoiLaoDong');

            // Style Header
            $header_style = [
                'font' => [
                    'bold' => true,
                    'size' => 10,
                    'name' => 'Times New Roman',
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => false, // không cho xuống dòng
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000'],
                    ],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFFF00'], // vàng nhạt
                ],
            ];

            // Style Dữ liệu
            $data_style = [
                'font' => ['name' => 'Times New Roman', 'size' => 10],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => false],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]]
            ];

            // Tiêu đề trên cùng
            $sheet->setCellValue('A1', 'CHI NHÁNH CÔNG TY TNHH MVT NGỌC TRAI NGỌC HIỀN PHÚ QUỐC - NGỌC HIỀN SEA')->mergeCells('A1:R1');
            $sheet->setCellValue('A2', 'Địa chỉ: Tổ 1, khu phố Hòn Rỏi, Đặc khu Phú Quốc, tỉnh An Giang')->mergeCells('A2:R2');
            $sheet->getStyle('A1')->getFont()->setName('Times New Roman')->setBold(true)->setSize(12);
            $sheet->getStyle('A2')->getFont()->setName('Times New Roman')->setItalic(true)->setSize(10);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

            // Header chính
            $sheet->setCellValue('A4', 'STT')->mergeCells('A4:A5');
            $sheet->setCellValue('B4', 'MÃ NHÂN VIÊN')->mergeCells('B4:B5');
            $sheet->setCellValue('C4', 'HỌ VÀ TÊN')->mergeCells('C4:C5');
            $sheet->setCellValue('D4', 'SỐ HỢP ĐỒNG')->mergeCells('D4:D5');
            $sheet->setCellValue('E4', 'NĂM SINH')->mergeCells('E4:E5');
            $sheet->setCellValue('F4', 'GIỚI TÍNH')->mergeCells('F4:F5');
            $sheet->setCellValue('G4', 'DÂN TỘC')->mergeCells('G4:G5');
            $sheet->setCellValue('H4', 'ĐỊA CHỈ')->mergeCells('H4:H5');
            $sheet->setCellValue('I4', 'CCCD')->mergeCells('I4:I5');
            $sheet->setCellValue('J4', 'CẤP NGÀY')->mergeCells('J4:J5');
            $sheet->setCellValue('K4', 'SỐ ĐIỆN THOẠI')->mergeCells('K4:K5');
            $sheet->setCellValue('L4', 'LOẠI HỢP ĐỒNG')->mergeCells('L4:L5');
            $sheet->setCellValue('M4', 'FORM')->mergeCells('M4:M5');
            $sheet->setCellValue('N4', 'TO')->mergeCells('N4:N5');
            $sheet->setCellValue('O4', 'PHÒNG BAN')->mergeCells('O4:O5');
            $sheet->setCellValue('P4', 'CHỨC DANH')->mergeCells('P4:P5');
            $sheet->setCellValue('Q4', 'MỨC LƯƠNG')->mergeCells('Q4:Q5');
            $sheet->setCellValue('R4', 'THỜI ĐIỂM BẮT ĐẦU ĐÓNG BHXH')->mergeCells('R4:R5');

            $sheet->getStyle('A4:R5')->applyFromArray($header_style);

            // Ghi dữ liệu
            $rowIndex = 6;
            foreach ($employee_data as $index => $employee) {
                $sheet->setCellValue('A' . $rowIndex, $index + 1);
                $sheet->setCellValue('B' . $rowIndex, $employee['ma_nv']);
                $sheet->setCellValue('C' . $rowIndex, $employee['ho_ten']);
                $sheet->setCellValue('D' . $rowIndex, $employee['so_hop_dong']);
                $sheet->setCellValue('E' . $rowIndex, $employee['ngay_sinh'] ?? "");
                $sheet->setCellValue('F' . $rowIndex, $employee['gioi_tinh'] == 1 ? 'Nam' : ($employee['gioi_tinh'] == 2 ? 'Nữ' : ''));
                $sheet->setCellValue('G' . $rowIndex, $employee['dan_toc']);
                $sheet->setCellValue('H' . $rowIndex, $employee['dia_chi']);
                $sheet->setCellValue('I' . $rowIndex, $employee['cccd']);
                $sheet->setCellValue('J' . $rowIndex, $employee['ngay_cap_cccd'] ?? "");
                $sheet->setCellValue('K' . $rowIndex, $employee['sdt']);
                $sheet->setCellValue('L' . $rowIndex, $setting['personnels_contracts'][$employee['loai_hd']]['name'] ?? 'Không rõ');
                $sheet->setCellValue('M' . $rowIndex, !empty($employee['ngay_ky_hd']) ? \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($employee['ngay_ky_hd']) : '');
                $sheet->setCellValue('N' . $rowIndex, !empty($employee['ngay_het_han_hd']) ? \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($employee['ngay_het_han_hd']) : '');
                $sheet->setCellValue('O' . $rowIndex, $employee['phong_ban']);
                $sheet->setCellValue('P' . $rowIndex, $employee['chuc_danh']);
                $sheet->setCellValue('Q' . $rowIndex, $employee['muc_luong']);
                $sheet->setCellValue('R' . $rowIndex, !empty($employee['ngay_dong_bhxh']) ? \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($employee['ngay_dong_bhxh']) : '');
                $rowIndex++;
            }

            $lastRow = $rowIndex - 1;
            if ($lastRow >= 6) {
                $sheet->getStyle('A6:R' . $lastRow)->applyFromArray($data_style);

                // Format ngày
                $sheet->getStyle('E6:E' . $lastRow)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
                $sheet->getStyle('J6:J' . $lastRow)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
                $sheet->getStyle('M6:N' . $lastRow)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
                $sheet->getStyle('R6:R' . $lastRow)->getNumberFormat()->setFormatCode('dd/mm/yyyy');

                // Format lương
                $sheet->getStyle('Q6:Q' . $lastRow)->getNumberFormat()->setFormatCode('#,##0');

                // Căn chỉnh
                $sheet->getStyle('A6:G' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('I6:J' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('Q6:R' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('C6:C' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle('H6:H' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            }

            // Chiều rộng cột
            $sheet->getColumnDimension('C')->setWidth(30);
            $sheet->getColumnDimension('H')->setWidth(40);
            foreach (['A', 'B', 'D', 'E', 'F', 'G', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R'] as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Trang in
            $sheet->getPageSetup()
                ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                ->setPaperSize(PageSetup::PAPERSIZE_A3)
                ->setFitToWidth(1)
                ->setFitToHeight(0);

            ob_end_clean();
            $filename = "SoTheoDoiLaoDong_" . date('d-m-Y') . ".xlsx";
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        } catch (Exception $e) {
            ob_end_clean();
            http_response_code(500);
            exit("Lỗi: " . $e->getMessage());
        }
    })->setPermissions(['contract']);

    $app->router("/contract-add", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $accStore) {
        $vars['title'] = $jatbi->lang("Thêm Hợp đồng lao động");
        if ($app->method() === 'GET') {
            $vars['personnels'] = $app->select("personnels", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "stores" => $accStore, "ORDER" => ["name" => "ASC"]]);
            $vars['offices'] = array_merge(
                [["value" => "", "text" => $jatbi->lang("Chọn")]],
                $app->select("offices", ["id (value)", "name (text)"], [
                    "deleted" => 0,
                    "status" => "A",
                    "ORDER" => ["name" => "ASC"]
                ])
            );

            $vars['positions'] = array_merge(
                [["value" => "", "text" => $jatbi->lang("Chọn chức vụ")]],
                $app->select("hrm_positions", ["id (value)", "name (text)"], [
                    "deleted" => 0,
                    "status" => "A",
                    "ORDER" => ["name" => "ASC"]
                ])
            );
            $vars['contract_type'] = [
                ["value" => "", "text" => $jatbi->lang("Chọn")],
                ["value" => 1, "text" => $jatbi->lang("Thử việc")],
                ["value" => 2, "text" => $jatbi->lang("Chính thức lần 1")],
                ["value" => 3, "text" => $jatbi->lang("Chính thức lần 2")],
                ["value" => 4, "text" => $jatbi->lang("Không xác định thời hạn")],
            ];
            $vars['salary_categorys'] = array_merge(
                [["value" => "", "text" => $jatbi->lang("Chọn")]],
                $app->select("salary_categorys", ["id (value)", "name (text)", "type"], [
                    "type" => 1,
                    "deleted" => 0,
                    "status" => "A",
                    "ORDER" => ["name" => "ASC"]
                ])
            );
            $vars['allowance_categorys'] = array_merge(
                [["value" => "", "text" => $jatbi->lang("Chọn")]],
                $app->select("salary_categorys", ["id (value)", "name (text)"], [
                    "type" => 2,
                    "deleted" => 0,
                    "status" => "A",
                    "ORDER" => ["name" => "ASC"]
                ])
            );
            $vars['data'] = [];
            echo $app->render($template . '/hrm/contract-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $date_contract = $app->xss($_POST['date_contract']);
            $duration = (int) $app->xss($_POST['duration']);

            // Tính toán ngày kết thúc
            $date_end = date('Y-m-d', strtotime($date_contract . " + " . $duration . " months"));
            if ($app->xss($_POST['personnels']) == '' || $app->xss($_POST['date_contract']) == '' || $app->xss($_POST['type']) == '' || $app->xss($_POST['code']) == '' || $app->xss($_POST['offices']) == '' || $app->xss($_POST['positions']) == '') {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
                return;
            }
            // if ($app->xss($_POST['date_contract']) > $app->xss($_POST['date_end'])) {
            //     echo json_encode(["status" => "error", "content" => $jatbi->lang("Ngày kết thúc phải lơn hơn ngày hợp đoòng")]);
            //     return;
            // }
            $is_overlapping = $app->has("personnels_contract", [
                "AND" => [
                    "personnels" => $app->xss($_POST['personnels']),
                    "deleted" => 0,
                    "date_contract[<=]" => $date_end,
                    "date_end[>=]" => $date_contract,
                ]
            ]);

            if ($is_overlapping) {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Thời hạn hợp đồng bị trùng với hợp đồng trước đó")]);
                return;
            }
            // $values = [];
            // foreach ($_POST['salary_content'] as $key => $value) {
            //     if ($_POST['salary_content'][$key] != '' && $_POST['salary_deleted'][$key] == '') {
            //         if ($_POST['salary_price'][$key] < 0) {
            //             echo json_encode(["status" => "error", "content" => $jatbi->lang("Số tiền không hợp lệ")]);
            //             return;
            //         }
            //         if (in_array($_POST['salary_content'][$key], $values)) {
            //             echo json_encode(["status" => "error", "content" => $jatbi->lang("Loại tiền lương bị trùng") . $key]);
            //             return;
            //         }
            //         $values[] = $_POST['salary_content'][$key];
            //     }
            // }


            $insert = [
                "personnels" => $app->xss($_POST['personnels']),
                "offices" => $app->xss($_POST['offices']),
                "position" => $app->xss($_POST['positions']),
                "degree" => $app->xss($_POST['degree']),
                "certificate" => $app->xss($_POST['certificate']),
                "health" => $app->xss($_POST['health']),
                "workday" => $app->xss($_POST['workday']),
                "interview" => $app->xss($_POST['interview']),
                "type" => $app->xss($_POST['type']),
                "code" => $app->xss($_POST['code']),
                "date_contract" => $app->xss($_POST['date_contract']),
                "duration" => $app->xss($_POST['duration']),
                "date_end" => $date_end,
                // "salary_date" => date("Y-m-01", strtotime($app->xss($_POST['salary_date']))),
                "salary" => $app->xss(str_replace([','], '', $_POST['salary'])),
                // "salary_eat" 		=> $app->xss(str_replace([','],'',$_POST['salary_eat'])),
                // "salary_diligence"	=> $app->xss(str_replace([','],'',$_POST['salary_diligence'])),
                // "salary_overtime" 	=> $app->xss(str_replace([','],'',$_POST['salary_overtime'])),
                "notes" => $app->xss($_POST['notes']),
                "date" => date("Y-m-d H:i:s"),
                "user" => $app->getSession("accounts")['id'] ?? null,
            ];
            $app->insert("personnels_contract", $insert);

            $GetID = $app->id();
            // $salary = [
            //     "personnels" => $app->xss($_POST['personnels']),
            //     "contract" => $GetID,
            //     "salary_date" => date("Y-m-01", strtotime($app->xss($_POST['salary_date']))),
            //     "date_poster" => date("Y-m-d H:i:s"),
            //     "user" => $app->getSession("accounts")['id'] ?? null,
            // ];
            // $app->insert("personnels_contract_salary", $salary);
            $GetIDSalary = $app->id();
            // foreach ($_POST['salary_content'] as $key => $value) {
            //     $getSalary = $app->get("salary_categorys", "*", ["id" => $value, "deleted" => 0]);
            //     if ($getSalary > 1) {
            //         if ($_POST['salary_deleted'][$key] == '') {
            //             $salary_details = [
            //                 "contract" => $GetID,
            //                 "salary" => $GetIDSalary,
            //                 "type" => $getSalary['type'],
            //                 "duration" => $getSalary['duration'],
            //                 "salary_date" => date("Y-m-01", strtotime($app->xss($_POST['salary_date']))),
            //                 "personnels" => $app->xss($_POST['personnels']),
            //                 "content" => $getSalary['id'],
            //                 "price" => $app->xss(str_replace([','], '', $_POST['salary_price'][$key])),
            //                 // "date"			=> $_POST['salary_detail_date'][$key],
            //                 "notes" => $_POST['salary_notes'][$key],
            //             ];
            //             $salary_details_logs[] = $salary_details;
            //             $app->insert("personnels_contract_salary_details", $salary_details);
            //         }
            //     }
            // }

            $app->update("personnels", [
                "office" => $insert['offices'],
                // "positions" => $insert['positions'], // Cập nhật luôn chức vụ cho nhân sự
            ], ["id" => $insert['personnels']]);

            $jatbi->logs('hrm', 'contract-add', [$insert, "", $salary_details_logs ?? []]);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        }
    })->setPermissions(['contract.add']);

    $app->router("/contract-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $accStore) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Sửa Hợp đồng lao động");
            $vars['data'] = $app->get("personnels_contract", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($vars['data'])) {
                $vars['personnels'] = $app->select("personnels", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "stores" => $accStore, "ORDER" => ["name" => "ASC"]]);
                $vars['offices'] = array_merge(
                    [["value" => "", "text" => $jatbi->lang("Chọn")]],
                    $app->select("offices", ["id (value)", "name (text)"], [
                        "deleted" => 0,
                        "status" => "A",
                        "ORDER" => ["name" => "ASC"]
                    ])
                );
                $vars['positions'] = array_merge(
                    [["value" => "", "text" => $jatbi->lang("Chọn chức vụ")]],
                    $app->select("hrm_positions", ["id (value)", "name (text)"], [
                        "deleted" => 0,
                        "status" => "A",
                        "ORDER" => ["name" => "ASC"]
                    ])
                );
                $vars['contract_type'] = [
                    ["value" => "", "text" => $jatbi->lang("Chọn")],
                    ["value" => 1, "text" => $jatbi->lang("Thử việc")],
                    ["value" => 2, "text" => $jatbi->lang("Chính thức lần 1")],
                    ["value" => 3, "text" => $jatbi->lang("Chính thức lần 2")],
                    ["value" => 4, "text" => $jatbi->lang("Không xác định thời hạn")],
                ];
                $vars['salary_categorys'] = array_merge(
                    [["value" => "", "text" => $jatbi->lang("Chọn")]],
                    $app->select("salary_categorys", ["id (value)", "name (text)"], [
                        "type" => 1,
                        "deleted" => 0,
                        "status" => "A",
                        "ORDER" => ["name" => "ASC"]
                    ])
                );
                $vars['allowance_categorys'] = array_merge(
                    [["value" => "", "text" => $jatbi->lang("Chọn")]],
                    $app->select("salary_categorys", ["id (value)", "name (text)"], [
                        "type" => 2,
                        "deleted" => 0,
                        "status" => "A",
                        "ORDER" => ["name" => "ASC"]
                    ])
                );
                $vars['dataSalary'] = $app->get("personnels_contract_salary", "*", ["deleted" => 0, "contract" => $vars['data']['id']]);
                $vars['salarys'] = $app->select("personnels_contract_salary_details", "*", ["type" => 1, "contract" => $vars['data']['id'], "status" => 0, "salary" => $vars['dataSalary']['id'], "deleted" => 0]);
                $vars['allowances'] = $app->select("personnels_contract_salary_details", "*", ["type" => 2, "contract" => $vars['data']['id'], "status" => 0, "salary" => $vars['dataSalary']['id'], "deleted" => 0]);

                echo $app->render($template . '/hrm/contract-post.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
            }
        }
        if ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $data = $app->get("personnels_contract", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($data)) {
                $date_contract = $app->xss($_POST['date_contract']);
                $duration = (int) $app->xss($_POST['duration']);

                // Tính toán ngày kết thúc
                $date_end = date('Y-m-d', strtotime($date_contract . " + " . $duration . " months"));
                if ($app->xss($_POST['personnels']) == '' || $app->xss($_POST['date_contract']) == '' || $app->xss($_POST['type']) == '' || $app->xss($_POST['code']) == '' || $app->xss($_POST['offices']) == '' || $app->xss($_POST['positions']) == '') {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
                    return;
                }
                if ($app->xss($_POST['date_contract']) >  $date_end) {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Ngày kết thúc phải lơn hơn ngày hợp đoòng")]);
                    return;
                }
                if (
                    $app->has("personnels_contract", [
                        "id[!]" => $data['id'],
                        "personnels" => $app->xss($_POST['personnels']),
                        "deleted" => 0,
                        "OR" => [
                            "date_contract[<>]" => [$app->xss($_POST['date_contract']), $date_end],
                            "date_end[<>]" => [$app->xss($_POST['date_contract']), $date_end],
                        ]
                    ])
                ) {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Thời hạn hợp đồng bị trùng với hợp đồng trước đó")]);
                    return;
                }
                // $values = [];
                // foreach ($_POST['salary_content'] as $key => $value) {
                //     if ($_POST['salary_content'][$key] != '' && $_POST['salary_deleted'][$key] == '') {
                //         if ($_POST['salary_price'][$key] < 0) {
                //             echo json_encode(["status" => "error", "content" => $jatbi->lang("Số tiền không hợp lệ")]);
                //             return;
                //         }
                //         if (in_array($_POST['salary_content'][$key], $values)) {
                //             echo json_encode(["status" => "error", "content" => $jatbi->lang("Loại tiền lương bị trùng") . $key]);
                //             return;
                //         }
                //         $values[] = $_POST['salary_content'][$key];
                //     }
                // }

                $insert = [
                    "personnels" => $app->xss($_POST['personnels']),
                    "offices" => $app->xss($_POST['offices']),
                    "position" => $app->xss($_POST['positions']),
                    "degree" => $app->xss($_POST['degree']),
                    "certificate" => $app->xss($_POST['certificate']),
                    "health" => $app->xss($_POST['health']),
                    "workday" => $app->xss($_POST['workday']),
                    "interview" => $app->xss($_POST['interview']),
                    "type" => $app->xss($_POST['type']),
                    "code" => $app->xss($_POST['code']),
                    "date_contract" => $app->xss($_POST['date_contract']),
                    "date_end" => $date_end,
                    // "salary_date" => date("Y-m-01", strtotime($app->xss($_POST['salary_date']))),
                    "salary"             => $app->xss(str_replace([','], '', $_POST['salary'])),
                    // "salary_eat" 		=> $app->xss(str_replace([','],'',$_POST['salary_eat'])),
                    // "salary_diligence"	=> $app->xss(str_replace([','],'',$_POST['salary_diligence'])),
                    // "salary_overtime" 	=> $app->xss(str_replace([','],'',$_POST['salary_overtime'])),
                    "notes" => $app->xss($_POST['notes']),
                    "date" => date("Y-m-d H:i:s"),
                    "user" => $app->getSession("accounts")['id'] ?? null,
                ];
                $app->update("personnels_contract", $insert, ["id" => $data['id']]);
                // $salary = [
                //     "personnels" => $app->xss($_POST['personnels']),
                //     "contract" => $data['id'],
                //     "salary_date" => date("Y-m-01", strtotime($app->xss($_POST['salary_date']))),
                //     "date_poster" => date("Y-m-d H:i:s"),
                //     "user" => $app->getSession("accounts")['id'] ?? null,
                // ];
                // $dataSalary = $app->get("personnels_contract_salary", "*", ["deleted" => 0, "contract" => $data['id'], "status" => 0, "ORDER" => ["id" => "DESC"]]);
                // if ($dataSalary > 1) {
                //     $app->update("personnels_contract_salary", $salary, ["id" => $dataSalary['id']]);
                //     $GetIDSalary = $dataSalary['id'];
                // } else {
                //     $app->insert("personnels_contract_salary", $salary);
                //     $GetIDSalary = $app->id();
                // }
                // foreach ($_POST['salary_content'] as $key => $value) {
                //     $getSalary = $app->get("salary_categorys", "*", ["id" => $value, "deleted" => 0]);
                //     if ($getSalary > 1) {
                //         $salary_details = [
                //             "contract" => $data['id'],
                //             "salary" => $GetIDSalary,
                //             "type" => $getSalary['type'],
                //             "duration" => $getSalary['duration'],
                //             "salary_date" => date("Y-m-01", strtotime($app->xss($_POST['salary_date']))),
                //             "personnels" => $app->xss($_POST['personnels']),
                //             "content" => $getSalary['id'],
                //             "price" => $app->xss(str_replace([','], '', $_POST['salary_price'][$key])),
                //             // "date"			=> $_POST['salary_detail_date'][$key],
                //             "notes" => $_POST['salary_notes'][$key],
                //         ];
                //         $salary_details_logs[] = $salary_details;
                //         if ($_POST['salary_deleted'][$key] == '' && $_POST['salary_id'][$key] == '') {
                //             $app->insert("personnels_contract_salary_details", $salary_details);
                //         } elseif ($_POST['salary_deleted'][$key] == '' && $_POST['salary_id'][$key] != '') {
                //             $app->update("personnels_contract_salary_details", $salary_details, ["id" => $_POST['salary_id'][$key], "contract" => $data['id'], "salary" => $GetIDSalary]);
                //         } elseif ($_POST['salary_deleted'][$key] == '1' && $_POST['salary_id'][$key] != '') {
                //             $app->update("personnels_contract_salary_details", ["deleted" => 1], ["id" => $_POST['salary_id'][$key], "contract" => $data['id'], "salary" => $GetIDSalary]);
                //         }
                //     }
                // }
                $jatbi->logs('hrm', 'insurrance-edit', [$insert, $salary_details_logs ?? []]);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
            }
        }
    })->setPermissions(['contract.edit']);

    $app->router("/contract-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa Hợp đồng lao động");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("personnels_contract", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("personnels_contract", ["deleted" => 1], ["id" => $data['id']]);
                }
                $jatbi->logs('hrm', 'contract-deleted', $datas);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['contract.deleted']);

    $app->router("/contract-view/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {
        $vars['title'] = $jatbi->lang("Hợp đồng lao động");
        if ($app->method() === 'GET') {
            $vars['data'] = $app->get("personnels_contract", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($vars['data'])) {
                $vars['dataSalary'] = $app->get("personnels_contract_salary", "*", ["deleted" => 0, "contract" => $vars['data']['id'], "status" => 0, "ORDER" => ["id" => "DESC"]]);
                $vars['salarys'] = $app->select("personnels_contract_salary_details", "*", ["type" => 1, "contract" => $vars['data']['id'], "status" => 0, "salary" => $vars['dataSalary']['id'], "deleted" => 0]);
                $vars['allowances'] = $app->select("personnels_contract_salary_details", "*", ["type" => 2, "contract" => $vars['data']['id'], "status" => 0, "salary" => $vars['dataSalary']['id'], "deleted" => 0]);
                $vars['personnels_contracts'] = $setting['personnels_contracts'][$vars['data']['type']]['name'];
                $vars['personnel_name'] = $app->get("personnels", "name", ["id" => $vars['data']['personnels']]);
                echo $app->render($template . '/hrm/contract-view.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
            }
        }
    })->setPermissions(['contract.add']);

    $app->router("/contract-salary-update/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        if ($app->method() === 'GET') {
            $vars['data'] = $app->get("personnels_contract", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($vars['data'])) {
                $vars['salary_categorys'] = array_merge(
                    [["value" => "", "text" => $jatbi->lang("Chọn")]],
                    $app->select("salary_categorys", ["id (value)", "name (text)"], [
                        "type" => 1,
                        "deleted" => 0,
                        "status" => "A",
                        "ORDER" => ["name" => "ASC"]
                    ])
                );
                $vars['allowance_categorys'] = array_merge(
                    [["value" => "", "text" => $jatbi->lang("Chọn")]],
                    $app->select("salary_categorys", ["id (value)", "name (text)"], [
                        "type" => 2,
                        "deleted" => 0,
                        "status" => "A",
                        "ORDER" => ["name" => "ASC"]
                    ])
                );
                $vars['dataSalary'] = $app->get("personnels_contract_salary", "*", ["deleted" => 0, "contract" => $vars['data']['id']]);
                $vars['salarys'] = $app->select("personnels_contract_salary_details", "*", ["type" => 1, "contract" => $vars['data']['id'], "status" => 0, "salary" => $vars['dataSalary']['id'], "deleted" => 0]);
                $vars['allowances'] = $app->select("personnels_contract_salary_details", "*", ["type" => 2, "contract" => $vars['data']['id'], "status" => 0, "salary" => $vars['dataSalary']['id'], "deleted" => 0]);
                $vars['title'] = $jatbi->lang("Cập nhật Lương");
                echo $app->render($template . '/hrm/contract-salary-update.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
            }
        }
        if ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $data = $app->get("personnels_contract", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($data)) {
                if ($app->xss($_POST['content']) == '' || $app->xss($_POST['salary_date']) == '') {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
                } else {
                    $dataSalary = $app->get("personnels_contract_salary", "*", ["deleted" => 0, "contract" => $data['id']]);
                    $app->update("personnels_contract_salary", ["status" => 1], ["id" => $dataSalary['id']]);
                    $app->update("personnels_contract_salary_details", ["status" => 1], ["salary" => $dataSalary['id']]);
                    $salary = [
                        "personnels" => $data['personnels'],
                        "contract" => $data['id'],
                        "salary_date" => date("Y-m-01", strtotime($app->xss($_POST['salary_date']))),
                        "date_poster" => date("Y-m-d H:i:s"),
                        "user" => $app->getSession("accounts")['id'] ?? null,
                    ];
                    $app->insert("personnels_contract_salary", $salary);
                    $GetIDSalary = $app->id();
                    foreach ($_POST['salary_content'] as $key => $value) {
                        $getSalary = $app->get("salary_categorys", "*", ["id" => $value, "deleted" => 0]);
                        if ($getSalary > 1) {
                            $salary_details = [
                                "contract" => $data['id'],
                                "salary" => $GetIDSalary,
                                "type" => $getSalary['type'],
                                "duration" => $getSalary['duration'],
                                "salary_date" => date("Y-m-01", strtotime($app->xss($_POST['salary_date']))),
                                "personnels" => $app->xss($_POST['personnels']),
                                "content" => $getSalary['id'],
                                "price" => $app->xss(str_replace([','], '', $_POST['salary_price'][$key])),
                                "notes" => $_POST['salary_notes'][$key],
                            ];
                            $salary_details_logs[] = $salary_details;
                            if ($_POST['salary_deleted'][$key] == '' && $_POST['salary_id'][$key] == '') {
                                $app->insert("personnels_contract_salary_details", $salary_details);
                            } elseif ($_POST['salary_deleted'][$key] == '' && $_POST['salary_id'][$key] != '') {
                                $app->update("personnels_contract_salary_details", $salary_details, ["id" => $_POST['salary_id'][$key], "contract" => $data['id'], "salary" => $GetIDSalary]);
                            } elseif ($_POST['salary_deleted'][$key] == '1' && $_POST['salary_id'][$key] != '') {
                                $app->update("personnels_contract_salary_details", ["deleted" => 1], ["id" => $_POST['salary_id'][$key], "contract" => $data['id'], "salary" => $GetIDSalary]);
                            }
                        }
                    }
                    $jatbi->logs('hrm', 'contract-salary-update', [$salary, $salary_details_logs]);
                    echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
                }
            } else {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
            }
        }
    })->setPermissions(['contract.edit']);

    $app->router('/contract-print/{id}', 'GET', function ($vars) use ($app, $jatbi, $template) {
        $id = (int) ($vars['id'] ?? 0);
        $vars['title'] = $jatbi->lang("In Hợp đồng lao động");

        // 1. Lấy dữ liệu chính của hợp đồng và các thông tin liên quan
        $data = $app->get("personnels_contract", [
            "[>]personnels(p)" => ["personnels" => "id"],
            "[>]offices(o)" => ["offices" => "id"],
        ], [
            "personnels_contract.id",
            "personnels_contract.code",
            "personnels_contract.date_contract",
            "personnels_contract.type",
            "personnels_contract.duration",
            "personnels_contract.salary",
            "personnels_contract.offices",
            "personnels_contract.interview",
            "personnels_contract.workday",
            "personnels_contract.degree",
            "personnels_contract.certificate",
            "personnels_contract.health",
            "personnels_contract.notes",
            "personnels_contract.personnels",
            "p.name(personnel_name)",
            "p.birthday",
            "p.address",
            "p.idcode",
            "p.iddate",
            "p.idplace",
            "p.gender",
            "p.nation",
            "o.name(office_name)"
        ], ["personnels_contract.id" => $id, "personnels_contract.deleted" => 0]);

        if (!$data) {
            return $app->render($template . '/error.html', $vars, $jatbi->ajax());
        }

        // 2. Lấy chi tiết lương và trợ cấp
        $DataSalary = $app->get("personnels_contract_salary", "*", ["contract" => $id, "status" => 0, "deleted" => 0, "ORDER" => ["id" => "DESC"]]);

        $salarys_db = [];
        $allowances_db = [];

        if ($DataSalary) {
            $salarys_db = $app->select("personnels_contract_salary_details", "*", ["salary" => $DataSalary['id'], "type" => 1, "deleted" => 0]);
            $allowances_db = $app->select("personnels_contract_salary_details", "*", ["salary" => $DataSalary['id'], "type" => 2, "deleted" => 0]);

            $all_salary_items = array_merge($salarys_db, $allowances_db);
            $category_ids = array_unique(array_column($all_salary_items, 'content'));
            $category_map = [];
            if (!empty($category_ids)) {
                $category_map = array_column($app->select("salary_categorys", ["id", "name"], ["id" => $category_ids]), 'name', 'id');
            }

            foreach ($salarys_db as &$item) {
                $item['content_name'] = $category_map[$item['content']] ?? 'N/A';
                $item['duration_name'] = $duration_types[$item['duration']]['name'] ?? 'N/A';
            }
            unset($item);

            foreach ($allowances_db as &$item) {
                $item['content_name'] = $category_map[$item['content']] ?? 'N/A';
                $item['duration_name'] = $duration_types[$item['duration']]['name'] ?? 'N/A';
            }
            unset($item);
        }

        $vars['data'] = $data;
        $vars['salarys'] = $salarys_db;
        $vars['allowances'] = $allowances_db;
        // $vars['personnels_contracts_map'] = $personnels_contracts;

        echo $app->render($template . '/hrm/contract-print.html', $vars, $jatbi->ajax());
    })->setPermissions(['contract']);

    //Bao hiem
    $app->router('/insurrance', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores, $template) {
        $vars['title'] = $jatbi->lang("Bảo hiểm");
        if ($app->method() === 'GET') {
            $store_ids = array_column($stores, column_key: 'value');
            $vars['personnels'] = $app->select("personnels", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "stores" => $store_ids, "ORDER" => ["name" => "ASC"]]);
            echo $app->render($template . '/hrm/insurrance.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : $setting['site_page'] ?? 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'personnels_insurrance.id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $status = (isset($_POST['status']) && in_array($_POST['status'], ['A', 'D'])) ? [$_POST['status'], $_POST['status']] : '';
            $personnels = isset($_POST['personnels']) ? $_POST['personnels'] : '';

            $joins = [
                "[>]personnels" => ["personnels" => "id"],
            ];
            $store_ids = array_column($stores, column_key: 'value');

            $where = [
                "AND" => [
                    "OR" => [
                        "personnels_insurrance.social[~]" => $searchValue,
                        "personnels_insurrance.health[~]" => $searchValue,
                        "personnels.name[~]" => $searchValue,
                    ],
                    "personnels_insurrance.deleted" => 0,
                    "personnels_insurrance.status[<>]" => $status,
                    "personnels.stores" => $store_ids,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];
            if (!empty($personnels)) {
                $where['AND']['personnels.id'] = $personnels;
            }

            $count = $app->count("personnels_insurrance", $joins, "personnels_insurrance.id", $where['AND']);
            $datas = [];

            $app->select("personnels_insurrance", $joins, [
                'personnels_insurrance.id',
                'personnels_insurrance.price',
                'personnels_insurrance.social',
                'personnels_insurrance.social_date',
                'personnels_insurrance.social_place',
                'personnels_insurrance.health',
                'personnels_insurrance.health_date',
                'personnels_insurrance.health_place',
                'personnels_insurrance.date',
                'personnels_insurrance.status',
                'personnels.name(personnel_name)',
            ], $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "personnel_name" => $data['personnel_name'],
                    "price" => number_format($data['price']),
                    "social" => $data['social'],
                    'social_date' => date("d/m/Y", timestamp: strtotime(datetime: $data['social_date'])),
                    "social_place" => $data['social_place'],
                    "health" => $data['health'],
                    'health_date' => date("d/m/Y", timestamp: strtotime(datetime: $data['health_date'])),
                    "health_place" => $data['health_place'],
                    "date" => $jatbi->datetime($data['date'], 'datetime'),
                    "status" => $app->component("status", [
                        "url" => "/hrm/insurrance-status/" . $data['id'],
                        "data" => $data['status'],
                        "permission" => ['insurrance.edit']
                    ]),
                    "action" => ($app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xem"),
                                'permission' => ['insurrance'],
                                'action' => ['data-url' => '/hrm/insurrance-view/' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['insurrance.edit'],
                                'action' => ['data-url' => '/hrm/insurrance-edit/' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'permission' => ['insurrance.deleted'],
                                'action' => ['data-url' => '/hrm/insurrance-deleted?box=' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                        ]
                    ]))
                ];
            });
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas,
            ]);
        }
    })->setPermissions(['insurrance']);

    $app->router('/insurrance-excel', 'GET', function ($vars) use ($app, $stores) {
        try {
            // --- BƯỚC 1: LẤY VÀ XỬ LÝ CÁC BỘ LỌC TỪ URL ---
            $status_value = $_GET['status'] ?? 'A';
            $status = ($status_value === '') ? '' : [$status_value, $status_value];
            $personnels = $_GET['personnels'] ?? '';
            // **THÊM BỘ LỌC STORES**
            $store_ids = array_column($stores, column_key: 'value');

            // --- BƯỚC 2: XÂY DỰNG ĐIỀU KIỆN TRUY VẤN ---
            $joins = [
                "[>]personnels" => ["personnels" => "id"],
            ];

            $where = [
                "AND" => [
                    "personnels_insurrance.deleted" => 0,
                    "personnels_insurrance.status[<>]" => $status,
                    "personnels.stores" => $store_ids,
                ],
                "ORDER" => ["personnels.name" => "ASC"],
            ];
            if (!empty($personnels)) {
                $where['AND']['personnels.id'] = $personnels;
            }
            // **ÁP DỤNG BỘ LỌC STORES VÀO MỆNH ĐỀ WHERE**
            if (!empty($store_ids)) {
                $where['AND']['personnels.stores'] = $store_ids;
            }

            // --- BƯỚC 3: TRUY VẤN TOÀN BỘ DỮ LIỆU CẦN XUẤT ---
            $columns = [
                'personnels_insurrance.price',
                'personnels_insurrance.social',
                'personnels_insurrance.social_date',
                'personnels_insurrance.social_place',
                'personnels_insurrance.health',
                'personnels_insurrance.health_date',
                'personnels_insurrance.health_place',
                'personnels_insurrance.status',
                'personnels.name(personnel_name)',
            ];

            $report_data = $app->select("personnels_insurrance", $joins, $columns, $where);

            if (empty($report_data)) {
                ob_end_clean();
                exit("Không có dữ liệu để xuất file.");
            }

            // --- BƯỚC 4: TẠO FILE EXCEL ---
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('BaoHiemNhanSu');

            $headers = [
                'STT',
                'TÊN NHÂN VIÊN',
                'MỨC LƯƠNG ĐÓNG BH',
                'SỐ SỔ BHXH',
                'NGÀY CẤP SỔ BHXH',
                'NƠI CẤP SỔ BHXH',
                'SỐ THẺ BHYT',
                'NGÀY CẤP THẺ BHYT',
                'NƠI CẤP THẺ BHYT',
            ];
            $sheet->fromArray($headers, null, 'A1');
            $sheet->getStyle('A1:J1')->getFont()->setBold(true);

            // --- BƯỚC 5: ĐIỀN DỮ LIỆU VÀO EXCEL ---
            $row_index = 2;
            foreach ($report_data as $index => $data) {
                $sheet->setCellValue('A' . $row_index, $index + 1);
                $sheet->setCellValue('B' . $row_index, $data['personnel_name']);
                $sheet->setCellValue('C' . $row_index, $data['price']);
                $sheet->setCellValue('D' . $row_index, $data['social']);
                $sheet->setCellValue('E' . $row_index, !empty($data['social_date']) ? date("d/m/Y", strtotime($data['social_date'])) : '');
                $sheet->setCellValue('F' . $row_index, $data['social_place']);
                $sheet->setCellValue('G' . $row_index, $data['health']);
                $sheet->setCellValue('H' . $row_index, !empty($data['health_date']) ? date("d/m/Y", strtotime($data['health_date'])) : '');
                $sheet->setCellValue('I' . $row_index, $data['health_place']);
                $row_index++;
            }

            // --- BƯỚC 6: ĐỊNH DẠNG CỘT ---
            foreach (range('A', 'J') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            $sheet->getStyle('C2:C' . $row_index)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('E2:E' . $row_index)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
            $sheet->getStyle('H2:H' . $row_index)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
            $sheet->getStyle('J2:J' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // --- BƯỚC 7: XUẤT FILE ---
            ob_end_clean();
            $file_name = 'baohiem_nhansu_' . date('d-m-Y') . '.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $file_name . '"');
            header('Cache-Control: max-age=0');
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        } catch (Exception $e) {
            ob_end_clean();
            http_response_code(500);
            exit("Lỗi: " . $e->getMessage());
        }
    })->setPermissions(['insurrance']);

    $app->router('/insurrance-excel-d02-ts', 'GET', function ($vars) use ($app, $setting, $stores) {
        try {
            $store_ids = array_column($stores, 'value');


            $selected_month_year = $_GET['month_year'] ?? date('Y-m');
            $month_text = date('m/Y', strtotime($selected_month_year));

            $joins = [
                "[><]personnels(p)" => ["pi.personnels" => "id"],
                "[>]offices(o)" => ["p.office" => "id"],
                "[>]personnels_contract(pc)" => [
                    "p.id" => "personnels",
                    "AND" => ["pc.status" => "A", "pc.deleted" => 0]
                ]
            ];

            $where = [
                "AND" => [
                    "pi.deleted" => 0,
                    "pi.status" => 'A',
                    "p.stores" => $store_ids
                ],
                "ORDER" => ["p.name" => "ASC"],
            ];

            $columns = [
                "p.name",
                "pi.social(bhxh_code)",
                "pc.salary(salary_base)",
                "o.name(office_name)",
            ];

            $report_data = $app->select("personnels_insurrance(pi)", $joins, $columns, $where);

            if (empty($report_data)) {
                exit("Không có dữ liệu nhân viên tham gia bảo hiểm để xuất file.");
            }

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('D02-TS');

            // Thông tin đơn vị
            $sheet->setCellValue('A1', 'Tên đơn vị: ' . ($setting['site_name'] ?? 'CÔNG TY TNHH MTV NGỌC TRAI NGỌC HIỀN PHÚ QUỐC'));
            $sheet->setCellValue('A2', 'Mã đơn vị: ' . ($setting['company_code'] ?? 'XXXXXXXXXX'));
            $sheet->setCellValue('A3', 'Địa chỉ: ' . ($setting['site_address'] ?? 'ĐỊA CHỈ CÔNG TY'));

            // Tiêu đề chính
            $sheet->setCellValue('A5', 'DANH SÁCH LAO ĐỘNG THAM GIA BHXH, BHYT, BHTN');
            $sheet->setCellValue('A6', '(Kèm theo Mẫu D02-TS)');
            $sheet->mergeCells('A5:H5')->getStyle('A5')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A5:A6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->mergeCells('A6:H6');

            // Header của bảng
            $headers = ['A' => 'STT', 'B' => 'Họ và tên', 'C' => 'Mã số BHXH', 'D' => 'Cấp bậc, chức vụ...', 'E' => 'Tiền lương', 'F' => 'Từ tháng, năm', 'G' => 'Đến tháng, năm', 'H' => 'Ghi chú'];
            foreach ($headers as $col => $header) {
                $sheet->setCellValue($col . '8', $header);
            }
            $sheet->getStyle('A8:H8')->getFont()->setBold(true);
            $sheet->getStyle('A8:H8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // --- BƯỚC 5: ĐIỀN DỮ LIỆU ---
            $rowIndex = 9;
            foreach ($report_data as $index => $row) {
                $sheet->setCellValue('A' . $rowIndex, $index + 1);
                $sheet->setCellValue('B' . $rowIndex, $row['name']);
                $sheet->setCellValue('C' . $rowIndex, $row['bhxh_code']);
                $sheet->setCellValue('D' . $rowIndex, $row['office_name']);
                $sheet->setCellValue('E' . $rowIndex, $row['salary_base']);
                $sheet->setCellValue('F' . $rowIndex, $month_text);
                $sheet->setCellValue('G' . $rowIndex, '');
                $sheet->setCellValue('H' . $rowIndex, '');
                $rowIndex++;
            }

            // --- BƯỚC 6: ĐỊNH DẠNG VÀ KẺ BẢNG ---
            $lastRow = $rowIndex - 1;
            if ($lastRow >= 9) {
                $sheet->getStyle('A8:H' . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle('E9:E' . $lastRow)->getNumberFormat()->setFormatCode('#,##0');
            }
            foreach (range('A', 'H') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // --- BƯỚC 7: FOOTER KÝ TÊN ---
            $sheet->setCellValue('F' . ($rowIndex + 2), '............, ngày ...... tháng ...... năm ......');
            $sheet->setCellValue('F' . ($rowIndex + 3), 'Thủ trưởng đơn vị');
            $sheet->getStyle('F' . ($rowIndex + 3))->getFont()->setBold(true);
            $sheet->getStyle('F' . ($rowIndex + 2) . ':F' . ($rowIndex + 3))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // --- BƯỚC 8: XUẤT FILE ---
            ob_end_clean();
            $filename = "D02-TS_Thang_" . date('m_Y', strtotime($selected_month_year)) . ".xlsx";
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        } catch (Exception $e) {
            ob_end_clean();
            http_response_code(500);
            exit("Lỗi: " . $e->getMessage());
        }
    })->setPermissions(['insurrance']);

    $app->router("/insurrance-status/{id}", 'POST', function ($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $data = $app->get("personnels_insurrance", "*", ["id" => $vars['id'], "deleted" => 0]);
        if ($data > 1) {
            if ($data['status'] === 'A') {
                $status = "D";
            } elseif ($data['status'] === 'D') {
                $status = "A";
            }
            $app->update("personnels_insurrance", ["status" => $status], ["id" => $data['id']]);
            $jatbi->logs('hrm', 'insurrance-status', $data);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['insurrance.edit']);

    $app->router("/insurrance-add", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $accStore) {
        $vars['title'] = $jatbi->lang("Thêm Bảo hiểm");
        if ($app->method() === 'GET') {
            $vars['personnels'] = $app->select("personnels", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "stores" => $accStore, "ORDER" => ["name" => "ASC"]]);
            $vars['data'] = [
                "status" => 'A',
            ];
            echo $app->render($template . '/hrm/insurrance-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            if ($app->xss($_POST['price']) == '' || $app->xss($_POST['status']) == '' || $app->xss($_POST['personnels']) == '') {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
            } else {
                $insert = [
                    "personnels" => $app->xss($_POST['personnels']),
                    "price" => $app->xss(str_replace([','], '', $_POST['price'])),
                    "total" => $app->xss(str_replace([','], '', $_POST['total'])),
                    // "bhxh_price" 		=> $app->xss(str_replace([','],'',$_POST['bhxh_price'])),
                    // "bhyt_price" 		=> $app->xss(str_replace([','],'',$_POST['bhyt_price'])),
                    "social" => $app->xss($_POST['social']),
                    "social_place" => $app->xss($_POST['social_place']),
                    "social_date" => $app->xss($_POST['social_date']),
                    "health" => $app->xss($_POST['health']),
                    "health_place" => $app->xss($_POST['health_place']),
                    "health_date" => $app->xss($_POST['health_date']),
                    "notes" => $app->xss($_POST['notes']),
                    "status" => $app->xss($_POST['status']),
                    "date" => date("Y-m-d H:i:s"),
                    "user" => $app->getSession("accounts")['id'] ?? null,
                ];
                $app->insert("personnels_insurrance", $insert);
                $jatbi->logs('hrm', 'insurrance-add', [$insert]);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            }
        }
    })->setPermissions(['insurrance.add']);

    $app->router("/insurrance-add/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $vars['title'] = $jatbi->lang("Thêm Bảo hiểm");
        if ($app->method() === 'GET') {
            $vars['personnels'] = $app->select("personnels", [
                "id(value)",
                "name(text)"
            ], ["deleted" => 0, "status" => "A", "id" => $vars['id']]);
            $vars['data'] = [
                "status" => 'A',
            ];
            echo $app->render($template . '/hrm/insurrance-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            if ($app->xss($_POST['price']) == '' || $app->xss($_POST['status']) == '' || $app->xss($_POST['personnels']) == '') {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
            } else {
                $insert = [
                    "personnels" => $app->xss($_POST['personnels']),
                    "price" => $app->xss(str_replace([','], '', $_POST['price'])),
                    "total" => $app->xss(str_replace([','], '', $_POST['total'])),
                    // "bhxh_price" 		=> $app->xss(str_replace([','],'',$_POST['bhxh_price'])),
                    // "bhyt_price" 		=> $app->xss(str_replace([','],'',$_POST['bhyt_price'])),
                    "social" => $app->xss($_POST['social']),
                    "social_place" => $app->xss($_POST['social_place']),
                    "social_date" => $app->xss($_POST['social_date']),
                    "health" => $app->xss($_POST['health']),
                    "health_place" => $app->xss($_POST['health_place']),
                    "health_date" => $app->xss($_POST['health_date']),
                    "notes" => $app->xss($_POST['notes']),
                    "status" => $app->xss($_POST['status']),
                    "date" => date("Y-m-d H:i:s"),
                    "user" => $app->getSession("accounts")['id'] ?? null,
                ];
                $app->insert("personnels_insurrance", $insert);
                $jatbi->logs('hrm', 'insurrance-add', [$insert]);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            }
        }
    })->setPermissions(['insurrance.add']);

    $app->router("/insurrance-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $accStore) {
        $vars['title'] = $jatbi->lang("Sửa Bảo hiểm");
        if ($app->method() === 'GET') {
            $vars['data'] = $app->get("personnels_insurrance", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($vars['data'])) {
                $vars['personnels'] = $app->select("personnels", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "stores" => $accStore, "ORDER" => ["name" => "ASC"]]);
                echo $app->render($template . '/hrm/insurrance-post.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $data = $app->get("personnels_insurrance", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($data)) {
                if ($app->xss($_POST['price']) == '' || $app->xss($_POST['status']) == '' || $app->xss($_POST['personnels']) == '') {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
                } else {
                    $insert = [
                        "personnels" => $app->xss($_POST['personnels']),
                        "price" => $app->xss(str_replace([','], '', $_POST['price'])),
                        "total" => $app->xss(str_replace([','], '', $_POST['total'])),
                        // "bhxh_price" 		=> $app->xss(str_replace([','],'',$_POST['bhxh_price'])),
                        // "bhyt_price" 		=> $app->xss(str_replace([','],'',$_POST['bhyt_price'])),
                        "social" => $app->xss($_POST['social']),
                        "social_place" => $app->xss($_POST['social_place']),
                        "social_date" => $app->xss($_POST['social_date']),
                        "health" => $app->xss($_POST['health']),
                        "health_place" => $app->xss($_POST['health_place']),
                        "health_date" => $app->xss($_POST['health_date']),
                        "notes" => $app->xss($_POST['notes']),
                        "status" => $app->xss($_POST['status']),
                        "date" => date("Y-m-d H:i:s"),
                        "user" => $app->getSession("accounts")['id'] ?? null,
                    ];
                    $app->update("personnels_insurrance", $insert, ["id" => $data['id']]);
                    $jatbi->logs('hrm', 'insurrance-edit', [$insert]);
                    echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
                }
            } else {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
            }
        }
    })->setPermissions(['insurrance.edit']);

    $app->router("/insurrance-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa Bảo hiểm");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("personnels_insurrance", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("personnels_insurrance", ["deleted" => 1], ["id" => $data['id']]);
                }
                $jatbi->logs('hrm', 'insurrance-deleted', $datas);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['insurrance.deleted']);

    $app->router("/insurrance-view/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $vars['title'] = $jatbi->lang("Bảo hiểm");
        if ($app->method() === 'GET') {
            $vars['data'] = $app->get("personnels_insurrance", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($vars['data'])) {
                $vars['dataSalary'] = $app->get("personnels_contract_salary", "*", ["deleted" => 0, "contract" => $vars['data']['id'], "status" => 0, "ORDER" => ["id" => "DESC"]]);
                $vars['salarys'] = $app->select("personnels_contract_salary_details", "*", ["type" => 1, "contract" => $vars['data']['id'], "status" => 0, "salary" => $vars['dataSalary']['id'], "deleted" => 0]);
                $vars['allowances'] = $app->select("personnels_contract_salary_details", "*", ["type" => 2, "contract" => $vars['data']['id'], "status" => 0, "salary" => $vars['dataSalary']['id'], "deleted" => 0]);
                echo $app->render($template . '/hrm/insurrance-view.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
            }
        }
    })->setPermissions(['insurrance']);

    $app->router('/furlough-categorys', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Danh mục nghỉ phép");
            echo $app->render($template . '/hrm/furlough-categorys.html', $vars);
        }
        if ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : $setting['site_page'] ?? 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'furlough_categorys.id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $status = (isset($_POST['status']) && in_array($_POST['status'], ['A', 'D'])) ? [$_POST['status'], $_POST['status']] : '';

            $where = [
                "AND" => [
                    "OR" => [
                        "furlough_categorys.code[~]" => $searchValue,
                        "furlough_categorys.name[~]" => $searchValue,
                    ],
                    "furlough_categorys.deleted" => 0,
                    "furlough_categorys.status[<>]" => $status,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];

            $count = $app->count("furlough_categorys", "furlough_categorys.id", $where['AND']);
            $datas = [];

            $app->select("furlough_categorys", [
                'furlough_categorys.id',
                'furlough_categorys.code',
                'furlough_categorys.name',
                'furlough_categorys.type',
                'furlough_categorys.amount',
                'furlough_categorys.duration',
                'furlough_categorys.notes',
                'furlough_categorys.status',
            ], $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "code" => $data['code'],
                    "name" => $data['name'],
                    "amount" => $data['amount'] . " " . $jatbi->lang("ngày") . " / " . (
                        $data['duration'] == '4'
                        ? $jatbi->lang("tháng")
                        : ($data['duration'] == '5'
                            ? $jatbi->lang("năm")
                            : $jatbi->lang("không xác định")
                        )
                    ),
                    'type' => $data['type'] == '1' ? $jatbi->lang("Nghỉ có lương") : $jatbi->lang("Nghỉ không lương"),
                    "notes" => $data['notes'],
                    "status" => $app->component("status", [
                        "url" => "/hrm/furlough-categorys-status/" . $data['id'],
                        "data" => $data['status'],
                        "permission" => ['furlough-categorys.edit']
                    ]),
                    "action" => ($app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['furlough-categorys.edit'],
                                'action' => ['data-url' => '/hrm/furlough-categorys-edit/' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'permission' => ['furlough-categorys.deleted'],
                                'action' => ['data-url' => '/hrm/furlough-categorys-deleted?box=' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                        ]
                    ]))
                ];
            });
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas,
            ]);
        }
    })->setPermissions(['furlough-categorys']);

    $app->router("/furlough-categorys-status/{id}", 'POST', function ($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $data = $app->get("furlough_categorys", "*", ["id" => $vars['id'], "deleted" => 0]);
        if ($data > 1) {
            if ($data['status'] === 'A') {
                $status = "D";
            } elseif ($data['status'] === 'D') {
                $status = "A";
            }
            $app->update("furlough_categorys", ["status" => $status], ["id" => $data['id']]);
            $jatbi->logs('hrm', 'furlough-categorys-status', $data);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['furlough-categorys.edit']);

    $app->router("/furlough-categorys-add", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Thêm Danh mục nghỉ phép");
            $vars['types'] = [
                ["value" => "", "text" => $jatbi->lang("Chọn")],
                ["value" => 1, "text" => $jatbi->lang("Nghỉ có lương")],
                ["value" => 2, "text" => $jatbi->lang("Nghỉ không lương")],
                ["value" => 3, "text" => $jatbi->lang("Nghỉ đặc biệt")],
            ];
            $vars['duration_types'] = [
                ["value" => "", "text" => $jatbi->lang("Chọn")],
                ["value" => 4, "text" => $jatbi->lang("Tháng")],
                ["value" => 5, "text" => $jatbi->lang("Năm")],
            ];
            $vars['data'] = [
                "furlough" => '',
                "status" => 'A',
                "duration" => '',
            ];
            echo $app->render($template . '/hrm/furlough-categorys-post.html', $vars, $jatbi->ajax());
        }
        if ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            if ($app->xss($_POST['code']) == '' || $app->xss($_POST['name']) == '' || $app->xss($_POST['amount']) == '' || $app->xss($_POST['type']) == '') {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
            } else {
                $insert = [
                    "code" => $app->xss($_POST['code']),
                    "name" => $app->xss($_POST['name']),
                    "type" => $app->xss($_POST['type']),
                    "amount" => $app->xss($_POST['amount']),
                    "duration" => $app->xss($_POST['duration']),
                    "notes" => $app->xss($_POST['notes']),
                    "status" => $app->xss($_POST['status']),
                    "date" => date("Y-m-d H:i:s"),
                    "user" => $app->getSession("accounts")['id'] ?? null,
                ];
                $app->insert("furlough_categorys", $insert);
                $jatbi->logs('hrm', 'furlough-categorys-add', [$insert]);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            }
        }
    })->setPermissions(['furlough-categorys.add']);

    $app->router("/furlough-categorys-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Sửa Danh mục nghỉ phép");
            $vars['data'] = $app->get("furlough_categorys", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($vars['data'])) {
                $vars['types'] = [
                    ["value" => 1, "text" => $jatbi->lang("Nghỉ có lương")],
                    ["value" => 2, "text" => $jatbi->lang("Nghỉ không lương")],
                    ["value" => 3, "text" => $jatbi->lang("Nghỉ đặc biệt")],

                ];
                $vars['duration_types'] = [
                    ["value" => 4, "text" => $jatbi->lang("Tháng")],
                    ["value" => 5, "text" => $jatbi->lang("Năm")],
                ];
                echo $app->render($template . '/hrm/furlough-categorys-post.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $data = $app->get("personnels_insurrance", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($data)) {
                if ($app->xss($_POST['code']) == '' || $app->xss($_POST['name']) == '' || $app->xss($_POST['amount']) == '' || $app->xss($_POST['type']) == '') {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
                } else {
                    $insert = [
                        "code" => $app->xss($_POST['code']),
                        "name" => $app->xss($_POST['name']),
                        "type" => $app->xss($_POST['type']),
                        "amount" => $app->xss($_POST['amount']),
                        "duration" => $app->xss($_POST['duration']),
                        "notes" => $app->xss($_POST['notes']),
                        "status" => $app->xss($_POST['status']),
                        "date" => date("Y-m-d H:i:s"),
                        "user" => $app->getSession("accounts")['id'] ?? null,
                    ];
                    $app->update("furlough_categorys", $insert, ["id" => $data['id']]);
                    $jatbi->logs('hrm', 'furlough-categorys-edit', [$insert]);
                    echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
                }
            } else {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
            }
        }
    })->setPermissions(['furlough-categorys.edit']);

    $app->router("/furlough-categorys-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("furlough_categorys", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("furlough_categorys", ["deleted" => 1], ["id" => $data['id']]);
                }
                $jatbi->logs('hrm', 'furlough-categorys-deleted', $datas);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['furlough-categorys.deleted']);

    $app->router('/furlough', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $accStore, $template) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Nghỉ phép");
            $vars['personnels'] = $app->select("personnels", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "stores" => $accStore, "ORDER" => ["name" => "ASC"]]);
            $vars['furlough_categorys'] = $app->select("furlough_categorys", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "ORDER" => ["name" => "ASC"]]);
            echo $app->render($template . '/hrm/furlough.html', $vars);
        }
        if ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : $setting['site_page'] ?? 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'furlough.id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $status = (isset($_POST['status']) && in_array($_POST['status'], ['A', 'D'])) ? [$_POST['status'], $_POST['status']] : '';
            $personnels = isset($_POST['personnels']) ? $_POST['personnels'] : '';
            $furlough_categorys = isset($_POST['furlough_categorys']) ? $_POST['furlough_categorys'] : '';

            $joins = [
                "[>]furlough_categorys" => ["furlough" => "id"],
                "[>]personnels" => ["personnels" => "id"],
            ];
            $where = [
                "AND" => [
                    "OR" => [
                        "furlough_categorys.name[~]" => $searchValue,
                        "personnels.name[~]" => $searchValue,
                    ],
                    "furlough.deleted" => 0,
                    "furlough.status[<>]" => $status,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];
            if (!empty($personnels)) {
                $where['AND']['personnels.id'] = $personnels;
            }
            if (!empty($furlough_categorys)) {
                $where['AND']['furlough_categorys.id'] = $furlough_categorys;
            }

            $count = $app->count("furlough", $joins, "furlough.id", $where['AND']);
            $datas = [];

            $app->select("furlough", $joins, [
                'furlough.id',
                'furlough_categorys.name(furlough_category_name)',
                'personnels.name(personnel_name)',
                'furlough.date_from',
                'furlough.date_to',
                'furlough.notes',
                'furlough.status',
                'furlough.date',
            ], $where, function ($data) use (&$datas, $jatbi, $app) {
                $from = new DateTime($data['date_from']);
                $to = new DateTime($data['date_to']);
                $interval = $from->diff($to);
                $total_days_off = $interval->days + 1;
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "furlough_category_name" => $data['furlough_category_name'],
                    "personnel_name" => $data['personnel_name'],
                    "total_days_off" => $total_days_off,
                    "date_from" => $data['date_from'],
                    "date_to" => $data['date_to'],
                    "status" => $app->component("clickable_approval", [
                        "url" => "/hrm/furlough-status/" . $data['id'],
                        "data" => $data['status'],
                        "permission" => ['furlough.edit']
                    ]),
                    "date" => $data['date'],
                    "notes" => $data['notes'],
                    "action" => ($app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['furlough.edit'],
                                'action' => ['data-url' => '/hrm/furlough-edit/' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'permission' => ['furlough.deleted'],
                                'action' => ['data-url' => '/hrm/furlough-deleted?box=' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("In"),
                                'permission' => ['furlough'],
                                'action' => ['data-url' => '/hrm/furlough-print/' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                        ]
                    ]))
                ];
            });
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas,
            ]);
        }
    })->setPermissions(['furlough']);

    $app->router('/furlough-print/{id}', 'GET', function ($vars) use ($app, $jatbi, $template) {
        $id = (int) ($vars['id'] ?? 0);
        $vars['title'] = $jatbi->lang("In Đơn nghỉ phép");

        // 1. Lấy dữ liệu chính của đơn và JOIN với các bảng liên quan
        $data = $app->get("furlough", [
            "[>]personnels(p)" => ["personnels" => "id"],
            "[>]offices(o)" => ["p.office" => "id"],
            "[>]furlough_categorys(fc)" => ["furlough" => "id"],
            // "[>]accounts(a)" => ["user_approve" => "id"]
        ], [
            "furlough.id",
            "furlough.date_from",
            "furlough.date_to",
            "furlough.notes",
            "furlough.furlough",
            "p.name(personnel_name)",
            "o.name(office_name)",
            "fc.name(furlough_category_name)",
            // "a.name(approver_name)"
        ], ["furlough.id" => $id]);

        if (!$data) {
            return $app->render($template . '/error.html', $vars, $jatbi->ajax());
        }

        // 2. Tính toán số ngày nghỉ
        $from = new DateTime($data['date_from']);
        $to = new DateTime($data['date_to']);
        $interval = $from->diff($to);
        $data['total_days_off'] = $interval->days + 1;

        $vars['data'] = $data;
        if ($data['furlough'] == 11) {
            // nghỉ phép năm
            echo $app->render($template . '/hrm/furlough-year-print.html', $vars, $jatbi->ajax());
        } elseif ($data['furlough'] == 8) {
            // nghỉ thai sản
            echo $app->render($template . '/hrm/furlough-maternity-print.html', $vars, $jatbi->ajax());
        } elseif ($data['furlough'] == 1) {
            //nghỉ không lương
            echo $app->render($template . '/hrm/furlough-nosalary-print.html', $vars, $jatbi->ajax());
        } elseif ($data['furlough'] == 9) {
            // nghỉ bù
            echo $app->render($template . '/hrm/furlough-compensatory-print.html', $vars, $jatbi->ajax());
        } else {
            echo $app->render($template . '/hrm/furlough-print.html', $vars, $jatbi->ajax());
        }
    })->setPermissions(['furlough']);

    $app->router("/furlough-status/{id}", 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json',]);
        $data = $app->get("furlough", "*", ["id" => $vars['id'], "deleted" => 0]);
        if ($data) {
            $status = "";
            if ($data['status'] === 'A') {
                $status = "D";
                $app->delete("annual_leave", ["detai" => $data['id']]);
            } elseif ($data['status'] === 'D') {
                $status = "A";
                $getfurloughCat = $app->get("furlough_categorys", ["type"], ["id" => $data['furlough']]);

                if ($getfurloughCat && $getfurloughCat['type'] == 1) {
                    $from = new DateTime($data['date_from']);
                    $to = new DateTime($data['date_to']);
                    $duration = $from->diff($to)->days + 1;
                    $app->insert("annual_leave", [
                        "amount" => "-" . $duration,
                        "month" => date("m", strtotime($data['date_from'])),
                        "year" => date("Y", strtotime($data['date_from'])),
                        "personnels" => $data['personnels'],
                        "detai" => $data['id']
                    ]);
                }
            }
            if ($status) {
                $app->update("furlough", ["status" => $status], ["id" => $data['id']]);
                $jatbi->logs('hrm', 'furlough-status', $data);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Trạng thái không hợp lệ")]);
            }
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['furlough-categorys.edit']);

    $app->router("/furlough-add", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $accStore) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Thêm Nghỉ phép");
            $vars['personnels'] = $app->select("personnels", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "stores" => $accStore, "ORDER" => ["name" => "ASC"]]);
            $vars['furlough_categorys'] = array_merge(
                [["value" => "", "text" => $jatbi->lang("Chọn")]],
                $app->select("furlough_categorys", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "ORDER" => ["name" => "ASC"]])
            );
            $vars['data'] = [
                "furlough" => '',
                "status" => 'A',
            ];
            echo $app->render($template . '/hrm/furlough-post.html', $vars, $jatbi->ajax());
        }

        if ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $post = array_map([$app, 'xss'], $_POST);

            // --- BƯỚC 1: VALIDATION CƠ BẢN ---
            if (empty($post['personnels']) || empty($post['furlough']) || empty($post['date_from']) || empty($post['date_to'])) {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
                return;
            }
            if ($post['date_from'] > $post['date_to']) {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Ngày nghỉ phép không hợp lệ")]);
                return;
            }

            // --- BƯỚC 2: KIỂM TRA TRÙNG LẶP NGÀY NGHỈ ---
            $is_overlapping = $app->has("furlough", [
                "AND" => [
                    "personnels" => $post['personnels'],
                    "deleted" => 0,
                    "date_from[<=]" => $post['date_to'],
                    "date_to[>=]" => $post['date_from'],
                ]
            ]);
            if ($is_overlapping) {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Ngày nghỉ phép bị trùng")]);
                return;
            }

            // --- BƯỚC 3: KIỂM TRA GIỚI HẠN NGÀY NGHỈ (LOGIC MỚI) ---
            $getfurloughCat = $app->get("furlough_categorys", "*", ["id" => $post['furlough']]);
            if (!$getfurloughCat) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Loại nghỉ phép không hợp lệ")]);
                return;
            }

            // a. Tính số ngày của đơn xin nghỉ mới
            $new_request_from = new DateTime($post['date_from']);
            $new_request_to = new DateTime($post['date_to']);
            $new_request_duration = $new_request_from->diff($new_request_to)->days + 1;

            // b. Xác định khoảng thời gian cần kiểm tra (tháng hoặc năm)
            $period_from = ($getfurloughCat['duration'] == 4) ? date('Y-m-01', strtotime($post['date_from'])) : date('Y-01-01', strtotime($post['date_from']));
            $period_to = ($getfurloughCat['duration'] == 4) ? date('Y-m-t', strtotime($post['date_from'])) : date('Y-12-31', strtotime($post['date_from']));

            // c. Lấy tất cả các đơn nghỉ phép đã có trong kỳ
            $existing_furloughs = $app->select("furlough", ["date_from", "date_to"], [
                "personnels" => $post['personnels'],
                "furlough" => $getfurloughCat['id'],
                "deleted" => 0,
                "date_from[>=]" => $period_from,
                "date_to[<=]" => $period_to
            ]);

            // d. Tính tổng số ngày đã nghỉ
            $days_already_taken = 0;
            foreach ($existing_furloughs as $furlough) {
                $from = new DateTime($furlough['date_from']);
                $to = new DateTime($furlough['date_to']);
                $days_already_taken += $from->diff($to)->days + 1;
            }

            // // e. Kiểm tra giới hạn
            // if (($days_already_taken + $new_request_duration) > $getfurloughCat['amount']) {
            //     $remaining_days = $getfurloughCat['amount'] - $days_already_taken;
            //     echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Quá số ngày nghỉ phép. Chỉ còn lại: ") . ($remaining_days > 0 ? $remaining_days : 0) . " ngày."]);
            //     return;
            // }

            // --- BƯỚC 4: THÊM MỚI VÀO DATABASE ---
            $insert = [
                "personnels" => $post['personnels'],
                "furlough" => $post['furlough'],
                "type" => $getfurloughCat['type'],
                "date_from" => $post['date_from'],
                "date_to" => $post['date_to'],
                "notes" => $post['notes'],
                "status" => 'D',
                "date" => date("Y-m-d H:i:s"),
                "user" => $app->getSession("accounts")['id'] ?? null,
            ];

            $app->insert("furlough", $insert);
            $jatbi->logs('hrm', 'furlough-add', [$insert]);

            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        }
    })->setPermissions(['furlough.add']);

    $app->router("/furlough-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $accStore) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Sửa Nghỉ phép");
            $vars['data'] = $app->get("furlough", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($vars['data'])) {
                $vars['personnels'] = $app->select("personnels", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "stores" => $accStore, "ORDER" => ["name" => "ASC"]]);
                $vars['furlough_categorys'] = array_merge(
                    [["value" => "", "text" => $jatbi->lang("Chọn")]],
                    $app->select("furlough_categorys", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "ORDER" => ["name" => "ASC"]])
                );
                echo $app->render($template . '/hrm/furlough-post.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $data = $app->get("furlough", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($data)) {
                if ($app->xss($_POST['personnels']) == '' || $app->xss($_POST['furlough']) == '' || $app->xss($_POST['date_from']) == '' || $app->xss($_POST['date_to']) == '') {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
                    return;
                }
                if ($app->xss($_POST['date_from']) > $app->xss($_POST['date_to'])) {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Ngày nghỉ phép không hợp lệ")]);
                    return;
                }
                if (
                    $app->has("furlough", [
                        "id[!]" => $data['id'],
                        "date_from[<]" => $app->xss($_POST['date_from']),
                        "date_to[>]" => $app->xss($_POST['date_from']),
                        "personnels" => $app->xss($_POST['personnels']),
                        "deleted" => 0,
                    ]) || $app->has("furlough", [
                        "id[!]" => $data['id'],
                        "date_from[<=]" => $app->xss($_POST['date_to']),
                        "date_to[>=]" => $app->xss($_POST['date_to']),
                        "personnels" => $app->xss($_POST['personnels']),
                        "deleted" => 0,
                    ])
                ) {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Ngày nghỉ phép bị trùng")]);
                    return;
                }
                $getfurloughCat = $app->get("furlough_categorys", "*", [
                    "id" => $app->xss($_POST['furlough']),
                    "status" => "A",
                    "deleted" => 0,
                ]);
                if ($getfurloughCat['duration'] == 4) { // lấy ngày đầu tháng và cuối tháng
                    $duration_from = date('Y-m-01', strtotime($app->xss($_POST['date_from'])));
                    $duration_to = date('Y-m-t', strtotime($app->xss($_POST['date_from'])));
                    $duration_lastday = date('Y-m-t', strtotime($app->xss($_POST['date_to'])));
                    $plus = '+1 month';
                } else { // lấy ngày đầu năm và cuối năm
                    $duration_from = date('Y-01-01', strtotime($app->xss($_POST['date_from'])));
                    $duration_to = date('Y-12-31', strtotime($app->xss($_POST['date_from'])));
                    $duration_lastday = date('Y-12-31', strtotime($app->xss($_POST['date_to'])));
                    $plus = '+1 year';
                }
                while ($duration_to <= $duration_lastday) {
                    $get_furlough = $app->select("furlough", "*", [
                        "id[!]" => $data['id'],
                        "personnels" => $app->xss($_POST['personnels']),
                        "furlough" => $getfurloughCat['id'],
                        "deleted" => 0,
                        "OR" => [
                            "date_from[<>]" => [$duration_from, $duration_to],
                            "date_to[<>]" => [$duration_from, $duration_to],
                        ]
                    ]);
                    $i = 0;
                    $total_days_off = 0;
                    $furlough = [
                        "date_from" => (strtotime($app->xss($_POST['date_from'])) > strtotime($duration_from))
                            ? $app->xss($_POST['date_from'])
                            : $duration_from,
                        "date_to" => (strtotime($app->xss($_POST['date_to'])) < strtotime($duration_to))
                            ? $app->xss($_POST['date_to'])
                            : $duration_to,
                    ];
                    do {
                        $day_from = $furlough['date_from'] > $duration_from ? $furlough['date_from'] : $duration_from;
                        $day_to = $furlough['date_to'] < $duration_to ? $furlough['date_to'] : $duration_to;
                        $day_from = new DateTime($day_from);
                        $day_to = new DateTime($day_to);
                        $interval = $day_from->diff($day_to);
                        $total_days_off += $interval->days + 1;
                        $furlough = $get_furlough[$i] ?? [];
                        $i++;
                    } while ($furlough);
                    if ($total_days_off > $getfurloughCat['amount']) {
                        echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Quá số ngày nghỉ phép ")]);
                        return;
                    }
                    $duration_from = date('Y-m-01', strtotime($plus, strtotime($duration_from)));
                    $duration_to = date('Y-m-t', strtotime($plus, strtotime($duration_to)));
                }
                $from = new DateTime($data['date_from']);
                $to = new DateTime($data['date_to']);
                $duration = $from->diff($to)->days + 1;
                $insert = [
                    "personnels" => $app->xss($_POST['personnels']),
                    "furlough" => $app->xss($_POST['furlough']),
                    "type" => $getfurloughCat['type'],
                    "date_from" => $app->xss($_POST['date_from']),
                    "date_to" => $app->xss($_POST['date_to']),
                    "notes" => $app->xss($_POST['notes']),
                    "status" => $app->xss($_POST['status']),
                    "date" => date("Y-m-d H:i:s"),
                    "user" => $app->getSession("accounts")['id'] ?? null,
                ];
                $app->update("furlough", $insert, ["id" => $data['id']]);
                $app->delete("annual_leave", ["detai" => $data['id']]);
                $app->insert("annual_leave", [
                    "amount" => "-" . $duration,
                    "month" => date("m", strtotime($data['date_from'])),
                    "year" => date("Y", strtotime($data['date_from'])),
                    "personnels" => $data['personnels'],
                    "detai" => $data['id']
                ]);
                $jatbi->logs('hrm', 'furlough-edit', [$insert]);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
            }
        }
    })->setPermissions(['furlough.edit']);

    $app->router("/furlough-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("furlough", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("furlough", ["deleted" => 1], ["id" => $data['id']]);
                    $app->delete("annual_leave", ["detai" => $data['id']]);
                }
                $jatbi->logs('hrm', 'furlough-deleted', $datas);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['furlough.deleted']);

    $app->router('/holiday', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Ngày lễ");
            echo $app->render($template . '/hrm/holiday.html', $vars);
        }
        if ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : $setting['site_page'] ?? 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'holiday.id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $status = (isset($_POST['status']) && in_array($_POST['status'], ['A', 'D'])) ? [$_POST['status'], $_POST['status']] : '';

            $joins = [
                "[>]offices" => ["office" => "id"],
            ];
            $where = [
                "AND" => [
                    "OR" => [
                        "holiday.name[~]" => $searchValue,
                    ],
                    "holiday.deleted" => 0,
                    "holiday.status[<>]" => $status,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];

            $count = $app->count("holiday", $joins, "holiday.id", $where['AND']);
            $datas = [];

            $app->select("holiday", $joins, [
                'holiday.id',
                'holiday.name',
                'holiday.date_from',
                'holiday.date_to',
                'holiday.salary',
                'holiday.notes',
                'holiday.status',
                'offices.name(office_name)',
            ], $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "office_name" => $data['office_name'],
                    "name" => $data['name'],
                    "total_days" => 1,
                    "salary" => $data['salary'],
                    "notes" => $data['notes'],
                    "status" => $app->component("status", [
                        "url" => "/hrm/furlough-categorys-status/" . $data['id'],
                        "data" => $data['status'],
                        "permission" => ['insurrance.edit']
                    ]),
                    "action" => ($app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['holiday.edit'],
                                'action' => ['data-url' => '/hrm/holiday-edit/' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'permission' => ['holiday.deleted'],
                                'action' => ['data-url' => '/hrm/holiday-deleted?box=' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                        ]
                    ]))
                ];
            });
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas,
            ]);
        }
    })->setPermissions(['holiday']);

    $app->router("/holiday-status/{id}", 'POST', function ($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $data = $app->get("holiday", "*", ["id" => $vars['id'], "deleted" => 0]);
        if ($data > 1) {
            if ($data['status'] === 'A') {
                $status = "D";
            } elseif ($data['status'] === 'D') {
                $status = "A";
            }
            $app->update("holiday", ["status" => $status], ["id" => $data['id']]);
            $jatbi->logs('hrm', 'holiday-status', $data);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['holiday.edit']);

    $app->router("/holiday-add", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Thêm Ngày lễ");
            $vars['offices'] = array_merge(
                [["value" => "", "text" => $jatbi->lang("Tất cả")]],
                $app->select("offices", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "ORDER" => ["name" => "ASC"]])
            );
            echo $app->render($template . '/hrm/holiday-post.html', $vars, $jatbi->ajax());
        }
        if ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            if ($app->xss($_POST['name']) == '' || $app->xss($_POST['date_from']) == '' || $app->xss($_POST['date_to']) == '') {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
                return;
            }
            if ($app->xss($_POST['date_from']) > $app->xss($_POST['date_to'])) {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Ngày nghỉ không hợp lệ")]);
                return;
            }
            $salary = trim($app->xss($_POST['salary']));
            if (is_numeric($salary) && floatval($salary) >= 0) {
                $insert = [
                    "name" => $app->xss($_POST['name']),
                    "date_from" => $app->xss($_POST['date_from']),
                    "date_to" => $app->xss($_POST['date_to']),
                    "salary" => $app->xss($_POST['salary']),
                    "office" => $app->xss($_POST['office']),
                    "notes" => $app->xss($_POST['notes']),
                    "status" => $app->xss($_POST['status']),
                    "date" => date("Y-m-d H:i:s"),
                    "user" => $app->getSession("accounts")['id'] ?? null,
                ];
                $app->insert("holiday", $insert);
                $jatbi->logs('hrm', 'holiday-add', [$insert]);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Hệ số lương không hợp lệ")]);
            }
        }
    })->setPermissions(['holiday.add']);

    $app->router("/holiday-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Sửa Ngày lễ");
            $vars['data'] = $app->get("holiday", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($vars['data'])) {
                $vars['offices'] = array_merge(
                    [["value" => "", "text" => $jatbi->lang("Tất cả")]],
                    $app->select("offices", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "ORDER" => ["name" => "ASC"]])
                );
                echo $app->render($template . '/hrm/holiday-post.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $data = $app->get("holiday", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($data)) {
                if ($app->xss($_POST['name']) == '' || $app->xss($_POST['date_from']) == '' || $app->xss($_POST['date_to']) == '') {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
                    return;
                }
                if ($app->xss($_POST['date_from']) > $app->xss($_POST['date_to'])) {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Ngày nghỉ không hợp lệ")]);
                    return;
                }
                $salary = trim($app->xss($_POST['salary']));
                if (is_numeric($salary) && floatval($salary) >= 0) {
                    $insert = [
                        "name" => $app->xss($_POST['name']),
                        "date_from" => $app->xss($_POST['date_from']),
                        "date_to" => $app->xss($_POST['date_to']),
                        "salary" => $app->xss($_POST['salary']),
                        "office" => $app->xss($_POST['office']),
                        "notes" => $app->xss($_POST['notes']),
                        "status" => $app->xss($_POST['status']),
                        "date" => date("Y-m-d H:i:s"),
                        "user" => $app->getSession("accounts")['id'] ?? null,
                    ];
                    $app->update("holiday", $insert, ["id" => $data['id']]);
                    $jatbi->logs('hrm', 'holiday-edit', [$insert]);
                    echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
                } else {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Hệ số lương không hợp lệ")]);
                }
            } else {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
            }
        }
    })->setPermissions(['holiday.edit']);

    $app->router("/holiday-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("holiday", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("holiday", ["deleted" => 1], ["id" => $data['id']]);
                }
                $jatbi->logs('hrm', 'holiday-deleted', $datas);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['holiday.deleted']);

    $app->router('/reward-discipline', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $accStore, $template) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Khen thưởng kỉ luật");
            $vars['personnels'] = $app->select("personnels", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "stores" => $accStore, "ORDER" => ["name" => "ASC"]]);
            $vars['types'] = [
                ["value" => 0, "text" => $jatbi->lang("Tất cả")],
                ["value" => 1, "text" => $jatbi->lang("Khen thưởng")],
                ["value" => 2, "text" => $jatbi->lang("Kỷ luật")],
            ];
            echo $app->render($template . '/hrm/reward-discipline.html', $vars);
        }
        if ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : $setting['site_page'] ?? 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'reward_discipline.id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $personnels = isset($_POST['personnels']) ? $_POST['personnels'] : '';
            $type = isset($_POST['type']) ? $_POST['type'] : '';

            $joins = [
                "[>]personnels" => ["personnels" => "id"],
                "[>]accounts" => ["reward_discipline.user" => "id"],
            ];
            $where = [
                "AND" => [
                    "OR" => [
                        "personnels.name[~]" => $searchValue,
                        "reward_discipline.price[~]" => $searchValue,
                        "reward_discipline.content[~]" => $searchValue,
                    ],
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];
            if (!empty($personnels)) {
                $where['AND']['personnels.id'] = $personnels;
            }
            if (!empty($type)) {
                $where['AND']['reward_discipline.type'] = $type;
            }

            $count = $app->count("reward_discipline", $joins, "reward_discipline.id", $where['AND']);
            $datas = [];

            $app->select("reward_discipline", $joins, [
                'reward_discipline.id',
                'reward_discipline.type',
                'reward_discipline.price',
                'reward_discipline.date',
                'reward_discipline.content',
                'reward_discipline.date_poster',
                'accounts.name(user_name)',
                'personnels.name(personnel_name)',
            ], $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "type" => $data['type'] == 1 ? "Khen thưởng" : "Kỷ luật",
                    "personnel_name" => $data['personnel_name'],
                    "price" => number_format($data['price']),
                    "date" => $data['date'],
                    "content" => $data['content'],
                    "date_poster" => $data['date_poster'],
                    "user"=> $data['user_name'],
                    "action" => $app->component("action", [
                        "button" => array_merge(
                            $data['type'] != 1 ? [[
                                'type' => 'button',
                                'name' => $jatbi->lang("Xem"),
                                'permission' => ['reward-discipline'],
                                'action' => ['data-url' => '/hrm/discipline-views/' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ]] : [],
                            [
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Sửa"),
                                    'permission' => ['reward-discipline.edit'],
                                    'action' => ['data-url' => '/hrm/reward-discipline-edit/' . ($data['id'] ?? ''), 'data-action' => 'modal']
                                ],
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Xóa"),
                                    'permission' => ['reward-discipline.deleted'],
                                    'action' => ['data-url' => '/hrm/reward-discipline-deleted?box=' . ($data['id'] ?? ''), 'data-action' => 'modal']
                                ],
                            ]
                        )
                    ])
                ];
            });
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas,
            ]);
        }
    })->setPermissions(['reward-discipline']);

    $app->router('/discipline-views/{id}', ['GET'], function ($vars) use ($app, $jatbi, $setting, $template) {
        $id = (int) $vars['id'];

        $data = $app->get(
            "reward_discipline",
            [
                "[>]personnels" => ["personnels" => "id"],
                "[>]offices" => ["personnels.office" => "id"],
                "[>]accounts" => ["reward_discipline.user" => "id"],
            ],
            [
                'reward_discipline.type',
                'reward_discipline.price',
                'reward_discipline.date',
                'reward_discipline.user',
                'reward_discipline.content',
                'reward_discipline.date_poster',
                'personnels.name(personnel_name)',
                'offices.name(department_name)',
                'accounts.name(user_name)',
            ],
            [
                "AND" => [
                    "reward_discipline.id" => $id,
                    "reward_discipline.type" => 2
                ]
            ]
        );

        if (!$data) {
            $vars['modalContent'] = $jatbi->lang("Không tìm thấy dữ liệu kỷ luật.");
            echo $app->render($setting['template'] . '/common/reward.html', $vars, $jatbi->ajax());
            return;
        }

        $vars['data'] = $data;

        echo $app->render($template . '/hrm/discipline-views.html', $vars, $jatbi->ajax());
    })->setPermissions(['reward-discipline']);

    $app->router('/reward-discipline-views', ['GET'], function ($vars) use ($app, $jatbi, $setting, $template) {

        $boxid = explode(',', $app->xss($_GET['box']));

        $datas = $app->select(
            "reward_discipline",
            [
                "[>]personnels" => ["personnels" => "id"],
                "[>]offices" => ["personnels.office" => "id"],
                "[>]accounts" => ["reward_discipline.user" => "id"],
            ],
            [
                'reward_discipline.id',
                'reward_discipline.type',
                'reward_discipline.price',
                'reward_discipline.date',
                'reward_discipline.user',
                'reward_discipline.content',
                'reward_discipline.date_poster',
                'personnels.name(personnel_name)',
                'personnels.office(department_name)',
                'accounts.name(user_name)',
                'offices.name(office_name)',
            ],
            [
                "reward_discipline.id" => $boxid
            ]
        );

        if (empty($datas)) {
            $vars['modalContent'] = $jatbi->lang("Dữ liệu không tồn tại");
            echo $app->render($setting['template'] . '/common/reward.html', $vars, $jatbi->ajax());
            return;
        }

        $types = array_unique(array_column($datas, 'type'));
        if (count($types) !== 1 || reset($types) != 1) {
            $vars['modalContent'] = $jatbi->lang("Chức năng này chỉ dùng để in phiếu Khen thưởng. Vui lòng chỉ chọn các mục khen thưởng.");
            echo $app->render($setting['template'] . '/common/reward.html', $vars, $jatbi->ajax());
            return;
        }

        $vars['datas'] = $datas;
        // $vars['user'] = $app->getSession("accounts")['name'];


        echo $app->render($template . '/hrm/reward-views.html', $vars, $jatbi->ajax());
    })->setPermissions(['reward-discipline']);

    $app->router("/reward-discipline-add", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $accStore) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Thêm Khen thưởng kỉ luật");
            $vars['personnels'] = $app->select("personnels", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "stores" => $accStore, "ORDER" => ["name" => "ASC"]]);
            $vars['types'] = [
                ["value" => 1, "text" => $jatbi->lang("Khen thưởng")],
                ["value" => 2, "text" => $jatbi->lang("Kỷ luật")],
            ];
            echo $app->render($template . '/hrm/reward-discipline-post.html', $vars, $jatbi->ajax());
        }
        if ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            if ($app->xss($_POST['type']) == '' || $app->xss($_POST['personnels']) == '' || $app->xss($_POST['date']) == '' || $app->xss($_POST['price']) == '') {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
            } else {
                $insert = [
                    "personnels" => $app->xss($_POST['personnels']),
                    "type" => $app->xss($_POST['type']),
                    "date" => $app->xss($_POST['date']),
                    "price" => $app->xss(str_replace([','], '', $_POST['price'])),
                    "content" => $app->xss($_POST['content']),
                    "notes" => $app->xss($_POST['notes']),
                    "date_poster" => date("Y-m-d H:i:s"),
                    "user" => $app->getSession("accounts")['id'] ?? null,
                ];
                $app->insert("reward_discipline", $insert);
                $jatbi->logs('hrm', 'reward-discipline-add', [$insert]);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            }
        }
    })->setPermissions(['reward-discipline.add']);

    $app->router("/reward-discipline-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $accStore) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Sửa Khen thưởng kỉ luật");
            $vars['data'] = $app->get("reward_discipline", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($vars['data'])) {
                $vars['personnels'] = $app->select("personnels", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "stores" => $accStore, "ORDER" => ["name" => "ASC"]]);
                $vars['types'] = [
                    ["value" => "", "text" => $jatbi->lang("Chọn")],
                    ["value" => 1, "text" => $jatbi->lang("Khen thưởng")],
                    ["value" => 2, "text" => $jatbi->lang("Kỷ luật")],
                ];
                echo $app->render($template . '/hrm/reward-discipline-post.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $data = $app->get("reward_discipline", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($data)) {
                if ($app->xss($_POST['type']) == '' || $app->xss($_POST['personnels']) == '' || $app->xss($_POST['date']) == '' || $app->xss($_POST['price']) == '') {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
                } else {
                    $insert = [
                        "personnels" => $app->xss($_POST['personnels']),
                        "type" => $app->xss($_POST['type']),
                        "date" => $app->xss($_POST['date']),
                        "price" => $app->xss(str_replace([','], '', $_POST['price'])),
                        "content" => $app->xss($_POST['content']),
                        "notes" => $app->xss($_POST['notes']),
                        "date_poster" => date("Y-m-d H:i:s"),
                        "user" => $app->getSession("accounts")['id'] ?? null,
                    ];
                    $app->update("reward_discipline", $insert, ["id" => $data['id']]);
                    $jatbi->logs('hrm', 'reward-discipline-edit', [$insert]);
                    echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
                }
            } else {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
            }
        }
    })->setPermissions(['reward-discipline.edit']);

    $app->router("/reward-discipline-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("reward_discipline", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("reward_discipline", ["deleted" => 1], ["id" => $data['id']]);
                }
                $jatbi->logs('hrm', 'reward-discipline-deleted', $datas);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['reward-discipline.deleted']);

    $app->router('/time-late', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Đi trễ về sớm");
            echo $app->render($template . '/hrm/time-late.html', $vars);
        }
        if ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : $setting['site_page'] ?? 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'time_late.id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $status = (isset($_POST['status']) && in_array($_POST['status'], ['A', 'D'])) ? [$_POST['status'], $_POST['status']] : '';

            $where = [
                "AND" => [
                    "OR" => [
                        "time_late.name[~]" => $searchValue,
                        "time_late.value[~]" => $searchValue,
                    ],
                    "time_late.deleted" => 0,
                    "time_late.status[<>]" => $status,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];

            $count = $app->count("time_late", "time_late.id", $where['AND']);
            $datas = [];

            $app->select("time_late", [
                'time_late.id',
                'time_late.type',
                'time_late.name',
                'time_late.value',
                'time_late.price',
                'time_late.date',
                'time_late.content',
                'time_late.status',
            ], $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "type" => $data['type'],
                    "name" => $data['name'],
                    "value" => $data['value'] . ' ' . $jatbi->lang("phút"),
                    "price" => $data['price'],
                    "date" => $data['date'],
                    "content" => $data['content'],
                    "status" => $app->component("status", [
                        "url" => "/hrm/time-late-status/" . $data['id'],
                        "data" => $data['status'],
                        "permission" => ['time-late.edit']
                    ]),
                    "action" => ($app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['time-late.edit'],
                                'action' => ['data-url' => '/hrm/time-late-edit/' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'permission' => ['time-late.deleted'],
                                'action' => ['data-url' => '/hrm/time-late-deleted?box=' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                        ]
                    ]))
                ];
            });
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas,
            ]);
        }
    })->setPermissions(['time-late']);

    $app->router("/time-late-status/{id}", 'POST', function ($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $data = $app->get("time_late", "*", ["id" => $vars['id'], "deleted" => 0]);
        if ($data > 1) {
            if ($data['status'] === 'A') {
                $status = "D";
            } elseif ($data['status'] === 'D') {
                $status = "A";
            }
            $app->update("time_late", ["status" => $status], ["id" => $data['id']]);
            $jatbi->logs('hrm', 'time-late-status', $data);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['time-late.edit']);

    $app->router("/time-late-add", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $accStore) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Thêm Đi trễ về sớm");
            $vars['personnels'] = $app->select("personnels", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "stores" => $accStore, "ORDER" => ["name" => "ASC"]]);
            $vars['types'] = [
                ["value" => "", "text" => $jatbi->lang("Chọn")],
                ["value" => 1, "text" => $jatbi->lang("Đi trễ")],
                ["value" => 2, "text" => $jatbi->lang("Về sớm")],
            ];
            echo $app->render($template . '/hrm/time-late-post.html', $vars, $jatbi->ajax());
        }
        if ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            if ($app->xss($_POST['type']) == '' || $app->xss($_POST['name']) == '' || $app->xss($_POST['date']) == '' || $app->xss($_POST['value']) == '' || $app->xss($_POST['price']) == '') {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
            } else {
                $insert = [
                    "name" => $app->xss($_POST['name']),
                    "type" => $app->xss($_POST['type']),
                    "date" => $app->xss($_POST['date']),
                    "price" => $app->xss(str_replace([','], '', $_POST['price'])),
                    "value" => $app->xss($_POST['value']),
                    "content" => $app->xss($_POST['content']),
                    "status" => $app->xss($_POST['status']),
                    "date_poster" => date("Y-m-d H:i:s"),
                    "user" => $app->getSession("accounts")['id'] ?? null,
                ];
                $app->insert("time_late", $insert);
                $jatbi->logs('hrm', 'time-late-add', [$insert]);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            }
        }
    })->setPermissions(['time-late.add']);

    $app->router("/time-late-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $accStore) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Sửa Đi trễ về sớm");
            $vars['data'] = $app->get("time_late", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($vars['data'])) {
                $vars['personnels'] = $app->select("personnels", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "stores" => $accStore, "ORDER" => ["name" => "ASC"]]);
                $vars['types'] = [
                    ["value" => "", "text" => $jatbi->lang("Chọn")],
                    ["value" => 1, "text" => $jatbi->lang("Đi trễ")],
                    ["value" => 2, "text" => $jatbi->lang("Về sớm")],
                ];
                echo $app->render($template . '/hrm/time-late-post.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $data = $app->get("reward_discipline", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($data)) {
                if ($app->xss($_POST['type']) == '' || $app->xss($_POST['name']) == '' || $app->xss($_POST['date']) == '' || $app->xss($_POST['value']) == '' || $app->xss($_POST['price']) == '') {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
                } else {
                    $insert = [
                        "name" => $app->xss($_POST['name']),
                        "type" => $app->xss($_POST['type']),
                        "date" => $app->xss($_POST['date']),
                        "price" => $app->xss(str_replace([','], '', $_POST['price'])),
                        "value" => $app->xss($_POST['value']),
                        "content" => $app->xss($_POST['content']),
                        "status" => $app->xss($_POST['status']),
                        "date_poster" => date("Y-m-d H:i:s"),
                        "user" => $app->getSession("accounts")['id'] ?? null,
                    ];
                    $app->update("time_late", $insert, ["id" => $data['id']]);
                    $jatbi->logs('hrm', 'time-late-edit', [$insert]);
                    echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
                }
            } else {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
            }
        }
    })->setPermissions(['reward-discipline.edit']);

    $app->router("/time-late-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("time_late", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("time_late", ["deleted" => 1], ["id" => $data['id']]);
                }
                $jatbi->logs('hrm', 'time-late-deleted', $datas);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['reward-discipline.deleted']);

    $app->router('/salary-advance', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template, $accStore) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Ứng lướng");
            $vars['personnels'] = $app->select("personnels", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "stores" => $accStore, "ORDER" => ["name" => "ASC"]]);
            echo $app->render($template . '/hrm/salary-advance.html', $vars);
        }
        if ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : $setting['site_page'] ?? 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'salary_advance.id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $personnels = isset($_POST['personnels']) ? $_POST['personnels'] : '';
            $date = isset($_POST['date']) ? $_POST['date'] : '';

            $joins = [
                "[>]personnels" => ["personnels" => "id"],
            ];
            $where = [
                "AND" => [
                    "OR" => [
                        "personnels.name[~]" => $searchValue,
                    ],
                    "salary_advance.deleted" => 0,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];
            if (!empty($personnels)) {
                $where['AND']['personnels.id'] = $personnels;
            }
            if (!empty($date)) {
                $where['AND']['salary_advance.date'] = $date;
            }

            $count = $app->count("salary_advance", $joins, "salary_advance.id", $where['AND']);
            $datas = [];

            $app->select("salary_advance", $joins, [
                'salary_advance.id',
                'salary_advance.date',
                'salary_advance.price',
                'salary_advance.notes',
                'salary_advance.date_poster',
                'personnels.name(personnel_name)',
            ], $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "date" => $data['date'],
                    "personnel_name" => $data['personnel_name'],
                    "price" => number_format($data['price']),
                    "notes" => $data['notes'],
                    "date_poster" => $data['date_poster'],
                    "action" => ($app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['salary-advance.edit'],
                                'action' => ['data-url' => '/hrm/salary-advance-edit/' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'permission' => ['salary-advance.deleted'],
                                'action' => ['data-url' => '/hrm/salary-advance-deleted?box=' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                        ]
                    ]))
                ];
            });
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas,
            ]);
        }
    })->setPermissions(['salary-advance']);

    $app->router("/salary-advance-add", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $accStore) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Thêm Ứng lương");
            $vars['personnels'] = $app->select("personnels", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "stores" => $accStore, "ORDER" => ["name" => "ASC"]]);
            echo $app->render($template . '/hrm/salary-advance-post.html', $vars, $jatbi->ajax());
        }
        if ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            if ($app->xss($_POST['date']) == '' || $app->xss($_POST['personnels']) == '' || $app->xss($_POST['price']) == '') {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
            } else {
                $insert = [
                    "personnels" => $app->xss($_POST['personnels']),
                    "date" => $app->xss($_POST['date']),
                    "price" => $app->xss(str_replace([','], '', $_POST['price'])),
                    "notes" => $app->xss($_POST['notes']),
                    "date_poster" => date("Y-m-d H:i:s"),
                    "user" => $app->getSession("accounts")['id'] ?? null,
                ];
                $app->insert("salary_advance", $insert);
                $jatbi->logs('hrm', 'salary-advance-add', [$insert]);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            }
        }
    })->setPermissions(['salary-advance.add']);

    $app->router("/salary-advance-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $accStore) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Sửa Ứng lương");
            $vars['data'] = $app->get("salary_advance", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($vars['data'])) {
                $vars['personnels'] = $app->select("personnels", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "stores" => $accStore, "ORDER" => ["name" => "ASC"]]);
                echo $app->render($template . '/hrm/salary-advance-post.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $data = $app->get("reward_discipline", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($data)) {
                if ($app->xss($_POST['date']) == '' || $app->xss($_POST['personnels']) == '' || $app->xss($_POST['price']) == '') {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
                } else {
                    $insert = [
                        "personnels" => $app->xss($_POST['personnels']),
                        "date" => $app->xss($_POST['date']),
                        "price" => $app->xss(str_replace([','], '', $_POST['price'])),
                        "notes" => $app->xss($_POST['notes']),
                        "date_poster" => date("Y-m-d H:i:s"),
                        "user" => $app->getSession("accounts")['id'] ?? null,
                    ];
                    $app->update("salary_advance", $insert, ["id" => $data['id']]);
                    $jatbi->logs('hrm', 'salary-advance-edit', [$insert]);
                    echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
                }
            } else {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
            }
        }
    })->setPermissions(['salary-advance.edit']);

    $app->router("/salary-advance-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("salary_advance", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("salary_advance", ["deleted" => 1], ["id" => $data['id']]);
                }
                $jatbi->logs('hrm', 'salary-advance-deleted', $datas);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['salary-advance.deleted']);

    $app->router('/timekeeping', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template, $accStore) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Chấm công");

            // Danh sách năm
            $years = [];
            for ($i = 2020; $i <= date('Y'); $i++) {
                $years[] = ["value" => $i, "text" => $i];
            }

            // Danh sách tháng
            $months = [];
            for ($i = 1; $i <= 12; $i++) {
                $value = str_pad($i, 2, "0", STR_PAD_LEFT);
                $months[] = ["value" => $value, "text" => $i];
            }

            $vars['years'] = $years;
            $vars['months'] = $months;

            // Nhân viên theo cửa hàng
            $personnels = $app->select("personnels", ["id(value)", "name(text)"], [
                "deleted" => 0,
                "status" => 'A',
                "stores" => $accStore
            ]);
            $vars['personnels'] = array_merge(
                [['value' => '', 'text' => $jatbi->lang('Chọn nhân viên')]],
                $personnels
            );

            $office = $app->select("offices", ["id(value)", "name(text)"], [
                "deleted" => 0,
                "status" => 'A',
            ]);
            $vars['office'] = array_merge(
                [['value' => '', 'text' => $jatbi->lang('Chọn phòng ban')]],
                $office
            );

            // Lấy filter
            $year = $_GET['year'] ?? date('Y');
            $month = $_GET['month'] ?? date('m');
            $personnelF = $_GET['personnel'] ?? '';
            $officeF = $_GET['office'] ?? '';
            $stores = $_GET['stores'] ?? $accStore;

            $date_form = date("t", strtotime($year . "-" . $month . "-01"));

            // --- fix lọc phòng ban ---
            $whereOffice = [
                "deleted" => 0,
                "status" => 'A'
            ];
            if (!empty($officeF)) {
                $whereOffice["id"] = $officeF;
            }
            $offices = $app->select("offices", "*", $whereOffice);

            // Danh sách ngày trong tháng
            $dates = [];
            for ($day = 1; $day <= $date_form; $day++) {
                $dayFormatted = str_pad($day, 2, "0", STR_PAD_LEFT);
                $date = date("Y-m-d", strtotime($year . "-" . $month . "-" . $dayFormatted));
                $week = date("N", strtotime($date));
                $dates[] = [
                    "name" => $dayFormatted,
                    "date" => $date,
                    "week" => $week,
                ];
            }

            $datas = [];

            foreach (($offices ?? []) as $key => $office) {

                // $joinPersonnel = [
                //     "[><]personnels_contract" => ["id" => "personnels"]
                // ];

                // $wherePersonnel = [
                //     "personnels.office" => $office['id'],
                //     "personnels.deleted" => 0,
                //     "personnels_contract.deleted" => 0,
                //     "personnels.status" => 'A',
                //     "personnels.stores" => $stores,
                //     "GROUP" => "personnels.id"
                // ];

                $wherePersonnel = [
                    "office" => $office['id'],
                    "personnels.deleted" => 0,
                    "personnels.status" => 'A',
                    "personnels.stores" => $stores,
                    "GROUP" => "personnels.id"
                ];
                if (!empty($personnelF)) {
                    $wherePersonnel["id"] = $personnelF;
                }

                $SelectPer = $app->select("personnels", "*", $wherePersonnel);


                $datas[$key] = [
                    "name" => $office['name'],
                    "personnels" => []
                ];

                if (empty($SelectPer))
                    continue;

                $perIds = array_column($SelectPer, "id");

                // --- Query trước toàn bộ dữ liệu ---
                $timekeepingAll = $app->select("timekeeping", ["personnels", "date", "checkin", "checkout"], [
                    "date[>=]" => $year . "-" . $month . "-01",
                    "date[<=]" => $year . "-" . $month . "-" . $date_form,
                    "personnels" => $perIds
                ]);
                $timekeepingMap = [];
                foreach ($timekeepingAll as $c) {
                    $timekeepingMap[$c['personnels']][$c['date']] = $c;
                }

                $rostersAll = $app->select("rosters", ["id", "personnels", "date", "timework"], [
                    "personnels" => $perIds,
                    "deleted" => 0
                ]);
                $rosterMap = [];
                foreach ($rostersAll as $r) {
                    $rosterMap[$r['personnels']] = $r['timework'];
                }

                $timeworkDetailsAll = $app->select("timework_details", "*", [
                    "timework" => array_values($rosterMap)
                ]);
                $timeworkDetailsMap = [];
                foreach ($timeworkDetailsAll as $t) {
                    $timeworkDetailsMap[$t['timework']][$t['week']] = $t;
                }

                $furloughsAll = $app->select("furlough", "*", [
                    "personnels" => $perIds,
                    "date_from[<=]" => $year . "-" . $month . "-" . $date_form,
                    "date_to[>=]" => $year . "-" . $month . "-01",
                    "deleted" => 0,
                    "status" => 'A'
                ]);
                $furloughMap = [];
                foreach ($furloughsAll as $f) {
                    for ($d = strtotime($f['date_from']); $d <= strtotime($f['date_to']); $d += 86400) {
                        $furloughMap[$f['personnels']][date("Y-m-d", $d)] = $f;
                    }
                }

                // --- Gán dữ liệu cho từng nhân viên ---
                foreach ($SelectPer as $per) {
                    $datas[$key]["personnels"][$per['id']] = [
                        "id" => $per['id'],
                        "name" => $per['name'],
                        "dates" => []
                    ];

                    foreach ($dates as $date) {
                        $checked = $timekeepingMap[$per['id']][$date['date']] ?? null;
                        $roster = $rosterMap[$per['id']] ?? null;
                        $timeworkDetail = $roster ? ($timeworkDetailsMap[$roster][$date['week']] ?? null) : null;
                        $furlough = $furloughMap[$per['id']][$date['date']] ?? null;

                        $color = "bg-light bg-opacity-10";
                        $off = 0;
                        $offcontent = "";

                        if ($timeworkDetail && $timeworkDetail['off'] == 1) {
                            $off = 1;
                            $offcontent = 'OFF';
                            $color = 'bg-primary bg-opacity-10';
                        } else {
                            if (!empty($checked['checkin']) && empty($checked['checkout'])) {
                                $color = 'bg-danger bg-opacity-10';
                            } elseif (empty($checked['checkin']) && empty($checked['checkout'])) {
                                if (strtotime($date['date'] . ' ' . $setting['timework_to']) <= strtotime(date("Y-m-d H:i:s"))) {
                                    $color = 'bg-warning bg-opacity-10';
                                } else {
                                    $color = 'bg-light bg-opacity-10';
                                }
                            } elseif (!empty($checked['checkin']) && !empty($checked['checkout'])) {
                                $color = 'bg-success bg-opacity-10';
                            }
                        }

                        if ($furlough) {
                            $off = 1;
                            $offcontent = $app->get("furlough_categorys", "code", ["id" => $furlough['id']]);
                            $color = 'bg-primary bg-opacity-25';
                        }

                        $datas[$key]["personnels"][$per['id']]["dates"][$date['date']] = [
                            "name" => $date['name'],
                            "date" => $date['date'],
                            "week" => $date['week'],
                            "color" => $color,
                            "checkin" => [
                                "time" => $checked['checkin'] ?? null,
                            ],
                            "checkout" => [
                                "time" => $checked['checkout'] ?? null,
                            ],
                            "off" => [
                                "status" => $off,
                                "content" => $offcontent,
                            ],
                        ];
                    }
                }
            }

            $vars['dates'] = $dates;
            $vars['datas'] = $datas;
            $vars['offices'] = $offices;
            $vars['furlough_categorys'] = $app->select("furlough_categorys", "*", ["deleted" => 0, "status" => 'A']);

            echo $app->render($template . '/hrm/timekeeping.html', $vars);
        }
    })->setPermissions(['timekeeping']);


    //     $app->router('/timekeeping', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template, $accStore) {
    //     if ($app->method() === 'GET') {
    //         $vars['title'] = $jatbi->lang("Chấm công");

    //         // --- Chuẩn bị dữ liệu tĩnh và filter (Không thay đổi) ---
    //         $years = [];
    //         for ($i = 2020; $i <= date('Y'); $i++) {
    //             $years[] = ["value" => $i, "text" => $i];
    //         }
    //         $months = [];
    //         for ($i = 1; $i <= 12; $i++) {
    //             $value = str_pad($i, 2, "0", STR_PAD_LEFT);
    //             $months[] = ["value" => $value, "text" => $i];
    //         }
    //         $vars['years'] = $years;
    //         $vars['months'] = $months;

    //         // Lấy filter
    //         $year = $_GET['year'] ?? date('Y');
    //         $month = $_GET['month'] ?? date('m');
    //         $personnelF = $_GET['personnel'] ?? '';
    //         $officeF = $_GET['office'] ?? '';
    //         $stores = $_GET['stores'] ?? $accStore;
    //         $date_form = date("t", strtotime($year . "-" . $month . "-01"));

    //         // --- Truy vấn dữ liệu cho các Select Box (Giữ nguyên) ---
    //         $personnelsList = $app->select("personnels", ["id(value)", "name(text)"], [
    //             "deleted" => 0,
    //             "status" => 'A',
    //             "stores" => $accStore
    //         ]);
    //         $vars['personnels'] = array_merge(
    //             [['value' => '', 'text' => $jatbi->lang('Chọn nhân viên')]],
    //             $personnelsList
    //         );

    //         $officeList = $app->select("offices", ["id(value)", "name(text)"], [
    //             "deleted" => 0,
    //             "status" => 'A',
    //         ]);
    //         $vars['office'] = array_merge(
    //             [['value' => '', 'text' => $jatbi->lang('Chọn phòng ban')]],
    //             $officeList
    //         );

    //         // --- Danh sách ngày trong tháng (Giữ nguyên) ---
    //         $dates = [];
    //         for ($day = 1; $day <= $date_form; $day++) {
    //             $dayFormatted = str_pad($day, 2, "0", STR_PAD_LEFT);
    //             $date = date("Y-m-d", strtotime($year . "-" . $month . "-" . $dayFormatted));
    //             $week = date("N", strtotime($date));
    //             $dates[] = [
    //                 "name" => $dayFormatted,
    //                 "date" => $date,
    //                 "week" => $week,
    //             ];
    //         }

    //         // --- Lọc phòng ban (Giữ nguyên) ---
    //         $whereOffice = [
    //             "deleted" => 0,
    //             "status" => 'A'
    //         ];
    //         if (!empty($officeF)) {
    //             $whereOffice["id"] = $officeF;
    //         }
    //         $offices = $app->select("offices", "*", $whereOffice);

    //         // --- Tối ưu: Lấy trước toàn bộ nhân viên cần xử lý ---
    //         $allOfficeIds = array_column($offices ?? [], 'id');
    //         if (empty($allOfficeIds)) {
    //             $vars['dates'] = $dates;
    //             $vars['datas'] = [];
    //             $vars['offices'] = $offices;
    //             $vars['furlough_categorys'] = $app->select("furlough_categorys", "*", ["deleted" => 0, "status" => 'A']);
    //             echo $app->render($template . '/hrm/timekeeping.html', $vars);
    //             return;
    //         }

    //         $wherePersonnelAll = [
    //             "office" => $allOfficeIds, // Lọc theo tất cả các phòng ban đã chọn/tìm thấy
    //             "deleted" => 0,
    //             "status" => 'A',
    //             "stores" => $stores
    //         ];
    //         if (!empty($personnelF)) {
    //             $wherePersonnelAll["id"] = $personnelF; // Nếu có lọc nhân viên cụ thể
    //         }
    //         $allPersonnels = $app->select("personnels", "*", $wherePersonnelAll);

    //         if (empty($allPersonnels)) {
    //             $vars['dates'] = $dates;
    //             $vars['datas'] = [];
    //             $vars['offices'] = $offices;
    //             $vars['furlough_categorys'] = $app->select("furlough_categorys", "*", ["deleted" => 0, "status" => 'A']);
    //             echo $app->render($template . '/hrm/timekeeping.html', $vars);
    //             return;
    //         }

    //         $perIds = array_column($allPersonnels, "id");
    //         $personnelsByOffice = [];
    //         foreach ($allPersonnels as $per) {
    //             $personnelsByOffice[$per['office']][$per['id']] = $per;
    //         }

    //         // --- Query trước toàn bộ dữ liệu chỉ với một lần gọi cho mỗi bảng ---
    //         // 1. Chấm công
    //         $timekeepingAll = $app->select("timekeeping", ["personnels", "date", "checkin", "checkout"], [
    //             "date[>=]" => $year . "-" . $month . "-01",
    //             "date[<=]" => $year . "-" . $month . "-" . $date_form,
    //             "personnels" => $perIds
    //         ]);
    //         $timekeepingMap = [];
    //         foreach ($timekeepingAll as $c) {
    //             $timekeepingMap[$c['personnels']][$c['date']] = $c;
    //         }

    //         // 2. Ca làm việc (Rosters)
    //         $rostersAll = $app->select("rosters", ["personnels", "timework"], [ // Chỉ lấy 2 cột cần thiết
    //             "personnels" => $perIds,
    //             "deleted" => 0
    //         ]);
    //         $rosterMap = [];
    //         $timeworkIds = [];
    //         foreach ($rostersAll as $r) {
    //             $rosterMap[$r['personnels']] = $r['timework'];
    //             $timeworkIds[] = $r['timework'];
    //         }
    //         $timeworkIds = array_unique($timeworkIds);

    //         // 3. Chi tiết ca làm việc (Timework Details)
    //         $timeworkDetailsAll = $app->select("timework_details", ["timework", "week", "off"], [ // Chỉ lấy 3 cột cần thiết
    //             "timework" => $timeworkIds
    //         ]);
    //         $timeworkDetailsMap = [];
    //         foreach ($timeworkDetailsAll as $t) {
    //             $timeworkDetailsMap[$t['timework']][$t['week']] = $t;
    //         }

    //         // 4. Nghỉ phép (Furloughs)
    //         $furloughsAll = $app->select("furlough", ["personnels", "date_from", "date_to", "id(furlough_id)", "categorys"], [ // Bổ sung categorys
    //             "personnels" => $perIds,
    //             "date_from[<=]" => $year . "-" . $month . "-" . $date_form,
    //             "date_to[>=]" => $year . "-" . $month . "-01",
    //             "deleted" => 0
    //         ]);
    //         $furloughMap = [];
    //         $furloughCategoryIds = [];
    //         foreach ($furloughsAll as $f) {
    //             for ($d = strtotime($f['date_from']); $d <= strtotime($f['date_to']); $d += 86400) {
    //                 $dateKey = date("Y-m-d", $d);
    //                 // Đảm bảo logic xử lý ngày nghỉ nằm trong tháng được lặp đúng
    //                 if ($dateKey >= $year . "-" . $month . "-01" && $dateKey <= $year . "-" . $month . "-" . $date_form) {
    //                     $furloughMap[$f['personnels']][$dateKey] = $f;
    //                     $furloughCategoryIds[] = $f['categorys'];
    //                 }
    //             }
    //         }
    //         $furloughCategoryIds = array_unique($furloughCategoryIds);

    //         // 5. Categorys Nghỉ phép (Tối ưu: Chỉ truy vấn các category ID đang được dùng)
    //         $furloughCategorysAll = $app->select("furlough_categorys", ["id", "code"], [
    //             "id" => $furloughCategoryIds
    //         ]);
    //         $furloughCategoryCodeMap = [];
    //         foreach($furloughCategorysAll as $cat) {
    //             $furloughCategoryCodeMap[$cat['id']] = $cat['code'];
    //         }

    //         // Truy vấn furlough_categorys đầy đủ cho phần Legend
    //         $vars['furlough_categorys'] = $app->select("furlough_categorys", "*", ["deleted" => 0, "status" => 'A']);


    //         // --- Lặp qua dữ liệu đã chuẩn bị để gán vào $datas (Giữ nguyên logic) ---
    //         $datas = [];
    //         foreach (($offices ?? []) as $key => $office) {
    //             $officeId = $office['id'];
    //             $datas[$key] = [
    //                 "name" => $office['name'],
    //                 "personnels" => []
    //             ];

    //             $SelectPer = $personnelsByOffice[$officeId] ?? [];

    //             if (empty($SelectPer))
    //                 continue;

    //             // --- Gán dữ liệu cho từng nhân viên ---
    //             foreach ($SelectPer as $per) {
    //                 $datas[$key]["personnels"][$per['id']] = [
    //                     "id" => $per['id'],
    //                     "name" => $per['name'],
    //                     "dates" => []
    //                 ];

    //                 foreach ($dates as $date) {
    //                     $checked = $timekeepingMap[$per['id']][$date['date']] ?? null;
    //                     $roster = $rosterMap[$per['id']] ?? null;
    //                     $timeworkDetail = $roster ? ($timeworkDetailsMap[$roster][$date['week']] ?? null) : null;
    //                     $furlough = $furloughMap[$per['id']][$date['date']] ?? null;

    //                     $color = "bg-light bg-opacity-10";
    //                     $off = 0;
    //                     $offcontent = "";
    //                     $furloughCode = null; // Tối ưu: Thêm biến để lưu code

    //                     if ($timeworkDetail && $timeworkDetail['off'] == 1) {
    //                         $off = 1;
    //                         $offcontent = 'OFF';
    //                         $color = 'bg-primary bg-opacity-10';
    //                     } else {
    //                         // Tối ưu: Lấy $setting['timework_to'] bên ngoài vòng lặp nếu nó cố định
    //                         $timework_to_time = strtotime($date['date'] . ' ' . $setting['timework_to']);

    //                         if (!empty($checked['checkin']) && empty($checked['checkout'])) {
    //                             $color = 'bg-danger bg-opacity-10';
    //                         } elseif (empty($checked['checkin']) && empty($checked['checkout'])) {
    //                             if ($timework_to_time <= strtotime(date("Y-m-d H:i:s"))) {
    //                                 $color = 'bg-warning bg-opacity-10';
    //                             } else {
    //                                 $color = 'bg-light bg-opacity-10';
    //                             }
    //                         } elseif (!empty($checked['checkin']) && !empty($checked['checkout'])) {
    //                             $color = 'bg-success bg-opacity-10';
    //                         }
    //                     }

    //                     if ($furlough) {
    //                         $off = 1;
    //                         // Tối ưu: Thay vì query DB trong vòng lặp, dùng Map đã chuẩn bị
    //                         $furloughCode = $furloughCategoryCodeMap[$furlough['categorys']] ?? '';
    //                         $offcontent = $furloughCode;
    //                         $color = 'bg-primary bg-opacity-25';
    //                     }

    //                     $datas[$key]["personnels"][$per['id']]["dates"][$date['date']] = [
    //                         "name" => $date['name'],
    //                         "date" => $date['date'],
    //                         "week" => $date['week'],
    //                         "color" => $color,
    //                         "checkin" => [
    //                             "time" => $checked['checkin'] ?? null,
    //                         ],
    //                         "checkout" => [
    //                             "time" => $checked['checkout'] ?? null,
    //                         ],
    //                         "off" => [
    //                             "status" => $off,
    //                             "content" => $offcontent,
    //                         ],
    //                         // Có thể thêm thông tin timework detail nếu cần
    //                         "timework_detail" => $timeworkDetail,
    //                     ];
    //                 }
    //             }
    //         }

    //         $vars['dates'] = $dates;
    //         $vars['datas'] = $datas;
    //         $vars['offices'] = $offices;
    //         // $vars['furlough_categorys'] đã được truy vấn ở trên cho Legend

    //         echo $app->render($template . '/hrm/timekeeping.html', $vars);
    //     }
    // })->setPermissions(['timekeeping']);
    $app->router('/timekeeping-excel', ['GET'], function ($vars) use ($app, $accStore) {
        try {
            $year = $_GET['year'] ?? date('Y');
            $month = $_GET['month'] ?? date('m');
            $personnelF = $_GET['personnel'] ?? '';
            $officeF = $_GET['office'] ?? '';
            $stores = $_GET['stores'] ?? $accStore;

            $totalDays = date("t", strtotime("$year-$month-01"));
            $from_date = "$year-$month-01";
            $to_date = "$year-$month-$totalDays";

            $wherePersonnel = ["AND" => ["deleted" => 0, "status" => 'A', "stores" => $stores]];
            if (!empty($officeF)) $wherePersonnel['AND']['office'] = $officeF;
            if (!empty($personnelF)) $wherePersonnel['AND']['id'] = $personnelF;

            $personnel_list = $app->select("personnels", ["id", "code", "name", "office"], $wherePersonnel);
            if (empty($personnel_list)) {
                exit("Không có nhân viên nào thỏa mãn điều kiện lọc.");
            }
            $personnel_ids = array_column($personnel_list, 'id');

            $timekeeping_data = $app->select("timekeeping", "*", ["personnels" => $personnel_ids, "date[<>]" => [$from_date, $to_date]]);
            $furlough_data = $app->select("furlough", "*", ["personnels" => $personnel_ids, "date_from[<=]" => $to_date, "date_to[>=]" => $from_date, "deleted" => 0, "status" => 'A']);

            $rosters_data = $app->select("rosters", ["personnels", "timework", "date"], ["personnels" => $personnel_ids, "date[<=]" => $to_date, "deleted" => 0]);
            $timework_ids = array_unique(array_column($rosters_data, 'timework'));
            $timework_details_data = $app->select("timework_details", ["timework", "week", "time_from", "time_to", "off"], ["timework" => $timework_ids]);

            $office_map = array_column($app->select("offices", ["id", "name"]), 'name', 'id');
            $furlough_category_map = array_column($app->select("furlough_categorys", ["id", "code"]), 'code', 'id');

            $timekeeping_map = [];
            foreach ($timekeeping_data as $tk) $timekeeping_map[$tk['personnels']][$tk['date']] = $tk;

            $furlough_map = [];
            foreach ($furlough_data as $f) {
                for ($d = strtotime($f['date_from']); $d <= strtotime($f['date_to']); $d += 86400) {
                    $furlough_map[$f['personnels']][date("Y-m-d", $d)] = $f;
                }
            }

            $rosters_map = [];
            foreach ($rosters_data as $roster) $rosters_map[$roster['personnels']][] = $roster;
            foreach ($rosters_map as $pid => &$p_rosters) {
                usort($p_rosters, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));
            }

            $timework_details_map = [];
            foreach ($timework_details_data as $detail) $timework_details_map[$detail['timework']][$detail['week']] = $detail;

            $excelData = [];
            $stt = 1;
            foreach ($personnel_list as $personnel) {
                $pid = $personnel['id'];
                $dailyData = [];
                $total_day_hours = $total_sunday_hours = $work_days = $late_count = $late_minutes = $early_count = $early_minutes = 0;

                foreach (range(1, $totalDays) as $day) {
                    $dayStr = str_pad($day, 2, '0', STR_PAD_LEFT);
                    $current_date_str = "$year-$month-$dayStr";
                    $current_date_ts = strtotime($current_date_str);
                    $week_day = date('N', $current_date_ts);
                    $value = '';

                    $active_roster = null;
                    if (isset($rosters_map[$pid])) {
                        foreach ($rosters_map[$pid] as $roster) {
                            if (strtotime($roster['date']) <= $current_date_ts) {
                                $active_roster = $roster;
                                break;
                            }
                        }
                    }
                    $schedule = null;
                    if ($active_roster) {
                        $schedule = $timework_details_map[$active_roster['timework']][$week_day] ?? null;
                    }

                    if (isset($furlough_map[$pid][$current_date_str])) {
                        $furlough_record = $furlough_map[$pid][$current_date_str];
                        $value = $furlough_category_map[$furlough_record['furlough']] ?? 'P';
                    } elseif ($schedule && $schedule['off'] == 1) {
                        $value = 'OFF';
                    } elseif (isset($timekeeping_map[$pid][$current_date_str])) {
                        $tk = $timekeeping_map[$pid][$current_date_str];
                        if (!empty($tk['checkin']) && !empty($tk['checkout'])) {
                            $checkin_time = strtotime("$current_date_str {$tk['checkin']}");
                            $checkout_time = strtotime("$current_date_str {$tk['checkout']}");
                            $hours = ($checkout_time - $checkin_time) / 3600;
                            $work_days++;

                            if ($week_day == 7) $total_sunday_hours += $hours;
                            else $total_day_hours += $hours;

                            $value = round($hours / 8, 2);

                            if ($schedule && $schedule['off'] == 0) {
                                $expected_start = strtotime("$current_date_str {$schedule['time_from']}");
                                $expected_end = strtotime("$current_date_str {$schedule['time_to']}");

                                if ($checkin_time > $expected_start) {
                                    $late_count++;
                                    $late_minutes += ($checkin_time - $expected_start) / 60;
                                }
                                if ($checkout_time < $expected_end) {
                                    $early_count++;
                                    $early_minutes += ($expected_end - $checkout_time) / 60;
                                }
                            }
                        } else {
                            $value = 'K/L';
                        }
                    } else {
                        if ($schedule && $schedule['off'] == 0) {
                            $value = 'V';
                        }
                    }
                    $dailyData[$dayStr] = $value;
                }

                $excelData[] = [
                    'STT' => $stt++,
                    'MaNV' => $personnel['code'],
                    'HoTen' => $personnel['name'],
                    'BoPhan' => $office_map[$personnel['office']] ?? '',
                    'DailyData' => $dailyData,
                    'TongGio_Ngay' => round($total_day_hours, 2),
                    'TongGio_Dem' => 0,
                    'TongGio_CN' => round($total_sunday_hours, 2),
                    'TongGio_Tong' => round($total_day_hours + $total_sunday_hours, 2),
                    'CongLam_Ngay' => $work_days,
                    'CongLam_Gio' => round(($total_day_hours + $total_sunday_hours) / 8, 2),
                    'DiMuon_Lan' => $late_count,
                    'DiMuon_Phut' => round($late_minutes),
                    'VeSom_Lan' => $early_count,
                    'VeSom_Phut' => round($early_minutes)
                ];
            }

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle("BangCongThang_$month-$year");

            $sheet->setCellValue('A2', 'CÔNG TY NGỌC TRAI NGỌC HIỀN PHÚ QUỐC')->mergeCells('A2:D2');
            $sheet->setCellValue('A3', 'BẢNG CÔNG THÁNG ' . "$month/$year")->mergeCells('A3:D3');
            $sheet->getStyle('A2:A3')->getFont()->setBold(true);

            $headers_row6 = ['STT', 'Mã NV', 'Họ và tên', 'Bộ phận'];
            $headers_row7 = ['', '', '', ''];
            $col_index = 5;
            $days_of_week_map = ['1' => 'T2', '2' => 'T3', '3' => 'T4', '4' => 'T5', '5' => 'T6', '6' => 'T7', '7' => 'CN'];
            foreach (range(1, $totalDays) as $day) {
                $headers_row6[] = str_pad($day, 2, '0', STR_PAD_LEFT);
                $week_day = date('N', strtotime("$year-$month-$day"));
                $headers_row7[] = $days_of_week_map[$week_day];
                $col_index++;
            }
            $summary_headers = [
                'Tổng giờ' => ['Ngày', 'Đêm', 'CN', 'Tổng'],
                'Công làm' => ['Ngày', 'Giờ'],
                'Đi muộn' => ['Lần', 'Phút'],
                'Về sớm' => ['Lần', 'Phút']
            ];
            foreach ($summary_headers as $main => $subs) {
                $headers_row6[] = $main;
                for ($i = 0; $i < count($subs) - 1; $i++)
                    $headers_row6[] = '';
                foreach ($subs as $sub)
                    $headers_row7[] = $sub;
            }

            $sheet->fromArray($headers_row6, null, 'A6');
            $sheet->fromArray($headers_row7, null, 'A7');

            $sheet->mergeCells('A6:A7');
            $sheet->mergeCells('B6:B7');
            $sheet->mergeCells('C6:C7');
            $sheet->mergeCells('D6:D7');
            $col = 'E';
            $startMergeCol = 5 + $totalDays;
            foreach ($summary_headers as $main => $subs) {
                $endCol = Coordinate::stringFromColumnIndex($startMergeCol + count($subs) - 1);
                $startCol = Coordinate::stringFromColumnIndex($startMergeCol);
                $sheet->mergeCells($startCol . '6:' . $endCol . '6');
                $startMergeCol += count($subs);
            }

            $rowIndex = 8;
            foreach ($excelData as $data) {
                $colIndex = 1;
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $data['STT']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $data['MaNV']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $data['HoTen']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $data['BoPhan']);
                foreach (range(1, $totalDays) as $day) {
                    $dayStr = str_pad($day, 2, '0', STR_PAD_LEFT);
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $data['DailyData'][$dayStr] ?? '');
                }
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $data['TongGio_Ngay']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $data['TongGio_Dem']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $data['TongGio_CN']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $data['TongGio_Tong']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $data['CongLam_Ngay']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $data['CongLam_Gio']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $data['DiMuon_Lan']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $data['DiMuon_Phut']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $data['VeSom_Lan']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $data['VeSom_Phut']);
                $rowIndex++;
            }

            $lastColLetter = $sheet->getHighestColumn();
            $sheet->getStyle('A6:' . $lastColLetter . ($rowIndex - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle('A6:' . $lastColLetter . '7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle('A6:' . $lastColLetter . '7')->getFont()->setBold(true);

            $sheet->getColumnDimension('A')->setAutoSize(true);
            $sheet->getColumnDimension('B')->setAutoSize(true);
            $sheet->getColumnDimension('C')->setAutoSize(true);
            $sheet->getColumnDimension('D')->setAutoSize(true);

            for ($i = 1; $i <= $totalDays; $i++) {
                $colLetter = Coordinate::stringFromColumnIndex(4 + $i);
                $sheet->getColumnDimension($colLetter)->setWidth(5);
            }

            $summaryStartColIndex = 4 + $totalDays + 1;
            $lastColIndex = Coordinate::columnIndexFromString($lastColLetter);
            for ($i = $summaryStartColIndex; $i <= $lastColIndex; $i++) {
                $colLetter = Coordinate::stringFromColumnIndex($i);
                $sheet->getColumnDimension($colLetter)->setAutoSize(true);
            }

            ob_end_clean();
            $filename = "BangCong_Thang_{$month}_{$year}.xlsx";
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        } catch (Exception $e) {
            ob_end_clean();
            http_response_code(500);
            exit("Lỗi khi tạo file Excel: " . $e->getMessage());
        }
    })->setPermissions(['timekeeping']);

    $app->router("/timekeeping-add", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $accStore) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Thêm Chấm công");
            $vars['personnels'] = $app->select("personnels", [
                "id (value)",
                "name (text)"
            ], [
                "deleted" => 0,
                "status" => "A",
                "stores" => $accStore,
                "ORDER" => ["name" => "ASC"]
            ]);
            echo $app->render($template . '/hrm/timekeeping-post.html', $vars, $jatbi->ajax());
        }
        if ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            if (empty($_POST['personnels']) || empty($_POST['date']) || empty($_POST['status'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng không để trống")]);
            }
            if ($_POST['personnels'] && $_POST['date'] && $_POST['status']) {
                $gettime = $app->get("timekeeping", "*", [
                    "personnels" => $app->xss($_POST['personnels']),
                    "date" => date("Y-m-d", strtotime($_POST['date'])),
                ]);
                if ($_POST['status'] == 1) {
                    $timekeeping = [
                        "personnels" => $app->xss($_POST['personnels']),
                        "date" => $app->xss(date("Y-m-d", strtotime($_POST['date']))),
                        "checkin" => $app->xss(date("H:i:s", strtotime($_POST['date']))),
                        "date_poster" => date("Y-m-d H:i:s"),
                    ];
                }
                if ($_POST['status'] == 2) {
                    $timekeeping = [
                        "personnels" => $app->xss($_POST['personnels']),
                        "date" => $app->xss(date("Y-m-d", strtotime($_POST['date']))),
                        "checkout" => $app->xss(date("H:i:s", strtotime($_POST['date']))),
                        "date_poster" => date("Y-m-d H:i:s"),
                    ];
                }
                if ($gettime > 1) {
                    $app->update("timekeeping", $timekeeping, ["id" => $gettime['id']]);
                    $getID = $gettime['id'];
                } else {
                    $app->insert("timekeeping", $timekeeping);
                    $getID = $app->id();
                }
                $insert = [
                    "personnels" => $app->xss($_POST['personnels']),
                    "notes" => $app->xss($_POST['notes']),
                    "date" => $app->xss($_POST['date']),
                    "status" => $app->xss($_POST['status']),
                    "user" => $app->getSession("accounts")['id'] ?? 0,
                    "date_poster" => date("Y-m-d H:i:s"),
                ];
                $app->insert("timekeeping_details", $insert);
                $jatbi->logs('timekeeping', 'add', [$insert, $timeLate ?? ""]);

                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            }
        }
    })->setPermissions(['timekeeping.add']);

    $app->router("/timekeeping-view/{personnelId}/{date}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $vars['title'] = $jatbi->lang("Nhật ký chấm công");
        $per = $app->get("personnels", "*", ["id" => $app->xss($vars['personnelId'])]);
        if ($per > 1) {
            $datas = $app->select("timekeeping_details", "*", [
                "personnels" => $per['id'],
                "date[<>]" => [date("Y-m-d 00:00:00", strtotime($vars['date'])), date("Y-m-d 23:59:59", strtotime($vars['date']))],
                "deleted" => 0,
            ]);
            $vars['datas'] = $datas;
            $vars['per'] = $per;
            $vars['date'] = $vars['date'];
            echo $app->render($template . '/hrm/timekeeping-view.html', $vars, $jatbi->ajax());
        } else {
            echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
        }
    })->setPermissions(['timekeeping.edit']);

    $app->router("/timekeeping-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $accStore) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Sửa Ứng lương");
            $vars['data'] = $app->get("salary_advance", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($vars['data'])) {
                $vars['personnels'] = $app->select("personnels", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "stores" => $accStore, "ORDER" => ["name" => "ASC"]]);
                echo $app->render($template . '/hrm/salary-advance-post.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $data = $app->get("reward_discipline", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($data)) {
                if ($app->xss($_POST['date']) == '' || $app->xss($_POST['personnels']) == '' || $app->xss($_POST['price']) == '') {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
                } else {
                    $insert = [
                        "personnels" => $app->xss($_POST['personnels']),
                        "date" => $app->xss($_POST['date']),
                        "price" => $app->xss(str_replace([','], '', $_POST['price'])),
                        "notes" => $app->xss($_POST['notes']),
                        "date_poster" => date("Y-m-d H:i:s"),
                        "user" => $app->getSession("accounts")['id'] ?? null,
                    ];
                    $app->update("salary_advance", $insert, ["id" => $data['id']]);
                    $jatbi->logs('hrm', 'salary-advance-edit', [$insert]);
                    echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
                }
            } else {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
            }
        }
    })->setPermissions(['timekeeping.edit']);

    $app->router("/timekeeping-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("salary_advance", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("salary_advance", ["deleted" => 1], ["id" => $data['id']]);
                }
                $jatbi->logs('hrm', 'salary-advance-deleted', $datas);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['timekeeping.deleted']);

    $app->router('/salary', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $accStore) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Tính lương");
            for ($i = 2020; $i <= date('Y'); $i++) {
                $years[] = [
                    "value" => $i,
                    "text" => $i,
                ];
            }
            for ($i = 1; $i <= 12; $i++) {
                $value = str_pad($i, 2, "0", STR_PAD_LEFT); // thêm số 0 vào đầu nếu chỉ có 1 chữ số
                $months[] = [
                    "value" => $value,
                    "text" => $i,
                ];
            }
            $salary_categorys = $app->select("salary_categorys", ["id", "name"], ["deleted" => 0, "status" => "A",]);

            $vars['years'] = $years;
            $vars['months'] = $months;
            $vars['offices'] = $app->select("offices", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "ORDER" => ["name" => "ASC"]]);
            $vars['salary_categorys'] = $salary_categorys;

            $year = isset($_GET['year']) ? $_GET['year'] : date('Y');
            $month = isset($_GET['month']) ? $_GET['month'] : date('m');
            $offices = isset($_GET['offices']) ? $_GET['offices'] : '';
            $totalDays = date("t", mktime(0, 0, 0, $month, 1, $year));
            for ($day = 1; $day <= $totalDays; $day++) {
                $date = strtotime($year . "-" . $month . "-" . $day);
                if ($day < 10) {
                    $day = '0' . $day;
                }
                $dates[] = [
                    "name" => $day,
                    "date" => date("Y-m-d", $date),
                    "week" => date('N', $date)
                ];
            }

            $rosters = [];
            $timework_detail = [];
            $timekeeping = [];
            $where = [
                "personnels.deleted" => 0,
                "personnels.status" => 'A',
                "personnels_contract.deleted" => 0,
                "stores" => $accStore,
                "ORDER" => [
                    "personnels.name" => "ASC",
                    "personnels_contract.date_contract" => "ASC",
                ],
                "OR" => [
                    "date_contract[<>]" => [($year . "-" . $month . "-01"), ($year . "-" . $month . "-" . $totalDays)],
                    "date_end[<>]" => [($year . "-" . $month . "-01"), ($year . "-" . $month . "-" . $totalDays)],
                    "AND" => [
                        "date_contract[<]" => ($year . "-" . $month . "-01"),
                        "date_end[>]" => ($year . "-" . $month . "-" . $totalDays),
                    ]
                ]
            ];
            if (!empty($offices)) {
                $where['AND']['personnels.office'] = $offices;
            }
            $app->select("personnels", [
                "[>]offices" => ["office" => "id"],
                "[>]personnels_contract" => ["id" => "personnels"],
            ], [
                'personnels.id',
                'personnels.name',
                'offices.name(offices_name)',
                "personnels_contract.id(contract_id)",
                "personnels_contract.date_contract",
                "personnels_contract.date_end",
            ], $where, function ($personnel) use (&$datas, &$rosters, &$timework_detail, &$timekeeping, $jatbi, $app, $dates, $year, $month, $totalDays) {
                $rosters[$personnel['id']]['rosters'] = $app->select("rosters", "*", [ // lấy bảng danh sách phân công trong tháng
                    "personnels" => $personnel['id'],
                    "date[<>]" => [($year . "-" . $month . "-01"), ($year . "-" . $month . "-" . $totalDays)],
                    "deleted" => 0,
                    "ORDER" => ["date" => "ASC"]
                ]);
                if (empty($rosters[$personnel['id']]['rosters']) || $rosters[$personnel['id']]['rosters'][0]['date'] > ($year . "-" . $month . "-01")) {
                    array_unshift($rosters[$personnel['id']]['rosters'], $app->get("rosters", "*", [ // lấy bảng phân công ngày trước tháng
                        "personnels" => $personnel['id'],
                        "date[<=]" => ($year . "-" . $month . "-01"),
                        "deleted" => 0,
                        "ORDER" => ["date" => "DESC"],
                    ]));
                }
                $n = 0;
                $hours = 0;
                $roster = $rosters[$personnel['id']]['rosters'] ?? [];
                if (!isset($datas[$personnel['id']])) {
                    $datas[$personnel['id']]["personnel_name"] = $personnel['name'];
                    $datas[$personnel['id']]["offices_name"] = $personnel['offices_name'];
                    $timekeeping[$personnel['id']] = [
                        "price" => [],
                        'total_working_days' => 0,
                        'working_days' => 0,
                        'paid_leave_days' => 0,
                        'unpaid_leave_days' => 0,
                        'unapproved_leave' => 0,
                        'violation' => 0,
                        'total_salary' => 0,
                    ];
                    foreach ($dates as $date) {
                        if ($roster[$n]) {
                            while (isset($roster[$n + 1]) && $roster[$n + 1]['date'] <= $date['date']) { // lấy phân công phù hợp
                                $n++;
                            }
                            if (empty($timework_detail[$roster[$n]['id']])) {
                                $timework_detail[$roster[$n]['id']] = $app->select("timework_details", [
                                    'time_from',
                                    'time_to',
                                    'off',
                                ], [
                                    "timework" => $roster[$n]['timework'] ?? '',
                                    "ORDER" => ["week" => "ASC"],
                                ]);
                            }
                            if (!empty($timework_detail[$roster[$n]['id']][$date['week'] - 1])) {
                                if ($timework_detail[$roster[$n]['id']][$date['week'] - 1]['off'] != 1) {
                                    $timekeeping[$personnel['id']]['total_working_days']++;
                                    if ($personnel['date_contract'] > $date['date'] || $personnel['date_end'] < $date['date']) {
                                        continue;
                                    }
                                    $furloughs = $app->get("furlough", "type", [ // lấy ngày nghỉ phép
                                        "personnels" => $personnel['id'],
                                        "date_from[<=]" => $date['date'],
                                        "date_to[>=]" => $date['date'],
                                        "deleted" => 0,
                                    ]);
                                    if ($furloughs) {
                                        if ($furloughs == 2) {
                                            $timekeeping[$personnel['id']]['unpaid_leave_days']++;
                                        } else { // nghỉ có lương
                                            $timekeeping[$personnel['id']]['unpaid_leave_days']++;
                                            $hours += $jatbi->diffHours($timework_detail[$roster[$n]['id']][$date['week'] - 1]['time_from'], $timework_detail[$roster[$n]['id']][$date['week'] - 1]['time_to']);
                                        }
                                    } else { // kiểm tra đi làm
                                        $checkin = $app->min("timekeeping_details", "date", [
                                            "personnels" => $personnel['id'],
                                            "deleted" => 0,
                                            "date[<>]" => [
                                                $date['date'] . " 00:00:00",
                                                $date['date'] . " 23:59:59"
                                            ]
                                        ]);
                                        $checkout = $app->max("timekeeping_details", "date", [
                                            "personnels" => $personnel['id'],
                                            "deleted" => 0,
                                            "date[<>]" => [
                                                $date['date'] . " 00:00:00",
                                                $date['date'] . " 23:59:59"
                                            ]
                                        ]);
                                        $timetime_checkin = !empty($checkin) ? date("H:i:s", strtotime($checkin)) : null;
                                        $timetime_checkout = !empty($checkout) ? date("H:i:s", strtotime($checkout)) : null;
                                        if ($timetime_checkin) {
                                            if ($timetime_checkin > $timework_detail[$roster[$n]['id']][$date['week'] - 1]['time_from']) {
                                                $min = $jatbi->diffHours($timework_detail[$roster[$n]['id']][$date['week'] - 1]['time_from'], $timetime_checkin, 'minute');
                                                $price = $app->get("time_late", "price", [
                                                    "type" => 1,
                                                    "value[<=]" => $min,
                                                    "status" => 'A',
                                                    "deleted" => 0,
                                                    "date[<=]" => $date['date'],
                                                    "ORDER" => ["id" => "DESC"],
                                                ]);
                                                $timekeeping[$personnel['id']]['violation'] += $price ?? 0;
                                            }
                                            if ($timetime_checkout < $timework_detail[$roster[$n]['id']][$date['week'] - 1]['time_to']) {
                                                $min = $jatbi->diffHours($timetime_checkout, $timework_detail[$roster[$n]['id']][$date['week'] - 1]['time_to'], 'minute');
                                                $price = $app->get("time_late", "price", [
                                                    "type" => 2,
                                                    "value[<=]" => $min,
                                                    "status" => 'A',
                                                    "deleted" => 0,
                                                    "date[<=]" => $date['date'],
                                                    "ORDER" => ["id" => "DESC"],
                                                ]);
                                                $timekeeping[$personnel['id']]['violation'] += $price;
                                            }
                                            if ($timetime_checkin && $timetime_checkout) {
                                                $hours += $jatbi->diffHours($timework_detail[$roster[$n]['id']][$date['week'] - 1]['time_from'], $timework_detail[$roster[$n]['id']][$date['week'] - 1]['time_to']);
                                                $timekeeping[$personnel['id']]['working_days']++;
                                            }
                                        } else {
                                            $timekeeping[$personnel['id']]['unapproved_leave']++;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    $salary_advance = $app->sum("salary_advance", 'price', [
                        "personnels" => $personnel['id'],
                        "date[<>]" => [
                            $year . '-' . $month . '-01',
                            $year . '-' . $month . '-' . $totalDays
                        ]
                    ]);
                    $discipline = $app->sum("reward_discipline", 'price', [
                        "personnels" => $personnel['id'],
                        "type" => 2,
                        "deleted" => 0,
                        "date[<>]" => [
                            $year . '-' . $month . '-01',
                            $year . '-' . $month . '-' . $totalDays
                        ]
                    ]);
                    $reward = $app->sum("reward_discipline", 'price', [
                        "personnels" => $personnel['id'],
                        "type" => 1,
                        "deleted" => 0,
                        "date[<>]" => [
                            $year . '-' . $month . '-01',
                            $year . '-' . $month . '-' . $totalDays
                        ]
                    ]);
                    $datas[$personnel['id']]["discipline"] = number_format((float) $discipline, 0, ',', '.');
                    $datas[$personnel['id']]["reward"] = number_format((float) $reward, 0, ',', '.');
                    $datas[$personnel['id']]["salary_advance"] = number_format((float) $salary_advance, 0, ',', '.');

                    $timekeeping[$personnel['id']]['total_salary'] += (float) $reward - (float) $salary_advance - (float) $discipline - (float) $timekeeping[$personnel['id']]['violation'];
                } else {
                    $day_from = $year . "-" . $month . "-01";
                    $day_to = $year . "-" . $month . "-" . $totalDays;
                    $day_from = ($personnel['date_contract'] > $day_from) ? $personnel['date_contract'] : $day_from;
                    $day_to = ($personnel['date_end'] < $day_to) ? $personnel['date_end'] : $day_to;
                    $from = date("d", strtotime($day_from));
                    $to = date("d", strtotime($day_to));
                    $n = 0;
                    $roster = $rosters[$personnel['id']]['rosters'] ?? [];
                    for ($i = $from - 1; $i < $to; $i++) {
                        if ($roster[$n]) {
                            while (isset($roster[$n + 1]) && $roster[$n + 1]['date'] <= $dates[$i]['date']) { // lấy phân công phù hợp
                                $n++;
                            }
                            if (!empty($timework_detail[$roster[$n]['id']][$dates[$i]['week'] - 1])) {
                                if ($timework_detail[$roster[$n]['id']][$dates[$i]['week'] - 1]['off'] != 1) {
                                    $furloughs = $app->get("furlough", "type", [ // lấy ngày nghỉ phép
                                        "personnels" => $personnel['id'],
                                        "date_from[<=]" => $dates[$i]['date'],
                                        "date_to[>=]" => $dates[$i]['date'],
                                        "deleted" => 0,
                                    ]);
                                    if ($furloughs) {
                                        if ($furloughs == 2) {
                                            $timekeeping[$personnel['id']]['unpaid_leave_days']++;
                                        } else { // nghỉ có lương
                                            $timekeeping[$personnel['id']]['unpaid_leave_days']++;
                                            $hours += $jatbi->diffHours($timework_detail[$roster[$n]['id']][$dates[$i]['week'] - 1]['time_from'], $timework_detail[$roster[$n]['id']][$dates[$i]['week'] - 1]['time_to']);
                                        }
                                    } else { // kiểm tra đi làm
                                        $checkin = $app->min("timekeeping_details", "date", [
                                            "personnels" => $personnel['id'],
                                            "deleted" => 0,
                                            "date[<>]" => [
                                                $dates[$i]['date'] . " 00:00:00",
                                                $dates[$i]['date'] . " 23:59:59"
                                            ]
                                        ]);
                                        $checkout = $app->max("timekeeping_details", "date", [
                                            "personnels" => $personnel['id'],
                                            "deleted" => 0,
                                            "date[<>]" => [
                                                $dates[$i]['date'] . " 00:00:00",
                                                $dates[$i]['date'] . " 23:59:59"
                                            ]
                                        ]);
                                        $timetime_checkin = !empty($checkin) ? date("H:i:s", strtotime($checkin)) : null;
                                        $timetime_checkout = !empty($checkout) ? date("H:i:s", strtotime($checkout)) : null;
                                        if ($timetime_checkin) {
                                            if ($timetime_checkin > $timework_detail[$roster[$n]['id']][$dates[$i]['week'] - 1]['time_from']) {
                                                $min = $jatbi->diffHours($timework_detail[$roster[$n]['id']][$dates[$i]['week'] - 1]['time_from'], $timetime_checkin, 'minute');
                                                $price = $app->get("time_late", "price", [
                                                    "type" => 1,
                                                    "value[<=]" => $min,
                                                    "status" => 'A',
                                                    "deleted" => 0,
                                                    "date[<=]" => $dates[$i]['date'],
                                                    "ORDER" => ["id" => "DESC"],
                                                ]);
                                                $timekeeping[$personnel['id']]['violation'] += $price ?? 0;
                                                $timekeeping[$personnel['id']]['total_salary'] -= $price;
                                            }
                                            if ($timetime_checkout < $timework_detail[$roster[$n]['id']][$dates[$i]['week'] - 1]['time_to']) {
                                                $min = $jatbi->diffHours($timetime_checkout, $timework_detail[$roster[$n]['id']][$dates[$i]['week'] - 1]['time_to'], 'minute');
                                                $price = $app->get("time_late", "price", [
                                                    "type" => 2,
                                                    "value[<=]" => $min,
                                                    "status" => 'A',
                                                    "deleted" => 0,
                                                    "date[<=]" => $dates[$i]['date'],
                                                    "ORDER" => ["id" => "DESC"],
                                                ]);
                                                $timekeeping[$personnel['id']]['violation'] += $price;
                                                $timekeeping[$personnel['id']]['total_salary'] -= $price;
                                            }
                                            if ($timetime_checkin && $timetime_checkout) {
                                                $hours += $jatbi->diffHours($timework_detail[$roster[$n]['id']][$dates[$i]['week'] - 1]['time_from'], $timework_detail[$roster[$n]['id']][$dates[$i]['week'] - 1]['time_to']);
                                                $timekeeping[$personnel['id']]['working_days']++;
                                            }
                                        } else {
                                            $timekeeping[$personnel['id']]['unapproved_leave']++;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                $salary_details = $app->select("personnels_contract_salary_details", [
                    'type',
                    'content',
                    'price',
                    'duration',
                ], [
                    "contract" => $personnel['contract_id'],
                    "deleted" => 0,
                    "ORDER" => ["content" => "ASC"]
                ]);
                $day_from = $year . "-" . $month . "-01";
                $day_to = $year . "-" . $month . "-" . $totalDays;
                $day_from = ($personnel['date_contract'] > $day_from) ? $personnel['date_contract'] : $day_from;
                $day_to = ($personnel['date_end'] < $day_to) ? $personnel['date_end'] : $day_to;
                $from = date("d", strtotime($day_from));
                $to = date("d", strtotime($day_to));

                foreach ($salary_details as $salary) {
                    if ($salary['type'] == 1) {
                        if ($salary['duration'] == 1) {
                            $timekeeping[$personnel['id']]['total_salary'] += $salary['price'] * $hours;
                            $datas[$personnel['id']]["salary"][$salary['content']] = number_format($salary['price'], 0, ',', '.') . " / giờ";
                        } elseif ($salary['duration'] == 3) {
                            $timekeeping[$personnel['id']]['total_salary'] += $salary['price'] * ($timekeeping[$personnel['id']]['working_days'] + $timekeeping[$personnel['id']]['paid_leave_days']);
                            $datas[$personnel['id']]["salary"][$salary['content']] = number_format($salary['price'], 0, ',', '.') . " / ngày";
                        } elseif ($salary['duration'] == 4 && $timekeeping[$personnel['id']]['total_working_days'] > 0) {
                            $timekeeping[$personnel['id']]['total_salary'] += ($salary['price'] / $timekeeping[$personnel['id']]['total_working_days']) * ($timekeeping[$personnel['id']]['working_days'] + $timekeeping[$personnel['id']]['paid_leave_days']);
                            $datas[$personnel['id']]["salary"][$salary['content']] = number_format($salary['price'], 0, ',', '.') . " / tháng";
                        }
                    } elseif ($salary['type'] == 2) {
                        if ($salary['duration'] == 1) {
                            $timekeeping[$personnel['id']]['total_salary'] += $salary['price'] * $hours;
                            $datas[$personnel['id']]["salary"][$salary['content']] = number_format($salary['price'], 0, ',', '.') . " / giờ";
                        } elseif ($salary['duration'] == 3) {
                            $timekeeping[$personnel['id']]['total_salary'] += $salary['price'] * $timekeeping[$personnel['id']]['working_days'];
                            $datas[$personnel['id']]["salary"][$salary['content']] = number_format($salary['price'], 0, ',', '.') . " / ngày";
                        } elseif ($salary['duration'] == 4 && $timekeeping[$personnel['id']]['total_working_days'] > 0) {
                            $timekeeping[$personnel['id']]['total_salary'] += $timekeeping[$personnel['id']]['working_days'];
                            $datas[$personnel['id']]["salary"][$salary['content']] = number_format($salary['price'], 0, ',', '.') . " / tháng";
                        }
                    }
                }

                $datas[$personnel['id']]["personnel_id"] = $datas[$personnel['id']]["salary"][3] ?? '';
                $datas[$personnel['id']]["working_days"] = $timekeeping[$personnel['id']]['working_days']
                    . " / "
                    . $timekeeping[$personnel['id']]['total_working_days'];
                $datas[$personnel['id']]["timework"] = $salary[1]['price'] ?? '123';
                $datas[$personnel['id']]["paid_leave_days"] = $timekeeping[$personnel['id']]['paid_leave_days'];
                $datas[$personnel['id']]["unpaid_leave_days"] = $timekeeping[$personnel['id']]['unpaid_leave_days'];
                $datas[$personnel['id']]["unapproved_leave"] = $timekeeping[$personnel['id']]['unapproved_leave'];
                $datas[$personnel['id']]["total_paid_days"] = $timekeeping[$personnel['id']]['working_days']
                    + $timekeeping[$personnel['id']]['paid_leave_days'];
                $datas[$personnel['id']]["violation"] = number_format((float) $timekeeping[$personnel['id']]['violation'], 0, ',', '.');
                $datas[$personnel['id']]["total"] = number_format($timekeeping[$personnel['id']]['total_salary'], 0, ',', '.');
            });
            $vars['datas'] = $datas ?? [];
            echo $app->render($template . '/hrm/salary.html', $vars);
        }
    })->setPermissions(['salary']);

    $app->router('/uniforms-items', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {

        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Danh mục Đồng phục");
            echo $app->render($template . '/hrm/uniforms-items.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

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
                        "uniform_items.item_name[~]" => $searchValue,
                        "uniform_items.size[~]" => $searchValue,
                        "uniform_items.description[~]" => $searchValue,

                    ],
                    "uniform_items.deleted" => 0,
                    "uniform_items.status[<>]" => $status,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];


            $count = $app->count("uniform_items", [
                "AND" => $where['AND'],
            ]);


            $datas = [];
            $app->select("uniform_items", "*", $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "item_name" => $data['item_name'],
                    "size" => $data['size'],
                    "description" => $data['description'],
                    "status" => $app->component("status", [
                        "url" => "/hrm/uniforms-items-status/" . $data['id'],
                        "data" => $data['status'],
                        "permission" => ['uniforms_items.edit']
                    ]),
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['uniforms_items.edit'],
                                'action' => ['data-url' => '/hrm/uniforms-items-edit/' . $data['id'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'permission' => ['uniforms_items.delete'],
                                'action' => ['data-url' => '/hrm/uniforms-items-delete?box=' . $data['id'], 'data-action' => 'modal']
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
    })->setPermissions(['uniforms_items']);

    $app->router('/uniforms-items-add', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $vars['title'] = $jatbi->lang("Thêm Đồng phục");

        if ($app->method() === 'GET') {
            echo $app->render($template . '/hrm/uniforms-items-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $post = array_map([$app, 'xss'], $_POST);

            if (empty($post['item_name'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng điền tên đồng phục")]);
                return;
            }
            if (empty($post['size'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng điền size")]);
                return;
            }

            $insert_data = [
                "item_name" => $post['item_name'],
                "size" => $post['size'],
                "description" => $post['description'],
                "status" => $post['status'],
            ];

            $app->insert("uniform_items", $insert_data);
            $jatbi->logs('uniform_items', 'add', $insert_data);

            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Thêm mới thành công')]);
        }
    })->setPermissions(['uniforms_items.add']);

    $app->router('/uniforms-items-edit/{id}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $id = (int) ($vars['id'] ?? 0);
        $vars['title'] = $jatbi->lang("Sửa Đồng phục");

        $data = $app->get("uniform_items", "*", ["id" => $id, "deleted" => 0]);
        if (!$data)
            return $app->render($template . '/error.html', $vars, $jatbi->ajax());
        $vars['data'] = $data;

        if ($app->method() === 'GET') {
            echo $app->render($template . '/hrm/uniforms-items-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $post = array_map([$app, 'xss'], $_POST);

            if (empty($post['item_name'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng điền tên đồng phục")]);
                return;
            }

            $update_data = [
                "item_name" => $post['item_name'],
                "size" => $post['size'],
                "description" => $post['description'],
                "status" => $post['status'],
            ];
            $app->update("uniform_items", $update_data, ["id" => $id]);
            $jatbi->logs('uniform_items', 'edit', $update_data, $id);

            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
        }
    })->setPermissions(['uniforms_items.edit']);

    $app->router("/uniforms-items-delete", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa đồng phục");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("uniform_items", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("uniform_items", ["deleted" => 1], ["id" => $data['id']]);
                    $name[] = $data['item_name'];
                }
                $jatbi->logs('uniform_items', 'uniform_items-deleted', $datas);
                $jatbi->trash('/hrm/uniform_items-restore', "Xóa đồng phục: " . implode(', ', $name), ["database" => 'uniform_items', "data" => $boxid]);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['uniforms_items.delete']);

    $app->router("/uniforms-items-status/{id}", 'POST', function ($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $data = $app->get("uniform_items", "*", ["id" => $vars['id'], "deleted" => 0]);
        if ($data > 1) {
            if ($data > 1) {
                if ($data['status'] === 'A') {
                    $status = "D";
                } elseif ($data['status'] === 'D') {
                    $status = "A";
                }
                $app->update("uniform_items", ["status" => $status], ["id" => $data['id']]);
                $jatbi->logs('uniform_items', 'uniform_items-status', $data);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại"),]);
            }
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['uniforms_items.edit']);

    $app->router('/uniforms-allocations', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $setting) {

        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Lịch sử cấp phát đồng phục");

            $empty_option = [['value' => '', 'text' => $jatbi->lang('')]];

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


            $uniform_items_db = $app->select(
                "uniform_items",
                ["id", "item_name", "size"],
                ["deleted" => 0]
            );

            $formatted_items = array_map(function ($item) {
                return [
                    'value' => $item['id'],
                    'text' => $item['item_name']
                ];
            }, $uniform_items_db);

            $formatted_items_size = array_map(function ($item) {
                return [
                    'value' => $item['id'],
                    'text' => $item['size']
                ];
            }, $uniform_items_db);

            $vars['uniform_items'] = array_merge($empty_option, $formatted_items);

            $vars['uniform_items_size'] = array_merge($empty_option, $formatted_items_size);


            echo $app->render($template . '/hrm/allocations_list.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : $setting['site_page'] ?? 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

            $filter_employee = $_POST['employee'] ?? '';
            $filter_item = $_POST['item'] ?? '';
            $filter_item_size = $_POST['item-size'] ?? '';
            $filter_date = $_POST['date'] ?? '';


            $joins = [
                "[>]personnels(p)" => ["employee_id" => "id"],
                "[>]uniform_items(ui)" => ["item_id" => "id"],
                "[>]accounts(a)" => ["user" => "id"]

            ];
            $where = ["AND" => ["uniform_allocations.deleted" => 0]];

            if (!empty($searchValue)) {
                $where['AND']['OR'] = ["p.name[~]" => $searchValue, "ui.item_name[~]" => $searchValue];
            }
            if (!empty($filter_employee))
                $where['AND']['p.id'] = $filter_employee;
            if (!empty($filter_item))
                $where['AND']['ui.id'] = $filter_item;
            if (!empty($filter_item_size))
                $where['AND']['ui.id'] = $filter_item_size;

            if (!empty($filter_date)) {
                $date_parts = explode(' - ', $filter_date);
                if (count($date_parts) == 2) {
                    $date_from = date('Y-m-d', strtotime(str_replace('/', '-', trim($date_parts[0]))));
                    $date_to = date('Y-m-d', strtotime(str_replace('/', '-', trim($date_parts[1]))));
                }
            } else {
                $date_from = date('Y-m-d');
                $date_to = date('Y-m-d');
            }
            $where['AND']['uniform_allocations.issue_date[<>]'] = [$date_from, $date_to];

            $count = $app->count("uniform_allocations", $joins, "uniform_allocations.id", $where);

            $where["ORDER"] = ["uniform_allocations." . $orderName => strtoupper($orderDir)];
            $where["LIMIT"] = [$start, $length];

            $columns = [
                "uniform_allocations.id",
                "uniform_allocations.quantity",
                "uniform_allocations.issue_date",
                "uniform_allocations.notes",
                "uniform_allocations.user",
                "p.name(employee_name)",
                "p.code(employee_code)",
                "ui.item_name(item_name)",
                "ui.size(item_size)",
                "a.name(user_name)"

            ];
            $datas = [];
            $total = 0;
            $app->select("uniform_allocations", $joins, $columns, $where, function ($data) use (&$datas, &$total, $jatbi, $app) {
                $total += $data['quantity'];
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "employee_name" => ($data['employee_code'] . "-" . $data['employee_name']) ?? "",
                    "item_name" => $data['item_name'] ?? "",
                    "item_size" => $data['item_size'] ?? "",
                    "quantity" => $data['quantity'] ?? 0,
                    "issue_date" => date('d/m/Y', strtotime($data['issue_date'])),
                    "notes" => $data['notes'] ?? "",
                    "user" => $data['user_name'] ?? "",
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['uniforms_allocations.edit'],
                                'action' => ['data-url' => '/hrm/uniforms-allocations-edit/' . $data['id'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'permission' => ['uniforms_allocations.delete'],
                                'action' => ['data-url' => '/hrm/uniforms-allocations-delete?box=' . $data['id'], 'data-action' => 'modal']
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
                "footerData" => [
                    "total" => $total ?? 0,
                ]

            ]);
        }
    })->setPermissions(['uniforms_allocations']);

    $app->router('/uniforms_allocations-add', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $vars['title'] = $jatbi->lang("Cấp phát Đồng phục");

        if ($app->method() === 'GET') {
            $empty_option = [['value' => '', 'text' => $jatbi->lang('')]];

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


            $uniform_items_db = $app->select(
                "uniform_items",
                ["id", "item_name", "size"],
                ["deleted" => 0]
            );

            $formatted_items = array_map(function ($item) {
                return [
                    'value' => $item['id'],
                    'text' => $item['item_name'] . "-" . $item['size']
                ];
            }, $uniform_items_db);


            $vars['uniform_items'] = array_merge($empty_option, $formatted_items);

            echo $app->render($template . '/hrm/uniforms_allocations_post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $post = array_map([$app, 'xss'], $_POST);

            if (empty($post['employee_id']) || empty($post['item_id']) || empty($post['quantity']) || empty($post['issue_date'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng điền đủ thông tin bắt buộc")]);
                return;
            }

            $insert_data = [
                "employee_id" => $post['employee_id'],
                "item_id" => $post['item_id'],
                "quantity" => $post['quantity'],
                "issue_date" => $post['issue_date'],
                "notes" => $post['notes'],
                "user" => $app->getSession("accounts")['id'] ?? 0,
            ];
            $app->insert("uniform_allocations", $insert_data);
            $jatbi->logs('uniform_allocations', 'add', $insert_data);

            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Thêm mới thành công')]);
        }
    })->setPermissions(['uniforms_allocations.add']);

    $app->router('/uniforms-allocations-edit/{id}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $id = (int) ($vars['id'] ?? 0);
        $vars['title'] = $jatbi->lang("Sửa Cấp phát Đồng phục");

        $data = $app->get("uniform_allocations", "*", ["id" => $id, "deleted" => 0]);
        if (!$data)
            return $app->render($template . '/error.html', $vars, $jatbi->ajax());
        $vars['data'] = $data;

        if ($app->method() === 'GET') {
            $empty_option = [['value' => '', 'text' => $jatbi->lang('')]];

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


            $uniform_items_db = $app->select(
                "uniform_items",
                ["id", "item_name", "size"],
                ["deleted" => 0]
            );

            $formatted_items = array_map(function ($item) {
                return [
                    'value' => $item['id'],
                    'text' => $item['item_name'] . "-" . $item['size']
                ];
            }, $uniform_items_db);


            $vars['uniform_items'] = array_merge($empty_option, $formatted_items);

            echo $app->render($template . '/hrm/uniforms_allocations_post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $post = array_map([$app, 'xss'], $_POST);

            if (empty($post['employee_id']) || empty($post['item_id']) || empty($post['quantity']) || empty($post['issue_date'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng điền đủ thông tin bắt buộc")]);
                return;
            }

            $update_data = [
                "employee_id" => $post['employee_id'],
                "item_id" => $post['item_id'],
                "quantity" => $post['quantity'],
                "issue_date" => $post['issue_date'],
                "notes" => $post['notes'],
                "user" => $app->getSession("accounts")['id'] ?? 0,
            ];
            $app->update("uniform_allocations", $update_data, ["id" => $id]);
            $jatbi->logs('uniform_allocations', 'edit', $update_data, $id);

            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
        }
    })->setPermissions(['uniforms_allocations.edit']);

    $app->router("/uniforms-allocations-delete", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa cấp phát đồng phục");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("uniform_allocations", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("uniform_allocations", ["deleted" => 1], ["id" => $data['id']]);
                }
                $jatbi->logs('uniform_allocations', 'uniform_allocations-deleted', $datas);
                $jatbi->trash('/hrm/uniform_allocations-restore', "", ["database" => 'uniform_allocations', "data" => $boxid]);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['uniforms_allocations.delete']);

    $app->router('/reports', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $stores) {

        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Báo cáo biến động nhân sự");
            $store_ids = array_column($stores, 'value');


            $selected_month_year = $_GET['month_year'] ?? date('Y-m');

            $date_from = date('Y-m-01', strtotime($selected_month_year));
            $date_to = date('Y-m-t', strtotime($selected_month_year));

            $vars['current_month_text'] = date('m/Y', strtotime($selected_month_year));

            $vars['total_active'] = $app->count("personnels", [
                "deleted" => 0,
                "status" => 'A',
                "stores" => $store_ids,
                "date[<=]" => $date_to . ' 23:59:59',
            ]);

            $vars['total_used_furlough'] = $app->count(
                "furlough",
                [
                    "[>]personnels" => ["personnels" => "id"]
                ],
                "furlough.id",
                [
                    "AND" => [
                        "furlough.deleted" => 0,
                        "furlough.status" => 'A',
                        "furlough.date[<=]" => $date_to . ' 23:59:59',
                        "personnels.stores" => $store_ids
                    ]
                ]
            );
            $vars['new_hires_count'] = $app->count("personnels", [
                "deleted" => 0,
                "date[<>]" => [$date_from . ' 00:00:00', $date_to . ' 23:59:59'],
                "stores" => $store_ids,
            ]);

            $vars['total_salary'] = $app->sum(
                "personnels_contract",
                [
                    "[>]personnels" => ["personnels" => "id"]
                ],
                "personnels_contract.salary",
                [
                    "AND" => [
                        "personnels_contract.deleted" => 0,
                        "personnels_contract.date_contract[<=]" => $date_to,
                        "personnels_contract.date_end[>=]" => $date_from,
                        "personnels.stores" => $store_ids
                    ]
                ]
            );

            // Tính Tổng Thưởng
            $vars['total_reward_cost'] = $app->sum(
                "reward_discipline(rd)",
                [
                    "[>]personnels(p)" => ["rd.personnels" => "id"]
                ],
                "rd.price",
                [
                    "AND" => [
                        "rd.type" => 1,
                        "rd.date[<>]" => [$date_from . ' 00:00:00', $date_to . ' 23:59:59'],
                        "p.stores" => $store_ids
                    ]
                ]
            );

            $vars['total_reward_discipline_cost'] = $app->sum(
                "reward_discipline(rd)",
                [
                    "[>]personnels(p)" => ["rd.personnels" => "id"]
                ],
                "rd.price",
                [
                    "AND" => [
                        "rd.type" => 2,
                        "rd.date[<>]" => [$date_from . ' 00:00:00', $date_to . ' 23:59:59'],
                        "p.stores" => $store_ids
                    ]
                ]
            );

            $resigned_personnel_ids = $app->query(
                "SELECT personnels FROM personnels_contract GROUP BY personnels HAVING MAX(DATE_ADD(date_contract, INTERVAL duration MONTH)) BETWEEN :date_from AND :date_to",
                [':date_from' => $date_from, ':date_to' => $date_to]
            )->fetchAll(PDO::FETCH_COLUMN);

            $vars['resignations_count'] = 0;
            if (!empty($resigned_personnel_ids)) {
                $vars['resignations_count'] = $app->count("personnels", [
                    "AND" => ["deleted" => 0, "id" => $resigned_personnel_ids]
                ]);
            }

            $vars['month_year_options'] = [];
            for ($i = 0; $i < 12; $i++) {
                $date = strtotime("-$i months");
                $value = date('Y-m', $date);
                $text = "Tháng " . date('m/Y', $date);
                $vars['month_year_options'][] = ['value' => $value, 'text' => $text];
            }

            $vars['selected_month_year'] = $selected_month_year ?? date('Y-m');

            echo $app->render($template . '/hrm/reports.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Phương thức không hợp lệ")]);
        }
    })->setPermissions(['reports']);

    $app->router('/camera', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $vars['title'] = $jatbi->lang("Camera");

        if ($app->method() === 'GET') {
            echo $app->render($template . '/hrm/camera.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $statusValue = isset($_POST['status']) ? $_POST['status'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

            $where = [
                "AND" => [
                    "hrm_decided.deleted" => 0,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)]
            ];

            if ($searchValue != '') {
                $where['AND']['OR'] = [
                    'hrm_decided.name[~]' => $searchValue,

                ];
            }

            // if ($statusValue != '') {
            //     $where['AND']['hrm_decided.status'] = $statusValue;
            // }

            $countWhere = [
                "AND" => array_merge(
                    ["hrm_decided.deleted" => 0],
                    $searchValue != '' ? [
                        "OR" => [
                            'hrm_decided.name[~]' => $searchValue,

                        ]
                    ] : [],
                    // $statusValue != '' ? ["hrm_decided.status" => $statusValue] : []
                )
            ];
            $count = $app->count("hrm_decided", $countWhere);

            $datas = [];
            $app->select("hrm_decided", [
                'hrm_decided.id',

                'hrm_decided.name',
                'hrm_decided.decided',
                'hrm_decided.password'

            ], $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => ($app->component("box", ["data" => $data['id'] ?? '']) ?? '<input type="checkbox">'),
                    "name" => ($data['name'] ?? ''),
                    "decided" => ($data["decided"] ?? ''),
                    "password" => ($data["password"] ?? ''),
                    "action" => ($app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['camera.edit'],
                                'action' => ['data-url' => '/hrm/camera-edit/' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'permission' => ['camera.deleted'],
                                'action' => ['data-url' => '/hrm/camera-deleted?box=' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Cấu hình"),
                                'permission' => ['camera.edit'],
                                'action' => [
                                    'data-url' => '/hrm/camera-option/' . ($data['decided'] ?? '') . '/' . ($data['password'] ?? ''),
                                    'data-action' => 'modal'
                                ]
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
    })->setPermissions(['camera']);


    $app->router('/camera-add', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $vars['title'] = $jatbi->lang("Thêm camera");

        if ($app->method() === 'GET') {
            echo $app->render($template . '/hrm/camera-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json',]);
            if (empty($_POST['name']) || empty($_POST['decided']) || empty($_POST['password'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng không để trống")]);
                return;
            }

            $insert = [
                "name" => $app->xss($_POST['name']),
                "decided" => $app->xss($_POST['decided']),
                "password" => $app->xss($_POST['password']),
            ];
            $app->insert("hrm_decided", $insert);

            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        }
    })->setPermissions(['camera.add']);

    $app->router("/camera-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $vars['title'] = $jatbi->lang("Sửa camera");

        if ($app->method() === 'GET') {
            $vars['data'] = $app->select("hrm_decided", [
                "id",

                "name",
                "decided",
                "password"
            ], [
                "AND" => [
                    "id" => $vars['id'],
                    "deleted" => 0
                ],
                "LIMIT" => 1
            ]);

            if (!empty($vars['data'])) {
                $vars['data'] = $vars['data'][0];
                echo $app->render($template . '/hrm/camera-post.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);

            $data = $app->select("hrm_decided", [
                "id",
                "name",
                "decided",
                "password"
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
                if ($app->xss($_POST['decided']) == '') {
                    $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")];
                }
                if ($app->xss($_POST['password']) == '') {
                    $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")];
                }


                if (empty($error)) {
                    $insert = [

                        "name" => $app->xss($_POST['name']),
                        "decided" => $app->xss($_POST['decided']),
                        "password" => $app->xss($_POST['password']),
                    ];
                    $app->update("hrm_decided", $insert, ["id" => $data['id']]);

                    echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công"), "url" => $_SERVER['HTTP_REFERER']]);
                } else {
                    echo json_encode($error);
                }
            } else {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
            }
        }
    })->setPermissions(['camera.edit']);

    $app->router("/camera-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {
        $vars['title'] = $jatbi->lang("Xóa camera");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("hrm_decided", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("hrm_decided", ["deleted" => 1], ["id" => $data['id']]);
                    $name[] = $data['name'];
                }

                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['camera.deleted']);


    $app->router('/camera-option/{deviceKey}/{secret}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $setting) {
        // Lấy deviceKey và secret từ URL một cách an toàn
        $deviceKey = $vars['deviceKey'] ?? '';
        $secret = $vars['secret'] ?? '';

        // --- XỬ LÝ KHI NGƯỜI DÙNG TẢI TRANG (GET) ---
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Cấu hình Camera");

            // 1. Định nghĩa các giá trị mặc định cho một cấu hình mới
            $default_options = [
                'recSucTtsMode' => '1', // Mặc định là 'Không phát âm thanh'
                'recSucTtsCustom' => '',
                'recThreshold1v1' => '0.85', // Giá trị gợi ý
                'recInterva' => '5',        // Giá trị gợi ý
                'recDistance' => '0',       // Mặc định là 'Không giới hạn'
                'recStrangerEnable' => '0', // Mặc định là 'Không nhận diện người lạ'
                'sevUploadRecRecordUrl' => '',
            ];

            // 2. Lấy dữ liệu đã lưu từ database (nếu có)
            $saved_options = $app->get("camera_option", "*", [
                "deviceKey" => $deviceKey,
                'secret' => $secret
            ]);

            // 3. Hợp nhất mảng mặc định với mảng đã lưu.
            // Các giá trị đã lưu sẽ ghi đè lên giá trị mặc định.
            // Nếu không có gì được lưu, mảng mặc định sẽ được sử dụng.
            $vars['camera_op'] = array_merge($default_options, $saved_options ? $saved_options : []);

            // 4. Render view, bạn không cần thay đổi gì ở file .html
            echo $app->render($template . '/hrm/camera-option.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            // 1. Kiểm tra dữ liệu đầu vào
            if (empty($_POST['recThreshold1v1']) || empty($_POST['sevUploadRecRecordUrl'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Độ chính xác và webhook không được để trống')]);
                return;
            }

            // 2. Chuẩn bị dữ liệu để gửi đi và lưu trữ
            $dataToSubmit = [
                'deviceKey' => $deviceKey,
                'secret' => $secret,
                'recThreshold1v1' => $_POST['recThreshold1v1'],
                'recInterva' => $_POST['recInterva'],
                'recDistance' => $_POST['recDistance'],
                'recStrangerEnable' => $_POST['recStrangerEnable'],
                'sevUploadRecRecordUrl' => $_POST['sevUploadRecRecordUrl'],
                'recSucTtsMode' => $_POST['recSucTtsMode'],
                'recSucTtsCustom' => $_POST['recSucTtsCustom'],
            ];

            // 3. Gọi API của camera để cập nhật cấu hình
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => 'http://camera.ellm.io:8190/api/device/setConfig',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => http_build_query($dataToSubmit),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer your_token',
                    'Content-Type: application/x-www-form-urlencoded'
                ],
            ]);
            $response = curl_exec($curl);
            $apiResponse = json_decode($response, true);
            curl_close($curl);

            // 4. Xử lý kết quả từ API
            if (isset($apiResponse['success']) && $apiResponse['success'] === true) {
                // API thành công, tiến hành cập nhật vào database của bạn
                $existing_op = $app->get("camera_option", ["id"], ["deviceKey" => $deviceKey, 'secret' => $secret]);

                if ($existing_op) {
                    // Nếu đã có cấu hình -> Cập nhật
                    $app->update('camera_option', $dataToSubmit, ['id' => $existing_op['id']]);
                } else {
                    // Nếu chưa có -> Thêm mới
                    $app->insert('camera_option', $dataToSubmit);
                }

                echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công!', "url" => $_SERVER['HTTP_REFERER']]);
            } else {
                // API thất bại, báo lỗi
                $errorMessage = $apiResponse['msg'] ?? "Lỗi không xác định từ API Camera.";
                echo json_encode(['status' => 'error', 'content' => $errorMessage]);
            }
        }
    })->setPermissions(['camera.edit']);


    $app->router('/faceid', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $setting) {
        // --- XỬ LÝ KHI NGƯỜI DÙNG TẢI TRANG (GET) ---
        if ($app->method() === 'GET') {

            $vars['title'] = $jatbi->lang("Nhật ký nhận diện");

            $date_from = date('Y-m-d 00:00:00', strtotime('first day of this month'));
            $date_to = date('Y-m-d 23:59:59');

            $vars['date_from'] = $date_from;
            $vars['date_to'] = $date_to;
            echo $app->render($template . '/hrm/faceid.html', $vars);
        }
        // --- XỬ LÝ KHI DATATABLES GỌI AJAX ĐỂ LẤY DỮ LIỆU (POST) ---
        elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            // 1. Lấy các tham số từ DataTables và bộ lọc
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? $app->xss($_POST['search']['value']) : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $filter_date = isset($_POST['date']) ? $_POST['date'] : '';

            // Xử lý khoảng ngày
            $date_from = date('2021-01-01 00:00:00', strtotime('first day of this month'));
            $date_to = date('Y-m-d 23:59:59');


            if (!empty($filter_date)) {
                $date_parts = explode(' - ', $filter_date);
                if (count($date_parts) == 2) {
                    $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date_parts[0]))));
                    $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date_parts[1]))));
                }
            }


            // 2. Xây dựng điều kiện truy vấn (WHERE)
            $where = [
                "AND" => [
                    "webhook.deleted" => 0,
                    "webhook.date_face[<>]" => [$date_from, $date_to],
                ],
                "ORDER" => ["webhook." . $orderName => strtoupper($orderDir)]
            ];

            if ($searchValue != '') {
                $where['AND']['webhook.name[~]'] = $searchValue;
            }

            // 3. Đếm tổng số bản ghi khớp với điều kiện
            $count = $app->count("webhook", $where);

            // Thêm LIMIT để phân trang
            $where["LIMIT"] = [$start, $length];

            // 4. Lấy dữ liệu và tối ưu hóa truy vấn
            // Lấy danh sách thiết bị trước để tránh lỗi N+1 query
            $decided_devices = $app->select("hrm_decided", ["decided", "name"]);
            $decided_map = [];
            foreach ($decided_devices as $device) {
                $decided_map[$device['decided']] = $device['name'];
            }

            $datas = [];
            $app->select("webhook", "*", $where, function ($data) use (&$datas, $jatbi, $setting, $decided_map) {
                $person_type_html = $data['personSn'] != 0
                    ? '<span class="">' . $jatbi->lang('Nhân viên') . '</span>'
                    : '<span class="">' . $jatbi->lang('Người lạ') . '</span>';

                $imagePath = !empty($data['photo']) ? '' . $data['photo'] : '';

                $datas[] = [
                    "image" => '<img src="' . $setting['url'] . $imagePath . '" class="rounded-3 shadow-sm" style="width: 60px; height: 60px; object-fit: cover;">',
                    "person_name" => $data['name'] ?? '',
                    "device_name" => $decided_map[$data['devicekey']] ?? $jatbi->lang('Không xác định'),
                    "date" => date("d/m/Y H:i:s", strtotime($data['date_face'])),
                    "type" => $person_type_html,
                    "action" => '<button data-action="modal" data-url="/hrm/faceid-views/' . $data['id'] . '" class="btn btn-eclo-light btn-sm border-0 py-1 px-2 rounded-3" aria-label="' . $jatbi->lang('Xem') . '"><i class="ti ti-eye"></i></button>',
                ];
            });

            // 5. Trả về kết quả dưới dạng JSON
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas ?? []
            ]);
        }
    })->setPermissions(['faceid']);

    $app->router('/faceid-views/{id}', 'GET', function ($vars) use ($app, $jatbi, $template, $setting) {

        $vars['title'] = $jatbi->lang("Ảnh nhận diện");
        $vars['data'] = $app->get("webhook", "*", [
            "AND" => [
                "id" => $vars['id'],
                "deleted" => 0
            ]
        ]);
        echo $app->render($template . '/hrm/faceid-post.html', $vars, $jatbi->ajax());
    })->setPermissions(['faceid']);

    $app->router('/annual_leave', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $setting, $stores) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Nghỉ phép năm");
            $currentYear = date('Y');
            $years = [];
            for ($i = 0; $i < 10; $i++) {
                $year = $currentYear - $i;
                $years[] = ['value' => $year, 'text' => $year];
            }

            $vars['years'] = $years;
            echo $app->render($template . '/hrm/annual_leave.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? $app->xss($_POST['search']['value']) : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'annual_leave.id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $year = (int)($app->xss($_POST['year'] ?? date('Y')));

            $furloughCategory = $app->get("furlough_categorys", ["amount"], ["id" => 11, "deleted" => 0]);
            $totalEntitledLeave = ($furloughCategory['amount'] ?? 1);


            $join = [
                "[>]personnels" => ["personnels" => "id"]
            ];

            $where = [
                "AND" => [
                    "annual_leave.deleted" => 0,
                    "personnels.stores" => array_column($stores, 'value'),
                ],
            ];

            if (!empty($searchValue)) {
                $where['AND']['OR'] = [
                    "personnels.name[~]" => $searchValue,
                    "personnels.code[~]" => $searchValue,
                ];
            }

            if (!empty($year)) {
                $where['AND']['annual_leave.year'] = (int) $year;
            }

            $count = $app->count("annual_leave", $join, "annual_leave.id", $where);

            $where["GROUP"] = "annual_leave.personnels";
            $where["LIMIT"] = [$start, $length];
            $where["ORDER"] = [$orderName => strtoupper($orderDir)];

            $datas = [];
            $app->select(
                "annual_leave",
                $join,
                [
                    'annual_leave.id',
                    'annual_leave.type',
                    'annual_leave.personnels(personnel_id)',
                    'annual_leave.amount',
                    'annual_leave.year',
                    'personnels.code(personnel_code)',
                    'personnels.name(personnel_name)',
                ],
                $where,
                function ($data) use (&$datas, $jatbi, $app, $year, $totalEntitledLeave) {

                    $sumOfAmounts = $app->sum("annual_leave", "amount", [
                        "personnels" => $data['personnel_id'],
                        "year" => $year,
                        "deleted" => 0
                    ]);
                    $takenLeave = abs($sumOfAmounts ?? 0);

                    // TÍNH SỐ PHÉP CÒN LẠI
                    $remainingLeave = $totalEntitledLeave - $takenLeave;

                    $datas[] = [
                        "checkbox" => $app->component('box', ["data" => $data['id']]),
                        "type" => $data['type'] == 1 ? $jatbi->lang('Phép năm tồn') : $jatbi->lang('Hưởng nghỉ việc'),
                        "personnel_code" => $data['personnel_code'],
                        "personnel_name" => $data['personnel_name'],
                        "amount" => $remainingLeave,
                        "action" => $app->component("action", [
                            "button" => [
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Sửa"),
                                    'permission' => ['annual_leave.edit'],
                                    'action' => ['data-url' => '/hrm/annual_leave-edit/' . $data['id'], 'data-action' => 'modal']
                                ],
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Xóa"),
                                    'permission' => ['annual_leave.deleted'],
                                    'action' => ['data-url' => '/hrm/annual_leave-delete/' . $data['id'], 'data-action' => 'modal']
                                ],
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Xem chi tiết"),
                                    'permission' => ['annual_leave'],
                                    'action' => ['data-url' => '/hrm/annual_leave-views/' . $data['personnel_id'] . '?year=' . $year, 'data-action' => 'modal']
                                ]
                            ]
                        ]),
                    ];
                }
            );

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas ?? []
            ]);
        }
    })->setPermissions(['annual_leave']);

    $app->router('/annual_leave-views/{id}', 'GET', function ($vars) use ($app, $jatbi, $template) {
        $personnel_id = $vars['id'];
        $year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
        $personnel_info = $app->get("personnels", ["name", "code"], ["id" => $personnel_id]);

        $vars['title'] = $jatbi->lang("Chi tiết nghỉ phép năm ") . $year . " - " . ($personnel_info['name'] ?? 'N/A');

        $vars['records'] = $app->select("annual_leave", "*", [
            "personnels" => $personnel_id,
            "year" => $year,
            "deleted" => 0,
        ]);

        echo $app->render($template . '/hrm/annual_leave-views.html', $vars, $jatbi->ajax());
    })->setPermissions(['annual_leave']);

    $app->router('/annual_leave-add', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template, $stores) {
        $vars['title'] = $jatbi->lang("Thêm mới phép năm");

        if ($app->method() === 'GET') {

            $vars['personnels'] = $app->select(
                "personnels",
                ["id(value)", "name(text)"],
                [
                    "deleted" => 0,
                    "stores" => array_column($stores, 'value'),
                    "ORDER" => ["name" => "ASC"]
                ]
            );

            echo $app->render($template . '/hrm/annual_leave-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            $post = array_map([$app, 'xss'], $_POST);
            if (empty($post['personnels']) || empty($post['amount'])) {
                echo json_encode(value: ['status' => 'error', 'content' => $jatbi->lang('Vui lòng điền đủ thông tin bắt buộc')]);
                return;
            }

            $insert = [
                "type"       => $post['type'] ?? 1,
                "personnels" => $post['personnels'],
                "amount"     => (float) str_replace(",", "", $post['amount']),
            ];

            $app->insert("annual_leave", $insert);
            $jatbi->logs('annual_leave', 'add', $insert);

            echo json_encode(value: ['status' => 'success', 'content' => $jatbi->lang('Thêm mới thành công')]);
        }
    })->setPermissions(['annual_leave.add']);

    $app->router('/annual_leave-edit/{id}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template, $stores) {
        $vars['title'] = $jatbi->lang("Chỉnh sửa phép năm");
        $id = (int) $vars['id'];

        $data = $app->get("annual_leave", "*", ["id" => $id]);

        if (!$data) {
            echo $jatbi->lang("Không tìm thấy dữ liệu phù hợp!");
            return;
        }

        if ($app->method() === 'GET') {
            $vars['data'] = $data;
            $vars['personnels'] = $app->select(
                "personnels",
                ["id(value)", "name(text)"],
                [
                    "deleted" => 0,
                    "stores" => array_column($stores, 'value'),
                    "ORDER" => ["name" => "ASC"]
                ]
            );

            echo $app->render($template . '/hrm/annual_leave-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            $post = array_map([$app, 'xss'], $_POST);
            if (empty($post['personnels']) || empty($post['amount'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Vui lòng điền đủ thông tin bắt buộc')]);
                return;
            }

            $update = [
                "type"       => $post['type'] ?? 1,
                "personnels" => $post['personnels'],
                "amount"     => (float) str_replace(",", "", $post['amount']),
            ];

            $app->update("annual_leave", $update, ["id" => $id]);
            $jatbi->logs('annual_leave', 'edit', $update);

            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
        }
    })->setPermissions(['annual_leave.edit']);

    $app->router("/annual_leave-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa phép năm");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("annual_leave", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("annual_leave", ["deleted" => 1], ["id" => $data['id']]);
                }
                $jatbi->logs('annual_leave', 'annual_leave-deleted', $datas);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['annual_leave.deleted']);






    $app->router('/decided', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $vars['title'] = $jatbi->lang("Quyết định thôi việc");

        if ($app->method() === 'GET') {

            echo $app->render($template . '/hrm/decided.html', $vars);
        } elseif ($app->method() === 'POST') {

            $app->header(['Content-Type' => 'application/json']);


            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';


            $personnelValue = isset($_POST['personnels']) ? $_POST['personnels'] : '';
            $officeValue = isset($_POST['offices']) ? $_POST['offices'] : '';
            $statusValue = isset($_POST['status']) ? $_POST['status'] : '';


            $baseWhere = [
                "contract_annex.type" => 2,
                "contract_annex.deleted" => 0,
            ];

            $filterWhere = [];


            if ($searchValue != '') {
                $filterWhere['OR'] = [
                    'personnels.name[~]' => $searchValue,
                    'personnels.code[~]' => $searchValue,
                    'contract_annex.content[~]' => $searchValue,
                ];
            }


            if ($personnelValue != '') {
                $filterWhere['contract_annex.contract'] = $personnelValue;
            }
            if ($officeValue != '') {
                $filterWhere['personnels.offices_id'] = $officeValue;
            }
            if ($statusValue != '') {
                $filterWhere['contract_annex.status'] = $statusValue;
            }

            $where = [
                "AND" => array_merge($baseWhere, $filterWhere),
                "LIMIT" => [$start, $length],
                "ORDER" => ["contract_annex." . $orderName => strtoupper($orderDir)]
            ];


            $countWhere = ["AND" => array_merge($baseWhere, $filterWhere)];


            $join = [
                "[>]personnels" => ["contract" => "id"]
            ];


            $count = $app->count("contract_annex", $join, ["contract_annex.id"], $countWhere);

            $datas = [];

            $app->select("contract_annex", $join, [
                'contract_annex.id',
                'contract_annex.date',
                'contract_annex.content',
                'contract_annex.notes',
                'personnels.name',
                'personnels.code'
            ], $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => ($app->component("box", ["data" => $data['id'] ?? '']) ?? '<input type="checkbox">'),
                    "personnel" => ($data['code'] ?? '') . ' - ' . ($data['name'] ?? ''),
                    "date" => $jatbi->date($data['date']),
                    "content" => ($data['content'] ?? ''),
                    "notes" => ($data['notes'] ?? ''),
                    "action" => ($app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['decided.edit'],
                                'action' => ['data-url' => '/hrm/decided-edit/' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'permission' => ['decided.deleted'],
                                'action' => ['data-url' => '/hrm/decided-deleted?box=' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xem"),
                                'permission' => ['decided'],
                               'action' => ['data-url' => '/hrm/decided-views/' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                        ]
                    ])),
                ];
            });


            echo json_encode(
                [
                    "draw" => $draw,
                    "recordsTotal" => $app->count("contract_annex", ["AND" => $baseWhere]),
                    "recordsFiltered" => $count,
                    "data" => $datas ?? []
                ]
            );
        }
    })->setPermissions(['decided']);




    $app->router('/decided-add', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $vars['title'] = $jatbi->lang("Thêm quyết định thôi việc");

        if ($app->method() === 'GET') {

            // [ĐÃ SỬA] Logic tạo mã Quyết định theo "Ngọc Hiền"
            // 1. Lấy năm hiện tại
            $current_year = date('Y');

            // 2. Tìm 'code' (số thứ tự) lớn nhất đã được dùng trong năm nay
            // (Giả định 'code' chỉ lưu số, ví dụ: 50, 51...)
            $max_code_number = $app->max("contract_annex", "code", [
                "type" => 2, // 2 = Quyết định
                "deleted" => 0,
                "date[~]" => $current_year . '-%' // Lọc theo năm của 'date' (ngày ban hành)
            ]);

            // 3. Số mới = số lớn nhất + 1
            $new_number = (int)$max_code_number + 1;
            
            // 4. Gán SỐ (ví dụ: 51) này vào form.
            // File template in (decided-views.html) sẽ tự động thêm /NĂM/QĐ-NTNH
            $vars['data']['code'] = $new_number;
            $vars['personnels'] = array_map(
                fn($item) => ['value' => $item['id'], 'text' => $item['code'] . ' - ' . $item['name']],
                $app->select("personnels", ["id", "code", "name"], ["deleted" => 0, "status" => "A"])
            );

            echo $app->render($template . '/hrm/decided-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            if (empty($_POST['personnels']) || empty($_POST['content'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng không để trống các trường bắt buộc")]);
                return;
            }

            $insert = [
                "code"          => $app->xss($_POST['code']),
                "contract"      => $app->xss($_POST['personnels']),
                "content"       => $app->xss($_POST['content']),
                "date"          => $app->xss($_POST['date']),
                "date_poster"   => $app->xss($_POST['date_poster']),
                "type"          => 2,
                "off"           => $app->xss($_POST['off']),
                "notes"         => $app->xss($_POST['notes']),
            ];


            if ($insert['content'] == 'Đơn phương chấm dứt hợp đồng') {
                $type = 1;
            } else {
                $type = 2;
            }

            $app->insert("contract_annex", $insert);
            $app->update("personnels_contract", ["status_type" => 1], ["personnels" => $insert['contract']]);


            $app->update("personnels", [
                "status_type" => 1,
                "date_off"    => $insert['date'],
                "type"        => $type,
                "off"         => $insert['off']
            ], ["id" => $insert['contract']]);

            $jatbi->logs('contract_annex', 'add', [$insert]);

            echo json_encode([
                'status' => 'success',
                'content' => $jatbi->lang("Cập nhật thành công"),

            ]);
        }
    })->setPermissions(['decided.add']);


    $app->router('/decided-edit/{id}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $vars['title'] = $jatbi->lang("Sửa quyết định thôi việc");
        $id = $vars['id'];

        $data = $app->get("contract_annex", "*", ["id" => $id]);

        if (!$data) {
            header("HTTP/1.0 404 Not Found");
            die();
        }

        if ($app->method() === 'GET') {
            $vars['data'] = $data;
            $mapData = function ($item) {
                return ['value' => $item['id'], 'text' => $item['code'] . ' - ' . $item['name']];
            };
            $vars['personnels'] = array_map($mapData, $app->select("personnels", ["id", "code", "name"], ["deleted" => 0, "status" => "A"]));
            echo $app->render($template . '/hrm/decided-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            if (empty($_POST['personnels']) || empty($_POST['content'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng không để trống các trường bắt buộc")]);
                return;
            }

            $update = [
                "code"          => $app->xss($_POST['code']),
                "contract"      => $app->xss($_POST['personnels']),
                "content"       => $app->xss($_POST['content']),
                "date"          => $app->xss($_POST['date']),
                "date_poster"   => $app->xss($_POST['date_poster']),
                "type"          => 2,
                "off"           => $app->xss($_POST['off']),
                "notes"         => $app->xss($_POST['notes']),
            ];


            if ($update['content'] == 'Đơn phương chấm dứt hợp đồng') {
                $type = 1;
            } else {
                $type = 2;
            }

            $app->update("contract_annex", $update, ["id" => $id]);
            $app->update("personnels_contract", ["status_type" => 1], ["personnels" => $update['contract']]);


            $app->update("personnels", [
                "status_type" => 1,
                "date_off"    => $update['date'],
                "type"        => $type,
                "off"         => $update['off']
            ], ["id" => $update['contract']]);

            $jatbi->logs('contract_annex', 'edit', $update, ["id" => $id]);

            echo json_encode([
                'status' => 'success',
                'content' => $jatbi->lang("Cập nhật thành công"),
                'url' => $_SERVER['HTTP_REFERER']
            ]);
        }
    })->setPermissions(['decided.edit']);



    $app->router("/decided-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {
        $vars['title'] = $jatbi->lang("Xóa quyết định thôi việc");

        if ($app->method() === 'GET') {

            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);


            $box_ids = explode(',', $app->xss($_GET['box']));


            $datas = $app->select("contract_annex", "*", ["id" => $box_ids, "deleted" => 0]);

            if (count($datas) > 0) {
                // Lấy danh sách ID của các nhân viên bị ảnh hưởng
                $personnel_ids = array_column($datas, 'contract');



                // 1. Đánh dấu đã xóa các quyết định trong `contract_annex`
                $app->update("contract_annex", ["deleted" => 1], ["id" => $box_ids]);

                // 2. Phục hồi trạng thái hợp đồng về "đang hoạt động"
                $app->update("personnels_contract", ["status_type" => 0], ["personnels" => $personnel_ids]);

                // 3. Phục hồi trạng thái nhân viên về "đang làm việc" và xóa ngày nghỉ
                $app->update("personnels", ["status_type" => 0, "date_off" => NULL], ["id" => $personnel_ids]);


                $jatbi->logs('contract_annex', 'delete', $datas);


                echo json_encode([
                    'status' => 'success',
                    "content" => $jatbi->lang("Cập nhật thành công"),

                ]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xảy ra hoặc dữ liệu không tồn tại")]);
            }
        }
    })->setPermissions(['decided.deleted']);


    $app->router('/positions', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $vars['title'] = $jatbi->lang("Chức vụ");

        if ($app->method() === 'GET') {
            echo $app->render($template . '/hrm/positions.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            // Lấy các tham số từ DataTables
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $statusValue = isset($_POST['status']) ? $_POST['status'] : '';
            $orderName = isset($_POST['order'][0]['column']) ? $_POST['columns'][$_POST['order'][0]['column']]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

            // Xây dựng điều kiện WHERE
            $where = [
                "AND" => [
                    "hrm_positions.deleted" => 0,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)]
            ];

            // Thêm điều kiện tìm kiếm (tìm theo Tên và Mã chức vụ)
            if ($searchValue != '') {
                $where['AND']['OR'] = [
                    'hrm_positions.name[~]' => $searchValue,
                    'hrm_positions.code[~]' => $searchValue,
                ];
            }

            // Thêm điều kiện lọc theo Trạng thái
            if ($statusValue != '') {
                $where['AND']['hrm_positions.status'] = $statusValue;
            }

            // Xây dựng điều kiện đếm (phải giống hệt $where nhưng không có LIMIT, ORDER)
            $countWhere = [
                "AND" => array_merge(
                    ["hrm_positions.deleted" => 0],
                    $searchValue != '' ? [
                        "OR" => [
                            'hrm_positions.name[~]' => $searchValue,
                            'hrm_positions.code[~]' => $searchValue,
                        ]
                    ] : [],
                    $statusValue != '' ? ["hrm_positions.status" => $statusValue] : []
                )
            ];
            $count = $app->count("hrm_positions", $countWhere);

            // Lấy dữ liệu
            $datas = [];
            $app->select("hrm_positions", [
                'hrm_positions.id',
                'hrm_positions.code',
                'hrm_positions.name',
                'hrm_positions.status',
                'hrm_positions.notes'
            ], $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => ($app->component("box", ["data" => $data['id'] ?? '']) ?? '<input type="checkbox">'),
                    "code" => ($data['code'] ?? ''),
                    "name" => ($data['name'] ?? ''),
                    "notes" => ($data['notes'] ?? ''),
                    "status" => ($app->component("status", [
                        "url" => "/hrm/positions-status/" . ($data['id'] ?? ''),
                        "data" => $data['status'] ?? '',
                        "permission" => ['positions.edit'],
                    ])),
                    "action" => ($app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['positions.edit'],
                                'action' => ['data-url' => '/hrm/positions-edit/' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'permission' => ['positions.deleted'],
                                'action' => ['data-url' => '/hrm/positions-deleted?box=' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                        ]
                    ]))
                ];
            });

            // Trả về dữ liệu JSON cho DataTables
            echo json_encode(
                [
                    "draw" => $draw,
                    "recordsTotal" => $count,
                    "recordsFiltered" => $count,
                    "data" => $datas ?? []
                ],
            );
        }
    })->setPermissions(['positions']);




    // THÊM MỚI CHỨC VỤ
    $app->router('/positions-add', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $vars['title'] = $jatbi->lang("Thêm Chức vụ");

        if ($app->method() === 'GET') {
            // Giả sử bạn có file post.html trong thư mục /hrm/
            echo $app->render($template . '/hrm/positions-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json',]);
            if (empty($_POST['name'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng không để trống tên chức vụ")]);
                return;
            }

            $insert = [
                "code" => $app->xss($_POST['code']),
                "name" => $app->xss($_POST['name']),
                "notes" => $app->xss($_POST['notes']),
                "status" => $app->xss($_POST['status']),
            ];
            $app->insert("hrm_positions", $insert);
            $jatbi->logs('positions', 'add', $insert);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Thêm mới thành công")]);
        }
    })->setPermissions(['positions.add']);


    // SỬA CHỨC VỤ
    $app->router("/positions-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $vars['title'] = $jatbi->lang("Sửa Chức vụ");

        if ($app->method() === 'GET') {
            $vars['data'] = $app->get("hrm_positions", "*", [
                "AND" => [
                    "id" => $vars['id'],
                    "deleted" => 0
                ]
            ]);

            if (!empty($vars['data'])) {
                echo $app->render($template . '/hrm/positions-post.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json',]);

            $data = $app->get("hrm_positions", "*", [
                "AND" => [
                    "id" => $vars['id'],
                    "deleted" => 0
                ]
            ]);

            if (!empty($data)) {
                if (empty($_POST['name'])) {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
                    return;
                }

                $update = [
                    "code" => $app->xss($_POST['code']),
                    "name" => $app->xss($_POST['name']),
                    "notes" => $app->xss($_POST['notes']),
                    "status" => $app->xss($_POST['status']),
                ];
                $app->update("hrm_positions", $update, ["id" => $data['id']]);
                $jatbi->logs('positions', 'edit', $update);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
            }
        }
    })->setPermissions(['positions.edit']);


    // XÓA CHỨC VỤ
    $app->router("/positions-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {
        $vars['title'] = $jatbi->lang("Xóa Chức vụ");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json',]);

            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("hrm_positions", "*", ["id" => $boxid, "deleted" => 0]);

            if (count($datas) > 0) {
                $name = [];
                foreach ($datas as $data) {
                    $app->update("hrm_positions", ["deleted" => 1], ["id" => $data['id']]);
                    $name[] = $data['name'];
                }
                $jatbi->logs('positions', 'deleted', $datas);
                $jatbi->trash('/hrm/positions-restore', "Xóa chức vụ: " . implode(', ', $name), ["database" => 'hrm_positions', "data" => $boxid]);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Xóa thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xảy ra")]);
            }
        }
    })->setPermissions(['positions.deleted']);


    // CẬP NHẬT TRẠNG THÁI CHỨC VỤ
    $app->router("/positions-status/{id}", 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json',]);

        $data = $app->get("hrm_positions", "*", ["id" => $vars['id'], "deleted" => 0]);
        if (!empty($data)) {
            $status = ($data['status'] === 'A') ? 'D' : 'A';

            $app->update("hrm_positions", ["status" => $status], ["id" => $data['id']]);
            $jatbi->logs('positions', 'status', $data);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['positions.edit']);


$app->router('/decided-views/{id}', 'GET', function ($vars) use ($app, $jatbi, $template) { // <-- Gỡ $database khỏi use()
        $id = (int) ($vars['id'] ?? 0);
        $vars['title'] = $jatbi->lang("In Quyết định thôi việc");

        // 1. Lấy dữ liệu Quyết định (bảng contract_annex)
        $data = $app->get("contract_annex", "*", [
            "id" => $id,
            "type" => 2,
            "deleted" => 0
        ]);

        if (!$data) {
            return $app->render($template . '/error.html', $vars, $jatbi->ajax());
        }

        // 2. Lấy thông tin nhân viên (bảng personnels)
        $per = $app->get("personnels", "*", [
            "id" => $data['contract'],
            "deleted" => 0
        ]);

        if (!$per) {
            $vars['error_message'] = "Không tìm thấy thông tin nhân viên.";
            return $app->render($template . '/error.html', $vars, $jatbi->ajax());
        }
        
        // 3. Truyền biến sang template
        $vars['data'] = $data;
        $vars['per'] = $per; 
        
        // [SỬA LỖI] Truyền đối tượng $app vào template dưới tên biến là 'database'
        // Bây giờ $database->get() trong template sẽ hoạt động (vì nó chính là $app->get())
        $vars['database'] = $app; 

        // 4. Render file template
        echo $app->render($template . '/hrm/decided-views.html', $vars, $jatbi->ajax());

    })->setPermissions(['decided']);
})->middleware('login');
