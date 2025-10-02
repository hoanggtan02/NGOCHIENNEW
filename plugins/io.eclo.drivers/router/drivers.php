<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\IOFactory;

if (!defined('ECLO'))
    die("Hacking attempt");

use ECLO\App;

$template = __DIR__ . '/../templates';
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

$app->group($setting['manager'] . "/drivers", function ($app) use ($jatbi, $setting, $stores,$template, $accStore) {

    $app->router('/driver', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $accStore,$template) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Thông tin tài xế");
            $vars['users'] = array_merge(
                [['value' => '', 'text' => $jatbi->lang('Tất cả')]],
                array_map(
                    fn($u) => ['value' => $u['value'], 'text' => $u['text'] . ' - ' . $u['phone']],
                    $app->select(
                        "accounts",
                        ["id(value)", "name(text)", "phone"],
                        ["deleted" => 0, "status" => "A", "ORDER" => ["name" => "ASC"]]
                    )
                )
            );


            echo $app->render($template . '/drivers/driver.html', $vars);

            // Xử lý yêu cầu POST: Lọc và trả về dữ liệu JSON cho bảng
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';

            $orderName = isset($_POST['order'][0]['column']) ? $_POST['columns'][$_POST['order'][0]['column']]['name'] : 'drivers.id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $date_string = isset($_POST['date']) ? $app->xss($_POST['date']) : '';

            $filter_user = isset($_POST['users']) ? $app->xss($_POST['users']) : '';


            // Define joins for the query
            $joins = [
                "[>]accounts (user_info)" => ["user" => "id"],
                "[>]accounts (creator_info)" => ["accounts" => "id"]
            ];

            // Khởi tạo điều kiện WHERE
            $where = [
                "AND" => [
                    "drivers.deleted" => 0,
                    "drivers.stores" => $accStore
                ]
            ];

            // Thêm điều kiện tìm kiếm
            if (!empty($searchValue)) {
                $where['AND']['OR'] = [
                    'drivers.car[~]' => $searchValue,
                    'drivers.number_car[~]' => $searchValue,
                    'drivers.count[~]' => $searchValue,
                    'drivers.code[~]' => $searchValue,
                    'user_info.name[~]' => $searchValue,
                    'user_info.phone[~]' => $searchValue,
                ];
            }


            // Process date range
            if ($date_string) {
                $date = explode('-', $date_string);
                $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date[0]))));
                $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date[1]))));
            } else {
                $date_from = date('Y-m-01 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }

            // Áp dụng bộ lọc tài xế
            if (!empty($filter_user)) {
                $where['AND']['drivers.user'] = $filter_user;
            }

            if (!empty($searchValue)) {
                $where['AND']['drivers.count'] = $searchValue;
            }

            $where['AND']['drivers.date[<>]'] = [$date_from, $date_to];

            $count = $app->count("drivers", $joins, "drivers.id", $where);


            // Thêm sắp xếp và phân trang vào điều kiện WHERE
            $where["ORDER"] = [$orderName => strtoupper($orderDir)];
            $where["LIMIT"] = [$start, $length];

            $datas = [];

            $columns = [
                'drivers.id',
                'drivers.date',
                'drivers.number_car',
                'drivers.car',
                'drivers.count',
                'drivers.amount',
                'drivers.code',
                'drivers.status',
                'user_info.name(user_name)',
                'user_info.phone(user_phone)',
                'creator_info.name(creator_name)'
            ];

            $app->select("drivers", $joins, $columns, $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "date" => date('d/m/Y', strtotime($data['date'])),
                    "user" => $data['user_name'] . ' - ' . $data['user_phone'],
                    "number_car" => $data['number_car'],
                    "car" => $data['car'],
                    "count" => $data['count'],
                    "amount" => $data['amount'],
                    "code" => $data['code'],
                    "status" => ($data['status'] == 0 ? '<span class="text-danger fw-bold">' . $jatbi->lang("Chưa thanh toán") . '</span>' : '<span class="text-success fw-bold">' . $jatbi->lang("Đã thanh toán") . '</span>'),
                    "accounts" => $data['creator_name'],
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['driver.edit'],
                                'action' => ['data-url' => '/drivers/driver-edit' . '/' . $data['id'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'permission' => ['driver.deleted'],
                                'action' => ['data-url' => '/drivers/driver/deleted?box=' . $data['id'], 'data-action' => 'modal']
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
    })->setPermissions(['driver']);

    $app->router('/invoices', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores,$template) {
        if ($app->method() === 'GET') {

            $vars['title'] = $jatbi->lang("Đơn hàng");

            // 1. Lấy dữ liệu động từ DB
            // $customers_db = $app->select("customers", ['id(value)', 'name(text)'], ['deleted' => 0]);
            $accounts_db = $app->select("accounts", ['id(value)', 'name(text)'], ['deleted' => 0]);

            // 2. Thêm lựa chọn "Tất cả" vào đầu mỗi mảng
            // $vars['customers'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $customers_db);
            $vars['stores'] = $stores;
            $vars['accounts'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $accounts_db);

            echo $app->render($template . '/drivers/invoices.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            // --- 1. Đọc tham số ---
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';
            $orderName = isset($_POST['order'][0]['column']) ? $_POST['columns'][$_POST['order'][0]['column']]['name'] : 'invoices.id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

            $filter_user = isset($_POST['user']) ? trim($_POST['user']) : '';
            $filter_customers = isset($_POST['customers']) ? trim($_POST['customers']) : '';
            $filter_status = isset($_POST['status']) ? trim($_POST['status']) : '';
            $filter_stores = isset($_POST['stores']) ? trim($_POST['stores']) : '';
            $filter_date = isset($_POST['date']) ? trim($_POST['date']) : '';

            // --- 2. Xây dựng truy vấn với JOIN ---
            $joins = [
                "[>]customers" => ["customers" => "id"],
                "[>]accounts" => ["user" => "id"],
                "[>]stores" => ["stores" => "id"]
            ];

            $where = [
                "AND" => [
                    "invoices.deleted" => 0,
                    "invoices.cancel" => [0, 1],
                ],
            ];

            // Áp dụng bộ lọc
            if (!empty($searchValue)) {
                $where['AND']['OR'] = [
                    'invoices.code[~]' => $searchValue,
                    'invoices.code_group[~]' => $searchValue,
                    'customers.name[~]' => $searchValue,
                ];
            }
            if (!empty($filter_status)) {
                $where['AND']['invoices.status'] = $filter_status;
            }
            if (!empty($filter_user)) {
                $where['AND']['invoices.user'] = $filter_user;
            }
            if (!empty($filter_customers) && $filter_customers == null) {
                $where['AND']['invoices.customers'] = $filter_customers;
            }
            if (!empty($filter_stores)) {
                $where['AND']['invoices.stores'] = $filter_stores;
            }

            if (!empty($filter_date)) {
                $date_parts = explode(' - ', $filter_date);
                if (count($date_parts) == 2) {
                    $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date_parts[0]))));
                    $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date_parts[1]))));
                }
            } else {
                $date_from = date('Y-m-d 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }
            $where['AND']['invoices.date[<>]'] = [$date_from, $date_to];

            // --- 3. Đếm bản ghi ---

            $count = $app->count("invoices", $joins, "invoices.id", $where);

            // Thêm sắp xếp và phân trang
            $where["ORDER"] = [$orderName => strtoupper($orderDir)];
            $where["LIMIT"] = [$start, $length];

            // --- 4. Lấy dữ liệu chính và tính tổng cho trang hiện tại ---
            $datas = [];
            $totalSum_page = 0; // Biến tính tổng cho trang hiện tại

            $columns = [
                "invoices.id",
                "invoices.code",
                "invoices.type",
                "invoices.returns",
                "invoices.total",
                "invoices.minus",
                "invoices.discount",
                "invoices.surcharge",
                "invoices.transport",
                "invoices.payments",
                "invoices.prepay",
                "invoices.status",
                "invoices.notes",
                "invoices.date_poster",
                "invoices.cancel",
                "invoices.cancel_content",
                "invoices.code_group",
                "customers.name(customer_name)",
                "accounts.name(user_name)",
                "stores.name(store_name)"
            ];

            $app->select("invoices", $joins, $columns, $where, function ($data) use (&$datas, &$totalSum_page, $jatbi, $app) {
                // Tính tổng cho trang hiện tại
                if ($data['type'] == 3) { // Nếu là hóa đơn trả hàng
                    $totalSum_page -= floatval($data['prepay']);
                } else {
                    $totalSum_page += floatval($data['prepay']);
                }
                $buttons = [];

                // Xem
                $buttons[] = [
                    'type' => 'link',
                    'name' => 'Xem',
                    'permission' => ['invoices'],
                    'action' => [
                        'href' => '/invoices/invoices-views/' . $data['id']
                    ]
                ];

                // Yêu cầu huỷ
                if ($data['cancel'] == 0) {
                    $buttons[] = [
                        'type' => 'button',
                        'name' => $jatbi->lang("Yêu cầu huỷ"),
                        'action' => [
                            'data-url' => '/invoices/invoices-cancel-req/' . $data['id'],
                            'data-action' => 'modal'
                        ]
                    ];
                }

                // Xác nhận huỷ
                if ($data['cancel'] == 1) {
                    $buttons[] = [
                        'type' => 'button',
                        'name' => $jatbi->lang("Xác nhận huỷ"),
                        'action' => [
                            'data-url' => '/invoices/invoices-cancel-confirm/' . $data['id'],
                            'data-action' => 'modal'
                        ]
                    ];
                }

                // Trả hàng
                $buttons[] = [
                    'type' => 'link',
                    'permission' => ['returns.add'],
                    'name' => $jatbi->lang("Trả hàng"),
                    'action' => [
                        'href' => '/invoices/returns-add/' . $data['id'],
                        'data-pjax' => ''
                    ]
                ];

                // Ghi chú
                $buttons[] = [
                    'type' => 'button',
                    'name' => $jatbi->lang("Ghi chú"),
                    'action' => [
                        'data-url' => '/invoices/invoices-notes/' . $data['id'],
                        'data-action' => 'modal'
                    ]
                ];
                $datas[] = [
                    "code_group" => $data['code_group'],
                    "code" => '<a class="text-nowrap pjax-load" href="/invoices/invoices-views/' . $data['id'] . '">#HĐ-' . $data['code'] . $data['id'] . '</a>',
                    "type" => $data['type'] == 3 ? $jatbi->lang("Trả hàng hóa đơn #HĐ-") . $data['code'] . $data['returns'] : $jatbi->lang("Bán hàng"),
                    "details" => '<button data-action="modal" data-url="/invoices/products_views/' . $data['id'] . '" style="border: 1px solid #dc3545; color: #dc3545; background-color: transparent; padding: 0.375rem 0.75rem; font-size: 1rem; border-radius: 0.25rem; text-decoration: none; display: inline-block; text-align: center; transition: all 0.2s ease-in-out;" onmouseover="this.style.backgroundColor=\'#dc3545\'; this.style.color=\'#ffffff\';" onmouseout="this.style.backgroundColor=\'transparent\'; this.style.color=\'#dc3545\';" aria-label="' . $jatbi->lang('Xem chi tiết') . '">Xem chi tiết</button>',
                    "customer" => $data['customer_name'],
                    "total" => number_format($data['total'] ?? 0),
                    "minus" => number_format($data['minus'] ?? 0),
                    "discount" => $data['discount'] != '' ? number_format($data['discount']) . "%" : '',
                    "surcharge" => number_format($data['surcharge'] ?? 0),
                    "transport" => number_format($data['transport'] ?? 0),
                    "payments" => number_format($data['payments'] ?? 0),
                    "prepay" => number_format($data['prepay'] ?? 0),
                    "remain" => '<a href="#!" class="modal-url text-danger" data-url="/invoices/invoices-prepay/' . $data['id'] . '/">' . number_format($data['payments'] - $data['prepay']) . '</a>',
                    "status" => $data['status'] == 1 ? '<span class="fw-bold text-success">' . $jatbi->lang("Đã thanh toán") . '</span>' : '<span class="fw-bold text-danger">' . $jatbi->lang("Công nợ") . '</span>',
                    "notes" => $data['notes'],
                    "date_poster" => date('d/m/Y H:i:s', strtotime($data['date_poster'])),
                    "user" => $data['user_name'],
                    "store" => $data['store_name'],
                    "cancel_status" => $data['cancel'] == 1 ? '<span data-bs-toggle="tooltip" title="' . $jatbi->lang('Yêu cầu hủy') . ': ' . $data['cancel_content'] . '" class="bg-danger d-block rounded-circle" style="width:10px;height: 10px;"></span>' : '',
                    "action" => $app->component("action", [
                        "button" => $buttons
                    ]),
                ];
            });

            // --- 5. Trả về JSON ---
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas,
                "footerData" => [
                    "sum_prepay" => number_format($totalSum_page)
                ]
            ]);
        }
    })->setPermissions(['invoices']);

    $app->router('/driver-payment', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting,$template) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Thanh toán tài xế");
            $accounts_db = $app->select("accounts", ['id(value)', 'name(text)'], ['deleted' => 0, 'status' => 'A']);
            $vars['accountsList'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $accounts_db);
            $drivers_db = $app->select(
                "drivers",
                [
                    "[>]accounts(driver_info)" => ["user" => "id"]
                ],
                [
                    "driver_info.id",
                    "driver_info.name(driver_name)",
                    "driver_info.phone(driver_phone)"
                ],
                [
                    "drivers.deleted" => 0,
                    "driver_info.status" => "A",
                    "GROUP" => "driver_info.id",
                    "ORDER" => [
                        "driver_info.name" => "ASC"
                    ]
                ]
            );
            $driversList = [['value' => '', 'text' => $jatbi->lang('Tất cả')]];
            foreach ($drivers_db as $driver) {
                $driversList[] = [
                    'value' => $driver['id'],
                    'text' => $driver['driver_name'] . ' - ' . $driver['driver_phone']
                ];
            }
            $vars['driversList'] = $driversList;
            echo $app->render($template . '/drivers/driver-payment.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['column']) ? $_POST['columns'][$_POST['order'][0]['column']]['data'] : 'drivers_payment.date';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $filter_user = isset($_POST['user']) ? $_POST['user'] : '';
            $filter_accounts = isset($_POST['accounts']) ? $_POST['accounts'] : '';
            $filter_date = isset($_POST['date']) ? $_POST['date'] : '';

            $joins = [
                "[>]drivers" => ["drivers" => "id"],
                "[>]accounts (driver_info)" => ["drivers.user" => "id"],
                "[>]accounts (creator_info)" => ["drivers_payment.accounts" => "id"],
                // "[>]expenditure" => ["id" => "drivers_payment"]
            ];
            $where = [
                "AND" => [
                    "drivers_payment.deleted" => 0,
                    "drivers_payment.type" => 0,
                ]
            ];
            if (!empty($searchValue)) {
                $where['AND']['OR'] = [
                    'drivers_payment.code[~]' => $searchValue,
                    'drivers_payment.code_group[~]' => $searchValue,
                    'driver_info.name[~]' => $searchValue,
                    'driver_info.phone[~]' => $searchValue,
                ];
            }
            if (!empty($filter_user)) {
                $where['AND']['drivers.user'] = $filter_user;
            }
            if (!empty($filter_accounts)) {
                $where['AND']['drivers_payment.accounts'] = $filter_accounts;
            }
            if (!empty($filter_date)) {
                $date_parts = explode(' - ', $filter_date);
                $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date_parts[0]))));
                $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date_parts[1]))));
            } else {
                $date_from = date('Y-m-d 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }
            $where['AND']['drivers_payment.date[<>]'] = [$date_from, $date_to];
            $totalSum = $app->sum("drivers_payment", $joins, "drivers_payment.total", $where);
            $count = $app->count("drivers_payment", $joins, "drivers_payment.id", $where);
            $where["ORDER"] = [$orderName => strtoupper($orderDir)];
            $where["LIMIT"] = [$start, $length];
            $datas = [];
            $columns = [
                'drivers_payment.id',
                'drivers_payment.code',
                'drivers_payment.date',
                'drivers_payment.code_group',
                'drivers_payment.total',
                'driver_info.name(driver_name)',
                'driver_info.phone(driver_phone)',
                'drivers.number_car',
                'drivers.car',
                'drivers.count',
                'drivers.amount',
                'creator_info.name(creator_name)',
                // 'expenditure.id(expenditure_id)'
            ];
            $app->select("drivers_payment", $joins, $columns, $where, function ($data) use (&$datas, $jatbi, $app, &$totalSum) {
                $expenditure_id = $app->get("expenditure", "id", ["drivers_payment" => $data['id']]);
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "code" => '<button data-action="modal" class="modal-url btn btn-eclo-light" data-url="/drivers/driver-payment-views/' . $data['id'] . '">#' . $data['code'] . '</button>',
                    "date" => date('d/m/Y', strtotime($data['date'])),
                    "user" => $data['driver_name'] . ' - ' . $data['driver_phone'],
                    "number_car" => $data['number_car'] ?? "",
                    "car" => $data['car'] ?? "",
                    "count" => $data['count'] ?? "",
                    "amount" => $data['amount'] ?? "",
                    "code_group" => $data['code_group'] ?? "",
                    "total" => number_format($data['total'] ?? 0),
                    "accounts" => $data['creator_name'] ?? "",
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xem"),
                                'action' => ['data-url' => '/drivers/driver-payment-views/' . $data['id'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'link',
                                'name' => $jatbi->lang("Sửa"),
                                'action' => ['href' => '/drivers/driver-payment-edit/' . $data['id'], 'data-pjax' => '']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("In"),
                                'action' => ['data-url' => '/accountants/expenditure-views/' . $expenditure_id, 'data-action' => 'modal']
                            ],
                        ]
                    ]),
                ];
            });
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count ?? 0,
                "recordsFiltered" => $count ?? 0,
                "data" => $datas,
                "footerData" => [
                    "sum_total" => number_format((int) $totalSum ?? 0),
                ]
            ]);
        }
    })->setPermissions(['driver-payment']);

    $app->router("/driver-payment-delete", ['GET', 'POST'], function ($vars) use ($app, $jatbi,$setting) {
        $vars['title'] = $jatbi->lang("Xóa phiếu thanh toán");

        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);

            $box_ids = explode(',', $app->xss($_GET['box'] ?? ''));
            if (empty(array_filter($box_ids))) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng chọn dữ liệu cần xóa")]);
                return;
            }

            $datas = $app->select("drivers_payment", "*", ["id" => $box_ids, "deleted" => 0]);

            if (count($datas) > 0) {
                $log_names = [];
                foreach ($datas as $data) {
                    $app->update("drivers_payment", ["deleted" => 1], ["id" => $data['id']]);

                    $app->update("expenditure", ["deleted" => 1], ["drivers_payment" => $data["id"]]);

                    $log_names[] = '#' . $data['code'] . $data['id'];
                }

                $jatbi->logs('drivers_payment', 'delete', $datas);
                $jatbi->trash('/driver-payment/restore', "Xóa phiếu thanh toán: " . implode(', ', $log_names), ["database" => 'drivers_payment', "data" => $box_ids]);

                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công"), 'load' => 'true']);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xảy ra hoặc không tìm thấy dữ liệu")]);
            }
        }
    })->setPermissions(['driver-payment.delete']);

    $app->router("/driver-payment-edit/{id}", 'GET', function ($vars) use ($app, $jatbi, $template, $stores, $accStore) {
        $vars['title'] = $jatbi->lang("Sửa Phiếu thanh toán tài xế");
        $action = 'edit';
        $id = (int) ($vars['id'] ?? 0);

        // --- BƯỚC 1: LẤY DỮ LIỆU GỐC TỪ DATABASE ---
        $drivers_payment = $app->get("drivers_payment", "*", ["id" => $id, "deleted" => 0]);
        if (!$drivers_payment) {
            return $app->render($template . '/error.html', $vars);
        }
        $driver = $app->get("drivers", "*", ["id" => $drivers_payment['drivers']]);

        // Chỉ khởi tạo session từ database nếu đây là lần đầu tiên mở phiếu sửa này
        if (($_SESSION['drivers'][$action]['order_id'] ?? null) != $drivers_payment['id']) {
            // unset($_SESSION['drivers'][$action]);
            $_SESSION['drivers'][$action]['order_id'] = $drivers_payment['id'];
            $_SESSION['drivers'][$action]['date'] = date("Y-m-d", strtotime($drivers_payment['date']));
            $_SESSION['drivers'][$action]['driver_id'] = $drivers_payment['drivers'];
            $_SESSION['drivers'][$action]['stores_id'] = $drivers_payment['stores'];
            $_SESSION['drivers'][$action]['debt'] = $drivers_payment['debt'];
            $_SESSION['drivers'][$action]['has'] = $drivers_payment['has'];
            $_SESSION['drivers'][$action]['ballot'] = $drivers_payment['ballot'];
            $_SESSION['drivers'][$action]['content'] = $drivers_payment['notes'];
            $_SESSION['drivers'][$action]['invoices'] = $drivers_payment['invoices'];
        }
        $data = $_SESSION['drivers'][$action];


        $vars['data'] = $data;
        $vars['action'] = $action;
        $vars['stores'] = $stores;
        $accountants_data = $app->select(
            "accountants_code",
            ["id", "code", "name"],
            ["deleted" => 0, "status" => 'A']
        );

        $vars['accountants'] = array_map(function ($item) {
            return [
                'value' => $item['id'],
                'text' => $item['code'] . ' - ' . $item['name']
            ];
        }, $accountants_data);

        $drivers_db = $app->select("drivers", ["[>]accounts" => ["user" => "id"]], [
            "drivers.id(value)",
            "accounts.name(text_name)",
            "accounts.phone(text_phone)",
            "drivers.code(text_code)",
            "drivers.number_car(text_car)"
        ], ["drivers.deleted" => 0, "drivers.date" => $data['date']]);
        $vars['drivers'] = array_map(function ($d) {
            $d['text'] = "{$d['text_code']} - {$d['text_name']} - {$d['text_phone']} - {$d['text_car']}";
            return $d;
        }, $drivers_db);

        $invoices = $app->select("invoices", "*", [
            "deleted" => 0,
            "date" => $data['date'],
            "code_group" => $driver['code'],
            "cancel[!]" => 2,
            "stores" => $accStore
        ]);

        // Tối ưu N+1: Lấy hết tên khách hàng trong 1 lần query
        $customer_ids = array_column($invoices, 'customers');
        $customers_map = $app->select("customers", ["id", "name"], ["id" => $customer_ids]);
        $customers_map = array_column($customers_map, 'name', 'id');

        $total_final = 0;
        foreach ($invoices as &$invoice) {
            $invoice['customer_name'] = $customers_map[$invoice['customers']] ?? 'N/A';
            $invoice_id = $invoice['id'];

            // Tính toán các giá trị mặc định từ session
            if (empty($data['invoices'][$invoice_id]['payment'])) {
                $data['invoices'][$invoice_id]['payment'] = $invoice['total'] - $invoice['discount_price'] - $invoice['minus'];
            }
            if (empty($data['invoices'][$invoice_id]['rate'])) {
                $data['invoices'][$invoice_id]['rate'] = 20;
            }
            if (empty($data['invoices'][$invoice_id]['price'])) {
                $data['invoices'][$invoice_id]['price'] = ($data['invoices'][$invoice_id]['payment'] * $data['invoices'][$invoice_id]['rate']) / 100;
            }

            $total_final += ($invoice['type'] == 3) ? -(float) $data['invoices'][$invoice_id]['price'] : (float) $data['invoices'][$invoice_id]['price'];
        }
        unset($invoice);

        $_SESSION['drivers'][$action] = $data;
        $vars['invoices'] = $invoices;
        $vars['data']['total'] = $total_final;

        echo $app->render($template . '/drivers/payment-edit.html', $vars);
    });

    $app->router('/driver-payment-update/{action}/{field}/{id}/{x}', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $router['2'] = $vars['action'];
        $router['3'] = $vars['field'];
        $router['4'] = $vars['id'];
        $router['5'] = $vars['x'];
        $action = $router['2'];

        if ($router['3'] == 'date' || $router['3'] == 'content' || $router['3'] == 'driver' || $router['3'] == 'debt' || $router['3'] == 'has' || $router['3'] == 'ballot') {
            $_SESSION['drivers'][$action][$router['3']] = $app->xss($_POST['value']);
            if ($router['3'] == 'date') {
                unset($_SESSION['drivers'][$action]['driver']);
            }
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công"),]);
        } elseif ($router['3'] == 'stores') {
            $data = $app->get("stores", "*", ["id" => $app->xss($_POST['value'])]);
            if ($data > 1) {
                $_SESSION['drivers'][$action]['stores'] = [
                    "id" => $data['id'],
                    "name" => $data['name'],
                ];
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công"),]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại"),]);
            }
        } elseif ($router['3'] == 'price') {
            $_SESSION['drivers'][$action][$router['3']] = str_replace(",", "", $app->xss($_POST['value']));
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công"),]);
        } elseif ($router['3'] == 'cancel') {
            unset($_SESSION['drivers'][$action]);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công"),]);
        } elseif ($router['3'] == 'invoices') {
            $data = $_SESSION['drivers'][$action] ?? [];

            if (isset($_POST['value'])) {
                $postedValue = $app->xss($_POST['value']);
                if ($router['5'] == 'rate' && $postedValue > 100) {
                    echo json_encode(['status' => 'error', 'content' => 'Tối đa 100%']);
                } else {
                    $_SESSION['drivers'][$action]['invoices'][$router['4']][$router['5']] = str_replace(",", "", $postedValue);
                    if ($router['5'] == 'rate') {
                        $getinvoice = $app->get("invoices", "*", ["id" => $app->xss($router['4'])]);
                        $rate = ($data['invoices'][$router['4']]['payment'] * $postedValue) / 100;
                        $_SESSION['drivers'][$action]['invoices'][$router['4']]['price'] = $rate;
                    }
                    echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công"),]);
                }
            }
        } elseif ($router['3'] == 'completed') {
            $data = $_SESSION['drivers'][$action] ?? [];

            // --- BƯỚC 1: KHỞI TẠO TẤT CẢ CÁC BIẾN ĐỂ TRÁNH LỖI ---
            $error = [];
            $log_data = [];
            $details_to_insert = [];
            $expenditure_data = [];
            $payment_id = null;
            $expenditure_id = null;

            // --- BƯỚC 2: VALIDATION AN TOÀN VỚI CÁC KEY ĐÃ CHUẨN HÓA ---
            if (empty($data['date']) || empty($data['driver_id']) || empty($data['debt']) || empty($data['has']) || empty($data['stores_id'])) {
                $error = ["status" => 'error', 'content' => $jatbi->lang("Vui lòng điền đủ thông tin bắt buộc")];
            }

            if (empty($error)) {
                $getdrive = $app->get("drivers", "*", ["id" => $data['driver_id']]);

                // --- BƯỚC 3: TÁI CẤU TRÚC LOGIC ADD/EDIT ---
                $payment_data = [
                    "user" => $getdrive['user'],
                    "drivers" => $getdrive['id'],
                    "price" => $data['price'] ?? 0,
                    "total" => $data['total'] ?? 0,
                    "ballot" => $data['ballot'],
                    "has" => $data['has'],
                    "debt" => $data['debt'],
                    "date" => $data['date'],
                    "code_group" => $getdrive['code'],
                    "accounts" => $app->getSession("accounts")['id'] ?? 0,
                    "notes" => $data['content'],
                    "stores" => $data['stores_id'],
                ];

                if ($action == 'add') {
                    $payment_data["date_poster"] = date("Y-m-d H:i:s");
                    $payment_data["code"] = strtotime(date("Y-m-d H:i:s"));
                    $app->insert("drivers_payment", $payment_data);
                    $payment_id = $app->id();
                } elseif ($action == 'edit') {
                    $payment_id = $data['order_id'];
                    $app->update("drivers_payment", $payment_data, ["id" => $payment_id]);
                    $app->update("drivers_payment_details", ["deleted" => 1], ["payment" => $payment_id]);
                }

                // --- BƯỚC 4: XỬ LÝ CHI TIẾT HÓA ĐƠN ---
                $invoices = $app->select("invoices", "*", ["deleted" => 0, "date" => $data['date'], "code_group" => $getdrive['code'], "stores" => $data['stores_id'], "cancel[!]" => 2]);
                foreach ($invoices as $invoice) {
                    $invoice_id = $invoice['id'];
                    $payment_val = $data['invoices'][$invoice_id]['payment'] ?? ($invoice['total'] - $invoice['discount_price'] - $invoice['minus']);
                    $rate_val = $data['invoices'][$invoice_id]['rate'] ?? 20;
                    $price_val = $data['invoices'][$invoice_id]['price'] ?? ($payment_val * $rate_val / 100);

                    $details_to_insert[] = [
                        "payment" => $payment_id,
                        "invoices" => $invoice_id,
                        "pay" => $payment_val,
                        "price" => $price_val,
                        "rate" => $rate_val,
                        "notes" => $data['invoices'][$invoice_id]['notes'] ?? '',
                        "user" => $payment_data['user'],
                        "accounts" => $payment_data['accounts'],
                        "date" => $payment_data['date'],
                        "date_poster" => $payment_data['date_poster'] ?? date("Y-m-d H:i:s"),
                    ];
                }
                if (!empty($details_to_insert)) {
                    $app->insert("drivers_payment_details", $details_to_insert);
                }

                // --- BƯỚC 5: XỬ LÝ PHIẾU CHI ---
                $expenditure_data = [
                    "type" => 2,
                    "debt" => $payment_data['debt'],
                    "has" => $payment_data['has'],
                    "price" => '-' . $payment_data['total'],
                    "content" => $payment_data['notes'],
                    "date" => $payment_data['date'],
                    "ballot" => $payment_data['ballot'],
                    "accounts" => $payment_data['user'],
                    "drivers_payment" => $payment_id,
                    "user" => $app->getSession("accounts")['id'] ?? 0,
                    "date_poster" => date("Y-m-d H:i:s"),
                    "stores" => $payment_data['stores'],
                ];
                if ($action == 'add') {
                    $app->insert("expenditure", $expenditure_data);
                    $expenditure_id = $app->id();
                    $app->update("drivers", ["status" => 1], ["id" => $getdrive['id']]);
                } elseif ($action == 'edit') {
                    $app->update("expenditure", $expenditure_data, ["drivers_payment" => $payment_id]);
                    $expenditure_id = $app->get("expenditure", "id", ["drivers_payment" => $payment_id]);
                }

                $log_data = ($action == 'add') ? $payment_data : array_merge(['id' => $payment_id], $payment_data);
                $jatbi->logs('driver-payment', $action, [$log_data, $details_to_insert, $expenditure_data]);
                unset($_SESSION['drivers'][$action]);

                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công"), "print" => "/accountants/expenditure-views/" . ($expenditure_id ?? '')]);
            } else {
                echo json_encode($error);
            }
        }
    });

    //xem chi tiết thanh toán tài xế
    $app->router("/driver-payment-views/{id}", 'GET', function ($vars) use ($app, $jatbi, $template) {

        $vars['data'] = $app->get("drivers_payment", "*", [
            "id" => $vars['id'],
            "deleted" => 0
        ]);

        if (!empty($vars['data'])) {

            $details = $app->select("drivers_payment_details", "*", [
                "payment" => $vars['id'],
                "deleted" => 0
            ]);

            $vars['invoices_details'] = [];
            if (!empty($details)) {

                $invoice_ids = array_column($details, 'invoices');


                $invoices_data = $app->select("invoices", "*", ["id" => $invoice_ids]);
                $invoices_map = array_column($invoices_data, null, 'id');

                $customer_ids = array_column($invoices_data, 'customers');
                $customers = $app->select("customers", ["id", "name"], ["id" => array_unique($customer_ids)]);
                $customer_map = array_column($customers, 'name', 'id');


                foreach ($details as $detail) {
                    $invoice_id = $detail['invoices'];
                    if (isset($invoices_map[$invoice_id])) {
                        $invoice_info = $invoices_map[$invoice_id];

                        $invoice_info['customer_name'] = $customer_map[$invoice_info['customers']] ?? 'N/A';

                        $detail['invoice_data'] = $invoice_info;
                        $vars['invoices_details'][] = $detail;
                    }
                }
            }


            $vars['ballot_code'] = $app->get("ballot_code", "*", ["type" => "invoices"]);

            echo $app->render($template . '/drivers/drivers-payment-views.html', $vars, $jatbi->ajax());
        } else {
            echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
        }
    })->setPermissions(['driver-payment']);

    ob_start();
    $app->router('/driver-payment/driver-excel', 'GET', function ($vars) use ($app, $jatbi, $setting) {
        try {
            $searchValue = $_GET['search']['value'] ?? ($_GET['name'] ?? '');
            $filter_user = $_GET['user'] ?? '';
            $filter_accounts = $_GET['accounts'] ?? '';
            $filter_date = $_GET['date'] ?? '';


            $where = [
                "AND" => [
                    "drivers_payment.deleted" => 0,
                    "drivers_payment.type" => 0,
                ]
            ];


            if (!empty($searchValue)) {
                $where['AND']['OR'] = [
                    'drivers_payment.code[~]' => $searchValue,
                    'drivers_payment.code_group[~]' => $searchValue,
                    'driver_info.name[~]' => $searchValue,
                    'driver_info.phone[~]' => $searchValue,
                ];
            }


            if (!empty($filter_user)) {
                $where['AND']['drivers.user'] = $filter_user;
            }
            if (!empty($filter_accounts)) {
                $where['AND']['drivers_payment.accounts'] = $filter_accounts;
            }

            if (!empty($filter_date)) {
                $dates = explode(' - ', $filter_date);
                if (count($dates) === 2) {
                    $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($dates[0]))));
                    $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($dates[1]))));
                    $where["AND"]["drivers_payment.date[<>]"] = [$date_from, $date_to];
                }
            } else {

                $date_from = date('Y-m-01 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
                $where["AND"]["drivers_payment.date[<>]"] = [$date_from, $date_to];
            }

            $joins = [
                "[>]drivers" => ["drivers" => "id"],
                "[>]accounts (driver_info)" => ["drivers.user" => "id"],
                "[>]accounts (creator_info)" => ["drivers_payment.accounts" => "id"]
            ];
            $columns = [
                'drivers_payment.id',
                'drivers_payment.code',
                'drivers_payment.date',
                'drivers_payment.code_group',
                'drivers_payment.total',
                'driver_info.name(driver_name)',
                'driver_info.phone(driver_phone)',
                'drivers.number_car',
                'drivers.car',
                'drivers.count',
                'drivers.amount',
                'creator_info.name(creator_name)'
            ];
            // Sắp xếp theo ID giảm dần để có dữ liệu mới nhất
            $where["ORDER"] = ["drivers_payment.id" => "DESC"];
            $datas = $app->select("drivers_payment", $joins, $columns, $where);
            if ($datas === false) {
                throw new Exception("Lỗi truy vấn database: " . json_encode($app->error()));
            }
            // --- TẠO FILE EXCEL ---
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('ThanhToanTaiXe');
            $sheet->setCellValue('A1', 'MÃ THANH TOÁN')
                ->setCellValue('B1', 'NGÀY')
                ->setCellValue('C1', 'TÀI XẾ')
                ->setCellValue('D1', 'SỐ XE')
                ->setCellValue('E1', 'LOẠI XE')
                ->setCellValue('F1', 'LỮ HÀNH')
                ->setCellValue('G1', 'SỐ KHÁCH')
                ->setCellValue('H1', 'MÃ ĐOÀN')
                ->setCellValue('I1', 'SỐ TIỀN')
                ->setCellValue('J1', 'TÀI KHOẢN');
            $row_index = 2;
            $sum_total = 0;
            foreach ($datas as $data) {
                $sheet->setCellValue('A' . $row_index, '#' . ($data['code'] ?? ''));
                $sheet->setCellValue('B' . $row_index, $data['date'] ? date('d/m/Y', strtotime($data['date'])) : '');
                // $sheet->setCellValue('B' . $row_index, $jatbi->datetime($data['date'] ?? ''));
                $sheet->setCellValue('C' . $row_index, ($data['driver_name'] ?? '') . ' - ' . ($data['driver_phone'] ?? ''));
                $sheet->setCellValue('D' . $row_index, $data['number_car'] ?? '');
                $sheet->setCellValue('E' . $row_index, $data['car'] ?? '');
                $sheet->setCellValue('F' . $row_index, $data['count'] ?? 0);
                $sheet->setCellValue('G' . $row_index, $data['amount'] ?? 0);
                $sheet->setCellValue('H' . $row_index, $data['code_group'] ?? '');
                $sheet->setCellValue('I' . $row_index, $data['total'] ?? 0);
                $sheet->setCellValue('J' . $row_index, $data['creator_name'] ?? '');
                $sheet->getStyle('I' . $row_index)->getNumberFormat()->setFormatCode('#,##0');
                $sum_total += (float) ($data['total'] ?? 0);
                $row_index++;
            }
            $sheet->setCellValue('H' . $row_index, 'Tổng cộng');
            $sheet->setCellValue('I' . $row_index, $sum_total);
            $sheet->getStyle('I' . $row_index)->getNumberFormat()->setFormatCode('#,##0');
            foreach (range('A', 'J') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
            // --- BƯỚC KIỂM TRA QUAN TRỌNG ---
            $stray_output = ob_get_contents(); // Lấy nội dung đang có trong bộ đệm
            if (!empty(trim($stray_output))) {
                ob_end_clean(); // Xóa bộ đệm để hiển thị lỗi
                echo "<h3>Phát hiện có dữ liệu lạ được xuất ra trước khi tạo file Excel:</h3>";
                echo "<pre>";
                // Hiển thị dữ liệu lạ đó (có thể là lỗi Warning hoặc khoảng trắng)
                var_dump($stray_output);
                echo "</pre>";
                exit;
            }
            // --- KẾT THÚC BƯỚC KIỂM TRA ---
            // --- XUẤT FILE ---
            ob_end_clean(); // Xóa bộ đệm trước khi gửi header
            $file_name = 'thanhtoantaixe_' . date('d-m-Y') . '.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $file_name . '"');
            header('Cache-Control: max-age=0');
            header('Pragma: no-cache');
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
            exit;
        } catch (Exception $e) {
            ob_end_clean(); // Xóa bộ đệm nếu có lỗi
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8'); // Đảm bảo header HTML
            echo "<h1>Có lỗi nghiêm trọng xảy ra!</h1>";
            echo "<h3>Thông báo lỗi:</h3>";
            echo "<p><pre>" . htmlspecialchars($e->getMessage()) . "</pre></p>";
            echo "<h3>Chi tiết (Stack Trace):</h3>";
            echo "<pre>";
            var_dump($e);
            echo "</pre>";
            exit;
        }
    });

    $app->router('/other_commission_costs', ['GET', 'POST'], function ($vars) use ($app, $jatbi,$template ,$setting) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Thanh toán hoa hồng khác");

            $accounts_db = $app->select("accounts", ['id(value)', 'name(text)'], ['deleted' => 0, 'status' => 'A']);
            $vars['accountsList'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $accounts_db);
            echo $app->render($template . '/drivers/other_commission_costs.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';

            $orderName = isset($_POST['order'][0]['column']) ? $_POST['columns'][$_POST['order'][0]['column']]['date'] : 'drivers_payment.date';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

            $filter_user = isset($_POST['user']) ? $_POST['user'] : '';
            $filter_accounts = isset($_POST['accounts']) ? $_POST['accounts'] : '';
            $filter_date = isset($_POST['date']) ? $_POST['date'] : '';

            $joins = [
                "[>]drivers" => ["drivers" => "id"],
                "[>]accounts (user_info)" => ["drivers_payment.user" => "id"],
                "[>]accounts (creator_info)" => ["drivers_payment.accounts" => "id"],
                "[>]expenditure" => ["drivers_payment.id" => "drivers_payment"]
            ];

            $where = [
                "AND" => [
                    "drivers_payment.deleted" => 0,
                    "drivers_payment.type" => 1,
                ]
            ];

            // Thêm điều kiện tìm kiếm
            if (!empty($searchValue)) {
                $where['AND']['OR'] = [
                    'drivers_payment.code[~]' => $searchValue,
                    'user_info.name[~]' => $searchValue,
                    'user_info.phone[~]' => $searchValue,
                ];
            }

            if (!empty($filter_user)) {
                $where['AND']['drivers_payment.user'] = $filter_user;
            }
            if (!empty($filter_accounts)) {
                $where['AND']['drivers_payment.accounts'] = $filter_accounts;
            }

            if (!empty($filter_date)) {
                $date_parts = explode(' - ', $filter_date);
                $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date_parts[0]))));
                $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date_parts[1]))));
            } else {
                $date_from = date('Y-m-d 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }

            $where['AND']['drivers_payment.date[<>]'] = [$date_from, $date_to];

            $count = $app->count("drivers_payment", $joins, "drivers_payment.id", $where);
            $totalSum = $app->sum("drivers_payment", $joins, "drivers_payment.total", $where);

            $where["ORDER"] = [$orderName => strtoupper($orderDir)];
            $where["LIMIT"] = [$start, $length];

            $datas = [];

            $columns = [
                'drivers_payment.id',
                'drivers_payment.code',
                'drivers_payment.date',
                'drivers_payment.total',
                'drivers_payment.code_group',
                'user_info.name(user_name)',
                'user_info.phone(user_phone)',
                'creator_info.name(creator_name)',
                'drivers.number_car',
                'drivers.car',
                'drivers.count',
                'drivers.amount',
                'expenditure.id(id_expenditure)'
            ];


            $app->select("drivers_payment", $joins, $columns, $where, function ($data) use (&$datas, &$totalSum, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "code" => '#' . $data['code'] ?? "",
                    "date" => date('d/m/Y', strtotime($data['date'])),
                    "user" => $data['user_name'] . ' - ' . $data['user_phone'],
                    "number_car" => $data['number_car'] ?? "",
                    "car" => $data['car'] ?? "",
                    "count" => $data['count'] ?? 0,
                    "amount" => number_format($data['amount'] ?? 0),
                    "code_group" => $data['code_group'] ?? 0,
                    "total" => '<strong>' . number_format($data['total']) . '</strong>',
                    "accounts" => $data['creator_name'] ?? '',
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xem"),
                                'action' => ['data-url' => '/drivers/other-commission-costs-views/' . $data['id'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("In"),
                                'action' => ['data-url' => '/accountants/expenditure-views/' . $data['id_expenditure'], 'data-action' => 'modal']
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
                    "sum_total" => number_format(floatval($totalSum))
                ]
            ]);
        }
    })->setPermissions(['other_commission_costs']);

    $app->router("/other-commission-costs-views/{id}", 'GET', function ($vars) use ($app, $jatbi, $template) {
        $id = (int) ($vars['id'] ?? 0);

        $data = $app->get("drivers_payment", "*", [
            "id" => $id,
            "type" => 1,
            "deleted" => 0
        ]);

        if (!$data) {
            return $app->render($template . '/error.html', $vars, $jatbi->ajax());
        }

        $user_ids = array_unique([$data['accounts'], $data['user']]);
        $accounts_map = array_column(
            $app->select("accounts", ["id", "name"], ["id" => $user_ids]),
            'name',
            'id'
        );

        $data['creator_name'] = $accounts_map[$data['accounts']] ?? 'N/A';
        $data['driver_user_name'] = $accounts_map[$data['user']] ?? 'N/A';

        $vars['data'] = $data;

        echo $app->render($template . '/drivers/other_commission_costs-views.html', $vars, $jatbi->ajax());
    })->setPermissions(['other_commission_costs']);

    $app->router('/other_commission_costs-add', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $stores, $accStore) {
        $vars['title'] = $jatbi->lang("Thêm hoa hồng khác");
        $action = 'add';
        if (!isset($_SESSION['rose'][$action]['date'])) {
            $_SESSION['rose'][$action]['date'] = date("Y-m-d");
        }
        if (count($stores) == 1) {
            $_SESSION['rose'][$action]['stores'] = ["id" => $app->get("stores", "id", ["id" => $accStore, "status" => 'A', "deleted" => 0, "ORDER" => ["id" => "ASC"]])];
        }
        $data = [
            "date" => $_SESSION['rose'][$action]['date'],
            "driver" => $_SESSION['rose'][$action]['driver'] ?? "",
            "content" => $_SESSION['rose'][$action]['content'] ?? "",
            "stores" => $_SESSION['rose'][$action]['stores'] ?? "",
            "debt" => $_SESSION['rose'][$action]['debt'] ?? "",
            "has" => $_SESSION['rose'][$action]['has'] ?? "",
            "ballot" => $_SESSION['rose'][$action]['ballot'] ?? "",
            "price" => $_SESSION['rose'][$action]['price'] ?? "",
        ];
        $drivers = $app->select("drivers", "*", [
            "deleted" => 0,
            "status" => '0',
            "date" => $data['date']
        ]);

        $driverOptions = [];

        // thêm dòng rỗng
        $driverOptions[] = [
            "value" => "",
            "text" => ""
        ];

        foreach ($drivers as $driver) {
            $getuser = $app->get("accounts", "*", ["id" => $driver['user']]);
            $driverOptions[] = [
                "value" => $driver['id'],
                "text" => $driver['code'] . " - " . $getuser['name'] . " - " . $getuser['phone'] . " - " . $driver['number_car'],
            ];
        }
        $vars['drivers'] = $driverOptions;
        $accountants = $app->select("accountants_code", "*", ["deleted" => 0, "status" => 'A']);
        $accountantsOptions = [];
        // thêm dòng rỗng
        $accountantsOptions[] = [
            "value" => "",
            "text" => ""
        ];

        foreach ($accountants as $accountant) {
            $accountantsOptions[] = [
                "value" => $accountant['code'],
                "text" => $accountant['code'] . " - " . $accountant['name'],
            ];
        }
        $vars['accountants'] = $accountantsOptions;
        $stores = $app->select("stores", ['id', 'name'], ["deleted" => 0, "status" => "A", "id" => $accStore]);
        $storesOptions = [];
        // thêm dòng rỗng
        if (count($stores) > 1) {
            $storesOptions[] = [
                "value" => "",
                "text" => ""
            ];
        }
        foreach ($stores as $store) {
            $storesOptions[] = [
                "value" => $store['id'],
                "text" => $store['name'],
            ];
        }
        $vars['stores'] = $storesOptions;
        $drive = $app->get("drivers", "*", ["id" => $data['driver']]);
        $vars['data'] = $data;

        echo $app->render($template . '/drivers/other-commission-costs-post.html', $vars, $jatbi->ajax());
    })->setPermissions(['other_commission_costs.add']);

    $app->router('/other_commission_costs-deleted', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa thanh toán hoa hồng");

        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            $boxid = explode(',', $app->xss($_GET['box']));

            $datas = $app->select("drivers_payment", "*", ["id" => $boxid, "deleted" => 0]);

            if (count($datas) > 0) {
                $codes = array_column($datas, 'code');

                // Thực hiện soft delete trên cả 2 bảng
                $app->update("drivers_payment", ["deleted" => 1], ["id" => $boxid]);
                $app->update("expenditure", ["deleted" => 1], ["drivers_payment" => $boxid]);

                // Ghi log theo format chuẩn
                $jatbi->logs('other_commission_costs', 'delete', $datas);

                $jatbi->trash(
                    '/other-commission-costs-restore',
                    "Xóa thanh toán hoa hồng: " . implode(', ', $codes),
                    ["database" => 'drivers_payment', "data" => $boxid]
                );

                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Xóa thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Không tìm thấy dữ liệu để xóa")]);
            }
        }
    })->setPermissions(['other_commission_costs.deleted']);

    $app->router('/driver-payment-add', 'GET', function ($vars) use ($app, $jatbi, $template, $stores, $accStore) {
        $action = 'add';
        $vars['action'] = $action;
        $vars['title'] = $jatbi->lang("Thanh toán tài xế");

        if (empty($_SESSION['drivers'][$action]['date'])) {
            $_SESSION['drivers'][$action]['date'] = date("Y-m-d");
        }
        if (count($stores) == 1) {
            $_SESSION['drivers'][$action]['stores'] = ["id" => $accStore[0] ?? null];
        }
        $data = $_SESSION['drivers'][$action] ?? [];
        $vars['data'] = $data;

        $accountants_data = $app->select(
            "accountants_code",
            ["id", "code", "name"],
            ["deleted" => 0, "status" => 'A']
        );

        $vars['accountants'] = array_map(function ($item) {
            return [
                'value' => $item['id'],
                'text' => $item['code'] . ' - ' . $item['name']
            ];
        }, $accountants_data);

        $drivers_db = $app->select(
            "drivers",
            ["[>]accounts" => ["user" => "id"]],
            [
                "drivers.id",
                "drivers.code",
                "drivers.number_car",
                "accounts.name",
                "accounts.phone"
            ],
            ["drivers.deleted" => 0, "drivers.status" => '0', "drivers.date" => $data['date']]
        );

        // Dùng array_map để định dạng lại mảng
        $formatted_drivers = array_map(function ($driver) {
            return [
                'value' => $driver['id'],
                'text' => $driver['code'] . ' - ' . $driver['name'] . ' - ' . $driver['phone'] . ' - ' . $driver['number_car']
            ];
        }, $drivers_db);

        // Tạo lựa chọn rỗng
        $empty_option = [['value' => '', 'text' => $jatbi->lang('--- Chọn ---')]];

        // Gộp lựa chọn rỗng vào đầu danh sách đã định dạng
        $vars['drivers'] = array_merge($empty_option, $formatted_drivers);

        $invoices = [];
        if (!empty($data['driver'])) {
            $driver = $app->get("drivers", "code", ["id" => $data['driver']]);
            if ($driver) {
                $invoices = $app->select("invoices", "*", ["deleted" => 0, "date" => $data['date'], "code_group" => $driver, "cancel[!]" => 2, "stores" => $accStore]);

                $customer_ids = array_column($invoices, 'customers');
                $customers_map = array_column($app->select("customers", ["id", "name"], ["id" => $customer_ids]), 'name', 'id');

                $total_price = 0;
                foreach ($invoices as &$invoice) {
                    $invoice['customer_name'] = $customers_map[$invoice['customers']] ?? 'N/A';
                    $invoice_id = $invoice['id'];
                    if (empty($data['invoices'][$invoice_id]['payment']))
                        $data['invoices'][$invoice_id]['payment'] = $invoice['total'] - $invoice['discount_price'] - $invoice['minus'];
                    if (empty($data['invoices'][$invoice_id]['rate']))
                        $data['invoices'][$invoice_id]['rate'] = 20;
                    if (empty($data['invoices'][$invoice_id]['price']))
                        $data['invoices'][$invoice_id]['price'] = ($data['invoices'][$invoice_id]['payment'] * $data['invoices'][$invoice_id]['rate']) / 100;

                    $total_price += ($invoice['type'] == 3) ? -(float) $data['invoices'][$invoice_id]['price'] : (float) $data['invoices'][$invoice_id]['price'];
                }
                unset($invoice);

                $_SESSION['drivers'][$action]['total'] = $total_price;
                $data['total'] = $total_price;
            }
        }

        $vars['invoices'] = $invoices;
        $vars['data'] = $data;
        $vars['stores'] = $stores;

        echo $app->render($template . '/drivers/payment-add.html', $vars);
    })->setPermissions(['driver-payment.add']);

    $app->router('/driver-payment-update/{action}/date', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';

        $_SESSION['drivers'][$action]['date'] = $app->xss($_POST['value'] ?? '');
        unset($_SESSION['drivers'][$action]['driver']);

        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/driver-payment/update/{action}/content', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $_SESSION['drivers'][$action]['content'] = $app->xss($_POST['value'] ?? '');
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/driver-payment-update/{action}/driver', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $_SESSION['drivers'][$action]['driver'] = $app->xss($_POST['value'] ?? '');
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/driver-payment-update/{action}/debt', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $_SESSION['drivers'][$action]['debt'] = $app->xss($_POST['value'] ?? '');
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/driver-payment-update/{action}/has', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $_SESSION['drivers'][$action]['has'] = $app->xss($_POST['value'] ?? '');
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/driver-payment-update/{action}/ballot', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $_SESSION['drivers'][$action]['ballot'] = $app->xss($_POST['value'] ?? '');
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/driver-payment-update/{action}/stores', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $data = $app->get("stores", ["id", "name"], ["id" => $app->xss($_POST['value'] ?? 0)]);
        if ($data) {
            $_SESSION['drivers'][$action]['stores'] = ["id" => $data['id'], "name" => $data['name']];
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } else {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại")]);
        }
    });

    $app->router('/driver-payment-update/{action}/price', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $_SESSION['drivers'][$action]['price'] = str_replace(",", "", $app->xss($_POST['value'] ?? ''));
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/driver-payment-update/{action}/invoices/{invoice_id}/{field}', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $invoice_id = $vars['invoice_id'] ?? 0;
        $field = $vars['field'] ?? '';
        $post_value = $app->xss($_POST['value'] ?? '');

        if ($field == 'rate' && (float) $post_value > 100) {
            echo json_encode(['status' => 'error', 'content' => 'Tối đa 100%']);
            return;
        }

        $_SESSION['drivers'][$action]['invoices'][$invoice_id][$field] = str_replace(",", "", $post_value);

        if ($field == 'rate') {
            $payment = (float) ($_SESSION['drivers'][$action]['invoices'][$invoice_id]['payment'] ?? 0);
            $rate = (float) $post_value;
            $_SESSION['drivers'][$action]['invoices'][$invoice_id]['price'] = ($payment * $rate) / 100;
        }

        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/driver-payment-update/{action}/cancel', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        unset($_SESSION['drivers'][$action]);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Đã hủy")]);
    });

    $app->router('/driver-payment-update/{action}/completed', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $data = $_SESSION['drivers'][$action] ?? [];
        $error = [];
        // Validate required fields
        if (empty($data['date']) || empty($data['driver']) || empty($data['debt']) || empty($data['has']) || empty($data['stores']['id'])) {
            $error = ["status" => 'error', 'content' => $jatbi->lang("Lỗi trống")];
        }

        if (empty($error)) {
            // Fetch driver details
            $getdrive = $app->get("drivers", "*", ["id" => $data['driver']]);

            // Prepare payment data
            $payment_data = [
                "user" => $getdrive['user'],
                "drivers" => $getdrive['id'],
                "price" => $data['price'] ?? 0,
                "total" => $data['total'] ?? 0,
                "ballot" => $data['ballot'] ?? '',
                "has" => $data['has'] ?? 0,
                "debt" => $data['debt'] ?? 0,
                "date" => $data['date'],
                "code_group" => $getdrive['code'] ?? '',
                "accounts" => $app->getSession("accounts")['id'] ?? 0,
                "date_poster" => date("Y-m-d H:i:s"),
                "notes" => $data['content'] ?? '',
                "stores" => $data['stores']['id'],
            ];

            $payment_id = null;
            $expenditure_id = null;

            // Handle add or edit action
            if ($action == 'add') {
                $payment_data['code'] = strtotime(date("Y-m-d H:i:s"));
                $app->insert("drivers_payment", $payment_data);
                $payment_id = $app->id();
            } elseif ($action == 'edit') {
                $payment_id = $_SESSION['drivers'][$action]['order'];
                $app->update("drivers_payment", $payment_data, ["id" => $payment_id]);
                $app->update("drivers_payment_details", ["deleted" => 1], ["payment" => $payment_id]);
            }

            // Fetch invoices
            $invoices = $app->select("invoices", "*", [
                "deleted" => 0,
                "date" => $data['date'],
                "code_group" => $getdrive['code'],
                "stores" => $data['stores']['id'],
                "cancel[!]" => 2
            ]);

            $details_logs = [];

            // Process each invoice
            foreach ($invoices as $invoice) {
                $invoice_data = $data['invoices'][$invoice['id']] ?? [];
                $price = $invoice_data['price'] ?? 0;

                if (empty($invoice_data['price'])) {
                    $base_amount = $invoice_data['payment'] ?? ($invoice['total'] - $invoice['discount_price'] - $invoice['minus']);
                    $rate = $invoice_data['rate'] ?? 20;
                    $price = $base_amount * $rate / 100;
                }

                $details = [
                    "payment" => $payment_id,
                    "invoices" => $invoice['id'],
                    "content" => "",
                    "pay" => $invoice_data['payment'] ?? ($invoice['total'] - $invoice['discount_price'] - $invoice['minus']),
                    "price" => $price,
                    "rate" => $invoice_data['rate'] ?? 20,
                    "notes" => $invoice_data['notes'] ?? '',
                    "user" => $payment_data['user'],
                    "accounts" => $payment_data['accounts'],
                    "date" => $payment_data['date'],
                    "date_poster" => $payment_data['date_poster'],
                ];

                $app->insert("drivers_payment_details", $details);
                $details_logs[] = $details;
            }

            // Handle expenditure
            $expenditure = [
                "type" => 2,
                "debt" => $app->xss($payment_data['debt']),
                "has" => $app->xss($payment_data['has']),
                "price" => '-' . $payment_data['total'],
                "content" => $app->xss($payment_data['notes']),
                "date" => $app->xss($payment_data['date']),
                "ballot" => $app->xss($payment_data['ballot']),
                "accounts" => $app->xss($payment_data['user']),
                "drivers_payment" => $payment_id,
                "notes" => $app->xss($payment_data['notes']),
                "user" => $app->getSession("accounts")['id'] ?? 0,
                "date_poster" => date("Y-m-d H:i:s"),
                "stores" => $payment_data['stores'],
            ];
            if ($action == 'add') {
                $app->insert("expenditure", $expenditure);
                $expenditure_id = $app->id();
                $app->update("drivers", ["status" => 1], ["id" => $getdrive['id']]);
            } elseif ($action == 'edit') {
                $app->update("expenditure", $expenditure, ["drivers_payment" => $payment_id]);
            }

            // Log the action
            $jatbi->logs('driver-payment', $action, [$payment_data, $details_logs, $expenditure]);
            unset($_SESSION['drivers'][$action]);

            // Prepare response
            $response = ['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")];
            if ($action == 'add') {
                $response['print'] = "/accountants/expenditure-views/" . $expenditure_id . "";
            }
            echo json_encode($response);
        } else {
            echo json_encode($error);
        }
    });

    $app->router('/driver-add', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $stores) {
        $vars['title'] = $jatbi->lang("Thêm thông tin tài xế");

        if ($app->method() === 'GET') {
            $account_id = $app->getSession("accounts")['id'] ?? 0;
            $account = $app->get("accounts", "*", ["id" => $account_id]);
            $vars['account'] = $account;


            if (($account['type'] ?? null) != 10) {
                $accounts_data = $app->select("accounts", ["id", "name", "phone"], ["deleted" => 0, "status" => 'A', "type" => 10]); // Chỉ lấy các tài khoản có type=10
                $vars['user'] = array_map(function ($acc) {
                    return [
                        'value' => $acc['id'],
                        'text' => $acc['name'] . ' - ' . $acc['phone']
                    ];
                }, $accounts_data);
            }

            // Lấy danh sách cửa hàng
            $vars['stores'] = $stores;

            // --- BƯỚC 3: RENDER GIAO DIỆN ---
            echo $app->render($template . '/drivers/add-driver.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $post = array_map([$app, 'xss'], $_POST);

            $required_fields = ['number_car', 'car', 'count', 'amount', 'code', 'date', 'stores'];
            foreach ($required_fields as $field) {
                if (empty($post[$field])) {
                    echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Vui lòng điền đủ thông tin')]);
                    return;
                }
            }

            $insert = [
                "user" => $post['user'],
                "number_car" => $post['number_car'],
                "car" => $post['car'],
                "count" => $post['count'],
                "amount" => $post['amount'],
                "code" => $post['code'],
                "status" => $post['status'] ?? 0,
                "notes" => $post['notes'],
                "date" => $post['date'],
                "date_poster" => date("Y-m-d H:i:s"),
                "stores" => $post['stores'],
                "accounts" => $app->getSession("accounts")['id'] ?? 0,

            ];
            $app->insert("drivers", $insert);
            $jatbi->logs('drivers', 'add', $insert);

            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Thêm thành công')]);
        }
    })->setPermissions(['driver.add']);

    $app->router('/driver-edit/{id}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $stores) {
        $id = $vars['id'] ?? 0;

        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Sửa thông tin tài xế");

            // Lấy dữ liệu tài xế cần sửa
            $data = $app->get("drivers", "*", ["id" => $id, "deleted" => 0]);
            if (!$data) {
                return $app->render($template . '/error.html', $vars, $jatbi->ajax());
            }
            $vars['data'] = $data;
            $vars['stores'] = $stores;

            // Lấy danh sách tài khoản type=10 để chọn
            $accounts_data = $app->select("accounts", ["id", "name", "phone"], ["deleted" => 0, "status" => 'A', "type" => 10]);
            $vars['user'] = array_map(function ($acc) {
                return ['value' => $acc['id'], 'text' => $acc['name'] . ' - ' . $acc['phone']];
            }, $accounts_data);

            echo $app->render($template . '/drivers/add-driver.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            // --- XỬ LÝ CẬP NHẬT ---
            $app->header(['Content-Type' => 'application/json']);
            $post = array_map([$app, 'xss'], $_POST);

            // Validation
            $required_fields = ['user', 'number_car', 'car', 'count', 'amount', 'code', 'date', 'stores'];
            foreach ($required_fields as $field) {
                if (empty($post[$field])) {
                    echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Vui lòng điền đủ thông tin bắt buộc')]);
                    return;
                }
            }

            // Chuẩn bị dữ liệu update
            $update = [
                "user" => $post['user'],
                "number_car" => $post['number_car'],
                "car" => $post['car'],
                "count" => $post['count'],
                "amount" => $post['amount'],
                "code" => $post['code'],
                "notes" => $post['notes'],
                "date" => $post['date'],
                "stores" => $post['stores'],
                "accounts" => $app->getSession("accounts")['id'] ?? 0,
            ];

            $app->update("drivers", $update, ["id" => $id]);
            $jatbi->logs('drivers', 'edit', $update);

            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
        }
    })->setPermissions(['driver.edit']);

    $app->router("/driver-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa thông tin tài xế");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("drivers", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("drivers", ["deleted" => 1], ["id" => $data['id']]);
                }
                $jatbi->logs('drivers', 'drivers-deleted', $datas);
                $jatbi->trash('/drivers/driver-restore', "Xóa tài xế: ", ["database" => 'drivers', "data" => $boxid]);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['driver.deleted']);


    $app->router('/other_commission_costs-update/add/date', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $action = 'add';
        $_SESSION['rose'][$action]["date"] = $app->xss($_POST['value']);
        unset($_SESSION['rose'][$action]['driver']);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
    });

    $app->router('/other_commission_costs-update/add/driver', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $action = 'add';
        $_SESSION['rose'][$action]["driver"] = $app->xss($_POST['value']);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
    });

    $app->router('/other_commission_costs-update/add/price', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $action = 'add';
        $_SESSION['rose'][$action]["price"] = str_replace(",", "", $app->xss($_POST['value']));
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
    });

    $app->router('/other_commission_costs-update/add/debt', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $action = 'add';
        $_SESSION['rose'][$action]["debt"] = $app->xss($_POST['value']);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
    });

    $app->router('/other_commission_costs-update/add/has', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $action = 'add';
        $_SESSION['rose'][$action]["has"] = $app->xss($_POST['value']);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
    });

    $app->router('/other_commission_costs-update/add/ballot', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $action = 'add';
        $_SESSION['rose'][$action]["ballot"] = $app->xss($_POST['value']);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
    });

    $app->router('/other_commission_costs-update/add/stores', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $action = 'add';
        $data = $app->get("stores", "*", ["id" => $app->xss($_POST['value'])]);
        if ($data > 1) {
            $_SESSION['rose'][$action]['stores'] = [
                "id" => $data['id'],
                "name" => $data['name'],
            ];
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
        } else {
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thất bại')]);
        }
    });

    $app->router('/other_commission_costs-update/add/content', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $action = 'add';
        $_SESSION['rose'][$action]["content"] = $app->xss($_POST['value']);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
    });

    $app->router('/other_commission_costs-update/add/cancel', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        if ($app->method() === 'GET') {
            echo $app->render($template . '/common/comfirm-modal.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $action = 'add';
            unset($_SESSION['rose'][$action]);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
        }
    });


    $app->router('/other_commission_costs-update/add/completed', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        if ($app->method() === 'GET') {
            $vars['url'] = "/drivers/other_commission_costs";
            echo $app->render($template . '/common/comfirm-modal.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $action = 'add';
            $data = $_SESSION['rose'][$action] ?? [];
            $error = [];
            if (
                empty($data['date']) ||
                empty($data['driver']) ||
                empty($data['debt']) ||
                empty($data['has']) ||
                empty($data['stores']['id'] ?? '')
            ) {
                $error = ["status" => 'error', 'content' => $jatbi->lang('Vui lòng điền đủ thông tin')];
            }
            if (count($error) == 0) {
                $code = strtotime(date("Y-m-d H:i:s"));
                $getdrive = $app->get("drivers", "*", ["id" => $data['driver']]);
                $insert = [
                    "user" => $getdrive['user'],
                    "drivers" => $getdrive['id'],
                    "price" => $data['price'],
                    "total" => $data['price'],
                    "ballot" => $data['ballot'],
                    "has" => $data['has'],
                    "debt" => $data['debt'],
                    "date" => $data['date'],
                    "code" => $code,
                    "code_group" => $getdrive['code'],
                    "accounts" => $app->getSession("accounts")['id'] ?? 0,
                    "date_poster" => date("Y-m-d H:i:s"),
                    "notes" => $data['content'],
                    "stores" => $data['stores']['id'],
                    "type" => 1,
                ];
                $app->insert("drivers_payment", $insert);
                $getID = $app->id();
                $expenditure = [
                    "type" => 2,
                    "debt" => $app->xss($insert['debt']),
                    "has" => $app->xss($insert['has']),
                    "price" => '-' . $insert['total'],
                    "content" => $app->xss($insert['notes']),
                    "date" => $app->xss($insert['date']),
                    "ballot" => $app->xss($insert['ballot']),
                    "accounts" => $app->xss($insert['user']),
                    "drivers_payment" => $app->xss($getID),
                    "notes" => $app->xss($insert['notes']),
                    "user" => $app->getSession("accounts")['id'] ?? 0,
                    "date_poster" => date("Y-m-d H:i:s"),
                    "stores" => $data['stores']['id'],
                ];
                $app->insert("expenditure", $expenditure);
                $getEx = $app->id();
                //$database->update("drivers",["status"=>1],["id"=>$getdrive['id']]);
                $jatbi->logs('other_commission_costs', $action, [$insert, $expenditure]);
                unset($_SESSION['rose'][$action]);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $error['content']]);
            }
        }
    });
    
})->middleware(names: 'login');
