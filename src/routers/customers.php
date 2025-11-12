<?php
if (!defined('ECLO'))
    die("Hacking attempt");

use ECLO\App;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$common = $app->getValueData('common');

$stores_json = $app->getCookie('stores') ?? json_encode([]);
$stores_from_cookie = json_decode($stores_json, true);
$app->group($setting['manager'] . "/customers", function ($app) use ($setting, $jatbi, $common, $stores_from_cookie) {
    $account_id = $app->getSession("accounts")['id'] ?? null;
    $account = $account_id ? $app->get("accounts", "*", ["id" => $account_id]) : [];
    $app->router("/customers", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores_from_cookie) {
        $vars['title'] = $jatbi->lang("Khách hàng & NCC");
        if ($app->method() === 'GET') {
            $vars['types'] = $app->select("customers_types", ["id(value)", "name(text)"], ["deleted" => 0]);
            $vars['sources'] = $app->select("sources", ["id(value)", "name(text)"], ["deleted" => 0]);
            $vars['stores'] = $stores_from_cookie;
            $vars['province'] = $app->select("province", ["id(value)", "name(text)"], ["deleted" => 0, "ORDER" => ["name" => "ASC"]]);
            echo $app->render($setting['template'] . '/customers/customers.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $status = isset($_POST['status']) ? [$_POST['status'], $_POST['status']] : '';
            $type = isset($_POST['type']) ? $_POST['type'] : '';

            $source = isset($_POST['source']) ? $_POST['source'] : '';
            $stores = isset($_POST['stores']) ? $_POST['stores'] : '';
            $province = isset($_POST['province']) ? $_POST['province'] : '';
            $date_range = isset($_POST['date']) ? $_POST['date'] : '';
            // $sosanh = $_POST['sosanh'] ?? '';
            // $total = (float) str_replace([','], '', $_POST['total'] ?? 0);
            // $total_to = (float) str_replace([','], '', $_POST['total_to'] ?? 0);
            // $total_from = (float) str_replace([','], '', $_POST['total_from'] ?? 0);


            $where = [
                "AND" => [
                    "OR" => [
                        "customers.name[~]" => $searchValue,
                        "customers.code[~]" => $searchValue,
                        "customers.phone[~]" => $searchValue,
                        "customers.email[~]" => $searchValue,
                    ],
                    "customers.status[<>]" => $status,
                    "customers.deleted" => 0,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];

            if (!empty($type)) {
                $where["AND"]["customers.type"] = $type;
            }
            if (!empty($source)) {
                $where["AND"]["customers.source"] = $source;
            }
            if (!empty($stores)) {
                $where["AND"]["customers.stores"] = $stores;
            }
            if (!empty($province)) {
                $where["AND"]["customers.province"] = $province;
            }

            if (!empty($date_range)) {
                $dates = explode(' - ', $date_range);
                if (count($dates) === 2) {
                    $date_from = date('Y-m-d', strtotime($dates[0]));
                    $date_to = date('Y-m-d', strtotime($dates[1]));
                    $where["AND"]["customers.date[<>]"] = [$date_from, $date_to];
                }
            }

            $count = $app->count("customers", [
                "AND" => $where['AND'],
            ]);

            $datas = [];
            $app->select(
                "customers",
                [
                    "[>]customers_types" => ["type" => "id"],
                    "[>]accounts" => ["user" => "id"],
                    // "[>]invoices_clone" => ["id" => "customers"],
                ],
                [
                    'customers.id',
                    'customers.name',
                    'customers.active',
                    'customers.code',
                    'customers.date',
                    'customers.phone',
                    'customers.status',
                    'customers_types.name (type)',
                    'accounts.name (user)',
                ],
                $where,
                function ($data) use (&$datas, $jatbi, $app) {
                    $invoice_last = $app->get("invoices", ["id", "code", "date_poster"], ["deleted" => 0, "type" => [1, 2], "customers" => $data['id'], "ORDER" => ["id" => "DESC"]]);
                    $datas[] = [
                        "checkbox" => $app->component("box", ["data" => $data['active']]),
                        "id" => $data['id'],
                        "type" => $data['type'],
                        "code" => $data['code'],
                        "date" => $jatbi->datetime($data['date']),
                        "name" => $data['name'],
                        "user" => $data['user'],
                        "phone" => $data['phone'],
                        "order" => '<a class="text-nowrap pjax-load" href="/invoices/invoices-views/' . ($invoice_last['id'] ?? '') . '/">' . ($invoice_last['code'] ?? '') . '' . ($invoice_last['id'] ?? '') . '</a><small class="d-block mt-1">' . ($invoice_last['date_poster'] ?? '') . '</small>',
                        "order" => 1,
                        "products" => $data['phone'],
                        "sum" => '',
                        "status" => $app->component("status", [
                            "url" => "/customers/customers-status/" . $data['active'],
                            "data" => $data['status'],
                            "permission" => ['customers.edit']
                        ]),
                        "action" => $app->component("action", [
                            "button" => [
                                [
                                    'type' => 'link',
                                    'name' => $jatbi->lang("Xem"),
                                    // 'permission' => ['customers'],
                                    'action' => ['href' => '/customers/customers-views/' . $data['active'], 'data-pjax' => '']
                                ],
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Sửa"),
                                    // 'permission' => ['customers.edit'],
                                    'action' => ['data-url' => '/customers/customers-edit/' . $data['active'], 'data-action' => 'modal']
                                ],
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Xóa"),
                                    // 'permission' => ['customers.deleted'],
                                    'action' => ['data-url' => '/customers/customers-deleted?box=' . $data['active'], 'data-action' => 'modal']
                                ],
                            ]
                        ]),
                    ];
                }
            );
            $customer_ids = array_column($datas ?: [], 'id');
            $sales_data = [];
            $products_data = [];

            if (!empty($customer_ids)) {
                $sales_data = $app->select("invoices", [
                    "customers",
                    "total_sales" => App::raw("SUM(CASE WHEN type IN (1,2) AND cancel IN (0,1) THEN payments ELSE 0 END)")
                ], [
                    "customers" => $customer_ids,
                    "GROUP" => "customers"
                ]);
                $products_data = $app->select(
                    "invoices_products",
                    [
                        "[>]products" => ["products" => "id"],
                    ],
                    [
                        "products.id",
                        "products.code",
                        "products.name",
                        "invoices_products.customers(customer_id)"
                    ],
                    [
                        "invoices_products.customers" => $customer_ids,
                        "products.deleted" => 0,
                    ]
                );
            }
            $sales_map = array_column($sales_data, 'total_sales', 'customers');
            $products_by_customer = [];
            foreach ($products_data ?: [] as $product) {
                $products_by_customer[$product['customer_id']][] = $product;
            }
            foreach ($datas as &$customer) {
                $customer['sum'] = number_format($sales_map[$customer['id']] ?? 0);
                $products_list = $products_by_customer[$customer['id']] ?? [];
                $product_strings = array_map(function ($product) {
                    return '<span class="badge text-body">' . $product['code'] . ' - ' . $product['name'] . '</span>';
                }, $products_list);
                $customer['products'] = implode('<br>', $product_strings);
            }
            unset($customer);
            $datas = $datas;
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas ?? [],
            ]);
        }
    })->setPermissions(['customers']);
    ob_start();


    $app->router('/customers-excel', 'GET', function ($vars) use ($app, $jatbi, $setting, $stores_from_cookie) {
        try {
            // --- BƯỚC 1: LẤY VÀ XỬ LÝ CÁC BỘ LỌC TỪ URL ---
            $searchValue = $_GET['search']['value'] ?? '';
            $status = isset($_GET['status']) ? [$_GET['status'], $_GET['status']] : '';
            $type = $_GET['type'] ?? '';
            $source = $_GET['source'] ?? '';
            $stores = $_GET['stores'] ?? '';
            $province = $_GET['province'] ?? '';
            $date_range = $_GET['date'] ?? '';
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 1000;

            // Xây dựng WHERE clause động
            $where = ["AND" => ["customers.deleted" => 0]];

            if (!empty($searchValue)) {
                $where['AND']['OR'] = [
                    "customers.name[~]" => $searchValue,
                    "customers.code[~]" => $searchValue,
                    "customers.phone[~]" => $searchValue,
                    "customers.email[~]" => $searchValue,
                ];
            }
            if ($status !== '') {
                $where['AND']['customers.status'] = $status;
            }
            if (!empty($type)) {
                $where['AND']['customers.type'] = $type;
            }
            if (!empty($source)) {
                $where['AND']['customers.source'] = $source;
            }
            if (!empty($province)) {
                $where['AND']['customers.province'] = $province;
            }
            if ($stores !== null && $stores !== '') {
                $where["AND"]["customers.stores"] = $app->xss($stores);
            } else {
                $accessible_store_ids = array_column($stores_from_cookie, 'value');
                if (!empty($accessible_store_ids)) {
                    $where["AND"]["customers.stores"] = $accessible_store_ids;
                }
            }
            if (!empty($date_range)) {
                $dates = explode(' - ', $date_range);
                if (count($dates) === 2) {
                    $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', $dates[0])));
                    $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', $dates[1])));
                    $where["AND"]["customers.date[<>]"] = [$date_from, $date_to];
                }
            }

            // Thêm giới hạn để giảm bộ nhớ
            $where["LIMIT"] = [0, $limit];
            $where["ORDER"] = ['customers.id' => 'DESC'];

            // --- BƯỚC 2: LẤY DỮ LIỆU CHÍNH ---
            $datas = $app->select("customers", [
                "[>]customers_types" => ["type" => "id"],
                "[>]accounts" => ["user" => "id"],
            ], [
                'customers.id',
                'customers.name',
                'customers.code',
                'customers.phone',
                'customers.email',
                'customers.date',
                'customers.status',
                'customers.active',
                'customers_types.name(type_name)',
                'accounts.name(user_name)',
            ], $where);

            if (empty($datas)) {
                ob_end_clean();
                exit("Không có dữ liệu để xuất file với điều kiện lọc đã chọn.");
            }

            // --- BƯỚC 3: LẤY DỮ LIỆU BỔ SUNG (HÓA ĐƠN, SẢN PHẨM, TỔNG TIỀN) ---
            $customer_ids = array_column($datas, 'id');

            // Lấy thông tin hóa đơn mới nhất
            $invoice_data = $app->select("invoices", [
                "id",
                "code",
                "date_poster",
                "customers"
            ], [
                "deleted" => 0,
                "type" => [1, 2],
                "customers" => $customer_ids,
                "ORDER" => ["id" => "DESC"],
                "LIMIT" => count($customer_ids) // Giới hạn số hóa đơn
            ]);
            $invoice_map = [];
            foreach ($invoice_data as $invoice) {
                if (!isset($invoice_map[$invoice['customers']])) {
                    $invoice_map[$invoice['customers']] = $invoice;
                }
            }

            // Lấy tổng tiền
            $sales_data = $app->select("invoices", [
                "customers",
                "total_sales" => \Medoo\Medoo::raw("SUM(CASE WHEN type IN (1,2) AND cancel IN (0,1) THEN payments ELSE 0 END)")
            ], [
                "customers" => $customer_ids,
                "deleted" => 0,
                "GROUP" => "customers"
            ]);
            $sales_map = array_column($sales_data, 'total_sales', 'customers');

            // Lấy danh sách sản phẩm
            $products_data = $app->select("invoices_products", [
                "[>]products" => ["products" => "id"],
            ], [
                "products.code",
                "products.name",
                "invoices_products.customers(customer_id)"
            ], [
                "invoices_products.customers" => $customer_ids,
                "products.deleted" => 0,
                "LIMIT" => count($customer_ids) * 10 // Giới hạn 10 sản phẩm mỗi khách hàng
            ]);
            $products_by_customer = [];
            foreach ($products_data as $product) {
                $products_by_customer[$product['customer_id']][] = $product['code'] . ' - ' . $product['name'];
            }

            // --- BƯỚC 4: TẠO FILE EXCEL ---
            $spreadsheet = new Spreadsheet();
            $spreadsheet->getDefaultStyle()->getFont()->setSize(12); // Giảm kích thước font
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('KhachHang');

            // Định nghĩa tiêu đề cột
            $headers = [
                'LOẠI',
                'MÃ KHÁCH HÀNG',
                'TÊN',
                'ĐIỆN THOẠI',
                'EMAIL',
                'NGÀY TẠO',
                'NGƯỜI TẠO',
                'HÓA ĐƠN GẦN NHẤT',
                'SẢN PHẨM MUA',
                'TỔNG TIỀN'
            ];
            $sheet->fromArray($headers, null, 'A1');

            // Định dạng tiêu đề
            $sheet->getStyle('A1:J1')->applyFromArray([
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'color' => ['argb' => 'D3D3D3']],
            ]);

            // --- BƯỚC 5: ĐIỀN DỮ LIỆU VÀO EXCEL ---
            $row_index = 2;
            $sum_total = 0;

            foreach ($datas as $data) {
                $invoice = $invoice_map[$data['id']] ?? ['code' => '', 'id' => '', 'date_poster' => ''];
                $products_list = implode("\n", array_slice($products_by_customer[$data['id']] ?? [], 0, 5)); // Giới hạn 5 sản phẩm
                $total_price = $sales_map[$data['id']] ?? 0;
                $sum_total += $total_price;

                $sheet->setCellValueExplicit('A' . $row_index, $data['type_name'] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('B' . $row_index, $data['code'] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('C' . $row_index, $data['name'] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('D' . $row_index, $data['phone'] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('E' . $row_index, $data['email'] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('F' . $row_index, $jatbi->datetime($data['date'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('G' . $row_index, $data['user_name'] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('H' . $row_index, ($invoice['code'] ?? '') . ($invoice['id'] ?? '') . "\n" . ($invoice['date_poster'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('I' . $row_index, $products_list, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue('J' . $row_index, $total_price);

                $row_index++;
            }

            // --- BƯỚC 6: THÊM DÒNG TỔNG ---
            $sheet->setCellValue('I' . $row_index, 'Tổng');
            $sheet->setCellValue('J' . $row_index, $sum_total);
            $sheet->getStyle('I' . $row_index . ':J' . $row_index)->applyFromArray([
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ]);

            // --- BƯỚC 7: ĐỊNH DẠNG CỘT ---
            foreach (range('A', 'J') as $columnID) {
                $sheet->getColumnDimension($columnID)->setAutoSize(true);
            }
            // Định dạng số và tiền tệ
            $sheet->getStyle('J2:J' . $row_index)->getNumberFormat()->setFormatCode('#,##0');

            // Định dạng cột NGÀY TẠO
            $sheet->getStyle('F2:F' . $row_index)->getNumberFormat()->setFormatCode('dd/mm/yyyy hh:mm:ss');

            // Căn chỉnh cột
            $sheet->getStyle('A2:A' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('B2:B' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('C2:C' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('D2:D' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('E2:E' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('F2:F' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('G2:G' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('H2:H' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('I2:I' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('J2:J' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            // --- BƯỚC 8: XUẤT FILE ---
            ob_end_clean();
            $file_name = 'danhsachkhachhang_' . date('d-m-Y') . '.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $file_name . '"');
            header('Cache-Control: max-age=0');
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        } catch (Exception $e) {
            ob_end_clean();
            http_response_code(500);
            exit("Lỗi: " . $e->getMessage());
        }
    })->setPermissions(['customers']);


    $app->router('/customers-add', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores_from_cookie) {
        $vars['title'] = $jatbi->lang("Thêm Khách hàng & NCC");

        if ($app->method() === 'GET') {
            // Chuẩn bị dữ liệu cho các dropdown
            $vars['types'] = $app->select("customers_types", ["id(value)", "name(text)"], ["deleted" => 0]);
            $vars['sources'] = $app->select("sources", ["id(value)", "name(text)"], ["deleted" => 0]);

            // Truyền danh sách stores lấy từ cookie sang cho giao diện
            $vars['stores'] = $stores_from_cookie;
            $vars['fields'] = $app->select("custom_fields", "*", ["data" => "customers", "status" => "A"]);

            echo $app->render($setting['template'] . '/customers/customers-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $post = array_map([$app, 'xss'], $_POST);

            // --- VALIDATION ---
            if (empty($post['name']) || empty($post['type'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Vui lòng điền đủ thông tin bắt buộc')]);
                return;
            }

            // --- CHUẨN BỊ DỮ LIỆU VÀ INSERT ---
            $insert = [
                "code" => $post['code'],
                "payments" => $post['payments'],
                "number" => $post['number'],
                "type" => $post['type'],
                "name" => $post['name'],
                "company" => $post['company'],
                "phone" => $post['phone'],
                "email" => $post['email'],
                "gender" => $post['gender'],
                "address" => $post['address'],
                "province" => $post['province'] ?? 0,
                "district" => $post['district'] ?? 0,
                "ward" => $post['ward'] ?? 0,
                "source" => $post['source'],
                "notes" => $post['notes'],
                "status" => $post['status'] ?? 'A',
                "date" => date("Y-m-d H:i:s"),
                "user" => $app->getSession("accounts")['id'] ?? 0,
                "follow" => $app->getSession("accounts")['id'] ?? 0,
                "active" => $jatbi->active(32),
                "stores" => $post['stores'] ?? $stores_from_cookie,
            ];


            $app->insert("customers", $insert);
            $get_id = $app->id();
            $jatbi->logs('customers', 'add', $insert);

            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Thêm mới thành công')]);
        }
    })->setPermissions(['customers.add']);

    $app->router('/customers-add/{dispatch}/{action}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores_from_cookie) {
        $vars['title'] = $jatbi->lang("Thêm Khách hàng & NCC");

        if ($app->method() === 'GET') {
            // Chuẩn bị dữ liệu cho các dropdown
            $vars['types'] = $app->select("customers_types", ["id(value)", "name(text)"], ["deleted" => 0]);
            $vars['sources'] = $app->select("sources", ["id(value)", "name(text)"], ["deleted" => 0]);

            // Truyền danh sách stores lấy từ cookie sang cho giao diện
            $vars['stores'] = $stores_from_cookie;
            $vars['fields'] = $app->select("custom_fields", "*", ["data" => "customers", "status" => "A"]);

            echo $app->render($setting['template'] . '/customers/customers-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $post = array_map([$app, 'xss'], $_POST);

            // --- VALIDATION ---
            if (empty($post['name']) || empty($post['type'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Vui lòng điền đủ thông tin bắt buộc')]);
                return;
            }

            // --- CHUẨN BỊ DỮ LIỆU VÀ INSERT ---
            $insert = [
                "code" => $post['code'],
                "payments" => $post['payments'],
                "number" => $post['number'],
                "type" => $post['type'],
                "name" => $post['name'],
                "company" => $post['company'],
                "phone" => $post['phone'],
                "email" => $post['email'],
                "gender" => $post['gender'],
                "address" => $post['address'],
                "province" => $post['province'] ?? 0,
                "district" => $post['district'] ?? 0,
                "ward" => $post['ward'] ?? 0,
                "source" => $post['source'],
                "notes" => $post['notes'],
                "status" => $post['status'] ?? 'A',
                "date" => date("Y-m-d H:i:s"),
                "user" => $app->getSession("accounts")['id'] ?? 0,
                "follow" => $app->getSession("accounts")['id'] ?? 0,
                "active" => $jatbi->active(32),
                "stores" => $post['stores'] ?? $stores_from_cookie,
            ];


            $app->insert("customers", $insert);
            $get_id = $app->id();

            $dispatch = $vars['dispatch'];
            $action = $vars['action'];
            $_SESSION[$dispatch][$action]['customers'] = [
                "id" => $get_id,
                "name" => $insert['name'],
                "phone" => $insert['phone'],
                "email" => $insert['email'],
                "card" => $getCard ?? 0,
            ];
            $jatbi->logs('customers', 'add', $insert);

            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Thêm mới thành công')]);
        }
    })->setPermissions(['customers.add']);

    $app->router('/customers-add-v2/{dispatch}/{action}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores_from_cookie) {
        // Set page title for the template
        $vars['title'] = $jatbi->lang("Thêm Khách hàng & NCC");

        if ($app->method() === 'GET') {
            // Prepare data for dropdowns
            $vars['types'] = $app->select("customers_types", ["id(value)", "name(text)"], ["deleted" => 0]) ?? [];
            $vars['sources'] = $app->select("sources", ["id(value)", "name(text)"], ["deleted" => 0]) ?? [];

            // Pass stores from cookie to the template
            $vars['stores'] = $stores_from_cookie;
            $vars['fields'] = $app->select("custom_fields", "*", ["data" => "customers", "status" => "A"]) ?? [];

            // Render the customer add template
            echo $app->render($setting['template'] . '/customers/customers-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            // Set response header to JSON
            $app->header(['Content-Type' => 'application/json']);

            // Sanitize POST data
            $post = array_map([$app, 'xss'], $_POST);

            // --- VALIDATION ---
            if (empty($post['name']) || empty($post['type'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Vui lòng điền đủ thông tin bắt buộc')]);
                return;
            }

            // --- PREPARE DATA AND INSERT ---
            $insert = [
                "code" => $post['code'] ?? '',
                "payments" => $post['payments'] ?? '',
                "number" => $post['number'] ?? '',
                "type" => $post['type'],
                "name" => $post['name'],
                "company" => $post['company'] ?? '',
                "phone" => $post['phone'] ?? '',
                "email" => $post['email'] ?? '',
                "gender" => $post['gender'] ?? '',
                "address" => $post['address'] ?? '',
                "province" => $post['province'] ?? 0,
                "district" => $post['district'] ?? 0,
                "ward" => $post['ward'] ?? 0,
                "source" => $post['source'] ?? '',
                "notes" => $post['notes'] ?? '',
                "status" => $post['status'] ?? 'A',
                "date" => date("Y-m-d H:i:s"),
                "user" => json_decode($app->getCookie("accounts") ?? json_encode([]), true)['id'] ?? 0,
                "follow" => json_decode($app->getCookie("accounts") ?? json_encode([]), true)['id'] ?? 0,
                "active" => $jatbi->active(32),
                "stores" => $post['stores'] ?? $stores_from_cookie,
            ];

            // Insert customer data into the database
            $app->insert("customers", $insert);
            $get_id = $app->id();

            // Retrieve and decode cookie data, default to empty array if unset or invalid
            $dispatch = $vars['dispatch'];
            $action = $vars['action'];
            $sales_session = json_decode($app->getCookie($dispatch) ?? json_encode([]), true) ?? [];

            // Initialize action array if not set
            if (!isset($sales_session[$action])) {
                $sales_session[$action] = [];
            }

            // Update customer data in cookie
            $sales_session[$action]['customers'] = [
                "id" => $get_id,
                "name" => $insert['name'],
                "phone" => $insert['phone'],
                "email" => $insert['email'],
                "card" => $getCard ?? 0, // Note: $getCard is undefined in the original code; retained for compatibility
            ];

            // Save updated data to cookie
            $app->setCookie($dispatch, json_encode($sales_session), time() + $setting['cookie'], '/');

            // Log the customer addition
            $jatbi->logs('customers', 'add', $insert);

            // Return success response
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Thêm mới thành công')]);
        }
    })->setPermissions(['customers.add']);


    $app->router("/customers-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores_from_cookie) {
        $vars['title'] = $jatbi->lang("Sửa Khách hàng");
        $active_id = $vars['id'] ?? '';

        // Lấy dữ liệu khách hàng cần sửa
        $data = $app->get("customers", "*", ["active" => $active_id, "deleted" => 0]);

        if (!$data) {
            // Nếu không tìm thấy khách hàng, hiển thị trang lỗi
            return $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
        }

        if ($app->method() === 'GET') {
            // Chuẩn bị dữ liệu cho các dropdown
            $vars['data'] = $data;
            $vars['types'] = $app->select("customers_types", ["id(value)", "name(text)"], ["deleted" => 0]);
            $vars['sources'] = $app->select("sources", ["id(value)", "name(text)"], ["deleted" => 0]);
            $vars['stores'] = $stores_from_cookie;
            $vars['district'] = $app->select("district", ["id(value)", "name(text)"], ["deleted" => 0, "province" => $data['province'], "ORDER" => ["name" => "ASC"]]);
            $vars['ward'] = $app->select("ward", ["id(value)", "name(text)"], ["deleted" => 0, "district" => $data['district'], "ORDER" => ["name" => "ASC"]]);
            $vars['province'] = $app->select("province", ["id(value)", "name(text)"], ["deleted" => 0, "ORDER" => ["name" => "ASC"]]);
            $vars['fields'] = $app->select("custom_fields", "*", ["data" => "customers", "status" => "A"]);

            // Lấy các giá trị custom field đã lưu
            $field_values = $app->select("custom_field_values", ["field_slug", "value"], ["item_id" => $data['id']]);
            $vars['field_values'] = array_column($field_values, 'value', 'field_slug');

            echo $app->render($setting['template'] . '/customers/customers-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $post = array_map([$app, 'xss'], $_POST);

            // --- VALIDATION ---
            if (empty($post['name']) || empty($post['type'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Vui lòng điền đủ thông tin bắt buộc')]);
                return;
            }

            // --- CHUẨN BỊ DỮ LIỆU VÀ UPDATE ---
            $update = [
                "code" => $post['code'],
                "payments" => $post['payments'],
                "number" => $post['number'],
                "type" => $post['type'],
                "name" => $post['name'],
                "company" => $post['company'],
                "phone" => $post['phone'],
                "email" => $post['email'],
                "gender" => $post['gender'],
                "address" => $post['address'],
                "province" => $post['province'] ?? 0,
                "district" => $post['district'] ?? 0,
                "ward" => $post['ward'] ?? 0,
                "source" => $post['source'],
                "notes" => $post['notes'],
                "status" => $post['status'] ?? 'A',
                "stores" => $post['stores'] ?? $stores_from_cookie,
            ];

            // Cập nhật bảng chính
            $app->update("customers", $update, ["id" => $data['id']]);

            // Cập nhật custom fields (xóa cũ, thêm mới)
            if (isset($post['fields']) && is_array($post['fields'])) {
                $app->delete("custom_field_values", ["item_id" => $data['id']]);
                foreach ($post['fields'] as $slug => $value) {
                    if (!empty($value)) {
                        $app->insert("custom_field_values", [
                            "item_id" => $data['id'],
                            "field_slug" => $slug,
                            "value" => $value
                        ]);
                    }
                }
            }

            $jatbi->logs('customers', 'edit', $update);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
        }
    })->setPermissions(['customers.edit']);

    $app->router("/customers-views/{id}", 'GET', function ($vars) use ($app, $jatbi, $setting, $common) {
        $vars['data'] = $app->get("customers", "*", ["active" => $vars['id'], "deleted" => 0]);
        if ($vars['data'] > 1) {
            $vars['title'] = $jatbi->lang("Chi tiết khách hàng");
            $vars['name'] = $vars['data']['name'];
            $vars['template'] = 'logs';
            $vars['types'] = $app->select("customers_type", "*", ["deleted" => 0, "status" => "A"]);
            $values = $app->select("customers_values", "*", [
                "customers" => $vars['data']['id'],
                "deleted" => 0
            ]);
            $field_values = [];
            $field_cache = [];
            $option_cache = [];
            $connect_cache = [];
            foreach ($values as $v) {
                $field_id = $v['fields'];
                $value_id = $v['value'];
                $is_option = $v['options'] != 0;
                if (!isset($field_cache[$field_id])) {
                    $field_cache[$field_id] = $app->get("customers_fields", "*", ["id" => $field_id]);
                }
                $checkfield = $field_cache[$field_id];
                $field_name = $checkfield['name'];
                $source = $checkfield['source'];

                $field_values[$field_id]['name'] = $field_name;
                if ($source === 'choices') {
                    if (!isset($option_cache[$value_id])) {
                        $option_cache[$value_id] = $app->get("customers_fields_options", "name", ["id" => $value_id]);
                    }
                    $result_value = $option_cache[$value_id];
                } elseif ($source === 'connect') {
                    $connect_table = $checkfield['database'];
                    if ($is_option) {
                        if (!isset($connect_cache[$connect_table][$value_id])) {
                            $connect_cache[$connect_table][$value_id] = $app->get($connect_table, "name", ["id" => $value_id]);
                        }
                        $result_value = $connect_cache[$connect_table][$value_id];
                    } else {
                        $result_value = $app->get($connect_table, "name", ["id" => $value_id]);
                    }
                } else {
                    if ($is_option) {
                        if (!isset($option_cache[$value_id])) {
                            $option_cache[$value_id] = $app->get("customers_fields_options", "name", ["id" => $value_id]);
                        }
                        $result_value = $option_cache[$value_id];
                    } else {
                        $result_value = $value_id;
                    }
                }
                if ($is_option) {
                    $field_values[$field_id]['value'][] = $result_value;
                } else {
                    $field_values[$field_id]['value'] = $result_value;
                }
            }
            $vars['field_values'] = $field_values;
            $vars['common'] = $common['data-field'];
            $vars['logs'] = $app->select("customers_logs", "*", ["customers" => $vars['data']['id'], "ORDER" => ["id" => "DESC"]]);
            echo $app->render($setting['template'] . '/customers/customers-views.html', $vars);
        } else {
            echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
        }
    })->setPermissions(['customers']);

    $app->router("/customers-views/{id}/{type}", 'GET', function ($vars) use ($app, $jatbi, $setting, $common) {
        $vars['data'] = $app->get("customers", "*", ["active" => $vars['id'], "deleted" => 0]);
        if ($vars['data'] > 1) {
            $vars['title'] = $jatbi->lang("Chi tiết khách hàng");
            $vars['name'] = $vars['data']['name'];
            $vars['template'] = $vars['type'];
            $vars['types'] = $app->select("customers_type", "*", ["deleted" => 0, "status" => "A"]);
            $values = $app->select("customers_values", "*", [
                "customers" => $vars['data']['id'],
                "deleted" => 0
            ]);
            $field_values = [];
            $field_cache = [];
            $option_cache = [];
            $connect_cache = [];
            foreach ($values as $v) {
                $field_id = $v['fields'];
                $value_id = $v['value'];
                $is_option = $v['options'] != 0;
                if (!isset($field_cache[$field_id])) {
                    $field_cache[$field_id] = $app->get("customers_fields", "*", ["id" => $field_id]);
                }
                $checkfield = $field_cache[$field_id];
                $field_name = $checkfield['name'];
                $source = $checkfield['source'];

                $field_values[$field_id]['name'] = $field_name;
                if ($source === 'choices') {
                    if (!isset($option_cache[$value_id])) {
                        $option_cache[$value_id] = $app->get("customers_fields_options", "name", ["id" => $value_id]);
                    }
                    $result_value = $option_cache[$value_id];
                } elseif ($source === 'connect') {
                    $connect_table = $checkfield['database'];
                    if ($is_option) {
                        if (!isset($connect_cache[$connect_table][$value_id])) {
                            $connect_cache[$connect_table][$value_id] = $app->get($connect_table, "name", ["id" => $value_id]);
                        }
                        $result_value = $connect_cache[$connect_table][$value_id];
                    } else {
                        $result_value = $app->get($connect_table, "name", ["id" => $value_id]);
                    }
                } else {
                    if ($is_option) {
                        if (!isset($option_cache[$value_id])) {
                            $option_cache[$value_id] = $app->get("customers_fields_options", "name", ["id" => $value_id]);
                        }
                        $result_value = $option_cache[$value_id];
                    } else {
                        $result_value = $value_id;
                    }
                }
                if ($is_option) {
                    $field_values[$field_id]['value'][] = $result_value;
                } else {
                    $field_values[$field_id]['value'] = $result_value;
                }
            }
            $vars['field_values'] = $field_values;
            $vars['common'] = $common['data-field'];
            $vars['logs'] = $app->select("customers_logs", "*", ["customers" => $vars['data']['id'], "ORDER" => ["id" => "DESC"]]);
            echo $app->render($setting['template'] . '/customers/customers-views.html', $vars);
        } else {
            echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
        }
    })->setPermissions(['customers.edit']);

    $app->router("/customers-status/{id}", 'POST', function ($vars) use ($app, $jatbi, $setting) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $data = $app->get("customers", "*", ["active" => $vars['id'], "deleted" => 0]);
        if ($data > 1) {
            if ($data > 1) {
                if ($data['status'] === 'A') {
                    $status = "D";
                } elseif ($data['status'] === 'D') {
                    $status = "A";
                }
                $app->update("customers", ["status" => $status], ["id" => $data['id']]);
                $jatbi->logs('customers', 'customers-status', $data);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại"),]);
            }
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['customers.edit']);

    $app->router("/customers-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa khách hàng");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("customers", "*", ["active" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("customers", ["deleted" => 1], ["id" => $data['id']]);
                    $name[] = $data['name'];
                }
                $jatbi->logs('customers', 'customers-deleted', $datas);
                $jatbi->trash('/customers/customers-restore', "Xóa khách hàng: " . implode(', ', $name), ["database" => 'customers', "data" => $boxid]);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['customers.deleted']);

    $app->router("/customers-restore/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'GET') {
            $vars['data'] = $app->get("trashs", "*", ["active" => $vars['id'], "deleted" => 0]);
            if ($vars['data'] > 1) {
                echo $app->render($setting['template'] . '/common/restore.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $trash = $app->get("trashs", "*", ["active" => $vars['id'], "deleted" => 0]);
            if ($trash > 1) {
                $datas = json_decode($trash['data']);
                foreach ($datas->data as $active) {
                    $app->update("customers", ["deleted" => 0], ["active" => $active]);
                }
                $app->delete("trashs", ["id" => $trash['id']]);
                $jatbi->logs('customers', 'customers-restore', $datas);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['blockip.deleted']);

    $app->router("/config", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Cấu hình");
        $vars['name'] = $jatbi->lang("Loại khách hàng");
        $vars['template'] = 'type';
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/customers/config.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $status = isset($_POST['status']) ? [$_POST['status'], $_POST['status']] : '';
            $where = [
                "AND" => [
                    "OR" => [
                        "name[~]" => $searchValue,
                        "code[~]" => $searchValue,
                    ],
                    "status[<>]" => $status,
                    "deleted" => 0,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)]
            ];
            $count = $app->count("customers_type", [
                "AND" => $where['AND'],
            ]);
            $app->select("customers_type", "*", $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['active']]),
                    "code" => $data['code'],
                    "name" => $data['name'],
                    "status" => $app->component("status", ["url" => "/customers/config-type-status/" . $data['active'], "data" => $data['status'], "permission" => ['customers.config.edit']]),
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['customers.config.edit'],
                                'action' => ['data-url' => '/customers/config-type-edit/' . $data['active'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'permission' => ['customers.config.deleted'],
                                'action' => ['data-url' => '/customers/config-type-deleted?box=' . $data['active'], 'data-action' => 'modal']
                            ],
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
    })->setPermissions(['customers.config']);

    $app->router("/config-type-add", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Thêm loại khách hàng");
        if ($app->method() === 'GET') {
            $vars['data'] = [
                "status" => 'A',
            ];
            echo $app->render($setting['template'] . '/customers/config-type-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            if ($app->xss($_POST['name']) == '' || $app->xss($_POST['status']) == '') {
                $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")];
            }
            if (empty($error)) {
                $insert = [
                    "code" => $app->xss($_POST['code']),
                    "name" => $app->xss($_POST['name']),
                    "status" => $app->xss($_POST['status']),
                    "active" => $jatbi->active(),
                ];
                $app->insert("customers_type", $insert);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
                $jatbi->logs('customers', 'type-add', $insert);
            } else {
                echo json_encode($error);
            }
        }
    })->setPermissions(['customers.config.add']);

    $app->router("/config-type-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Sửa Loại khách hàng");
        if ($app->method() === 'GET') {
            $vars['data'] = $app->get("customers_type", "*", ["active" => $vars['id'], "deleted" => 0]);
            if ($vars['data'] > 1) {
                echo $app->render($setting['template'] . '/customers/config-type-post.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $data = $app->get("customers_type", "*", ["active" => $vars['id'], "deleted" => 0]);
            if ($data > 1) {
                if ($app->xss($_POST['name']) == '' || $app->xss($_POST['status']) == '') {
                    $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")];
                }
                if (empty($error)) {
                    $insert = [
                        "code" => $app->xss($_POST['code']),
                        "name" => $app->xss($_POST['name']),
                        "status" => $app->xss($_POST['status']),
                    ];
                    $app->update("customers_type", $insert, ["id" => $data['id']]);
                    echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
                    $jatbi->logs('customers', 'type-edit', $insert);
                } else {
                    echo json_encode($error);
                }
            } else {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
            }
        }
    })->setPermissions(['customers.config.edit']);

    $app->router("/config-type-status/{id}", 'POST', function ($vars) use ($app, $jatbi, $setting) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $data = $app->get("customers_type", "*", ["active" => $vars['id'], "deleted" => 0]);
        if ($data > 1) {
            if ($data > 1) {
                if ($data['status'] === 'A') {
                    $status = "D";
                } elseif ($data['status'] === 'D') {
                    $status = "A";
                }
                $app->update("customers_type", ["status" => $status], ["id" => $data['id']]);
                $jatbi->logs('customers', 'type-status', $data);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại"),]);
            }
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['customers.config.edit']);

    $app->router("/config-type-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa Loại khách hàng");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("customers_type", "*", ["active" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("customers_type", ["deleted" => 1], ["id" => $data['id']]);
                    $name[] = $data['name'];
                }
                $jatbi->logs('customers', 'type-deleted', $datas);
                $jatbi->trash('/customers/config-type-restore', "Xóa loại khách hàng: " . implode(', ', $name), ["database" => 'customers_type', "data" => $boxid]);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['customers.config.deleted']);

    $app->router("/config-type-restore/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'GET') {
            $vars['data'] = $app->get("trashs", "*", ["active" => $vars['id'], "deleted" => 0]);
            if ($vars['data'] > 1) {
                echo $app->render($setting['template'] . '/common/restore.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $trash = $app->get("trashs", "*", ["active" => $vars['id'], "deleted" => 0]);
            if ($trash > 1) {
                $datas = json_decode($trash['data']);
                foreach ($datas->data as $active) {
                    $app->update("customers_type", ["deleted" => 0], ["active" => $active]);
                }
                $app->delete("trashs", ["id" => $trash['id']]);
                $jatbi->logs('customers', 'type-restore', $datas);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['customers.config.deleted']);

    $app->router("/config/data-field", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $common) {
        $vars['title'] = $jatbi->lang("Cấu hình");
        $vars['name'] = $jatbi->lang("Trường thông tin");
        $vars['template'] = 'data-field';
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/customers/config.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $status = isset($_POST['status']) ? [$_POST['status'], $_POST['status']] : '';
            $where = [
                "AND" => [
                    "OR" => [
                        "name[~]" => $searchValue,
                        "code[~]" => $searchValue,
                    ],
                    "status[<>]" => $status,
                    "deleted" => 0,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)]
            ];
            $count = $app->count("customers_fields", [
                "AND" => $where['AND'],
            ]);
            $app->select("customers_fields", "*", $where, function ($data) use (&$datas, $jatbi, $app, $common) {
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['active']]),
                    "code" => $data['code'],
                    "position" => $data['position'],
                    "name" => $data['name'],
                    "required" => $data['required'] == '0' ? '<span class="text-secondary">' . $jatbi->lang("không kích hoạt") . '</span>' : '<span class="text-danger">' . $jatbi->lang("kích hoạt") . '</span>',
                    "show_table" => $data['show_table'] == '0' ? '<span class="text-secondary">' . $jatbi->lang("không kích hoạt") . '</span>' : '<span class="text-primary">' . $jatbi->lang("kích hoạt") . '</span>',
                    "type" => $common['data-field'][$data['type']]['label'],
                    "status" => $app->component("status", ["url" => "/customers/config-data-field-status/" . $data['active'], "data" => $data['status'], "permission" => ['customers.config.edit']]),
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['customers.config.edit'],
                                'action' => ['data-url' => '/customers/config-data-field-edit/' . $data['active'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'permission' => ['customers.config.deleted'],
                                'action' => ['data-url' => '/customers/config-data-field-deleted?box=' . $data['active'], 'data-action' => 'modal']
                            ],
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
    })->setPermissions(['customers.config']);

    $app->router("/config/field-load/{id}", 'POST', function ($vars) use ($app, $jatbi, $setting, $common) {
        $vars['data'] = $app->get("customers_fields", "*", ["active" => $vars['id'], "deleted" => 0]) ?? ["source" => ''];
        $vars['type'] = $app->xss($_POST['type'] ?? $vars['data']['type']);
        $vars['common'] = $common['data-field'][$vars['type']];
        echo $app->render($setting['template'] . '/customers/config-field-load.html', $vars, $jatbi->ajax());
    })->setPermissions(['customers.config']);

    $app->router("/config/field-load-option/{id}", 'POST', function ($vars) use ($app, $jatbi, $setting, $common) {
        $vars['data'] = $app->get("customers_fields", "*", ["active" => $vars['id'], "deleted" => 0]) ?? ["source" => '', "id" => ''];
        $vars['source'] = $app->xss($_POST['source'] ?? $vars['data']['source']);
        if ($vars['data']) {
            $vars['options'] = $app->select("customers_fields_options", "*", ["deleted" => 0, "fields" => $vars['data']['id']]);
        }
        $vars['database'] = $common['database'];
        echo $app->render($setting['template'] . '/customers/config-field-load-option.html', $vars, $jatbi->ajax());
    })->setPermissions(['customers.config']);

    $app->router("/config-data-field-add", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $common) {
        $vars['title'] = $jatbi->lang("Thêm Trường thông tin");
        if ($app->method() === 'GET') {
            $vars['data'] = [
                "status" => 'A',
                "type" => '',
                "required" => '0',
                "col" => '6',
                "show_table" => '0',
            ];
            foreach ($common['data-field'] as $key => $field) {
                $vars['fields'][$key] = [
                    'text' => $field['label'] ?? '',
                    'type' => $field['type'] ?? '',
                    'value' => $key ?? '',
                    'options' => $field['options'] ?? [],
                ];
            }
            echo $app->render($setting['template'] . '/customers/config-data-field-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            if ($app->xss($_POST['name']) == '' || $app->xss($_POST['status']) == '') {
                $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")];
            }
            if (empty($error)) {
                $insert = [
                    "code" => $app->xss($_POST['code']),
                    "position" => $app->xss($_POST['position']),
                    "name" => $app->xss($_POST['name']),
                    "type" => $app->xss($_POST['type']),
                    "required" => $app->xss($_POST['required']),
                    "notes" => $app->xss($_POST['notes']),
                    "status" => $app->xss($_POST['status']),
                    "show_table" => $app->xss($_POST['show_table']),
                    "col" => $app->xss($_POST['col']),
                    "source" => $app->xss($_POST['source'] ?? ''),
                    "database" => $app->xss($_POST['database'] ?? ''),
                    "default_value" => $app->xss($_POST['default_value'] ?? ''),
                    "active" => $jatbi->active(),
                ];
                $app->insert("customers_fields", $insert);
                $GetID = $app->id();
                $insert_option_log = [];
                if (isset($_POST['options']) && is_array($_POST['options']) && count($_POST['options']) > 0) {
                    foreach ($_POST['options'] as $key => $option) {
                        $insert_option = [
                            "type" => $insert['type'],
                            "fields" => $GetID,
                            "name" => $option,
                            "active" => $jatbi->active(),
                            "status" => 'A',
                        ];
                        $app->insert("customers_fields_options", $insert_option);
                        $insert_option_log[] = $insert_option;
                    }
                }
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
                $jatbi->logs('customers', 'data-field-add', [$insert, $insert_option_log]);
            } else {
                echo json_encode($error);
            }
        }
    })->setPermissions(['customers.config.add']);

    $app->router("/config-data-field-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $common) {
        $vars['title'] = $jatbi->lang("Sửa Trường thông tin");
        if ($app->method() === 'GET') {
            $vars['data'] = $app->get("customers_fields", "*", ["active" => $vars['id'], "deleted" => 0]);
            if ($vars['data'] > 1) {
                foreach ($common['data-field'] as $key => $field) {
                    $vars['fields'][$key] = [
                        'text' => $field['label'] ?? '',
                        'type' => $field['type'] ?? '',
                        'value' => $key ?? '',
                        'options' => $field['options'] ?? [],
                    ];
                }
                echo $app->render($setting['template'] . '/customers/config-data-field-post.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $data = $app->get("customers_fields", "*", ["active" => $vars['id'], "deleted" => 0]);
            if ($data > 1) {
                if ($app->xss($_POST['name']) == '' || $app->xss($_POST['status']) == '') {
                    $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")];
                }
                if (empty($error)) {
                    $insert = [
                        "code" => $app->xss($_POST['code']),
                        "position" => $app->xss($_POST['position']),
                        "name" => $app->xss($_POST['name']),
                        "type" => $app->xss($_POST['type']),
                        "required" => $app->xss($_POST['required']),
                        "notes" => $app->xss($_POST['notes']),
                        "status" => $app->xss($_POST['status']),
                        "show_table" => $app->xss($_POST['show_table']),
                        "col" => $app->xss($_POST['col']),
                        "source" => $app->xss($_POST['source'] ?? ''),
                        "database" => $app->xss($_POST['database'] ?? ''),
                        "default_value" => $app->xss($_POST['default_value'] ?? ''),
                    ];
                    $app->update("customers_fields", $insert, ["id" => $data['id']]);
                    if (isset($_POST['options']) && is_array($_POST['options'])) {
                        // Lấy toàn bộ các option đang có trong DB cho field này
                        $existing_options = $app->select("customers_fields_options", "*", [
                            "deleted" => 0,
                            "fields" => $data['id']
                        ]);

                        // Tạo mảng để tra nhanh
                        $existing_options_map = [];
                        foreach ($existing_options as $opt) {
                            $existing_options_map[$opt['active']] = $opt;
                        }

                        // Danh sách các active hiện có trong POST
                        $posted_keys = array_keys($_POST['options']);

                        // Xử lý cập nhật hoặc thêm mới
                        foreach ($_POST['options'] as $key => $option_name) {
                            if (isset($existing_options_map[$key])) {
                                // Nếu tồn tại thì cập nhật (không cập nhật active)
                                $app->update("customers_fields_options", [
                                    "name" => $option_name,
                                    "type" => $insert['type'],
                                ], [
                                    "id" => $existing_options_map[$key]['id']
                                ]);
                            } else {
                                // Nếu chưa tồn tại thì thêm mới
                                $app->insert("customers_fields_options", [
                                    "type" => $insert['type'],
                                    "fields" => $data['id'],
                                    "name" => $option_name,
                                    "active" => $jatbi->active(),
                                    "status" => 'A'
                                ]);
                            }
                        }

                        // Xử lý xóa những cái đã bị bỏ đi
                        foreach ($existing_options_map as $active => $opt) {
                            if (!in_array($active, $posted_keys)) {
                                $app->update("customers_fields_options", [
                                    "deleted" => 1
                                ], [
                                    "id" => $opt['id']
                                ]);
                            }
                        }
                    }
                    echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
                    $jatbi->logs('customers', 'data-field-edit', $insert);
                } else {
                    echo json_encode($error);
                }
            } else {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
            }
        }
    })->setPermissions(['customers.config.edit']);

    $app->router("/config-data-field-status/{id}", 'POST', function ($vars) use ($app, $jatbi, $setting) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $data = $app->get("customers_fields", "*", ["active" => $vars['id'], "deleted" => 0]);
        if ($data > 1) {
            if ($data > 1) {
                if ($data['status'] === 'A') {
                    $status = "D";
                } elseif ($data['status'] === 'D') {
                    $status = "A";
                }
                $app->update("customers_fields", ["status" => $status], ["id" => $data['id']]);
                $jatbi->logs('customers', 'data-field-status', $data);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại"),]);
            }
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['customers.config.edit']);

    $app->router("/config-data-field-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa Trường thông tin");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("customers_fields", "*", ["active" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("customers_fields", ["deleted" => 1], ["id" => $data['id']]);
                    $name[] = $data['name'];
                }
                $jatbi->logs('customers', 'data-field-deleted', $datas);
                $jatbi->trash('/customers/config-data-field-restore', "Xóa loại khách hàng: " . implode(', ', $name), ["database" => 'customers_fields', "data" => $boxid]);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['customers.config.deleted']);

    $app->router("/config-data-field-restore/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'GET') {
            $vars['data'] = $app->get("trashs", "*", ["active" => $vars['id'], "deleted" => 0]);
            if ($vars['data'] > 1) {
                echo $app->render($setting['template'] . '/common/restore.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $trash = $app->get("trashs", "*", ["active" => $vars['id'], "deleted" => 0]);
            if ($trash > 1) {
                $datas = json_decode($trash['data']);
                foreach ($datas->data as $active) {
                    $app->update("customers_type", ["deleted" => 0], ["active" => $active]);
                }
                $app->delete("trashs", ["id" => $trash['id']]);
                $jatbi->logs('customers', 'type-restore', $datas);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['customers.config.deleted']);

    //sources
    $app->router("/sources", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Nguồn kênh");

        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/customers/sources.html', $vars);
        }

        if ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

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
                        "name[~]" => $searchValue,
                    ],
                    "deleted" => 0,
                    "status[<>]" => $status,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];

            $count = $app->count("sources", [
                "AND" => $where['AND'],
            ]);

            $datas = [];
            $app->select("sources", "*", $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "name" => $data['name'],
                    "notes" => $data['notes'],
                    "status" => $app->component("status", ["url" => "/customers/sources-status/" . $data['id'], "data" => $data['status'], "permission" => ['sources.edit']]),

                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                // 'permission' => ['sources.edit'],
                                'action' => ['data-url' => '/customers/sources-edit/' . $data['id'], 'data-action' => 'modal']
                            ],
                        ]
                    ])

                ];
            });
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas ?? [],
            ]);
        }
    })->setPermissions(['sources']);
    //source

    $app->router('/sources-add', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Thêm nguồn kênh");

        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/customers/sources-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json',]);
            if (empty($_POST['name'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng không để trống")]);
                return;
            }
            $insert = [
                "name" => $app->xss($_POST['name']),
                "notes" => $app->xss($_POST['notes']),
                "status" => $app->xss($_POST['status']),
            ];
            $app->insert("sources", $insert);
            $jatbi->logs('sources', 'add', $insert);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        }
    })->setPermissions(['sources.add']);


    // 3. Sửa thông tin nguồn khách hàng
    $app->router('/sources-edit/{id}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $id = $vars['id'] ?? 0;
        $data = $app->get("sources", "*", ["id" => $id, "deleted" => 0]);

        if (!$data) {
            if ($app->isAjax()) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Không tìm thấy dữ liệu")]);
            } else {
                $app->error(404);
            }
            return;
        }
        $vars['data'] = $data;
        $vars['title'] = $jatbi->lang("Sửa nguồn kênh");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/customers/sources-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            if (empty($_POST['name'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng không để trống")]);
                return;
            }
            $update = [
                "name" => $app->xss($_POST['name']),
                "notes" => $app->xss($_POST['notes']),
                "status" => $app->xss($_POST['status']),
            ];
            $app->update("sources", $update, ["id" => $id]);
            $jatbi->logs('sources', 'edit', $update);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        }
    })->setPermissions(['sources.edit']);



    // 4. Thay đổi trạng thái
    $app->router("/sources-status/{id}", 'POST', function ($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $data = $app->get("sources", "*", ["id" => $vars['id'], "deleted" => 0]);
        if ($data > 1) {
            if ($data > 1) {
                if ($data['status'] === 'A') {
                    $status = "D";
                } elseif ($data['status'] === 'D') {
                    $status = "A";
                }
                $app->update("sources", ["status" => $status], ["id" => $data['id']]);
                $jatbi->logs('customers', 'customers-sources-status', $data);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại"),]);
            }
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['sources.edit']);


    $app->router("/sources-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa nguồn kênh");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("sources", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("sources", ["deleted" => 1], ["id" => $data['id']]);
                    $name[] = $data['name'];
                }
                $jatbi->logs('customers', 'customers-deleted', $datas);
                $jatbi->trash('/customers/customers-restore', "Xóa nguồn kênh: " . implode(', ', $name), ["database" => 'sources', "data" => $boxid]);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['sources.deleted']);

    //customers-card
    $app->router("/customers-card", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Thẻ khách hàng");

        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/customers/customers-card.html', $vars);
        }

        if ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            // Lấy tham số từ DataTables
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : $setting['site_page'] ?? 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $status = isset($_POST['status']) ? [$_POST['status'], $_POST['status']] : '';
            $customers = isset($_POST['customers']) ? $app->xss($_POST['customers']) : '';


            $where = [
                "AND" => [
                    "OR" => [
                        "code[~]" => $searchValue,
                    ],
                    "deleted" => 0,
                    "status[<>]" => $status,
                    //"customers[<>]" => $customers,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];
            if ($customers != '') {
                $where['AND']['customers'] = $customers;
            }

            $count = $app->count("customers_card", [
                "AND" => $where['AND'],
            ]);

            $datas = [];
            $app->select("customers_card", "*", $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "customers" => $data['customers'],
                    "code" => $data['code'],
                    "discount" => $data['discount'],
                    "notes" => $data['notes'],

                    "status" => $app->component("status", ["url" => "/customers/customers-card-status/" . $data['id'], "data" => $data['status'], "permission" => ['customers-card.edit']]),
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                // 'permission' => ['sources.edit'],
                                'action' => ['data-url' => '/customers/customers-card-edit/' . $data['id'], 'data-action' => 'modal']
                            ],
                        ]
                    ]),
                ];
            });
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas ?? [],
            ]);
        }
    })->setPermissions(['customers-card']);


    $app->router('/customers-card-add', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Thêm thẻ khách hàng");

        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/customers/customers-card-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json',]);
            if (empty($_POST['name'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng không để trống")]);
                return;
            }
            $insert = [
                "customers" => $app->xss($_POST['customers']),
                "code" => $app->xss($_POST['code']),
                "discount" => $app->xss($_POST['discount']),
                "notes" => $app->xss($_POST['notes']),
                "status" => $app->xss($_POST['status']),
            ];
            $app->insert("customers_card", $insert);
            $jatbi->logs('customers_card', 'add', $insert);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        }
    })->setPermissions(['customers-card.add']);


    $app->router("/customers-card-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa thẻ khách hàng");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("customers_card", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("customers_card", ["deleted" => 1], ["id" => $data['id']]);
                    $name[] = $data['name'];
                }
                $jatbi->logs('customers', 'customers-deleted', $datas);
                $jatbi->trash('/customers/customers-restore', "Xóa thẻ khách hàng: " . implode(', ', $name), ["database" => 'customers_card', "data" => $boxid]);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['customers-card.deleted']);


    $app->router('/customers-card-edit/{id}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $id = $vars['id'] ?? 0;
        $data = $app->get("customers_card", "*", ["id" => $id, "deleted" => 0]);

        if (!$data) {
            if ($app->isAjax()) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Không tìm thấy dữ liệu")]);
            } else {
                $app->error(404);
            }
            return;
        }
        $vars['data'] = $data;
        $vars['title'] = $jatbi->lang("Sửa Loại khách hàng");

        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/customers/customers-card-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            if (empty($_POST['code']) || empty($_POST['customers'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng không để trống")]);
                return;
            }
            $update = [
                "customers" => $app->xss($_POST['customers']),
                "code" => $app->xss($_POST['code']),
                "discount" => $app->xss($_POST['discount']),
                "notes" => $app->xss($_POST['notes']),
                "status" => $app->xss($_POST['status']),
            ];
            $app->update("customers_card", $update, ["id" => $id]);
            $jatbi->logs('customers_card', 'edit', $update);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        }
    })->setPermissions(['customers-card']);

    $app->router("/customers-card-status/{id}", 'POST', function ($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $data = $app->get("customers_card", "*", ["id" => $vars['id'], "deleted" => 0]);
        if ($data > 1) {
            if ($data > 1) {
                if ($data['status'] === 'A') {
                    $status = "D";
                } elseif ($data['status'] === 'D') {
                    $status = "A";
                }
                $app->update("customers_card", ["status" => $status], ["id" => $data['id']]);
                $jatbi->logs('customers', 'customers-status', $data);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại"),]);
            }
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['customers-card.edit']);


    //route customers-types
    $app->router("/customers-types", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $jatbi->permission('customers-types');
        $vars['title'] = $jatbi->lang("Loại khách hàng");

        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/customers/customers-types.html', $vars);
        }
        if ($app->method() === 'POST') {
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
                    "OR" => [
                        'customers_types.name[~]' => $searchValue,
                        'customers_types.notes[~]' => $searchValue,
                    ],
                    'deleted' => 0,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];

            if ($statusValue != '') {
                $where['AND']['customers_types.status'] = $statusValue;
            }

            $countWhere = [
                "AND" => array_merge(
                    ["customers_types.deleted" => 0],
                    $searchValue != '' ? [
                        "OR" => [

                            'customers_types.name[~]' => $searchValue,
                            'customers_types.notes[~]' => $searchValue,
                        ]
                    ] : [],
                    $statusValue != '' ? ["customers_types.status" => $statusValue] : []
                )
            ];
            $count = $app->count("customers_types", $countWhere);

            $datas = [];
            $app->select("customers_types", "*", $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "name" => ($data['name'] ?? ''),
                    "notes" => ($data['notes'] ?? ''),
                    "status" => $app->component("status", ["url" => "/customers/customers-types-status/" . $data['id'], "data" => $data['status'], "permission" => ['customers-types.edit']]),
                    "action" => ($app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                // 'permission' => ['customers-types.edit'],
                                'action' => ['data-url' => '/customers/customers-types-edit/' . ($data['id'] ?? ''), 'data-action' => 'modal']
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
    })->setPermissions(['customers-types']);

    $app->router("/customers-types-status/{id}", 'POST', function ($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $data = $app->get("customers_types", "*", ["id" => $vars['id'], "deleted" => 0]);
        if ($data > 1) {
            if ($data > 1) {
                if ($data['status'] === 'A') {
                    $status = "D";
                } elseif ($data['status'] === 'D') {
                    $status = "A";
                }
                $app->update("customers_types", ["status" => $status], ["id" => $data['id']]);
                $jatbi->logs('customers', 'customers-types-status', $data);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại"),]);
            }
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['customers-types.edit']);


    $app->router('/customers-types-add', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Thêm Loại khách hàng");

        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/customers/sources-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json',]);
            if (empty($_POST['name'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng không để trống")]);
                return;
            }
            $insert = [
                "name" => $app->xss($_POST['name']),
                "notes" => $app->xss($_POST['notes']),
                "status" => $app->xss($_POST['status']),
            ];
            $app->insert("customers_types", $insert);
            $jatbi->logs('customers_types', 'add', $insert);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        }
    })->setPermissions(['customers-types.add']);

    // 5. Xóa Loại khách hàng
    $app->router("/customers-types-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa loại khách hàng");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("customers_types", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("customers_types", ["deleted" => 1], ["id" => $data['id']]);
                    $name[] = $data['name'];
                }
                $jatbi->logs('customers_types', 'customers_types-deleted', $datas);
                $jatbi->trash('/customers/customers-restore', "Xóa loại khách hàng: " . implode(', ', $name), ["database" => 'customers_types', "data" => $boxid]);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['customers-types.deleted']);



    // 3. Sửa thông tin Loại khách hàn
    $app->router('/customers-types-edit/{id}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $id = $vars['id'] ?? 0;
        $data = $app->get("customers_types", "*", ["id" => $id, "deleted" => 0]);

        if (!$data) {
            if ($app->isAjax()) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Không tìm thấy dữ liệu")]);
            } else {
                $app->error(404);
            }
            return;
        }
        $vars['data'] = $data;
        $vars['title'] = $jatbi->lang("Sửa Loại khách hàng");

        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/customers/sources-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            if (empty($_POST['name'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng không để trống")]);
                return;
            }
            $update = [
                "name" => $app->xss($_POST['name']),
                "notes" => $app->xss($_POST['notes']),
                "status" => $app->xss($_POST['status']),
            ];
            $app->update("customers_types", $update, ["id" => $id]);
            $jatbi->logs('customers_types', 'edit', $update);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        }
    })->setPermissions(['customers-types']);


    // 4. Thay đổi trạng thái
    $app->router('/customers/customers-types/status/{id}', 'POST', function ($vars) use ($app, $jatbi, $setting) {
        $jatbi->permission('customers-types.edit');
        $id = $vars['id'] ?? 0;
        $data = $app->get("customers_types", ["id", "status"], ["id" => $id, "deleted" => 0]);

        if ($data) {
            $new_status = ($data['status'] === 'A') ? 'D' : 'A';
            $app->update("customers_types", ["status" => $new_status], ["id" => $id]);
            $jatbi->logs('customers_types', 'status', ["data" => $data, "status" => $new_status]);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật trạng thái"), 'sound' => $setting['site_sound']]);
        } else {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại")]);
        }
    })->setPermissions(['customers-types.edit']);

    $app->router("/search", ['GET','POST'], function($vars) use ($app, $jatbi,$setting) {
        if ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $searchValue = isset($_POST['keyword']) ? $_POST['keyword'] : '';
            $where = [
                "AND" => [
                    "OR" => [
                        "customers.name[~]" => $searchValue,
                        "customers.phone[~]" => $searchValue,
                        "customers.code[~]" => $searchValue,
                    ],
                    "customers.status" => 'A',
                    "customers.deleted" => 0,
                    "customers.form" => 0,
                ],
                "LIMIT" => 40,
            ];
            $app->select("customers", "*", $where, function ($data) use (&$datas,$jatbi,$app) {
                $datas[] = [
                    "value" => $data['id'],
                    "text" => $data['name'],
                ];
            });
            echo json_encode($datas);
        }
    })->setPermissions(['customers']);
})->middleware('login');
