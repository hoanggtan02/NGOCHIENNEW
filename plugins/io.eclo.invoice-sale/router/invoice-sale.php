<?php
if (!defined('ECLO')) die("Hacking attempt");
// Handle cookies and stores
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

$app->group($setting['manager'] . "/invoices", function ($app) use ($jatbi, $setting, $stores, $accStore, $template) {
    // Chứng từ bán hàng 

    $app->router('/sales/{action}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores, $accStore, $template) {
        $dispatch = "sales";

        // Retrieve and decode cookie data, default to empty array if unset or invalid
        $sales_session = json_decode($app->getCookie($dispatch) ?? json_encode([]), true) ?? [];

        // Assign action from route variables
        $action = $vars['action'];

        // Get first key of sales_session, safely handle empty array
        $first = !empty($sales_session) ? array_key_first($sales_session) : null;

        // Clear cookie if multiple stores exist
        if (count($stores) > 1) {
            $app->setCookie($dispatch, json_encode([]), time() + $setting['cookie'], '/');
            $sales_session = []; // Sync variable with cleared cookie
        }

        // Check for store change and reset cookie if necessary
        if (
            $first !== null &&
            isset($stores[0]['value'], $sales_session[$first]['stores']) &&
            $stores[0]['value'] != $sales_session[$first]['stores'] &&
            count($stores) == 1
        ) {
            $app->setCookie($dispatch, json_encode([]), time() + $setting['cookie'], '/');
            header("Location: /invoices/sales/" . $vars['action']);
            exit;
        }

        // Initialize cookie data for single store if empty
        if (empty($sales_session) && count($stores) == 1) {
            $action = 1;

            // Set default status and type if not present
            if (!isset($sales_session[$action]['status']) || empty($sales_session[$action]['status'])) {
                $sales_session[$action]['status'] = 1;
                $sales_session[$action]['type'] = 2;
            }

            // Set default date if not present
            if (!isset($sales_session[$action]['date']) || empty($sales_session[$action]['date'])) {
                $sales_session[$action]['date'] = date("Y-m-d");
            }

            // Fetch and set payment types if not present
            if (!isset($sales_session[$action]['type_payments']) || empty($sales_session[$action]['type_payments'])) {
                $type_payments = $app->get("type_payments", "id", [
                    "status" => 'A',
                    "deleted" => 0,
                    "ORDER" => ["id" => "ASC"]
                ]);
                $sales_session[$action]['type_payments'] = $type_payments ?? 0; // Fallback to 0 if null
            }

            // Fetch and set store ID if not present
            if (!isset($sales_session[$action]['stores']) || empty($sales_session[$action]['stores'])) {
                $store_id = $app->get("stores", "id", [
                    "id" => $accStore,
                    "status" => 'A',
                    "deleted" => 0,
                    "ORDER" => ["id" => "ASC"]
                ]);
                $sales_session[$action]['stores'] = $store_id ?? 0; // Fallback to 0 if null
            }

            // Fetch and set point ID if not present
            if (!isset($sales_session[$action]['point']) || empty($sales_session[$action]['point'])) {
                $point_id = $app->get("point", "id", [
                    "status" => 'A',
                    "ORDER" => ["id" => "DESC"]
                ]);
                $sales_session[$action]['point'] = $point_id ?? 0; // Fallback to 0 if null
            }

            // Save initialized data to cookie
            $app->setCookie($dispatch, json_encode($sales_session), time() + $setting['cookie'], '/');
        }

        // Retrieve action-specific data from cookie
        $data = $sales_session[$action] ?? [];

        // Fetch customer data if customer ID exists
        $getCus = [];
        if (isset($data['customers']) && is_array($data['customers']) && !empty($data['customers']['id'])) {
            $getCus = $app->get("customers", ['id', 'type', 'name', 'phone', 'email', 'address', 'province', 'district', 'ward'], [
                "id" => $data['customers']['id']
            ]) ?? [];
        }

        // Retrieve selected products
        $SelectProducts = $data['products'] ?? [];

        // Prepare template variables
        $vars['data'] = $data;
        $vars['getCus'] = $getCus;
        $vars['SelectProducts'] = $SelectProducts;
        $vars['action'] = $action;
        $vars['next_key'] = !empty($data) && is_array($data) ? (int)max(array_keys($data)) + 1 : 1;
        $vars['stores'] = $stores;

        // Fetch personnel data with fallback to empty array
        $personnelsData = $app->select("personnels", ["id", "name", "code"], [
            "deleted" => 0,
            "status" => 'A',
            "stores" => $accStore
        ]) ?? [];
        $personnels = [];

        // Add default empty personnel option
        $personnels[] = [
            "value" => "",
            "text"  => "Nhân viên bán hàng"
        ];

        // Populate personnel options
        foreach ($personnelsData as $personnel) {
            if (isset($personnel['id'], $personnel['name'], $personnel['code'])) {
                $personnels[] = [
                    "value" => $personnel['id'],
                    "text"  => $personnel['code'] . " - " . $personnel['name'],
                ];
            }
        }

        $vars['personnels'] = $personnels;
        $empty_option = [['value' => '', 'text' => $jatbi->lang('')]];

        // Fetch branch data with fallback to empty array
        $branchs_data = $app->select("branch", ["id(value)", "name(text)"], [
            "deleted" => 0,
            "status" => 'A',
            "stores" => $accStore
        ]) ?? [];

        $vars['branchs'] = array_merge($empty_option, $branchs_data);

        // Fetch payment types with fallback to empty array
        $vars['type_payments'] = $app->select("type_payments", ["id", "name", "has", "debt"], [
            "deleted" => 0,
            "status" => 'A',
            "main" => 0
        ]) ?? [];

        // Render the sales template
        echo $app->render($template . '/invoices/sales.html', $vars);
    })->setPermissions(['sales']);

    $app->router('/invoices-update/{dispatch}/{action}/products/add/{get}', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
        // Set response header to JSON
        $app->header(['Content-Type' => 'application/json']);

        // Extract route parameters
        $dispatch = $vars['dispatch'];
        $action = $vars['action'];
        $get = $vars['get'];

        // Retrieve and decode cookie data, default to empty array if unset or invalid
        $sales_session = json_decode($app->getCookie($dispatch) ?? json_encode([]), true) ?? [];

        // Fetch product data with sanitized input
        $data = $app->get("products", "*", [
            "id" => $app->xss($get),
            "status" => "A",
            "deleted" => 0
        ]);

        if ($data) {
            $sum = $data['amount'] ?? 0;
            $error = [];

            // Validate product quantity if amount tracking is enabled
            if (($data['amount_status'] ?? 0) == 1 && $sum <= 0) {
                $error[] = "Số lượng không đủ";
            }

            if (empty($error)) {
                // Initialize products array if not set
                if (!isset($sales_session[$action]['products'])) {
                    $sales_session[$action]['products'] = [];
                }

                if (!isset($sales_session[$action]['products'][$data['id']])) {
                    // Calculate VAT and pricing based on vat_type
                    if ($data['vat_type'] == 1) {
                        $vat_price = $data['price'] - ($data['price'] / (1 + ($data['vat'] / 100)));
                        $price = $data['price'];
                        $price_vat = $data['price'] - $vat_price;
                    } else {
                        $vat_price = $data['price'] * ($data['vat'] / 100);
                        $price = $data['price'] + $vat_price;
                        $price_vat = $data['price'];
                    }

                    // Add new product to cookie data
                    $sales_session[$action]['products'][$data['id']] = [
                        "products" => $data['id'],
                        "amount" => 1,
                        "price" => $price,
                        "price_vat" => $price_vat,
                        "vendor" => $data['vendor'] ?? null,
                        "vat" => $data['vat'] ?? 0,
                        "vat_type" => $data['vat_type'] ?? 0,
                        "vat_price" => $vat_price,
                        "price_old" => $price,
                        "images" => $data['images'] ?? null,
                        "code" => $data['code'] ?? '',
                        "name" => $data['name'] ?? '',
                        "group" => $data['group'] ?? null,
                        "categorys" => $data['categorys'] ?? null,
                        "amount_status" => $data['amount_status'] ?? 0,
                        "units" => $data['units'] ?? null,
                        "notes" => $data['notes'] ?? null,
                        "branch" => $data['branch'] ?? null,
                        "warehouses" => $sum > 0 ? $sum : 0,
                        "precent" => $data['precent'] ?? 0,
                        "minus" => $data['minus'] ?? 0,
                    ];

                    // Calculate total product value
                    $total_products = 0;
                    foreach ($sales_session[$action]['products'] as $value) {
                        $total_products += ($value['amount'] ?? 0) * ($value['price'] ?? 0);
                    }

                    // Update personnel prices
                    if (isset($sales_session[$action]['personnels']) && is_array($sales_session[$action]['personnels'])) {
                        foreach ($sales_session[$action]['personnels'] as $key => $a) {
                            $sales_session[$action]['personnels'][$key]['price'] = $total_products;
                        }
                    }

                    // Store product name and branch
                    $sales_session[$action]['products_name'][$data['id']] = [
                        "code" => $data['code'] ?? '',
                        "name" => $data['name'] ?? '',
                        "branch" => $app->get("branch", "name", ["id" => $data['branch']]) ?? '',
                    ];

                    // Store VAT data
                    $sales_session[$action]['vat'][$data['vat']][$data['id']] = $vat_price;

                    // Save updated data to cookie
                    $app->setCookie($dispatch, json_encode($sales_session), time() + $setting['cookie'], '/');
                    echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
                } else {
                    // Update existing product quantity
                    $getPro = $sales_session[$action]['products'][$data['id']];
                    $value = ($getPro['amount'] ?? 0) + 1;

                    if ($data['amount_status'] == 1) {
                        if ($value > $sum) {
                            $sales_session[$action]['products'][$data['id']]['amount'] = $sum;
                            // Save updated data to cookie
                            $app->setCookie($dispatch, json_encode($sales_session), time() + $setting['cookie'], '/');
                            echo json_encode(['status' => 'error', 'content' => 'Số lượng không đủ']);
                        } else {
                            $sales_session[$action]['products'][$data['id']]['amount'] = $value;
                            // Recalculate totals
                            $total_products = 0;
                            $total_surcharge = 0;
                            foreach ($sales_session[$action]['products'] as $value) {
                                $total_products += ($value['amount'] ?? 0) * ($value['price'] ?? 0);
                            }
                            foreach ($sales_session[$action]['surcharge'] ?? [] as $surcharge) {
                                $total_surcharge += $surcharge['price'] ?? 0;
                            }
                            if (isset($sales_session[$action]['personnels']) && is_array($sales_session[$action]['personnels'])) {
                                foreach ($sales_session[$action]['personnels'] as $key => $a) {
                                    $sales_session[$action]['personnels'][$key]['price'] = $total_products + $total_surcharge;
                                }
                            }
                            // Save updated data to cookie
                            $app->setCookie($dispatch, json_encode($sales_session), time() + $setting['cookie'], '/');
                            echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
                        }
                    } else {
                        $sales_session[$action]['products'][$data['id']]['amount'] = $value;
                        // Recalculate totals
                        $total_products = 0;
                        $total_surcharge = 0;
                        foreach ($sales_session[$action]['products'] as $value) {
                            $total_products += ($value['amount'] ?? 0) * ($value['price'] ?? 0);
                        }
                        foreach ($sales_session[$action]['surcharge'] ?? [] as $surcharge) {
                            $total_surcharge += $surcharge['price'] ?? 0;
                        }
                        if (isset($sales_session[$action]['personnels']) && is_array($sales_session[$action]['personnels'])) {
                            foreach ($sales_session[$action]['personnels'] as $key => $a) {
                                $sales_session[$action]['personnels'][$key]['price'] = $total_products + $total_surcharge;
                            }
                        }
                        // Save updated data to cookie
                        $app->setCookie($dispatch, json_encode($sales_session), time() + $setting['cookie'], '/');
                        echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
                    }
                }
            } else {
                echo json_encode(['status' => 'error', 'content' => $error[0] ?? 'Không tìm thấy']);
            }
        } else {
            echo json_encode(['status' => 'error', 'content' => 'Không tìm thấy']);
        }
    })->setPermissions(['sales']);

    // Route for deleting a product
    $app->router('/invoices-update/{dispatch}/{action}/products/deleted/{get}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'GET') {
            // Render delete confirmation template for GET requests
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            // Set response header to JSON
            $app->header(['Content-Type' => 'application/json']);

            // Extract route parameters
            $dispatch = $vars['dispatch'];
            $action = $vars['action'];
            $get = $vars['get'];

            // Retrieve and decode cookie data, default to empty array if unset or invalid
            $sales_session = json_decode($app->getCookie($dispatch) ?? json_encode([]), true) ?? [];

            // Fetch product data with sanitized input
            $data = $app->get("products", ["id", "vat"], [
                "id" => $app->xss($get)
            ]);

            if ($data) {
                // Initialize arrays if not set
                if (!isset($sales_session[$action])) {
                    $sales_session[$action] = [];
                }
                if (!isset($sales_session[$action]['products'])) {
                    $sales_session[$action]['products'] = [];
                }
                if (!isset($sales_session[$action]['vat'])) {
                    $sales_session[$action]['vat'] = [];
                }

                // Remove product and VAT entry
                unset($sales_session[$action]['products'][$data['id']]);
                unset($sales_session[$action]['vat'][$data['vat']][$data['id']]);

                // Calculate total product value
                $total_products = 0;
                foreach ($sales_session[$action]['products'] ?? [] as $value) {
                    $total_products += ($value['amount'] ?? 0) * ($value['price'] ?? 0);
                }

                // Calculate total surcharge
                $total_surcharge = 0;
                foreach ($sales_session[$action]['surcharge'] ?? [] as $surcharge) {
                    $total_surcharge += $surcharge['price'] ?? 0;
                }

                // Update personnel prices
                if (isset($sales_session[$action]['personnels']) && is_array($sales_session[$action]['personnels'])) {
                    foreach ($sales_session[$action]['personnels'] as $key => $a) {
                        $sales_session[$action]['personnels'][$key]['price'] = $total_products + $total_surcharge;
                    }
                }

                // Remove VAT group if empty
                if (isset($sales_session[$action]['vat'][$data['vat']]) && empty($sales_session[$action]['vat'][$data['vat']])) {
                    unset($sales_session[$action]['vat'][$data['vat']]);
                }

                // Save updated data to cookie
                $app->setCookie($dispatch, json_encode($sales_session), time() + $setting['cookie'], '/');
                echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
            } else {
                echo json_encode(['status' => 'error', 'content' => 'Không tìm thấy sản phẩm']);
            }
        }
    })->setPermissions(['sales']);

    // Route for updating product price
    $app->router('/invoices-update/{dispatch}/{action}/products/price/{get}', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
        // Set response header to JSON
        $app->header(['Content-Type' => 'application/json']);

        // Extract route parameters
        $dispatch = $vars['dispatch'];
        $action = $vars['action'];
        $get = $vars['get'];

        // Retrieve and decode cookie data, default to empty array if unset or invalid
        $sales_session = json_decode($app->getCookie($dispatch) ?? json_encode([]), true) ?? [];

        // Initialize arrays if not set
        if (!isset($sales_session[$action])) {
            $sales_session[$action] = [];
        }
        if (!isset($sales_session[$action]['products'])) {
            $sales_session[$action]['products'] = [];
        }

        // Update product price with sanitized input
        if (isset($sales_session[$action]['products'][$get])) {
            $sales_session[$action]['products'][$get]['price'] = str_replace(',', '', $app->xss($_POST['value'] ?? '0'));
            // Save updated data to cookie
            $app->setCookie($dispatch, json_encode($sales_session), time() + $setting['cookie'], '/');
            echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
        } else {
            echo json_encode(['status' => 'error', 'content' => 'Không tìm thấy sản phẩm']);
        }
    })->setPermissions(['sales']);

    // Route for updating product amount
    $app->router('/invoices-update/{dispatch}/{action}/products/amount/{get}', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
        // Set response header to JSON
        $app->header(['Content-Type' => 'application/json']);

        // Extract route parameters
        $dispatch = $vars['dispatch'];
        $action = $vars['action'];
        $get = $vars['get'];

        // Retrieve and decode cookie data, default to empty array if unset or invalid
        $sales_session = json_decode($app->getCookie($dispatch) ?? json_encode([]), true) ?? [];

        // Initialize arrays if not set
        if (!isset($sales_session[$action])) {
            $sales_session[$action] = [];
        }
        if (!isset($sales_session[$action]['products'])) {
            $sales_session[$action]['products'] = [];
        }

        $value = null;
        if (isset($_POST['value'])) {
            // Handle direct amount input
            $value = str_replace(',', '', $app->xss($_POST['value']));
        } elseif (isset($_POST['increment'])) {
            // Handle increment/decrement buttons
            $current_amount = $sales_session[$action]['products'][$get]['amount'] ?? 0;
            if ($_POST['increment'] === 'plus') {
                $value = $current_amount + 1;
            } elseif ($_POST['increment'] === 'minus') {
                $value = max(0, $current_amount - 1); // Prevent negative quantity
            }
        }

        if (!isset($sales_session[$action]['products'][$get])) {
            echo json_encode(['status' => 'error', 'content' => 'Không tìm thấy sản phẩm']);
            return;
        }

        if ($value === null || $value < 0) {
            echo json_encode(['status' => 'error', 'content' => 'Số lượng không hợp lệ']);
        } else {
            if ($dispatch == "returns") {
                $warehouses = $sales_session[$action]['products'][$get]['warehouses'] ?? 0;
                if ($value > $warehouses) {
                    $sales_session[$action]['products'][$get]['amount'] = $warehouses;
                    // Save updated data to cookie
                    $app->setCookie($dispatch, json_encode($sales_session), time() + $setting['cookie'], '/');
                    echo json_encode(['status' => 'error', 'content' => 'Số lượng không đủ']);
                } else {
                    $sales_session[$action]['products'][$get]['amount'] = $app->xss($value);
                    // Save updated data to cookie
                    $app->setCookie($dispatch, json_encode($sales_session), time() + $setting['cookie'], '/');
                    echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
                }
            } else {
                $getAmount = $app->get("products", ["id", "amount_status", "amount"], [
                    "id" => $sales_session[$action]['products'][$get]['products'] ?? 0
                ]);
                if ($getAmount && ($getAmount['amount_status'] ?? 0) == 1) {
                    $warehouses = $getAmount["amount"] ?? 0;
                    if ($value > $warehouses) {
                        $sales_session[$action]['products'][$get]['amount'] = $warehouses;
                        // Save updated data to cookie
                        $app->setCookie($dispatch, json_encode($sales_session), time() + $setting['cookie'], '/');
                        echo json_encode(['status' => 'error', 'content' => 'Số lượng không đủ']);
                    } else {
                        $sales_session[$action]['products'][$get]['amount'] = $app->xss($value);
                        // Recalculate totals
                        $total_products = 0;
                        foreach ($sales_session[$action]['products'] as $value) {
                            $total_products += ($value['amount'] ?? 0) * ($value['price'] ?? 0);
                        }
                        $total_surcharge = 0;
                        foreach ($sales_session[$action]['surcharge'] ?? [] as $surcharge) {
                            $total_surcharge += $surcharge['price'] ?? 0;
                        }
                        if (isset($sales_session[$action]['personnels']) && is_array($sales_session[$action]['personnels'])) {
                            foreach ($sales_session[$action]['personnels'] as $key => $a) {
                                $sales_session[$action]['personnels'][$key]['price'] = $total_products + $total_surcharge;
                            }
                        }
                        // Save updated data to cookie
                        $app->setCookie($dispatch, json_encode($sales_session), time() + $setting['cookie'], '/');
                        echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
                    }
                } else {
                    $sales_session[$action]['products'][$get]['amount'] = $app->xss($value);
                    // Recalculate totals
                    $total_products = 0;
                    foreach ($sales_session[$action]['products'] as $value) {
                        $total_products += ($value['amount'] ?? 0) * ($value['price'] ?? 0);
                    }
                    $total_surcharge = 0;
                    foreach ($sales_session[$action]['surcharge'] ?? [] as $surcharge) {
                        $total_surcharge += $surcharge['price'] ?? 0;
                    }
                    if (isset($sales_session[$action]['personnels']) && is_array($sales_session[$action]['personnels'])) {
                        foreach ($sales_session[$action]['personnels'] as $key => $a) {
                            $sales_session[$action]['personnels'][$key]['price'] = $total_products + $total_surcharge;
                        }
                    }
                    // Save updated data to cookie
                    $app->setCookie($dispatch, json_encode($sales_session), time() + $setting['cookie'], '/');
                    echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
                }
            }
        }
    })->setPermissions(['sales']);

    // Route for updating other product fields
    $app->router('/invoices-update/{dispatch}/{action}/products/{req}/{get}', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
        // Set response header to JSON
        $app->header(['Content-Type' => 'application/json']);

        // Extract route parameters
        $dispatch = $vars['dispatch'];
        $action = $vars['action'];
        $req = $vars['req'];
        $get = $vars['get'];

        // Retrieve and decode cookie data, default to empty array if unset or invalid
        $sales_session = json_decode($app->getCookie($dispatch) ?? json_encode([]), true) ?? [];

        // Initialize arrays if not set
        if (!isset($sales_session[$action])) {
            $sales_session[$action] = [];
        }
        if (!isset($sales_session[$action]['products'])) {
            $sales_session[$action]['products'] = [];
        }

        // Update specified product field with sanitized input
        if (isset($sales_session[$action]['products'][$get])) {
            $sales_session[$action]['products'][$get][$req] = $app->xss($_POST['value'] ?? '');
            // Save updated data to cookie
            $app->setCookie($dispatch, json_encode($sales_session), time() + $setting['cookie'], '/');
            echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
        } else {
            echo json_encode(['status' => 'error', 'content' => 'Không tìm thấy sản phẩm']);
        }
    })->setPermissions(['sales']);

    $app->router('/invoices-update/{dispatch}/{action}/orderid/{get}', ['POST'], function ($vars) use ($app, $jatbi, $setting, $accStore) {
        // Set response header to JSON
        $app->header(['Content-Type' => 'application/json']);

        // Extract route parameters
        $dispatch = $vars['dispatch'];
        $action = $vars['action'];
        $get = $vars['get'];

        // Retrieve and decode cookie data, default to empty array if unset or invalid
        $sales_session = json_decode($app->getCookie($dispatch) ?? json_encode([]), true) ?? [];

        // Initialize action array if not set
        if (!isset($sales_session[$action])) {
            $sales_session[$action] = [];
        }

        // Set sales-active cookie if active variable is set
        if (isset($active)) {
            $app->setCookie('sales-active', $app->xss($get), time() + $setting['cookie'], '/');
        }

        // Reset action data if status is empty
        if (empty($sales_session[$action]['status'])) {
            $sales_session[$action] = [];
            $sales_session[$action]['status'] = 1;
            $sales_session[$action]['type'] = 2;
        }

        // Set default date if not present
        if (empty($sales_session[$action]['date'])) {
            $sales_session[$action]['date'] = date("Y-m-d");
        }

        // Set default payment types if not present
        if (empty($sales_session[$action]['type_payments'])) {
            $type_payments = $app->get("type_payments", "id", [
                "status" => 'A',
                "deleted" => 0,
                "ORDER" => ["id" => "ASC"]
            ]);
            $sales_session[$action]['type_payments'] = $type_payments ?? 0;
        }

        // Set default store ID if not present
        if (empty($sales_session[$action]['stores'])) {
            $store_id = $app->get("stores", "id", [
                "id" => $accStore,
                "status" => 'A',
                "deleted" => 0,
                "ORDER" => ["id" => "ASC"]
            ]);
            $sales_session[$action]['stores'] = $store_id ?? 0;
        }

        // Set default point ID if not present
        if (empty($sales_session[$action]['point'])) {
            $point_id = $app->get("point", "id", [
                "status" => 'A',
                "ORDER" => ["id" => "DESC"]
            ]);
            $sales_session[$action]['point'] = $point_id ?? 0;
        }

        // Save updated data to cookie
        $app->setCookie($dispatch, json_encode($sales_session), time() + $setting['cookie'], '/');
        echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
    })->setPermissions(['sales']);

    $app->router('/invoices-updatee/{dispatch}/{orderkey}/cancel', ['POST'], function ($vars) use ($app, $jatbi, $setting, $accStore) {
        // Set response header to JSON
        $app->header(['Content-Type' => 'application/json']);

        // Extract route parameters
        $dispatch = $vars['dispatch'];
        $orderkey = $vars['orderkey'];

        // Retrieve and decode cookie data, default to empty array if unset or invalid
        $sales_session = json_decode($app->getCookie($dispatch) ?? json_encode([]), true) ?? [];

        // Remove orderkey data
        unset($sales_session[$orderkey]);

        // Save updated data to cookie
        $app->setCookie($dispatch, json_encode($sales_session), time() + $setting['cookie'], '/');
        echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
    })->setPermissions(['sales']);

    $app->router('/invoices-update/{dispatch}/{action}/{type}', ['POST'], function ($vars) use ($app, $jatbi, $setting, $accStore) {
        // Set response header to JSON
        $app->header(['Content-Type' => 'application/json']);

        // Extract route parameters
        $dispatch = $vars['dispatch'];
        $action = $vars['action'];
        $type = $vars['type'];

        // Retrieve and decode cookie data, default to empty array if unset or invalid
        $sales_session = json_decode($app->getCookie($dispatch) ?? json_encode([]), true) ?? [];

        // Initialize action array if not set
        if (!isset($sales_session[$action])) {
            $sales_session[$action] = [];
        }

        // Update specified type field with sanitized input
        $sales_session[$action][$type] = $app->xss($_POST['value'] ?? '');

        // Save updated data to cookie
        $app->setCookie($dispatch, json_encode($sales_session), time() + $setting['cookie'], '/');
        echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
    })->setPermissions(['sales']);

    $app->router('/invoices-updatee/{dispatch}/{action}/personnels', ['POST'], function ($vars) use ($app, $jatbi, $setting, $accStore) {
        // Set response header to JSON
        $app->header(['Content-Type' => 'application/json']);

        // Extract route parameters
        $dispatch = $vars['dispatch'];
        $action = $vars['action'];

        // Retrieve and decode cookie data, default to empty array if unset or invalid
        $sales_session = json_decode($app->getCookie($dispatch) ?? json_encode([]), true) ?? [];

        // Initialize arrays if not set
        if (!isset($sales_session[$action])) {
            $sales_session[$action] = [];
        }
        if (!isset($sales_session[$action]['personnels'])) {
            $sales_session[$action]['personnels'] = [];
        }
        if (!isset($sales_session[$action]['personnels_name'])) {
            $sales_session[$action]['personnels_name'] = [];
        }

        // Fetch personnel data with sanitized input
        $data = $app->get("personnels", ["id", "name"], ["id" => $app->xss($_POST['value'] ?? 0)]);

        if ($data && isset($data['id'], $data['name'])) {
            // Add personnel data
            $sales_session[$action]['personnels'][$data['id']] = [
                "id" => $data['id'],
                "name" => $data['name'],
            ];
            $sales_session[$action]['personnels_name'][$data['id']] = [
                "name" => $data['name'],
            ];

            // Calculate total product and surcharge values
            $total_products = 0;
            $total_surcharge = 0;
            if (!empty($sales_session[$action]['products']) && is_array($sales_session[$action]['products'])) {
                foreach ($sales_session[$action]['products'] as $value) {
                    $total_products += ($value['amount'] ?? 0) * ($value['price'] ?? 0);
                }
            }
            if (!empty($sales_session[$action]['surcharge']) && is_array($sales_session[$action]['surcharge'])) {
                foreach ($sales_session[$action]['surcharge'] as $surcharge) {
                    $total_surcharge += $surcharge['price'] ?? 0;
                }
            }

            // Update personnel price
            $sales_session[$action]['personnels'][$data['id']]['price'] = $total_products + $total_surcharge;

            // Save updated data to cookie
            $app->setCookie($dispatch, json_encode($sales_session), time() + $setting['cookie'], '/');
            echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
        } else {
            echo json_encode(['status' => 'error', 'content' => 'Cập nhật thất bại']);
        }
    })->setPermissions(['sales']);

    $app->router('/invoices-update/{dispatch}/{action}/commission/{req}', ['POST'], function ($vars) use ($app, $jatbi, $setting, $accStore) {
        // Set response header to JSON
        $app->header(['Content-Type' => 'application/json']);

        // Extract route parameters
        $dispatch = $vars['dispatch'];
        $action = $vars['action'];
        $req = $vars['req'];

        // Retrieve and decode cookie data, default to empty array if unset or invalid
        $sales_session = json_decode($app->getCookie($dispatch) ?? json_encode([]), true) ?? [];

        // Initialize arrays if not set
        if (!isset($sales_session[$action])) {
            $sales_session[$action] = [];
        }
        if (!isset($sales_session[$action]['personnels'])) {
            $sales_session[$action]['personnels'] = [];
        }

        // Update personnel commission with sanitized input
        if (isset($sales_session[$action]['personnels'][$req])) {
            $sales_session[$action]['personnels'][$req]['price'] = str_replace(",", "", $app->xss($_POST['value'] ?? '0'));
            // Save updated data to cookie
            $app->setCookie($dispatch, json_encode($sales_session), time() + $setting['cookie'], '/');
            echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
        } else {
            echo json_encode(['status' => 'error', 'content' => 'Không tìm thấy nhân viên']);
        }
    })->setPermissions(['sales']);

    $app->router('/invoices-update/{dispatch}/{action}/personnels-deleted/{req}', ['POST'], function ($vars) use ($app, $jatbi, $setting, $accStore) {
        // Set response header to JSON
        $app->header(['Content-Type' => 'application/json']);

        // Extract route parameters
        $dispatch = $vars['dispatch'];
        $action = $vars['action'];
        $req = $vars['req'];

        // Retrieve and decode cookie data, default to empty array if unset or invalid
        $sales_session = json_decode($app->getCookie($dispatch) ?? json_encode([]), true) ?? [];

        // Initialize arrays if not set
        if (!isset($sales_session[$action])) {
            $sales_session[$action] = [];
        }

        // Remove personnel entry
        unset($sales_session[$action]['personnels'][$req]);

        // Save updated data to cookie
        $app->setCookie($dispatch, json_encode($sales_session), time() + $setting['cookie'], '/');
        echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
    })->setPermissions(['sales']);

    $app->router('/invoices-updatee/{dispatch}/{action}/branch', ['POST'], function ($vars) use ($app, $jatbi, $setting, $accStore) {
        // Set response header to JSON
        $app->header(['Content-Type' => 'application/json']);

        // Extract route parameters
        $dispatch = $vars['dispatch'];
        $action = $vars['action'];

        // Retrieve and decode cookie data, default to empty array if unset or invalid
        $sales_session = json_decode($app->getCookie($dispatch) ?? json_encode([]), true) ?? [];

        // Initialize action array if not set
        if (!isset($sales_session[$action])) {
            $sales_session[$action] = [];
        }

        // Fetch branch data with sanitized input
        $data = $app->get("branch", "id", ["id" => $app->xss($_POST['value'] ?? 0)]);

        if ($data) {
            $sales_session[$action]['branch'] = $data;
            // Save updated data to cookie
            $app->setCookie($dispatch, json_encode($sales_session), time() + $setting['cookie'], '/');
            echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
        } else {
            echo json_encode(['status' => 'error', 'content' => 'Cập nhật thất bại']);
        }
    })->setPermissions(['sales']);

    $app->router('/invoices-updatee/{dispatch}/{action}/customers/{get}', ['POST'], function ($vars) use ($app, $jatbi, $setting, $accStore) {
        // Set response header to JSON
        $app->header(['Content-Type' => 'application/json']);

        // Extract route parameters
        $dispatch = $vars['dispatch'];
        $action = $vars['action'];
        $get = $vars['get'];

        // Retrieve and decode cookie data, default to empty array if unset or invalid
        $sales_session = json_decode($app->getCookie($dispatch) ?? json_encode([]), true) ?? [];

        // Initialize action array if not set
        if (!isset($sales_session[$action])) {
            $sales_session[$action] = [];
        }

        // Fetch customer data
        $data = $app->get("customers", ["id", "name", "phone", "email"], ["id" => $get]);

        if ($data && isset($data['id'])) {
            $getCard = $app->get("customers_card", "*", [
                "customers" => $data['id'],
                "deleted" => 0,
                "ORDER" => ["discount" => "DESC", "id" => "DESC"]
            ]);
            $sales_session[$action]['customers'] = [
                "id" => $data['id'],
                "name" => $data['name'] ?? '',
                "phone" => $data['phone'] ?? '',
                "email" => $data['email'] ?? '',
                "card" => $getCard ?? [],
            ];
            if ($getCard && isset($getCard['discount'], $getCard['id'])) {
                $sales_session[$action]['discount_customers'] = $getCard['discount'];
                $sales_session[$action]['discount_card'] = $getCard['id'];
            }
            // Save updated data to cookie
            $app->setCookie($dispatch, json_encode($sales_session), time() + $setting['cookie'], '/');
            echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
        } else {
            echo json_encode(['status' => 'error', 'content' => 'Cập nhật thất bại']);
        }
    })->setPermissions(['sales']);

    $app->router('/invoices-update/{dispatch}/{action}/details-input/surcharge/{get}', ['POST'], function ($vars) use ($app, $jatbi, $setting, $accStore) {
        // Set response header to JSON
        $app->header(['Content-Type' => 'application/json']);

        // Extract route parameters
        $dispatch = $vars['dispatch'];
        $action = $vars['action'];
        $get = $vars['get'];

        // Retrieve and decode cookie data, default to empty array if unset or invalid
        $sales_session = json_decode($app->getCookie($dispatch) ?? json_encode([]), true) ?? [];

        // Initialize arrays if not set
        if (!isset($sales_session[$action])) {
            $sales_session[$action] = [];
        }
        if (!isset($sales_session[$action]['surcharge'])) {
            $sales_session[$action]['surcharge'] = [];
        }

        // Add surcharge entry
        $sales_session[$action]['surcharge'][$get] = [
            "code" => strtotime(date('Y-m-d H:i:s')),
            "type" => 'surcharge',
            "price" => $app->xss(str_replace(['.', ','], '', $app->xss($_POST['value'] ?? '0'))),
            "type_payments" => $app->xss($_POST['type'] ?? ""),
            "content" => $app->xss($_POST['content'] ?? ""),
            "date" => isset($_POST['date']) && $app->xss($_POST['date']) != '' ? date('Y-m-d H:i:s', strtotime($app->xss($_POST['date']))) : date("Y-m-d H:i:s"),
            "user" => json_decode($app->getCookie("accounts") ?? json_encode([]), true)['id'] ?? 0,
        ];

        // Calculate total product and surcharge values
        $total_products = 0;
        $total_surcharge = 0;
        if (!empty($sales_session[$action]['products']) && is_array($sales_session[$action]['products'])) {
            foreach ($sales_session[$action]['products'] as $value) {
                $total_products += ($value['amount'] ?? 0) * ($value['price'] ?? 0);
            }
        }
        if (!empty($sales_session[$action]['surcharge']) && is_array($sales_session[$action]['surcharge'])) {
            foreach ($sales_session[$action]['surcharge'] as $surcharge) {
                $total_surcharge += $surcharge['price'] ?? 0;
            }
        }

        // Update personnel prices
        if (!empty($sales_session[$action]['personnels']) && is_array($sales_session[$action]['personnels'])) {
            foreach ($sales_session[$action]['personnels'] as $key => $a) {
                $sales_session[$action]['personnels'][$key]['price'] = $total_products + $total_surcharge;
            }
        }

        // Save updated data to cookie
        $app->setCookie($dispatch, json_encode($sales_session), time() + $setting['cookie'], '/');
        echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
    })->setPermissions(['sales']);

    $app->router('/invoices-update/{dispatch}/{action}/details/{req}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $accStore, $template) {
        if ($app->method() === 'GET') {
            // Prepare template variables for rendering
            $vars['title'] = "Chi tiết thanh toán";

            // Retrieve and decode cookie data, default to empty array if unset or invalid
            $sales_session = json_decode($app->getCookie($vars['dispatch']) ?? json_encode([]), true) ?? [];

            // Initialize action array if not set
            if (!isset($sales_session[$vars['action']])) {
                $sales_session[$vars['action']] = [];
            }

            $vars['datas'] = $sales_session[$vars['action']][$vars['req']] ?? [];
            $vars['type_payments'] = $app->select("type_payments", ["id", "name", "has", "debt"], [
                "deleted" => 0,
                "status" => 'A',
                "main" => 0
            ]) ?? [];

            // Render the details template
            echo $app->render($template . '/invoices/invoices-update-details.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            // Set response header to JSON
            $app->header(['Content-Type' => 'application/json']);

            // Extract route parameters
            $dispatch = $vars['dispatch'];
            $action = $vars['action'];
            $req = $vars['req'];

            // Retrieve and decode cookie data, default to empty array if unset or invalid
            $sales_session = json_decode($app->getCookie($dispatch) ?? json_encode([]), true) ?? [];

            // Initialize arrays if not set
            if (!isset($sales_session[$action])) {
                $sales_session[$action] = [];
            }
            if (!isset($sales_session[$action][$req])) {
                $sales_session[$action][$req] = [];
            }

            // Validate date input
            if (empty($_POST['date'])) {
                echo json_encode(['status' => 'error', 'content' => 'lỗi trống']);
            } else {
                // Add new detail entry
                $sales_session[$action][$req][] = [
                    "code" => strtotime(date('Y-m-d H:i:s')),
                    "type" => $req,
                    "price" => $app->xss(str_replace([','], '', $_POST['price'] ?? '0')),
                    "type_payments" => $app->xss($_POST['type'] ?? ""),
                    "content" => $app->xss($_POST['content'] ?? ''),
                    "date" => $app->xss($_POST['date']) == '' ? date("Y-m-d H:i:s") : date('Y-m-d H:i:s', strtotime($app->xss($_POST['date']))),
                    "user" => json_decode($app->getCookie("accounts") ?? json_encode([]), true)['id'] ?? 0,
                    "test" => 1
                ];

                // Save updated data to cookie
                $app->setCookie($dispatch, json_encode($sales_session), time() + $setting['cookie'], '/');
                echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
            }
        }
    })->setPermissions(['sales']);

    $app->router('/invoices-updatee/{dispatch}/{action}/products-details/{req}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $accStore, $template) {
        if ($app->method() === 'GET') {
            // Prepare template variables for rendering
            $vars['title'] = "Chi tiết sản phẩm";

            // Retrieve and decode cookie data, default to empty array if unset or invalid
            $sales_session = json_decode($app->getCookie($vars['dispatch']) ?? json_encode([]), true) ?? [];

            // Initialize action array if not set
            if (!isset($sales_session[$vars['action']])) {
                $sales_session[$vars['action']] = [];
            }

            $vars['data'] = $sales_session[$vars['action']]['products'][$vars['req']] ?? [];
            $types = [
                ["value" => "", "text" => $jatbi->lang("Chọn")],
                ["value" => "1", "text" => $jatbi->lang("Giá sản phẩm")],
                ["value" => "2", "text" => $jatbi->lang("Giảm tiền")],
                ["value" => "3", "text" => $jatbi->lang("Giảm phần trăm")],
            ];
            $vars['types'] = $types;

            // Render the product details template
            echo $app->render($template . '/invoices/invoices-update-products-details.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            // Set response header to JSON
            $app->header(['Content-Type' => 'application/json']);

            // Extract route parameters
            $dispatch = $vars['dispatch'];
            $action = $vars['action'];
            $req = $vars['req'];

            // Retrieve and decode cookie data, default to empty array if unset or invalid
            $sales_session = json_decode($app->getCookie($dispatch) ?? json_encode([]), true) ?? [];

            // Initialize arrays if not set
            if (!isset($sales_session[$action])) {
                $sales_session[$action] = [];
            }
            if (!isset($sales_session[$action]['products'])) {
                $sales_session[$action]['products'] = [];
            }

            if (isset($sales_session[$action]['products'][$req])) {
                $price_old = $sales_session[$action]['products'][$req]['price_old'] ?? 0;
                $price = 0;
                $value = 0;
                $minus = 0;
                $precent = 0;

                if ($app->xss($_POST['type'] ?? '') == 2) {
                    $minus = $app->xss(str_replace([','], '', $_POST['minus'] ?? '0'));
                    $price = $price_old - $minus;
                } elseif ($app->xss($_POST['type'] ?? '') == 3) {
                    $precent = $app->xss(str_replace([','], '', $_POST['precent'] ?? '0'));
                    if ($precent > 100) {
                        $precent = 100;
                    } elseif ($precent < 0) {
                        $precent = 0;
                    }
                    $price = $price_old - (($price_old * $precent) / 100);
                } else {
                    $value = $app->xss(str_replace([','], '', $_POST['price'] ?? '0'));
                    $price = $value;
                }

                // Update product details
                $sales_session[$action]['products'][$req]['details'] = [
                    "type" => $app->xss($_POST['type'] ?? 0),
                    "value" => $value,
                    "minus" => $minus,
                    "precent" => $precent,
                    "price" => $price,
                    "content" => $app->xss($_POST['content'] ?? ""),
                ];
                $sales_session[$action]['products'][$req]['price'] = $price;
                $sales_session[$action]['products'][$req]['minus'] = $minus;
                $sales_session[$action]['products'][$req]['precent'] = $precent;

                // Recalculate totals
                $total_products = 0;
                $total_surcharge = 0;
                foreach ($sales_session[$action]['products'] as $value) {
                    $total_products += (float)($value['amount'] ?? 0) * (float)($value['price'] ?? 0);
                }
                foreach ($sales_session[$action]['surcharge'] ?? [] as $surcharge) {
                    $total_surcharge += (float)($surcharge['price'] ?? 0);
                }
                if (isset($sales_session[$action]['personnels']) && is_array($sales_session[$action]['personnels'])) {
                    foreach ($sales_session[$action]['personnels'] as $key => $a) {
                        $sales_session[$action]['personnels'][$key]['price'] = $total_products + $total_surcharge;
                    }
                }

                // Save updated data to cookie
                $app->setCookie($dispatch, json_encode($sales_session), time() + $setting['cookie'], '/');
                echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
            } else {
                echo json_encode(['status' => 'error', 'content' => 'Không tìm thấy sản phẩm']);
            }
        }
    })->setPermissions(['sales']);

    $app->router('/invoices-updatee/{dispatch}/{action}/transport', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $accStore, $template) {
        if ($app->method() === 'GET') {
            // Prepare template variables for rendering
            $vars['title'] = "Vận chuyển";

            // Retrieve and decode cookie data, default to empty array if unset or invalid
            $sales_session = json_decode($app->getCookie($vars['dispatch']) ?? json_encode([]), true) ?? [];

            $vars['data'] = $sales_session[$vars['action']]['transport'] ?? [];

            // Render the transport template
            echo $app->render($template . '/invoices/invoices-update-transport.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            // Set response header to JSON
            $app->header(['Content-Type' => 'application/json']);

            // Extract route parameters
            $dispatch = $vars['dispatch'];
            $action = $vars['action'];

            // Retrieve and decode cookie data, default to empty array if unset or invalid
            $sales_session = json_decode($app->getCookie($dispatch) ?? json_encode([]), true) ?? [];

            // Initialize action array if not set
            if (!isset($sales_session[$action])) {
                $sales_session[$action] = [];
            }

            // Update transport details
            $sales_session[$action]['transport'] = [
                "type" => $app->xss($_POST['type'] ?? 0),
                "price" => $app->xss(str_replace([','], '', $_POST['price'] ?? '0')),
                "content" => $app->xss($_POST['content'] ?? ''),
            ];

            // Save updated data to cookie
            $app->setCookie($dispatch, json_encode($sales_session), time() + $setting['cookie'], '/');
            echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
        }
    })->setPermissions(['sales']);

    $app->router('/invoices-updatee/{dispatch}/{action}/discount', ['GET'], function ($vars) use ($app, $jatbi, $setting, $accStore, $template) {
        // Prepare template variables for rendering
        $vars['title'] = "Giảm giá";

        // Retrieve and decode cookie data, default to empty array if unset or invalid
        $sales_session = json_decode($app->getCookie($vars['dispatch']) ?? json_encode([]), true) ?? [];

        $vars['data'] = $sales_session[$vars['action']]['discount'] ?? 0;

        // Render the discount template
        echo $app->render($template . '/invoices/invoices-update-discount.html', $vars, $jatbi->ajax());
    })->setPermissions(['sales']);

    $app->router('/invoices-updatee/{dispatch}/{action}/discount/add', ['POST'], function ($vars) use ($app, $jatbi, $setting, $accStore) {
        // Set response header to JSON
        $app->header(['Content-Type' => 'application/json']);

        // Extract route parameters
        $dispatch = $vars['dispatch'];
        $action = $vars['action'];

        // Retrieve and decode cookie data, default to empty array if unset or invalid
        $sales_session = json_decode($app->getCookie($dispatch) ?? json_encode([]), true) ?? [];

        // Initialize action array if not set
        if (!isset($sales_session[$action])) {
            $sales_session[$action] = [];
        }

        // Update discount value
        $sales_session[$action]['discount'] = $app->xss(str_replace([','], '', $_POST['value'] ?? '0'));

        // Save updated data to cookie
        $app->setCookie($dispatch, json_encode($sales_session), time() + $setting['cookie'], '/');
        echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
    })->setPermissions(['sales']);

    $app->router('/invoices-updatee/{dispatch}/{action}/details-prepay/{req}/{get}', ['POST'], function ($vars) use ($app, $jatbi, $setting, $accStore) {
        // Set response header to JSON
        $app->header(['Content-Type' => 'application/json']);

        // Extract route parameters
        $dispatch = $vars['dispatch'];
        $action = $vars['action'];
        $get = $vars['get'];
        $req = $vars['req'];

        // Retrieve and decode cookie data, default to empty array if unset or invalid
        $sales_session = json_decode($app->getCookie($dispatch) ?? json_encode([]), true) ?? [];

        // Initialize arrays if not set
        if (!isset($sales_session[$action])) {
            $sales_session[$action] = [];
        }
        if (!isset($sales_session[$action]['prepay'])) {
            $sales_session[$action]['prepay'] = [];
        }

        // Update or add prepay entry
        if (
            isset($sales_session[$action]['prepay'][$get]) &&
            is_array($sales_session[$action]['prepay'][$get]) &&
            empty($sales_session[$action]['prepay'][$get])
        ) {
            $sales_session[$action]['prepay'][$get] = [
                "code" => strtotime(date('Y-m-d H:i:s')),
                "type" => 'prepay',
                $req => $app->xss(str_replace(['.', ','], '', $_POST['value'] ?? '0')),
                "user" => json_decode($app->getCookie("accounts") ?? json_encode([]), true)['id'] ?? 0,
            ];
        } else {
            $sales_session[$action]['prepay'][$get][$req] = $app->xss(str_replace([','], '', $_POST['value'] ?? '0'));
        }

        // Save updated data to cookie
        $app->setCookie($dispatch, json_encode($sales_session), time() + $setting['cookie'], '/');
        echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
    })->setPermissions(['sales']);

    $app->router('/invoices-updatee/{dispatch}/{action}/details-prepay-add', ['POST'], function ($vars) use ($app, $jatbi, $setting, $accStore) {
        // Set response header to JSON
        $app->header(['Content-Type' => 'application/json']);

        // Extract route parameters
        $dispatch = $vars['dispatch'];
        $action = $vars['action'];

        // Retrieve and decode cookie data, default to empty array if unset or invalid
        $sales_session = json_decode($app->getCookie($dispatch) ?? json_encode([]), true) ?? [];

        // Initialize arrays if not set
        if (!isset($sales_session[$action])) {
            $sales_session[$action] = [];
        }
        if (!isset($sales_session[$action]['prepay'])) {
            $sales_session[$action]['prepay'] = [];
        }

        // Add new prepay entry
        $sales_session[$action]['prepay'][] = [
            "code" => strtotime(date('Y-m-d H:i:s')),
            "type" => 'prepay',
            "user" => json_decode($app->getCookie("accounts") ?? json_encode([]), true)['id'] ?? 0,
            "type_payments" => 18,
        ];

        // Save updated data to cookie
        $app->setCookie($dispatch, json_encode($sales_session), time() + $setting['cookie'], '/');
        echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
    })->setPermissions(['sales']);

    $app->router('/invoices-update/{dispatch}/{action}/details-deleted/{req}/{get}', ['POST'], function ($vars) use ($app, $jatbi, $setting, $accStore) {
        // Set response header to JSON
        $app->header(['Content-Type' => 'application/json']);

        // Extract route parameters
        $dispatch = $vars['dispatch'];
        $action = $vars['action'];
        $get = $vars['get'];
        $req = $vars['req'];

        // Retrieve and decode cookie data, default to empty array if unset or invalid
        $sales_session = json_decode($app->getCookie($dispatch) ?? json_encode([]), true) ?? [];

        // Initialize arrays if not set
        if (!isset($sales_session[$action])) {
            $sales_session[$action] = [];
        }

        // Remove detail entry
        unset($sales_session[$action][$req][$get]);

        // Save updated data to cookie
        $app->setCookie($dispatch, json_encode($sales_session), time() + $setting['cookie'], '/');
        echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
    })->setPermissions(['sales']);

    $app->router('/invoices-update/{dispatch}/{action}/completed/sales', ['POST'], function ($vars) use ($app, $jatbi, $setting, $accStore) {
        // Set response header to JSON
        $app->header(['Content-Type' => 'application/json']);
        
        $dispatch = $vars['dispatch'];
        $action = $vars['action'];
        
        // Retrieve cookie data and decode JSON, fallback to empty array if invalid
        $sales_session = json_decode($app->getCookie($dispatch) ?? json_encode([]), true) ?? [];
        if (json_last_error() !== JSON_ERROR_NONE) {
            $sales_session = [];
        }
        
        // Get action-specific data, ensure it exists
        $datas = $sales_session[$action] ?? [];
        
        // Initialize totals and ensure nested arrays exist
        $total_products = 0;
        $total_vat = 0;
        $total_minus = 0;
        $total_prepay = 0;
        $total_surcharge = 0;
        
        $datas['products'] = $datas['products'] ?? [];
        $datas['vat'] = $datas['vat'] ?? [];
        $datas['minus'] = $datas['minus'] ?? [];
        $datas['prepay'] = $datas['prepay'] ?? [];
        $datas['surcharge'] = $datas['surcharge'] ?? [];
        $datas['discount'] = $datas['discount'] ?? 0;
        $datas['discount_customers'] = $datas['discount_customers'] ?? 0;
        $datas['discount_card'] = $datas['discount_card'] ?? 0;
        $datas['transport'] = $datas['transport'] ?? ['price' => 0, 'type' => '', 'content' => ''];
        $datas['status'] = $datas['status'] ?? 0;
        $datas['type'] = $datas['type'] ?? 0;
        $datas['type_payments'] = $datas['type_payments'] ?? 0;
        $datas['notes'] = $datas['notes'] ?? '';
        $datas['stores'] = $datas['stores'] ?? 0;
        $datas['invoices'] = $datas['invoices'] ?? null;
        $datas['code_group'] = $datas['code_group'] ?? '';
        $datas['point'] = $datas['point'] ?? 0;
        $datas['customers'] = $datas['customers'] ?? ['id' => ''];
        $datas['personnels'] = $datas['personnels'] ?? [];
        $datas['personnels_name'] = $datas['personnels_name'] ?? '';
        $datas['products_name'] = $datas['products_name'] ?? '';
        
        // Calculate totals
        foreach ($datas['products'] as $value) {
            $total_products += ($value['amount'] ?? 0) * ($value['price'] ?? 0);
        }
        foreach ($datas['vat'] as $vat) {
            $total_vat += array_sum($vat);
        }
        foreach ($datas['minus'] as $minus) {
            $total_minus += $minus['price'] ?? 0;
        }
        foreach ($datas['prepay'] as $prepay) {
            $total_prepay += $prepay['price'] ?? 0;
        }
        foreach ($datas['surcharge'] as $surcharge) {
            $total_surcharge += $surcharge['price'] ?? 0;
        }
        
        // Calculate discounts and final payment
        $discount = ($datas['discount'] * $total_products) / 100;
        $discount_customers = ($datas['discount_customers'] * $total_products) / 100;
        $payments = ($total_products - $total_minus - $discount - $discount_customers) + $total_surcharge + ($datas['transport']['price'] ?? 0);
        
        // Validate data
        if (empty($datas['customers']['id']) || count($datas['products']) == 0 || count($datas['personnels']) == 0) {
            $error = ["status" => 'error', 'content' => 'Lỗi trống'];
        } elseif (count($datas['prepay']) == 0) {
            $error = ['status' => 'error', 'content' => 'Chọn phương thức thanh toán'];
        } elseif ($total_prepay > 0 && $total_prepay >= $payments && $datas['status'] == 2) {
            $error = ['status' => 'error', 'content' => 'Trạng thái không đúng'];
        } elseif ($total_prepay > 0 && $total_prepay < $payments && $datas['status'] == 1) {
            $error = ['status' => 'error', 'content' => 'Trạng thái không đúng'];
        }
        
        // Process if no errors
        if (!isset($error)) {
            $total = [];
            foreach ($datas['products'] as $pr) {
                $total['amount'][] = $pr['amount'] ?? 0;
            }
            
            // Prepare invoice data
            $insert = [
                "type"            => $datas['type'],
                "type_payments"   => $datas['type_payments'],
                "customers"       => $datas['customers']['id'],
                "code"            => 'PT',
                "total"           => $total_products,
                "vat"             => $total_vat,
                "minus"           => $total_minus,
                "surcharge"       => $total_surcharge,
                "prepay"          => ($total_prepay == $payments || $datas['status'] == 1) ? $payments : $total_prepay,
                "discount"        => $datas['discount'],
                "discount_card"   => $datas['discount_card'],
                "discount_customers" => $datas['discount_customers'],
                "discount_customers_price" => $discount_customers,
                "discount_price"  => $discount,
                "transport"       => $datas['transport']['price'] ?? 0,
                "payments"        => $payments,
                "user"            => $app->getSession("accounts")['id'] ?? 0,
                "date"            => date("Y-m-d"),
                "date_poster"     => date("Y-m-d H:i:s"),
                "status"          => $datas['status'],
                "notes"           => $datas['notes'],
                "stores"          => $datas['stores'],
                "active"          => $jatbi->active(30),
                "returns"         => $datas['invoices'],
                "code_group"      => $datas['code_group'],
                "branch"          => $datas['branch'] ?? "",
                "name_products"   => $datas['products_name'],
                "personnels_name" => $datas['personnels_name'],
                "amount"          => array_sum($total['amount'])
            ];
            
            // Insert invoice
            $app->insert("invoices", $insert);
            $orderId = $app->id();
            
            // Handle customer points
            if ($datas['point'] > 0) {
                $getPoint = $app->get("point", "*", ["id" => $datas['point']]);
                if (($getPoint['no_discount'] ?? 0) == 1 && $discount == 0 || ($getPoint['no_discount'] ?? 0) == 0) {
                    $insert_point = ($insert['payments'] / ($getPoint['point_price'] ?? 1)) * ($getPoint['point_point'] ?? 0);
                    $point = [
                        "customers" => $insert['customers'],
                        "type"      => 1,
                        "invoices"  => $orderId,
                        "price"     => $insert['payments'],
                        "point"     => $insert_point,
                        "user"      => $app->getSession("accounts")['id'] ?? 0,
                        "date"      => date("Y-m-d H:i:s"),
                    ];
                    $app->insert("customers_point", $point);
                }
            }
            
            // Handle warehouse operations
            if ($insert['stores'] > 0 && $insert['stores'] != '') {
                $insert_warehouses = [
                    "code"            => 'PX',
                    "type"            => 'export',
                    "data"            => 'products',
                    "stores"          => $insert['stores'],
                    "customers"       => $insert['customers'],
                    "content"         => $insert['notes'],
                    "user"            => $app->getSession("accounts")['id'] ?? 0,
                    "date"            => $insert['date'],
                    "active"          => $jatbi->active(30),
                    "date_poster"     => date("Y-m-d H:i:s"),
                    "invoices"        => $orderId,
                ];
                $app->insert("warehouses", $insert_warehouses);
                $warehousesID = $app->id();
                
                foreach ($datas['products'] as $value2) {
                    $warehouses_details = [
                        "warehouses"    => $warehousesID,
                        "type"          => 'export',
                        "data"          => 'products',
                        "products"      => $value2['products'] ?? 0,
                        "price"         => $value2['price'] ?? 0,
                        "amount"        => $value2['amount'] ?? 0,
                        "amount_used"   => $value2['amount'] ?? 0,
                        "amount_total"  => $value2['amount'] ?? 0,
                        "stores"        => $insert['stores'],
                        "notes"         => empty($insert['notes']) ? "Xuất bán hàng" : $insert['notes'],
                        "user"          => $app->getSession("accounts")['id'] ?? 0,
                        "date"          => date('Y-m-d H:i:s'),
                        "branch"        => $insert['branch'] ?? "",
                    ];
                    $app->insert("warehouses_details", $warehouses_details);
                    $iddetails = $app->id();
                    
                    $warehouses_logs = [
                        "type"      => 'export',
                        "data"      => 'products',
                        "warehouses" => $warehousesID,
                        "details"   => $iddetails,
                        "products"  => $value2['products'] ?? 0,
                        "price"     => $value2['price'] ?? 0,
                        "cost"      => $value2['cost'] ?? "",
                        "amount"    => $value2['amount'] ?? 0,
                        "total"     => ($value2['amount'] ?? 0) * ($value2['price'] ?? 0),
                        "notes"     => $warehouses_details['notes'],
                        "duration"  => $value2['duration'] ?? "",
                        "date"      => date('Y-m-d H:i:s'),
                        "user"      => $app->getSession("accounts")['id'] ?? 0,
                        "stores"    => $insert['stores'],
                        "invoices"  => $orderId,
                    ];
                    $app->insert("warehouses_logs", $warehouses_logs);
                    
                    $total_cost = ($warehouses_logs['amount'] ?? 0) * ($warehouses_logs['price'] ?? 0);
                    
                    $pro = [
                        "type"            => $datas['type'],
                        "invoices"        => $orderId,
                        "products"        => $value2['products'] ?? 0,
                        "nearsightedness" => $value2['nearsightedness'] ?? "",
                        "colors"          => $value2['colors'] ?? "",
                        "products_group"  => $value2['group'] ?? "",
                        "categorys"       => $value2['categorys'] ?? "",
                        "cost"            => $value2['cost'] ?? "",
                        "price"           => $value2['price'] ?? 0,
                        "price_vat"       => $value2['price_vat'] ?? 0,
                        "vat"             => $value2['vat'] ?? 0,
                        "vat_type"        => $value2['vat_type'] ?? 0,
                        "vat_price"       => $value2['vat_price'] ?? 0,
                        "price_old"       => $value2['price_old'] ?? 0,
                        "amount"          => $value2['amount'] ?? 0,
                        "warehouses"      => $value2['warehouses'] ?? 0,
                        "customers"       => $insert['customers'],
                        "vendor"          => $value2['vendor'] ?? "",
                        "total"           => ($value2['amount'] ?? 0) * ($value2['price'] ?? 0),
                        "total_cost"      => $total_cost,
                        "date"            => $insert['date'],
                        "date_poster"     => date("Y-m-d H:i:s"),
                        "user"            => $app->getSession("accounts")['id'] ?? 0,
                        "active"          => $jatbi->active(30),
                        "stores"          => $insert['stores'],
                        "discount"        => $value2['precent'] ?? 0,
                        "discount_price"  => ($value2['precent'] ?? 0) * ($value2['price_old'] ?? 0) / 100,
                        "minus"           => $value2['minus'] ?? 0,
                        "discount_invoices" => $datas['discount'] ?? 0,
                        "discount_priceinvoices" => ($datas['discount'] ?? 0) * ($value2['price'] ?? 0) / 100,
                        "branch"          => $app->get("products", "branch", ["id" => $value2['products'] ?? 0]) ?? "",
                        "additional_information" => $value2['additional_information'] ?? "",
                    ];
                    $app->insert("invoices_products", $pro);
                    $getpro_invoice = $app->id();
                    $pro_logs[] = $pro;
                    
                    $getPro = $app->get("products", ["id", "amount"], ["id" => $value2['products'] ?? 0]) ?? ['id' => 0, 'amount' => 0];
                    $app->update("products", ["amount" => $getPro['amount'] - ($pro['amount'] ?? 0)], ["id" => $getPro['id']]);
                }
                
                // Insert personnels
                if (!empty($datas['personnels'])) {
                    foreach ($datas['personnels'] as $per) {
                        $person = [
                            "invoices"    => $orderId,
                            "personnels"  => $per['id'] ?? 0,
                            "price"       => $app->xss(str_replace(',', '', $per['price'] ?? 0)),
                            "date"        => date('Y-m-d H:i:s'),
                            "user"        => $app->getSession("accounts")['id'] ?? 0,
                            "stores"      => $insert['stores'],
                            "invoices_type" => $insert['type'],
                        ];
                        $app->insert("invoices_personnels", $person);
                    }
                }
                
                // Insert transport
                if (!empty($datas['transport']['type'])) {
                    $transports = [
                        "transport"    => $datas['transport']['type'],
                        "code"        => $insert['code'],
                        "invoices"    => $orderId,
                        "price"       => $datas['transport']['price'] ?? 0,
                        "status"      => 1,
                        "content"     => $app->xss($datas['transport']['content'] ?? ''),
                        "date"        => date('Y-m-d H:i:s'),
                        "user"        => $app->getSession("accounts")['id'] ?? 0,
                        "stores"      => $insert['stores'],
                    ];
                    $app->insert("invoices_transport", $transports);
                }
                
                // Insert minus details
                foreach ($datas['minus'] as $minus) {
                    if (($minus['price'] ?? 0) > 0) {
                        $minus_insert = [
                            "code"        => $minus['code'] ?? '',
                            "invoices"    => $orderId,
                            "type"        => "minus",
                            "price"       => $app->xss(str_replace(',', '', $minus['price'] ?? 0)),
                            "content"     => $app->xss($minus['content'] ?? ''),
                            "date"        => date('Y-m-d H:i:s', strtotime($app->xss($minus['date'] ?? date('Y-m-d')))),
                            "user"        => $minus['user'] ?? $app->getSession("accounts")['id'] ?? 0,
                            "date_poster" => date('Y-m-d H:i:s'),
                            "stores"      => $insert['stores'],
                        ];
                        $app->insert("invoices_details", $minus_insert);
                    }
                }
                
                // Insert surcharge details
                foreach ($datas['surcharge'] as $surcharge) {
                    if (($surcharge['price'] ?? 0) > 0) {
                        $surcharge_insert = [
                            "code"        => $surcharge['code'] ?? '',
                            "invoices"    => $orderId,
                            "type"        => "surcharge",
                            "price"       => $app->xss(str_replace(',', '', $surcharge['price'] ?? 0)),
                            "content"     => $app->xss($surcharge['content'] ?? ''),
                            "date"        => date('Y-m-d H:i:s', strtotime($app->xss($surcharge['date'] ?? date('Y-m-d')))),
                            "user"        => $surcharge['user'] ?? $app->getSession("accounts")['id'] ?? 0,
                            "date_poster" => date('Y-m-d H:i:s'),
                            "stores"      => $insert['stores'],
                        ];
                        $app->insert("invoices_details", $surcharge_insert);
                    }
                }
                
                // Handle expenditure for status 1, type 2
                $expenditure_type = 1;
                $expenditure_code = '';
                if ($datas['status'] == 1 && $datas['type'] == 2) {
                    foreach ($datas['prepay'] as $prepay) {
                        $getType_Payment = $app->get("type_payments", ["id", "code", "debt", "has"], ["id" => $prepay['type_payments'] ?? 0]) ?? ['id' => 0, 'code' => '', 'debt' => 0, 'has' => 0];
                        $prepay_payment = [
                            "type_payments" => $prepay['type_payments'] ?? 0,
                            "code"         => strtotime(date('Y-m-d H:i:s')),
                            "invoices"     => $orderId,
                            "type"         => "prepay",
                            "price"        => count($datas['prepay']) == 1 ? $insert['payments'] : str_replace(',', '', $prepay['price'] ?? 0),
                            "content"      => $app->xss($insert['notes'] ?? ''),
                            "date"         => date('Y-m-d H:i:s', strtotime($app->xss($insert['date'] ?? date('Y-m-d')))),
                            "user"         => $app->getSession("accounts")['id'] ?? 0,
                            "date_poster"  => date('Y-m-d H:i:s'),
                            "stores"       => $insert['stores'],
                        ];
                        $app->insert("invoices_details", $prepay_payment);
                        
                        $code = count($datas['prepay']) == 1 ? ($getType_Payment['code'] ?? '') : 'CK';
                        $app->update("invoices", ["code" => $code], ["id" => $orderId]);
                        
                        $expenditure = [
                            "type"         => $expenditure_type,
                            "debt"         => $app->xss($getType_Payment['debt'] ?? 0),
                            "has"          => $app->xss($getType_Payment['has'] ?? 0),
                            "price"        => count($datas['prepay']) == 1 ? $insert['payments'] : str_replace(',', '', $prepay['price'] ?? 0),
                            "content"      => $app->xss($insert['code'] ?? ''),
                            "date"         => $app->xss($insert['date'] ?? date('Y-m-d')),
                            "ballot"       => $app->xss($prepay_payment['code'] ?? ''),
                            "invoices"     => $app->xss($orderId),
                            "customers"    => $app->xss($insert['customers'] ?? ''),
                            "notes"        => $app->xss($insert['notes'] ?? ''),
                            "user"         => $app->getSession("accounts")['id'] ?? 0,
                            "date_poster"  => date("Y-m-d H:i:s"),
                            "stores"       => $insert['stores'],
                        ];
                        $app->insert("expenditure", $expenditure);
                    }
                }
                
                // Handle expenditure for status 1, type 1
                if ($datas['status'] == 1 && $datas['type'] == 1) {
                    $getType_Payment3 = $app->get("type_payments", ["id", "code", "debt", "has"], ["id" => $datas['type_payments'] ?? 0]) ?? ['id' => 0, 'code' => '', 'debt' => 0, 'has' => 0];
                    $prepay_payment3 = [
                        "type_payments" => $getType_Payment3['id'],
                        "code"         => strtotime(date('Y-m-d H:i:s')),
                        "invoices"     => $orderId,
                        "type"         => "prepay",
                        "price"        => $app->xss($insert['payments'] ?? 0),
                        "content"      => $app->xss($insert['content'] ?? ''),
                        "date"         => date('Y-m-d H:i:s', strtotime($app->xss($insert['date'] ?? date('Y-m-d')))),
                        "user"         => $app->getSession("accounts")['id'] ?? 0,
                        "date_poster"  => date('Y-m-d H:i:s'),
                        "stores"       => $insert['stores'],
                    ];
                    $app->insert("invoices_details", $prepay_payment3);
                    
                    $code = count($datas['prepay']) == 1 ? ($getType_Payment3['code'] ?? '') : 'CK';
                    $app->update("invoices", ["code" => $code], ["id" => $orderId]);
                    
                    $expenditure3 = [
                        "type"         => $expenditure_type,
                        "debt"         => $app->xss($getType_Payment3['debt'] ?? 0),
                        "has"          => $app->xss($getType_Payment3['has'] ?? 0),
                        "price"        => $insert['payments'] ?? 0,
                        "content"      => $app->xss($insert['code'] ?? ''),
                        "date"         => $app->xss($insert['date'] ?? date('Y-m-d')),
                        "ballot"       => $app->xss($prepay_payment3['code'] ?? ''),
                        "invoices"     => $app->xss($orderId),
                        "customers"    => $app->xss($insert['customers'] ?? ''),
                        "notes"        => $app->xss($insert['notes'] ?? ''),
                        "user"         => $app->getSession("accounts")['id'] ?? 0,
                        "date_poster"  => date("Y-m-d H:i:s"),
                        "stores"       => $insert['stores'],
                    ];
                    $app->insert("expenditure", $expenditure3);
                }
                
                // Handle expenditure for status 2, type 1
                if ($datas['status'] == 2 && $datas['type'] == 1) {
                    $getType_Payment4 = $app->get("type_payments", ["id", "code", "debt", "has"], ["id" => $datas['type_payments'] ?? 0]) ?? ['id' => 0, 'code' => '', 'debt' => 0, 'has' => 0];
                    $prepay_payment4 = [
                        "type_payments" => $getType_Payment4['id'],
                        "code"         => strtotime(date('Y-m-d H:i:s')),
                        "invoices"     => $orderId,
                        "type"         => "debt",
                        "price"        => $app->xss($insert['payments'] ?? 0),
                        "content"      => $app->xss($insert['content'] ?? ''),
                        "date"         => date('Y-m-d H:i:s', strtotime($app->xss($insert['date'] ?? date('Y-m-d')))),
                        "user"         => $app->getSession("accounts")['id'] ?? 0,
                        "date_poster"  => date('Y-m-d H:i:s'),
                        "stores"       => $insert['stores'],
                    ];
                    $expenditure4 = [
                        "type"         => 4,
                        "debt"         => 131,
                        "has"          => 5111,
                        "price"        => $insert['payments'] ?? 0,
                        "content"      => $app->xss($insert['notes'] ?? ''),
                        "date"         => $app->xss($insert['date'] ?? date('Y-m-d')),
                        "ballot"       => strtotime(date('Y-m-d H:i:s')),
                        "invoices"     => $app->xss($orderId),
                        "customers"    => $app->xss($insert['customers'] ?? ''),
                        "notes"        => $app->xss($insert['notes'] ?? ''),
                        "user"         => $app->getSession("accounts")['id'] ?? 0,
                        "date_poster"  => date("Y-m-d H:i:s"),
                        "stores"       => $insert['stores'],
                    ];
                    $app->insert("expenditure", $expenditure4);
                }
                
                // Log invoice creation
                $app->insert("invoices_logs", [
                    "invoices" => $orderId,
                    "user"     => $app->getSession("accounts")['id'] ?? 0,
                    "date"     => date("Y-m-d H:i:s"),
                    "content"  => "Khởi tạo hóa đơn",
                    "datas"    => json_encode($sales_session[$action] ?? []),
                ]);
                
                // Remove action data from cookie
                unset($sales_session[$action]);
                $app->setCookie($dispatch, json_encode($sales_session), time() + $setting['cookie'], '/');
                
                // Return success response
                echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công', "views" => $orderId, "print" => "/invoices/invoices-sales-print/" . $orderId]);
            } else {
                // Return error response
                echo json_encode(['status' => 'error', 'content' => $error['content'], "url" => $_SERVER['HTTP_REFERER'] ?? '']);
            }
        } else {
            echo json_encode(['status' => 'error', 'content' => $error['content'], "url" => $_SERVER['HTTP_REFERER']]);
        }
    })->setPermissions(['sales']);


    $app->router('/invoices-update/{dispatch}/{action}/success/sales', ['POST'], function ($vars) use ($app, $jatbi, $setting, $accStore) {
        $app->header(['Content-Type' => 'application/json']);
        $dispatch = $vars['dispatch'];
        $action = $vars['action'];
        $datas = $_SESSION[$dispatch][$action] ?? [];
        $total_products = 0;
        $total_vat = 0;
        $total_minus = 0;
        $total_prepay = 0;
        $total_surcharge = 0;
        $error = [];
        foreach (($datas['products'] ?? []) as $value) {
            $total_products += $value['amount'] * $value['price'];
        }
        foreach (($datas['vat'] ?? []) as $vat) {
            $total_vat += (float)array_sum($vat);
        }
        foreach (($datas['minus'] ?? []) as $minus) {
            $total_minus += (float)$minus['price'];
        }
        foreach (($datas['prepay'] ?? []) as $prepay) {
            $total_prepay += (float)$prepay['price'];
        }
        foreach (($datas['surcharge'] ?? []) as $surcharge) {
            $total_surcharge += (float)$surcharge['price'];
        }
        $discount = (($datas['discount'] ?? 0) * $total_products) / 100;
        $discount_customers = (($datas['discount_customers'] ?? 0) * $total_products) / 100;
        if ($dispatch == "returns") {
            $payments = ($total_products - $total_minus - $discount - $discount_customers) + $total_surcharge;
        }

        if (!isset($datas['customers']['id']) || count($datas['products'] ?? []) == 0 || empty($datas['prepay']) && ($datas['type'] ?? 0) == 1 || count($datas['personnels'] ?? []) == 0) {
            $error = ["status" => 'error', 'content' => $jatbi->lang("Vui lòng không để trống")];
        } elseif ($total_products > 0 && $total_prepay == 0 && !isset($total_prepay) && $datas['status'] == 1 || $total_prepay == 0 && !isset($total_prepay) && $datas['type'] == 1) {
            $error = ['status' => 'error', 'content' => 'Nhập phương thức thanh toán và số tiền cần thanh toán',];
        }
        if (count($error) == 0) {
            $insert = [
                "type"            => $datas['type'] ?? 0,
                "type_payments"    => $datas['type_payments'] ?? null,
                "customers"        => $datas['customers']['id'] ?? null,
                "code"            => 'PT',
                "total"            => $total_products ?? 0,
                "vat"            => $total_vat ?? 0,
                "minus"            => $total_minus ?? 0,
                "surcharge"        => $total_surcharge ?? 0,
                "prepay"        => $datas['status'] == 1 ? $payments : 0,
                "discount"        => $datas['discount'] ?? 0,
                "discount_card"    => $datas['discount_card'] ?? null,
                "discount_customers" => $datas['discount_customers'] ?? "",
                "discount_customers_price" => $discount_customers,
                "discount_price" => $discount,
                "transport"        => $datas['transport']['price'] ?? 0,
                "payments"        => $payments,
                "user"            => $app->getSession("accounts")['id'] ?? 0,
                "date"            => date("Y-m-d"),
                "date_poster"    => date("Y-m-d H:i:s"),
                "status"        => $datas['status'] ?? 0,
                "notes"            => $datas['notes'] ?? "",
                "stores"        => $datas['stores'] ?? null,
                "active"        => $jatbi->active(30),
                "returns"        => $datas['invoices'] ?? null,
                "code_group"    => $datas['code_group'] ?? null,
                "branch"        => $datas['branch'] ?? null,
            ];
            $app->insert("invoices", $insert);
            $orderId = $app->id();
            if ($insert["type"] == 3) {
                $app->update("invoices", ["returns" => $orderId, "status_return" => 1], ["id" => $insert['returns']]);
            }
            if ($datas['point'] > 0) {
                $getPoint = $app->get("point", "*", ["id" => $datas['point']]);
                if ($getPoint['no_discount'] == 1 && $discount == 0 || $getPoint['no_discount'] == 0) {
                    if ($dispatch == "returns") {
                        $insert_point = - ($insert['payments'] / $getPoint['point_price']) * $getPoint['point_point'];
                    } else {
                        $insert_point = ($insert['payments'] / $getPoint['point_price']) * $getPoint['point_point'];
                    }
                    $point = [
                        "customers" => $insert['customers'] ?? null,
                        "type"        => 1,
                        "invoices"     => $orderId,
                        "price"     => $insert['payments'] ?? 0,
                        "point"     => $insert_point ?? 0,
                        "user"        => $app->getSession("accounts")['id'] ?? 0,
                        "date"        => date("Y-m-d H:i:s"),
                    ];
                    $app->insert("customers_point", $point);
                }
            }
            if ($dispatch == "returns") {
                $insert_warehouses_return = [
                    "code"            => 'PN',
                    "type"            => 'import',
                    "data"            => 'products',
                    "stores"        => $insert['stores'] ?? null,
                    "customers"        => $insert['customers'] ?? null,
                    "content"        => $insert['notes'] ?? "",
                    "user"            => $app->getSession("accounts")['id'] ?? 0,
                    "date"            => $insert['date'] ?? null,
                    "active"        => $jatbi->active(30),
                    "date_poster"    => date("Y-m-d H:i:s"),
                    "branch"        => $insert['branch'] ?? null,
                ];
                $app->insert("warehouses", $insert_warehouses_return);
                $warehouses_return = $app->id();
                foreach ($datas['products'] as $value3) {
                    if ($value3['amount'] > 0) {
                        $pro = [
                            "type"            => $datas['type'] ?? null,
                            "invoices"        => $orderId,
                            "products"        => $value3['products'] ?? null,
                            "nearsightedness" => $value3['nearsightedness'] ?? null,
                            "farsightedness" => $value3['farsightedness'] ?? null,
                            "astigmatism"     => $value3['astigmatism'] ?? null,
                            "colors"        => $value3['colors'] ?? null,
                            "products_group" => $value3['group'] ?? null,
                            "categorys"        => $value3['categorys'] ?? null,
                            "cost"            => $value3['cost'] ?? null,
                            "price"            => $value3['price'] ?? 0,
                            "price_vat"        => $value3['price_vat'] ?? 0,
                            "vat"            => $value3['vat'] ?? 0,
                            "vat_type"        => $value3['vat_type'] ?? 0,
                            "vat_price"        => $value3['vat_price'] ?? 0,
                            "price_old"        => $value3['price_old'] ?? 0,
                            "amount"        => $value3['amount'] ?? 0,
                            "warehouses"    => $value3['warehouses'] ?? null,
                            "customers"        => $insert['customers'] ?? null,
                            "vendor"        => $value3['vendor'] ?? null,
                            "total"            => (float)($value3['amount'] ?? 0) * (float)($value3['price'] ?? 0),
                            "total_cost"    => $total_cost ?? 0,
                            "date"            => $insert['date'] ?? null,
                            "date_poster"    => date("Y-m-d H:i:s"),
                            "user"            => $app->getSession("accounts")['id'] ?? 0,
                            "active"        => $jatbi->active(30),
                            "returns"        => $insert['returns'] ?? null,
                            "discount"        => $value3['discount'] == '' ? 0 : $value3['discount'],
                            "discount_price" => ($value3['discount']) * $value3['price_old'] / 100 == '' ? 0 : ($value3['discount']) * $value3['price_old'] / 100,
                            "discount_invoices" => $datas['discount'] == '' ? 0 : $datas['discount'],
                            "discount_priceinvoices" => ($datas['discount']) * $value3['price'] / 100 == '' ? 0 : ($datas['discount']) * $value3['price'] / 100,
                            "minus"            => !isset($value3['minus']) ? 0 : $value3['minus'],
                            "stores"        => $insert['stores'],
                            "branch"        => $app->get("products", "branch", ["id" => $value3['products']]),
                        ];
                        $app->insert("invoices_products", $pro);
                        $getpro_invoice = $app->id();
                        $app->update("invoices", ["amount_return" => $app->get("invoices", "amount_return", ["id" => $datas['invoices']]) + $value3['amount']], ["id" => $datas['invoices']]);
                        $app->update("invoices_products", ["returns" => $getpro_invoice], ["id" => $pro['returns']]);
                        $getducts = $app->get("products", ["id", "amount"], ["id" => $pro['products']]);
                        $app->update("products", ["amount" => $getducts["amount"] + $pro["amount"]], ["id" => $getducts["id"]]);
                        $insert_warehouses_details_return = [
                            "warehouses"    => $warehouses_return,
                            "type"            => 'import',
                            "data"            => 'products',
                            "products"        => $pro['products'],
                            "price"            => $pro['price'],
                            "amount"        => $pro['amount'],
                            "amount_total"    => $pro['amount'],
                            "stores"        => $insert['stores'],
                            "notes"            => "Nhập từ trả hàng",
                            "user"            => $app->getSession("accounts")['id'] ?? 0,
                            "date"            => $insert['date'],
                            "branch"        => $insert['branch'],
                            "user" => $app->getSession("accounts")['id'] ?? 0,
                        ];
                        $app->insert("warehouses_details", $insert_warehouses_details_return);
                        $GetID = $app->id();
                        $warehouses_logs = [
                            "type" => $insert_warehouses_details_return['type'],
                            "data" => $insert_warehouses_details_return['data'],
                            "warehouses" => $warehouses_return,
                            "details" => $GetID,
                            "products" => $pro['products'],
                            "price" => $pro['price'],
                            "amount" => $pro['amount'],
                            "total" => $pro['amount'] * $pro['price'],
                            "notes" => $insert_warehouses_details_return['notes'],
                            "date"     => date('Y-m-d H:i:s'),
                            "user"     => $app->getSession("accounts")['id'] ?? 0,
                            "stores"        => $insert['stores'],
                            "branch"        => $insert['branch'],
                            "invoices"        => $orderId,
                        ];
                        $app->insert("warehouses_logs", $warehouses_logs);
                        $pro_logs[] = $pro;
                    }
                }
            }
            if (count($datas['personnels']) > 0) {
                foreach ($datas['personnels'] as $key => $per) {
                    $person = [
                        "invoices"    => $orderId,
                        "personnels" => $per['id'],
                        //"commission"=> $per['commission'],
                        "price"     => $app->xss(str_replace([','], '', $per['price'])),
                        "date"         => date('Y-m-d H:i:s'),
                        "user"        => $app->getSession("accounts")['id'] ?? 0,
                        "stores"    => $insert['stores'],
                        "invoices_type" => $insert['type'],
                    ];
                    $app->insert("invoices_personnels", $person);
                }
            }
            if (count($datas['transport'] ?? []) > 0) {
                $transports = [
                    "transport"    => $datas['transport']['type'],
                    "code"        => $insert['code'],
                    "invoices"    => $orderId,
                    "price"     => $datas['transport']['price'],
                    "status"    => 1,
                    "content"     => $app->xss($datas['transport']['content']),
                    "date"         => date('Y-m-d H:i:s'),
                    "user"        => $app->getSession("accounts")['id'] ?? 0,
                    "stores"    => $insert['stores'],
                ];
                $app->insert("invoices_transport", $transports);
            }
            foreach (($datas['minus'] ?? []) as $minus) {
                if ((float)($minus['price'] ?? 0) > 0) {
                    $minus_insert = [
                        "code"        => $minus['code'],
                        "invoices"    => $orderId,
                        "type"        => "minus",
                        "price"     => $app->xss(str_replace([','], '', $minus['price'])),
                        "content"     => $app->xss($minus['content']),
                        "date"         => date('Y-m-d H:i:s', strtotime($app->xss($minus['date']))),
                        "user"        => $minus['user'],
                        "date_poster" => date('Y-m-d H:i:s'),
                        "stores"    => $insert['stores'],
                    ];
                    $app->insert("invoices_details", $minus_insert);
                }
            }
            foreach (($datas['surcharge'] ?? []) as $surcharge) {
                if ($surcharge['price'] > 0) {
                    $surcharge_insert = [
                        "code"        => $surcharge['code'],
                        "invoices"    => $orderId,
                        "type"        => "surcharge",
                        "price"     => $app->xss(str_replace([','], '', $surcharge['price'])),
                        "content"     => $app->xss($surcharge['content']),
                        "date"         => date('Y-m-d H:i:s', strtotime($app->xss($surcharge['date']))),
                        "user"        => $surcharge['user'],
                        "date_poster" => date('Y-m-d H:i:s'),
                        "stores"    => $insert['stores'],
                    ];
                    $app->insert("invoices_details", $surcharge_insert);
                }
            }
            if ($dispatch == "returns") {
                $expenditure_type = 2;
                $expenditure_code = '-';
            }
            if (count($datas['prepay'] ?? []) > 0 && $datas['status'] == '1') {
                foreach ($datas['prepay'] as $prepay) {
                    //if($prepay['price']>0){
                    $getType_Payment = $app->get("type_payments", ["id", "code", "debt", "has"], ["id" => $prepay['type_payments']]);
                    $prepay_insert = [
                        "type_payments"    => $prepay['type_payments'],
                        "code"        => strtotime(date('Y-m-d H:i:s')),
                        "invoices"    => $orderId,
                        "type"        => "prepay",
                        "price"     => $app->xss(str_replace([','], '', $prepay['price'])),
                        "content"     => $prepay['content'] ?? "",
                        "date"         => date('Y-m-d H:i:s', strtotime($app->xss($prepay['date'] ?? ""))),
                        "user"        => $prepay['user'],
                        "date_poster" => date('Y-m-d H:i:s'),
                        "stores"    => $insert['stores'],
                    ];
                    $app->insert("invoices_details", $prepay_insert);
                    if (count($datas['prepay']) == 1) {
                        $code = $getType_Payment['code'];
                    }
                    if (count($datas['prepay']) > 1) {
                        $code = 'CK';
                    }
                    $update = [
                        "code"            => $code,
                    ];
                    $app->update("invoices", $update, ["id" => $orderId]);
                    $expenditure = [
                        "type"             => $expenditure_type,
                        "debt"             => $app->xss($getType_Payment['debt']),
                        "has"             => $app->xss($getType_Payment['has']),
                        "price"         => $expenditure_code . $prepay_insert['price'],
                        "content"         => $app->xss($prepay_insert['content']),
                        "date"             => $app->xss($prepay_insert['date']),
                        "ballot"         => $app->xss($prepay_insert['code']),
                        "invoices"         => $app->xss($orderId),
                        "customers"     => $app->xss($insert['customers']),
                        "notes"         => $app->xss($insert['notes']),
                        "user"            => $app->getSession("accounts")['id'] ?? 0,
                        "date_poster"    => date("Y-m-d H:i:s"),
                        "stores"    => $insert['stores'],
                    ];
                    $app->insert("expenditure", $expenditure);
                    $prepay_insert_log[] = $prepay_insert;
                    //}
                }
            }
            if (count($datas['prepay']) > 0 && $datas['status'] == '2') {
                foreach ($datas['prepay'] as $prepay) {
                    $getType_Payment = $app->get("type_payments", ["id", "code", "debt", "has"], ["id" => $prepay['type_payments']]);
                    $prepay_insert = [
                        "type_payments"    => $prepay['type_payments'],
                        "code"        => strtotime(date('Y-m-d H:i:s')),
                        "invoices"    => $orderId,
                        "type"        => "prepay",
                        "price"     => $app->xss(str_replace([','], '', $prepay['price'])),
                        "content"     => $prepay['content'],
                        "date"         => date('Y-m-d H:i:s', strtotime($app->xss($prepay['date']))),
                        "user"        => $prepay['user'],
                        "date_poster" => date('Y-m-d H:i:s'),
                        "stores"    => $insert['stores'],
                    ];
                    $app->insert("invoices_details", $prepay_insert);
                    if (count($datas['prepay']) == 1) {
                        $code = $getType_Payment['code'];
                    }
                    if (count($datas['prepay']) > 1) {
                        $code = 'CK';
                    }
                    $update = [
                        "code"            => $code,
                    ];
                    $app->update("invoices", $update, ["id" => $orderId]);
                    $expenditure = [
                        "type"             => 5,
                        "debt"             => 5111,
                        "has"             => 131,
                        "price"         => $expenditure_code . $payments,
                        "content"         => $app->xss($prepay_insert['content']),
                        "date"             => $app->xss($prepay_insert['date']),
                        "ballot"         => $app->xss($prepay_insert['code']),
                        "invoices"         => $app->xss($orderId),
                        "customers"     => $app->xss($insert['customers']),
                        "notes"         => $app->xss($insert['notes']),
                        "user"            => $app->getSession("accounts")['id'] ?? 0,
                        "date_poster"    => date("Y-m-d H:i:s"),
                        "stores"    => $insert['stores'],
                    ];
                    $app->insert("expenditure", $expenditure);
                }
            }
            $app->insert("invoices_logs", [
                "invoices" => $orderId,
                "user" => $app->getSession("accounts")['id'] ?? 0,
                "date" => date("Y-m-d H:i:s"),
                "content" => "Khởi tạo hóa đơn trả hàng",
                "datas" => $_SESSION[$dispatch][$action],
            ]);
            $jatbi->logs('invoices', 'add', [$insert, $pro_logs, $datas, ($transports ?? 0), $prepay_insert_log], $orderId);
            unset($_SESSION[$dispatch][$action]);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công"), "views" => $orderId, "print" => "/invoices/invoices-print/" . $orderId]);
        } else {
            echo json_encode(['status' => 'error', 'content' => $error['content'], "url" => $_SERVER['HTTP_REFERER']]);
        }
    })->setPermissions(['sales']);

    $app->router('/invoices-sales-print/{get}', ['GET'], function ($vars) use ($app, $jatbi, $setting, $accStore, $template) {
        // $app->header(['Content-Type' => 'application/json']);

        $id = $vars['get'] ?? 0;

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

        // Áp dụng lại logic tính toán phức tạp từ code cũ
        $total_payment_calculated = ($data['total'] - $data['discount_price'] - $data['minus'] - $data['discount_customers_price']) + $data['surcharge'] + $data['transport'];
        $data['total_payment_calculated'] = $total_payment_calculated;

        $vars['data'] = $data;
        $vars['title'] = $jatbi->lang("In hóa đơn");

        echo $app->render($template . '/invoices/print.html', $vars, $jatbi->ajax());

        // $app->setSession($dispatch, $sales_session);
    })->setPermissions(['sales']);

    $app->router('/invoices-customers/{dispatch}/{action}/completed/{get}', ['POST'], function ($vars) use ($app, $jatbi, $setting, $accStore) {
        $app->header(['Content-Type' => 'application/json']);
        $dispatch = $vars['dispatch'];
        $action = $vars['action'];
        $type = "completed";
        $datala = $_SESSION[$dispatch][$action];
        $data = $app->get("invoices", ["id", "customers"], ["id" => $app->xss($vars['get']), "deleted" => 0, "stores" => $accStore]);
        if ($data > 1) {
            if ($type == 'completed') {
                $custo = [
                    "customers" => $datala['customers']['id'],
                ];
                $app->update("invoices", $custo, ["id" => $data["id"]]);
                $app->update("invoices_products", $custo, ["invoices" => $data["id"]]);
                echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
                unset($_SESSION[$dispatch][$action]);
            }
        }
    })->setPermissions(['sales']);

    $app->router('/invoices-personnels/{action}/{type}/{get}/{id}', ['POST'], function ($vars) use ($app, $jatbi, $setting, $accStore) {
        $app->header(['Content-Type' => 'application/json']);
        $router['2'] = $vars['action'];
        $router['3'] = $vars['type'];
        $router['4'] = $vars['get'];
        $router['5'] = $vars['id'];
        $action = $router['2'];
        if ($router['3'] == 'personnels') {
            if ($router['4'] == 'add') {
                $data = $app->get("personnels", ["id", "name", "code"], ["id" => $app->xss($_POST['value']), "status" => "A", "deleted" => 0,]);
                if ($data > 1) {
                    $_SESSION['personnels'][$action]['personnels'][$data['id']] = [
                        "personnels"    => $data['id'],
                        "price"            => 0,
                        "name"            => $data['name'],
                        "code"            => $data['code'],
                    ];
                    echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
                } else {
                    echo json_encode(['status' => 'success', 'content' => 'Cập nhật thất bại']);
                }
            } elseif ($router['4'] == 'deleted') {
                unset($_SESSION['personnels'][$action]['personnels'][$router['5']]);
                echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
            } elseif ($router['4'] == 'price') {
                $_SESSION['personnels'][$action]['personnels'][$router['5']][$router['4']] = $app->xss(str_replace([','], '', $_POST['value']));
                echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
            } else {
                $_SESSION['personnels'][$action]['personnels'][$router['5']][$router['4']] = $app->xss($_POST['value']);
                echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
            }
        } elseif ($router['3'] == 'cancel') {
            unset($_SESSION['personnels'][$action]);
            echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
        } elseif ($router['3'] == 'completed') {
            $SelectType_payments = $_SESSION['personnels'][$action];
            $get = $app->get("invoices", "payments", ["id" => $SelectType_payments['order']]);
            $sumprice = 0;
            $error_warehouses = "";
            $error = [];
            $errors = "";
            foreach ($SelectType_payments['personnels'] as $key => $tt) {
                $sumprice += (float)$tt['price'];
                if ($tt['price'] < 0) {
                    $error_warehouses = 'true';
                }
            }
            if ($get != $sumprice) {
                $errors = 'false';
            }
            if (count($SelectType_payments['personnels']) == 0) {
                $error = ["status" => 'error', 'content' => $jatbi->lang("Vui lòng không để trống")];
            } elseif ($error_warehouses == 'true') {
                $error = ['status' => 'error', 'content' => $jatbi->lang("Vui lòng nhập số tiền")];
            } elseif ($errors == 'false') {
                $error = ['status' => 'error', 'content' => $jatbi->lang("Tổng tiền hoa hồng không trùng với số tiền đơn hàng")];
            }
            if (count($error) == 0) {
                $app->update("invoices_personnels", ["deleted" => 1], ["invoices" => $_SESSION['personnels'][$action]['order']]);
                foreach ($SelectType_payments['personnels'] as $key => $value) {
                    $getType_Payment = $app->get("personnels", ["id", "code", "name"], ["id" => $value['personnels']]);
                    $get_invoices = $app->get("invoices", ["id", "type", "date", "stores", "payments"], ["id" => $_SESSION['personnels'][$action]['order']]);
                    $insert = [
                        "invoices"         => $get_invoices['id'],
                        "invoices_type"    => $get_invoices['type'],
                        "price"            => $value['price'],
                        "personnels"    => $value['personnels'],
                        "date"            => $get_invoices['date'],
                        "user"            => $app->getSession("accounts")['id'] ?? 0,
                        "stores"        => $get_invoices['stores'],
                    ];
                    $app->insert("invoices_personnels", $insert);
                }
                $jatbi->logs('invoices_personnels', $action, [$insert, $_SESSION['personnels'][$action]], $SelectType_payments['order']);
                unset($_SESSION['personnels'][$action]);
                echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
            } else {
                echo json_encode(['status' => 'error', 'content' => $error['content']]);
            }
        }
    })->setPermissions(['sales']);

})->middleware('login');
