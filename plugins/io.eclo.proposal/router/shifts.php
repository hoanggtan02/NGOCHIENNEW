<?php
    // shifts.php
    use ECLO\App;
    
    $ClassProposals = new Proposal($app);
    $app->setValueData('process',$ClassProposals);
    $getprocess = $app->getValueData('process');
    $template = __DIR__.'/../templates';
    $jatbi = $app->getValueData('jatbi');
    $setting = $app->getValueData('setting');
    $common = $jatbi->getPluginCommon('io.eclo.invoices');
    $account = $app->getValueData('account');
    $app->group("/shifts",function($app) use ($jatbi,$template, $setting,$common,$account,$getprocess){

        $app->router("/list", ['GET','POST'], function($vars) use ($app, $jatbi, $template, $common,$account) {
            if($app->method()==='GET'){
                $vars['title'] = $jatbi->lang("Kết ca");
                echo $app->render($template.'/shifts/list.html', $vars);
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
                $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
                $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
                $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
                $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'date';
                $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
                $status = isset($_POST['status']) ? [$_POST['status'],$_POST['status']] : '';
                $stores = isset($_POST['stores']) ? $_POST['stores'] : $jatbi->stores();
                $booking = isset($_POST['booking']) ? $_POST['booking'] : '';
                $account = isset($_POST['account']) ? $_POST['account'] : '';
                $customers = isset($_POST['customers']) ? $_POST['customers'] : '';
                $rooms = isset($_POST['rooms']) ? $_POST['rooms'] : '';
                $dateRange = isset($_POST['date']) ? trim($_POST['date']) : '';
                $dateStart = null;
                $dateEnd = null;
                if (!empty($dateRange) && strpos($dateRange, ' - ') !== false) {
                    [$dateStart, $dateEnd] = $jatbi->parseDateRange($dateRange);
                }
                $where = [
                    "AND" => [
                        "OR" => [
                            "shifts.id[~]" => $searchValue,
                            "accounts.name[~]" => $searchValue,
                        ],
                        "shifts.deleted" => 0,
                    ],
                    "LIMIT" => [$start, $length],
                    "ORDER" => [$orderName => strtoupper($orderDir)]
                ];
                if (!empty($stores)) {
                    $where["AND"]["shifts.stores"] = $stores;
                }
                if (!empty($account)) {
                    $where["AND"]["shifts.account"] = $account;
                }
                if ($dateStart && $dateEnd) {
                    $where["AND"]["shifts.date_start[<>]"] = [$dateStart, $dateEnd];
                }
                $count = $app->count("shifts",[
                    "[>]accounts" => ["account" => "id"],
                    "[>]stores" => ["stores" => "id"]
                ],["@shifts.id",],["AND" => $where['AND']]);

                $total = [];
                $app->select("shifts", [
                    "[>]accounts" => ["account" => "id"],
                    "[>]stores" => ["stores" => "id"]
                ],[
                    "shifts.id",
                    "shifts.notes",
                    "shifts.active",
                    "shifts.total",
                    "shifts.date",
                    "shifts.total_debit",
                    "shifts.count_bill",
                    "shifts.count_debit",
                    "accounts.name (account)",
                    "stores.name (stores_name)",
                    "stores.id (stores)",
                ], $where, function ($data) use (&$datas, &$total, $jatbi,$app,$common) {
                    $buttons[] =  [
                            'type' => 'button',
                            'name' => $jatbi->lang("Xem"),
                            'permission' => ['shifts'],
                            'action' => ['data-url' => '/shifts/views/'.$data['active'], 'data-action' => 'modal']
                    ];
                    $datas[] = [
                        "date" => $jatbi->date($data['date']),
                        "id" => '#'.$data['id'],
                        "total" => $jatbi->money($data['total'] ?? 0),
                        "total_debit" => $jatbi->money($data['total_debit'] ?? 0),
                        "count_bill" => $jatbi->money($data['count_bill'] ?? 0),
                        "count_debit" => $jatbi->money($data['count_debit'] ?? 0),
                        "account" => $data['account'],
                        "stores" => $data['stores_name'],
                        "action" => $app->component("action",["button" =>$buttons]),
                    ];
                });
                echo json_encode([
                    "draw" => $draw,
                    "recordsTotal" => $count,
                    "recordsFiltered" => $count,
                    "data" => $datas ?? [],
                ]);
            }
        })->setPermissions(['shifts']);

        $app->router("/add", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$account,$getprocess) {
            $vars['title'] = $jatbi->lang("Thêm kết ca");
            if($app->method()==='GET'){
                $vars['data'] = [
                    "stores" => '',
                ];
                $vars['payments'] = $app->select("payments", [
                    "[>]stores_linkables" => ["id" => "data","AND"=>["stores_linkables.type"=>"payments"]]
                ],[
                    "@payments.id",
                    "payments.name"
                ], [
                    "payments.deleted"=>0,
                    "payments.status"=> "A",
                    "stores_linkables.stores" => $jatbi->stores(),
                ]);
                [$start_date_1, $end_date_1] = $jatbi->parseDateRange();
                $summary = $app->get("invoices", [
                    "tongbill"      => App::raw("COUNT(CASE WHEN status IN (2,20) THEN id ELSE NULL END)"),
                    "tongbillno"     => App::raw("COUNT(CASE WHEN status IN (20) THEN id ELSE NULL END)"),
                    "tongtien"         => App::raw("SUM(CASE WHEN status IN (2) THEN payments ELSE 0 END)"),
                    "tongno"         => App::raw("SUM(CASE WHEN status IN (20) THEN payments ELSE 0 END)"),
                ],[
                    "date_start[<>]" => [$start_date_1,$end_date_1],
                    "stores" => $jatbi->stores('ID'),
                    "deleted" => 0
                ]);
                $vars['summary'] = $summary;
                $payments_total = [];
                foreach($vars['payments'] as $payment){
                    $payments_total[$payment['id']] = (float) $app->sum("invoices_payments", 
                        [
                            "[<]invoices" => ["invoices" => "id"]
                        ],
                            "invoices_payments.total",
                        [
                            "invoices.status" => [2,20],
                            "invoices.deleted" => 0,
                            "invoices.date_start[<>]" => [$start_date_1, $end_date_1],
                            "invoices.stores" => $jatbi->stores('ID'),
                            "invoices_payments.payment" => $payment['id'],
                            "invoices_payments.deleted" => 0,
                            "invoices_payments.status" => 2,
                            "invoices_payments.total[>]" => 0,
                            "invoices_payments.stores" => $jatbi->stores('ID'),
                        ]
                    ) ?? 0;
                }
                $vars['payments_total'] = $payments_total;
                $vars['date'] = $start_date_1;
                echo $app->render($template.'/shifts/shifts-post.html', $vars, $jatbi->ajax());
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                [$start_date_1, $end_date_1] = $jatbi->parseDateRange();
                $checker = $app->get("shifts","id",["deleted"=>0,"stores"=>$jatbi->stores("ID"),"date"=>$start_date_1]);
                $checkAccount = $app->count("proposal_diagram_accounts","*",["account"=>$account['id'],"deleted"=>0]);
                if($app->xss($_POST['date'])==''){
                    $error = ["status"=>"error","content"=>$jatbi->lang("Vui lòng không để trống")];
                }
                elseif($checker>0){
                    $error = ["status"=>"error","content"=>$jatbi->lang("Ngày này đã kết ca")];
                }
                elseif($checkAccount==0) {
                    $error = ["status"=>"error","content"=>$jatbi->lang("Tài khoản của bạn không thuộc sơ đồ tổ tài khoản, không thể gửi đề xuất")];
                }
                if(empty($error)){
                    $payments = $app->select("payments", [
                        "[>]stores_linkables" => ["id" => "data","AND"=>["stores_linkables.type"=>"payments"]]
                    ],[
                        "@payments.id",
                        "payments.name",
                        "payments.form",
                        "payments.target",
                        "payments.workflows",
                    ], [
                        "payments.deleted"=>0,
                        "payments.status"=> "A",
                        "stores_linkables.stores" => $jatbi->stores(),
                    ]);
                    $summary = $app->get("invoices", [
                        "tongbill"      => App::raw("COUNT(CASE WHEN status IN (2,20) THEN id ELSE NULL END)"),
                        "tongbillno"     => App::raw("COUNT(CASE WHEN status IN (20) THEN id ELSE NULL END)"),
                        "tongtien"         => App::raw("SUM(CASE WHEN status IN (2) THEN payments ELSE 0 END)"),
                        "tongno"         => App::raw("SUM(CASE WHEN status IN (20) THEN payments ELSE 0 END)"),
                    ],[
                        "date_start[<>]" => [$start_date_1,$end_date_1],
                        "stores" => $jatbi->stores('ID'),
                        "deleted" => 0
                    ]);
                    $payments_total = [];
                    $insert = [
                        "date"          => $start_date_1,
                        "total"         => $summary['tongtien'],
                        "total_debit"   => $summary['tongno'],
                        "count_bill"    => $summary['tongbill'],
                        "count_debit"   => $summary['tongbillno'],
                        "notes"         => $app->xss($_POST['notes']),
                        "account"       => $account['id'],
                        "stores"        => $jatbi->stores('ID'),
                        "modify"        => date("Y-m-d H:i:s"),
                        "active"        => $jatbi->active(),
                    ];
                    $app->insert("shifts",$insert);
                    $getID = $app->id();
                    foreach($payments as $payment){
                        $payments_total[$payment['id']] = (float) $app->sum("invoices_payments", 
                            [
                                "[<]invoices" => ["invoices" => "id"]
                            ],
                            "invoices_payments.total",
                            [
                                "invoices.status" => [2,20],
                                "invoices.deleted" => 0,
                                "invoices.date_start[<>]" => [$start_date_1, $end_date_1],
                                "invoices.stores" => $jatbi->stores('ID'),
                                "invoices_payments.payment" => $payment['id'],
                                "invoices_payments.deleted" => 0,
                                "invoices_payments.status" => 2,
                                "invoices_payments.total[>]" => 0,
                                "invoices_payments.stores" => $jatbi->stores('ID'),
                            ]
                        ) ?? 0;
                        $details = [
                            "shifts" => $getID,
                            "payments" => $payment['id'],
                            "form" => $payment['form'],
                            "target" => $payment['target'],
                            "workflows" => $payment['workflows'],
                            "total" => $payments_total[$payment['id']]
                        ];
                        $app->insert("shifts_details",$details);
                        if($details['total']>0 && $details['form']>0 && $details['target']>0 && $details['workflows']>0){
                            $proposal = [
                                "type" => 1,
                                "form" => $details['form'],
                                "workflows" => $details['workflows'],
                                "category" => $details['target'],
                                "price" => $details['total'],
                                "reality" => $details['total'],
                                "date" => $insert['date'],
                                "modify" => date("Y-m-d H:i:s"),
                                "create" => date("Y-m-d H:i:s"),
                                "account" => $account['id'],
                                "vat_type" => 1,
                                "status" => 0,
                                "code" => time(),
                                "stores" => $jatbi->stores('ID'),
                                "stores_id" => $jatbi->stores('ID'),
                                "active" => $jatbi->active(),
                                "auto" => 1,
                                "shifts" => $details['shifts'],
                                "content" => 'Kết ca '.$payment['name'],
                            ];
                            $app->insert("proposals",$proposal);
                            $ProposalID = $app->id();
                            $jatbi->logs('proposal','proposal-create',$proposal);
                            $insert_accounts = [
                                "account" => $account['id'],
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
                                    "account" => $account['id'],
                                    "node"  => $process[0]['node_id'],
                                    "approval" => 1,
                                    "approval_date" => date("Y-m-d H:i:s"),
                                ];
                                $app->insert("proposal_process",$proposal_process);
                                $proposal_process_ID = $app->id();
                                $proposal_process_logs = [
                                    "proposal" => $ProposalID,
                                    "date" => date("Y-m-d H:i:s"),
                                    "account" => $account['id'],
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
                                $jatbi->notification($account['id'],$process[1]['approver_account_id'],'Đề xuất #'.$ProposalID,$content_notification,'/proposal/views/'.$proposal['active'],'');
                                $insert_accounts = [
                                    "account" => $process[1]['approver_account_id'],
                                    "proposal" => $ProposalID,
                                    "date" => date("Y-m-d H:i:s"),
                                ];
                                $app->insert("proposal_accounts",$insert_accounts);
                                foreach($process[1]['follows'] as $follow){
                                    $jatbi->notification($account['id'],$follow,'Theo dõi đề xuất #'.$data['id'],$content_notification,'/proposal/views/'.$data['active'],'');
                                    $insert_accounts_follow = [
                                        "account" => $follow,
                                        "proposal" => $data['id'],
                                        "date" => date("Y-m-d H:i:s"),
                                    ];
                                    $app->insert("proposal_accounts",$insert_accounts_follow);
                                }
                            }
                        }
                    }
                    echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                    // $jatbi->logs('events','events-add',$insert);
                }
                else {
                    echo json_encode($error);
                }
            }
        })->setPermissions(['shifts.add']);

        $app->router("/views/{active}", 'GET', function($vars) use ($app, $jatbi, $template, $common,$account) {
            $vars['data'] = $app->get("shifts","*",["active"=>$vars['active'],"deleted"=>0]);
            $vars['details'] = $app->select("shifts_details",[
                "[>]payments" => ["payments" => "id"]
            ],[
                "shifts_details.total",
                "payments.name (payments)",
            ],[
                "shifts_details.shifts"=>$vars['data']['id'],
            ]);
            $vars['title'] = $jatbi->lang("Kết ca: #").$vars['data']['id'];
            echo $app->render($template.'/shifts/views.html', $vars , $jatbi->ajax());
        })->setPermissions(['login']);

    })->middleware('login');
    $app->group("/proposal",function($app) use ($jatbi,$template, $setting,$common,$account,$getprocess){

        $app->router("/invoices/{active}", 'GET', function($vars) use ($app, $jatbi, $template,$account,$getprocess) {
            $vars['title'] = $jatbi->lang("Đề xuất ghi nợ");
            $data = $app->get("invoices","*",["deleted"=>0,"active"=>$vars['active']]);
            $vars['data'] = $data;
            echo $app->render($template.'/shifts/invoices.html', $vars, $jatbi->ajax());
        })->setPermissions(['shifts.add']);

        $app->router("/invoices/{active}", 'POST', function($vars) use ($app, $jatbi, $template,$account,$getprocess) {
            $vars['title'] = $jatbi->lang("Đề xuất ghi nợ");
            $data = $app->get("invoices","*",["deleted"=>0,"active"=>$vars['active']]);
            $input = json_decode(file_get_contents('php://input'), true);
            if($input){
                $insert = $input['data'] ?? null;
            }
            else {
                $insert = [
                    "notes" => $app->xss($_POST['notes']),
                    "date" => $app->xss($_POST['date']),
                    "form" => $app->xss($_POST['form']),
                    "workflows" => $app->xss($_POST['workflows']),
                    "category" => $app->xss($_POST['category']),
                    "stores" => $data['stores'],
                    "stores" => $data['stores'],
                ];
            }
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            if($app->xss($insert['date'])=='' || $app->xss($insert['form'])=='' || $app->xss($insert['workflows'])=='' || $app->xss($insert['category'])==''){
                $error = ["status"=>"error","content"=>$jatbi->lang("Vui lòng không để trống")];
            }
            if(empty($error)){
                $notes = $app->xss($insert['notes']);
                $date = $app->xss($insert['date']);
                $form = $app->xss($insert['form']);
                $workflows = $app->xss($insert['workflows']);
                $category = $app->xss($insert['category']);
                $stores = $app->xss($insert['stores']);
                if(isset($account['id'])){
                    $account = $account;
                }
                else {
                    $account = $app->get("accounts","*",["id"=>$insert['account']]);
                }
                if($data['payments']>0){
                    $price = $data['payments']-$data['total_pay'];
                    $proposal = [
                        "type" => 1,
                        "form" => $form,
                        "workflows" => $workflows,
                        "category" => $category,
                        "price" => $price,
                        "reality" => $price,
                        "date" => $date,
                        "modify" => date("Y-m-d H:i:s"),
                        "create" => date("Y-m-d H:i:s"),
                        "account" => $account['id'],
                        "vat_type" => 1,
                        "status" => 0,
                        "code" => time(),
                        "stores" => $stores,
                        "stores_id" => $stores,
                        "active" => $jatbi->active(),
                        "auto" => 0,
                        "invoices" => $data['id'],
                        "customers" => $data['customers'],
                        "notes" => $notes,
                        "content" => 'Ghi nợ hóa đơn #'.$data['id'],
                    ];
                    $app->insert("proposals",$proposal);
                    $ProposalID = $app->id();
                    $jatbi->logs('proposal','proposal-create',$proposal);
                    $insert_accounts = [
                        "account" => $account['id'],
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
                            "account" => $account['id'],
                            "node"  => $process[0]['node_id'],
                            "approval" => 1,
                            "approval_date" => date("Y-m-d H:i:s"),
                        ];
                        $app->insert("proposal_process",$proposal_process);
                        $proposal_process_ID = $app->id();
                        $proposal_process_logs = [
                            "proposal" => $ProposalID,
                            "date" => date("Y-m-d H:i:s"),
                            "account" => $account['id'],
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
                        $jatbi->notification($account['id'],$process[1]['approver_account_id'],'Đề xuất #'.$ProposalID,$content_notification,'/proposal/views/'.$proposal['active'],'');
                        $insert_accounts = [
                            "account" => $process[1]['approver_account_id'],
                            "proposal" => $ProposalID,
                            "date" => date("Y-m-d H:i:s"),
                        ];
                        $app->insert("proposal_accounts",$insert_accounts);
                        foreach($process[1]['follows'] as $follow){
                            $jatbi->notification($account['id'],$follow,'Theo dõi đề xuất #'.$ProposalID,$content_notification,'/proposal/views/'.$data['active'],'');
                            $insert_accounts_follow = [
                                "account" => $follow,
                                "proposal" => $data['id'],
                                "date" => date("Y-m-d H:i:s"),
                            ];
                            $app->insert("proposal_accounts",$insert_accounts_follow);
                        }
                    }
                }
                echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                // $jatbi->logs('events','events-add',$insert);
            }
            else {
                echo json_encode($error);
            }
        });

    });
 ?>