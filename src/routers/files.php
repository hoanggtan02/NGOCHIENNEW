<?php
if (!defined('ECLO')) die("Hacking attempt");

use ECLO\App;

$app->group($setting['manager'] . "/files", function ($vars) use ($app, $jatbi, $common, $setting) {
    $account_id = $app->getSession("accounts")['id'] ?? null;
    $account = $account_id ? $app->get("accounts", "*", ["id" => $account_id]) : [];

    $app->router("", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $account) {
        $vars['title'] = $jatbi->lang("Dữ liệu");
        if ($app->method() === 'GET') {
            $vars['folders'] = $app->select("files_folders", "*", [
                "account" => $account['id'],
                "deleted" => 0,
                "main" => 0,
                "ORDER" => ["date" => "DESC"]
            ]);
            $vars['totalStorage'] = $jatbi->totalStorage();
            $vars['account'] = $account;
            echo $app->render($setting['template'] . '/files/files.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'date';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $status = isset($_POST['status']) ? [$_POST['status'], $_POST['status']] : '';
            $where = [
                "AND" => [
                    "OR" => [
                        "name[~]" => $searchValue,
                        "active[~]" => $searchValue,
                        "extension[~]" => $searchValue,
                    ],
                    "category" => 0,
                    "account" => $account['id'],
                    "deleted" => 0,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)]
            ];
            $count = $app->count("files", ["AND" => $where['AND']]);
            $app->select("files", "*", $where, function ($data) use (&$datas, $jatbi, $app, $account) {
                $star = $app->count("files_stars", "id", ["data" => $data['id'], "type" => 'files', "deleted" => 0, "account" => $account['id']]);
                $permission = $data['permission'] == 0 ? '' : '<i class="ti ti-users-group me-1 fs-6 text-danger"></i>';
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['active']]),
                    "name" => '<div class="d-flex align-items-center position-relative"><span class="file-icon me-2 rounded-3 lazyload" data-bgset="' . $jatbi->getFileIcon($data['active']) . '" style="--width:30px;--height:30px;"></span>' . $permission . $data['name'] . '<a data-url="/files/files-views/' . $data['active'] . '" data-action="modal" class="stretched-link"></a></div>',
                    "size" => $jatbi->formatFileSize($data['size']),
                    "modify" => $data['modify'],
                    "date" => $jatbi->datetime($data['date']),
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xem"),
                                'icon' => '<i class="ti ti-eye me-2"></i>',
                                'action' => ['data-url' => '/files/files-views/' . $data['active'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'link',
                                'name' => $jatbi->lang("Tải về"),
                                'icon' => '<i class="ti ti-download me-2"></i>',
                                'action' => ['href' => '/files/files-download/' . $data['active'], 'download' => 'true']
                            ],
                            [
                                'type' => 'divider',
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Đổi tên"),
                                'icon' => '<i class="ti ti-writing-sign me-2"></i>',
                                'action' => ['data-url' => '/files/files-name/' . $data['active'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Di chuyển"),
                                'icon' => '<i class="ti ti-folder-symlink me-2"></i>',
                                'action' => ['data-url' => '/files/files-move/' . $data['active'], 'data-action' => 'modal', 'data-pjax-history' => 'false']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sao chép"),
                                'icon' => '<i class="ti ti-copy me-2"></i>',
                                'action' => ['data-url' => '/files/files-copy/' . $data['active'], 'data-action' => 'modal', 'data-pjax-history' => 'false']
                            ],
                            [
                                'type' => 'divider',
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Đánh Sao"),
                                'icon' => '<i class="ti ti-star' . ($star > 0 ? '-filled text-warning' : '') . ' me-2"></i>',
                                'action' => ['data-url' => '/files/files-star/' . $data['active'], 'data-action' => 'click', 'data-alert' => 'true', 'data-load' => 'this']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Chia sẽ"),
                                'icon' => '<i class="ti ti-user-share me-2"></i>',
                                'action' => ['data-url' => '/files/files-share/' . $data['active'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Thông tin"),
                                'icon' => '<i class="ti ti-info-circle me-2"></i>',
                                'action' => ['data-url' => '/files/files-infomation/' . $data['active'], 'data-action' => 'offcanvas']
                            ],
                            [
                                'type' => 'divider',
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'icon' => '<i class="ti ti-trash me-2"></i>',
                                'action' => ['data-url' => '/files/files-deleted?box=' . $data['active'], 'data-action' => 'modal']
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
    });

    $app->router("/assets", 'GET', function ($vars) use ($app, $jatbi, $setting, $account) {
        $vars['title'] = $jatbi->lang("Dữ liệu");

        $getFolder = $app->xss($_GET['folder'] ?? 0);
        $getType = $app->xss($_GET['type'] ?? '');
        if ($getFolder != 0) {
            $getFolder = $app->get("files_folders", ["id", "name", "active"], [
                "active" => $getFolder,
                "account" => $account['id'],
                "deleted" => 0
            ]) ?? 0;
            $vars['subs'] = $jatbi->folders($getFolder['id']);
        }
        $vars['folders'] = $app->select("files_folders", "*", [
            "account" => $account['id'],
            "deleted" => 0,
            "main" => $getFolder['id'] ?? 0,
            "ORDER" => ["date" => "DESC"]
        ]);
        if ($getFolder == 0) {
            $getFolder = ["id" => 0, "name" => $jatbi->lang("Mục chính"), "active" => 'main'];
        }
        $vars['getfolder'] = $getFolder;
        if (!empty($vars['subs'])) {
            $lastKey = array_key_last($vars['subs']);
            $previousKey = $lastKey - 1;
            if (isset($vars['subs'][$previousKey])) {
                $vars['back'] = $vars['subs'][$previousKey]['active'];
            } else {
                $vars['back'] = '';
            }
        } else {
            $vars['back'] = '';
        }
        $where = [
            "AND" => [
                "category" => $getFolder['id'] ?? 0,
                "deleted" => 0,
            ],
            "ORDER" => ['date' => "DESC"]
        ];
        if ($getType) {
            $where['AND']['mime'] = $jatbi->searchFiles($getType);
        }
        $count = $app->count("files", ["AND" => $where['AND']]);
        $app->select("files", "*", $where, function ($data) use (&$datas, $jatbi, $account) {
            $datas[] = [
                "name" => $data['name'],
                "active" => $data['active'],
                "files" => $jatbi->getFileIcon($data['active']),
            ];
        });
        $vars['datas'] = $datas ?? [];
        $url = $_SERVER['REQUEST_URI'];
        $parts = parse_url($url);
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        unset($query['folder']);
        $newQuery = http_build_query($query);
        $vars['url'] = $jatbi->url($parts['path'] . ($newQuery ? '?' . $newQuery . '&' : '?'));
        echo $app->render($setting['template'] . '/files/files-assets.html', $vars, $jatbi->ajax());
    });

    $app->router("/folder/{active}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $account) {
        $vars['title'] = $jatbi->lang("Dữ liệu");
        if ($app->method() === 'GET') {
            $vars['account'] = $account;
            $vars['data'] = $app->get("files_folders", "*", [
                "active" => $app->xss($vars['active']),
                "deleted" => 0,
            ]);
            $viewer = $jatbi->checkFolder($vars['data']['active']);
            if ($viewer) {
                $vars['subs'] = $jatbi->folders($vars['data']['id']);
                $vars['name'] = $vars['data']['name'];
                $vars['folders'] = $app->select("files_folders", "*", [
                    "deleted" => 0,
                    "main" => $vars['data']['id']
                ]);
                if ($vars['account']['id'] == $vars['data']['account']) {
                    $app->update("files_folders", ["modify" => date("Y-m-d H:i:s")], ["id" => $vars['data']['id']]);
                }
                $vars['totalStorage'] = $jatbi->totalStorage();
                echo $app->render($setting['template'] . '/files/files.html', $vars);
            } else {
                echo $app->render($setting['template'] . '/error.html', $vars);
            }
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);

            $folder = $app->get("files_folders", "*", [
                "active" => $app->xss($vars['active']),
                "deleted" => 0,
            ]);
            $viewer = $jatbi->checkFolder($folder['active']);
            if ($viewer) {
                $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
                $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
                $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
                $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
                $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'date';
                $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
                $status = isset($_POST['status']) ? [$_POST['status'], $_POST['status']] : '';
                $where = [
                    "AND" => [
                        "OR" => [
                            "name[~]" => $searchValue,
                            "active[~]" => $searchValue,
                            "extension[~]" => $searchValue,
                        ],
                        "category" => $folder['id'],
                        "deleted" => 0,
                    ],
                    "LIMIT" => [$start, $length],
                    "ORDER" => [$orderName => strtoupper($orderDir)]
                ];
                $count = $app->count("files", ["AND" => $where['AND']]);
                $app->select("files", "*", $where, function ($data) use (&$datas, $jatbi, $app, $account) {
                    $star = $app->count("files_stars", "id", ["data" => $data['id'], "type" => 'files', "deleted" => 0, "account" => $account['id']]);
                    $hidden = $account['id'] != $data['account'] ? false : true;
                    $permission = $data['permission'] == 0 ? '' : '<i class="ti ti-users-group me-1 fs-6 text-danger"></i>';
                    $datas[] = [
                        "checkbox" => $app->component("box", ["data" => $data['active']]),
                        "name" => '<div class="d-flex align-items-center position-relative"><span class="file-icon me-2 rounded-3 lazyload" data-bgset="' . $jatbi->getFileIcon($data['active']) . '" style="--width:30px;--height:30px;"></span>' . $permission . $data['name'] . '<a data-url="/files/files-views/' . $data['active'] . '" data-action="modal" class="stretched-link"></a></div>',
                        "size" => $jatbi->formatFileSize($data['size']),
                        "modify" => $data['modify'],
                        "date" => $jatbi->datetime($data['date']),
                        "action" => $app->component("action", [
                            "button" => [
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Xem"),
                                    'icon' => '<i class="ti ti-eye me-2"></i>',
                                    'action' => ['data-url' => '/files/files-views/' . $data['active'], 'data-action' => 'modal']
                                ],
                                [
                                    'type' => 'link',
                                    'name' => $jatbi->lang("Tải về"),
                                    'icon' => '<i class="ti ti-download me-2"></i>',
                                    'action' => ['href' => '/files/files-download/' . $data['active'], 'download' => 'true']
                                ],
                                [
                                    'type' => 'divider',
                                ],
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Đổi tên"),
                                    'icon' => '<i class="ti ti-writing-sign me-2"></i>',
                                    'hidden' => $hidden,
                                    'action' => ['data-url' => '/files/files-name/' . $data['active'], 'data-action' => 'modal']
                                ],
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Di chuyển"),
                                    'icon' => '<i class="ti ti-folder-symlink me-2"></i>',
                                    'hidden' => $hidden,
                                    'action' => ['data-url' => '/files/files-move/' . $data['active'], 'data-action' => 'modal', 'data-pjax-history' => 'false']
                                ],
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Sao chép"),
                                    'icon' => '<i class="ti ti-copy me-2"></i>',
                                    'action' => ['data-url' => '/files/files-copy/' . $data['active'], 'data-action' => 'modal', 'data-pjax-history' => 'false']
                                ],
                                [
                                    'type' => 'divider',
                                ],
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Đánh Sao"),
                                    'icon' => '<i class="ti ti-star' . ($star > 0 ? '-filled text-warning' : '') . ' me-2"></i>',
                                    'action' => ['data-url' => '/files/files-star/' . $data['active'], 'data-action' => 'click', 'data-alert' => 'true', 'data-load' => 'this']
                                ],
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Chia sẽ"),
                                    'icon' => '<i class="ti ti-user-share me-2"></i>',
                                    'hidden' => $hidden,
                                    'action' => ['data-url' => '/files/files-share/' . $data['active'], 'data-action' => 'modal']
                                ],
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Thông tin"),
                                    'icon' => '<i class="ti ti-info-circle me-2"></i>',
                                    'action' => ['data-url' => '/files/files-infomation/' . $data['active'], 'data-action' => 'offcanvas']
                                ],
                                [
                                    'type' => 'divider',
                                ],
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Xóa"),
                                    'icon' => '<i class="ti ti-trash me-2"></i>',
                                    'action' => ['data-url' => '/files/files-deleted?box=' . $data['active'], 'data-action' => 'modal']
                                ],
                            ]
                        ]),
                    ];
                });
            }
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas ?? []
            ]);
        }
    });

    $app->router("/shared-with-me", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $account) {
        $vars['title'] = $jatbi->lang("Chia sẽ với tôi");
        if ($app->method() === 'GET') {
            $vars['account'] = $account;
            $vars['folders'] = $app->select("files_folders", [
                "[>]files_accounts" => ["id" => "data"],
                "[>]files_shares" => ["id" => "data"]
            ], [
                "files_folders.active",
                "files_folders.name",
                "files_folders.id",
                "files_folders.permission",
                "files_folders.account",
                "files_folders.deleted",
                "files_folders.date",
                "total_accounts" => App::raw("COUNT(DISTINCT files_accounts.account)"),
                "is_visible" => App::raw("
                        CASE 
                            -- Nếu thư mục bị xóa hoặc chia sẻ bị xóa thì ẩn
                            WHEN files_folders.deleted = 1 THEN 0
                            WHEN files_shares.deleted = 1 THEN 0

                            -- Nếu permission = 1, nhưng có files_accounts với deleted = 1, thì xem như permission = 3 (bị loại)
                            WHEN files_folders.permission = 1 
                                AND EXISTS (
                                    SELECT 1 FROM files_accounts fa 
                                    WHERE fa.data = files_folders.id 
                                    AND fa.account = " . $app->quote($vars['account']['id']) . " 
                                    AND fa.deleted = 0
                                    ORDER BY fa.id DESC LIMIT 1
                                ) THEN 0

                            -- Nếu permission = 2, chỉ xem được nếu có files_accounts với deleted = 0
                            WHEN files_folders.permission = 2 
                                AND EXISTS (
                                    SELECT 1 FROM files_accounts fa 
                                    WHERE fa.data = files_folders.id 
                                    AND fa.account = " . $app->quote($vars['account']['id']) . " 
                                    AND fa.deleted = 0
                                    ORDER BY fa.id DESC LIMIT 1
                                ) THEN 1

                            -- Nếu permission = 2 nhưng không có files_accounts hoặc đã bị xóa thì không xem được
                            WHEN files_folders.permission = 2 THEN 0

                            -- Nếu permission = 1 và không bị loại bỏ thì vẫn xem được
                            WHEN files_folders.permission = 1 THEN 1

                            ELSE 0
                        END
                    ")
            ], [
                "files_folders.deleted" => 0,
                "files_shares.deleted" => 0,
                "files_folders.account[!]" => $vars['account']['id'],
                "GROUP" => "files_folders.id",
                "HAVING" => ["is_visible" => 1],
                "ORDER" => ["files_folders.date" => "DESC"]
            ]);
            $vars['totalStorage'] = $jatbi->totalStorage();
            echo $app->render($setting['template'] . '/files/files.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'files.id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $status = isset($_POST['status']) ? [$_POST['status'], $_POST['status']] : '';
            $count = $app->count("files", [
                "[>]files_accounts" => ["id" => "data"],
                "[>]files_shares" => ["id" => "data"]
            ], [
                "files.id",
                "total_accounts" => App::raw("COUNT(DISTINCT files_accounts.account)"),
                "is_visible" => App::raw("
                        CASE 
                            -- Nếu file bị xóa hoặc chia sẻ bị xóa thì ẩn
                            WHEN files.deleted = 1 THEN 0
                            WHEN files_shares.deleted = 1 THEN 0

                            -- Nếu permission = 1 nhưng có files_accounts.deleted = 1, xem như bị loại
                            WHEN files.permission = 1 
                                AND EXISTS (
                                    SELECT 1 FROM files_accounts fa 
                                    WHERE fa.data = files.id 
                                    AND fa.account = " . $app->quote($account['id']) . " 
                                    AND fa.deleted = 0
                                    AND fa.type = 'files'
                                    ORDER BY fa.date DESC LIMIT 1
                                ) THEN 0

                            -- Nếu permission = 2, chỉ được xem nếu có files_accounts với deleted = 0
                            WHEN files.permission = 2 
                                AND EXISTS (
                                    SELECT 1 FROM files_accounts fa 
                                    WHERE fa.data = files.id 
                                    AND fa.account = " . $app->quote($account['id']) . " 
                                    AND fa.deleted = 0
                                    AND fa.type = 'files'
                                    ORDER BY fa.date DESC LIMIT 1
                                ) THEN 1
                            
                            -- Nếu permission = 1 và không bị loại bỏ thì vẫn xem được
                            WHEN files.permission = 1 THEN 1

                            -- Nếu files_shares có permission 1 thì vẫn xem được
                            WHEN files_shares.permission = 1 
                                AND files_shares.deleted = 0 THEN 1

                            ELSE 0
                        END
                    ")
            ], [
                "files.deleted" => 0,
                "OR" => [
                    "files_accounts.type" => 'files',
                    "files_shares.permission" => 1
                ],
                "OR" => [
                    "files.name[~]" => $searchValue,
                    "files.active[~]" => $searchValue,
                    "files.extension[~]" => $searchValue,
                ],
                "files_shares.type" => 'files',
                "files.account[!]" => $account['id'],
                "GROUP" => "files.id",
                "HAVING" => ["is_visible" => 1],
            ]);

            $app->select("files", [
                "[>]files_accounts" => ["id" => "data"],
                "[>]files_shares" => ["id" => "data"]
            ], [
                "files.id",
                "files.active",
                "files.name",
                "files.date",
                "files.extension",
                "files.permission",
                "files.modify",
                "files.size",
                "files_shares.active (share_active)",
                "total_accounts" => App::raw("COUNT(DISTINCT files_accounts.account)"),
                "is_visible" => App::raw("
                        CASE 
                            WHEN files.deleted = 1 THEN 0
                            WHEN files_shares.deleted = 1 THEN 0
                            WHEN files.permission = 1 
                                AND EXISTS (
                                    SELECT 1 FROM files_accounts fa 
                                    WHERE fa.data = files.id 
                                    AND fa.account = " . $app->quote($account['id']) . " 
                                    AND fa.deleted = 0
                                    AND fa.type = 'files'
                                    ORDER BY fa.date DESC LIMIT 1
                                ) THEN 0
                            WHEN files.permission = 2 
                                AND EXISTS (
                                    SELECT 1 FROM files_accounts fa 
                                    WHERE fa.data = files.id 
                                    AND fa.account = " . $app->quote($account['id']) . " 
                                    AND fa.deleted = 0
                                    AND fa.type = 'files'
                                    ORDER BY fa.date DESC LIMIT 1
                                ) THEN 1

                            WHEN files.permission = 1 THEN 1
                            WHEN files_shares.permission = 1 
                                AND files_shares.deleted = 0 THEN 1

                            ELSE 0
                        END
                    ")
            ], [
                "files.deleted" => 0,
                "OR" => [
                    "files_accounts.type" => 'files',
                    "files_shares.permission" => 1
                ],
                "OR" => [
                    "files.name[~]" => $searchValue,
                    "files.active[~]" => $searchValue,
                    "files.extension[~]" => $searchValue,
                ],
                "files_shares.type" => 'files',
                "files.account[!]" => $account['id'],
                "GROUP" => "files.id",
                "HAVING" => ["is_visible" => 1],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)]
            ], function ($data) use (&$datas, $jatbi, $app, $account) {
                $star = $app->count("files_stars", "id", [
                    "data" => $data['id'],
                    "type" => 'files',
                    "deleted" => 0,
                    "account" => $account['id']
                ]);
                $permission = $data['permission'] == 0 ? '' : '<i class="ti ti-users-group me-1 fs-6 text-danger"></i>';
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['active']]),
                    "name" => '<div class="d-flex align-items-center position-relative"><span class="file-icon me-2 rounded-3 lazyload" data-bgset="' . $jatbi->getFileIcon($data['active']) . '" style="--width:30px;--height:30px;"></span>' . $permission . $data['name'] . '<a data-url="/files/files-views/' . $data['active'] . '" data-action="modal" class="stretched-link"></a></div>',
                    "size" => $jatbi->formatFileSize($data['size']),
                    "modify" => $data['modify'],
                    "date" => $jatbi->datetime($data['date']),
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xem"),
                                'icon' => '<i class="ti ti-eye me-2"></i>',
                                'action' => ['data-url' => '/files/files-views/' . $data['active'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'link',
                                'name' => $jatbi->lang("Tải về"),
                                'icon' => '<i class="ti ti-download me-2"></i>',
                                'action' => ['href' => '/files/files-download/' . $data['active'], 'download' => 'true']
                            ],
                            [
                                'type' => 'divider',
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sao chép"),
                                'icon' => '<i class="ti ti-copy me-2"></i>',
                                'action' => ['data-url' => '/files/files-copy/' . $data['active'], 'data-action' => 'modal', 'data-pjax-history' => 'false']
                            ],
                            [
                                'type' => 'divider',
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Đánh Sao"),
                                'icon' => '<i class="ti ti-star' . ($star > 0 ? '-filled text-warning' : '') . ' me-2"></i>',
                                'action' => [
                                    'data-url' => '/files/files-star/' . $data['active'],
                                    'data-action' => 'click',
                                    'data-alert' => 'true',
                                    'data-load' => 'this'
                                ]
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Thông tin"),
                                'icon' => '<i class="ti ti-info-circle me-2"></i>',
                                'action' => [
                                    'data-url' => '/files/files-infomation/' . $data['active'],
                                    'data-action' => 'offcanvas'
                                ]
                            ],
                            [
                                'type' => 'divider',
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Hủy"),
                                'icon' => '<i class="ti ti-trash me-2"></i>',
                                'action' => [
                                    'data-url' => '/files/user-share-deleted?box=' . $data['share_active'],
                                    'data-action' => 'modal'
                                ]
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
    });

    $app->router("/recent", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $account) {
        $vars['title'] = $jatbi->lang("Gần đây");
        if ($app->method() === 'GET') {
            $vars['account'] = $account;
            $vars['folders'] = $app->select("files_folders", "*", [
                "account" => $vars['account']['id'],
                "deleted" => 0,
                "LIMIT" => 8,
                "ORDER" => ["modify" => "DESC"]
            ]);
            $vars['totalStorage'] = $jatbi->totalStorage();
            echo $app->render($setting['template'] . '/files/files.html', $vars);
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
                        "active[~]" => $searchValue,
                        "extension[~]" => $searchValue,
                    ],
                    "category" => 0,
                    "account" => $account['id'],
                    "deleted" => 0,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => ["modify" => "DESC", $orderName => strtoupper($orderDir)]
            ];
            $count = $app->count("files", ["AND" => $where['AND']]);
            $app->select("files", "*", $where, function ($data) use (&$datas, $jatbi, $app, $account) {
                $star = $app->count("files_stars", "id", ["data" => $data['id'], "type" => 'files', "deleted" => 0, "account" => $account['id']]);
                $permission = $data['permission'] == 0 ? '' : '<i class="ti ti-users-group me-1 fs-6 text-danger"></i>';
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['active']]),
                    "name" => '<div class="d-flex align-items-center position-relative"><span class="file-icon me-2 rounded-3 lazyload" data-bgset="' . $jatbi->getFileIcon($data['active']) . '" style="--width:30px;--height:30px;"></span>' . $permission . $data['name'] . '<a data-url="/files/files-views/' . $data['active'] . '" data-action="modal" class="stretched-link"></a></div>',
                    "size" => $jatbi->formatFileSize($data['size']),
                    "modify" => $data['modify'],
                    "date" => $jatbi->datetime($data['date']),
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xem"),
                                'icon' => '<i class="ti ti-eye me-2"></i>',
                                'action' => ['data-url' => '/files/files-views/' . $data['active'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'link',
                                'name' => $jatbi->lang("Tải về"),
                                'icon' => '<i class="ti ti-download me-2"></i>',
                                'action' => ['href' => '/files/files-download/' . $data['active'], 'download' => 'true']
                            ],
                            [
                                'type' => 'divider',
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Đổi tên"),
                                'icon' => '<i class="ti ti-writing-sign me-2"></i>',
                                'action' => ['data-url' => '/files/files-name/' . $data['active'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Di chuyển"),
                                'icon' => '<i class="ti ti-folder-symlink me-2"></i>',
                                'action' => ['data-url' => '/files/files-move/' . $data['active'], 'data-action' => 'modal', 'data-pjax-history' => 'false']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sao chép"),
                                'icon' => '<i class="ti ti-copy me-2"></i>',
                                'action' => ['data-url' => '/files/files-copy/' . $data['active'], 'data-action' => 'modal', 'data-pjax-history' => 'false']
                            ],
                            [
                                'type' => 'divider',
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Đánh Sao"),
                                'icon' => '<i class="ti ti-star' . ($star > 0 ? '-filled text-warning' : '') . ' me-2"></i>',
                                'action' => ['data-url' => '/files/files-star/' . $data['active'], 'data-action' => 'click', 'data-alert' => 'true', 'data-load' => 'this']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Chia sẽ"),
                                'icon' => '<i class="ti ti-user-share me-2"></i>',
                                'action' => ['data-url' => '/files/files-share/' . $data['active'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Thông tin"),
                                'icon' => '<i class="ti ti-info-circle me-2"></i>',
                                'action' => ['data-url' => '/files/files-infomation/' . $data['active'], 'data-action' => 'offcanvas']
                            ],
                            [
                                'type' => 'divider',
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'icon' => '<i class="ti ti-trash me-2"></i>',
                                'action' => ['data-url' => '/files/files-deleted?box=' . $data['active'], 'data-action' => 'modal']
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
    });

    $app->router("/starred", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $account) {
        $vars['title'] = $jatbi->lang("Đánh sao");
        if ($app->method() === 'GET') {
            $vars['account'] = $account;
            $folderStar = $app->select("files_stars", "data", [
                "deleted" => 0,
                "type" => 'folder',
                "account" => $vars['account']['id'],
            ]);
            $vars['folders'] = $app->select("files_folders", [
                "[>]files_stars" => ["id" => "data"]
            ], [
                "files_folders.id",
                "files_folders.active",
                "files_folders.name",
                "files_folders.date",
                "files_folders.account",
                "files_folders.deleted",
                "files_folders.permission",
            ], [
                "files_stars.type" => 'folder',
                "files_stars.account" => $vars['account']['id'],
                "files_folders.deleted" => 0,
                "files_stars.deleted" => 0,
                "ORDER" => ["files_folders.date" => "DESC"]
            ]);
            $vars['totalStorage'] = $jatbi->totalStorage();
            echo $app->render($setting['template'] . '/files/files.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'files.id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $status = isset($_POST['status']) ? [$_POST['status'], $_POST['status']] : '';
            $where = [
                "AND" => [
                    "OR" => [
                        "files.name[~]" => $searchValue,
                        "files.active[~]" => $searchValue,
                        "files.extension[~]" => $searchValue,
                    ],
                    "files_stars.account" => $account['id'],
                    "files.deleted" => 0,
                    "files_stars.deleted" => 0,
                    "files_stars.type" => 'files',
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)]
            ];
            $count = $app->count("files", [
                "[>]files_stars" => ["id" => "data"]
            ], ["AND" => $where['AND']]);
            $app->select("files", [
                "[>]files_stars" => ["id" => "data"]
            ], [
                "files.id",
                "files.name",
                "files.account",
                "files.active",
                "files.size",
                "files.permission",
                "files.modify",
                "files.date",
                "files_stars.account (star_account)"
            ], $where, function ($data) use (&$datas, $jatbi, $app, $account) {
                $star = $app->count("files_stars", "id", ["data" => $data['id'], "type" => 'files', "deleted" => 0, "account" => $account['id']]);
                $hidden = $account['id'] != $data['account'] ? false : true;
                $permission = $data['permission'] == 0 ? '' : '<i class="ti ti-users-group me-1 fs-6 text-danger"></i>';
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['active']]),
                    "name" => '<div class="d-flex align-items-center position-relative"><span class="file-icon me-2 rounded-3 lazyload" data-bgset="' . $jatbi->getFileIcon($data['active']) . '" style="--width:30px;--height:30px;"></span>' . $permission . $data['name'] . '<a data-url="/files/files-views/' . $data['active'] . '" data-action="modal" class="stretched-link"></a></div>',
                    "size" => $jatbi->formatFileSize($data['size']),
                    "modify" => $data['modify'],
                    "date" => $jatbi->datetime($data['date']),
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xem"),
                                'icon' => '<i class="ti ti-eye me-2"></i>',
                                'action' => ['data-url' => '/files/files-views/' . $data['active'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'link',
                                'name' => $jatbi->lang("Tải về"),
                                'icon' => '<i class="ti ti-download me-2"></i>',
                                'action' => ['href' => '/files/files-download/' . $data['active'], 'download' => 'true']
                            ],
                            [
                                'type' => 'divider',
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Đổi tên"),
                                'icon' => '<i class="ti ti-writing-sign me-2"></i>',
                                'hidden' => $hidden,
                                'action' => ['data-url' => '/files/files-name/' . $data['active'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Di chuyển"),
                                'icon' => '<i class="ti ti-folder-symlink me-2"></i>',
                                'hidden' => $hidden,
                                'action' => ['data-url' => '/files/files-move/' . $data['active'], 'data-action' => 'modal', 'data-pjax-history' => 'false']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sao chép"),
                                'icon' => '<i class="ti ti-copy me-2"></i>',
                                'action' => ['data-url' => '/files/files-copy/' . $data['active'], 'data-action' => 'modal', 'data-pjax-history' => 'false']
                            ],
                            [
                                'type' => 'divider',
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Đánh Sao"),
                                'icon' => '<i class="ti ti-star' . ($star > 0 ? '-filled text-warning' : '') . ' me-2"></i>',
                                'action' => ['data-url' => '/files/files-star/' . $data['active'], 'data-action' => 'click', 'data-alert' => 'true', 'data-load' => 'this']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Chia sẽ"),
                                'icon' => '<i class="ti ti-user-share me-2"></i>',
                                'hidden' => $hidden,
                                'action' => ['data-url' => '/files/files-share/' . $data['active'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Thông tin"),
                                'icon' => '<i class="ti ti-info-circle me-2"></i>',
                                'action' => ['data-url' => '/files/files-infomation/' . $data['active'], 'data-action' => 'offcanvas']
                            ],
                            [
                                'type' => 'divider',
                            ],
                            [
                                'type' => 'button',
                                'name' => $account['id'] != $data['account'] ? $jatbi->lang("Hủy") : $jatbi->lang("Xóa"),
                                'icon' => '<i class="ti ti-trash me-2"></i>',
                                'action' => ['data-url' => '/files/files-deleted?box=' . $data['active'], 'data-action' => 'modal']
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
    });

    $app->router("/trash", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $account) {
        $vars['title'] = $jatbi->lang("Thùng rác");
        if ($app->method() === 'GET') {
            $vars['account'] = $account;
            $vars['folders'] = $app->select("files_folders", "*", [
                "account" => $vars['account']['id'],
                "deleted" => 1,
                "ORDER" => ["modify" => "DESC"]
            ]);
            $vars['totalStorage'] = $jatbi->totalStorage();
            echo $app->render($setting['template'] . '/files/files.html', $vars);
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
                        "active[~]" => $searchValue,
                        "extension[~]" => $searchValue,
                    ],
                    "category" => 0,
                    "account" => $account['id'],
                    "deleted" => 1,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)]
            ];
            $count = $app->count("files", ["AND" => $where['AND']]);
            $app->select("files", "*", $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['active']]),
                    "name" => '<div class="d-flex align-items-center"><span class="file-icon me-2 rounded-3 lazyload" data-bgset="' . $jatbi->getFileIcon($data['active']) . '" style="--width:30px;--height:30px;"></span>' . $data['name'] . '</div>',
                    "size" => $jatbi->formatFileSize($data['size']),
                    "date" => $jatbi->datetime($data['date']),
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Phục hồi"),
                                'icon' => '<i class="ti ti-restore me-2"></i>',
                                'action' => ['data-url' => '/files/files-restore-deleted?box=' . $data['active'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'divider',
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'icon' => '<i class="ti ti-trash me-2"></i>',
                                'action' => ['data-url' => '/files/files-erase-deleted?box=' . $data['active'], 'data-action' => 'modal']
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
    });

    $app->router("/create-folder/{active}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $account, $setting) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Thêm thư mục");
            echo $app->render($setting['template'] . '/files/files-folder.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);

            $data = $app->get("files_folders", "*", ["active" => $vars['active'], "account" => $account['id'], "deleted" => 0]);

            if ($app->xss($_POST['name']) == '') {
                $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")];
            }
            if (empty($error)) {
                $insert = [
                    "name"          => $app->xss($_POST['name']),
                    "date"          => date("Y-m-d H:i:s"),
                    "modify"         => date("Y-m-d H:i:s"),
                    "account"       => $account['id'],
                    "active"        => $jatbi->active(),
                    "main"          => $data['id'] ?? 0,
                ];
                $app->insert("files_folders", $insert);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
                $jatbi->logs('files', 'files-folders-add', $insert);
            } else {
                echo json_encode($error);
            }
        }
    });

    $app->router("/upload/{active}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $account, $setting) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Tải tệp lên");
            $getType = $app->xss($_GET['type'] ?? '');
            if ($getType == 'assets') {
                $vars['assets'] = 'data-load="/files/assets?type=images&folder=' . $vars['active'] . '" data-selector="[modal-move-load]" data-pjax-history="false" data-pjax-scrollTo="false"';
            } else {
                $vars['assets'] = 'data-load="this"';
            }
            echo $app->render($setting['template'] . '/files/files-upload.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);

            $data = $app->get("files_folders", "*", ["active" => $vars['active'], "account" => $account['id'], "deleted" => 0]);
            $relative_path = $_POST['path'] ?? '';
            $file_info = pathinfo($relative_path);
            $folder_path = $setting['uploads'] . '/' . $account['active'];
            $dirname = $file_info['dirname'] === '.' ? '' : $file_info['dirname'];
            $path_parts = $dirname ? explode('/', trim($dirname, '/')) : [];
            $parent_id = $data['id'] ?? 0;
            foreach ($path_parts as $part) {
                $existing_folder = $app->get("files_folders", "*", [
                    "name" => $part,
                    "account" => $account['id'],
                    "main" => $parent_id
                ]);
                if (!$existing_folder) {
                    $insert_folder = [
                        "name" => $app->xss($part),
                        "date" => date("Y-m-d H:i:s"),
                        "modify" => date("Y-m-d H:i:s"),
                        "account" => $account['id'],
                        "active" => $jatbi->active(),
                        "main" => $parent_id,
                    ];
                    $app->insert("files_folders", $insert_folder);
                    $parent_id = $app->id();
                } else {
                    $parent_id = $existing_folder['id'];
                }
            }
            $handle = $app->upload($_FILES['file']);
            $newimages = $jatbi->active();
            if ($handle->uploaded) {
                $handle->allowed = ['application/*', 'image/*'];
                $handle->file_new_name_body = $newimages;
                $handle->file_max_size = '31457280';
                $handle->Process($folder_path);
            }
            if ($handle->processed) {
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
                $file_url = $folder_path . '/' . $handle->file_dst_name;
                $insert_file = [
                    "account" => $account['id'],
                    "category" => $parent_id ?? 0,
                    "name" => $data['file_src_name'],
                    "url" => $file_url,
                    "extension" => $jatbi->getFileExtension($data['file_src_name']),
                    "date" => date("Y-m-d H:i:s"),
                    "modify" => date("Y-m-d H:i:s"),
                    "active" => $jatbi->active(),
                    "size" => $data['file_src_size'],
                    "mime" => $data['file_src_mime'],
                    "data" => json_encode($data),
                ];
                $app->insert("files", $insert_file);
                echo json_encode(['status' => "success", "content" => $jatbi->lang("Tải lên thành công")]);
            } else {
                echo json_encode(['status' => "error", "content" => $handle->error ?? $jatbi->lang("Tải lên thất bại")]);
            }
        }
    });

    $app->router("/{files}-move/{active}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $account, $setting) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Di chuyển");

            if ($vars['files'] == 'files') {
                $database = 'files';
                $type = 'files';
            } else {
                $database = 'files_folders';
                $type = 'folder';
            }
            $vars['data'] = $app->get($database, "*", [
                "active" => $vars['active'],
                "deleted" => 0,
                "account" => $account['id']
            ]);
            if ($vars['data'] > 1) {
                $getFolder = $app->xss($_GET['folder'] ?? 0);
                if ($getFolder != 0) {
                    $getFolder = $app->get("files_folders", ["id", "name", "active"], [
                        "active" => $getFolder,
                        "account" => $account['id'],
                        "deleted" => 0
                    ]) ?? 0;
                    $vars['subs'] = $jatbi->folders($getFolder['id']);
                }
                if ($type == 'folder') {
                    $vars['folders'] = $app->select("files_folders", "*", [
                        "account" => $account['id'],
                        "deleted" => 0,
                        "main" => $getFolder['id'] ?? 0,
                        "id[!]" => $vars['data']['id'],
                        "ORDER" => ["name" => "DESC"]
                    ]);
                } else {
                    $vars['folders'] = $app->select("files_folders", "*", [
                        "account" => $account['id'],
                        "deleted" => 0,
                        "main" => $getFolder['id'] ?? 0,
                        "ORDER" => ["name" => "DESC"]
                    ]);
                }
                if ($getFolder == 0) {
                    $getFolder = ["id" => 0, "name" => $jatbi->lang("Mục chính"), "active" => 'main'];
                }
                $vars['getfolder'] = $getFolder;
                $vars['type'] = $type;
                if (!empty($vars['subs'])) {
                    $lastKey = array_key_last($vars['subs']); // Lấy key cuối cùng
                    $previousKey = $lastKey - 1; // Trừ đi 1

                    if (isset($vars['subs'][$previousKey])) {
                        $vars['back'] = $vars['subs'][$previousKey]['active'];
                    } else {
                        $vars['back'] = ''; // Nếu không có key hợp lệ, đặt giá trị mặc định
                    }
                } else {
                    $vars['back'] = '';
                }
                echo $app->render($setting['template'] . '/files/files-move.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);

            if ($vars['files'] == 'files') {
                $database = 'files';
                $type = 'files';
            } else {
                $database = 'files_folders';
                $type = 'folder';
            }
            $data = $app->get($database, "*", ["active" => $vars['active'], "deleted" => 0, "account" => $account['id']]);
            if ($data > 1) {
                if ($app->xss($_POST['folder'] ?? '') == '') {
                    echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng chọn thư mục cần chuyển")]);
                } else {
                    $getFolder = $app->get("files_folders", "id", ["active" => $app->xss($_POST['folder']), "account" => $account['id'], "deleted" => 0]) ?? 0;
                    if ($type == 'files') {
                        $update = [
                            "category" => $getFolder,
                            "modify" => date("Y-m-d H:i:s"),
                        ];
                    } elseif ($type == 'folder') {
                        $update = [
                            "main" => $getFolder,
                            "modify" => date("Y-m-d H:i:s"),
                        ];
                    }
                    $app->update($database, $update, ["id" => $data['id']]);
                    $jatbi->logs('files', $type . '-move', $data);
                    echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
                }
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    });

    $app->router("/{files}-copy/{active}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $account, $setting) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Sao chép");

            if ($vars['files'] == 'files') {
                $database = 'files';
                $type = 'files';
            } else {
                $database = 'files_folders';
                $type = 'folder';
            }
            $vars['data'] = $app->get($database, "*", [
                "active" => $vars['active'],
                "deleted" => 0,
            ]);
            if ($vars['data'] > 1) {
                if ($type == 'files') {
                    $viewer = $jatbi->checkFiles($vars['data']['active']);
                } elseif ($type == 'folder') {
                    $viewer = $jatbi->checkFolder($vars['data']['active']);
                }
                if ($viewer) {
                    $getFolder = $app->xss($_GET['folder'] ?? 0);
                    if ($getFolder != 0) {
                        $getFolder = $app->get("files_folders", ["id", "name", "active"], [
                            "active" => $getFolder,
                            "account" => $account['id'],
                            "deleted" => 0
                        ]) ?? 0;
                        $vars['subs'] = $jatbi->folders($getFolder['id']);
                    }
                    if ($type == 'folder') {
                        $vars['folders'] = $app->select("files_folders", "*", [
                            "account" => $account['id'],
                            "deleted" => 0,
                            "main" => $getFolder['id'] ?? 0,
                            "id[!]" => $vars['data']['id'],
                            "ORDER" => ["name" => "DESC"]
                        ]);
                    } else {
                        $vars['folders'] = $app->select("files_folders", "*", [
                            "account" => $account['id'],
                            "deleted" => 0,
                            "main" => $getFolder['id'] ?? 0,
                            "ORDER" => ["name" => "DESC"]
                        ]);
                    }
                    if ($getFolder == 0) {
                        $getFolder = ["id" => 0, "name" => $jatbi->lang("Mục chính"), "active" => 'main'];
                    }
                    $vars['getfolder'] = $getFolder;
                    $vars['type'] = $type;
                    if (!empty($vars['subs'])) {
                        $lastKey = array_key_last($vars['subs']); // Lấy key cuối cùng
                        $previousKey = $lastKey - 1; // Trừ đi 1

                        if (isset($vars['subs'][$previousKey])) {
                            $vars['back'] = $vars['subs'][$previousKey]['active'];
                        } else {
                            $vars['back'] = ''; // Nếu không có key hợp lệ, đặt giá trị mặc định
                        }
                    } else {
                        $vars['back'] = '';
                    }
                    echo $app->render($setting['template'] . '/files/files-copy.html', $vars, $jatbi->ajax());
                } else {
                    echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
                }
            } else {
                echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);

            if ($vars['files'] == 'files') {
                $database = 'files';
                $type = 'files';
            } else {
                $database = 'files_folders';
                $type = 'folder';
            }
            $data = $app->get($database, "*", ["active" => $vars['active'], "deleted" => 0]);
            if ($data > 1) {
                if ($type == 'files') {
                    $viewer = $jatbi->checkFiles($data['active']);
                } elseif ($type == 'folder') {
                    $viewer = $jatbi->checkFolder($data['active']);
                }
                if ($viewer) {
                    if ($app->xss($_POST['folder'] ?? '') == '') {
                        echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Vui lòng chọn thư mục cần chuyển")]);
                    } else {
                        $getFolder = $app->get("files_folders", "id", ["active" => $app->xss($_POST['folder']), "account" => $account['id'], "deleted" => 0]) ?? 0;
                        if ($type == 'files') {
                            $geturl = $data['url']; // URL của file cần sao chép
                            $folder_path = 'datas/' . $account['active'];
                            $name_file = $jatbi->active();
                            $destination = $folder_path . '/' . $name_file . '.' . $data['extension']; // Đổi tên file khi lưu

                            if (copy($geturl, $destination)) {
                                $url = $destination;
                                $insert = [
                                    "category" => $getFolder,
                                    "account" => $account['id'],
                                    "name" => $data['name'],
                                    "extension" => $data['extension'],
                                    "url" => $url,
                                    "data" => $data['data'],
                                    "size" => $data['size'],
                                    "mime" => $data['mime'],
                                    "permission" => 0,
                                    "modify" => date("Y-m-d H:i:s"),
                                    "date" => date("Y-m-d H:i:s"),
                                    "active" => $jatbi->active(),
                                ];
                                $app->insert($database, $insert);
                                $jatbi->logs('files', $type . '-copy', $data);
                                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
                            } else {
                                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
                            }
                        } elseif ($type == 'folder') {
                            $newFolderId = $jatbi->duplicateFolderStructure($data['id'], $getFolder);
                            if ($newFolderId) {
                                $jatbi->logs('files', $type . '-copy', $data);
                                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
                            } else {
                                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra"), 'test' => $newFolderId,]);
                            }
                        }
                    }
                } else {
                    echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
                }
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    });

    $app->router("/{files}-name/{active}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $account, $setting) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Đổi tên");

            if ($vars['files'] == 'files') {
                $database = 'files';
                $type = 'files';
            } else {
                $database = 'files_folders';
                $type = 'folder';
            }
            $vars['data'] = $app->get($database, "*", [
                "active" => $vars['active'],
                "deleted" => 0,
                "account" => $account['id']
            ]);
            if ($vars['data'] > 1) {
                echo $app->render($setting['template'] . '/files/files-name.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);

            if ($vars['files'] == 'files') {
                $database = 'files';
                $type = 'files';
            } else {
                $database = 'files_folders';
                $type = 'folder';
            }
            $data = $app->get($database, "*", ["active" => $vars['active'], "deleted" => 0, "account" => $account['id']]);
            if ($data > 1) {
                $update = [
                    "name" => $app->xss($_POST['name']),
                    "modify" => date("Y-m-d H:i:s"),
                ];
                $app->update($database, $update, ["id" => $data['id']]);
                $jatbi->logs('files', $type . '-name', $data);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    });

    $app->router("/{files}-infomation/{active}", 'GET', function ($vars) use ($app, $jatbi, $setting, $account) {
        if ($vars['files'] == 'files') {
            $database = 'files';
            $type = 'files';
        } else {
            $database = 'files_folders';
            $type = 'folder';
        }
        $vars['data'] = $app->get($database, "*", [
            $database . ".active" => $vars['active'],
            $database . ".deleted" => 0,
        ]);
        if ($vars['data']) {
            if ($type == 'files') {
                $viewer = $jatbi->checkFiles($vars['data']['active']);
            } elseif ($type == 'folder') {
                $viewer = $jatbi->checkFolder($vars['data']['active']);
            }
            if ($viewer) {
                $vars['star'] = $app->count("files_stars", "id", ["data" => $vars['data']['id'], "type" => $type, "deleted" => 0]);
                $vars['user'] = $app->get("accounts", ["name", "avatar"], ["id" => $vars['data']['account']]);
                $vars['share'] = $app->get("files_shares", ["id", "url", "permission", "active", "date"], [
                    "account" => $account['id'],
                    "type" => $type,
                    "data" => $vars['data']['id'],
                    "deleted" => 0
                ]) ?? ["id" => 0];
                if ($vars['share']['id'] > 0) {
                    $vars['SelectAccounts'] = $app->select(
                        "files_accounts",
                        [
                            "[>]accounts" => ["account" => "id"]
                        ],
                        [
                            'files_accounts.id',
                            'files_accounts.active',
                            'accounts.name',
                            'accounts.avatar',
                        ],
                        [
                            "files_accounts.share" => $vars['share']['id'],
                            "files_accounts.deleted" => 0
                        ]
                    ) ?? [];
                    $url = $setting['url'] . '/files/share/' . $vars['share']['active'] . '?token=' . $vars['share']['url'];
                }
                $vars['type'] = $type;
                $vars['url'] = $url ?? '';
                $vars['account'] = $account;
                echo $app->render($setting['template'] . '/files/files-infomation.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
            }
        } else {
            echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
        }
    });

    $app->router("/{files}-download/{active}", 'GET', function ($vars) use ($app, $jatbi, $account, $setting) {
        ob_start();
        if ($vars['files'] == 'files') {
            $database = 'files';
            $type = 'files';
        } else {
            $database = 'files_folders';
            $type = 'folder';
        }
        $data = $app->get($database, "*", [
            "active" => $vars['active'],
            "deleted" => 0,
        ]);
        if ($data) {
            if ($type == 'files') {
                $viewer = $jatbi->checkFiles($data['active']);
            } elseif ($type == 'folder') {
                $viewer = $jatbi->checkFolder($data['active']);
            }
            if ($viewer) {
                if ($type == 'files') {
                    $filePath = $data['url'];
                    $mimeType = mime_content_type($filePath);
                    header('Content-Type: ' . $mimeType);
                    header('Content-Disposition: attachment; filename="' . basename($data['name']) . '"');
                    header('Content-Length: ' . filesize($filePath));
                    readfile($filePath);
                } elseif ($type == 'folder') {
                    $zipFile = $jatbi->createZipFromFolder($data['id']);
                    if (!$zipFile) {
                        header('HTTP/1.0 500 Internal Server Error');
                        die("Lỗi tạo file ZIP.");
                    }
                    $mimeType = mime_content_type($zipFile);
                    header('Content-Type: ' . $mimeType);
                    header('Content-Disposition: attachment; filename="' . basename($data['name'] . '-' . time() . '.zip') . '"');
                    header('Content-Length: ' . filesize($zipFile));
                    readfile($zipFile);
                    unlink($zipFile);
                }
            } else {
                header('HTTP/1.0 404 Not Found');
                die("File not found.");
            }
        } else {
            header('HTTP/1.0 404 Not Found');
            die("File not found.");
        }
        ob_end_flush();
    });

    $app->router("/{files}-star/{active}", 'POST', function ($vars) use ($app, $jatbi, $account, $setting) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        if ($vars['files'] == 'files') {
            $database = 'files';
            $type = 'files';
        } else {
            $database = 'files_folders';
            $type = 'folder';
        }
        $data = $app->get($database, [
            "[>]files_accounts" => ["id" => "data"],
        ], [
            $database . ".id",
            $database . ".active",
            $database . ".name",
            $database . ".date",
            $database . ".modify",
            $database . ".account",
        ], [
            $database . ".active" => $vars['active'],
            $database . ".deleted" => 0,
        ]);
        if ($data > 1) {
            $getStar = $app->get("files_stars", "*", ["account" => $account['id'], "data" => $data['id'], "deleted" => 0]);
            if ($getStar > 1) {
                $app->update("files_stars", ["deleted" => 1], ["id" => $getStar['id']]);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Xóa bỏ sao thành công")]);
            } else {
                if ($type == 'files') {
                    $viewer = $jatbi->checkFiles($data['active']);
                } elseif ($type == 'folder') {
                    $viewer = $jatbi->checkFolder($data['active']);
                }
                if ($viewer) {
                    $insert = [
                        "account" => $account['id'],
                        "type" => $type,
                        "data" => $data['id'],
                        "date" => date("Y-m-d H:i:s"),
                    ];
                    $app->insert("files_stars", $insert);
                    echo json_encode(['status' => 'success', "content" => $jatbi->lang("Đánh sao thành công")]);
                    $jatbi->logs('files', $type . '-star', $data);
                } else {
                    echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
                }
            }
        } else {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
        }
    });

    $app->router("/{files}-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $account, $setting) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Xóa thư mục");
            $deletedCases = ['user-share', 'files', 'folder', 'share', 'files-erase', 'folder-erase'];
            $restoreCases = ['files-restore', 'folder-restore'];
            if (in_array($vars['files'], $deletedCases)) {
                echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
            } elseif (in_array($vars['files'], $restoreCases)) {
                echo $app->render($setting['template'] . '/common/restore.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);

            $boxid = isset($_GET['box']) ? explode(',', $app->xss($_GET['box'])) : [];
            if (empty($boxid)) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Dữ liệu không hợp lệ")]);
                return;
            }
            $deleted = 0;
            if ($vars['files'] == 'user-share') {
                $database = 'files_shares';
                $type = 'user-share';
            } elseif ($vars['files'] == 'files') {
                $database = 'files';
                $type = 'files';
            } elseif ($vars['files'] == 'folder') {
                $database = 'files_folders';
                $type = 'folder';
            } elseif ($vars['files'] == 'share') {
                $database = 'files_shares';
                $type = 'share';
            } elseif ($vars['files'] == 'files-erase') {
                $database = 'files';
                $type = 'files-erase';
                $deleted = 1;
            } elseif ($vars['files'] == 'folder-erase') {
                $database = 'files_folders';
                $type = 'folder-erase';
                $deleted = 1;
            } elseif ($vars['files'] == 'files-restore') {
                $database = 'files';
                $type = 'files-restore';
                $deleted = 1;
            } elseif ($vars['files'] == 'folder-restore') {
                $database = 'files_folders';
                $type = 'folder-restore';
                $deleted = 1;
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Loại file không hợp lệ")]);
                return;
            }
            $datas = $app->select($database, "*", ["active" => $boxid, "deleted" => $deleted]);

            if (!$datas || count($datas) == 0) {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Không tìm thấy dữ liệu hợp lệ")]);
                return;
            }
            $error = null;
            foreach ($datas as $data) {
                if ($type == 'user-share') {
                    $accountCheck = $app->get("files_accounts", "*", [
                        "share" => $data['id'],
                        "data" => $data['data'],
                        "type" => $data['type'],
                        "account" => $account['id'],
                        "deleted" => 0,
                        "ORDER" => ["id" => "DESC"],
                    ]);
                    if ($accountCheck) {
                        $app->update("files_accounts", ["deleted" => 1], ["id" => $accountCheck['id']]);
                    } elseif ($data['permission'] == 1) {
                        $app->insert("files_accounts", [
                            "share" => $data['id'],
                            "account" => $account['id'],
                            "data" => $data['data'],
                            "type" => $data['type'],
                            "deleted" => 0,
                            "date" => date("Y-m-d H:i:s"),
                            "active" => $jatbi->active(),
                            "permission" => $data['permission']
                        ]);
                    }
                } elseif ($type == 'share' && $data['account'] == $account['id']) {
                    $app->update("files_shares", ["deleted" => 1], ["id" => $data['id']]);
                    $app->update("files_accounts", ["deleted" => 1], ["share" => $data['id']]);
                    if ($data['type'] == 'files') {
                        $app->update("files", ["permission" => 0], ["id" => $data['data']]);
                    } elseif ($data['type'] == 'folder') {
                        $app->update("files_folders", ["permission" => 0], ["id" => $data['data']]);
                    }
                } elseif (($type == 'files' || $type == 'folder') && $data['account'] == $account['id']) {
                    $app->update($database, ["deleted" => 1], ["id" => $data['id'], "account" => $account['id']]);
                } elseif (($type == 'files-erase' || $type == 'folder-erase') && $data['account'] == $account['id']) {
                    $app->update($database, ["deleted" => 2], ["id" => $data['id'], "account" => $account['id']]);
                } elseif (($type == 'files-restore' || $type == 'folder-restore') && $data['account'] == $account['id']) {
                    $app->update($database, ["deleted" => 0], ["id" => $data['id'], "account" => $account['id']]);
                } else {
                    $error = ['status' => 'error', 'content' => $jatbi->lang("Có lỗi xảy ra")];
                }
            }
            $jatbi->logs('files', $type . '-deleted', $datas);
            echo json_encode($error ?: ['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
        }
    });

    $app->router("/{files}-share/{active}", 'GET', function ($vars) use ($app, $jatbi, $setting, $account) {
        if ($vars['files'] == 'files') {
            $database = 'files';
            $type = 'files';
        } else {
            $database = 'files_folders';
            $type = 'folder';
        }
        $vars['data'] = $app->get($database, "*", [
            "active" => $vars['active'],
            "deleted" => 0,
            "account" => $account['id']
        ]);
        if ($vars['data'] > 1) {
            $vars['title'] = $jatbi->lang("Chia sẽ");
            $vars['account'] = $account;
            $vars['accounts'] = $app->select("accounts", ["id", "name", "avatar", "active"], ["id[!]" => $account['id'], "status" => 'A', "deleted" => 0]);
            $vars['share'] = $app->get("files_shares", ["id", "url", "permission", "active", "url"], [
                "account" => $account['id'],
                "type" => $type,
                "data" => $vars['data']['id'],
                "deleted" => 0
            ]) ?? ["id" => 0, "url" => $app->randomString(128), "permission" => 1, "active" => $jatbi->active()];
            if ($vars['share']['id'] == 0) {
                $insert = [
                    "account" => $account['id'],
                    "type" => $type,
                    "data" => $vars['data']['id'],
                    "date" => date("Y-m-d H:i:s"),
                    "modify" => date("Y-m-d H:i:s"),
                    "access" => 0,
                    "active" => $vars['share']['active'],
                    "url" => $vars['share']['url'],
                    "permission" => 1,
                ];
                $app->insert("files_shares", $insert);
                $app->update($database, ["permission" => 1], ["id" => $vars['data']['id']]);
                $url = $setting['url'] . '/files/share/' . $insert['active'] . '?token=' . $insert['url'];
            } else {
                $app->select("files_accounts", "*", ["share" => $vars['share']['id'], "deleted" => 0], function ($data) use (&$vars, $jatbi, $app) {
                    $vars['SelectAccounts'][$data['account']] = $data['account'];
                });
                $url = $setting['url'] . '/files/share/' . $vars['share']['active'] . '?token=' . $vars['share']['url'];
            }
            $vars['type'] = $type;
            $vars['url'] = $url;
            echo $app->render($setting['template'] . '/files/files-share.html', $vars, $jatbi->ajax());
        } else {
            echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
        }
    });

    $app->router("/{files}-share/{active}/{name}/{type}", 'POST', function ($vars) use ($app, $jatbi, $account, $setting) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $data = $app->get('files_shares', "*", ["active" => $vars['active'], "deleted" => 0, "account" => $account['id']]);
        if ($data > 1) {
            if ($data['type'] == 'files') {
                $database = 'files';
            } elseif ($data['type'] == 'folder') {
                $database = 'files_folders';
            }
            if ($vars['name'] == 'permission') {
                switch ($vars['type']) {
                    case 'all':
                        $permission = 1;
                        break;

                    case 'only':
                        $permission = 2;
                        break;

                    default:
                        $permission = 0;
                        break;
                }
                $update = [
                    "permission" => $permission,
                    "modify" => date("Y-m-d H:i:s"),
                ];
                $app->update("files_shares", $update, ["id" => $data['id']]);
                $app->update($database, ["permission" => $update['permission']], ["id" => $data['data']]);
                $app->update("files_accounts", ["permission" => $update['permission']], ["share" => $data['id'], "deleted" => 0]);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } elseif ($vars['name'] == 'accounts') {
                $SelectAccounts = explode(",", $app->xss($_POST['account']));
                $SelectAccounts = array_filter($SelectAccounts, function ($acc) {
                    return !empty(trim($acc));
                });
                $existingAccounts = $app->select("files_accounts", "account", [
                    "share" => $data['id'],
                    "deleted" => 0,
                ]);
                // Chuyển thành dạng mảng con nếu cần
                $existingAccountIds = array_map(function ($account) {
                    return ['account' => $account];
                }, $existingAccounts);
                // Lấy danh sách ID tài khoản
                $existingAccountIds = array_column($existingAccountIds, 'account');
                // Xóa trùng lặp
                $existingAccountIds = array_unique($existingAccountIds);
                if (!empty($SelectAccounts)) {
                    foreach ($SelectAccounts as $acc) {
                        if (in_array($acc, $existingAccountIds)) {
                            // Nếu tài khoản đã tồn tại, cập nhật deleted = 0
                            $app->update("files_accounts", [
                                "permission" => $data['permission']
                            ], [
                                "account" => $acc,
                                "share" => $data['id']
                            ]);
                        } else {
                            // Nếu tài khoản chưa tồn tại, thêm mới
                            $app->insert("files_accounts", [
                                "account" => $acc,
                                "share" => $data['id'],
                                "type" => $data['type'],
                                "data" => $data['data'],
                                "date" => date("Y-m-d H:i:s"),
                                "active" => $jatbi->active(),
                                "permission" => $data['permission']
                            ]);
                        }
                    }
                }
                // Cập nhật deleted = 1 cho các tài khoản không còn trong SelectAccounts
                $accountsToDelete = array_diff($existingAccountIds, $SelectAccounts);
                if (!empty($accountsToDelete)) {
                    $app->update("files_accounts", ["deleted" => 1], [
                        "account" => $accountsToDelete,
                        "share" => $data['id'],
                    ]);
                }
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', "content" => $jatbi->lang("Có lỗi xảy ra")]);
            }
        } else {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
        }
    });

    $app->router("/files-views/{active}", 'GET', function ($vars) use ($app, $jatbi, $account, $setting) {
        $vars['data'] = $app->get("files", "*", [
            "files.active" => $vars['active'],
            "files.deleted" => 0,
        ]);
        if ($vars['data']) {
            $viewer = $jatbi->checkFiles($vars['data']['active']);
            if ($viewer) {
                $vars['title'] = $vars['data']['name'];
                $token = $app->randomString(128);
                $expiry = time() + 600;
                $app->insert("files_token", [
                    "data" => $vars['data']['id'],
                    "account" => $account['id'],
                    "token" => $token,
                    "expires" => $expiry,
                    "date" => date("Y-m-d H:i:s"),
                ]);
                $vars['views'] = $jatbi->viewsFile($vars['data']['active'], $token);
                if ($vars['data']['account'] == $account['id']) {
                    $app->update("files", ["modify" => date("Y-m-d H:i:s")], ["id" => $vars['data']['id']]);
                }
                echo $app->render($setting['template'] . '/files/files-views.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
            }
        } else {
            echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
        }
    });

    $app->router("/views/{id}", 'GET', function ($vars) use ($app, $jatbi, $account, $setting) {
        ob_start();
        if ($account > 1) {
            $file_path = $app->get("files", "*", [
                "files.active" => $vars['id'],
            ]);
            if ($file_path > 1) {
                $viewer = $jatbi->checkFiles($file_path['active']);
                if ($viewer) {
                    $path = $file_path['url'];
                    $mime_type = function_exists('mime_content_type') ? mime_content_type($path) : 'application/octet-stream';
                    header('Content-Type: ' . $mime_type);
                    header('Content-Length: ' . filesize($path));
                    header('Cache-Control: public, max-age=31536000, immutable');
                    readfile($path);
                    ob_end_flush();
                    exit;
                }
            }
        } else {
            header('HTTP/1.0 404 Not Found');
            die();
        }
    });

    $app->router("/share/{id}", 'GET', function ($vars) use ($app, $jatbi, $account, $setting) {
        if ($account <= 1) {
            echo $app->render($setting['template'] . '/common/error.html', $vars);
        }
        $share = $app->get("files_shares", "*", [
            "active" => $vars['id'],
            "url" => $app->xss($_GET['token'] ?? ''),
            "deleted" => 0,
        ]);
        if (!$share) {
            echo $app->render($setting['template'] . '/common/error.html', $vars);
        }
        $database = ($share['type'] == 'files') ? 'files' : 'files_folders';
        $data = $app->get($database, "*", [
            "id" => $share['data'],
            "deleted" => 0,
        ]);
        if (!$data) {
            echo $app->render($setting['template'] . '/common/error.html', $vars);
        }
        $viewer = ($share['type'] == 'files')
            ? $jatbi->checkFiles($data['active'])
            : $jatbi->checkFolder($data['active']);

        if (!$viewer) {
            echo $app->render($setting['template'] . '/common/error.html', $vars);
        }
        $header = ($share['type'] == 'files')
            ? '/files/shared-with-me?files=' . $data['active']
            : '/files/folder/' . $data['active'];

        header("Location: " . $header);
    });
})->middleware('login');

$app->router("/files/data/{id}", 'GET', function ($vars) use ($app, $jatbi, $setting) {
    ob_start();
    $file_path = $app->get("files", ['url', "id"], ["active" => $vars['id'], "deleted" => 0]);
    $token = $_GET['token'] ?? null;
    $record = $app->get("files_token", "*", [
        "data" => $file_path['id'],
        "token" => $token
    ]) ?? ["id" => 0];
    if ($record['id'] == 0) {
        header('HTTP/1.0 404 Not Found');
        die();
    }
    $app->delete("files_token", ["token" => $token, "data" => $file_path['id'],]);
    $path = $file_path['url'];
    $mime_type = function_exists('mime_content_type') ? mime_content_type($path) : 'application/octet-stream';
    header('Content-Type: ' . $mime_type);
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: public, max-age=31536000, immutable');
    readfile($path);
    ob_end_flush();
    exit;
});

// $app->router("/upload/{path}/{active}", 'GET', function($vars) use ($app) {
//     $active = $vars['active'];
//     $getType = isset($_GET['type']) ? $_GET['type'] : ''; // Lấy tham số type

//     $upload = $app->select("uploads", "*", ["active" => $active]);

//     $filePath = '';
//     if ($upload && isset($upload[0]['content'])) {
//         // Chuyển đường dẫn tương đối thành tuyệt đối từ src/routers
//         $filePath = realpath(__DIR__ . '/../' . $upload[0]['content']);

//         if ($getType === 'thumb') {
//             // Thay thế '/images/' bằng '/images/thumb/' trong đường dẫn tuyệt đối
//             $filePath = str_replace('/images/', '/images/thumb/', $filePath);
//         }

//         if (!file_exists($filePath)) {
//             $filePath = realpath(__DIR__ . '/../../public/assets/img/eclo.png');
//         }
//     }

//     if (file_exists($filePath)) {
//         $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
//         $mimeTypes = [
//             'jpg'  => 'image/jpeg',
//             'jpeg' => 'image/jpeg',
//             'png'  => 'image/png',
//             'gif'  => 'image/gif',
//             'webp' => 'image/webp',
//             'svg'  => 'image/svg+xml',
//         ];
//         $mime = $mimeTypes[$ext] ?? 'application/octet-stream';

//         header("Content-Type: $mime");
//         header("Content-Length: " . filesize($filePath));
//         header("Cache-Control: public, max-age=31536000, immutable");
//         header("X-Debug-Path: $filePath");

//         // Xóa buffer để tránh dữ liệu thừa
//         ob_clean();
//         readfile($filePath);
//         exit;
//     }

//     http_response_code(404);
//     echo "File không tồn tại";
// });

// $app->router("/upload/{path}/{id}", 'GET', function($vars) use ($app, $jatbi) {
//     ob_start();
//     $getType = $app->xss($_GET['type'] ?? '');
//     $data = $app->get("uploads", 'content', ["active" => $vars['id'], "deleted" => 0]);
//     $file_path = '';
//     if($data){
//         $file_path = realpath(__DIR__ . '/../' . $data['content']);
//         if ($getType == 'thumb') {
//             $file_path = str_replace($vars['path'], $vars['path'] . '/thumb', $file_path);
//         }
//     }
//     if (!file_exists($file_path)) {
//         $file_path = realpath(__DIR__ . '/../../public/assets/img/eclo.png');
//     }
//     $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
//     $mimeTypes = [
//         'jpg'  => 'image/jpeg',
//         'jpeg' => 'image/jpeg',
//         'png'  => 'image/png',
//         'gif'  => 'image/gif',
//         'webp' => 'image/webp',
//         'svg'  => 'image/svg+xml',
//     ];
//     $mime = $mimeTypes[$ext] ?? 'application/octet-stream';
//     header('Content-Type: ' . $mime);
//     header('Content-Length: ' . filesize($file_path));
//     header('Cache-Control: public, max-age=31536000, immutable');
//     readfile($file_path);
//     ob_end_flush();
//     exit;
// });

$app->router("/upload/{path}/{id}", 'GET', function ($vars) use ($app, $jatbi) {
    $getType = $app->xss($_GET['type'] ?? '');
    $data = $app->get("uploads", 'content', ["active" => $vars['id']]);
    $file_path = '';
    if ($data) {
        if ($getType == 'thumb') {
            $file_path = str_replace($vars['path'], $vars['path'] . '/thumb', $data);
        } else {
            $file_path = $data;
        }
    }
    if (!file_exists($file_path)) {
        $file_path = realpath(__DIR__ . '/../../public/assets/img/eclo.png');
    }
    $mime_type = function_exists('mime_content_type') ? mime_content_type($file_path) : 'application/octet-stream';
    header('Content-Type: ' . $mime_type);
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: public, max-age=31536000, immutable');
    ob_clean();
    readfile($file_path);
    exit;
    // echo $file_path;
});

$app->router("/upload-images", 'POST', function ($vars) use ($app, $jatbi, $setting) {
    $app->header([
        'Content-Type' => 'application/json',
    ]);
    $account = $app->get("accounts", "*", ["id" => $app->getSession("accounts")['id']]);
    $handle = $app->upload($_FILES['file']);
    $path_upload = $setting['uploads'] . '/' . $account['active'] . '/images/';
    if (!is_dir($path_upload)) {
        mkdir($path_upload, 0755, true);
    }
    $path_upload_thumb = $setting['uploads'] . '/' . $account['active'] . '/images/thumb';
    if (!is_dir($path_upload_thumb)) {
        mkdir($path_upload_thumb, 0755, true);
    }
    $newimages = $jatbi->active();
    if ($handle->uploaded) {
        $handle->allowed        = array('image/*');
        $handle->file_new_name_body = $newimages;
        $handle->Process($path_upload);
        $handle->image_resize   = true;
        $handle->image_ratio_crop  = true;
        $handle->image_y        = '200';
        $handle->image_x        = '200';
        $handle->allowed        = array('image/*');
        $handle->file_new_name_body = $newimages;
        $handle->Process($path_upload_thumb);
    }
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
        $insert = [
            "account" => $account['id'],
            "type" => "images",
            "content" => $path_upload . $handle->file_dst_name,
            "date" => date("Y-m-d H:i:s"),
            "active" => $newimages,
            "size" => $data['file_src_size'],
            "data" => json_encode($data),
        ];
        $app->insert("uploads", $insert);
    }
    echo json_encode(['status' => "success", "url" => $getimage]);
})->middleware('login');
