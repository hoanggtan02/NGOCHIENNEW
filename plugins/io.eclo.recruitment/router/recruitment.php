<?php
if (!defined('ECLO')) die("Hacking attempt");

use ECLO\App;
$ClassProposals = new Proposal($app);
$app->setValueData('process',$ClassProposals);
$getprocess = $app->getValueData('process');
$template = __DIR__ . '/../templates';
$jatbi = $app->getValueData('jatbi');
$common = $jatbi->getPluginCommon('io.eclo.proposal');
$setting = $app->getValueData('setting');
$account = $app->getValueData('account');
// Xử lý session và stores (giữ nguyên từ expenditure.php)
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
        $accStore[0] = "0";
    }
    foreach ($stores as $itemStore) {
        $accStore[$itemStore['value']] = $itemStore['value'];
    }
}

$app->group($setting['manager'] . "/recruitment", function ($app) use ($jatbi, $setting, $account, $template, $accStore, $stores, $getprocess) {

    // Route: Danh sách tin tuyển dụng
    $app->router("/job_postings", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template, $accStore, $stores) {
        $vars['title'] = $jatbi->lang("Tin tuyển dụng");
        if ($app->method() === 'GET') {
            if (count($stores) > 1) {
                array_unshift($stores, [
                    'value' => '',
                    'text' => $jatbi->lang('Tất cả')
                ]);
            }
            $vars['stores'] = $stores;
            $accounts = $app->select("accounts", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A']);
            array_unshift($accounts, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars['accounts'] = $accounts;
            echo $app->render($template . '/recruitment/job_postings.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? $app->xss($_POST['search']['value']) : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $date_string = isset($_POST['date']) ? $app->xss($_POST['date']) : '';

            $status = isset($_POST['status']) ? [$_POST['status'], $_POST['status']] : '';
            $accounts = isset($_POST['accounts']) ? $_POST['accounts'] : '';

            // Process date range
            if ($date_string) {
                $date = explode('-', $date_string);
                $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date[0]))));
                $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date[1]))));
            } else {
                $date_from = date('Y-m-01 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }

            $stores = isset($_POST['stores']) ? $app->xss($_POST['stores']) : $accStore;

            $where = [
                "AND" => [
                    "OR" => [
                        "job_postings.title[~]" => $searchValue,
                        "job_postings.description[~]" => $searchValue,
                    ],
                    "job_postings.deleted" => 0,
                    "job_postings.created_date[<>]" => [$date_from, $date_to],
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];

            if (!empty($status)) {
                $where["AND"]["job_postings.status"] = $status;
            }
            if (!empty($accounts)) {
                $where["AND"]["job_postings.user"] = $accounts;
            }
            if (!empty($stores)) {
                $where["AND"]["job_postings.stores"] = $stores;
            }

            $count = $app->count("job_postings", ["AND" => $where['AND']]);

            $joins = [
                "[>]accounts" => ["user" => "id"],
                "[>]stores" => ["stores" => "id"],
            ];

            $columns = [
                "job_postings.id",
                "job_postings.title",
                "job_postings.description",
                "job_postings.requirements",
                "job_postings.status",
                "job_postings.jobs",
                "job_postings.interest",
                "job_postings.active",
                "job_postings.created_date",
                "accounts.name(user_name)",
                "stores.name(store_name)"
            ];
            $datas = [];
            $app->select("job_postings", $joins, $columns, $where, function ($data) use (&$datas, $jatbi, $app) {
                $job_names_html = '';
                $job_ids = @unserialize($data['jobs']);
                if (is_array($job_ids) && !empty($job_ids)) {

                    $position_names = $app->select("hrm_positions", "name", [
                        "id" => $job_ids,
                        "ORDER" => ["name" => "ASC"]
                    ]);

                    if (!empty($position_names)) {
                        $badges = [];
                        foreach ($position_names as $name) {
                            $badges[] = '<span class="badge bg-light-secondary text-body-secondary fw-semibold">' . htmlspecialchars($name) . '</span>';
                        }
                        $job_names_html = implode(' ', $badges);
                    }
                }
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['active']]),
                    "title" => $data['title'],
                    "jobs" => $job_names_html,
                    "description" => mb_substr($data['description'] ?? '', 0, 50, 'UTF-8') . '...',
                    "interest" => mb_substr($data['interest'] ?? '', 0, 50, 'UTF-8') . '...',
                    "requirements" => mb_substr($data['requirements'] ?? '', 0, 50, 'UTF-8') . '...',
                    "created_date" => $data['created_date'],
                    "stores" => $data['store_name'],
                    "user" => $data['user_name'],
                    "status" => $app->component("status", [
                        "url" => "/recruitment/job_postings-status/" . $data['active'],
                        "data" => $data['status'],
                        "permission" => ['job_postings.edit']
                    ]),
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xem"),
                                'permission' => ['job_postings'],
                                'action' => [
                                    'data-url' => '/recruitment/job_postings-views/' . $data['active'],
                                    'data-action' => 'modal'
                                ]
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['job_postings.edit'],
                                'action' => [
                                    'data-url' => '/recruitment/job_postings-edit/' . $data['active'],
                                    'data-action' => 'modal'
                                ]
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'permission' => ['job_postings.deleted'],
                                'action' => [
                                    'data-url' => '/recruitment/job_postings-deleted?box=' . $data['active'],
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
                "data" => $datas
            ]);
        }
    })->setPermissions(['job_postings']);

    $app->router('/job_postings-views/{active}', ['GET'], function ($vars) use ($app, $setting, $jatbi, $template) {
        $active_id = $vars['active'];

        $columns = [
            "job_postings.title",
            "job_postings.requirements",
            "job_postings.interest",
            "job_postings.jobs"
        ];

        $where = [
            "job_postings.active" => $active_id,
            "job_postings.deleted" => 0
        ];

        $data = $app->get("job_postings", $columns, $where);

        if (!$data) {
            $vars['modalContent'] = $jatbi->lang("Tin tuyển dụng không tồn tại");
            echo $app->render($setting['template'] . '/common/reward.html', $vars, $jatbi->ajax());
            return;
        }

        $job_list = [];
        $job_ids = @unserialize($data['jobs']);

        if (is_array($job_ids) && !empty($job_ids)) {
            $job_list = $app->select("hrm_positions", "name", [
                "id" => $job_ids
            ]);
        }

        $vars['title'] = $jatbi->lang("Chi tiết tin tuyển dụng");
        $vars['data'] = $data;
        $vars['job_list'] = $job_list;

        echo $app->render($template . '/recruitment/job_postings-views.html', $vars, $jatbi->ajax());
    })->setPermissions(['job_postings']);

    // Route: Thêm tin tuyển dụng
    $app->router("/job_postings-add", ['GET', 'POST'], function ($vars) use ($app, $jatbi,$account ,$template, $stores, $accStore, $getprocess) {
        $vars['title'] = $jatbi->lang("Thêm tin tuyển dụng");
        if ($app->method() === 'GET') {
            $vars['stores'] = $stores;
            $vars['job_postings'] = $app->select("hrm_positions", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A"]);

            $vars['forms'] = $app->select("proposal_form", ["id (value)", "name (text)"], [
                "deleted" => 0,
                "status" => "A"
            ]);

            array_unshift($vars['forms'], ["value" => "", "text" => ""]);
            // $vars['workflows'] = $app->select("proposal_workflows", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A"]);
            // $vars['categorys'] = $app->select("proposal_target", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A"]);

            echo $app->render($template . '/recruitment/job_postings-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $error = [];
            if (empty($app->xss($_POST['title'])) || empty($app->xss($_POST['description'])) || empty($app->xss($_POST['requirements']))) {
                $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")];
            } elseif (count($stores) > 1 && empty($app->xss($_POST['stores']))) {
                $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng chọn cửa hàng")];
            }
            if (empty($error)) {
                $input_stores = count($stores) > 1 ? $app->xss($_POST['stores']) : $app->get("stores", "id", ["id" => $accStore, "status" => 'A', "deleted" => 0]);
                $jobs_array = $_POST['jobs'];
                $data_to_save = serialize($jobs_array);
                $insert = [
                    "title" => $app->xss($_POST['title']),
                    "description" => $app->xss($_POST['description']),
                    "requirements" => $app->xss($_POST['requirements']),
                    "jobs" => $data_to_save,
                    "interest" => $app->xss($_POST['interest']),
                    "status" => 'A',
                    "user" => $app->getSession("accounts")['id'],
                    "created_date" => date("Y-m-d H:i:s"),
                    "active" => $jatbi->active(32),
                    "stores" => $input_stores,
                ];
                $app->insert("job_postings", $insert);
                $job_id = $app->id();
                $recruitment_proposal = [
                    "job_posting_id" => $job_id,
                    "form" => $_POST['form'] ?? 0,
                    "target" => $_POST['category'] ?? 0,
                    "workflows" => $_POST['workflows'] ?? 0,
                    "active" => $jatbi->active(),
                    "status" => 1,
                ];
                $app->insert("recruitment_proposal", $recruitment_proposal);
                if (($recruitment_proposal['form'] > 0) && ($recruitment_proposal['target'] > 0) && ($recruitment_proposal['workflows'] > 0)) {
                    $proposal = [
                        "type"       => 3, // 3 = đề xuất tuyển dụng
                        "form"       => $recruitment_proposal['form'],
                        "workflows"  => $recruitment_proposal['workflows'],
                        "category"   => $recruitment_proposal['target'],
                        "price"      => 0, // có thể bỏ qua, vì tuyển dụng không có chi phí
                        "reality"    => 0,
                        "date"       => date("Y-m-d"),
                        "modify"     => date("Y-m-d H:i:s"),
                        "create"     => date("Y-m-d H:i:s"),
                        "account"    => $app->getSession("accounts")['id'],
                        "status"     => 0,
                        "stores"     => $jatbi->stores('ID'),
                        "stores_id"  => $jatbi->stores('ID'),
                        "active"     => $jatbi->active(),
                        "content"    => 'Đề xuất tuyển dụng: '.$insert['title'],
                        "job_posting_id"=> $job_id,
                    ];
                    $proposal['create'] = date("Y-m-d H:i:s");
                    $proposal['code'] = time();
                    $proposal['active'] = $jatbi->active();
                    $app->insert("proposals",$proposal);
                    $ProposalID = $app->id();
                    $jatbi->logs('proposal','proposal-create',$proposal);

                    $insert_accounts = [
                        "account" => $app->getSession("accounts")['id'],
                        "proposal" => $ProposalID,
                        "date" => date("Y-m-d H:i:s"),
                    ];
                    $app->insert("proposal_accounts",$insert_accounts);
                    $process = $getprocess->workflows($ProposalID,$proposal['account']);

                    if($process[0]){
                        $app->update("proposal_process",["deleted"=>1],["proposal"=>$ProposalID,"workflows"=>$proposal['workflows']]);
                        $proposal_process = [
                            "proposal" => $ProposalID,
                            "workflows" => $proposal['workflows'],
                            "date" => date("Y-m-d H:i:s"),
                            "account" => $app->getSession("accounts")['id'],
                            "node"  => $process[0]['node_id'],
                            "approval" => 1,
                            "approval_date" => date("Y-m-d H:i:s"),
                        ];
                        $app->insert("proposal_process",$proposal_process);
                        $proposal_process_ID = $app->id();
                        $proposal_process_logs = [
                            "proposal" => $ProposalID,
                            "date" => date("Y-m-d H:i:s"),
                            "account" => $app->getSession("accounts")['id'],
                            "content" => 'Gửi đề xuất',
                            "process" => $proposal_process_ID,
                            "data" => json_encode($proposal_process),
                        ];
                        $app->insert("proposal_logs",$proposal_process_logs);
                        $update = [
                            "status" => 1,
                            "process" => $process[0]['node_id'],
                            "modify" => date("Y-m-d H:i:s"),
                        ];
                        $app->update("proposals",$update,["id"=>$ProposalID]);
                        $content_notification = $account['name'].' đề xuất: '.$proposal['content'];
                        $jatbi->notification($app->getSession("accounts")['id'],$process[1]['approver_account_id'],'Đề xuất #'.$ProposalID,$content_notification,'/proposal/views/'.$proposal['active'],'');
                        $insert_accounts = [
                            "account" => $process[1]['approver_account_id'],
                            "proposal" => $ProposalID,
                            "date" => date("Y-m-d H:i:s"),
                        ];
                        $app->insert("proposal_accounts",$insert_accounts);
                    } 
                }
                $jatbi->logs('job_postings', 'add', $insert);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode($error);
            }
        }
    })->setPermissions(['job_postings.add']);

    // Route: Sửa tin tuyển dụng
    $app->router("/job_postings-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $stores, $accStore, $account, $getprocess) {
        $vars['title'] = $jatbi->lang("Sửa tin tuyển dụng");
        $vars['job_postings'] = $app->select("hrm_positions", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A"]);

        // Lấy dữ liệu job
        $data = $app->get("job_postings", "*", ["active" => $vars['id'], "deleted" => 0]);
        if (!$data) {
            echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
            return;
        }

        // Lấy dữ liệu recruitment_proposal (nếu có)
        $GetRecruit = $app->get("recruitment_proposal", "*", ["job_posting_id" => $data['id'], "deleted" => 0]);

        // Đưa giá trị form/workflow/category hiện có vào dropdown (để hiển thị selected)
        $vars['forms'][] = $app->get("proposal_form", ["id (value)", "name (text)"], ["id" => $GetRecruit['form'] ?? 0]);
        $vars['workflows'][] = $app->get("proposal_workflows", ["id (value)", "name (text)"], ["id" => $GetRecruit['workflows'] ?? 0]);
        $vars['categorys'][] = $app->get("proposal_target", ["id (value)", "name (text)"], ["id" => $GetRecruit['target'] ?? 0]);

        $vars['data'] = $data;
        $vars['data']['jobs'] = @unserialize($data['jobs']);

        if ($app->method() === 'GET') {
            $vars['stores'] = $stores;
            echo $app->render($template . '/recruitment/job_postings-post.html', $vars, $jatbi->ajax());
            return;
        }

        // POST (cập nhật)
        $app->header(['Content-Type' => 'application/json']);
        $error = [];

        if (empty($app->xss($_POST['title'])) || empty($app->xss($_POST['description'])) || empty($app->xss($_POST['requirements']))) {
            $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")];
        } elseif (count($stores) > 1 && empty($app->xss($_POST['stores']))) {
            $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng chọn cửa hàng")];
        }

        if (!empty($error)) {
            echo json_encode($error);
            return;
        }

        $input_stores = count($stores) > 1 ? $app->xss($_POST['stores']) : $app->get("stores", "id", ["id" => $accStore, "status" => 'A', "deleted" => 0]);
        $jobs_array = $_POST['jobs'] ?? [];
        $data_to_save = serialize($jobs_array);
        
        // Update job_postings
        $update = [
            "title" => $app->xss($_POST['title']),
            "description" => $app->xss($_POST['description']),
            "requirements" => $app->xss($_POST['requirements']),
            "interest" => $app->xss($_POST['interest']),
            "jobs" => $data_to_save,
            "stores" => $input_stores,
        ];
        $app->update("job_postings", $update, ["id" => $data['id']]);

        // Update hoặc Insert recruitment_proposal (cần WHERE job_posting_id)
        $recruitment_proposal = [
            "form" => $_POST['form'] ?? 0,
            "target" => $_POST['category'] ?? 0,
            "workflows" => $_POST['workflows'] ?? 0,
            "active" => $GetRecruit['active'] ?? $jatbi->active(),
            "status" => 1,
        ];

        if ($GetRecruit && !empty($GetRecruit['id'])) {
            // Update đúng 1 record liên kết với job
            $app->update("recruitment_proposal", $recruitment_proposal, ["id" => $GetRecruit['id']]);
            $recruitment_id = $GetRecruit['id'];
        } else {
            $recruitment_proposal["job_posting_id"] = $data['id'];
            $app->insert("recruitment_proposal", $recruitment_proposal);
            $recruitment_id = $app->id();
        }

        // Nếu đủ form/target/workflows thì tạo/update proposal
        if ($recruitment_proposal['form'] > 0 && $recruitment_proposal['target'] > 0 && $recruitment_proposal['workflows'] > 0) {
            // Kiểm tra proposal đã tồn tại cho job này chưa
            $checkProposal = $app->get("proposals", ["id", "active", "workflows", "account"], ["job_posting_id" => $data['id'], "deleted" => 0]);

            $user_id = $app->getSession("accounts")['id'];
            $user_name = $app->getSession("accounts")['name'] ?? '';

            // Chuẩn dữ liệu proposal
            $proposalData = [
                "type" => 3,
                "form" => $recruitment_proposal['form'],
                "workflows" => $recruitment_proposal['workflows'],
                "category" => $recruitment_proposal['target'],
                "price" => 0,
                "reality" => 0,
                "date" => date("Y-m-d"),
                "modify" => date("Y-m-d H:i:s"),
                "account" => $user_id,
                "status" => 0,
                "stores" => $jatbi->stores('ID'),
                "stores_id" => $jatbi->stores('ID'),
                "active" => $jatbi->active(),
                "content" => 'Đề xuất tuyển dụng: ' . $app->xss($_POST['title']),
                "job_posting_id" => $data['id'],
            ];

            if ($checkProposal && !empty($checkProposal['id'])) {
                // Update existing
                $app->update("proposals", $proposalData, ["id" => $checkProposal['id']]);
                $ProposalID = $checkProposal['id'];
                $proposalData['active'] = $checkProposal['active'];
                $jatbi->logs('proposal', 'proposal-update', $proposalData);
            } else {
                // Insert new
                $proposalData['create'] = date("Y-m-d H:i:s");
                $proposalData['code'] = time();
                $proposalData['active'] = $proposalData['active']; // giữ giá trị active đã sinh
                $app->insert("proposals", $proposalData);
                $ProposalID = $app->id();
                $jatbi->logs('proposal', 'proposal-create', $proposalData);
            }

            // Ghi người tạo (nếu chưa có)
            $app->insert("proposal_accounts", [
                "account" => $user_id,
                "proposal" => $ProposalID,
                "date" => date("Y-m-d H:i:s"),
            ]);

            // Lấy process và tạo bước đầu nếu có
            $process = $getprocess->workflows($ProposalID, $proposalData['account']);

            if (!empty($process[0])) {
                $app->update("proposal_process", ["deleted" => 1], ["proposal" => $ProposalID, "workflows" => $proposalData['workflows']]);

                $proposal_process = [
                    "proposal" => $ProposalID,
                    "workflows" => $proposalData['workflows'],
                    "date" => date("Y-m-d H:i:s"),
                    "account" => $user_id,
                    "node" => $process[0]['node_id'],
                    "approval" => 1,
                    "approval_date" => date("Y-m-d H:i:s"),
                ];
                $app->insert("proposal_process", $proposal_process);
                $proposal_process_ID = $app->id();

                $app->insert("proposal_logs", [
                    "proposal" => $ProposalID,
                    "date" => date("Y-m-d H:i:s"),
                    "account" => $user_id,
                    "content" => 'Gửi đề xuất',
                    "process" => $proposal_process_ID,
                    "data" => json_encode($proposal_process),
                ]);

                $app->update("proposals", [
                    "status" => 1,
                    "process" => $process[0]['node_id'],
                    "modify" => date("Y-m-d H:i:s"),
                ], ["id" => $ProposalID]);

                // Gửi notification nếu có approver tiếp theo
                if (!empty($process[1]['approver_account_id'])) {
                    $content_notification = $user_name . ' đề xuất: ' . $proposalData['content'];
                    $jatbi->notification($user_id, $process[1]['approver_account_id'], 'Đề xuất #' . $ProposalID, $content_notification, '/proposal/views/' . $proposalData['active'], '');

                    // Ghi đề tài khoản người duyệt
                    $app->insert("proposal_accounts", [
                        "account" => $process[1]['approver_account_id'],
                        "proposal" => $ProposalID,
                        "date" => date("Y-m-d H:i:s"),
                    ]);
                }
            }
        }

        $jatbi->logs('job_postings', 'edit', $update);
        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công"), 'url' => $_SERVER['HTTP_REFERER']]);
    })->setPermissions(['job_postings.edit']);

    // Route: Xóa tin tuyển dụng
    $app->router("/job_postings-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa tin tuyển dụng");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("job_postings", "*", ["active" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("job_postings", ["deleted" => 1], ["id" => $data['id']]);
                }
                $jatbi->logs('job_postings', 'job_postings-deleted', $datas);
                $jatbi->trash('/job_postings/job_postings-restore', "Xóa tin tuyển dụng: ", ["database" => 'job_postings', "data" => $boxid]);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['job_postings.deleted']);

    // Route: Cập nhật trạng thái tin tuyển dụng
    $app->router("/job_postings-status/{id}", ['POST'], function ($vars) use ($app, $jatbi) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);
        $data = $app->get("job_postings", "*", ["active" => $vars['id'], "deleted" => 0]);
        if ($data > 1) {
            if ($data > 1) {
                if ($data['status'] === 'A') {
                    $status = "D";
                } elseif ($data['status'] === 'D') {
                    $status = "A";
                }
                $app->update("job_postings", ["status" => $status], ["id" => $data['id']]);
                $jatbi->logs('job_postings', 'job_postings-status', $data);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Cập nhật thất bại"),]);
            }
        } else {
            echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
        }
    })->setPermissions(['job_postings.edit']);

    // Route: Danh sách ứng viên
    $app->router("/candidates", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template, $accStore, $stores) {
        $vars['title'] = $jatbi->lang("Hồ sơ ứng viên");
        if ($app->method() === 'GET') {
            if (count($stores) > 1) {
                array_unshift($stores, [
                    'value' => '',
                    'text' => $jatbi->lang('Tất cả')
                ]);
            }
            $vars['stores'] = $stores;
            $accounts = $app->select("accounts", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A']);
            array_unshift($accounts, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars['accounts'] = $accounts;
            $jobs = $app->select("job_postings", ["id(value)", "title(text)"], ["deleted" => 0, "status" => "A"]);
            array_unshift($jobs, ['value' => '', 'text' => '']);
            $vars['job'] = $jobs;
            echo $app->render($template . '/recruitment/candidates.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            // Get DataTables parameters
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? $app->xss($_POST['search']['value']) : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $date_string = isset($_POST['date']) ? $app->xss($_POST['date']) : '';

            $accounts = isset($_POST['accounts']) ? $_POST['accounts'] : '';
            $job = isset($_POST['job']) ? $_POST['job'] : '';


            // Process date range
            if ($date_string) {
                $date = explode('-', $date_string);
                $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date[0]))));
                $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date[1]))));
            } else {
                $date_from = date('Y-m-01 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }

            $stores = isset($_POST['stores']) ? $app->xss($_POST['stores']) : $accStore;

            $where = [
                "AND" => [
                    "OR" => [
                        "candidates.full_name[~]" => $searchValue ?: '%',
                        "candidates.email[~]" => $searchValue ?: '%',
                        "candidates.phone[~]" => $searchValue ?: '%',
                    ],
                    "candidates.deleted" => 0,
                    "candidates.created_date[<>]" => [$date_from, $date_to],
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];
            if (!empty($accounts)) {
                $where["AND"]["candidates.user"] = $accounts;
            }
            if (!empty($stores)) {
                $where["AND"]["candidates.stores"] = $stores;
            }
            if (!empty($job)) {
                $where["AND"]["candidates.job"] = $job;
            }

            $count = $app->count("candidates", ["AND" => $where['AND']]);

            $joins = [
                "[>]accounts" => ["user" => "id"],
                "[>]stores" => ["stores" => "id"],
                "[>]job_postings" => ["job" => "id"],

            ];

            $columns = [
                "candidates.id",
                "candidates.full_name",
                "candidates.email",
                "candidates.phone",
                "candidates.cv_path",
                "candidates.source",
                "candidates.active",
                "candidates.created_date",
                "accounts.name(user_name)",
                "stores.name(store_name)",
                "job_postings.title(job_title)"
            ];

            $datas = [];

            $app->select("candidates", $joins, $columns, $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "full_name" => $data['full_name'],
                    "email" => $data['email'],
                    "phone" => $data['phone'],
                    "source" => $data['source'],
                    "stores" => $data['store_name'],
                    "user" => $data['user_name'],
                    "job" => $data['job_title'],
                    "created_date" => $data['created_date'],
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['candidates.edit'],
                                'action' => ['data-url' => '/recruitment/candidates-edit/' . $data['id'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'permission' => ['candidates.deleted'],
                                'action' => ['data-url' => '/recruitment/candidates-deleted?box=' . $data['id'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'link',
                                'name' => $jatbi->lang("Xem CV"),
                                'permission' => ['candidates'],
                                'action' => [
                                    'href' => '/recruitment/candidates-view-CV/' . $data['cv_path'],
                                    'target' => '_blank',
                                ]
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("In phiếu pv"),
                                'permission' => ['candidates'],
                                'action' => ['data-url' => '/recruitment/candidates-print/' . $data['id'], 'data-action' => 'modal']
                            ],
                        ]
                    ]),
                ];
            });

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas
            ]);
        }
    })->setPermissions(['candidates']);

    $app->router("/candidates-view-CV/{id}", 'GET', function ($vars) use ($app) {
        $id = $vars['id'];

        // Lấy file từ DB
        $file = $app->get("files", ["url", "name"], ["active" => $id, "deleted" => 0]);

        if (!$file) {
            http_response_code(404);
            echo "File không tồn tại";
            exit;
        }

        // Đường dẫn tuyệt đối trên server
        $file_path = $file['url'];

        if (!file_exists($file_path)) {
            http_response_code(404);
            echo "Không tìm thấy file trên server";
            exit;
        }

        header("Content-Type: application/pdf");
        header("Content-Disposition: inline; filename=\"" . basename($file['name']) . "\"");
        header("Content-Length: " . filesize($file_path));

        readfile($file_path);
        exit;
    });

    $app->router("/candidates-print/{id}", ['GET'], function ($vars) use ($app, $setting, $jatbi, $template) {
        $id = $vars['id'];

        $candidate = $app->get(
            "candidates",
            [
                "[>]job_postings" => ["job" => "id"]
            ],
            [
                "candidates.id",
                "candidates.full_name",
                "candidates.email",
                "candidates.phone",
                "candidates.source",
                "candidates.created_date",
                "job_postings.title(job_title)"
            ],
            [
                "candidates.id" => $id
            ]
        );

        if (!$candidate) {
            echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Không tin thấy ứng viên")]);
        }

        $vars['candidate'] = $candidate;
        $vars['setting'] = $setting;

        echo $app->render($template . '/recruitment/candidates-print-view.html', $vars, $jatbi->ajax());
    })->setPermissions(['candidates']);

    // Route: Thêm ứng viên
    $app->router("/candidates-add", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template, $stores, $accStore) {
        $vars['title'] = $jatbi->lang("Thêm hồ sơ ứng viên");
        if ($app->method() === 'GET') {
            $vars['stores'] = $stores;
            $vars['job'] = $app->select("job_postings", ["id(value)", "title(text)"], ["deleted" => 0, "status" => "A"]);
            echo $app->render($template . '/recruitment/candidates-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $error = [];

            // Validate cơ bản
            if (empty($app->xss($_POST['full_name']))) {
                $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống họ tên")];
            } elseif (count($stores) > 1 && empty($app->xss($_POST['stores']))) {
                $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng chọn cửa hàng")];
            }

            // Xử lý upload CV
            $cv_path = '';
            $account = $app->get('accounts', '*', ['id' => $app->getSession('accounts')['id']]);
            if (!$account) {
                $error = ["status" => "error", "content" => $jatbi->lang("Tài khoản không hợp lệ")];
            } else {
                if (isset($_FILES['cv_path']) && $_FILES['cv_path']['error'] === UPLOAD_ERR_OK) {
                    // Upload file mới
                    $handle = $app->upload($_FILES['cv_path']);
                    $path_upload = $setting['uploads'] . '/' . $account['active'] . '/cvs/';
                    if (!is_dir($path_upload)) {
                        mkdir($path_upload, 0755, true);
                    }
                    $new_file_id = $jatbi->active();
                    $allowed_types = [
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                    ];

                    if ($handle->uploaded) {
                        $handle->allowed = $allowed_types;
                        $handle->file_max_size = 10485760; // 10MB
                        $handle->file_new_name_body = $new_file_id;
                        $handle->Process($path_upload);
                    }

                    if ($handle->processed) {
                        $file_url = $path_upload . $handle->file_dst_name;
                        $file_data = [
                            'account' => $account['id'],
                            'category' => 0,
                            'name' => $handle->file_src_name,
                            'extension' => $jatbi->getFileExtension($handle->file_src_name),
                            'url' => $file_url,
                            'size' => $handle->file_src_size,
                            'mime' => $handle->file_src_mime,
                            'permission' => 0,
                            'active' => $new_file_id,
                            'date' => date('Y-m-d H:i:s'),
                            'modify' => date('Y-m-d H:i:s'),
                            'deleted' => 0,
                            'data' => json_encode([
                                'file_src_name' => $handle->file_src_name,
                                'file_src_name_body' => $handle->file_src_name_body,
                                'file_src_name_ext' => $handle->file_src_name_ext,
                                'file_src_pathname' => $handle->file_src_pathname,
                                'file_src_mime' => $handle->file_src_mime,
                                'file_src_size' => $handle->file_src_size
                            ])
                        ];
                        $app->insert('files', $file_data);
                        $jatbi->logs('files', 'upload-cvs', [
                            'file' => $file_data['name'],
                            'active' => $new_file_id,
                            'candidate' => $app->xss($_POST['full_name'])
                        ]);
                        $cv_path = $new_file_id;
                        $handle->clean();
                    } else {
                        $error = ["status" => "error", "content" => $jatbi->lang("Upload CV thất bại: ") . ($handle->error ?? 'Lỗi không xác định')];
                    }
                } elseif (!empty($_POST['cv_path'])) {
                    // Chọn file đã upload sẵn
                    $file = $app->get('files', '*', ['active' => $app->xss($_POST['cv_path']), 'deleted' => 0]);
                    if ($file && $jatbi->checkFiles($file['active'])) {
                        $cv_path = $file['active'];
                    } else {
                        $error = ["status" => "error", "content" => $jatbi->lang("File không hợp lệ hoặc không có quyền truy cập")];
                    }
                } else {
                    $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng tải lên CV")];
                }
            }

            // Nếu không có lỗi thì insert ứng viên
            if (empty($error)) {
                $input_stores = count($stores) > 1 ? $app->xss($_POST['stores']) : $app->get("stores", "id", ["id" => $accStore, "status" => 'A', "deleted" => 0]);
                $insert = [
                    "full_name" => $app->xss($_POST['full_name']),
                    "email" => $app->xss($_POST['email'] ?? ''),
                    "phone" => $app->xss($_POST['phone'] ?? ''),
                    "cv_path" => $cv_path,
                    "source" => $app->xss($_POST['source'] ?? ''),
                    // "notes" => $app->xss($_POST['notes'] ?? ''),
                    "user" => $app->getSession("accounts")['id'],
                    "created_date" => date("Y-m-d H:i:s"),
                    "active" => $new_file_id,
                    "job" =>  $app->xss($_POST['job']),
                    "stores" => $input_stores,
                ];
                $app->insert("candidates", $insert);
                $jatbi->logs('candidates', 'add', $insert);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Thêm thành công")]);
            } else {
                echo json_encode($error);
            }
        }
    })->setPermissions(['candidates.add']);

    // Route: Sửa ứng viên
    $app->router("/candidates-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $setting, $stores, $accStore) {
        $vars['title'] = $jatbi->lang("Sửa hồ sơ ứng viên");
        $data = $app->get("candidates", "*", ["id" => $vars['id'], "deleted" => 0]);

        if (!$data) {
            echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
            return;
        }
        $vars['data'] = $data;

        if ($app->method() === 'GET') {
            $vars['stores'] = $stores;
            // Bổ sung lấy danh sách vị trí tuyển dụng (giống hàm add)
            $vars['job'] = $app->select("job_postings", ["id(value)", "title(text)"], ["deleted" => 0, "status" => "A"]);
            echo $app->render($template . '/recruitment/candidates-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $error = [];

            // Validate cơ bản (giữ nguyên)
            if (empty($app->xss($_POST['full_name']))) {
                $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống họ tên")];
            } elseif (count($stores) > 1 && empty($app->xss($_POST['stores']))) {
                $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng chọn cửa hàng")];
            }

            // --- BẮT ĐẦU PHẦN XỬ LÝ CV ĐƯỢC COPY TỪ HÀM ADD ---

            // Mặc định giữ lại CV cũ nếu không có thay đổi
            $cv_path = $data['cv_path'];

            $account = $app->get('accounts', '*', ['id' => $app->getSession('accounts')['id']]);
            if (!$account && empty($error)) {
                $error = ["status" => "error", "content" => $jatbi->lang("Tài khoản không hợp lệ")];
            } else {
                // Trường hợp 1: Có file MỚI được tải lên
                if (isset($_FILES['cv_path']) && $_FILES['cv_path']['error'] === UPLOAD_ERR_OK) {
                    $handle = $app->upload($_FILES['cv_path']);
                    $path_upload = $setting['uploads'] . '/' . $account['active'] . '/cvs/';
                    if (!is_dir($path_upload)) {
                        mkdir($path_upload, 0755, true);
                    }
                    $new_file_id = $jatbi->active();
                    $allowed_types = [
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                    ];

                    if ($handle->uploaded) {
                        $handle->allowed = $allowed_types;
                        $handle->file_max_size = 10485760; // 10MB
                        $handle->file_new_name_body = $new_file_id;
                        $handle->Process($path_upload);
                    }

                    if ($handle->processed) {
                        // Toàn bộ logic lưu thông tin file vào bảng 'files'
                        $file_url = $path_upload . $handle->file_dst_name;
                        $file_data = [
                            'account' => $account['id'],
                            'category' => 0,
                            'name' => $handle->file_src_name,
                            'extension' => $jatbi->getFileExtension($handle->file_src_name),
                            'url' => $file_url,
                            'size' => $handle->file_src_size,
                            'mime' => $handle->file_src_mime,
                            'permission' => 0,
                            'active' => $new_file_id,
                            'date' => date('Y-m-d H:i:s'),
                            'modify' => date('Y-m-d H:i:s'),
                            'deleted' => 0,
                            'data' => json_encode(['file_src_name' => $handle->file_src_name, 'file_src_name_body' => $handle->file_src_name_body, 'file_src_name_ext' => $handle->file_src_name_ext, 'file_src_pathname' => $handle->file_src_pathname, 'file_src_mime' => $handle->file_src_mime, 'file_src_size' => $handle->file_src_size])
                        ];
                        $app->insert('files', $file_data);
                        $jatbi->logs('files', 'upload-cvs', ['file' => $file_data['name'], 'active' => $new_file_id, 'candidate' => $app->xss($_POST['full_name'])]);

                        // Cập nhật $cv_path thành ID active của file MỚI
                        $cv_path = $new_file_id;
                        $handle->clean();
                    } else {
                        $error = ["status" => "error", "content" => $jatbi->lang("Upload CV thất bại: ") . ($handle->error ?? 'Lỗi không xác định')];
                    }

                    // Trường hợp 2: Chọn một file ĐÃ CÓ SẴN (và khác với file cũ)
                } elseif (!empty($_POST['cv_path']) && $_POST['cv_path'] != $data['cv_path']) {
                    $file = $app->get('files', '*', ['active' => $app->xss($_POST['cv_path']), 'deleted' => 0]);
                    if ($file && $jatbi->checkFiles($file['active'])) {
                        $cv_path = $file['active'];
                    } else {
                        $error = ["status" => "error", "content" => $jatbi->lang("File không hợp lệ hoặc không có quyền truy cập")];
                    }
                }
                // Trường hợp 3: Không upload file mới, cũng không chọn file có sẵn -> $cv_path giữ nguyên giá trị ban đầu (file cũ), không làm gì cả.
            }

            // --- KẾT THÚC PHẦN XỬ LÝ CV ---

            // Nếu không có lỗi thì update ứng viên
            if (empty($error)) {
                $input_stores = count($stores) > 1 ? $app->xss($_POST['stores']) : $app->get("stores", "id", ["id" => $accStore, "status" => 'A', "deleted" => 0]);

                $update = [
                    "full_name" => $app->xss($_POST['full_name']),
                    "email"     => $app->xss($_POST['email'] ?? ''),
                    "phone"     => $app->xss($_POST['phone'] ?? ''),
                    "cv_path"   => $cv_path, // Sử dụng $cv_path đã được xử lý
                    "source"    => $app->xss($_POST['source'] ?? ''),
                    "job"       => $app->xss($_POST['job']),
                    "stores"    => $input_stores,
                ];
                $app->update("candidates", $update, ["id" => $vars['id']]);
                $jatbi->logs('candidates', 'edit', $update);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode($error);
            }
        }
    })->setPermissions(['candidates.edit']);

    // Route: Xóa ứng viên
    $app->router("/candidates-deleted", ['GET'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa ứng viên");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("candidates", "*", ["active" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("candidates", ["deleted" => 1], ["id" => $data['id']]);
                }
                $jatbi->logs('candidates', 'candidates-deleted', $datas);
                $jatbi->trash('/candidates/candidates-restore', "Xóa tin tuyển dụng: ", ["database" => 'candidates', "data" => $boxid]);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['candidates.deleted']);

    // Route: Danh sách đơn ứng tuyển
    $app->router("/applications", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $template, $stores, $accStore) {
        $vars['title'] = $jatbi->lang("Theo dõi quy trình ứng viên");
        if ($app->method() === 'GET') {
            if (count($stores) > 1) {
                array_unshift($stores, [
                    'value' => '',
                    'text' => $jatbi->lang('Tất cả')
                ]);
            }
            $vars['stores'] = $stores;
            $accounts = $app->select("accounts", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A']);
            array_unshift($accounts, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars['accounts'] = $accounts;
            $status_options = [
                ["value" => "submitted",  "text" => "Đã nộp"],
                ["value" => "screening",  "text" => "Sàng lọc CV"],
                ["value" => "interview",  "text" => "Phỏng vấn"],
                ["value" => "offered",    "text" => "Đã gửi offer"],
                ["value" => "hired",      "text" => "Đã nhận việc"],
                ["value" => "rejected",   "text" => "Từ chối"],
            ];
            $vars['status'] = $status_options;
            echo $app->render($template . '/recruitment/applications.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            // Get DataTables parameters
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? $app->xss($_POST['search']['value']) : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'ASC';
            $date_string = isset($_POST['date']) ? $app->xss($_POST['date']) : '';

            $status = isset($_POST['status']) ? [$_POST['status'], $_POST['status']] : '';
            $accounts = isset($_POST['accounts']) ? $_POST['accounts'] : '';

            // Process date range
            if ($date_string) {
                $date = explode('-', $date_string);
                $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date[0]))));
                $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date[1]))));
            } else {
                $date_from = date('Y-m-01 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }

            $stores = isset($_POST['stores']) ? $app->xss($_POST['stores']) : $accStore;

            $where = [
                "AND" => [
                    "applications.deleted" => 0,
                    "candidates.deleted" => 0,
                    "job_postings.deleted" => 0,
                    "applications.application_date[<>]" => [$date_from, $date_to],
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];
            if (!empty($status)) {
                $where["AND"]["applications.status"] = $status;
            }
            if (!empty($stores)) {
                $where["AND"]["applications.stores"] = $stores;
            }
            if (!empty($accounts)) {
                $where["AND"]["applications.user"] = $accounts;
            }
            if ($searchValue) {
                $where["AND"]["OR"] = [
                    "candidates.full_name[~]" => $searchValue,
                    "job_postings.title[~]" => $searchValue,
                ];
            }

            $joins = [
                "[>]accounts" => ["user" => "id"],
                "[>]stores" => ["stores" => "id"],
                "[>]candidates" => ["candidate_id" => "id"],
                "[>]job_postings" => ["job_posting_id" => "id"],
            ];

            $columns = [
                "applications.id",
                "applications.status",
                "applications.application_date",
                "applications.active",
                "candidates.full_name",
                "candidates.active(candidates_active)",
                "job_postings.title",
                "accounts.name(user_name)",
                "stores.name(store_name)"
            ];

            $count = $app->count("applications", $joins, "*", [
                "AND" => $where['AND'],
            ]);



            $datas = [];
            $app->select("applications", $joins, $columns, $where, function ($data) use (&$datas, $jatbi, $app) {
                $status_labels = [
                    'submitted' => '<span class="badge bg-secondary">Đã nộp</span>',
                    'screening' => '<span class="badge bg-info text-dark">Sàng lọc CV</span>',
                    'interview' => '<span class="badge bg-primary">Phỏng vấn</span>',
                    'offered'   => '<span class="badge bg-warning text-dark">Đã gửi offer</span>',
                    'hired'     => '<span class="badge bg-success">Đã nhận việc</span>',
                    'rejected'  => '<span class="badge bg-danger">Từ chối</span>',
                ];
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['active']]),
                    "full_name" => $data['full_name'],
                    "title" => $data['title'],
                    "status" => $status_labels[$data['status']] ?? $data['status'],
                    "application_date" => date('d/m/Y H:i', strtotime($data['application_date'])),
                    "user" => $data['user_name'],
                    "stores" => $data['store_name'],
                    "action" => $app->component("action", [
                        "button" => [
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Chuyển ứng viên"),
                                'permission' => ['applications.add'],
                                'action' => [
                                    'data-url' => '/recruitment/applications-move/' . $data['candidates_active'],
                                    'data-action' => 'modal'
                                ]
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Sửa"),
                                'permission' => ['applications.edit'],
                                'action' => [
                                    'data-url' => '/recruitment/applications-edit/' . $data['active'],
                                    'data-action' => 'modal'
                                ]
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Xóa"),
                                'permission' => ['applications.deleted'],
                                'action' => [
                                    'data-url' => '/recruitment/applications-deleted?box=' . $data['active'],
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
                "data" => $datas
            ]);
        }
    })->setPermissions(['applications']);

    // Route: Chuyển đơn ứng tuyển
    $app->router("/applications-move/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $stores, $accStore) {
        $vars['title'] = $jatbi->lang("Chuyển ứng viên");
        if ($app->method() === 'GET') {
            $vars['stores'] = array_merge(
                [["value" => "", "text" => $jatbi->lang("Chọn")]],
                $app->select("stores", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "ORDER" => ["name" => "ASC"]])
            );
            $vars['offices'] = array_merge(
                [["value" => "", "text" => $jatbi->lang("Chọn")]],
                $app->select("offices", ["id (value)", "name (text)"], ["deleted" => 0, "status" => "A", "ORDER" => ["name" => "ASC"]])
            );
            $vars['gender'] = [
                ["value" => "", "text" => "Chọn"],
                ["value" => 0, "text" => "Nữ"],
                ["value" => 1, "text" => "Nam"],
            ];
            $vars['idtype'] = [
                ["value" => "", "text" => "Chọn"],
                ["value" => 1, "text" => "CMND"],
                ["value" => 2, "text" => "CCCD"],
                ["value" => 3, "text" => "CCCD gắn chíp"],
                ["value" => 4, "text" => "Hộ chiếu"],
            ];
            $vars['data'] = $app->get("candidates", ["phone", "email", "full_name(name)", "stores"], ["active" => $vars['id'], "deleted" => 0]);
            echo $app->render($template . '/hrm/personnels-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            if ($app->xss($_POST['name']) == '' || $app->xss($_POST['phone']) == '' || $app->xss($_POST['stores']) == '') {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")]);
            } else {
                // if($app->xss($_POST['same_address'])==1){
                // 	$permanent_address = $app->xss($_POST['address']);
                // 	$permanent_province = $app->xss($_POST['province']);
                // 	$permanent_district = $app->xss($_POST['district']);
                // 	$permanent_ward = $app->xss($_POST['ward']);
                // }
                // else {
                // 	$permanent_address = $app->xss($_POST['permanent_address']);
                // 	$permanent_province = $app->xss($_POST['permanent_province']);
                // 	$permanent_district = $app->xss($_POST['permanent_district']);
                // 	$permanent_ward = $app->xss($_POST['permanent_ward']);
                // }
                $insert = [
                    // "active" 	=> $jatbi->random(32),
                    "code" => $app->xss($_POST['code']),
                    "name" => $app->xss($_POST['name']),
                    "phone" => $app->xss($_POST['phone']),
                    "email" => $app->xss($_POST['email']),
                    "address" => $app->xss($_POST['address']),
                    "province" => $app->xss($_POST['province']),
                    "district" => $app->xss($_POST['district']),
                    "ward" => $app->xss($_POST['ward']),
                    "nationality" => $app->xss($_POST['nationality']),
                    "nation" => $app->xss($_POST['nation']),
                    "idtype" => $app->xss($_POST['idtype']),
                    "idcode" => $app->xss($_POST['idcode']),
                    "iddate" => $app->xss($_POST['iddate']),
                    "idplace" => $app->xss($_POST['idplace']),
                    // "same_address"	=> $app->xss($_POST['same_address']),
                    // "permanent_address" 	=> $permanent_address,
                    // "permanent_province" 	=> $permanent_province,
                    // "permanent_district" 	=> $permanent_district,
                    // "permanent_ward" 		=> $permanent_ward,
                    "birthday" => $app->xss($_POST['birthday']),
                    "gender" => $app->xss(!empty($_POST['gender']) ? $_POST['gender'] : -1),
                    "notes" => $app->xss($_POST['notes']),
                    "status" => $app->xss($_POST['status']),
                    "date" => date("Y-m-d H:i:s"),
                    "user" => $app->getSession("accounts")['id'] ?? null,
                    "stores" => $app->xss($_POST['stores']),
                    "office" => $app->xss($_POST['office']),
                ];
                $app->insert("personnels", $insert);
                $app->update("candidates", [
                    "deleted" => 1
                ], ["active" => $vars['id']]);
                $jatbi->logs('hrm', 'personnels-add', [$insert]);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            }
        }
    })->setPermissions(['applications.add']);

    // Route: Sửa đơn ứng tuyển
    $app->router("/applications-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template) {
        $vars['title'] = $jatbi->lang("Cập nhật trạng thái ứng viên");
        $data = $app->get("applications", "*", ["active" => $vars['id'], "deleted" => 0]);

        if (!$data) {
            echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
            return;
        }

        $vars['data'] = $data;

        if ($app->method() === 'GET') {
            // Mảng trạng thái value-text để render select
            $vars['status_options'] = [
                ["value" => "submitted", "text" => $jatbi->lang("Đã nộp")],
                ["value" => "screening", "text" => $jatbi->lang("Sàng lọc CV")],
                ["value" => "interview", "text" => $jatbi->lang("Phỏng vấn")],
                ["value" => "offered",   "text" => $jatbi->lang("Đã gửi offer")],
                ["value" => "hired",     "text" => $jatbi->lang("Đã nhận việc")],
                ["value" => "rejected",  "text" => $jatbi->lang("Từ chối")],
            ];

            echo $app->render($template . '/recruitment/applications-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $status = $app->xss($_POST['status'] ?? '');
            $notes = $app->xss($_POST['notes'] ?? '');

            if (empty($status)) {
                echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng chọn trạng thái")]);
                return;
            }

            $update = [
                "status" => $status,
                "notes"  => $notes,
            ];

            $app->update("applications", $update, ["id" => $data['id']]);
            $jatbi->logs('applications', 'status_update', $update);

            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật trạng thái thành công"), 'url' => $_SERVER['HTTP_REFERER']]);
        }
    })->setPermissions(['applications.edit']);

    // Route: Xóa đơn ứng tuyển
    $app->router("/applications-deleted", ['GET'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa hồ sơ theo dõi");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("applications", "*", ["active" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("applications", ["deleted" => 1], ["id" => $data['id']]);
                }
                $jatbi->logs('applications', 'applications-deleted', $datas);
                $jatbi->trash('/applications/applications-restore', "Xóa hồ sơ theo dõi: ", ["database" => 'applications', "data" => $boxid]);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['applications.deleted']);


    // Route: Danh sách phỏng vấn
    $app->router("/interviews", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores, $template, $accStore) {
        $vars['title'] = $jatbi->lang("Lịch phỏng vấn");
        if ($app->method() === 'GET') {
            if (count($stores) > 1) {
                array_unshift($stores, [
                    'value' => '',
                    'text' => $jatbi->lang('Tất cả')
                ]);
            }
            $vars['stores'] = $stores;
            $accounts = $app->select("accounts", ["id (value)", "name (text)"], ["deleted" => 0, "status" => 'A']);
            array_unshift($accounts, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars['accounts'] = $accounts;
            $results_options = [
                ["value" => "",  "text" => ""],
                ["value" => "passed",  "text" => "Đậu"],
                ["value" => "pending",  "text" => "Chờ"],
                ["value" => "failed",  "text" => "Trượt"],
            ];
            $vars['results'] = $results_options;
            echo $app->render($template . '/recruitment/interviews.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            // Get DataTables parameters
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? $app->xss($_POST['search']['value']) : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'ASC';
            $date_string = isset($_POST['date']) ? $app->xss($_POST['date']) : '';

            $result = isset($_POST['result']) ? [$_POST['result'], $_POST['result']] : '';
            $accounts = isset($_POST['accounts']) ? $_POST['accounts'] : '';

            // Process date range
            if ($date_string) {
                $date = explode('-', $date_string);
                $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date[0]))));
                $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date[1]))));
            } else {
                $date_from = date('Y-m-d 00:00:00'); // hôm nay
                $date_to = date('Y-m-d 23:59:59', strtotime('+7 days')); // 7 ngày tới
            }

            $stores = isset($_POST['stores']) ? $app->xss($_POST['stores']) : $accStore;

            $where = [
                "AND" => [
                    "interviews.deleted" => 0,
                    "interviews.schedule_time[<>]" => [$date_from, $date_to],
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];
            if (!empty($result)) {
                $where["AND"]["interviews.result"] = $result;
            }
            if (!empty($stores)) {
                $where["AND"]["interviews.stores"] = $stores;
            }
            if (!empty($accounts)) {
                $where["AND"]["interviews.user"] = $accounts;
            }
            if ($searchValue) {
                $where["AND"]["OR"] = [
                    "candidates.full_name[~]" => $searchValue,
                    "job_postings.title[~]" => $searchValue,
                    "personnels.name[~]" => $searchValue,
                ];
            }

            $joins = [
                "[>]accounts" => ["user" => "id"],
                "[>]stores" => ["stores" => "id"],
                "[>]applications" => ["application_id" => "id"],
                "[>]candidates" => ["applications.candidate_id" => "id"],
                "[>]job_postings" => ["applications.job_posting_id" => "id"],
                "[>]personnels" => ["interviewer_id" => "id"],
            ];

            $columns = [
                "interviews.id",
                "interviews.result",
                "interviews.schedule_time",
                "interviews.created_date",
                "interviews.active",
                "candidates.full_name",
                "job_postings.title",
                "personnels.name(interviewer_name)",
                "accounts.name(user_name)",
                "stores.name(store_name)"
            ];

            $count = $app->count("interviews", $joins, "*", [
                "AND" => $where['AND'],
            ]);

            $datas = [];
            $app->select("interviews", $joins, $columns, $where, function ($data) use (&$datas, $jatbi, $app) {
                $result_options = [
                    'pending'  => '<span class="badge bg-primary">Chờ</span>',
                    'passed'   => '<span class="badge bg-success">Đậu</span>',
                    'failed'   => '<span class="badge bg-danger">Trượt</span>',
                ];
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['active']]),
                    "full_name" => $data['full_name'],
                    "title" => $data['title'],
                    "interviewer_name" => $data['interviewer_name'],
                    "schedule_time" => date('d/m/Y H:i', strtotime($data['schedule_time'])),
                    "result" => $result_options[$data['result']] ?? $data['result'],
                    "stores" => $data['store_name'],
                    "user" => $data['user_name'],
                    "created_date" => $data['created_date'],
                    "action" => $app->component("action", [
                        "button" => [
                            ['type' => 'button', 'name' => $jatbi->lang("Sửa"), 'permission' => ['interviews.edit'], 'action' => ['data-url' => '/recruitment/interviews-edit/' . $data['active'], 'data-action' => 'modal']],
                            ['type' => 'button', 'name' => $jatbi->lang("Xóa"), 'permission' => ['interviews.deleted'], 'action' => ['data-url' => '/recruitment/interviews-deleted?box=' . $data['active'], 'data-action' => 'modal']],
                        ]
                    ]),
                ];
            });

            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas
            ]);
        }
    })->setPermissions(['interviews']);

    // Route: Thêm phỏng vấn
    $app->router("/interviews-add", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $stores, $accStore) {
        $vars['title'] = $jatbi->lang("Tạo lịch phỏng vấn");
        if ($app->method() === 'GET') {
            // Lấy danh sách ứng dụng (applications) để hiển thị trong form
            $applications = $app->select("applications", [
                "[>]candidates" => ["candidate_id" => "id"],
                "[>]job_postings" => ["job_posting_id" => "id"],
            ], [
                "applications.id",
                "candidates.full_name",
                "job_postings.title",
            ], [
                "AND" => [
                    "applications.deleted" => 0,
                    "applications.status" => 'interview',
                    "candidates.deleted" => 0, // Chỉ lấy ứng viên chưa bị xóa
                    "job_postings.deleted" => 0 // Chỉ lấy tin tuyển dụng chưa bị xóa
                ]
            ]);

            // Chuyển đổi kết quả sang định dạng {value, text}
            $vars['applications'] = array_map(function ($row) {
                return [
                    'value' => $row['id'],
                    'text' => $row['full_name'] . ' - ' . $row['title']
                ];
            }, $applications);

            // Lấy danh sách nhân sự (personnels)
            $vars['interviewer_ids'] = $app->select("personnels", [
                "id (value)",
                "name (text)"
            ], [
                "AND" => [
                    "deleted" => 0,
                    "status" => 'A'
                ]
            ]);

            // Gửi danh sách cửa hàng
            $vars['stores'] = $stores;
            echo $app->render($template . '/recruitment/interviews-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $error = [];
            if (empty($app->xss($_POST['application_id'])) || empty($app->xss($_POST['interviewer_id'])) || empty($app->xss($_POST['schedule_time']))) {
                $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống các trường bắt buộc")];
            } elseif (count($stores) > 1 && empty($app->xss($_POST['stores']))) {
                $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng chọn cửa hàng")];
            }
            if (empty($error)) {
                $input_stores = count($stores) > 1 ? $app->xss($_POST['stores']) : $app->get("stores", "id", ["id" => $accStore, "status" => 'A', "deleted" => 0]);
                $insert = [
                    "application_id" => $app->xss($_POST['application_id']),
                    "interviewer_id" => $app->xss($_POST['interviewer_id']),
                    "schedule_time" => $app->xss($_POST['schedule_time']),
                    "notes" => $app->xss($_POST['notes'] ?? ''),
                    "user" => $app->getSession("accounts")['id'],
                    "created_date" => date("Y-m-d H:i:s"),
                    "active" => $jatbi->active(32),
                    "stores" => $input_stores,
                ];
                $app->insert("interviews", $insert);
                $jatbi->logs('interviews', 'add', $insert);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Thêm thành công")]);
            } else {
                echo json_encode($error);
            }
        }
    })->setPermissions(['interviews.add']);

    // Route: Sửa phỏng vấn
    $app->router("/interviews-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $template, $stores, $accStore) {
        $vars['title'] = $jatbi->lang("Sửa lịch phỏng vấn");
        $data = $app->get("interviews", "*", ["active" => $vars['id'], "deleted" => 0]);
        if (!$data) {
            echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
            return;
        }
        $vars['data'] = $data;
        if ($app->method() === 'GET') {
            // Lấy danh sách ứng dụng (applications) để hiển thị trong form
            $applications = $app->select("applications", [
                "[>]candidates" => ["candidate_id" => "id"],
                "[>]job_postings" => ["job_posting_id" => "id"],
            ], [
                "applications.id",
                "candidates.full_name",
                "job_postings.title",
            ], [
                "AND" => [
                    "applications.deleted" => 0,
                    "applications.status" => 'interview',
                    "candidates.deleted" => 0, // Chỉ lấy ứng viên chưa bị xóa
                    "job_postings.deleted" => 0 // Chỉ lấy tin tuyển dụng chưa bị xóa
                ]
            ]);

            // Chuyển đổi kết quả sang định dạng {value, text}
            $vars['applications'] = array_map(function ($row) {
                return [
                    'value' => $row['id'],
                    'text' => $row['full_name'] . ' - ' . $row['title']
                ];
            }, $applications);

            // Lấy danh sách nhân sự (personnels)
            $vars['interviewer_ids'] = $app->select("personnels", [
                "id (value)",
                "name (text)"
            ], [
                "AND" => [
                    "deleted" => 0,
                    "status" => 'A'
                ]
            ]);

            // Gửi danh sách cửa hàng
            $vars['stores'] = $stores;
            $results_options = [
                ["value" => "",  "text" => ""],
                ["value" => "passed",  "text" => "Đậu"],
                ["value" => "pending",  "text" => "Chờ"],
                ["value" => "failed",  "text" => "Trượt"],
            ];
            $vars['result_options'] = $results_options;
            $vars['edit'] = true;
            echo $app->render($template . '/recruitment/interviews-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $error = [];
            if (empty($app->xss($_POST['application_id'])) || empty($app->xss($_POST['interviewer_id'])) || empty($app->xss($_POST['schedule_time']))) {
                $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống các trường bắt buộc")];
            } elseif (count($stores) > 1 && empty($app->xss($_POST['stores']))) {
                $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng chọn cửa hàng")];
            }
            if (empty($error)) {
                $input_stores = count($stores) > 1 ? $app->xss($_POST['stores']) : $app->get("stores", "id", ["id" => $accStore, "status" => 'A', "deleted" => 0]);
                $update = [
                    "application_id" => $app->xss($_POST['application_id']),
                    "interviewer_id" => $app->xss($_POST['interviewer_id']),
                    "schedule_time" => $app->xss($_POST['schedule_time']),
                    "result" => $app->xss($_POST['result']),
                    "notes" => $app->xss($_POST['notes'] ?? ''),
                    "stores" => $input_stores,
                ];
                $app->update("interviews", $update, ["id" => $data['id']]);
                $jatbi->logs('interviews', 'edit', $update);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode($error);
            }
        }
    })->setPermissions(['interviews.edit']);

    // Route: Xóa phỏng vấn
    $app->router("/interviews-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa phỏng vấn");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("interviews", "*", ["active" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("interviews", ["deleted" => 1], ["id" => $data['id']]);
                }
                $jatbi->logs('interviews', 'interviews-deleted', $datas);
                $jatbi->trash('/interviews/interviews-restore', "Xóa lịch phỏng vấn: ", ["database" => 'applications', "data" => $boxid]);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['interviews.deleted']);
})->middleware('login');

$app->router("/cv/upload", 'POST', function ($vars) use ($app, $jatbi, $setting) {
    $app->header(['Content-Type' => 'application/json']);
    $account = $app->get("accounts", "*", ["id" => $app->getSession("accounts")['id']]);
    if (!$account) {
        echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Tài khoản không hợp lệ")]);
        return;
    }

    if (!isset($_FILES['cv_file']) || $_FILES['cv_file']['error'] !== UPLOAD_ERR_OK) {
        $error_message = $jatbi->lang("Vui lòng tải lên file");
        if (isset($_FILES['cv_file']['error'])) {
            switch ($_FILES['cv_file']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                    $error_message = "File quá lớn (giới hạn: upload_max_filesize)";
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $error_message = "File quá lớn (giới hạn: MAX_FILE_SIZE)";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $error_message = "File chỉ được tải lên một phần";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $error_message = "Không có file được tải lên";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $error_message = "Thiếu thư mục tạm";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $error_message = "Không thể ghi file";
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $error_message = "Tiện ích PHP chặn upload";
                    break;
            }
        }
        error_log('Upload error: ' . print_r($_FILES, true));
        echo json_encode(['status' => 'error', 'content' => $error_message, 'debug' => $_FILES]);
        return;
    }

    $handle = $app->upload($_FILES['cv_file']);
    $upload_dir = $setting['uploads'] . '/' . $account['active'] . '/cvs/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    $new_file_id = $jatbi->active();
    $allowed_types = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];

    if ($handle->uploaded) {
        $handle->allowed = $allowed_types;
        $handle->file_max_size = 10485760; // 10MB
        $handle->file_new_name_body = $new_file_id;
        $handle->Process($upload_dir);
    }

    if ($handle->processed) {
        $file_url = $upload_dir . $handle->file_dst_name;
        $file_data = [
            'account' => $account['id'],
            'category' => 0,
            'name' => $handle->file_src_name,
            'extension' => $jatbi->getFileExtension($handle->file_src_name),
            'url' => $file_url,
            'size' => $handle->file_src_size,
            'mime' => $handle->file_src_mime,
            'permission' => 0,
            'active' => $new_file_id,
            'date' => date('Y-m-d H:i:s'),
            'modify' => date('Y-m-d H:i:s'),
            'deleted' => 0,
            'data' => json_encode([
                'file_src_name' => $handle->file_src_name,
                'file_src_name_body' => $handle->file_src_name_body,
                'file_src_name_ext' => $handle->file_src_name_ext,
                'file_src_pathname' => $handle->file_src_pathname,
                'file_src_mime' => $handle->file_src_mime,
                'file_src_size' => $handle->file_src_size
            ])
        ];
        // Chèn file và lấy ID
        $app->insert('files', $file_data);
        $file_id = $app->pdo()->lastInsertId(); // Lấy ID bản ghi vừa chèn

        // Lưu token vào files_token
        $token = $jatbi->active();
        $app->insert('files_token', [
            'data' => $file_id,
            'token' => $token,
            'date' => date('Y-m-d H:i:s')
        ]);

        $jatbi->logs('files', 'upload-cvs', [
            'file' => $file_data['name'],
            'active' => $new_file_id,
            'account' => $account['id']
        ]);
        echo json_encode(['status' => 'success', 'active' => $new_file_id, 'name' => $file_data['name'], 'token' => $token]);
        $handle->clean();
    } else {
        echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Upload file thất bại: ") . ($handle->error ?? 'Lỗi không xác định')]);
    }
})->middleware('login');
