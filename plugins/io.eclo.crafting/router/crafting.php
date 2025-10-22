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
$app->group($setting['manager'] . "/crafting", function ($app) use ($jatbi, $setting, $stores, $accStore, $template) {

    $app->router('/craftinggold', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {
        // Xử lý yêu cầu GET: Lấy dữ liệu cho các bộ lọc và hiển thị trang
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Kho chế tác vàng");
            $vars['types'] = [
                ['value' => '', 'text' => $jatbi->lang('Tất cả')],
                ['value' => '1', 'text' => 'Đai'],
                ['value' => '2', 'text' => 'Ngọc'],
                ['value' => '3', 'text' => 'Khác'],
            ];
            // 2. Dữ liệu động từ DB
            $pearls_db = $app->select("pearl", ['id(value)', 'name(text)'], ['deleted' => 0]);
            $sizes_db = $app->select("sizes", ['id(value)', 'name(text)'], ['deleted' => 0]);
            $colors_db = $app->select("colors", ['id(value)', 'name(text)'], ['deleted' => 0]);
            $groups_db = $app->select("products_group", ['id(value)', 'name(text)'], ['deleted' => 0]);
            $categorys_db = $app->select("categorys", ['id(value)', 'name(text)'], ['deleted' => 0]);
            $units_db = $app->select("units", ['id(value)', 'name(text)'], ['deleted' => 0]);

            // 3. Thêm lựa chọn "Tất cả" vào đầu mỗi mảng dữ liệu động
            $vars['pearls'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $pearls_db);
            $vars['sizes'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $sizes_db);
            $vars['colors'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $colors_db);
            $vars['groups'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $groups_db);
            $vars['categorys'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $categorys_db);
            $vars['units'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $units_db);

            // 4. Gửi các biến đã xử lý qua view
            echo $app->render($template . '/crafting/craftinggold.html', $vars);

            // Xử lý yêu cầu POST: Lọc và trả về dữ liệu JSON cho bảng
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';

            $orderName = isset($_POST['order'][0]['column']) ? $_POST['columns'][$_POST['order'][0]['column']]['name'] : 'ingredient.id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

            // Lấy giá trị từ các bộ lọc
            $filter_type = isset($_POST['type']) ? $_POST['type'] : '';
            $filter_colors = isset($_POST['colors']) ? $_POST['colors'] : '';
            $filter_pearl = isset($_POST['pearl']) ? $_POST['pearl'] : '';
            $filter_sizes = isset($_POST['sizes']) ? $_POST['sizes'] : '';
            $filter_categorys = isset($_POST['categorys']) ? $_POST['categorys'] : '';
            $filter_group = isset($_POST['group']) ? $_POST['group'] : '';
            $filter_units = isset($_POST['units']) ? $_POST['units'] : '';
            $filter_status = isset($_POST['status']) ? $_POST['status'] : '';

            // Define joins for the query
            $joins = [
                "[>]pearl" => ["pearl" => "id"],
                "[>]sizes" => ["sizes" => "id"],
                "[>]colors" => ["colors" => "id"],
                "[>]categorys" => ["categorys" => "id"],
                "[>]products_group" => ["group" => "id"],
                "[>]units" => ["units" => "id"],
            ];

            // Khởi tạo điều kiện WHERE
            $where = [
                "AND" => [
                    "ingredient.deleted" => 0,
                    "ingredient.crafting[!]" => null,
                ]
            ];

            // Thêm điều kiện tìm kiếm và lọc
            if (!empty($searchValue)) {
                $where['AND']['ingredient.code[~]'] = $searchValue;
            }
            if (!empty($filter_type))
                $where['AND']['ingredient.type'] = $filter_type;
            if (!empty($filter_colors))
                $where['AND']['ingredient.colors'] = $filter_colors;
            if (!empty($filter_pearl))
                $where['AND']['ingredient.pearl'] = $filter_pearl;
            if (!empty($filter_sizes))
                $where['AND']['ingredient.sizes'] = $filter_sizes;
            if (!empty($filter_categorys))
                $where['AND']['ingredient.categorys'] = $filter_categorys;
            if (!empty($filter_group))
                $where['AND']['ingredient.group'] = $filter_group;
            if (!empty($filter_units))
                $where['AND']['ingredient.units'] = $filter_units;
            if (!empty($filter_status)) {
                $where['AND']['ingredient.status'] = $filter_status;
            } else {
                $where['AND']['ingredient.status'] = ["A", "D"];
            }

            // Đếm tổng số bản ghi với điều kiện lọc (trước khi thêm LIMIT và ORDER)
            $count = $app->count("ingredient", $joins, "ingredient.id", $where);

            // Thêm sắp xếp và phân trang vào điều kiện WHERE
            $where["ORDER"] = [$orderName => strtoupper($orderDir)];
            $where["LIMIT"] = [$start, $length];

            $datas = [];
            $totalCrafting = 0;

            $columns = [
                'ingredient.id',
                'ingredient.type',
                'ingredient.code',
                'ingredient.crafting',
                'ingredient.price',
                'ingredient.status',
                'pearl.name(pearl)',
                'sizes.name(sizes)',
                'colors.name(colors)',
                'units.name(unit)',
                'categorys.name(categorys)',
                'products_group.name(group)'
            ];

            $app->select("ingredient", $joins, $columns, $where, function ($data) use (&$datas, &$totalCrafting, $jatbi, $app) {
                $craftingValue = floatval($data['crafting']);
                if (is_numeric($craftingValue)) {
                    $totalCrafting += $craftingValue;
                }

                $datas[] = [
                    "type" => ($data['type'] == 1 ? $jatbi->lang("Đai") : ($data['type'] == 2 ? $jatbi->lang("Ngọc") : $jatbi->lang("Khác"))),
                    "code" => $data['code'],
                    "pearl" => $data['pearl'] ?? $data['categorys'],
                    "sizes" => $data['sizes'] ?? $data['group'],
                    "colors" => $data['colors'],
                    // "categorys" => $data['categorys'],
                    // "group" => $data['group'],
                    "crafting" => $data['crafting'],
                    "price" => number_format($data['price']),
                    "unit" => $data['unit'],
                    "action" => '<a href="/crafting/craftinggold-details/' . $data['id'] . '" class="pjax-load btn btn-eclo-light btn-sm border-0 py-1 px-2 rounded-3" aria-label="' . $jatbi->lang('Xem') . '"><i class="ti ti-eye"></i></a>',

                ];
            });

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas,
                "footerData" => [
                    "totalCrafting" => $totalCrafting
                ]
            ]);
        }
    })->setPermissions(['craftinggold']);

    $app->router('/craftingsilver', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {
        // Xử lý yêu cầu GET: Lấy dữ liệu cho các bộ lọc và hiển thị trang
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Kho chế tác bạc");

            $vars['types'] = [
                ['value' => '', 'text' => $jatbi->lang('Tất cả')],
                ['value' => '1', 'text' => 'Đai'],
                ['value' => '2', 'text' => 'Ngọc'],
                ['value' => '3', 'text' => 'Khác'],
            ];
            // 2. Dữ liệu động từ DB
            $pearls_db = $app->select("pearl", ['id(value)', 'name(text)'], ['deleted' => 0]);
            $sizes_db = $app->select("sizes", ['id(value)', 'name(text)'], ['deleted' => 0]);
            $colors_db = $app->select("colors", ['id(value)', 'name(text)'], ['deleted' => 0]);
            $groups_db = $app->select("products_group", ['id(value)', 'name(text)'], ['deleted' => 0]);
            $categorys_db = $app->select("categorys", ['id(value)', 'name(text)'], ['deleted' => 0]);
            $units_db = $app->select("units", ['id(value)', 'name(text)'], ['deleted' => 0]);

            // 3. Thêm lựa chọn "Tất cả" vào đầu mỗi mảng dữ liệu động
            $vars['pearls'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $pearls_db);
            $vars['sizes'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $sizes_db);
            $vars['colors'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $colors_db);
            $vars['groups'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $groups_db);
            $vars['categorys'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $categorys_db);
            $vars['units'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $units_db);

            echo $app->render($template . '/crafting/craftingsilver.html', $vars);

            // Xử lý yêu cầu POST: Lọc và trả về dữ liệu JSON cho bảng
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';

            $orderName = isset($_POST['order'][0]['column']) ? $_POST['columns'][$_POST['order'][0]['column']]['name'] : 'ingredient.id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

            // Lấy giá trị từ các bộ lọc
            $filter_type = isset($_POST['type']) ? $_POST['type'] : '';
            $filter_colors = isset($_POST['colors']) ? $_POST['colors'] : '';
            $filter_pearl = isset($_POST['pearl']) ? $_POST['pearl'] : '';
            $filter_sizes = isset($_POST['sizes']) ? $_POST['sizes'] : '';
            $filter_categorys = isset($_POST['categorys']) ? $_POST['categorys'] : '';
            $filter_group = isset($_POST['group']) ? $_POST['group'] : '';
            $filter_units = isset($_POST['units']) ? $_POST['units'] : '';
            $filter_status = isset($_POST['status']) ? $_POST['status'] : '';

            // Define joins for the query
            $joins = [
                "[>]pearl" => ["pearl" => "id"],
                "[>]sizes" => ["sizes" => "id"],
                "[>]colors" => ["colors" => "id"],
                "[>]categorys" => ["categorys" => "id"],
                "[>]products_group" => ["group" => "id"],
                "[>]units" => ["units" => "id"],
            ];

            // Khởi tạo điều kiện WHERE
            $where = [
                "AND" => [
                    "ingredient.deleted" => 0,
                    "ingredient.craftingsilver[!]" => null,
                ]
            ];

            // Thêm điều kiện tìm kiếm và lọc
            if (!empty($searchValue)) {
                $where['AND']['ingredient.code[~]'] = $searchValue;
            }
            if (!empty($filter_type))
                $where['AND']['ingredient.type'] = $filter_type;
            if (!empty($filter_colors))
                $where['AND']['ingredient.colors'] = $filter_colors;
            if (!empty($filter_pearl))
                $where['AND']['ingredient.pearl'] = $filter_pearl;
            if (!empty($filter_sizes))
                $where['AND']['ingredient.sizes'] = $filter_sizes;
            if (!empty($filter_categorys))
                $where['AND']['ingredient.categorys'] = $filter_categorys;
            if (!empty($filter_group))
                $where['AND']['ingredient.group'] = $filter_group;
            if (!empty($filter_units))
                $where['AND']['ingredient.units'] = $filter_units;
            if (!empty($filter_status)) {
                $where['AND']['ingredient.status'] = $filter_status;
            } else {
                $where['AND']['ingredient.status'] = ["A", "D"];
            }

            // Đếm tổng số bản ghi với điều kiện lọc (trước khi thêm LIMIT và ORDER)
            $count = $app->count("ingredient", $joins, "ingredient.id", $where);

            // Thêm sắp xếp và phân trang vào điều kiện WHERE
            $where["ORDER"] = [$orderName => strtoupper($orderDir)];
            $where["LIMIT"] = [$start, $length];

            $datas = [];
            $totalCrafting = 0;

            $columns = [
                'ingredient.id',
                'ingredient.type',
                'ingredient.code',
                'ingredient.craftingsilver',
                'ingredient.price',
                'ingredient.status',
                'pearl.name(pearl)',
                'sizes.name(sizes)',
                'colors.name(colors)',
                'units.name(unit)',
                'categorys.name(categorys)',
                'products_group.name(group)'
            ];

            $app->select("ingredient", $joins, $columns, $where, function ($data) use (&$datas, &$totalCrafting, $jatbi, $app) {
                $craftingValue = floatval($data['craftingsilver']);
                if (is_numeric($craftingValue)) {
                    $totalCrafting += $craftingValue;
                }

                $datas[] = [
                    "type" => ($data['type'] == 1 ? $jatbi->lang("Đai") : ($data['type'] == 2 ? $jatbi->lang("Ngọc") : $jatbi->lang("Khác"))),
                    "code" => $data['code'],
                    "pearl" => $data['pearl'] ?? $data['categorys'],
                    "sizes" => $data['sizes'] ?? $data['group'],
                    "colors" => $data['colors'],
                    // "categorys" => $data['categorys'],
                    // "group" => $data['group'],
                    "craftingsilver" => $data['craftingsilver'],
                    "price" => number_format($data['price']),
                    "unit" => $data['unit'],
                    "action" => '<a href="/crafting/craftingsilver-details/' . $data['id'] . '" class="pjax-load btn btn-eclo-light btn-sm border-0 py-1 px-2 rounded-3" aria-label="' . $jatbi->lang('Xem') . '"><i class="ti ti-eye"></i></a>',

                ];
            });

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas,
                "footerData" => [
                    "totalCrafting" => $totalCrafting
                ]
            ]);
        }
    })->setPermissions(['craftingsilver']);

    $app->router('/craftingchain', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {
        // Xử lý yêu cầu GET: Lấy dữ liệu cho các bộ lọc và hiển thị trang
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Kho chế tác chuỗi");

            $vars['types'] = [
                ['value' => '', 'text' => $jatbi->lang('Tất cả')],
                ['value' => '1', 'text' => 'Đai'],
                ['value' => '2', 'text' => 'Ngọc'],
                ['value' => '3', 'text' => 'Khác'],
            ];
            // 2. Dữ liệu động từ DB
            $pearls_db = $app->select("pearl", ['id(value)', 'name(text)'], ['deleted' => 0]);
            $sizes_db = $app->select("sizes", ['id(value)', 'name(text)'], ['deleted' => 0]);
            $colors_db = $app->select("colors", ['id(value)', 'name(text)'], ['deleted' => 0]);
            $groups_db = $app->select("products_group", ['id(value)', 'name(text)'], ['deleted' => 0]);
            $categorys_db = $app->select("categorys", ['id(value)', 'name(text)'], ['deleted' => 0]);
            $units_db = $app->select("units", ['id(value)', 'name(text)'], ['deleted' => 0]);

            // 3. Thêm lựa chọn "Tất cả" vào đầu mỗi mảng dữ liệu động
            $vars['pearls'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $pearls_db);
            $vars['sizes'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $sizes_db);
            $vars['colors'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $colors_db);
            $vars['groups'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $groups_db);
            $vars['categorys'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $categorys_db);
            $vars['units'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $units_db);

            echo $app->render($template . '/crafting/craftingchain.html', $vars);

            // Xử lý yêu cầu POST: Lọc và trả về dữ liệu JSON cho bảng
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';

            $orderName = isset($_POST['order'][0]['column']) ? $_POST['columns'][$_POST['order'][0]['column']]['name'] : 'ingredient.id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

            // Lấy giá trị từ các bộ lọc
            $filter_type = isset($_POST['type']) ? $_POST['type'] : '';
            $filter_colors = isset($_POST['colors']) ? $_POST['colors'] : '';
            $filter_pearl = isset($_POST['pearl']) ? $_POST['pearl'] : '';
            $filter_sizes = isset($_POST['sizes']) ? $_POST['sizes'] : '';
            $filter_categorys = isset($_POST['categorys']) ? $_POST['categorys'] : '';
            $filter_group = isset($_POST['group']) ? $_POST['group'] : '';
            $filter_units = isset($_POST['units']) ? $_POST['units'] : '';
            $filter_status = isset($_POST['status']) ? $_POST['status'] : '';

            // Define joins for the query
            $joins = [
                "[>]pearl" => ["pearl" => "id"],
                "[>]sizes" => ["sizes" => "id"],
                "[>]colors" => ["colors" => "id"],
                "[>]categorys" => ["categorys" => "id"],
                "[>]products_group" => ["group" => "id"],
                "[>]units" => ["units" => "id"],
            ];

            // Khởi tạo điều kiện WHERE
            $where = [
                "AND" => [
                    "ingredient.deleted" => 0,
                    "ingredient.craftingchain[!]" => null,
                ]
            ];

            // Thêm điều kiện tìm kiếm và lọc
            if (!empty($searchValue)) {
                $where['AND']['ingredient.code[~]'] = $searchValue;
            }
            if (!empty($filter_type))
                $where['AND']['ingredient.type'] = $filter_type;
            if (!empty($filter_colors))
                $where['AND']['ingredient.colors'] = $filter_colors;
            if (!empty($filter_pearl))
                $where['AND']['ingredient.pearl'] = $filter_pearl;
            if (!empty($filter_sizes))
                $where['AND']['ingredient.sizes'] = $filter_sizes;
            if (!empty($filter_categorys))
                $where['AND']['ingredient.categorys'] = $filter_categorys;
            if (!empty($filter_group))
                $where['AND']['ingredient.group'] = $filter_group;
            if (!empty($filter_units))
                $where['AND']['ingredient.units'] = $filter_units;
            if (!empty($filter_status)) {
                $where['AND']['ingredient.status'] = $filter_status;
            } else {
                $where['AND']['ingredient.status'] = ["A", "D"];
            }
            $count = $app->count("ingredient", $joins, "ingredient.id", $where);

            // Thêm sắp xếp và phân trang vào điều kiện WHERE
            $where["ORDER"] = [$orderName => strtoupper($orderDir)];
            $where["LIMIT"] = [$start, $length];

            $datas = [];
            $totalCrafting = 0;

            $columns = [
                'ingredient.id',
                'ingredient.type',
                'ingredient.code',
                'ingredient.craftingchain',
                'ingredient.price',
                'ingredient.status',
                'pearl.name(pearl)',
                'sizes.name(sizes)',
                'colors.name(colors)',
                'units.name(unit)',
                'categorys.name(categorys)',
                'products_group.name(group)'
            ];

            $app->select("ingredient", $joins, $columns, $where, function ($data) use (&$datas, &$totalCrafting, $jatbi, $app) {
                $craftingValue = floatval($data['craftingchain']);
                if (is_numeric($craftingValue)) {
                    $totalCrafting += $craftingValue;
                }

                $datas[] = [
                    "type" => ($data['type'] == 1 ? $jatbi->lang("Đai") : ($data['type'] == 2 ? $jatbi->lang("Ngọc") : $jatbi->lang("Khác"))),
                    "code" => $data['code'],
                    "pearl" => $data['pearl'] ?? $data['categorys'],
                    "sizes" => $data['sizes'] ?? $data['group'],
                    "colors" => $data['colors'],
                    // "categorys" => $data['categorys'],
                    // "group" => $data['group'],
                    "craftingchain" => $data['craftingchain'],
                    "price" => number_format($data['price']),
                    "unit" => $data['unit'],
                    "action" => '<a href="/crafting/craftingchain-details/' . $data['id'] . '" class="pjax-load btn btn-eclo-light btn-sm border-0 py-1 px-2 rounded-3" aria-label="' . $jatbi->lang('Xem') . '"><i class="ti ti-eye"></i></a>',
                ];
            });

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas,
                "footerData" => [
                    "totalCrafting" => $totalCrafting
                ]
            ]);
        }
    })->setPermissions(['craftingchain']);

    $app->router('/{type}-details/{id}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {

        // --- PHẦN LOGIC CHUNG CHO CẢ GET VÀ POST ---
        $id = (int) ($vars['id'] ?? 0);
        $type = $vars['type'] ?? '';

        $groupCraftingId = match ($type) {
            'craftinggold' => 1,
            'craftingsilver' => 2,
            'craftingchain' => 3,
            default => null,
        };

        if ($groupCraftingId === null) {
            $app->error(404);
            return;
        }

        if ($app->method() === 'GET') {
            $data = $app->get("ingredient", [
                "[>]colors" => ["colors" => "id"],
                "[>]pearl" => ["pearl" => "id"],
                "[>]sizes" => ["sizes" => "id"],
                "[>]products_group" => ["group" => "id"],
                "[>]categorys" => ["categorys" => "id"],
                "[>]units" => ["units" => "id"],
            ], [
                "ingredient.id",
                "ingredient.code",
                "ingredient.price",
                "colors.name(color_name)",
                "pearl.name(pearl_name)",
                "sizes.name(size_name)",
                "products_group.name(group_name)",
                "categorys.name(category_name)",
                "units.name(unit_name)"
            ], ["ingredient.id" => $id]);

            if (!$data) {
                return;
            }

            $date_from = date('Y-m-d 00:00:00', strtotime('first day of this month'));
            $date_to = date('Y-m-d 23:59:59');

            $vars['date_from'] = $date_from;
            $vars['date_to'] = $date_to;

            // Truyền các biến cần thiết cho View
            $vars['data'] = $data;
            $vars['title'] = $jatbi->lang("Chi tiết nguyên liệu") . ': ' . $data['code'];
            $vars['route_type'] = $type;
            $vars['route_id'] = $id;

            echo $app->render($template . '/crafting/details.html', $vars);
        }
        // --- XỬ LÝ YÊU CẦU POST: CUNG CẤP JSON CHO DATATABLES ---
        elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            // Lấy các tham số từ DataTables
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : 10;


            // // Lấy bộ lọc ngày tháng từ dữ liệu POST (do JavaScript gửi lên)
            // $dateRange = $_POST['date'] ?? '';
            // $dateParts = explode(' - ', $dateRange);
            // $date_from = !empty($dateParts[0]) ? date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', $dateParts[0]))) : date('Y-m-01 00:00:00');
            // $date_to = !empty($dateParts[1]) ? date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', $dateParts[1]))) : date('Y-m-t 23:59:59');
            $filter_date = isset($_POST['date']) ? $_POST['date'] : '';



            $date_from = date('2021-01-01 00:00:00', strtotime('first day of this month'));
            $date_to = date('Y-m-d 23:59:59');


            if (!empty($filter_date)) {
                $date_parts = explode(' - ', $filter_date);
                if (count($date_parts) == 2) {
                    $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date_parts[0]))));
                    $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date_parts[1]))));
                }
            }
            $baseWhere = [
                "warehouses_logs.ingredient" => $id,
                "warehouses_logs.date[<>]" => [$date_from, $date_to],
                "warehouses_logs.deleted" => 0,
                "warehouses_logs.data" => 'crafting',
                "warehouses_logs.group_crafting" => $groupCraftingId,
            ];

            $joins = [
                "[>]accounts" => ["user" => "id"]
            ];

            $count = $app->count("warehouses_logs", $joins, "warehouses_logs.id", $baseWhere);

            $all_logs_for_period = $app->select("warehouses_logs", ["type", "amount"], $baseWhere);
            $totalImport = 0;
            $totalExport = 0;
            $totalPairing = 0;
            $totalCancel = 0;

            foreach ($all_logs_for_period as $log) {
                switch ($log['type']) {
                    case 'import':
                        $totalImport += $log['amount'];
                        break;
                    case 'export':
                        $totalExport += $log['amount'];
                        break;
                    case 'pairing':
                        $totalPairing = $log['amount'];
                        break;
                    case 'cancel':
                        $totalCancel += $log['amount'];
                        break;
                }
            }

            // Thêm LIMIT và ORDER để lấy dữ liệu cho trang hiện tại
            $pagedWhere = $baseWhere;
            $pagedWhere["LIMIT"] = [$start, $length];
            $pagedWhere["ORDER"] = ["warehouses_logs.date" => "DESC"];

            // Lấy dữ liệu đã phân trang
            $warehouses_logs = $app->select("warehouses_logs", $joins, [
                "warehouses_logs.warehouses",
                "warehouses_logs.type",
                "warehouses_logs.amount",
                "warehouses_logs.price",
                "warehouses_logs.notes",
                "warehouses_logs.date",
                "accounts.name(user_name)"
            ], $pagedWhere);

            // Định dạng lại dữ liệu cho DataTables
            $output_data = [];
            foreach ($warehouses_logs as $log) {
                $output_data[] = [
                    "id" => '<a class="modal-url" data-url="/crafting/crafting-history-views/' . $log['warehouses'] . '/">#' . $log['warehouses'] . '</a>',
                    "import" => $log['type'] == 'import' ? $log['amount'] : '',
                    "export" => ($log['type'] == 'export' || $log['type'] == 'pairing') ? $log['amount'] : '',
                    "cancel" => $log['type'] == 'cancel' ? $log['amount'] : '',
                    "price" => number_format($log['price']),
                    "notes" => $log['notes'],
                    "date" => date('d/m/Y', strtotime($log['date'])),
                    "user" => $log['user_name'] ?? 'N/A'
                ];
            }

            // Trả về kết quả JSON
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $output_data,
                "footerData" => [
                    "totalImport" => number_format($totalImport),
                    "totalExport" => number_format($totalExport),
                    "totalPairing" => number_format($totalPairing),
                    "totalCancel" => number_format($totalCancel),
                ],
            ]);
        }
    });

    $app->router('/goldsmithing', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {
        // Xử lý yêu cầu GET: Lấy dữ liệu cho các bộ lọc và hiển thị trang
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Chế tác sản phẩm");

            $groups_db = $app->select("products_group", ['id(value)', 'name(text)'], ['deleted' => 0]);
            $categorys_db = $app->select("categorys", ['id(value)', 'name(text)'], ['deleted' => 0]);
            $units_db = $app->select("units", ['id(value)', 'name(text)'], ['deleted' => 0]);

            // 2. Dữ liệu tĩnh cho bộ lọc "Loại Chế Tác"
            $vars['crafting_types'] = [
                ['value' => '', 'text' => $jatbi->lang('Tất cả')],
                ['value' => '1', 'text' => 'Chế tác vàng'],
                ['value' => '2', 'text' => 'Chế tác bạc'],
                ['value' => '3', 'text' => 'Chế tác chuỗi'],
            ];

            // 3. Thêm lựa chọn "Tất cả" vào đầu mỗi mảng dữ liệu động
            $vars['groups'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $groups_db);
            $vars['categorys'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $categorys_db);
            $vars['units'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $units_db);

            echo $app->render($template . '/crafting/goldsmithing.html', $vars);

            // Xử lý yêu cầu POST: Lọc và trả về dữ liệu JSON cho bảng
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';

            $orderName = isset($_POST['order'][0]['column']) ? $_POST['columns'][$_POST['order'][0]['column']]['name'] : 'crafting.date';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

            // Lấy giá trị từ các bộ lọc
            $filter_categorys = isset($_POST['categorys']) ? $_POST['categorys'] : '';
            $filter_group = isset($_POST['group']) ? $_POST['group'] : '';
            $filter_units = isset($_POST['units']) ? $_POST['units'] : '';

            // Define joins for the query
            $joins = [
                "[>]units" => ["units" => "id"],
                "[>]categorys" => ["categorys" => "id"],
                "[>]products_group" => ["group" => "id"]
            ];

            // Khởi tạo điều kiện WHERE
            $where = [
                "AND" => [
                    "crafting.deleted" => 0,
                    "crafting.type" => [0, 1],
                    "crafting.group_crafting" => 1,

                ]
            ];

            // Thêm điều kiện tìm kiếm
            if (!empty($searchValue)) {
                $where['AND']['OR'] = [
                    'crafting.name[~]' => $searchValue,
                    'crafting.code[~]' => $searchValue,
                ];
            }


            // Lọc theo danh mục
            if (!empty($filter_categorys)) {
                $where['AND']['crafting.categorys'] = $filter_categorys;
            }
            // Lọc theo nhóm sản phẩm
            if (!empty($filter_group)) {
                $where['AND']['crafting.group'] = $filter_group;
            }
            // Lọc theo đơn vị
            if (!empty($filter_units)) {
                $where['AND']['crafting.units'] = $filter_units;
            }

            // Đếm tổng số bản ghi với điều kiện lọc (trước khi thêm LIMIT và ORDER)
            $count = $app->count("crafting", $joins, "crafting.id", $where);

            // Thêm sắp xếp và phân trang vào điều kiện WHERE cho truy vấn lấy dữ liệu
            $where["ORDER"] = [$orderName => strtoupper($orderDir)];
            $where["LIMIT"] = [$start, $length];

            $datas = [];
            $totalCrafting = 0;
            $priceCrafting = 0;

            $columns = [
                'crafting.id',
                'crafting.code',
                'crafting.name',
                'crafting.price',
                'crafting.amount_total',
                'crafting.group_crafting',
                'units.name(units)',
                'categorys.name(categorys)',
                'products_group.name(group)'
            ];

            $app->select("crafting", $joins, $columns, $where, function ($data) use (&$datas, &$priceCrafting, &$totalCrafting, $app, $jatbi) {
                // Price calculation
                $craftingValue = floatval($data['price']);
                if (is_numeric($craftingValue)) {
                    $priceCrafting += $craftingValue;
                }
                // Amount calculation
                $craftingValue2 = floatval($data['amount_total']);
                if (is_numeric($craftingValue2)) {
                    $totalCrafting += $craftingValue2;
                }
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "code" => $data['code'],
                    "name" => strtolower($data['name'] ?? ''),
                    'price' => number_format($data['price']),
                    'amount_total' => number_format($data['amount_total']),
                    "units" => $data['units'],
                    "categorys" => $data['categorys'],
                    "group" => $data['group'],
                    "group_crafting" => match (intval($data['group_crafting'])) {
                        1 => $jatbi->lang("Chế tác vàng"),
                        2 => $jatbi->lang("Chế tác bạc"),
                        3 => $jatbi->lang("Chế tác chuỗi"),
                        default => $jatbi->lang(""),
                    },
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'action' => ['data-url' => 'crafting/chainmaking-edit/' . $data['id'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xem"),
                                'action' => ['data-url' => '/crafting/pairing-views/' . $data['id'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'link',
                                'name' => 'Xem lịch sử',
                                'action' => [
                                    'href' => '/crafting/history-crafting/' . $data['id']
                                ]
                            ]
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
                    "sum_price" => number_format($priceCrafting),
                    "sum_soluong" => number_format($totalCrafting),
                ]
            ]);
        }
    })->setPermissions(['goldsmithing']);

    $app->router('/silversmithing', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {
        // Xử lý yêu cầu GET: Lấy dữ liệu cho các bộ lọc và hiển thị trang
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Chế tác bạc");

            $groups_db = $app->select("products_group", ['id(value)', 'name(text)'], ['deleted' => 0]);
            $categorys_db = $app->select("categorys", ['id(value)', 'name(text)'], ['deleted' => 0]);
            $units_db = $app->select("units", ['id(value)', 'name(text)'], ['deleted' => 0]);

            // 2. Dữ liệu tĩnh cho bộ lọc "Loại Chế Tác"
            $vars['crafting_types'] = [
                ['value' => '', 'text' => $jatbi->lang('Tất cả')],
                ['value' => '1', 'text' => 'Chế tác vàng'],
                ['value' => '2', 'text' => 'Chế tác bạc'],
                ['value' => '3', 'text' => 'Chế tác chuỗi'],
            ];

            // 3. Thêm lựa chọn "Tất cả" vào đầu mỗi mảng dữ liệu động
            $vars['groups'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $groups_db);
            $vars['categorys'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $categorys_db);
            $vars['units'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $units_db);

            echo $app->render($template . '/crafting/silversmithing.html', $vars);

            // Xử lý yêu cầu POST: Lọc và trả về dữ liệu JSON cho bảng
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';

            $orderName = isset($_POST['order'][0]['column']) ? $_POST['columns'][$_POST['order'][0]['column']]['name'] : 'crafting.date';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

            // Lấy giá trị từ các bộ lọc
            $filter_categorys = isset($_POST['categorys']) ? $_POST['categorys'] : '';
            $filter_group = isset($_POST['group']) ? $_POST['group'] : '';
            $filter_units = isset($_POST['units']) ? $_POST['units'] : '';
            // Define joins for the query
            $joins = [
                "[>]units" => ["units" => "id"],
                "[>]categorys" => ["categorys" => "id"],
                "[>]products_group" => ["group" => "id"]
            ];

            // Khởi tạo điều kiện WHERE chỉ với các bộ lọc
            $where = [
                "AND" => [
                    "crafting.deleted" => 0,
                    "crafting.type" => [0, 1],
                    "crafting.group_crafting" => 2,

                ]
            ];

            // Thêm điều kiện tìm kiếm
            if (!empty($searchValue)) {
                $where['AND']['OR'] = [
                    'crafting.name[~]' => $searchValue,
                    'crafting.code[~]' => $searchValue,
                ];
            }

            // Lọc theo danh mục
            if (!empty($filter_categorys)) {
                $where['AND']['crafting.categorys'] = $filter_categorys;
            }
            // Lọc theo nhóm sản phẩm
            if (!empty($filter_group)) {
                $where['AND']['crafting.group'] = $filter_group;
            }
            // Lọc theo đơn vị
            if (!empty($filter_units)) {
                $where['AND']['crafting.units'] = $filter_units;
            }

            // Đếm tổng số bản ghi với điều kiện lọc (trước khi thêm LIMIT và ORDER)
            $count = $app->count("crafting", $joins, "crafting.id", $where);

            // Thêm sắp xếp và phân trang vào điều kiện WHERE cho truy vấn lấy dữ liệu
            $where["ORDER"] = [$orderName => strtoupper($orderDir)];
            $where["LIMIT"] = [$start, $length];

            $datas = [];
            $totalCrafting = 0;
            $priceCrafting = 0;

            $columns = [
                'crafting.id',
                'crafting.code',
                'crafting.name',
                'crafting.price',
                'crafting.amount_total',
                'crafting.group_crafting',
                'units.name(units)',
                'categorys.name(categorys)',
                'products_group.name(group)'
            ];

            $app->select("crafting", $joins, $columns, $where, function ($data) use (&$datas, &$priceCrafting, &$totalCrafting, $app, $jatbi) {
                // Price calculation
                $craftingValue = floatval($data['price']);
                if (is_numeric($craftingValue)) {
                    $priceCrafting += $craftingValue;
                }
                // Amount calculation
                $craftingValue2 = floatval($data['amount_total']);
                if (is_numeric($craftingValue2)) {
                    $totalCrafting += $craftingValue2;
                }
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "code" => $data['code'],
                    "name" => $data['name'],
                    'price' => number_format($data['price']),
                    'amount_total' => number_format($data['amount_total']),
                    "units" => $data['units'],
                    "categorys" => $data['categorys'],
                    "group" => $data['group'],
                    "group_crafting" => match (intval($data['group_crafting'])) {
                        1 => $jatbi->lang("Chế tác vàng"),
                        2 => $jatbi->lang("Chế tác bạc"),
                        3 => $jatbi->lang("Chế tác chuỗi"),
                        default => $jatbi->lang(""),
                    },
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'action' => ['data-url' => 'crafting/chainmaking-edit/' . $data['id'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xem"),
                                'action' => ['data-url' => '/crafting/pairing-views/' . $data['id'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'link',
                                'name' => 'Xem lịch sử',
                                'action' => [
                                    'href' => '/crafting/history-crafting/' . $data['id']
                                ]
                            ]
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
                    "sum_price" => number_format($priceCrafting),
                    "sum_soluong" => number_format($totalCrafting),
                ]
            ]);
        }
    })->setPermissions(['silversmithing']);

    $app->router('/chainmaking', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {
        // Xử lý yêu cầu GET: Lấy dữ liệu cho các bộ lọc và hiển thị trang
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Chế tác chuỗi");

            $groups_db = $app->select("products_group", ['id(value)', 'name(text)'], ['deleted' => 0]);
            $categorys_db = $app->select("categorys", ['id(value)', 'name(text)'], ['deleted' => 0]);
            $units_db = $app->select("units", ['id(value)', 'name(text)'], ['deleted' => 0]);

            // 2. Dữ liệu tĩnh cho bộ lọc "Loại Chế Tác"
            $vars['crafting_types'] = [
                ['value' => '', 'text' => $jatbi->lang('Tất cả')],
                ['value' => '1', 'text' => 'Chế tác vàng'],
                ['value' => '2', 'text' => 'Chế tác bạc'],
                ['value' => '3', 'text' => 'Chế tác chuỗi'],
            ];

            // 3. Thêm lựa chọn "Tất cả" vào đầu mỗi mảng dữ liệu động
            $vars['groups'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $groups_db);
            $vars['categorys'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $categorys_db);
            $vars['units'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $units_db);

            echo $app->render($template . '/crafting/chainmaking.html', $vars);

            // Xử lý yêu cầu POST: Lọc và trả về dữ liệu JSON cho bảng
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            // --- Lấy các tham số của DataTables ---
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'crafting.date';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

            $filter_categorys = isset($_POST['category']) ? $_POST['category'] : '';
            $filter_group = isset($_POST['group_product']) ? $_POST['group_product'] : '';
            $filter_units = isset($_POST['units']) ? $_POST['units'] : '';
            // --- Xây dựng điều kiện WHERE ---
            $where = [
                "AND" => [
                    "crafting.deleted" => 0,
                    "crafting.type" => [0, 1],
                    "crafting.group_crafting" => 3,
                ],
            ];

            // Thêm điều kiện tìm kiếm
            if (!empty($searchValue)) {
                $where['AND']['OR'] = [
                    'crafting.name[~]' => $searchValue,
                    'crafting.code[~]' => $searchValue,
                ];
            }

            // Thêm điều kiện từ các bộ lọc
            if (!empty($filter_categorys)) {
                $where['AND']['crafting.categorys'] = $filter_categorys;
            }
            if (!empty($filter_group)) {
                $where['AND']['crafting.group'] = $filter_group;
            }
            if (!empty($filter_units)) {
                $where['AND']['crafting.units'] = $filter_units;
            }

            $joins = [
                "[>]units" => ["units" => "id"],
                "[>]categorys" => ["categorys" => "id"],
                "[>]products_group" => ["group" => "id"]
            ];

            // --- Đếm tổng số bản ghi (recordsFiltered) ---
            // Câu lệnh count không cần LIMIT và ORDER
            $count = $app->count("crafting", $joins, "crafting.id", $where);

            // --- Thêm LIMIT và ORDER để lấy dữ liệu cho trang hiện tại ---
            $where["ORDER"] = [$orderName => strtoupper($orderDir)];
            $where["LIMIT"] = [$start, $length];

            // --- Lấy dữ liệu ---
            $datas = [];
            $priceCrafting = 0;
            $totalCrafting = 0;

            $columns = [
                'crafting.id',
                'crafting.code',
                'crafting.name',
                'crafting.price',
                'crafting.amount_total',
                'crafting.group_crafting',
                'units.name(units)',
                'categorys.name(categorys)',
                'products_group.name(group)'
            ];

            // Dùng select thay cho callback để code gọn hơn
            $results = $app->select("crafting", $joins, $columns, $where);

            foreach ($results as $data) {
                $priceCrafting += floatval($data['price']);
                $totalCrafting += floatval($data['amount_total']);
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "code" => $data['code'],
                    "name" => $data['name'],
                    'price' => number_format($data['price']),
                    'amount_total' => number_format($data['amount_total']),
                    "units" => $data['units'],
                    "categorys" => $data['categorys'],
                    "group" => $data['group'],
                    "group_crafting" => match (intval($data['group_crafting'])) {
                        1 => $jatbi->lang("Chế tác vàng"),
                        2 => $jatbi->lang("Chế tác bạc"),
                        3 => $jatbi->lang("Chế tác chuỗi"),
                        default => '',
                    },
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'action' => ['data-url' => 'crafting/chainmaking-edit/' . $data['id'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xem"),
                                'action' => ['data-url' => '/crafting/pairing-views/' . $data['id'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'link',
                                'name' => 'Xem lịch sử',
                                'action' => [
                                    'href' => '/crafting/history-crafting/' . $data['id']
                                ]
                            ]
                        ]
                    ]),
                ];
            }

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas,
                "footerData" => [
                    "sum_price" => number_format($priceCrafting),
                    "sum_soluong" => number_format($totalCrafting),
                ]
            ]);
        }
    })->setPermissions(['chainmaking']);

    // view
    $app->router('/pairing-views/{id}', 'GET', function ($vars) use ($app, $jatbi, $setting, $template) {
        $id = (int) ($vars['id'] ?? 0);

        // 1. Lấy dữ liệu chính của phiếu chế tác và các tên liên quan trong 1 câu lệnh
        $data = $app->get("crafting", [
            "[>]accounts" => ["user" => "id"],
            "[>]categorys" => ["categorys" => "id"],
            "[>]products_group" => ["group" => "id"],
        ], [
            "crafting.id",
            "crafting.code",
            "crafting.date",
            "crafting.amount_total",
            "crafting.price",
            "crafting.content",
            "accounts.name(user_name)",
            "categorys.name(category_name)",
            "products_group.name(group_name)"
        ], ["crafting.id" => $id]);

        // 2. Xử lý nếu không tìm thấy phiếu
        if (!$data) {
            $app->error(404);
            return;
        }

        // 3. Lấy TOÀN BỘ chi tiết và thông tin nguyên liệu liên quan chỉ bằng MỘT câu lệnh JOIN
        $details = $app->select("crafting_details", [
            "[>]ingredient" => ["ingredient" => "id"],
            "[>]units" => ["ingredient.units" => "id"],
            "[>]pearl" => ["ingredient.pearl" => "id"],
            "[>]colors" => ["ingredient.colors" => "id"],
            "[>]sizes" => ["ingredient.sizes" => "id"],
            "[>]categorys" => ["ingredient.categorys" => "id"],
            "[>]products_group" => ["ingredient.group" => "id"]
        ], [
            "crafting_details.amount",
            "crafting_details.price",
            "ingredient.code(ingredient_code)",
            "ingredient.type(ingredient_type)",
            "units.name(unit_name)",
            "pearl.name(pearl_name)",
            "colors.name(color_name)",
            "sizes.name(size_name)",
            "categorys.name(ingredient_category_name)",
            "products_group.name(ingredient_group_name)"
        ], [
            "crafting_details.crafting" => $data['id'],
            "crafting_details.deleted" => 0
        ]);

        // 4. Chuẩn bị tất cả dữ liệu và truyền cho view
        $vars['data'] = $data;
        $vars['details'] = $details;
        $vars['title'] = $jatbi->lang("Chế tác") . ' #' . ($data['code'] ?? '');

        // 5. Gọi file view để hiển thị
        echo $app->render($template . '/crafting/pairing-views.html', $vars, $jatbi->ajax());
    })->setPermissions(['chainmaking']);

    $app->router('/fixed', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Kho sửa chế tác");

            $groups_db = $app->select("products_group", ['id(value)', 'name(text)'], ['deleted' => 0]);
            $categorys_db = $app->select("categorys", ['id(value)', 'name(text)'], ['deleted' => 0]);
            $units_db = $app->select("units", ['id(value)', 'name(text)'], ['deleted' => 0]);

            // 2. Dữ liệu tĩnh cho bộ lọc "Loại Chế Tác"
            $vars['crafting_types'] = [
                ['value' => '', 'text' => $jatbi->lang('Tất cả')],
                ['value' => '1', 'text' => 'Chế tác vàng'],
                ['value' => '2', 'text' => 'Chế tác bạc'],
                ['value' => '3', 'text' => 'Chế tác chuỗi'],
            ];

            // 3. Thêm lựa chọn "Tất cả" vào đầu mỗi mảng dữ liệu động
            $vars['groups'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $groups_db);
            $vars['categorys'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $categorys_db);
            $vars['units'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $units_db);

            echo $app->render($template . '/crafting/fixed.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';

            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'remove_products.id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

            $filter_category = isset($_POST['category']) ? $_POST['category'] : '';
            $filter_unit = isset($_POST['units']) ? $_POST['units'] : '';
            $filter_group_product = isset($_POST['group_product']) ? $_POST['group_product'] : '';


            // Define joins for the query
            $joins = [
                "[>]products" => ["products" => "id"],
                "[>]categorys" => ["category" => "id"],
                "[>]units" => ["unit" => "id"],
                "[>]products_group" => ["group_product" => "id"]
            ];

            $where = [
                "AND" => [
                    "remove_products.amount[>]" => 0,
                ],
                "ORDER" => [$orderName => strtoupper($orderDir)],
                "LIMIT" => [$start, $length]
            ];

            if (!empty($searchValue)) {
                $where['AND']['OR'] = [
                    "products.name[~]" => $searchValue,
                    "products.code[~]" => $searchValue,
                ];
            }

            if (!empty($filter_category)) {
                $where['AND']['remove_products.category'] = $filter_category;
            }
            if (!empty($filter_unit)) {
                $where['AND']['remove_products.unit'] = $filter_unit;
            }
            if (!empty($filter_group_product)) {
                $where['AND']['remove_products.group_product'] = $filter_group_product;
            }

            $countWhere = $where['AND'];
            $count = $app->count("remove_products", $joins, "remove_products.id", ["AND" => $countWhere]);

            $datas = [];
            $totalCrafting = 0;
            $priceCrafting = 0;

            // Define columns to select
            $columns = [
                'remove_products.id',
                'remove_products.price',
                'remove_products.amount',
                'products.active(product_active)',
                'products.name(name)',
                'products.code(code)',
                'categorys.name(category)',
                'units.name(unit)',
                'products_group.name(group_product)'
            ];

            $app->select("remove_products", $joins, $columns, $where, function ($data) use (&$datas, &$priceCrafting, &$totalCrafting, $app, $jatbi) {
                // Price calculation
                $priceValue = floatval($data['price']);
                if (is_numeric($priceValue)) {
                    $priceCrafting += $priceValue;
                } else {
                    error_log("Invalid price value for ID: " . $data['id'] . ", value: " . $data['price']);
                }
                // Amount calculation
                $amountValue = floatval($data['amount']);
                if (is_numeric($amountValue)) {
                    $totalCrafting += $amountValue;
                } else {
                    error_log("Invalid amount value for ID: " . $data['id'] . ", value: " . $data['amount']);
                }
                $qr_url = "https://chart.googleapis.com/chart?chs=100x100&cht=qr&chl=" . urlencode($data['product_active']) . "&choe=UTF-8";
                $img_tag = '<img src="' . $qr_url . '" class="w-100 rounded-3">';
                $datas[] = [
                    "img" => $img_tag ?? "",
                    "code" => $data['code'] ?? '',
                    "name" => strtolower($data['name'] ?? ''),
                    'price' => number_format($data['price'] ?? 0),
                    "amount" => number_format($data['amount'] ?? 0),
                    "categorys" => $data['category'] ?? "",
                    "units" => $data['unit'] ?? "",
                    "group_product" => $data['group_product'] ?? '',
                    "action" => '<button data-action="modal" data-url="/crafting/fixed-views/' . $data['id'] . '" class="btn btn-eclo-light btn-sm border-0 py-1 px-2 rounded-3" aria-label="' . $jatbi->lang('Xem') . '"><i class="ti ti-eye"></i></button>',
                ];
            });

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas,
                "footerData" => [
                    "sum_price" => number_format($priceCrafting),
                    "sum_soluong" => number_format($totalCrafting),
                ]
            ]);
        }
    })->setPermissions(['fixed']);

    $app->router('/fixed-views/{id}', 'GET', function ($vars) use ($app, $jatbi, $template) {
        $id = $vars['id'] ?? 0;

        $data = $app->get("remove_products", [
            "[>]products" => ["products" => "id"],
            "[>]categorys" => ["products.categorys" => "id"],
            "[>]products_group" => ["products.group" => "id"]
        ], [
            "remove_products.id",
            "remove_products.date",
            "remove_products.amount",
            "remove_products.price",
            "products.code(product_code)",
            "products.id(product_id)",
            "products.name",
            "categorys.name(category_name)",
            "products_group.name(group_name)"
        ], ["remove_products.id" => $id]);

        if (!$data) {
            $app->error(404);
            return;
        }

        $details = $app->select("products_details", [
            "[>]ingredient" => ["ingredient" => "id"],
            "[>]pearl" => ["ingredient.pearl" => "id"],
            "[>]sizes" => ["ingredient.sizes" => "id"],
            "[>]colors" => ["ingredient.colors" => "id"],
            "[>]categorys" => ["ingredient.categorys" => "id"],
            "[>]products_group" => ["ingredient.group" => "id"],
            "[>]units" => ["ingredient.units" => "id"],
        ], [
            "products_details.amount",
            "products_details.price",
            "products_details.total",
            // "products_details.notes",
            "ingredient.code(ingredient_code)",
            "ingredient.type(ingredient_type)",
            "pearl.name(pearl_name)",
            "sizes.name(size_name)",
            "colors.name(color_name)",
            "categorys.name(category_name)",
            "products_group.name(group_name)",
            "units.name(unit_name)"
        ], [
            "products_details.products" => $data['product_id'],
            "products_details.deleted" => 0
        ]);

        // 3. Truyền tất cả dữ liệu đã xử lý sang view
        $vars['data'] = $data;
        $vars['details'] = $details;
        $vars['title'] = $jatbi->lang("Sản phẩm") . ' #' . ($data['name'] ?? '');

        echo $app->render($template . '/crafting/fixed-views.html', $vars, $jatbi->ajax());
    })->setPermissions(['fixed']);

    $app->router('/history-crafting/{id}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $setting) {
        $id = $vars['id'];

        $crafting_item = $app->get("crafting", ["id", "name"], ["id" => $id]);

        if (!$crafting_item) {
            $app->error(404);
            return;
        }

        $vars['title'] = $jatbi->lang("Lịch sử chế tác");
        $vars['crafting_id'] = $id;

        if ($app->method() === 'GET') {
            $accounts_db = $app->select("accounts", ['id(value)', 'name(text)'], ['deleted' => 0, 'status' => 'A']);
            $vars['accountsList'] = array_merge([['value' => '', 'text' => $jatbi->lang('Tất cả')]], $accounts_db);
            echo $app->render($template . '/crafting/history-crafting.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            $draw = intval($_POST['draw'] ?? 0);
            $start = intval($_POST['start'] ?? 0);
            $length = intval($_POST['length'] ?? 10);
            $searchValue = $_POST['search']['value'] ?? '';
            $filter_user = $_POST['user'] ?? '';
            $filter_date = $_POST['date'] ?? '';

            $date_from = date('2021-01-01 00:00:00');
            $date_to = date('Y-m-d 23:59:59');
            if (!empty($filter_date)) {
                $dates = explode(' - ', $filter_date);
                $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', $dates[0])));
                $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', $dates[1])));
            }

            $where = [
                "AND" => [
                    "OR" => [
                        'code[~]' => $searchValue,
                        'name[~]' => $searchValue,
                    ],
                    "crafting" => $id,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => ['id' => 'DESC']
            ];

            if ($filter_user !== '') {
                $where['AND']['user'] = $filter_user;
            }
            if (!empty($filter_date)) {
                $dates = explode(' - ', $filter_date);
                $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', $dates[0])));
                $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', $dates[1])));
                $where['AND']['date[<>]'] = [$date_from, $date_to];
            }

            $count = $app->count("history_crafting", [
                "AND" => $where['AND']
            ]);

            $datas = [];
            $app->select("history_crafting", [
                'id',
                'code',
                'name',
                'price',
                'amount',
                'unit',
                'user',
                'date'
            ], $where, function ($data) use (&$datas, $jatbi, $app, $setting) {
                $unit_name = $app->get("units", "name", ["id" => $data['unit']]);
                $user_name = $app->get("accounts", "name", ["id" => $data['user']]);

                $datas[] = [
                    "code" => $data['code'],
                    "name" => $data['name'],
                    "price" => number_format($data['price']),
                    "amount" => $data['amount'],
                    "unit" => $unit_name,
                    "date" => $jatbi->datetime($data['date']),
                    "action" => '<button data-action="modal" data-url="/crafting/history-crafting-ingredient/' . $data['id'] . '" class="btn btn-eclo-light btn-sm border-0 py-1 px-2 rounded-3" aria-label="' . $jatbi->lang('Xem') . '"><i class="ti ti-eye"></i></button>',

                ];
            });

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas
            ]);
        }
    });

    $app->router('/history-crafting-ingredient/{id}', 'GET', function ($vars) use ($app, $jatbi, $template) {
        $id = $vars['id'] ?? 0;

        $data = $app->get("history_crafting", "*", ["id" => $id]);


        if (!$data) {
            header("HTTP/1.0 404 Not Found");
            exit;
        }

        $details = $app->select(
            "history_crafting_ingredient",
            [
                "[>]ingredient" => ["ingredient" => "id"],
                "[>]products_group" => ["ingredient.group" => "id"],
                "[>]categorys" => ["ingredient.categorys" => "id"],
                "[>]pearl" => ["ingredient.pearl" => "id"],
                "[>]colors" => ["ingredient.colors" => "id"],
                "[>]sizes" => ["ingredient.sizes" => "id"],
                "[>]units" => ["ingredient.units" => "id"],
            ],
            [
                "history_crafting_ingredient.amount",
                "history_crafting_ingredient.price",
                "ingredient.code",
                "ingredient.type",
                "products_group.name(group_name)",
                "categorys.name(category_name)",
                "pearl.name(pearl_name)",
                "colors.name(color_name)",
                "sizes.name(size_name)",
                "units.name(unit_name)",
            ],
            ["history_crafting_ingredient.history_crafting" => $data["id"]]
        );

        // Gửi dữ liệu đã xử lý qua view
        $vars['data'] = $data;
        $vars['details'] = $details;

        echo $app->render($template . '/crafting/history-crafting-ingredient.html', $vars, $jatbi->ajax());
    });

    $app->router('/pairing-import/{type}', 'GET', function ($vars) use ($app, $jatbi, $template) {
        $router[2] = $vars['type'];
        $action = 'add';
        $nguyenlieu = [];
        $total_price = 0;
        $total_cost = 0;
        if ($router['2'] == 'gold') {
            if (($_SESSION['pairing'][$action]['group_crafting'] ?? null) != 1) {
                unset($_SESSION['pairing'][$action]);
                $_SESSION['pairing'][$action]['group_crafting'] = 1;
            }
            $nguyenlieu = $app->select("ingredient", ["id", "code", "type"], ["deleted" => 0, "status" => "A", "crafting[>]" => 0]);
        }
        if ($router['2'] == 'silver') {
            if (($_SESSION['pairing'][$action]['group_crafting'] ?? null) != 2) {
                unset($_SESSION['pairing'][$action]);
                $_SESSION['pairing'][$action]['group_crafting'] = 2;
            }
            $nguyenlieu = $app->select("ingredient", ["id", "code", "type"], ["deleted" => 0, "status" => "A", "craftingsilver[>]" => 0]);
        }
        if ($router['2'] == 'chain') {
            if (($_SESSION['pairing'][$action]['group_crafting'] ?? null) != 3) {
                unset($_SESSION['pairing'][$action]);
                $_SESSION['pairing'][$action]['group_crafting'] = 3;
            }
            $nguyenlieu = $app->select("ingredient", ["id", "code", "type"], ["deleted" => 0, "status" => "A", "craftingchain[>]" => 0]);
        }
        if (!isset($_SESSION['pairing'][$action]['date'])) {
            $_SESSION['pairing'][$action]['date'] = date("Y-m-d H:i:s");
        }
        $_SESSION['pairing'][$action]['stores']['id'] = 0;
        if (!isset($_SESSION['pairing'][$action]['amount'])) {
            $_SESSION['pairing'][$action]['amount'] = 1;
        }
        foreach (($_SESSION['pairing'][$action]['ingredient'] ?? []) as $key => $getprice) {
            $total_price += (float) $getprice['amount'] * (float) $getprice['price'];
            $total_cost += (float) $getprice['amount'] * (float) $getprice['cost'];
        }
        $_SESSION['pairing'][$action]['price'] = $total_price;
        $_SESSION['pairing'][$action]['cost'] = $total_cost;
        $data = [
            "group_crafting" => $_SESSION['pairing'][$action]['group_crafting'] ?? "",
            "date" => $_SESSION['pairing'][$action]['date'] ?? "",
            "code" => $_SESSION['pairing'][$action]['code'] ?? "",
            "content" => $_SESSION['pairing'][$action]['content'] ?? "",
            "price" => $_SESSION['pairing'][$action]['price'] ?? "",
            "cost" => $_SESSION['pairing'][$action]['cost'] ?? 0,
            "amount" => $_SESSION['pairing'][$action]['amount'] ?? 1,
            "name" => $_SESSION['pairing'][$action]['name'] ?? "",
            "categorys" => $_SESSION['pairing'][$action]['categorys'] ?? "",
            "group" => $_SESSION['pairing'][$action]['group'] ?? "",
            "default_code" => $_SESSION['pairing'][$action]['default_code'] ?? "",
            "units" => $_SESSION['pairing'][$action]['units'] ?? "",
            "personnels" => $_SESSION['pairing'][$action]['personnels'] ?? "",
        ];
        $SelectProducts = $_SESSION['pairing'][$action]['ingredient'] ?? [];
        $vars['title'] = $jatbi->lang("Nhập liệu chế tác");
        $options = [];
        $options[] = [
            "value" => "",
            "text" => "Chọn nguyên liệu"
        ];
        foreach ($nguyenlieu as $row) {
            $typeName = ($row['type'] == 1) ? $jatbi->lang('Đai')
                : (($row['type'] == 2) ? $jatbi->lang('Ngọc') : 'Khác');

            $options[] = [
                "value" => $row['id'],
                "text" => $row['code'] . " - " . $typeName
            ];
        }
        $vars['nguyenlieu'] = $options;
        $vars['data'] = $data;
        $vars['SelectProducts'] = $SelectProducts;
        $vars['action'] = $action;
        $groups = $app->select("products_group", ['id(value)', 'code', 'name (text)'], ["deleted" => 0, "status" => 'A']);
        $vars['groups'] = array_merge([['value' => '', 'text' => $jatbi->lang('Chọn nhóm sản phẩm')]], $groups);
        $categorys = $app->select("categorys", ['id(value)', 'code', 'name (text)'], ["deleted" => 0, "status" => 'A']);
        $vars['categorys'] = array_merge([['value' => '', 'text' => $jatbi->lang('Chọn danh mục')]], $categorys);
        $default_codes = $app->select("default_code", ['id(value)', 'code', 'name (text)'], ["deleted" => 0, "status" => 'A']);
        $vars['default_codes'] = array_merge([['value' => '', 'text' => $jatbi->lang('Chọn mã')]], $default_codes);
        $units = $app->select("units", ['id(value)', 'name(text)'], ["deleted" => 0, "status" => 'A']);
        $vars['units'] = array_merge([['value' => '', 'text' => $jatbi->lang('Chọn đơn vị tính')]], $units);
        $personnels = $app->select("personnels", ['id(value)', 'code', 'name (text)'], ["deleted" => 0, "status" => 'A']);
        $vars['personnels'] = array_merge([['value' => '', 'text' => $jatbi->lang('Chọn nhân viên')]], $personnels);
        echo $app->render($template . '/crafting/pairing-import.html', $vars);
    })->setPermissions(['crafting.add']);

    $app->router('/pairing-ingredient/{action}', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $action = $vars['action'] ?? 'add';
        $do = $_POST['do'] ?? ''; // 'add', 'update_amount', 'delete'
        $sessionData = &$_SESSION['pairing'][$action]; // Dùng tham chiếu cho gọn

        switch ($do) {
            case 'add':
                $id = $_POST['ingredient_id'] ?? 0;
                $data = $app->get("ingredient", "*", ["id" => $id, "deleted" => 0]);
                if (!$data) {
                    echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Nguyên liệu không tồn tại')]);
                    return;
                }
                // Logic kiểm tra tồn kho...
                // Logic thêm mới hoặc tăng số lượng vào session...
                // $sessionData['ingredient'][...] = ...
                // $sessionData['code'] = trim($jatbi->getcode($sessionData['ingredient'])); // Tính lại code
                echo json_encode(['status' => 'success', 'content' => 'Thêm nguyên liệu thành công', 'reload' => true]);
                break;

            case 'update_amount':
                $key = $_POST['key'] ?? '';
                $amount = $_POST['amount'] ?? 0;
                if (isset($sessionData['ingredient'][$key])) {
                    // ... logic kiểm tra tồn kho chi tiết ...
                    $sessionData['ingredient'][$key]['amount'] = $amount;
                    echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công', 'reload' => true]);
                }
                break;

            case 'delete':
                $key = $_POST['key'] ?? '';
                if (isset($sessionData['ingredient'][$key])) {
                    unset($sessionData['ingredient'][$key]);
                    // $sessionData['code'] = trim($jatbi->getcode($sessionData['ingredient'])); // Tính lại code
                    echo json_encode(['status' => 'success', 'content' => 'Xóa thành công', 'reload' => true]);
                }
                break;
        }
    })->setPermissions(['crafting.add']);

    $app->router('/pairing-action/{action}', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $action = $vars['action'] ?? 'add';
        $do = $_POST['do'] ?? '';

        if ($do === 'cancel') {
            unset($_SESSION['pairing'][$action]);
            echo json_encode(['status' => 'success', 'content' => 'Đã hủy thao tác']);
            return;
        }

        if ($do === 'complete') {
            $data = $_SESSION['pairing'][$action] ?? [];

            // --- BẮT ĐẦU KHỐI LOGIC "HOÀN TẤT" TỪ CODE CŨ CỦA BẠN ---

            // 1. Validation cuối cùng
            if (empty($data['name']) || empty($data['code']) || empty($data['ingredient']) /*... các điều kiện khác ...*/) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Vui lòng điền đủ thông tin')]);
                return;
            }

            // 2. Kiểm tra sản phẩm đã có (với giá khác)
            $check = $app->count("crafting", "id", ["code" => $data['code'], "price[!]" => $data['price'], "deleted" => 0, "group_crafting" => $data["group_crafting"]]);
            if ($check > 0) {
                echo json_encode(['status' => 'error', 'content' => 'Mã sản phẩm này đã có nhưng số tiền không đồng bộ...']);
                return;
            }

            // 3. Logic thêm mới hoặc cập nhật "crafting"
            $check_crafting = $app->get("crafting", "*", ["code" => $data['code'], "price" => $data['price'], "deleted" => 0, "group_crafting" => $data["group_crafting"]]);
            if ($check_crafting) {
                // Cập nhật số lượng
                $app->update("crafting", ["amount[+]" => $data['amount'], "amount_total[+]" => $data['amount']], ["id" => $check_crafting['id']]);
                $crafting_id = $check_crafting['id'];
            } else {
                // Thêm mới
                $crafting_insert = [ /* ... mảng dữ liệu insert cho crafting ... */];
                $app->insert("crafting", $crafting_insert);
                $crafting_id = $app->id();
            }

            // 4. Ghi Lịch sử, Kho, Chi tiết và cập nhật tồn kho (toàn bộ logic phức tạp còn lại)
            // ... (insert vào history_crafting)
            // ... (insert vào warehouses & warehouses_details)
            // ... (foreach qua $data['ingredient'] để update tồn kho và insert vào crafting_details)

            // --- Kết thúc khối logic "Hoàn tất" ---

            unset($_SESSION['pairing'][$action]);
            echo json_encode(['status' => 'success', 'content' => 'Hoàn tất chế tác thành công']);
            return;
        }
    })->setPermissions(['crafting.add']);


    $app->router('/pairing-export/{type}', 'GET', function ($vars) use ($app, $jatbi, $setting, $stores, $accStore, $template) {
        $action = "export";
        $vars['action'] = $action;
        $type = $vars['type'] ?? 'gold';

        switch ($type) {
            case 'silver':
                $vars['title'] = $jatbi->lang("Xuất kho Bạc");
                $group_crafting_id = 2;
                break;
            case 'chain':
                $vars['title'] = $jatbi->lang("Xuất kho Chuỗi");
                $group_crafting_id = 3;
                break;
            case 'gold':
            default:
                $vars['title'] = $jatbi->lang("Xuất kho Vàng");
                $group_crafting_id = 1;
                break;
        }

        if (($_SESSION['pairing'][$action]['group_crafting'] ?? null) != $group_crafting_id) {
            unset($_SESSION['pairing'][$action]);
            $_SESSION['pairing'][$action]['group_crafting'] = $group_crafting_id;
        }

        if (empty($_SESSION['pairing'][$action]['date'])) {
            $_SESSION['pairing'][$action]['date'] = date("Y-m-d");
        }

        $data = $_SESSION['pairing'][$action] ?? [];
        $vars['data'] = $data;

        $craftings_data = $app->select(
            "crafting",
            ["id", "code", "name"],
            ["deleted" => 0, "amount_total[>]" => 0, "group_crafting" => $data["group_crafting"] ?? $group_crafting_id]
        );
        $vars['craftings'] = array_map(function ($item) {
            return ['value' => $item['id'], 'text' => $item['code'] . ' - ' . $item['name']];
        }, $craftings_data);


        $empty_option = [['value' => '', 'text' => $jatbi->lang('Tất cả')]];

        $vars['stores'] = array_merge($empty_option, $stores);

        $branchs_data = $app->select(
            "branch",
            ["id(value)", "name(text)"],
            ["deleted" => 0, "status" => "A", "stores" => $data['stores']['id'] ?? $accStore]
        );

        $vars['branchs'] = array_merge($empty_option, $branchs_data);

        $session_products = $data['crafting'] ?? [];
        if (!empty($session_products)) {
            $unit_ids = array_column($session_products, 'units');
            $units_map = array_column($app->select("units", ["id", "name"], ["id" => $unit_ids]), 'name', 'id');
            foreach ($session_products as &$item) {
                $item['unit_name'] = $units_map[$item['units']] ?? 'N/A';
            }
            unset($item);
        }

        $vars['SelectProducts'] = $session_products;

        echo $app->render($template . '/crafting/pairing-export.html', $vars);
    });

    $app->router('/pairing/export/update/date', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = 'export';
        $_SESSION['pairing'][$action]['date'] = $app->xss($_POST['value'] ?? '');
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/pairing/export/update/content', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = 'export';
        $_SESSION['pairing'][$action]['content'] = $app->xss($_POST['value'] ?? '');
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/pairing/export/update/stores', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = 'export';
        $data = $app->get("stores", ["id", "name"], ["id" => $app->xss($_POST['value'] ?? 0)]);
        if ($data) {
            $_SESSION['pairing'][$action]['stores'] = ["id" => $data['id'], "name" => $data['name']];
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } else {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại")]);
        }
    });

    $app->router('/pairing/export/update/branch', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = 'export';
        $data = $app->get("branch", ["id", "name"], ["id" => $app->xss($_POST['value'] ?? 0)]);
        if ($data) {
            $_SESSION['pairing'][$action]['branch'] = ["id" => $data['id'], "name" => $data['name']];
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } else {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại")]);
        }
    });

    $app->router('/pairing/export/update/craftings/add', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = 'export';
        $post_value = $app->xss($_POST['value'] ?? '');

        $data = $app->get("crafting", "*", ["id" => $post_value]);
        if ($data) {
            if (($data['amount_total'] ?? 0) <= 0) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Số lượng không đủ')]);
                return;
            }
            if (!isset($_SESSION['pairing'][$action]['crafting'][$data['id']])) {
                $_SESSION['pairing'][$action]['crafting'][$data['id']] = [
                    "crafting" => $data['id'],
                    "name" => $data['name'],
                    "amount" => 0,
                    "code" => $data['code'],
                    "categorys" => $data['categorys'],
                    "price" => $data['price'],
                    "cost" => $data['cost'],
                    "group" => $data['group'],
                    "default_code" => $data['default_code'],
                    "units" => $data['units'],
                    "notes" => $data['notes'],
                    "warehouses" => $data['amount_total'],
                ];
            }
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } else {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại")]);
        }
    });

    $app->router('/pairing/export/update/craftings/deleted/{key}', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = 'export';
        $item_key = $vars['key'] ?? 0;
        unset($_SESSION['pairing'][$action]['crafting'][$item_key]);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/pairing/export/update/craftings/amount/{key}', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = 'export';
        $item_key = $vars['key'] ?? 0;
        $value = str_replace(',', '', $_POST['value'] ?? 0);

        if ($value < 0) {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Số lượng không âm")]);
            return;
        }
        $getAmount = $app->get("crafting", "amount_total", ["id" => $_SESSION['pairing'][$action]['crafting'][$item_key]['crafting']]);
        if ($value > ($getAmount ?? 0)) {
            $_SESSION['pairing'][$action]['crafting'][$item_key]['amount'] = $getAmount;
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Số lượng không đủ')]);
        } else {
            $_SESSION['pairing'][$action]['crafting'][$item_key]['amount'] = $app->xss($value);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        }
    });

    $app->router('/pairing/export/update/craftings/notes/{key}', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = 'export';
        $item_key = $vars['key'] ?? 0;
        $_SESSION['pairing'][$action]['crafting'][$item_key]['notes'] = $app->xss($_POST['value'] ?? '');
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/pairing/export/update/cancel', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = 'export';
        unset($_SESSION['pairing'][$action]);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Đã hủy")]);
    });

    $app->router('/pairing/export/update/completed', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = 'export';
        $data = $_SESSION['pairing'][$action] ?? [];
        $error = [];


        if (empty($data['content']) || empty($data['crafting']) || empty($data['stores']['id']) || empty($data['branch']['id'])) {
            $error = ["status" => 'error', 'content' => $jatbi->lang("Lỗi trống")];
        } else {
            foreach ($data['crafting'] as $value) {
                if (empty($value['amount']) || $value['amount'] <= 0) {
                    $error = ['status' => 'error', 'content' => $jatbi->lang('Vui lòng nhập số lượng')];
                    break;
                }
            }
        }

        if (empty($error)) {
            $insert = ["code" => 'PX', "type" => 'export', "data" => 'pairing', "content" => $data['content'], "stores" => $data['stores']['id'], "branch" => $data['branch']['id'], "user" => $app->getSession("accounts")['id'] ?? 0, "date" => $data['date'], "active" => $jatbi->active(30), "date_poster" => date("Y-m-d H:i:s")];
            $app->insert("warehouses", $insert);
            $orderId = $app->id();

            $details_to_insert = [];
            foreach ($data['crafting'] as $value) {
                $details_to_insert[] = [
                    "warehouses" => $orderId,
                    "data" => $insert['data'],
                    "type" => $insert['type'],
                    "vendor" => $value['vendor'] ?? NULL,
                    "crafting" => $value['crafting'],
                    "amount" => $value['amount'],
                    "amount_total" => $value['amount'],
                    "price" => $value['price'],
                    "cost" => $value['cost'],
                    "notes" => $value['notes'],
                    "date" => date("Y-m-d H:i:s"),
                    "user" => $app->getSession("accounts")['id'] ?? 0,
                    "stores" => $insert['stores'],
                    "branch" => $insert['branch']
                ];
                $app->update("crafting", ["amount_total[-]" => $value['amount'], "amount_export[+]" => $value['amount']], ["id" => $value['crafting']]);
            }
            if (!empty($details_to_insert))
                $app->insert("warehouses_details", $details_to_insert);

            $jatbi->logs('warehouses', $action, []);
            unset($_SESSION['pairing'][$action]);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } else {
            echo json_encode($error);
        }
    });

    $app->router('/pairing-export-history', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $vars['title'] = $jatbi->lang("Lịch sử xuất hàng");

        if ($app->method() === 'GET') {
            $vars['accounts'] = array_merge(
                [['value' => '', 'text' => $jatbi->lang('Tất cả')]],
                $app->select("accounts", ["id(value)", "name(text)"], ["deleted" => 0, "status" => 'A'])
            );
            echo $app->render($template . '/crafting/pairing-export-history.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            $draw = intval($_POST['draw'] ?? 0);
            $start = intval($_POST['start'] ?? 0);
            $length = intval($_POST['length'] ?? 10);
            $searchValue = $_POST['search']['value'] ?? '';
            $orderName = $_POST['columns'][intval($_POST['order'][0]['column'] ?? 0)]['date'] ?? 'warehouses.date';
            $orderDir = $_POST['order'][0]['dir'] ?? 'DESC';
            $filter_user = $_POST['user'] ?? '';
            $filter_date = $_POST['date'] ?? '';

            $where = [
                "AND" => [
                    "warehouses.deleted" => 0,
                    "warehouses.data" => 'pairing',
                    "warehouses.type" => 'export',
                ]
            ];

            if (!empty($searchValue))
                $where['AND']['warehouses.id[~]'] = $searchValue;
            if (!empty($filter_user))
                $where['AND']['warehouses.user'] = $filter_user;

            if (!empty($filter_date)) {
                $date_parts = explode(' - ', $filter_date);
                if (count($date_parts) == 2) {
                    $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date_parts[0]))));
                    $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date_parts[1]))));
                } else {
                    $date_from = date('Y-m-d 00:00:00');
                    $date_to = date('Y-m-d 23:59:59');
                }
            } else {
                $date_from = date('Y-m-d 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }

            $where['AND']["warehouses.date[<>]"] = [$date_from, $date_to];

            $joins = ["[<]accounts" => ["user" => "id"]];

            // Đếm bản ghi
            $count = $app->count("warehouses", $joins, "warehouses.id", $where);

            // Thêm sắp xếp và phân trang
            $where["ORDER"] = [$orderName => strtoupper($orderDir)];
            $where["LIMIT"] = [$start, $length];

            // Lấy dữ liệu
            $columns = [
                "warehouses.id",
                "warehouses.code",
                "warehouses.content",
                "warehouses.date_poster",
                "warehouses.export_status",
                "accounts.name(user_name)"
            ];
            $datas = $app->select("warehouses", $joins, $columns, $where);

            $resultData = [];
            foreach ($datas as $data) {
                $resultData[] = [
                    "code" => '<a data-action="modal" data-url="/crafting/pairing-export-views/' . $data['id'] . '">#' . $data['code'] . $data['id'] . '</a>',
                    "content" => $data['content'],
                    "date" => $jatbi->datetime($data['date_poster']),
                    "user" => $data['user_name'],
                    "status" => $data['export_status'] == 2 ? $jatbi->lang('Đã nhận hàng') : '',
                    "view" => '<button data-action="modal" data-url="/crafting/pairing-export-views/' . $data['id'] . '" class="btn btn-eclo-light btn-sm border-0 py-1 px-2 rounded-3" aria-label="' . $jatbi->lang('Xem') . '"><i class="ti ti-eye"></i></button>',

                ];
            }

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $resultData
            ]);
        }
    });

    $app->router('/pairing-export-views/{id}', 'GET', function ($vars) use ($app, $jatbi, $template) {
        $id = $vars['id'] ?? 0;

        $data = $app->get("warehouses", [
            "[<]stores" => ["stores" => "id"],
            "[<]branch" => ["branch" => "id"],
            "[<]accounts" => ["user" => "id"],
        ], [
            "warehouses.id",
            "warehouses.code",
            "warehouses.date",
            "warehouses.date_poster",
            "warehouses.content",
            "stores.name(store_name)",
            "branch.name(branch_name)",
            "accounts.name(user_name)",
        ], ["warehouses.id" => $id]);

        if (!$data) {
            header("HTTP/1.0 404 Not Found");
            exit;
        }

        $vars['data'] = $data;
        $vars['title'] = $jatbi->lang('Phiếu xuất kho') . ' #' . $data['id'];

        $details = $app->select("warehouses_details", [
            "[<]crafting" => ["crafting" => "id"],
            "[<]units" => ["crafting.units" => "id"]
        ], [
            "warehouses_details.amount",
            "warehouses_details.price",
            "crafting.code(crafting_code)",
            "units.name(unit_name)",
        ], ["warehouses" => $id]);

        $vars['details'] = $details;

        echo $app->render($template . '/crafting/pairing-export-views.html', $vars, $jatbi->ajax());
    });

    $app->router('/crafting-{action}-history/{group}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $action = $vars['action'] ?? 'import';
        $group = $vars['group'] ?? 'gold';

        // Cấu hình dựa trên action và group
        $title_map = [
            'import' => 'Lịch sử nhập',
            'export' => 'Lịch sử xuất hàng',
            'cancel' => 'Lịch sử hủy'
        ];
        $group_map = [
            'gold' => 'vàng',
            'silver' => 'bạc',
            'chain' => 'chuỗi'
        ];
        $type_map = [
            'import' => 'import',
            'export' => ['export', 'export_crafting'],
            'cancel' => 'cancel'
        ];

        // Assign group ID using if-else
        if ($group === 'gold') {
            $group_id = 1;
        } elseif ($group === 'silver') {
            $group_id = 2;
        } elseif ($group === 'chain') {
            $group_id = 3;
        } else {
            $group_id = 4;
        }

        $vars['title'] = $jatbi->lang($title_map[$action]);
        $vars['type'] = $action;
        $vars['group'] = $group;

        if ($app->method() === 'GET') {
            $empty_option = [['value' => '', 'text' => $jatbi->lang('Tất cả')]];

            $accounts_db = $app->select("accounts", ["id(value)", "name(text)"], ["deleted" => 0, "status" => 'A']);

            $vars['accounts'] = array_merge($empty_option, $accounts_db);
            echo $app->render($template . '/crafting/history.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            $draw = intval($_POST['draw'] ?? 0);
            $start = intval($_POST['start'] ?? 0);
            $length = intval($_POST['length'] ?? 10);
            $searchValue = $_POST['search']['value'] ?? '';
            $orderName = $_POST['columns'][intval($_POST['order'][0]['column'] ?? 0)]['date'] ?? 'warehouses.date';
            $orderDir = $_POST['order'][0]['dir'] ?? 'DESC';
            $filter_user = $_POST['user'] ?? '';
            $filter_date = $_POST['date'] ?? '';

            // Xây dựng mệnh đề WHERE
            $where = [
                "AND" => [
                    "warehouses.deleted" => 0,
                    "warehouses.data" => 'crafting',
                    "warehouses.type" => $type_map[$action],
                    "warehouses.group_crafting" => $group_id,
                ]
            ];
            if (!empty($searchValue)) {
                $where['AND']['OR'] = ['warehouses.id[~]' => $searchValue, 'warehouses.content[~]' => $searchValue];
            }
            if (!empty($filter_user)) {
                $where['AND']['warehouses.user'] = $filter_user;
            }
            if (!empty($filter_date)) {
                // Nếu có, xử lý ngày người dùng chọn
                $dates = explode(' - ', $filter_date);
                if (count($dates) === 2) {
                    $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($dates[0]))));
                    $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($dates[1]))));
                }
            } else {
                // Nếu không, mặc định lấy ngày hôm nay
                $date_from = date('Y-m-d 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }

            // Luôn thêm điều kiện lọc ngày vào mệnh đề WHERE
            $where["AND"]["warehouses.date[<>]"] = [$date_from, $date_to];

            $joins = ["[<]accounts" => ["user" => "id"]];

            // Đếm bản ghi
            $count = $app->count("warehouses", $joins, "warehouses.id", $where);

            $where["ORDER"] = [$orderName => strtoupper($orderDir)];
            $where["LIMIT"] = [$start, $length];

            // Lấy dữ liệu
            $columns = [
                "warehouses.id",
                "warehouses.code",
                "warehouses.content",
                "warehouses.date_poster",
                "warehouses.ingredient",
                "accounts.name(user_name)"
            ];
            $datas = $app->select("warehouses", $joins, $columns, $where);

            $resultData = [];
            foreach ($datas as $data) {
                $code_link = '<a data-action="modal" data-url="/crafting/crafting-history-views/' . $data['id'] . '">#' . $data['code'] . $data['id'] . '</a>';
                $content = $data['content'];
                if ($data['ingredient'] != '') {
                    $content = '<a data-action="modal" data-url="/warehouses/ingredient-history-views/' . $data['ingredient'] . '">' . $data['content'] . '</a>';
                }

                $resultData[] = [
                    "code" => $code_link,
                    "content" => $content,
                    "date" => $jatbi->datetime($data['date_poster']),
                    "user" => $data['user_name'],
                    "action" => '<a data-action="modal" class="btn btn-eclo-light btn-sm border-0 py-1 px-2 rounded-3" data-url="/crafting/crafting-history-views/' . $data['id'] . '"><i class="ti ti-eye"></i></a>'
                ];
            }

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $resultData
            ]);
        }
    })->setPermissions(['crafting.history']);

    $app->router('/crafting-history-views/{id}', 'GET', function ($vars) use ($app, $jatbi, $template) {
        $id = (int) ($vars['id'] ?? 0);

        $data = $app->get("warehouses", "*", ["id" => $id]);
        if (!$data) {
            return $app->render($template . '/error.html', [], $jatbi->ajax());
        }

        $details = $app->select("warehouses_logs", "*", ["warehouses" => $data['id'], "data" => 'crafting', "ingredient[!]" => null]);

        $ingredient_ids = array_column($details, 'ingredient');
        $user_ids[] = $data['user'];

        $ingredients_map = [];
        $units_map = [];
        if (!empty($ingredient_ids)) {
            $ingredient_info = $app->select(
                "ingredient",
                ["[>]units" => ["units" => "id"]],
                ["ingredient.id", "ingredient.code", "units.name(unit_name)"],
                ["ingredient.id" => $ingredient_ids]
            );
            $ingredients_map = array_column($ingredient_info, null, 'id');
        }

        $accounts_map = array_column($app->select("accounts", ["id", "name"], ["id" => array_unique($user_ids)]), 'name', 'id');

        foreach ($details as &$detail) {
            $ingredient_id = $detail['ingredient'];
            if (isset($ingredients_map[$ingredient_id])) {
                $detail['ingredient_code'] = $ingredients_map[$ingredient_id]['code'];
                $detail['unit_name'] = $ingredients_map[$ingredient_id]['unit_name'];
            }
        }
        unset($detail);

        $data['details'] = $details;
        $data['user_name'] = $accounts_map[$data['user']] ?? 'N/A';

        $vars['data'] = $data;
        $vars['title'] = "Chi tiết phiếu #" . $data['code'] . $data['id'];

        echo $app->render($template . '/crafting/history-views.html', $vars, $jatbi->ajax());
    });

    $app->router('/products-import-move/{type}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $accStore) {
        $type = $vars['type'] ?? 'gold';

        switch ($type) {
            case 'silver':
                $vars['title'] = $jatbi->lang("Danh sách nhập hàng bạc");
                $group_crafting_id = 2;
                break;
            case 'chain':
                $vars['title'] = $jatbi->lang("Danh sách nhập hàng chuỗi");
                $group_crafting_id = 3;
                break;
            case 'gold':
            default:
                $vars['title'] = $jatbi->lang("Danh sách nhập hàng vàng");
                $group_crafting_id = 1;
                break;
        }

        if ($app->method() === 'GET') {
            // --- TẢI GIAO DIỆN VÀ DỮ LIỆU CHO BỘ LỌC ---
            $vars['type'] = $type;
            $vars['accounts'] = $app->select("accounts", ['id(value)', 'name(text)'], ['deleted' => 0, 'status' => 'A']);
            echo $app->render($template . '/crafting/import-move-list.html', $vars);
        } elseif ($app->method() === 'POST') {
            // --- CUNG CẤP DỮ LIỆU JSON CHO DATATABLE ---
            $app->header(['Content-Type' => 'application/json']);

            $draw = $_POST['draw'] ?? 0;
            $start = $_POST['start'] ?? 0;
            $length = $_POST['length'] ?? 10;
            $searchValue = $_POST['search']['value'] ?? '';
            $orderName = $_POST['columns'][$_POST['order'][0]['column'] ?? 0]['data'] ?? 'id';
            $orderDir = $_POST['order'][0]['dir'] ?? 'DESC';

            $filter_date = $_POST['date'] ?? '';
            $filter_user = $_POST['user'] ?? '';

            $joins = ["[>]accounts" => ["user" => "id"]];
            $where = [
                "AND" => [
                    "warehouses.deleted" => 0,
                    "warehouses.data" => 'products',
                    "warehouses.type" => 'move',
                    "warehouses.export_status" => 3,
                    "warehouses.group_crafting" => $group_crafting_id,
                ]
            ];

            if (!empty($searchValue)) {
                $where['AND']['warehouses.id[~]'] = $searchValue;
            }
            if (!empty($filter_user)) {
                $where['AND']['warehouses.user'] = $filter_user;
            }
            if (!empty($filter_date)) {
                $dates = explode(' - ', $filter_date);
                if (count($dates) == 2) {
                    $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', $dates[0])));
                    $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', $dates[1])));
                    $where['AND']['warehouses.date[<>]'] = [$date_from, $date_to];
                }
            }

            $count = $app->count("warehouses", $joins, "warehouses.id", $where);

            $where['ORDER'] = ["warehouses." . $orderName => strtoupper($orderDir)];
            $where['LIMIT'] = [$start, $length];

            $columns = [
                "warehouses.id",
                "warehouses.code",
                "warehouses.content",
                "warehouses.date_poster",
                "warehouses.ingredient",
                "accounts.name(user_name)"
            ];
            $results = $app->select("warehouses", $joins, $columns, $where);

            $datas = [];
            foreach ($results as $data) {
                $import_url = "/crafting/crafting-import-{$type}/{$data['id']}/";
                $datas[] = [
                    "id" => $data['id'],
                    "code" => '#' . $data['code'] . $data['id'],
                    "content" => $data['content'],
                    "date" => date('d/m/Y H:i:s', strtotime($data['date_poster'])),
                    "user" => $data['user_name'],
                    "action" => '<a class="btn btn-sm btn-primary pjax-load" href="' . $import_url . '">' . $jatbi->lang("Nhập hàng") . '</a>'
                        . '<a class="btn btn-sm btn-light ms-2 modal-url" data-url="/crafting/ingredient-history-views/' . $data['id'] . '/"><i class="ti ti-eye"></i></a>',
                ];
            }

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas
            ]);
        }
    });

    $app->router('/crafting-move-crafting/{type}', 'GET', function ($vars) use ($app, $jatbi, $template) {
        $action = "from_to";
        $vars['action'] = $action;
        $type = $vars['type'] ?? 'gold';

        switch ($type) {
            case 'silver':
                $vars['title'] = $jatbi->lang("Chuyển kho chế tác");
                $crafting_from_id = 2;
                $stock_column = 'craftingsilver';
                $name = $jatbi->lang("Bạc");
                break;
            case 'chain':
                $vars['title'] = $jatbi->lang("Chuyển kho chế tác");
                $crafting_from_id = 3;
                $stock_column = 'craftingchain';
                $name = $jatbi->lang("Chuỗi");
                break;
            case 'gold':
            default:
                $vars['title'] = $jatbi->lang("Chuyển kho chế tác");
                $crafting_from_id = 1;
                $stock_column = 'crafting';
                $name = $jatbi->lang("Vàng");
                break;
        }

        // 2. Xử lý session
        if (($_SESSION['fromto'][$action]['crafting_from'] ?? null) != $crafting_from_id) {
            unset($_SESSION['fromto'][$action]);
            $_SESSION['fromto'][$action]['crafting_from'] = $crafting_from_id;
            $_SESSION['fromto'][$action]['name'] = $jatbi->lang("Kho chế tác ") . strtolower($name);
        }

        $data = $_SESSION['fromto'][$action] ?? [];
        $vars['data'] = $data;

        // 3. Chuẩn bị dữ liệu cho View
        $vars['ingredient_options'] = $app->select("ingredient", ['id(value)', 'code(text)'], ["deleted" => 0, "status" => 'A', "{$stock_column}[>]" => 0]);

        // 4. Tối ưu hóa: Lấy trước thông tin cần thiết cho bảng
        $session_products = $data['ingredient'] ?? [];
        if (!empty($session_products)) {
            $ingredient_ids = array_column($session_products, 'ingredient');
            $ingredient_info = $app->select("ingredient", ["[>]units" => ["units" => "id"]], ["ingredient.id", "ingredient.code", "ingredient.type(ingredient_type)", "ingredient.price", "units.name(unit_name)"], ["ingredient.id" => $ingredient_ids]);
            $ingredient_map = array_column($ingredient_info, null, 'id');

            foreach ($session_products as &$item) {
                $id = $item['ingredient'];
                if (isset($ingredient_map[$id])) {
                    $item['code'] = $ingredient_map[$id]['code'];
                    $item['unit_name'] = $ingredient_map[$id]['unit_name'];
                    $item['ingredient_type'] = $ingredient_map[$id]['ingredient_type'];
                    $item['price'] = $ingredient_map[$id]['price'];
                }
            }
            unset($item);
        }
        $vars['SelectProducts'] = $session_products;

        echo $app->render($template . '/crafting/move.html', $vars);
    });

    $app->router('/crafting-from-to/update/{action}/crafting_to', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $post_value = $app->xss($_POST['value'] ?? '');

        if ($post_value > 0) {
            $_SESSION['fromto'][$action]['crafting_to'] = $post_value;
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } else {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại")]);
        }
    });

    $app->router('/crafting-from-to/update/{action}/ingredient/add', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $post_value = $app->xss($_POST['value'] ?? '');

        $data = $app->get("ingredient", "*", ["id" => $post_value]);
        if ($data) {
            $warehouses = 0;
            $crafting_from = $_SESSION['fromto'][$action]['crafting_from'] ?? 1;
            if ($crafting_from == 1)
                $warehouses = $data['crafting'];
            elseif ($crafting_from == 2)
                $warehouses = $data['craftingsilver'];
            elseif ($crafting_from == 3)
                $warehouses = $data['craftingchain'];

            if ($warehouses <= 0) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Số lượng không đủ')]);
                return;
            }

            if (!isset($_SESSION['fromto'][$action]['ingredient'][$data['id']])) {
                $_SESSION['fromto'][$action]['ingredient'][$data['id']] = [
                    "ingredient" => $data['id'],
                    "amount" => 1,
                    "warehouses" => $warehouses > 0 ? $warehouses : 0,
                    "price" => $data['price'],
                    "cost" => $data['cost'],
                    "notes" => $data['notes'],
                    "vendor" => $data['vendor'],

                ];
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => "Sản phẩm đã dùng"]);
            }
        } else {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại")]);
        }
    });

    $app->router('/crafting-from-to/update/{action}/ingredient/deleted/{key}', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $key = $vars['key'] ?? 0;
        unset($_SESSION['fromto'][$action]['ingredient'][$key]);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/crafting-from-to/update/{action}/ingredient/amount/{key}', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $key = $vars['key'] ?? 0;
        $value = $app->xss(str_replace(',', '', $_POST['value'] ?? 0));

        if ($value < 0) {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Số lượng không âm")]);
            return;
        }

        $getAmount = $app->get("ingredient", ['crafting', 'craftingsilver', 'craftingchain'], ["id" => $_SESSION['fromto'][$action]['ingredient'][$key]['ingredient']]);
        $crafting_from = $_SESSION['fromto'][$action]['crafting_from'] ?? 1;
        $stock = 0;
        if ($crafting_from == 1)
            $stock = $getAmount['crafting'];
        elseif ($crafting_from == 2)
            $stock = $getAmount['craftingsilver'];
        elseif ($crafting_from == 3)
            $stock = $getAmount['craftingchain'];

        if ($value > $stock) {
            $_SESSION['fromto'][$action]['ingredient'][$key]['amount'] = $stock;
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Số lượng không đủ')]);
        } else {
            $_SESSION['fromto'][$action]['ingredient'][$key]['amount'] = $value;
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        }
    });

    $app->router('/crafting-from-to/update/{action}/content', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $post_value = $app->xss($_POST['value'] ?? '');

        $_SESSION['fromto'][$action]['content'] = $post_value;

        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/crafting-from-to/update/{action}/crafting_from', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $post_value = $app->xss($_POST['value'] ?? '');

        $_SESSION['fromto'][$action]['crafting_from'] = $post_value;

        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/crafting-from-to/update/{action}/ingredient/notes/{key}', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $key = $vars['key'] ?? 0;
        $_SESSION['fromto'][$action]['ingredient'][$key]['notes'] = $app->xss($_POST['value'] ?? '');
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/crafting-from-to/update/{action}/cancel', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        unset($_SESSION['fromto'][$action]);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Đã hủy")]);
    });

    $app->router('/crafting-from-to/update/{action}/ingredient/price/{key}', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);

        $action = $vars['action'] ?? '';
        $key = $vars['key'] ?? 0;
        $post_value = $_POST['value'] ?? '';

        $price = str_replace([','], '', $app->xss($post_value));

        $_SESSION['fromto'][$action]['ingredient'][$key]['price'] = $price;

        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/crafting-from-to/update/{action}/completed', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $action = $vars['action'] ?? '';
        $data = $_SESSION['fromto'][$action] ?? [];
        $error = [];
        $error_warehouses = false;

        foreach ($data['ingredient'] ?? [] as $value) {
            if (empty($value['amount']) || $value['amount'] <= 0) {
                $error_warehouses = true;
                break;
            }
        }

        if (($data['crafting_from'] ?? '') == ($data['crafting_to'] ?? '')) {
            $error = ["status" => 'error', 'content' => $jatbi->lang("Kho chế tác không được trùng")];
        } elseif (empty($data['content']) || empty($data['ingredient']) || empty($data['crafting_to'])) {
            $error = ["status" => 'error', 'content' => $jatbi->lang("Lỗi trống")];
        } elseif ($error_warehouses) {
            $error = ['status' => 'error', 'content' => $jatbi->lang('Vui lòng nhập số lượng')];
        }

        if (empty($error)) {
            $insert = [
                "code" => 'PX',
                "type" => 'export_crafting',
                "data" => 'crafting',
                "content" => $data['content'],
                "user" => $app->getSession("accounts")['id'] ?? 0,
                "date" => $data['date'] ?? date("Y-m-d H:i:s"),
                "active" => $jatbi->active(30),
                "date_poster" => date("Y-m-d H:i:s"),
                "group_crafting" => $data['crafting_from'],
                "group_crafting_reveice" => $data['crafting_to'],
                "export_status" => 1,
            ];
            $app->insert("warehouses", $insert);
            $orderId = $app->id();
            $pro_logs = [];
            foreach ($data['ingredient'] as $value) {
                $pro = [
                    "warehouses" => $orderId,
                    "data" => $insert['data'],
                    "type" => $insert['type'],
                    "vendor" => $value['vendor'] ?? 0,
                    "ingredient" => $value['ingredient'],
                    "amount_old" => $value['warehouses'],
                    "amount" => str_replace([','], '', $value['amount']),
                    "amount_total" => str_replace([','], '', $value['amount']),
                    "price" => $value['price'],
                    "cost" => $value['cost'] ?? 0,
                    "notes" => $value['notes'] ?? '',
                    "date" => $insert['date_poster'],
                    "user" => $app->getSession("accounts")['id'] ?? 0,
                    "group_crafting" => $insert['group_crafting'],
                ];


                $app->insert("warehouses_details", $pro);
                $details_id = $app->id();

                $warehouses_logs = [
                    "type" => 'export',
                    "data" => 'crafting',
                    "warehouses" => $orderId,
                    "details" => $details_id,
                    "ingredient" => $value['ingredient'],
                    "price" => $value['price'],
                    "cost" => $value['cost'] ?? 0,
                    "amount" => str_replace([','], '', $value['amount']),
                    "total" => str_replace([','], '', $value['amount']) * $value['price'],
                    "notes" => $value['notes'] ?? '',
                    "date" => $insert['date_poster'],
                    "user" => $app->getSession("accounts")['id'] ?? 0,
                    "group_crafting" => $insert['group_crafting'],
                ];

                $app->insert("warehouses_logs", $warehouses_logs);

                $stock_column = 'crafting';
                if ($insert['group_crafting'] == 2)
                    $stock_column = 'craftingsilver';
                if ($insert['group_crafting'] == 3)
                    $stock_column = 'craftingchain';
                $app->update("ingredient", ["{$stock_column}[-]" => $value['amount']], ["id" => $value['ingredient']]);

                $pro_logs[] = $pro;
            }

            $jatbi->logs('warehouses', $action, $insert);
            unset($_SESSION['fromto'][$action]);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } else {
            echo json_encode($error);
        }
    });

    $app->router('/crafting-move/{type}', 'GET', function ($vars) use ($app, $jatbi, $template, $stores, $accStore) {
        $action = "to_products";
        $vars['action'] = $action;
        $type = $vars['type'] ?? 'gold';
        $vars['type_param'] = $type;

        switch ($type) {
            case 'silver':
                // $jatbi->permission('crafting-move-silver'); // Cần định nghĩa quyền
                $vars['title'] = $jatbi->lang("Chuyển qua kho thành phẩm");
                $group_crafting_id = 2;
                $stock_column = 'craftingsilver';
                break;
            case 'chain':
                // $jatbi->permission('crafting-move-chain'); // Cần định nghĩa quyền?
                $vars['title'] = $jatbi->lang("Chuyển qua kho thành phẩm");
                $group_crafting_id = 3;
                $stock_column = 'craftingchain';
                break;
            case 'gold':
            default:
                // $jatbi->permission('crafting-move-gold'); // Cần định nghĩa quyền
                $vars['title'] = $jatbi->lang("Chuyển qua kho thành phẩm");
                $group_crafting_id = 1;
                $stock_column = 'crafting';
                break;
        }

        if (($_SESSION['to'][$action]['group_crafting'] ?? null) != $group_crafting_id) {
            unset($_SESSION['to'][$action]);
            $_SESSION['to'][$action]['group_crafting'] = $group_crafting_id;
        }
        if (empty($_SESSION['to'][$action]['date']))
            $_SESSION['to'][$action]['date'] = date("Y-m-d");
        if (empty($_SESSION['to'][$action]['type']))
            $_SESSION['to'][$action]['type'] = $action;

        $data = $_SESSION['to'][$action] ?? [];
        $vars['data'] = $data;

        $vars['ingredient_options'] = $app->select("ingredient", ['id(value)', 'code(text)'], ["deleted" => 0, "status" => 'A', "{$stock_column}[>]" => 0]);
        $empty_option = [['value' => '', 'text' => $jatbi->lang('Chọn')]];

        $vars['stores'] = array_merge($empty_option, $stores);

        $branchs_data = $app->select("branch", ['id(value)', 'name(text)'], ["deleted" => 0, "status" => "A", "stores" => $data['stores']['id'] ?? $accStore]);

        $vars['branchs'] = array_merge($empty_option, $branchs_data);

        // 4. Tối ưu hóa: Lấy trước thông tin cần thiết cho bảng
        $session_products = $data['ingredient'] ?? [];
        if (!empty($session_products)) {
            $ingredient_ids = array_column($session_products, 'ingredient');
            $ingredient_info = $app->select("ingredient", ["[>]units" => ["units" => "id"]], ["ingredient.id", "ingredient.code", "ingredient.type(ingredient_type)", "ingredient.price", "units.name(unit_name)"], ["ingredient.id" => $ingredient_ids]);
            $ingredient_map = array_column($ingredient_info, null, 'id');
            foreach ($session_products as &$item) {
                $id = $item['ingredient'];
                if (isset($ingredient_map[$id])) {
                    $item = array_merge($item, $ingredient_map[$id]);
                }
            }
            unset($item);
        }
        $vars['SelectProducts'] = $session_products;

        echo $app->render($template . '/crafting/move-to-products.html', $vars);
    });

    $app->router('/crafting-move-products/update/{action}/stores', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $data = $app->get("stores", ["id", "name"], ["id" => $app->xss($_POST['value'] ?? 0)]);
        if ($data) {
            $_SESSION['to'][$action]['stores'] = ["id" => $data['id'], "name" => $data['name']];
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } else {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại")]);
        }
    });

    $app->router('/crafting-move-products/update/{action}/branch', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $data = $app->get("branch", ["id", "name"], ["id" => $app->xss($_POST['value'] ?? 0)]);
        if ($data) {
            $_SESSION['to'][$action]['branch'] = ["id" => $data['id'], "name" => $data['name']];
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } else {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại")]);
        }
    });

    $app->router('/crafting-move-products/update/{action}/ingredient/add', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $data = $app->get("ingredient", "*", ["id" => $app->xss($_POST['value'] ?? 0)]);
        if ($data) {
            $warehouses = 0;
            $group_crafting = $_SESSION['to'][$action]['group_crafting'] ?? 1;
            if ($group_crafting == 1)
                $warehouses = $data['crafting'];
            elseif ($group_crafting == 2)
                $warehouses = $data['craftingsilver'];
            elseif ($group_crafting == 3)
                $warehouses = $data['craftingchain'];

            if ($warehouses <= 0) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Số lượng không đủ')]);
                return;
            }

            if (!isset($_SESSION['to'][$action]['ingredient'][$data['id']])) {
                $_SESSION['to'][$action]['ingredient'][$data['id']] = [
                    "ingredient" => $data['id'],
                    "amount" => 1,
                    "warehouses" => $warehouses,
                    "price" => $data['price'],
                    "cost" => $data['cost'],
                    "notes" => $data['notes'],
                    "vendor" => $data['vendor'],
                ];
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => "Sản phẩm đã dùng"]);
            }
        } else {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại")]);
        }
    });

    // Xóa nguyên liệu
    $app->router('/crafting-move-products/update/{action}/ingredient/deleted/{key}', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $key = $vars['key'] ?? 0;
        unset($_SESSION['to'][$action]['ingredient'][$key]);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    // Sửa số lượng nguyên liệu
    $app->router('/crafting-move-products/update/{action}/ingredient/amount/{key}', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $key = $vars['key'] ?? 0;
        $value = $app->xss(str_replace(',', '', $_POST['value'] ?? 0));

        if ($value < 0) {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Số lượng không âm")]);
            return;
        }

        $getAmount = $app->get("ingredient", ['crafting', 'craftingsilver', 'craftingchain'], ["id" => $_SESSION['to'][$action]['ingredient'][$key]['ingredient']]);
        $group_crafting = $_SESSION['to'][$action]['group_crafting'] ?? 1;
        $stock = 0;
        if ($group_crafting == 1)
            $stock = $getAmount['crafting'];
        elseif ($group_crafting == 2)
            $stock = $getAmount['craftingsilver'];
        elseif ($group_crafting == 3)
            $stock = $getAmount['craftingchain'];

        if ($value > $stock) {
            $_SESSION['to'][$action]['ingredient'][$key]['amount'] = $stock;
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Số lượng không đủ')]);
        } else {
            $_SESSION['to'][$action]['ingredient'][$key]['amount'] = $value;
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        }
    });

    // Sửa ghi chú nguyên liệu
    $app->router('/crafting-move-products/update/{action}/ingredient/notes/{key}', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $key = $vars['key'] ?? 0;
        $_SESSION['to'][$action]['ingredient'][$key]['notes'] = $app->xss($_POST['value'] ?? '');
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/crafting-move-products/update/{action}/content', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $_SESSION['to'][$action]['content'] = $app->xss($_POST['value'] ?? '');
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    // Hủy phiếu
    $app->router('/crafting-move-products/update/{action}/cancel', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        unset($_SESSION['to'][$action]);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Đã hủy")]);
    });

    $app->router('/crafting-move-products/update/{action}/completed', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $action = $vars['action'] ?? '';
        $data = $_SESSION['to'][$action] ?? [];
        $error = [];
        $error_warehouses = false;
        $total_products = 0;

        // Calculate total and check for invalid amounts
        foreach ($data['ingredient'] as $value) {
            $total_products += $value['amount'] * $value['price'];
            if (empty($value['amount']) || $value['amount'] <= 0) {
                $error_warehouses = true;
            }
        }

        // Validate required fields
        if (empty($data['content']) || empty($data['ingredient']) || empty($data['stores']['id']) || empty($data['branch']['id'])) {
            $error = ["status" => 'error', 'content' => $jatbi->lang("Lỗi trống")];
        } elseif ($error_warehouses) {
            $error = ['status' => 'error', 'content' => $jatbi->lang('Vui lòng nhập số lượng')];
        }

        if (empty($error)) {
            // Insert export warehouse record
            $insert_export = [
                "code" => 'PX',
                "type" => 'export_crafting',
                "data" => 'crafting',
                "content" => $data['content'],
                "vendor" => $data['vendor']['id'] ?? NULL,
                "stores" => $data['stores']['id'],
                "branch" => $data['branch']['id'],
                "user" => $app->getSession("accounts")['id'] ?? 0,
                "date" => $data['date'],
                "active" => $jatbi->active(30),
                "date_poster" => date("Y-m-d H:i:s"),
                "group_crafting" => $data['group_crafting'],
            ];
            $app->insert("warehouses", $insert_export);
            $export_id = $app->id();

            // Insert import warehouse record
            $insert_import = [
                "code" => 'PN',
                "type" => 'import',
                "data" => 'products',
                "content" => $data['content'] . ' Nhập hàng từ phiếu PX' . $export_id,
                "vendor" => $data['vendor']['id'] ?? NULL,
                "stores" => $data['stores']['id'],
                "branch" => $data['branch']['id'],
                "user" => $app->getSession("accounts")['id'] ?? 0,
                "date" => $data['date'],
                "active" => $jatbi->active(30),
                "date_poster" => date("Y-m-d H:i:s"),
                "crafting" => $export_id,
            ];
            $app->insert("warehouses", $insert_import);
            $import_id = $app->id();

            $pro_logs = [];
            foreach ($data['ingredient'] as $value) {
                // Insert export details
                $pro_export = [
                    "warehouses" => $export_id,
                    "data" => 'crafting',
                    "type" => 'export',
                    "vendor" => $value['vendor'] ?? NULL,
                    "ingredient" => $value['ingredient'],
                    "amount_old" => $value['warehouses'] ?? 0,
                    "amount" => str_replace(',', '', $value['amount']),
                    "amount_total" => str_replace(',', '', $value['amount']),
                    "price" => $value['price'] ?? 0,
                    "cost" => $value['cost'] ?? 0,
                    "notes" => $value['notes'] ?? '',
                    "date" => date("Y-m-d H:i:s"),
                    "user" => $app->getSession("accounts")['id'] ?? 0,
                    "stores" => $insert_export['stores'],
                    "branch" => $insert_export['branch'],
                    "group_crafting" => $data['group_crafting'],
                ];
                $app->insert("warehouses_details", $pro_export);
                $pro_logs[] = $pro_export;
                $details_id = $app->id();

                // Insert export log
                $warehouses_logs = [
                    "type" => 'export',
                    "data" => 'crafting',
                    "warehouses" => $export_id,
                    "details" => $details_id,
                    "ingredient" => $value['ingredient'],
                    "price" => $value['price'] ?? 0,
                    "cost" => $value['cost'] ?? 0,
                    "amount" => str_replace(',', '', $value['amount']),
                    "total" => str_replace(',', '', $value['amount']) * ($value['price'] ?? 0),
                    "notes" => $value['notes'] ?? '',
                    "date" => date('Y-m-d H:i:s'),
                    "user" => $app->getSession("accounts")['id'] ?? 0,
                    "stores" => $insert_export['stores'],
                    "group_crafting" => $data['group_crafting'],
                ];
                $app->insert("warehouses_logs", $warehouses_logs);

                // Update ingredient stock
                $getIngredient = $app->get("ingredient", "*", ["id" => $value['ingredient']]);
                $stock_column = match ($data['group_crafting']) {
                    1 => 'crafting',
                    2 => 'craftingsilver',
                    3 => 'craftingchain',
                    default => 'crafting',
                };
                $app->update("ingredient", ["{$stock_column}[-]" => $value['amount']], ["id" => $value['ingredient']]);

                // Generate product code
                $store_code = $app->get("stores", "code", ["id" => $insert_export['stores']]);
                $branch_code = $app->get("branch", "code", ["id" => $insert_export['branch']]);
                $new_product_code = $store_code . $branch_code . $getIngredient['code'];

                // Check if product exists
                $check_product = $app->get("products", "*", ["code" => $new_product_code, "deleted" => 0, 'status' => 'A']);
                $product_id_to_import = null;

                if ($check_product && $getIngredient['code'] == $check_product['code_products']) {
                    // Update existing product
                    $app->update("products", ["amount[+]" => $value['amount'], "type" => 2], ["id" => $check_product['id']]);
                    $product_id_to_import = $check_product['id'];
                } else {
                    // Create new product
                    $name = match ($getIngredient['type']) {
                        1 => 'Đai',
                        2 => 'Ngọc',
                        default => 'Khác',
                    };
                    $new_product_data = [
                        "type" => 2,
                        "main" => 0,
                        "code" => $new_product_code,
                        "name" => $name,
                        "categorys" => '',
                        "vat" => $app->settings['site_vat'] ?? 0,
                        "vat_type" => 1,
                        "amount" => $value['amount'],
                        "content" => $value['content'] ?? '',
                        "vendor" => $getIngredient['vendor'] ?? NULL,
                        "group" => '',
                        "price" => str_replace(',', '', $getIngredient['price'] ?? 0),
                        "cost" => str_replace(',', '', $getIngredient['cost'] ?? 0),
                        "units" => $getIngredient['units'] ?? '',
                        "notes" => $getIngredient['notes'] ?? '',
                        "active" => $jatbi->active(32),
                        "images" => 'no-images.jpg',
                        "date" => date('Y-m-d H:i:s'),
                        "status" => 'A',
                        "user" => $app->getSession("accounts")['id'] ?? 0,
                        "new" => 1,
                        "stores" => $insert_import['stores'],
                        "branch" => $insert_import['branch'],
                        "code_products" => $getIngredient['code'] ?? NULL,
                    ];
                    $app->insert("products", $new_product_data);
                    $product_id_to_import = $app->id();
                }

                // Insert import details
                $pro_import = [
                    "warehouses" => $import_id,
                    "data" => 'products',
                    "type" => 'import',
                    "vendor" => $value['vendor'] ?? NULL,
                    "products" => $product_id_to_import,
                    "amount_buy" => $value['amount_buy'] ?? 0,
                    "amount" => str_replace(',', '', $value['amount']),
                    "amount_total" => str_replace(',', '', $value['amount']),
                    "price" => $value['price'] ?? 0,
                    "notes" => $value['notes'] ?? '',
                    "date" => date("Y-m-d H:i:s"),
                    "user" => $app->getSession("accounts")['id'] ?? 0,
                    "stores" => $insert_import['stores'],
                    "branch" => $insert_import['branch'],
                ];
                $app->insert("warehouses_details", $pro_import);
                $details_id = $app->id();

                // Insert import log
                $warehouses_logs = [
                    "data" => 'products',
                    "type" => 'import',
                    "warehouses" => $import_id,
                    "details" => $details_id,
                    "products" => $product_id_to_import,
                    "price" => $value['price'] ?? 0,
                    "amount" => str_replace(',', '', $value['amount']),
                    "total" => str_replace(',', '', $value['amount']) * ($value['price'] ?? 0),
                    "notes" => $value['notes'] ?? '',
                    "date" => date('Y-m-d H:i:s'),
                    "user" => $app->getSession("accounts")['id'] ?? 0,
                    "stores" => $insert_import['stores'],
                    "branch" => $insert_import['branch'],
                ];
                $app->insert("warehouses_logs", $warehouses_logs);
            }

            // Log action and clear session
            $jatbi->logs('warehouses', $action, [$insert_export, $pro_logs, $_SESSION['to'][$action]]);
            unset($_SESSION['to'][$action]);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } else {
            echo json_encode($error);
        }
    });

    $app->router('/{group}-import-ingredient', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $group = $vars['group'] ?? 'gold';

        // Cấu hình dựa trên group
        $group_map = ['gold' => 1, 'silver' => 2, 'chain' => 3];
        $group_id = $group_map[$group] ?? 1;


        $vars['title'] = $jatbi->lang("Danh sách nhập hàng");
        $vars['group'] = $group;

        if ($app->method() === 'GET') {
            $vars['accounts'] = $app->select("accounts", ["id(value)", "name(text)"], ["deleted" => 0, "status" => 'A']);
            echo $app->render($template . '/crafting/import-ingredient-list.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            // Đọc tham số từ DataTables và bộ lọc
            $draw = intval($_POST['draw'] ?? 0);
            $start = intval($_POST['start'] ?? 0);
            $length = intval($_POST['length'] ?? 10);
            $searchValue = $_POST['search']['value'] ?? '';
            $orderName = $_POST['columns'][intval($_POST['order'][0]['column'] ?? 0)]['date'] ?? 'warehouses.date';
            $orderDir = $_POST['order'][0]['dir'] ?? 'DESC';
            $filter_user = $_POST['user'] ?? '';
            $filter_date = $_POST['date'] ?? '';

            // Xây dựng mệnh đề WHERE
            $where = [
                "AND" => [
                    "warehouses.deleted" => 0,
                    "warehouses.data" => 'ingredient',
                    "warehouses.type" => 'export',
                    "warehouses.export_status" => 1,
                    "warehouses.group_crafting" => $group_id,
                ]
            ];

            if (!empty($searchValue))
                $where['AND']['OR'] = ['warehouses.id[~]' => $searchValue, 'warehouses.content[~]' => $searchValue];
            if (!empty($filter_user))
                $where['AND']['warehouses.user'] = $filter_user;
            if (!empty($filter_date)) {
                $dates = explode(' - ', $filter_date);

                if (count($dates) === 2) {
                    $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($dates[0]))));
                    $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($dates[1]))));

                    $where["AND"]["warehouses.date[<>]"] = [$date_from, $date_to];
                }
            }

            $joins = ["[<]accounts" => ["user" => "id"]];

            // Đếm bản ghi
            $count = $app->count("warehouses", $joins, "warehouses.id", $where);

            $where["ORDER"] = [$orderName => strtoupper($orderDir)];
            $where["LIMIT"] = [$start, $length];

            // Lấy dữ liệu
            $columns = [
                "warehouses.id",
                "warehouses.code",
                "warehouses.content",
                "warehouses.date_poster",
                "accounts.name(user_name)"
            ];
            $datas = $app->select("warehouses", $joins, $columns, $where);

            $resultData = [];
            foreach ($datas as $data) {
                $resultData[] = [
                    "code" => '<a class="info" data-action="modal" data-url="/warehouses/ingredient-history-views/' . $data['id'] . '">#' . $data['code'] . $data['id'] . '</a>',
                    "content" => $data['content'],
                    "date" => $jatbi->datetime($data['date_poster']),
                    "user" => $data['user_name'],
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'link',
                                'name' => $jatbi->lang('Nhập hàng'),
                                'permission' => ['crafting.import'],
                                'action' => ['href' => '/crafting/crafting_' . $group . '_import/' . $data['id'], 'data-pjax' => '']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang('Xem'),
                                'permission' => ['crafting.import'],
                                'action' => ['data-url' => '/warehouses/ingredient-history-views/' . $data['id'], 'data-action' => 'modal']
                            ],
                        ]
                    ])

                ];
            }

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $resultData
            ]);
        }
    })->setPermissions(['crafting.import']);


    $app->router('/crafting-cancel/{type}', 'GET', function ($vars) use ($app, $jatbi, $template) {
        $action = "cancel";
        $vars['action'] = $action;
        $type = $vars['type'] ?? 'gold';
        $vars['type_param'] = $type;

        switch ($type) {
            case 'silver':
                $jatbi->permission('crafting-cancel-silver');
                $vars['title'] = $jatbi->lang("Hủy nguyên liệu kho Bạc");
                $group_crafting_id = 2;
                $stock_column = 'craftingsilver';
                break;
            case 'chain':
                $jatbi->permission('crafting-cancel-chain');
                $vars['title'] = $jatbi->lang("Hủy nguyên liệu kho Chuỗi");
                $group_crafting_id = 3;
                $stock_column = 'craftingchain';
                break;
            case 'gold':
            default:
                $jatbi->permission('crafting-cancel-gold');
                $vars['title'] = $jatbi->lang("Hủy nguyên liệu kho Vàng");
                $group_crafting_id = 1;
                $stock_column = 'crafting';
                break;
        }

        if (($_SESSION['warehouses_cancel'][$action]['group_crafting'] ?? null) != $group_crafting_id) {
            unset($_SESSION['warehouses_cancel'][$action]);
            $_SESSION['warehouses_cancel'][$action]['group_crafting'] = $group_crafting_id;
        }
        if (empty($_SESSION['warehouses_cancel'][$action]['date']))
            $_SESSION['warehouses_cancel'][$action]['date'] = date("Y-m-d");
        if (empty($_SESSION['warehouses_cancel'][$action]['type']))
            $_SESSION['warehouses_cancel'][$action]['type'] = $action;
        if (empty($_SESSION['warehouses_cancel'][$action]['data']))
            $_SESSION['warehouses_cancel'][$action]['data'] = 'crafting';

        $data = $_SESSION['warehouses_cancel'][$action] ?? [];
        $vars['data'] = $data;

        $vars['ingredient_options'] = $app->select("ingredient", ['id(value)', 'code(text)'], ["deleted" => 0, "status" => 'A', "{$stock_column}[>]" => 0]);

        // --- PHẦN SỬA LỖI ---
        $SelectProducts = $data['ingredient'] ?? [];

        if (!empty($SelectProducts)) {
            $ingredient_ids = array_column($SelectProducts, 'ingredient');
            $ingredient_info = $app->select(
                "ingredient",
                ["[>]units" => ["units" => "id"]],
                [
                    "ingredient.id",
                    "ingredient.code",
                    "ingredient.name_ingredient(ingredient_name)",
                    "units.name(unit_name)"
                ],
                ["ingredient.id" => $ingredient_ids]
            );

            $ingredient_map = array_column($ingredient_info, null, 'id');

            foreach ($SelectProducts as $key => &$item) {
                $id = $item['ingredient'];
                if (isset($ingredient_map[$id])) {
                    $item = array_merge($item, $ingredient_map[$id]);
                }
            }
            unset($item);
        }

        $vars['SelectProducts'] = $SelectProducts;

        echo $app->render($template . '/crafting/cancel.html', $vars);
    });

    $app->router('/crafting-cancel/update/{action}/ingredient/add', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';

        $data = $app->get("ingredient", "*", ["id" => $app->xss(string: $_POST['value'])]);


        if ($data) {
            $warehouses = 0;
            $group_crafting = $_SESSION['warehouses_cancel'][$action]['group_crafting'] ?? 1;
            if ($group_crafting == 1)
                $warehouses = $data['crafting'];
            elseif ($group_crafting == 2)
                $warehouses = $data['craftingsilver'];
            elseif ($group_crafting == 3)
                $warehouses = $data['craftingchain'];

            if ($warehouses <= 0) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Số lượng không đủ')]);
                return;
            }

            if (!isset($_SESSION['warehouses_cancel'][$action]['ingredient'][$data['id']])) {
                // Sửa lại: Lưu đầy đủ các trường cần thiết vào session
                $_SESSION['warehouses_cancel'][$action]['ingredient'][$data['id']] = [
                    "ingredient" => $data['id'],
                    "amount" => 1,
                    "code" => $data['code'],
                    "name" => $data['name'] ?? '',
                    "units" => $data['units'],
                    "price" => $data['price'],
                    "warehouses" => $warehouses,
                ];
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => "Sản phẩm đã được thêm"]);
            }
        } else {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại")]);
        }
    });

    // Xóa nguyên liệu
    $app->router('/crafting-cancel/update/{action}/ingredient/deleted/{key}', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $key = $vars['key'] ?? 0;
        unset($_SESSION['warehouses_cancel'][$action]['ingredient'][$key]);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    // Sửa số lượng nguyên liệu
    $app->router('/crafting-cancel/update/{action}/ingredient/amount/{key}', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $key = $vars['key'] ?? 0;
        $value = $app->xss($_POST['value'] ?? 0);

        if ($value < 0) {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Số lượng không âm")]);
            return;
        }

        $getAmount = $app->get("ingredient", "*", ["id" => $_SESSION["warehouses_cancel"][$action]['ingredient'][$key]['ingredient']]);
        $group_crafting = $_SESSION['warehouses_cancel'][$action]['group_crafting'] ?? 1;
        $stock = 0;
        if ($group_crafting == 1)
            $stock = $getAmount['crafting'];
        elseif ($group_crafting == 2)
            $stock = $getAmount['craftingsilver'];
        elseif ($group_crafting == 3)
            $stock = $getAmount['craftingchain'];

        if ($value > $stock) {
            $_SESSION['warehouses_cancel'][$action]['ingredient'][$key]['amount'] = $stock;
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Số lượng không đủ')]);
        } else {
            $_SESSION["warehouses_cancel"][$action]['ingredient'][$key]['amount'] = $value;
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        }
    });

    $app->router('/crafting-cancel/update/{action}/date', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $_SESSION['warehouses_cancel'][$action]['date'] = $app->xss($_POST['value'] ?? '');
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });
    // Cập nhật nội dung
    $app->router('/crafting-cancel/update/{action}/content', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $_SESSION['warehouses_cancel'][$action]['content'] = $app->xss($_POST['value'] ?? '');
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/crafting-cancel/update/{action}/cancel', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        unset($_SESSION['warehouses_cancel'][$action]);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Đã hủy")]);
    });

    $app->router('/crafting-cancel/update/{action}/completed', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $data = $_SESSION['warehouses_cancel'][$action] ?? [];
        $error = [];
        $error_warehouses = false;
        $total_products = 0;

        // Calculate total and validate amounts
        foreach ($data['ingredient'] ?? [] as $value) {
            $total_products += ($value['amount'] * ($value['price'] ?? 0));
            if (empty($value['amount']) || $value['amount'] <= 0) {
                $error_warehouses = true;
            }
        }

        // Validate required fields
        if (empty($data['content']) || empty($data['ingredient'])) {
            $error = ["status" => 'error', 'content' => $jatbi->lang("Lỗi trống")];
        } elseif ($error_warehouses) {
            $error = ['status' => 'error', 'content' => $jatbi->lang('Vui lòng nhập số lượng')];
        }

        if (empty($error)) {
            // Insert warehouse record
            $insert = [
                "code" => 'cancel',
                "type" => $data['type'],
                "data" => $data['data'],
                "content" => $data['content'],
                "user" => $app->getSession("accounts")['id'] ?? 0,
                "date" => $data['date'],
                "active" => $jatbi->active(30),
                "date_poster" => date("Y-m-d H:i:s"),
                "receive_status" => 2,
                "export_status" => 2,
                "group_crafting" => $data['group_crafting'],
            ];
            $app->insert("warehouses", $insert);
            $orderId = $app->id();

            $pro_logs = [];
            foreach ($data['ingredient'] as $value) {
                // Update ingredient stock
                $getIngredient = $app->get("ingredient", "*", ["id" => $value['ingredient'], "deleted" => 0]);
                $stock_column = match ($data['group_crafting']) {
                    1 => 'crafting',
                    2 => 'craftingsilver',
                    3 => 'craftingchain',
                    default => 'crafting',
                };
                $app->update("ingredient", ["{$stock_column}[-]" => $value['amount']], ["id" => $getIngredient['id']]);

                // Insert warehouse details
                $pro_detail = [
                    "warehouses" => $orderId,
                    "data" => $insert['data'],
                    "type" => $insert['type'],
                    "ingredient" => $value['ingredient'],
                    "amount" => str_replace(',', '', $value['amount']),
                    "amount_total" => str_replace(',', '', $value['amount']),
                    "price" => $value['price'] ?? 0,
                    "notes" => $data['content'],
                    "date" => date("Y-m-d H:i:s"),
                    "user" => $app->getSession("accounts")['id'] ?? 0,
                    "group_crafting" => $insert['group_crafting'],
                ];
                $app->insert("warehouses_details", $pro_detail);
                $details_id = $app->id();
                $pro_logs[] = $pro_detail;

                // Insert warehouse log
                $warehouses_logs = [
                    "type" => $insert['type'],
                    "data" => $insert['data'],
                    "warehouses" => $orderId,
                    "details" => $details_id,
                    "ingredient" => $value['ingredient'],
                    "price" => $value['price'] ?? 0,
                    "amount" => str_replace(',', '', $value['amount']),
                    "total" => str_replace(',', '', $value['amount']) * ($value['price'] ?? 0),
                    "notes" => $data['content'],
                    "date" => date('Y-m-d H:i:s'),
                    "user" => $app->getSession("accounts")['id'] ?? 0,
                    "group_crafting" => $insert['group_crafting'],
                ];
                $app->insert("warehouses_logs", $warehouses_logs);
            }

            // Log action and clear session
            $jatbi->logs('warehouses_cancel', $action, [$insert, $pro_logs, $_SESSION['warehouses_cancel'][$action]]);
            unset($_SESSION['warehouses_cancel'][$action]);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } else {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Dữ liệu trống vui lòng kiểm tra lại')]);
        }
    });

    $app->router('/crafting_{type}_import/{id}', 'GET', function ($vars) use ($app, $jatbi, $template) {
        $action = "import";
        $type = $vars['type'] ?? 'gold';
        $id = (int) ($vars['id'] ?? 0);
        $vars['id'] = $id;

        // 1. Xác định các biến dựa trên {type} từ URL
        switch ($type) {
            case 'silver':
                $jatbi->permission('crafting-import-silver');
                $vars['title'] = $jatbi->lang("Nhập kho Bạc");
                $group_crafting_id = 2;
                $stock_column = 'craftingsilver';
                break;
            case 'chain':
                $jatbi->permission('crafting-import-chain');
                $vars['title'] = $jatbi->lang("Nhập kho Chuỗi");
                $group_crafting_id = 3;
                $stock_column = 'craftingchain';
                break;
            case 'gold':
            default:
                $jatbi->permission('crafting-import-gold');
                $vars['title'] = $jatbi->lang("Nhập kho Vàng");
                $group_crafting_id = 1;
                $stock_column = 'crafting';
                break;
        }

        // 2. Lấy dữ liệu phiếu xuất kho gốc
        $source_warehouse = $app->get("warehouses", ["id", "code"], ["data" => 'ingredient', "type" => 'export', "id" => $id, "deleted" => 0]);
        if (!$source_warehouse) {
            return $app->render($template . '/error.html', $vars);
        }

        // 3. Xử lý session: Chỉ khởi tạo lại nếu người dùng mở một phiếu khác
        if (($_SESSION['crafting'][$action]['crafting'] ?? null) != $source_warehouse['id']) {
            unset($_SESSION['crafting'][$action]);
            $_SESSION['crafting'][$action]['crafting'] = $source_warehouse['id'];
            $_SESSION['crafting'][$action]['type'] = $action;
            $_SESSION['crafting'][$action]['date'] = date("Y-m-d H:i:s");
            $_SESSION['crafting'][$action]['group_crafting'] = $group_crafting_id;
            $_SESSION['crafting'][$action]['content'] = "Nhập hàng từ phiếu #" . $source_warehouse['code'] . $source_warehouse['id'];

            // 4. Tối ưu hóa: Lấy dữ liệu chi tiết và tồn kho trong ít câu query nhất
            $details = $app->select("warehouses_details", "*", ["warehouses" => $source_warehouse['id']]);
            $ingredient_ids = array_column($details, 'ingredient');
            $ingredients_stock = [];
            if (!empty($ingredient_ids)) {
                $ingredients_stock = array_column($app->select("ingredient", ["id", $stock_column], ["id" => $ingredient_ids]), $stock_column, 'id');
            }

            foreach ($details as $value) {
                $_SESSION['crafting'][$action]['ingredient'][] = [
                    "ingredient" => $value['ingredient'],
                    "amount" => $value['amount'],
                    "price" => $value['price'],
                    "cost" => $value['cost'],
                    "vendor" => $value['vendor'],
                    "notes" => $value['notes'],
                    "details" => $value['id'],
                    "warehouses" => $ingredients_stock[$value['ingredient']] ?? 0,
                ];
            }
        }

        // 5. Chuẩn bị dữ liệu cuối cùng cho View (đã tối ưu)
        $data = $_SESSION['crafting'][$action] ?? [];
        $SelectProducts = $data['ingredient'] ?? [];

        if (!empty($SelectProducts)) {
            $ingredient_ids = array_column($SelectProducts, 'ingredient');
            $ingredient_info = $app->select("ingredient", ["[>]units" => ["units" => "id"]], ["ingredient.id", "ingredient.code", "units.name(unit_name)"], ["ingredient.id" => $ingredient_ids]);
            $ingredient_map = array_column($ingredient_info, null, 'id');
            $vars['ingredient_map'] = $ingredient_map;
        }

        $vars['data'] = $data;
        $vars['action'] = $action;
        $vars['SelectProducts'] = $SelectProducts;
        $vars['type1'] = $type;

        echo $app->render($template . '/crafting/import-form.html', $vars);
    });

    $app->router('/crafting-import-move/{type}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $type = $vars['type'] ?? 'gold';

        switch ($type) {
            case 'silver':
                $vars['title'] = $jatbi->lang("Danh sách nhập hàng");
                $group_crafting_id = 2;
                break;
            case 'chain':
                $vars['title'] = $jatbi->lang("Danh sách nhập hàng");
                $group_crafting_id = 3;
                break;
            case 'gold':
            default:
                $vars['title'] = $jatbi->lang("Danh sách nhập hàng");
                $group_crafting_id = 1;
                break;
        }

        if ($app->method() === 'GET') {
            // --- TẢI GIAO DIỆN VÀ DỮ LIỆU CHO BỘ LỌC ---
            $vars['type'] = $type;
            $vars['accounts'] = $app->select("accounts", ['id(value)', 'name(text)'], ['deleted' => 0, 'status' => 'A']);
            echo $app->render($template . '/crafting/import-move-list.html', $vars);
        } elseif ($app->method() === 'POST') {
            // --- CUNG CẤP DỮ LIỆU JSON CHO DATATABLE ---
            $app->header(['Content-Type' => 'application/json']);

            $draw = $_POST['draw'] ?? 0;
            $start = $_POST['start'] ?? 0;
            $length = $_POST['length'] ?? 10;
            $searchValue = $_POST['search']['value'] ?? '';
            $orderName = $_POST['columns'][$_POST['order'][0]['column'] ?? 0]['data'] ?? 'id';
            $orderDir = $_POST['order'][0]['dir'] ?? 'DESC';

            $filter_date = $_POST['date'] ?? '';
            $filter_user = $_POST['user'] ?? '';

            $joins = ["[>]accounts" => ["user" => "id"]];
            $where = [
                "AND" => [
                    "warehouses.deleted" => 0,
                    "warehouses.data" => 'crafting',
                    "warehouses.type" => 'export_crafting',
                    "warehouses.export_status" => 1,
                    "warehouses.group_crafting_receive" => $group_crafting_id,
                ]
            ];

            if (!empty($searchValue)) {
                $where['AND']['warehouses.id[~]'] = $searchValue;
            }
            if (!empty($filter_user) && $filter_user == null) {
                $where['AND']['warehouses.user'] = $filter_user;
            }
            if (!empty($filter_date)) {
                $dates = explode(' - ', $filter_date);
                if (count($dates) == 2) {
                    $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', $dates[0])));
                    $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', $dates[1])));
                    $where['AND']['warehouses.date[<>]'] = [$date_from, $date_to];
                }
            }

            $count = $app->count("warehouses", $joins, "warehouses.id", $where);

            $where['ORDER'] = ["warehouses." . $orderName => strtoupper($orderDir)];
            $where['LIMIT'] = [$start, $length];

            $columns = [
                "warehouses.id",
                "warehouses.code",
                "warehouses.content",
                "warehouses.date_poster",
                "accounts.name(user_name)"
            ];
            $results = $app->select("warehouses", $joins, $columns, $where);

            $datas = [];
            foreach ($results as $data) {
                // Logic tạo URL nhập kho động
                $import_url = "/crafting/crafting-import-{$type}/{$data['id']}/";
                $datas[] = [
                    // "id" => $data['id'],
                    "code" => '#' . $data['code'] . $data['id'],
                    "content" => $data['content'],
                    "date" => date('d/m/Y H:i:s', strtotime($data['date_poster'])),
                    "user" => $data['user_name'],
                    "action" => '<a class="btn btn-sm btn-primary pjax-load" href="' . $import_url . '">' . $jatbi->lang("Nhập hàng") . '</a>'
                        . '<a class="btn btn-sm btn-light ms-2 modal-url" data-url="/warehouses/ingredient-history-views/' . $data['id'] . '/"><i class="ti ti-eye"></i></a>',
                ];
            }

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas
            ]);
        }
    });

    $app->router('/crafting-export/{type}', 'GET', function ($vars) use ($app, $jatbi, $template) {
        $action = "export";
        $vars['action'] = $action;
        $type = $vars['type'] ?? 'gold';

        switch ($type) {
            case 'silver':
                // $jatbi->permission('crafting-export-silver');
                $vars['title'] = $jatbi->lang("Xuất kho Bạc");
                $group_crafting_id = 2;
                $stock_column = 'craftingsilver';
                break;
            case 'chain':
                // $jatbi->permission('crafting-export-chain');
                $vars['title'] = $jatbi->lang("Xuất kho Chuỗi");
                $group_crafting_id = 3;
                $stock_column = 'craftingchain';
                break;
            case 'gold':
            default:
                // $jatbi->permission('crafting-export-gold');
                $vars['title'] = $jatbi->lang("Xuất kho Vàng");
                $group_crafting_id = 1;
                $stock_column = 'crafting';
                break;
        }

        if (($_SESSION['crafting'][$action]['group_crafting'] ?? null) != $group_crafting_id) {
            unset($_SESSION['crafting'][$action]);
            $_SESSION['crafting'][$action]['group_crafting'] = $group_crafting_id;
        }
        if (empty($_SESSION['crafting'][$action]['date'])) {
            $_SESSION['crafting'][$action]['date'] = date("Y-m-d");
        }
        if (empty($_SESSION['crafting'][$action]['type'])) {
            $_SESSION['crafting'][$action]['type'] = $action;
        }

        $data = $_SESSION['crafting'][$action] ?? [];
        $vars['data'] = $data;

        // 3. Chuẩn bị dữ liệu cho View
        $vars['ingredient_options'] = $app->select("ingredient", ['id(value)', 'code(text)'], ["deleted" => 0, "status" => 'A', "{$stock_column}[>]" => 0]);

        // 4. Tối ưu hóa: Lấy trước thông tin cần thiết cho bảng
        $session_products = $data['ingredient'] ?? [];
        if (!empty($session_products)) {
            $ingredient_ids = array_column($session_products, 'ingredient');
            // Lấy thông tin nguyên liệu và đơn vị trong 1 lần query
            $ingredient_info = $app->select("ingredient", ["[>]units" => ["units" => "id"]], ["ingredient.id", "ingredient.code", "units.name(unit_name)"], ["ingredient.id" => $ingredient_ids]);
            $ingredient_map = array_column($ingredient_info, null, 'id');

            // Gộp dữ liệu đã tối ưu vào mảng session
            foreach ($session_products as &$item) {
                $id = $item['ingredient'];
                $item['code'] = $ingredient_map[$id]['code'] ?? 'N/A';
                $item['unit_name'] = $ingredient_map[$id]['unit_name'] ?? 'N/A';
            }
            unset($item);
        }
        $vars['SelectProducts'] = $session_products;

        echo $app->render($template . '/crafting/export.html', $vars);
    });

    $app->router('/crafting-update/{action}/ingredient/add', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $post_value = $app->xss($_POST['value'] ?? '');

        $data = $app->get("ingredient", "*", ["id" => $post_value]);
        if ($data) {
            $warehouses = 0;
            $group_crafting = $_SESSION['crafting'][$action]['group_crafting'] ?? 1;
            if ($group_crafting == 1)
                $warehouses = $data['crafting'];
            elseif ($group_crafting == 2)
                $warehouses = $data['craftingsilver'];
            elseif ($group_crafting == 3)
                $warehouses = $data['craftingchain'];

            if ($action == 'export' && $warehouses <= 0) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Số lượng không đủ')]);
                return;
            }

            if (!isset($_SESSION['crafting'][$action]['ingredient'][$data['id']])) {
                $_SESSION['crafting'][$action]['ingredient'][$data['id']] = [
                    "ingredient" => $data['id'],
                    "amount" => 1,
                    "price" => $data['price'],
                    "cost" => $data['cost'],
                    "code" => $data['code'],
                    "warehouses" => $warehouses > 0 ? $warehouses : 0,
                ];
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => "Sản phẩm đã dùng"]);
            }
        } else {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại")]);
        }
    });

    $app->router('/crafting-update/{action}/ingredient/deleted/{key}', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $item_key = $vars['key'] ?? 0;
        unset($_SESSION['crafting'][$action]['ingredient'][$item_key]);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/crafting-update/{action}/ingredient/price/{key}', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $item_key = $vars['key'] ?? 0;
        $post_value = $app->xss($_POST['value'] ?? '');
        $_SESSION['crafting'][$action]['ingredient'][$item_key]['price'] = str_replace([','], '', $post_value);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/crafting-update/{action}/ingredient/price/{key}', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $item_key = $vars['key'] ?? 0;
        $post_value = $app->xss($_POST['value'] ?? '');
        $_SESSION['crafting'][$action]['ingredient'][$item_key]['price'] = str_replace([','], '', $post_value);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/crafting-update/{action}/ingredient/amount/{key}', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $item_key = $vars['key'] ?? 0;
        $value = $app->xss($_POST['value'] ?? 0);

        if ($value <= 0) {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Số lượng phải lớn hơn 0")]);
            return;
        }

        if ($action == 'export') {
            $getAmount = $app->get("ingredient", "*", ["id" => $_SESSION['crafting'][$action]['ingredient'][$item_key]['ingredient']]);
            $group_crafting = $_SESSION['crafting'][$action]['group_crafting'] ?? 1;
            $stock = 0;
            if ($group_crafting == 1)
                $stock = $getAmount['crafting'];
            elseif ($group_crafting == 2)
                $stock = $getAmount['craftingsilver'];
            elseif ($group_crafting == 3)
                $stock = $getAmount['craftingchain'];

            if ($value > $stock) {
                $_SESSION['crafting'][$action]['ingredient'][$item_key]['amount'] = $stock;
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Số lượng không đủ')]);
                return;
            }
        }

        $_SESSION['crafting'][$action]['ingredient'][$item_key]['amount'] = $value;
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/crafting-update/{action}/content', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $_SESSION['crafting'][$action]['content'] = $app->xss($_POST['value'] ?? '');
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/crafting-update/{action}/cancel', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        unset($_SESSION['crafting'][$action]);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Đã hủy")]);
    });

    $app->router('/crafting-update/{action}/completed', 'POST', function ($vars) use ($app, $jatbi, $setting) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = $vars['action'] ?? '';
        $data = $_SESSION['crafting'][$action] ?? [];

        $error = [];
        $error_warehouses = false;
        $error_warehouses_details = false;
        $cancel_amount = 0;
        $AmountMinus = false;

        foreach ($data['ingredient'] ?? [] as $value) {
            if (empty($value['amount']) || $value['amount'] <= 0) {
                $error_warehouses = true;
            }
            if ($action == 'cancel') {
                $ingredient_ex = $app->get("warehouses_details", "amount", ["id" => $value['details']]);
                if (($value['amount'] ?? 0) > ($ingredient_ex ?? 0)) {
                    $error_warehouses_details = true;
                }
            }
        }

        if ($action == 'cancel') {
            foreach ($data['warehouses'] ?? [] as $AmounCancel) {
                $cancel_amount += (float) ($AmounCancel['amount'] ?? 0);
                if ((float) ($AmounCancel['amount'] ?? 0) < 0) {
                    $AmountMinus = true;
                }
            }
        }

        if (empty($data['content']) || empty($data['ingredient'])) {
            $error = ["status" => 'error', 'content' => $jatbi->lang("Lỗi trống")];
        } elseif ($error_warehouses) {
            $error = ['status' => 'error', 'content' => $jatbi->lang('Vui lòng nhập số lượng')];
        } elseif ($cancel_amount <= 0 && $action == 'cancel') {
            $error = ["status" => 'error', 'content' => $jatbi->lang('Phải có số lượng hủy')];
        } elseif ($AmountMinus && $action == 'cancel') {
            $error = ["status" => 'error', 'content' => $jatbi->lang('Số lượng không âm')];
        }

        if (empty($error)) {
            $code = ($data['type'] ?? 'import') === 'import' ? 'PN' : 'PX';
            $insert_warehouse = [
                "code" => $code,
                "type" => $action,
                "data" => 'crafting',
                "content" => $data['content'],
                "user" => $app->getSession("accounts")['id'] ?? 0,
                "date" => $data['date'],
                "active" => $jatbi->active(30),
                "date_poster" => date("Y-m-d H:i:s"),
                "group_crafting" => $data['group_crafting'],
            ];
            $app->insert("warehouses", $insert_warehouse);
            $orderId = $app->id();

            if (!empty($data['move'])) {
                $app->update("warehouses", ["receive_status" => 2, "receive_date" => date("Y-m-d H:i:s")], ["id" => $data['move']]);
            }

            $pro_logs = [];
            foreach ($data['ingredient'] as $value) {
                if (($value['amount'] ?? 0) > 0) {
                    $getProducts = $app->get("ingredient", "*", ["id" => $value['ingredient']]);

                    if ($action == 'import') {
                        $update_col = ($data['group_crafting'] == 1) ? "crafting[+]" : (($data['group_crafting'] == 2) ? "craftingsilver[+]" : "craftingchain[+]");
                        $app->update("ingredient", [$update_col => $value['amount']], ["id" => $value['ingredient']]);
                    } elseif ($action == 'export') {
                        $update_col = ($data['group_crafting'] == 1) ? "crafting[-]" : (($data['group_crafting'] == 2) ? "craftingsilver[-]" : "craftingchain[-]");
                        $app->update("ingredient", [$update_col => $value['amount']], ["id" => $value['ingredient']]);
                    }

                    $pro_detail = [
                        "warehouses" => $orderId,
                        "data" => 'crafting',
                        "type" => $action,
                        "ingredient" => $value['ingredient'],
                        "amount" => $value['amount'],
                        "amount_total" => $value['amount'],
                        "price" => $value['price'],
                        "cost" => $value['cost'],
                        "notes" => $value['notes'] ?? '',
                        "date" => date("Y-m-d H:i:s"),
                        "user" => $app->getSession("accounts")['id'] ?? 0,
                        "group_crafting" => $data['group_crafting'],
                    ];
                    $app->insert("warehouses_details", $pro_detail);
                    $detail_id = $app->id();

                    $warehouses_logs = [
                        "type" => $action,
                        "data" => 'crafting',
                        "warehouses" => $orderId,
                        "details" => $detail_id,
                        "ingredient" => $value['ingredient'],
                        "price" => $value['price'],
                        "cost" => $value['cost'],
                        "amount" => $value['amount'],
                        "total" => $value['amount'] * $value['price'],
                        "date" => date('Y-m-d H:i:s'),
                        "user" => $app->getSession("accounts")['id'] ?? 0,
                    ];
                    $app->insert("warehouses_logs", $warehouses_logs);
                    $pro_logs[] = $pro_detail;
                }
            }

            if ($action == 'cancel') {
                foreach ($data['warehouses'] ?? [] as $value) {
                    if (($value['amount'] ?? 0) > 0) {
                        $getwarehouses = $app->get("warehouses_details", "*", ["id" => $value['id'], "deleted" => 0, "amount_total[>]" => 0, "type" => "import", "data" => 'crafting']);
                        $app->update("warehouses_details", [
                            "amount_cancel[+]" => $value['amount'],
                            "amount_total[-]" => $value['amount'],
                        ], ["id" => $getwarehouses['id']]);

                        $pro = [
                            "warehouses" => $orderId,
                            "data" => 'crafting',
                            "type" => $action,
                            "ingredient" => $value['ingredient'],
                            "amount" => $value['amount'],
                            "amount_total" => $value['amount_total'] - $value['amount'],
                            "price" => $value['price'],
                            "cost" => $value['cost'],
                            "notes" => $value['notes'],
                            "date" => date("Y-m-d H:i:s"),
                            "user" => $app->getSession("accounts")['id'] ?? 0,
                        ];
                        $app->insert("warehouses_details", $pro);
                        $pro_logs[] = $pro;
                    }
                }
                $getProducts = $app->get("ingredient", "*", ["id" => $data['ingredient']]);
                $app->update("ingredient", ["amount[-]" => array_sum(array_column($data['warehouses'], 'amount'))], ["id" => $getProducts['id']]);
            }

            $jatbi->logs('warehouses', $action, [$insert_warehouse, $pro_logs, $data]);
            if (!empty($data['crafting'])) {
                $app->update("warehouses", ["export_status" => 2, "export_date" => date("Y-m-d H:i:s")], ["id" => $data['crafting']]);
            }
            unset($_SESSION['crafting'][$action]);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } else {
            echo json_encode($error);
        }
    });

    $app->router('/crafting-update/{action}/ingredient/notes/{key}', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);

        $action = $vars['action'] ?? '';
        $item_key = $vars['key'] ?? 0;
        $post_value = $app->xss($_POST['value'] ?? '');

        $_SESSION['crafting'][$action]['ingredient'][$item_key]['notes'] = $post_value;

        // if (!isset($_SESSION['crafting'][$action]['ingredient'][$item_key]) || !is_array($_SESSION['crafting'][$action]['ingredient'][$item_key])) {
        //     $_SESSION['crafting'][$action]['ingredient'][$item_key] = [];
        // }


        // $_SESSION['crafting'][$action]['ingredient'][$item_key]['notes'] = $post_value;

        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/fixed-history', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $vars['title'] = $jatbi->lang("Lịch sử sửa chế tác");

        if ($app->method() === 'GET') {
            $vars['accounts'] = $app->select("accounts", ["id(value)", "name(text)"], ["deleted" => 0, "status" => 'A']);
            // Thêm các dropdown khác cho bộ lọc nếu cần
            echo $app->render($template . '/crafting/fixed-history.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            // Đọc tham số từ DataTables và bộ lọc
            $draw = intval($_POST['draw'] ?? 0);
            $start = intval($_POST['start'] ?? 0);
            $length = intval($_POST['length'] ?? 10);
            $searchValue = $_POST['search']['value'] ?? '';
            $orderName = $_POST['columns'][intval($_POST['order'][0]['column'] ?? 0)]['name'] ?? 'history_crafting.id';
            $orderDir = $_POST['order'][0]['dir'] ?? 'DESC';

            $filter_user = $_POST['user'] ?? '';
            $filter_date = $_POST['date'] ?? '';
            $filter_group = $_POST['group_crafting'] ?? '';

            // Xây dựng mệnh đề WHERE
            $where = [
                "AND" => [
                    "history_crafting.type" => 2,
                ]
            ];

            if (!empty($searchValue))
                $where['AND']['history_crafting.id[~]'] = $searchValue;
            if (!empty($filter_user))
                $where['AND']['history_crafting.user'] = $filter_user;
            if (!empty($filter_group))
                $where['AND']['history_crafting.group_crafting'] = $filter_group;
            // Kiểm tra xem người dùng có chọn ngày không
            // Process date range
            if ($filter_date) {
                $date = explode('-', $filter_date);
                $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date[0]))));
                $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date[1]))));
            } else {
                $date_from = date('Y-m-01 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }
            $where['AND']['history_crafting.date[<>]'] = [$date_from, $date_to];

            $joins = ["[<]accounts" => ["user" => "id"]];

            // Đếm bản ghi
            $count = $app->count("history_crafting", $joins, "history_crafting.id", $where);

            $where["ORDER"] = [$orderName => strtoupper($orderDir)];
            $where["LIMIT"] = [$start, $length];

            // Lấy dữ liệu
            $columns = ["history_crafting.id", "history_crafting.notes", "history_crafting.date", "accounts.name(user_name)"];
            $datas = $app->select("history_crafting", $joins, $columns, $where);

            $resultData = [];
            foreach ($datas as $data) {
                $resultData[] = [
                    "id" => '<a data-action="modal" data-url="/crafting/fixed-history-views/' . $data['id'] . '/">#' . $data['id'] . '</a>',
                    "notes" => $data['notes'],
                    "date" => $jatbi->datetime($data['date']),
                    "user" => $data['user_name'],
                    "action" => '<button data-action="modal" data-url="/crafting/fixed-history-views/' . $data['id'] . '" class="btn btn-eclo-light btn-sm border-0 py-1 px-2 rounded-3" aria-label="' . $jatbi->lang('Xem') . '"><i class="ti ti-eye"></i></button>',
                ];
            }

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $resultData
            ]);
        }
    });

    $app->router('/fixed-history-views/{id}', 'GET', function ($vars) use ($app, $jatbi, $template) {
        $id = $vars['id'] ?? 0;

        $data = $app->get("history_crafting", [
            "[<]accounts" => ["user" => "id"],
            "[<]products" => ["products" => "id"]
        ], [
            "history_crafting.id",
            "history_crafting.date",
            "history_crafting.notes",
            "history_crafting.code",
            "history_crafting.amount",
            "accounts.name(user_name)",
            "products.code_products(product_code_old)",
        ], ["history_crafting.id" => $id]);

        if (!$data) {
            echo $app->render($template . '/error.html', ['content' => 'Không tìm thấy lịch sử'], $jatbi->ajax());
            return;
        }

        $vars['data'] = $data;
        $vars['title'] = $jatbi->lang('Chi tiết Lịch sử sửa chế tác') . ' #' . $data['id'];

        $details = $app->select("history_crafting_ingredient", [
            "[<]ingredient" => ["ingredient" => "id"],
            "[<]units" => ["ingredient.units" => "id"]
        ], [
            "ingredient.code(ingredient_code)",
            "history_crafting_ingredient.price",
            "history_crafting_ingredient.amount",
            "units.name(unit_name)",
        ], ["history_crafting" => $id]);

        $vars['details'] = $details;

        echo $app->render($template . '/crafting/fixed-history-views.html', $vars, $jatbi->ajax());
    });

    $app->router('/split-history', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $vars['title'] = $jatbi->lang("Lịch sử tách đai ngọc");

        if ($app->method() === 'GET') {
            $vars['accounts'] = $app->select("accounts", ["id(value)", "name(text)"], ["deleted" => 0, "status" => 'A']);
            echo $app->render($template . '/crafting/split-history.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json; charset=utf-8']);

            $draw = intval($_POST['draw'] ?? 0);
            $start = intval($_POST['start'] ?? 0);
            $length = intval($_POST['length'] ?? 10);
            $searchValue = $_POST['search']['value'] ?? '';
            $orderName = $_POST['columns'][intval($_POST['order'][0]['column'] ?? 0)]['name'] ?? 'history_split.id';
            $orderDir = $_POST['order'][0]['dir'] ?? 'DESC';

            $filter_user = $_POST['user'] ?? '';
            $filter_date = $_POST['date'] ?? '';

            $where = [
                "AND" => [
                    "OR" => [
                        "history_split.id[~]" => $searchValue,
                    ],
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)]
            ];

            if (!empty($filter_user))
                $where['AND']['history_split.user'] = $filter_user;


            if ($filter_date) {
                $date = explode('-', $filter_date);
                $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date[0]))));
                $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date[1]))));
            } else {
                $date_from = date('Y-m-01 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }
            $where['AND']['history_split.date[<>]'] = [$date_from, $date_to];

            $joins = [
                "[<]accounts" => ["user" => "id"],
                "[<]products" => ["products" => "id"]
            ];

            $count = $app->count("history_split", ["AND" => $where['AND']]);


            $columns = [
                "history_split.id",
                "history_split.content",
                "history_split.date",
                "accounts.name(user_name)",
                "products.code(product_code)",
                "products.name(product_name)"
            ];
            $datas = $app->select("history_split", $joins, $columns, $where);

            $resultData = [];
            foreach ($datas as $data) {
                $resultData[] = [
                    "id" => '<a data-action="modal" data-url="/crafting/split-history-views/' . $data['id'] . '">#' . $data['id'] . '</a>',
                    "product" => ($data['product_code'] ?? '') . ' - ' . ($data['product_name'] ?? ''),
                    "content" => $data['content'],
                    "date" => $jatbi->datetime($data['date']),
                    "user" => $data['user_name'],
                    "action" => '<button data-action="modal" data-url="/crafting/split-history-views/' . $data['id'] . '" class="btn btn-eclo-light btn-sm border-0 py-1 px-2 rounded-3" aria-label="' . $jatbi->lang('Xem') . '"><i class="ti ti-eye"></i></button>',
                ];
            }

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $resultData
            ]);
        }
    });

    $app->router('/split-history-views/{id}', 'GET', function ($vars) use ($app, $jatbi, $template) {
        $id = $vars['id'] ?? 0;

        $data = $app->get("history_split", [
            "[<]accounts" => ["user" => "id"],
            "[<]products" => ["products" => "id"]
        ], [
            "history_split.id",
            "history_split.date",
            "history_split.content",
            "history_split.amount",
            "accounts.name(user_name)",
            "products.code(product_code)",
        ], ["history_split.id" => $id]);

        if (!$data) {
            echo $app->render($template . '/error.html');
            return;
        }

        $vars['data'] = $data;
        $vars['title'] = $jatbi->lang('Chi tiết Lịch sử tách') . ' #' . $data['id'];

        $details = $app->select("history_split_ingredient", [
            "[<]ingredient" => ["ingredient" => "id"],
            "[<]units" => ["ingredient.units" => "id"]
        ], [
            "ingredient.code(ingredient_code)",
            "history_split_ingredient.price",
            "history_split_ingredient.amount",
            "units.name(unit_name)",
        ], ["history_split" => $id]);
        $vars['details'] = $details;

        echo $app->render($template . '/crafting/split-history-views.html', $vars, $jatbi->ajax());
    });

    $app->router('/pairing-change', 'GET', function ($vars) use ($app, $template) {
        $vars['title'] = "Sửa chế tác";
        $action = "change";
        $pairing = $app->getSession('pairing') ?? [];
        if (empty($pairing[$action]['date'])) {
            $pairing[$action]['date'] = date("Y-m-d H:i:s");
            $app->setSession('pairing', $pairing);
        }
        if (($pairing[$action]['group_crafting'] ?? 0) == 1) {
            $ingredient = $app->select("ingredient", ["id", "code", "type", "crafting"], ["deleted" => 0, "status" => "A", "crafting[>]" => 0]);
        }
        if (($pairing[$action]['group_crafting'] ?? 0) == 2) {
            $ingredient = $app->select("ingredient", ["id", "code", "type", "craftingsilver"], ["deleted" => 0, "status" => "A", "craftingsilver[>]" => 0]);
        }
        if (($pairing[$action]['group_crafting'] ?? 0) == 3) {
            $ingredient = $app->select("ingredient", ["id", "code", "type", "craftingchain"], ["deleted" => 0, "status" => "A", "craftingchain[>]" => 0]);
        }
        $vars['ingredient'] = $ingredient ?? [];
        $vars['SelectProducts'] = $pairing[$action]['ingredient'] ?? [];
        $vars['data'] = [
            "group_crafting" => $pairing[$action]['group_crafting'] ?? '',
            "date" => $pairing[$action]['date'] ?? '',
            "code" => $pairing[$action]['code'] ?? '',
            "content" => $pairing[$action]['content'] ?? '',
            "price" => $pairing[$action]['price'] ?? '',
            "cost" => $pairing[$action]['cost'] ?? '',
            "amount" => $pairing[$action]['amount'] ?? '',
            "name" => $pairing[$action]['name'] ?? '',
            "categorys" => $pairing[$action]['categorys'] ?? '',
            "group" => $pairing[$action]['group'] ?? '',
            "default_code" => $pairing[$action]['default_code'] ?? '',
            "units" => $pairing[$action]['units'] ?? '',
            "personnels" => $pairing[$action]['personnels'] ?? '',
        ];
        $vars['remove_products'] = $app->select("remove_products", ["id", "products"], ["amount[>]" => 0]);
        $vars['groups'] = $app->select("products_group", ['id', 'code', 'name'], ["deleted" => 0, "status" => 'A']);
        $vars['categorys'] = $app->select("categorys", ['id', 'code', 'name'], ["deleted" => 0, "status" => 'A']);
        $vars['default_codes'] = $app->select("default_code", ['id', 'code', 'name'], ["deleted" => 0, "status" => 'A']);
        $vars['units'] = $app->select("units", ['id', 'name'], ["deleted" => 0, "status" => 'A']);
        $vars['personnels'] = $app->select("personnels", ['id', 'code', 'name'], ["deleted" => 0, "status" => 'A']);
        $vars['action'] = $action;

        echo $app->render($template . '/crafting/pairing-change.html', $vars);
    });

    $app->router('/pairing-update-change/change/products', 'POST', function ($vars) use ($app, $jatbi, $setting) {
        $app->header(['Content-Type' => 'application/json']);
        $action = "change";
        $data = $app->get("remove_products", ["products", "price", "category", "group_product", "unit", "name_code", "amount"], ["id" => $app->xss($_POST['value'])]);
        if (!empty($data)) {
            unset($_SESSION['pairing'][$action]);
            $details = $app->select("products_details", "*", ["products" => $data['products'], "deleted" => 0]);
            $_SESSION['pairing'][$action]['price'] = $data['price'];
            $_SESSION['pairing'][$action]['categorys'] = $data['category'];
            $_SESSION['pairing'][$action]['group'] = $data['group_product'];
            $_SESSION['pairing'][$action]['units'] = $data['unit'];
            $_SESSION['pairing'][$action]['default_code'] = $app->get("products", "default_code", ["id" => $data['products']]);
            $_SESSION['pairing'][$action]['name'] = $data['name_code'];
            $_SESSION['pairing'][$action]['products'] = $data['products'];
            $_SESSION['pairing'][$action]['amount'] = $data['amount'];
            foreach ($details as $key => $value) {
                $ingredients = $app->get("ingredient", ['crafting', 'craftingsilver', 'craftingchain'], ["id" => $value["ingredient"]]);
                $group_crafting = isset($_SESSION['pairing'][$action]['group_crafting']) ? $_SESSION['pairing'][$action]['group_crafting'] : 0; // Giá trị mặc định
                if ($group_crafting == 1) {
                    $ingredient = $ingredients['crafting'];
                } elseif ($group_crafting == 2) {
                    $ingredient = $ingredients['craftingsilver'];
                } elseif ($group_crafting == 3) {
                    $ingredient = $ingredients['craftingchain'];
                } else {
                    $ingredient = '';
                }
                if ($value['type'] == 1) {
                    $_SESSION['pairing'][$action]['ingredient']['dai'] = [
                        "products" => $value['products'],
                        "ingredient" => $value['ingredient'],
                        "type" => $value['type'],
                        "amount" => $value['amount'],
                        "code" => $value['code'],
                        "group" => $value['group'],
                        "categorys" => $value['categorys'],
                        "pearl" => $value['pearl'],
                        "sizes" => $value['sizes'],
                        "colors" => $value['colors'],
                        "units" => $value['units'],
                        "price" => $value['price'],
                        "cost" => $value['cost'],
                        "notes" => $value['notes'] ?? "",
                        "warehouses" => $ingredient,
                        "deleted" => 0,
                    ];
                    $_SESSION['pairing'][$action]['ingredient-old']['dai'] = [
                        "products" => $value['products'],
                        "ingredient" => $value['ingredient'],
                        "type" => $value['type'],
                        "amount" => $value['amount'],
                        "code" => $value['code'],
                        "group" => $value['group'],
                        "categorys" => $value['categorys'],
                        "pearl" => $value['pearl'],
                        "sizes" => $value['sizes'],
                        "colors" => $value['colors'],
                        "units" => $value['units'],
                        "price" => $value['price'],
                        "cost" => $value['cost'],
                        "notes" => $value['notes'] ?? "",
                        "warehouses" => $ingredient,
                        "deleted" => 0,
                        "new" => 0,
                    ];
                } else {
                    $_SESSION['pairing'][$action]['ingredient'][$value['ingredient']] = [
                        "products" => $value['products'],
                        "ingredient" => $value['ingredient'],
                        "type" => $value['type'],
                        "amount" => $value['amount'],
                        "code" => $value['code'],
                        "group" => $value['group'],
                        "categorys" => $value['categorys'],
                        "pearl" => $value['pearl'],
                        "sizes" => $value['sizes'],
                        "colors" => $value['colors'],
                        "units" => $value['units'],
                        "price" => $value['price'],
                        "cost" => $value['cost'],
                        "notes" => $value['notes'] ?? "",
                        "warehouses" => $ingredient,
                        "deleted" => 0,
                    ];
                    $_SESSION['pairing'][$action]['ingredient-old'][$value['ingredient']] = [
                        "products" => $value['products'],
                        "ingredient" => $value['ingredient'],
                        "type" => $value['type'],
                        "amount" => $value['amount'],
                        "code" => $value['code'],
                        "group" => $value['group'],
                        "categorys" => $value['categorys'],
                        "pearl" => $value['pearl'],
                        "sizes" => $value['sizes'],
                        "colors" => $value['colors'],
                        "units" => $value['units'],
                        "price" => $value['price'],
                        "cost" => $value['cost'],
                        "notes" => $value['notes'] ?? "",
                        "warehouses" => $ingredient,
                        "deleted" => 0,
                        "new" => 0,
                    ];
                }
            }
            $_SESSION['pairing'][$action]['code'] = trim($jatbi->getcode($_SESSION['pairing'][$action]['ingredient']));
            echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
        } else {
            echo json_encode(['status' => 'error', 'content' => "Cập nhật thất bại"]);
        }
    });

    $app->router('/pairing-update-change/changee/{req}', 'POST', function ($vars) use ($app, $jatbi, $setting) {
        $app->header(['Content-Type' => 'application/json']);
        $action = "change";
        $req = $vars['req'];
        $_SESSION['pairing'][$action][$req] = $app->xss($_POST['value']);
        echo json_encode(['status' => 'success', 'content' => "Cập nhật thành công"]);
    });

    $app->router('/pairing-update-change/change/price', 'POST', function ($vars) use ($app, $jatbi, $setting) {
        $app->header(['Content-Type' => 'application/json']);
        $action = "change";
        $_SESSION['pairing'][$action]['price'] = str_replace(",", "", $app->xss($_POST['value']));
        echo json_encode(['status' => 'success', 'content' => "Cập nhật thành công"]);
    });

    $app->router('/pairing-update-change/change/amount', 'POST', function ($vars) use ($app, $jatbi, $setting) {
        $app->header(['Content-Type' => 'application/json']);
        $action = "change";
        $value = str_replace([','], '', $_POST['value']);
        if ($value < 0 || $value == 0) {
            echo json_encode(['status' => 'error', 'content' => "Số lượng không âm và lớn hơn 0"]);
        } else {
            $produt = $app->get("remove_products", "amount", ["products" => $_SESSION['pairing'][$action]['products']]);
            if ($value > $produt) {
                $_SESSION['pairing'][$action]['amount'] = $produt;
                echo json_encode(['status' => 'error', 'content' => "Số lượng sản phẩm không đủ"]);
            } else {
                $_SESSION['pairing'][$action]['amount'] = $app->xss($value);
                echo json_encode(['status' => 'success', 'content' => "Cập nhật thành công"]);
            }
        }
    });

    $app->router('/pairing-update-change/change/ingredient/add', 'POST', function ($vars) use ($app, $jatbi, $setting) {
        $app->header(['Content-Type' => 'application/json']);
        $action = "change";
        $data = $app->get("ingredient", "*", ["id" => $app->xss($_POST['value'])]);
        if ($data > 1) {
            if ($_SESSION['pairing'][$action]['group_crafting'] == 1) {
                $warehouses = $data['crafting'];
            } elseif ($_SESSION['pairing'][$action]['group_crafting'] == 2) {
                $warehouses = $data['craftingsilver'];
            } elseif ($_SESSION['pairing'][$action]['group_crafting'] == 3) {
                $warehouses = $data['craftingchain'];
            }
            if ($warehouses <= 0) {
                $error = "Số lượng không đủ";
            }
            if (!isset($error)) {
                if (!isset($_SESSION['pairing'][$action]['ingredient'][$data['id']])) {
                    if ($data['type'] == 1) {
                        $_SESSION['pairing'][$action]['ingredient']['dai'] = [
                            "ingredient" => $data['id'],
                            "type" => $data['type'],
                            "amount" => 1,
                            "code" => $data['code'],
                            "group" => $data['group'],
                            "categorys" => $data['categorys'],
                            "pearl" => $data['pearl'],
                            "sizes" => $data['sizes'],
                            "colors" => $data['colors'],
                            "units" => $data['units'],
                            "notes" => $data['notes'],
                            "price" => $data['price'],
                            "cost" => $data['cost'],
                            "warehouses" => $warehouses,
                            "new" => 1,
                        ];
                    } else {
                        $_SESSION['pairing'][$action]['ingredient'][$data['id']] = [
                            "ingredient" => $data['id'],
                            "type" => $data['type'],
                            "amount" => 1,
                            "code" => $data['code'],
                            "group" => $data['group'],
                            "categorys" => $data['categorys'],
                            "pearl" => $data['pearl'],
                            "sizes" => $data['sizes'],
                            "colors" => $data['colors'],
                            "units" => $data['units'],
                            "notes" => $data['notes'],
                            "price" => $data['price'],
                            "cost" => $data['cost'],
                            "warehouses" => $warehouses,
                            "new" => 1,
                        ];
                    }
                    $_SESSION['pairing'][$action]['code'] = trim($jatbi->getcode($_SESSION['pairing'][$action]['ingredient']));
                    echo json_encode(['status' => 'success', 'content' => "Cập nhật thành công"]);
                } else {
                    echo json_encode(['status' => 'error', 'content' => "Sản phẩm đã dùng"]);
                }
            } else {
                echo json_encode(['status' => 'error', 'content' => $error,]);
            }
        } else {
            echo json_encode(['status' => 'error', 'content' => "Cập nhật thất bại"]);
        }
    });

    $app->router('/pairing-update-change/change/ingredient/deleted/{get}', 'POST', function ($vars) use ($app, $jatbi, $setting) {
        $app->header(['Content-Type' => 'application/json']);
        $action = "change";
        $get = $vars['get'];
        $_SESSION['pairing'][$action]['ingredient'][$get]['deleted'] = $get;
        $_SESSION['pairing'][$action]['code'] = trim($jatbi->getcode($_SESSION['pairing'][$action]['ingredient']));
        echo json_encode(['status' => 'success', 'content' => "Cập nhật thành công"]);
    });

    $app->router('/pairing-update-change/change/cancel', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/comfirm-modal.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            unset($_SESSION['pairing']['change']);
            echo json_encode(['status' => 'success', 'content' => "Cập nhật thành công"]);
        }
    });

    $app->router('/pairing-update-change/change/completed', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/comfirm-modal.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $action = "change";
            $data = $_SESSION['pairing'][$action] ?? [];
            $total_products = 0;
            $error_warehouses = '';

            if (!empty($data['ingredient']) && is_array($data['ingredient'])) {
                foreach ($data['ingredient'] as $value) {
                    $total_products += ($value['amount'] ?? 0) * ($value['price'] ?? 0);
                    if (empty($value['amount']) || $value['amount'] == 0 || $value['amount'] < 0) {
                        $error_warehouses = 'true';
                    }
                }
            }

            $check = $app->count("crafting", "id", [
                "code" => $data['code'] ?? '',
                "price[!]" => $data['price'] ?? 0,
                "deleted" => 0,
                "group_crafting" => $data['group_crafting'] ?? ''
            ]);
            $error = [];

            if (empty($data['content'] ?? '') || empty($data['ingredient'] ?? []) || empty($data['group_crafting'] ?? '')) {
                $error = ["status" => 'error', 'content' => "Lỗi trống"];
            } elseif ($check > 0) {
                $error = ["status" => 'error', 'content' => 'Mã sản phẩm này đã có nhưng số tiền không đồng bộ, vui lòng chỉnh thêm biến thiên mã!'];
            } elseif ($error_warehouses == 'true') {
                $error = ['status' => 'error', 'content' => "Vui lòng nhập số lượng"];
            }

            if (count($error) == 0) {
                $check_crafting = $app->get("crafting", ["code", "price", "id", "amount", "amount_total", "group_crafting"], [
                    "code" => $data['code'] ?? '',
                    "price" => $data['price'] ?? 0,
                    "deleted" => 0,
                    "group_crafting" => $data['group_crafting'] ?? ''
                ]);

                if (
                    is_array($check_crafting) &&
                    isset($check_crafting['code'], $check_crafting['price'], $check_crafting['group_crafting']) &&
                    $check_crafting['code'] == ($data['code'] ?? '') &&
                    $check_crafting['price'] == ($data['price'] ?? 0) &&
                    $check_crafting['group_crafting'] == ($data['group_crafting'] ?? '')
                ) {
                    $update_crafting = [
                        "amount" => $check_crafting['amount'] + ($data['amount'] ?? 0),
                        "amount_total" => $check_crafting['amount_total'] + ($data['amount'] ?? 0)
                    ];
                    $app->update("crafting", $update_crafting, ["id" => $check_crafting['id']]);
                    $getID = $check_crafting['id'];

                    $get_remove = $app->get("remove_products", "*", ["products" => $data['products'] ?? 0]);
                    $update_remove = [
                        "amount" => $get_remove["amount"] - ($data["amount"] ?? 0),
                    ];
                    $app->update("remove_products", $update_remove, ["id" => $get_remove["id"]]);

                    $history_crafting = [
                        "code" => $data['code'] ?? '',
                        "name" => $data['name'] ?? '',
                        "notes" => $data['content'] ?? '',
                        "amount" => $data['amount'] ?? 0,
                        "price" => $data['price'] ?? 0,
                        "total" => ($data['amount'] ?? 0) * ($data['price'] ?? 0),
                        "user" => $app->getSession("accounts")['id'] ?? 0,
                        "date" => date("Y-m-d H:i:s"),
                        "personnels" => $data['personnels'] ?? '',
                        "group_crafting" => $data['group_crafting'] ?? '',
                        "crafting" => $check_crafting['id'],
                        "unit" => $data['units'] ?? '',
                        "type" => 2,
                        "products" => $data['products'] ?? 0,
                    ];
                    $app->insert("history_crafting", $history_crafting);
                    $history = $app->id();

                    $code_ware = strtotime(date("Y-m-d H:i:s"));
                    $insert = [
                        "code" => $code_ware,
                        "type" => 'pairing',
                        "data" => 'crafting',
                        "content" => $data['content'] ?? '',
                        "vendor" => isset($data['vendor']['id']) ? $data['vendor']['id'] : 0,
                        "stores" => isset($data['stores']['id']) ? $data['stores']['id'] : 0,
                        "branch" => isset($data['branch']['id']) ? $data['branch']['id'] : 0,
                        "user" => $app->getSession("accounts")['id'] ?? 0,
                        "date" => $data['date'] ?? date("Y-m-d H:i:s"),
                        "active" => $jatbi->active(30),
                        "crafting" => $getID,
                        "date_poster" => date("Y-m-d H:i:s"),
                        "group_crafting" => $data['group_crafting'] ?? '',
                    ];
                    $app->insert("warehouses", $insert);
                    $orderId = $app->id();

                    $insert1 = [
                        "code" => 'PN',
                        "type" => 'import',
                        "data" => 'crafting',
                        "content" => 'Nhập từ sửa chế tác',
                        "vendor" => isset($data['vendor']['id']) ? $data['vendor']['id'] : 0,
                        "stores" => isset($data['stores']['id']) ? $data['stores']['id'] : 0,
                        "branch" => isset($data['branch']['id']) ? $data['branch']['id'] : 0,
                        "user" => $app->getSession("accounts")['id'] ?? 0,
                        "date" => $data['date'] ?? date("Y-m-d H:i:s"),
                        "active" => $jatbi->active(30),
                        "date_poster" => date("Y-m-d H:i:s"),
                        "crafting" => $getID,
                        "group_crafting" => $data['group_crafting'] ?? '',
                    ];
                    $app->insert("warehouses", $insert1);
                    $orderId1 = $app->id();

                    foreach ($data['ingredient'] ?? [] as $key => $value) {
                        $getupdate = '';
                        if (($value['ingredient'] ?? '') != ($data['ingredient-old'][$key]['ingredient'] ?? '')) {
                            $getupdate = "true";
                            $amount = $value['amount'] ?? 0;
                        }
                        if (($value['ingredient'] ?? '') == ($data['ingredient-old'][$key]['ingredient'] ?? '') && ($value['amount'] ?? 0) < ($data['ingredient-old'][$key]['amount'] ?? 0)) {
                            $getupdate = "true";
                            $amount = ($data['ingredient-old'][$key]['amount'] ?? 0) - ($value['amount'] ?? 0);
                        }
                        if (($value['ingredient'] ?? '') == ($data['ingredient-old'][$key]['ingredient'] ?? '') && ($value['amount'] ?? 0) > ($data['ingredient-old'][$key]['amount'] ?? 0)) {
                            $getupdate = "true";
                            $amount = ($value['amount'] ?? 0) - ($data['ingredient-old'][$key]['amount'] ?? 0);
                        }
                        if ($getupdate == "true") {
                            if (($value['ingredient'] ?? '') == ($data['ingredient-old'][$key]['ingredient'] ?? '') && ($value['amount'] ?? 0) < ($data['ingredient-old'][$key]['amount'] ?? 0)) {
                                $pro1 = [
                                    "warehouses" => $orderId1,
                                    "data" => $insert1['data'],
                                    "type" => 'import',
                                    "ingredient" => $data['ingredient-old'][$key]['ingredient'] ?? 0,
                                    "amount" => $amount * ($data['amount'] ?? 0),
                                    "amount_total" => $amount * ($data['amount'] ?? 0),
                                    "price" => $value['price'] ?? 0,
                                    "cost" => $value['cost'] ?? 0,
                                    "notes" => 'Nhập từ sửa chế tác',
                                    "date" => date("Y-m-d H:i:s"),
                                    "user" => $app->getSession("accounts")['id'] ?? 0,
                                    "group_crafting" => $data['group_crafting'] ?? '',
                                ];
                                $app->insert("warehouses_details", $pro1);
                                $iddetails1 = $app->id();
                                $warehouses_logs1 = [
                                    "type" => 'import',
                                    "data" => $insert1['data'],
                                    "warehouses" => $orderId1,
                                    "details" => $iddetails1,
                                    "ingredient" => $data['ingredient-old'][$key]['ingredient'] ?? 0,
                                    "price" => $data['ingredient-old'][$key]['price'] ?? 0,
                                    "cost" => $data['ingredient-old'][$key]['cost'] ?? 0,
                                    "amount" => $amount * ($data['amount'] ?? 0),
                                    "total" => $amount * ($data['amount'] ?? 0) * ($data['ingredient-old'][$key]['price'] ?? 0),
                                    "notes" => 'Nhập từ sửa chế tác',
                                    "date" => date('Y-m-d H:i:s'),
                                    "user" => $app->getSession("accounts")['id'] ?? 0,
                                    "group_crafting" => $data['group_crafting'] ?? '',
                                ];
                                $app->insert("warehouses_logs", $warehouses_logs1);
                                $getProducts = $app->get("ingredient", ["id", "crafting", "craftingchain", "craftingsilver"], ["id" => $value['ingredient'] ?? 0]);
                                if (($data['group_crafting'] ?? '') == 1) {
                                    $app->update("ingredient", ["crafting" => ($getProducts['crafting'] ?? 0) + ($warehouses_logs1['amount'] ?? 0)], ["id" => $getProducts['id'] ?? 0]);
                                } elseif (($data['group_crafting'] ?? '') == 2) {
                                    $app->update("ingredient", ["craftingsilver" => ($getProducts['craftingsilver'] ?? 0) + ($warehouses_logs1['amount'] ?? 0)], ["id" => $getProducts['id'] ?? 0]);
                                } elseif (($data['group_crafting'] ?? '') == 3) {
                                    $app->update("ingredient", ["craftingchain" => ($getProducts['craftingchain'] ?? 0) + ($warehouses_logs1['amount'] ?? 0)], ["id" => $getProducts['id'] ?? 0]);
                                }
                            } elseif (($value['ingredient'] ?? '') == ($data['ingredient-old'][$key]['ingredient'] ?? '') && ($value['amount'] ?? 0) > ($data['ingredient-old'][$key]['amount'] ?? 0)) {
                                if (isset($value['deleted']) && $value['deleted'] == 0) {
                                    $pro = [
                                        "warehouses" => $orderId,
                                        "data" => $insert['data'],
                                        "type" => 'export',
                                        "ingredient" => $value['ingredient'] ?? 0,
                                        "amount" => $amount * ($data['amount'] ?? 0),
                                        "amount_total" => $amount * ($data['amount'] ?? 0),
                                        "price" => $value['price'] ?? 0,
                                        "cost" => $value['cost'] ?? 0,
                                        "notes" => 'Xuất sửa chế tác',
                                        "date" => date("Y-m-d H:i:s"),
                                        "user" => $app->getSession("accounts")['id'] ?? 0,
                                        "group_crafting" => $data['group_crafting'] ?? '',
                                    ];
                                    $app->insert("warehouses_details", $pro);
                                    $iddetails = $app->id();
                                    $warehouses_logs = [
                                        "type" => 'export',
                                        "data" => $insert['data'],
                                        "warehouses" => $orderId,
                                        "details" => $iddetails,
                                        "ingredient" => $value['ingredient'] ?? 0,
                                        "price" => $value['price'] ?? 0,
                                        "cost" => $value['cost'] ?? 0,
                                        "amount" => $amount * ($data['amount'] ?? 0),
                                        "total" => $amount * ($data['amount'] ?? 0) * ($value['price'] ?? 0),
                                        "notes" => 'Xuất sửa chế tác',
                                        "date" => date('Y-m-d H:i:s'),
                                        "user" => $app->getSession("accounts")['id'] ?? 0,
                                        "group_crafting" => $data['group_crafting'] ?? '',
                                    ];
                                    $app->insert("warehouses_logs", $warehouses_logs);
                                }
                                $getProducts = $app->get("ingredient", ["id", "crafting", "craftingchain", "craftingsilver"], ["id" => $value['ingredient'] ?? 0]);
                                if (($data['group_crafting'] ?? '') == 1) {
                                    $app->update("ingredient", ["crafting" => ($getProducts['crafting'] ?? 0) - ($warehouses_logs['amount'] ?? 0)], ["id" => $getProducts['id'] ?? 0]);
                                } elseif (($data['group_crafting'] ?? '') == 2) {
                                    $app->update("ingredient", ["craftingsilver" => ($getProducts['craftingsilver'] ?? 0) - ($warehouses_logs['amount'] ?? 0)], ["id" => $getProducts['id'] ?? 0]);
                                } elseif (($data['group_crafting'] ?? '') == 3) {
                                    $app->update("ingredient", ["craftingchain" => ($getProducts['craftingchain'] ?? 0) - ($warehouses_logs['amount'] ?? 0)], ["id" => $getProducts['id'] ?? 0]);
                                }
                            } elseif (($value['ingredient'] ?? '') != ($data['ingredient-old'][$key]['ingredient'] ?? '') && (isset($value['deleted']) && $value['deleted'] == 0)) {
                                $pro = [
                                    "warehouses" => $orderId,
                                    "data" => $insert['data'],
                                    "type" => 'export',
                                    "ingredient" => $value['ingredient'] ?? 0,
                                    "amount" => $amount * ($data['amount'] ?? 0),
                                    "amount_total" => $amount * ($data['amount'] ?? 0),
                                    "price" => $value['price'] ?? 0,
                                    "cost" => $value['cost'] ?? 0,
                                    "notes" => 'Xuất sửa chế tác',
                                    "date" => date("Y-m-d H:i:s"),
                                    "user" => $app->getSession("accounts")['id'] ?? 0,
                                    "group_crafting" => $data['group_crafting'] ?? '',
                                ];
                                $app->insert("warehouses_details", $pro);
                                $iddetails = $app->id();
                                $warehouses_logs = [
                                    "type" => 'export',
                                    "data" => $insert['data'],
                                    "warehouses" => $orderId,
                                    "details" => $iddetails,
                                    "ingredient" => $value['ingredient'] ?? 0,
                                    "price" => $value['price'] ?? 0,
                                    "cost" => $value['cost'] ?? 0,
                                    "amount" => $amount * ($data['amount'] ?? 0),
                                    "total" => $amount * ($data['amount'] ?? 0) * ($value['price'] ?? 0),
                                    "notes" => 'Xuất sửa chế tác',
                                    "date" => date('Y-m-d H:i:s'),
                                    "user" => $app->getSession("accounts")['id'] ?? 0,
                                    "group_crafting" => $data['group_crafting'] ?? '',
                                ];
                                $app->insert("warehouses_logs", $warehouses_logs);
                                $pro1 = [
                                    "warehouses" => $orderId1,
                                    "data" => $insert['data'],
                                    "type" => 'import',
                                    "ingredient" => $data['ingredient-old'][$key]['ingredient'] ?? 0,
                                    "amount" => ($data['ingredient-old'][$key]['amount'] ?? 0) * ($data['amount'] ?? 0),
                                    "amount_total" => ($data['ingredient-old'][$key]['amount'] ?? 0) * ($data['amount'] ?? 0),
                                    "price" => $value['price'] ?? 0,
                                    "cost" => $value['cost'] ?? 0,
                                    "notes" => 'Nhập từ sửa chế tác',
                                    "date" => date("Y-m-d H:i:s"),
                                    "user" => $app->getSession("accounts")['id'] ?? 0,
                                    "group_crafting" => $data['group_crafting'] ?? '',
                                ];
                                $app->insert("warehouses_details", $pro1);
                                $iddetails1 = $app->id();
                                $warehouses_logs1 = [
                                    "type" => 'import',
                                    "data" => $insert['data'],
                                    "warehouses" => $orderId1,
                                    "details" => $iddetails1,
                                    "ingredient" => $data['ingredient-old'][$key]['ingredient'] ?? 0,
                                    "price" => $data['ingredient-old'][$key]['price'] ?? 0,
                                    "cost" => $data['ingredient-old'][$key]['cost'] ?? 0,
                                    "amount" => ($data['ingredient-old'][$key]['amount'] ?? 0) * ($data['amount'] ?? 0),
                                    "total" => ($data['ingredient-old'][$key]['amount'] ?? 0) * ($data['amount'] ?? 0) * ($data['ingredient-old'][$key]['price'] ?? 0),
                                    "notes" => 'Nhập từ sửa chế tác',
                                    "date" => date('Y-m-d H:i:s'),
                                    "user" => $app->getSession("accounts")['id'] ?? 0,
                                    "group_crafting" => $data['group_crafting'] ?? '',
                                ];
                                $app->insert("warehouses_logs", $warehouses_logs1);
                                $getProducts = $app->get("ingredient", ["id", "crafting", "craftingchain", "craftingsilver"], ["id" => $data['ingredient-old'][$key]['ingredient'] ?? 0]);
                                $getingredients = $app->get("ingredient", ["id", "crafting", "craftingchain", "craftingsilver"], ["id" => $value['ingredient'] ?? 0]);
                                if (($data['group_crafting'] ?? '') == 1) {
                                    $app->update("ingredient", ["crafting" => ($getProducts['crafting'] ?? 0) + ($warehouses_logs1['amount'] ?? 0)], ["id" => $getProducts['id'] ?? 0]);
                                    $app->update("ingredient", ["crafting" => ($getingredients['crafting'] ?? 0) - ($warehouses_logs['amount'] ?? 0)], ["id" => $getingredients['id'] ?? 0]);
                                } elseif (($data['group_crafting'] ?? '') == 2) {
                                    $app->update("ingredient", ["craftingsilver" => ($getProducts['craftingsilver'] ?? 0) + ($warehouses_logs1['amount'] ?? 0)], ["id" => $getProducts['id'] ?? 0]);
                                    $app->update("ingredient", ["craftingsilver" => ($getingredients['craftingsilver'] ?? 0) - ($warehouses_logs['amount'] ?? 0)], ["id" => $getingredients['id'] ?? 0]);
                                } elseif (($data['group_crafting'] ?? '') == 3) {
                                    $app->update("ingredient", ["craftingchain" => ($getProducts['craftingchain'] ?? 0) + ($warehouses_logs1['amount'] ?? 0)], ["id" => $getProducts['id'] ?? 0]);
                                    $app->update("ingredient", ["craftingchain" => ($getingredients['craftingchain'] ?? 0) - ($warehouses_logs['amount'] ?? 0)], ["id" => $getingredients['id'] ?? 0]);
                                }
                            }
                        }
                        if (($value['ingredient'] ?? '') == ($data['ingredient-old'][$key]['ingredient'] ?? '') && (!isset($value['deleted']) || $value['deleted'] != 0)) {
                            $pro1 = [
                                "warehouses" => $orderId1,
                                "data" => $insert['data'],
                                "type" => 'import',
                                "ingredient" => $data['ingredient-old'][$key]['ingredient'] ?? 0,
                                "amount" => ($data['ingredient-old'][$key]['amount'] ?? 0) * ($data['amount'] ?? 0),
                                "amount_total" => ($data['ingredient-old'][$key]['amount'] ?? 0) * ($data['amount'] ?? 0),
                                "price" => $data['ingredient-old'][$key]['price'] ?? 0,
                                "cost" => $data['ingredient-old'][$key]['cost'] ?? 0,
                                "notes" => 'Nhập từ sửa chế tác',
                                "date" => date("Y-m-d H:i:s"),
                                "user" => $app->getSession("accounts")['id'] ?? 0,
                                "group_crafting" => $data['group_crafting'] ?? '',
                            ];
                            $app->insert("warehouses_details", $pro1);
                            $iddetails1 = $app->id();
                            $warehouses_logs1 = [
                                "type" => 'import',
                                "data" => $insert['data'],
                                "warehouses" => $orderId1,
                                "details" => $iddetails1,
                                "ingredient" => $data['ingredient-old'][$key]['ingredient'] ?? 0,
                                "price" => $data['ingredient-old'][$key]['price'] ?? 0,
                                "cost" => $data['ingredient-old'][$key]['cost'] ?? 0,
                                "amount" => ($data['ingredient-old'][$key]['amount'] ?? 0) * ($data['amount'] ?? 0),
                                "total" => ($data['ingredient-old'][$key]['amount'] ?? 0) * ($data['amount'] ?? 0) * ($data['ingredient-old'][$key]['price'] ?? 0),
                                "notes" => 'Nhập từ sửa chế tác',
                                "date" => date('Y-m-d H:i:s'),
                                "user" => $app->getSession("accounts")['id'] ?? 0,
                                "group_crafting" => $data['group_crafting'] ?? '',
                            ];
                            $app->insert("warehouses_logs", $warehouses_logs1);
                            $getProducts1 = $app->get("ingredient", ["id", "crafting", "craftingchain", "craftingsilver"], ["id" => $value['ingredient'] ?? 0]);
                            if (($data['group_crafting'] ?? '') == 1) {
                                $app->update("ingredient", ["crafting" => ($getProducts1['crafting'] ?? 0) + ($warehouses_logs1['amount'] ?? 0)], ["id" => $getProducts1['id'] ?? 0]);
                            } elseif (($data['group_crafting'] ?? '') == 2) {
                                $app->update("ingredient", ["craftingsilver" => ($getProducts1['craftingsilver'] ?? 0) + ($warehouses_logs1['amount'] ?? 0)], ["id" => $getProducts1['id'] ?? 0]);
                            } elseif (($data['group_crafting'] ?? '') == 3) {
                                $app->update("ingredient", ["craftingchain" => ($getProducts1['craftingchain'] ?? 0) + ($warehouses_logs1['amount'] ?? 0)], ["id" => $getProducts1['id'] ?? 0]);
                            }
                        }
                        if (isset($value['deleted']) && $value['deleted'] == 0) {
                            $crafting_details = [
                                "crafting" => $getID,
                                "ingredient" => $value['ingredient'] ?? 0,
                                "code" => $value['code'] ?? '',
                                "type" => $value['type'] ?? 0,
                                "group" => $value['group'] ?? '',
                                "categorys" => $value['categorys'] ?? '',
                                "pearl" => $value['pearl'] ?? '',
                                "sizes" => $value['sizes'] ?? '',
                                "colors" => $value['colors'] ?? '',
                                "units" => $value['units'] ?? '',
                                "amount" => $value['amount'] ?? 0,
                                "price" => $value['price'] ?? 0,
                                "cost" => $value['cost'] ?? 0,
                                "total" => ($value['price'] ?? 0) * ($value['amount'] ?? 0),
                                "user" => $app->getSession("accounts")['id'] ?? 0,
                                "date" => date("Y-m-d H:i:s"),
                            ];
                            $app->insert("crafting_details", $crafting_details);
                            $pro_logs[] = $crafting_details;
                            $history_crafting_ingredient = [
                                "history_crafting" => $history,
                                "crafting" => $crafting_details['crafting'],
                                "ingredient" => $value['ingredient'] ?? 0,
                                "code" => $value['code'] ?? '',
                                "type" => $value['type'] ?? 0,
                                "amount" => $value['amount'] ?? 0,
                                "price" => $value['price'] ?? 0,
                                "total" => ($value['amount'] ?? 0) * ($value['price'] ?? 0),
                                "user" => $app->getSession("accounts")['id'] ?? 0,
                                "date" => date("Y-m-d H:i:s"),
                            ];
                            $app->insert("history_crafting_ingredient", $history_crafting_ingredient);
                        }
                    }
                } else {
                    $crafting = [
                        "code" => $data['code'] ?? '',
                        "name" => $data['name'] ?? '',
                        "content" => $data['content'] ?? '',
                        "notes" => $data['notes'] ?? '',
                        "amount" => $data['amount'] ?? 0,
                        "amount_total" => $data['amount'] ?? 0,
                        "price" => $data['price'] ?? 0,
                        "cost" => $data['cost'] ?? 0,
                        "total" => ($data['amount'] ?? 0) * ($data['price'] ?? 0),
                        "categorys" => $data['categorys'] ?? '',
                        "group" => $data['group'] ?? '',
                        "units" => $data['units'] ?? '',
                        "user" => $app->getSession("accounts")['id'] ?? 0,
                        "date" => date("Y-m-d H:i:s"),
                        "status" => 1,
                        "type" => 1,
                        "products" => $data['products'] ?? 0,
                        "group_crafting" => $data['group_crafting'] ?? '',
                        "default_code" => $data['default_code'] ?? '',
                    ];
                    $app->insert("crafting", $crafting);
                    $getID = $app->id();
                    $history_crafting = [
                        "code" => $data['code'] ?? '',
                        "name" => $data['name'] ?? '',
                        "notes" => $data['content'] ?? '',
                        "amount" => $data['amount'] ?? 0,
                        "price" => $data['price'] ?? 0,
                        "total" => ($data['amount'] ?? 0) * ($data['price'] ?? 0),
                        "user" => $app->getSession("accounts")['id'] ?? 0,
                        "date" => date("Y-m-d H:i:s"),
                        "personnels" => $data['personnels'] ?? '',
                        "group_crafting" => $data['group_crafting'] ?? '',
                        "crafting" => $getID,
                        "unit" => $data['units'] ?? '',
                        "type" => 2,
                        "products" => $data['products'] ?? 0,
                    ];
                    $app->insert("history_crafting", $history_crafting);
                    $history = $app->id();
                    $get_remove = $app->get("remove_products", "*", ["products" => $crafting["products"] ?? 0]);
                    $update_remove = [
                        "amount" => ($get_remove["amount"] ?? 0) - ($crafting["amount"] ?? 0),
                    ];
                    $app->update("remove_products", $update_remove, ["id" => $get_remove["id"] ?? 0]);
                    $code_ware = strtotime(date("Y-m-d H:i:s"));
                    $insert = [
                        "code" => $code_ware,
                        "type" => 'pairing',
                        "data" => 'crafting',
                        "content" => $data['content'] ?? '',
                        "vendor" => isset($data['vendor']['id']) ? $data['vendor']['id'] : 0,
                        "stores" => isset($data['stores']['id']) ? $data['stores']['id'] : 0,
                        "branch" => isset($data['branch']['id']) ? $data['branch']['id'] : 0,
                        "user" => $app->getSession("accounts")['id'] ?? 0,
                        "date" => $data['date'] ?? date("Y-m-d H:i:s"),
                        "active" => $jatbi->active(30),
                        "crafting" => $getID,
                        "date_poster" => date("Y-m-d H:i:s"),
                        "group_crafting" => $data['group_crafting'] ?? '',
                    ];
                    $app->insert("warehouses", $insert);
                    $orderId = $app->id();
                    $insert1 = [
                        "code" => 'PN',
                        "type" => 'import',
                        "data" => 'crafting',
                        "content" => 'Nhập từ sửa chế tác',
                        "vendor" => isset($data['vendor']['id']) ? $data['vendor']['id'] : 0,
                        "stores" => isset($data['stores']['id']) ? $data['stores']['id'] : 0,
                        "branch" => isset($data['branch']['id']) ? $data['branch']['id'] : 0,
                        "user" => $app->getSession("accounts")['id'] ?? 0,
                        "date" => $data['date'] ?? date("Y-m-d H:i:s"),
                        "active" => $jatbi->active(30),
                        "date_poster" => date("Y-m-d H:i:s"),
                        "crafting" => $getID,
                        "group_crafting" => $data['group_crafting'] ?? '',
                    ];
                    $app->insert("warehouses", $insert1);
                    $orderId1 = $app->id();
                    foreach ($data['ingredient'] ?? [] as $key => $value) {
                        $getupdate = '';
                        if (($value['ingredient'] ?? '') != ($data['ingredient-old'][$key]['ingredient'] ?? '')) {
                            $getupdate = "true";
                            $amount = $value['amount'] ?? 0;
                        }
                        if (($value['ingredient'] ?? '') == ($data['ingredient-old'][$key]['ingredient'] ?? '') && ($value['amount'] ?? 0) < ($data['ingredient-old'][$key]['amount'] ?? 0)) {
                            $getupdate = "true";
                            $amount = ($data['ingredient-old'][$key]['amount'] ?? 0) - ($value['amount'] ?? 0);
                        }
                        if (($value['ingredient'] ?? '') == ($data['ingredient-old'][$key]['ingredient'] ?? '') && ($value['amount'] ?? 0) > ($data['ingredient-old'][$key]['amount'] ?? 0)) {
                            $getupdate = "true";
                            $amount = ($value['amount'] ?? 0) - ($data['ingredient-old'][$key]['amount'] ?? 0);
                        }
                        if ($getupdate == "true") {
                            if (($value['ingredient'] ?? '') == ($data['ingredient-old'][$key]['ingredient'] ?? '') && ($value['amount'] ?? 0) < ($data['ingredient-old'][$key]['amount'] ?? 0)) {
                                $pro1 = [
                                    "warehouses" => $orderId1,
                                    "data" => $insert1['data'],
                                    "type" => 'import',
                                    "ingredient" => $data['ingredient-old'][$key]['ingredient'] ?? 0,
                                    "amount" => $amount * ($data['amount'] ?? 0),
                                    "amount_total" => $amount * ($data['amount'] ?? 0),
                                    "price" => $value['price'] ?? 0,
                                    "cost" => $value['cost'] ?? 0,
                                    "notes" => 'Nhập từ sửa chế tác',
                                    "date" => date("Y-m-d H:i:s"),
                                    "user" => $app->getSession("accounts")['id'] ?? 0,
                                    "group_crafting" => $data['group_crafting'] ?? '',
                                ];
                                $app->insert("warehouses_details", $pro1);
                                $iddetails1 = $app->id();
                                $warehouses_logs1 = [
                                    "type" => 'import',
                                    "data" => $insert1['data'],
                                    "warehouses" => $orderId1,
                                    "details" => $iddetails1,
                                    "ingredient" => $data['ingredient-old'][$key]['ingredient'] ?? 0,
                                    "price" => $data['ingredient-old'][$key]['price'] ?? 0,
                                    "cost" => $data['ingredient-old'][$key]['cost'] ?? 0,
                                    "amount" => $amount * ($data['amount'] ?? 0),
                                    "total" => $amount * ($data['amount'] ?? 0) * ($data['ingredient-old'][$key]['price'] ?? 0),
                                    "notes" => 'Nhập từ sửa chế tác',
                                    "date" => date('Y-m-d H:i:s'),
                                    "user" => $app->getSession("accounts")['id'] ?? 0,
                                    "group_crafting" => $data['group_crafting'] ?? '',
                                ];
                                $app->insert("warehouses_logs", $warehouses_logs1);
                                $getProducts = $app->get("ingredient", ["id", "crafting", "craftingchain", "craftingsilver"], ["id" => $value['ingredient'] ?? 0]);
                                if (($data['group_crafting'] ?? '') == 1) {
                                    $app->update("ingredient", ["crafting" => ($getProducts['crafting'] ?? 0) + ($warehouses_logs1['amount'] ?? 0)], ["id" => $getProducts['id'] ?? 0]);
                                } elseif (($data['group_crafting'] ?? '') == 2) {
                                    $app->update("ingredient", ["craftingsilver" => ($getProducts['craftingsilver'] ?? 0) + ($warehouses_logs1['amount'] ?? 0)], ["id" => $getProducts['id'] ?? 0]);
                                } elseif (($data['group_crafting'] ?? '') == 3) {
                                    $app->update("ingredient", ["craftingchain" => ($getProducts['craftingchain'] ?? 0) + ($warehouses_logs1['amount'] ?? 0)], ["id" => $getProducts['id'] ?? 0]);
                                }
                            } elseif (($value['ingredient'] ?? '') == ($data['ingredient-old'][$key]['ingredient'] ?? '') && ($value['amount'] ?? 0) > ($data['ingredient-old'][$key]['amount'] ?? 0)) {
                                if (isset($value['deleted']) && $value['deleted'] == 0) {
                                    $pro = [
                                        "warehouses" => $orderId,
                                        "data" => $insert['data'],
                                        "type" => 'export',
                                        "ingredient" => $value['ingredient'] ?? 0,
                                        "amount" => $amount * ($data['amount'] ?? 0),
                                        "amount_total" => $amount * ($data['amount'] ?? 0),
                                        "price" => $value['price'] ?? 0,
                                        "cost" => $value['cost'] ?? 0,
                                        "notes" => 'Xuất sửa chế tác',
                                        "date" => date("Y-m-d H:i:s"),
                                        "user" => $app->getSession("accounts")['id'] ?? 0,
                                        "group_crafting" => $data['group_crafting'] ?? '',
                                    ];
                                    $app->insert("warehouses_details", $pro);
                                    $iddetails = $app->id();
                                    $warehouses_logs = [
                                        "type" => 'export',
                                        "data" => $insert['data'],
                                        "warehouses" => $orderId,
                                        "details" => $iddetails,
                                        "ingredient" => $value['ingredient'] ?? 0,
                                        "price" => $value['price'] ?? 0,
                                        "cost" => $value['cost'] ?? 0,
                                        "amount" => $amount * ($data['amount'] ?? 0),
                                        "total" => $amount * ($data['amount'] ?? 0) * ($value['price'] ?? 0),
                                        "notes" => 'Xuất sửa chế tác',
                                        "date" => date('Y-m-d H:i:s'),
                                        "user" => $app->getSession("accounts")['id'] ?? 0,
                                        "group_crafting" => $data['group_crafting'] ?? '',
                                    ];
                                    $app->insert("warehouses_logs", $warehouses_logs);
                                }
                                $getProducts = $app->get("ingredient", ["id", "crafting", "craftingchain", "craftingsilver"], ["id" => $value['ingredient'] ?? 0]);
                                if (($data['group_crafting'] ?? '') == 1) {
                                    $app->update("ingredient", ["crafting" => ($getProducts['crafting'] ?? 0) - ($warehouses_logs['amount'] ?? 0)], ["id" => $getProducts['id'] ?? 0]);
                                } elseif (($data['group_crafting'] ?? '') == 2) {
                                    $app->update("ingredient", ["craftingsilver" => ($getProducts['craftingsilver'] ?? 0) - ($warehouses_logs['amount'] ?? 0)], ["id" => $getProducts['id'] ?? 0]);
                                } elseif (($data['group_crafting'] ?? '') == 3) {
                                    $app->update("ingredient", ["craftingchain" => ($getProducts['craftingchain'] ?? 0) - ($warehouses_logs['amount'] ?? 0)], ["id" => $getProducts['id'] ?? 0]);
                                }
                            } elseif (($value['ingredient'] ?? '') != ($data['ingredient-old'][$key]['ingredient'] ?? '') && (isset($value['deleted']) && $value['deleted'] == 0)) {
                                $pro = [
                                    "warehouses" => $orderId,
                                    "data" => $insert['data'],
                                    "type" => 'export',
                                    "ingredient" => $value['ingredient'] ?? 0,
                                    "amount" => $amount * ($data['amount'] ?? 0),
                                    "amount_total" => $amount * ($data['amount'] ?? 0),
                                    "price" => $value['price'] ?? 0,
                                    "cost" => $value['cost'] ?? 0,
                                    "notes" => 'Xuất sửa chế tác',
                                    "date" => date("Y-m-d H:i:s"),
                                    "user" => $app->getSession("accounts")['id'] ?? 0,
                                    "group_crafting" => $data['group_crafting'] ?? '',
                                ];
                                $app->insert("warehouses_details", $pro);
                                $iddetails = $app->id();
                                $warehouses_logs = [
                                    "type" => 'export',
                                    "data" => $insert['data'],
                                    "warehouses" => $orderId,
                                    "details" => $iddetails,
                                    "ingredient" => $value['ingredient'] ?? 0,
                                    "price" => $value['price'] ?? 0,
                                    "cost" => $value['cost'] ?? 0,
                                    "amount" => $amount * ($data['amount'] ?? 0),
                                    "total" => $amount * ($data['amount'] ?? 0) * ($value['price'] ?? 0),
                                    "notes" => 'Xuất sửa chế tác',
                                    "date" => date('Y-m-d H:i:s'),
                                    "user" => $app->getSession("accounts")['id'] ?? 0,
                                    "group_crafting" => $data['group_crafting'] ?? '',
                                ];
                                $app->insert("warehouses_logs", $warehouses_logs);
                                $pro1 = [
                                    "warehouses" => $orderId1,
                                    "data" => $insert['data'],
                                    "type" => 'import',
                                    "ingredient" => $data['ingredient-old'][$key]['ingredient'] ?? 0,
                                    "amount" => ($data['ingredient-old'][$key]['amount'] ?? 0) * ($data['amount'] ?? 0),
                                    "amount_total" => ($data['ingredient-old'][$key]['amount'] ?? 0) * ($data['amount'] ?? 0),
                                    "price" => $value['price'] ?? 0,
                                    "cost" => $value['cost'] ?? 0,
                                    "notes" => 'Nhập từ sửa chế tác',
                                    "date" => date("Y-m-d H:i:s"),
                                    "user" => $app->getSession("accounts")['id'] ?? 0,
                                    "group_crafting" => $data['group_crafting'] ?? '',
                                ];
                                $app->insert("warehouses_details", $pro1);
                                $iddetails1 = $app->id();
                                $warehouses_logs1 = [
                                    "type" => 'import',
                                    "data" => $insert['data'],
                                    "warehouses" => $orderId1,
                                    "details" => $iddetails1,
                                    "ingredient" => $data['ingredient-old'][$key]['ingredient'] ?? 0,
                                    "price" => $data['ingredient-old'][$key]['price'] ?? 0,
                                    "cost" => $data['ingredient-old'][$key]['cost'] ?? 0,
                                    "amount" => ($data['ingredient-old'][$key]['amount'] ?? 0) * ($data['amount'] ?? 0),
                                    "total" => ($data['ingredient-old'][$key]['amount'] ?? 0) * ($data['amount'] ?? 0) * ($data['ingredient-old'][$key]['price'] ?? 0),
                                    "notes" => 'Nhập từ sửa chế tác',
                                    "date" => date('Y-m-d H:i:s'),
                                    "user" => $app->getSession("accounts")['id'] ?? 0,
                                    "group_crafting" => $data['group_crafting'] ?? '',
                                ];
                                $app->insert("warehouses_logs", $warehouses_logs1);
                                $getProducts = $app->get("ingredient", ["id", "crafting", "craftingchain", "craftingsilver"], ["id" => $data['ingredient-old'][$key]['ingredient'] ?? 0]);
                                $getingredients = $app->get("ingredient", ["id", "crafting", "craftingchain", "craftingsilver"], ["id" => $value['ingredient'] ?? 0]);
                                if (($data['group_crafting'] ?? '') == 1) {
                                    $app->update("ingredient", ["crafting" => ($getProducts['crafting'] ?? 0) + ($warehouses_logs1['amount'] ?? 0)], ["id" => $getProducts['id'] ?? 0]);
                                    $app->update("ingredient", ["crafting" => ($getingredients['crafting'] ?? 0) - ($warehouses_logs['amount'] ?? 0)], ["id" => $getingredients['id'] ?? 0]);
                                } elseif (($data['group_crafting'] ?? '') == 2) {
                                    $app->update("ingredient", ["craftingsilver" => ($getProducts['craftingsilver'] ?? 0) + ($warehouses_logs1['amount'] ?? 0)], ["id" => $getProducts['id'] ?? 0]);
                                    $app->update("ingredient", ["craftingsilver" => ($getingredients['craftingsilver'] ?? 0) - ($warehouses_logs['amount'] ?? 0)], ["id" => $getingredients['id'] ?? 0]);
                                } elseif (($data['group_crafting'] ?? '') == 3) {
                                    $app->update("ingredient", ["craftingchain" => ($getProducts['craftingchain'] ?? 0) + ($warehouses_logs1['amount'] ?? 0)], ["id" => $getProducts['id'] ?? 0]);
                                    $app->update("ingredient", ["craftingchain" => ($getingredients['craftingchain'] ?? 0) - ($warehouses_logs['amount'] ?? 0)], ["id" => $getingredients['id'] ?? 0]);
                                }
                            }
                        }
                        if (isset($value['deleted']) && $value['deleted'] == 0) {
                            $crafting_details = [
                                "crafting" => $getID,
                                "ingredient" => $value['ingredient'] ?? 0,
                                "code" => $value['code'] ?? '',
                                "type" => $value['type'] ?? 0,
                                "group" => $value['group'] ?? '',
                                "categorys" => $value['categorys'] ?? '',
                                "pearl" => $value['pearl'] ?? '',
                                "sizes" => $value['sizes'] ?? '',
                                "colors" => $value['colors'] ?? '',
                                "units" => $value['units'] ?? '',
                                "amount" => $value['amount'] ?? 0,
                                "price" => $value['price'] ?? 0,
                                "cost" => $value['cost'] ?? 0,
                                "total" => ($value['price'] ?? 0) * ($value['amount'] ?? 0),
                                "user" => $app->getSession("accounts")['id'] ?? 0,
                                "date" => date("Y-m-d H:i:s"),
                            ];
                            $app->insert("crafting_details", $crafting_details);
                            $pro_logs[] = $crafting_details;
                            $history_crafting_ingredient = [
                                "history_crafting" => $history,
                                "crafting" => $crafting_details['crafting'],
                                "ingredient" => $value['ingredient'] ?? 0,
                                "code" => $value['code'] ?? '',
                                "type" => $value['type'] ?? 0,
                                "amount" => $value['amount'] ?? 0,
                                "price" => $value['price'] ?? 0,
                                "total" => ($value['amount'] ?? 0) * ($value['price'] ?? 0),
                                "user" => $app->getSession("accounts")['id'] ?? 0,
                                "date" => date("Y-m-d H:i:s"),
                            ];
                            $app->insert("history_crafting_ingredient", $history_crafting_ingredient);
                        }
                    }
                }
                $jatbi->logs('pairing', $action, [$crafting, isset($update_crafting) ? $update_crafting : [], $_SESSION['pairing'][$action] ?? []]);
                unset($_SESSION['pairing'][$action]);
                echo json_encode(['status' => 'success', 'content' => "Cập nhật thành công"]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $error['content']]);
            }
        }
    });

    $app->router("/crafting-split", 'GET', function ($vars) use ($app, $jatbi, $template) {

        $action = "split";
        $vars['title'] = $jatbi->lang("Tách dây ngọc");
        $vars['action'] = $action;

        // --- BƯỚC 1: XỬ LÝ SESSION ---
        if (empty($_SESSION['split'][$action]['group_crafting'])) {
            $_SESSION['split'][$action]['group_crafting'] = 1;
        }
        $vars['session_data'] = $_SESSION['split'][$action] ?? [];


        $remove_products_db = $app->select(
            "remove_products",
            ["[>]products" => ["products" => "id"]],
            ["remove_products.id(value)", "text" => Medoo\Medoo::raw("CONCAT(products.code, ' - ', products.name)")],
            ["remove_products.amount[>]" => 0]
        );
        $vars['remove_products_options'] = $remove_products_db;

        // --- BƯỚC 3: TỐI ƯU HÓA - LẤY VÀ XỬ LÝ DỮ LIỆU CHO BẢNG ---
        $session_products = $_SESSION['split'][$action]['products'] ?? [];
        $enriched_products = [];

        if (!empty($session_products)) {
            $product_ids = array_column($session_products, 'products');

            // Lấy thông tin sản phẩm chính và đơn vị trong 1 lần query
            $main_products_info = $app->select(
                "products",
                ["[>]units" => ["units" => "id"]],
                ["products.id", "products.code", "products.name", "units.name(unit_name)"],
                ["products.id" => $product_ids]
            );
            $main_products_map = array_column($main_products_info, null, 'id');

            // Lấy tất cả nguyên liệu của tất cả sản phẩm trong 1 lần query
            $all_materials = $app->select("products_details", "*", ["products" => $product_ids, "deleted" => 0]);
            $materials_by_product = [];
            foreach ($all_materials as $material) {
                $materials_by_product[$material['products']][] = $material;
            }

            // Gộp dữ liệu lại
            foreach ($session_products as $key => $item) {
                $p_id = $item['products'];
                if (isset($main_products_map[$p_id])) {
                    $enriched_products[$key] = array_merge($item, $main_products_map[$p_id], [
                        'key' => $key,
                        'materials' => $materials_by_product[$p_id] ?? []
                    ]);
                }
            }
        }
        $vars['SelectProducts'] = $enriched_products;

        // --- BƯỚC 4: RENDER VIEW ---
        echo $app->render($template . '/crafting/split.html', $vars);
    })->setPermissions(['split']);

    $app->router('/crafting-split-update/{action}/group_crafting', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = 'split';
        $post_value = $app->xss($_POST['value'] ?? '');

        $_SESSION['split'][$action]['group_crafting'] = $post_value;
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/crafting-split-update/{action}/content', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = 'split';
        $post_value = $app->xss($_POST['value'] ?? '');

        $_SESSION['split'][$action]['content'] = $post_value;
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/crafting-split-update/{action}/products/add', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = 'split';
        $post_value = $app->xss($_POST['value'] ?? '');

        $data = $app->get("remove_products", "*", ["id" => $post_value]);
        if ($data) {
            if (($data['amount'] ?? 0) <= 0) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Số lượng không đủ')]);
                return;
            }
            if (!isset($_SESSION['split'][$action]['products'][$data['id']])) {
                $_SESSION['split'][$action]['products'][$data['id']] = [
                    "remove_products" => $data['id'],
                    "products" => $data['products'],
                    "amount" => $data['amount'],
                    "price" => $data['price'],
                    "units" => $data['unit'],
                    "warehouses" => $data['amount'],
                ];
            }
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } else {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại")]);
        }
    });

    $app->router('/crafting-split-update/{action}/products/deleted/{key}', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = 'split';
        $product_key = $vars['key'] ?? 0;

        unset($_SESSION['split'][$action]['products'][$product_key]);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });

    $app->router('/crafting-split-update/{action}/products/amount/{key}', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = 'split';
        $product_key = $vars['key'] ?? 0;
        $post_value = $app->xss($_POST['value'] ?? '');

        $value = (float) str_replace(',', '', $post_value);
        if ($value <= 0) {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Số lượng phải lớn hơn 0')]);
            return;
        }

        $remove_product_id = $_SESSION["split"][$action]['products'][$product_key]['remove_products'] ?? 0;
        $getAmount = $app->get("remove_products", "amount", ["id" => $remove_product_id]);

        if ($value > ($getAmount ?? 0)) {
            $_SESSION["split"][$action]['products'][$product_key]['amount'] = $getAmount;
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Số lượng không đủ')]);
        } else {
            $_SESSION["split"][$action]['products'][$product_key]['amount'] = $value;
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        }
    });

    $app->router('/crafting-split-update/cancel', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = 'split';

        unset($_SESSION['split'][$action]);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Đã hủy phiếu")]);
    });

    $app->router('/crafting-split-update/{action}/completed', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);
        $action = 'split';
        $data = $_SESSION['split'][$action] ?? [];
        $error = [];

        if (empty($data['products'])) {
            $error = ['status' => 'error', 'content' => $jatbi->lang('Vui lòng nhập sản phẩm')];
        } else {
            foreach ($data['products'] as $value) {
                if (empty($value['amount']) || $value['amount'] <= 0) {
                    $error = ['status' => 'error', 'content' => $jatbi->lang('Vui lòng nhập số lượng')];
                    break;
                }
            }
        }
        if(empty($data['content'])) {
            $error = ['status' => 'error', 'content' => $jatbi->lang('Vui lòng nhập nội dung')];
        }

        if (empty($error)) {
            $insert = ["code" => 'PN', "type" => 'import_split', "data" => 'crafting', "content" => $data['content'], "user" => $app->getSession("accounts")['id'] ?? 0, "date" => date('Y-m-d'), "active" => $jatbi->active(30), "date_poster" => date("Y-m-d H:i:s"), "export_status" => 2, "group_crafting" => $data['group_crafting']];
            $app->insert("warehouses", $insert);
            $orderId = $app->id();

            foreach ($data['products'] as $value) {
                $get_remove = $app->get("remove_products", "*", ["products" => $value["products"]]);
                $app->update("remove_products", ["amount[-]" => $value["amount"]], ["id" => $get_remove["id"]]);

                $history_split = ["user" => $app->getSession("accounts")['id'] ?? 0, "date" => date("Y-m-d H:i:s"), "group_crafting" => $data['group_crafting'], "products" => $value['products'], "amount" => $value['amount'], "content" => $insert['content']];
                $app->insert("history_split", $history_split);
                $history_id = $app->id();

                $products_details = $app->select("products_details", "*", ["products" => $value["products"], "deleted" => 0]);
                $logs_to_insert = [];

                foreach ($products_details as $details) {
                    $new_amount = $details['amount'] * $value['amount'];
                    $pro1 = ["warehouses" => $orderId, "data" => $insert['data'], "type" => 'import', "ingredient" => $details['ingredient'], "amount" => $new_amount, "amount_total" => $new_amount, "price" => $details['price'], "notes" => 'Nhập từ tách đai ngọc', "date" => $insert['date_poster'], "user" => $app->getSession("accounts")['id'] ?? 0, "group_crafting" => $insert['group_crafting']];
                    $app->insert("warehouses_details", $pro1);
                    $iddetails1 = $app->id();

                    $logs_to_insert[] = ["type" => 'import', "data" => $insert['data'], "warehouses" => $orderId, "details" => $iddetails1, "ingredient" => $details['ingredient'], "price" => $details['price'], "amount" => $pro1['amount'], "total" => $pro1['amount'] * $details['price'], "notes" => 'Nhập từ sửa chế tác', "date" => $insert['date_poster'], "user" => $app->getSession("accounts")['id'] ?? 0, "group_crafting" => $insert['group_crafting']];

                    $update_col = ($insert['group_crafting'] == 1) ? "crafting[+]" : (($insert['group_crafting'] == 2) ? "craftingsilver[+]" : "craftingchain[+]");
                    $app->update("ingredient", [$update_col => $pro1['amount']], ["id" => $details['ingredient']]);

                    $app->insert("history_split_ingredient", ["history_split" => $history_id, "ingredient" => $details['ingredient'], "amount" => $pro1['amount'], "price" => $details['price'], "total" => $pro1['amount'] * $details['price'], "user" => $app->getSession("accounts")['id'] ?? 0, "date" => date("Y-m-d H:i:s")]);
                }
                if (!empty($logs_to_insert))
                    $app->insert("warehouses_logs", $logs_to_insert);
            }
            $jatbi->logs('split', $action, $insert);
            unset($_SESSION['split'][$action]);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } else {
            echo json_encode($error);
        }
    });

    $app->router('/pairing-update/add/amount', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $action = 'add';
        $value = $_POST['value'];
        if ($value < 0) {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Số lượng phải lớn hơn 0'),]);
        } else {
            $error = 0;
            foreach (($_SESSION['pairing']['add']['ingredient'] ?? []) as $key => $pro) {
                if (($value * $pro['amount']) > $pro['warehouses']) {
                    $error += 1;
                }
            }
            if ($error > 0) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Số lượng không đủ'),]);
            } else {
                $_SESSION['pairing'][$action]["amount"] = $app->xss($_POST['value']);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
            }
        }
    });

    $app->router('/pairing-update/add/code', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $action = 'add';
        $_SESSION['pairing'][$action]["code"] = $app->xss($_POST['value']);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
    });

    $app->router('/pairing-update/add/date', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $action = 'add';
        $_SESSION['pairing'][$action]["date"] = $app->xss($_POST['value']);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
    });

    $app->router('/pairing-update/add/content', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $action = 'add';
        $_SESSION['pairing'][$action]["content"] = $app->xss($_POST['value']);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
    });

    $app->router('/pairing-update/add/name', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $action = 'add';
        $_SESSION['pairing'][$action]["name"] = $app->xss($_POST['value']);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
    });

    $app->router('/pairing-update/add/categorys', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $action = 'add';
        $_SESSION['pairing'][$action]["categorys"] = $app->xss($_POST['value']);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
    });

    $app->router('/pairing-update/add/group', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $action = 'add';
        $_SESSION['pairing'][$action]["group"] = $app->xss($_POST['value']);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
    });

    $app->router('/pairing-update/add/units', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $action = 'add';
        $_SESSION['pairing'][$action]["units"] = $app->xss($_POST['value']);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
    });

    $app->router('/pairing-update/add/default_code', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $action = 'add';
        $_SESSION['pairing'][$action]["default_code"] = $app->xss($_POST['value']);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
    });

    $app->router('/pairing-update/add/personnels', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $action = 'add';
        $_SESSION['pairing'][$action]["personnels"] = $app->xss($_POST['value']);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
    });

    $app->router('/pairing-update/add/price', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $action = 'add';
        $_SESSION['pairing'][$action]["price"] = str_replace(",", "", $app->xss($_POST['value']));
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
    });

    $app->router('/pairing-update/add/cancel', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'GET') {
            // $vars['url'] = "/purchases/purchase";
            echo $app->render($setting['template'] . '/common/comfirm-modal.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $action = 'add';
            unset($_SESSION['pairing'][$action]);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
        }
    });

    $app->router('/pairing-update/add/ingredient/{req}/{id}', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $action = 'add';
        $router['4'] = $vars['req'] ?? '';
        $router['5'] = $vars['id'] ?? '';
        if ($router['4'] == 'add') {
            $data = $app->get("ingredient", "*", ["id" => $app->xss($_POST['value']), "deleted" => 0]);
            if ($data > 1) {
                if ($_SESSION['pairing'][$action]['group_crafting'] == 1) {
                    $warehouses = $data['crafting'];
                } elseif ($_SESSION['pairing'][$action]['group_crafting'] == 2) {
                    $warehouses = $data['craftingsilver'];
                } elseif ($_SESSION['pairing'][$action]['group_crafting'] == 3) {
                    $warehouses = $data['craftingchain'];
                }
                if ($warehouses <= 0) {
                    $error = $jatbi->lang('Số lượng không đủ');
                }
                if (!isset($error)) {
                    if (
                        !in_array(
                            $data['id'],
                            $_SESSION['pairing'][$action]['ingredient'][$data['id']] ?? []
                        )
                    ) {
                        if ($data['type'] == 1) {
                            $_SESSION['pairing'][$action]['ingredient']['dai'] = [
                                "ingredient" => $data['id'],
                                "type" => $data['type'],
                                "amount" => 1,
                                "code" => $data['code'],
                                "group" => $data['group'],
                                "categorys" => $data['categorys'],
                                "pearl" => $data['pearl'],
                                "sizes" => $data['sizes'],
                                "colors" => $data['colors'],
                                "units" => $data['units'],
                                "notes" => $data['notes'],
                                "price" => $data['price'],
                                "cost" => $data['cost'],
                                "warehouses" => $warehouses > 0 ? $warehouses : 0,
                            ];
                        } else {
                            $_SESSION['pairing'][$action]['ingredient'][$data['id']] = [
                                "ingredient" => $data['id'],
                                "type" => $data['type'],
                                "amount" => 1,
                                "code" => $data['code'],
                                "group" => $data['group'],
                                "categorys" => $data['categorys'],
                                "pearl" => $data['pearl'],
                                "sizes" => $data['sizes'],
                                "colors" => $data['colors'],
                                "units" => $data['units'],
                                "notes" => $data['notes'],
                                "price" => $data['price'],
                                "cost" => $data['cost'],
                                "warehouses" => $warehouses > 0 ? $warehouses : 0,
                            ];
                        }
                        $_SESSION['pairing'][$action]['code'] = trim($jatbi->getcode($_SESSION['pairing'][$action]['ingredient']));
                        echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công'), 'test' => $_SESSION['pairing'][$action]['ingredient'],]);
                    } else {
                        $getPro = $_SESSION['pairing'][$action]['ingredient'][$data['id']];
                        $value = $getPro['amount'] + 1;
                        if ($value > $warehouses) {
                            $_SESSION['pairing'][$action]['ingredient'][$data['id']]['amount'] = $warehouses;
                            echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Số lượng không đủ'),]);
                        } else {
                            $_SESSION['pairing'][$action]['ingredient'][$data['id']]['amount'] = $value;
                            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
                        }
                    }
                } else {
                    echo json_encode(['status' => 'error', 'content' => $error,]);
                }
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Cập nhật thất bại'),]);
            }
        } elseif ($router['4'] == 'deleted') {
            unset($_SESSION['pairing'][$action]['ingredient'][$router['5']]);
            $_SESSION['pairing'][$action]['code'] = trim($jatbi->getcode($_SESSION['pairing'][$action]['ingredient']));
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
        } elseif ($router['4'] == 'amount') {
            $value = (float) ($_POST['value'] ?? 0);
            $pairingAmount = (float) ($_SESSION['pairing'][$action]['amount'] ?? 0);
            $groupCrafting = (int) ($_SESSION['pairing'][$action]['group_crafting'] ?? 0);

            $getAmount = $app->get("ingredient", "*", ["id" => $_SESSION['pairing'][$action]['ingredient'][$router['5']]['ingredient']]);

            $crafting = (float) ($getAmount['crafting'] ?? 0);
            $craftingsilver = (float) ($getAmount['craftingsilver'] ?? 0);
            $craftingchain = (float) ($getAmount['craftingchain'] ?? 0);

            if ($value < 0) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Số lượng phải lớn hơn 0')]);
            } elseif (($value * $pairingAmount) > $crafting && $groupCrafting == 1) {
                $_SESSION['pairing'][$action]['ingredient'][$router['5']]['amount'] = $crafting / $pairingAmount;
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Số lượng không đủ')]);
            } elseif (($value * $pairingAmount) > $craftingsilver && $groupCrafting == 2) {
                $_SESSION['pairing'][$action]['ingredient'][$router['5']]['amount'] = $craftingsilver / $pairingAmount;
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Số lượng không đủ')]);
            } elseif (($value * $pairingAmount) > $craftingchain && $groupCrafting == 3) {
                $_SESSION['pairing'][$action]['ingredient'][$router['5']]['amount'] = $craftingchain / $pairingAmount;
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Số lượng không đủ')]);
            } else {
                $_SESSION['pairing'][$action]['ingredient'][$router['5']]['amount'] = $app->xss($value);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
            }
        } else {
            $_SESSION['pairing'][$action]['ingredient'][$router['5']][$router['4']] = $app->xss($_POST['value']);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
        }
    });

    $app->router('/pairing-update/add/completed/{rou}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template) {
        if ($app->method() === 'GET') {
            $rou = $vars['rou'];

            $vars['url'] = "/crafting/" . $rou;
            echo $app->render($template . '/common/comfirm-modal.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $action = 'add';
            $data = $_SESSION['pairing'][$action];
            $error = [];
            $error_warehouses = 'false';
            $total_products = 0;

            foreach ($data['ingredient'] as $value) {
                $total_products += $value['amount'] * $value['price'];
                if ($value['amount'] == '' || $value['amount'] == 0 || $value['amount'] < 0) {
                    $error_warehouses = 'true';
                }
            }
            $check = $app->count("crafting", "id", ["code" => $data['code'], "price[!]" => $data['price'], "deleted" => 0, "group_crafting" => $data["group_crafting"]]);
            if (empty($data['content']) || empty($data['name']) || empty($data['code']) || empty($data['price']) || empty($data['group']) || empty($data['categorys']) || empty($data['default_code']) || empty($data['units']) || empty($data['personnels']) || empty($data['ingredient'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Vui lòng nhập đầy đủ thông tin')]);
                return;
            } elseif ($error_warehouses == 'true') {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Vui lòng nhập đầy đủ số lượng nguyên liệu')]);
                return;
            } elseif ($check > 0) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Mã sản phẩm đã tồn tại')]);
                return;
            }
            if (count($error) == 0) {
                $check_crafting = $app->get("crafting", ["code", "price", "id", "amount", "amount_total", "group_crafting"], ["code" => $data['code'], "price" => $data['price'], "deleted" => 0, "group_crafting" => $data["group_crafting"], "ORDER" => ["id" => "DESC"]]);
                if (
                    ($check_crafting['code'] ?? null) === ($data['code'] ?? null) &&
                    (float) ($check_crafting['price'] ?? 0) === (float) ($data['price'] ?? 0) &&
                    (int) ($check_crafting['group_crafting'] ?? 0) === (int) ($data['group_crafting'] ?? 0)
                ) {
                    $update_crafting = [
                        "amount" => $check_crafting['amount'] + $data['amount'],
                        "amount_total" => $check_crafting['amount_total'] + $data['amount'],
                        "date" => date("Y-m-d H:i:s"),
                        "user" => $app->getSession("accounts")['id'],
                        "content" => $data['content'],
                    ];
                    $app->update("crafting", $update_crafting, ["id" => $check_crafting['id']]);
                    $history_crafting = [
                        "code" => $data['code'],
                        "name" => $data['name'],
                        "notes" => $data['content'],
                        "amount" => $data['amount'],
                        "price" => $data['price'],
                        "total" => $data['amount'] * $data['price'],
                        "user" => $app->getSession("accounts")['id'],
                        "date" => date("Y-m-d H:i:s"),
                        "personnels" => $data['personnels'],
                        "group_crafting" => $data['group_crafting'],
                        "crafting" => $check_crafting['id'],
                        "unit" => $data['units'],
                        "type" => 1,
                    ];
                    $app->insert("history_crafting", $history_crafting);
                    $history = $app->id();
                    $code = strtotime(date("Y-m-d H:i:s"));
                    $insert = [
                        "code" => $code,
                        "type" => 'pairing',
                        "data" => 'crafting',
                        "content" => $data['content'],
                        "vendor" => $data['vendor']['id'],
                        "stores" => $data['stores']['id'],
                        "user" => $app->getSession("accounts")['id'],
                        "date" => $data['date'],
                        "active" => $jatbi->active(30),
                        "date_poster" => date("Y-m-d H:i:s"),
                        "group_crafting" => $data['group_crafting'],
                    ];
                    $app->insert("warehouses", $insert);
                    $orderId = $app->id();
                    foreach ($data['ingredient'] as $key => $value) {
                        $pro = [
                            "warehouses" => $orderId,
                            "data" => 'crafting',
                            "type" => 'export',
                            "ingredient" => $value['ingredient'],
                            "amount" => $value['amount'] * $data['amount'],
                            "amount_total" => $value['amount'] * $data['amount'],
                            "price" => $value['price'],
                            "cost" => $value['cost'],
                            "notes" => 'Xuất chế tác',
                            "date" => date("Y-m-d H:i:s"),
                            "user" => $app->getSession("accounts")['id'],
                            "stores" => $insert['stores'],
                            "group_crafting" => $insert['group_crafting'],
                        ];
                        $app->insert("warehouses_details", $pro);
                        $details = $app->id();
                        $warehouses_logs = [
                            "type" => 'export',
                            "data" => 'crafting',
                            "warehouses" => $orderId,
                            "details" => $details,
                            "ingredient" => $value['ingredient'],
                            "amount" => $app->xss($value['amount'] * $data['amount']),
                            "price" => $value['price'],
                            "cost" => $value['cost'],
                            "total" => $value['amount'] * $data['amount'] * $value['price'],
                            "date" => date('Y-m-d H:i:s'),
                            "user" => $app->getSession("accounts")['id'],
                            "notes" => 'Xuất chế tác',
                            "stores" => $insert['stores'],
                            "group_crafting" => $insert['group_crafting'],
                        ];
                        $app->insert("warehouses_logs", $warehouses_logs);
                        $getProducts = $app->get("ingredient", ["id", "crafting", "craftingchain", "craftingsilver"], ["id" => $value['ingredient']]);
                        if ($insert['group_crafting'] == 1) {
                            $app->update("ingredient", ["crafting" => $getProducts['crafting'] - $warehouses_logs['amount'],], ["id" => $getProducts['id']]);
                        } elseif ($insert['group_crafting'] == 2) {
                            $app->update("ingredient", ["craftingsilver" => $getProducts['craftingsilver'] - $warehouses_logs['amount'],], ["id" => $getProducts['id']]);
                        } elseif ($insert['group_crafting'] == 3) {
                            $app->update("ingredient", ["craftingchain" => $getProducts['craftingchain'] - $warehouses_logs['amount'],], ["id" => $getProducts['id']]);
                        }
                        $history_crafting_ingredient = [
                            "history_crafting" => $history,
                            "crafting" => $check_crafting['id'],
                            "ingredient" => $value['ingredient'],
                            "code" => $value['code'],
                            "type" => $value['type'],
                            "amount" => $value['amount'],
                            "price" => $value['price'],
                            "total" => $value['amount'] * $value['price'],
                            "user" => $app->getSession("accounts")['id'],
                            "date" => date("Y-m-d H:i:s"),
                        ];
                        $app->insert("history_crafting_ingredient", $history_crafting_ingredient);
                    }
                } else {
                    $crafting = [
                        "code" => $data['code'],
                        "name" => $data['name'],
                        "content" => $data['content'],
                        "notes" => $data['notes'] ?? "",
                        "amount" => $data['amount'],
                        "amount_total" => $data['amount'],
                        "price" => $data['price'],
                        "cost" => $data['cost'],
                        "total" => $data['amount'] * $data['price'],
                        "categorys" => $data['categorys'],
                        "group" => $data['group'],
                        "units" => $data['units'],
                        "user" => $app->getSession("accounts")['id'],
                        "date" => date("Y-m-d H:i:s"),
                        "status" => 1,
                        "personnels" => $data['personnels'],
                        "group_crafting" => $data['group_crafting'],
                        "default_code" => $data['default_code'],
                    ];
                    $app->insert("crafting", $crafting);
                    $getID = $app->id();
                    $history_crafting = [
                        "code" => $data['code'],
                        "name" => $data['name'],
                        "notes" => $data['content'],
                        "amount" => $data['amount'],
                        "price" => $data['price'],
                        "total" => $data['amount'] * $data['price'],
                        "user" => $app->getSession("accounts")['id'],
                        "date" => date("Y-m-d H:i:s"),
                        "personnels" => $data['personnels'],
                        "group_crafting" => $data['group_crafting'],
                        "crafting" => $getID,
                        "unit" => $data['units'],
                        "type" => 1,
                    ];
                    $app->insert("history_crafting", $history_crafting);
                    $history = $app->id();
                    $code = strtotime(date("Y-m-d H:i:s"));
                    $insert = [
                        "code" => $code,
                        "type" => 'pairing',
                        "data" => 'crafting',
                        "content" => $data['content'],
                        "vendor" => $data['vendor']['id'] ?? 0,
                        "stores" => $data['stores']['id'],
                        "user" => $app->getSession("accounts")['id'],
                        "date" => $data['date'],
                        "active" => $jatbi->active(30),
                        "date_poster" => date("Y-m-d H:i:s"),
                        "group_crafting" => $data['group_crafting'],
                    ];
                    $app->insert("warehouses", $insert);
                    $orderId = $app->id();
                    foreach ($data['ingredient'] as $key => $value) {
                        $pro = [
                            "warehouses" => $orderId,
                            "data" => 'crafting',
                            "type" => 'export',
                            "ingredient" => $value['ingredient'],
                            "amount" => $value['amount'] * $data['amount'],
                            "amount_total" => $value['amount'] * $data['amount'],
                            "price" => $value['price'],
                            "cost" => $value['cost'],
                            "notes" => 'Xuất chế tác',
                            "date" => date("Y-m-d H:i:s"),
                            "user" => $app->getSession("accounts")['id'],
                            "stores" => $insert['stores'],
                            "group_crafting" => $insert['group_crafting'],
                        ];
                        $app->insert("warehouses_details", $pro);
                        $details = $app->id();
                        $warehouses_logs = [
                            "type" => 'export',
                            "data" => 'crafting',
                            "warehouses" => $orderId,
                            "details" => $details,
                            "ingredient" => $value['ingredient'],
                            "amount" => $app->xss($value['amount'] * $data['amount']),
                            "price" => $value['price'],
                            "cost" => $value['cost'],
                            "total" => $value['amount'] * $data['amount'] * $value['price'],
                            "date" => date('Y-m-d H:i:s'),
                            "user" => $app->getSession("accounts")['id'],
                            "notes" => 'Xuất chế tác',
                            "stores" => $insert['stores'],
                            "group_crafting" => $insert['group_crafting'],
                        ];
                        $app->insert("warehouses_logs", $warehouses_logs);
                        $crafting_details = [
                            "crafting" => $getID,
                            "ingredient" => $value['ingredient'],
                            "code" => $value['code'],
                            "type" => $value['type'],
                            "group" => $value['group'],
                            "categorys" => $value['categorys'],
                            "pearl" => $value['pearl'],
                            "sizes" => $value['sizes'],
                            "colors" => $value['colors'],
                            "units" => $value['units'],
                            "amount" => $value['amount'],
                            "price" => $value['price'],
                            "cost" => $value['cost'],
                            "total" => $value['amount'] * $value['price'],
                            "user" => $app->getSession("accounts")['id'],
                            "date" => date("Y-m-d H:i:s"),
                        ];
                        $app->insert("crafting_details", $crafting_details);
                        $pro_logs[] = $crafting_details;
                        $history_crafting_ingredient = [
                            "history_crafting" => $history,
                            "crafting" => $getID,
                            "ingredient" => $value['ingredient'],
                            "code" => $value['code'],
                            "type" => $value['type'],
                            "amount" => $value['amount'],
                            "price" => $value['price'],
                            "total" => $value['amount'] * $value['price'],
                            "user" => $app->getSession("accounts")['id'],
                            "date" => date("Y-m-d H:i:s"),
                        ];
                        $app->insert("history_crafting_ingredient", $history_crafting_ingredient);
                        $getProducts = $app->get("ingredient", "*", ["id" => $value['ingredient']]);
                        if ($insert['group_crafting'] == 1) {
                            $app->update("ingredient", ["crafting" => $getProducts['crafting'] - $warehouses_logs['amount'],], ["id" => $getProducts['id']]);
                        } elseif ($insert['group_crafting'] == 2) {
                            $app->update("ingredient", ["craftingsilver" => $getProducts['craftingsilver'] - $warehouses_logs['amount'],], ["id" => $getProducts['id']]);
                        } elseif ($insert['group_crafting'] == 3) {
                            $app->update("ingredient", ["craftingchain" => $getProducts['craftingchain'] - $warehouses_logs['amount'],], ["id" => $getProducts['id']]);
                        }
                    }
                }
                $jatbi->logs('pairing', $action, [$crafting, $pro_logs, $_SESSION['pairing'][$action]]);
                unset($_SESSION['pairing'][$action]);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $error['content']]);
            }
        }
    });
})->middleware('login');
