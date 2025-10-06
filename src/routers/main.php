<?php
if (!defined('ECLO'))
    die("Hacking attempt");

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


$app->group($setting['manager'], function ($app) use ($jatbi, $setting, $common, $stores, $accStore) {


    $app->router('/', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $common, $accStore, $stores) {
        $filters = ($app->method() === 'POST') ? $_POST : $_GET;
        $month = $filters['month'] ?? date('m');
        $year = $filters['year'] ?? date('Y');


        if ($app->method() === 'GET') {
            $account_id = $app->getSession("accounts")['id'] ?? null;
            $account = $account_id ? $app->get("accounts", "*", ["id" => $account_id]) : [];
            if (($account['type'] ?? null) == 10) {
                echo $app->render($setting['template'] . '/pages/home_driver.html', $vars);
                return;
            }
        }

        $all_option = [['value' => '', 'text' => $jatbi->lang('Tất cả')]];

        $vars['stores'] = array_merge($all_option, $stores);
        $vars['units'] = array_merge($all_option, $app->select("units", ["id(value)", "name(text)"], ["deleted" => 0]));
        $vars['categorys'] = array_merge($all_option, $app->select("categorys", ["id(value)", "name(text)"], ["deleted" => 0]));
        $vars['groups'] = array_merge($all_option, $app->select("products_group", ["id(value)", "name(text)"], ["deleted" => 0]));
        $vars['pearls'] = array_merge($all_option, $app->select("pearl", ["id(value)", "name(text)"], ["deleted" => 0]));
        $vars['sizes'] = array_merge($all_option, $app->select("sizes", ["id(value)", "name(text)"], ["deleted" => 0]));
        $vars['colors'] = array_merge($all_option, $app->select("colors", ["id(value)", "name(text)"], ["deleted" => 0]));

        $firstDayOfMonth = strtotime($year . '-' . $month . '-01');
        $date_from = date('Y-m-d 00:00:00', $firstDayOfMonth);
        $date_to = date('Y-m-t 23:59:59', $firstDayOfMonth);
        $prev_month_from = date('Y-m-d 00:00:00', strtotime($date_from . ' -1 month'));

        $vars['dayLastMonth'] = date('t', $firstDayOfMonth);
        $startDay = date('w', $firstDayOfMonth);
        $vars['chartdate'] = range(1, $vars['dayLastMonth']);


        $where = ["AND" => ["type" => [1, 2], "deleted" => 0, "returns" => null, "date[<>]" => [$prev_month_from, $date_to]]];
        if (!empty($accStore)) {
            $where['AND']['stores'] = $accStore;
        }

        $getOrders = $app->select("invoices", ["id", "date", "payments"], $where);

        $stats = [
            'today' => ['count' => 0, 'payment' => 0],
            'yesterday' => ['count' => 0, 'payment' => 0],
            'this_week' => ['count' => 0, 'payment' => 0],
            'this_month' => ['count' => 0, 'payment' => 0],
            'last_month' => ['count' => 0, 'payment' => 0],
            'chart' => array_fill(1, date('t', $firstDayOfMonth), 0)
        ];

        $today_start = strtotime('today 00:00:00');
        $today_end = strtotime('today 23:59:59');
        $yesterday_start = strtotime('yesterday 00:00:00');
        $yesterday_end = strtotime('yesterday 23:59:59');
        $week_start = strtotime('monday this week 00:00:00');

        foreach ($getOrders as $order) {
            $order_time = strtotime($order['date']);
            if ($order_time >= $today_start && $order_time <= $today_end) {
                $stats['today']['count']++;
                $stats['today']['payment'] += $order['payments'];
            }
            if ($order_time >= $yesterday_start && $order_time <= $yesterday_end) {
                $stats['yesterday']['count']++;
                $stats['yesterday']['payment'] += $order['payments'];
            }
            if ($order_time >= $week_start) {
                $stats['this_week']['count']++;
                $stats['this_week']['payment'] += $order['payments'];
            }
            if ($order_time >= $firstDayOfMonth) {
                $stats['this_month']['count']++;
                $stats['this_month']['payment'] += $order['payments'];
                $stats['chart'][(int) date('j', $order_time)]++;
            } else {
                $stats['last_month']['count']++;
                $stats['last_month']['payment'] += $order['payments'];
            }
        }

        $filter_stores = $filters['stores'] ?? ($accStore ?? null);
        $products_inventory_where = ['deleted' => 0];
        if (!empty($filter_stores))
            $products_inventory_where['stores'] = $filter_stores;
        if (!empty($filters['branch']))
            $products_inventory_where['branch'] = $filters['branch'];
        if (!empty($filters['units']))
            $products_inventory_where['units'] = $filters['units'];
        if (!empty($filters['categorys']))
            $products_inventory_where['categorys'] = $filters['categorys'];
        if (!empty($filters['groups']))
            $products_inventory_where['group'] = $filters['groups'];
        if (!empty($filters['statuss'])) {
            $products_inventory_where['status'] = $filters['statuss'];
        } else {
            $products_inventory_where['status'] = ['A', 'D'];
        }

        $ingredients_inventory_where = ['deleted' => 0];
        if (!empty($filters['type']))
            $ingredients_inventory_where['type'] = $filters['type'];
        if (!empty($filters['pearl']))
            $ingredients_inventory_where['pearl'] = $filters['pearl'];
        if (!empty($filters['sizes']))
            $ingredients_inventory_where['sizes'] = $filters['sizes'];
        if (!empty($filters['colors']))
            $ingredients_inventory_where['colors'] = $filters['colors'];
        if (!empty($filters['status'])) {
            $ingredients_inventory_where['status'] = $filters['status'];
        } else {
            $ingredients_inventory_where['status'] = ['A', 'D'];
        }

        $inventory_stats = [
            'products' => [
                'low_stock' => $app->count("products", array_merge($products_inventory_where, ["amount[<]" => 5])),
                'medium_stock' => $app->count("products", array_merge($products_inventory_where, ["amount[<>]" => [5, 10]])),
                'high_stock' => $app->count("products", array_merge($products_inventory_where, ["amount[>]" => 10])),
            ],
            'ingredients' => [
                'low_stock' => $app->count("ingredient", array_merge($ingredients_inventory_where, ["amount[<]" => 5])),
                'medium_stock' => $app->count("ingredient", array_merge($ingredients_inventory_where, ["amount[<>]" => [5, 10]])),
                'high_stock' => $app->count("ingredient", array_merge($ingredients_inventory_where, ["amount[>]" => 10])),
            ]
        ];

        if ($app->method() === 'GET') {
            $vars['stats'] = $stats;
            $vars['inventory_stats'] = $inventory_stats;
            $vars['title'] = $jatbi->lang("Trang chủ");
            $vars['dayLastMonth'] = date('t', $firstDayOfMonth);
            $startDay = date('w', $firstDayOfMonth);
            $vars['startDay'] = ($startDay == 0) ? 7 : $startDay;
            $vars['month'] = $month;
            $vars['year'] = $year;
            $vars['weeks'] = $common['weeks'];
            echo $app->render($setting['template'] . '/pages/home1.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);
            echo json_encode([
                'stats' => $stats,
                'inventory_stats' => $inventory_stats
            ]);
        }
    });

    $app->router('/list-driver', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Danh sách khai báo");

        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/home_driver/list-driver.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $filter_date = $_POST['date'] ?? '';

            $where = [
                "AND" => [
                    "OR" => [
                        "drivers.car[~]" => $searchValue,
                        "drivers.number_car[~]" => $searchValue,
                        "drivers.code[~]" => $searchValue
                    ],
                    "drivers.deleted" => 0,
                    "drivers.user" => $app->getSession("accounts")['id'] ?? 0
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];


            if (!empty($filter_date)) {
                $dates = explode(' - ', $filter_date);
                if (count($dates) === 2) {
                    $date_from = date('Y-m-d', strtotime(str_replace('/', '-', trim($dates[0]))));
                    $date_to = date('Y-m-d', strtotime(str_replace('/', '-', trim($dates[1]))));
                    $where["AND"]["drivers.date[<>]"] = [$date_from, $date_to];
                }
            }

            $count = $app->count("drivers", $where);
            $app->select(
                "drivers",
                "*",
                $where,
                function ($data) use (&$datas, $jatbi, $app) {
                    $status_html = $data['status'] == 0
                        ? '<span class="badge bg-danger">' . $jatbi->lang('Chưa thanh toán') . '</span>'
                        : '<span class="badge bg-success">' . $jatbi->lang('Đã thanh toán') . '</span>';
                    $datas[] = [
                        "checkbox" => $app->component("box", ["data" => $data['id']]),
                        "date" => date('d/m/Y', strtotime($data['date'])),
                        "number_car" => $data['number_car'],
                        "car" => $data['car'],
                        "count" => $data['count'],
                        "amount" => $data['amount'],
                        "code" => $data['code'],
                        "status" => $status_html,
                        "user" => $app->getSession("accounts")['name'] ?? 0,
                    ];
                }
            );

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas ?? [],
            ]);
        }
    });

    $app->router('/add-driver', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores) {
        $vars['title'] = $jatbi->lang("Khai báo thông tin");

        if ($app->method() === 'GET') {
            $account_id = $app->getSession("accounts")['id'] ?? 0;
            $account = $app->get("accounts", "*", ["id" => $account_id]);
            $vars['account'] = $account;


            if (($account['type'] ?? null) != 10) {
                $accounts_data = $app->select("accounts", ["id", "name", "phone"], ["deleted" => 0, "status" => 'A', "type" => 10]);
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
            echo $app->render($setting['template'] . '/home_driver/add-driver.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $post = array_map([$app, 'xss'], $_POST);

            // Validation...
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

            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Khai báo thành công')]);
        }
    });

    $app->router('/list-driver-payment', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $jatbi->permission('drivers.payment.view');
        $vars['title'] = $jatbi->lang("Lịch sử thanh toán");

        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/home_driver/list-driver-payment.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            $draw = intval($_POST['draw'] ?? 0);
            $start = intval($_POST['start'] ?? 0);
            $length = intval($_POST['length'] ?? 10);
            $searchValue = $_POST['search']['value'] ?? '';
            $orderName = $_POST['columns'][intval($_POST['order'][0]['column'] ?? 0)]['name'] ?? 'dp.id';
            $orderDir = $_POST['order'][0]['dir'] ?? 'DESC';
            $filter_date = $_POST['date'] ?? '';

            $where = ["AND" => ["dp.deleted" => 0, "dp.user" => $app->getSession("accounts")['id'] ?? 0]];

            if (!empty($searchValue))
                $where['AND']['dp.code[~]'] = $searchValue;
            if (!empty($filter_date)) {
                $dates = explode(' - ', $filter_date);
                if (count($dates) === 2) {
                    $date_from = date('Y-m-d', strtotime(str_replace('/', '-', trim($dates[0]))));
                    $date_to = date('Y-m-d', strtotime(str_replace('/', '-', trim($dates[1]))));
                    $where["AND"]["dp.date[<>]"] = [$date_from, $date_to];
                }
            }

            $joins = [
                "[<]drivers(d)" => ["drivers" => "id"],
                "[<]accounts(a)" => ["accounts" => "id"],
            ];

            $totalRecords = $app->count("drivers_payment", ["deleted" => 0, "user" => $app->getSession("accounts")['id'] ?? 0]);
            $filteredRecords = $app->count("drivers_payment(dp)", $joins, "dp.id", $where);

            $where["ORDER"] = [$orderName => strtoupper($orderDir)];
            $where["LIMIT"] = [$start, $length];

            $columns = [
                "dp.id",
                "dp.code",
                "dp.date",
                "dp.total",
                "dp.type",
                "d.number_car",
                "d.car",
                "d.count",
                "d.amount(driver_amount)",
                "d.code(driver_code)",
                "a.name(approver_name)"
            ];
            $datas = $app->select("drivers_payment(dp)", $joins, $columns, $where);

            $resultData = [];
            foreach ($datas as $data) {
                $resultData[] = [
                    "code" => '<a data-action="modal" data-url="/drivers/driver-payment-views/' . $data['id'] . '">#' . $data['code'] . '</a>',
                    "date" => date('d/m/Y', strtotime($data['date'])),
                    "number_car" => $data['number_car'],
                    "car" => $data['car'],
                    "count" => $data['count'],
                    "driver_amount" => $data['driver_amount'],
                    "code_group" => $data['code_group'] ?? "",
                    "commission" => ($data['type'] == 1) ? number_format($data['total']) : '',
                    "payment" => ($data['type'] == 0) ? number_format($data['total']) : '',
                    "approver" => $data['approver_name'],
                    "action" => $app->component("action", [
                        "button" => [
                            ['type' => 'button', 'name' => 'Xem', 'icon' => '<i class="ti ti-eye me-2"></i>', 'action' => ['data-url' => '/drivers/driver-payment-views/' . $data['id'], 'data-action' => 'modal']]
                        ]
                    ])
                ];
            }

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $totalRecords,
                "recordsFiltered" => $filteredRecords,
                "data" => $resultData
            ]);
        }
    });
})->middleware('login');


$app->router("::404", 'GET', function ($vars) use ($app, $jatbi, $setting) {
    echo $app->render($setting['template'] . '/pages/error.html', $vars, $jatbi->ajax());
});
