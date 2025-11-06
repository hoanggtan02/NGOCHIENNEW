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
$common = $jatbi->getPluginCommon('io.eclo.hrm');
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
$app->group($setting['manager'] . "/hrm", function ($app) use ($jatbi, $setting, $accStore, $stores, $template, $common) {

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

            if (($app->getSession("accounts")['your_self'] ?? 0) == 1) {
                $where['AND']['personnels.id'] = $app->getSession("accounts")['personnels_id'];
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
                                'name' => $jatbi->lang("Xem chi tiết hợp đồng"),
                                'permission' => ['contract'],
                                'action' => ['data-url' => '/hrm/contract-view-personnel/' . ($data['id'] ?? ''), 'data-action' => 'modal']
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
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Đánh giá nhân viên"),
                                'permission' => ['personnels.evaluation'],
                                'action' => ['data-url' => '/hrm/personnels-evaluation/' . $data['id'], 'data-action' => 'modal']
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

    $app->router("/personnels-add", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $stores, $accStore) {
        $vars['title'] = $jatbi->lang("Thêm Nhân viên");
        if ($app->method() === 'GET') {
            $vars['permissions'] = $app->select("permission", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A"]);
            $vars['stores'] = array_merge([["value" => "", "text" => $jatbi->lang("Chọn")]], $app->select("stores", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "ORDER" => ["name" => "ASC"]]));
            $vars['offices'] = array_merge([["value" => "", "text" => $jatbi->lang("Chọn")]], $app->select("offices", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "ORDER" => ["name" => "ASC"]]));
            $vars['gender'] = [["value" => "", "text" => "Chọn"], ["value" => 0, "text" => "Nữ"], ["value" => 1, "text" => "Nam"],];
            $vars['idtype'] = [["value" => "", "text" => "Chọn"], ["value" => 1, "text" => "CMND"], ["value" => 2, "text" => "CCCD"], ["value" => 3, "text" => "CCCD gắn chíp"], ["value" => 4, "text" => "Hộ chiếu"],];
            $vars['data'] = ["status" => 'A',];
            $vars['province'] = array_merge([["value" => "", "text" => $jatbi->lang("Chọn")]], $app->select("province", ["id(value)", "name(text)"], ["deleted" => 0, "ORDER" => ["name" => "ASC"]]));
            $vars['province_new'] = array_merge([["value" => "", "text" => $jatbi->lang("Chọn")]], $app->select("province_new", ["id(value)", "name(text)"], ["deleted" => 0, "status" => "A", "ORDER" => ["name" => "ASC"]]));
            echo $app->render($template . '/hrm/personnels-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
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
                "relative_name" => $app->xss($_POST['relative_name']),
                "relative_phone" => $app->xss($_POST['relative_phone']),
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
            $result = $app->action(function () use ($app, $jatbi, $insert_data, $stores, $accStore) {

                $app->insert("personnels", $insert_data);
                $new_personnel_id = $app->id();
                if (count($stores) > 1) {
                    $input_stores = isset($_POST['stores']) ? $_POST['stores'] : [];
                    $input_stores = isset($_POST['stores']) ? $_POST['stores'] : [];
                    if (!is_array($input_stores)) {
                        $input_stores = [$input_stores];
                    }
                    $input_stores = array_map([$app, 'xss'], $input_stores);
                    if (empty($input_stores)) {
                        $input_stores = $app->select("stores", "id", ["id" => $accStore, "status" => 'A', "deleted" => 0, "ORDER" => ["id" => "ASC"]]);
                    }
                } else {
                    $input_stores = $app->select("stores", "id", ["id" => $accStore, "status" => 'A', "deleted" => 0, "ORDER" => ["id" => "ASC"]]);
                }
                $insert_account = [
                    "type" => 0,
                    "name" => $app->xss($_POST['name']),
                    "account" => $app->xss($_POST['account']),
                    "email" => $app->xss($_POST['email']),
                    "permission" => $app->xss($_POST['permission']),
                    "phone" => $app->xss($_POST['phone']),
                    "gender" => $app->xss($_POST['gender']),
                    "birthday" => $app->xss($_POST['birthday']),
                    "password" => password_hash($app->xss($_POST['password']), PASSWORD_DEFAULT),
                    "active" => $jatbi->active(),
                    "date" => date('Y-m-d H:i:s'),
                    "stores" => serialize($input_stores),
                    // "login"         => 'create',
                    "status" => $app->xss($_POST['status']),
                    // "lang"          => $_COOKIE['lang'] ?? 'vi',
                ];
                $app->insert("accounts", $insert_account);
                $account_id = $app->id();
                $jatbi->logs('hrm', 'accounts-add', [$insert_account]);
                // $jatbi->setStores("add",'accounts',$account_id,$_POST['stores'] ?? '');
                // 3️⃣ Cập nhật lại bảng nhân viên
                $app->update("personnels", [
                    "account" => $account_id
                ], [
                    "id" => $new_personnel_id
                ]);
                if (!$new_personnel_id) {
                    return false;
                }

                $furlough_type = $app->get("furlough_categorys", "id", ["code" => "NPN", "deleted" => 0]);
                $furlough_id = $furlough_type ?? null;

                $annual_leave_data = [
                    "profile_id" => $new_personnel_id,
                    "furlough_id" => $furlough_id,
                    "year" => date('Y'),
                    "total_accrued" => 0,
                    "carried_over" => 0,
                    "days_used" => 0,
                    "account" => $app->getSession("accounts")['id'] ?? 0,
                    "date" => date("Y-m-d H:i:s"),
                    "deleted" => 0,
                    "active" => $jatbi->active()
                ];

                $app->insert("annual_leave", $annual_leave_data);

                $insert = [
                    "personnels" => $new_personnel_id,
                    "offices" => $app->xss($_POST['office']),
                    "workday" => $app->xss($_POST['workday']),
                    "date" => date("Y-m-d H:i:s"),
                    "user" => $app->getSession("accounts")['id'] ?? null,
                ];
                $app->insert("personnels_contract", $insert);

                return $new_personnel_id;
            });

            
            $jatbi->logs('hrm', 'personnels-add', [$insert_data]);
            
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Thêm mới thành công")]);
        }
    })->setPermissions(['personnels.add']);

    // Route sửa nhân viên
    $app->router("/personnels-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $stores, $accStore) {
        $vars['title'] = $jatbi->lang("Sửa Nhân viên");

        // ===== GET: Load form =====
        if ($app->method() === 'GET') {
            $vars['data'] = $app->get("personnels", "*", ["id" => $vars['id'], "deleted" => 0]);
            $contract_data = $app->get("personnels_contract", ["workday", "offices"], ["personnels" => $vars['id']]);
            $vars['data']['workday'] = $contract_data['workday'];
            if (!$vars['data']) {
                echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
                return;
            }
            $vars['data_account'] = $app->get("accounts", "*", ["id" => $vars['data']['account']]);
            // Load dropdowns
            $vars['permissions'] = $app->select("permission", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A"]);
            $vars['stores'] = array_merge([["value" => "", "text" => $jatbi->lang("Chọn")]], $app->select("stores", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "ORDER" => ["name" => "ASC"]]));
            $vars['offices'] = array_merge([["value" => "", "text" => $jatbi->lang("Chọn")]], $app->select("offices", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "ORDER" => ["name" => "ASC"]]));
            $vars['gender'] = [["value" => "", "text" => "Chọn"], ["value" => 0, "text" => "Nữ"], ["value" => 1, "text" => "Nam"]];
            $vars['idtype'] = [["value" => "", "text" => "Chọn"], ["value" => 1, "text" => "CMND"], ["value" => 2, "text" => "CCCD"], ["value" => 3, "text" => "CCCD gắn chíp"], ["value" => 4, "text" => "Hộ chiếu"]];
            $vars['province'] = array_merge([["value" => "", "text" => $jatbi->lang("Chọn")]], $app->select("province", ["id(value)", "name(text)"], ["deleted" => 0, "ORDER" => ["name" => "ASC"]]));
            $vars['province_new'] = array_merge([["value" => "", "text" => $jatbi->lang("Chọn")]], $app->select("province_new", ["id(value)", "name(text)"], ["deleted" => 0, "status" => "A", "ORDER" => ["name" => "ASC"]]));

            echo $app->render($template . '/hrm/personnels-post.html', $vars, $jatbi->ajax());
        }

        // ===== POST: Cập nhật dữ liệu =====
        elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            $data = $app->get("personnels", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!$data) {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy nhân viên")]);
                return;
            }

            if (empty($_POST['name']) || empty($_POST['phone']) || empty($_POST['stores'])) {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống các trường bắt buộc")]);
                return;
            }

            $is_same_address = isset($_POST['same_address']) && $_POST['same_address'] == 1;

            // ===== Cập nhật bảng personnels =====
            $update_personnel = [
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
                "user" => $app->getSession("accounts")['id'] ?? null,
                "stores" => $app->xss($_POST['stores']),
                "office" => $app->xss($_POST['office']),
                "relative_name" => $app->xss($_POST['relative_name']),
                "relative_phone" => $app->xss($_POST['relative_phone']),
            ];

            if ($is_same_address) {
                $update_personnel['permanent_address'] = $update_personnel['address'];
                $update_personnel['permanent_province'] = $update_personnel['province'];
                $update_personnel['permanent_district'] = $update_personnel['district'];
                $update_personnel['permanent_ward'] = $update_personnel['ward'];
                $update_personnel['permanent_address_new'] = $update_personnel['address-new'];
                $update_personnel['permanent_province_new'] = $update_personnel['province-new'];
                $update_personnel['permanent_ward_new'] = $update_personnel['ward-new'];
            } else {
                $update_personnel['permanent_address'] = $app->xss($_POST['permanent_address']);
                $update_personnel['permanent_province'] = $app->xss($_POST['permanent_province'] ?? 0);
                $update_personnel['permanent_district'] = $app->xss($_POST['permanent_district'] ?? 0);
                $update_personnel['permanent_ward'] = $app->xss($_POST['permanent_ward'] ?? 0);
                $update_personnel['permanent_address_new'] = $app->xss($_POST['permanent_address_new']);
                $update_personnel['permanent_province_new'] = $app->xss($_POST['permanent_province_new'] ?? 0);
                $update_personnel['permanent_ward_new'] = $app->xss($_POST['permanent_ward_new'] ?? 0);
            }

            $app->update("personnels", $update_personnel, ["id" => $vars['id']]);
            $update_contract = [
                "workday" => $app->xss($_POST['workday']),
                "date" => date("Y-m-d H:i:s"),
                "user" => $app->getSession("accounts")['id'] ?? null,
            ];
            // Cập nhật dựa trên personnel_id
            $app->update("personnels_contract", $update_contract, ["personnels" => $vars['id']]);
            // ===== Cập nhật bảng accounts liên kết =====
            $account = $app->get("accounts", "*", ["id" => $data['account'], "deleted" => 0]);
            if ($account) {
                if (count($stores) > 1) {
                    $input_stores = isset($_POST['stores']) ? $_POST['stores'] : [];
                    $input_stores = isset($_POST['stores']) ? $_POST['stores'] : [];
                    if (!is_array($input_stores)) {
                        $input_stores = [$input_stores]; // ép chuỗi thành mảng
                    }
                    $input_stores = array_map([$app, 'xss'], $input_stores);
                    if (empty($input_stores)) {
                        $input_stores = $app->select("stores", "id", ["id" => $accStore, "status" => 'A', "deleted" => 0, "ORDER" => ["id" => "ASC"]]);
                    }
                } else {
                    $input_stores = $app->select("stores", "id", ["id" => $accStore, "status" => 'A', "deleted" => 0, "ORDER" => ["id" => "ASC"]]);
                }
                $update_account = [
                    "name" => $app->xss($_POST['name']),
                    "account" => $app->xss($_POST['account']),
                    "email" => $app->xss($_POST['email']),
                    "phone" => $app->xss($_POST['phone']),
                    "gender" => $app->xss($_POST['gender']),
                    "birthday" => $app->xss($_POST['birthday']),
                    "permission" => $app->xss($_POST['permission']),
                    "status" => $app->xss($_POST['status']),
                    "date" => date('Y-m-d H:i:s'),
                    "stores" => serialize($input_stores),
                ];

                // Nếu có nhập mật khẩu mới
                if (!empty($_POST['password'])) {
                    $update_account["password"] = password_hash($app->xss($_POST['password']), PASSWORD_DEFAULT);
                }

                $app->update("accounts", $update_account, ["id" => $account['id']]);
                // $jatbi->setStores("edit",'accounts',$account['id'],$_POST['stores'] ?? '');
            }

            $jatbi->logs('hrm', 'personnels-edit', [$update_personnel]);
            $jatbi->logs('hrm', 'accounts-edit', [$update_account ?? []]);

            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        }
    })->setPermissions(['personnels.edit']);


    $app->router("/personnels-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa nhân viên");

        $hrm_deviced = $app->select("hrm_decided", ["decided", "password"], ["deleted" => 0]);

        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $ids = explode(',', $app->xss($_GET['box']));

            if (empty($ids)) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Vui lòng chọn nhân viên cần xóa!')]);
                return;
            }

            $datas = $app->select("personnels", ["id"], ["id" => $ids, "deleted" => 0]);

            if (empty($datas)) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Không tìm thấy nhân viên hợp lệ để xóa.")]);
                return;
            }

            $update_result = $app->update(
                "personnels",
                ["deleted" => 1, "face" => 0],
                ["id" => $ids]
            );

            if ($update_result && $update_result->rowCount() > 0) {
                $jatbi->logs('personnels', 'deleted', $datas);

                $api_errors = [];
                foreach ($datas as $personnel) {
                    $personnel_id_to_delete = $personnel['id'];

                    foreach ($hrm_deviced as $device) {
                        $payload = [
                            'deviceKey' => $device['decided'],
                            'secret' => $device['password'],
                            'sn' => $personnel_id_to_delete,
                        ];

                        try {
                            $response = $jatbi->callCameraApi('person/delete', $payload);
                        } catch (Exception $e) {
                            $api_errors[] = "Lỗi gọi API xóa NV {$personnel_id_to_delete} khỏi thiết bị {$device['decided']}: " . $e->getMessage();
                        }
                    }
                }

                $content_message = $jatbi->lang("Xóa nhân viên thành công.");
                if (!empty($api_errors)) {
                    $content_message .= " " . $jatbi->lang("Tuy nhiên, có lỗi xảy ra khi xóa khỏi một số camera:") . " " . implode("; ", $api_errors);
                    echo json_encode(['status' => 'warning', 'content' => $content_message, 'reload' => true]);
                } else {
                    echo json_encode(['status' => 'success', 'content' => $content_message, 'reload' => true]);
                }
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Xóa nhân viên thất bại hoặc nhân viên đã bị xóa.')]);
            }
        }
    })->setPermissions(['personnels.deleted']);

    $app->router('/personnels-detail/{id}', 'GET', function ($vars) use ($app, $jatbi, $template, $setting) {
        if ($app->method() === 'GET') {
            $vars['data'] = $app->get("personnels", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($vars['data'])) {
                if (($app->getSession("accounts")['your_self'] ?? 0) == 1 && $app->getSession("accounts")['personnels_id'] != $vars['id']) {
                    echo $app->render($setting['template'] . '/pages/error.html', $vars, $jatbi->ajax());
                } else {
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
                }
            } else {
                echo $app->render($setting['template'] . '/pages/error.html', $vars, $jatbi->ajax());
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
            $vars['office'] = $app->select("offices", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "ORDER" => ["name" => "ASC"]]);
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
            $office = isset($_POST['office']) ? $_POST['office'] : '';

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
            if (($app->getSession("accounts")['your_self'] ?? 0) == 1) {
                $where['AND']['personnels.id'] = $app->getSession("accounts")['personnels_id'];
            }
            if (!empty($office)) {
                $where['AND']['personnels.office'] = $office;
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
                                'name' => $jatbi->lang("Xem"),
                                'permission' => ['rosters'],
                                'action' => ['data-url' => '/hrm/rosters-views/' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
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

    $app->router('/rosters-views/{id}', 'GET', function ($vars) use ($app, $jatbi, $template) {
        $vars['data'] = $app->get("rosters", "*", ["id" => $vars['id'], "deleted" => 0]);
        if (!empty($vars['data'])) {
            $vars['title'] = $jatbi->lang("Xem chi tiết bảng phân công");
            $vars['personnel'] = $app->get("personnels", "*", ["id" => $vars['data']['personnels'], "deleted" => 0]);
            $vars['timework'] = $app->get("timework", "*", ["id" => $vars['data']['timework'], "deleted" => 0]);
            $vars['timework_details'] = $app->select("timework_details", "*", ["timework" => $vars['timework']['id'], "deleted" => 0]);
            echo $app->render($template . '/hrm/rosters-views.html', $vars, $jatbi->ajax());
        } else {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['rosters']);

    $app->router('/rosters-excel', 'GET', function ($vars) use ($app, $stores) {
        try {
            $month_year = $_GET['month_year'] ?? date('Y-m');
            $officeF = $_GET['office'] ?? '';
            $personnel = $_GET['personnels'] ?? '';
            $store_ids = array_column($stores, 'value');

            $year = date('Y', strtotime($month_year));
            $month = date('m', strtotime($month_year));
            $totalDays = date("t", strtotime($month_year));
            $lastDayOfMonth = "$year-$month-$totalDays";

            // === 1. LẤY DANH SÁCH NHÂN VIÊN ===
            $wherePersonnel = [
                "deleted" => 0,
                "status" => 'A',
                "stores" => $store_ids,
            ];
            if (!empty($officeF))
                $wherePersonnel['office'] = $officeF;
            if (!empty($personnel))
                $wherePersonnel['id'] = $personnel;

            $personnel_list = $app->select("personnels", ["id", "name"], ["ORDER" => ["name" => "ASC"]] + $wherePersonnel);
            if (empty($personnel_list))
                exit("Không có nhân viên nào thỏa mãn điều kiện.");

            $personnel_ids = array_column($personnel_list, 'id');

            // === 2. LẤY CA LÀM VIỆC (ROSTER) ===
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

            $roster_schedule_map = [];
            foreach ($all_rosters as $roster) {
                $roster_schedule_map[$roster['personnels']][$roster['date']] = $roster['timework'];
            }

            // === 3. LẤY CHI TIẾT CA (TIMEWORK) ===
            $all_timework_details = $app->select("timework_details", [
                "id",
                "timework",
                "week",
                "time_from",
                "time_to",
                "off"
            ]);

            $timework_details_map = [];
            foreach ($all_timework_details as $detail) {
                $timework_details_map[$detail['timework']][$detail['week']] = $detail;
            }

            // === 4. LẤY NGHỈ PHÉP ĐÃ DUYỆT (status = 'A') ===
            $leave_requests = $app->select("hrm_leave_request_details", [
                "[>]hrm_leave_requests" => ["leave_request_id" => "id"]
            ], [
                "hrm_leave_requests.profile_id",
                "hrm_leave_request_details.leave_date",
                "hrm_leave_request_details.leave_session"
            ], [
                "hrm_leave_requests.profile_id" => $personnel_ids,
                "hrm_leave_requests.deleted" => 0,
                "hrm_leave_requests.status" => 'A', // ĐÃ DUYỆT
                "hrm_leave_request_details.leave_date[>=]" => "$year-$month-01",
                "hrm_leave_request_details.leave_date[<=]" => $lastDayOfMonth
            ]);

            $leave_map = [];
            foreach ($leave_requests as $leave) {
                $pid = $leave['profile_id'];
                $date = $leave['leave_date'];
                $session = $leave['leave_session'];
                $leave_map[$pid][$date] = $session;
            }

            // === 5. XÂY DỰNG LỊCH CHO TỪNG NHÂN VIÊN ===
            $roster_map = [];
            $off_days = []; // Đếm ngày OFF + NP
            $work_hours = []; // Tổng công

            foreach ($personnel_ids as $pid) {
                $current_timework_id = null;
                $off_days[$pid] = 0;
                $work_hours[$pid] = 0;

                // Lấy ca làm việc cuối cùng
                if (isset($roster_schedule_map[$pid])) {
                    $dates = array_keys($roster_schedule_map[$pid]);
                    rsort($dates);
                    foreach ($dates as $d) {
                        if ($d <= $lastDayOfMonth) {
                            $current_timework_id = $roster_schedule_map[$pid][$d];
                            break;
                        }
                    }
                }

                for ($day = 1; $day <= $totalDays; $day++) {
                    $current_date_str = sprintf('%s-%s-%02d', $year, $month, $day);
                    $week_day = date('N', strtotime($current_date_str));

                    if (isset($leave_map[$pid][$current_date_str])) {
                        $session = $leave_map[$pid][$current_date_str];
                        if ($session === 'full_day') {
                            $roster_map[$pid][$current_date_str] = 'OFF';
                            $off_days[$pid]++;
                        } elseif ($session === 'morning') {
                            $roster_map[$pid][$current_date_str] = 'OFF:S';
                            $off_days[$pid] += 0.5;
                            $work_hours[$pid] += 0.5;
                        } elseif ($session === 'afternoon') {
                            $roster_map[$pid][$current_date_str] = 'OFF:C';
                            $off_days[$pid] += 0.5;
                            $work_hours[$pid] += 0.5;
                        }
                        continue;
                    }

                    // --- KIỂM TRA CA LÀM VIỆC ---
                    if (isset($roster_schedule_map[$pid][$current_date_str])) {
                        $current_timework_id = $roster_schedule_map[$pid][$current_date_str];
                    }

                    if ($current_timework_id === null) {
                        $roster_map[$pid][$current_date_str] = '';
                        continue;
                    }

                    if (isset($timework_details_map[$current_timework_id][$week_day])) {
                        $detail = $timework_details_map[$current_timework_id][$week_day];

                        if ($detail['off'] == 1) {
                            $roster_map[$pid][$current_date_str] = 'OFF';
                            $off_days[$pid]++;
                        } elseif (!empty($detail['time_from']) && !empty($detail['time_to']) && $detail['time_from'] != '00:00:00') {
                            $hours = (strtotime($detail['time_to']) - strtotime($detail['time_from'])) / 3600;
                            $cong = round($hours / 8, 2);
                            $roster_map[$pid][$current_date_str] = $cong;
                            $work_hours[$pid] += $cong;
                        } else {
                            $roster_map[$pid][$current_date_str] = '';
                        }
                    } else {
                        $roster_map[$pid][$current_date_str] = 'OFF';
                        $off_days[$pid]++;
                    }
                }
            }

            // === 6. TẠO FILE EXCEL ===
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle("LichLamViec_Thang_{$month}_{$year}");

            // Tiêu đề
            $sheet->setCellValue('A1', "LỊCH LÀM VIỆC & NGHỈ PHÉP THÁNG $month/$year");
            $mergeToCol = Coordinate::stringFromColumnIndex(3 + $totalDays + 3);
            $sheet->mergeCells("A1:$mergeToCol" . '1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF99');

            // Header
            $sheet->setCellValue('A2', 'STT')->mergeCells('A2:A3');
            $sheet->setCellValue('B2', 'Họ và tên')->mergeCells('B2:B3');

            $days_of_week_map = ['1' => 'T2', '2' => 'T3', '3' => 'T4', '4' => 'T5', '5' => 'T6', '6' => 'T7', '7' => 'CN'];
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
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($lastDayColIndex + 1) . '2', 'TỔNG CÔNG')->mergeCells(Coordinate::stringFromColumnIndex($lastDayColIndex + 1) . '2:' . Coordinate::stringFromColumnIndex($lastDayColIndex + 1) . '3');
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($lastDayColIndex + 2) . '2', 'TỔNG NGHỈ')->mergeCells(Coordinate::stringFromColumnIndex($lastDayColIndex + 2) . '2:' . Coordinate::stringFromColumnIndex($lastDayColIndex + 2) . '3');
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($lastDayColIndex + 3) . '2', 'GHI CHÚ')->mergeCells(Coordinate::stringFromColumnIndex($lastDayColIndex + 3) . '2:' . Coordinate::stringFromColumnIndex($lastDayColIndex + 3) . '3');

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

                foreach (range(1, $totalDays) as $day) {
                    $current_date_str = "$year-$month-" . str_pad($day, 2, '0', STR_PAD_LEFT);
                    $value = $roster_map[$pid][$current_date_str] ?? '';

                    $colLetter = Coordinate::stringFromColumnIndex(2 + $day);
                    $sheet->setCellValue($colLetter . $rowIndex, $value);

                    // Tô màu
                    if ($value === 'OFF') {
                        $sheet->getStyle($colLetter . $rowIndex)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFCCCB');
                    } elseif ($value === 'S' || $value === 'C') {
                        $sheet->getStyle($colLetter . $rowIndex)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF99');
                    } elseif ($value === 'OFF') {
                        $sheet->getStyle($colLetter . $rowIndex)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('DDDDDD');
                    }
                }

                // Tổng công / nghỉ
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($lastDayColIndex + 1) . $rowIndex, round($work_hours[$pid], 2));
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($lastDayColIndex + 2) . $rowIndex, round($off_days[$pid], 1));
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($lastDayColIndex + 3) . $rowIndex, '');

                $rowIndex++;
            }

            // Viền + kích thước
            $lastColLetter = $sheet->getHighestColumn();
            $sheet->getStyle('A2:' . $lastColLetter . ($rowIndex - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
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
            $filename = "LichLamViec_NghiPhep_Thang_{$month}_{$year}.xlsx";
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
                ["value" => "", "text" => "Tất cả"],
                ["value" => 1, "text" => $jatbi->lang("Xác định thời hạn")],
                ["value" => 2, "text" => $jatbi->lang("Không xác định thời hạn")],
                ["value" => 3, "text" => $jatbi->lang("Thử việc")],
            ];
            $vars['insurance_status'] = [
                ["value" => "", "text" => "Tất cả"],
                ["value" => "official_has", "text" => "Chính thức – vô bảo hiểm"],
                ["value" => "official_no", "text" => "Chính thức – chưa vô bảo hiểm"],
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
            $insurance_status = isset($_POST['insurance_status']) ? $_POST['insurance_status'] : '';

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
                    "personnels.stores" => $accStore,
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
            if (!empty($insurance_status)) {
                if ($insurance_status === 'official_has') {
                    // Chính thức – vô bảo hiểm
                    // Lấy danh sách nhân viên có hợp đồng chính thức (type = 1,2)
                    // và có tên trong bảng personnels_insurrance
                    $insured_personnels = $app->select("personnels_insurrance", "personnels", [
                        "deleted" => 0,
                        "status" => 'A'
                    ]);
                    $where['AND']['personnels_contract.type'] = [1, 2]; // 1: XĐTH, 2: KXĐTH
                    $where['AND']['personnels.id'] = $insured_personnels;
                } elseif ($insurance_status === 'official_no') {
                    // Chính thức – chưa vô bảo hiểm
                    // Lấy danh sách nhân viên có hợp đồng chính thức
                    // nhưng KHÔNG có trong bảng personnels_insurrance
                    $insured_personnels = $app->select("personnels_insurrance", "personnels", [
                        "deleted" => 0,
                        "status" => 'A'
                    ]);
                    $where['AND']['personnels_contract.type'] = [1, 2];
                    $where['AND']['personnels.id[!]'] = $insured_personnels;
                }
            }
            if (($app->getSession("accounts")['your_self'] ?? 0) == 1) {
                $where['AND']['personnels.id'] = $app->getSession("accounts")['personnels_id'];
            }

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
                'personnels.id(personnels_id)',
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
                                'name' => $jatbi->lang("Xem chi tiết lương"),
                                'permission' => ['contract'],
                                'action' => ['data-url' => '/hrm/contract-salary/' . ($data['personnels_id'] ?? ''), 'data-action' => 'modal']
                            ],
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

    $app->router("/contract-salary/{id}", ['GET'], function ($vars) use ($app, $jatbi, $setting, $template) {
        $vars['title'] = $jatbi->lang("Lịch sử lương nhân viên");
        if ($app->method() === 'GET') {
            $personnel_id = $vars['id'];

            $vars['personnel_data'] = $app->get("personnels", ["id", "name", "code"], [
                "id" => $personnel_id,
                "deleted" => 0
            ]);

            if (empty($vars['personnel_data'])) {
                echo $app->render($setting['template'] . '/pages/error.html', $vars, $jatbi->ajax());
                return;
            }

            $current_personnel_id = $app->getSession("accounts")["personnels_id"] ?? 0;
            $is_your_self = $app->getSession("accounts")['your_self'] ?? 0;

            if ($is_your_self == 1 && $personnel_id != $current_personnel_id) {
                echo $app->render($setting['template'] . '/pages/error.html', $vars, $jatbi->ajax());
                return;
            }

            $contracts = $app->select("personnels_contract", [
                "id",
                "salary",
                "code",
                "type",
                "salary_diligence",
                "salary_allowance",
                "date_contract",
                "date_end",
                "position",
            ], [
                "personnels" => $personnel_id,
                "deleted" => 0,
                "ORDER" => ["date_contract" => "DESC"]
            ]);


            $vars['contracts_list'] = [];

            if (!empty($contracts)) {
                foreach ($contracts as $contract) {
                    $contract['position_name'] = $app->get("hrm_positions", "name", ["id" => $contract['position']]) ?? '';
                    $contract['contract_type_name'] = $setting['personnels_contracts'][$contract['type']]['name'] ?? 'Không xác định';

                    $vars['contracts_list'][] = $contract;
                }
            }

            echo $app->render($template . '/hrm/personnel-salary-history.html', $vars, $jatbi->ajax());
        }
    })->setPermissions(['contract']);

    $app->router('/contract-salary', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $setting, $accStore) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Bảng lương nhân viên");
            $vars['personnels'] = $app->select("personnels", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "stores" => $accStore, "ORDER" => ["name" => "ASC"]]);
            $vars['offices'] = $app->select("offices", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "ORDER" => ["name" => "ASC"]]);
            $vars['contract_type'] = [
                ["value" => "", "text" => "Tất cả"],
                ["value" => 1, "text" => $jatbi->lang("Xác định thời hạn")],
                ["value" => 2, "text" => $jatbi->lang("Không xác định thời hạn")],
                ["value" => 3, "text" => $jatbi->lang("Thử việc")],
            ];
            $vars['insurance_status'] = [
                ["value" => "", "text" => "Tất cả"],
                ["value" => "official_has", "text" => "Chính thức – vô bảo hiểm"],
                ["value" => "official_no", "text" => "Chính thức – chưa vô bảo hiểm"],
            ];
            echo $app->render($template . '/hrm/contract-salary.html', $vars);
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
            $insurance_status = isset($_POST['insurance_status']) ? $_POST['insurance_status'] : '';

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
                    "personnels.stores" => $accStore,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
                "GROUP" => ["personnels_contract.personnels "]
            ];

            if (!empty($personnels))
                $where['AND']['personnels.id'] = $personnels;
            if (!empty($type))
                $where['AND']['personnels_contract.type'] = $type;
            if (!empty($offices))
                $where['AND']['offices.id'] = $offices;
            if (!empty($insurance_status)) {
                if ($insurance_status === 'official_has') {

                    $insured_personnels = $app->select("personnels_insurrance", "personnels", [
                        "deleted" => 0,
                        "status" => 'A'
                    ]);
                    $where['AND']['personnels_contract.type'] = [1, 2]; // 1: XĐTH, 2: KXĐTH
                    $where['AND']['personnels.id'] = $insured_personnels;
                } elseif ($insurance_status === 'official_no') {
                    $insured_personnels = $app->select("personnels_insurrance", "personnels", [
                        "deleted" => 0,
                        "status" => 'A'
                    ]);
                    $where['AND']['personnels_contract.type'] = [1, 2];
                    $where['AND']['personnels.id[!]'] = $insured_personnels;
                }
            }
            if (($app->getSession("accounts")['your_self'] ?? 0) == 1) {
                $where['AND']['personnels.id'] = $app->getSession("accounts")['personnels_id'];
            }

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
                'personnels_contract.salary_diligence',
                'personnels_contract.salary_allowance',
                'personnels.id(personnels_id)',
                'personnels.name(personnel_name)',
                'offices.name(office_name)'
            ], $where, function ($data) use (&$datas, $jatbi, $app, $setting) {

                $start_date = date("Y-m-d", strtotime($data['date_contract']));
                $end_date = date("Y-m-d", strtotime($start_date . " +" . $data['duration'] . " month"));
                $diff_seconds = strtotime($end_date) - strtotime(date("Y-m-d"));
                $remaining_days = round($diff_seconds / (60 * 60 * 24));

                $datas[] = [
                    "personnel_name" => $data['personnel_name'] ?? "",
                    "office_name" => $data['office_name'] ?? "",
                    // "type" => $setting['personnels_contracts'][$data['type']]['name'] ?? "",
                    "workday" => date("d/m/Y", timestamp: strtotime(datetime: $data['workday'])),
                    "code" => $data['code'] ?? "",
                    "salary" => number_format($data['salary'] ?? 0),
                    "salary_diligence" => number_format($data['salary_diligence'] ?? 0),
                    "salary_allowance" => number_format($data['salary_allowance'] ?? 0),
                    "total_salary" => number_format(($data['salary'] + $data['salary_diligence'] + $data['salary_allowance']) ?? 0),
                    "action" => ($app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xem chi tiết lương"),
                                'permission' => ['contract'],
                                'action' => ['data-url' => '/hrm/contract-salary/' . ($data['personnels_id'] ?? ''), 'data-action' => 'modal']
                            ],
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
    })->setPermissions(['contract-salary']);

    $app->router('/contract-excel', 'GET', function ($vars) use ($app, $setting, $stores) {
        try {
            $store_ids = array_column($stores, 'value');
            $personnels_filter = $_GET['personnels'] ?? '';
            $type_filter = $_GET['type'] ?? '';
            $offices_filter = $_GET['offices'] ?? '';

            $joins = [
                "[><]personnels(p)" => ["pc.personnels" => "id"],
                "[>]offices(o)" => ["p.office" => "id"],
                "[>]personnels_insurrance(pi)" => ["p.id" => "personnels"],

                "[>]ward(w)" => ["p.ward" => "id"],
                "[>]district(d)" => ["p.district" => "id"],
                "[>]province(pv)" => ["p.province" => "id"],

                "[>]hrm_positions(pos)" => ["pc.position" => "id"],

                "[>]province_new(pvn)" => ["p.province-new" => "id"],
                "[>]district_new(dn)" => ["p.ward-new" => "id"],
            ];

            $where = [
                "AND" => [
                    "pc.deleted" => 0,
                    "p.deleted" => 0,
                    "p.stores" => $store_ids
                ],
                "ORDER" => ["p.name" => "ASC"]
            ];

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
                "dia_chi" => $app->raw(
                    "CASE 
                        WHEN p.`address-new` IS NOT NULL AND p.`address-new` != '' 
                        THEN CONCAT_WS(', ', p.`address-new`, dn.name, pvn.name) 
                        ELSE CONCAT_WS(', ', p.address, w.name, d.name, pv.name) 
                    END"
                ),
                "p.phone(sdt)",
                "p.idcode(cccd)",
                "p.iddate(ngay_cap_cccd)",
                "p.idplace(noi_cap_cccd)",
                "o.name(phong_ban)",
                "pos.name(chuc_danh)",
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
                ["value" => 1, "text" => $jatbi->lang("Xác định thời hạn")],
                ["value" => 2, "text" => $jatbi->lang("Không xác định thời hạn")],
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
                "salary_diligence" => $app->xss(str_replace([','], '', $_POST['salary_diligence'])),
                "salary_allowance" => $app->xss(str_replace([','], '', $_POST['salary_allowance'])),
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

    $app->router("/contract-add/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $accStore) {
        $vars['title'] = $jatbi->lang("Thêm Hợp đồng lao động");
        if ($app->method() === 'GET') {

            $vars['personnels'] = $app->select("personnels", ["id (value)", "name (text)"], ["deleted" => 0, "id" => $vars['id'], "status" => "A", "stores" => $accStore, "ORDER" => ["name" => "ASC"]]);
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
                ["value" => 1, "text" => $jatbi->lang("Xác định thời hạn")],
                ["value" => 2, "text" => $jatbi->lang("Không xác định thời hạn")],
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
                "salary_diligence" => $app->xss(str_replace([','], '', $_POST['salary_diligence'])),
                "salary_allowance" => $app->xss(str_replace([','], '', $_POST['salary_allowance'])),
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
                    ["value" => 1, "text" => $jatbi->lang("Xác định thời hạn")],
                    ["value" => 2, "text" => $jatbi->lang("Không xác định thời hạn")],
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
                // $vars['dataSalary'] = $app->get("personnels_contract_salary", "*", ["deleted" => 0, "contract" => $vars['data']['id']]);
                // $vars['salarys'] = $app->select("personnels_contract_salary_details", "*", ["type" => 1, "contract" => $vars['data']['id'], "status" => 0, "salary" => $vars['dataSalary']['id'], "deleted" => 0]);
                // $vars['allowances'] = $app->select("personnels_contract_salary_details", "*", ["type" => 2, "contract" => $vars['data']['id'], "status" => 0, "salary" => $vars['dataSalary']['id'], "deleted" => 0]);

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
                if ($app->xss($_POST['date_contract']) > $date_end) {
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
                    "salary" => $app->xss(str_replace([','], '', $_POST['salary'])),
                    // "salary_eat" 		=> $app->xss(str_replace([','],'',$_POST['salary_eat'])),
                    "salary_diligence" => $app->xss(str_replace([','], '', $_POST['salary_diligence'])),
                    "salary_allowance" => $app->xss(str_replace([','], '', $_POST['salary_allowance'])),
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
                // Kiểm tra quyền xem hợp đồng của chính mình
                $current_user_contract_id = $app->get("personnels_contract", "id", [
                    "personnels" => $app->getSession("accounts")["personnels_id"] ?? 0,
                    "deleted" => 0
                ]);

                if (($app->getSession("accounts")['your_self'] ?? 0) == 1 && $current_user_contract_id != $vars['id']) {
                    echo $app->render($setting['template'] . '/pages/error.html', $vars, $jatbi->ajax());
                    return;
                }

                // Lấy dữ liệu bổ sung
                $vars['personnel_name'] = $app->get("personnels", "name", ["id" => $vars['data']['personnels']]) ?? '';
                $vars['office_name'] = $app->get("offices", "name", ["id" => $vars['data']['offices']]) ?? '';
                $vars['position_name'] = $app->get("hrm_positions", "name", ["id" => $vars['data']['position']]) ?? '';
                $vars['user_name'] = $app->get("accounts", "name", ["id" => $vars['data']['user']]) ?? '';

                // Chỉ dùng loại hợp đồng có sẵn
                $vars['contract_type_name'] = $setting['personnels_contracts'][$vars['data']['type']]['name'] ?? 'Không xác định';

                // Lương bổ sung (nếu có bảng personnels_contract_salary)
                $vars['dataSalary'] = $app->get("personnels_contract_salary", "*", [
                    "deleted" => 0,
                    "contract" => $vars['data']['id'],
                    "status" => 0,
                    "ORDER" => ["id" => "DESC"]
                ]);

                echo $app->render($template . '/hrm/contract-view.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
            }
        }
    })->setPermissions(['contract']);

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

    $app->router('/contract-print/{id}', 'GET', function ($vars) use ($app, $jatbi, $template, $setting) {
        $id = (int) ($vars['id'] ?? 0);
        $vars['title'] = $jatbi->lang("In Hợp đồng lao động");

        $data = $app->get("personnels_contract", [
            "[>]personnels(p)" => ["personnels" => "id"],
            "[>]offices(o)" => ["offices" => "id"],
            "[>]hrm_positions(pos)" => ["position" => "id"],
            "[>]province(pv)" => ["p.province" => "id"],
            "[>]district(d)" => ["p.district" => "id"],
            "[>]ward(w)" => ["p.ward" => "id"],
            "[>]province_new(pvn)" => ["p.province-new" => "id"],
            "[>]district_new(dn)" => ["p.ward-new" => "id"]
        ], [
            "personnels_contract.id",
            "personnels_contract.code",
            "personnels_contract.date_contract",
            "personnels_contract.type",
            "personnels_contract.duration",
            "personnels_contract.salary",
            "personnels_contract.salary_diligence",
            "personnels_contract.salary_allowance",
            "personnels_contract.offices",
            "personnels_contract.interview",
            "personnels_contract.workday",
            "personnels_contract.degree",
            "personnels_contract.certificate",
            "personnels_contract.health",
            "personnels_contract.notes",
            "personnels_contract.personnels",
            "p.name(personnel_name)",
            "pos.name(position)",
            "p.birthday",
            "address" => $app->raw(
                "CASE 
                        WHEN p.`address-new` IS NOT NULL AND p.`address-new` != '' 
                        THEN CONCAT_WS(', ', p.`address-new`, dn.name, pvn.name) 
                        ELSE CONCAT_WS(', ', p.address, w.name, d.name, pv.name) 
                    END"
            ),
            "p.idcode",
            "p.iddate",
            "p.idplace",
            "p.gender",
            "p.nation",
            "o.name(office_name)",
        ], ["personnels_contract.id" => $id, "personnels_contract.deleted" => 0]);

        if (!$data) {
            return $app->render($template . '/error.html', $vars, $jatbi->ajax());
        }


        // 3. Lấy lịch làm việc
        $timework_details = [];
        $timework_name = "Chưa phân công";

        $roster = $app->get("rosters", ["timework"], [
            "personnels" => $data['personnels'],
            "date[<=]" => $data['date_contract'],
            "deleted" => 0,
            "ORDER" => ["date" => "DESC"]
        ]);

        if ($roster && $roster['timework']) {
            $timework_id = $roster['timework'];
            $timework_info = $app->get("timework", ["name"], ["id" => $timework_id, "deleted" => 0]);
            if ($timework_info) {
                $timework_name = $timework_info['name'];
            }
            $details = $app->select("timework_details", "*", [
                "timework" => $timework_id,
                "deleted" => 0,
                "ORDER" => ["week" => "ASC"]
            ]);
            foreach ($details as $detail) {
                $timework_details[$detail['week']] = $detail;
            }
        }

        // 4. Tạo chuỗi lịch làm việc
        $work_schedule_line1 = "";
        $work_schedule_line2 = "";
        $days_map = [1 => 'Thứ Hai', 2 => 'Thứ Ba', 3 => 'Thứ Tư', 4 => 'Thứ Năm', 5 => 'Thứ Sáu', 6 => 'Thứ Bảy', 7 => 'Chủ Nhật'];
        $work_schedule_summary = [];
        $off_days = [];
        $shift_counter = 1;

        for ($i = 1; $i <= 7; $i++) {
            if (isset($timework_details[$i])) {
                $detail = $timework_details[$i];
                if ($detail['off'] == 1) {
                    $off_days[] = $days_map[$i];
                } else {
                    $start = date('H:i', strtotime($detail['time_from']));
                    $end = date('H:i', strtotime($detail['time_to']));
                    $schedule_key = "{$start} - {$end}";
                    $work_schedule_summary[$schedule_key][] = $days_map[$i];
                }
            } else {
                $off_days[] = $days_map[$i];
            }
        }

        $first_line_text = "Từ ngày Thứ Hai đến ngày Chủ nhật hàng tuần:";
        if (!empty($off_days)) {
            $first_line_text .= " nghỉ ngày " . implode(', ', $off_days);
        }
        $work_schedule_line1 = $first_line_text;

        $shift_lines = [];
        if (!empty($work_schedule_summary)) {
            foreach ($work_schedule_summary as $schedule => $days) {
                list($start_time, $end_time) = explode(' - ', $schedule);
                $shift_lines[] = "+ " . htmlspecialchars($timework_name) . ": Từ {$start_time} giờ đến {$end_time} giờ (bao gồm 1:30 giờ nghỉ trưa).";
            }
            $work_schedule_line2 = implode("\n", $shift_lines);
        } elseif (empty($work_schedule_summary) && empty($off_days)) {
            $work_schedule_line1 = "- Lịch làm việc chi tiết sẽ được thông báo sau.";
            $work_schedule_line2 = "";
        }

        // 5. Gán biến cho template
        $vars['data'] = $data; // $data này BÂY GIỜ chứa 'personnel_address'
        // $vars['salarys'] = $salarys_db;
        // $vars['allowances'] = $allowances_db;
        $vars['work_schedule_line1'] = $work_schedule_line1;
        $vars['work_schedule_line2'] = $work_schedule_line2;

        echo $app->render($template . '/hrm/contract-print.html', $vars, $jatbi->ajax());
    })->setPermissions(['contract']);

    //Bao hiem
    $app->router('/insurrance', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores, $template, $accStore) {
        $vars['title'] = $jatbi->lang("Bảo hiểm");
        if ($app->method() === 'GET') {
            $store_ids = array_column($stores, column_key: 'value');
            $vars['insurance_status'] = [
                ["value" => "", "text" => "Tất cả"],
                ["value" => "official_has", "text" => "Chính thức – vô bảo hiểm"],
                ["value" => "official_no", "text" => "Chính thức – chưa vô bảo hiểm"],
            ];
            $vars['personnels'] = $app->select("personnels", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "stores" => $accStore, "ORDER" => ["name" => "ASC"]]);
            echo $app->render($template . '/hrm/insurrance.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            $draw = intval($_POST['draw'] ?? 0);
            $start = intval($_POST['start'] ?? 0);
            $length = intval($_POST['length'] ?? ($setting['site_page'] ?? 10));
            $searchValue = $_POST['search']['value'] ?? '';
            $orderName = $_POST['order'][0]['name'] ?? 'personnels.id';
            $orderDir = strtoupper($_POST['order'][0]['dir'] ?? 'DESC');
            $personnels = $_POST['personnels'] ?? '';
            $insurance_status = isset($_POST['insurance_status']) ? $_POST['insurance_status'] : '';
            $status = (isset($_POST['status']) && in_array($_POST['status'], ['A', 'D'])) ? [$_POST['status'], $_POST['status']] : '';

            $joins = [
                "[>]personnels_insurrance" => ["id" => "personnels"],
            ];

            // ✅ Gom điều kiện cho đúng cấu trúc SQL
            $where = [
                "AND" => [
                    "personnels.deleted" => 0,
                    "personnels.stores" => $accStore,
                    // cho phép cả có hoặc chưa có bảo hiểm
                    "OR #insurance-null" => [
                        "personnels_insurrance.id[!]" => null,
                        "personnels_insurrance.id" => null,
                    ],
                    // bộ lọc tìm kiếm
                    "OR #search" => [
                        "personnels.name[~]" => $searchValue,
                        "personnels_insurrance.social[~]" => $searchValue,
                        "personnels_insurrance.health[~]" => $searchValue,
                    ],
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => $orderDir],
            ];

            if (!empty($status)) {
                $where['AND']['personnels_insurrance.status'] = $status;
            }
            if (!empty($personnels)) {
                $where['AND']['personnels.id'] = $personnels;
            }
            if (!empty($insurance_status)) {
                if ($insurance_status === 'official_has') {
                    $where['AND']['personnels_insurrance.id[!]'] = null; // có bảo hiểm
                } elseif ($insurance_status === 'official_no') {
                    $where['AND']['personnels_insurrance.id'] = null; // chưa có bảo hiểm
                }
            }

            if (($app->getSession("accounts")['your_self'] ?? 0) == 1) {
                $where['AND']['personnels.id'] = $app->getSession("accounts")['personnels_id'];
            }

            $count = $app->count("personnels", $joins, "personnels.id", $where['AND']);
            $datas = [];

            $app->select("personnels", $joins, [
                'personnels.id(personnel_id)',
                'personnels.name(personnel_name)',
                'personnels.status(personnel_status)',
                'personnels_insurrance.id(insurrance_id)',
                'personnels_insurrance.price',
                'personnels_insurrance.social',
                'personnels_insurrance.social_date',
                'personnels_insurrance.social_place',
                'personnels_insurrance.health',
                'personnels_insurrance.health_date',
                'personnels_insurrance.health_place',
                'personnels_insurrance.date',
                'personnels_insurrance.status',
            ], $where, function ($data) use (&$datas, $jatbi, $app) {

                $has_insurrance = !empty($data['insurrance_id']);

                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['insurrance_id'] ?? '']),
                    "personnel_name" => $data['personnel_name'] ?? '',
                    "price" => $has_insurrance ? number_format($data['price']) : '',
                    "social" => $data['social'] ?? '',
                    "social_date" => !empty($data['social_date']) ? date("d/m/Y", strtotime($data['social_date'])) : '',
                    "social_place" => $data['social_place'] ?? '',
                    "health" => $data['health'] ?? '',
                    "health_date" => !empty($data['health_date']) ? date("d/m/Y", strtotime($data['health_date'])) : '',
                    "health_place" => $data['health_place'] ?? '',
                    "date" => !empty($data['date']) ? $jatbi->datetime($data['date'], 'datetime') : '',
                    "status" => $has_insurrance
                        ? $app->component("status", [
                            "url" => "/hrm/insurrance-status/" . $data['insurrance_id'],
                            "data" => $data['status'],
                            "permission" => ['insurrance.edit']
                        ])
                        : '<span class="badge bg-warning text-dark small">Chưa có</span>',
                    "action" => ($app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xem"),
                                'permission' => ['insurrance'],
                                'action' => ['data-url' => '/hrm/insurrance-view/' . ($data['insurrance_id'] ?? ''), 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['insurrance.edit'],
                                'action' => ['data-url' => '/hrm/insurrance-edit/' . ($data['insurrance_id'] ?? ''), 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'permission' => ['insurrance.deleted'],
                                'action' => ['data-url' => '/hrm/insurrance-deleted?box=' . ($data['insurrance_id'] ?? ''), 'data-action' => 'modal']
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

    $app->router("/insurrance-view/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $setting) {
        $vars['title'] = $jatbi->lang("Bảo hiểm");
        if ($app->method() === 'GET') {
            $vars['data'] = $app->get("personnels_insurrance", "*", ["id" => $vars['id'], "deleted" => 0]);
            if (!empty($vars['data'])) {
                $insurrance_id = $app->get("personnels_insurrance", "id", ["personnels" => $app->getSession("accounts")["personnels_id"], "deleted" => 0]);

                if (($app->getSession("accounts")['your_self'] ?? 0) == 1 && $insurrance_id != $vars['id']) {
                    echo $app->render($setting['template'] . '/pages/error.html', $vars, $jatbi->ajax());
                } else {
                    $vars['dataSalary'] = $app->get("personnels_contract_salary", "*", ["deleted" => 0, "contract" => $vars['data']['id'], "status" => 0, "ORDER" => ["id" => "DESC"]]);
                    $vars['salarys'] = $app->select("personnels_contract_salary_details", "*", ["type" => 1, "contract" => $vars['data']['id'], "status" => 0, "salary" => $vars['dataSalary']['id'], "deleted" => 0]);
                    $vars['allowances'] = $app->select("personnels_contract_salary_details", "*", ["type" => 2, "contract" => $vars['data']['id'], "status" => 0, "salary" => $vars['dataSalary']['id'], "deleted" => 0]);
                    echo $app->render($template . '/hrm/insurrance-view.html', $vars, $jatbi->ajax());
                }
            } else {
                echo $app->render($setting['template'] . '/pages/error.html', $vars, $jatbi->ajax());
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

    $app->router("/furlough", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $common, $stores, $accStore) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Nghỉ phép");
            $vars['personnels'] = $app->select("personnels", ["id(value)", "name(text)"], ["deleted" => 0, "stores" => $accStore]);
            $vars['furloughs'] = $app->select("furlough_categorys", ["id(value)", "name(text)"], ["deleted" => 0, "status" => "A"]);
            $vars['status_options'] = array_map(function ($key, $item) {
                return ['value' => $key, 'text' => $item['name']];
            }, array_keys($common['leave_request_status']), $common['leave_request_status']);
            echo $app->render($template . '/hrm/furlough.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'hrm_leave_requests.id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $profile_filter = isset($_POST['profile']) ? $_POST['profile'] : '';
            $furlough_filter = isset($_POST['furlough']) ? $_POST['furlough'] : '';
            $status_filter = isset($_POST['status']) ? $_POST['status'] : '';
            $date_filter = isset($_POST['date']) ? $jatbi->parseDateRange($_POST['date']) : null;

            $join = [
                "[>]personnels" => ["profile_id" => "id"],
                "[>]furlough_categorys" => ["furlough_id" => "id"]
            ];
            $where = [
                "AND" => [
                    "hrm_leave_requests.deleted" => 0,
                    "furlough_categorys.code[!]" => "PT"
                ],
                "personnels.stores" => $accStore,
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)]
            ];
            if ($searchValue) {
                $where["AND"]["OR"] = ["personnels.name[~]" => $searchValue, "furlough_categorys.name[~]" => $searchValue];
            }
            if ($profile_filter) {
                $where["AND"]["hrm_leave_requests.profile_id"] = $profile_filter;
            }
            if ($furlough_filter) {
                $where["AND"]["hrm_leave_requests.furlough_id"] = $furlough_filter;
            }
            if ($status_filter) {
                $where["AND"]["hrm_leave_requests.status"] = $status_filter;
            }
            if ($date_filter) {
                $where["AND"]["hrm_leave_requests.id"] = $app->select("hrm_leave_request_details", "leave_request_id", [
                    "leave_date[<>]" => [$date_filter[0], $date_filter[1]]
                ]);
            }
            if (($app->getSession("accounts")['your_self'] ?? 0) == 1) {
                $where['AND']['personnels.id'] = $app->getSession("accounts")['personnels_id'];
            }

            $count = $app->count("hrm_leave_requests", $join, "hrm_leave_requests.id", ["AND" => $where['AND']]);
            $datas = [];

            $vars['profiles'] = $app->select("personnels", ["id(value)", "name(text)"], ["deleted" => 0]);
            $vars['furloughs'] = $app->select("furlough_categorys", ["id(value)", "name(text)"], ["deleted" => 0, "status" => "A"]);
            $vars['status_options'] = array_map(function ($key, $item) {
                return ['value' => $key, 'text' => $item['name']];
            }, array_keys($common['leave_request_status']), $common['leave_request_status']);

            $requests = $app->select("hrm_leave_requests", $join, [
                "hrm_leave_requests.id",
                "hrm_leave_requests.active",
                "hrm_leave_requests.total_days",
                "hrm_leave_requests.status",
                "personnels.name(profile_name)",
                "furlough_categorys.name(furlough_name)"
            ], $where);
            $request_ids = array_column($requests, 'id');
            $details = [];
            if (!empty($request_ids)) {
                $all_details = $app->select("hrm_leave_request_details", "*", ["leave_request_id" => $request_ids, "ORDER" => ["leave_date" => "ASC"]]);
                foreach ($all_details as $detail) {
                    $details[$detail['leave_request_id']][] = $detail;
                }
            }

            foreach ($requests as $data) {
                // $status_info = $common['leave_request_status'][$data['status']] ?? ['name' => 'N/A', 'color' => '#ccc'];

                $session_names = [
                    'full_day' => $jatbi->lang("Nguyên ngày"),
                    'morning' => $jatbi->lang("Buổi sáng"),
                    'afternoon' => $jatbi->lang("Buổi chiều"),
                ];

                $request_details = $details[$data['id']] ?? [];
                $first_day = $request_details[0] ?? null;
                $last_day = end($request_details) ?? null;

                $duration = 'N/A';
                if ($first_day && $last_day) {
                    // Lấy tên buổi nghỉ đã dịch
                    $start_session_name = $session_names[$first_day['leave_session']] ?? '';
                    $end_session_name = $session_names[$last_day['leave_session']] ?? '';

                    $start_session_str = ($first_day['leave_session'] !== 'full_day' && $start_session_name) ? " ({$start_session_name})" : '';
                    $end_session_str = ($last_day['leave_session'] !== 'full_day' && $end_session_name) ? " ({$end_session_name})" : '';

                    if ($first_day['leave_date'] == $last_day['leave_date']) {
                        // Trường hợp nghỉ trong 1 ngày
                        $duration = date('d/m/Y', strtotime($first_day['leave_date'])) . " ({$start_session_name})";
                    } else {
                        // Trường hợp nghỉ nhiều ngày
                        $duration = date('d/m/Y', strtotime($first_day['leave_date'])) . " ({$start_session_name}) - " . date('d/m/Y', strtotime($last_day['leave_date'])) . " ({$end_session_name})";
                    }
                }

                $action_buttons = [];
                // if ($data['status'] === 'pending') {
                //     $action_buttons[] = [
                //         'type' => 'button',
                //         'name' => $jatbi->lang("Xử lý"),
                //         'permission' => ['furlough.approve'],
                //         'action' => ['data-url' => '/hrm/leave-approve/' . $data['active'], 'data-action' => 'modal']
                //     ];
                // }
                $action_buttons[] = [
                    'type' => 'button',
                    'name' => $jatbi->lang("Sửa"),
                    'permission' => ['furlough.edit'],
                    'action' => ['data-url' => '/hrm/furlough-edit/' . $data['active'], 'data-action' => 'modal']
                ];
                $action_buttons[] = [
                    'type' => 'button',
                    'name' => $jatbi->lang("Xóa"),
                    'permission' => ['furlough.deleted'],
                    'action' => ['data-url' => '/hrm/furlough-deleted?box=' . $data['active'], 'data-action' => 'modal']
                ];
                $action_buttons[] = [
                    'type' => 'button',
                    'name' => $jatbi->lang("In"),
                    'permission' => ['furlough'],
                    'action' => ['data-url' => '/hrm/furlough-print/' . ($data['id'] ?? ''), 'data-action' => 'modal']
                ];

                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['active'], "class" => "checker"]),
                    "profile_name" => $data['profile_name'],
                    "furlough_name" => $data['furlough_name'],
                    "duration" => $duration,
                    "total_days" => $data['total_days'],
                    "status" => $app->component("clickable_approval", [
                        "url" => "/hrm/furlough-status/" . $data['active'],
                        "data" => $data['status'],
                        "permission" => ['furlough.approve']
                    ]),
                    "action" => $app->component("action", ["button" => $action_buttons]),
                ];
            }
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas ?? []
            ]);
        }
    })->setPermissions(['furlough']);

    $app->router('/furlough-print/{id}', 'GET', function ($vars) use ($app, $jatbi, $template) {
        $id = (int) ($vars['id'] ?? 0);
        $vars['title'] = $jatbi->lang("In Đơn nghỉ phép");

        $data = $app->get(
            "hrm_leave_requests",
            [
                "[>]personnels(p)" => ["profile_id" => "id"],
                "[>]offices(o)" => ["p.office" => "id"],
                "[>]furlough_categorys(fc)" => ["furlough_id" => "id"],
                "[>]personnels_contract(pc)" => ["profile_id" => "personnels"],
                "[>]hrm_positions(pos)" => ["pc.position" => "id"],
            ],
            [
                "hrm_leave_requests.id",
                "hrm_leave_requests.profile_id",
                "hrm_leave_requests.furlough_id(furlough)",
                "hrm_leave_requests.total_days(total_days_off)",
                "hrm_leave_requests.reason(notes)",
                "hrm_leave_requests.status",
                "p.name(personnel_name)",
                "o.name(office_name)",
                "fc.name(furlough_category_name)",
                "pos.name(position)"
            ],
            [
                "hrm_leave_requests.id" => $id
            ]
        );
        if ($data) {
            $leave_dates = $app->select(
                "hrm_leave_request_details",
                ["min_date" => Medoo\Medoo::raw('MIN(leave_date)'), "max_date" => Medoo\Medoo::raw('MAX(leave_date)')],
                ["leave_request_id" => $data['id']]
            );

            $data['date_from'] = $leave_dates[0]['min_date'] ?? '';
            $data['date_to'] = $leave_dates[0]['max_date'] ?? '';
        }


        if (!$data) {
            return $app->render($template . '/error.html', $vars, $jatbi->ajax());
        }

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
        $app->header(['Content-Type' => 'application/json']);
        $data = $app->get("hrm_leave_requests", "*", ["active" => $vars['id'], "deleted" => 0]);

        if (!$data) {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
            return;
        }

        $status = "";
        if ($data['status'] === 'A') {
            $status = "D";
            $getfurloughCat = $app->get("furlough_categorys", ["type", "code"], ["id" => $data['furlough_id']]);
            if ($getfurloughCat && $getfurloughCat['code'] == 'NPN') {
                $year = date('Y', strtotime($data['date']));
                $profile_id = $data['profile_id'];
                $days_to_subtract = $data['total_days'];

                $annual = $app->get("annual_leave", ["days_used"], [
                    "year" => $year,
                    "profile_id" => $profile_id
                ]);

                if ($annual && isset($annual['days_used'])) {
                    $newDays = max(0, $annual['days_used'] - $days_to_subtract);
                    $app->update("annual_leave", [
                        "days_used" => $newDays
                    ], [
                        "year" => $year,
                        "profile_id" => $profile_id
                    ]);
                }
            }
        } elseif ($data['status'] === 'D') {
            $status = "A";
            $getfurloughCat = $app->get("furlough_categorys", ["type", "code"], ["id" => $data['furlough_id']]);
            if ($getfurloughCat && $getfurloughCat['code'] == 'NPN') {
                $year = date('Y', strtotime($data['date']));
                $profile_id = $data['profile_id'];
                $days_to_add = $data['total_days'];

                $existing = $app->get("annual_leave", "*", [
                    "year" => $year,
                    "profile_id" => $profile_id
                ]);

                if ($existing) {
                    $app->update("annual_leave", [
                        "days_used" => $existing['days_used'] + $days_to_add
                    ], [
                        "year" => $year,
                        "profile_id" => $profile_id
                    ]);
                } else {
                    $app->insert("annual_leave", [
                        "year" => $year,
                        "days_used" => $days_to_add,
                        "profile_id" => $profile_id,
                        "total_accrued" => 0,
                        "carried_over" => 0,
                        "account" => $app->getSession("accounts")['id'] ?? null,
                        "date" => date("Y-m-d H:i:s"),
                        "active" => $jatbi->active(32)
                    ]);
                }
            }
        }
        if ($status) {
            $app->update("hrm_leave_requests", ["status" => $status], ["id" => $data['id']]);
            $jatbi->logs('hrm', 'furlough-status', $data);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Trạng thái không hợp lệ")]);
        }
    })->setPermissions(['furlough.approve']);

    $app->router("/furlough-add", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $accStore) {
        $vars['title'] = $jatbi->lang("Tạo Đơn xin nghỉ phép");

        if ($app->method() === 'GET') {
            // --- Phần GET: Hiển thị form ---
            $vars['data'] = ['status' => 'pending', 'leave_session' => 'full_day'];
            if (($app->getSession("accounts")['your_self'] ?? 0) == 1) {
                $vars['profiles'] = $app->select("personnels", ["id(value)", "name(text)"], ["deleted" => 0, "id" => $app->getSession("accounts")['personnels_id']]);
            } else {
                $vars['profiles'] = $app->select("personnels", ["id(value)", "name(text)"], ["deleted" => 0, "stores" => $accStore]);
            }
            $vars['furloughs'] = $app->select("furlough_categorys", ["id(value)", "name(text)", "code"], ["deleted" => 0, "status" => "A"]);
            $vars['leave_details'] = [];
            echo $app->render($template . '/hrm/furlough-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            // --- Phần POST: Xử lý dữ liệu form ---
            $app->header(['Content-Type' => 'application/json']);

            // 1. Lấy dữ liệu và kiểm tra cơ bản
            $personnel_id = (int) ($_POST['profile_id']);
            $furlough_id = (int) ($_POST['furlough_id']);
            $leave_dates = $_POST['leave_date'];
            $leave_sessions = $_POST['leave_session'];
            $reason = $app->xss($_POST['reason'] ?? '');

            if (empty($personnel_id) || empty($furlough_id) || empty($leave_dates)) {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng điền đủ thông tin bắt buộc (Nhân viên, Loại phép, Ngày nghỉ)")]);
                return;
            }

            // 2. Tính toán tổng số ngày nghỉ và kiểm tra tính hợp lệ từng ngày
            $total_days = 0;
            $first_valid_date = null;
            $processed_dates = []; // Để lưu các ngày đã xử lý

            foreach ($leave_dates as $index => $date_str) {
                // Bỏ qua nếu ngày trống hoặc session không hợp lệ
                if (empty($date_str) || !isset($leave_sessions[$index])) {
                    continue;
                }
                $date_obj = date_create($date_str);
                if (!$date_obj)
                    continue;
                $date_formatted = date_format($date_obj, 'Y-m-d');

                // Lấy ngày hợp lệ đầu tiên
                if ($first_valid_date === null) {
                    $first_valid_date = $date_formatted;
                }

                $session = $leave_sessions[$index];
                $days_for_this_entry = ($session === 'full_day') ? 1.0 : 0.5;
                $total_days += $days_for_this_entry;

                // ---- THÊM KIỂM TRA CHO TỪNG NGÀY ----
                // a) Kiểm tra ngày lễ (bảng holiday)
                $is_holiday = $app->has("holiday", [
                    "AND" => [
                        "date_from[<=]" => $date_formatted,
                        "date_to[>=]" => $date_formatted,
                        "deleted" => 0,
                        "status" => "A"
                    ]
                ]);
                if ($is_holiday) {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Ngày {$date_str} là ngày lễ, không thể xin nghỉ.")]);
                    return;
                }

                // c) Kiểm tra trùng lặp đơn nghỉ phép khác (hrm_leave_requests & hrm_leave_request_details)
                $overlap_check = $app->count("hrm_leave_request_details", [
                    "[>]hrm_leave_requests" => ["leave_request_id" => "id"]
                ], "hrm_leave_request_details.id", [
                    "AND" => [
                        "hrm_leave_requests.profile_id" => $personnel_id,
                        "hrm_leave_requests.deleted" => 0,
                        "hrm_leave_requests.status" => ['A'], // Chỉ kiểm tra đơn đang chờ hoặc đã duyệt
                        "hrm_leave_request_details.leave_date" => $date_formatted,
                        "OR" => [
                            "hrm_leave_request_details.leave_session" => "full_day", // Nếu đã có đơn cả ngày
                            "leave_session" => "full_day",                      // Hoặc nếu đang xin cả ngày
                            "hrm_leave_request_details.leave_session" => $session // Hoặc nếu trùng buổi
                        ]
                    ]
                ]);

                if ($overlap_check > 0) {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Ngày/Buổi {$date_str} đã tồn tại trong một đơn nghỉ phép khác.")]);
                    return;
                }
                // ---- KẾT THÚC KIỂM TRA TỪNG NGÀY ----

                $processed_dates[$index] = ['date' => $date_formatted, 'session' => $session]; // Lưu lại ngày đã xử lý
            }

            // 3. Kiểm tra lại nếu không có ngày hợp lệ nào
            if ($total_days <= 0 || $first_valid_date === null) {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng chọn ít nhất một ngày nghỉ hợp lệ")]);
                return;
            }

            // 4. KIỂM TRA SỐ DƯ (NẾU LÀ PHÉP NĂM)
            // Lấy thông tin loại phép đã chọn
            $furlough_info = $app->get("furlough_categorys", ["id", "code", "amount", "duration"], ["id" => $furlough_id]);

            if ($furlough_info && $furlough_info['code'] === 'NPN') {
                $year = date('Y', strtotime($first_valid_date));
                $balance = $app->get("annual_leave", ["total_accrued", "carried_over"], [
                    "profile_id" => $personnel_id,
                    "year" => $year,
                    "deleted" => 0
                ]);

                $remaining_days = ($balance['total_accrued'] ?? 0) + ($balance['carried_over'] ?? 0);

                if ($total_days > $remaining_days) {
                    echo json_encode([
                        "status" => "error",
                        "content" => $jatbi->lang("Số ngày Phép Năm yêu cầu ({$total_days}) vượt quá số phép còn lại ({$remaining_days}) trong năm {$year}")
                    ]);
                    return;
                }
            }

            if ($furlough_info && $furlough_info['amount'] > 0 && in_array($furlough_info['duration'], [4, 5])) {
                $amount = (float) $furlough_info['amount'];
                $duration = $furlough_info['duration']; // 4=tháng, 5=năm

                $year = date('Y', strtotime($first_valid_date));
                $month = date('m', strtotime($first_valid_date));

                // DÙNG NAMED PARAMETER → KHÔNG LỖI bindValue
                $sql = "
                    SELECT COALESCE(SUM(
                        CASE WHEN d.leave_session = 'full_day' THEN 1.0 ELSE 0.5 END
                    ), 0) AS used_days
                    FROM hrm_leave_request_details d
                    INNER JOIN hrm_leave_requests r ON d.leave_request_id = r.id
                    WHERE r.profile_id = :profile_id
                    AND r.furlough_id = :furlough_id
                    AND r.deleted = 0
                    AND r.status != 'D'
                    AND " . ($duration == 5
                    ? "YEAR(d.leave_date) = :year"
                    : "YEAR(d.leave_date) = :year AND MONTH(d.leave_date) = :month"
                );

                $params = [
                    ':profile_id' => $personnel_id,
                    ':furlough_id' => $furlough_id,
                ];

                if ($duration == 5) {
                    $params[':year'] = $year;
                } else {
                    $params[':year'] = $year;
                    $params[':month'] = $month;
                }

                try {
                    $stmt = $app->query($sql, $params);
                    $row = $stmt->fetch();
                    $used_days = (float) ($row['used_days'] ?? 0);
                } catch (Exception $e) {
                    $used_days = 0;
                }

                if (($used_days + $total_days) > $amount) {
                    $cycle_text = $duration == 5 ? "năm {$year}" : "tháng {$month}/{$year}";
                    echo json_encode([
                        "status" => "error",
                        "content" => $jatbi->lang("Loại phép này chỉ được nghỉ tối đa {$amount} ngày/{$cycle_text}. Đã dùng: {$used_days}, yêu cầu thêm: {$total_days}")
                    ]);
                    return;
                }
            }


            // 5. Lưu vào CSDL (Transaction)
            $app->action(function () use ($app, $jatbi, $personnel_id, $furlough_id, $total_days, $reason, $processed_dates) {
                // Bảng hrm_leave_requests
                $request_data = [
                    "active" => $jatbi->active(),
                    "profile_id" => $personnel_id,
                    "furlough_id" => $furlough_id,
                    "total_days" => $total_days,
                    "reason" => $reason,
                    "status" => 'D',
                    "account" => $app->getSession("accounts")['id'] ?? 0,
                    "date" => date("Y-m-d H:i:s"),
                ];
                $app->insert("hrm_leave_requests", $request_data);
                $leave_request_id = $app->id();

                // Bảng hrm_leave_request_details (Dùng $processed_dates)
                foreach ($processed_dates as $detail) {
                    $app->insert("hrm_leave_request_details", [
                        "leave_request_id" => $leave_request_id,
                        "leave_date" => $detail['date'],
                        "leave_session" => $detail['session']
                    ]);
                }
                // $jatbi->logs('leave_request', 'add', $request_data);
            });

            // 6. Trả về thành công
            echo json_encode([
                'status' => 'success',
                'content' => $jatbi->lang("Tạo đơn thành công"),
                'reload' => true
            ]);
        }
    })->setPermissions(['furlough.add']);

    $app->router("/furlough-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template, $accStore) {
        $request_id = $vars['id'];
        $vars['title'] = $jatbi->lang("Chỉnh sửa đơn xin nghỉ phép");

        // Lấy đơn nghỉ phép
        $request = $app->get("hrm_leave_requests", "*", [
            "active" => $request_id,
            "deleted" => 0
        ]);


        if (!$request) {
            $vars['modalContent'] = $jatbi->lang("Không tìm thấy dữ liệu");
            echo $app->render($setting['template'] . '/common/reward.html', $vars, $jatbi->ajax());
            return;
        }

        if ($request['status'] === 'A') {
            $vars['modalContent'] = $jatbi->lang("Đơn nghỉ phép đã được duyệt hoặc từ chối và không thể chỉnh sửa.");
            echo $app->render($setting['template'] . '/common/reward.html', $vars, $jatbi->ajax());
            return;
        }

        $account_id = $app->getSession("accounts")['id'] ?? 0;
        $is_self = ($app->getSession("accounts")['your_self'] ?? 0) == 1;
        $personnel_id = $request['profile_id'];

        if ($app->method() === 'GET') {
            $vars['data'] = $request;
            // Chi tiết ngày nghỉ
            $vars['leave_details'] = $app->select("hrm_leave_request_details", [
                "leave_date",
                "leave_session"
            ], [
                "leave_request_id" => $request['id'],
                "ORDER" => ["leave_date" => "ASC"]
            ]);
            // Danh sách nhân viên
            if ($is_self) {
                $vars['profiles'] = $app->select("personnels", ["id(value)", "name(text)"], [
                    "deleted" => 0,
                    "id" => $app->getSession("accounts")['personnels_id']
                ]);
            } else {
                $vars['profiles'] = $app->select("personnels", ["id(value)", "name(text)"], [
                    "deleted" => 0,
                    "stores" => $accStore
                ]);
            }

            // Loại phép
            $vars['furloughs'] = $app->select("furlough_categorys", ["id(value)", "name(text)", "code"], [
                "deleted" => 0,
                "status" => "A"
            ]);


            echo $app->render($template . '/hrm/furlough-post.html', $vars, $jatbi->ajax());
            return;
        }

        // ==================== POST: CẬP NHẬT ĐƠN ====================
        if ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            $personnel_id = (int) ($_POST['profile_id']);
            $furlough_id = (int) ($_POST['furlough_id']);
            $leave_dates = $_POST['leave_date'] ?? [];
            $leave_sessions = $_POST['leave_session'] ?? [];
            $reason = $app->xss($_POST['reason'] ?? '');

            if (empty($personnel_id) || empty($furlough_id) || empty($leave_dates)) {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng điền đủ thông tin bắt buộc.")]);
                return;
            }

            // === 1. KIỂM TRA TỪNG NGÀY ===
            $total_days = 0;
            $first_valid_date = null;
            $processed_dates = [];

            foreach ($leave_dates as $index => $date_str) {
                if (empty($date_str) || !isset($leave_sessions[$index]))
                    continue;

                $date_obj = date_create($date_str);
                if (!$date_obj)
                    continue;
                $date_formatted = date_format($date_obj, 'Y-m-d');

                if ($first_valid_date === null)
                    $first_valid_date = $date_formatted;

                $session = $leave_sessions[$index];
                $days = ($session === 'full_day') ? 1.0 : 0.5;
                $total_days += $days;

                // Kiểm tra ngày lễ
                $is_holiday = $app->has("holiday", [
                    "AND" => [
                        "date_from[<=]" => $date_formatted,
                        "date_to[>=]" => $date_formatted,
                        "deleted" => 0,
                        "status" => "A"
                    ]
                ]);
                if ($is_holiday) {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Ngày {$date_str} là ngày lễ.")]);
                    return;
                }

                // Kiểm tra trùng đơn khác (ngoại trừ chính đơn này)
                $overlap = $app->count("hrm_leave_request_details", [
                    "[>]hrm_leave_requests" => ["leave_request_id" => "id"]
                ], "hrm_leave_request_details.id", [
                    "AND" => [
                        "hrm_leave_requests.profile_id" => $personnel_id,
                        "hrm_leave_requests.deleted" => 0,
                        "hrm_leave_requests.status" => ['A', 'P'],
                        "hrm_leave_requests.id[!]" => $request_id, // Bỏ qua đơn hiện tại
                        "hrm_leave_request_details.leave_date" => $date_formatted,
                        "OR" => [
                            "hrm_leave_request_details.leave_session" => "full_day",
                            "leave_session" => "full_day",
                            "hrm_leave_request_details.leave_session" => $session
                        ]
                    ]
                ]);

                if ($overlap > 0) {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Ngày/Buổi {$date_str} đã tồn tại trong đơn khác.")]);
                    return;
                }

                $processed_dates[] = ['date' => $date_formatted, 'session' => $session];
            }

            if ($total_days <= 0) {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng chọn ít nhất một ngày nghỉ.")]);
                return;
            }

            // === 2. KIỂM TRA SỐ DƯ PHÉP NĂM (NPN) ===
            $furlough_info = $app->get("furlough_categorys", ["code", "amount", "duration"], ["id" => $furlough_id]);
            if ($furlough_info && $furlough_info['code'] === 'NPN') {
                $year = date('Y', strtotime($first_valid_date));
                $balance = $app->get("annual_leave", ["total_accrued", "carried_over"], [
                    "profile_id" => $personnel_id,
                    "year" => $year,
                    "deleted" => 0
                ]);
                $remaining = ($balance['total_accrued'] ?? 0) + ($balance['carried_over'] ?? 0);

                // Trừ đi số ngày cũ của đơn này
                $old_total = (float) $request['total_days'];
                $net_increase = $total_days - $old_total;

                if ($net_increase > $remaining) {
                    echo json_encode([
                        "status" => "error",
                        "content" => $jatbi->lang("Số ngày phép năm vượt quá số dư ({$remaining} ngày).")
                    ]);
                    return;
                }
            }

            // === 3. KIỂM TRA GIỚI HẠN THEO LOẠI PHÉP ===
            if ($furlough_info && $furlough_info['amount'] > 0 && in_array($furlough_info['duration'], [4, 5])) {
                $amount = (float) $furlough_info['amount'];
                $duration = $furlough_info['duration'];
                $year = date('Y', strtotime($first_valid_date));
                $month = date('m', strtotime($first_valid_date));

                $sql = "
                SELECT COALESCE(SUM(
                    CASE WHEN d.leave_session = 'full_day' THEN 1.0 ELSE 0.5 END
                ), 0) AS used_days
                FROM hrm_leave_request_details d
                INNER JOIN hrm_leave_requests r ON d.leave_request_id = r.id
                WHERE r.profile_id = :profile_id
                  AND r.furlough_id = :furlough_id
                  AND r.deleted = 0
                  AND r.status != 'D'
                  AND r.id != :request_id
                  AND " . ($duration == 5 ? "YEAR(d.leave_date) = :year" : "YEAR(d.leave_date) = :year AND MONTH(d.leave_date) = :month");

                $params = [
                    ':profile_id' => $personnel_id,
                    ':furlough_id' => $furlough_id,
                    ':request_id' => $request_id
                ];
                if ($duration == 5) {
                    $params[':year'] = $year;
                } else {
                    $params[':year'] = $year;
                    $params[':month'] = $month;
                }

                $stmt = $app->query($sql, $params);
                $used_days = (float) ($stmt->fetch()['used_days'] ?? 0);
                $old_days = (float) $request['total_days'];
                $new_total = $used_days + $total_days;

                if ($new_total > $amount) {
                    $cycle = $duration == 5 ? "năm {$year}" : "tháng {$month}/{$year}";
                    echo json_encode([
                        "status" => "error",
                        "content" => $jatbi->lang("Vượt giới hạn phép: tối đa {$amount} ngày/{$cycle}. Đã dùng: {$used_days}, thêm: {$total_days}")
                    ]);
                    return;
                }
            }

            // === 4. CẬP NHẬT CSDL (TRANSACTION) ===
            $app->action(function () use ($app, $request_id, $personnel_id, $furlough_id, $total_days, $reason, $processed_dates) {
                // Cập nhật đơn chính
                $app->update("hrm_leave_requests", [
                    "profile_id" => $personnel_id,
                    "furlough_id" => $furlough_id,
                    "total_days" => $total_days,
                    "reason" => $reason,
                    "status" => 'D',
                    "account" => $app->getSession("accounts")['id'] ?? 0
                ], ["id" => $request_id]);

                // Xóa chi tiết cũ
                $app->delete("hrm_leave_request_details", ["leave_request_id" => $request_id]);

                // Thêm chi tiết mới
                foreach ($processed_dates as $detail) {
                    $app->insert("hrm_leave_request_details", [
                        "leave_request_id" => $request_id,
                        "leave_date" => $detail['date'],
                        "leave_session" => $detail['session']
                    ]);
                }
            });

            // === 5. TRẢ KẾT QUẢ ===
            echo json_encode([
                'status' => 'success',
                'content' => $jatbi->lang("Cập nhật đơn thành công"),
                'reload' => true
            ]);
        }
    })->setPermissions(['furlough.edit']);

    $app->router("/furlough-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            // Lấy danh sách ID cần xóa
            $boxid = array_filter(explode(',', $app->xss($_GET['box'] ?? '')));
            if (empty($boxid)) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Không có dữ liệu để xóa")]);
                return;
            }
            $datas = $app->select("hrm_leave_requests", "*", [
                "active" => $boxid,
            ]);

            if (empty($datas)) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Không tìm thấy dữ liệu")]);
                return;
            }

            $updatedAnnual = false;

            foreach ($datas as $data) {
                // Bước 1: Xóa mềm đơn nghỉ phép (luôn thực hiện)
                $app->update("hrm_leave_requests", ["deleted" => 1], ["id" => $data['id']]);

                // Bước 2: Kiểm tra loại nghỉ phép có phải NPN không
                $isNPN = false;
                $cat = $app->get("furlough_categorys", ["code"], ["id" => $data['furlough_id']]);
                if ($cat && $cat['code'] === 'NPN') {
                    $isNPN = true;
                }

                // Bước 3: Chỉ xử lý annual_leave nếu: NPN + đã duyệt (A)
                if ($isNPN && $data['status'] === 'A') {
                    $year = date('Y', strtotime($data['date']));
                    $profile_id = $data['profile_id'];
                    $days_to_subtract = $data['total_days'];

                    // Lấy days_used dưới dạng mảng → tránh lỗi offset
                    $annual = $app->get("annual_leave", ["days_used"], [
                        "year" => $year,
                        "profile_id" => $profile_id
                    ]);

                    if ($annual && isset($annual['days_used'])) {
                        $newDays = max(0, $annual['days_used'] - $days_to_subtract);
                        $app->update("annual_leave", [
                            "days_used" => $newDays
                        ], [
                            "year" => $year,
                            "profile_id" => $profile_id
                        ]);
                        $updatedAnnual = true;
                    }
                }
            }

            $jatbi->logs('hrm', 'furlough-deleted', $datas);

            $msg = $jatbi->lang("Cập nhật thành công");
            if ($updatedAnnual) {
                $msg .= " " . $jatbi->lang("(Đã cập nhật lại ngày phép năm)");
            }
            echo json_encode([
                'status' => 'success',
                'content' => $msg
            ]);
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
                    // "office_name" => $data['office_name'],
                    "date_from" => $data['date_from'],
                    "date_to" => $data['date_to'],
                    "name" => $data['name'],
                    "total_days" => 1,
                    "notes" => $data['notes'],
                    "salary" => "x" . $data['salary'],
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
            // $salary = trim($app->xss($_POST['salary']));
            $insert = [
                "name" => $app->xss($_POST['name']),
                "date_from" => $app->xss($_POST['date_from']),
                "date_to" => $app->xss($_POST['date_to']),
                "salary" => $app->xss($_POST['salary']),
                // "office" => $app->xss($_POST['office']),
                // "notes" => $app->xss($_POST['notes']),
                "status" => 'A',
                "date" => date("Y-m-d H:i:s"),
                "user" => $app->getSession("accounts")['id'] ?? null,
            ];
            $app->insert("holiday", $insert);
            $jatbi->logs('hrm', 'holiday-add', [$insert]);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
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
                        // "office" => $app->xss($_POST['office']),
                        // "notes" => $app->xss($_POST['notes']),
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
            $months = [];
            $current_year = date('Y');
            $current_month = date('m');

            for ($i = -6; $i <= 6; $i++) {
                $date = strtotime("$current_year-$current_month-01 $i month");
                $y = date('Y', $date);
                $m = date('m', $date);
                $value = "$y-$m";
                $text = "Tháng " . date('m/Y', $date);
                $months[] = ['value' => $value, 'text' => $text];
            }
            $vars['months'] = $months;
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
            $month_filter = isset($_POST['months']) ? $_POST['months'] : '';

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
                    "reward_discipline.deleted" => 0,
                    "personnels.stores" => $accStore,
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
            if ($month_filter && strlen($month_filter) === 7) {
                $where["AND"]["reward_discipline.date[~]"] = $month_filter . "%";
            }
            if (($app->getSession("accounts")['your_self'] ?? 0) == 1) {
                $where['AND']['personnels.id'] = $app->getSession("accounts")['personnels_id'];
            }

            $count = $app->count("reward_discipline", $joins, "reward_discipline.id", $where['AND']);
            $datas = [];

            $app->select("reward_discipline", $joins, [
                'reward_discipline.id',
                'reward_discipline.type',
                'reward_discipline.price',
                'reward_discipline.date',
                'reward_discipline.content',
                'reward_discipline.percent',
                'reward_discipline.date_poster',
                'accounts.name(user_name)',
                'personnels.name(personnel_name)',
            ], $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "type" => $data['type'] == 1 ? "Khen thưởng" : "Kỷ luật",
                    "personnel_name" => $data['personnel_name'],
                    "price" => (float)$data['percent'] > 0 ? $data['percent'] . " %" : number_format($data['price']),
                    "date" => date('d/m/Y', strtotime($data['date'])) ?? "",
                    "content" => $data['content'],
                    "date_poster" => $data['date_poster'],
                    "user" => $data['user_name'],
                    "action" => $app->component("action", [
                        "button" => array_merge(
                            $data['type'] != 1 ? [
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Xem"),
                                    'permission' => ['reward-discipline'],
                                    'action' => ['data-url' => '/hrm/discipline-views/' . ($data['id'] ?? ''), 'data-action' => 'modal']
                                ]
                            ] : [],
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

    $app->router('/reward-excel', 'GET', function ($vars) use ($app, $jatbi, $accStore) {
        try {
            $searchValue = $_GET['search']['value'] ?? '';
            $personnels = $_GET['personnels'] ?? '';
            $month_filter = $_GET['months'] ?? '';

            $joins = [
                "[>]personnels" => ["personnels" => "id"],
                "[>]accounts" => ["reward_discipline.user" => "id"],
                "[>]offices" => ["personnels.office" => "id"],
            ];

            $where = [
                "AND" => [
                    "OR" => [
                        "personnels.name[~]" => $searchValue,
                        "reward_discipline.price[~]" => $searchValue,
                        "reward_discipline.content[~]" => $searchValue,
                    ],
                    "reward_discipline.deleted" => 0,
                    "personnels.stores" => $accStore,
                    "reward_discipline.type" => 1,
                ],
                "ORDER" => ["reward_discipline.id" => "DESC"],
            ];
            $title_month = '';
            $file_month = '';

            // Nếu có lọc tháng (dạng YYYY-MM)
            if (!empty($month_filter) && preg_match('/^\d{4}-\d{2}$/', $month_filter)) {
                $date_obj = DateTime::createFromFormat('Y-m', $month_filter);
                $title_month = ' THÁNG ' . $date_obj->format('m.Y');
                $file_month = '_' . $date_obj->format('m_Y');
            } else {
                // Không có lọc → chỉ ghi "DANH SÁCH THƯỞNG"
                $title_month = '';
                $file_month = '';
            }
            if (!empty($personnels)) {
                $where['AND']['personnels.id'] = $personnels;
            }
            if ($month_filter && strlen($month_filter) === 7) {
                $where["AND"]["reward_discipline.date[~]"] = $month_filter . "%";
            }
            if (($app->getSession("accounts")['your_self'] ?? 0) == 1) {
                $where['AND']['personnels.id'] = $app->getSession("accounts")['personnels_id'];
            }

            $columns = [
                'personnels.name(personnel_name)',
                'offices.name(department_name)',
                'reward_discipline.content',
                'reward_discipline.price',
                'reward_discipline.percent',
                'reward_discipline.notes',
            ];

            $report_data = $app->select("reward_discipline", $joins, $columns, $where);

            // --- TẠO FILE EXCEL ---
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('THƯỞNG ');

            // === TIÊU ĐỀ CHÍNH ===
            $sheet->mergeCells('A1:F1');
            $sheet->setCellValue('A1', 'DANH SÁCH THƯỞNG' . $title_month);
            $sheet->getStyle('A1')->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 16,
                    'name' => 'Arial',
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFD9EAD3'],
                ],
            ]);
            $sheet->getRowDimension(1)->setRowHeight(35);

            // === TÊN SHEET ===
            $sheet->setTitle('THƯỞNG' . ($file_month ?: ''));

            // === HEADER BẢNG (DÒNG 2) ===
            $headers = ['Stt', 'Họ và tên', 'Bộ phận', 'Lý do khen thưởng', 'Mức thưởng', 'Ghi chú'];
            $sheet->fromArray($headers, null, 'A2');
            $headerStyle = [
                'font' => [
                    'bold' => true,
                    'size' => 11,
                    'color' => ['argb' => 'FF000000'],
                    'name' => 'Arial',
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFD9EAD3'],
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000'],
                    ],
                ],
            ];
            $sheet->getStyle('A2:F2')->applyFromArray($headerStyle);
            $sheet->getRowDimension(2)->setRowHeight(25);

            // === DỮ LIỆU ===
            $row_index = 3;
            foreach ($report_data as $index => $data) {
                $price_display = $data['percent'] > 0 ? $data['percent'] . ' %' : number_format((float)$data['price'], 0, ',', '.');

                $sheet->setCellValue('A' . $row_index, $index + 1);
                $sheet->setCellValue('B' . $row_index, $data['personnel_name']);
                $sheet->setCellValue('C' . $row_index, $data['department_name'] ?? '');
                $sheet->setCellValue('D' . $row_index, $data['content']);
                $sheet->setCellValue('E' . $row_index, $price_display);
                $sheet->setCellValue('F' . $row_index, $data['notes'] ?? '');

                // Căn giữa cột STT, Mức thưởng
                $sheet->getStyle('A' . $row_index . ':A' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('E' . $row_index . ':E' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Viền toàn bộ ô
                $sheet->getStyle("A$row_index:F$row_index")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ],
                ]);

                $row_index++;
            }

            // Nếu không có dữ liệu → thêm dòng trống
            if (empty($report_data)) {
                for ($i = 0; $i < 6; $i++) {
                    $empty_row = $row_index++;
                    $sheet->mergeCells("A$empty_row:F$empty_row");
                    $sheet->getStyle("A$empty_row:F$empty_row")->applyFromArray([
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                        ],
                    ]);
                }
            }

            // === ĐỊNH DẠNG CỘT - KÍCH THƯỚC CỐ ĐỊNH NHƯ EXCEL GỐC ===
            $sheet->getColumnDimension('A')->setWidth(6);   // STT
            $sheet->getColumnDimension('B')->setWidth(25);  // Họ tên
            $sheet->getColumnDimension('C')->setWidth(15);  // Bộ phận
            $sheet->getColumnDimension('D')->setWidth(30);  // Lý do
            $sheet->getColumnDimension('E')->setWidth(15);  // Mức thưởng
            $sheet->getColumnDimension('F')->setWidth(20);  // Ghi chú

            // === PHẦN CHỮ KÝ - GIỐNG HỆT EXCEL ===
            $sig_start = $row_index + 2;

            // Dòng 1: BAN GIÁM ĐỐC | HCNS
            $sheet->mergeCells("A$sig_start:C$sig_start");
            $sheet->setCellValue("A$sig_start", 'BAN GIÁM ĐỐC');
            $sheet->getStyle("A$sig_start")->getFont()->setBold(true)->setSize(11);
            $sheet->getStyle("A$sig_start")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->mergeCells("D$sig_start:F$sig_start");
            $sheet->setCellValue("D$sig_start", 'HCNS');
            $sheet->getStyle("D$sig_start")->getFont()->setBold(true)->setSize(11);
            $sheet->getStyle("D$sig_start")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Dòng tên (cách 4 dòng)
            $name_row = $sig_start + 4;
            $sheet->mergeCells("D$name_row:F$name_row");
            $sheet->setCellValue("D$name_row", 'Lê Thị Phương');
            $sheet->getStyle("D$name_row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Dòng trống giữa
            for ($i = 1; $i <= 3; $i++) {
                $empty_sig = $sig_start + $i;
                $sheet->getRowDimension($empty_sig)->setRowHeight(20);
            }

            // === XUẤT FILE ===
            ob_end_clean();
            $file_name = 'DANH_SACH_THUONG' . $file_month . '.xlsx'; // Ví dụ: DANH_SACH_THUONG_02_2025.xlsx
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
    })->setPermissions(['reward-discipline']);

    $app->router('/discipline-excel', 'GET', function ($vars) use ($app, $jatbi, $accStore) {
        try {
            $searchValue = $_GET['search']['value'] ?? '';
            $personnels = $_GET['personnels'] ?? '';
            $month_filter = $_GET['months'] ?? '';

            $joins = [
                "[>]personnels" => ["personnels" => "id"],
                "[>]accounts" => ["reward_discipline.user" => "id"],
                "[>]offices" => ["personnels.office" => "id"],
            ];

            $where = [
                "AND" => [
                    "OR" => [
                        "personnels.name[~]" => $searchValue,
                        "reward_discipline.price[~]" => $searchValue,
                        "reward_discipline.content[~]" => $searchValue,
                    ],
                    "reward_discipline.deleted" => 0,
                    "personnels.stores" => $accStore,
                    "reward_discipline.type" => 2,
                ],
                "ORDER" => ["reward_discipline.id" => "DESC"],
            ];

            if (!empty($personnels)) {
                $where['AND']['personnels.id'] = $personnels;
            }
            if (!empty($month_filter) && preg_match('/^\d{4}-\d{2}$/', $month_filter)) {
                $where["AND"]["reward_discipline.date[~]"] = $month_filter . "%";
            }
            if (($app->getSession("accounts")['your_self'] ?? 0) == 1) {
                $where['AND']['personnels.id'] = $app->getSession("accounts")['personnels_id'];
            }

            $columns = [
                'personnels.name(personnel_name)',
                'offices.name(department_name)',
                'reward_discipline.content',
                'reward_discipline.price',
                'reward_discipline.percent',
            ];

            $report_data = $app->select("reward_discipline", $joins, $columns, $where);

            $title_month = '';
            $file_month = '';
            $sheet_title = 'PHẠT';
            if (!empty($month_filter) && preg_match('/^\d{4}-\d{2}$/', $month_filter)) {
                $date_obj = DateTime::createFromFormat('Y-m', $month_filter);
                $title_month = ' THÁNG ' . $date_obj->format('m.Y');
                $file_month = '_' . $date_obj->format('m_Y');
                $sheet_title .= $file_month;
            }

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle($sheet_title);

            $sheet->mergeCells('A1:E1');
            $sheet->setCellValue('A1', 'DANH SÁCH PHẠT' . $title_month);
            $sheet->getStyle('A1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 16, 'name' => 'Arial'],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFD9EAD3'],
                ],
            ]);
            $sheet->getRowDimension(1)->setRowHeight(35);

            $headers = ['Stt', 'Họ và tên', 'Bộ phận', 'Lỗi vi phạm', 'Hình thức xử lý'];
            $sheet->fromArray($headers, null, 'A2');
            $headerStyle = [
                'font' => ['bold' => true, 'size' => 11, 'name' => 'Arial'],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFD9EAD3'],
                ],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                ],
            ];
            $sheet->getStyle('A2:E2')->applyFromArray($headerStyle);
            $sheet->getRowDimension(2)->setRowHeight(25);

            $row_index = 3;
            foreach ($report_data as $index => $data) {
                // $price_clean = preg_replace('/[^\d]/', '', (string)$data['price']);
                $penalty_display = $data['percent'] > 0
                    ? $data['percent'] . ' %'
                    : (number_format($data['price']) ?? '');

                $sheet->setCellValue('A' . $row_index, $index + 1);
                $sheet->setCellValue('B' . $row_index, $data['personnel_name']);
                $sheet->setCellValue('C' . $row_index, $data['department_name'] ?? '');
                $sheet->setCellValue('D' . $row_index, $data['content']);
                $sheet->setCellValue('E' . $row_index, $penalty_display);

                $sheet->getStyle('A' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A$row_index:E$row_index")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                $row_index++;
            }

            if (empty($report_data)) {
                for ($i = 0; $i < 6; $i++) {
                    $empty_row = $row_index++;
                    $sheet->mergeCells("A$empty_row:E$empty_row");
                    $sheet->getStyle("A$empty_row:E$empty_row")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                }
            }

            $sheet->getColumnDimension('A')->setWidth(6);
            $sheet->getColumnDimension('B')->setWidth(25);
            $sheet->getColumnDimension('C')->setWidth(15);
            $sheet->getColumnDimension('D')->setWidth(35);
            $sheet->getColumnDimension('E')->setWidth(18);

            $sig_start = $row_index + 2;
            $sheet->mergeCells("C$sig_start:E$sig_start");
            $sheet->setCellValue("C$sig_start", 'HCNS');
            $sheet->getStyle("C$sig_start")->getFont()->setBold(true)->setSize(11);
            $sheet->getStyle("C$sig_start")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $name_row = $sig_start + 4;
            $sheet->mergeCells("C$name_row:E$name_row");
            $sheet->setCellValue("C$name_row", 'Lê Thị Phương');
            $sheet->getStyle("C$name_row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            for ($i = 1; $i <= 3; $i++) {
                $sheet->getRowDimension($sig_start + $i)->setRowHeight(20);
            }

            ob_end_clean();
            $file_name = 'DANH_SACH_PHAT' . $file_month . '.xlsx';
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
                // 'personnels.office(department_name)',
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

            if ($app->xss($_POST['type']) == '' || $app->xss($_POST['personnels']) == '' || $app->xss($_POST['date']) == '' || $app->xss($_POST['value']) == '') {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
            } else {
                $value_raw = $app->xss($_POST['value']);
                $value_type = $app->xss($_POST['value_type']);
                $price = 0;
                $percent = 0;

                if ($value_type == 'money') {
                    $price = $app->xss(str_replace([','], '', $value_raw));
                } else {
                    $percent = $app->xss($value_raw);
                }

                $insert = [
                    "personnels" => $app->xss($_POST['personnels']),
                    "type" => $app->xss($_POST['type']),
                    "date" => $app->xss($_POST['date']),
                    "price" => $price,
                    "percent" => $percent,
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

                if ($app->xss($_POST['type']) == '' || $app->xss($_POST['personnels']) == '' || $app->xss($_POST['date']) == '' || $app->xss($_POST['value']) == '') {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
                } else {

                    $value_raw = $app->xss($_POST['value']);
                    $value_type = $app->xss($_POST['value_type']);

                    $price = 0;
                    $percent = 0;

                    if ($value_type == 'money') {
                        $price = $app->xss(str_replace([','], '', $value_raw));
                    } else {
                        $percent = $app->xss($value_raw);
                    }

                    $update = [
                        "personnels" => $app->xss($_POST['personnels']),
                        "type" => $app->xss($_POST['type']),
                        "date" => $app->xss($_POST['date']),

                        "price" => $price,
                        "percent" => $percent,

                        "content" => $app->xss($_POST['content']),
                        "notes" => $app->xss($_POST['notes']),
                        "date_poster" => date("Y-m-d H:i:s"),
                        "user" => $app->getSession("accounts")['id'] ?? null,
                    ];

                    $app->update("reward_discipline", $update, ["id" => $data['id']]);
                    $jatbi->logs('hrm', 'reward-discipline-edit', [$update]);
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
                    "type" => $data['type'] == 1 ? $jatbi->lang("Đi trễ") : $jatbi->lang("Về sớm"),
                    "name" => $data['name'],
                    "value" => $data['value'] . ' ' . $jatbi->lang("phút"),
                    // "price" => $data['price'],
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
            if ($app->xss($_POST['type']) == '' || $app->xss($_POST['name']) == '' || $app->xss($_POST['date']) == '' || $app->xss($_POST['value']) == '') {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
            } else {
                $insert = [
                    "name" => $app->xss($_POST['name']),
                    "type" => $app->xss($_POST['type']),
                    "date" => $app->xss($_POST['date']),
                    // "price" => $app->xss(str_replace([','], '', $_POST['price'])),
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
                if ($app->xss($_POST['type']) == '' || $app->xss($_POST['name']) == '' || $app->xss($_POST['date']) == '' || $app->xss($_POST['value']) == '') {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
                } else {
                    $insert = [
                        "name" => $app->xss($_POST['name']),
                        "type" => $app->xss($_POST['type']),
                        "date" => $app->xss($_POST['date']),
                        // "price" => $app->xss(str_replace([','], '', $_POST['price'])),
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
    })->setPermissions(['time-late.edit']);

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
    })->setPermissions(['time-late.deleted']);

    $app->router('/timekeeping-late', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template, $accStore) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Đi trễ / Về sớm");
            $vars['type'] = [
                ["value" => 1, "text" => $jatbi->lang("Đi trễ")],
                ["value" => 2, "text" => $jatbi->lang("Về sớm")],
            ];
            $vars['personnels'] = $app->select("personnels", ["id(value)", "name(text)"], ["deleted" => 0, "status" => "A"]);
            echo $app->render($template . '/hrm/timekeeping-late.html', $vars);
        }
        if ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? $app->xss($_POST['search']['value']) : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'ttl.id'; // Use alias
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

            $status_filter = isset($_POST['status']) && ctype_digit($_POST['status']) ? (int) $_POST['status'] : null;
            $filter_date = isset($_POST['date']) ? $_POST['date'] : '';


            $join = [
                "[>]personnels(p)" => ["personnels" => "id"]
            ];

            $where = [
                "AND" => [
                    "ttl.deleted" => 0,
                    "p.stores" => $accStore,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];
            if (!empty($searchValue)) {
                $where["AND"]["OR"] = [
                    "p.name[~]" => $searchValue,
                    "p.code[~]" => $searchValue
                ];
            }

            if ($status_filter !== null) {
                $where["AND"]["ttl.status"] = $status_filter;
            }
            if (($app->getSession("accounts")['your_self'] ?? 0) == 1) {
                $where['AND']['p.id'] = $app->getSession("accounts")['personnels_id'];
            }

            // Add filters for date range, specific personnel if needed from $_POST
            // Example:
            if (!empty($_POST['personnels'])) {
                $where["AND"]["ttl.personnels"] = (int) $_POST['personnels'];
            }

            if (!empty($_POST['type'])) {
                $where["AND"]["ttl.type"] = (int) $_POST['type'];
            }

            if (!empty($filter_date)) {
                $date_parts = explode(' - ', $filter_date);
                if (count($date_parts) == 2) {
                    $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date_parts[0]))));
                    $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date_parts[1]))));
                    $where['AND']["ttl.date[<>]"] = [$date_from, $date_to];
                }
            }

            $countWhere = ["AND" => $where['AND']];
            $count = $app->count("timekeeping_time_late(ttl)", $join, "ttl.id", $countWhere);

            $datas = [];

            // Select data with join
            $app->select("timekeeping_time_late(ttl)", $join, [
                'ttl.id',
                'ttl.type',
                'ttl.date',      // Datetime of the incident
                'ttl.time_late', // Minutes late/early
                'ttl.status',    // Integer status (0 or 1 assumed)
                'p.name(personnel_name)' // Personnel name from join
            ], $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "type" => $data['type'] == 1 ? $jatbi->lang("Đi trễ") : $jatbi->lang("Về sớm"),
                    "personnel_name" => $data['personnel_name'],
                    "date" => date('d/m/Y', strtotime($data['date'])), // Format datetime
                    "time_late" => $data['time_late'] . ' ' . $jatbi->lang("phút"),
                    "action" => ($app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['time-late.edit'],
                                'action' => ['data-url' => '/hrm/timekeeping-time-late-edit/' . ($data['id'] ?? ''), 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'permission' => ['time-late.deleted'],
                                'action' => ['data-url' => '/hrm/timekeeping-time-late-deleted?box=' . ($data['id'] ?? ''), 'data-action' => 'modal']
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

    $app->router('/timekeeping-time-late-edit/{id}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $setting) {
        $id = (int) $vars['id'];
        $vars['title'] = $jatbi->lang("Sửa Ghi Nhận Đi trễ/Về sớm");

        // Lấy dữ liệu hiện tại
        $data = $app->get("timekeeping_time_late", "*", ["id" => $id, "deleted" => 0]);

        if (!$data) {
            $vars['modalContent'] = $jatbi->lang("Không tìm thấy dữ liệu ghi nhận đi trễ/về sớm.");
            echo $app->render($setting['template'] . '/common/reward.html', $vars);
            return;
        }

        if ($app->method() === 'GET') {
            // --- Hiển thị form ---
            $vars['data'] = $data;
            // Lấy danh sách nhân viên (nếu cần cho phép đổi nhân viên, thường thì không nên)
            $vars['personnels'] = $app->select("personnels", ["id(value)", "name(text)"], ["deleted" => 0, "id" => $data['personnels'], "status" => "A", "ORDER" => ["name" => "ASC"]]);
            // Render form chỉnh sửa (cần tạo file này)
            echo $app->render($template . '/hrm/timekeeping-time-late-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            // --- Xử lý cập nhật ---
            $app->header(['Content-Type' => 'application/json']);
            $post = $app->request->getParsedBody(); // Lấy dữ liệu an toàn hơn

            if (empty($post['personnels']) || empty($post['date']) || empty($post['type']) || !isset($post['time_late']) || !ctype_digit((string) $post['time_late'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng điền đủ thông tin hợp lệ (Nhân viên, Ngày giờ, Loại, Số phút).")]);
                return;
            }

            // Chuẩn bị dữ liệu cập nhật
            $update_data = [
                "personnels" => (int) $post['personnels'],
                "type" => (int) $post['type'], // 1=Late, 2=Early
                "date" => date("Y-m-d", strtotime($post['date'])),
                "time_late" => (int) $post['time_late'], // Số phút
                // "status" => isset($post['status']) ? (int)$post['status'] : $data['status'], // Cập nhật status nếu có
                "notes" => $app->xss($post['notes'] ?? ''), // Thêm trường notes nếu có trong form
                "user" => $app->getSession("accounts")['id'] ?? 0, // Ghi nhận người sửa
                "date_poster" => date("Y-m-d H:i:s") // Ghi nhận thời gian sửa
            ];

            // Thực hiện cập nhật
            $app->update("timekeeping_time_late", $update_data, ["id" => $id]);

            // Ghi log
            $jatbi->logs('timekeeping_time_late', 'edit', ['id' => $id, 'changes' => $update_data]);

            echo json_encode([
                'status' => 'success',
                'content' => $jatbi->lang("Cập nhật thành công"),
                'reload' => true
            ]);
        }
    })->setPermissions(['time-late.edit']);

    $app->router("/timekeeping-time-late-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("timekeeping_time_late", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("timekeeping_time_late", ["deleted" => 1], ["id" => $data['id']]);
                }
                $jatbi->logs('hrm', 'timekeeping_time_late-deleted', $datas);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['time-late.deleted']);

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
                    "personnels.stores" => $accStore,
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
            if (($app->getSession("accounts")['your_self'] ?? 0) == 1) {
                $where['AND']['personnels.id'] = $app->getSession("accounts")['personnels_id'];
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

    $app->router('/timekeeping-excel', ['GET'], function ($vars) use ($app, $jatbi, $setting, $template, $accStore) {
        try {
            // --- Fetch Filter Parameters ---
            $year = $_GET['year'] ?? date('Y');
            $month = $_GET['month'] ?? date('m');
            $personnelF = $_GET['personnel'] ?? '';
            $officeF = $_GET['office'] ?? '';
            $stores = $_GET['stores'] ?? $accStore;

            $start_date_month = "$year-$month-01";
            $end_date_month = date("Y-m-t", strtotime($start_date_month));
            $totalDays = date("t", strtotime($start_date_month));

            // --- Filter Personnel ---
            $wherePersonnel = ["AND" => ["deleted" => 0, "status" => 'A', "stores" => $stores]];
            if (!empty($officeF))
                $wherePersonnel['AND']['office'] = $officeF;
            if (!empty($personnelF))
                $wherePersonnel['AND']['id'] = $personnelF;

            $personnel_list = $app->select("personnels", ["id", "code", "name", "office"], $wherePersonnel);
            if (empty($personnel_list)) {
                exit("Không có nhân viên nào thỏa mãn điều kiện lọc.");
            }
            $personnel_ids = array_column($personnel_list, 'id');

            // --- Pre-fetch Data ---
            $timekeeping_data = $app->select("timekeeping", ["personnels", "date", "checkin", "checkout"], ["personnels" => $personnel_ids, "date[>=]" => $start_date_month, "date[<=]" => $end_date_month]);
            $leaveRequestsAll = $app->select("hrm_leave_request_details", [
                "[>]hrm_leave_requests(lr)" => ["leave_request_id" => "id"],
                "[>]furlough_categorys(fc)" => ["lr.furlough_id" => "id"]
            ], [
                "lr.profile_id",
                "hrm_leave_request_details.leave_date",
                "hrm_leave_request_details.leave_session",
                "fc.code(category_code)",
                "fc.name(category_name)"
            ], [
                "lr.profile_id" => $personnel_ids,
                "hrm_leave_request_details.leave_date[>=]" => $start_date_month,
                "hrm_leave_request_details.leave_date[<=]" => $end_date_month,
                "lr.deleted" => 0,
                "lr.status" => 'A'
            ]);
            $rosters_data = $app->select("rosters", ["personnels", "timework", "date"], ["personnels" => $personnel_ids, "date[<=]" => $end_date_month, "deleted" => 0]);
            $timework_ids = array_unique(array_filter(array_column($rosters_data, 'timework')));
            $timework_details_data = [];
            if (!empty($timework_ids)) {
                $timework_details_data = $app->select("timework_details", ["timework", "week", "time_from", "time_to", "off"], ["timework" => $timework_ids, "deleted" => 0]);
            }
            $holidaysAll = $app->select("holiday", ["name", "date_from", "date_to", "salary"], [
                "date_from[<=]" => $end_date_month,
                "date_to[>=]" => $start_date_month,
                "deleted" => 0,
                "status" => 'A'
            ]);
            $overtime_data = $app->select(
                "hrm_overtime_requests",
                ["profile_id", "work_date", "total_hours", "multiplier"],
                [
                    "profile_id" => $personnel_ids,
                    "work_date[>=]" => $start_date_month,
                    "work_date[<=]" => $end_date_month,
                    "deleted" => 0,
                    "status" => 'approved'
                ]
            );
            $office_map = array_column($app->select("offices", ["id", "name"], ["deleted" => 0, "status" => "A"]), 'name', 'id');


            // --- Create Data Maps ---
            $timekeeping_map = [];
            foreach ($timekeeping_data as $tk)
                $timekeeping_map[$tk['personnels']][$tk['date']] = $tk;
            $leave_map = [];
            foreach ($leaveRequestsAll as $l)
                $leave_map[$l['profile_id']][$l['leave_date']][] = $l;
            $rosters_map = [];
            foreach ($rosters_data as $roster) {
                if (!isset($rosters_map[$roster['personnels']]) || strtotime($roster['date']) > strtotime($rosters_map[$roster['personnels']]['date'])) {
                    $rosters_map[$roster['personnels']] = $roster;
                }
            }
            $timework_details_map = [];
            foreach ($timework_details_data as $detail)
                $timework_details_map[$detail['timework']][$detail['week']] = $detail;

            $holidayMap = [];
            foreach ($holidaysAll as $h) {
                $start = strtotime($h['date_from']);
                $end = strtotime($h['date_to']);
                for ($d = $start; $d <= $end; $d += 86400) {
                    $currentDateStr = date("Y-m-d", $d);
                    if ($currentDateStr >= $start_date_month && $currentDateStr <= $end_date_month) {
                        $holidayMap[$currentDateStr] = [
                            'name' => $h['name'] ?? 'Lễ',
                            'rate' => (float) ($h['salary'] ?? 1.5)
                        ];
                    }
                }
            }
            $overtime_map = [];
            foreach ($overtime_data as $ot) {
                $currentDateStr = $ot['work_date'];
                if ($currentDateStr >= $start_date_month && $currentDateStr <= $end_date_month) {
                    $overtime_map[$ot['profile_id']][$currentDateStr] = ['approved_hours' => (float) ($ot['total_hours'] ?? 0), 'rate' => (float) ($ot['multiplier'] ?? 1.0)];
                }
            }

            // --- Process Data for Excel ---
            $excelData = [];
            $stt = 1;

            foreach ($personnel_list as $personnel) {
                $pid = $personnel['id'];
                $dailyData = [];
                $total_day_hours = $total_sunday_hours = $work_days = $late_count = $late_minutes = $early_count = $early_minutes = 0;
                $leave_days_count = 0;
                $total_counted_hours_effect = 0;
                $raw_overtime_hours = 0;

                $active_roster = $rosters_map[$pid] ?? null;
                $currentTimeworkId = $active_roster ? $active_roster['timework'] : null;

                foreach (range(1, $totalDays) as $day) {
                    $dayStr = str_pad($day, 2, '0', STR_PAD_LEFT);
                    $current_date_str = "$year-$month-$dayStr";
                    $current_date_ts = strtotime($current_date_str);
                    $week_day = date('N', $current_date_ts);
                    $value = '';
                    $workValue = null;
                    $actual_hours_for_cong = 0;
                    $day_ot_hours_effect = 0;
                    $day_raw_overtime_hours = 0;
                    $day_base_hours_effect = 0;

                    $schedule = $currentTimeworkId ? ($timework_details_map[$currentTimeworkId][$week_day] ?? null) : null;
                    $holidayInfo = $holidayMap[$current_date_str] ?? null;
                    $holidayName = $holidayInfo['name'] ?? null;
                    $holiday_rate = $holidayInfo['rate'] ?? 1;
                    $is_holiday = ($holidayName != null);
                    $leaveInfoArray = $leave_map[$pid][$current_date_str] ?? null;
                    $tk = $timekeeping_map[$pid][$current_date_str] ?? null;
                    $approved_overtime = $overtime_map[$pid][$current_date_str] ?? null;

                    $is_full_day_leave = false;
                    $day_leave_value = 0;
                    $leaveTextValue = '';

                    if ($leaveInfoArray) {
                        $leaveTextParts = [];
                        foreach ($leaveInfoArray as $leaveDetail) {
                            $sessionText = '';
                            if ($leaveDetail['leave_session'] === 'morning') {
                                $sessionText = ' (S)';
                                $day_leave_value += 0.5;
                            } elseif ($leaveDetail['leave_session'] === 'afternoon') {
                                $sessionText = ' (C)';
                                $day_leave_value += 0.5;
                            } else {
                                $is_full_day_leave = true;
                                $day_leave_value = 1.0;
                            }
                            $leaveTextParts[] = ($leaveDetail['category_code'] ?? $leaveDetail['category_name']) . $sessionText;
                        }
                        $leaveTextValue = implode(', ', $leaveTextParts);
                        if ($is_full_day_leave || $day_leave_value >= 1.0)
                            $is_full_day_leave = true;
                        $leave_days_count += $day_leave_value;
                    }

                    if (isset($tk) && !empty($tk['checkin']) && !empty($tk['checkout'])) {
                        $checkin_time = strtotime("$current_date_str {$tk['checkin']}");
                        $checkout_time = strtotime("$current_date_str {$tk['checkout']}");
                        $total_hours_worked = max(0, ($checkout_time - $checkin_time)) / 3600;
                        // $lunch_deduction_hours = ($total_hours_worked > 5) ? 1.5 : 0;
                        $net_hours_worked = max(0, $total_hours_worked /*- $lunch_deduction_hours*/);
                        $work_days++;

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

                            $effective_start = max($checkin_time, $expected_start);
                            $effective_end = min($checkout_time, $expected_end);
                            $duration_within_schedule_seconds = max(0, $effective_end - $effective_start);
                            $scheduled_hours = max(0, $expected_end - $expected_start) / 3600;
                            // $scheduled_lunch_deduction = ($scheduled_hours > 5) ? 1.5 : 0;
                            $actual_hours_for_cong = max(0, ($duration_within_schedule_seconds / 3600) /*- $scheduled_lunch_deduction*/);

                            $actual_ot_hours_worked = 0;
                            if ($checkin_time < $expected_start) {
                                $actual_ot_hours_worked += ($expected_start - $checkin_time) / 3600;
                            }
                            if ($checkout_time > $expected_end) {
                                $actual_ot_hours_worked += ($checkout_time - $expected_end) / 3600;
                            }

                            $base_rate = $is_holiday ? $holiday_rate : 1.0;
                            $day_base_hours_effect = $actual_hours_for_cong * $base_rate;

                            if ($actual_ot_hours_worked > 0) {
                                $ot_rate_from_request = $approved_overtime['rate'] ?? 1;
                                $final_ot_rate = $is_holiday ? ($holiday_rate * $ot_rate_from_request) : $ot_rate_from_request;

                                // *** LOGIC CORRECTION: Removed || $is_holiday ***
                                if ($approved_overtime) {
                                    $approved_hours = $approved_overtime['approved_hours'] ?? 0;
                                    $counted_ot_hours = min($actual_ot_hours_worked, $approved_hours);
                                    $day_ot_hours_effect = ($counted_ot_hours * $final_ot_rate);
                                    $day_raw_overtime_hours = $counted_ot_hours;
                                }
                            }

                            if ($is_holiday)
                                $day_raw_overtime_hours += $actual_hours_for_cong; // Add base holiday hours to raw total

                        } else {
                            // --- OFF DAY WORK ---
                            $actual_hours_for_cong = 0;
                            $workValue = 'K/P';
                            $actual_ot_hours_worked = $net_hours_worked;

                            if ($actual_ot_hours_worked > 0) {
                                $ot_rate_from_request = $approved_overtime['rate'] ?? 1;
                                $final_ot_rate = $is_holiday ? ($holiday_rate * $ot_rate_from_request) : $ot_rate_from_request;

                                // *** LOGIC CORRECTION: Removed || $is_holiday ***
                                if ($approved_overtime) {
                                    $approved_hours = $approved_overtime['approved_hours'] ?? 0;
                                    $counted_ot_hours = min($actual_ot_hours_worked, $approved_hours);
                                    $day_ot_hours_effect = ($counted_ot_hours * $final_ot_rate);
                                    $day_raw_overtime_hours = $counted_ot_hours;
                                    $workValue = round($day_ot_hours_effect / 8, 2);
                                }
                            }
                        }

                        if (!$is_holiday) {
                            if ($week_day == 7)
                                $total_sunday_hours += $actual_hours_for_cong;
                            else
                                $total_day_hours += $actual_hours_for_cong;
                        }
                        $raw_overtime_hours += $day_raw_overtime_hours;
                        $total_counted_hours_effect += $day_base_hours_effect + $day_ot_hours_effect;

                        if (!isset($workValue)) {
                            $base_cong_value = round($day_base_hours_effect / 8, 3);
                            $ot_cong_value = round($day_ot_hours_effect / 8, 3);
                            $workValue = $base_cong_value + $ot_cong_value;
                            if ($workValue == 0 && $net_hours_worked > 0) {
                                $workValue = 0;
                            }
                        }
                    } elseif (isset($tk)) {
                        $workValue = 'K/L';
                    } else {
                        if ($schedule && $schedule['off'] == 0 && $current_date_ts < time() && !$holidayName && !$leaveInfoArray) {
                            $workValue = 'V';
                        }
                    }

                    if ($holidayName) {
                        // Display holiday name + work value if it exists
                        $value = $holidayName . (isset($workValue) && $workValue > 0 ? " : " . $workValue : ($workValue === 0 ? " : 0" : ""));
                    } elseif ($is_full_day_leave) {
                        $value = $leaveTextValue;
                    } elseif (!empty($leaveTextValue)) {
                        $value = $leaveTextValue . (isset($workValue) && $workValue !== 'V' && $workValue !== 'K/L' && $workValue !== 'K/P' && ($day_base_hours_effect + $day_ot_hours_effect) > 0 ? " : " . $workValue : "");
                    } elseif ($schedule && $schedule['off'] == 1) {
                        $value = 'OFF';
                    } elseif (isset($workValue)) {
                        $value = $workValue;
                    } else {
                        $value = '';
                    }
                    $dailyData[$dayStr] = $value;
                } // End daily loop

                $excelData[] = [
                    'STT' => $stt++,
                    'MaNV' => $personnel['code'],
                    'HoTen' => $personnel['name'],
                    'BoPhan' => $office_map[$personnel['office']] ?? '',
                    'DailyData' => $dailyData,
                    'TongGio_Ngay' => round($total_day_hours, 2),
                    'TongGio_CN' => round($total_sunday_hours, 2),
                    'TongGio_TangCa' => round($raw_overtime_hours, 2),
                    'TongGio_Tong' => round($total_day_hours + $total_sunday_hours + $raw_overtime_hours, 2),
                    'CongLam_Ngay' => $work_days,
                    'CongLam_Gio' => round($total_counted_hours_effect / 8, 2),
                    'CongPhep' => $leave_days_count,
                    'DiMuon_Lan' => $late_count,
                    'DiMuon_Phut' => round($late_minutes),
                    'VeSom_Lan' => $early_count,
                    'VeSom_Phut' => round($early_minutes)
                ];
            } // End personnel loop

            // --- Generate Excel File ---
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle("BangCongThang_$month-$year");

            // Set Headers and Styles
            $sheet->setCellValue('A2', 'CÔNG TY TNHH MTV NGỌC TRAI NGỌC HIỀN PHÚ QUỐC')->mergeCells('A2:D2');
            $sheet->setCellValue('A3', 'BẢNG CHẤM CÔNG THÁNG ' . "$month/$year")->mergeCells('A3:F3');
            $sheet->getStyle('A2:A3')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $headers_row6 = ['STT', 'Mã NV', 'Họ và tên', 'Bộ phận'];
            $headers_row7 = ['', '', '', ''];
            $days_of_week_map = ['1' => 'T2', '2' => 'T3', '3' => 'T4', '4' => 'T5', '5' => 'T6', '6' => 'T7', '7' => 'CN'];
            foreach (range(1, $totalDays) as $day) {
                $headers_row6[] = str_pad($day, 2, '0', STR_PAD_LEFT);
                $week_day = date('N', strtotime("$year-$month-$day"));
                $headers_row7[] = $days_of_week_map[$week_day];
            }

            $summary_headers = [
                'Tổng giờ' => ['Ngày', 'CN', 'Tăng Ca', 'Tổng'],
                'Tổng công' => ['Ngày làm', 'Công giờ', 'Phép'],
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
            $startMergeCol = 5 + $totalDays;
            foreach ($summary_headers as $main => $subs) {
                $endColIndex = $startMergeCol + count($subs) - 1;
                $startColLetter = Coordinate::stringFromColumnIndex($startMergeCol);
                $endColLetter = Coordinate::stringFromColumnIndex($endColIndex);
                if ($startColLetter != $endColLetter) {
                    $sheet->mergeCells($startColLetter . '6:' . $endColLetter . '6');
                }
                $startMergeCol = $endColIndex + 1;
            }

            // Write Data Rows
            $rowIndex = 8;
            $colIndexBase = 1;
            foreach ($excelData as $data) {
                $colIndex = $colIndexBase;
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $data['STT']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $data['MaNV']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $data['HoTen']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $data['BoPhan']);
                foreach (range(1, $totalDays) as $day) {
                    $dayStr = str_pad($day, 2, '0', STR_PAD_LEFT);
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $data['DailyData'][$dayStr] ?? '');
                }
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $data['TongGio_Ngay']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $data['TongGio_CN']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $data['TongGio_TangCa']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $data['TongGio_Tong']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $data['CongLam_Ngay']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $data['CongLam_Gio']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $data['CongPhep']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $data['DiMuon_Lan']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $data['DiMuon_Phut']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $data['VeSom_Lan']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $data['VeSom_Phut']);
                $rowIndex++;
            }


            // Apply Styles and Column Widths
            $lastColLetter = Coordinate::stringFromColumnIndex($colIndex - 1);
            $sheet->getStyle('A6:' . $lastColLetter . ($rowIndex - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle('A6:' . $lastColLetter . '7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle('A6:' . $lastColLetter . '7')->getFont()->setBold(true);
            $sheet->getStyle('A8:' . $lastColLetter . ($rowIndex - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle('C8:D' . ($rowIndex - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

            $sheet->getColumnDimension('A')->setAutoSize(true);
            $sheet->getColumnDimension('B')->setAutoSize(true);
            $sheet->getColumnDimension('C')->setWidth(30);
            $sheet->getColumnDimension('D')->setAutoSize(true);
            for ($i = 1; $i <= $totalDays; $i++) {
                $colLetter = Coordinate::stringFromColumnIndex(4 + $i);
                $sheet->getColumnDimension($colLetter)->setWidth(7);
            }
            $summaryStartColIndex = 4 + $totalDays + 1;
            $lastColIndex = Coordinate::columnIndexFromString($lastColLetter);
            for ($i = $summaryStartColIndex; $i <= $lastColIndex; $i++) {
                $colLetter = Coordinate::stringFromColumnIndex($i);
                $sheet->getColumnDimension($colLetter)->setAutoSize(True);
            }


            // --- Output Excel File ---
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

    $app->router('/timekeeping', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template, $accStore) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Chấm công");

            // Prepare year and month lists for filters
            $years = [];
            for ($i = 2020; $i <= date('Y'); $i++) {
                $years[] = ["value" => $i, "text" => $i];
            }
            $months = [];
            for ($i = 1; $i <= 12; $i++) {
                $value = str_pad($i, 2, "0", STR_PAD_LEFT);
                $months[] = ["value" => $value, "text" => $i];
            }
            $vars['years'] = $years;
            $vars['months'] = $months;

            // Personnel list based on accessible stores
            $personnels = $app->select("personnels", ["id(value)", "name(text)"], [
                "deleted" => 0,
                "status" => 'A',
                "stores" => $accStore
            ]);
            $vars['personnels'] = array_merge(
                [['value' => '', 'text' => $jatbi->lang('Chọn nhân viên')]],
                $personnels
            );

            // Office list
            $office = $app->select("offices", ["id(value)", "name(text)"], [
                "deleted" => 0,
                "status" => 'A',
            ]);
            $vars['office'] = array_merge(
                [['value' => '', 'text' => $jatbi->lang('Chọn phòng ban')]],
                $office
            );

            // Get filters
            $year = $_GET['year'] ?? date('Y');
            $month = $_GET['month'] ?? date('m');
            $personnelF = $_GET['personnel'] ?? '';
            $officeF = $_GET['office'] ?? '';
            $stores = $_GET['stores'] ?? $accStore; // Use accessible stores if not specified

            $start_date_month = $year . "-" . $month . "-01";
            $end_date_month = date("Y-m-t", strtotime($start_date_month)); // Get last day of the month

            // Filter offices
            $whereOffice = ["deleted" => 0, "status" => 'A'];
            if (!empty($officeF)) {
                $whereOffice["id"] = $officeF;
            }
            if (($app->getSession("accounts")['your_self'] ?? 0) == 1) {
                $office_id = $app->get("personnels", "office", ["id" => $app->getSession("accounts")['personnels_id']]);
                $whereOffice['AND']['id'] = $office_id;
            }
            $offices = $app->select("offices", "*", $whereOffice);

            // Generate date list for the month
            $dates = [];
            $date_form = date("t", strtotime($start_date_month));
            for ($day = 1; $day <= $date_form; $day++) {
                $dayFormatted = str_pad($day, 2, "0", STR_PAD_LEFT);
                $date = date("Y-m-d", strtotime($year . "-" . $month . "-" . $dayFormatted));
                $week = date("N", strtotime($date)); // 1 (Mon) to 7 (Sun)
                $dates[] = ["name" => $dayFormatted, "date" => $date, "week" => $week];
            }

            $datas = []; // Final structure to pass to template

            foreach (($offices ?? []) as $key => $office) {
                // Filter personnel for the current office
                $wherePersonnel = [
                    "office" => $office['id'],
                    "personnels.deleted" => 0,
                    "personnels.status" => 'A',
                    "personnels.stores" => $stores,
                    "GROUP" => "personnels.id" // Ensure unique personnel
                ];
                if (!empty($personnelF)) {
                    $wherePersonnel["personnels.id"] = $personnelF; // Filter by specific personnel if selected
                }
                if (($app->getSession("accounts")['your_self'] ?? 0) == 1) {
                    $wherePersonnel['AND']['personnels.id'] = $app->getSession("accounts")['personnels_id'];
                }
                $SelectPer = $app->select("personnels", "*", $wherePersonnel);

                $datas[$key] = [
                    "name" => $office['name'],
                    "personnels" => []
                ];

                if (empty($SelectPer))
                    continue; // Skip if no personnel in this office

                $perIds = array_column($SelectPer, "id"); // Get IDs for batch queries

                // --- Pre-query all necessary data for these personnel within the month ---

                // 1. Timekeeping data
                $timekeepingAll = $app->select("timekeeping", ["personnels", "date", "checkin", "checkout"], [
                    "date[>=]" => $start_date_month,
                    "date[<=]" => $end_date_month,
                    "personnels" => $perIds
                ]);
                $timekeepingMap = [];
                foreach ($timekeepingAll as $c) {
                    $timekeepingMap[$c['personnels']][$c['date']] = $c;
                }

                // 2. Roster data
                $rostersAll = $app->select("rosters", ["id", "personnels", "date", "timework"], [
                    "personnels" => $perIds,
                    "date[<=]" => $end_date_month,
                    "deleted" => 0,
                    "ORDER" => ["date" => "DESC"]
                ]);
                $rosterMap = [];
                foreach ($rostersAll as $r) {
                    if (!isset($rosterMap[$r['personnels']])) {
                        $rosterMap[$r['personnels']] = $r['timework'];
                    }
                }

                // 3. Timework details
                $activeTimeworkIds = array_unique(array_filter(array_values($rosterMap)));
                $timeworkDetailsAll = [];
                if (!empty($activeTimeworkIds)) {
                    $timeworkDetailsAll = $app->select("timework_details", "*", [
                        "timework" => $activeTimeworkIds,
                        "deleted" => 0
                    ]);
                }
                $timeworkDetailsMap = [];
                foreach ($timeworkDetailsAll as $t) {
                    $timeworkDetailsMap[$t['timework']][$t['week']] = $t;
                }

                // 4. Leave Request Data
                $leaveRequestsAll = $app->select("hrm_leave_request_details", [
                    "[>]hrm_leave_requests(lr)" => ["leave_request_id" => "id"],
                    "[>]furlough_categorys(fc)" => ["lr.furlough_id" => "id"]
                ], [
                    "lr.profile_id",
                    "hrm_leave_request_details.leave_date",
                    "hrm_leave_request_details.leave_session",
                    "fc.code(category_code)",
                    "fc.name(category_name)"
                ], [
                    "lr.profile_id" => $perIds,
                    "hrm_leave_request_details.leave_date[>=]" => $start_date_month,
                    "hrm_leave_request_details.leave_date[<=]" => $end_date_month,
                    "lr.deleted" => 0,
                    "lr.status" => 'A' // Corrected status to 'A' for Approved
                ]);
                $leaveMap = [];
                foreach ($leaveRequestsAll as $l) {
                    $leaveMap[$l['profile_id']][$l['leave_date']][] = $l;
                }

                // 5. Holiday Data
                $holidaysAll = $app->select("holiday", ["name", "date_from", "date_to"], [
                    "date_from[<=]" => $end_date_month,
                    "date_to[>=]" => $start_date_month,
                    "deleted" => 0,
                    "status" => 'A'
                ]);
                $holidayMap = [];
                foreach ($holidaysAll as $h) {
                    $start = strtotime($h['date_from']);
                    $end = strtotime($h['date_to']);
                    for ($d = $start; $d <= $end; $d += 86400) {
                        $currentDateStr = date("Y-m-d", $d);
                        if ($currentDateStr >= $start_date_month && $currentDateStr <= $end_date_month) {
                            $holidayMap[$currentDateStr] = $h['name'] ?? 'Lễ';
                        }
                    }
                }

                // --- Assign data for each personnel ---
                foreach ($SelectPer as $per) {
                    $personnelData = [
                        "id" => $per['id'],
                        "name" => $per['name'],
                        "dates" => []
                    ];

                    $currentTimeworkId = $rosterMap[$per['id']] ?? null;

                    foreach ($dates as $dateInfo) {
                        $currentDate = $dateInfo['date'];
                        $currentWeekDay = $dateInfo['week'];

                        $checked = $timekeepingMap[$per['id']][$currentDate] ?? null;
                        $timeworkDetail = $currentTimeworkId ? ($timeworkDetailsMap[$currentTimeworkId][$currentWeekDay] ?? null) : null;
                        $leaveInfoArray = $leaveMap[$per['id']][$currentDate] ?? null; // Array of leaves for the day
                        $holidayName = $holidayMap[$currentDate] ?? null;

                        $color = "bg-light bg-opacity-10";
                        $off = 0;
                        $offcontent = "";
                        $is_full_day_leave = false; // Flag for full day leave

                        // --- Determine Off Status, Content, and Color ---
                        if ($holidayName) {
                            $off = 1;
                            $offcontent = $holidayName;
                            $color = 'bg-info bg-opacity-25';
                        } elseif ($leaveInfoArray) {
                            $leaveText = [];
                            $is_full_day_leave = false; // Reset flag for each day
                            $leave_session_count = 0; // Count half-day sessions

                            foreach ($leaveInfoArray as $leaveDetail) {
                                $sessionText = '';
                                if ($leaveDetail['leave_session'] === 'morning') {
                                    $sessionText = ' (S)';
                                    $leave_session_count++;
                                } elseif ($leaveDetail['leave_session'] === 'afternoon') {
                                    $sessionText = ' (C)';
                                    $leave_session_count++;
                                } else { // full_day
                                    $is_full_day_leave = true; // Mark as full day leave
                                }
                                $leaveText[] = ($leaveDetail['category_code'] ?? $leaveDetail['category_name']) . $sessionText;
                            }
                            $offcontent = implode(', ', $leaveText);
                            $color = 'bg-primary bg-opacity-25'; // Leave color

                            // Set off=1 only if it's a full day leave or two half-day leaves
                            if ($is_full_day_leave || $leave_session_count >= 2) {
                                $off = 1;
                                $is_full_day_leave = true; // Ensure flag is set if two half days
                            }
                        } elseif ($timeworkDetail && $timeworkDetail['off'] == 1) {
                            $off = 1;
                            $offcontent = 'OFF';
                            $color = 'bg-secondary bg-opacity-25';
                        }

                        if ($off == 0) {
                            if (empty($checked)) { // No checkin/out record
                                if (strtotime($currentDate) < strtotime(date("Y-m-d"))) {
                                    if ($timeworkDetail && $timeworkDetail['off'] != 1) {
                                        $color = 'bg-warning bg-opacity-10'; // Absent
                                        $offcontent = 'V'; // Mark Absent in content if desired
                                    } else {
                                        $color = 'bg-light bg-opacity-10'; // Non-working day
                                    }
                                } else {
                                    $color = 'bg-light bg-opacity-10'; // Future/Today
                                }
                            } elseif (empty($checked['checkout'])) { // Checked in but not out
                                $color = 'bg-danger bg-opacity-10'; // Incomplete
                            } else { // Checked in and out
                                $color = 'bg-success bg-opacity-10'; // Complete
                            }

                            // If it was a half-day leave, override color but keep times
                            if ($leaveInfoArray && !$is_full_day_leave) {
                                $color = 'bg-primary bg-opacity-25'; // Use leave color for half-days
                            }
                        }

                        // --- Store calculated data for the date ---
                        $personnelData["dates"][$currentDate] = [
                            "name" => $dateInfo['name'],
                            "date" => $currentDate,
                            "week" => $currentWeekDay,
                            "color" => $color,
                            // Show checkin/out UNLESS it's a full off day (Holiday, Full Leave, Scheduled Off)
                            "checkin" => ["time" => ($off == 1 && $is_full_day_leave) ? null : ($checked['checkin'] ?? null)],
                            "checkout" => ["time" => ($off == 1 && $is_full_day_leave) ? null : ($checked['checkout'] ?? null)],
                            "off" => ["status" => $off, "content" => $offcontent],
                        ];
                    } // End loop through dates

                    $datas[$key]["personnels"][$per['id']] = $personnelData;
                } // End loop through personnel
            } // End loop through offices

            $vars['dates'] = $dates;
            $vars['datas'] = $datas;
            $vars['offices'] = $offices;
            $vars['furlough_categorys'] = $app->select("furlough_categorys", "*", ["deleted" => 0, "status" => 'A']);

            echo $app->render($template . '/hrm/timekeeping.html', $vars);
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

            // Input validation - Removed time check, assuming date includes time
            if (empty($_POST['personnels']) || empty($_POST['date']) || empty($_POST['status'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng điền đủ thông tin Nhân viên, Ngày giờ và Loại")]);
                return; // Stop execution if validation fails
            }

            $personnel_id = (int) $app->xss($_POST['personnels']);
            // --- CORRECTED: Extract date and time from the single $_POST['date'] field ---
            $datetime_input = $_POST['date']; // e.g., "2025-10-26T08:30" or "2025-10-26 08:30:00"
            $datetime_ts = strtotime($datetime_input);
            if ($datetime_ts === false) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Định dạng Ngày giờ không hợp lệ")]);
                return;
            }
            $date_str = date("Y-m-d", $datetime_ts); // Extract Date part
            $time_str = date("H:i:s", $datetime_ts); // Extract Time part
            // --- END CORRECTION ---

            $status = (int) $app->xss($_POST['status']); // 1 = checkin, 2 = checkout
            $notes = $app->xss($_POST['notes']);
            $current_user_id = $app->getSession("accounts")['id'] ?? 0;
            $now_datetime = date("Y-m-d H:i:s");
            $timekeeping_id = 0; // Initialize

            // Get existing timekeeping record for the day
            $gettime = $app->get("timekeeping", "*", [
                "personnels" => $personnel_id,
                "date" => $date_str,
            ]);

            $timekeeping_data = []; // Prepare data for insert/update

            if ($status == 1) { // Checkin
                $timekeeping_data["checkin"] = $time_str;
            } elseif ($status == 2) { // Checkout
                $timekeeping_data["checkout"] = $time_str;
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Loại chấm công không hợp lệ")]);
                return;
            }

            if ($gettime) { // Record exists, update it
                if ($status == 1 && (empty($gettime['checkin']) || strtotime($time_str) < strtotime($gettime['checkin']))) {
                    $app->update("timekeeping", ["checkin" => $time_str], ["id" => $gettime['id']]);
                } elseif ($status == 2 && (empty($gettime['checkout']) || strtotime($time_str) > strtotime($gettime['checkout']))) {
                    $app->update("timekeeping", ["checkout" => $time_str], ["id" => $gettime['id']]);
                }
                $timekeeping_id = $gettime['id'];
            } else { // No record, insert new one
                $timekeeping_data["personnels"] = $personnel_id;
                $timekeeping_data["date"] = $date_str;
                $timekeeping_data["date_poster"] = $now_datetime;
                $app->insert("timekeeping", $timekeeping_data);
                $timekeeping_id = $app->id();
            }

            // Insert into timekeeping_details
            $insert_details = [
                "personnels" => $personnel_id,
                "notes" => $notes,
                "date" => date("Y-m-d H:i:s", $datetime_ts), // Use the full timestamp from input
                "status" => $status,
                "user" => $current_user_id,
                "date_poster" => $now_datetime,
                "deleted" => 0
            ];
            $app->insert("timekeeping_details", $insert_details);

            // --- START: ADD LATE/EARLY CALCULATION AND INSERT ---
            if ($timekeeping_id > 0) {
                $day_late_minutes = 0;
                $day_early_minutes = 0;

                // Find schedule
                $schedule = null;
                $active_roster = $app->get("rosters", ["timework"], [
                    "personnels" => $personnel_id,
                    "date[<=]" => $date_str,
                    "deleted" => 0,
                    "ORDER" => ["date" => "DESC"]
                ]);
                if ($active_roster && $active_roster['timework']) {
                    $week_day = date('N', strtotime($date_str));
                    $schedule = $app->get("timework_details", ["time_from", "time_to", "off"], [
                        "timework" => $active_roster['timework'],
                        "week" => $week_day,
                        "deleted" => 0
                    ]);
                }

                // Check against schedule if exists and not OFF day
                if ($schedule && $schedule['off'] == 0) {
                    $time_ts = $datetime_ts; // Use the timestamp derived from input
                    $expected_start_ts = strtotime("$date_str {$schedule['time_from']}");
                    $expected_end_ts = strtotime("$date_str {$schedule['time_to']}");

                    if ($status == 1) { // Checkin added/updated
                        if ($time_ts > $expected_start_ts + 60) {
                            $day_late_minutes = ($time_ts - $expected_start_ts) / 60;
                        }
                    } elseif ($status == 2) { // Checkout added/updated
                        if ($time_ts < $expected_end_ts - 60) {
                            $day_early_minutes = ($expected_end_ts - $time_ts) / 60;
                        }
                    }

                    $incident_date = date("Y-m-d H:i:s", $time_ts); // Correct incident date/time

                    // Record Late Arrival
                    if ($day_late_minutes > 0) {
                        $exists_late = $app->has("timekeeping_time_late", ["timekeeping" => $timekeeping_id, "type" => 1]);
                        if (!$exists_late) {
                            $app->insert("timekeeping_time_late", [
                                "type" => 1,
                                "personnels" => $personnel_id,
                                "date" => $incident_date,
                                "time_late" => (int) round($day_late_minutes),
                                "timekeeping" => $timekeeping_id,
                                "date_poster" => $now_datetime,
                                "user" => $current_user_id,
                                "status" => 0,
                                "deleted" => 0
                            ]);
                        }
                    }

                    // Record Early Departure
                    if ($day_early_minutes > 0) {
                        $exists_early = $app->has("timekeeping_time_late", ["timekeeping" => $timekeeping_id, "type" => 2]);
                        if (!$exists_early) {
                            $app->insert("timekeeping_time_late", [
                                "type" => 2,
                                "personnels" => $personnel_id,
                                "date" => $incident_date,
                                "time_late" => (int) round($day_early_minutes),
                                "timekeeping" => $timekeeping_id,
                                "date_poster" => $now_datetime,
                                "user" => $current_user_id,
                                "status" => 0,
                                "deleted" => 0
                            ]);
                        }
                    }
                } // End if schedule check
            } // End if timekeeping_id > 0
            // --- END: ADD LATE/EARLY ---

            // Log the main action
            $jatbi->logs('timekeeping', 'add/update', [
                'timekeeping_id' => $timekeeping_id,
                'details' => $insert_details,
            ]);

            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công"), 'reload' => true]);
        } // End POST method

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
    })->setPermissions(['timekeeping']);

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

    $app->router('/uniforms-allocations', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $setting, $accStore) {

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
            $where = [
                "AND" => [
                    "uniform_allocations.deleted" => 0,
                    "p.stores" => $accStore
                ]
            ];

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
            if (($app->getSession("accounts")['your_self'] ?? 0) == 1) {
                $where['AND']['p.id'] = $app->getSession("accounts")['personnels_id'];
            }
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

    $app->router('/uniforms_allocations-add', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $accStore, $template) {
        $vars['title'] = $jatbi->lang("Cấp phát Đồng phục");

        if ($app->method() === 'GET') {
            $empty_option = [['value' => '', 'text' => $jatbi->lang('')]];

            $employees_db = $app->select(
                "personnels",
                ["id", "code", "name"],
                ["deleted" => 0, "status" => 'A', "stores" => $accStore]
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

    $app->router("/annual_leave", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $common, $accStore) {
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
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $year = $_POST['year'] ?? date('Y');



            $join = [
                "[>]personnels" => ["profile_id" => "id"],
                "[>]furlough_categorys" => ["furlough_id" => "id"]
            ];
            $where = [
                "AND" => [
                    "annual_leave.deleted" => 0,
                    "annual_leave.year" => $year,
                    "personnels.stores" => $accStore,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];
            if ($searchValue) {
                $where["AND"]["OR"] = ["personnels.name[~]" => $searchValue, "personnels.code[~]" => $searchValue];
            }
            if (($app->getSession("accounts")['your_self'] ?? 0) == 1) {
                $where['AND']['personnels.id'] = $app->getSession("accounts")['personnels_id'];
            }

            $count = $app->count("annual_leave", $join, "annual_leave.id", ["AND" => $where['AND']]);
            $datas = [];

            $app->select("annual_leave", $join, [
                "annual_leave.id",
                "annual_leave.active",
                "annual_leave.total_accrued",
                "annual_leave.carried_over",
                "annual_leave.days_used",
                "personnels.name(profile_name)",
                "personnels.code(profile_code)",
                "furlough_categorys.name(furlough_name)"
            ], $where, function ($data) use (&$datas, $jatbi, $app) {
                $remaining = ($data['total_accrued'] + $data['carried_over']) - $data['days_used'];
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['active'], "class" => "checker"]),
                    "profile_code" => $data['profile_code'],
                    "profile_name" => $data['profile_name'],
                    "furlough_name" => $data['furlough_name'],
                    "total_accrued" => $data['total_accrued'],
                    "carried_over" => $data['carried_over'],
                    "days_used" => $data['days_used'],
                    "remaining" => $remaining,
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['annual_leave.edit'],
                                'action' => ['data-url' => '/hrm/annual_leave-edit/' . $data['active'], 'data-action' => 'modal']
                            ],
                            // [
                            //     'type' => 'button',
                            //     'name' => $jatbi->lang("Xem"),
                            //     'permission' => ['annual_leave.edit'],
                            //     'action' => ['data-url' => '/hrm/annual_leave-views/' . $data['active'], 'data-action' => 'modal']
                            // ],
                        ]
                    ]),
                ];
            });
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
        $year = isset($_GET['year']) ? (int) $_GET['year'] : date('Y');
        $personnel_info = $app->get("personnels", ["name", "code"], ["id" => $personnel_id]);

        $vars['title'] = $jatbi->lang("Chi tiết nghỉ phép năm ") . $year . " - " . ($personnel_info['name'] ?? 'N/A');

        $vars['records'] = $app->select("annual_leave", "*", [
            "personnels" => $personnel_id,
            "year" => $year,
            "deleted" => 0,
        ]);

        echo $app->render($template . '/hrm/annual_leave-views.html', $vars, $jatbi->ajax());
    })->setPermissions(['annual_leave']);

    $app->router("/annual_leave-add", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $vars['title'] = $jatbi->lang("Thêm Tồn phép");
        if ($app->method() === 'GET') {
            $vars['data'] = ['year' => date('Y')];
            $vars['personnels'] = $app->select("personnels", ["id(value)", "name(text)"], ["deleted" => 0, "status" => "A"]);
            $vars['furloughs'] = $app->select("furlough_categorys", ["id(value)", "name(text)"], ["deleted" => 0, "code" => "NPN"]);
            echo $app->render($template . '/hrm/annual_leave-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $profile_id = (int) $_POST['personnels'];
            $furlough_id = (int) $_POST['furlough_id'];
            $year = (int) $_POST['year'];

            if (empty($profile_id) || empty($furlough_id) || empty($year)) {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng điền đủ thông tin")]);
                return;
            }
            if ($app->has("annual_leave", ["profile_id" => $profile_id, "furlough_id" => $furlough_id, "year" => $year])) {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Đã tồn tại cấu hình phép cho nhân viên này trong năm {$year}")]);
                return;
            }

            $post_data = [
                "active" => $jatbi->active(),
                "profile_id" => $profile_id,
                "furlough_id" => $furlough_id,
                "year" => $year,
                "total_accrued" => (float) $_POST['total_accrued'],
                "carried_over" => (float) $_POST['carried_over'],
                "notes" => $app->xss($_POST['notes']),
                "account" => $app->getSession("accounts")['id'] ?? 0,
                "date" => date("Y-m-d H:i:s"),
            ];
            $app->insert("annual_leave", $post_data);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Thêm thành công"), 'url' => '/hrm/leave-balances']);
        }
    })->setPermissions(['annual_leave.add']);

    $app->router("/annual_leave-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $vars['title'] = $jatbi->lang("Sửa Tồn phép");
        $data = $app->get("annual_leave", "*", ["active" => $vars['id'], "deleted" => 0]);
        if (!$data) {
            echo $app->render($template . '/pages/error.html', ['content' => $jatbi->lang("Không tìm thấy dữ liệu")], $jatbi->ajax());
            return;
        }

        if ($app->method() === 'GET') {
            $vars['data'] = $data;
            $vars['profile_id'] = $app->select("personnels", ["id(value)", "name(text)"], ["deleted" => 0, "status" => "A"]);
            $vars['furloughs'] = $app->select("furlough_categorys", ["id(value)", "name(text)"], ["deleted" => 0]);
            echo $app->render($template . '/hrm/annual_leave-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $profile_id = (int) $_POST['profile_id'];
            $furlough_id = (int) $_POST['furlough_id'];
            $year = (int) $_POST['year'];

            if (empty($profile_id) || empty($furlough_id) || empty($year)) {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng điền đủ thông tin")]);
                return;
            }

            if ($app->has("annual_leave", ["id[!]" => $data['id'], "profile_id" => $profile_id, "furlough_id" => $furlough_id, "year" => $year])) {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Đã tồn tại cấu hình phép cho nhân viên này trong năm {$year}")]);
                return;
            }

            $post_data = [
                "profile_id" => $profile_id,
                "furlough_id" => $furlough_id,
                "year" => $year,
                "total_accrued" => (float) $_POST['total_accrued'],
                "carried_over" => (float) $_POST['carried_over'],
                "notes" => $app->xss($_POST['notes']),
                "modify" => date("Y-m-d H:i:s"),
            ];
            $app->update("annual_leave", $post_data, ["id" => $data['id']]);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công"), 'url' => 'true']);
        }
    })->setPermissions(['annual_leave.edit']);

    $app->router("/annual_leave-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa Phép năm");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("annual_leave", "*", ["active" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                $app->update("annual_leave", ["deleted" => 1], ["active" => $boxid]);
                $name = array_column($datas, 'name');
                $jatbi->logs('annual_leave', 'leave-balance-deleted', $datas);
                $jatbi->trash('/hrm/annual_leave-restore', "Phép năm: " . implode(', ', $name), ["database" => 'annual_leave', "data" => $boxid]);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xảy ra")]);
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

            $current_year = date('Y');

            $max_code_number = $app->max("contract_annex", "code", [
                "type" => 2,
                "deleted" => 0,
                "date[~]" => $current_year . '-%'
            ]);

            $new_number = (int) $max_code_number + 1;

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
                "code" => $app->xss($_POST['code']),
                "contract" => $app->xss($_POST['personnels']),
                "content" => $app->xss($_POST['content']),
                "date" => $app->xss($_POST['date']),
                "date_poster" => $app->xss($_POST['date_poster']),
                "type" => 2,
                "off" => $app->xss($_POST['off']),
                "notes" => $app->xss($_POST['notes']),
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
                "date_off" => $insert['date'],
                "type" => $type,
                "off" => $insert['off']
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
                "code" => $app->xss($_POST['code']),
                "contract" => $app->xss($_POST['personnels']),
                "content" => $app->xss($_POST['content']),
                "date" => $app->xss($_POST['date']),
                "date_poster" => $app->xss($_POST['date_poster']),
                "type" => 2,
                "off" => $app->xss($_POST['off']),
                "notes" => $app->xss($_POST['notes']),
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
                "date_off" => $update['date'],
                "type" => $type,
                "off" => $update['off']
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


    $app->router('/decided-views/{id}', 'GET', function ($vars) use ($app, $jatbi, $setting, $template) { // <-- Gỡ $database khỏi use()
        $id = (int) ($vars['id'] ?? 0);
        $vars['title'] = $jatbi->lang("In Quyết định thôi việc");


        $data = $app->get("contract_annex", "*", [
            "id" => $id,
            "type" => 2,
            "deleted" => 0
        ]);

        if (!$data) {
            return $app->render($template . '/error.html', $vars, $jatbi->ajax());
        }


        $per = $app->get("personnels", "*", [
            "id" => $data['contract'],
            "deleted" => 0
        ]);

        if (!$per) {
            $vars['error_message'] = "Không tìm thấy thông tin nhân viên.";
            return $app->render($template . '/error.html', $vars, $jatbi->ajax());
        }
        $vars['data'] = $data;
        $vars['per'] = $per;
        $vars['database'] = $app;
        echo $app->render($template . '/hrm/decided-views.html', $vars, $jatbi->ajax());
    })->setPermissions(['decided']);


    $app->router("/overtime", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $common, $accStore) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Tăng ca");
            $vars['sub_title'] = $jatbi->lang("Quản lý Đăng ký Tăng ca");
            $vars['profiles'] = $app->select("hrm_profiles", ["id(value)", "name(text)"], ["deleted" => 0]);
            $vars['status_options'] = array_map(function ($key, $item) {
                return ['value' => $key, 'text' => $item['name']];
            }, array_keys($common['overtime_status']), $common['overtime_status']);
            echo $app->render($template . '/hrm/overtime.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'work_date';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

            $profile_filter = isset($_POST['profile']) ? $_POST['profile'] : '';
            $status_filter = isset($_POST['status']) ? $_POST['status'] : '';
            $date_filter = isset($_POST['date']) ? $jatbi->parseDateRange($_POST['date']) : null;

            $join = ["[>]personnels" => ["profile_id" => "id"]];
            $where = [
                "AND" => [
                    "hrm_overtime_requests.deleted" => 0,
                    "personnels.stores" => $accStore,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)]
            ];
            if ($searchValue) {
                $where["AND"]["OR"] = ["personnels.name[~]" => $searchValue];
            }
            if ($profile_filter) {
                $where["AND"]["hrm_overtime_requests.profile_id"] = $profile_filter;
            }
            if ($status_filter) {
                $where["AND"]["hrm_overtime_requests.status"] = $status_filter;
            }
            if ($date_filter) {
                $where["AND"]["hrm_overtime_requests.work_date[<>]"] = [$date_filter[0], $date_filter[1]];
            }
            if (($app->getSession("accounts")['your_self'] ?? 0) == 1) {
                $where['AND']['personnels.id'] = $app->getSession("accounts")['personnels_id'];
            }

            $count = $app->count("hrm_overtime_requests", $join, "hrm_overtime_requests.id", ["AND" => $where['AND']]);
            $datas = [];
            $app->select("hrm_overtime_requests", $join, [
                "hrm_overtime_requests.active",
                "hrm_overtime_requests.work_date",
                "hrm_overtime_requests.start_time",
                "hrm_overtime_requests.end_time",
                "hrm_overtime_requests.total_hours",
                "hrm_overtime_requests.multiplier",
                "hrm_overtime_requests.status",
                "personnels.name(profile_name)"
            ], $where, function ($data) use (&$datas, $jatbi, $app, $common) {
                $status_info = $common['overtime_status'][$data['status']] ?? ['name' => 'N/A', 'color' => '#ccc'];
                $action_buttons = [];
                if ($data['status'] === 'pending') {
                    $action_buttons[] = ['type' => 'button', 'name' => $jatbi->lang("Xử lý"), 'permission' => ['overtime.approve'], 'action' => ['data-url' => '/hrm/overtime-approve/' . $data['active'], 'data-action' => 'modal']];
                }
                $action_buttons[] = ['type' => 'button', 'name' => $jatbi->lang("Sửa"), 'permission' => ['overtime.edit'], 'action' => ['data-url' => '/hrm/overtime-edit/' . $data['active'], 'data-action' => 'modal']];
                $action_buttons[] = ['type' => 'button', 'name' => $jatbi->lang("Xóa"), 'permission' => ['overtime.deleted'], 'action' => ['data-url' => '/hrm/overtime-deleted?box=' . $data['active'], 'data-action' => 'modal']];
                $datas[] = [
                    "checkbox" => $app->component("box", [
                        "data" => $data['active'],
                        "class" => "checker"
                    ]),
                    "profile_name" => $data['profile_name'],
                    "work_date" => date('d/m/Y', strtotime($data['work_date'])),
                    "time_range" => date('H:i', strtotime($data['start_time'])) . ' - ' . date('H:i', strtotime($data['end_time'])),
                    "total_hours" => $data['total_hours'],
                    "multiplier" => "x" . $data['multiplier'],
                    "status" => "<span class='badge' style='background-color: {$status_info['color']}'>{$status_info['name']}</span>",
                    "action" => $app->component("action", ["button" => $action_buttons]),
                ];
            });
            echo json_encode(["draw" => $draw, "recordsTotal" => $count, "recordsFiltered" => $count, "data" => $datas ?? []]);
        }
    })->setPermissions(['overtime']);

    $app->router("/overtime-add", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $vars['title'] = $jatbi->lang("Tạo Đơn đăng ký Tăng ca");
        if ($app->method() === 'GET') {
            $vars['data'] = ['work_date' => date('Y-m-d'), 'multiplier' => 1.5];
            if (($app->getSession("accounts")['your_self'] ?? 0) == 1) {
                $vars['profiles'] = $app->select("personnels", ["id(value)", "name(text)"], ["deleted" => 0, "id" => $app->getSession("accounts")['personnels_id']]);
            } else {
                $vars['profiles'] = $app->select("personnels", ["id(value)", "name(text)"], ["deleted" => 0]);
            }
            echo $app->render($template . '/hrm/overtime-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            if (empty($_POST['profile_id']) || empty($_POST['work_date']) || empty($_POST['start_time']) || empty($_POST['end_time'])) {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng điền đủ thông tin")]);
                return;
            }
            $start = new DateTime($_POST['start_time']);
            $end = new DateTime($_POST['end_time']);
            $total_hours = round(abs($end->getTimestamp() - $start->getTimestamp()) / 3600, 2);
            $post_data = [
                "active" => $jatbi->active(),
                "profile_id" => (int) $_POST['profile_id'],
                "work_date" => $app->xss($_POST['work_date']),
                "start_time" => $app->xss($_POST['start_time']),
                "end_time" => $app->xss($_POST['end_time']),
                "total_hours" => $total_hours,
                "multiplier" => (float) $_POST['multiplier'],
                "reason" => $app->xss($_POST['reason']),
                "status" => 'pending',
                "account" => $app->getSession("accounts")['id'] ?? 0,
                "date" => date("Y-m-d H:i:s"),
            ];
            $app->insert("hrm_overtime_requests", $post_data);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Tạo yêu cầu thành công"), 'url' => '/hrm/overtime']);
        }
    })->setPermissions(['overtime.add']);

    $app->router("/overtime-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $vars['title'] = $jatbi->lang("Sửa Đơn đăng ký Tăng ca");
        $data = $app->get("hrm_overtime_requests", "*", ["active" => $vars['id'], "deleted" => 0]);
        if (!$data) {
            echo $app->render($template . '/pages/error.html', ['content' => $jatbi->lang("Không tìm thấy dữ liệu")], $jatbi->ajax());
            return;
        }
        if ($app->method() === 'GET') {
            $vars['data'] = $data;
            $vars['profiles'] = $app->select("personnels", ["id(value)", "name(text)"], ["deleted" => 0]);
            echo $app->render($template . '/hrm/overtime-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $start = new DateTime($_POST['start_time']);
            $end = new DateTime($_POST['end_time']);
            $total_hours = round(abs($end->getTimestamp() - $start->getTimestamp()) / 3600, 2);
            $post_data = [
                "profile_id" => (int) $_POST['profile_id'],
                "work_date" => $app->xss($_POST['work_date']),
                "start_time" => $app->xss($_POST['start_time']),
                "end_time" => $app->xss($_POST['end_time']),
                "total_hours" => $total_hours,
                // "multiplier" => (float)$_POST['multiplier'],
                "reason" => $app->xss($_POST['reason']),
                "modify" => date("Y-m-d H:i:s")
            ];
            $app->update("hrm_overtime_requests", $post_data, ["id" => $data['id']]);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công"), 'url' => 'true']);
        }
    })->setPermissions(['overtime.edit']);

    $app->router("/overtime-approve/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {
        $vars['title'] = $jatbi->lang("Xử lý Đơn Tăng ca");
        $overtime = $app->get("hrm_overtime_requests", "*", ["active" => $vars['id'], "deleted" => 0]);
        if (!$overtime || $overtime['status'] !== 'pending') {
            echo $app->render($setting['templat'] . '/error.html', ['content' => $jatbi->lang("Yêu cầu không hợp lệ hoặc đã được xử lý.")], $jatbi->ajax());
            return;
        }
        if ($app->method() === 'GET') {
            $vars['data'] = $overtime;
            echo $app->render($template . '/hrm/overtime-approve.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $new_status = $_POST['status'] ?? 'approved';
            if (!in_array($new_status, ['approved', 'rejected'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Trạng thái không hợp lệ")]);
                return;
            }
            $app->update("hrm_overtime_requests", ["status" => $new_status, "approver_id" => $app->getSession("accounts")['id'] ?? 0, "approved_at" => date("Y-m-d H:i:s"), "notes" => $app->xss($_POST['notes'])], ["id" => $overtime['id']]);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Xử lý yêu cầu thành công")]);
        }
    })->setPermissions(['overtime.approve']);

    $app->router("/overtime-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa Yêu cầu tăng ca");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("hrm_overtime_requests", "*", ["active" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                $app->update("hrm_overtime_requests", ["deleted" => 1], ["active" => $boxid]);
                $name = array_column($datas, 'name');
                $jatbi->logs('hrm_overtime_requests', 'overtime-deleted', $datas);
                $jatbi->trash('/hrm/overtime-restore', "Yêu cầu tăng ca : " . implode(', ', $name), ["database" => 'hrm_overtime_requests', "data" => $boxid]);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xảy ra")]);
            }
        }
    })->setPermissions(['overtime.deleted']);

    // $app->router("/overtime-restore/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $account) {
    //     if ($app->method() === 'GET') {
    //         $vars['data'] = $app->get("trashs", "*", ["active" => $vars['id'], "deleted" => 0]);
    //         if ($vars['data']) {
    //             echo $app->render($setting['template'] . '/common/restore.html', $vars, $jatbi->ajax());
    //         } else {
    //             echo $app->render($setting['template'] . '/pages/error.html', $vars, $jatbi->ajax());
    //         }
    //     } elseif ($app->method() === 'POST') {
    //         $app->header(['Content-Type' => 'application/json']);
    //         $trash = $app->get("trashs", "*", ["active" => $vars['id'], "deleted" => 0]);
    //         if ($trash) {
    //             $app->action(function () use ($app, $jatbi, $trash) {
    //                 $trash_data = json_decode($trash['data']);
    //                 $boxid = $trash_data->data;
    //                 $datas = $app->select("hrm_overtime_requests", "*", ["active" => $boxid, "deleted" => 1]);
    //                 $app->update("hrm_overtime_requests", ["deleted" => 0], ["active" => $boxid]);
    //                 $app->delete("trashs", ["id" => $trash['id']]);
    //                 $jatbi->logs('hrm_overtime_requests', 'overtime-restore', $datas);
    //             });
    //             echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
    //         } else {
    //             echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xảy ra")]);
    //         }
    //     }
    // })->setPermissions(['hrm.overtime.deleted']);


    $app->router("/furlough-month", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $common, $stores, $accStore) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Nghỉ phép tháng");
            $vars['personnels'] = $app->select("personnels", ["id(value)", "name(text)"], ["deleted" => 0, "stores" => $accStore]);
            $months = [];
            $current_year = date('Y');
            $current_month = date('m');

            for ($i = -6; $i <= 6; $i++) {
                $date = strtotime("$current_year-$current_month-01 $i month");
                $y = date('Y', $date);
                $m = date('m', $date);
                $value = "$y-$m";
                $text = "Tháng " . date('m/Y', $date);
                $months[] = ['value' => $value, 'text' => $text];
            }
            $vars['months'] = $months;
            echo $app->render($template . '/hrm/furlough-month.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'hrm_leave_requests.id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $profile_filter = isset($_POST['profile']) ? $_POST['profile'] : '';
            $date_filter = isset($_POST['date']) ? $jatbi->parseDateRange($_POST['date']) : null;
            $month_filter = isset($_POST['month_year']) ? $_POST['month_year'] : '';

            $join = [
                "[>]personnels" => ["profile_id" => "id"],
                "[>]furlough_categorys" => ["furlough_id" => "id"],
                "[>]hrm_leave_request_details" => ["hrm_leave_requests.id" => "leave_request_id"],
            ];
            $where = [
                "AND" => [
                    "hrm_leave_requests.deleted" => 0,
                    "furlough_categorys.code" => "PT"
                ],
                "personnels.stores" => $accStore,
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
                "GROUP" => ["hrm_leave_requests.id"],
            ];
            if ($searchValue) {
                $where["AND"]["OR"] = ["personnels.name[~]" => $searchValue, "furlough_categorys.name[~]" => $searchValue];
            }
            if ($profile_filter) {
                $where["AND"]["hrm_leave_requests.profile_id"] = $profile_filter;
            }

            if ($month_filter && strlen($month_filter) === 7) {
                $where["AND"]["hrm_leave_request_details.leave_date[~]"] = $month_filter . "%";
            }

            if ($date_filter) {
                $where["AND"]["hrm_leave_requests.id"] = $app->select("hrm_leave_request_details", "leave_request_id", [
                    "leave_date[<>]" => [$date_filter[0], $date_filter[1]]
                ]);
            }
            if (($app->getSession("accounts")['your_self'] ?? 0) == 1) {
                $where['AND']['personnels.id'] = $app->getSession("accounts")['personnels_id'];
            }

            $count = $app->count("hrm_leave_requests", $join, "hrm_leave_requests.id", ["AND" => $where['AND']]);
            $datas = [];

            $vars['profiles'] = $app->select("personnels", ["id(value)", "name(text)"], ["deleted" => 0]);
            $vars['furloughs'] = $app->select("furlough_categorys", ["id(value)", "name(text)"], ["deleted" => 0, "status" => "A"]);
            $vars['status_options'] = array_map(function ($key, $item) {
                return ['value' => $key, 'text' => $item['name']];
            }, array_keys($common['leave_request_status']), $common['leave_request_status']);

            $requests = $app->select("hrm_leave_requests", $join, [
                "hrm_leave_requests.id",
                "hrm_leave_requests.active",
                "hrm_leave_requests.total_days",
                "hrm_leave_requests.status",
                "personnels.name(profile_name)",
                "furlough_categorys.name(furlough_name)"
            ], $where);
            $request_ids = array_column($requests, 'id');
            $details = [];
            if (!empty($request_ids)) {
                $all_details = $app->select("hrm_leave_request_details", "*", ["leave_request_id" => $request_ids, "ORDER" => ["leave_date" => "ASC"]]);
                foreach ($all_details as $detail) {
                    $details[$detail['leave_request_id']][] = $detail;
                }
            }

            foreach ($requests as $data) {

                $session_names = [
                    'full_day' => $jatbi->lang("Nguyên ngày"),
                    'morning' => $jatbi->lang("Buổi sáng"),
                    'afternoon' => $jatbi->lang("Buổi chiều"),
                ];

                $request_details = $details[$data['id']] ?? [];

                $duration_parts = [];
                if (!empty($request_details)) {
                    foreach ($request_details as $detail) {
                        $date_formatted = date('d/m/Y', strtotime($detail['leave_date']));
                        $session_name = $session_names[$detail['leave_session']] ?? '';
                        $session_str = !empty($session_name) ? " ({$session_name})" : '';
                        $duration_parts[] = $date_formatted . $session_str;
                    }
                }

                if (!empty($duration_parts)) {
                    $duration = implode('<br>', $duration_parts);
                } else {
                    $duration = 'N/A';
                }
                $action_buttons = [];
                $action_buttons[] = [
                    'type' => 'button',
                    'name' => $jatbi->lang("Sửa"),
                    'permission' => ['furlough.edit'],
                    'action' => ['data-url' => '/hrm/furlough-month-edit/' . $data['active'], 'data-action' => 'modal']
                ];
                $action_buttons[] = [
                    'type' => 'button',
                    'name' => $jatbi->lang("Xóa"),
                    'permission' => ['furlough.deleted'],
                    'action' => ['data-url' => '/hrm/furlough-deleted?box=' . $data['active'], 'data-action' => 'modal']
                ];

                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['active'], "class" => "checker"]),
                    "profile_name" => $data['profile_name'],
                    "furlough_name" => $data['furlough_name'],
                    "duration" => $duration,
                    "total_days" => $data['total_days'],
                    // "status" => $app->component("clickable_approval", [
                    //     "url" => "/hrm/furlough-status/" . $data['active'],
                    //     "data" => $data['status'],
                    //     "permission" => ['furlough.approve']
                    // ]),
                    "action" => $app->component("action", ["button" => $action_buttons]),
                ];
            }
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas ?? []
            ]);
        }
    })->setPermissions(['furlough-month']);

    $app->router("/furlough-month-add", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $accStore) {
        $vars['title'] = $jatbi->lang("Tạo Đơn xin nghỉ phép");

        if ($app->method() === 'GET') {
            // --- Phần GET: Hiển thị form ---
            $vars['data'] = ['status' => 'pending', 'leave_session' => 'full_day'];
            if (($app->getSession("accounts")['your_self'] ?? 0) == 1) {
                $vars['profiles'] = $app->select("personnels", ["id(value)", "name(text)"], ["deleted" => 0, "id" => $app->getSession("accounts")['personnels_id']]);
            } else {
                $vars['profiles'] = $app->select("personnels", ["id(value)", "name(text)"], ["deleted" => 0, "stores" => $accStore]);
            }
            $vars['furloughs'] = $app->select("furlough_categorys", ["id(value)", "name(text)", "code"], ["deleted" => 0, "status" => "A", "code" => "PT"]); // Lấy thêm 'code'
            $vars['leave_details'] = [];
            echo $app->render($template . '/hrm/furlough-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            // --- Phần POST: Xử lý dữ liệu form ---
            $app->header(['Content-Type' => 'application/json']);

            // 1. Lấy dữ liệu và kiểm tra cơ bản
            $personnel_id = (int) ($_POST['profile_id']);
            $furlough_id = (int) ($_POST['furlough_id']);
            $leave_dates = $_POST['leave_date'];
            $leave_sessions = $_POST['leave_session'];
            $reason = $app->xss($_POST['reason'] ?? '');

            if (empty($personnel_id) || empty($furlough_id) || empty($leave_dates)) {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng điền đủ thông tin bắt buộc (Nhân viên, Loại phép, Ngày nghỉ)")]);
                return;
            }

            // 2. Tính toán tổng số ngày nghỉ và kiểm tra tính hợp lệ từng ngày
            $total_days = 0;
            $first_valid_date = null;
            $processed_dates = []; // Để lưu các ngày đã xử lý

            foreach ($leave_dates as $index => $date_str) {
                // Bỏ qua nếu ngày trống hoặc session không hợp lệ
                if (empty($date_str) || !isset($leave_sessions[$index])) {
                    continue;
                }
                $date_obj = date_create($date_str);
                if (!$date_obj)
                    continue;
                $date_formatted = date_format($date_obj, 'Y-m-d');

                // Lấy ngày hợp lệ đầu tiên
                if ($first_valid_date === null) {
                    $first_valid_date = $date_formatted;
                }

                $session = $leave_sessions[$index];
                $days_for_this_entry = ($session === 'full_day') ? 1.0 : 0.5;
                $total_days += $days_for_this_entry;

                // ---- THÊM KIỂM TRA CHO TỪNG NGÀY ----
                // a) Kiểm tra ngày lễ (bảng holiday)
                $is_holiday = $app->has("holiday", [
                    "AND" => [
                        "date_from[<=]" => $date_formatted,
                        "date_to[>=]" => $date_formatted,
                        "deleted" => 0,
                        "status" => "A"
                    ]
                ]);
                if ($is_holiday) {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Ngày {$date_str} là ngày lễ, không thể xin nghỉ.")]);
                    return;
                }

                // c) Kiểm tra trùng lặp đơn nghỉ phép khác (hrm_leave_requests & hrm_leave_request_details)
                $overlap_check = $app->count("hrm_leave_request_details", [
                    "[>]hrm_leave_requests" => ["leave_request_id" => "id"]
                ], "hrm_leave_request_details.id", [
                    "AND" => [
                        "hrm_leave_requests.profile_id" => $personnel_id,
                        "hrm_leave_requests.deleted" => 0,
                        "hrm_leave_requests.status" => ['A'], // Chỉ kiểm tra đơn đang chờ hoặc đã duyệt
                        "hrm_leave_request_details.leave_date" => $date_formatted,
                        "OR" => [
                            "hrm_leave_request_details.leave_session" => "full_day", // Nếu đã có đơn cả ngày
                            "leave_session" => "full_day",                      // Hoặc nếu đang xin cả ngày
                            "hrm_leave_request_details.leave_session" => $session // Hoặc nếu trùng buổi
                        ]
                    ]
                ]);

                if ($overlap_check > 0) {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Ngày/Buổi {$date_str} đã tồn tại trong một đơn nghỉ phép khác.")]);
                    return;
                }
                // ---- KẾT THÚC KIỂM TRA TỪNG NGÀY ----

                $processed_dates[$index] = ['date' => $date_formatted, 'session' => $session]; // Lưu lại ngày đã xử lý
            }

            // 3. Kiểm tra lại nếu không có ngày hợp lệ nào
            if ($total_days <= 0 || $first_valid_date === null) {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng chọn ít nhất một ngày nghỉ hợp lệ")]);
                return;
            }

            // 4. KIỂM TRA SỐ DƯ (NẾU LÀ PHÉP NĂM)
            // Lấy thông tin loại phép đã chọn
            $furlough_info = $app->get("furlough_categorys", ["id", "code", "amount", "duration"], ["id" => $furlough_id]);


            if ($furlough_info && $furlough_info['amount'] > 0 && in_array($furlough_info['duration'], [4, 5])) {
                $amount = (float) $furlough_info['amount'];
                $duration = $furlough_info['duration']; // 4=tháng, 5=năm

                $year = date('Y', strtotime($first_valid_date));
                $month = date('m', strtotime($first_valid_date));

                // DÙNG NAMED PARAMETER → KHÔNG LỖI bindValue
                $sql = "
                    SELECT COALESCE(SUM(
                        CASE WHEN d.leave_session = 'full_day' THEN 1.0 ELSE 0.5 END
                    ), 0) AS used_days
                    FROM hrm_leave_request_details d
                    INNER JOIN hrm_leave_requests r ON d.leave_request_id = r.id
                    WHERE r.profile_id = :profile_id
                    AND r.furlough_id = :furlough_id
                    AND r.deleted = 0
                    AND r.status != 'D'
                    AND " . ($duration == 5
                    ? "YEAR(d.leave_date) = :year"
                    : "YEAR(d.leave_date) = :year AND MONTH(d.leave_date) = :month"
                );

                $params = [
                    ':profile_id' => $personnel_id,
                    ':furlough_id' => $furlough_id,
                ];

                if ($duration == 5) {
                    $params[':year'] = $year;
                } else {
                    $params[':year'] = $year;
                    $params[':month'] = $month;
                }

                try {
                    $stmt = $app->query($sql, $params);
                    $row = $stmt->fetch();
                    $used_days = (float) ($row['used_days'] ?? 0);
                } catch (Exception $e) {
                    $used_days = 0;
                }

                if (($used_days + $total_days) > $amount) {
                    $cycle_text = $duration == 5 ? "năm {$year}" : "tháng {$month}/{$year}";
                    echo json_encode([
                        "status" => "error",
                        "content" => $jatbi->lang("Loại phép này chỉ được nghỉ tối đa {$amount} ngày/{$cycle_text}. Đã dùng: {$used_days}, yêu cầu thêm: {$total_days}")
                    ]);
                    return;
                }
            }

            // 5. Lưu vào CSDL (Transaction)
            $app->action(function () use ($app, $jatbi, $personnel_id, $furlough_id, $total_days, $reason, $processed_dates) {
                // Bảng hrm_leave_requests
                $request_data = [
                    "active" => $jatbi->active(),
                    "profile_id" => $personnel_id,
                    "furlough_id" => $furlough_id,
                    "total_days" => $total_days,
                    "reason" => $reason,
                    "status" => 'A',
                    "account" => $app->getSession("accounts")['id'] ?? 0,
                    "date" => date("Y-m-d H:i:s"),
                ];
                $app->insert("hrm_leave_requests", $request_data);
                $leave_request_id = $app->id();

                foreach ($processed_dates as $detail) {
                    $app->insert("hrm_leave_request_details", [
                        "leave_request_id" => $leave_request_id,
                        "leave_date" => $detail['date'],
                        "leave_session" => $detail['session']
                    ]);
                }
                $jatbi->logs('leave_request', 'add', $request_data);
            });

            // 6. Trả về thành công
            echo json_encode([
                'status' => 'success',
                'content' => $jatbi->lang("Tạo đơn thành công"),
                'reload' => true
            ]);
        }
    })->setPermissions(['furlough-month.add']);

    $app->router("/furlough-month-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template, $accStore) {
        $request_active = $vars['id'];  // active từ URL
        $vars['title'] = $jatbi->lang("Chỉnh sửa đơn xin nghỉ phép");

        // === 1. LẤY ĐƠN THEO active ===
        $request = $app->get("hrm_leave_requests", "*", [
            "active" => $request_active,
            "deleted" => 0
        ]);

        if (!$request) {
            $vars['modalContent'] = $jatbi->lang("Không tìm thấy dữ liệu");
            echo $app->render($setting['template'] . '/common/reward.html', $vars, $jatbi->ajax());
            return;
        }

        $request_db_id = $request['id'];  // ← ID CSDL THẬT

        $account_id = $app->getSession("accounts")['id'] ?? 0;
        $is_self = ($app->getSession("accounts")['your_self'] ?? 0) == 1;

        if ($app->method() === 'GET') {
            $vars['data'] = $request;
            $vars['leave_details'] = $app->select("hrm_leave_request_details", [
                "leave_date",
                "leave_session"
            ], [
                "leave_request_id" => $request_db_id,
                "ORDER" => ["leave_date" => "ASC"]
            ]);
            if (($app->getSession("accounts")['your_self'] ?? 0) == 1) {
                $vars['profiles'] = $app->select("personnels", ["id(value)", "name(text)"], ["deleted" => 0, "id" => $app->getSession("accounts")['personnels_id']]);
            } else {
                $vars['profiles'] = $app->select("personnels", ["id(value)", "name(text)"], ["deleted" => 0, "stores" => $accStore]);
            }
            $vars['furloughs'] = $app->select("furlough_categorys", ["id(value)", "name(text)", "code"], ["deleted" => 0, "status" => "A", "code" => "PT"]);




            echo $app->render($template . '/hrm/furlough-post.html', $vars, $jatbi->ajax());
            return;
        }

        // ==================== POST ====================
        if ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);


            if (empty($_POST['profile_id']) || empty($_POST['furlough_id']) || empty($_POST['leave_date'])) {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng điền đủ thông tin bắt buộc.")]);
                return;
            }
            $personnel_id = (int) ($_POST['profile_id']);
            $furlough_id = (int) ($_POST['furlough_id']);
            $leave_dates = $_POST['leave_date'] ?? [];
            $leave_sessions = $_POST['leave_session'] ?? [];
            $reason = $app->xss($_POST['reason'] ?? '');

            $total_days = 0;
            $first_valid_date = null;
            $processed_dates = [];

            foreach ($leave_dates as $index => $date_str) {
                if (empty($date_str) || !isset($leave_sessions[$index]))
                    continue;

                $date_obj = date_create($date_str);
                if (!$date_obj)
                    continue;
                $date_formatted = date_format($date_obj, 'Y-m-d');

                if ($first_valid_date === null)
                    $first_valid_date = $date_formatted;

                $session = $leave_sessions[$index];
                $days = ($session === 'full_day') ? 1.0 : 0.5;
                $total_days += $days;

                // Kiểm tra ngày lễ
                $is_holiday = $app->has("holiday", [
                    "AND" => [
                        "date_from[<=]" => $date_formatted,
                        "date_to[>=]" => $date_formatted,
                        "deleted" => 0,
                        "status" => "A"
                    ]
                ]);
                if ($is_holiday) {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Ngày {$date_str} là ngày lễ.")]);
                    return;
                }

                // === KIỂM TRA TRÙNG: LOẠI TRỪ CHÍNH ĐƠN NÀY ===
                $overlap = $app->count("hrm_leave_request_details", [
                    "[>]hrm_leave_requests" => ["leave_request_id" => "id"]
                ], "hrm_leave_request_details.id", [
                    "AND" => [
                        "hrm_leave_requests.profile_id" => $personnel_id,
                        "hrm_leave_requests.deleted" => 0,
                        "hrm_leave_requests.status" => ['A', 'P'],
                        "hrm_leave_requests.id[!]" => $request_db_id,  // ← DÙNG id CSDL
                        "hrm_leave_request_details.leave_date" => $date_formatted,
                        "OR" => [
                            "hrm_leave_request_details.leave_session" => "full_day",
                            "leave_session" => "full_day",
                            "hrm_leave_request_details.leave_session" => $session
                        ]
                    ]
                ]);

                if ($overlap > 0) {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Ngày/Buổi {$date_str} đã tồn tại trong đơn khác.")]);
                    return;
                }

                $processed_dates[] = ['date' => $date_formatted, 'session' => $session];
            }

            if ($total_days <= 0) {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng chọn ít nhất một ngày nghỉ.")]);
                return;
            }

            $app->action(function () use ($app, $request_db_id, $personnel_id, $furlough_id, $total_days, $reason, $processed_dates) {
                $app->update("hrm_leave_requests", [
                    "profile_id" => $personnel_id,
                    "furlough_id" => $furlough_id,
                    "total_days" => $total_days,
                    "reason" => $reason,
                    "status" => 'A',
                    "account" => $app->getSession("accounts")['id'] ?? 0
                ], ["id" => $request_db_id]);

                $app->delete("hrm_leave_request_details", ["leave_request_id" => $request_db_id]);

                foreach ($processed_dates as $detail) {
                    $app->insert("hrm_leave_request_details", [
                        "leave_request_id" => $request_db_id,
                        "leave_date" => $detail['date'],
                        "leave_session" => $detail['session']
                    ]);
                }
            });

            echo json_encode([
                'status' => 'success',
                'content' => $jatbi->lang("Cập nhật đơn thành công"),
                'reload' => true
            ]);
        }
    })->setPermissions(['furlough-month.edit']);




    $app->router('/personnels-evaluation/{id}', 'GET', function ($vars) use ($app, $jatbi, $template, $setting) {
        $id = (int) ($vars['id'] ?? 0);
        $vars['title'] = $jatbi->lang("In Phiếu Đánh Giá Nhân Viên");


        $personnel_data = $app->get("personnels", "*", [
            "id" => $id,
            "deleted" => 0
        ]);

        if (!$personnel_data) {
            return $app->render($template . '/error.html', $vars, $jatbi->ajax());
        }


        if (!empty($personnel_data['office'])) {
            $office = $app->get("offices", ["name"], ["id" => $personnel_data['office']]);
            $personnel_data['office_name'] = $office['name'] ?? 'N/A';
        } else {
            $personnel_data['office_name'] = 'N/A';
        }


        $latest_contract = $app->get(
            "personnels_contract",
            ["position", "workday"],
            [
                "personnels" => $id,
                "deleted" => 0,
                "ORDER" => ["date_contract" => "DESC"]
            ]
        );


        if ($latest_contract) {

            if (!empty($latest_contract['position'])) {
                $position = $app->get("hrm_positions", ["name"], ["id" => $latest_contract['position']]);
                $personnel_data['position_name'] = $position['name'] ?? 'N/A';
            } else {
                $personnel_data['position_name'] = 'N/A';
            }


            $personnel_data['work_start_date'] = $latest_contract['workday'] ?? $personnel_data['date'];
        } else {

            $personnel_data['position_name'] = 'N/A';
            $personnel_data['work_start_date'] = $personnel_data['date'];
        }

        $vars['data'] = $personnel_data;


        $vars['criteria_list'] = [
            1 => $jatbi->lang('Chuyên môn nhân viên'),
            2 => $jatbi->lang('Khối lượng công việc'),
            3 => $jatbi->lang('Tính tự giác trong công việc'),
            4 => $jatbi->lang('Tính lắng nghe và cải thiện'),
            5 => $jatbi->lang('Tính sáng tạo, linh động'),
            6 => $jatbi->lang('Tính phối hợp tổ chức'),
            7 => $jatbi->lang('Tinh thần trách nhiệm'),
            8 => $jatbi->lang('Tinh thần học hỏi'),
            9 => $jatbi->lang('Giao tiếp tốt'),
            10 => $jatbi->lang('Tuân thủ quy định của công ty'),
            11 => $jatbi->lang('Tuân thủ quy trình hoạt động của công ty'),
            12 => $jatbi->lang('Tuân thủ văn hóa của công ty'),
        ];


        echo $app->render($template . '/hrm/personnels-evaluation.html', $vars, $jatbi->ajax());
    })->setPermissions(['personnels.evaluation']);


    // Route MỚI để xem hợp đồng bằng ID NHÂN VIÊN
    $app->router("/contract-view-personnel/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {
        $vars['title'] = $jatbi->lang("Hợp đồng lao động");

        if ($app->method() === 'GET') {
            $personnel_id = $vars['id']; // Đây là ID Nhân viên từ URL

            // Kiểm tra quyền "your_self"
            $current_user_personnel_id = $app->getSession("accounts")["personnels_id"] ?? 0;
            if (($app->getSession("accounts")['your_self'] ?? 0) == 1 && $current_user_personnel_id != $personnel_id) {
                // Người này đang cố xem hợp đồng của người khác
                echo $app->render($setting['template'] . '/pages/error.html', $vars, $jatbi->ajax());
                return;
            }

            // SỬA LỖI: Tìm hợp đồng DỰA TRÊN personnel_id, lấy cái mới nhất
            $vars['data'] = $app->get("personnels_contract", "*", [
                "personnels" => $personnel_id,
                "deleted" => 0,
                "ORDER" => ["date_contract" => "DESC"] // Lấy hợp đồng mới nhất
            ]);

            if (!empty($vars['data'])) {
                // ... (code xử lý khi tìm thấy vẫn giữ nguyên)
                $vars['personnel_name'] = $app->get("personnels", "name", ["id" => $vars['data']['personnels']]) ?? '';
                $vars['office_name'] = $app->get("offices", "name", ["id" => $vars['data']['offices']]) ?? '';
                $vars['position_name'] = $app->get("hrm_positions", "name", ["id" => $vars['data']['position']]) ?? '';
                $vars['user_name'] = $app->get("accounts", "name", ["id" => $vars['data']['user']]) ?? '';
                $vars['contract_type_name'] = $setting['personnels_contracts'][$vars['data']['type']]['name'] ?? 'Không xác định';
                $vars['dataSalary'] = $app->get("personnels_contract_salary", "*", [
                    "deleted" => 0,
                    "contract" => $vars['data']['id'],
                    "status" => 0,
                    "ORDER" => ["id" => "DESC"]
                ]);

                echo $app->render($template . '/hrm/contract-view.html', $vars, $jatbi->ajax());
            } else {
                // === SỬA Ở ĐÂY ===
                // Phải render file HTML báo lỗi, không phải echo JSON
                $vars['error_message'] = $jatbi->lang("Nhân viên này chưa có hợp đồng.");
                echo $app->render($setting['template'] . '/pages/error.html', $vars, $jatbi->ajax());
                return;
            }
        }
    })->setPermissions(['contract']);
})->middleware('login');
