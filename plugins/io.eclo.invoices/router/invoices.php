<?php
if (!defined('ECLO'))
    die("Hacking attempt");



use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;


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
$app->group($setting['manager'] . "/invoices", function ($app) use ($jatbi, $setting, $accStore, $stores,$template) {
    // Chứng từ bán hàng 

    // $app->router('/license_sale', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
    //     $vars['title'] = $jatbi->lang("Chứng từ bán hàng");

    //     if ($app->method() === 'GET') {
    //         $vars['stores'] = array_merge([["value" => "", "text" => $jatbi->lang("Tất cả")]], $app->select("stores", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A']));
    //         $vars['accounts'] = array_merge([["value" => "", "text" => $jatbi->lang("Tất cả")]], $app->select("accounts", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A']));
    //         $vars['personnels'] = array_merge([["value" => "", "text" => $jatbi->lang("Tất cả")]], $app->select("personnels", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A']));
    //         $vars['branchs'] = array_merge([["value" => "", "text" => $jatbi->lang("Tất cả")]], $app->select("branch", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A']));
    //         $vars['title'] = $jatbi->lang("Chứng từ bán hàng");
    //         echo $app->render($setting['template'] . '/invoices/license-sale.html', $vars);

    //     } elseif ($app->method() === 'POST') {
    //         $app->header(['Content-Type' => 'application/json; charset=utf-8']);

    //         // --- 1. Đọc tham số ---
    //         $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
    //         $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    //         $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
    //         $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
    //         $orderName = isset($_POST['order'][0]['column']) ? $_POST['columns'][$_POST['order'][0]['column']]['name'] : 'invoices_products.id';
    //         $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

    //         $filter_code_group = isset($_POST['code_group']) ? trim($_POST['code_group']) : '';
    //         $filter_user = isset($_POST['user']) ? trim($_POST['user']) : '';
    //         $filter_personnels = isset($_POST['personnels']) ? trim($_POST['personnels']) : '';
    //         $filter_status = isset($_POST['status']) ? trim($_POST['status']) : '';
    //         $storeFilter = isset($_POST['stores']) ? $_POST['stores'] : '';
    //         $filter_branch = isset($_POST['branch']) ? trim($_POST['branch']) : '';
    //         $filter_date = isset($_POST['date']) ? trim($_POST['date']) : '';

    //         // --- 2. Xây dựng truy vấn với JOIN ---
    //         $joins = [
    //             "[>]invoices" => ["invoices" => "id"],
    //             "[>]products" => ["products" => "id"],
    //             "[>]units" => ["products.units" => "id"],
    //             "[>]stores" => ["invoices_products.stores" => "id"],
    //             "[>]branch" => ["invoices_products.branch" => "id"]
    //         ];

    //         $where = [
    //             "AND" => [
    //                 "invoices_products.deleted" => 0,
    //                 "invoices_products.cancel" => [0, 1],
    //                 "invoices.status" => 2,
    //             ],
    //         ];

    //         // Áp dụng bộ lọc
    //         if (!empty($searchValue)) {
    //             $where['AND']['OR'] = [
    //                 "invoices.code_group[~]" => $searchValue,
    //                 "products.name[~]" => $searchValue,
    //                 "products.code[~]" => $searchValue,
    //             ];
    //         }
    //         if (!empty($storeFilter)) {
    //             $where['AND']['invoices_products.stores'] = $storeFilter;
    //         }
    //         if (!empty($filter_branch)) {
    //             $where['AND']['invoices_products.branch'] = $filter_branch;
    //         }
    //         if (!empty($filter_code_group)) {
    //             $where['AND']['invoices.code_group[~]'] = $filter_code_group;
    //         }
    //         if (!empty($filter_user)) {
    //             $where['AND']['invoices.user'] = $filter_user;
    //         }
    //         if (!empty($filter_status)) {
    //             $where['AND']['invoices.status'] = $filter_status;
    //         }
    //         if (!empty($filter_personnels)) {
    //             $invoice_ids = $app->select("invoices_personnels", "invoices", ["personnels" => $filter_personnels]);
    //             $where['AND']['invoices_products.invoices'] = $invoice_ids;
    //         }

    //         if (!empty($filter_date)) {
    //             $date_parts = explode(' - ', $filter_date);
    //             if (count($date_parts) == 2) {
    //                 $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date_parts[0]))));
    //                 $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date_parts[1]))));
    //             }
    //         } else {
    //             $date_from = date('Y-m-d 00:00:00');
    //             $date_to = date('Y-m-d 23:59:59');
    //         }
    //         $where['AND']['invoices_products.date_poster[<>]'] = [$date_from, $date_to];

    //         // --- 3. Đếm và tính tổng ---
    //         $count = $app->count("invoices_products", $joins, "invoices_products.id", $where);

    //         // Tính tổng cho toàn bộ dữ liệu đã lọc
    //         $total_mua_grand = 0;
    //         $total_tra_grand = 0;
    //         $total_quantity_filtered = 0;

    //         $where_for_totals = $where;
    //         unset($where_for_totals['LIMIT'], $where_for_totals['ORDER']);

    //         // SỬA LỖI: Truy vấn tính tổng cần phải có JOIN
    //         $all_filtered_data = $app->select("invoices_products", $joins, [
    //             'invoices_products.price',
    //             'invoices_products.type',
    //             'invoices_products.amount',
    //             'invoices_products.discount_priceinvoices'
    //         ], $where_for_totals);

    //         foreach ($all_filtered_data as $loc) {
    //             $thanh_tien = ($loc['price'] - $loc['discount_priceinvoices']) * $loc['amount'];
    //             if ($loc['type'] == 3) {
    //                 $total_tra_grand += $thanh_tien;
    //                 $total_quantity_filtered -= $loc['amount'];
    //             } else {
    //                 $total_mua_grand += $thanh_tien;
    //                 $total_quantity_filtered += $loc['amount'];
    //             }
    //         }

    //         // Thêm sắp xếp và phân trang
    //         $where["ORDER"] = [$orderName => strtoupper($orderDir)];
    //         $where["LIMIT"] = [$start, $length];

    //         // --- 4. Lấy dữ liệu chính ---
    //         $datas = [];
    //         $total_mua_page = 0;
    //         $total_tra_page = 0;
    //         $total_quantity_page = 0;

    //         $columns = [
    //             'invoices_products.id',
    //             'invoices_products.invoices',
    //             'invoices_products.products',
    //             'invoices_products.price',
    //             'invoices_products.price_old',
    //             'invoices_products.discount_price',
    //             'invoices_products.discount_priceinvoices',
    //             'invoices_products.discount_invoices',
    //             'invoices_products.discount',
    //             'invoices_products.type',
    //             'invoices_products.amount',
    //             'invoices_products.minus',
    //             'invoices_products.date_poster',
    //             'invoices_products.additional_information',
    //             'invoices.code(invoice_code)',
    //             'invoices.code_group',
    //             'products.name(product_name)',
    //             'products.code(product_code)',
    //             'units.name(unit_name)',
    //             'stores.name(store_name)',
    //             'branch.name(branch_name)',
    //             'invoices.code(invoices_code)',
    //             'invoices.status(invoices_status)',
    //         ];

    //         $app->select("invoices_products", $joins, $columns, $where, function ($data) use (&$datas, &$total_mua_page, &$total_tra_page, &$total_quantity_page, $jatbi, $app, $setting) {

    //             $personnel_html = '';
    //             $personnel_data = $app->select("invoices_personnels", ["[>]personnels" => ["personnels" => "id"]], "personnels.name", ["invoices" => $data["invoices"], "invoices_personnels.deleted" => 0]);
    //             foreach ($personnel_data as $per_name) {
    //                 $personnel_html .= ($per_name ?? 'N/A') . '<br>';
    //             }

    //             $giamgiasp = $data['price_old'] - $data['price'];
    //             $price_display = (($data['discount_price'] != 0 || $data['discount_price'] != '') ? $data['discount_price'] :
    //                 ((($data['discount_price'] == 0 && $data['discount_priceinvoices'] == 0 && $data['discount_invoices'] == 0 && $data['discount'] == 0) && $giamgiasp != 0) ? $giamgiasp : ''));
    //             $is_promo = ($giamgiasp != 0 || $data['price'] == 0 || $data['discount'] || $data['minus'] || $data['discount_invoices'] || $data['discount_priceinvoices'] > 0);
    //             $thanh_tien = ($data['price'] - $data['discount_priceinvoices']) * $data['amount'];

    //             // Tính tổng cho trang hiện tại
    //             if ($data['type'] == 3) {
    //                 $total_tra_page += $thanh_tien;
    //                 $total_quantity_page -= $data['amount'];
    //             } else {
    //                 $total_mua_page += $thanh_tien;
    //                 $total_quantity_page += $data['amount'];
    //             }

    //             $datas[] = [
    //                 "checkbox" => $app->component("box", ["data" => $data['id']]),
    //                 "ma_hoa_don" => '<a class="text-nowrap pjax-load" href="/invoices/invoices-views/' . $data['invoices'] . '">#' . $setting['ballot_code']['invoices'] . '-' . $data['invoices_code'] . '' . $data['invoices'] . '</a>',
    //                 "ma_doan" => (string) ($data['code_group'] ?? ''),
    //                 "ma_hang" => (string) ($data['product_code'] ?? ''),
    //                 "ten_hang" => (string) ($data['product_name'] ?? ''),
    //                 "thong_tin_bo_sung" => (string) ($data['additional_information'] ?? ''),
    //                 "don_gia" => number_format($data['price_old'] ?? 0),
    //                 "so_luong" => (string) ($data['type'] == 3 ? '-' . ($data['amount'] ?? 0) : ($data['amount'] ?? 0)),
    //                 "hang_khuyen_mai" => '<input type="checkbox" ' . ($is_promo ? 'checked' : '') . ' disabled>',
    //                 "dvt" => (string) ($data['unit_name'] ?? ''),
    //                 "chiet_khau_sp_percent" => ($data['discount'] == 0 ? '' : number_format($data['discount'])),
    //                 "tien_chiet_khau_sp" => ($price_display == '' ? '' : number_format($price_display)),
    //                 "chiet_khau_dh_percent" => ($data['discount_invoices'] == 0 ? '' : number_format($data['discount_invoices'])),
    //                 "tien_chiet_khau_dh" => ($data['discount_priceinvoices'] == 0 ? '' : number_format($data['discount_priceinvoices'])),
    //                 "giam_tru" => ($data['minus'] == 0 ? '' : number_format($data['minus'])),
    //                 "thanh_tien" => ($data['type'] == 3 ? '-' : '') . number_format($thanh_tien),
    //                 "trang_thai" => '<span class="fw-bold text-' . ($setting['Status_invoices'][$data['invoices_status']]['color'] ?? 'secondary') . '">' . ($setting['Status_invoices'][$data['invoices_status']]['name'] ?? 'Không xác định') . '</span>',
    //                 "nhan_vien_ban_hang" => $personnel_html,
    //                 "ngay" => (string) ($data['date_poster'] ?? ''),
    //                 "cua_hang" => (string) ($data['store_name'] ?? ''),
    //                 "quay_hang" => (string) ($data['branch_name'] ?? ''),
    //                 "action" => $app->component("action", [
    //                     "button" => [
    //                         ['type' => 'button', 'name' => $jatbi->lang("Sửa"), 'permission' => ['license_sale.edit'], 'action' => ['data-url' => '/invoices/license_sale/edit/' . $data['id'], 'data-action' => 'modal']],
    //                         ['type' => 'button', 'name' => $jatbi->lang("Xóa"), 'permission' => ['license_sale.delete'], 'action' => ['data-url' => '/invoices/license_sale/delete?box=' . $data['id'], 'data-action' => 'modal']]
    //                     ]
    //                 ]),
    //             ];
    //         });

    //         // --- 5. Trả về JSON ---
    //         echo json_encode([
    //             "draw" => $draw,
    //             "recordsTotal" => $count,
    //             "recordsFiltered" => $count,
    //             "data" => $datas,
    //             "footerData" => [
    //                 "page_total" => number_format($total_mua_page - $total_tra_page),
    //                 "grand_total" => number_format($total_mua_grand - $total_tra_grand),
    //                 "total_quantity_page" => $total_quantity_page,
    //                 "total_quantity_filtered" => $total_quantity_filtered,
    //                 "total_value_filtered" => number_format($total_mua_grand - $total_tra_grand)
    //             ]
    //         ]);
    //     }
    // })->setPermissions(['license_sale']);




    $app->router('/license_sale', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $accStore, $stores,$template) {
        $vars['title'] = $jatbi->lang("Chứng từ bán hàng");
        if ($app->method() === 'GET') {

            array_unshift($stores, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars['stores'] = $stores;
            $vars['accounts'] = array_merge([["value" => "", "text" => $jatbi->lang("Tất cả")]], $app->select("accounts", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A']));
            $vars['personnels'] = array_merge([["value" => "", "text" => $jatbi->lang("Tất cả")]], $app->select("personnels", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A']));

            $vars['title'] = $jatbi->lang("Chứng từ bán hàng");
            echo $app->render($template. '/invoices/license-sale.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            // 1. Lấy các tham số từ DataTables và form
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['column']) ? $_POST['columns'][$_POST['order'][0]['column']]['name'] : 'invoices_products.id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

            // Lấy các giá trị filter
            $filter_name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $filter_code_group = isset($_POST['code_group']) ? trim($_POST['code_group']) : '';
            $filter_price = isset($_POST['price']) ? str_replace(',', '', trim($_POST['price'])) : '';
            $filter_customers = isset($_POST['customers']) ? trim($_POST['customers']) : '';
            $filter_date = isset($_POST['date']) ? trim($_POST['date']) : '';
            $store = isset($_POST['stores']) ? $app->xss($_POST['stores']) : $accStore;
            $branch = isset($_POST['branch']) ? $app->xss($_POST['branch']) : '';
            $filter_user = isset($_POST['user']) ? trim($_POST['user']) : '';
            $filter_personnels = isset($_POST['personnels']) ? trim($_POST['personnels']) : '';
            $filter_status = isset($_POST['status']) ? trim($_POST['status']) : '';

            // 2. Xây dựng truy vấn
            $joins = [
                "[>]invoices" => ["invoices" => "id"],
                "[>]products" => ["products" => "id"],
                "[>]units" => ["products.units" => "id"],
                "[>]stores" => ["invoices_products.stores" => "id"],
                "[>]branch" => ["invoices_products.branch" => "id"]
            ];

            $where = [
                "AND" => [
                    "invoices_products.deleted" => 0,
                    "invoices_products.cancel" => [0, 1],
                ],
            ];

            // Áp dụng các bộ lọc
            if (!empty($searchValue)) {
                $where['AND']['OR'] = [
                    "products.name[~]" => $searchValue,
                    "products.code[~]" => $searchValue,
                    "invoices.code_group[~]" => $searchValue,
                    "invoices_products.price_old[~]" => str_replace(',', '', $searchValue),
                    "invoices.code[~]" => $searchValue
                ];
            }
            if (!empty($filter_name)) {
                $where['AND']['OR'] = ["products.name[~]" => $filter_name, "products.code[~]" => $filter_name];
            }
            if (!empty($filter_code_group)) {
                $where['AND']['invoices.code_group[~]'] = $filter_code_group;
            }
            if ($filter_price !== '') {
                $where['AND']['invoices_products.price_old'] = $filter_price;
            }
            if ($filter_customers !== '') {
                $where['AND']['invoices.customers'] = $filter_customers;
            }
            if ($branch !== '') {
                $where['AND']['invoices_products.branch'] = $branch;
            }
            if ($store && $store != [0]) {
                $where['AND']['invoices_products.stores'] = $store;
            }
            if (!empty($filter_user)) {
                $where['AND']['invoices.user'] = $filter_user;
            }
            if (!empty($filter_personnels)) {
                $invoice_ids = $app->select("invoices_personnels", "invoices", ["personnels" => $filter_personnels]);
                $where['AND']['invoices_products.invoices'] = $invoice_ids;
            }
            if (!empty($filter_status)) {
                $where['AND']['invoices.status'] = $filter_status;
            }

            if (!empty($filter_date)) {
                // Nếu người dùng chọn ngày, xử lý theo ngày họ chọn
                $date_parts = explode(' - ', $filter_date);
                if (count($date_parts) == 2) {
                    $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date_parts[0]))));
                    $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date_parts[1]))));
                }
            } else {
                // SỬA LỖI: Nếu không có ngày nào được chọn, mặc định là ngày hôm nay
                $date_from = date('Y-m-d 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }

            $where['AND']['invoices_products.date_poster[<>]'] = [$date_from, $date_to];

            // 3. Tính toán tổng toàn bộ (Grand Total)
            $total_mua_grand = 0;
            $total_tra_grand = 0;
            $total_quantity_filtered = 0;

            $where_for_totals = $where;
            unset($where_for_totals['LIMIT'], $where_for_totals['ORDER']);

            $all_filtered_data = $app->select("invoices_products", $joins, [
                'invoices_products.price',
                'invoices_products.type',
                'invoices_products.amount',
                'invoices_products.discount_priceinvoices'
            ], $where_for_totals);

            foreach ($all_filtered_data as $loc) {
                $thanh_tien = ($loc['price'] - $loc['discount_priceinvoices']) * $loc['amount'];
                if ($loc['type'] == 3) {
                    $total_tra_grand += $thanh_tien;
                    $total_quantity_filtered -= $loc['amount'];
                } else {
                    $total_mua_grand += $thanh_tien;
                    $total_quantity_filtered += $loc['amount'];
                }
            }

            // 4. Lấy dữ liệu cho trang hiện tại
            $count = $app->count("invoices_products", $joins, "invoices_products.id", $where);

            $where["ORDER"] = [$orderName => strtoupper($orderDir)];
            $where["LIMIT"] = [$start, $length];

            $datas = [];
            $total_mua_page = 0;
            $total_tra_page = 0;
            $total_quantity_page = 0;

            $columns = [
                'invoices_products.id',
                'invoices_products.invoices',
                'invoices_products.products',
                'invoices_products.price',
                'invoices_products.price_old',
                'invoices_products.discount_price',
                'invoices_products.discount_priceinvoices',
                'invoices_products.discount_invoices',
                'invoices_products.discount',
                'invoices_products.type',
                'invoices_products.amount',
                'invoices_products.minus',
                'invoices_products.date_poster',
                'invoices_products.additional_information',
                'invoices.code(invoice_code)',
                'invoices.code_group',
                'invoices.status(invoice_status)',
                'products.name(product_name)',
                'products.code(product_code)',
                'units.name(unit_name)',
                'stores.name(store_name)',
                'branch.name(branch_name)'
            ];

            $app->select("invoices_products", $joins, $columns, $where, function ($data) use (&$datas, &$total_mua_page, &$total_tra_page, &$total_quantity_page, $jatbi, $app, $setting) {

                $thanh_tien = ($data['price'] - $data['discount_priceinvoices']) * $data['amount'];
                if ($data['type'] == 3) {
                    $total_tra_page += $thanh_tien;
                    $total_quantity_page -= $data['amount'];
                } else {
                    $total_mua_page += $thanh_tien;
                    $total_quantity_page += $data['amount'];
                }

                $personnel_html = '';
                $personnel_data = $app->select("invoices_personnels", ["[>]personnels" => ["personnels" => "id"]], "personnels.name", ["invoices" => $data["invoices"], "invoices_personnels.deleted" => 0]);
                foreach ($personnel_data as $per_name) {
                    $personnel_html .= ($per_name ?? 'N/A') . '<br>';
                }
                $giamgiasp = $data['price_old'] - $data['price'];
                $price_display = (($data['discount_price'] != 0) ? $data['discount_price'] : ((($data['discount'] == 0) && $giamgiasp != 0) ? $giamgiasp : ''));
                $is_promo = ($giamgiasp != 0 || $data['price'] == 0 || $data['discount'] > 0 || $data['minus'] > 0 || $data['discount_invoices'] > 0 || $data['discount_priceinvoices'] > 0);

                $datas[] = [
                    // "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "ma_hoa_don" => '<a class="text-nowrap pjax-load" href="/invoices/invoices-views/' . $data['invoices'] . '">#' . $setting['ballot_code']['invoices'] . '-' . $data['invoice_code'] . '' . $data['invoices'] . '</a>',
                    "ma_doan" => (string) ($data['code_group'] ?? ''),
                    "ma_hang" => (string) ($data['product_code'] ?? ''),
                    "ten_hang" => (string) ($data['product_name'] ?? ''),
                    "thong_tin_bo_sung" => (string) ($data['additional_information'] ?? ''),
                    "don_gia" => number_format($data['price_old'] ?? 0),
                    "so_luong" => (string) ($data['type'] == 3 ? '-' . $data['amount'] : $data['amount']),
                    "hang_khuyen_mai" => '<input type="checkbox" ' . ($is_promo ? 'checked' : '') . ' disabled>',
                    "dvt" => (string) ($data['unit_name'] ?? ''),
                    "chiet_khau_sp_percent" => ($data['discount'] == 0 ? '' : number_format($data['discount'])),
                    "tien_chiet_khau_sp" => ($price_display == '' ? '' : number_format($price_display)),
                    "chiet_khau_dh_percent" => ($data['discount_invoices'] == 0 ? '' : number_format($data['discount_invoices'])),
                    "tien_chiet_khau_dh" => ($data['discount_priceinvoices'] == 0 ? '' : number_format($data['discount_priceinvoices'])),
                    "giam_tru" => ($data['minus'] == 0 ? '' : number_format($data['minus'])),
                    "thanh_tien" => ($data['type'] == 3 ? '-' : '') . number_format($thanh_tien),
                    "trang_thai" => '<span class="fw-bold text-' . ($setting['Status_invoices'][$data['invoice_status']]['color'] ?? 'secondary') . '">' . ($setting['Status_invoices'][$data['invoice_status']]['name'] ?? 'N/A') . '</span>',
                    "nhan_vien_ban_hang" => $personnel_html,
                    "ngay" => $jatbi->datetime($data['date_poster'] ?? ''),
                    "cua_hang" => (string) ($data['store_name'] ?? ''),
                    "quay_hang" => (string) ($data['branch_name'] ?? ''),
                    // "action" => $app->component("action", [
                    //     "button" => [
                    //         ['type' => 'button', 'name' => $jatbi->lang("Sửa"), 'permission' => ['license_sale.edit'], 'action' => ['data-url' => '/invoices/license_sale/edit/' . $data['id'], 'data-action' => 'modal']],
                    //         ['type' => 'button', 'name' => $jatbi->lang("Xóa"), 'permission' => ['license_sale.delete'], 'action' => ['data-url' => '/invoices/license_sale/delete?box=' . $data['id'], 'data-action' => 'modal']]
                    //     ]
                    // ]),
                ];
            });

            // 5. Trả về dữ liệu JSON cho DataTables
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas,
                "footerData" => [
                    "page_total" => number_format($total_mua_page - $total_tra_page),
                    "grand_total" => number_format($total_mua_grand - $total_tra_grand),
                    "total_quantity_page" => $total_quantity_page,
                    "total_quantity_filtered" => $total_quantity_filtered,
                ]
            ]);
        }
    })->setPermissions(['license_sale']);
    ob_start();

    $app->router('/license_sale/license_sale_excel', 'GET', function ($vars) use ($app, $jatbi, $setting) {
        try {
            // --- BƯỚC 1: LẤY VÀ XỬ LÝ CÁC BỘ LỌC TỪ URL ---
            $searchValue = $_GET['search']['value'] ?? ($_GET['name'] ?? '');
            $filter_name = $_GET['name'] ?? '';
            $filter_code_group = $_GET['code_group'] ?? '';
            $filter_price = $_GET['price'] ?? '';
            $filter_customers = $_GET['customers'] ?? '';
            $filter_date = $_GET['date'] ?? '';
            $store = $_GET['stores'] ?? '';
            $filter_branch = $_GET['branch'] ?? '';
            $filter_user = $_GET['user'] ?? '';
            $filter_personnels = $_GET['personnels'] ?? '';
            $filter_status = $_GET['status'] ?? '';
            
            // Khởi tạo điều kiện WHERE
            $where = [
                "AND" => [
                    "invoices_products.deleted" => 0,
                    "invoices_products.cancel" => [0, 1],
                ]
            ];

            // Xử lý bộ lọc ngày
            if (!empty($filter_date)) {
                $date_parts = explode(' - ', $filter_date);
                if (count($date_parts) == 2) {
                    $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date_parts[0]))));
                    $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date_parts[1]))));
                    $where['AND']['invoices_products.date_poster[<>]'] = [$date_from, $date_to];
                }
            } else {
                $date_from = date('Y-m-d 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
                $where['AND']['invoices_products.date_poster[<>]'] = [$date_from, $date_to];
            }

            // Áp dụng các bộ lọc
            if (!empty($searchValue)) {
                $where['AND']['OR'] = [
                    "products.name[~]" => $searchValue,
                    "products.code[~]" => $searchValue,
                    "invoices.code_group[~]" => $searchValue,
                    "invoices_products.price_old[~]" => str_replace(',', '', $searchValue),
                    "invoices.code[~]" => $searchValue
                ];
            }
            if (!empty($filter_name)) {
                $where['AND']['OR'] = ["products.name[~]" => $filter_name, "products.code[~]" => $filter_name];
            }
            if (!empty($filter_code_group)) {
                $where['AND']['invoices.code_group[~]'] = $app->xss($filter_code_group);
            }
            if ($filter_price !== '') {
                $where['AND']['invoices_products.price_old'] = str_replace(',', '', $filter_price);
            }
            if ($filter_customers !== '') {
                $where['AND']['invoices.customers'] = $app->xss($filter_customers);
            }
            if ($filter_branch !== '') {
                $where['AND']['invoices_products.branch'] = $app->xss($filter_branch);
            }
            if ($store && $store != [0]) {
                $where['AND']['invoices_products.stores'] = $app->xss($store);
            }
            if (!empty($filter_user)) {
                $where['AND']['invoices.user'] = $app->xss($filter_user);
            }
            if (!empty($filter_personnels)) {
                $invoice_ids = $app->select("invoices_personnels", "invoices", ["personnels" => $app->xss($filter_personnels)]);
                $where['AND']['invoices_products.invoices'] = $invoice_ids ?: [0];
            }
            if (!empty($filter_status)) {
                $where['AND']['invoices.status'] = $app->xss($filter_status);
            }

            // --- BƯỚC 2: TRUY VẤN DỮ LIỆU GỐC ---
            $joins = [
                "[>]invoices" => ["invoices" => "id"],
                "[>]products" => ["products" => "id"],
                "[>]units" => ["products.units" => "id"],
                "[>]stores" => ["invoices_products.stores" => "id"],
                "[>]branch" => ["invoices_products.branch" => "id"]
            ];
            $columns = [
                'invoices_products.id',
                'invoices_products.invoices',
                'invoices_products.products',
                'invoices_products.price',
                'invoices_products.price_old',
                'invoices_products.discount_price',
                'invoices_products.discount_priceinvoices',
                'invoices_products.discount_invoices',
                'invoices_products.discount',
                'invoices_products.type',
                'invoices_products.amount',
                'invoices_products.minus',
                'invoices_products.date_poster',
                'invoices_products.additional_information',
                'invoices.code(invoice_code)',
                'invoices.code_group',
                'invoices.status(invoice_status)',
                'products.name(product_name)',
                'products.code(product_code)',
                'units.name(unit_name)',
                'stores.name(store_name)',
                'branch.name(branch_name)',
            ];

            $loctheodieukien = $app->select("invoices_products", $joins, $columns, $where);

            if (empty($loctheodieukien)) {
                ob_end_clean();
                exit("Không có dữ liệu để xuất file.");
            }

            // --- BƯỚC 3: TỐI ƯU HÓA - LẤY DỮ LIỆU LIÊN QUAN ---
            $invoice_ids = array_unique(array_column($loctheodieukien, 'invoices'));
            $personnels_data = $app->select("invoices_personnels", ["[>]personnels" => ["personnels" => "id"]], ["invoices", "personnels.name"], ["invoices" => $invoice_ids, "invoices_personnels.deleted" => 0]);
            $personnel_map = [];
            foreach ($personnels_data as $per) {
                $personnel_map[$per['invoices']][] = $per['name'] ?: 'N/A';
            }

            // --- BƯỚC 4: TÍNH TỔNG ---
            $total_mua_grand = 0;
            $total_tra_grand = 0;
            $total_quantity_filtered = 0;

            foreach ($loctheodieukien as $loc) {
                $thanh_tien = ($loc['price'] - ($loc['discount_priceinvoices'] ?? 0)) * $loc['amount'];
                if ($loc['type'] == 3) {
                    $total_tra_grand += $thanh_tien;
                    $total_quantity_filtered -= $loc['amount'];
                } else {
                    $total_mua_grand += $thanh_tien;
                    $total_quantity_filtered += $loc['amount'];
                }
            }

            // --- BƯỚC 5: TẠO FILE EXCEL ---
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('ChungTuBanHang');

            // Định nghĩa tiêu đề cột (khớp với DataTables)
            $headers = [
                'MÃ HÓA ĐƠN',
                'MÃ ĐOÀN',
                'MÃ HÀNG',
                'TÊN HÀNG',
                'THÔNG TIN BỔ SUNG',
                'ĐƠN GIÁ',
                'SỐ LƯỢNG',
                'HÀNG KHUYẾN MẠI',
                'ĐVT',
                'CK SP (%)',
                'TIỀN CK SP',
                'CK ĐH (%)',
                'TIỀN CK ĐH',
                'GIẢM TRỪ',
                'THÀNH TIỀN',
                'TRẠNG THÁI',
                'NHÂN VIÊN BÁN HÀNG',
                'NGÀY',
                'CỬA HÀNG',
                'QUẦY HÀNG'
            ];
            $sheet->fromArray($headers, null, 'A1');

            // Định dạng tiêu đề
            $sheet->getStyle('A1:T1')->applyFromArray([
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'color' => ['argb' => 'D3D3D3']],
            ]);

            // --- BƯỚC 6: ĐIỀN DỮ LIỆU VÀO EXCEL ---
            $row_index = 2;
            $sum_sl = 0;
            $sum_thanhtien = 0;

            foreach ($loctheodieukien as $data) {
                $personnel_names = implode(', ', $personnel_map[$data['invoices']] ?? []);

                $thanh_tien = ($data['price'] - ($data['discount_priceinvoices'] ?? 0)) * $data['amount'];
                $so_luong = $data['type'] == 3 ? -$data['amount'] : $data['amount'];
                $thanh_tien_final = $data['type'] == 3 ? -$thanh_tien : $thanh_tien;

                $sum_sl += $so_luong;
                $sum_thanhtien += $thanh_tien_final;

                $giamgiasp = ($data['price_old'] ?? 0) - ($data['price'] ?? 0);
                $price_display = (($data['discount_price'] ?? 0) != 0) ? ($data['discount_price'] ?? 0) : ((($data['discount'] ?? 0) == 0 && $giamgiasp != 0) ? $giamgiasp : '');
                $is_promo = ($giamgiasp != 0 || ($data['price'] ?? 0) == 0 || ($data['discount'] ?? 0) > 0 || ($data['minus'] ?? 0) > 0 || ($data['discount_invoices'] ?? 0) > 0 || ($data['discount_priceinvoices'] ?? 0) > 0);

                // Điền dữ liệu vào các cột
                $sheet->setCellValue('A' . $row_index, '#' . ($setting['ballot_code']['invoices'] ?? 'HĐ') . ($data['invoice_code'] ?? '') . $data['invoices']);
                $sheet->setCellValue('B' . $row_index, $data['code_group'] ?? '');
                $sheet->setCellValue('C' . $row_index, $data['product_code'] ?? '');
                $sheet->setCellValue('D' . $row_index, $data['product_name'] ?? '');
                $sheet->setCellValue('E' . $row_index, $data['additional_information'] ?? '');
                $sheet->setCellValue('F' . $row_index, $data['price_old'] ?? 0);
                $sheet->setCellValue('G' . $row_index, $so_luong);
                $sheet->setCellValue('H' . $row_index, $is_promo ? 'Có' : '');
                $sheet->setCellValue('I' . $row_index, $data['unit_name'] ?? '');
                $sheet->setCellValue('J' . $row_index, ($data['discount'] ?? 0) == 0 ? '' : ($data['discount'] ?? 0));
                $sheet->setCellValue('K' . $row_index, $price_display == '' ? '' : $price_display);
                $sheet->setCellValue('L' . $row_index, ($data['discount_invoices'] ?? 0) == 0 ? '' : ($data['discount_invoices'] ?? 0));
                $sheet->setCellValue('M' . $row_index, ($data['discount_priceinvoices'] ?? 0) == 0 ? '' : ($data['discount_priceinvoices'] ?? 0));
                $sheet->setCellValue('N' . $row_index, ($data['minus'] ?? 0) == 0 ? '' : ($data['minus'] ?? 0));
                $sheet->setCellValue('O' . $row_index, $thanh_tien_final);
                $sheet->setCellValue('P' . $row_index, ($setting['Status_invoices'][$data['invoice_status']]['name'] ?? 'N/A'));
                $sheet->setCellValue('Q' . $row_index, $personnel_names);
                $sheet->setCellValue('R' . $row_index, $jatbi->datetime($data['date_poster'] ?? ''));
                $sheet->setCellValue('S' . $row_index, $data['store_name'] ?? '');
                $sheet->setCellValue('T' . $row_index, $data['branch_name'] ?? '');

                $row_index++;
            }

            // --- BƯỚC 7: THÊM DÒNG TỔNG ---
            $sheet->setCellValue('F' . $row_index, 'Tổng');
            $sheet->setCellValue('G' . $row_index, $sum_sl);
            $sheet->setCellValue('O' . $row_index, $sum_thanhtien);
            $sheet->getStyle('F' . $row_index . ':O' . $row_index)->applyFromArray([
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ]);

            // --- BƯỚC 8: ĐỊNH DẠNG CỘT ---
            foreach (range('A', 'T') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Định dạng số và tiền tệ
            $sheet->getStyle('F2:F' . $row_index)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('J2:J' . $row_index)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('K2:K' . $row_index)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('L2:L' . $row_index)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('M2:M' . $row_index)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('N2:N' . $row_index)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('O2:O' . $row_index)->getNumberFormat()->setFormatCode('#,##0');

            // Định dạng cột NGÀY
            $sheet->getStyle('R2:R' . $row_index)->getNumberFormat()->setFormatCode('dd/mm/yyyy hh:mm:ss');

            // Căn chỉnh cột
            $sheet->getStyle('A2:A' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT); // MÃ HÓA ĐƠN
            $sheet->getStyle('B2:B' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT); // MÃ ĐOÀN
            $sheet->getStyle('C2:C' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT); // MÃ HÀNG
            $sheet->getStyle('D2:D' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT); // TÊN HÀNG
            $sheet->getStyle('E2:E' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT); // THÔNG TIN BỔ SUNG
            $sheet->getStyle('F2:F' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); // ĐƠN GIÁ
            $sheet->getStyle('G2:G' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // SỐ LƯỢNG
            $sheet->getStyle('H2:H' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // HÀNG KHUYẾN MẠI
            $sheet->getStyle('I2:I' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // ĐVT
            $sheet->getStyle('J2:J' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); // CK SP (%)
            $sheet->getStyle('K2:K' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); // TIỀN CK SP
            $sheet->getStyle('L2:L' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); // CK ĐH (%)
            $sheet->getStyle('M2:M' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); // TIỀN CK ĐH
            $sheet->getStyle('N2:N' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); // GIẢM TRỪ
            $sheet->getStyle('O2:O' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); // THÀNH TIỀN
            $sheet->getStyle('P2:P' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // TRẠNG THÁI
            $sheet->getStyle('Q2:Q' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT); // NHÂN VIÊN BÁN HÀNG
            $sheet->getStyle('R2:R' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // NGÀY
            $sheet->getStyle('S2:S' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT); // CỬA HÀNG
            $sheet->getStyle('T2:T' . $row_index)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT); // QUẦY HÀNG

            // --- BƯỚC 9: XUẤT FILE ---
            ob_end_clean();
            $file_name = 'chungtubanhang_' . date('d-m-Y') . '.xlsx';
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
    })->setPermissions(['license_sale']);


    // tra hang
    $app->router('/returns', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores, $accStore,$template) {
        $vars['title'] = $jatbi->lang("Trả hàng");
        if ($app->method() === 'GET') {
            $vars['accounts'] = array_merge([["value" => "", "text" => $jatbi->lang("Tất cả")]], $app->select("accounts", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A']));
            $vars['stores'] = $stores;
            echo $app->render($template . '/invoices/returns.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            // --- 1. Đọc tham số ---
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';
            $orderName = isset($_POST['order'][0]['column']) ? $_POST['columns'][$_POST['order'][0]['column']]['name'] : 'invoices.id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

            $filter_customers = isset($_POST['customers']) ? trim($_POST['customers']) : '';
            $filter_date = isset($_POST['date']) ? trim($_POST['date']) : '';
            $filter_stores = isset($_POST['stores']) ? trim($_POST['stores']) : '';
            $filter_branch = isset($_POST['branch']) ? trim($_POST['branch']) : '';
            $filter_user = isset($_POST['user']) ? trim($_POST['user']) : '';
            $filter_status = isset($_POST['status']) ? trim($_POST['status']) : '';

            // --- 2. Xây dựng truy vấn với JOIN ---
            $joins = [
                "[>]customers" => ["customers" => "id"],
                "[>]accounts" => ["user" => "id"],
                "[>]stores" => ["stores" => "id"],
                "[>]branch" => ["branch" => "id"]
            ];

            $where = [
                "AND" => [
                    "invoices.deleted" => 0,
                    "invoices.type" => 3, // Lọc hóa đơn trả hàng
                    "invoices.cancel" => [0, 1],
                ],
            ];

            // Áp dụng bộ lọc
            if (!empty($searchValue)) {
                $where['AND']['OR'] = [
                    "invoices.code[~]" => $searchValue,
                    "customers.name[~]" => $searchValue,
                ];
            }
            if (!empty($filter_customers)) {
                $where['AND']['invoices.customers'] = $filter_customers;
            }
            if (!empty($filter_branch)) {
                $where['AND']['invoices.branch'] = $filter_branch;
            }
            if (!empty($filter_status)) {
                $where['AND']['invoices.status'] = $filter_status;
            }
            if (!empty($filter_user)) {
                $where['AND']['invoices.user'] = $filter_user;
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
            $where['AND']['invoices.date_poster[<>]'] = [$date_from, $date_to];

            // --- 3. Đếm và tính tổng ---
            $count = $app->count("invoices", $joins, "invoices.id", $where);

            // Tính tổng cho footer
            $summary = [
                'sum_sl' => 0,
                'sum_tt' => 0,
                'sum_minus' => 0,
                'sum_surcharge' => 0,
                'sum_transport' => 0,
                'sum_payments' => 0,
                'sum_prepay' => 0
            ];

            // Thêm sắp xếp và phân trang
            $where["ORDER"] = [$orderName => strtoupper($orderDir)];
            $where["LIMIT"] = [$start, $length];

            // --- 4. Lấy dữ liệu chính ---
            $datas = [];



            $columns = [
                "invoices.id",
                "invoices.code",
                "invoices.total",
                "invoices.minus",
                "invoices.discount",
                "invoices.surcharge",
                "invoices.transport",
                "invoices.payments",
                "invoices.prepay",
                "invoices.status",
                "invoices.date_poster",
                "invoices.cancel",
                "invoices.cancel_content",
                "customers.name(customer_name)",
                "accounts.name(user_name)",
                "stores.name(store_name)",
                "branch.name(branch_name)"
            ];
            $summary = [
                'sum_sl' => 0,
                'sum_tt' => 0,
                'sum_minus' => 0,
                'sum_surcharge' => 0,
                'sum_transport' => 0,
                'sum_payments' => 0,
                'sum_prepay' => 0
            ];

            $app->select("invoices", $joins, $columns, $where, function ($data) use (&$datas, $jatbi, $app, &$summary, $setting) {
                $products_info = $app->select(
                    "invoices_products",
                    ["[>]products" => ["products" => "id"]],
                    ["products.code", "products.name", "invoices_products.amount"],
                    ["invoices" => $data['id']]
                );
                $products_html = '';
                $total_amount = 0;
                foreach ($products_info as $p) {
                    $products_html .= '<span style="font-size: 0.9em;"><strong>- ' . ($p['code'] ?? '') . ' - ' . ($p['name'] ?? '') . '</strong></span><br>';
                    $total_amount += (float) ($p['amount'] ?? 0);
                }

                $personnel_id = $app->get("invoices_personnels", "personnels", ["invoices" => $data['id']]);
                $personnel_name = $personnel_id ? $app->get("personnels", "name", ["id" => $personnel_id]) : '';

                $summary['sum_sl'] += $total_amount;
                $summary['sum_tt'] += $data['total'];
                $summary['sum_minus'] += $data['minus'];
                $summary['sum_surcharge'] += $data['surcharge'];
                $summary['sum_transport'] += $data['transport'];
                $summary['sum_payments'] += $data['payments'];
                $summary['sum_prepay'] += $data['prepay'];

                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "code" => '<a class="text-nowrap pjax-load" href="/invoices/invoices-views/' . $data['id'] . '">#' . ($setting['ballot_code']['invoices'] ?? '') . '-' . $data['code'] . $data['id'] . '</a>',
                    "products" => $products_html,
                    "amount" => $total_amount,
                    "customer" => $data['customer_name'],
                    "total" => number_format($data['total'] ?? 0),
                    "prepay" => number_format($data['prepay'] ?? 0),
                    "minus" => number_format($data['minus'] ?? 0),
                    "discount" => number_format($data['discount'] ?? 0),
                    "surcharge" => number_format($data['surcharge'] ?? 0),
                    "transport" => number_format($data['transport'] ?? 0),
                    "payments" => number_format($data['payments'] ?? 0),
                    "remaining" => '<a href="#!" class="modal-url text-danger fw-bold" data-url="/invoices/invoices-prepay/' . $data['id'] . '/">' . number_format(($data['payments'] ?? 0) - ($data['prepay'] ?? 0)) . '</a>',
                    "status" => (((float) ($data['prepay'] ?? 0)) >= ((float) ($data['payments'] ?? 0)))
                        ? '<span class="fw-bold text-success">Đã thanh toán</span>'
                        : '<span class="fw-bold text-danger">Công nợ</span>',
                    "date_poster" => date('d/m/Y H:i:s', strtotime($data['date_poster'])),
                    "personnel" => $personnel_name,
                    "user" => $data['user_name'],
                    "stores" => $data['store_name'],
                    "branch" => $data['branch_name'],
                    "cancel_status" => ($data['cancel'] == 1) ? '<span data-bs-toggle="tooltip" title="' . $jatbi->lang('Yêu cầu hủy') . ': ' . $data['cancel_content'] . '" class="bg-danger d-block rounded-circle animate__animated animate__infinite infinite animate__heartBeat" style="width:10px;height: 10px;"></span>' : '',
                    "action" => $app->component("action", [
                        "button" => array_filter([
                            [
                                'type' => 'link',
                                'name' => $jatbi->lang("Xem"),
                                'action' => ['href' => '/invoices/invoices-views/' . $data['id'] . '', 'data-pjax' => '']
                            ],
                            ($jatbi->permission('invoices_codegroup.edit', 'button')) ? [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa mã đoàn"),
                                'action' => ['data-url' => '/invoices/invoices-code-group/' . $data['id'] . '', 'data-action' => 'modal']
                            ] : null,
                            ($jatbi->permission('invoices_customers.edit', 'button')) ? [
                                'type' => 'link',
                                'name' => $jatbi->lang("Sửa khách hàng"),
                                'action' => ['href' => '/invoices/invoices-update-customers/' . $data['id'] . '', 'data-pjax' => '']
                            ] : null,
                            ($jatbi->permission('invoices_personnels.edit', 'button')) ? [
                                'type' => 'link',
                                'name' => $jatbi->lang("Sửa nhân viên bán hàng"),
                                'action' => ['href' => '/invoices/invoices-update-personnels/' . $data['id'] . '', 'data-pjax' => '']
                            ] : null,
                            (($data['status'] ?? 0) == 2) ? [
                                'type' => 'button',
                                'name' => $jatbi->lang("Thanh toán"),
                                'action' => ['data-url' => '/invoices/invoices-prepay/' . $data['id'] . '', 'data-action' => 'modal']
                            ] : null,
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Ghi chú"),
                                'action' => ['data-url' => '/invoices/invoices-notes/' . $data['id'] . '', 'data-action' => 'modal']
                            ]
                        ])
                    ])
                ];
            });
            // --- 5. Trả về JSON ---
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas,
                "footerData" => [
                    "sum_sl" => number_format($summary['sum_sl']),
                    "sum_tt" => number_format($summary['sum_tt']),
                    "sum_minus" => number_format($summary['sum_minus']),
                    "sum_surcharge" => number_format($summary['sum_surcharge']),
                    "sum_transport" => number_format($summary['sum_transport']),
                    "sum_payments" => number_format($summary['sum_payments']),
                    "sum_prepay" => number_format($summary['sum_prepay']),
                    "sum_remaining" => number_format($summary['sum_payments'] - $summary['sum_prepay'])
                ]
            ]);
        }
    })->setPermissions(['returns']);

    // hoa hong nhan vien 
    $app->router('/invoices-commission', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores,$template) {
        if ($app->method() === 'GET') {
            $vars['stores'] = array_merge([["value" => "", "text" => $jatbi->lang("Tất cả")]], $app->select("stores", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A']));
            $vars['accounts'] = array_merge([["value" => "", "text" => $jatbi->lang("Tất cả")]], $app->select("accounts", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A']));
            $vars['personnels'] = array_merge([["value" => "", "text" => $jatbi->lang("Tất cả")]], $app->select("personnels", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A']));
            $vars['title'] = $jatbi->lang("Hoa hồng nhân viên");


            echo $app->render($template . '/invoices/invoices-commission.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            // --- 1. Đọc tham số ---
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
            $searchValue = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';
            $orderName = isset($_POST['order'][0]['column']) ? $_POST['columns'][$_POST['order'][0]['column']]['name'] : 'invoices_personnels.id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

            $filter_personnels = isset($_POST['personnels']) ? trim($_POST['personnels']) : '';
            $filter_date = isset($_POST['date']) ? trim($_POST['date']) : '';
            $filter_stores = isset($_POST['stores']) ? trim($_POST['stores']) : '';
            $filter_user = isset($_POST['user']) ? trim($_POST['user']) : '';
            // $status = isset($_POST['status']) ? [$_POST['status'], $_POST['status']] : '';

            // --- 2. Xây dựng truy vấn với JOIN ---
            $joins = [
                "[>]invoices" => ["invoices" => "id"],
                "[>]personnels" => ["personnels" => "id"],
                "[>]stores" => ["stores" => "id"],
                "[>]accounts" => ["user" => "id"]
            ];

            $where = [
                "AND" => [
                    "invoices_personnels.deleted" => 0,
                    "invoices_personnels.cancel" => [0, 1],
                    // "invoices.cancel" => [0, 1],
                ],
            ];

            // Áp dụng bộ lọc
            if (!empty($searchValue)) {
                $where['AND']['OR'] = [
                    "invoices.code[~]" => $searchValue,
                    "personnels.name[~]" => $searchValue,
                ];
            }
            if (!empty($filter_personnels)) {
                $where['AND']['invoices_personnels.personnels'] = $filter_personnels;
            }
            if (!empty($filter_stores)) {
                $where['AND']['invoices_personnels.stores'] = $filter_stores;
            }
            if (!empty($filter_user)) {
                $where['AND']['invoices_personnels.user'] = $filter_user;
            }
            // if (!empty($filter_user)) {
            //     $where['AND']['invoices_personnels.user'] = $status;
            // }

            // Cập nhật logic lọc theo ngày
            if (!empty($filter_date)) {
                $date_parts = explode(' - ', $filter_date);
                if (count($date_parts) == 2) {
                    $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date_parts[0]))));
                    $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date_parts[1]))));
                }
            } else {
                // Mặc định lọc theo ngày hiện tại nếu không có ngày nào được chọn
                $date_from = date('Y-m-d 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }
            $where['AND']['invoices_personnels.date[<>]'] = [$date_from, $date_to];

            // --- 3. Đếm và tính tổng ---
            $count = $app->count("invoices_personnels", $joins, "invoices_personnels.id", $where);

            $total_mua_grand = 0;
            $total_tra_grand = 0;
            $all_filtered_data = $app->select("invoices_personnels", ["price", "invoices_type"], $where);
            foreach ($all_filtered_data as $item) {
                if ($item['invoices_type'] == 3) {
                    $total_tra_grand += $item['price'];
                } else {
                    $total_mua_grand += $item['price'];
                }
            }

            // Thêm sắp xếp và phân trang
            $where["ORDER"] = [$orderName => strtoupper($orderDir)];
            $where["LIMIT"] = [$start, $length];

            // --- 4. Lấy dữ liệu chính ---
            $datas = [];
            $total_mua_page = 0;
            $total_tra_page = 0;

            $columns = [
                'invoices_personnels.id',
                'invoices_personnels.invoices',
                'invoices_personnels.invoices_type',
                'invoices_personnels.commission',
                'invoices_personnels.price',
                'invoices_personnels.date',
                'invoices.code(invoice_code)',
                'invoices.payments(invoice_payments)',
                'personnels.name(personnel_name)',
                'stores.name(store_name)',
                'accounts.name(user_name)'
            ];

            $app->select("invoices_personnels", $joins, $columns, $where, function ($data) use (&$datas, &$total_mua_page, &$total_tra_page, $jatbi, $app, $setting) {
                if ($data['invoices_type'] == 3) {
                    $total_tra_page += $data['price'];
                } else {
                    $total_mua_page += $data['price'];
                }
                $invoice = $app->get("invoices", ["id", "code", "payments"], ["id" => $data['invoices'], "deleted" => 0, "cancel" => [0, 1]]);


                $datas[] = [
                    "invoice_code" => '<a class="text-nowrap pjax-load" href="/invoices/invoices-views/' . $data['invoices'] . '/">#' . $setting['ballot_code']['invoices'] . '-' . ($invoice['code'] ?? '') . ($invoice['id'] ?? '') . '</a>',
                    "personnel_name" => $data['personnel_name'],
                    "invoice_payments" => '<span class="text-nowrap pjax-load text-info">' . number_format($data['invoices_type'] == 3 ? -$data['invoice_payments'] : $data['invoice_payments']) . '</span>',
                    "commission_percent" => '<span class="text-danger">' . (float) $data['commission'] . '</span>',
                    "commission_value" => '<span class="text-success">' . ($data['invoices_type'] == 3 ? '-' . number_format($data['price']) : number_format($data['price'])) . '</span>',
                    "date" => date('d/m/Y H:i:s', strtotime($data['date'])),
                    "user" => $data['user_name'],
                    "store_name" => $data['store_name'],
                    "text_class" => $data['invoices_type'] == 3 ? 'text-danger' : 'text-success',

                ];
            });

            // --- 5. Trả về JSON ---
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas,
                "footerData" => [
                    "page_total" => number_format($total_mua_page - $total_tra_page),
                    "grand_total" => number_format($total_mua_grand - $total_tra_grand)
                ]
            ]);
        }
    })->setPermissions(['invoices-commission']);

    ob_start();
    $app->router('/invoices-commission/invoices-commission-excel', 'GET', function ($vars) use ($app, $jatbi, $setting) {
        try {
            // --- BƯỚC 1: LẤY VÀ XỬ LÝ CÁC BỘ LỌC TỪ URL ---
            $searchValue = $_GET['search']['value'] ?? ($_GET['name'] ?? '');
            $filter_personnels = $_GET['personnels'] ?? '';
            $filter_date = $_GET['date'] ?? '';
            $filter_stores = $_GET['stores'] ?? '';
            $filter_user = $_GET['user'] ?? '';


            // --- BƯỚC 2: XÂY DỰNG TRUY VẤN VỚI JOIN ---
            $joins = [
                "[<]invoices" => ["invoices" => "id"],
                "[<]personnels" => ["personnels" => "id"],
                "[<]stores" => ["invoices_personnels.stores" => "id"],
                "[<]accounts" => ["invoices_personnels.user" => "id"]
            ];

            $where = [
                "AND" => [
                    "invoices_personnels.deleted" => 0,
                    "invoices_personnels.cancel" => [0, 1],
                ]
            ];

            // Áp dụng bộ lọc
            if (!empty($searchValue)) {
                $where['AND']['OR'] = [
                    "invoices.code[~]" => $searchValue,
                    "personnels.name[~]" => $searchValue,
                ];
            }
            if (!empty($filter_personnels))
                $where['AND']['invoices_personnels.personnels'] = $filter_personnels;
            if (!empty($filter_stores))
                $where['AND']['invoices_personnels.stores'] = $filter_stores;
            if (!empty($filter_user))
                $where['AND']['invoices_personnels.user'] = $filter_user;

            if (!empty($filter_date)) {
                $date_parts = explode(' - ', $filter_date);
                if (count($date_parts) == 2) {
                    $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date_parts[0]))));
                    $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date_parts[1]))));
                    $where['AND']['invoices_personnels.date[<>]'] = [$date_from, $date_to];
                }
            }

            $where["ORDER"] = ['invoices_personnels.id' => 'DESC'];

            // --- BƯỚC 3: LẤY DỮ LIỆU ---
            $columns = [
                'invoices_personnels.invoices',
                'invoices_personnels.invoices_type',
                'invoices_personnels.commission',
                'invoices_personnels.price',
                'invoices_personnels.date',
                'invoices.code(invoice_code)',
                'invoices.payments(invoice_payments)',
                'personnels.name(personnel_name)',
                'stores.name(store_name)',
                'accounts.name(user_name)'
            ];
            $datas = $app->select("invoices_personnels", $joins, $columns, $where);

            if ($datas === false) {
                throw new Exception("Lỗi truy vấn database.");
            }

            // --- BƯỚC 4: TẠO FILE EXCEL ---
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('HoaHongNhanVien');

            $headers = ['MÃ HÓA ĐƠN', 'NHÂN VIÊN', 'TỔNG TIỀN', 'PHẦN TRĂM (%)', 'THÀNH TIỀN (HH)', 'NGÀY', 'TÀI KHOẢN', 'CỬA HÀNG'];
            $sheet->fromArray($headers, null, 'A1');

            $row_index = 2;
            $total_mua = 0;
            $total_tra = 0;

            foreach ($datas as $data) {
                $price = $data['price'];
                if ($data['invoices_type'] == 3) { // Hàng trả
                    $price = -$price;
                    $total_tra += $data['price'];
                } else {
                    $total_mua += $data['price'];
                }

                $sheet->setCellValue('A' . $row_index, '#' . ($setting['ballot_code']['invoices'] ?? 'HĐ') . $data['invoices']);
                $sheet->setCellValue('B' . $row_index, $data['personnel_name']);
                $sheet->setCellValue('C' . $row_index, $data['invoice_payments'] * ($data['invoices_type'] == 3 ? -1 : 1));
                $sheet->setCellValue('D' . $row_index, (float) $data['commission'] ?? 0);
                $sheet->setCellValue('E' . $row_index, $price);
                $sheet->setCellValue('F' . $row_index, $jatbi->datetime($data['date'], $setting['site_datetime']));
                $sheet->setCellValue('G' . $row_index, $data['user_name']);
                $sheet->setCellValue('H' . $row_index, $data['store_name']);

                $row_index++;
            }

            // Ghi dòng tổng
            $total_net = $total_mua - $total_tra;
            $sheet->setCellValue('D' . ($row_index + 1), 'Tổng cộng');
            $sheet->setCellValue('E' . ($row_index + 1), $total_net);

            // Định dạng cột số
            $sheet->getStyle('C2:E' . ($row_index + 1))->getNumberFormat()->setFormatCode('#,##0');
            foreach (range('A', 'H') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            // --- BƯỚC 5: XUẤT FILE ---
            ob_end_clean();
            $file_name = 'hoahongnhanvien_' . date('d-m-Y') . '.xls';
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="' . $file_name . '"');
            header('Cache-Control: max-age=0');
            $writer = new Xls($spreadsheet);
            $writer->save('php://output');
            exit;
        } catch (Exception $e) {
            ob_end_clean();
            http_response_code(500);
            exit("Lỗi: " . $e->getMessage());
        }
    })->setPermissions(['invoices-commission']);

    $app->router('/invoices-cancel', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores, $accStore,$template) {
        $vars['title'] = $jatbi->lang("Hóa đơn đã hủy");
        if ($app->method() === 'GET') {
            $vars['accounts'] = array_merge([["value" => "", "text" => $jatbi->lang("Tất cả")]], $app->select("accounts", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A']));
            $vars['stores'] = $stores;
            echo $app->render($template. '/invoices/invoices-cancel.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            // --- 1. Đọc tham số ---
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
            $searchValue = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';
            $orderName = isset($_POST['order'][0]['column']) ? $_POST['columns'][$_POST['order'][0]['column']]['name'] : 'invoices.id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

            $filter_customers = isset($_POST['customers']) ? trim($_POST['customers']) : '';
            $filter_date = isset($_POST['date']) ? trim($_POST['date']) : '';
            $filter_stores = isset($_POST['stores']) ? trim($_POST['stores']) : '';
            $filter_branch = isset($_POST['branch']) ? trim($_POST['branch']) : '';
            $filter_user = isset($_POST['user']) ? trim($_POST['user']) : '';
            $filter_status = isset($_POST['status']) ? trim($_POST['status']) : '';

            // --- 2. Xây dựng truy vấn với JOIN ---
            $joins = [
                "[>]customers" => ["customers" => "id"],
                "[>]accounts" => ["cancel_confirm_user" => "id"],
                "[>]stores" => ["stores" => "id"],
                "[>]branch" => ["branch" => "id"]
            ];

            $where = [
                "AND" => [
                    "invoices.deleted" => 0,
                    "invoices.type" => [1, 2],
                    "invoices.cancel" => 2,
                ],
            ];

            // Áp dụng bộ lọc
            if (!empty($searchValue)) {
                $where['AND']['OR'] = [
                    "invoices.code[~]" => $searchValue,
                    "invoices.notes[~]" => $searchValue,
                    "customers.name[~]" => $searchValue,
                ];
            }
            if (!empty($filter_customers)) {
                $where['AND']['invoices.customers'] = $filter_customers;
            }
            if (!empty($filter_branch)) {
                $where['AND']['invoices.branch'] = $filter_branch;
            }
            if (!empty($filter_status)) {
                $where['AND']['invoices.status'] = $filter_status;
            }
            if (!empty($filter_user)) {
                $where['AND']['invoices.cancel_confirm_user'] = $filter_user;
            }
            if (!empty($filter_stores)) {
                $where['AND']['invoices.stores'] = $filter_stores;
            } else {
                $where['AND']['invoices.stores'] = $accStore;
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

            // --- 3. Đếm và tính tổng ---
            $count = $app->count("invoices", $joins, "invoices.id", $where);

            // Thêm sắp xếp và phân trang
            $where["ORDER"] = [$orderName => strtoupper($orderDir)];
            $where["LIMIT"] = [$start, $length];

            // --- 4. Lấy dữ liệu chính ---
            $datas = [];
            $tongtien_page = 0;
            $thanhtoan_page = 0;
            $dathanhtoan_page = 0;

            $columns = [
                "invoices.id",
                "invoices.code",
                "invoices.total",
                "invoices.minus",
                "invoices.discount",
                "invoices.surcharge",
                "invoices.transport",
                "invoices.payments",
                "invoices.prepay",
                "invoices.status",
                "invoices.cancel_confirm",
                "customers.name(customer_name)",
                "accounts.name(account_name)",
                "stores.name(store_name)",
                "branch.name(branch_name)"
            ];

            $app->select("invoices", $joins, $columns, $where, function ($data) use (&$datas, &$tongtien_page, &$thanhtoan_page, &$dathanhtoan_page, $jatbi, $app, $setting) {
                $tongtien_page += $data['total'];
                $thanhtoan_page += $data['payments'];
                $dathanhtoan_page += $data['prepay'];

                $datas[] = [
                    "code" => '<a class="text-nowrap pjax-load" href="/invoices/invoices-views/' . $data['id'] . '">#' . ($setting['ballot_code']['invoices'] ?? '') . '-' . $data['code'] . $data['id'] . '</a>',
                    "customers" => $data['customer_name'] ?? "",
                    "total" => '<span class="fw-bold text-primary">' . number_format($data['total'] ?? 0) . '</span>',
                    "minus" => '<span class="fw-bold text-info">' . number_format($data['minus'] ?? 0) . '</span>',
                    "discount" => '<span class="fw-bold text-info">' . number_format($data['discount'] ?? 0) . '</span>',
                    "surcharge" => '<span class="fw-bold text-info">' . number_format($data['surcharge'] ?? 0) . '</span>',
                    "transport" => '<span class="fw-bold text-info">' . number_format($data['transport'] ?? 0) . '</span>',
                    "payments" => '<span class="fw-bold text-success">' . number_format($data['payments'] ?? 0) . '</span>',
                    "prepay" => '<span class="fw-bold text-warning">' . number_format($data['prepay'] ?? 0) . '</span>',
                    "remaining" => '<span class="fw-bold text-danger">' . number_format($data['payments'] - $data['prepay']) ?? 0 . '</span>',
                    "status" => $data['status'] == 1
                        ? '<span class="fw-bold text-success">' . $jatbi->lang("Đã thanh toán") . '</span>'
                        : '<span class="fw-bold text-danger">' . $jatbi->lang("Công nợ") . '</span>',
                    "cancel_confirm" => date('d/m/Y H:i:s', strtotime($data['cancel_confirm'])),
                    "accounts" => $data['account_name'] ?? "",
                    "store" => $data['store_name'] ?? "",
                    "branch" => $data['branch_name'] ?? "",
                    "views" => '<a href="/invoices/invoices-views/' . $data['id'] . '" data-pjax class="btn btn-eclo-light btn-sm border-0 py-1 px-2 rounded-3" aria-label="' . $jatbi->lang('Xem') . '"><i class="ti ti-eye"></i></a>',
                ];
            });

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas,
                "footerData" => [
                    "page_total" => number_format($tongtien_page),
                    "page_payments" => number_format($thanhtoan_page),
                    "page_prepay" => number_format($dathanhtoan_page),
                ]
            ]);
        }
    })->setPermissions(['invoices-cancel']);


    $app->router('/invoices', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores,$template) {
        $vars['title'] = $jatbi->lang("Đơn hàng");
        if ($app->method() === 'GET') {
            $vars['stores'] = array_merge([["value" => "", "text" => $jatbi->lang("Tất cả")]], $app->select("stores", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A']));
            $vars['accounts'] = array_merge([["value" => "", "text" => $jatbi->lang("Tất cả")]], $app->select("accounts", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A']));
            $vars['personnels'] = array_merge([["value" => "", "text" => $jatbi->lang("Tất cả")]], $app->select("personnels", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A']));
            echo $app->render($template. '/invoices/invoices.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            // --- 1. Đọc tham số ---
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
            $searchValue = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';
            $orderName = isset($_POST['order'][0]['column']) ? $_POST['columns'][$_POST['order'][0]['column']]['name'] : 'invoices.id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

            $filter_customers = isset($_POST['customers']) ? trim($_POST['customers']) : '';
            $filter_date = isset($_POST['date']) ? trim($_POST['date']) : '';
            $filter_stores = isset($_POST['stores']) ? trim($_POST['stores']) : '';
            $filter_branch = isset($_POST['branch']) ? trim($_POST['branch']) : '';
            $filter_user = isset($_POST['user']) ? trim($_POST['user']) : '';
            $filter_status = isset($_POST['status']) ? trim($_POST['status']) : '';
            $filter_personnels = isset($_POST['personnels']) ? trim($_POST['personnels']) : '';

            // --- 2. Xây dựng truy vấn với JOIN ---
            $joins = [
                "[>]customers" => ["customers" => "id"],
                "[>]accounts" => ["user" => "id"],
                "[>]stores" => ["stores" => "id"],
                "[>]branch" => ["branch" => "id"]
            ];

            $where = [
                "AND" => [
                    "invoices.deleted" => 0,
                    "invoices.type" => [1, 2],
                    "invoices.cancel" => [0, 1],
                ],
            ];

            // Áp dụng bộ lọc
            if (!empty($searchValue)) {
                $where['AND']['OR'] = [
                    "invoices.code[~]" => $searchValue,
                    "invoices.notes[~]" => $searchValue,
                    "customers.name[~]" => $searchValue,
                ];
            }
            if (!empty($filter_customers)) {
                $where['AND']['invoices.customers'] = $filter_customers;
            }
            if (!empty($filter_branch)) {
                $where['AND']['invoices.branch'] = $filter_branch;
            }
            if (!empty($filter_status)) {
                $where['AND']['invoices.status'] = $filter_status;
            }
            if (!empty($filter_user)) {
                $where['AND']['invoices.user'] = $filter_user;
            }
            if (!empty($filter_stores)) {
                $where['AND']['invoices.stores'] = $filter_stores;
            }
            if (!empty($filter_personnels)) {
                $invoice_ids = $app->select("invoices_personnels", "invoices", ["personnels" => $filter_personnels]);
                $where['AND']['invoices.id'] = $invoice_ids;
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
            $where['AND']['invoices.date_poster[<>]'] = [$date_from, $date_to];

            $count = $app->count("invoices", $joins, "invoices.id", $where);

            $where["ORDER"] = [$orderName => strtoupper($orderDir)];
            $where["LIMIT"] = [$start, $length];

            $datas = [];
            $tongtien_page = 0;
            $thanhtoan_page = 0;
            $dathanhtoan_page = 0;
            $sum_minus_page = 0;
            $sum_buy_qty_page = 0;
            $sum_return_qty_page = 0;

            $columns = [
                "invoices.id",
                "invoices.code",
                "invoices.total",
                "invoices.minus",
                "invoices.discount",
                "invoices.surcharge",
                "invoices.transport",
                "invoices.payments",
                "invoices.prepay",
                "invoices.status",
                "invoices.date_poster",
                "invoices.lock_or_unlock",
                "invoices.cancel",
                "invoices.cancel_content",
                "invoices.code_group",
                "invoices.notes",
                "invoices.amount_return",
                "customers.name(customer_name)",
                "accounts.name(user_name)",
                "stores.name(store_name)",
                "branch.name(branch_name)"
            ];

            $app->select("invoices", $joins, $columns, $where, function ($data) use (&$datas, &$tongtien_page, &$thanhtoan_page, &$dathanhtoan_page, &$sum_minus_page, &$sum_buy_qty_page, &$sum_return_qty_page, $jatbi, $app, $setting) {

                $products_info = $app->select(
                    "invoices_products",
                    ["[>]products" => ["products" => "id"]],
                    ["products.code", "products.name", "invoices_products.amount"],
                    ["invoices" => $data['id']]
                );
                $products_html = '';
                $total_amount = 0;
                foreach ($products_info as $p) {
                    $products_html .= '- ' . ($p['code'] ?? '') . ' - ' . ($p['name'] ?? '') . '<br>';
                    $total_amount += (float) ($p['amount'] ?? 0);
                }

                $personnel_html = '';

                $personnel_data = $app->select(
                    "invoices_personnels",
                    ["[>]personnels" => ["personnels" => "id"]],
                    ["personnels.code", "personnels.name"],
                    ["invoices" => $data["id"], "invoices_personnels.deleted" => 0]
                );

                foreach ($personnel_data as $personnel) {
                    $code = $personnel['code'] ?? '';
                    $name = $personnel['name'] ?? 'N/A';
                    $personnel_html .= htmlspecialchars($code . ' - ' . $name) . '<br>';
                }

                // Tính tổng cho trang hiện tại
                $tongtien_page += $data['total'];
                $thanhtoan_page += $data['payments'];
                $dathanhtoan_page += $data['prepay'];
                $sum_minus_page += $data['minus'] ?? 0;
                $sum_buy_qty_page += $total_amount;
                $sum_return_qty_page += (float) ($data['amount_return'] ?? 0);

                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "ma_hoa_don" => '<a class="text-nowrap pjax-load" href="/invoices/invoices-views/' . $data['id'] . '/">#' . ($setting['ballot_code']['invoices'] ?? '') . '-' . $data['code'] . $data['id'] . '</a>',
                    "ma_doan" => $data['code_group'] ?? '',
                    "khach_hang" => $data['customer_name'] ?? "",
                    "san_pham_mua" => $products_html ?? "",
                    "so_luong_mua" => '<span class="fw-bold text-primary">' . number_format($total_amount ?? 0) . '</span>',
                    "so_luong_tra" => '<span class="fw-bold text-primary">' . number_format($data['amount_return'] ?? 0) . '</span>',
                    "tong_tien" => '<span class="fw-bold text-primary">' . number_format($data['total'] ?? 0) . '</span>',
                    "giam_tru" => '<span class="fw-bold text-info">' . number_format($data['minus'] ?? 0) . '</span>',
                    "giam_gia" => '<span class="fw-bold text-info">' . number_format($data['discount'] ?? 0) . '</span>',
                    "surcharge" => '<span class="fw-bold text-info">' . number_format($data['surcharge'] ?? 0) . '</span>', // Sửa ở đây
                    "transport" => '<span class="fw-bold text-info">' . number_format($data['transport'] ?? 0) . '</span>',
                    "thanh_toan" => '<span class="fw-bold text-success">' . number_format($data['payments'] ?? 0) . '</span>',
                    "da_thanh_toan" => '<span class="fw-bold text-warning">' . number_format($data['prepay'] ?? 0) . '</span>',
                    "con_lai" => '<span class="fw-bold text-danger">' . number_format(($data['payments'] ?? 0) - ($data['prepay'] ?? 0)) . '</span>', // Sửa ở đây
                    "ghi_chu" => $data['notes'] ?? '',
                    "trang_thai" => $data['status'] == 1 ? '<span class="fw-bold text-success">' . $jatbi->lang("Đã thanh toán") . '</span>' : '<span class="fw-bold text-danger">' . $jatbi->lang("Chưa thanh toán") . '</span>',
                    "nhan_vien_ban_hang" => $personnel_html ?? "",
                    "ngay" => date('d/m/Y H:i:s', strtotime($data['date_poster'])) ?? "",
                    "tai_khoan" => $data['user_name'] ?? "",
                    "cua_hang" => $data['store_name'] ?? "",
                    "quay_hang" => $data['branch_name'] ?? "",
                    "huy_don" => $data['cancel'] == 1 ? '<span data-bs-toggle="tooltip" title="' . $jatbi->lang('Yêu cầu hủy') . ': ' . $data['cancel_content'] . '" class="bg-danger d-block rounded-circle animate__animated animate__infinite infinite animate__heartBeat" style="width:10px;height: 10px;"></span>' : '',
                    "action" => $app->component("action", [
                        "button" => [
                            // Xem
                            [
                                'type' => 'link',
                                'name' => $jatbi->lang("Xem"),
                                'action' => ['href' => '/invoices/invoices-views/' . $data['id'] . '', 'data-pjax' => '']
                            ],
                            // Sửa mã đoàn
                            ($data['lock_or_unlock'] == 0 && $jatbi->permission('invoices_codegroup.edit', 'button')) ? [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa mã đoàn"),
                                'action' => ['data-url' => '/invoices/invoices-code-group/' . $data['id'] . '', 'data-action' => 'modal']
                            ] : null,
                            // Sửa khách hàng
                            ($data['lock_or_unlock'] == 0 && $jatbi->permission('invoices_customers.edit', 'button')) ? [
                                'type' => 'link',
                                'name' => $jatbi->lang("Sửa khách hàng"),
                                'action' => ['href' => '/invoices/invoices-update-customers/' . $data['id'] . '', 'data-pjax' => '']
                            ] : null,
                            // Sửa nhân viên bán hàng
                            ($data['lock_or_unlock'] == 0 && $jatbi->permission('invoices_personnels.edit', 'button')) ? [
                                'type' => 'link',
                                'name' => $jatbi->lang("Sửa nhân viên bán hàng"),
                                'action' => ['href' => '/invoices/invoices-update-personnels/' . $data['id'] . '', 'data-pjax' => '']
                            ] : null,
                            // Sửa hình thức thanh toán
                            ($data['lock_or_unlock'] == 0 && $jatbi->permission('invoices_payments.edit', 'button')) ? [
                                'type' => 'link',
                                'name' => $jatbi->lang("Sửa hình thức thanh toán"),
                                'action' => ['href' => '/invoices/payments-edit/' . $data['id'] . '', 'data-pjax' => '']
                            ] : null,
                            // Sửa thông tin bổ sung
                            ($data['lock_or_unlock'] == 0 && $jatbi->permission('invoices_additional_information.edit', 'button')) ? [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa thông tin bổ sung"),
                                'action' => ['data-url' => '/invoices/invoices-additional_information/' . $data['id'] . '', 'data-action' => 'modal']
                            ] : null,
                            // Yêu cầu hủy
                            (empty($data['returns']) && $data['cancel'] == 0) ? [
                                'type' => 'button',
                                'name' => $jatbi->lang("Yêu cầu hủy"),
                                'action' => ['data-url' => '/invoices/invoices-cancel-req/' . $data['id'] . '', 'data-action' => 'modal']
                            ] : null,
                            // Xác nhận hủy
                            (empty($data['returns']) && $data['cancel'] == 1) ? [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xác nhận hủy"),
                                'action' => ['data-url' => '/invoices/invoices-cancel-confirm/' . $data['id'] . '', 'data-action' => 'modal']
                            ] : null,
                            // Trả hàng
                            ($jatbi->permission('returns.add', 'button')) ? [
                                'type' => 'link',
                                'name' => $jatbi->lang("Trả hàng"),
                                'action' => ['href' => '/invoices/returns-add/' . $data['id'] . '', 'data-pjax' => '']
                            ] : null,
                            // Thanh toán
                            ($data['status'] == 2) ? [
                                'type' => 'button',
                                'name' => $jatbi->lang("Thanh toán"),
                                'action' => ['data-url' => '/invoices/invoices-prepay/' . $data['id'] . '', 'data-action' => 'modal']
                            ] : null,
                            // Ghi chú
                            ($jatbi->permission('invoices_notes.edit', 'button')) ? [
                                'type' => 'button',
                                'name' => $jatbi->lang("Ghi chú"),
                                'action' => ['data-url' => '/invoices/invoices-notes/' . $data['id'], 'data-action' => 'modal']
                            ] : null,
                        ]
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
                    "sum_buy_qty" => number_format($sum_buy_qty_page),
                    "sum_return_qty" => number_format($sum_return_qty_page),
                    "page_total" => number_format($tongtien_page),
                    "sum_minus" => number_format($sum_minus_page),
                    "page_payments" => number_format($thanhtoan_page),
                    "page_prepay" => number_format($dathanhtoan_page),
                    "sum_remaining" => number_format($thanhtoan_page - $dathanhtoan_page),
                ]
            ]);
        }
    })->setPermissions(['invoices']);

    $app->router('/invoices-code-group/{id}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $id = $vars['id'] ?? 0;

        // Lấy dữ liệu hóa đơn để làm việc
        $data = $app->get("invoices", ["id", "code", "code_group"], ["id" => $id, "deleted" => 0]);
        if (!$data) {
            return $app->render($template . '/error.html', [], $jatbi->ajax());
        }

        $vars['title'] = $jatbi->lang("Sửa mã đoàn") . ' #' . $data['code'] . $data['id'];

        if ($app->method() === 'GET') {
            // --- HIỂN THỊ FORM ---
            $vars['data'] = $data;
            echo $app->render($setting['template'] . '/invoices/code-group-edit.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            // --- XỬ LÝ CẬP NHẬT ---
            $app->header(['Content-Type' => 'application/json']);
            $post = array_map([$app, 'xss'], $_POST);

            if (empty($post['code_group'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Vui lòng không để trống')]);
                return;
            }

            $update = ["code_group" => $post['code_group']];
            $app->update("invoices", $update, ["id" => $id]);

            // Ghi log
            $log_content = 'Sửa mã đoàn: ' . ($data['code_group'] ?? 'trống') . ' -> ' . $update['code_group'];
            $app->insert("invoices_logs", [
                "invoices" => $id,
                "user" => $app->getSession("accounts")['id'] ?? 0,
                "date" => date("Y-m-d H:i:s"),
                "content" => $log_content,
            ]);
            $jatbi->logs('invoices', 'code-group-edit', $update, $id);

            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công'), 'load' => 'true']);
        }
    })->setPermissions(['invoices_codegroup.edit']);

    ob_start();

    $app->router('/invoices/invoices-excel', 'GET', function ($vars) use ($app, $jatbi, $setting) {

        try {
            // --- BƯỚC 1: LẤY VÀ XỬ LÝ CÁC BỘ LỌC TỪ URL ---
            $searchValue = $_GET['search']['value'] ?? '';
            $filter_customers = $_GET['customers'] ?? '';
            $filter_date = $_GET['date'] ?? '';
            $filter_stores = $_GET['stores'] ?? '';
            $filter_branch = $_GET['branch'] ?? '';
            $filter_user = $_GET['user'] ?? '';
            $filter_status = $_GET['status'] ?? '';
            $filter_personnels = $_GET['personnels'] ?? '';

            // --- BƯỚC 2: XÂY DỰNG TRUY VẤN VỚI JOIN ---
            $joins = [
                "[<]customers" => ["customers" => "id"],
                "[<]accounts" => ["user" => "id"],
                "[<]stores" => ["invoices.stores" => "id"],
                "[<]branch" => ["invoices.branch" => "id"]
            ];
            $where = [
                "AND" => [
                    "invoices.deleted" => 0,
                    "invoices.type" => [1, 2],
                    "invoices.cancel" => [0, 1],
                ]
            ];

            if ($filter_customers  === 'null') {
                $filter_customers  = '';
            }
            if ($filter_branch === 'null') {
                $filter_branch = '';
            }
            if ($filter_stores === 'null') {
                $filter_stores = '';
            }
            // Áp dụng bộ lọc
            if (!empty($searchValue))
                $where['AND']['OR'] = ["invoices.code[~]" => $searchValue, "customers.name[~]" => $searchValue];
            if (!empty($filter_customers))
                $where['AND']['invoices.customers'] = $filter_customers;
            if (!empty($filter_branch))
                $where['AND']['invoices.branch'] = $filter_branch;
            if (!empty($filter_status))
                $where['AND']['invoices.status'] = $filter_status;
            if (!empty($filter_user))
                $where['AND']['invoices.user'] = $filter_user;
            if (!empty($filter_stores))
                $where['AND']['invoices.stores'] = $filter_stores;
            if (!empty($filter_personnels)) {
                $invoice_ids = $app->select("invoices_personnels", "invoices", ["personnels" => $filter_personnels]);
                $where['AND']['invoices.id'] = $invoice_ids ?: [0];
            }

            if (!empty($filter_date)) {
                $date_parts = explode(' - ', $filter_date);
                if (count($date_parts) == 2) {
                    $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date_parts[0]))));
                    $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date_parts[1]))));
                    $where['AND']['invoices.date_poster[<>]'] = [$date_from, $date_to];
                }
            }

            $where["ORDER"] = ['invoices.id' => 'DESC'];

            // --- BƯỚC 3: LẤY DỮ LIỆU CHÍNH ---
            $columns = ["invoices.id", "invoices.code", "invoices.total", "invoices.minus", "invoices.discount", "invoices.surcharge", "invoices.transport", "invoices.payments", "invoices.prepay", "invoices.status", "invoices.date_poster", "invoices.code_group", "invoices.notes", "invoices.amount_return", "customers.name(customer_name)", "accounts.name(user_name)", "stores.name(store_name)", "branch.name(branch_name)"];
            $datas = $app->select("invoices", $joins, $columns, $where);

            if (empty($datas)) {
                ob_end_clean();
                exit("Không có dữ liệu để xuất file.");
            }

            // --- BƯỚC 4: TỐI ƯU HÓA - LẤY DỮ LIỆU PHỤ ---
            $invoice_ids = array_column($datas, 'id');
            $products_data = $app->select("invoices_products", ["[>]products" => ["products" => "id"]], ["invoices_products.invoices", "products.code", "products.name"], ["invoices" => $invoice_ids]);
            $personnels_data = $app->select("invoices_personnels", ["[>]personnels" => ["personnels" => "id"]], ["invoices_personnels.invoices", "personnels.name"], ["invoices" => $invoice_ids]);

            $product_map = [];
            foreach ($products_data as $p) {
                $product_map[$p['invoices']][] = ($p['code'] ?? '') . ' - ' . ($p['name'] ?? '');
            }
            $personnel_map = [];
            foreach ($personnels_data as $p) {
                $personnel_map[$p['invoices']][] = $p['name'];
            }

            // --- BƯỚC 5: TẠO FILE EXCEL ---
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('DonHang');
            $headers = ['MÃ HÓA ĐƠN', 'MÃ ĐOÀN', 'KHÁCH HÀNG', 'SẢN PHẨM MUA', 'SL MUA', 'SL TRẢ', 'TỔNG TIỀN', 'GIẢM TRỪ', 'GIẢM GIÁ (%)', 'THANH TOÁN', 'ĐÃ TT', 'CÒN LẠI', 'GHI CHÚ', 'TRẠNG THÁI', 'NHÂN VIÊN', 'NGÀY', 'TÀI KHOẢN', 'CỬA HÀNG'];
            $sheet->fromArray($headers, null, 'A1');

            $row_index = 2;
            foreach ($datas as $data) {
                $products_list = implode("\n", $product_map[$data['id']] ?? []);
                $personnels_list = implode(', ', $personnel_map[$data['id']] ?? []);
                $total_amount = $app->sum("invoices_products", "amount", ["invoices" => $data['id'], "type" => [1, 2]]);

                $sheet->setCellValue('A' . $row_index, '#' . ($setting['ballot_code']['invoices'] ?? 'HĐ') . $data['id']);
                $sheet->setCellValue('B' . $row_index, $data['code_group']);
                $sheet->setCellValue('C' . $row_index, $data['customer_name']);
                $sheet->setCellValue('D' . $row_index, $products_list);
                $sheet->setCellValue('E' . $row_index, $total_amount);
                $sheet->setCellValue('F' . $row_index, $data['amount_return']);
                $sheet->setCellValue('G' . $row_index, $data['total']);
                $sheet->setCellValue('H' . $row_index, $data['minus']);
                $sheet->setCellValue('I' . $row_index, $data['discount']);
                $sheet->setCellValue('J' . $row_index, $data['payments']);
                $sheet->setCellValue('K' . $row_index, $data['prepay']);
                $sheet->setCellValue('L' . $row_index, $data['payments'] - $data['prepay']);
                $sheet->setCellValue('M' . $row_index, $data['notes']);
                $sheet->setCellValue('N' . $row_index, ($setting['Status_invoices'][$data['status']]['name'] ?? ''));
                $sheet->setCellValue('P' . $row_index, $jatbi->datetime($data['date_poster']));
                $sheet->setCellValue('O' . $row_index, $personnels_list);
                $sheet->setCellValue('Q' . $row_index, $data['user_name']);
                $sheet->setCellValue('R' . $row_index, $data['store_name']);

                $row_index++;
            }

            // ... (Định dạng cột và Ghi dòng tổng) ...

            // --- BƯỚC 6: XUẤT FILE ---
            ob_end_clean();
            $file_name = 'donhang_' . date('d-m-Y') . '.xls';
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="' . $file_name . '"');
            header('Cache-Control: max-age=0');
            $writer = new Xls($spreadsheet);
            $writer->save('php://output');
            exit;
        } catch (Exception $e) {
            ob_end_clean();
            http_response_code(500);
            exit("Lỗi: " . $e->getMessage());
        }
    })->setPermissions(['invoices']);

    $app->router('/insurance', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores,$template) {

        $vars['title'] = $jatbi->lang("Bảo hành");
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Bảo hành");
            $vars['stores'] = $stores;
            $vars['accounts'] = array_merge([["value" => "", "text" => $jatbi->lang("Tất cả")]], $app->select("accounts", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A']));
            echo $app->render($template. '/invoices/insurance.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            // --- 1. Đọc tham số ---
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : $setting['site_page'] ?? 10;
            $searchValue = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';
            $orderName = isset($_POST['order'][0]['column']) ? $_POST['columns'][$_POST['order'][0]['column']]['name'] : 'invoices.id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

            $filter_customers = isset($_POST['customers']) ? trim($_POST['customers']) : '';
            $filter_date = isset($_POST['date']) ? trim($_POST['date']) : '';
            $filter_stores = isset($_POST['stores']) ? trim($_POST['stores']) : '';
            $filter_branch = isset($_POST['branch']) ? trim($_POST['branch']) : '';
            $filter_user = isset($_POST['user']) ? trim($_POST['user']) : '';
            $filter_status = isset($_POST['status']) ? trim($_POST['status']) : '';

            // --- 2. Xây dựng truy vấn với JOIN ---
            $joins = [
                "[>]customers" => ["customers" => "id"],
                "[>]accounts" => ["user" => "id"],
                "[>]stores" => ["stores" => "id"],
                "[>]branch" => ["branch" => "id"],
                "[>]status_invoices" => ["status" => "id"]
            ];

            $where = [
                "AND" => [
                    "invoices.deleted" => 0,
                    "invoices.type" => 4,
                    "invoices.cancel" => [0, 1],
                ],
            ];

            // Áp dụng bộ lọc
            if (!empty($searchValue)) {
                $where['AND']['OR'] = [
                    "invoices.code[~]" => $searchValue,
                    "invoices.notes[~]" => $searchValue,
                    "customers.name[~]" => $searchValue,
                ];
            }
            if (!empty($filter_customers)) {
                $where['AND']['invoices.customers'] = $filter_customers;
            }
            if (!empty($filter_branch)) {
                $where['AND']['invoices.branch'] = $filter_branch;
            }
            if (!empty($filter_status)) {
                $where['AND']['invoices.status'] = $filter_status;
            }
            if (!empty($filter_user)) {
                $where['AND']['invoices.user'] = $filter_user;
            }
            if (!empty($filter_stores)) {
                $where['AND']['invoices.stores'] = $filter_stores;
            }
            //  else {
            //     $where['AND']['invoices.stores'] = $accStore;
            // }

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

            // Lọc theo quyền
            // if (!$jatbi->permission('see-all', 'button')) {
            //     $where['AND']['invoices.user'] = $account['id'];
            // }

            // --- 3. Đếm bản ghi ---
            $count = $app->count("invoices", $joins, "invoices.id", $where);

            $where["ORDER"] = [$orderName => strtoupper($orderDir)];
            $where["LIMIT"] = [$start, $length];

            $datas = [];
            $tongtien_page = 0;
            $thanhtoan_page = 0;
            $dathanhtoan_page = 0;
            $sum_minus_page = 0;

            $columns = [
                "invoices.id",
                "invoices.code",
                "invoices.total",
                "invoices.minus",
                "invoices.discount",
                "invoices.surcharge",
                "invoices.transport",
                "invoices.payments",
                "invoices.prepay",
                "invoices.status",
                "invoices.date",
                "invoices.cancel",
                "invoices.cancel_content",
                "invoices.code_group",
                "invoices.notes",
                "customers.name(customer_name)",
                "accounts.name(user_name)",
                "stores.name(store_name)",
                "branch.name(branch_name)",
                "status_invoices.name(status_name)",
                "status_invoices.color(status_color)"
            ];

            $app->select("invoices", $joins, $columns, $where, function ($data) use (&$datas, &$tongtien_page, &$thanhtoan_page, &$dathanhtoan_page, &$sum_minus_page, $jatbi, $app, $setting) {
                // Tính tổng cho trang hiện tại
                $tongtien_page += $data['total'] ?? 0;
                $thanhtoan_page += $data['payments'] ?? 0;
                $dathanhtoan_page += $data['prepay'] ?? 0;
                $sum_minus_page += $data['minus'] ?? 0;

                $con_lai = ($data['payments'] ?? 0) - ($data['prepay'] ?? 0);

                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "ma_hoa_don" => '<a class="text-nowrap pjax-load" href="/invoices/invoices-views/' . $data['id'] . '">#' . ($setting['ballot_code']['invoices'] ?? '') . '-' . $data['code'] . '</a>',
                    "ma_doan" => $data['code_group'] ?? '',
                    "khach_hang" => $data['customer_name'] ?? "",
                    "tong_tien" => '<span class="fw-bold text-primary">' . number_format($data['total'] ?? 0) . '</span>',
                    "giam_tru" => '<span class="fw-bold text-info">' . number_format($data['minus'] ?? 0) . '</span>',
                    "giam_gia" => '<span class="fw-bold text-info">' . number_format($data['discount'] ?? 0) . '</span>',
                    "phu_thu" => '<span class="fw-bold text-info">' . number_format($data['surcharge'] ?? 0) . '</span>',
                    "van_chuyen" => '<span class="fw-bold text-info">' . number_format($data['transport'] ?? 0) . '</span>',
                    "thanh_toan" => '<span class="fw-bold text-success">' . number_format($data['payments'] ?? 0) . '</span>',
                    "da_thanh_toan" => '<span class="fw-bold text-warning">' . number_format($data['prepay'] ?? 0) . '</span>',
                    "con_lai" => '<a href="#!" class="modal-url text-danger" data-url="/invoices/invoices-prepay/' . $data['id'] . '">' . number_format($con_lai) . '</a>',
                    "trang_thai" => '<span class="fw-bold text-' . ($data['status_color'] ?? 'secondary') . '">' . ($data['status_name'] ?? '') . '</span>',
                    "ngay" => date('d/m/Y H:i:s', strtotime($data['date'])) ?? 0,
                    "tai_khoan" => $data['user_name'] ?? "",
                    "cua_hang" => $data['store_name'] ?? "",
                    "huy_don" => $data['cancel'] == 1 ? '<span data-bs-toggle="tooltip" title="' . $jatbi->lang('Yêu cầu hủy') . ': ' . ($data['cancel_content'] ?? '') . '" class="bg-danger d-block rounded-circle animate__animated animate__infinite infinite animate__heartBeat" style="width:10px;height: 10px;"></span>' : '',
                    "action" => $app->component("action", [
                        "button" => [
                            ['type' => 'button', 'name' => $jatbi->lang('Xem'), 'permission' => ['invoices.view'], 'action' => ['href' => '/invoices/invoices-views/' . $data['id'] . '', 'class' => 'pjax-load']],
                            $data['cancel'] == 0 ? ['type' => 'button', 'name' => $jatbi->lang('Yêu cầu hủy'), 'permission' => ['invoices.cancel'], 'action' => ['data-url' => '/invoices/invoices-cancel-req/' . $data['id'] . '', 'data-action' => 'modal']] : [],
                            $data['cancel'] == 1 ? ['type' => 'button', 'name' => $jatbi->lang('Xác nhận hủy'), 'permission' => ['invoices.cancel.confirm'], 'action' => ['data-url' => '/invoices/invoices-cancel-confirm/' . $data['id'] . '', 'data-action' => 'modal']] : [],
                            ['type' => 'button', 'name' => $jatbi->lang('Ghi chú'), 'permission' => ['invoices.notes'], 'action' => ['data-url' => '/invoices/invoices-notes/' . $data['id'] . '', 'data-action' => 'modal']],
                        ]
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
                    "page_total" => number_format($tongtien_page, 0),
                    "sum_minus" => number_format($sum_minus_page, 0),
                    "page_payments" => number_format($thanhtoan_page, 0),
                    "page_prepay" => number_format($dathanhtoan_page, 0),
                    "sum_remaining" => number_format($thanhtoan_page - $dathanhtoan_page, 0),
                ]
            ]);
        }
    })->setPermissions(['insurance']);



    // xem hoa don 
    $app->router('/products_views/{id}', 'GET', function ($vars) use ($app, $jatbi, $setting,$template) {
        $vars['datas'] = $app->select("invoices_products", ["id", "products"], ["invoices" => $vars['id']]);
        if (!empty($vars['datas'])) {
            echo $app->render($template . '/drivers/driver-views.html', $vars, $jatbi->ajax());
        } else {
            echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
        }
    })->setPermissions(['invoices']);


    $app->router('/invoices-views/{id}', 'GET', function ($vars) use ($app, $jatbi, $setting,$template) {
        $id = $vars['id'] ?? 0;

        $data = $app->get("invoices", [
            "[>]customers" => ["customers" => "id"],
            "[>]customers_types" => ["customers.type" => "id"],
            "[>]stores" => ["stores" => "id"],
            "[>]province" => ["customers.province" => "id"],
            "[>]district" => ["customers.district" => "id"],
            "[>]ward" => ["customers.ward" => "id"],
        ], [
            "invoices.id",
            "invoices.customers",
            "invoices.date",
            "invoices.date_poster",
            "invoices.discount",
            "invoices.total",
            "invoices.vat",
            "invoices.discount_price",
            "invoices.notes",
            "invoices.code_group",
            "invoices.minus",
            "invoices.status",
            "invoices.type",
            "invoices.surcharge",
            "invoices.transport",
            "invoices.prepay",
            "invoices.discount_customers",
            "invoices.discount_customers_price",
            "invoices.code",
            "invoices.cancel",
            "customers.name(customer_name)",
            "customers.phone(customer_phone)",
            "customers.email(customer_email)",
            "customers.address(customer_address)",
            "customers.notes(customer_notes)",
            "customers_types.name(customer_type_name)",
            "stores.name(store_name)",
            "province.name(province_name)",
            "district.name(district_name)",
            "ward.name(ward_name)",
        ], ["invoices.id" => $id, "invoices.deleted" => 0]);

        if (!$data) {
            header("HTTP/1.0 404 Not Found");
            exit;
        }

        // --- CÁC TRUY VẤN PHỤ ---
        $SelectProducts = $app->select(
            "invoices_products(ip)",
            [
                "[<]products(p)" => ["ip.products" => "id"],
                "[<]units(u)" => ["p.units" => "id"],
            ],
            [
                "ip.amount",
                "ip.price",
                "ip.price_old",
                "ip.vat",
                "ip.vat_price",
                "ip.additional_information",
                "p.code(product_code)",
                "p.name(product_name)",
                "u.name(unit_name)"
            ],
            ["ip.invoices" => $id, "ip.deleted" => 0]
        );
        $details = $app->select(
            "invoices_details",
            [
                "[<]accounts" => ["user" => "id"],
                "[<]type_payments" => ["type_payments" => "id"]
            ],
            [
                "invoices_details.type",
                "invoices_details.code",
                "invoices_details.date",
                "invoices_details.price",
                "invoices_details.content",
                "accounts.name(user_name)",
                "type_payments.name(type_payment_name)"
            ],
            [
                "invoices_details.invoices" => $id,
                "invoices_details.deleted" => 0
            ]
        );

        $selectpers = $app->select(
            "invoices_personnels",
            [
                "[<]personnels" => ["personnels" => "id"]
            ],
            [
                "personnels.name(personnel_name)",
                "invoices_personnels.price"
            ],
            [
                "invoices_personnels.invoices" => $id,
                "invoices_personnels.deleted" => 0
            ]
        );

        $logs = $app->select("invoices_logs", ["[<]accounts" => ["user" => "id"]], ["invoices_logs.date", "invoices_logs.content", "accounts.name(user_name)"], ["invoices" => $id]);
        $transport = $app->get("invoices_transport", ["[<]transport" => ["transport" => "id"]], ["t.name(transport_name)", "it.content"], ["it.invoices" => $id, "it.deleted" => 0]);
        $getCard = $app->get("customers_card", "*", ["customers" => $data['customers']]);
        $getPoint = $app->get("customers_point", "*", ["invoices" => $id, "customers" => $data['customers'], "deleted" => 0]);

        $total_amount = array_sum(array_column($SelectProducts, 'amount'));
        $vats = [];
        foreach ($SelectProducts as $item) {
            if ($item['vat'] > 0) {
                $vats[$item['vat']] = ($vats[$item['vat']] ?? 0) + $item['vat_price'];
            }
        }
        $total_payment = ($data['total'] - $data['discount_price'] - $data['minus'] - $data['discount_customers_price']) + $data['surcharge'];
        $payment = $total_payment + $data['transport'];
        $payment_prepay = $payment - $data['prepay'];

        $grouped_detaills = [];
        $type_map = [
            'prepay' => 'Thanh toán',
            'minus' => 'Giảm trừ',
            'surcharge' => 'Phụ thu',
        ];
        foreach ($details as $item) {
            if (isset($type_map[$item['type']])) {
                $display_type = $type_map[$item['type']];
                $grouped_detaills[$display_type][] = $item;
            }
        }

        $vars['data'] = $data;
        $vars['SelectProducts'] = $SelectProducts;
        $vars['transport'] = $transport;
        $vars['selectpers'] = $selectpers;
        $vars['logs'] = $logs;
        $vars['getCard'] = $getCard;
        $vars['detaills'] = $details;
        $vars['getPoint'] = $getPoint;
        $vars['total_amount'] = $total_amount;
        $vars['vats'] = $vats;
        $vars['total_payment'] = $total_payment;
        $vars['payment'] = $payment;
        $vars['payment_prepay'] = $payment_prepay;
        $vars['grouped_detaills'] = $grouped_detaills;

        echo $app->render($template . '/invoices/invoices-views.html', $vars);
    })->setPermissions(['invoices']);

    $app->router('/invoices-notes/{id}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting,$template) {
        $id = $vars['id'] ?? 0;
        $vars['title'] = $jatbi->lang("Thêm ghi chú");

        $data = $app->get("invoices", ["id", "notes"], ["id" => $id, "deleted" => 0]);
        if (!$data) {
            // Nếu không tìm thấy, hiển thị trang lỗi
            echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
            return;
        }

        if ($app->method() === 'GET') {
            $vars['data'] = $data;
            echo $app->render($template . '/invoices/notes-post.html', $vars, $jatbi->ajax());
        }
        // XỬ LÝ POST: CẬP NHẬT DỮ LIỆU
        elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            $notes = $_POST['notes'] ?? '';

            if (empty($notes)) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng không để trống")]);
                return;
            }

            $update = ["notes" => $app->xss($notes)];
            $app->update("invoices", $update, ["id" => $id]);

            $jatbi->logs('invoices', 'edit_notes', $update);

            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công"), 'url' => 'auto']);
        }
    })->setPermissions(['invoices.edit']);


    $app->router('/invoices-print/{id}', 'GET', function ($vars) use ($app, $jatbi, $setting,$template) {
        $id = $vars['id'] ?? 0;

        $data = $app->get("invoices", "*", ["id" => $id]);
        if (!$data) {
            echo $app->render($setting['template'] . '/error.html');
            return;
        }

        $customer_info = $app->get(
            "customers",
            [
                "[>]province" => ["province" => "id"],
                "[>]district" => ["district" => "id"],
                "[>]ward" => ["ward" => "id"]
            ],
            [
                "customers.name",
                "customers.phone",
                "customers.address",
                "province.name(province_name)",
                "district.name(district_name)",
                "ward.name(ward_name)"
            ],
            ["customers.id" => $data['customers']]
        );
        $data['customer'] = $customer_info;

        $data['customer']['full_address'] = implode(', ', array_filter([$customer_info['address'], $customer_info['ward_name'], $customer_info['district_name'], $customer_info['province_name']]));

        $store_info = $app->get("stores", ["name", "address"], ["id" => $data["stores"]]);
        $data['store_info'] = $store_info;

        $creator_info = $app->get("accounts", "name", ["id" => $data['user']]);
        $data['creator_name'] = $creator_info;

        $personnel_info = $app->get("invoices_personnels", ["[<]personnels" => ["personnels" => "id"]], "personnels.name", ["invoices" => $id]);
        $data['personnel_name'] = $personnel_info;

        $data['products'] = $app->select("invoices_products", [
            "[<]products" => ["products" => "id"],
            "[<]units" => ["products.units" => "id"],
        ], [
            "products.code(product_code)",
            "products.name(product_name)",
            "units.name(unit_name)",
            "invoices_products.amount",
            "invoices_products.price",
            "invoices_products.price_old",
            "invoices_products.discount",
            "invoices_products.discount_price",
            "invoices_products.minus",
            "invoices_products.additional_information"
        ], ["invoices" => $id, "invoices_products.deleted" => 0]);

        $total_payment_calculated = ($data['total'] - $data['discount_price'] - $data['minus'] - $data['discount_customers_price']) + $data['surcharge'] + $data['transport'];
        $data['total_payment_calculated'] = $total_payment_calculated;

        $vars['data'] = $data;
        $vars['title'] = $jatbi->lang("In hóa đơn");

        echo $app->render($template. '/invoices/print.html', $vars, $jatbi->ajax());
    })->setPermissions(['invoices']);

    $app->router('/returns-delete', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa phiếu trả hàng");

        if ($app->method() === 'GET') {
            // Hiển thị modal xác nhận xóa
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            $box_ids = explode(',', $app->xss($_GET['box'] ?? ''));
            if (empty(array_filter($box_ids))) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng chọn dữ liệu cần xóa")]);
                return;
            }

            // --- BƯỚC 1: LẤY DỮ LIỆU HÀNG LOẠT ---
            $invoices_to_delete = $app->select("invoices", "*", ["id" => $box_ids, "deleted" => 0]);
            if (empty($invoices_to_delete)) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Không tìm thấy dữ liệu")]);
                return;
            }

            $invoice_ids = array_column($invoices_to_delete, 'id');
            $products_in_invoices = $app->select("invoices_products", ["invoices", "products", "price", "amount"], ["invoices" => $invoice_ids, "deleted" => 0]);

            // --- BƯỚC 2: XỬ LÝ LOGIC TRONG VÒNG LẶP (ĐÃ TỐI ƯU) ---
            foreach ($invoices_to_delete as $invoice) {
                // Tạo phiếu kho xuất hàng
                $warehouse_insert = [
                    "code" => 'PX',
                    "type" => 'export',
                    "data" => 'products',
                    "stores" => $invoice['stores'],
                    "customers" => $invoice['customers'],
                    "content" => "Xuất hàng do xóa hóa đơn trả #" . $invoice['code'] . $invoice['id'],
                    "user" => $app->getSession("accounts")['id'] ?? 0,
                    "date" => date("Y-m-d"),
                    "active" => $jatbi->active(30),
                    "date_poster" => date("Y-m-d H:i:s"),
                ];
                $app->insert("warehouses", $warehouse_insert);
                $warehouse_id = $app->id();

                $details_to_insert = [];
                $logs_to_insert = [];

                // Lặp qua các sản phẩm đã được lấy sẵn
                foreach ($products_in_invoices as $product) {
                    if ($product['invoices'] == $invoice['id']) {
                        $product_info = $app->get("products", ["id", "branch", "amount"], ["id" => $product["products"]]);

                        // Cập nhật lại số lượng sản phẩm
                        $app->update("products", ["amount[-]" => $product["amount"]], ["id" => $product["products"]]);

                        // Cập nhật lại số lượng trả trên hóa đơn gốc
                        $app->update("invoices", ["amount_return[-]" => $product['amount']], ["id" => $invoice['returns']]);

                        // Chuẩn bị dữ liệu để insert hàng loạt
                        $detail_data = [
                            "warehouses" => $warehouse_id,
                            "type" => 'export',
                            "data" => 'products',
                            "products" => $product['products'],
                            "price" => $product['price'],
                            "amount" => $product['amount'],
                            "amount_total" => $product['amount'],
                            "stores" => $invoice['stores'],
                            "branch" => $product_info['branch'],
                            "notes" => "Xuất hàng do xóa hóa đơn trả",
                            "user" => $app->getSession("accounts")['id'] ?? 0,
                            "date" => date('Y-m-d H:i:s'),
                        ];
                        $details_to_insert[] = $detail_data;
                    }
                }
                // Insert chi tiết kho và log hàng loạt
                if (!empty($details_to_insert)) {
                    $app->insert("warehouses_details", $details_to_insert);
                }
            }

            // --- BƯỚC 3: CẬP NHẬT TRẠNG THÁI XÓA HÀNG LOẠT ---
            $app->update("invoices", ["deleted" => 1], ["id" => $invoice_ids]);
            $app->update("invoices_details", ["deleted" => 1], ["invoices" => $invoice_ids]);
            $app->update("invoices_products", ["deleted" => 1], ["invoices" => $invoice_ids]);
            $app->update("invoices_transport", ["deleted" => 1], ["invoices" => $invoice_ids]);
            $app->update("expenditure", ["deleted" => 1], ["invoices" => $invoice_ids]);
            $app->update("drivers_payment_details", ["deleted" => 1], ["invoices" => $invoice_ids]);
            $app->update("invoices_personnels", ["deleted" => 1], ["invoices" => $invoice_ids]);

            // --- BƯỚC 4: GHI LOG VÀ TRẢ VỀ KẾT QUẢ ---
            $names = array_column($invoices_to_delete, 'id');
            $jatbi->logs('invoices', 'delete', $invoices_to_delete);
            $jatbi->trash('/returns/restore', "Xóa phiếu trả hàng: #" . implode(', #', $names), ["database" => 'invoices', "data" => $invoice_ids]);

            echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
        }
    })->setPermissions(['returns.delete']);

    $app->router('/invoices-update-customers/{id}', 'GET', function ($vars) use ($app, $jatbi, $setting, $accStore,$template) {
        $vars['title'] = $jatbi->lang("Sửa khách hàng");
        $dispatch = "update-customers";
        $action = $vars['id'];
        $vars['action'] = $action;
        $vars['dispatch'] = $dispatch;
        $datala = $_SESSION[$dispatch][$action] ?? [];
        $getCust = $app->get("customers", "*", ["id" => $datala['customers']['id'] ?? ""]);
        if ($vars['id']) {
            $data = $app->get("invoices", ["id", "customers"], ["id" => $app->xss($vars['id']), "deleted" => 0, "stores" => $accStore]);
            if ($data > 1) {
                $getCustomers = $app->get("customers", "*", ["id" => $data['customers']]);
                $vars['getCustomers'] = $getCustomers;
                $vars['getCust'] = $getCust;
                $vars['data'] = $data;
                echo $app->render($template . '/invoices/update-customers.html', $vars);
            } else {
                echo $app->render($setting['template'] . '/error.html', $vars);
            }
        }
    })->setPermissions(['invoices_customers.edit']);

    $app->router('/invoices-update-customers/{action}/{id}', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $dispatch = "update-customers";
        $action = $vars['action'];
        $data = $app->get("customers", ["id", "name", "phone", "email"], ["id" => $app->xss($vars['id'])]);
        if ($data > 1) {
            $getCard = $app->get("customers_card", "*", ["customers" => $data['id'], "deleted" => 0, "ORDER" => ["discount" => "DESC", "id" => "DESC"]]);
            $_SESSION[$dispatch][$action]['customers'] = [
                "id" => $data['id'],
                "name" => $data['name'],
                "phone" => $data['phone'],
                "email" => $data['email'],
                "card" => $getCard,
            ];
            if ($getCard > 1) {
                $_SESSION[$dispatch][$action]['discount_customers'] = $getCard['discount'];
                $_SESSION[$dispatch][$action]['discount_card'] = $getCard['id'];
            }
            echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
        } else {
            echo json_encode(['status' => 'success', 'content' => 'Cập nhật thất bại']);
        }
    });

    $app->router('/invoices-additional_information/{id}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting,$template) {
        $id = (int) ($vars['id'] ?? 0);

        $invoice = $app->get("invoices", "*", ["id" => $id, "deleted" => 0]);
        if (!$invoice) {
            return $app->render($setting['template'] . '/error.html', [], $jatbi->ajax());
        }

        $vars['title'] = $jatbi->lang("Sửa thông tin bổ sung");
        $vars['invoice'] = $invoice;

        if ($app->method() === 'GET') {
            $products_list = $app->select(
                "invoices_products",
                ["[>]products" => ["products" => "id"]],
                [
                    "invoices_products.id",
                    "invoices_products.additional_information",
                    "products.code",
                    "products.name"
                ],
                ["invoices_products.invoices" => $id, "invoices_products.deleted" => 0]
            );
            $vars['products_list'] = $products_list;

            echo $app->render($template . '/invoices/additional-information-edit.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $post_data = $_POST['additional_information'] ?? [];

            foreach ($post_data as $product_id => $info) {
                $update = [
                    "additional_information" => $app->xss($info)
                ];
                $app->update("invoices_products", $update, ["id" => (int) $product_id]);
            }

            $jatbi->logs('invoices', 'additional-information-edit', $post_data, $id);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công'), 'load' => 'true']);
        }
    })->setPermissions(['invoices_additional_information.edit']);

    $app->router('/payments-edit/{id}', 'GET', function ($vars) use ($app, $jatbi, $setting,$template) {
        $action = 'payments';
        $vars['title'] = $jatbi->lang("Hình thức thanh toán");
        $invoices = $app->get("invoices", "id", ["id" => $app->xss($vars['id']), "deleted" => 0]);
        if ($invoices > 1) {
            $payments = $app->select("invoices_details", ["id", "invoices", "type", "type_payments", "price", "date", "date_poster", "user", "stores", "content"], ["invoices" => $invoices, "deleted" => 0, "type" => "prepay"]);
            if (($_SESSION['editpayments'][$action]['order'] ?? null) != $invoices) {
                unset($_SESSION['editpayments'][$action]);
                foreach ($payments as $key => $value) {
                    $_SESSION['editpayments'][$action]['type_payments'][$value['id']] = [
                        "invoices_details" => $value['id'],
                        "invoices" => $value['invoices'],
                        "type_payments" => $value['type_payments'],
                        "type" => $value['type'],
                        "price" => $value['price'],
                        "date" => $value['date'],
                        "date_poster" => $value['date_poster'],
                        "user" => $value['user'],
                        "stores" => $value['stores'],
                        "content" => $value['content'],
                    ];
                }
                $_SESSION['editpayments'][$action]['order'] = $invoices;
            }
            $SelectType_payments = $_SESSION['editpayments'][$action]['type_payments'] ?? [];
            $vars['action'] = $action;
            $vars['SelectType_payments'] = $SelectType_payments;
            $type_payments = $app->select("type_payments", ["id", "name", "has", "debt"], ["deleted" => 0, "status" => 'A']);
            $type_paymentsOptions = [['value' => '', 'text' => '']]; // hàng rỗng
            foreach ($type_payments as $row) {
                $type_paymentsOptions[] = [
                    'value' => $row['id'],
                    'text' => $row['name']
                ];
            }
            $vars['type_payments'] = $type_paymentsOptions;
            $vars['invoices'] = $invoices;
            echo $app->render($template. '/invoices/payments-edit.html', $vars);
        } else {
            echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
        }
    })->setPermissions(['invoices_payments.edit']);

    $app->router('/payments-update/{action}/{type}/{field}/{id}', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $router['2'] = $vars["action"];
        $router['3'] = $vars["type"];
        $router['4'] = $vars["field"];
        $router['5'] = $vars["id"];
        $action = $router['2'];
        if ($router['3'] == 'typepayments') {
            if ($router['4'] == 'add') {
                $data = $app->get("type_payments", ["id", "name", "code"], ["id" => $app->xss($_POST['value']), "status" => "A", "deleted" => 0,]);
                if ($data > 1) {
                    $_SESSION['editpayments'][$action]['type_payments'][$data['id']] = [
                        "type_payments" => $data['id'],
                        "price" => '',
                        "name" => $data['name'],
                        "code" => $data['code'],
                    ];
                    echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
                } else {
                    echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thất bại")]);
                }
            } elseif ($router['4'] == 'deleted') {
                unset($_SESSION['editpayments'][$action]['type_payments'][$router['5']]);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } elseif ($router['4'] == 'price') {
                $_SESSION['editpayments'][$action]['type_payments'][$router['5']][$router['4']] = $app->xss(str_replace([','], '', $_POST['value']));
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                $_SESSION['editpayments'][$action]['type_payments'][$router['5']][$router['4']] = $app->xss($_POST['value']);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            }
        } elseif ($router['3'] == 'cancel') {
            unset($_SESSION['editpayments'][$action]);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } elseif ($router['3'] == 'completed') {
            $SelectType_payments = $_SESSION['editpayments'][$action];
            $get = $app->get("invoices", "payments", ["id" => $SelectType_payments['order']]);
            $sumprice = 0;
            $error_warehouses = "";
            $errors = "";
            $error = [];
            foreach ($SelectType_payments['type_payments'] as $key => $tt) {
                $sumprice += (float) $tt['price'];
                if (!isset($tt['price']) || $tt['price'] < 0) {
                    $error_warehouses = 'true';
                }
            }
            if ($get != $sumprice) {
                $errors = 'false';
            }
            if (count($SelectType_payments['type_payments']) == 0) {
                $error = ["status" => 'error', 'content' => $jatbi->lang("Vui lòng không để trống")];
            } elseif ($error_warehouses == 'true') {
                $error = ['status' => 'error', 'content' => $jatbi->lang("Vui lòng nhập số tiền")];
            } elseif ($errors == 'false') {
                $error = ['status' => 'error', 'content' => $jatbi->lang("Tổng số tiền thanh toán không trùng với số tiền đơn hàng")];
            }
            if (count($error) == 0) {
                $app->update("invoices_details", ["deleted" => 1], ["invoices" => $_SESSION['editpayments'][$action]['order']]);
                $app->update("expenditure", ["deleted" => 1], ["invoices" => $_SESSION['editpayments'][$action]['order']]);
                foreach (($SelectType_payments['type_payments'] ?? []) as $key => $value) {
                    $getType_Payment = $app->get("type_payments", ["id", "code", "debt", "has"], ["id" => $value['type_payments']]);
                    $get_invoices = $app->get("invoices", ["id", "date", "date_poster", "stores", "customers", "notes"], ["id" => $SelectType_payments['order']]);
                    if (count($SelectType_payments['type_payments'] ?? []) == 1) {
                        $code = $getType_Payment['code'];
                    }
                    if (count($SelectType_payments['type_payments'] ?? []) > 1) {
                        $code = 'CK';
                    }
                    $insert = [
                        "invoices" => $SelectType_payments['order'],
                        "type" => 'prepay',
                        "type_payments" => $value['type_payments'],
                        "code" => strtotime(date('Y-m-d H:i:s')),
                        "price" => $value['price'],
                        "content" => $value['content'],
                        "date" => $get_invoices['date'],
                        "date_poster" => $get_invoices['date_poster'],
                        "user" => $app->getSession("accounts")['id'] ?? 0,
                        "stores" => $get_invoices['stores'],
                    ];
                    $app->insert("invoices_details", $insert);
                    $expenditure = [
                        "type" => 1,
                        "debt" => $app->xss($getType_Payment['debt']),
                        "has" => $app->xss($getType_Payment['has']),
                        "price" => $insert['price'],
                        "content" => $code,
                        "date" => $app->xss($insert['date']),
                        "ballot" => $app->xss($insert['code']),
                        "invoices" => $app->xss($insert['invoices']),
                        "customers" => $get_invoices['customers'],
                        "notes" => $get_invoices['notes'],
                        "user" => $app->getSession("accounts")['id'] ?? 0,
                        "date_poster" => $insert['date_poster'],
                        "stores" => $insert['stores'],
                    ];
                    $app->insert("expenditure", $expenditure);

                    $app->update("invoices", ["code" => $code, "prepay" => $sumprice], ["id" => $_SESSION['editpayments'][$action]['order']]);
                }
                $jatbi->logs('editpayments', $action, [$insert, ($pro_logs ?? null), $_SESSION['editpayments'][$action]], $SelectType_payments['order']);
                unset($_SESSION['editpayments'][$action]);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $error['content']]);
            }
        }
    })->setPermissions(['invoices.cancel.req']);

    $app->router('/invoices-cancel-confirm/{id}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $accStore,$template) {
        $id = (int) ($vars['id'] ?? 0);

        // Lấy hóa đơn đang chờ xác nhận hủy
        $invoice = $app->get("invoices", "*", [
            "id" => $id,
            "deleted" => 0,
            "stores" => $accStore,
            "cancel" => 1 // Chỉ xử lý hóa đơn đang ở trạng thái "chờ hủy"
        ]);

        if (!$invoice) {
            return $app->render($setting['template'] . '/error.html', ['content' => 'Không tìm thấy hóa đơn hoặc hóa đơn không ở trạng thái chờ hủy.'], $jatbi->ajax());
        }

        $vars['title'] = $jatbi->lang("Xác nhận hủy hóa đơn");
        $vars['invoice'] = $invoice;

        if ($app->method() === 'GET') {
            // --- HIỂN THỊ MODAL XÁC NHẬN ---
            echo $app->render($template . '/invoices/cancel-confirm.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            $app->action(function ($db) use ($app, $invoice, $jatbi) {
                $invoice_id = $invoice['id'];

                $warehouse_id = $db->insert("warehouses", [
                    "code" => 'PN',
                    "type" => 'import',
                    "data" => 'products',
                    "stores" => $invoice['stores'],
                    "customers" => $invoice['customers'],
                    "content" => "Nhập lại hàng do hủy hóa đơn #" . $invoice['code'] . $invoice_id,
                    "user" => $app->getSession("accounts")['id'] ?? 0,
                    "date" => date("Y-m-d"),
                    "active" => $jatbi->active(30),
                    "date_poster" => date("Y-m-d H:i:s"),
                    "invoices" => $invoice_id,
                ]);

                $products_in_invoice = $db->select("invoices_products", ["products", "amount"], ["invoices" => $invoice_id, "deleted" => 0]);

                if (!empty($products_in_invoice)) {
                    foreach ($products_in_invoice as $product) {
                        $db->update("products", ["amount[+]" => $product["amount"]], ["id" => $product["products"]]);
                    }
                }

                $db->update("expenditure", ["deleted" => 1], ["invoices" => $invoice_id]);
                $db->update("customers_point", ["deleted" => 1], ["invoices" => $invoice_id]);
                $db->update("warehouses", ["deleted" => 1], ["type" => "export", "invoices" => $invoice_id]);
                $db->update("warehouses_logs", ["deleted" => 1], ["type" => "export", "invoices" => $invoice_id]);

                $db->update("invoices", [
                    "cancel" => 2,
                    "cancel_confirm" => date('Y-m-d H:i:s'),
                    "cancel_confirm_user" => $app->getSession("accounts")['id'] ?? 0,
                ], ["id" => $invoice_id]);

                $db->insert("invoices_logs", ["invoices" => $invoice_id, "user" => $app->getSession("accounts")['id'] ?? 0, "date" => date("Y-m-d H:i:s"), "content" => 'Xác nhận hủy hóa đơn']);
                $jatbi->logs('invoices', 'cancel-confirm', $invoice, $invoice_id);
            });

            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Đã hủy hóa đơn thành công')]);
        }
    })->setPermissions(['invoices.cancel.confirm']);

    $app->router('/returns-add/{id}', 'GET', function ($vars) use ($app, $jatbi, $setting, $accStore, $stores,$template) {
        $vars['title'] = $jatbi->lang("Tạo đơn hàng trả");
        $dispatch = "returns";
        $action = $vars['id'];
        $vars['action'] = $action;
        $vars['dispatch'] = $dispatch;
        $getInvoices = $app->get("invoices", ["id", "stores", "discount", "discount_customers", "code_group", "type_payments", "customers", "discount_card", 'branch', 'surcharge'], ["id" => $app->xss($vars['id']), "deleted" => 0, "stores" => $accStore]);
        if (!empty($getInvoices)) {
            $getProducts = $app->select("invoices_products", ["id", "products", "nearsightedness", "colors", "categorys", "vendor", "amount", "price_old", "discount", "discount_price", "vat", "vat_type", "vat_price"], ["invoices" => $getInvoices['id'], "deleted" => 0]);

            if (!isset($_SESSION[$dispatch][$action]['invoices'])) {

                $_SESSION[$dispatch][$action]['invoices'] = $getInvoices['id'];
                $_SESSION[$dispatch][$action]['status'] = 1;
                $_SESSION[$dispatch][$action]['type'] = 3;
                $_SESSION[$dispatch][$action]['stores'] = $getInvoices['stores'];
                $_SESSION[$dispatch][$action]['branch'] = $getInvoices['branch'];
                $_SESSION[$dispatch][$action]['discount'] = $getInvoices['discount'];
                $_SESSION[$dispatch][$action]['discount_customers'] = $getInvoices['discount_customers'];
                $_SESSION[$dispatch][$action]['code_group'] = $getInvoices['code_group'];
                $customers = $app->get("customers", ["id", "name", "phone", "email"], ["id" => $app->xss($getInvoices['customers'])]);
                if (!empty($customers)) {
                    $getCard = $app->get("customers_card", "*", ["customers" => $getInvoices['discount_card'], "deleted" => 0, "ORDER" => ["discount" => "DESC", "id" => "DESC"]]);
                    $_SESSION[$dispatch][$action]['customers'] = [
                        "id" => $customers['id'],
                        "name" => $customers['name'],
                        "phone" => $customers['phone'],
                        "email" => $customers['email'],
                        "card" => $getCard,
                    ];
                    if (!empty($getCard)) {
                        $_SESSION[$dispatch][$action]['discount_customers'] = $getCard['discount'];
                        $_SESSION[$dispatch][$action]['discount_card'] = $getCard['id'];
                    }
                }
                foreach (($getProducts ?? []) as $key => $value) {
                    $product = $app->get("products", "id", ["id" => $value['products']]);
                    $reTurn = $app->sum("invoices_products", "amount", ["returns" => $getInvoices['id'], "type" => 3, "deleted" => 0, "products" => $product]);
                    $_SESSION[$dispatch][$action]['products'][$value['id']] = [
                        "returns" => $value['id'],
                        "products" => $value['products'],
                        "nearsightedness" => $value['nearsightedness'],
                        "colors" => $value['colors'],
                        "categorys" => $value['categorys'],
                        "vendor" => $value['vendor'],
                        "warehouses" => (float) ($value['amount'] ?? 0) - (float) ($reTurn ?? 0),
                        "amount" => (((int) $value['amount'] - (int) $reTurn) === 0 ? 0 : 1),
                        "price" => (float) $value['price_old'] - ((float) $value['price_old'] * (float) $value['discount'] / 100),
                        "price_old" => $value['price_old'],
                        "discount" => $value['discount'],
                        "discount_price" => $value['discount_price'],
                        "vat" => $value['vat'],
                        "vat_type" => $value['vat_type'],
                        "vat_price" => $value['vat_price'],
                    ];
                    $_SESSION[$dispatch][$action]['vat'][$data['vat'] ?? null][$data['id'] ?? null] = $value['vat_price'];
                }
                $_SESSION[$dispatch][$action]['point'] = $app->get("point", "id", ["status" => 'A', "ORDER" => ["id" => "DESC"]]);
            }
            if (!isset($_SESSION[$dispatch][$action]['date'])) {
                $_SESSION[$dispatch][$action]['date'] = date("Y-m-d");
            }
            $data = $_SESSION[$dispatch][$action];
            $getCus = $app->get("customers", ["id", "name"], ["id" => $data['customers']['id'] ?? ""]);
            $branchss = $app->select("branch", ["id", "name", "code"], ["deleted" => 0, "stores" => $data['stores'], "status" => 'A']);
            $branchssOption = [];

            // Thêm dòng rỗng mặc định
            $branchssOption[] = [
                "value" => "",
                "text" => "Chọn quầy hàng"
            ];

            foreach ($branchss as $branch) {
                $branchssOption[] = [
                    "value" => $branch['id'],
                    "text" => $branch['name'],
                ];
            }

            $SelectProducts = $data['products'] ?? [];
            $vars['data'] = $data;
            $vars['getCus'] = $getCus;
            $vars['branchss'] = $branchssOption;
            $vars['SelectProducts'] = $SelectProducts;
            $Status_invoicesOptions = [];
            // thêm dòng rỗng
            $Status_invoicesOptions[] = [
                "value" => "",
                "text" => "Chọn một mục"
            ];

            foreach ($setting['Status_invoices'] as $Status_invoice) {
                $Status_invoicesOptions[] = [
                    "value" => $Status_invoice['id'],
                    "text" => $Status_invoice['name'],
                ];
            }
            $vars['Status_invoices'] = $Status_invoicesOptions;
            $type_payments = $app->select("type_payments", ["id", "name", "has", "debt"], ["deleted" => 0, "status" => 'A', "main" => 0]);
            $vars['type_payments'] = $type_payments;
            $personnelsData = $app->select("personnels", ["id", "name", "code"], ["deleted" => 0, "status" => 'A', "stores" => $accStore]);
            $personnels = [];

            // Thêm dòng rỗng mặc định
            $personnels[] = [
                "value" => "",
                "text" => "Nhân viên bán hàng"
            ];

            foreach ($personnelsData as $personnel) {
                $personnels[] = [
                    "value" => $personnel['id'],
                    "text" => $personnel['code'] . " - " . $personnel['name'],
                ];
            }

            $vars['personnels'] = $personnels;
            $vars['stores'] = array_merge([['value' => '', 'text' => $jatbi->lang('Chọn cửa hàng')]], $stores);

            echo $app->render($template . '/invoices/returns-add.html', $vars);
        } else {
            echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
        }
    })->setPermissions(['returns.add']);


    $app->router('/invoices-add', 'GET', function ($vars) use ($app, $jatbi, $setting, $accStore, $stores,$template) {
        $vars['title'] = $jatbi->lang("Tạo đơn hàng");
        $dispatch = "orders";
        $action = "add";
        $vars['dispatch'] = $dispatch;
        $vars['action'] = $action;
        if (
            ($_SESSION['stores'] ?? 0) != ($_SESSION[$dispatch][$action]['stores'] ?? 0)
            && ($_SESSION['stores'] ?? 0) != 0
        ) {
            unset($_SESSION[$dispatch][$action]);
        }

        if (empty($_SESSION[$dispatch][$action]['status'])) {
            unset($_SESSION[$dispatch][$action]);
            $_SESSION[$dispatch][$action]['status'] = 1;
            $_SESSION[$dispatch][$action]['type'] = 1;
        }

        if (empty($_SESSION[$dispatch][$action]['date'])) {
            $_SESSION[$dispatch][$action]['date'] = date("Y-m-d");
        }

        if (empty($_SESSION[$dispatch][$action]['type_payments'])) {
            $_SESSION[$dispatch][$action]['type_payments'] = $app->get("type_payments", "id", [
                "status" => 'A',
                "deleted" => 0,
                "ORDER" => ["id" => "ASC"]
            ]);
        }

        if (empty($_SESSION[$dispatch][$action]['stores'])) {
            $_SESSION[$dispatch][$action]['stores'] = $app->get("stores", "id", [
                "id" => $accStore,
                "status" => 'A',
                "deleted" => 0,
                "ORDER" => ["id" => "ASC"]
            ]);
        }

        if (empty($_SESSION[$dispatch][$action]['point'])) {
            $_SESSION[$dispatch][$action]['point'] = $app->get("point", "id", [
                "status" => 'A',
                "ORDER" => ["id" => "DESC"]
            ]);
        }
        $data = $_SESSION[$dispatch][$action] ?? [];
        $getCus = $app->get("customers", ["id", "name"], ["id" => $data['customers']['id'] ?? 0]);
        $SelectProducts = $data['products'] ?? [];
        $vars['data'] = $data;
        $vars['getCus'] = $getCus;
        $vars['SelectProducts'] = $SelectProducts;
        $Status_invoicesOptions = [];
        // thêm dòng rỗng
        $Status_invoicesOptions[] = [
            "value" => "",
            "text" => "Chọn một mục"
        ];

        foreach ($setting['Status_invoices'] as $Status_invoice) {
            $Status_invoicesOptions[] = [
                "value" => $Status_invoice['id'],
                "text" => $Status_invoice['name'],
            ];
        }
        $vars['Status_invoices'] = $Status_invoicesOptions;
        $type_payments = $app->select("type_payments", ["id", "name", "has", "debt"], ["deleted" => 0, "status" => 'A', "main" => 0]);

        $options = [];

        // Option mặc định
        $options[] = [
            "value" => "",
            "text" => $jatbi->lang("Chọn hình thức thanh toán"),
        ];

        foreach ($type_payments as $type_payment) {
            $subpays = $app->select("type_payments", "*", [
                "main" => $type_payment['id'],
                "status" => 'A',
                "deleted" => 0
            ]);

            // Option cha
            $options[] = [
                "value" => $type_payment['id'],
                "text" => $type_payment['name'],
            ];

            // Option con
            foreach ($subpays as $subpay) {
                $options[] = [
                    "value" => $subpay['id'],
                    "text" => "----- " . $subpay['name'],
                ];
            }
        }
        $vars['type_payments'] = $options;
        $personnelsData = $app->select("personnels", ["id", "name", "code"], ["deleted" => 0, "status" => 'A', "stores" => $accStore]);
        $personnels = [];

        // Thêm dòng rỗng mặc định
        $personnels[] = [
            "value" => "",
            "text" => "Nhân viên bán hàng"
        ];

        foreach ($personnelsData as $personnel) {
            $personnels[] = [
                "value" => $personnel['id'],
                "text" => $personnel['code'] . " - " . $personnel['name'],
            ];
        }

        $vars['personnels'] = $personnels;
        $vars['stores'] = array_merge([['value' => '', 'text' => $jatbi->lang('Chọn cửa hàng')]], $stores);
        echo $app->render($template . '/invoices/invoices-add.html', $vars);
    })->setPermissions(['invoices.add']);

    $app->router('/invoices-update-personnels/{id}', 'GET', function ($vars) use ($app, $jatbi, $setting, $accStore, $stores,$template) {
        $vars['title'] = $jatbi->lang("Sửa nhân viên bán hàng");
        $action = 'personnels';
        $invoices = $app->get("invoices", "id", ["id" => $app->xss($vars['id']), "deleted" => 0]);
        if ($invoices > 1) {
            $personnels = $app->select("invoices_personnels", ["id", "invoices", "invoices_type", "personnels", "price", "date", "user", "stores"], ["invoices" => $invoices, "deleted" => 0]);
            if (($_SESSION['personnels'][$action]['order'] ?? null) != $invoices) {
                unset($_SESSION['personnels'][$action]);
                foreach ($personnels as $key => $value) {
                    $_SESSION['personnels'][$action]['personnels'][$value['id']] = [
                        "invoices_personnels" => $value['id'],
                        "invoices" => $value['invoices'],
                        "invoices_type" => $value['invoices_type'],
                        "personnels" => $value['personnels'],
                        "price" => $value['price'],
                        "date" => $value['date'],
                        "user" => $value['user'],
                        "stores" => $value['stores'],
                        "name" => $app->get("personnels", "name", ["id" => $value["personnels"]]),
                    ];
                }
                $_SESSION['personnels'][$action]['order'] = $invoices;
            }
            $Select_personnels = $_SESSION['personnels'][$action]['personnels'] ?? [];
            $personnelsData = $app->select("personnels", ["id", "name", "code"], ["deleted" => 0, "status" => 'A', "stores" => $app->get("invoices", "stores", ["id" => $invoices])]);
            $personnelss = [];

            // Thêm dòng rỗng mặc định
            $personnelss[] = [
                "value" => "",
                "text" => "Nhân viên bán hàng"
            ];

            foreach ($personnelsData as $personnel) {
                $personnelss[] = [
                    "value" => $personnel['id'],
                    "text" => $personnel['code'] . " - " . $personnel['name'],
                ];
            }
            $vars['Select_personnels'] = $Select_personnels;
            $vars['personnelss'] = $personnelss;
            $vars['action'] = $action;
            $vars['invoices'] = $invoices;
            echo $app->render($template . '/invoices/invoices-update-personnels.html', $vars);
        } else {
            return $app->render($setting['template'] . '/error.html', [], $jatbi->ajax());
        }
    })->setPermissions(['invoices_personnels.edit']);
})->middleware('login');
