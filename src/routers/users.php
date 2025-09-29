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
$app->router($setting['manager'] . "/notification", 'GET', function ($vars) use ($app, $jatbi, $setting) {
    $vars['templates'] = 'notification';
    $user = $app->getSession("accounts");
    $vars['datas'] = $app->select("notifications", "*", ["account" => $user['id'], "deleted" => 0, "ORDER" => ["date" => "DESC"], "LIMIT" => 20]);
    echo $app->render($setting['template'] . '/users/notification.html', $vars, $jatbi->ajax());
})->middleware('login');

$app->group($setting['manager'] . "/users", function ($app) use ($jatbi, $setting, $accStore, $stores) {
    $app->router("/profile", 'GET', function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Thông tin");
        $vars['router'] = 'profile';
        $vars['account'] = $app->get("accounts", "*", ["id" => $app->getSession("accounts")['id']]);
        echo $app->render($setting['template'] . '/users/profile.html', $vars);
    });

    $app->router("/notification", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['router'] = 'notification';
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Thông báo");
            echo $app->render($setting['template'] . '/users/profile.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'desc';
            $where = [
                "OR" => [
                    "notifications.title[~]" => $searchValue,
                    "notifications.content[~]" => $searchValue,
                    "accounts.name[~]" => $searchValue,
                ],
                "notifications.account" => $app->getSession("accounts")['id'],
                "notifications.deleted" => 0,
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)]
            ];
            $count = $app->count("notifications", [
                "OR" => [
                    "notifications.title[~]" => $searchValue,
                    "notifications.content[~]" => $searchValue,
                ],
                "notifications.account" => $app->getSession("accounts")['id'],
                "notifications.deleted" => 0,
            ]);
            $app->select(
                "notifications",
                [
                    "[>]accounts" => ["user" => "id"]
                ],
                [
                    'notifications.id',
                    'notifications.template',
                    'notifications.date',
                    'notifications.title',
                    'notifications.active',
                    'notifications.views',
                    'notifications.content',
                    'notifications.user',
                    'accounts.name',
                    'accounts.avatar',
                ],
                $where,
                function ($data) use (&$datas, $jatbi, $app) {
                    if (isset($data['data']) && $data['data'] != '') {
                        $getdata = json_decode($data['data']);
                    } else {
                        $getdata = null;
                    }
                    $content = $jatbi->lang($data['content']);
                    $content = str_replace("[account]", $data['name'], $content);
                    if ($getdata && isset($getdata->content)) {
                        $content = str_replace("[content]", number_format($getdata->content), $content);
                    } else {
                        $content = str_replace("[content]", "0", $content);
                    }
                    if ($data['template'] == 'url') {
                        $url = '<a class="btn btn-sm btn-primary-light border-0 p-2" href="/users/notification/' . $data['active'] . '" data-pjax><i class="ti ti-eye"></i></a>';
                        $content = '<a class="link-primary" href="/users/notification/' . $data['active'] . '" data-pjax><span class="width height bg-' . ($data['views'] > 0 ? 'secondary' : 'danger') . ' rounded-circle d-inline-block me-2" style="--width:10px;--height:10px"></span>' . $content . '</a>';
                    } else {
                        $url = '<a class="btn btn-sm btn-primary-light border-0 p-2" data-action="modal" data-url="/users/notification/' . $data['active'] . '"><i class="ti ti-eye"></i></a>';
                        $content = '<a class="link-primary" href="/users/notification/' . $data['active'] . '" data-pjax><span class="width height bg-' . ($data['views'] > 0 ? 'secondary' : 'danger') . ' rounded-circle d-inline-block me-2" style="--width:10px;--height:10px"></span>' . $content . '</a>';
                    }
                    $datas[] = [
                        "checkbox" => $app->component("box", ["data" => $data['id']]),
                        "content" => $content,
                        "url" => $url,
                        "date" => $jatbi->datetime($data['date']),
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
    });
    // accounts-partner start
    $app->router('/accounts-partner', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $jatbi->permission('accounts-partner');
        $vars['title'] = $jatbi->lang("Tài Khoản Đối Tác");

        if ($app->method() === 'GET') {
            $vars['permission'] = $app->select("permission",["id(value)", "name(text)"]);
            echo $app->render($setting['template'] . '/users/accounts-partner.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);

            // Lấy tham số từ DataTables
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : $setting['site_page'] ?? 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $status = isset($_POST['status']) ? [$_POST['status'], $_POST['status']] : '';

            // Điều kiện lọc
            $where = [
                "AND" => [
                    "OR" => [
                        "accounts.name[~]" => $searchValue,
                        "accounts.email[~]" => $searchValue,
                        "accounts.account[~]" => $searchValue,
                    ],
                    "accounts.deleted" => 0,
                    "accounts.status[<>]" => $status,
                    "accounts.type" => 10, // Chỉ lấy tài khoản đối tác
                ],
            ];
            $session = $app->getSession("accounts");
            $sessionStores = $app->getSession("stores");
            $Search_ACC = [];
            $account = [];

            if (isset($session['id'])) {
                $account = $app->get("accounts", "*", [
                    "id" => $session['id'],
                    "deleted" => 0,
                    "status" => "A",
                ]);

                if ($sessionStores != 0) {
                    $stores = $app->select("stores", ["id", "name", "code"], ["deleted" => 0, "status" => 'A', "id" => $sessionStores]);
                } else {
                    if ($account['stores'] == '') {
                        $stores = $app->select("stores", ["id", "name", "code"], ["deleted" => 0, "status" => 'A']);
                    } else {
                        $stores = $app->select("stores", ["id", "name", "code"], ["deleted" => 0, "status" => 'A', "id" => unserialize($account['stores'])]);
                    }
                }
                foreach ($stores as $itemStore) {
                    if ($account['stores'] == '') {
                        $accStore[0] = "0";
                    }
                    $accStore[$itemStore['id']] = $itemStore['id'];
                }
            }
            $Accsearchs = $app->select("accounts", [
                "[>]permission" => ["permission" => "id"],
            ], [
                "accounts.id",
                "accounts.name",
                "accounts.status",
                "accounts.email",
                "accounts.avatar",
                "accounts.account",
                "accounts.phone",
                "accounts.stores",
            ], $where);

            foreach ($Accsearchs as $key => $search) {
                if ($account['stores'] == '' && $sessionStores == 0) {
                    $Search_ACC[$search['id']] = $search['id'];
                } else {
                    $storeData = unserialize($search['stores']);
                    if (is_array($storeData)) {
                        $getACCStores[$search['id']] = $storeData;
                        foreach ($storeData as $value) {
                            if (isset($accStore[$value])) {
                                $Search_ACC[$search['id']] = $search['id'];
                                break; // chỉ cần một match
                            }
                        }
                    }
                }

            }
            $where1 = [
                "AND" => [
                    "accounts.id" => $Search_ACC,
                    "OR" => [
                        "accounts.name[~]" => $searchValue,
                        "accounts.email[~]" => $searchValue,
                        "accounts.account[~]" => $searchValue,
                    ],
                    "accounts.deleted" => 0,
                    "accounts.status[<>]" => $status,
                    "accounts.type" => 10, // Chỉ lấy tài khoản đối tác
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];

            $where2 = [
                "AND" => [
                    "accounts.id" => $Search_ACC,
                    "OR" => [
                        "accounts.name[~]" => $searchValue,
                        "accounts.email[~]" => $searchValue,
                        "accounts.account[~]" => $searchValue,
                    ],
                    "accounts.deleted" => 0,
                    "accounts.status[<>]" => $status,
                    "accounts.type" => 10, // Chỉ lấy tài khoản đối tác
                ],
            ];
            $count = $app->count("accounts", [
                "AND" => $where2['AND'],
            ]);

            // Lấy dữ liệu phiếu giảm giá
            $datas = [];
            $app->select("accounts", [
                "[>]permission" => ["permission" => "id"],
            ], [
                "accounts.id",
                "accounts.name",
                "accounts.status",
                "accounts.email",
                "accounts.avatar",
                "accounts.account",
                "accounts.phone",
                "permission.name (permission_name)" // alias để dễ truy xuất
            ], $where1, function ($data) use (&$datas, $jatbi, $app, $setting) {
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "image" => '<img data-src="/' . $setting['upload']['images']['avatar']['url'] . $data['avatar'] . '" class="width rounded-circle me-2 lazyload" style="--width:40px">',
                    "name" => $data['name'],
                    "account" => $data['account'],
                    "email" => $data['email'],
                    "phone" => $data['phone'],
                    "status" => $app->component("status", [
                        "url" => "/users/accounts-partner-status/" . $data['id'],
                        "data" => $data['status'],
                        "permission" => ['accounts-partner.edit']
                    ]),
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['accounts-partner.edit'],
                                'action' => ['data-url' => '/users/accounts-partner-edit/' . $data['id'], 'data-action' => 'modal']
                            ],
                        ]
                    ]),
                ];
            });

            // Trả về dữ liệu JSON
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas ?? [],
            ]);
        }
    })->setPermissions(['accounts-partner']);

    $app->router("/accounts-partner-status/{id}", 'POST', function ($vars) use ($app, $jatbi, $setting) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $data = $app->get("accounts", "*", ["id" => $vars['id'], "deleted" => 0]);
        if ($data > 1) {
            if ($data > 1) {
                if ($data['status'] === 'A') {
                    $status = "D";
                } elseif ($data['status'] === 'D') {
                    $status = "A";
                }
                $app->update("accounts", ["status" => $status], ["id" => $data['id']]);
                $jatbi->logs('accounts', 'accounts-status', $data);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại"),]);
            }
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['accounts-partner.edit']);
    // accounts-partner end

    $app->router("/notification/{active}", 'GET', function ($vars) use ($app, $jatbi, $setting) {
        $data = $app->get("notifications", "*", ["active" => $app->xss($vars['active']), "deleted" => 0,]);
        $app->update("notifications", ["views" => $data['views'] + 1], ["id" => $data['id']]);
        if ($data['template'] == 'url') {
            $parsedUrl = parse_url($data['url']);
            $queryExists = isset($parsedUrl['query']);
            if ($queryExists) {
                $geturl = '&views=url';
            } else {
                $geturl = '?views=url';
            }
        }
        header("location: " . $data['url'] . $geturl);
    });

    $app->router("/notification-read", 'POST', function ($vars) use ($app, $jatbi, $setting) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $account = $app->get("accounts", "*", ["id" => $app->getSession("accounts")['id']]);
        if ($account > 1) {
            $app->update("notifications", ["views" => 1], ["account" => $account['id'], "views" => 0]);
            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
        } else {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
        }
    });

    $app->router("/notification-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa thông báo");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("notifications", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("notifications", ["deleted" => 1], ["id" => $data['id']]);
                }
                $jatbi->logs('accounts', 'notifications-deleted', $datas);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    });

    $app->router("/logs", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Nhật ký");
        $vars['router'] = 'logs';
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/users/profile.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);

            $draw = intval($_POST['draw'] ?? 0);
            $start = intval($_POST['start'] ?? 0);
            $length = intval($_POST['length'] ?? 10);
            $searchValue = $_POST['search']['value'] ?? '';
            $orderName = $_POST['order'][0]['name'] ?? 'id';
            $orderDir = $_POST['order'][0]['dir'] ?? 'desc';
            $dateRange = $_GET['date'] ?? null;

            $date_from = $date_to = null;
            if ($dateRange) {
                if (is_array($dateRange) && count($dateRange) == 2) {
                    $date_from = date('Y-m-d 00:00:00', strtotime($dateRange[0]));
                    $date_to = date('Y-m-d 23:59:59', strtotime($dateRange[1]));
                } elseif (is_string($dateRange)) {
                    $date_from = date('Y-m-d 00:00:00', strtotime($dateRange));
                    $date_to = date('Y-m-d 23:59:59', strtotime($dateRange));
                }
            }

            $where = [
                "AND" => [
                    "OR" => [
                        "logs.dispatch[~]" => $searchValue,
                        "logs.action[~]" => $searchValue,
                        "logs.content[~]" => $searchValue,
                        "logs.url[~]" => $searchValue,
                        "logs.ip[~]" => $searchValue,
                        "accounts.name[~]" => $searchValue,
                    ],
                    "user" => $app->getSession("accounts")['id'],
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)]
            ];

            if ($date_from && $date_to) {
                $where['AND']["logs.date[<>"] = [$date_from, $date_to];
            }

            $logs = $app->select("logs", ["[>]accounts" => ["user" => "id"]], [
                'logs.id',
                'logs.dispatch',
                'logs.action',
                'logs.url',
                'logs.ip',
                'logs.date',
                'logs.user',
                'logs.active',
                'accounts.name',
                'accounts.avatar',
            ], $where);

            if (!empty($logs)) {
                foreach ($logs as $log) {
                    $datas[] = [
                        "user" => '<img src="/' . $log['avatar'] . '" class="width rounded-circle me-2" style="--width:40px"> ' . $log['name'],
                        "dispatch" => $log['dispatch'],
                        "action" => $log['action'],
                        "url" => $log['url'],
                        "ip" => $log['ip'],
                        "date" => $jatbi->datetime($log['date']),
                        "views" => '<button data-action="modal" data-url="/admin/logs-views/' . $log['active'] . '" class="btn btn-primary-light btn-sm border-0 py-1 px-2 rounded-3" aria-label="' . $jatbi->lang('Xem') . '"><i class="ti ti-eye"></i></button>',
                    ];
                }
                echo json_encode([
                    "status" => "success",
                    "draw" => $draw,
                    "recordsTotal" => count($logs),
                    "recordsFiltered" => count($logs),
                    "data" => $datas ?? []
                ]);
            } else {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Không có dữ liệu")]);
            }
        }
    });

    $app->router("/logs-views/{id}", 'GET', function ($vars) use ($app, $jatbi, $setting) {
        $vars['data'] = $app->get("logs", "*", ["active" => $vars['id'], "deleted" => 0]);
        if ($vars['data'] > 1) {
            echo $app->render($setting['template'] . '/admin/logs-views.html', $vars, $jatbi->ajax());
        } else {
            echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
        }
    });

    $app->router("/settings", 'GET', function ($vars) use ($app, $jatbi, $setting) {
        $vars['router'] = 'settings';
        $vars['title'] = $jatbi->lang("Cài đặt");
        $vars['account'] = $app->get("accounts", "*", ["id" => $app->getSession("accounts")['id']]);
        $vars['data'] = $app->get("settings", "*", ["account" => $app->getSession("accounts")['id']]);
        echo $app->render($setting['template'] . '/users/profile.html', $vars);
    });

    $app->router("/settings/{action}", 'POST', function ($vars) use ($app, $jatbi, $setting) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $account = $app->get("accounts", "*", ["id" => $app->getSession("accounts")['id']]);
        $getsetting = $app->get("settings", "*", ["account" => $account['id']]);
        if ($account > 1) {
            if ($vars['action'] == 'notification') {
                $update = [
                    "notification" => $getsetting['notification'] == 1 ? 0 : 1,
                ];
                $app->update("settings", $update, ["account" => $account['id']]);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } elseif ($vars['action'] == 'notification-no') {
                $update = [
                    "notification" => 0,
                ];
                $app->update("settings", $update, ["account" => $account['id']]);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } elseif ($vars['action'] == 'notification_mail') {
                $update = [
                    "notification_mail" => $getsetting['notification_mail'] == 1 ? 0 : 1,
                ];
                $app->update("settings", $update, ["account" => $account['id']]);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } elseif ($vars['action'] == 'api') {
                $update = [
                    "api" => $getsetting['api'] == 1 ? 0 : 1,
                ];
                if ($update['api'] == 0) {
                    $update['access_token'] = '';
                } else {
                    $update['access_token'] = $app->randomString(128);
                }
                $app->update("settings", $update, ["account" => $account['id']]);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        } else {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
        }
    });

    $app->router("/change-password", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'GET') {
            $vars['account'] = $app->get("accounts", "*", ["id" => $app->getSession("accounts")['id'], "status" => "A"]);
            if ($vars['account'] > 1) {
                echo $app->render($setting['template'] . '/users/change-password.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $account = $app->get("accounts", "*", ["id" => $app->getSession("accounts")['id']]);

            if ($account > 1) {
                $passwordOld = $app->xss($_POST['password-old']);
                $passwordNew = $app->xss($_POST['password']);
                $passwordConfirm = $app->xss($_POST['password-confirm']);

                if ($passwordOld == '' || $passwordNew == '' || $passwordConfirm == '') {
                    echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng không để trống")]);
                } elseif ($passwordNew !== $passwordConfirm) {
                    echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Mật khẩu không khớp")]);
                } elseif (!password_verify($passwordOld, $account['password'])) {
                    echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Mật khẩu không đúng")]);
                } else {
                    $insert = ["password" => password_hash($passwordNew, PASSWORD_DEFAULT)];
                    $app->update("accounts", $insert, ["id" => $account['id']]);
                    echo json_encode(['status' => 'success', "content" => $jatbi->lang("Thay đổi thành công")]);
                    $jatbi->logs('account', 'change-password', $account);
                }
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xảy ra")]);
            }
        }
    });

    $app->router("/change-infomation", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'GET') {
            $vars['account'] = $app->get("accounts", "*", ["id" => $app->getSession("accounts")['id'], "status" => "A"]);
            if ($vars['account'] > 1) {
                echo $app->render($setting['template'] . '/users/change-infomation.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $account = $app->get("accounts", "*", ["id" => $app->getSession("accounts")['id']]);
            if ($account > 1) {
                $name = $app->xss($_POST['name'] ?? '');
                $phone = $app->xss($_POST['phone'] ?? '');
                $avatar = $app->xss($_POST['images'] ?? '');
                $birthday = !empty($_POST['birthday']) ? date('Y-m-d', strtotime(str_replace('/', '-', $_POST['birthday']))) : null;
                $gender = $app->xss($_POST['gender'] ?? 0);
                if ($name == '') {
                    echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng không để trống")]);
                    return;
                }
                $updateData = [
                    "name" => $name,
                    "phone" => $phone,
                    "avatar" => $avatar,
                    "birthday" => $birthday,
                    "gender" => $gender,
                ];
                $app->update("accounts", $updateData, ["id" => $account['id']]);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Thay đổi thành công")]);
                $jatbi->logs('account', 'change-infomation', $account);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xảy ra")]);
            }
        }
    });

    $app->router("/accounts", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Tài khoản");
        if ($app->method() === 'GET') {
            $vars['permission'] = $app->select("permission", ["name (text)", "id (value)"], ["deleted" => 0, "status" => "A"]);
            echo $app->render($setting['template'] . '/users/accounts.html', $vars);
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
            $permission = isset($_POST['permission']) ? $_POST['permission'] : '';
            $where = [
                "AND" => [
                    "OR" => [
                        "accounts.name[~]" => $searchValue,
                        "accounts.email[~]" => $searchValue,
                        "accounts.account[~]" => $searchValue,
                    ],
                    "accounts.status[<>]" => $status,
                    "accounts.deleted" => 0,
                    "accounts.type[!]" => 10,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)]
            ];

            if (!empty($permission)) {
                $where["AND"]["accounts.permission"] = $permission;
            }
            $count = $app->count("accounts", [
                "AND" => $where['AND'],
            ]);
            $app->select(
                "accounts",
                [
                    "[>]permission" => ["permission" => "id"]
                ],
                [
                    'accounts.id',
                    'accounts.name',
                    'accounts.active',
                    'accounts.email',
                    'accounts.avatar',
                    'accounts.status',
                    'permission.name (permission)',
                ],
                $where,
                function ($data) use (&$datas, $jatbi, $app) {
                    $datas[] = [
                        "checkbox" => $app->component("box", ["data" => $data['active']]),
                        "name" => '<img data-src="/' . $data['avatar'] . '?type=thumb" class="width rounded-circle me-2 lazyload" style="--width:40px"> ' . $data['name'],
                        "email" => $data['email'],
                        "permission" => $data['permission'],
                        "status" => $app->component("status", ["url" => "/users/accounts-status/" . $data['active'], "data" => $data['status'], "permission" => ['accounts.edit']]),
                        "action" => $app->component("action", [
                            "button" => [
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Sửa"),
                                    'permission' => ['accounts.edit'],
                                    'action' => ['data-url' => '/users/accounts-edit/' . $data['active'], 'data-action' => 'modal']
                                ],
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Xóa"),
                                    'permission' => ['accounts.deleted'],
                                    'action' => ['data-url' => '/users/accounts-deleted?box=' . $data['active'], 'data-action' => 'modal']
                                ],
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
    })->setPermissions(['accounts']);

    $app->router("/accounts-add", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores, $accStore) {
        $vars['title'] = $jatbi->lang("Thêm Tài khoản");
        if ($app->method() === 'GET') {
            $vars['permissions'] = $app->select("permission", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A"]);
            $vars['data'] = [
                "status" => 'A',
                "permission" => '',
                "gender" => '',
                "avatar" => '',
            ];
            $vars['stores'] = $stores;
            echo $app->render($setting['template'] . '/users/accounts-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            if ($app->xss($_POST['name']) == '' || $app->xss($_POST['email']) == '' || $app->xss($_POST['account']) == '' || $app->xss($_POST['password']) == '' || $app->xss($_POST['status']) == '') {
                $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")];
            } elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                $error = ['status' => 'error', 'content' => $jatbi->lang('Email không đúng')];
            }
            if (empty($error)) {
                if(count($stores)>1){
                    $input_stores = isset($_POST['stores']) ? $_POST['stores'] : [];
                    $input_stores = array_map([$app, 'xss'], $input_stores); // lọc từng phần tử
                    if (empty($input_stores)) {
                        $input_stores = $app->select("stores","id",["id"=>$accStore,"status"=>'A',"deleted"=>0,"ORDER"=>["id"=>"ASC"]]);
                    }
                }
                else {
                    $input_stores = $app->select("stores","id",["id"=>$accStore,"status"=>'A',"deleted"=>0,"ORDER"=>["id"=>"ASC"]]);
                }
                $insert = [
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
                    "stores"=> $input_stores,
                    // "login"         => 'create',
                    "status" => $app->xss($_POST['status']),
                    // "lang"          => $_COOKIE['lang'] ?? 'vi',
                ];
                $app->insert("accounts", $insert);
                $getID = $app->id();
                $app->insert("settings", ["account" => $getID]);
                $directory = 'datas/' . $insert['active'];
                mkdir($directory, 0755, true);
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $imageUrl = $_FILES['avatar'];
                } else {
                    $imageUrl = 'datas/avatar/avatar' . rand(1, 10) . '.png';
                }
                $handle = $app->upload($imageUrl);
                $path_upload = $setting['uploads'] . '/' . $insert['active'] . '/images/';
                if (!is_dir($path_upload)) {
                    mkdir($path_upload, 0755, true);
                }
                $path_upload_thumb = $setting['uploads'] . '/' . $insert['active'] . '/images/thumb';
                if (!is_dir($path_upload_thumb)) {
                    mkdir($path_upload_thumb, 0755, true);
                }
                $newimages = $jatbi->active();
                if ($handle->uploaded) {
                    $handle->allowed = array('image/*');
                    $handle->file_new_name_body = $newimages;
                    $handle->Process($path_upload);
                    $handle->image_resize = true;
                    $handle->image_ratio_crop = true;
                    $handle->image_y = '200';
                    $handle->image_x = '200';
                    $handle->allowed = array('image/*');
                    $handle->file_new_name_body = $newimages;
                    $handle->Process($path_upload_thumb);
                }
                $insert_upload = [];
                if ($handle->processed) {
                    $getimage = 'upload/images/' . $newimages;
                    $data = [
                        "file_src_name" => $handle->file_src_name,
                        "file_src_name_body" => $handle->file_src_name_body,
                        "file_src_name_ext" => $handle->file_src_name_ext,
                        "file_src_pathname" => $handle->file_src_pathname,
                        "file_src_mime" => $handle->file_src_mime,
                        "file_src_size" => $handle->file_src_size,
                        "image_src_x" => $handle->image_src_x,
                        "image_src_y" => $handle->image_src_y,
                        "image_src_pixels" => $handle->image_src_pixels,
                    ];
                    $insert_upload = [
                        "account" => $getID,
                        "type" => "images",
                        "content" => $path_upload . $handle->file_dst_name,
                        "date" => date("Y-m-d H:i:s"),
                        "active" => $newimages,
                        "size" => $data['file_src_size'],
                        "data" => json_encode($data),
                    ];
                    $app->insert("uploads", $insert_upload);
                    $app->update("accounts", ["avatar" => $getimage], ["id" => $getID]);
                }
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công"), "test" => $imageUrl]);
                $jatbi->logs('accounts', 'accounts-add', [$insert, $insert_upload]);
            } else {
                echo json_encode($error);
            }
        }
    })->setPermissions(['accounts.add']);

    $app->router("/accounts-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores, $accStore) {
        $vars['title'] = $jatbi->lang("Sửa Tài khoản");
        if ($app->method() === 'GET') {
            $vars['permissions'] = $app->select("permission", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A"]);
            $vars['data'] = $app->get("accounts", "*", ["active" => $vars['id'], "deleted" => 0]);
            $vars['stores'] = $stores;
            if (!empty($vars['data']['stores'])) {
                $vars['data']['stores'] = unserialize($vars['data']['stores']);
            } else {
                $vars['data']['stores'] = [];
            }
            if ($vars['data'] > 1) {
                echo $app->render($setting['template'] . '/users/accounts-post.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $data = $app->get("accounts", "*", ["active" => $vars['id'], "deleted" => 0]);
            if ($data > 1) {
                if ($app->xss($_POST['name']) == '' || $app->xss($_POST['email']) == '' || $app->xss($_POST['account']) == '' || $app->xss($_POST['status']) == '') {
                    $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")];
                } elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                    $error = ['status' => 'error', 'content' => $jatbi->lang('Email không đúng')];
                }
                if (empty($error)) {
                    if(count($stores)>1){
                        $input_stores = isset($_POST['stores']) ? $_POST['stores'] : [];
                        $input_stores = array_map([$app, 'xss'], $input_stores); // lọc từng phần tử
                        if (empty($input_stores)) {
                            $input_stores = $app->select("stores","id",["id"=>$accStore,"status"=>'A',"deleted"=>0,"ORDER"=>["id"=>"ASC"]]);
                        }
                    }
                    else {
                        $input_stores = $app->select("stores","id",["id"=>$accStore,"status"=>'A',"deleted"=>0,"ORDER"=>["id"=>"ASC"]]);
                    }
                    $insert = [
                        "type" => 0,
                        "name" => $app->xss($_POST['name']),
                        "account" => $app->xss($_POST['account']),
                        "email" => $app->xss($_POST['email']),
                        "permission" => $app->xss($_POST['permission']),
                        "phone" => $app->xss($_POST['phone']),
                        "gender" => $app->xss($_POST['gender']),
                        "birthday" => $app->xss($_POST['birthday']),
                        "password" => ($_POST['password'] == '' ? $data['password'] : password_hash($app->xss($_POST['password']), PASSWORD_DEFAULT)),
                        "active" => $data['active'],
                        "date" => date('Y-m-d H:i:s'),
                        "status" => $app->xss($_POST['status']),
                        "stores"=> $input_stores,
                        // "lang" => $data['lang'] ?? 'vi',
                    ];
                    $app->update("accounts", $insert, ["id" => $data['id']]);
                    $getID = $data['id'];
                    if ($_FILES['avatar']) {
                        $imageUrl = $_FILES['avatar'];
                        $handle = $app->upload($imageUrl);
                        $path_upload = $setting['uploads'] . '/' . $insert['active'] . '/images/';
                        if (!is_dir($path_upload)) {
                            mkdir($path_upload, 0755, true);
                        }
                        $path_upload_thumb = $setting['uploads'] . '/' . $insert['active'] . '/images/thumb';
                        if (!is_dir($path_upload_thumb)) {
                            mkdir($path_upload_thumb, 0755, true);
                        }
                        $newimages = $jatbi->active();
                        if ($handle->uploaded) {
                            $handle->allowed = array('image/*');
                            $handle->file_new_name_body = $newimages;
                            $handle->Process($path_upload);
                            $handle->image_resize = true;
                            $handle->image_ratio_crop = true;
                            $handle->image_y = '200';
                            $handle->image_x = '200';
                            $handle->allowed = array('image/*');
                            $handle->file_new_name_body = $newimages;
                            $handle->Process($path_upload_thumb);
                        }
                        $insert_upload = [];
                        if ($handle->processed) {
                            $getimage = 'upload/images/' . $newimages;
                            $imgdata = [
                                "file_src_name" => $handle->file_src_name,
                                "file_src_name_body" => $handle->file_src_name_body,
                                "file_src_name_ext" => $handle->file_src_name_ext,
                                "file_src_pathname" => $handle->file_src_pathname,
                                "file_src_mime" => $handle->file_src_mime,
                                "file_src_size" => $handle->file_src_size,
                                "image_src_x" => $handle->image_src_x,
                                "image_src_y" => $handle->image_src_y,
                                "image_src_pixels" => $handle->image_src_pixels,
                            ];
                            $insert_upload = [
                                "account" => $getID,
                                "type" => "images",
                                "content" => $path_upload . $handle->file_dst_name,
                                "date" => date("Y-m-d H:i:s"),
                                "active" => $newimages,
                                "size" => $imgdata['file_src_size'],
                                "data" => json_encode($imgdata),
                            ];
                            $app->insert("uploads", $insert_upload);
                            $app->update("accounts", ["avatar" => $getimage], ["id" => $data['id']]);
                        }
                    }
                    echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
                    $jatbi->logs('accounts', 'accounts-edit', [$insert, $insert_upload]);
                } else {
                    echo json_encode($error);
                }
            } else {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
            }
        }
    })->setPermissions(['accounts.edit']);

    $app->router("/accounts-status/{id}", 'POST', function ($vars) use ($app, $jatbi, $setting) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $data = $app->get("accounts", "*", ["active" => $vars['id'], "deleted" => 0]);
        if ($data > 1) {
            if ($data > 1) {
                if ($data['status'] === 'A') {
                    $status = "D";
                } elseif ($data['status'] === 'D') {
                    $status = "A";
                }
                $app->update("accounts", ["status" => $status], ["id" => $data['id']]);
                $jatbi->logs('accounts', 'accounts-status', $data);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại"),]);
            }
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['accounts.edit']);

    $app->router("/accounts-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa Tài khoản");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("accounts", "*", ["active" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("accounts", ["deleted" => 1], ["id" => $data['id']]);
                    $name[] = $data['name'];
                }
                $jatbi->logs('accounts', 'accounts-deleted', $datas);
                $jatbi->trash('/users/accounts-restore', "Tài khoản: " . implode(', ', $name), ["database" => 'accounts', "data" => $boxid]);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['accounts.deleted']);

    $app->router("/accounts-restore/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
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
                    $app->update("accounts", ["deleted" => 0], ["active" => $active]);
                }
                $app->delete("trashs", ["id" => $trash['id']]);
                $jatbi->logs('accounts', 'accounts-restore', $datas);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['accounts.deleted']);

    $app->router("/permission", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Nhóm quyền");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/users/permission.html', $vars);
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
                    ],
                    "status[<>]" => $status,
                    "deleted" => 0,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)]
            ];
            $count = $app->count("permission", ["AND" => $where['AND']]);
            $app->select("permission", "*", $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "name" => $data['name'],
                    "status" => $app->component("status", ["url" => "/users/permission-status/" . $data['id'], "data" => $data['status'], "permission" => ['permission.edit']]),
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'action' => ['data-url' => '/users/permission-edit/' . $data['id'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'action' => ['data-url' => '/users/permission-deleted?box=' . $data['id'], 'data-action' => 'modal']
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
    })->setPermissions(['permission']);

    $app->router("/permission-add", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Thêm Nhóm Quyền");
        if ($app->method() === 'GET') {
            $vars['permissions'] = $app->getValueData('permission');
            $vars['data'] = [
                "status" => 'A',
            ];
            echo $app->render($setting['template'] . '/users/permission-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            if ($app->xss($_POST['name']) == '' || $app->xss($_POST['status']) == '') {
                $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")];
            }
            if (empty($error)) {
                $PostPermission = $_POST['permissions'] ?? [];
                $selectPermission = [];
                foreach ($PostPermission as $key => $per) {
                    $selectPermission[$key] = $per;
                }
                $insert = [
                    "name" => $app->xss($_POST['name']),
                    "status" => $app->xss($_POST['status']),
                    // "active" => $jatbi->active(),
                    "group" => serialize($selectPermission),
                    "deleted" => 0,
                ];
                $app->insert("permission", $insert);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
                $jatbi->logs('permission', 'permission-add', $insert);
            } else {
                echo json_encode($error);
            }
        }
    })->setPermissions(['permission.add']);

    $app->router("/permission-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Sửa Nhóm Quyền");
        if ($app->method() === 'GET') {
            $vars['data'] = $app->get("permission", "*", ["id" => $vars['id'], "deleted" => 0]);
            if ($vars['data'] > 1) {
                $vars['permissions'] = $app->getValueData('permission');
                $vars['checkper'] = unserialize($vars['data']['group'] ?? '') ?: [];
                echo $app->render($setting['template'] . '/users/permission-post.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $data = $app->get("permission", "*", ["id" => $vars['id'], "deleted" => 0]);
            if ($data > 1) {
                $PostPermission = $_POST['permissions'] ?? [];
                if ($app->xss($_POST['name']) == '' || $app->xss($_POST['status']) == '') {
                    $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")];
                }
                if (empty($error)) {
                    $selectPermission = [];
                    foreach ($PostPermission as $key => $per) {
                        $selectPermission[$key] = $per;
                    }
                    $insert = [
                        "name" => $app->xss($_POST['name']),
                        "status" => $app->xss($_POST['status']),
                        "group" => serialize($selectPermission),
                    ];
                    $app->update("permission", $insert, ["id" => $data['id']]);
                    echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công"), 'data' => $data, "insert" => $insert]);
                    $jatbi->logs('permission', 'permission-edit', [$data, $insert]);
                } else {
                    echo json_encode($error);
                }
            } else {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
            }
        }
    });

    $app->router("/permission-status/{id}", 'POST', function ($vars) use ($app, $jatbi, $setting) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $data = $app->get("permissions", "*", ["active" => $vars['id'], "deleted" => 0]);
        if ($data > 1) {
            if ($data > 1) {
                if ($data['status'] === 'A') {
                    $status = "D";
                } elseif ($data['status'] === 'D') {
                    $status = "A";
                }
                $app->update("permissions", ["status" => $status], ["id" => $data['id']]);
                $jatbi->logs('permission', 'permission-status', $data);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại"),]);
            }
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['permission.edit']);

    $app->router("/permission-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa Tài khoản");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $getBox = $_GET['box'] ?? [];
            if (is_array($getBox)) {
                $boxid = $getBox;
            } else {
                $boxid = explode(',', $getBox);
            }
            $datas = $app->select("permissions", "*", ["active" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("permissions", ["deleted" => 1], ["id" => $data['id']]);
                    $name[] = $data['name'];
                }
                $jatbi->logs('permission', 'permission-deleted', $datas);
                $jatbi->trash('/users/permission-restore', "Nhóm quyền: " . implode(', ', $name), ["database" => 'permission', "data" => $boxid]);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['permission.deleted']);

    $app->router("/permission-restore/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
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
                    $app->update("permissions", ["deleted" => 0], ["active" => $active]);
                }
                $app->delete("trashs", ["id" => $trash['id']]);
                $jatbi->logs('permission', 'permission-restore', $datas);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['permission.deleted']);


    $app->router('/accounts-partner-add', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $accStore) {
        $vars['title'] = $jatbi->lang("Thêm tài khoản đối tác");

        if ($app->method() === 'GET') {
            $vars['stores'] = $app->select("stores", ["id(value)", "name(text)"], ["deleted" => 0, "status" => 'A']);
            echo $app->render($setting['template'] . '/users/partner-add.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $post = array_map([$app, 'xss'], $_POST);

            // --- VALIDATION ---
            if (empty($post['name']) || empty($post['account'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Vui lòng điền đủ thông tin bắt buộc')]);
                return;
            }
            if ($app->has("accounts", ["account" => $post['account'], "deleted" => 0])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Tài khoản đã có người sử dụng')]);
                return;
            }

            // --- XỬ LÝ ẢNH ĐẠI DIỆN ---
            $avatar_name = '';
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                // ... Logic xử lý upload ảnh của bạn ...
            } else {
                $avatars = ['avatar.png', 'avatar1.png', 'avatar2.png', 'avatar3.png', 'avatar4.png', 'avatar5.png', 'avatar6.png'];
                $avatar_name = $avatars[array_rand($avatars)];
            }

            $stores_json = $app->getCookie('stores') ?? json_encode([]);
            $stores_from_cookie = json_decode($stores_json, true);

            $session = $app->getSession("accounts");
            $final_store_ids = [];

            if (isset($session['id'])) {
                $account = $app->get("accounts", "stores", ["id" => $session['id']]);
                if (empty($account['stores'])) {
                    // $final_store_ids[] = "0";
                }
            }

            if (is_array($stores_from_cookie)) {
                foreach ($stores_from_cookie as $itemStore) {
                    if (isset($itemStore['value'])) {
                        $final_store_ids[] = $itemStore['value'];
                    }
                }
            }
            // Lấy các giá trị duy nhất và đảm bảo tất cả là chuỗi
            $input_stores = array_map('strval', array_values(array_unique($final_store_ids)));
            // --- CHUẨN BỊ DỮ LIỆU VÀ INSERT ---
            $insert = [
                "name" => $post['name'],
                "account" => $post['account'],
                "email" => $post['email'],
                "phone" => $post['phone'],
                "password" => password_hash($post['password'], PASSWORD_DEFAULT),
                "permission" => 0,
                "type" => 10,
                "active" => $jatbi->active(32),
                "avatar" => $avatar_name,
                "birthday" => empty($post['birthday']) ? null : $post['birthday'],
                "gender" => $post['gender'],
                "date" => date('Y-m-d H:i:s'),
                "status" => $post['status'] ?? 'A',
                "stores" => serialize($input_stores),
            ];

            $app->insert("accounts", $insert);
            $jatbi->logs('accounts', 'add_partner', $insert);

            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Thêm mới thành công')]);
        }
    })->setPermissions(['accounts-partner.add']);

    

    $app->router('/accounts-partner-edit/{id}', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $jatbi->permission('accounts-partner.edit');
        $id = $vars['id'] ?? 0;

        $data = $app->get("accounts", "*", ["id" => $id, "deleted" => 0]);
        if (!$data) {
            echo $app->render($setting['template'] . '/error.html', ['content' => 'Tài khoản không tồn tại']);
            return;
        }

        $vars['data'] = $data;
        $vars['title'] = $jatbi->lang("Sửa tài khoản đối tác");

        if ($app->method() === 'GET') {
            $vars['data']['stores'] = unserialize($data['stores']);
            $vars['stores'] = $app->select("stores", ["id(value)", "name(text)"], ["deleted" => 0, "status" => 'A']);
            echo $app->render($setting['template'] . '/users/partner-add.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $post = array_map([$app, 'xss'], $_POST);

            if (empty($post['name']) || empty($post['account'])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Vui lòng điền đủ thông tin bắt buộc')]);
                return;
            }
            if ($post['account'] != $data['account'] && $app->has("accounts", ["account" => $post['account'], "deleted" => 0])) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang('Tài khoản đã có người sử dụng')]);
                return;
            }

            $avatar_name = $data['avatar'];
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                // Logic xử lý upload ảnh của bạn
            }

            $input_stores = $post['stores'] ?? [];
            if (!is_array($input_stores)) {
                $input_stores = [$input_stores];
            }
            $stores_to_save = array_map('strval', $input_stores);

            $update = [
                "name" => $post['name'],
                "account" => $post['account'],
                "email" => $post['email'],
                "phone" => $post['phone'],
                "avatar" => $avatar_name,
                "birthday" => empty($post['birthday']) ? null : $post['birthday'],
                "gender" => $post['gender'],
                "status" => $post['status'] ?? 'A',
                "stores" => serialize($stores_to_save),
            ];

            if (!empty($post['password'])) {
                $update['password'] = password_hash($post['password'], PASSWORD_DEFAULT);
            }

            $app->update("accounts", $update, ["id" => $id]);
            $jatbi->logs('accounts', 'edit_partner', $update);

            echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công'), 'url' => 'auto']);
        }
    })->setPermissions(['accounts-partner.edit']);

})->middleware('login');




?>