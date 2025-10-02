<?php

use ECLO\App;

$template = __DIR__ . '/../templates';
$jatbi = $app->getValueData('jatbi');
$setting = $app->getValueData('setting');
$account = $app->getValueData('account');
$common = $jatbi->getPluginCommon('io.eclo.proposal');
$app->group("/proposal/config", function ($app) use ($setting, $jatbi, $common, $template, $account) {
    $app->router("/diagram", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $account, $common) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Cấu hình đề xuất");
            $vars['sub_title'] = $jatbi->lang("Sơ đồ tài khoản");
            echo $app->render($template . '/config/diagram.html', $vars);
        }
    })->setPermissions(['proposal.config']);

    $app->router("/diagram-update/{type}", 'POST', function ($vars) use ($app, $jatbi, $template, $setting, $common) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $type = $app->xss($vars['type']);
        if ($type == 'load') {
            $json = [
                "id" => 'diagram',
                "name" => 'diagram',
            ];
            $stores = $jatbi->stores();
            if (count($stores) > 1) {
                $stores[] = 0; // thêm 0 vào cuối mảng
            }
            $selectNodes = $app->select("proposal_diagram", "*", ["deleted" => 0, "stores" => $stores]);
            foreach ($selectNodes as $node) {
                $SelectAccounts = $app->select("proposal_diagram_accounts", [
                    "[>]accounts" => ["account" => "id"],
                ], [
                    "@proposal_diagram_accounts.id",
                    "accounts.name (name)",
                    "accounts.avatar (avatar)",
                    "accounts.id (user_id)",
                    "proposal_diagram_accounts.title",
                    "proposal_diagram_accounts.active",
                ], [
                    "proposal_diagram_accounts.diagram" => $node['id'],
                    "proposal_diagram_accounts.deleted" => 0,
                ]);
                $header = '<div class="d-flex align-items-center px-2"> #' . $node['id'] . '<input class="form-control border-0 bg-transparent p-2 fw-bold fst-italic" data-action="blur" data-url="/proposal/config/diagram-edit/' . $node['active'] . '" data-toast="true" data-form=\'{"value":"this.val"}\' value="' . $node['name'] . '"><button class="btn btn-light btn-sm p-1 me-2" data-action="modal" data-url="/proposal/config/diagram-account-add/' . $node['active'] . '"><i class="ti ti-plus"></i></button></div>';
                $htmlaccount = '';
                foreach ($SelectAccounts as $user) {
                    $htmlaccount .= '<div class="d-flex justify-content-start align-items-center mb-3">
                                <div class="me-2"><img data-src="/' . ($user['avatar'] ?? '') . '" class="lazyload width height rounded-circle" style="--widht:40px;--height:40px;"></div>
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong>#' . $user['user_id'] . ' - ' . ($user['name'] ?? '') . '</strong>
                                        <input class="form-control border-0 bg-transparent p-0 fst-italic" data-action="blur" data-url="/proposal/config/diagram-account-edit/' . $user['active'] . '" value="' . $user['title'] . '" data-toast="true" data-form=\'{"value":"this.val"}\'>
                                    </div>
                                    <div><button class="btn btn-light btn-sm p-1 text-eclo" data-action="click" data-confirm="Bạn có muốn xóa tài khoản này?" data-url="/proposal/config/diagram-account-deleted/' . $user['active'] . '" data-workflows-load="true" data-target-canvas="diagram-accounts"><i class="ti ti-trash"></i></button></div>
                                </div>
                            </div>';
                }
                $body = $htmlaccount;
                $content = [
                    "header" => $header,
                    "body" => $body,
                ];
                $json["nodes"][] = [
                    "id" => $node['id'],
                    "workflow_id" => 'diagram',
                    "type" => 'diagram',
                    "data" => $content,
                    "top" => $node['position_top'],
                    "left" => $node['position_left'],
                ];
            }
            $connections = $app->select("proposal_diagram_connections", "*", ["deleted" => 0]);
            foreach ($connections as $connect) {
                $json["connections"][] = [
                    "source" => $connect['source_node'],
                    "target" => $connect['target_node'],
                ];
            }
            echo json_encode($json);
        } elseif ($type == 'node-position') {
            $nodeID = $app->xss($_POST['node_id']);
            $node = $app->get("proposal_diagram", "*", ["id" => $nodeID, "deleted" => 0]);
            if (!empty($node)) {
                $update = [
                    "position_top" => $app->xss($_POST['position_top']),
                    "position_left" => $app->xss($_POST['position_left']),
                ];
                $app->update("proposal_diagram", $update, ["id" => $node['id']]);
                echo json_encode(["status" => "success", "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Lỗi: Node không tồn tại")]);
            }
        } elseif ($type == 'node-delete') {
            $nodeID = $app->xss($_POST['node_id']);
            $node = $app->get("proposal_diagram", "*", ["id" => $nodeID, "deleted" => 0]);
            if (!empty($node)) {
                $app->update("proposal_diagram", ["deleted" => 1], ["id" => $node['id']]);
                $app->update("proposal_diagram_connections", ["deleted" => 1], ["source_node" => $node['id']]);
                echo json_encode(["status" => "success", "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Lỗi: Node không tồn tại")]);
            }
        } elseif ($type == 'connect-create') {
            $insert = [
                "source_node" => $app->xss($_POST['source_node_id']),
                "target_node" => $app->xss($_POST['target_node_id']),
                "date" => date("Y-m-d H:i:s"),
                "active" => $jatbi->active(),
            ];
            $app->insert("proposal_diagram_connections", $insert);
            echo json_encode(["status" => "success", "content" => $jatbi->lang("Cập nhật thành công")]);
        } elseif ($type == 'connect-deleted') {
            $app->update("proposal_diagram_connections", ["deleted" => 1], [
                "source_node" => $app->xss($_POST['source_node_id']),
                "target_node" => $app->xss($_POST['target_node_id']),
            ]);
            echo json_encode(["status" => "success", "content" => $jatbi->lang("Cập nhật thành công")]);
        }
    })->setPermissions(['proposal.config']);

    $app->router("/diagram-add", 'POST', function ($vars) use ($app, $jatbi, $template, $setting, $common) {
        $vars['title'] = $jatbi->lang("Thêm sơ đồ tài khoản");
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        if (count($jatbi->stores()) == 1) {
            $stores = $jatbi->stores()[0];
        } else {
            $stores = 0;
        }
        $insert = [
            "name"          => '',
            "date"          => date("Y-m-d H:i:s"),
            "modify"        => date("Y-m-d H:i:s"),
            "position_top"  => '100',
            "position_left"  => '100',
            "active"        => $jatbi->active(),
            'stores'        => $stores,
        ];
        $app->insert("proposal_diagram", $insert);
        $getID = $app->id();
        $header = '<div class="d-flex align-items-center px-2"> #' . $getID . '<input class="form-control border-0 bg-transparent p-2 fw-bold fst-italic" data-action="blur" data-url="/proposal/config/diagram-edit/' . $insert['active'] . '" data-toast="true" data-form=\'{"value":"this.val"}\' value="' . $insert['name'] . '"><button class="btn btn-light btn-sm p-1 me-2" data-action="modal" data-url="/proposal/config/diagram-account-add/' . $insert['active'] . '"><i class="ti ti-plus"></i></button></div>';

        $body = '';
        $content = [
            "header" => $header,
            "body" => $body,
        ];
        echo json_encode([
            'status' => 'success',
            'content' => $jatbi->lang("Cập nhật thành công"),
            "nodeData" => [
                "id" => $getID,
                "type" => 'diagram',
                "data" => $content ?? '',
            ]
        ]);
    })->setPermissions(['proposal.config.add']);

    $app->router("/diagram-edit/{active}", 'POST', function ($vars) use ($app, $jatbi, $template, $setting, $common) {
        $data = $app->get("proposal_diagram", "*", ["active" => $vars['active'], "deleted" => 0]);
        if ($data > 1) {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $value = $app->xss($_POST['value'] ?? '');
            $insert = [
                "name"          => $value,
                "modify"        => date("Y-m-d H:i:s"),
            ];
            $app->update("proposal_diagram", $insert, ["id" => $data['id']]);
            echo json_encode([
                'status' => 'success',
                'content' => $jatbi->lang("Cập nhật thành công"),
            ]);
        }
    })->setPermissions(['proposal.config.add']);

    $app->router("/diagram-account-add/{active}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $common) {
        $vars['title'] = $jatbi->lang("Thêm tài khoản");
        $node = $app->get("proposal_diagram", "*", ["active" => $vars['active'], "deleted" => 0]);
        if ($node > 1) {
            if ($app->method() === 'GET') {
                echo $app->render($template . '/config/diagram-account-post.html', $vars, $jatbi->ajax());
            } elseif ($app->method() === 'POST') {
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $value = $app->xss($_POST['account']);
                if ($value == '') {
                    $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")];
                }
                $checkaccount = $app->count("proposal_diagram_accounts", "id", ["deleted" => 0, "account" => $value, "diagram" => $node['id']]);
                if ($checkaccount > 0) {
                    $error = ["status" => "warning", "content" => $jatbi->lang("Tài khoản này đã có trong sơ đồ vui lòng kiếm tra lại")];
                }
                if (empty($error)) {
                    $insert = [
                        "account"       => $value,
                        "title"         => $app->xss($_POST['title']),
                        "diagram"       => $node['id'],
                        "active"        => $jatbi->active(),
                    ];
                    $app->insert("proposal_diagram_accounts", $insert);
                    $SelectAccounts = $app->select("proposal_diagram_accounts", [
                        "[>]accounts" => ["account" => "id"],
                    ], [
                        "@proposal_diagram_accounts.id",
                        "accounts.name (name)",
                        "accounts.avatar (avatar)",
                        "proposal_diagram_accounts.title",
                        "proposal_diagram_accounts.active",
                    ], [
                        "proposal_diagram_accounts.diagram" => $node['id'],
                        "proposal_diagram_accounts.deleted" => 0,
                    ]);
                    $header = '<div class="d-flex align-items-center px-2"> #' . $node['id'] . '<input class="form-control border-0 bg-transparent p-2 fw-bold fst-italic" data-action="blur" data-url="/proposal/config/diagram-edit/' . $node['active'] . '" data-toast="true" data-form=\'{"value":"this.val"}\' value="' . $node['name'] . '"><button class="btn btn-light btn-sm p-1 me-2" data-action="modal" data-url="/proposal/config/diagram-account-add/' . $node['active'] . '"><i class="ti ti-plus"></i></button></div>';
                    $htmlaccount = '';
                    foreach ($SelectAccounts as $user) {
                        $htmlaccount .= '<div class="d-flex justify-content-start align-items-center mb-3">
                                    <div class="me-2"><img data-src="/' . ($user['avatar'] ?? '') . '" class="lazyload width height rounded-circle" style="--widht:40px;--height:40px;"></div>
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong>' . ($user['name'] ?? '') . '</strong>
                                            <input class="form-control border-0 bg-transparent p-0 fst-italic" data-action="blur" data-url="/proposal/config/diagram-account-edit/' . $user['active'] . '" value="' . $user['title'] . '" data-toast="true" data-form=\'{"value":"this.val"}\'>
                                        </div>
                                        <div><button class="btn btn-light btn-sm p-1 text-eclo" data-action="click" data-confirm="Bạn có muốn xóa tài khoản này?" data-url="/proposal/config/diagram-account-deleted/' . $user['active'] . '" data-workflows-load="true" data-target-canvas="diagram-accounts"><i class="ti ti-trash"></i></button></div>
                                    </div>
                                </div>';
                    }
                    $body = $htmlaccount;
                    $content = [
                        "header" => $header,
                        "body" => $body,
                    ];

                    echo json_encode([
                        'status' => 'success',
                        'content' => $jatbi->lang("Cập nhật thành công"),
                        "nodeData" => [
                            "id" => $node['id'],
                            "type" => 'diagram',
                            "data" => $content,
                        ]
                    ]);
                } else {
                    echo json_encode($error);
                }
            }
        }
    })->setPermissions(['proposal.config.add']);

    $app->router("/diagram-account-edit/{active}", 'POST', function ($vars) use ($app, $jatbi, $template, $setting, $common) {
        $data = $app->get("proposal_diagram_accounts", "*", ["active" => $vars['active'], "deleted" => 0]);
        if ($data > 1) {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $value = $app->xss($_POST['value'] ?? '');
            $insert = [
                "title"          => $value,
            ];
            $app->update("proposal_diagram_accounts", $insert, ["id" => $data['id']]);
            echo json_encode([
                'status' => 'success',
                'content' => $jatbi->lang("Cập nhật thành công"),
            ]);
        }
    })->setPermissions(['proposal.config.add']);

    $app->router("/diagram-account-deleted/{active}", 'POST', function ($vars) use ($app, $jatbi, $template, $setting, $common) {
        $data = $app->get("proposal_diagram_accounts", "*", ["active" => $vars['active'], "deleted" => 0]);
        if ($data > 1) {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $value = $app->xss($_POST['value'] ?? '');
            $insert = [
                "deleted"          => 1,
            ];
            $app->update("proposal_diagram_accounts", $insert, ["id" => $data['id']]);

            $node = $app->get("proposal_diagram", "*", ["id" => $data['diagram']]);
            $SelectAccounts = $app->select("proposal_diagram_accounts", [
                "[>]accounts" => ["account" => "id"],
            ], [
                "@proposal_diagram_accounts.id",
                "accounts.name (name)",
                "accounts.avatar (avatar)",
                "proposal_diagram_accounts.title",
                "proposal_diagram_accounts.active",
            ], [
                "proposal_diagram_accounts.diagram" => $node['id'],
                "proposal_diagram_accounts.deleted" => 0,
            ]);
            $header = '<div class="d-flex align-items-center px-2"> #' . $node['id'] . '<input class="form-control border-0 bg-transparent p-2 fw-bold fst-italic" data-action="blur" data-url="/proposal/config/diagram-edit/' . $node['active'] . '" data-toast="true" data-form=\'{"value":"this.val"}\' value="' . $node['name'] . '"><button class="btn btn-light btn-sm p-1 me-2" data-action="modal" data-url="/proposal/config/diagram-account-add/' . $node['active'] . '"><i class="ti ti-plus"></i></button></div>';
            $htmlaccount = '';
            foreach ($SelectAccounts as $user) {
                $htmlaccount .= '<div class="d-flex justify-content-start align-items-center mb-3">
                            <div class="me-2"><img data-src="/' . ($user['avatar'] ?? '') . '" class="lazyload width height rounded-circle" style="--widht:40px;--height:40px;"></div>
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong>' . ($user['name'] ?? '') . '</strong>
                                    <input class="form-control border-0 bg-transparent p-0 fst-italic" data-action="blur" data-url="/proposal/config/diagram-account-edit/' . $user['active'] . '" value="' . $user['title'] . '" data-toast="true" data-form=\'{"value":"this.val"}\'>
                                </div>
                                <div><button class="btn btn-light btn-sm p-1 text-eclo" data-action="click" data-confirm="Bạn có muốn xóa tài khoản này?" data-url="/proposal/config/diagram-account-deleted/' . $user['active'] . '" data-workflows-load="true" data-target-canvas="diagram-accounts"><i class="ti ti-trash"></i></button></div>
                            </div>
                        </div>';
            }
            $body = $htmlaccount;
            $content = [
                "header" => $header,
                "body" => $body,
            ];

            echo json_encode([
                'status' => 'success',
                'content' => $jatbi->lang("Cập nhật thành công"),
                "nodeData" => [
                    "id" => $node['id'],
                    "type" => 'diagram',
                    "data" => $content,
                ]
            ]);
        }
    })->setPermissions(['proposal.config.add']);
})->middleware('login');
