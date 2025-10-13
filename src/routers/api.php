<?php
if (!defined('ECLO'))
    die("Hacking attempt");
$app->group($setting['manager'] . "/api", function ($app) use ($jatbi, $setting) {
    $app->router("/district", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $searchValue = isset($_POST['keyword']) ? $_POST['keyword'] : '';
            $parent = isset($_POST['parent']) ? $_POST['parent'] : '';
            $where = [
                "AND" => [
                    "OR" => [
                        "district.name[~]" => $searchValue,
                    ],
                    "district.status" => 'A',
                    "district.deleted" => 0,
                ]
            ];
            if ($parent) {
                $where['AND']['district.province'] = $parent;
            }
            $app->select("district", "*", $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "value" => $data['id'],
                    "text" => $data['name'],
                ];
            });
            echo json_encode($datas);
        }
    });

    $app->router("/province", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'POST') {

            $searchValue = isset($_POST['keyword']) ? $_POST['keyword'] : '';
            $selected_id = $_POST['id'] ?? null;
            $where = [
                "AND" => [
                    "OR" => [
                        "province.name[~]" => $searchValue,
                    ],
                    "province.status" => 'A',
                    "province.deleted" => 0,
                ]
            ];

            $app->select("province", "*", $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "value" => $data['id'],
                    "text" => $data['name'],
                ];
            });
            echo json_encode($datas);
        }
    });

    $app->router("/ward", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $searchValue = isset($_POST['keyword']) ? $_POST['keyword'] : '';
            $parent_district = isset($_POST['parent']) ? $_POST['parent'] : '';
            $where = [
                "AND" => [
                    "OR" => [
                        "ward.name[~]" => $searchValue,
                    ],
                    "ward.status" => 'A',
                    "ward.deleted" => 0,
                ]
            ];
            if ($parent_district) {
                $where['AND']['ward.district'] = $parent_district;
            }
            $app->select("ward", "*", $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "value" => $data['id'],
                    "text" => $data['name'],
                ];
            });
            echo json_encode($datas);
        }
    });

    $app->router("/stores", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $searchValue = isset($_POST['keyword']) ? $_POST['keyword'] : '';
            $where = [
                "AND" => [
                    "OR" => [
                        "stores.name[~]" => $searchValue,
                    ],
                    "stores.status" => 'A',
                    "stores.deleted" => 0,
                ]
            ];

            $app->select("stores", ["id", "name"], $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "value" => $data['id'],
                    "text" => $data['name'],
                ];
            });
            echo json_encode($datas);
        }
    });
    $app->router("/branch", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $searchValue = isset($_POST['keyword']) ? $_POST['keyword'] : '';
            $parent = isset($_POST['parent']) ? $_POST['parent'] : '';
            $where = [
                "AND" => [
                    "OR" => [
                        "branch.name[~]" => $searchValue,
                    ],
                    "branch.status" => 'A',
                    "branch.deleted" => 0,
                ]
            ];
            if ($parent) {
                $where['AND']['branch.stores'] = $parent;
            }
            $app->select("branch", "*", $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "value" => $data['id'],
                    "text" => $data['name'],
                ];
            });
            echo json_encode($datas);
        }
    });

    $app->router("/customers", ['POST'], function ($vars) use ($app, $jatbi, $setting) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);

        // Lấy từ khóa tìm kiếm từ POST
        $searchValue = isset($_POST['keyword']) ? $app->xss($_POST['keyword']) : '';

        // Xây dựng điều kiện truy vấn
        $where = [
            "AND" => [
                "OR" => [
                    "name[~]" => $searchValue,
                    "code[~]" => $searchValue,
                    "phone[~]" => $searchValue,
                    "email[~]" => $searchValue,
                ],
                "status" => 'A',
                "deleted" => 0,
            ],
            "LIMIT" => 10 // Giới hạn số lượng kết quả để tối ưu
        ];

        // Thực hiện truy vấn và định dạng dữ liệu
        $datas = [];
        $app->select("customers", [
            "id",
            "name",
            "phone"
        ], $where, function ($data) use (&$datas, $jatbi, $app) {
            $datas[] = [
                "value" => $data['id'],
                "text" => $data['phone'] . ' - ' . $data['name'],
            ];
        });

        // Trả về JSON
        echo json_encode($datas);
    });

    $app->router("/products", ['POST'], function ($vars) use ($app, $jatbi, $setting) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);

        // Lấy từ khóa tìm kiếm từ POST
        $searchValue = isset($_POST['keyword']) ? $app->xss($_POST['keyword']) : '';

        // Xây dựng điều kiện truy vấn
        $where = [
            "AND" => [
                "OR" => [
                    "name[~]" => $searchValue,
                    "code[~]" => $searchValue,
                ],
                "status" => 'A',
                "deleted" => 0,
            ],
            "LIMIT" => 10
        ];

        // Thực hiện truy vấn và định dạng dữ liệu
        $datas = [];
        $app->select("products", [
            "id",
            "name",
        ], $where, function ($data) use (&$datas, $jatbi, $app) {
            $datas[] = [
                "value" => $data['id'],
                "text" => $data['name'],
            ];
        });

        // Trả về JSON
        echo json_encode($datas);
    });

    $app->router('/purchase-update/{action}/{field}', 'POST', function ($vars) use ($app) {
        $action = $vars['action'] ?? 'add';
        $field = $vars['field'] ?? '';
        $value = $_POST['value'] ?? '';
        if (!empty($field)) {
            $_SESSION['purchase'][$action][$field] = $app->xss($value);
        }
        echo json_encode(['status' => 'success']);
    })->setPermissions(['purchase.add']);


    $app->router('/crafting-details-by-ingredient/{id}', 'GET', function ($vars) use ($app) {
        $app->header(['Content-Type' => 'application/json']);
        $ingredient_id = $vars['id'] ?? 0;

        $crafting_detail = $app->get("crafting_details", "crafting", ["ingredient" => $ingredient_id]);

        if ($crafting_detail) {
            $crafting_product = $app->get("crafting", [
                "name",
                "code",
                "price",
                "group",
                "categorys",
                "units",
                "default_code",
                "content"
            ], ["id" => $crafting_detail]);

            if ($crafting_product) {
                echo json_encode(['status' => 'success', 'data' => $crafting_product]);
            } else {
                echo json_encode(['status' => 'error', 'content' => 'Không tìm thấy sản phẩm chế tác liên quan']);
            }
        } else {
            echo json_encode(['status' => 'error', 'content' => 'Nguyên liệu chưa được dùng trong sản phẩm nào']);
        }
    });

    $app->router("/product-search/{action}", ['POST'], function ($vars) use ($app) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);

        $searchValue = isset($_POST['search']) ? $app->xss($_POST['search']) : '';
        $stores_json = $app->getCookie('stores') ?? json_encode([]);
        $stores = json_decode($stores_json, true);
        $store = $stores[0]['value'];

        $where = [
            "AND" => [
                "OR" => [
                    "products.name[~]" => $searchValue,
                    "products.code[~]" => $searchValue,
                ],
                "products.status" => 'A',
                "products.deleted" => 0,
                "products.stores" => $store
            ],
            "LIMIT" => 15
        ];

        $datas = [];
        $app->select("products", [
            "[>]branch" => ["branch" => "id"]
        ], [
            "products.id",
            "products.code",
            "products.name",
            "products.amount",
            "branch.name(branch_name)"
        ], $where, function ($data) use (&$datas, $vars) {
            $datas[] = [
                "text" => $data['code'] . ' - ' . $data['name'],
                "brands" => $data['branch_name'],
                "amount" => $data['amount'],
                "url" => "/invoices/invoices-update/sales/" . $vars['action'] . "/products/add/" . $data['id'],
                "url-2" => "/invoices/invoices-update/orders/" . $vars['action'] . "/products/add/" . $data['id'],
                "url-3" => "/accountants/invoices-update/update-acountant-invoice/" . $vars['action'] . "/products/add/" . $data['id'],
                "value" => $data['id'],
            ];
        });

        echo json_encode($datas);
    });

    $app->router("/customers-search/{action}", ['POST'], function ($vars) use ($app) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);

        $searchValue = isset($_POST['search']) ? $app->xss($_POST['search']) : '';
        $stores_json = $app->getCookie('stores') ?? json_encode([]);
        $stores = json_decode($stores_json, true);
        $store = $stores[0]['value'];


        $where = [
            "AND" => [
                "OR" => [
                    "name[~]" => $searchValue,
                    "code[~]" => $searchValue,
                    "phone[~]" => $searchValue,
                    "email[~]" => $searchValue,
                ],
                "status" => 'A',
                "deleted" => 0,
            ],
            "LIMIT" => 10 // Giới hạn số lượng kết quả để tối ưu
        ];

        $datas = [];
        $app->select("customers", [
            "id",
            "name",
            "phone"
        ], $where, function ($data) use (&$datas, $app, $vars) {
            $datas[] = [
                "value" => $data['id'],
                "text" => $data['phone'] . ' - ' . $data['name'],
                "url" => "/invoices/invoices-updatee/sales/" . $vars['action'] . "/customers/" . $data['id'],
                "url-2" => "/invoices/invoices-update-customers/" . $vars['action'] . "/" . $data['id'],
                "url-3" => "/invoices/invoices-updatee/returns/" . $vars['action'] . "/customers/" . $data['id'],
                "url-4" => "/invoices/invoices-updatee/orders/" . $vars['action'] . "/customers/" . $data['id'],
            ];
        });

        echo json_encode($datas);
    });

    $app->router("/product-insert-pos/{id}", ['POST'], function ($vars) use ($app) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $data = $app->get("products", "*", ["id" => $vars['id'], "deleted" => 0]);
        if (isset($data)) {
            if ($data['vat_type'] == 1) {
                $vat_price = $data['price'] - ($data['price'] / (1 + ($data['vat'] / 100)));
                $price = $data['price'];
                $price_vat = $data['price'] - $vat_price;
            }
            if ($data['vat_type'] == 2) {
                $vat_price = $data['price'] * ($data['vat'] / 100);
                $price = $data['price'] + $vat_price;
                $price_vat = $data['price'];
            }
            $_SESSION['sale']['add']['products'][$data['id']] = [
                "products" => $data['id'],
                "amount" => 1,
                "price" => $price,
                "price_vat" => $price_vat,
                "vendor" => $data['vendor'],
                "vat" => $data['vat'],
                "vat_type" => $data['vat_type'],
                "vat_price" => $vat_price,
                "price_old" => $price,
                "images" => $data['images'],
                "code" => $data['code'],
                "name" => $data['name'],
                "group" => $data['group'],
                "categorys" => $data['categorys'],
                "amount_status" => $data['amount_status'],
                "units" => $data['units'],
                "notes" => $data['notes'],
                "branch" => $data['branch'],
            ];
            echo json_encode(["status" => "success", "content" => 'ok']);
        } else {
            echo json_encode(["status" => "error", "content" => 'Loi']);
        }
    });
    $app->router('/ticket-check/{code}', 'POST', function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $code = $vars['code'] ?? '';

        if (empty($code)) {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Vui lòng nhập hoặc quét mã vé.')]);
            return;
        }

        $ticket = $app->get("ticket", "*", ["active" => $code, "deleted" => 0]);

        if (!$ticket) {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Mã vé không hợp lệ hoặc không tồn tại.')]);
            return;
        }

        if ($ticket['status'] === 'A') {
            $error_message = $jatbi->lang('Vé đã được sử dụng ngày') . ' ' . date('d/m/Y H:i:s', strtotime($ticket['date_check']));
            echo json_encode(['status' => 'error', 'content' => $error_message]);
            return;
        }

        $app->update("ticket", ["status" => "A", "date_check" => date('Y-m-d H:i:s')], ["id" => $ticket["id"]]);
        $_SESSION['ticket']['check']['last_id'] = $ticket['id'];

        echo json_encode([
            'status' => 'success',
            'content' => $jatbi->lang('Check-in thành công!'),
            'ticket_data' => $ticket
        ]);
    });

    $app->router("/ticket-search", ['POST'], function ($vars) use ($app) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);

        $searchValue = isset($_POST['search']) ? $app->xss($_POST['search']) : '';


        $where = [
            "AND" => [
                "OR" => [
                    "active[~]" => $searchValue,
                ],
                "deleted" => 0,
            ],
            "LIMIT" => 10

        ];

        $datas = [];
        $app->select("ticket", ["id", "active"], $where, function ($data) use (&$datas) {
            $datas[] = [
                'id' => $data['id'],
                'text' => $data['active'],
                "url" => "/api/ticket-check/" . $data["active"],
            ];
        });

        echo json_encode($datas);
    });

    $app->router('/customers/invoices/{active}', 'POST', function ($vars) use ($app, $jatbi, $setting) {
        $app->header(['Content-Type' => 'application/json; charset=utf-8']);

        $active_hash = $vars['active'] ?? '';
        $customer = $app->get("customers", ["id", "name"], ["active" => $active_hash]);
        $customer_id = $customer['id'] ?? 0;

        $draw = intval($_POST['draw'] ?? 0);
        $start = intval($_POST['start'] ?? 0);
        $length = intval($_POST['length'] ?? 10);
        $searchValue = $_POST['search']['value'] ?? '';
        $orderName = $_POST['columns'][intval($_POST['order'][0]['column'] ?? 0)]['invoices.id'] ?? 'invoices.id';
        $orderDir = $_POST['order'][0]['dir'] ?? 'DESC';

        $where = [
            "AND" => [
                "OR" => [
                    "invoices.code[~]" => $searchValue,
                    "invoices.id[~]" => $searchValue,
                ],
                "invoices.deleted" => 0,
                "invoices.customers" => $customer_id,
                "invoices.type" => [1, 2],
                "invoices.cancel" => [0, 1]
            ],
            "LIMIT" => [$start, $length],
            "ORDER" => [$orderName => strtoupper($orderDir)],
        ];

        $joins = [
            "[<]accounts" => ["user" => "id"],
            "[<]stores" => ["stores" => "id"],
        ];

        $count = $app->count("invoices", [
            "AND" => $where['AND'],
        ]);

        $columns = [
            "invoices.id",
            "invoices.code",
            "invoices.total",
            "invoices.minus",
            "invoices.discount",
            "invoices.payments",
            "invoices.prepay",
            "invoices.status",
            "invoices.date",
            "invoices.notes",
            "accounts.name(user_name)",
            "stores.name(store_name)",
        ];
        $datas = $app->select("invoices", $joins, $columns, $where);


        $invoice_ids = array_column($datas, 'id');
        $products_db = $invoice_ids ? $app->select("invoices_products", ["[<]products" => ["products" => "id"]], ["invoices_products.invoices", "products.name"], ["invoices_products.invoices" => $invoice_ids]) : [];
        $products_map = [];
        foreach ($products_db as $p) {
            $products_map[$p['invoices']][] = $p['name'];
        }

        $resultData = [];
        foreach ($datas as $data) {
            $remaining = ($data['payments'] ?? 0) - ($data['prepay'] ?? 0);
            $resultData[] = [
                "ma_hoa_don" => '<a class="text-nowrap pjax-load" href="/invoices/invoices-views/' . $data['id'] . '/">#' . ($setting['ballot_code']['invoices'] ?? '') . '-' . $data['id'] . '</a>',
                "khach_hang" => $customer['name'],
                "san_pham_mua" => implode('<br>', $products_map[$data['id']] ?? []),
                "so_luong_mua" => $app->sum("invoices_products", "amount", ["invoices" => $data['id']]),
                "tong_tien" => number_format($data['total'] ?? 0),
                "thanh_toan" => number_format($data['payments'] ?? 0),
                "con_lai" => '<span class="fw-bold text-danger">' . number_format($remaining) . '</span>',
                "trang_thai" => $data['status'] == 1 ? '<span class="fw-bold text-success">' . $jatbi->lang("Đã thanh toán") . '</span>' : '<span class="fw-bold text-danger">' . $jatbi->lang("Chưa thanh toán") . '</span>',
                "ngay" => date('d/m/Y H:i', strtotime($data['date'])),
                "tai_khoan" => $data['user_name'],
                "cua_hang" => $data['store_name'],
                "action" => $app->component("action", [
                    "button" => [
                        [
                            'type' => 'link',
                            'name' => $jatbi->lang("Xem"),
                            'permission' => ['customers'],
                            'action' => ['href' => '/invoices/invoices-views/' . $data['id'], 'data-pjax' => '']
                        ],
                    ]
                ]),
            ];
        }

        echo json_encode([
            "draw" => $draw,
            "recordsTotal" => $count,
            "recordsFiltered" => $count,
            "data" => $resultData
        ]);
    })->setPermissions(['customers']);






    // --- API TÌM KIẾM NGUYÊN LIỆU ---
    $app->router("/ingredient-search", ['POST'], function ($vars) use ($app) {
        $app->header(['Content-Type' => 'application/json']);
        $searchValue = isset($_POST['search']) ? $app->xss($_POST['search']) : '';
        $where = ["AND" => ["OR" => ["name_ingredient[~]" => $searchValue, "code[~]" => $searchValue], "status" => 'A', "deleted" => 0], "LIMIT" => 10];
        $datas = [];
        $app->select("ingredient", ["id", "code", "name_ingredient"], $where, function ($data) use (&$datas) {
            $datas[] = [
                'id' => $data['id'],
                'text' => $data['code'] . ' - ' . $data['name_ingredient'],
                "url" => "/api/ingredient-import-add/" . $data["id"],
            ];
        });
        echo json_encode($datas);
    });

    // --- API THÊM NGUYÊN LIỆU VÀO PHIẾU ---
    $app->router('/ingredient-import-add/{id}', ['POST'], function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $id = (int) ($vars['id'] ?? 0);
        if ($id === 0) {
            echo json_encode(['status' => 'error', 'content' => 'ID không hợp lệ.']);
            return;
        }
        if (!isset($_SESSION['ingredient_import']['ingredients'])) {
            $_SESSION['ingredient_import']['ingredients'] = [];
        }
        if (isset($_SESSION['ingredient_import']['ingredients'][$id])) {
            $_SESSION['ingredient_import']['ingredients'][$id]['amount']++;
            echo json_encode(['status' => 'success', 'content' => 'Đã cập nhật số lượng.']);
            return;
        }
        $data = $app->get("ingredient", "*", ["id" => $id, "status" => 'A', "deleted" => 0]);
        if (!$data) {
            echo json_encode(['status' => 'error', 'content' => 'Nguyên liệu không tồn tại.']);
            return;
        }
        $unit_name = $app->get("units", "name", ["id" => $data['units']]) ?? 'N/A';
        $_SESSION['ingredient_import']['ingredients'][$data['id']] = [
            "ingredient_id" => $data['id'],
            "code" => $data['code'],
            "name" => $data['name_ingredient'],
            "selling_price" => $data['price'] ?? 0,
            "stock_quantity" => $data['amount'] ?? 0,
            "amount" => 1,
            "price" => $data['price_purchase'] ?? 0,
            "unit_name" => $unit_name,
            "notes" => $data['notes'] ?? '',
        ];
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Thêm nguyên liệu thành công')]);
    });

    // --- API CẬP NHẬT SỐ LƯỢNG ---
    $app->router('/ingredient-import/update-amount/{id}', ['POST'], function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $id = $vars['id'] ?? '';
        $value = $_POST['value'] ?? '';
        if (!isset($_SESSION['ingredient_import']['ingredients'][$id])) {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Nguyên liệu không tồn tại trong phiếu.')]);
            return;
        }
        $value = (int) str_replace([','], '', $app->xss($value));
        if ($value < 1) {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Số lượng phải lớn hơn 0.')]);
        } else {
            $_SESSION['ingredient_import']['ingredients'][$id]['amount'] = $value;
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
        }
    });

    // --- API CẬP NHẬT GHI CHÚ ---
    $app->router('/ingredient-import/update-notes/{id}', ['POST'], function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $id = $vars['id'] ?? '';
        $value = $_POST['value'] ?? '';
        if (!isset($_SESSION['ingredient_import']['ingredients'][$id])) {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Nguyên liệu không tồn tại trong phiếu.')]);
            return;
        }
        $_SESSION['ingredient_import']['ingredients'][$id]['notes'] = $app->xss($value);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
    });

    // --- API CẬP NHẬT NỘI DUNG PHIẾU NHẬP ---
    $app->router('/ingredient-import/set-content', ['POST'], function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $value = $_POST['value'] ?? '';
        if (!isset($_SESSION['ingredient_import'])) {
            $_SESSION['ingredient_import'] = ['ingredients' => [], 'content' => ''];
        }
        $_SESSION['ingredient_import']['content'] = $app->xss($value);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
    });

    $app->router('/ingredient-import/delete/{id}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {


        if ($app->method() === 'GET') {

            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);


            $id = $vars['id'] ?? 0;
            if ($id === 0) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('ID nguyên liệu không hợp lệ.')]);
                return;
            }


            if (!isset($_SESSION['ingredient_import']['ingredients']) || !isset($_SESSION['ingredient_import']['ingredients'][$id])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Nguyên liệu không tồn tại trong phiếu.')]);
                return;
            }


            $ingredient = $_SESSION['ingredient_import']['ingredients'][$id];
            $name = $ingredient['code'];


            unset($_SESSION['ingredient_import']['ingredients'][$id]);


            $jatbi->logs('ingredient_import', 'ingredient-import-delete', [$ingredient]);
            $jatbi->trash('/warehouses/ingredient-import-restore', "Xóa nguyên liệu trong phiếu: " . $name, ["app" => 'ingredient_import', "data" => [$id]]);


            echo json_encode([
                'status' => 'success',
                'content' => $jatbi->lang('Cập nhật thành công'),
            ]);
        }
    })->setPermissions(['ingredient']);



    // --- API HOÀN TẤT VÀ LƯU PHIẾU NHẬP ---

    $app->router('/ingredient-import/completed', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'GET') {

            echo $app->render($setting['template'] . '/common/confirm.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {

            $app->header(['Content-Type' => 'application/json']);
            $action = 'import';

            $current_time = date("Y-m-d H:i:s");
            // 3. Kiểm tra session có tồn tại
            if (!isset($_SESSION['ingredient_import']['ingredients']) || empty($_SESSION['ingredient_import']['ingredients'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Không có nguyên liệu trong phiếu.')]);
                return;
            }

            // 4. Lấy dữ liệu từ session
            $data = $_SESSION['ingredient_import'];
            $ingredients = $data['ingredients'] ?? [];
            $content = $data['content'] ?? '';
            $date = $data['date'] ?? $current_time;

            // 5. Kiểm tra dữ liệu hợp lệ
            if (empty($content)) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Nội dung không được để trống.')]);
                return;
            }

            $error_warehouses = false;
            $total_products = 0;
            foreach ($ingredients as $ingredient) {
                if (!isset($ingredient['amount']) || $ingredient['amount'] <= 0 || $ingredient['amount'] < 0) {
                    $error_warehouses = true;
                    break;
                }
                $total_products += $ingredient['amount'] * ($ingredient['price'] ?? 0);
            }

            if ($error_warehouses) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Vui lòng nhập số lượng hợp lệ.')]);
                return;
            }


            $code = 'PN';

            // 7. Chuẩn bị dữ liệu để insert vào bảng warehouses
            $insert = [
                "code" => $code,
                "type" => $action,
                "data" => 'ingredient',
                "content" => $app->xss($content),
                "user" => $app->getSession("accounts")['id'] ?? 0,
                "date" => $date,
                "active" => $jatbi->active(30),
                "date_poster" => $current_time, // Sử dụng thời gian hiện tại
                "stores" => $data['stores']['id'] ?? 0,
            ];

            // 8. Insert vào bảng warehouses
            $app->insert("warehouses", $insert);
            $orderId = $app->id();

            // 9. Lưu chi tiết nguyên liệu vào warehouses_details và warehouses_logs
            $pro_logs = [];
            foreach ($ingredients as $ingredient) {
                if ($ingredient['ingredient_id'] > 0) {
                    // Cập nhật số lượng trong bảng ingredient
                    $current_product = $app->get("ingredient", ["id", "amount"], ["id" => $ingredient['ingredient_id']]);
                    $app->update("ingredient", [
                        "amount" => $current_product['amount'] + $ingredient['amount']
                    ], ["id" => $current_product['id']]);

                    // Chuẩn bị dữ liệu cho warehouses_details
                    $pro = [
                        "warehouses" => $orderId,
                        "data" => $insert['data'],
                        "type" => $insert['type'],
                        "vendor" => $ingredient['vendor'] ?? 0,
                        "ingredient" => $ingredient['ingredient_id'],
                        "amount_buy" => $ingredient['amount_buy'] ?? 0,
                        "amount" => str_replace([','], '', $ingredient['amount']),
                        "amount_total" => str_replace([','], '', $ingredient['amount']),
                        "price" => $ingredient['price'] ?? 0,
                        "cost" => $ingredient['cost'] ?? 0,
                        "notes" => $ingredient['notes'],
                        "date" => $current_time,
                        "user" => $app->getSession("accounts")['id'] ?? 0,
                        "stores" => $insert['stores'],
                    ];

                    // Insert vào warehouses_details
                    $app->insert("warehouses_details", $pro);
                    $detailId = $app->id();

                    // Chuẩn bị dữ liệu cho warehouses_logs
                    $warehouses_logs = [
                        "type" => $insert['type'],
                        "data" => $insert['data'],
                        "warehouses" => $orderId,
                        "details" => $detailId,
                        "ingredient" => $ingredient['ingredient_id'],
                        "price" => $ingredient['price'] ?? 0,
                        "cost" => $ingredient['cost'] ?? 0,
                        "amount" => str_replace([','], '', $ingredient['amount']),
                        "total" => $ingredient['amount'] * ($ingredient['price'] ?? 0),
                        "notes" => $ingredient['notes'],
                        "date" => $current_time,
                        "user" => $app->getSession("accounts")['id'] ?? 0,
                        "stores" => $insert['stores'],
                    ];


                    $app->insert("warehouses_logs", $warehouses_logs);
                    $pro_logs[] = $pro;
                }
            }

            // 10. Ghi log và xóa session
            $jatbi->logs('warehouses', $action, [$insert, $pro_logs, $data]);
            unset($_SESSION['ingredient_import']);

            // 11. Trả về kết quả thành công
            echo json_encode([
                'status' => 'success',
                'content' => $jatbi->lang('Cập nhật thành công'),

            ]);
        }
    })->setPermissions(['ingredient']);


    $app->router('/ingredient-import/cancel', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {

        if ($app->method() === 'GET') {

            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {

            $app->header(['Content-Type' => 'application/json']);
            $action = 'import';


            if (isset($_SESSION['ingredient_import'])) {
                unset($_SESSION['ingredient_import']);
            }


            $jatbi->logs('ingredient_import', 'cancel', ['action' => $action, 'data' => $_SESSION['ingredient_import'] ?? []]);


            echo json_encode([
                'status' => 'success',
                'content' => $jatbi->lang('Cập nhật thành công'),
                'redirect' => '/warehouses/ingredient-import'
            ]);
        }
    })->setPermissions(['ingredient']);

    // --- API CẬP NHẬT GHI CHÚ CHO CRAFTING ---
    $app->router('/ingredient-crafting/update-notes/{id}', ['POST'], function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $id = $vars['id'] ?? '';
        $value = $_POST['value'] ?? '';
        if (!isset($_SESSION['ingredient_import_crafting']['ingredients'][$id])) {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Nguyên liệu không tồn tại trong phiếu.')]);
            return;
        }
        $_SESSION['ingredient_import_crafting']['ingredients'][$id]['notes'] = $app->xss($value);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
    });

    // --- API CẬP NHẬT NỘI DUNG PHIẾU NHẬP CHO CRAFTING ---
    $app->router('/ingredient-crafting/set-content', ['POST'], function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $value = $_POST['value'] ?? '';
        if (!isset($_SESSION['ingredient_import_crafting'])) {
            $_SESSION['ingredient_import_crafting'] = ['ingredients' => [], 'content' => ''];
        }
        $_SESSION['ingredient_import_crafting']['content'] = $app->xss($value);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
    });
    // --- API HỦY PHIẾU CHO CRAFTING ---
    $app->router('/ingredient-crafting/cancel', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {

        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            if (isset($_SESSION['ingredient_import_crafting'])) {
                unset($_SESSION['ingredient_import_crafting']);
            }
            echo json_encode([
                'status' => 'success',
                'content' => $jatbi->lang('Đã hủy phiếu'),
                'redirect' => '/ingredient-import-crafting'
            ]);
        }
    });

    // --- API HOÀN TẤT VÀ LƯU PHIẾU CHO CRAFTING ---
    $app->router('/ingredient-crafting/completed', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {

        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/confirm.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            if (!isset($_SESSION['ingredient_import_crafting']['ingredients']) || empty($_SESSION['ingredient_import_crafting']['ingredients'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Không có nguyên liệu trong phiếu.')]);
                return;
            }

            $data = $_SESSION['ingredient_import_crafting'];

            // Kiểm tra phiếu nhập kho trùng lặp
            if (!empty($data['crafting_id'])) {
                $existing_import = $app->get("warehouses", "id", ["id" => $data['crafting_id'], "export_status" => 2]);
                if ($existing_import) {
                    echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Phiếu chế tác này đã được nhập kho trước đó.')]);
                    return;
                }
            }

            $insert = [
                "code" => 'PN',
                "type" => 'import',
                "data" => 'ingredient',
                "content" => $app->xss($data['content']),
                "user" => $app->getSession("accounts")['id'] ?? 0,
                "date" => $data['date'] ?? date("Y-m-d H:i:s"),
                "date_poster" => date("Y-m-d H:i:s"),
                "crafting" => $data['crafting_id'] ?? null,
            ];

            $app->insert("warehouses", $insert);
            $orderId = $app->id();

            $pro_logs = [];
            foreach ($data['ingredients'] as $ingredient) {
                if (isset($ingredient['ingredient_id']) && $ingredient['ingredient_id'] > 0) {


                    $current_product = $app->get("ingredient", ["id", "amount"], ["id" => $ingredient['ingredient_id']]);
                    if ($current_product) {
                        $app->update("ingredient", [
                            "amount[+]" => $ingredient['amount']
                        ], ["id" => $current_product['id']]);
                    }


                    $pro = [
                        "warehouses" => $orderId,
                        "data" => $insert['data'],
                        "type" => $insert['type'],
                        "vendor" => $ingredient['vendor'] ?? 0,
                        "ingredient" => $ingredient['ingredient_id'],
                        "amount_buy" => $ingredient['amount_buy'] ?? 0,
                        "amount" => str_replace([','], '', $ingredient['amount']),
                        "amount_total" => str_replace([','], '', $ingredient['amount']), // Khi nhập thì amount_total = amount
                        "price" => $ingredient['price'] ?? 0,
                        "cost" => $ingredient['cost'] ?? 0,
                        "notes" => $ingredient['notes'] ?? '',
                        "date" => date("Y-m-d H:i:s"),
                        "user" => $app->getSession("accounts")['id'] ?? 0,
                    ];
                    $app->insert("warehouses_details", $pro);
                    $detailId = $app->id();


                    $warehouses_logs = [
                        "type" => $insert['type'],
                        "data" => $insert['data'],
                        "warehouses" => $orderId,
                        "details" => $detailId,
                        "ingredient" => $ingredient['ingredient_id'],
                        "price" => $ingredient['price'] ?? 0,
                        "cost" => $ingredient['cost'] ?? 0,
                        "amount" => str_replace([','], '', $ingredient['amount']),
                        "total" => ($ingredient['amount'] ?? 0) * ($ingredient['price'] ?? 0),
                        "notes" => $ingredient['notes'] ?? '',
                        "date" => date('Y-m-d H:i:s'),
                        "user" => $app->getSession("accounts")['id'] ?? 0,
                    ];
                    $app->insert("warehouses_logs", $warehouses_logs);

                    $pro_logs[] = $pro;
                }
            }
            if (!empty($data['crafting_id'])) {
                $app->update("warehouses", ["export_status" => 2, "export_date" => date("Y-m-d H:i:s")], ["id" => $data['crafting_id']]);
            }

            $jatbi->logs('warehouses', 'import', [$insert, $pro_logs, $data]);

            // Xóa session sau khi lưu thành công
            unset($_SESSION['ingredient_import_crafting']);

            //Thêm redirect vào response
            echo json_encode([
                'status' => 'success',
                'content' => $jatbi->lang('Lưu phiếu nhập thành công'),

            ]);
        }
    });

    $app->router("/product-search-error", ['POST'], function ($vars) use ($app) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);

        $searchValue = isset($_POST['search']) ? $app->xss($_POST['search']) : '';
        $stores_json = $app->getCookie('stores') ?? json_encode([]);
        $stores = json_decode($stores_json, true);
        $store = $stores[0]['value'];

        $where = [
            "AND" => [
                "OR" => [
                    "products.name[~]" => $searchValue,
                    "products.code[~]" => $searchValue,
                ],
                "products.status" => 'A',
                "products.deleted" => 0,
                "products.stores" => $store
            ],
            "LIMIT" => 15
        ];

        $datas = [];
        $app->select("products", [
            "[>]branch" => ["branch" => "id"]
        ], [
            "products.id",
            "products.code",
            "products.name",
            "products.amount",
            "branch.name(branch_name)"
        ], $where, function ($data) use (&$datas, $vars) {
            $datas[] = [
                "text" => $data['code'] . ' - ' . $data['name'],
                "brands" => $data['branch_name'],
                "amount" => $data['amount'],
                "url" => "/warehouses/warehouses-update-error/error/products/add/" . $data['id'],
                "value" => $data['id'],

            ];
        });

        echo json_encode($datas);
    });

    $app->router("/product-tim", ['POST'], function ($vars) use ($app) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);

        $searchValue = isset($_POST['search']) ? $app->xss($_POST['search']) : '';
        $store_id = (int) ($_SESSION['warehouses']['import']['stores'] ?? 1);
        $branch_id = (int) ($_SESSION['warehouses']['import']['branch'] ?? 1);

        // 2. Xây dựng câu điều kiện WHERE cơ bản
        $where = [
            "AND" => [
                "OR" => [
                    "products.name[~]" => $searchValue,
                    "products.code[~]" => $searchValue,
                ],
                "products.status" => 'A',
                "products.deleted" => 0,
                "products.stores" => $store_id,
                "products.branch" => $branch_id
            ],
            "LIMIT" => 15
        ];


        $datas = [];
        $app->select("products", [
            "[>]branch" => ["branch" => "id"],
            "[>]stores" => ["stores" => "id"]
        ], [
            "products.id",
            "products.code",
            "products.name",
            "products.amount",
            "stores.name(stores_name)",
            "branch.name(branch_name)"
        ], $where, function ($data) use (&$datas) {
            $datas[] = [
                "text" => $data['code'] . ' - ' . $data['name'],
                "brands" => $data['branch_name'],
                "stores" => $data['stores_name'],
                "amount" => $data['amount'],
                "url" => "/warehouses/warehouses-update/import/products/add/" . $data['id'],
                "value" => $data['id'],
            ];
        });

        echo json_encode($datas);
    });





    // // --- API: CẬP NHẬT CỬA HÀNG ---
    // $app->router('/ingredient-move-product/move/stores', ['POST'], function ($vars) use ($app) {
    //     $app->header(['Content-Type' => 'application/json']);
    //     $_SESSION['ingredient_move']['store_id'] = $_POST['value'] ?? null;
    //     $_SESSION['ingredient_move']['branch_id'] = null;
    //     $_SESSION['ingredient_move']['ingredients'] = [];
    //     echo json_encode(['status' => 'success']);
    // });

    // // --- API: CẬP NHẬT QUẦY HÀNG ---
    // $app->router('/ingredient-move-product/move/branch', ['POST'], function ($vars) use ($app) {
    //     $app->header(['Content-Type' => 'application/json']);
    //     $_SESSION['ingredient_move']['branch_id'] = $_POST['value'] ?? null;
    //     echo json_encode(['status' => 'success']);
    // });

    // // --- API: TÌM KIẾM NGUYÊN LIỆU ---
    // $app->router('/ingredient-move-product/move/ingredient/search', ['POST'], function ($vars) use ($app) {
    //     $app->header(['Content-Type' => 'application/json']);
    //     $searchValue = isset($_POST['search']) ? $app->xss($_POST['search']) : '';
    //     $where = ["AND" => ["OR" => ["name_ingredient[~]" => $searchValue, "code[~]" => $searchValue], "status" => 'A', "deleted" => 0, "amount[>]" => 0], "LIMIT" => 10];
    //     $datas = [];
    //     $app->select("ingredient", ["id", "code", "name_ingredient"], $where, function ($data) use (&$datas) {
    //         $datas[] = [
    //             'id' => $data['id'], 
    //             'text' => $data['code'] . ' - ' . $data['name_ingredient'], 
    //             "url" => "/api/ingredient-move-product/move/ingredient/add/" . $data["id"]
    //         ];
    //     });
    //     echo json_encode($datas);
    // });

    // // --- API: THÊM NGUYÊN LIỆU ---
    // $app->router('/ingredient-move-product/move/ingredient/add/{id}', ['POST'], function ($vars) use ($app, $jatbi) {
    //     $app->header(['Content-Type' => 'application/json']);
    //     $id = (int)($vars['id'] ?? 0);
    //     $data = $app->get("ingredient", "*", ["id" => $id, "status" => 'A', "deleted" => 0]);

    //     if (!$data || $data['amount'] <= 0) {
    //         echo json_encode(['status' => 'error', 'content' => 'Nguyên liệu không tồn tại hoặc đã hết hàng.']);
    //         return;
    //     }
    //     if (!isset($_SESSION['ingredient_move']['ingredients'])) $_SESSION['ingredient_move']['ingredients'] = [];
    //     if (isset($_SESSION['ingredient_move']['ingredients'][$id])) {
    //         $new_amount = $_SESSION['ingredient_move']['ingredients'][$id]['amount'] + 1;
    //         if ($new_amount > $data['amount']) {
    //              echo json_encode(['status' => 'error', 'content' => 'Số lượng vượt quá tồn kho.']);
    //              return;
    //         }
    //         $_SESSION['ingredient_move']['ingredients'][$id]['amount'] = $new_amount;
    //     } else {
    //         $unit_name = $app->get("units", "name", ["id" => $data['units']]) ?? 'N/A';
    //         $_SESSION['ingredient_move']['ingredients'][$id] = ["ingredient_id"  => $data['id'],"code" => $data['code'],"name" => $data['name_ingredient'],"stock_quantity" => $data['amount'],"amount" => 1,"price" => $data['price'] ?? 0,"unit_name" => $unit_name,"notes" => ''];
    //     }
    //     echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Thêm nguyên liệu thành công')]);
    // });

    // // --- API: CẬP NHẬT SỐ LƯỢNG ---
    // $app->router('/ingredient-move-product/move/ingredient/amount/{id}', ['POST'], function ($vars) use ($app) {
    //     $app->header(['Content-Type' => 'application/json']);
    //     $id = $vars['id'] ?? '';
    //     $value = (int)($_POST['value'] ?? 0);

    //     if (!isset($_SESSION['ingredient_move']['ingredients'][$id])) {
    //         echo json_encode(['status' => 'error', 'content' => 'Nguyên liệu không tồn tại.']);
    //         return;
    //     }

    //     $item = $_SESSION['ingredient_move']['ingredients'][$id];
    //     $stock_quantity = (int)($item['stock_quantity'] ?? 0);

    //     if ($value > $stock_quantity) {
    //         $_SESSION['ingredient_move']['ingredients'][$id]['amount'] = $stock_quantity; // Tự sửa về số lượng max
    //         echo json_encode(['status' => 'error', 'content' => 'Số lượng vượt quá tồn kho. Đã tự động điều chỉnh.']);
    //         return;
    //     }

    //     if ($value < 0) {
    //         $_SESSION['ingredient_move']['ingredients'][$id]['amount'] = 0; // Tự sửa về 0
    //         echo json_encode(['status' => 'error', 'content' => 'Số lượng không hợp lệ. Đã tự động điều chỉnh.']);
    //         return;
    //     }

    //     $_SESSION['ingredient_move']['ingredients'][$id]['amount'] = $value;
    //     echo json_encode(['status' => 'success']);
    // });

    // // --- API: CẬP NHẬT GHI CHÚ ---
    // $app->router('/ingredient-move-product/move/ingredient/notes/{key}', ['POST'], function ($vars) use ($app) {
    //     $app->header(['Content-Type' => 'application/json']);
    //     $key = $vars['key'] ?? '';
    //     if (isset($_SESSION['ingredient_move']['ingredients'][$key])) {
    //         $_SESSION['ingredient_move']['ingredients'][$key]['notes'] = $app->xss($_POST['value'] ?? '');
    //     }
    //     echo json_encode(['status' => 'success']);
    // });

    // // --- API: CẬP NHẬT NỘI DUNG ---
    // $app->router('/ingredient-move-product/move/content', ['POST'], function ($vars) use ($app) {
    //     $app->header(['Content-Type' => 'application/json']);
    //     $_SESSION['ingredient_move']['content'] = $app->xss($_POST['value'] ?? '');
    //     echo json_encode(['status' => 'success']);
    // });

    // // --- API: XÓA NGUYÊN LIỆU ---
    // $app->router('/ingredient-move-product/move/ingredient/deleted/{key}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
    //     if ($app->method() === 'GET') {
    //         echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
    //     } elseif ($app->method() === 'POST') {
    //         $app->header(['Content-Type' => 'application/json']);
    //         $key = $vars['key'] ?? '';
    //         if (isset($_SESSION['ingredient_move']['ingredients'][$key])) {
    //             unset($_SESSION['ingredient_move']['ingredients'][$key]);
    //         }
    //         echo json_encode(['status' => 'success', 'content' => 'Đã xóa nguyên liệu.']);
    //     }
    // });

    // // --- API: HỦY PHIẾU ---
    // $app->router('/ingredient-move-product/move/cancel', ['GET','POST'], function ($vars) use ($app, $jatbi, $setting) {
    //     if ($app->method() === 'GET') {
    //         echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
    //     } elseif ($app->method() === 'POST') {
    //         unset($_SESSION['ingredient_move']);
    //         echo json_encode(['status' => 'success', 'content' => 'Đã hủy phiếu', 'redirect' => '/ingredient-move']);
    //     }
    // });

    // // --- API: HOÀN TẤT ---
    // $app->router('/ingredient-move-product/move/completed', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
    //     if ($app->method() === 'GET') {
    //         echo $app->render($setting['template'] . '/common/confirm.html', $vars, $jatbi->ajax());
    //     } elseif ($app->method() === 'POST') {
    //         $app->header(['Content-Type' => 'application/json']);
    //         $data = $_SESSION['ingredient_move'];
    //         if (empty($data['store_id']) || empty($data['branch_id'])) {
    //             echo json_encode(['status' => 'error', 'content' => 'Vui lòng chọn đầy đủ cửa hàng và quầy hàng nhận.']);
    //             return;
    //         }
    //         if (empty($data['ingredients'])) {
    //             echo json_encode(['status' => 'error', 'content' => 'Chưa có nguyên liệu nào trong phiếu.']);
    //             return;
    //         }

    //         $insert = ["code" => 'PCK',"type" => 'move',"data" => 'ingredient',"content" => $app->xss($data['content']),"user" => $app->getSession("accounts")['id'] ?? 0,"date" => $data['date'],"date_poster" => date("Y-m-d H:i:s"),"stores" => $data['store_id'],"branch" => $data['branch_id']];
    //         $app->insert("warehouses", $insert);
    //         $orderId = $app->id();

    //         foreach ($data['ingredients'] as $item) {
    //             if ($item['amount'] > 0) {
    //                 $app->update("ingredient", ["amount[-]" => $item['amount']], ["id" => $item['ingredient_id']]);
    //                 $app->insert("warehouses_details", ["warehouses" => $orderId,"data" => 'ingredient',"type" => 'move',"ingredient" => $item['ingredient_id'],"amount_old" => $item['stock_quantity'],"amount" => $item['amount'],"price" => $item['price'],"notes" => $item['notes'],"date" => date("Y-m-d H:i:s"),"user" => $app->getSession("accounts")['id'] ?? 0]);
    //             }
    //         }

    //         unset($_SESSION['ingredient_move']);

    //         echo json_encode(['status' => 'success', 'content' => 'Chuyển kho thành công!', 'redirect' => '/warehouses-history/ingredient']);
    //     }
    // });






    $app->router("/ingredient-move-search", ['POST'], function ($vars) use ($app) {
        $app->header(['Content-Type' => 'application/json']);
        $searchValue = isset($_POST['search']) ? $app->xss($_POST['search']) : '';
        $where = ["AND" => ["OR" => ["name_ingredient[~]" => $searchValue, "code[~]" => $searchValue], "status" => 'A', "deleted" => 0, "amount[>]" => 0], "LIMIT" => 10];
        $datas = [];
        $app->select("ingredient", ["id", "code", "name_ingredient"], $where, function ($data) use (&$datas) {
            $datas[] = [
                'id' => $data['id'],
                'text' => $data['code'] . ' - ' . $data['name_ingredient'],
                "url" => "/api/ingredient-move-product/move/ingredient/add/" . $data["id"],
            ];
        });
        echo json_encode($datas);
    });

    // --- API THÊM NGUYÊN LIỆU VÀO PHIẾU ---
    $app->router('/ingredient-move-product/move/ingredient/add/{id}', ['POST'], function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $id = (int) ($vars['id'] ?? 0);
        if ($id === 0) {
            echo json_encode(['status' => 'error', 'content' => 'ID không hợp lệ.']);
            return;
        }

        if (!isset($_SESSION['ingredient_move']['ingredients'])) {
            $_SESSION['ingredient_move']['ingredients'] = [];
        }

        $ingredient_data = $app->get("ingredient", "*", ["id" => $id, "status" => 'A', "deleted" => 0]);
        if (!$ingredient_data || $ingredient_data['amount'] <= 0) {
            echo json_encode(['status' => 'error', 'content' => 'Nguyên liệu không tồn tại hoặc đã hết hàng.']);
            return;
        }
        $stock_quantity = $ingredient_data['amount'];

        if (isset($_SESSION['ingredient_move']['ingredients'][$id])) {
            if ($_SESSION['ingredient_move']['ingredients'][$id]['amount'] + 1 > $stock_quantity) {
                echo json_encode(['status' => 'error', 'content' => 'Số lượng vượt quá tồn kho. Tồn kho: ' . $stock_quantity]);
                return;
            }
            $_SESSION['ingredient_move']['ingredients'][$id]['amount']++;
            echo json_encode(['status' => 'success', 'content' => 'Đã cập nhật số lượng.']);
            return;
        }

        $unit_name = $app->get("units", "name", ["id" => $ingredient_data['units']]) ?? 'N/A';
        $_SESSION['ingredient_move']['ingredients'][$id] = [
            "ingredient_id" => $ingredient_data['id'],
            "code" => $ingredient_data['code'],
            "name" => $ingredient_data['name_ingredient'],
            "type" => $ingredient_data['type'],
            "selling_price" => $ingredient_data['price'] ?? 0,
            "stock_quantity" => $stock_quantity,
            "amount" => 1,
            "unit_name" => $unit_name,
            "notes" => '',
        ];
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Thêm nguyên liệu thành công')]);
    });

    // --- API CẬP NHẬT SỐ LƯỢNG ---
    $app->router('/ingredient-move-product/move/ingredient/amount/{id}', ['POST'], function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $id = $vars['id'] ?? '';
        $value = $_POST['value'] ?? '';

        if (!isset($_SESSION['ingredient_move']['ingredients'][$id])) {
            echo json_encode(['status' => 'error', 'content' => 'Nguyên liệu không tồn tại trong phiếu.']);
            return;
        }

        $value = (int) str_replace([','], '', $app->xss($value));
        $stock_quantity = $_SESSION['ingredient_move']['ingredients'][$id]['stock_quantity'];

        if ($value < 1) {
            echo json_encode(['status' => 'error', 'content' => 'Số lượng phải lớn hơn 0.']);
        } elseif ($value > $stock_quantity) {
            $_SESSION['ingredient_move']['ingredients'][$id]['amount'] = $stock_quantity;
            echo json_encode(['status' => 'error', 'content' => 'Số lượng vượt tồn kho.']);
        } else {
            $_SESSION['ingredient_move']['ingredients'][$id]['amount'] = $value;
            echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
        }
    });

    // --- API CẬP NHẬT GHI CHÚ ---
    $app->router('/ingredient-move-product/move/ingredient/notes/{id}', ['POST'], function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $id = $vars['id'] ?? '';
        $value = $_POST['value'] ?? '';
        if (!isset($_SESSION['ingredient_move']['ingredients'][$id])) {
            echo json_encode(['status' => 'error', 'content' => 'Nguyên liệu không tồn tại trong phiếu.']);
            return;
        }
        $_SESSION['ingredient_move']['ingredients'][$id]['notes'] = $app->xss($value);
        echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
    });

    // --- API CẬP NHẬT THÔNG TIN CHUNG CỦA PHIẾU ---
    $app->router('/ingredient-move-product/move/content', ['POST'], function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $field = $_POST['field'] ?? '';
        $value = $_POST['value'] ?? '';
        if (in_array($field, ['content', 'store_id', 'branch_id'])) {
            if (!isset($_SESSION['ingredient_move']))
                $_SESSION['ingredient_move'] = [];
            $_SESSION['ingredient_move'][$field] = $app->xss($value);
            echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
        } else {
            echo json_encode(['status' => 'error', 'content' => 'Trường dữ liệu không hợp lệ']);
        }
    });

    // --- API XÓA NGUYÊN LIỆU KHỎI PHIẾU ---
    $app->router('/ingredient-move-product/move/ingredient/deleted/{id}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $id = $vars['id'] ?? 0;
            if (!isset($_SESSION['ingredient_move']['ingredients'][$id])) {
                echo json_encode(['status' => 'error', 'content' => 'Nguyên liệu không tồn tại trong phiếu.']);
                return;
            }
            unset($_SESSION['ingredient_move']['ingredients'][$id]);
            echo json_encode(['status' => 'success', 'content' => 'Xóa thành công']);
        }
    });

    // --- API HỦY PHIẾU ---
    $app->router('/ingredient-move-product/move/cancel', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            if (isset($_SESSION['ingredient_move'])) {
                unset($_SESSION['ingredient_move']);
            }
            echo json_encode([
                'status' => 'success',
                'content' => 'Đã hủy phiếu thành công',

            ]);
        }
    });



    $app->router('/ingredient-move-product/move/completed', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/confirm-ingredient-move.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $current_time = date("Y-m-d H:i:s");
            $account = $app->getSession("accounts");
            $data = $_SESSION['ingredient_move'] ?? [];

            // Chuẩn hoá store_id và branch_id (tránh trường hợp bị mảng)
            if (is_array($data['store_id'] ?? null)) {
                $data['store_id'] = $data['store_id']['id'] ?? null;
            }
            if (is_array($data['branch_id'] ?? null)) {
                $data['branch_id'] = $data['branch_id']['id'] ?? null;
            }

            // 1. Kiểm tra dữ liệu hợp lệ
            if (empty($data['ingredients'])) {
                echo json_encode(['status' => 'error', 'content' => 'Không có nguyên liệu trong phiếu.']);
                return;
            }
            if (empty($data['content'])) {
                echo json_encode(['status' => 'error', 'content' => 'Nội dung không được để trống.']);
                return;
            }
            if (empty($data['store_id']) || empty($data['branch_id'])) {
                echo json_encode(['status' => 'error', 'content' => 'Vui lòng chọn Cửa hàng và Quầy hàng.']);
                return;
            }

            // Kiểm tra tính hợp lệ của store_id và branch_id
            $store_info = $app->get("stores", ["id", "code"], ["id" => $data['store_id'], "deleted" => 0, "status" => 'A']);
            $branch_info = $app->get("branch", ["id", "code"], ["id" => $data['branch_id'], "deleted" => 0, "status" => 'A', "stores" => $data['store_id']]);
            if (!$store_info) {
                echo json_encode(['status' => 'error', 'content' => 'Cửa hàng không hợp lệ.']);
                return;
            }
            if (!$branch_info) {
                echo json_encode(['status' => 'error', 'content' => 'Quầy hàng không hợp lệ hoặc không thuộc cửa hàng đã chọn.']);
                return;
            }

            // 2. Tạo phiếu xuất kho nguyên liệu (WAREHOUSES - EXPORT)
            $export_warehouse_data = [
                "code" => 'PX',
                "type" => 'move_products',
                "data" => 'ingredient',
                "content" => $app->xss($data['content']),
                "user" => $account['id'] ?? 0,
                "date" => $current_time,
                "date_poster" => $current_time,
                "stores" => $data['store_id'],
                "branch" => $data['branch_id'],
                "active" => $jatbi->active(30),
            ];
            $app->insert("warehouses", $export_warehouse_data);
            $exportWarehouseId = $app->id();

            // 3. Tạo phiếu nhập kho thành phẩm (WAREHOUSES - IMPORT)
            $import_warehouse_data = [
                "code" => 'PN',
                "type" => 'import',
                "data" => 'products',
                "content" => $app->xss($data['content']),
                "user" => $account['id'] ?? 0,
                "date" => $current_time,
                "date_poster" => $current_time,
                "stores" => $data['store_id'],
                "branch" => $data['branch_id'],
                "active" => $jatbi->active(30),
            ];
            $app->insert("warehouses", $import_warehouse_data);
            $importWarehouseId = $app->id();

            // 4. Xử lý từng dòng nguyên liệu trong phiếu
            foreach ($data['ingredients'] as $item) {
                $ingredient_id = $item['ingredient_id'];
                $move_amount = (int) $item['amount'];

                // Kiểm tra nguyên liệu
                $ingredient_info = $app->get("ingredient", "*", ["id" => $ingredient_id, "status" => 'A', "deleted" => 0]);
                if (!$ingredient_info || $ingredient_info['amount'] < $move_amount) {
                    echo json_encode(['status' => 'error', 'content' => 'Nguyên liệu ' . htmlspecialchars($item['name']) . ' không hợp lệ hoặc không đủ tồn kho.']);
                    return;
                }

                // === BƯỚC A: XỬ LÝ XUẤT KHO NGUYÊN LIỆU ===
                // Cập nhật tồn kho nguyên liệu
                $app->update("ingredient", ["amount[-]" => $move_amount], ["id" => $ingredient_id]);

                // Thêm chi tiết phiếu xuất kho
                $export_detail_data = [
                    "warehouses" => $exportWarehouseId,
                    "data" => 'ingredient',
                    "type" => 'export',
                    "ingredient" => $ingredient_id,
                    "amount" => $move_amount,
                    "price" => $item['selling_price'],
                    "notes" => $app->xss($item['notes'] ?? ''),
                    "date" => $current_time,
                    "user" => $account['id'] ?? 0,
                    "stores" => $data['store_id'],
                    "branch" => $data['branch_id'],
                ];
                $app->insert("warehouses_details", $export_detail_data);
                $exportDetailId = $app->id();

                // Ghi log phiếu xuất kho
                $app->insert("warehouses_logs", [
                    "warehouses" => $exportWarehouseId,
                    "details" => $exportDetailId,
                    "data" => 'ingredient',
                    "type" => 'export',
                    "ingredient" => $ingredient_id,
                    "price" => $item['selling_price'],
                    "amount" => $move_amount,
                    "total" => $move_amount * $item['selling_price'],
                    "notes" => $app->xss($item['notes'] ?? ''),
                    "date" => $current_time,
                    "user" => $account['id'] ?? 0,
                    "stores" => $data['store_id'],
                ]);

                // === BƯỚC B: XỬ LÝ NHẬP KHO THÀNH PHẨM ===
                $new_product_code = ($store_info['code'] ?? '') . ($branch_info['code'] ?? '') . $ingredient_info['code'];

                $existing_product = $app->get("products", ["id"], ["code" => $new_product_code, "deleted" => 0]);
                $product_id_for_import = 0;

                if ($existing_product) {
                    // Cập nhật số lượng thành phẩm hiện có
                    $product_id_for_import = $existing_product['id'];
                    $app->update("products", ["amount[+]" => $move_amount], ["id" => $product_id_for_import]);
                } else {
                    // Tạo thành phẩm mới
                    $name = $ingredient_info['type'] == 1 ? 'Đai' : ($ingredient_info['type'] == 2 ? 'Ngọc' : $ingredient_info['name_ingredient']);
                    $new_product_data = [
                        "code" => $new_product_code,
                        "name" => $name,
                        "amount" => $move_amount,
                        "price" => $ingredient_info['price'],
                        "cost" => $ingredient_info['cost'],
                        "units" => $ingredient_info['units'],
                        "status" => 'A',
                        "user" => $account['id'] ?? 0,
                        "date" => $current_time,
                        "stores" => $data['store_id'],
                        "branch" => $data['branch_id'],
                        "code_products" => $ingredient_info['code'],
                    ];
                    $app->insert("products", $new_product_data);
                    $product_id_for_import = $app->id();
                }

                // Thêm chi tiết phiếu nhập kho thành phẩm
                $import_detail_data = [
                    "warehouses" => $importWarehouseId,
                    "data" => 'products',
                    "type" => 'import',
                    "products" => $product_id_for_import,
                    "amount" => $move_amount,
                    "amount_total" => $move_amount,
                    "price" => $item['selling_price'],
                    "notes" => $app->xss($item['notes'] ?? ''),
                    "date" => $current_time,
                    "user" => $account['id'] ?? 0,
                    "stores" => $data['store_id'],
                    "branch" => $data['branch_id'],
                ];
                $app->insert("warehouses_details", $import_detail_data);
                $importDetailId = $app->id();

                // Ghi log phiếu nhập kho thành phẩm
                $app->insert("warehouses_logs", [
                    "warehouses" => $importWarehouseId,
                    "details" => $importDetailId,
                    "data" => 'products',
                    "type" => 'import',
                    "products" => $product_id_for_import,
                    "price" => $item['selling_price'],
                    "amount" => $move_amount,
                    "total" => $move_amount * $item['selling_price'],
                    "notes" => $app->xss($item['notes'] ?? ''),
                    "date" => $current_time,
                    "user" => $account['id'] ?? 0,
                    "stores" => $data['store_id'],
                ]);
            }

            // 5. Ghi log hệ thống và xóa session
            $jatbi->logs('warehouses', 'move_completed', [$export_warehouse_data, $import_warehouse_data, $data]);
            unset($_SESSION['ingredient_move']);

            echo json_encode(['status' => 'success', 'content' => 'Hoàn tất chuyển kho thành công!']);
        }
    });




    // API để LƯU lựa chọn CỬA HÀNG vào session
    $app->router('/ingredient-move-product/move/stores', ['POST'], function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $store_id = $_POST['value'] ?? 0;

        if (!isset($_SESSION['ingredient_move'])) {
            $_SESSION['ingredient_move'] = [];
        }

        $_SESSION['ingredient_move']['store_id'] = (int) $app->xss($store_id);
        // Khi đổi cửa hàng, reset lựa chọn quầy hàng
        $_SESSION['ingredient_move']['branch_id'] = null;

        echo json_encode(['status' => 'success', 'content' => 'Đã cập nhật cửa hàng.']);
    });

    // API để LƯU lựa chọn QUẦY HÀNG vào session
    $app->router('/ingredient-move-product/move/branch', ['POST'], function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $branch_id = $_POST['value'] ?? 0;

        if (!isset($_SESSION['ingredient_move'])) {
            $_SESSION['ingredient_move'] = [];
        }

        $_SESSION['ingredient_move']['branch_id'] = (int) $app->xss($branch_id);
        echo json_encode(['status' => 'success', 'content' => 'Đã cập nhật quầy hàng.']);
    });










    // API TÌM KIẾM NGUYÊN LIỆU CÓ TỒN KHO
    $app->router("/ingredient-export-search", ['POST'], function ($vars) use ($app) {
        $app->header(['Content-Type' => 'application/json']);
        $searchValue = isset($_POST['search']) ? $app->xss($_POST['search']) : '';

        // Chỉ tìm nguyên liệu có tồn kho lớn hơn 0
        $where = [
            "AND" => [
                "OR" => ["name_ingredient[~]" => $searchValue, "code[~]" => $searchValue],
                "status" => 'A',
                "deleted" => 0,
                "amount[>]" => 0
            ],
            "LIMIT" => 10
        ];

        $datas = [];
        $app->select("ingredient", ["id", "code", "name_ingredient"], $where, function ($data) use (&$datas) {
            $datas[] = [
                'id' => $data['id'],
                'text' => $data['code'] . ' - ' . $data['name_ingredient'],
                "url" => "/api/ingredient-update/export/ingredient/add/" . $data["id"],
            ];
        });
        echo json_encode($datas);
    });

    // API THÊM NGUYÊN LIỆU VÀO PHIẾU

    $app->router('/ingredient-update/export/ingredient/add/{id}', ['POST'], function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $id = (int) ($vars['id'] ?? 0);

        $ingredient_data = $app->get("ingredient", "*", ["id" => $id, "status" => 'A', "deleted" => 0]);
        if (!$ingredient_data || $ingredient_data['amount'] <= 0) {
            echo json_encode(['status' => 'error', 'content' => 'Nguyên liệu không tồn tại hoặc đã hết hàng.']);
            return;
        }

        if (!isset($_SESSION['ingredient_export']['ingredients'])) {
            $_SESSION['ingredient_export']['ingredients'] = [];
        }

        $stock_quantity = $ingredient_data['amount'];

        if (isset($_SESSION['ingredient_export']['ingredients'][$id])) {
            // Kiểm tra nếu thêm nữa có vượt tồn kho không
            if ($_SESSION['ingredient_export']['ingredients'][$id]['amount'] + 1 > $stock_quantity) {
                echo json_encode(['status' => 'error', 'content' => 'Số lượng vượt quá tồn kho. Tồn kho: ' . $stock_quantity]);
                return;
            }
            $_SESSION['ingredient_export']['ingredients'][$id]['amount']++;
        } else {
            $unit_name = $app->get("units", "name", ["id" => $ingredient_data['units']]) ?? 'N/A';
            $_SESSION['ingredient_export']['ingredients'][$id] = [
                "ingredient_id" => $ingredient_data['id'],
                "code" => $ingredient_data['code'],
                "name" => $ingredient_data['name_ingredient'],
                "selling_price" => $ingredient_data['price'] ?? 0,
                "stock_quantity" => $stock_quantity,
                "amount" => 1,
                "unit_name" => $unit_name,
                "notes" => '',
            ];
        }
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Thêm nguyên liệu thành công')]);
    });

    // API CẬP NHẬT SỐ LƯỢNG

    $app->router('/ingredient-update/export/ingredient/amount/{id}', ['POST'], function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $id = $vars['id'] ?? '';
        $value = (int) str_replace([','], '', $_POST['value'] ?? 0);

        if (!isset($_SESSION['ingredient_export']['ingredients'][$id])) {
            echo json_encode(['status' => 'error', 'content' => 'Nguyên liệu không tồn tại trong phiếu.']);
            return;
        }

        $stock_quantity = $_SESSION['ingredient_export']['ingredients'][$id]['stock_quantity'];

        if ($value < 1) {
            echo json_encode(['status' => 'error', 'content' => 'Số lượng phải lớn hơn 0.']);
        } elseif ($value > $stock_quantity) {
            $_SESSION['ingredient_export']['ingredients'][$id]['amount'] = $stock_quantity;
            echo json_encode(['status' => 'error', 'content' => 'Số lượng vượt tồn kho. Đã tự động điều chỉnh.']);
        } else {
            $_SESSION['ingredient_export']['ingredients'][$id]['amount'] = $value;
            echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
        }
    });

    // API CẬP NHẬT GHI CHÚ
    // Tương ứng với: ingredient-update/export/ingredient/notes/{id}
    $app->router('/ingredient-update/export/ingredient/notes/{id}', ['POST'], function ($vars) use ($app) {
        $app->header(['Content-Type' => 'application/json']);
        $id = $vars['id'] ?? '';
        $value = $_POST['value'] ?? '';

        if (isset($_SESSION['ingredient_export']['ingredients'][$id])) {
            $_SESSION['ingredient_export']['ingredients'][$id]['notes'] = $app->xss($value);
            echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
        }
    });

    // API CẬP NHẬT NHÓM CHẾ TÁC

    $app->router('/ingredient-update/export/group-crafting', ['POST'], function ($vars) use ($app) {
        $app->header(['Content-Type' => 'application/json']);
        $value = $_POST['value'] ?? 1;
        $_SESSION['ingredient_export']['group_crafting'] = (int) $app->xss($value);
        echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
    });

    // API CẬP NHẬT NỘI DUNG PHIẾU

    $app->router('/ingredient-update/export/ingredient/set-content', ['POST'], function ($vars) use ($app) {
        $app->header(['Content-Type' => 'application/json']);
        $value = $_POST['value'] ?? '';
        $_SESSION['ingredient_export']['content'] = $app->xss($value);
        echo json_encode(['status' => 'success', 'content' => 'Cập nhật thành công']);
    });

    // API XÓA NGUYÊN LIỆU KHỎI PHIẾU

    $app->router('/ingredient-update/export/ingredient/delete/{id}', ['GET', 'POST'], function ($vars) use ($app, $setting, $jatbi) {
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $id = $vars['id'] ?? 0;
            if (isset($_SESSION['ingredient_export']['ingredients'][$id])) {
                unset($_SESSION['ingredient_export']['ingredients'][$id]);
            }
            echo json_encode(['status' => 'success', 'content' => 'Xóa thành công']);
        }
    });

    // API HỦY PHIẾU

    $app->router('/ingredient-update/export/ingredient/cancel', ['GET', 'POST'], function ($vars) use ($app, $setting, $jatbi) {
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            if (isset($_SESSION['ingredient_export'])) {
                unset($_SESSION['ingredient_export']);
            }
            echo json_encode(['status' => 'success', 'content' => 'Đã hủy phiếu thành công']);
        }
    });

    // API HOÀN TẤT VÀ LƯU PHIẾU XUẤT

    $app->router('/ingredient-update/export/ingredient/completed', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/confirm-ingredient-export.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $account = $app->getSession("accounts");
            $data = $_SESSION['ingredient_export'] ?? [];

            // Kiểm tra dữ liệu
            if (empty($data['ingredients'])) {
                echo json_encode(['status' => 'error', 'content' => 'Không có nguyên liệu trong phiếu.']);
                return;
            }
            if (empty($data['content'])) {
                echo json_encode(['status' => 'error', 'content' => 'Nội dung không được để trống.']);
                return;
            }

            // Tạo phiếu xuất kho chính
            $warehouse_data = [
                "code" => 'PX',
                "type" => 'export',
                "data" => 'ingredient',
                "content" => $app->xss($data['content']),
                "user" => $account['id'] ?? 0,
                "date" => $data['date'] ?? date("Y-m-d H:i:s"),
                "date_poster" => date("Y-m-d H:i:s"),
                "group_crafting" => $data['group_crafting'] ?? 1,
                "active" => $jatbi->active(30),
            ];
            $app->insert("warehouses", $warehouse_data);
            $warehouseId = $app->id();

            // Xử lý từng dòng nguyên liệu
            foreach ($data['ingredients'] as $item) {
                $ingredient_id = $item['ingredient_id'];
                $export_amount = (int) $item['amount'];

                // Cập nhật tồn kho nguyên liệu (giảm số lượng)
                $app->update("ingredient", ["amount[-]" => $export_amount], ["id" => $ingredient_id]);

                // Thêm chi tiết phiếu xuất
                $detail_data = [
                    "warehouses" => $warehouseId,
                    "data" => 'ingredient',
                    "type" => 'export',
                    "ingredient" => $ingredient_id,
                    "amount" => $export_amount,
                    "price" => $item['selling_price'],
                    "notes" => $app->xss($item['notes'] ?? ''),
                    "date" => date("Y-m-d H:i:s"),
                    "user" => $account['id'] ?? 0,
                    "group_crafting" => $data['group_crafting'] ?? 1,
                ];
                $app->insert("warehouses_details", $detail_data);
                $detailId = $app->id();

                // Ghi log
                $app->insert("warehouses_logs", [
                    "warehouses" => $warehouseId,
                    "details" => $detailId,
                    "data" => 'ingredient',
                    "type" => 'export',
                    "ingredient" => $ingredient_id,
                    "price" => $item['selling_price'],
                    "amount" => $export_amount,
                    "total" => $export_amount * $item['selling_price'],
                    "notes" => $app->xss($item['notes'] ?? ''),
                    "date" => date("Y-m-d H:i:s"),
                    "user" => $account['id'] ?? 0,
                    "group_crafting" => $data['group_crafting'] ?? 1,
                ]);
            }

            $jatbi->logs('warehouses', 'export', [$warehouse_data, $data]);
            unset($_SESSION['ingredient_export']);

            echo json_encode(['status' => 'success', 'content' => 'Hoàn tất xuất kho thành công!']);
        }
    });






    $app->router('/ingredient-cancel/update-amount/{id}', ['POST'], function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $batch_id = (int) ($vars['id'] ?? 0);
        $value = $_POST['value'] ?? '';
        $session_key = 'ingredient_cancel';

        if (!isset($_SESSION[$session_key]) || !isset($_SESSION[$session_key]['batches'][$batch_id])) {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Lô hàng không tồn tại trong phiếu.")]);
            return;
        }

        $value = (int) str_replace([','], '', $app->xss($value));
        $amount_total = $_SESSION[$session_key]['batches'][$batch_id]['amount_total'];

        if ($value < 0) {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Số lượng hủy không được nhỏ hơn 0.")]);
            return;
        }

        if ($value > $amount_total) {
            $_SESSION[$session_key]['batches'][$batch_id]['amount_cancel'] = $amount_total;
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Số lượng hủy không được lớn hơn tồn kho.")]);
            return;
        }

        $_SESSION[$session_key]['batches'][$batch_id]['amount_cancel'] = $value;
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });




    $app->router('/warehouses/ingredient-update/cancel/notes/{id}', ['POST'], function ($vars) use ($app, $jatbi) {
        $id = $vars['id'] ?? '';
        $value = $_POST['notes'] ?? '';

        if (!isset($_SESSION['ingredient']['cancel']['ingredients'][$id])) {
            $_SESSION['error'] = $jatbi->lang('Nguyên liệu không tồn tại trong phiếu.');
        } else {
            $_SESSION['ingredient']['cancel']['ingredients'][$id]['notes'] = $app->xss($value);
            $_SESSION['success'] = $jatbi->lang('Cập nhật thành công');
        }
        $app->redirect('/warehouses/ingredient-update/cancel/' . $_SESSION['ingredient']['cancel']['warehouses_id']);
    })->setPermissions(['ingredient-cancel']);




    $app->router('/ingredient-cancel/update-content', ['POST'], function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $value = $_POST['value'] ?? '';
        $session_key = 'ingredient_cancel';

        if (!isset($_SESSION[$session_key])) {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Phiếu hủy không tồn tại.")]);
            return;
        }

        $_SESSION[$session_key]['content'] = $app->xss($value);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
    });




    // --- API HỦY BỎ PHIẾU HỦY NGUYÊN LIỆU ---
    $app->router('/ingredient-cancel/cancel', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        /**
         * Khi người dùng bấm nút "Hủy bỏ", một request GET sẽ được gửi tới đây
         * để hiển thị modal xác nhận.
         */
        // if ($app->method() === 'GET') {
        //     // Tùy chỉnh nội dung cho modal xác nhận
        //     $vars['title'] = $jatbi->lang("Xác nhận hủy bỏ");
        //     $vars['content'] = $jatbi->lang("Bạn có chắc chắn muốn hủy bỏ thao tác này không? Mọi dữ liệu về phiếu hủy đang tạo sẽ bị xóa.");

        //     // Render một template xác nhận chung (ví dụ: common/deleted.html)
        //     echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        // }
        /**
         * Khi người dùng xác nhận trong modal, một request POST sẽ được gửi tới đây
         * để thực hiện hành động hủy.
         */
        if ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $session_key = 'ingredient_cancel';

            // Kiểm tra và xóa session chứa dữ liệu tạm của phiếu hủy
            if (isset($_SESSION[$session_key])) {
                unset($_SESSION[$session_key]);
            }

            // Trả về thông báo thành công và yêu cầu chuyển hướng
            echo json_encode([
                'status' => 'success',
                'content' => $jatbi->lang('Thao tác đã được hủy bỏ thành công.'),
                'redirect' => '/warehouses/ingredient' // Chuyển hướng người dùng về trang danh sách nguyên liệu
            ]);
        }
    });




    $app->router('/ingredient-cancel/completed', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $session_key = 'ingredient_cancel';
        $data = $_SESSION[$session_key];
        if ($app->method() === 'GET') {
            $vars['id'] = $data['ingredient_id'];
            echo $app->render($setting['template'] . '/common/confirm-ingredient-cancel.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);


            // Lấy ID người dùng một cách an toàn
            $user_id = $app->getSession("accounts")['id'] ?? 0;

            // --- Kiểm tra dữ liệu đầu vào (Giữ nguyên) ---
            if (!isset($_SESSION[$session_key])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Phiếu hủy không tồn tại.")]);
                return;
            }


            $total_cancel_amount = 0;
            $has_invalid_amount = false;

            foreach ($data['batches'] as $batch_id => $batch) {
                if ($batch['amount_cancel'] > 0) {
                    $total_cancel_amount += $batch['amount_cancel'];
                }
                if ($batch['amount_cancel'] > $batch['amount_total']) {
                    $has_invalid_amount = true;
                    break;
                }
            }

            if ($total_cancel_amount <= 0) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng nhập số lượng hủy.")]);
                return;
            }
            if ($has_invalid_amount) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Số lượng hủy không được lớn hơn tồn kho.")]);
                return;
            }
            if (empty($data['content'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng nhập nội dung phiếu hủy.")]);
                return;
            }

            $ingredient_data = $app->get("ingredient", ["id", "amount"], ["id" => $data['ingredient_id']]);
            if (!$ingredient_data) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Nguyên liệu không tồn tại.")]);
                return;
            }

            // 1. Tạo phiếu hủy mới trong bảng warehouses
            $insert_warehouses = [
                "code" => 'PH',
                "type" => 'cancel',
                "data" => 'ingredient',
                "content" => $data['content'],
                "user" => $user_id, // Sử dụng biến an toàn
                "date" => $data['date'],
                "active" => $jatbi->active(30),
                "date_poster" => date("Y-m-d H:i:s"),
            ];
            $app->insert("warehouses", $insert_warehouses);
            $orderId = $app->id();

            $warehouses_details_logs = [];

            // 2. Cập nhật số lượng và lưu log cho từng lô hàng
            foreach ($data['batches'] as $batch) {
                if ($batch['amount_cancel'] > 0) {
                    $get_batch_details = $app->get("warehouses_details", "*", [
                        "id" => $batch['batch_id'],
                        "deleted" => 0,
                        "amount_total[>]" => 0,
                    ]);

                    if ($get_batch_details) {
                        $app->update("warehouses_details", [
                            "amount_cancel[+]" => $batch['amount_cancel'],
                            "amount_total[-]" => $batch['amount_cancel'],
                        ], ["id" => $batch['batch_id']]);

                        $pro = [
                            "warehouses" => $orderId,
                            "data" => 'ingredient',
                            "type" => 'cancel',
                            "ingredient" => $data['ingredient_id'],
                            "amount_old" => $batch['amount_total'],
                            "amount" => $batch['amount_cancel'],
                            "amount_total" => $get_batch_details['amount_total'] - $batch['amount_cancel'],
                            "price" => $batch['price'],
                            "cost" => $get_batch_details['cost'],
                            "date" => date("Y-m-d H:i:s"),
                            "user" => $user_id, // Sử dụng biến an toàn
                        ];
                        $app->insert("warehouses_details", $pro);

                        $warehouses_logs = [
                            "type" => 'cancel',
                            "data" => 'ingredient',
                            "warehouses" => $orderId,
                            "details" => $batch['batch_id'],
                            "ingredient" => $data['ingredient_id'],
                            "amount" => $batch['amount_cancel'],
                            "price" => $batch['price'],
                            "cost" => $get_batch_details['cost'],
                            "total" => $batch['amount_cancel'] * $batch['price'],
                            "date" => date('Y-m-d H:i:s'),
                            "user" => $user_id, // Sử dụng biến an toàn
                        ];
                        $app->insert("warehouses_logs", $warehouses_logs);
                        $warehouses_details_logs[] = $pro;
                    }
                }
            }

            // 3. Cập nhật tổng số lượng tồn kho của nguyên liệu
            $app->update("ingredient", ["amount[-]" => $total_cancel_amount], ["id" => $data['ingredient_id']]);

            // 4. Ghi log hệ thống và xóa session
            $jatbi->logs('warehouses', 'cancel', [$insert_warehouses, $warehouses_details_logs, $data]);
            unset($_SESSION[$session_key]);

            // 5. Trả về kết quả thành công
            // 5. Trả về kết quả thành công
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Hủy hàng thành công.")]);
        }
    });
    $app->router('/purchase-import/update-amount/{id}', ['POST'], function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $id = $vars['id'] ?? '';
        $value = $_POST['value'] ?? '';

        if (!isset($_SESSION['ingredient_purchase_import']['ingredients'][$id])) {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Nguyên liệu không tồn tại trong phiếu.')]);
            return;
        }
        $value = (int) str_replace([','], '', $app->xss($value));
        if ($value < 1) {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Số lượng phải lớn hơn 0.')]);
        } else {
            $_SESSION['ingredient_purchase_import']['ingredients'][$id]['amount'] = $value;
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
        }
    });



    $app->router('/purchase-import/update-notes/{id}', ['POST'], function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $id = $vars['id'] ?? '';
        $value = $_POST['value'] ?? '';

        if (!isset($_SESSION['ingredient_purchase_import']['ingredients'][$id])) {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Nguyên liệu không tồn tại trong phiếu.')]);
            return;
        }
        $_SESSION['ingredient_purchase_import']['ingredients'][$id]['notes'] = $app->xss($value);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
    });


    $app->router('/purchase-import/set-content', ['POST'], function ($vars) use ($app, $jatbi) {
        $app->header(['Content-Type' => 'application/json']);
        $value = $_POST['value'] ?? '';
        if (!isset($_SESSION['ingredient_purchase_import'])) {
            $_SESSION['ingredient_purchase_import'] = ['ingredients' => [], 'content' => ''];
        }
        $_SESSION['ingredient_purchase_import']['content'] = $app->xss($value);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công')]);
    });

    $app->router('/purchase-import/delete/{id}', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
        $app->header(['Content-Type' => 'application/json']);
        $id = $vars['id'] ?? 0;

        if (!isset($_SESSION['ingredient_purchase_import']['ingredients'][$id])) {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Nguyên liệu không tồn tại trong phiếu.')]);
            return;
        }

        unset($_SESSION['ingredient_purchase_import']['ingredients'][$id]);

        echo json_encode([
            'status' => 'success',
            'content' => $jatbi->lang('Xóa thành công'),
        ]);
    });


    $app->router('/purchase-import/cancel', ['POST'], function ($vars) use ($app, $jatbi) {
        if (isset($_SESSION['ingredient_purchase_import'])) {
            unset($_SESSION['ingredient_purchase_import']);
        }

        echo json_encode([
            'status' => 'success',
            'content' => $jatbi->lang('Đã hủy phiếu'),
            // 'redirect' => '/purchases' // Chuyển về trang danh sách phiếu mua hàng
        ]);
    });


    $app->router('/purchase-import/completed', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
        $app->header(['Content-Type' => 'application/json']);
        $sessionData = $_SESSION['ingredient_purchase_import'] ?? [];
        $ingredients = $sessionData['ingredients'] ?? [];

        if (empty($ingredients) || empty($sessionData['content'])) {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Vui lòng điền đủ thông tin.')]);
            return;
        }

        $userId = $app->getSession("accounts")['id'] ?? 0;
        $current_time = date("Y-m-d H:i:s");

        $warehouse_insert = [
            "code" => 'PN' . time(),
            "type" => 'import',
            "data" => 'ingredient',
            "content" => $sessionData['content'],
            "user" => $userId,
            "date" => $sessionData['date'],
            "date_poster" => $current_time,
            "vendor" => $sessionData['vendor'],
            "purchase" => $sessionData['purchase_id'],
        ];
        $app->insert("warehouses", $warehouse_insert);
        $warehouseId = $app->id();

        foreach ($ingredients as $ingredient) {
            $app->update("ingredient", ["amount[+]" => $ingredient['amount']], ["id" => $ingredient['ingredient_id']]);

            $detail_insert = [
                "warehouses" => $warehouseId,
                "data" => 'ingredient',
                "type" => 'import',
                "vendor" => $ingredient['vendor'],
                "ingredient" => $ingredient['ingredient_id'],
                "amount" => $ingredient['amount'],
                "amount_total" => $ingredient['amount'],
                "price" => $ingredient['price'],
                "notes" => $ingredient['notes'],
                "date" => $current_time,
                "user" => $userId,
            ];
            $app->insert("warehouses_details", $detail_insert);
            $detailId = $app->id();

            // Thêm logic lưu vào warehouses_logs
            $log_insert = [
                "type" => 'import',
                "data" => 'ingredient',
                "warehouses" => $warehouseId,
                "details" => $detailId,
                "ingredient" => $ingredient['ingredient_id'],
                "price" => $ingredient['price'],
                "amount" => $ingredient['amount'],
                "total" => $ingredient['amount'] * $ingredient['price'],
                "notes" => $ingredient['notes'],
                "date" => $current_time,
                "user" => $userId,
            ];
            $app->insert("warehouses_logs", $log_insert);
        }

        $jatbi->logs('warehouses', 'import', [$warehouse_insert, $ingredients]);
        unset($_SESSION['ingredient_purchase_import']);

        echo json_encode([
            'status' => 'success',
            'content' => $jatbi->lang('Lưu phiếu nhập thành công'),
            'redirect' => '/warehouses/ingredient-import-history/' . $warehouseId,
        ]);
    });

    $app->router("/products-search-purchase", ['POST'], function ($vars) use ($app) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);

        $searchValue = isset($_POST['search']) ? $app->xss($_POST['search']) : '';
        $stores_json = $app->getCookie('stores') ?? json_encode([]);
        $stores = json_decode($stores_json, true);
        $store = $stores[0]['value'];

        $where = [
            "AND" => [
                "OR" => [
                    "products.name[~]" => $searchValue,
                    "products.code[~]" => $searchValue,
                ],
                "products.status" => 'A',
                "products.deleted" => 0,
                "products.stores" => $store
            ],
            "LIMIT" => 15
        ];

        $datas = [];
        $app->select("products", [
            "[>]branch" => ["branch" => "id"]
        ], [
            "products.id",
            "products.code",
            "products.name",
            "products.amount",
            "branch.name(branch_name)"
        ], $where, function ($data) use (&$datas, $vars) {
            $datas[] = [
                "text" => $data['code'] . ' - ' . $data['name'],
                "brands" => $data['branch_name'],
                "amount" => $data['amount'],
                "url" => "/purchases/purchase-update/edit/products/add/" . $data['id'],
                "value" => $data['id'],

            ];
        });

        echo json_encode($datas);
    });

})->middleware(names: 'login');


