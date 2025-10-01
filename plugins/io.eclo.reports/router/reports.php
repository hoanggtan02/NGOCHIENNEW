<?php
if (!defined('ECLO'))
    die("Hacking attempt");

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\IOFactory;
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
$app->group($setting['manager'] . "/reports", function ($app) use ($jatbi, $setting, $accStore, $stores, $template) {
    $app->router("/revenue_personnels", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $accStore, $stores, $template) {
        $vars['title'] = $jatbi->lang("Doanh thu nhân viên");

        if ($app->method() === 'GET') {

            array_unshift($stores, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars['stores'] = $stores;


            echo $app->render($template . '/reports/revenue-personnels.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? $app->xss($_POST['search']['value']) : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';


            $date_string = isset($_POST['date']) ? $app->xss($_POST['date']) : '';
            $store = isset($_POST['stores']) ? $app->xss($_POST['stores']) : $accStore;


            if ($date_string) {
                $date = explode('-', $date_string);
                $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date[0]))));
                $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date[1]))));
            } else {
                $date_from = date('Y-m-01 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }


            $invoice_ids = $app->select("invoices", "id", [
                "deleted" => 0,
                "date[<>]" => [$date_from, $date_to],
                "cancel" => [0, 1],
            ]);


            $personnel_ids = $app->select("invoices_personnels", "personnels", [
                "deleted" => 0,
                "price[>]" => 0,
                "invoices" => $invoice_ids,
            ]);
            $personnelsIds = $app->select("invoices_personnels", [
                "[>]invoices" => ["invoices" => "id"]
            ], "invoices_personnels.personnels", [
                "invoices_personnels.deleted" => 0,
                "invoices_personnels.price[>]" => 0,
                "invoices.deleted" => 0,
                "invoices.date[<>]" => [$date_from, $date_to]
            ]);

            $where = [
                "AND" => [
                    "OR" => [
                        "name[~]" => $searchValue ?: '%',
                        "code[~]" => $searchValue ?: '%',
                    ],
                    "id" => $personnelsIds,
                    "deleted" => 0,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => ["$orderName" => strtoupper($orderDir)],
            ];


            if ($store != "") {
                $where['AND']['personnels.stores'] = $store;
            }


            $count = $app->count("personnels", ["AND" => $where['AND']]);


            $all_stores = $app->select("stores", ["id", "name"], ["deleted" => 0]);
            $stores_map = array_column($all_stores, 'name', 'id');


            $invoices_personnels = $app->select("invoices_personnels", [
                "personnels",
                "invoices",
                "price",
            ], [
                "deleted" => 0,
                "price[>]" => 0,
                "invoices" => $invoice_ids,
            ]);


            $inpers_by_personnel = [];
            foreach ($invoices_personnels as $ip) {
                $inpers_by_personnel[$ip['personnels']][] = $ip;
            }


            $invoices = $app->select("invoices", [
                "id",
                "type",
                "discount_price",
                "minus",
                "discount_customers_price",
                "discount",
            ], [
                "id" => $invoice_ids,
            ]);


            $invoices_map = array_column($invoices, null, 'id');



            $datas = [];
            $doanhthu = [];
            $chietkhau = [];
            $tralai = [];
            $giamtru = [];
            $sl = [];
            $doanhthuthuan = [];

            $app->select("personnels", [
                "id",
                "code",
                "name",
                "stores",
            ], $where, function ($data) use (&$datas, &$doanhthu, &$chietkhau, &$tralai, &$giamtru, &$sl, &$doanhthuthuan, $inpers_by_personnel, $invoices_map, $stores_map) {
                $personnel_doanhthu = 0;
                $personnel_chietkhau = 0;
                $personnel_tralai = 0;
                $personnel_giamtru = 0;
                $personnel_sl = 0;

                $inpers = $inpers_by_personnel[$data['id']] ?? [];
                foreach ($inpers as $perso) {
                    $invoice = $invoices_map[$perso['invoices']] ?? null;
                    if ($invoice) {
                        if (in_array($invoice['type'], [1, 2])) {
                            $personnel_doanhthu += (float) $perso['price'];
                            $personnel_chietkhau += (float) ($invoice['discount_price'] ?? 0) + (float) ($invoice['minus'] ?? 0) + (float) ($invoice['discount_customers_price'] ?? 0);
                            $personnel_giamtru += (float) ($invoice['discount'] ?? 0);
                            $personnel_sl += 1;
                        } elseif ($invoice['type'] == 3) {
                            $personnel_tralai += (float) $perso['price'];
                        }
                    }
                }

                $personnel_giamtru_percent = $personnel_sl > 0 ? ($personnel_giamtru / $personnel_sl) : 0;
                $personnel_doanhthuthuan = $personnel_doanhthu - $personnel_tralai;

                $doanhthu[$data['id']] = $personnel_doanhthu;
                $chietkhau[$data['id']] = $personnel_chietkhau;
                $tralai[$data['id']] = $personnel_tralai;
                $giamtru[$data['id']] = $personnel_giamtru_percent;
                $doanhthuthuan[$data['id']] = $personnel_doanhthuthuan;
                $sl[$data['id']] = $personnel_sl;

                $datas[] = [
                    "code" => $data['code'],
                    "name" => $data['name'],
                    "doanh_so_ban" => number_format($personnel_doanhthu),
                    "chiet_khau" => number_format($personnel_chietkhau),
                    "gia_tri_tra_lai" => number_format($personnel_tralai),
                    "gia_tri_giam_gia" => is_nan($personnel_giamtru_percent) ? '0%' : number_format($personnel_giamtru_percent) . '%',
                    "doanh_thu_thuan" => number_format($personnel_doanhthuthuan),
                    "cua_hang" => $stores_map[$data['stores']] ?? '',
                ];
            });


            $totals = [
                "total_doanhthu_page" => number_format(array_sum($doanhthu)),
                "total_chietkhau_page" => number_format(array_sum($chietkhau)),
                "total_tralai_page" => number_format(array_sum($tralai)),
                "total_doanhthuthuan_page" => number_format(array_sum($doanhthuthuan)),
            ];


            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas,
                "footerData" => $totals,
            ]);
        }
    })->setPermissions(['revenue_personnels']);

    $app->router('/revenue_personnels-excel', 'GET', function ($vars) use ($app, $jatbi, $setting, $accStore) {
        try {
            // --- BƯỚC 1: LẤY VÀ XỬ LÝ CÁC BỘ LỌC TỪ URL ---
            $date_string = $_GET['date'] ?? '';
            $store = $_GET['stores'] ?? $accStore;
            $searchValue = $_GET['search']['value'] ?? '';

            if (!empty($date_string)) {
                $date = explode(' - ', $date_string);
                $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date[0]))));
                $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date[1]))));
            } else {
                $date_from = date('Y-m-01 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }

            // --- BƯỚC 2: LẤY DỮ LIỆU (Mô phỏng chính xác logic của route POST) ---

            // 2.1. Lấy tất cả ID hóa đơn trong khoảng thời gian (KHÔNG lọc theo cửa hàng)
            $invoice_ids = $app->select("invoices", "id", [
                "deleted" => 0,
                "date[<>]" => [$date_from, $date_to],
                "cancel" => [0, 1],
            ]);
            if (empty($invoice_ids)) {
                exit("Không có giao dịch nào trong khoảng thời gian đã chọn.");
            }

            // 2.2. Lấy ID của tất cả nhân viên có giao dịch trong kỳ
            $personnelsIds = $app->select("invoices_personnels", [
                "[>]invoices" => ["invoices" => "id"]
            ], "invoices_personnels.personnels", [
                "invoices_personnels.deleted" => 0,
                "invoices_personnels.price[>]" => 0,
                "invoices.deleted" => 0,
                "invoices.date[<>]" => [$date_from, $date_to]
            ]);
            if (empty($personnelsIds)) {
                exit("Không có nhân viên nào có doanh thu trong kỳ này.");
            }

            // 2.3. Xây dựng điều kiện để lấy danh sách nhân viên cuối cùng
            $where_personnels = [
                "AND" => [
                    "id" => $personnelsIds,
                    "deleted" => 0,
                ]
            ];
            if (!empty($store)) {
                $where_personnels['AND']['stores'] = $store;
            }
            if (!empty($searchValue)) {
                $where_personnels['AND']['OR'] = [
                    "name[~]" => $searchValue,
                    "code[~]" => $searchValue,
                ];
            }

            // Lấy danh sách nhân viên sẽ hiển thị (không có LIMIT và ORDER)
            $personnels_to_display = $app->select("personnels", ["id", "code", "name", "stores"], $where_personnels);

            if (empty($personnels_to_display)) {
                exit("Không có nhân viên nào phù hợp với bộ lọc của bạn.");
            }

            // 2.4. Lấy TẤT CẢ dữ liệu cần thiết để tính toán
            $all_stores = $app->select("stores", ["id", "name"], ["deleted" => 0]);
            $stores_map = array_column($all_stores, 'name', 'id');

            $invoices_personnels_data = $app->select("invoices_personnels", ["personnels", "invoices", "price"], [
                "deleted" => 0,
                "price[>]" => 0,
                "invoices" => $invoice_ids,
            ]);

            $inpers_by_personnel = [];
            foreach ($invoices_personnels_data as $ip) {
                $inpers_by_personnel[$ip['personnels']][] = $ip;
            }

            $invoices_data = $app->select("invoices", ["id", "type", "discount_price", "minus", "discount_customers_price", "discount"], ["id" => $invoice_ids]);
            $invoices_map = array_column($invoices_data, null, 'id');

            // --- BƯỚC 3: XỬ LÝ VÀ TÍNH TOÁN DỮ LIỆU ---
            $report_data = [];
            foreach ($personnels_to_display as $personnel) {
                $personnel_doanhthu = 0;
                $personnel_chietkhau = 0;
                $personnel_tralai = 0;
                $personnel_giamtru = 0;
                $personnel_sl = 0;

                $inpers = $inpers_by_personnel[$personnel['id']] ?? [];
                foreach ($inpers as $perso) {
                    $invoice = $invoices_map[$perso['invoices']] ?? null;
                    if ($invoice) {
                        if (in_array($invoice['type'], [1, 2])) {
                            $personnel_doanhthu += (float) $perso['price'];
                            $personnel_chietkhau += (float) ($invoice['discount_price'] ?? 0) + (float) ($invoice['minus'] ?? 0) + (float) ($invoice['discount_customers_price'] ?? 0);
                            $personnel_giamtru += (float) ($invoice['discount'] ?? 0);
                            $personnel_sl += 1;
                        } elseif ($invoice['type'] == 3) {
                            $personnel_tralai += (float) $perso['price'];
                        }
                    }
                }

                $personnel_giamtru_percent = $personnel_sl > 0 ? ($personnel_giamtru / $personnel_sl) : 0;
                $personnel_doanhthuthuan = $personnel_doanhthu - $personnel_tralai;

                $report_data[] = [
                    'code' => $personnel['code'],
                    'name' => $personnel['name'],
                    'stores' => $stores_map[$personnel['stores']] ?? '',
                    'doanhthu' => $personnel_doanhthu,
                    'chietkhau' => $personnel_chietkhau,
                    'tralai' => $personnel_tralai,
                    'giamtru_percent' => $personnel_giamtru_percent,
                    'doanhthuthuan' => $personnel_doanhthuthuan,
                ];
            }

            // --- BƯỚC 4: TẠO VÀ XUẤT FILE EXCEL ---
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('DoanhThuNhanVien');
            $headers = ['MÃ NV', 'TÊN NV', 'DOANH SỐ BÁN', 'CHIẾT KHẤU', 'GIÁ TRỊ TRẢ LẠI', 'GIÁ TRỊ GIẢM GIÁ (%)', 'DOANH THU THUẦN', 'CỬA HÀNG'];
            $sheet->fromArray($headers, null, 'A1');
            $sheet->getStyle('A1:H1')->getFont()->setBold(true);

            $rowIndex = 2;
            foreach ($report_data as $data) {
                $sheet->setCellValue('A' . $rowIndex, $data['code']);
                $sheet->setCellValue('B' . $rowIndex, $data['name']);
                $sheet->setCellValue('C' . $rowIndex, $data['doanhthu']);
                $sheet->setCellValue('D' . $rowIndex, $data['chietkhau']);
                $sheet->setCellValue('E' . $rowIndex, $data['tralai']);
                $sheet->setCellValue('F' . $rowIndex, $data['giamtru_percent']);
                $sheet->setCellValue('G' . $rowIndex, $data['doanhthuthuan']);
                $sheet->setCellValue('H' . $rowIndex, $data['stores']);
                $rowIndex++;
            }

            // Định dạng và tính tổng
            $lastRow = $rowIndex - 1;
            if ($lastRow >= 2) {
                $sheet->getStyle('C2:E' . $lastRow)->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('G2:G' . $lastRow)->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('F2:F' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00"%"');

                $totalRow = $rowIndex;
                $sheet->getStyle('B' . $totalRow . ':G' . $totalRow)->getFont()->setBold(true);
                $sheet->setCellValue('B' . $totalRow, 'Tổng cộng');
                $sheet->setCellValue('C' . $totalRow, array_sum(array_column($report_data, 'doanhthu')))->getStyle('C' . $totalRow)->getNumberFormat()->setFormatCode('#,##0');
                $sheet->setCellValue('D' . $totalRow, array_sum(array_column($report_data, 'chietkhau')))->getStyle('D' . $totalRow)->getNumberFormat()->setFormatCode('#,##0');
                $sheet->setCellValue('E' . $totalRow, array_sum(array_column($report_data, 'tralai')))->getStyle('E' . $totalRow)->getNumberFormat()->setFormatCode('#,##0');
                $sheet->setCellValue('G' . $totalRow, array_sum(array_column($report_data, 'doanhthuthuan')))->getStyle('G' . $totalRow)->getNumberFormat()->setFormatCode('#,##0');
            }

            foreach (range('A', 'H') as $columnID) {
                $sheet->getColumnDimension($columnID)->setAutoSize(true);
            }

            ob_end_clean();
            $file_name = 'doanh-thu-nhan-vien_' . date('d-m-Y') . '.xls';
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="' . $file_name . '"');
            header('Cache-Control: max-age=0');
            $writer = new Xls($spreadsheet);
            $writer->save('php://output');
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            exit("Lỗi: " . $e->getMessage());
        }
    })->setPermissions(['revenue_personnels']);
    // cong no
    $app->router('/liabilities', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $accStore, $stores, $template) {
        $vars['title'] = $jatbi->lang("Công nợ");

        if ($app->method() === 'GET') {
            // Thêm tùy chọn "Tất cả" vào danh sách cửa hàng
            array_unshift($stores, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars['stores'] = $stores;

            // Lấy danh sách tài khoản
            $accounts = $app->select("accounts", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A']);
            array_unshift($accounts, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars['accounts'] = $accounts;

            // Lấy danh sách khách hàng
            $customers = $app->select("customers", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A']);
            array_unshift($customers, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars['customers'] = $customers;

            // Lấy danh sách trạng thái hóa đơn
            $Status_invoices = array_map(function ($status, $key) {
                return [
                    'value' => $status['id'] ?? $key,
                    'text' => $status['name'] ?? $status
                ];
            }, $setting['Status_invoices'] ?? [], array_keys($setting['Status_invoices'] ?? []));
            array_unshift($Status_invoices, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars['Status_invoices'] = $Status_invoices;

            // Render giao diện
            echo $app->render($template . '/reports/liabilities.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            // Lấy các tham số từ DataTables
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? $app->xss($_POST['search']['value']) : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

            // Lấy các tham số lọc
            $user = isset($_POST['user']) ? $app->xss($_POST['user']) : '';
            $customers = isset($_POST['customers']) ? $app->xss($_POST['customers']) : '';
            $status = isset($_POST['status']) ? $app->xss($_POST['status']) : '';
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

            $store = empty($_POST['stores']) ? $accStore : $app->xss($_POST['stores']);

            // Điều kiện lọc cho công nợ
            $where = [
                "AND" => [
                    "invoices.code[~]" => $searchValue,
                    "invoices.date[<>]" => [$date_from, $date_to],
                    "invoices.deleted" => 0,
                    "invoices.cancel" => [0, 1],
                    "invoices.status" => 2,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => ["invoices.id" => strtoupper($orderDir)],
            ];

            // Điều kiện lọc cho đầu kỳ
            $where_start = [
                "AND" => [
                    "invoices.code[~]" => $searchValue,
                    "invoices.date[<]" => $date_from,
                    "invoices.deleted" => 0,
                    "invoices.cancel" => [0, 1],
                    "invoices.status" => 2,
                ],
            ];
            if (!empty($store)) {
                $where_start['AND']['invoices.stores'] = $store;
                $where['AND']['invoices.stores'] = $store;
            }

            if (!empty($status)) {
                $where_start['AND']['invoices.status'] = $status;
                $where['AND']['invoices.status'] = $status;
            }

            if (!empty($user)) {
                $where_start['AND']['invoices.user'] = $user;
                $where['AND']['invoices.user'] = $user;
            }

            if (!empty($customers)) {
                $where_start['AND']['invoices.customers'] = $customers;
                $where['AND']['invoices.customers'] = $customers;
            }

            // Đếm tổng số bản ghi
            $count = $app->count("invoices", ["AND" => $where['AND']]);

            $getStart = $app->select("invoices", ["id", "code", "customers", "total", "minus", "discount", "surcharge", "transport", "payments", "prepay", "status", "date", "user", "stores", 'type'], [
                "AND" => $where_start['AND'],
            ]);

            $getDatas = $app->select("invoices", ["id", "code", "customers", "total", "minus", "discount", "surcharge", "transport", "payments", "prepay", "date", "user", "stores", 'type'], [
                "AND" => $where['AND'],
            ]);

            // Khởi tạo mảng để tránh lỗi array_sum(null)
            $total = [
                'total'     => [],
                'discount'  => [],
                'minus'     => [],
                'surcharge' => [],
                'transport' => [],
                'prepay'    => [],
                'payments'  => []
            ];

            foreach (($getDatas ?? []) as $value) {
                $sign = ($value['type'] == 3) ? -1 : 1;
                $total['total'][]     = $sign * (float)($value['total'] ?? 0);
                $total['discount'][]  = $sign * (float)($value['discount'] ?? 0);
                $total['minus'][]     = $sign * (float)($value['minus'] ?? 0);
                $total['surcharge'][] = $sign * (float)($value['surcharge'] ?? 0);
                $total['transport'][] = $sign * (float)($value['transport'] ?? 0);
                $total['prepay'][]    = $sign * (float)($value['prepay'] ?? 0);
                $total['payments'][]  = $sign * (float)($value['payments'] ?? 0);
            }

            // Khởi tạo mảng cho đầu kỳ
            $total_start = [
                'total'     => [],
                'discount'  => [],
                'minus'     => [],
                'surcharge' => [],
                'transport' => [],
                'prepay'    => [],
                'payments'  => []
            ];

            foreach (($getStart ?? []) as $start) {
                $ok = ($start['type'] == 3) ? -1 : 1;
                $total_start['total'][]     = $ok * (float)($start['total'] ?? 0);
                $total_start['discount'][]  = $ok * (float)($start['discount'] ?? 0);
                $total_start['minus'][]     = $ok * (float)($start['minus'] ?? 0);
                $total_start['surcharge'][] = $ok * (float)($start['surcharge'] ?? 0);
                $total_start['transport'][] = $ok * (float)($start['transport'] ?? 0);
                $total_start['prepay'][]    = $ok * (float)($start['prepay'] ?? 0);
                $total_start['payments'][]  = $ok * (float)($start['payments'] ?? 0);
            }
            // Dữ liệu hiển thị
            $datas = [];
            $app->select("invoices", [
                "id",
                "code",
                "customers",
                "total",
                "minus",
                "discount",
                "surcharge",
                "transport",
                "payments",
                "prepay",
                "date",
                "user",
                "stores",
                'type'
            ], $where, function ($data) use (&$datas, $app, $jatbi, $setting) {

                $datas[] = [
                    "code" => '<a class="text-nowrap pjax-load" href="/invoices/invoices-views/' . $data['id'] . '/">#' . ($setting['ballot_code']['invoices'] ?? '') . '-' . ($data['code'] ?? '') . $data['id'] . '</a>',
                    "customer_name" => $app->get("customers", "name", ["id" => $data['customers']]),
                    "total" => $data['type'] == 3 ? '-' . number_format(abs($data['total'] ?? 0)) : number_format($data['total'] ?? 0),
                    "minus" => number_format($data['minus']),
                    "discount" => number_format($data['discount']),
                    "surcharge" => number_format($data['surcharge']),
                    "transport" => number_format($data['transport']),
                    "payments" => $data['type'] == 3 ? '-' . number_format(abs($data['payments'] ?? 0)) : number_format($data['payments'] ?? 0),
                    "prepay" => $data['type'] == 3 ? '-' . number_format(abs($data['prepay'] ?? 0)) : number_format($data['prepay'] ?? 0),
                    "remaining" => '<a href="#!" class="modal-url text-danger" data-url="/invoices/invoices-prepay/' . $data['id'] . '/">' .
                        ($data['type'] == 3
                            ? '-' . number_format(abs(($data['payments'] ?? 0) - ($data['prepay'] ?? 0)))
                            : number_format(($data['payments'] ?? 0) - ($data['prepay'] ?? 0))
                        ) .
                        '</a>',
                    "status" => !empty($data['status']) && isset($setting['Status_invoices'][$data['status']])
                        ? '<span class="fw-bold text-' . ($setting['Status_invoices'][$data['status']]['color'] ?? '') . '">'
                        . ($setting['Status_invoices'][$data['status']]['name'] ?? '') .
                        '</span>'
                        : '',
                    "date" => date($setting['site_datetime'] ?? "d/m/Y H:i:s", strtotime($data['date'])),
                    "user_name" => $app->get("accounts", "name", ["id" => $data['user']]),
                    "store_name" => $app->get("stores", "name", ["id" => $data['stores']]),
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'link',
                                'name' => $jatbi->lang("Xem"),
                                'action' => ['href' => '/invoices/invoices-views/' . $data['id'], 'data-pjax' => '']
                            ],
                        ]
                    ]),
                ];
            });

            // Tổng hợp dữ liệu footer
            $totals = [
                "page_total" => number_format(array_sum($total['total'])),
                "page_minus" => number_format(array_sum($total['minus'])),
                "page_discount" => number_format(array_sum($total['discount'])),
                "page_surcharge" => number_format(array_sum($total['surcharge'])),
                "page_transport" => number_format(array_sum($total['transport'])),
                "page_payments" => number_format(array_sum($total['payments'])),
                "page_prepay" => number_format(array_sum($total['prepay'])),
                "page_remaining" => number_format(array_sum($total['payments']) - array_sum($total['prepay'])),
                "page_total_dauky" => number_format(array_sum($total_start['total'])),
                "page_minus_dauky" => number_format(array_sum($total_start['minus'])),
                "page_discount_dauky" => number_format(array_sum($total_start['discount'])),
                "page_surcharge_dauky" => number_format(array_sum($total_start['surcharge'])),
                "page_transport_dauky" => number_format(array_sum($total_start['transport'])),
                "page_payments_dauky" => number_format(array_sum($total_start['payments'])),
                "page_prepay_dauky" => number_format(array_sum($total_start['prepay'])),
                "page_remaining_dauky" => number_format(array_sum($total_start['payments']) - array_sum($total_start['prepay'])),
            ];

            // Trả về dữ liệu JSON cho DataTables
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas ?? [],
                "footerData" => $totals,
            ]);
        }
    })->setPermissions(['liabilities']);

    $app->router('/liabilities-excel', 'GET', function ($vars) use ($app, $jatbi, $setting, $accStore) {
        try {

            $user = $_GET['user'] ?? '';
            $customers = $_GET['customers'] ?? '';
            $status = $_GET['status'] ?? '';
            $date_string = $_GET['date'] ?? '';
            $store = $_GET['stores'] ?? $accStore;
            $searchValue = $_GET['search']['value'] ?? '';

            if (!empty($date_string)) {
                $date = explode(' - ', $date_string);
                $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date[0]))));
                $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date[1]))));
            } else {
                $date_from = date('Y-m-01 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }


            $where = ["AND" => [
                "invoices.deleted" => 0,
                "invoices.cancel" => [0, 1],
                "invoices.status" => 2,
                "invoices.date[<>]" => [$date_from, $date_to],
            ]];


            $where_start = ["AND" => [
                "invoices.deleted" => 0,
                "invoices.cancel" => [0, 1],
                "invoices.status" => 2,
                "invoices.date[<]" => $date_from,
            ]];


            if (!empty($searchValue)) {
                $where['AND']['invoices.code[~]'] = $searchValue;
                $where_start['AND']['invoices.code[~]'] = $searchValue;
            }
            if ($status !== '') {
                $where['AND']['invoices.status'] = $status;
                $where_start['AND']['invoices.status'] = $status;
            }
            if (!empty($user)) {
                $where['AND']['invoices.user'] = $user;
                $where_start['AND']['invoices.user'] = $user;
            }
            if (!empty($customers)) {
                $where['AND']['invoices.customers'] = $customers;
                $where_start['AND']['invoices.customers'] = $customers;
            }
            if (!empty($store)) {
                $where['AND']['invoices.stores'] = $store;
                $where_start['AND']['invoices.stores'] = $store;
            }


            $start_data = $app->select("invoices", "*", $where_start);


            $liabilities_data = $app->select("invoices", "*", $where);


            $customers_map = array_column($app->select("customers", ["id", "name"]), 'name', 'id');
            $accounts_map = array_column($app->select("accounts", ["id", "name"]), 'name', 'id');
            $stores_map = array_column($app->select("stores", ["id", "name"]), 'name', 'id');



            // Tính tổng đầu kỳ
            $total_start = ['total' => 0, 'minus' => 0, 'discount' => 0, 'surcharge' => 0, 'transport' => 0, 'prepay' => 0, 'payments' => 0];
            foreach ($start_data as $start) {
                $sign = ($start['type'] == 3) ? -1 : 1;
                $total_start['total'] += $sign * (float) ($start['total'] ?? 0);
                $total_start['minus'] += $sign * (float) ($start['minus'] ?? 0);
                $total_start['discount'] += $sign * (float) ($start['discount'] ?? 0);
                $total_start['surcharge'] += $sign * (float) ($start['surcharge'] ?? 0);
                $total_start['transport'] += $sign * (float) ($start['transport'] ?? 0);
                $total_start['prepay'] += $sign * (float) ($start['prepay'] ?? 0);
                $total_start['payments'] += $sign * (float) ($start['payments'] ?? 0);
            }

            // Tính tổng trong kỳ
            $total_in_period = ['total' => 0, 'minus' => 0, 'discount' => 0, 'surcharge' => 0, 'transport' => 0, 'prepay' => 0, 'payments' => 0];
            foreach ($liabilities_data as $data) {
                $sign = ($data['type'] == 3) ? -1 : 1;
                $total_in_period['total'] += $sign * (float) ($data['total'] ?? 0);
                $total_in_period['minus'] += $sign * (float) ($data['minus'] ?? 0);
                $total_in_period['discount'] += $sign * (float) ($data['discount'] ?? 0);
                $total_in_period['surcharge'] += $sign * (float) ($data['surcharge'] ?? 0);
                $total_in_period['transport'] += $sign * (float) ($data['transport'] ?? 0);
                $total_in_period['prepay'] += $sign * (float) ($data['prepay'] ?? 0);
                $total_in_period['payments'] += $sign * (float) ($data['payments'] ?? 0);
            }


            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('CongNo');

            $headers = ['MÃ PHIẾU', 'KHÁCH HÀNG', 'TỔNG TIỀN', 'GIẢM TRỪ', 'GIẢM GIÁ', 'PHỤ THU', 'VẬN CHUYỂN', 'THANH TOÁN', 'ĐÃ THANH TOÁN', 'CÒN LẠI', 'TRẠNG THÁI', 'NGÀY', 'TÀI KHOẢN', 'CỬA HÀNG'];
            $sheet->fromArray($headers, null, 'A1');


            $sheet->setCellValue('B2', 'Đầu kỳ');
            $sheet->setCellValue('C2', number_format($total_start['total']));
            $sheet->setCellValue('D2', number_format($total_start['minus']));
            $sheet->setCellValue('E2', number_format($total_start['discount']));
            $sheet->setCellValue('F2', number_format($total_start['surcharge']));
            $sheet->setCellValue('G2', number_format($total_start['transport']));
            $sheet->setCellValue('H2', number_format($total_start['payments']));
            $sheet->setCellValue('I2', number_format($total_start['prepay']));
            $sheet->setCellValue('J2', number_format($total_start['payments'] - $total_start['prepay']));

            // Ghi dữ liệu chi tiết trong kỳ
            $rowIndex = 2;
            foreach ($liabilities_data as $data) {
                $rowIndex++;
                $sign = ($data['type'] == 3) ? -1 : 1;
                $remaining = (float)$data['payments'] - (float)$data['prepay'];

                $sheet->setCellValue('A' . $rowIndex, '#' . ($ballot_code['invoices'] ?? 'HD') . $data['code'] . $data['id']);
                $sheet->setCellValue('B' . $rowIndex, $customers_map[$data['customers']] ?? '');
                $sheet->setCellValue('C' . $rowIndex, number_format(abs((float)($data['total'] ?? 0))));
                $sheet->setCellValue('D' . $rowIndex, number_format($sign * (float)($data['minus'] ?? 0)));
                $sheet->setCellValue('E' . $rowIndex, number_format($sign * (float)($data['discount'] ?? 0)));
                $sheet->setCellValue('F' . $rowIndex, number_format($sign * (float)($data['surcharge'] ?? 0)));
                $sheet->setCellValue('G' . $rowIndex, number_format($sign * (float)($data['transport'] ?? 0)));
                $sheet->setCellValue('H' . $rowIndex, number_format(abs((float)($data['payments'] ?? 0))));
                $sheet->setCellValue('I' . $rowIndex, number_format($sign * (float)($data['prepay'] ?? 0)));
                $sheet->setCellValue('J' . $rowIndex, number_format(abs($remaining)));
                $sheet->setCellValue('K' . $rowIndex, $Status_invoices[$data['status']]['name'] ?? '');
                $sheet->setCellValue('L' . $rowIndex, date($setting['site_date'] ?? 'd/m/Y', strtotime($data['date'])));
                $sheet->setCellValue('M' . $rowIndex, $accounts_map[$data['user']] ?? '');
                $sheet->setCellValue('N' . $rowIndex, $stores_map[$data['stores']] ?? '');
            }

            // Ghi dòng "Tổng cộng" TRONG KỲ
            $totalRowIndex = $rowIndex + 1;
            $sheet->setCellValue('B' . $totalRowIndex, 'Tổng cộng trong kỳ');
            $sheet->setCellValue('C' . $totalRowIndex, number_format($total_in_period['total']));
            $sheet->setCellValue('D' . $totalRowIndex, number_format($total_in_period['minus']));
            $sheet->setCellValue('E' . $totalRowIndex, number_format($total_in_period['discount']));
            $sheet->setCellValue('F' . $totalRowIndex, number_format($total_in_period['surcharge']));
            $sheet->setCellValue('G' . $totalRowIndex, number_format($total_in_period['transport']));
            $sheet->setCellValue('H' . $totalRowIndex, number_format($total_in_period['payments']));
            $sheet->setCellValue('I' . $totalRowIndex, number_format($total_in_period['prepay']));
            $sheet->setCellValue('J' . $totalRowIndex, number_format($total_in_period['payments'] - $total_in_period['prepay']));

            // Tự động giãn cột
            foreach (range('A', 'N') as $columnID) {
                $sheet->getColumnDimension($columnID)->setAutoSize(true);
            }

            // --- BƯỚC 6: XUẤT FILE ---
            ob_end_clean();
            $file_name = 'cong-no-' . date('d-m-Y') . '.xls';
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="' . $file_name . '"');
            header('Cache-Control: max-age=0');
            $writer = new Xls($spreadsheet);
            $writer->save('php://output');
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            exit("Lỗi: " . $e->getMessage());
        }
    })->setPermissions(['liabilities']);


    $app->router("/revenue", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $accStore, $stores, $template) {
        $vars['title'] = $jatbi->lang("Báo cáo doanh thu");

        if ($app->method() === 'GET') {
            if (count($stores) > 1) {
                array_unshift($stores, [
                    'value' => '',
                    'text' => $jatbi->lang('Tất cả')
                ]);
            }
            $vars['stores'] = $stores;

            $branchs = $accStore == 0
                ? $app->select("branch", ["id (value)", "name (text)"], ["status" => 'A', "deleted" => 0, "stores" => $stores[0]['id']])
                : $app->select("branch", ["id (value)", "name (text)"], ["status" => 'A', "deleted" => 0, "stores" => $accStore]);
            array_unshift($branchs, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars['branchs'] = $branchs;

            $accounts = $app->select("accounts", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A']);
            array_unshift($accounts, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars['accounts'] = $accounts;

            $customers = $app->select("customers", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A']);
            array_unshift($customers, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars['customers'] = $customers;

            $personnels = $app->select("personnels", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A', "stores" => $accStore]);
            array_unshift($personnels, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars['personnels'] = $personnels;

            $Status_invoices = array_map(function ($status, $key) {
                return [
                    'value' => $status['id'] ?? $key,
                    'text' => $status['name'] ?? $status
                ];
            }, $setting['Status_invoices'] ?? [], array_keys($setting['Status_invoices'] ?? []));
            array_unshift($Status_invoices, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars['Status_invoices'] = $Status_invoices;

            echo $app->render($template . '/reports/revenue.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? $app->xss($_POST['search']['value']) : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

            $user = isset($_POST['user']) ? $app->xss($_POST['user']) : '';
            $customers = isset($_POST['customers']) ? $app->xss($_POST['customers']) : '';
            $status = isset($_POST['status']) ? $app->xss($_POST['status']) : '';
            $personnels = isset($_POST['personnels']) ? $app->xss($_POST['personnels']) : '';
            $branchss = isset($_POST['branchss']) ? $app->xss($_POST['branchss']) : '';
            $date_string = isset($_POST['date']) ? $app->xss($_POST['date']) : '';

            if ($date_string) {
                $date = explode('-', $date_string);
                $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date[0]))));
                $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date[1]))));
            } else {
                $date_from = date('Y-m-01 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }
            $store = isset($_POST['stores']) ? $app->xss($_POST['stores']) : $accStore;

            $where = [
                "AND" => [
                    "invoices.id[<>]" => $searchValue ? [$searchValue, $searchValue] : '',
                    "invoices.status[<>]" => $status ? [$status, $status] : '',
                    "invoices.user[<>]" => $user ? [$user, $user] : '',
                    "invoices.customers[<>]" => $customers ? [$customers, $customers] : '',
                    "invoices.date[<>]" => [$date_from, $date_to],
                    "invoices.deleted" => 0,
                    "invoices.cancel" => [0, 1],
                    "invoices.status" => [1, 2],
                    "invoices.branch[<>]" => $branchss ? [$branchss, $branchss] : '',
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];

            if ($store != "") {
                $where['AND']['invoices.stores'] = $store;
            }
            if ($personnels) {
                $where['AND']['invoices.id'] = $app->select("invoices_personnels", "invoices", [
                    "personnels[<>]" => [$personnels, $personnels],
                    "deleted" => 0,
                ]);
                $where['AND']['invoices.lock_or_unlock'] = 0;
            } else {
                $where['AND']['invoices.lock_or_unlock'] = [0, 1];
            }
            // var_dump($where['AND']['invoices.stores']);
            $count = $app->count("invoices", ["AND" => $where['AND']]);

            $customers_map = [];
            $accounts_map = [];
            $stores_map = [];
            $branch_map = [];
            $personnels_map = [];

            $all_customers = $app->select("customers", ["id", "name"], ["deleted" => 0, "status" => 'A']);
            foreach ($all_customers as $c) {
                $customers_map[$c['id']] = $c['name'];
            }

            $all_accounts = $app->select("accounts", ["id", "name"], ["deleted" => 0, "status" => 'A']);
            foreach ($all_accounts as $a) {
                $accounts_map[$a['id']] = $a['name'];
            }

            $all_stores = $app->select("stores", ["id", "name"], ["deleted" => 0]);
            foreach ($all_stores as $s) {
                $stores_map[$s['id']] = $s['name'];
            }

            $all_branches = $app->select("branch", ["id", "name"], ["status" => 'A', "deleted" => 0]);
            foreach ($all_branches as $b) {
                $branch_map[$b['id']] = $b['name'];
            }

            $all_personnels = $app->select("personnels", ["id", "name"], ["deleted" => 0, "status" => 'A', "stores" => $accStore]);
            foreach ($all_personnels as $p) {
                $personnels_map[$p['id']] = $p['name'];
            }

            $invoices_ids = $app->select("invoices", ["id"], ["AND" => $where['AND']]);
            $invoices_ids_list = array_column($invoices_ids, 'id');
            $invoices_personnels = $invoices_ids_list ? $app->select("invoices_personnels", ["invoices", "personnels"], ["invoices" => $invoices_ids_list, "deleted" => 0]) : [];
            $personnels_by_invoice = [];
            foreach ($invoices_personnels as $ip) {
                $personnels_by_invoice[$ip['invoices']][] = $ip['personnels'];
            }

            $returns_ids = $app->select("invoices", ["returns"], ["AND" => array_merge($where['AND'], ["type" => 3])]);
            $returns_ids_list = array_filter(array_column($returns_ids, 'returns'));
            $returns_data = $returns_ids_list ? $app->select("invoices", ["id", "payments", "prepay"], ["id" => $returns_ids_list, "type" => [1, 2], "deleted" => 0]) : [];
            $returns_map = [];
            foreach ($returns_data as $r) {
                $returns_map[$r['id']] = $r;
            }

            // Tính tổng toàn cục
            $all_data = $app->select("invoices", [
                "id",
                "type",
                "code",
                "total",
                "minus",
                "discount",
                "payments",
                "prepay",
                "status",
                "date",
                "user",
                "customers",
                "stores",
                "branch",
                "returns"
            ], [
                "AND" => $where['AND']
            ]);

            $global_tong = 0;
            $global_minus = 0;
            $global_discount = 0;
            $global_sum_type = 0;
            $global_sum_typereturn = 0;
            $global_sum_prepay = 0;
            $global_sum_prepayreturn = 0;
            $global_tongconlai = 0;
            $global_tongdattmua = 0;
            $global_tongtheoloc = 0;

            $global_total = ['total' => [], 'discount_price' => [], 'discount' => [], 'minus' => [], 'surcharge' => [], 'transport' => [], 'prepay' => [], 'payments' => []];
            $global_totalreturn = ['total' => [], 'discount_price' => [], 'minus' => [], 'surcharge' => [], 'transport' => [], 'prepay' => [], 'payments' => []];

            foreach ($all_data as $data) {
                $type = $data['type'] == 3 ? 0 : (float) ($data['payments'] ?? 0);
                $type_return = $data['type'] == 3 ? (float) ($data['payments'] ?? 0) : 0;
                $prepay = $data['type'] == 3 ? 0 : (float) ($data['prepay'] ?? 0);
                $prepay_return = $data['type'] == 3 ? (float) ($data['prepay'] ?? 0) : 0;

                $global_tong += $data['type'] == 3 ? 0 : (float) ($data['total'] ?? 0);
                $global_minus += (float) ($data['minus'] ?? 0);
                $global_discount += $data['type'] == 3 ? 0 : (float) ($data['discount'] ?? 0);
                $global_sum_type += $type;
                $global_sum_typereturn += $type_return;
                $global_sum_prepayreturn += $prepay_return;

                if ($data['type'] == 3) {
                    $getinvoi = isset($data['returns']) && isset($returns_map[$data['returns']]) ? $returns_map[$data['returns']] : null;
                    if ($getinvoi) {
                        $tttmua = $data['prepay'] > 0 ? -(float) $data['payments'] : 0;
                        $conlai2 = (float) ($data['payments'] - $data['prepay']);
                    } else {
                        $tttmua = 0;
                        $conlai2 = 0;
                    }
                } else {
                    $tttmua = $prepay == 0 ? 0 : $prepay;
                    $conlai2 = 0;
                }

                $global_sum_prepay += $tttmua;
                $global_tongconlai += $type - $prepay - $conlai2;
                $global_tongdattmua += $tttmua;
                $increment = $data['type'] == 3 ? 0 : ((float) ($data['payments'] ?? 0) - (float) ($data['prepay'] ?? 0) - $conlai2);
                $global_tongtheoloc += $increment;

                if ($data['type'] != 3) {
                    $global_total['total'][] = (float) ($data['total'] ?? 0);
                    $global_total['discount_price'][] = (float) ($data['discount_price'] ?? 0);
                    $global_total['discount'][] = (float) ($data['discount'] ?? 0);
                    $global_total['minus'][] = (float) ($data['minus'] ?? 0);
                    $global_total['surcharge'][] = (float) ($data['surcharge'] ?? 0);
                    $global_total['transport'][] = (float) ($data['transport'] ?? 0);
                    $global_total['prepay'][] = (float) ($data['prepay'] ?? 0);
                    $global_total['payments'][] = (float) ($data['payments'] ?? 0);
                } else {
                    $global_totalreturn['total'][] = (float) ($data['total'] ?? 0);
                    $global_totalreturn['discount_price'][] = (float) ($data['discount_price'] ?? 0);
                    $global_totalreturn['minus'][] = (float) ($data['minus'] ?? 0);
                    $global_totalreturn['surcharge'][] = (float) ($data['surcharge'] ?? 0);
                    $global_totalreturn['transport'][] = (float) ($data['transport'] ?? 0);
                    $global_totalreturn['prepay'][] = (float) ($data['prepay'] ?? 0);
                    $global_totalreturn['payments'][] = (float) ($data['payments'] ?? 0);
                }
            }

            $datas = [];
            $tong = 0;
            $minus = 0;
            $discount = 0;
            $sum_type = 0;
            $sum_typereturn = 0;
            $sum_prepay = 0;
            $sum_prepayreturn = 0;
            $tongconlai = 0;
            $tongdattmua = 0;
            $tongtheoloc = 0;

            $total = ['total' => [], 'discount_price' => [], 'discount' => [], 'minus' => [], 'surcharge' => [], 'transport' => [], 'prepay' => [], 'payments' => []];
            $totalreturn = ['total' => [], 'discount_price' => [], 'minus' => [], 'surcharge' => [], 'transport' => [], 'prepay' => [], 'payments' => []];

            $app->select("invoices", [
                "id",
                "type",
                "code",
                "total",
                "minus",
                "discount",
                "payments",
                "prepay",
                "status",
                "date",
                "user",
                "customers",
                "stores",
                "branch",
                "returns"
            ], $where, function ($data) use (&$datas, &$tong, &$minus, &$discount, &$sum_type, &$sum_typereturn, &$sum_prepay, &$sum_prepayreturn, &$tongconlai, &$tongdattmua, &$tongtheoloc, &$total, &$totalreturn, $app, $jatbi, $setting, $date_from, $date_to, $customers_map, $accounts_map, $stores_map, $branch_map, $personnels_map, $personnels_by_invoice, $returns_map) {
                $personnels_html = '';
                if (isset($personnels_by_invoice[$data['id']])) {
                    foreach ($personnels_by_invoice[$data['id']] as $personnel_id) {
                        if (isset($personnels_map[$personnel_id])) {
                            $personnels_html .= $personnels_map[$personnel_id] . '<br>';
                        }
                    }
                }

                $type = $data['type'] == 3 ? 0 : (float) ($data['payments'] ?? 0);
                $type_return = $data['type'] == 3 ? (float) ($data['payments'] ?? 0) : 0;
                $prepay = $data['type'] == 3 ? 0 : (float) ($data['prepay'] ?? 0);
                $prepay_return = $data['type'] == 3 ? (float) ($data['prepay'] ?? 0) : 0;

                $tong += $data['type'] == 3 ? 0 : (float) ($data['total'] ?? 0);
                $minus += (float) ($data['minus'] ?? 0);
                $discount += $data['type'] == 3 ? 0 : (float) ($data['discount'] ?? 0);
                $sum_type += $type;
                $sum_typereturn += $type_return;
                $sum_prepayreturn += $prepay_return;

                if ($data['type'] == 3) {
                    $getinvoi = isset($data['returns']) && isset($returns_map[$data['returns']]) ? $returns_map[$data['returns']] : null;
                    if ($getinvoi) {
                        $tttmua = $data['prepay'] > 0 ? -(float) $data['payments'] : 0;
                        $conlai = ($data['payments'] - $data['prepay'] == 0) ? 0 : '-' . number_format($data['payments'] - $data['prepay']);
                        $conlai2 = (float) ($data['payments'] - $data['prepay']);
                    } else {
                        $tttmua = 0;
                        $conlai = number_format($data['payments'] - $data['prepay']);
                        $conlai2 = 0;
                    }
                } else {
                    $tttmua = $prepay == 0 ? 0 : $prepay;
                    $conlai = number_format($data['payments'] - $data['prepay']);
                    $conlai2 = 0;
                }

                $sum_prepay += $tttmua;
                $tongconlai += $type - $prepay - $conlai2;
                $tongdattmua += $tttmua;
                $increment = $data['type'] == 3 ? 0 : ((float) ($data['payments'] ?? 0) - (float) ($data['prepay'] ?? 0) - $conlai2);
                $tongtheoloc += $increment;

                if ($data['type'] != 3) {
                    $total['total'][] = (float) ($data['total'] ?? 0);
                    $total['discount_price'][] = (float) ($data['discount_price'] ?? 0);
                    $total['discount'][] = (float) ($data['discount'] ?? 0);
                    $total['minus'][] = (float) ($data['minus'] ?? 0);
                    $total['surcharge'][] = (float) ($data['surcharge'] ?? 0);
                    $total['transport'][] = (float) ($data['transport'] ?? 0);
                    $total['prepay'][] = (float) ($data['prepay'] ?? 0);
                    $total['payments'][] = (float) ($data['payments'] ?? 0);
                } else {
                    $totalreturn['total'][] = (float) ($data['total'] ?? 0);
                    $totalreturn['discount_price'][] = (float) ($data['discount_price'] ?? 0);
                    $totalreturn['minus'][] = (float) ($data['minus'] ?? 0);
                    $totalreturn['surcharge'][] = (float) ($data['surcharge'] ?? 0);
                    $totalreturn['transport'][] = (float) ($data['transport'] ?? 0);
                    $totalreturn['prepay'][] = (float) ($data['prepay'] ?? 0);
                    $totalreturn['payments'][] = (float) ($data['payments'] ?? 0);
                }

                $datas[] = [
                    "ma-hoa-don" => '<a class="text-nowrap pjax-load" href="/invoices/invoices-views/' . $data['id'] . '">#' . ($setting['ballot_code']['invoices'] ?? '') . '-' . ($data['code'] ?? '') . $data['id'] . '</a>',
                    "khach-hang" => $customers_map[$data['customers']] ?? '',
                    "tong-tien" => $data['type'] == 3 ? 0 : number_format($data['total'] ?? 0),
                    "giam-tru" => number_format($data['minus'] ?? 0),
                    "giam-gia" => number_format($data['discount'] ?? 0),
                    "thanh-toan-mua" => number_format($type),
                    "thanh-toan-tra" => number_format($type_return),
                    "da-thanh-toan-mua" => number_format($tttmua),
                    "da-thanh-toan-tra" => number_format($prepay_return),
                    "con-lai" => '<a href="#!" class="modal-url text-danger" data-url="/invoices/invoices-prepay/' . $data['id'] . '/">' . $conlai . '</a>',
                    "nhan-vien-ban-hang" => $personnels_html,
                    "trang-thai" => '<span class="fw-bold text-' . ($setting['Status_invoices'][$data['status']]['color'] ?? '') . '">' . ($setting['Status_invoices'][$data['status']]['name'] ?? '') . '</span>',
                    "ngay" => date($setting['site_date'] ?? "d/m/Y", strtotime($data['date'])),
                    "tai-khoan" => $accounts_map[$data['user']] ?? '',
                    "cua-hang" => $stores_map[$data['stores']] ?? '',
                    "quay-hang" => $branch_map[$data['branch']] ?? '',
                    // "action" => $app->component("action", [
                    //     "button" => [
                    //         [
                    //             'type' => 'link',
                    //             'name' => $jatbi->lang("Xem"),
                    //             'action' => ['href' => '/invoices/invoices-views/' . $data['id'], 'data-pjax' => '']
                    //         ],
                    //     ]
                    // ]),
                    "action" => '<a href="/invoices/invoices-views/' . $data['id'] . '" class="pjax-load btn btn-eclo-light btn-sm border-0 py-1 px-2 rounded-3" aria-label="' . $jatbi->lang('Xem') . '"><i class="ti ti-eye"></i></a>',
                ];
            });

            $totals = [
                "tong-tien" => number_format($tong),
                "giam-tru" => number_format($minus),
                "giam-gia" => number_format($discount),
                "thanh-toan-mua" => number_format($sum_type),
                "thanh-toan-tra" => number_format($sum_typereturn),
                "da-thanh-toan-mua" => number_format($sum_prepay),
                "da-thanh-toan-tra" => number_format($sum_prepayreturn),
                "con-lai" => number_format($tongconlai),
                "tong-theo-dieu-kien-tong" => number_format($global_tong),
                "tong-theo-dieu-kien-giam-tru" => number_format($global_minus),
                "tong-theo-dieu-kien-giam-gia" => number_format($global_discount),
                "tong-theo-dieu-kien-thanh-toan-mua" => number_format(array_sum($global_total['payments'] ?? [0])),
                "tong-theo-dieu-kien-thanh-toan-tra" => number_format(array_sum($global_totalreturn['payments'] ?? [0])),
                "tong-theo-dieu-kien-da-thanh-toan-mua" => number_format($global_tongdattmua),
                "tong-theo-dieu-kien-da-thanh-toan-tra" => number_format(array_sum($global_totalreturn['prepay'] ?? [0])),
                "tong-theo-dieu-kien-con-lai" => number_format($global_tongconlai),
            ];

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas ?? [],
                "footerData" => $totals,
            ]);
        }
    })->setPermissions(['revenue']);

    ob_start();


    $app->router('/revenue-excel', 'GET', function ($vars) use ($app, $jatbi, $setting, $accStore) {

        try {
            $user = $_GET['user'] ?? '';
            $customers = $_GET['customers'] ?? '';
            $status = $_GET['status'] ?? '';
            $personnels = $_GET['personnels'] ?? '';
            $branch = $_GET['branch'] ?? '';
            $date_string = $_GET['date'] ?? '';
            $searchValue = $_GET['search']['value'] ?? '';
            $store = $_GET['stores'] ?? $accStore;

            if ($customers === 'null') {
                $customers = '';
            }
            if ($branch === 'null') {
                $branch = '';
            }

            if (!empty($date_string)) {
                $date = explode(' - ', $date_string);
                $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date[0]))));
                $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date[1]))));
            } else {
                $date_from = date('Y-m-01 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }

            $where = [
                "AND" => [
                    "invoices.date[<>]" => [$date_from, $date_to],
                    "invoices.deleted" => 0,
                    "invoices.cancel" => [0, 1],
                    "invoices.status" => [1, 2]
                ]
            ];

            if (!empty($searchValue))
                $where['AND']['OR'] = ["invoices.id[~]" => $searchValue, "invoices.code[~]" => $searchValue];
            if (!empty($status))
                $where['AND']['invoices.status'] = $status;
            if (!empty($user))
                $where['AND']['invoices.user'] = $user;
            if (!empty($customers))
                $where['AND']['invoices.customers'] = $customers;
            if (!empty($branch))
                $where['AND']['invoices.branch'] = $branch;
            if ($store != [0] && !empty($store))
                $where['AND']['invoices.stores'] = $store;
            if (!empty($personnels)) {
                $invoice_ids_by_personnel = $app->select("invoices_personnels", "invoices", ["personnels" => $personnels, "deleted" => 0]);
                $where['AND']['invoices.id'] = $invoice_ids_by_personnel ?: [0];
            }

            $datas = $app->select("invoices", "*", $where);

            if (empty($datas)) {
                exit("Không có dữ liệu để xuất file.");
            }

            $invoice_ids = array_column($datas, 'id');
            $customers_map = array_column($app->select("customers", ["id", "name"]), 'name', 'id');
            $accounts_map = array_column($app->select("accounts", ["id", "name"]), 'name', 'id');
            $stores_map = array_column($app->select("stores", ["id", "name"]), 'name', 'id');
            $branch_map = array_column($app->select("branch", ["id", "name"]), 'name', 'id');

            $personnels_db = $app->select("invoices_personnels", ["[<]personnels" => ["personnels" => "id"]], ["invoices_personnels.invoices", "personnels.name"], ["invoices_personnels.invoices" => $invoice_ids]);
            $personnels_map = [];
            foreach ($personnels_db as $p) {
                $personnels_map[$p['invoices']][] = $p['name'];
            }

            $returns_ids = array_filter(array_column($datas, 'returns'));
            $returns_map = $returns_ids ? array_column($app->select("invoices", ["id"], ["id" => $returns_ids]), null, 'id') : [];

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('DoanhThu');

            $headers = ['MÃ HĐ', 'KHÁCH HÀNG', 'TỔNG TIỀN', 'GIẢM TRỪ', 'GIẢM GIÁ', 'TT MUA', 'TT TRẢ', 'ĐÃ TT MUA', 'ĐÃ TT TRẢ', 'CÒN LẠI', 'NV BÁN HÀNG', 'TRẠNG THÁI', 'NGÀY', 'TÀI KHOẢN', 'CỬA HÀNG', 'QUẦY HÀNG'];
            $sheet->fromArray($headers, null, 'A1');

            $rowIndex = 2;
            foreach ($datas as $data) {
                $payment_buy = in_array($data['type'], [1, 2]) ? $data['payments'] : 0;
                $payment_return = ($data['type'] == 3) ? $data['payments'] : 0;
                $prepay_buy = in_array($data['type'], [1, 2]) ? $data['prepay'] : 0;
                $prepay_return = ($data['type'] == 3) ? $data['prepay'] : 0;

                $remaining = 0;
                if ($data['type'] == 3 && isset($returns_map[$data['returns']])) {
                    $remaining = $data['prepay'] > 0 ? -$data['payments'] : 0;
                } else {
                    $remaining = $data['payments'] - $data['prepay'];
                }

                $sheet->setCellValue('A' . $rowIndex, '#' . $data['code'] . $data['id']);
                $sheet->setCellValue('B' . $rowIndex, $customers_map[$data['customers']] ?? '');
                $sheet->setCellValue('C' . $rowIndex, $data['total']);
                $sheet->setCellValue('D' . $rowIndex, $data['minus']);
                $sheet->setCellValue('E' . $rowIndex, $data['discount']);
                $sheet->setCellValue('F' . $rowIndex, $payment_buy);
                $sheet->setCellValue('G' . $rowIndex, $payment_return);
                $sheet->setCellValue('H' . $rowIndex, $prepay_buy);
                $sheet->setCellValue('I' . $rowIndex, $prepay_return);
                $sheet->setCellValue('J' . $rowIndex, $remaining);
                $sheet->setCellValue('K' . $rowIndex, implode(', ', $personnels_map[$data['id']] ?? []));
                $sheet->setCellValue('L' . $rowIndex, $setting['Status_invoices'][$data['status']]['name'] ?? 'Không rõ');
                $sheet->setCellValue('M' . $rowIndex, date('d/m/Y', strtotime($data['date'])));
                $sheet->setCellValue('N' . $rowIndex, $accounts_map[$data['user']] ?? '');
                $sheet->setCellValue('O' . $rowIndex, $stores_map[$data['stores']] ?? '');
                $sheet->setCellValue('P' . $rowIndex, $branch_map[$data['branch']] ?? '');
                $rowIndex++;
            }

            foreach (range('A', 'P') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
            ob_end_clean();

            $file_name = 'doanh-thu_' . date('d-m-Y') . '.xls';
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="' . $file_name . '"');
            header('Cache-Control: max-age=0');

            $writer = new Xls($spreadsheet);
            $writer->save('php://output');
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            exit("Lỗi: " . $e->getMessage());
        }
    })->setPermissions(['revenue']);






    $app->router("/purchases-liabilities", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $accStore, $stores, $template) {
        $vars['title'] = $jatbi->lang("Công nợ mua hàng");

        if ($app->method() === 'GET') {
            $vendor = $app->select("vendors", ["id (value) ", "name (text)"], ["deleted" => 0, "status" => 'A',]);
            array_unshift($vendor, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars['vendors'] = $vendor;
            $Status_invoices = $setting['Status_invoices'];
            $Status_invoices_formatted = array_map(function ($item) use ($jatbi) {
                return [
                    'value' => $item['id'],
                    'text' => $jatbi->lang($item['name'])
                ];
            }, $Status_invoices);
            array_unshift($Status_invoices_formatted, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);

            $Status_purchase = $setting['Status_purchase'];
            $Status_purchase_formatted = array_map(function ($item) use ($jatbi) {
                return [
                    'value' => $item['id'],
                    'text' => $jatbi->lang($item['name'])
                ];
            }, $Status_purchase);
            array_unshift($Status_purchase_formatted, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars["Status_invoices"] = $Status_invoices_formatted;
            $vars["Status_purchase"] = $Status_purchase_formatted;
            echo $app->render($template . '/reports/purchases-liabilities.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            // Lấy các tham số từ DataTables
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
            $searchValue = isset($_POST['search']['value']) ? $app->xss($_POST['search']['value']) : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'date';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

            // Lấy các tham số bộ lọc
            $vendor = isset($_POST['vendor']) ? $app->xss($_POST['vendor']) : '';
            $status_pay = isset($_POST['status_pay']) ? $app->xss($_POST['status_pay']) : '';
            $status = isset($_POST['status']) ? $app->xss($_POST['status']) : '';
            $date_string = isset($_POST['date']) ? $app->xss($_POST['date']) : '';

            // Xử lý khoảng thời gian
            if ($date_string) {
                $date = explode('-', $date_string);
                $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date[0]))));
                $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date[1]))));
            } else {
                $date_from = date('Y-m-d 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }

            // Xử lý stores từ session

            $store = isset($_POST['stores']) ? $app->xss($_POST['stores']) : $accStore;

            // Xây dựng điều kiện truy vấn
            $where = [
                "AND" => [
                    "purchase.code[<>]" => $searchValue ? [$searchValue, $searchValue] : '',
                    // "purchase.user[<>]" => $user ? [$user, $user] : '',
                    "purchase.vendor[<>]" => $vendor ? [$vendor, $vendor] : '',
                    "purchase.date[<>]" => [$date_from, $date_to],
                    "purchase.stores" => $store,
                    "purchase.deleted" => 0,
                    "purchase.status_pay" => 2,
                    "purchase.status" => [3, 5],
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => ["purchase.$orderName" => strtoupper($orderDir)],
            ];

            // Truy vấn dữ liệu đầu kỳ (trước date_from)
            $getStart = $app->select("purchase", ["id", "code", "vendor", "total", "minus", "discount", "surcharge", "payments", "prepay", "status", "date", "user"], [
                "AND" => [
                    "purchase.code[<>]" => $searchValue ? [$searchValue, $searchValue] : '',
                    // "purchase.user[<>]" => $user ? [$user, $user] : '',
                    "purchase.vendor[<>]" => $vendor ? [$vendor, $vendor] : '',
                    "purchase.date[<]" => $date_from,
                    "purchase.stores" => $store,
                    "purchase.deleted" => 0,
                    "purchase.status_pay" => 2,
                    "purchase.status" => [3, 5],
                ],
            ]);

            // Truy vấn tổng số bản ghi
            $count = $app->count("purchase", ["AND" => $where['AND']]);

            // Truy vấn dữ liệu chính
            $getDatas = $app->select("purchase", ["id", "code", "vendor", "total", "minus", "discount", "surcharge", "payments", "prepay", "status", "date", "user"], [
                "AND" => $where['AND'],
            ]);

            // Tính toán tổng hợp
            $total = [];
            foreach ($getDatas as $value) {
                $total['total'][] = (float) ($value['total'] ?? 0);
                $total['minus'][] = (float) ($value['minus'] ?? 0);
                $total['discount'][] = (float) ($value['discount'] ?? 0);
                $total['surcharge'][] = (float) ($value['surcharge'] ?? 0);
                $total['payments'][] = (float) ($value['payments'] ?? 0);
                $total['prepay'][] = (float) ($value['prepay'] ?? 0);
            }

            $total_start = [];
            foreach ($getStart as $start) {
                $total_start['total'][] = (float) ($start['total'] ?? 0);
                $total_start['minus'][] = (float) ($start['minus'] ?? 0);
                $total_start['discount'][] = (float) ($start['discount'] ?? 0);
                $total_start['surcharge'][] = (float) ($start['surcharge'] ?? 0);
                $total_start['payments'][] = (float) ($start['payments'] ?? 0);
                $total_start['prepay'][] = (float) ($start['prepay'] ?? 0);
            }

            // Xử lý dữ liệu cho DataTables
            $datas = [];
            $app->select("purchase", ["id", "code", "vendor", "total", "minus", "discount", "surcharge", "payments", "prepay", "status", "date", "user"], $where, function ($data) use (&$datas, $app, $jatbi, $setting) {
                if (!isset($data['id'], $data['code'], $data['vendor'], $data['total'], $data['status'], $data['date'], $data['user'])) {
                    error_log("Invalid purchase record: " . print_r($data, true));
                    return;
                }

                $datas[] = [
                    "ma-don-hang" => '#' . ($setting['ballot_code']['purchase'] ?? '') . '-' . $data['code'],
                    "nha-cung-cap" => $app->get("vendors", "name", ["id" => $data['vendor'], "deleted" => 0]) ?? '',
                    "tong-tien" => number_format((float) ($data['total'] ?? 0)),
                    "giam-tru" => number_format((float) ($data['minus'] ?? 0)),
                    "giam-gia" => number_format((float) ($data['discount'] ?? 0)),
                    "phu-thu" => number_format((float) ($data['surcharge'] ?? 0)),
                    "thanh-toan" => number_format((float) ($data['payments'] ?? 0)),
                    "da-thanh-toan" => number_format((float) ($data['prepay'] ?? 0)),
                    "con-lai" => '<a href="#!" class="modal-url text-danger" data-url="/purchases/purchase-prepay/' . $data['id'] . '/">' . number_format((float) ($data['payments'] ?? 0) - (float) ($data['prepay'] ?? 0)) . '</a>',
                    "trang-thai" => '<span class="fw-bold text-' . ($setting['Status_invoices'][$data['status']]['color'] ?? '') . '">' . ($setting['Status_invoices'][$data['status']]['name'] ?? '') . '</span>',
                    "ngay" => date("d/m/Y H:i:s", strtotime($data['date'])),
                    "tai-khoan" => $app->get("accounts", "name", ["id" => $data['user'], "deleted" => 0]) ?? '',
                    // "action-view" => '<button data-action="modal" data-url="/purchases/purchase-views/' . $data['id'] . '" class="btn btn-eclo-light btn-sm border-0 py-1 px-2 rounded-3" aria-label="' . $jatbi->lang('Xem') . '"><i class="ti ti-eye"></i></button>',
                    "action-view" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xem"),
                                'action' => ['data-url' => '/purchases/purchase-views/' . $data['id'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Thanh toán"),
                                'permission' => ['expenditure.edit'],
                                'action' => ['data-url' => '/purchases/purchase-prepay/' . $data['id'], 'data-action' => 'modal']
                            ],
                        ]
                    ]),
                ];
            });

            // Tổng hợp dữ liệu cho footer
            $totals = [
                "tong-tien-dau-ky" => number_format(array_sum($total_start['total'] ?? [0])),
                "giam-tru-dau-ky" => number_format(array_sum($total_start['minus'] ?? [0])),
                "giam-gia-dau-ky" => number_format(array_sum($total_start['discount'] ?? [0])),
                "phu-thu-dau-ky" => number_format(array_sum($total_start['surcharge'] ?? [0])),
                "thanh-toan-dau-ky" => number_format(array_sum($total_start['payments'] ?? [0])),
                "da-thanh-toan-dau-ky" => number_format(array_sum($total_start['prepay'] ?? [0])),
                "con-lai-dau-ky" => number_format(array_sum($total_start['payments'] ?? [0]) - array_sum($total_start['prepay'] ?? [0])),
                "tong-tien" => number_format(array_sum($total['total'] ?? [0])),
                "giam-tru" => number_format(array_sum($total['minus'] ?? [0])),
                "giam-gia" => number_format(array_sum($total['discount'] ?? [0])),
                "phu-thu" => number_format(array_sum($total['surcharge'] ?? [0])),
                "thanh-toan" => number_format(array_sum($total['payments'] ?? [0])),
                "da-thanh-toan" => number_format(array_sum($total['prepay'] ?? [0])),
                "con-lai" => number_format(array_sum($total['payments'] ?? [0]) - array_sum($total['prepay'] ?? [0])),
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
    })->setPermissions(['purchases-liabilities']);

    $app->router('/purchases-liabilities-excel', 'GET', function ($vars) use ($app, $jatbi, $setting, $accStore) {
        try {
            // --- BƯỚC 1: LẤY VÀ XỬ LÝ CÁC BỘ LỌC TỪ URL ---
            $customers = $_GET['customers'] ?? '';
            $date_string = $_GET['date'] ?? '';
            $store = $_GET['stores'] ?? $accStore;

            if (!empty($date_string)) {
                $date = explode(' - ', $date_string);
                $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date[0]))));
                $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date[1]))));
            } else {
                $date_from = date('Y-m-01 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }

            // --- BƯỚC 2: XÂY DỰNG ĐIỀU KIỆN LỌC ---
            $where = ["AND" => ["deleted" => 0, "status_pay" => 2, "status" => [3, 5], "date[<>]" => [$date_from, $date_to]]];
            $where_start = ["AND" => ["deleted" => 0, "status_pay" => 2, "status" => [3, 5], "date[<]" => $date_from]];

            if (!empty($customers)) {
                $where['AND']['customers'] = $customers;
                $where_start['AND']['customers'] = $customers;
            }
            if (!empty($store)) {
                $where['AND']['stores'] = $store;
                $where_start['AND']['stores'] = $store;
            }

            // --- BƯỚC 3: LẤY DỮ LIỆU ---
            $datas = $app->select("purchase", "*", $where);
            $start_data = $app->select("purchase", "*", $where_start);

            $vendors_map = array_column($app->select("customers", ["id", "name"]), 'name', 'id');
            $accounts_map = array_column($app->select("accounts", ["id", "name"]), 'name', 'id');
            $stores_map = array_column($app->select("stores", ["id", "name"]), 'name', 'id');

            // --- BƯỚC 4: TÍNH TOÁN TỔNG HỢP (SỬA LẠI CHO ĐÚNG) ---
            $calculate_totals = function ($dataset) {
                $totals = ['total' => 0, 'minus' => 0, 'discount' => 0, 'surcharge' => 0, 'payments' => 0, 'prepay' => 0]; // Thiếu transport, thêm nếu cần
                foreach ($dataset as $item) {
                    $totals['total'] += (float) ($item['total'] ?? 0);
                    $totals['minus'] += (float) ($item['minus'] ?? 0);
                    $totals['discount'] += (float) ($item['discount'] ?? 0);
                    $totals['surcharge'] += (float) ($item['surcharge'] ?? 0);
                    $totals['payments'] += (float) ($item['payments'] ?? 0);
                    $totals['prepay'] += (float) ($item['prepay'] ?? 0);
                }
                return $totals;
            };
            $total_start = $calculate_totals($start_data);
            $total_in_period = $calculate_totals($datas);

            // --- BƯỚC 5: TẠO FILE EXCEL VÀ GHI DỮ LIỆU (SỬA LẠI CHO ĐÚNG) ---
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('CongNoMuaHang');

            $headers = ['MÃ NHÂN VIÊN', 'KHÁCH HÀNG', 'TỔNG TIỀN', 'GIẢM TRỪ', 'GIẢM GIÁ', 'PHỤ THU', 'VẬN CHUYỂN', 'THANH TOÁN', 'ĐÃ THANH TOÁN', 'CÒN LẠI', 'TRẠNG THÁI', 'NGÀY', 'TÀI KHOẢN', 'CỬA HÀNG'];
            $sheet->fromArray($headers, null, 'A1');

            // Ghi dòng "Đầu kỳ"
            $sheet->setCellValue('B2', 'Đầu kỳ');
            $sheet->setCellValue('C2', $total_start['total']);
            $sheet->setCellValue('D2', $total_start['minus']);
            $sheet->setCellValue('E2', $total_start['discount']);
            $sheet->setCellValue('F2', $total_start['surcharge']);
            $sheet->setCellValue('G2', $total_start['transport']);
            $sheet->setCellValue('H2', $total_start['payments']);
            $sheet->setCellValue('I2', $total_start['prepay']);
            $sheet->setCellValue('J2', $total_start['payments'] - $total_start['prepay']);

            // Ghi dữ liệu chi tiết
            $rowIndex = 2;
            foreach ($datas as $data) {
                $rowIndex++;
                $sheet->setCellValue('A' . $rowIndex, '#' . ($setting['ballot_code']['invoices'] ?? '') . $data['code'] . ($data['id'] ?? ''));
                $sheet->setCellValue('B' . $rowIndex, $vendors_map[$data['customers']] ?? '');
                $sheet->setCellValue('C' . $rowIndex, (float)($data['total'] ?? 0));
                $sheet->setCellValue('D' . $rowIndex, (float)($data['minus'] ?? 0));
                $sheet->setCellValue('E' . $rowIndex, (float)($data['discount'] ?? 0));
                $sheet->setCellValue('F' . $rowIndex, (float)($data['surcharge'] ?? 0));
                $sheet->setCellValue('G' . $rowIndex, (float)($data['transport'] ?? 0));
                $sheet->setCellValue('H' . $rowIndex, (float)($data['payments'] ?? 0));
                $sheet->setCellValue('I' . $rowIndex, (float)($data['prepay'] ?? 0));
                $sheet->setCellValue('J' . $rowIndex, (float)($data['payments'] ?? 0) - (float)($data['prepay'] ?? 0));
                $sheet->setCellValue('K' . $rowIndex, $setting['Status_invoices'][$data['status']]['name'] ?? '');
                $sheet->setCellValue('L' . $rowIndex, date('d/m/Y', strtotime($data['date'])));
                $sheet->setCellValue('M' . $rowIndex, $accounts_map[$data['user']] ?? '');
                $sheet->setCellValue('N' . $rowIndex, $stores_map[$data['stores']] ?? '');
            }

            // Ghi dòng "Tổng cộng trong kỳ"
            $totalRowIndex = $rowIndex + 1;
            $sheet->setCellValue('B' . $totalRowIndex, 'Tổng cộng');
            $sheet->setCellValue('C' . $totalRowIndex, $total_in_period['total']);
            $sheet->setCellValue('D' . $totalRowIndex, $total_in_period['minus']);
            $sheet->setCellValue('E' . $totalRowIndex, $total_in_period['discount']);
            $sheet->setCellValue('F' . $totalRowIndex, $total_in_period['surcharge']);
            $sheet->setCellValue('G' . $totalRowIndex, $total_in_period['transport']);
            $sheet->setCellValue('H' . $totalRowIndex, $total_in_period['payments']);
            $sheet->setCellValue('I' . $totalRowIndex, $total_in_period['prepay']);
            $sheet->setCellValue('J' . $totalRowIndex, $total_in_period['payments'] - $total_in_period['prepay']);

            // Tự động giãn cột và định dạng số
            foreach (range('A', 'N') as $columnID) {
                $sheet->getColumnDimension($columnID)->setAutoSize(true);
            }
            foreach (range('C', 'J') as $columnID) {
                $sheet->getStyle($columnID . '2:' . $columnID . $totalRowIndex)->getNumberFormat()->setFormatCode('#,##0');
            }

            // --- BƯỚC 6: XUẤT FILE ---
            ob_end_clean();
            $file_name = 'cong-no-mua-hang_' . date('d-m-Y') . '.xls';
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="' . $file_name . '"');
            header('Cache-Control: max-age=0');
            $writer = new Xls($spreadsheet);
            $writer->save('php://output');
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            exit("Lỗi: " . $e->getMessage());
        }
    })->setPermissions(['purchases-liabilities']);
    $app->router("/purchases-revenue", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $accStore, $stores, $template) {
        $vars['title'] = $jatbi->lang("Chi phí mua hàng");

        if ($app->method() === 'GET') {
            // Lấy dữ liệu cho bộ lọc
            $vendor = $app->select("vendors", ["id (value) ", "name (text)"], ["deleted" => 0, "status" => 'A',]);
            array_unshift($vendor, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars['vendors'] = $vendor;
            $Status_invoices = $setting['Status_invoices'];
            $Status_invoices_formatted = array_map(function ($item) use ($jatbi) {
                return [
                    'value' => $item['id'],
                    'text' => $jatbi->lang($item['name'])
                ];
            }, $Status_invoices);
            array_unshift($Status_invoices_formatted, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);

            $Status_purchase = $setting['Status_purchase'];
            $Status_purchase_formatted = array_map(function ($item) use ($jatbi) {
                return [
                    'value' => $item['id'],
                    'text' => $jatbi->lang($item['name'])
                ];
            }, $Status_purchase);
            array_unshift($Status_purchase_formatted, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars["Status_invoices"] = $Status_invoices_formatted;
            $vars["Status_purchase"] = $Status_purchase_formatted;
            echo $app->render($template . '/reports/purchases-revenue.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            // Lấy các tham số từ DataTables
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
            $searchValue = isset($_POST['search']['value']) ? $app->xss($_POST['search']['value']) : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

            // Lấy các tham số bộ lọc
            $vendor = isset($_POST['vendor']) ? $app->xss($_POST['vendor']) : '';
            $status = isset($_POST['status']) ? $app->xss($_POST['status']) : '';
            $user = isset($_POST['user']) ? $app->xss($_POST['user']) : '';
            $date_string = isset($_POST['date']) ? $app->xss($_POST['date']) : '';

            // Xử lý khoảng thời gian
            if ($date_string) {
                $date = explode('-', $date_string);
                $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date[0]))));
                $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date[1]))));
            } else {
                $date_from = date('Y-m-d 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }

            $store = isset($_POST['stores']) ? $app->xss($_POST['stores']) : $accStore;

            // Xây dựng điều kiện truy vấn
            $where = [
                "AND" => [
                    "purchase.code[<>]" => $searchValue ? [$searchValue, $searchValue] : '',
                    "purchase.user[<>]" => $user ? [$user, $user] : '',
                    "purchase.vendor[<>]" => $vendor ? [$vendor, $vendor] : '',
                    "purchase.status[<>]" => $status ? [$status, $status] : '',
                    "purchase.date[<>]" => [$date_from, $date_to],
                    "purchase.stores" => $store,
                    "purchase.deleted" => 0,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => ["purchase.$orderName" => strtoupper($orderDir)],
            ];

            // Truy vấn dữ liệu đầu kỳ (trước date_from)
            $getStart = $app->select("purchase", ["id", "code", "vendor", "total", "minus", "discount", "surcharge", "payments", "prepay", "status", "date", "user"], [
                "AND" => [
                    "purchase.code[<>]" => $searchValue ? [$searchValue, $searchValue] : '',
                    "purchase.user[<>]" => $user ? [$user, $user] : '',
                    "purchase.vendor[<>]" => $vendor ? [$vendor, $vendor] : '',
                    "purchase.status[<>]" => $status ? [$status, $status] : '',
                    "purchase.date[<]" => $date_from,
                    "purchase.stores" => $store,
                    "purchase.deleted" => 0,
                    "purchase.status" => [3, 5],
                ],
            ]);

            // Truy vấn tổng số bản ghi
            $count = $app->count("purchase", ["AND" => $where['AND']]);

            // Truy vấn dữ liệu chính
            $getDatas = $app->select("purchase", ["id", "code", "vendor", "total", "minus", "discount", "surcharge", "payments", "prepay", "status", "date", "user"], [
                "AND" => $where['AND']
            ]);
            $where['AND']['purchase.status'] = [3, 5];
            // Tính toán tổng hợp
            $total = [];
            foreach ($getDatas as $value) {
                $total['total'][] = (float) ($value['total'] ?? 0);
                $total['minus'][] = (float) ($value['minus'] ?? 0);
                $total['discount'][] = (float) ($value['discount'] ?? 0);
                $total['surcharge'][] = (float) ($value['surcharge'] ?? 0);
                $total['payments'][] = (float) ($value['payments'] ?? 0);
                $total['prepay'][] = (float) ($value['prepay'] ?? 0);
            }

            $total_start = [];
            foreach ($getStart as $start) {
                $total_start['total'][] = (float) ($start['total'] ?? 0);
                $total_start['minus'][] = (float) ($start['minus'] ?? 0);
                $total_start['discount'][] = (float) ($start['discount'] ?? 0);
                $total_start['surcharge'][] = (float) ($start['surcharge'] ?? 0);
                $total_start['payments'][] = (float) ($start['payments'] ?? 0);
                $total_start['prepay'][] = (float) ($start['prepay'] ?? 0);
            }

            // Xử lý dữ liệu cho DataTables
            $datas = [];
            $app->select("purchase", ["id", "code", "vendor", "total", "minus", "discount", "surcharge", "payments", "prepay", "status", "date", "user"], $where, function ($data) use (&$datas, $app, $jatbi, $setting) {
                if (!isset($data['id'], $data['code'], $data['vendor'], $data['total'], $data['status'], $data['date'], $data['user'])) {
                    error_log("Invalid purchase record: " . print_r($data, true));
                    return;
                }

                $datas[] = [
                    "ma-don-hang" => '#' . ($setting['ballot_code']['purchase'] ?? '') . '-' . $data['code'],
                    "nha-cung-cap" => $app->get("vendors", "name", ["id" => $data['vendor'], "deleted" => 0]) ?? '',
                    "tong-tien" => number_format((float) ($data['total'] ?? 0)),
                    "giam-tru" => number_format((float) ($data['minus'] ?? 0)),
                    "giam-gia" => number_format((float) ($data['discount'] ?? 0)),
                    "phu-thu" => number_format((float) ($data['surcharge'] ?? 0)),
                    "thanh-toan" => number_format((float) ($data['payments'] ?? 0)),
                    "da-thanh-toan" => number_format((float) ($data['prepay'] ?? 0)),
                    "con-lai" => number_format((float) ($data['payments'] ?? 0) - (float) ($data['prepay'] ?? 0)),
                    "trang-thai" => '<span class="fw-bold text-' . ($setting['Status_invoices'][$data['status']]['color'] ?? '') . '">' . ($setting['Status_invoices'][$data['status']]['name'] ?? '') . '</span>',
                    "ngay" => date("d/m/Y H:i:s", strtotime($data['date'])),
                    "tai-khoan" => $app->get("accounts", "name", ["id" => $data['user'], "deleted" => 0]) ?? '',
                    "action-view" => '<button data-action="modal" data-url="/purchases/purchase-views/' . $data['id'] . '" class="btn btn-eclo-light btn-sm border-0 py-1 px-2 rounded-3" aria-label="' . $jatbi->lang('Xem') . '"><i class="ti ti-eye"></i></button>',
                ];
            });

            // Tổng hợp dữ liệu cho footer
            $totals = [
                "tong-tien-dau-ky" => number_format(array_sum($total_start['total'] ?? [0])),
                "giam-tru-dau-ky" => number_format(array_sum($total_start['minus'] ?? [0])),
                "giam-gia-dau-ky" => number_format(array_sum($total_start['discount'] ?? [0])),
                "phu-thu-dau-ky" => number_format(array_sum($total_start['surcharge'] ?? [0])),
                "thanh-toan-dau-ky" => number_format(array_sum($total_start['payments'] ?? [0])),
                "da-thanh-toan-dau-ky" => number_format(array_sum($total_start['prepay'] ?? [0])),
                "con-lai-dau-ky" => number_format(array_sum($total_start['payments'] ?? [0]) - array_sum($total_start['prepay'] ?? [0])),
                "tong-tien" => number_format(array_sum($total['total'] ?? [0])),
                "giam-tru" => number_format(array_sum($total['minus'] ?? [0])),
                "giam-gia" => number_format(array_sum($total['discount'] ?? [0])),
                "phu-thu" => number_format(array_sum($total['surcharge'] ?? [0])),
                "thanh-toan" => number_format(array_sum($total['payments'] ?? [0])),
                "da-thanh-toan" => number_format(array_sum($total['prepay'] ?? [0])),
                "con-lai" => number_format(array_sum($total['payments'] ?? [0]) - array_sum($total['prepay'] ?? [0])),
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
    })->setPermissions(['purchases-revenue']);

    $app->router("/inventory", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $accStore, $stores, $template) {
        $vars['title'] = $jatbi->lang("Xuất nhập tồn");

        if ($app->method() === 'GET') {
            // Chuẩn bị dữ liệu cho giao diện
            array_unshift($stores, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars['stores'] = $stores;

            $branchs = $accStore == 0
                ? $app->select("branch", ["id (value)", "name (text)"], ["status" => 'A', "deleted" => 0, "stores" => $stores[0]['id']])
                : $app->select("branch", ["id (value)", "name (text)"], ["status" => 'A', "deleted" => 0, "stores" => $accStore]);
            array_unshift($branchs, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars['branchs'] = $branchs;

            echo $app->render($template . '/reports/inventory.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            // Lấy tham số DataTables và bộ lọc

            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? $app->xss($_POST['search']['value']) : '';
            $price = isset($_POST['price']) ? str_replace(',', '', $app->xss($_POST['price'])) : '';
            $branchss = isset($_POST['branchss']) ? $app->xss($_POST['branchss']) : '';
            $date_string = isset($_POST['date']) ? $app->xss($_POST['date']) : '';
            $store_select = isset($_POST['stores']) && !empty($_POST['stores']) ? $app->xss($_POST['stores']) : $accStore;

            // Xử lý khoảng thời gian
            if ($date_string) {
                $date = explode('-', $date_string);
                $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date[0]))));
                $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date[1]))));
            } else {
                $date_from = date('Y-m-d 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }

            // Xây dựng điều kiện truy vấn cho bảng products
            $where = [
                "AND" => [
                    "products.deleted" => 0,
                    "products.stores" => $store_select,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => ["products.id" => 'DESC'],
            ];
            if ($searchValue != '') {
                $where['AND']['OR'] = [
                    "products.name[~]" => $searchValue,
                    "products.code[~]" => $searchValue,
                ];
            }
            if ($price != '')
                $where['AND']['products.price[<>]'] = [$price, $price];
            if ($branchss != '')
                $where['AND']['products.branch'] = $branchss;

            // Tạo điều kiện để tính tổng (không có phân trang)
            $where_totals = $where;
            unset($where_totals['LIMIT']);
            unset($where_totals['ORDER']);

            $count = $app->count("products", $where_totals);

            // Tính tồn đầu kỳ (dùng GROUP BY - vẫn giữ vì hiệu quả và đã giống code cũ)
            $dauky_rows = $app->select("warehouses_logs", [
                "products",
                "type",
                "total_amount" => Medoo\Medoo::raw("SUM(amount)")
            ], [
                "date[<]" => $date_from,
                "products[>]" => 0,
                "amount[>]" => 0,
                "stores" => $store_select,
                "data" => "products",
                "GROUP" => ["products", "type"]
            ]);
            $dauky = [];
            foreach ($dauky_rows as $row) {
                $productId = $row['products'];
                $type = $row['type'];
                $dauky[$productId][$type]['amount'] = $row['total_amount'];
            }

            // SỬA LẠI: TÍNH TRONG KỲ THEO LOGIC CỦA CODE CŨ (LẶP VÒNG LẶP)
            $app->select(
                "warehouses_logs",
                ['id', 'amount', 'type', 'products', 'notes'],
                [
                    "AND" => [
                        "date[<>]" => [$date_from, $date_to],
                        "products[>]" => 0,
                        "amount[>]" => 0,
                        "stores" => $store_select,
                        "data" => "products"
                    ],
                ],
                function ($getIns) use (&$trongky, &$trahang) {
                    // Khởi tạo nếu chưa tồn tại
                    if (!isset($trongky[$getIns['products']][$getIns['type']]['amount'])) {
                        $trongky[$getIns['products']][$getIns['type']]['amount'] = 0;
                    }
                    $trongky[$getIns['products']][$getIns['type']]['amount'] += $getIns['amount'];

                    if (!isset($trahang[$getIns['products']][$getIns['notes']]['amount'])) {
                        $trahang[$getIns['products']][$getIns['notes']]['amount'] = 0;
                    }
                    $trahang[$getIns['products']][$getIns['notes']]['amount'] += $getIns['amount'];
                }
            );

            // Khởi tạo các biến tính tổng
            $xuat = 0;
            $huy = 0;
            $ton = 0;
            $chuyen = 0;
            $app->select("products", [
                "id",
                "name",
                "code",
                "price"
            ], $where, function ($data) use (&$nhap, $xuat, $huy, $chuyen, $ton, &$sum_dauky, &$sum_trongky, &$datas, &$sum_chuyen, &$sum_tralai, &$sum_banhang, &$sum_cuoiky, &$sum_price, $dauky, $trongky, $jatbi) {

                $id = $data['id'] ?? null;
                if ($id === null) {
                    // nếu không có id thì bỏ qua
                    return;
                }

                // Lấy giá trị an toàn: nếu key không tồn tại -> trả về 0
                $dau_import = isset($dauky[$id]['import']['amount']) ? $dauky[$id]['import']['amount'] : 0;
                $dau_error = isset($dauky[$id]['error']['amount']) ? $dauky[$id]['error']['amount'] : 0;
                $dau_export = isset($dauky[$id]['export']['amount']) ? $dauky[$id]['export']['amount'] : 0;
                $dau_move = isset($dauky[$id]['move']['amount']) ? $dauky[$id]['move']['amount'] : 0;

                $trong_import = isset($trongky[$id]['import']['amount']) ? $trongky[$id]['import']['amount'] : 0;
                $trong_export = isset($trongky[$id]['export']['amount']) ? $trongky[$id]['export']['amount'] : 0;
                $trong_error = isset($trongky[$id]['error']['amount']) ? $trongky[$id]['error']['amount'] : 0;
                $trong_move = isset($trongky[$id]['move']['amount']) ? $trongky[$id]['move']['amount'] : 0;

                // Tính toán
                $nhap = $dau_import - $dau_error - $dau_export - $dau_move + $trong_import;
                $xuat = $trong_export;
                $huy = $trong_error;
                $chuyen = $trong_move;
                $ton = $nhap - $xuat - $huy - $chuyen;

                // Cộng vào các tổng (đã pass bằng reference)
                $sum_dauky += ($dau_import - $dau_export - $dau_move - $dau_error);
                $sum_trongky += $trong_import;
                $sum_chuyen += $chuyen;
                $sum_tralai += $huy;
                $sum_banhang += $xuat;
                $sum_cuoiky += $ton;
                $sum_price += $data['price'] ?? 0;

                $datas[] = [
                    "ma-san-pham" => $data['code'] ?? "",
                    "ten" => $data['name'] ?? "",
                    "dau-ky" => $dau_import - $dau_error - $dau_export - $dau_move,
                    "sl-nhap" => $trong_import,
                    "san-pham-loi" => $huy,
                    "chuyen-hang" => $chuyen,
                    "ban-hang" => $xuat,
                    "cuoi-ky" => $ton,
                    "don-gia" => number_format($data['price'], 0),
                    "action" => '<a href="/warehouses/products-details/' . $data['id'] . '" class="pjax-load btn btn-eclo-light btn-sm border-0 py-1 px-2 rounded-3" aria-label="' . $jatbi->lang('Xem') . '"><i class="ti ti-eye"></i></a>',
                ];
            });
            $xuatloc = 0;
            $huyloc = 0;
            $chuyenloc = 0;
            $tonloc = 0;
            if ($date_string) {
                $app->select("products", ["id", "price"], $where_totals, function ($loc) use (&$nhaploc, $xuatloc, $huyloc, $chuyenloc, $tonloc, &$sum_daukyloc, &$sum_trongkyloc, &$sum_chuyenloc, &$sum_tralailoc, &$sum_banhangloc, &$sum_cuoikyloc, &$sum_priceloc, $dauky, $trongky) {
                    $id = $loc['id'];

                    // Dùng isset để lấy giá trị an toàn
                    $dau_import = isset($dauky[$id]['import']['amount']) ? $dauky[$id]['import']['amount'] : 0;
                    $dau_error = isset($dauky[$id]['error']['amount']) ? $dauky[$id]['error']['amount'] : 0;
                    $dau_export = isset($dauky[$id]['export']['amount']) ? $dauky[$id]['export']['amount'] : 0;
                    $dau_move = isset($dauky[$id]['move']['amount']) ? $dauky[$id]['move']['amount'] : 0;

                    $trong_import = isset($trongky[$id]['import']['amount']) ? $trongky[$id]['import']['amount'] : 0;
                    $trong_export = isset($trongky[$id]['export']['amount']) ? $trongky[$id]['export']['amount'] : 0;
                    $trong_error = isset($trongky[$id]['error']['amount']) ? $trongky[$id]['error']['amount'] : 0;
                    $trong_move = isset($trongky[$id]['move']['amount']) ? $trongky[$id]['move']['amount'] : 0;

                    // Tính toán
                    $nhaploc = $dau_import - $dau_error - $dau_export - $dau_move + $trong_import;
                    $xuatloc = $trong_export;
                    $huyloc = $trong_error;
                    $chuyenloc = $trong_move;
                    $tonloc = $nhaploc - $xuatloc - $huyloc - $chuyenloc;

                    // Tổng
                    $sum_daukyloc += ($dau_import - $dau_export - $dau_move - $dau_error);
                    $sum_trongkyloc += $trong_import;
                    $sum_chuyenloc += $chuyenloc;
                    $sum_tralailoc += $huyloc;
                    $sum_banhangloc += $xuatloc;
                    $sum_cuoikyloc += $tonloc;
                    $sum_priceloc += $loc['price'] ?? 0;
                });
            }
            // Tổng hợp dữ liệu cho footer
            $totals = [
                "tong-dauky" => number_format($sum_dauky ?? 0, 1),
                "tong-nhap" => number_format($sum_trongky ?? 0, 1),
                "tong-sp-loi" => number_format($sum_tralai ?? 0, 1),
                "tong-chuyenhang" => number_format($sum_chuyen ?? 0, 1),
                "tong-banhang" => number_format($sum_banhang ?? 0, 1),
                "tong-cuoiky" => number_format($sum_cuoiky ?? 0, 1),
                "tong-price" => number_format($sum_price ?? 0, 1),
                "tong-loc-dauky" => number_format($sum_daukyloc ?? 0, 1),
                "tong-loc-nhap" => number_format($sum_trongkyloc ?? 0, 1),
                "tong-loc-sp-loi" => number_format($sum_tralailoc ?? 0, 1),
                "tong-loc-chuyenhang" => number_format($sum_chuyenloc ?? 0, 1),
                "tong-loc-banhang" => number_format($sum_banhangloc ?? 0, 1),
                "tong-loc-cuoiky" => number_format($sum_cuoikyloc ?? 0, 1),
                "tong-loc-price" => number_format($sum_priceloc ?? 0, 1),
            ];

            // Trả về dữ liệu JSON cho DataTables
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas ?? [],
                "footerData" => $totals,
            ]);
        }
    })->setPermissions(['inventory']);

    $app->router("/inventory_ingredient", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores, $accStore, $template) {
        $vars['title'] = $jatbi->lang("Xuất nhập tồn nguyên liệu");

        if ($app->method() === 'GET') {
            // Lấy dữ liệu cho form lọc
            $vars["pearls"] = $app->select("pearl", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A']);
            array_unshift($vars["pearls"], [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars["sizes"] = $app->select("sizes", ["id (value)", "name (text)"], [
                "deleted" => 0,
                "status" => 'A'
            ]);
            array_unshift($vars["sizes"], [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);

            $vars["colors"] = $app->select("colors", ["id (value)", "name (text)"], [
                "deleted" => 0,
                "status" => 'A'
            ]);
            array_unshift($vars["colors"], [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);

            $vars["categorys"] = $app->select("categorys", ["id (value)", "name (text)"], [
                "deleted" => 0,
                "status" => 'A'
            ]);
            array_unshift($vars["categorys"], [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);

            $vars["products_group"] = $app->select("products_group", ["id (value)", "name (text)"], [
                "deleted" => 0,
                "status" => 'A'
            ]);
            array_unshift($vars["products_group"], [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);

            $vars["units"] = $app->select("units", ["id (value)", "name (text)"], [
                "deleted" => 0,
                "status" => 'A'
            ]);
            array_unshift($vars["units"], [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);

            // Stores đã có từ trước
            array_unshift($stores, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars["stores"] = $stores;

            // Render template
            echo $app->render($template . '/reports/inventory_ingredient.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);


            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : $setting['site_page'] ?? 10;
            $searchValue = isset($_POST['search']['value']) ? $app->xss($_POST['search']['value']) : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';


            $type = isset($_POST['type']) ? $app->xss($_POST['type']) : '';
            $pearl = isset($_POST['pearl']) ? $app->xss($_POST['pearl']) : '';
            $sizes = isset($_POST['sizes']) ? $app->xss($_POST['sizes']) : '';
            $colors = isset($_POST['colors']) ? $app->xss($_POST['colors']) : '';
            $categorys = isset($_POST['categorys']) ? $app->xss($_POST['categorys']) : '';
            $group = isset($_POST['group']) ? $app->xss($_POST['group']) : '';
            $vendor = isset($_POST['vendor']) ? $app->xss($_POST['vendor']) : '';
            $units = isset($_POST['units']) ? $app->xss($_POST['units']) : '';
            $status = isset($_POST['status']) && $_POST['status'] !== '' ? $app->xss($_POST['status']) : '';
            $date_string = isset($_POST['date']) ? $app->xss($_POST['date']) : '';


            if ($date_string) {
                $date = explode('-', $date_string);
                $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date[0]))));
                $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date[1]))));
            } else {
                $date_from = date('Y-m-d 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }
            $store = isset($_POST['stores']) ? $app->xss($_POST['stores']) : $accStore;


            $where = [
                "AND" => [
                    "OR" => ["ingredient.code[~]" => $searchValue],
                    "ingredient.deleted" => 0,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => ["ingredient.id" => strtoupper($orderDir)],
            ];
            if ($type != '')
                $where['AND']['ingredient.type'] = $type;
            if ($pearl != '')
                $where['AND']['ingredient.pearl'] = $pearl;
            if ($sizes != '')
                $where['AND']['ingredient.sizes'] = $sizes;
            if ($colors != '')
                $where['AND']['ingredient.colors'] = $colors;
            if ($categorys != '')
                $where['AND']['ingredient.categorys'] = $categorys;
            if ($group != '')
                $where['AND']['ingredient.group'] = $group;
            if ($vendor != '')
                $where['AND']['ingredient.vendor'] = $vendor;
            if ($units != '')
                $where['AND']['ingredient.units'] = $units;
            if ($status != '')
                $where['AND']['ingredient.status'] = $status;


            $count = $app->count("ingredient", ["AND" => $where['AND']]);


            $getStarts = $app->select("warehouses_logs", [
                "id",
                "amount",
                "type",
                "ingredient",
                "data",
                "warehouses",
                "notes"
            ], [
                "AND" => [
                    "date[<]" => $date_from,
                    "ingredient[>]" => 0,
                    "amount[>]" => 0,
                ],
            ]);

            $dauky = [];

            foreach ($getStarts as $getStart) {
                // 1. Dùng toán tử `??` để thay thế các key bị thiếu bằng chuỗi rỗng ''
                //    Đây chính là hành vi mà PHP 7.3 đã làm một cách âm thầm
                $ingredient = $getStart['ingredient'] ?? '';
                $type = $getStart['type'] ?? '';
                $data = $getStart['data'] ?? '';

                // 2. Tương tự, nếu 'amount' bị thiếu thì coi như nó bằng 0
                $amount = $getStart['amount'] ?? 0;

                // 3. Thực hiện cộng dồn an toàn để tránh lỗi Warning trên PHP 8
                $dauky[$ingredient][$type][$data]['amount'] =
                    ($dauky[$ingredient][$type][$data]['amount'] ?? 0) + (float) $amount;
            }

            $getIns = $app->select("warehouses_logs", [
                "id",
                "amount",
                "type",
                "ingredient",
                "data",
                "warehouses",
                "notes"
            ], [
                "AND" => [
                    "date[<>]" => [$date_from, $date_to],
                    "ingredient[>]" => 0,
                    "amount[>]" => 0,
                ],
            ]);
            $trongky = [];
            foreach ($getIns as $getIn) {
                $trongky[$getIn['ingredient']][$getIn['type']][$getIn['data']]['amount'] =
                    ($trongky[$getIn['ingredient']][$getIn['type']][$getIn['data']]['amount'] ?? 0) + (float) $getIn['amount'];
            }


            $pearl_map = array_column($app->select("pearl", ["id", "name"]), 'name', 'id');
            $size_map = array_column($app->select("sizes", ["id", "name"]), 'name', 'id');
            $color_map = array_column($app->select("colors", ["id", "name"]), 'name', 'id');
            $category_map = array_column($app->select("categorys", ["id", "name"]), 'name', 'id');
            $group_map = array_column($app->select("products_group", ["id", "name"]), 'name', 'id');
            $unit_map = array_column($app->select("units", ["id", "name"]), 'name', 'id');


            $datas = [];
            $sum_dauky = 0;
            $sum_trongky = 0;
            $sum_xuatkho = 0;
            $sum_huy = 0;
            $chuyen = 0;
            $sum_cuoiky = 0;
            $sum_price = 0;

            $app->select(
                "ingredient",
                ["[>]units" => ["units" => "id"]],
                ["ingredient.id", "ingredient.type", "ingredient.code", "ingredient.name_ingredient", "ingredient.pearl", "ingredient.sizes", "ingredient.colors", "ingredient.categorys", "ingredient.group", "ingredient.units", "ingredient.price"],
                $where,
                function ($data) use (&$datas, &$sum_dauky, &$sum_trongky, &$sum_xuatkho, &$sum_huy, &$chuyen, &$sum_cuoiky, &$sum_price, $dauky, $trongky, $pearl_map, $size_map, $color_map, $category_map, $group_map, $unit_map, $jatbi) {


                    $ton_dauki = (float) ($dauky[$data['id']]['import']['ingredient']['amount'] ?? 0) -
                        (float) ($dauky[$data['id']]['import']['products']['amount'] ?? 0) -
                        (float) ($dauky[$data['id']]['export']['ingredient']['amount'] ?? 0) -
                        (float) ($dauky[$data['id']]['cancel']['ingredient']['amount'] ?? 0);
                    // var_dump($dauky[$data['id']]['import']['ingredient']['amount'] ?? 0, 
                    // $dauky[$data['id']]['import']['products']['amount'] ?? 0, 
                    // $dauky[$data['id']]['export']['ingredient']['amount'] ?? 0 ,
                    // $dauky[$data['id']]['cancel']['ingredient']['amount'] ?? 0);

                    $ton = $ton_dauki +
                        (float) ($trongky[$data['id']]['import']['ingredient']['amount'] ?? 0) -
                        (float) ($trongky[$data['id']]['export']['ingredient']['amount'] ?? 0) -
                        (float) ($trongky[$data['id']]['import']['products']['amount'] ?? 0) -
                        (float) ($trongky[$data['id']]['cancel']['ingredient']['amount'] ?? 0);


                    $sum_dauky += $ton_dauki;
                    $sum_trongky += (float) ($trongky[$data['id']]['import']['ingredient']['amount'] ?? 0);
                    $sum_xuatkho += (float) ($trongky[$data['id']]['export']['ingredient']['amount'] ?? 0);
                    $sum_huy += (float) ($trongky[$data['id']]['cancel']['ingredient']['amount'] ?? 0);
                    $chuyen += (float) ($trongky[$data['id']]['import']['products']['amount'] ?? 0);
                    $sum_cuoiky += $ton;
                    $sum_price += (float) ($data['price'] ?? 0);


                    $loai = $data['type'] == 1 ? 'Đai' : ($data['type'] == 2 ? 'Ngọc' : 'Khác');
                    $thuoctinh = '';
                    if ($data['type'] == 1) {
                        $thuoctinh = ($category_map[$data['categorys']] ?? '') . '_' . ($color_map[$data['colors']] ?? '') . '_' . ($group_map[$data['group']] ?? '');
                    } elseif ($data['type'] == 2) {
                        $thuoctinh = ($pearl_map[$data['pearl']] ?? '') . '_' . ($size_map[$data['sizes']] ?? '') . '_' . ($color_map[$data['colors']] ?? '');
                    } elseif ($data['type'] == 3) {
                        $thuoctinh = $group_map[$data['group']] ?? '';
                    }

                    $datas[] = [
                        "loai" => $loai,
                        "ma-nguyen-lieu" => $data['code'],
                        "ten-nguyen-lieu" => $data['name_ingredient'],
                        "thuoc-tinh" => $thuoctinh,
                        "don-vi" => $unit_map[$data['units']] ?? '',
                        "so-luong-dau-ky" => number_format($ton_dauki, 1),
                        "so-luong-nhap-thanh-pham" => '',
                        "so-luong-nhap" => (($trongky[$data['id']]['import']['ingredient']['amount'] ?? 0) != 0) ? number_format($trongky[$data['id']]['import']['ingredient']['amount'], 1) : '',
                        "so-luong-xuat" => (($trongky[$data['id']]['export']['ingredient']['amount'] ?? 0) != 0) ? number_format($trongky[$data['id']]['export']['ingredient']['amount'], 1) : '',
                        "so-luong-huy" => (($trongky[$data['id']]['cancel']['ingredient']['amount'] ?? 0) != 0) ? number_format($trongky[$data['id']]['cancel']['ingredient']['amount'], 1) : '',
                        "xuat-kho-thanh-pham" => (($trongky[$data['id']]['import']['products']['amount'] ?? 0) != 0) ? number_format($trongky[$data['id']]['import']['products']['amount'], 1) : '',
                        "so-luong-cuoi-ky" => number_format($ton, 1),
                        "don-gia" => number_format($data['price'] ?? 0, 0),
                        "action" => '<a href="/warehouses/ingredient-details/' . $data['id'] . '" class="pjax-load btn btn-eclo-light btn-sm border-0 py-1 px-2 rounded-3" aria-label="' . $jatbi->lang('Xem') . '"><i class="ti ti-eye"></i></a>',
                    ];
                }
            );

            $totals = [
                "tong-dauky" => number_format($sum_dauky, 1),
                "tong-trongky" => number_format($sum_trongky, 1),
                "tong-xuatkho" => number_format($sum_xuatkho, 1),
                "tong-huy" => number_format($sum_huy, 1),
                "tong-chuyen" => number_format($chuyen, 1),
                "tong-cuoiky" => number_format($sum_cuoiky, 1),
                "tong-price" => number_format($sum_price, 0),
            ];


            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas ?? [],
                "footerData" => $totals,
            ]);
        }
    })->setPermissions(['inventory_ingredient']);


    $app->router("/inventory_crafting/{type}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores, $accStore, $template) {
        $vars['title'] = $jatbi->lang("Xuất nhập tồn kho chế tác");

        if ($app->method() === 'GET') {
            // Lấy dữ liệu cho form lọc
            $vars["pearls"] = array_merge([["value" => "", "text" => "Tất cả"]], $app->select("pearl", ["id(value)", "name(text)"], ["deleted" => 0, "status" => 'A']));
            $vars["sizes"] = array_merge([["value" => "", "text" => "Tất cả"]], $app->select("sizes", ["id(value)", "name(text)"], ["deleted" => 0, "status" => 'A']));
            $vars["colors"] = array_merge([["value" => "", "text" => "Tất cả"]], $app->select("colors", ["id(value)", "name(text)"], ["deleted" => 0, "status" => 'A']));
            $vars["categorys"] = array_merge([["value" => "", "text" => "Tất cả"]], $app->select("categorys", ["id(value)", "name(text)"], ["deleted" => 0, "status" => 'A']));
            $vars["products_group"] = array_merge([["value" => "", "text" => "Tất cả"]], $app->select("products_group", ["id(value)", "name(text)"], ["deleted" => 0, "status" => 'A']));
            $vars["units"] = array_merge([["value" => "", "text" => "Tất cả"]], $app->select("units", ["id(value)", "name(text)"], ["deleted" => 0, "status" => 'A']));


            // Xử lý cửa hàng
            array_unshift($stores, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars['stores'] = $stores;

            // Render template
            echo $app->render($template . '/reports/inventory_crafting.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            // DataTables parameters
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : $setting['site_page'] ?? 10;
            $searchValue = isset($_POST['search']['value']) ? $app->xss($_POST['search']['value']) : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

            // Filter parameters
            $filters = [
                'type' => isset($_POST['type']) ? $app->xss($_POST['type']) : '',
                'pearl' => isset($_POST['pearl']) ? $app->xss($_POST['pearl']) : '',
                'sizes' => isset($_POST['sizes']) ? $app->xss($_POST['sizes']) : '',
                'colors' => isset($_POST['colors']) ? $app->xss($_POST['colors']) : '',
                'categorys' => isset($_POST['categorys']) ? $app->xss($_POST['categorys']) : '',
                'group' => isset($_POST['group']) ? $app->xss($_POST['group']) : '',
                'units' => isset($_POST['units']) ? $app->xss($_POST['units']) : '',
                'status' => isset($_POST['status']) ? $app->xss($_POST['status']) : '',
                'price' => isset($_POST['price']) ? str_replace(',', '', $app->xss($_POST['price'])) : '',
                'date' => isset($_POST['date']) ? $app->xss($_POST['date']) : '',
            ];

            // Process date range
            if ($filters['date']) {
                $date = explode('-', $filters['date']);
                $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date[0]))));
                $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date[1]))));
            } else {
                $date_from = date('Y-m-d 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }

            // Xử lý cửa hàng  
            $store = isset($_POST['stores']) && $app->xss($_POST['stores']) !== '' ? (array) $app->xss($_POST['stores']) : $accStore;

            // Build where clause
            $where = [
                "AND" => [
                    "ingredient.code[~]" => $searchValue,
                    "ingredient.deleted" => 0,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => ["ingredient.$orderName" => strtoupper($orderDir)],
            ];
            foreach (['type', 'pearl', 'sizes', 'colors', 'categorys', 'group', 'units'] as $field) {
                if ($filters[$field]) {
                    $where['AND']["ingredient.$field"] = $filters[$field];
                }
            }
            if ($filters['status']) {
                $where['AND']['ingredient.status'] = $filters['status'];
            } else {
                $where['AND']['ingredient.status'] = ['A', 'D'];
            }
            if ($filters['price']) {
                $where['AND']['ingredient.price[<>]'] = [$filters['price'], $filters['price']];
            }
            // if (!empty($store) && $store !== [0]) {
            //     $where['AND']['ingredient.stores'] = $store;
            // }
            // Count total records
            $count = $app->count("ingredient", ["AND" => $where['AND']]);

            // Calculate initial and in-period quantities
            $getStarts = $app->select("warehouses_logs", [
                "[>]warehouses" => ["warehouses" => "id"],
            ], [
                "warehouses_logs.id",
                "warehouses_logs.amount",
                "warehouses_logs.type",
                "warehouses_logs.ingredient",
                "warehouses_logs.data",
                "warehouses_logs.group_crafting",
                "warehouses_logs.warehouses",
            ], [
                "AND" => [
                    "warehouses_logs.date[<]" => $date_from,
                    "warehouses_logs.ingredient[>]" => 0,
                    "warehouses_logs.amount[>]" => 0,
                ],
            ]);
            $dauky = [];
            foreach ($getStarts as $getStart) {
                $ingredientId = $getStart['ingredient'] ?? '';
                $type = $getStart['type'] ?? '';
                $dataKey = $getStart['data'] ?? '';
                $groupCrafting = $getStart['group_crafting'] ?? '';
                $dauky[$getStart['ingredient']][$getStart['type']][$getStart['data']][$getStart['group_crafting']]['amount'] =
                    ($dauky[$getStart['ingredient']][$getStart['type']][$getStart['data']][$getStart['group_crafting']]['amount'] ?? 0) + (float) $getStart['amount'];
            }

            $getIns = $app->select("warehouses_logs", [
                "[>]warehouses" => ["warehouses" => "id"],
            ], [
                "warehouses_logs.id",
                "warehouses_logs.amount",
                "warehouses_logs.type",
                "warehouses_logs.ingredient",
                "warehouses_logs.data",
                "warehouses_logs.group_crafting",
                "warehouses_logs.warehouses",
            ], [
                "AND" => [
                    "warehouses_logs.date[<>]" => [$date_from, $date_to],
                    "warehouses_logs.ingredient[>]" => 0,
                    "warehouses_logs.amount[>]" => 0,
                ],
            ]);
            $trongky = [];
            foreach ($getIns as $getIn) {
                $trongky[$getIn['ingredient']][$getIn['type']][$getIn['data']][$getIn['group_crafting']]['amount'] =
                    ($trongky[$getIn['ingredient']][$getIn['type']][$getIn['data']][$getIn['group_crafting']]['amount'] ?? 0) + (float) $getIn['amount'];
            }

            // Fetch ingredient data
            $datas = [];
            $sum_dauky = 0;
            $sum_trongky = 0;
            $sum_xuatkho = 0;
            $sum_huy = 0;
            $sum_cuoiky = 0;
            $sum_price = 0;

            $crafting_type = $vars['type'];
            $loaichetac = $crafting_type === 'gold' ? '1' : ($crafting_type === 'silver' ? '2' : '3');
            $tenchetac = $crafting_type === 'gold' ? 'Vàng' : ($crafting_type === 'silver' ? 'Bạc' : 'Chuỗi');
            $detail_type = $crafting_type === 'gold' ? 'craftinggold-details' : ($crafting_type === 'silver' ? 'craftingsilver-details' : 'craftingchain-details');

            $app->select(
                "ingredient",
                [
                    "[>]pearl" => ["pearl" => "id"],
                    "[>]sizes" => ["sizes" => "id"],
                    "[>]colors" => ["colors" => "id"],
                    "[>]categorys" => ["categorys" => "id"],
                    "[>]products_group" => ["group" => "id"],
                    "[>]units" => ["units" => "id"],
                ],
                [
                    "ingredient.id",
                    "ingredient.type",
                    "ingredient.code",
                    "ingredient.name_ingredient",
                    "ingredient.pearl",
                    "ingredient.sizes",
                    "ingredient.colors",
                    "ingredient.categorys",
                    "ingredient.group",
                    "ingredient.units",
                    "ingredient.price",
                    "pearl.name(pearl_name)",
                    "sizes.name(size_name)",
                    "colors.name(color_name)",
                    "categorys.name(category_name)",
                    "products_group.name(group_name)",
                    "units.name(unit_name)"
                ],
                $where,
                function ($data) use (&$datas, &$sum_dauky, &$sum_trongky, &$sum_xuatkho, &$sum_huy, &$sum_cuoiky, &$sum_price, $app, $jatbi, $dauky, $trongky, $loaichetac, $tenchetac, $detail_type) {
                    // Calculate quantities with safe checks
                    $ton_dauki = (float) (isset($dauky[$data['id']]['import']['crafting'][$loaichetac]['amount']) ? $dauky[$data['id']]['import']['crafting'][$loaichetac]['amount'] : 0) -
                        (float) (isset($dauky[$data['id']]['export']['crafting'][$loaichetac]['amount']) ? $dauky[$data['id']]['export']['crafting'][$loaichetac]['amount'] : 0) -
                        (float) (isset($dauky[$data['id']]['pairing']['crafting'][$loaichetac]['amount']) ? $dauky[$data['id']]['pairing']['crafting'][$loaichetac]['amount'] : 0) -
                        (float) (isset($dauky[$data['id']]['cancel']['crafting'][$loaichetac]['amount']) ? $dauky[$data['id']]['cancel']['crafting'][$loaichetac]['amount'] : 0);

                    $ton = $ton_dauki +
                        (float) (isset($trongky[$data['id']]['import']['crafting'][$loaichetac]['amount']) ? $trongky[$data['id']]['import']['crafting'][$loaichetac]['amount'] : 0) -
                        (float) (isset($trongky[$data['id']]['export']['crafting'][$loaichetac]['amount']) ? $trongky[$data['id']]['export']['crafting'][$loaichetac]['amount'] : 0) -
                        (float) (isset($trongky[$data['id']]['pairing']['crafting'][$loaichetac]['amount']) ? $trongky[$data['id']]['pairing']['crafting'][$loaichetac]['amount'] : 0) -
                        (float) (isset($trongky[$data['id']]['cancel']['crafting'][$loaichetac]['amount']) ? $trongky[$data['id']]['cancel']['crafting'][$loaichetac]['amount'] : 0);

                    // Update sums
                    $sum_dauky += $ton_dauki;
                    $sum_trongky += (float) (isset($trongky[$data['id']]['import']['crafting'][$loaichetac]['amount']) ? $trongky[$data['id']]['import']['crafting'][$loaichetac]['amount'] : 0);
                    $sum_xuatkho += (float) (isset($trongky[$data['id']]['export']['crafting'][$loaichetac]['amount']) ? $trongky[$data['id']]['export']['crafting'][$loaichetac]['amount'] : 0) +
                        (float) (isset($trongky[$data['id']]['pairing']['crafting'][$loaichetac]['amount']) ? $trongky[$data['id']]['pairing']['crafting'][$loaichetac]['amount'] : 0);
                    $sum_huy += (float) (isset($trongky[$data['id']]['cancel']['crafting'][$loaichetac]['amount']) ? $trongky[$data['id']]['cancel']['crafting'][$loaichetac]['amount'] : 0);
                    $sum_cuoiky += $ton;
                    $sum_price += (float) ($data['price'] ?? 0);

                    // Determine type and attributes
                    $loai = $data['type'] == 1 ? 'Đai' : ($data['type'] == 2 ? 'Ngọc' : 'Khác');
                    $thuoctinh = '';
                    if ($data['type'] == 1) {
                        $thuoctinh = ($data['category_name'] ?? '') . '_' . ($data['color_name'] ?? '') . '_' . ($data['group_name'] ?? '');
                    } elseif ($data['type'] == 2) {
                        $thuoctinh = ($data['pearl_name'] ?? '') . '_' . ($data['size_name'] ?? '') . '_' . ($data['color_name'] ?? '');
                    } elseif ($data['type'] == 3) {
                        $thuoctinh = $data['group_name'] ?? '';
                    }

                    $datas[] = [
                        "loai" => $loai,
                        "ma-nguyen-lieu" => $data['code'],
                        "ten-nguyen-lieu" => $data['name_ingredient'],
                        "thuoc-tinh" => $thuoctinh,
                        "don-vi" => $data['unit_name'] ?? '',
                        "don-gia" => number_format($data['price'] ?? 0, 0),
                        "che-tac" => $tenchetac,
                        "so-luong-dau-ky" => number_format($ton_dauki, 1),
                        "so-luong-nhap" => (isset($trongky[$data['id']]['import']['crafting'][$loaichetac]['amount']) && $trongky[$data['id']]['import']['crafting'][$loaichetac]['amount'] != 0) ? number_format($trongky[$data['id']]['import']['crafting'][$loaichetac]['amount'], 1) : '',
                        "so-luong-xuat" => ((isset($trongky[$data['id']]['export']['crafting'][$loaichetac]['amount']) && $trongky[$data['id']]['export']['crafting'][$loaichetac]['amount'] != 0) || (isset($trongky[$data['id']]['pairing']['crafting'][$loaichetac]['amount']) && $trongky[$data['id']]['pairing']['crafting'][$loaichetac]['amount'] != 0)) ? number_format((float) (isset($trongky[$data['id']]['export']['crafting'][$loaichetac]['amount']) ? $trongky[$data['id']]['export']['crafting'][$loaichetac]['amount'] : 0) + (float) (isset($trongky[$data['id']]['pairing']['crafting'][$loaichetac]['amount']) ? $trongky[$data['id']]['pairing']['crafting'][$loaichetac]['amount'] : 0), 1) : '',
                        "so-luong-huy" => (isset($trongky[$data['id']]['cancel']['crafting'][$loaichetac]['amount']) && $trongky[$data['id']]['cancel']['crafting'][$loaichetac]['amount'] != 0) ? number_format($trongky[$data['id']]['cancel']['crafting'][$loaichetac]['amount'], 1) : '',
                        "so-luong-cuoi-ky" => number_format($ton, 1),
                        "action" => '<a class="pjax-load btn btn-eclo-light btn-sm border-0 py-1 px-2 rounded-3"  aria-label="' . $jatbi->lang('Xem') . '" href="/crafting/' . $detail_type . '/' . $data['id'] . '"><i class="ti ti-eye"></i></a>',
                    ];
                }
            );

            $totals = [
                "tong-dauky" => number_format($sum_dauky, 1),
                "tong-trongky" => number_format($sum_trongky, 1),
                "tong-xuatkho" => number_format($sum_xuatkho, 1),
                "tong-huy" => number_format($sum_huy, 1),
                "tong-cuoiky" => number_format($sum_cuoiky, 1),
                "tong-price" => number_format($sum_price, 0),
            ];

            // Return JSON data
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas ?? [],
                "footerData" => $totals,
            ]);
        }
    })->setPermissions(['inventory_crafting']);







    $app->router('/inventory_crafting_excel/{type}', 'GET', function ($vars) use ($app, $jatbi, $setting, $accStore) {
        try {
            // --- BƯỚC 1: LẤY VÀ XỬ LÝ CÁC BỘ LỌC TỪ URL ---
            $crafting_type = $vars['type'] ?? 'gold';
            $filters = [
                'type'      => $_GET['type'] ?? '',
                'pearl'     => $_GET['pearl'] ?? '',
                'sizes'     => $_GET['sizes'] ?? '',
                'colors'    => $_GET['colors'] ?? '',
                'categorys' => $_GET['categorys'] ?? '',
                'group'     => $_GET['group'] ?? '',
                'units'     => $_GET['units'] ?? '',
                'status'    => $_GET['status'] ?? '',
                'price'     => isset($_GET['price']) ? str_replace(',', '', $_GET['price']) : '',
                'date'      => $_GET['date'] ?? '',
            ];
            $searchValue = $_GET['search']['value'] ?? '';
            $store = isset($_GET['stores']) && $_GET['stores'] !== '' ? (array)$_GET['stores'] : $accStore;

            $loaichetac = $crafting_type === 'gold' ? '1' : ($crafting_type === 'silver' ? '2' : '3');
            $tenchetac = $crafting_type === 'gold' ? 'Vàng' : ($crafting_type === 'silver' ? 'Bạc' : 'Chuỗi');

            if ($filters['date']) {
                $date = explode(' - ', $filters['date']);
                $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date[0]))));
                $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date[1]))));
            } else {
                $date_from = date('Y-m-01 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }

            // --- BƯỚC 2: LẤY DỮ LIỆU TỒN KHO (TỐI ƯU HÓA BỘ NHỚ) ---

            // 2.1. Tính tổng đầu kỳ bằng SQL
            // Sửa lỗi: Dùng \Medoo\Medoo::raw để PHP tìm thấy class chính xác
            $start_aggregated = $app->select("warehouses_logs", [
                "ingredient",
                "type",
                "data",
                "group_crafting",
                "total_amount" => \Medoo\Medoo::raw('SUM(<amount>)')
            ], ["date[<]" => $date_from, "ingredient[>]" => 0, "amount[>]" => 0, "GROUP" => ["ingredient", "type", "data", "group_crafting"]]);

            $dauky = [];
            foreach ($start_aggregated as $item) {
                $dauky[$item['ingredient']][$item['type']][$item['data']][$item['group_crafting']]['amount'] = (float) $item['total_amount'];
            }

            // 2.2. Tính tổng trong kỳ bằng SQL

            $in_period_aggregated = $app->select("warehouses_logs", [
                "ingredient",
                "type",
                "data",
                "group_crafting",
                "total_amount" => \Medoo\Medoo::raw('SUM(<amount>)')
            ], ["date[<>]" => [$date_from, $date_to], "ingredient[>]" => 0, "amount[>]" => 0, "GROUP" => ["ingredient", "type", "data", "group_crafting"]]);

            $trongky = [];
            foreach ($in_period_aggregated as $item) {
                $trongky[$item['ingredient']][$item['type']][$item['data']][$item['group_crafting']]['amount'] = (float) $item['total_amount'];
            }

            // --- BƯỚC 3: LẤY DANH SÁCH NGUYÊN LIỆU ---
            $where = ["AND" => ["ingredient.deleted" => 0]];
            if (!empty($searchValue)) {
                $where['AND']['OR'] = ["ingredient.code[~]" => $searchValue, "ingredient.name_ingredient[~]" => $searchValue];
            }

            foreach (['type', 'pearl', 'sizes', 'colors', 'categorys', 'group', 'units'] as $field) {
                if (!empty($filters[$field])) {
                    $where['AND']["ingredient.$field"] = $filters[$field];
                }
            }
            if (!empty($filters['status'])) {
                $where['AND']['ingredient.status'] = $filters['status'];
            }
            if (!empty($filters['price'])) {
                $where['AND']['ingredient.price'] = $filters['price'];
            }

            $ingredients_data = $app->select("ingredient", [
                "[>]pearl" => ["pearl" => "id"],
                "[>]sizes" => ["sizes" => "id"],
                "[>]colors" => ["colors" => "id"],
                "[>]categorys" => ["categorys" => "id"],
                "[>]products_group" => ["group" => "id"],
                "[>]units" => ["units" => "id"],
            ], [
                "ingredient.id",
                "ingredient.type",
                "ingredient.code",
                "ingredient.name_ingredient",
                "ingredient.price",
                "pearl.name(pearl_name)",
                "sizes.name(size_name)",
                "colors.name(color_name)",
                "categorys.name(category_name)",
                "products_group.name(group_name)",
                "units.name(unit_name)"
            ], $where);

            // --- BƯỚC 4: TẠO FILE EXCEL ---
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('XuatNhapTonCheTac');
            $headers = ['LOẠI', 'MÃ NGUYÊN LIỆU', 'TÊN NGUYÊN LIỆU', 'THUỘC TÍNH', 'ĐƠN VỊ', 'CHẾ TÁC', 'SỐ LƯỢNG ĐẦU KỲ', 'SỐ LƯỢNG NHẬP', 'SỐ LƯỢNG XUẤT', 'SỐ LƯỢNG HỦY', 'SỐ LƯỢNG CUỐI KỲ', 'ĐƠN GIÁ'];
            $sheet->fromArray($headers, null, 'A1');

            $sum_dauky = $sum_trongky = $sum_xuatkho = $sum_huy = $sum_cuoiky = $sum_price = 0;
            $rowIndex = 1;

            foreach ($ingredients_data as $data) {
                $rowIndex++;

                $ton_dauki = ($dauky[$data['id']]['import']['crafting'][$loaichetac]['amount'] ?? 0) - ($dauky[$data['id']]['export']['crafting'][$loaichetac]['amount'] ?? 0) - ($dauky[$data['id']]['pairing']['crafting'][$loaichetac]['amount'] ?? 0) - ($dauky[$data['id']]['cancel']['crafting'][$loaichetac]['amount'] ?? 0);
                $trongky_nhap = $trongky[$data['id']]['import']['crafting'][$loaichetac]['amount'] ?? 0;
                $trongky_xuat = ($trongky[$data['id']]['export']['crafting'][$loaichetac]['amount'] ?? 0) + ($trongky[$data['id']]['pairing']['crafting'][$loaichetac]['amount'] ?? 0);
                $trongky_huy = $trongky[$data['id']]['cancel']['crafting'][$loaichetac]['amount'] ?? 0;
                $ton_cuoiky = $ton_dauki + $trongky_nhap - $trongky_xuat - $trongky_huy;

                $loai = '';
                $thuoctinh = '';
                if ($data['type'] == 1) {
                    $loai = 'Đai';
                    $thuoctinh = ($data['category_name'] ?? '') . '_' . ($data['color_name'] ?? '') . '_' . ($data['group_name'] ?? '');
                }
                if ($data['type'] == 2) {
                    $loai = 'Ngọc';
                    $thuoctinh = ($data['pearl_name'] ?? '') . '_' . ($data['size_name'] ?? '') . '_' . ($data['color_name'] ?? '');
                }
                if ($data['type'] == 3) {
                    $loai = 'Khác';
                    $thuoctinh = $data['group_name'] ?? '';
                }

                $sheet->setCellValue('A' . $rowIndex, $loai);
                $sheet->setCellValue('B' . $rowIndex, $data['code']);
                $sheet->setCellValue('C' . $rowIndex, $data['name_ingredient']);
                $sheet->setCellValue('D' . $rowIndex, $thuoctinh);
                $sheet->setCellValue('E' . $rowIndex, $data['unit_name']);
                $sheet->setCellValue('F' . $rowIndex, $tenchetac);
                $sheet->setCellValue('G' . $rowIndex, $ton_dauki);
                $sheet->setCellValue('H' . $rowIndex, $trongky_nhap);
                $sheet->setCellValue('I' . $rowIndex, $trongky_xuat);
                $sheet->setCellValue('J' . $rowIndex, $trongky_huy);
                $sheet->setCellValue('K' . $rowIndex, $ton_cuoiky);
                $sheet->setCellValue('L' . $rowIndex, (float)($data['price'] ?? 0));

                $sum_dauky += $ton_dauki;
                $sum_trongky += $trongky_nhap;
                $sum_xuatkho += $trongky_xuat;
                $sum_huy += $trongky_huy;
                $sum_cuoiky += $ton_cuoiky;
                $sum_price += (float)($data['price'] ?? 0);
            }

            $totalRow = $rowIndex + 1;
            $sheet->setCellValue('F' . $totalRow, 'Tổng');
            $sheet->setCellValue('G' . $totalRow, $sum_dauky);
            $sheet->setCellValue('H' . $totalRow, $sum_trongky);
            $sheet->setCellValue('I' . $totalRow, $sum_xuatkho);
            $sheet->setCellValue('J' . $totalRow, $sum_huy);
            $sheet->setCellValue('K' . $totalRow, $sum_cuoiky);
            $sheet->setCellValue('L' . $totalRow, $sum_price);

            // Định dạng cột Đơn giá và Tổng đơn giá có dấu phẩy
            $sheet->getStyle('L2:L' . $totalRow)->getNumberFormat()->setFormatCode('#,##0');

            // Định dạng các cột số lượng có 1 chữ số thập phân (nếu cần)
            $sheet->getStyle('G2:K' . $totalRow)->getNumberFormat()->setFormatCode('#,##0.0');

            foreach (range('A', 'L') as $columnID) {
                $sheet->getColumnDimension($columnID)->setAutoSize(true);
            }

            // --- BƯỚC 5: XUẤT FILE ---
            ob_end_clean();
            $file_name = 'xuat-nhap-ton-che-tac-' . $crafting_type . '-' . date('d-m-Y') . '.xls';
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="' . $file_name . '"');
            header('Cache-Control: max-age=0');
            $writer = new Xls($spreadsheet);
            $writer->save('php://output');
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            exit("Lỗi: " . $e->getMessage());
        }
    })->setPermissions(['inventory_crafting']);


    $app->router("/selling_products", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores, $accStore, $template) {
        $vars['title'] = $jatbi->lang("Sản phẩm bán chạy");

        if ($app->method() === 'GET') {
            // Lấy dữ liệu cho form lọc
            $mapData = function ($item) {
                return ['value' => $item['id'], 'text' => $item['code'] . ' - ' . $item['name']];
            };

            // Lấy và xử lý cho Danh mục
            $categorys_data = $app->select("categorys", ["id", "name", "code"], ["deleted" => 0, "status" => 'A']);
            $vars["categorys"] = array_merge(
                [['value' => '', 'text' => $jatbi->lang("Tất cả Danh mục")]],
                array_map($mapData, $categorys_data)
            );

            // Lấy và xử lý cho Nhóm sản phẩm
            $products_group_data = $app->select("products_group", ["id", "name", "code"], ["deleted" => 0, "status" => 'A']);
            $vars["products_group"] = array_merge(
                [['value' => '', 'text' => $jatbi->lang("Tất cả Nhóm sản phẩm")]],
                array_map($mapData, $products_group_data)
            );
            // Lấy và xử lý cho Đơn vị
            $units_data = $app->select("units", ["id(value)", "name(text)"], ["deleted" => 0, "status" => 'A']);
            $vars["units"] = array_merge([['value' => '', 'text' => $jatbi->lang("Tất cả Đơn vị")]], $units_data);

            // Lấy và xử lý cho Cửa hàng
            $stores_data = $app->select("stores", ["id(value)", "name(text)"], ["deleted" => 0, "status" => 'A']);
            $vars["stores"] = array_merge([['value' => '', 'text' => $jatbi->lang("Tất cả Cửa hàng")]], $stores_data);

            // Lấy và xử lý cho Quầy hàng
            $branches_data = $app->select("branch", ["id(value)", "name(text)"], ["deleted" => 0, "status" => 'A']);
            $vars["branches"] = array_merge([['value' => '', 'text' => $jatbi->lang("Tất cả Quầy hàng")]], $branches_data);


            $vars['stores'] = $stores;

            // Render template
            echo $app->render($template . '/reports/selling_products.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            // DataTables parameters
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : $setting['site_page'] ?? 10;
            $searchValue = isset($_POST['search']['value']) ? $app->xss($_POST['search']['value']) : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'amount';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

            // Filter parameters
            $filters = [
                'categorys' => isset($_POST['categorys']) ? $app->xss($_POST['categorys']) : '',
                'products_group' => isset($_POST['products_group']) ? $app->xss($_POST['products_group']) : '',
                'stores' => isset($_POST['stores']) ? $app->xss($_POST['stores']) : '',
                'date' => isset($_POST['date']) ? $app->xss($_POST['date']) : '',
            ];

            // Process date range
            if ($filters['date']) {
                $date = explode('-', $filters['date']);
                $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date[0]))));
                $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date[1]))));
            } else {
                $date_from = date('Y-m-01 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }
            $store = $filters['stores'] !== '' ? (array) $filters['stores'] : (!empty($accStore) ? $accStore : [0]);

            // Build where clause for invoices_products
            $product_ids = array_values($app->select("products", "id", ["deleted" => 0]));
            $where = [
                "AND" => [
                    "invoices_products.deleted" => 0,
                    "invoices_products.date[<>]" => [$date_from, $date_to],
                    // "invoices_products.products" => $app->select("products", "id", ["deleted" => 0]),
                    "products.deleted" => 0,
                ],
            ];
            // var_dump($product_ids);

            if ($filters['categorys']) {
                $where['AND']['invoices_products.categorys'] = $filters['categorys'];
            }
            if ($filters['products_group']) {
                $where['AND']['invoices_products.products_group'] = $filters['products_group'];
            }
            if ($store !== [0]) {
                $where['AND']['invoices_products.stores'] = $store;
            }
            if ($searchValue) {
                $where['AND']['products.name[~]'] = $searchValue;
            }

            // Fetch invoice products
            $invoices_products = $app->select("invoices_products", [
                "[>]products" => ["products" => "id"],
                "[>]categorys" => ["categorys" => "id"],
                "[>]products_group" => ["products_group" => "id"],
                "[>]units" => ["products.units" => "id"],
                "[>]stores" => ["stores" => "id"],
                "[>]branch" => ["products.branch" => "id"],
            ], [
                "invoices_products.products",
                "invoices_products.amount",
                "invoices_products.categorys",
                "invoices_products.products_group",
                "invoices_products.stores",
                "products.code",
                "products.name",
                "products.price",
                "products.units",
                "products.branch",
                "categorys.name(category_name)",
                "products_group.name(group_name)",
                "units.name(unit_name)",
                "stores.name(store_name)",
                "branch.name(branch_name)",
            ], $where);

            // Aggregate product quantities
            $products_buy_top = [];
            foreach ($invoices_products as $invoice_product) {
                $product_id = $invoice_product['products'];
                $products_buy_top[$product_id]['amount'] = ($products_buy_top[$product_id]['amount'] ?? 0) + (float) $invoice_product['amount'];
                $products_buy_top[$product_id]['id'] = $product_id;
                $products_buy_top[$product_id]['code'] = $invoice_product['code'] ?? 'N/A';
                $products_buy_top[$product_id]['name'] = $invoice_product['name'] ?? 'Sản phẩm không xác định';
                $products_buy_top[$product_id]['categorys'] = $invoice_product['categorys'] ?? '';
                $products_buy_top[$product_id]['products_group'] = $invoice_product['products_group'] ?? '';
                $products_buy_top[$product_id]['price'] = $invoice_product['price'] ?? 0;
                $products_buy_top[$product_id]['units'] = $invoice_product['units'] ?? '';
                $products_buy_top[$product_id]['stores'] = $invoice_product['stores'] ?? '';
                $products_buy_top[$product_id]['branch'] = $invoice_product['branch'] ?? '';
                $products_buy_top[$product_id]['category_name'] = $invoice_product['category_name'] ?? 'Không xác định';
                $products_buy_top[$product_id]['group_name'] = $invoice_product['group_name'] ?? 'Không xác định';
                $products_buy_top[$product_id]['unit_name'] = $invoice_product['unit_name'] ?? 'Không xác định';
                $products_buy_top[$product_id]['store_name'] = $invoice_product['store_name'] ?? 'Không xác định';
                $products_buy_top[$product_id]['branch_name'] = $invoice_product['branch_name'] ?? 'Không xác định';
            }

            // Sort and limit to top 500 products
            usort($products_buy_top, function ($a, $b) {
                return $b['amount'] <=> $a['amount'];
            });
            $products_buy_top = array_slice($products_buy_top, 0, 500); // Limit to top 500
            $new_products_buy_top = array_slice($products_buy_top, $start, $length); // Paginate

            // Prepare data for DataTables
            $datas = [];
            foreach ($new_products_buy_top as $key => $product) {
                $datas[] = [
                    "stt" => $start + $key + 1,
                    "ma-san-pham" => $product['code'],
                    "ten" => $product['name'],
                    "danh-muc" => $product['category_name'],
                    "nhom-san-pham" => $product['group_name'],
                    "so-luong" => number_format($product['amount'], 1),
                    "don-vi" => $product['unit_name'],
                    "don-gia" => number_format($product['price'], 0),
                    "cua-hang" => $product['store_name'],
                    "quay-hang" => $product['branch_name'],
                ];
            }

            // Count total records (limited to 500)
            $count = min(count($products_buy_top), 500);

            // Return JSON data
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas,
                "footerData" => [],
            ]);
        }
    })->setPermissions(['selling_products']);
})->middleware('login');
