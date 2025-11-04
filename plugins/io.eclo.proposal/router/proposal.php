<?php
    use ECLO\App;
    $ClassProposals = new Proposal($app);
    $template = __DIR__.'/../templates';
    $app->setValueData('process',$ClassProposals);
    $process = $app->getValueData('process');
    $jatbi = $app->getValueData('jatbi');
    $setting = $app->getValueData('setting');
    $account = $app->getValueData('account');
    $common = $jatbi->getPluginCommon('io.eclo.proposal');

    $app->group("/proposal",function($app) use($setting,$jatbi, $common, $template,$account,$process) {
        $app->router("", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$account,$common) {
            if($app->method()==='GET'){
                $vars['title'] = $jatbi->lang("Đề xuất");
                $vars['status'] = array_map(function($item) {
                    return [
                        'text' => $item['name'],
                        'value' => $item['id']
                    ];
                }, $common['proposal-status']);
                $vars['type'] = array_map(function($item) {
                    return [
                        'text' => $item['name'],
                        'value' => $item['id']
                    ];
                }, $common['proposal']);
                $vars['accounts'] = $app->select("accounts",["id (value)","name (text)"],["deleted"=>0,"status"=>"A"]);
                $vars['forms'] = $app->select("proposal_form",["id (value)","name (text)"],["deleted"=>0,"status"=>"A"]);
                $vars['categorys'] = $app->select("proposal_target",["id (value)","name (text)"],["deleted"=>0,"status"=>"A"]);
                $vars['workflows'] = $app->select("proposal_workflows",["id (value)","name (text)"],["deleted"=>0,"status"=>"A"]);
                $vars['objects'] = $app->select("proposal_objects",["id (value)","name (text)"],["deleted"=>0,"status"=>"A"]);
                $where = ["proposals.deleted" => 0];
                if ($jatbi->permission(['proposal.full']) != 'true') {
                    $where["AND"]["OR"] = [
                        "proposal_accounts.account" => $account['id'],
                        "AND" => [
                            "proposals.account" => $account['id'],
                        ]
                    ];
                }
                else {
                    $where["AND"]["OR"] = [
                        "proposals.status[!]" => 0,
                        "AND" => [
                            "proposals.account" => $account['id'],
                            "proposals.status" => 0
                        ]
                    ];
                }
                $proposal_ids = $app->select("proposals", [
                    "[>]proposal_accounts" => ["id" => "proposal"]
                ], "proposals.id", $where);
                $proposal_ids = array_unique($proposal_ids);
                $summary_where = ["id" => $proposal_ids];
                if (empty($proposal_ids)) {
                    $summary = [];
                } else {
                    $summary = $app->get("proposals", [
                        "tongdexuat"      => App::raw("COUNT(CASE WHEN status IN (1,2,3,4,5,10,20) THEN id ELSE NULL END)"),
                        "tongdaduyet"     => App::raw("COUNT(CASE WHEN status IN (2,4,5) THEN id ELSE NULL END)"),
                        "tongchi"         => App::raw("SUM(CASE WHEN status IN (1,10,2,4,5) AND type = 2 THEN price ELSE 0 END)"),
                        "tongthu"         => App::raw("SUM(CASE WHEN status IN (1,10,2,4,5) AND type = 1 THEN price ELSE 0 END)"),
                        "duyetchi"        => App::raw("SUM(CASE WHEN status IN (2,4,5) AND type = 2 THEN price ELSE 0 END)"),
                        "duyetthu"        => App::raw("SUM(CASE WHEN status IN (2,4,5) AND type = 1 THEN price ELSE 0 END)"),
                        "tongnhap"        => App::raw("COUNT(CASE WHEN status = 0 THEN id ELSE NULL END)"),
                        "tongcho"         => App::raw("COUNT(CASE WHEN status = 1 THEN id ELSE NULL END)"),
                        "tongduyet"       => App::raw("COUNT(CASE WHEN status = 2 THEN id ELSE NULL END)"),
                        "tongkhongduyet"  => App::raw("COUNT(CASE WHEN status = 3 THEN id ELSE NULL END)"),
                        "tongbuttoan"     => App::raw("COUNT(CASE WHEN status = 4 THEN id ELSE NULL END)"),
                        "tongyeucauhuy"   => App::raw("COUNT(CASE WHEN status = 10 THEN id ELSE NULL END)"),
                        "tonghuy"         => App::raw("COUNT(CASE WHEN status = 20 THEN id ELSE NULL END)"),
                        "over_5"          => App::raw("COUNT(CASE WHEN status IN (1,2,3,4,5,10,20) AND DATEDIFF(NOW(), date) >= 5  THEN id END)"),
                        "over_10"         => App::raw("COUNT(CASE WHEN status IN (1,2,3,4,5,10,20) AND DATEDIFF(NOW(), date) >= 10 THEN id END)"),
                        "over_15"         => App::raw("COUNT(CASE WHEN status IN (1,2,3,4,5,10,20) AND DATEDIFF(NOW(), date) >= 15 THEN id END)"),
                        "over_20"         => App::raw("COUNT(CASE WHEN status IN (1,2,3,4,5,10,20) AND DATEDIFF(NOW(), date) >= 20 THEN id END)"),
                        "over_25"         => App::raw("COUNT(CASE WHEN status IN (1,2,3,4,5,10,20) AND DATEDIFF(NOW(), date) >= 25 THEN id END)"),
                        "over_30"         => App::raw("COUNT(CASE WHEN status IN (1,2,3,4,5,10,20) AND DATEDIFF(NOW(), date) >= 30 THEN id END)")
                    ], $summary_where);
                }
                $vars['total'] = $summary;
                $vars['proposal_status'] = $common['proposal-status'];
                if (!empty($_GET['date'])) {
                    $dates = explode(" - ", $_GET['date']);
                    if (count($dates) == 2) {
                        $from = DateTime::createFromFormat('d/m/Y', trim($dates[0]));
                        $to   = DateTime::createFromFormat('d/m/Y', trim($dates[1]));
                        if ($from && $to) {
                            $vars['date'] = $from->format("d/m/Y").' - '.$to->format("d/m/Y");
                        }
                    } else {
                        $date = DateTime::createFromFormat('d/m/Y', trim($dates[0]));
                        if ($date) {
                            $vars['date'] = $date->format("d/m/Y").' - '.$date->format("d/m/Y");
                        }
                    }
                } 
                else {
                    $from = new DateTime("-1 year");
                    $to   = new DateTime("+1 year");
                    $vars['date'] = $from->format("d/m/Y").' - '.$to->format("d/m/Y");
                }
                if (!empty($_GET['create'])) {
                    $creates = explode(" - ", $_GET['create']);
                    if (count($creates) == 2) {
                        $from = DateTime::createFromFormat('d/m/Y', trim($creates[0]));
                        $to   = DateTime::createFromFormat('d/m/Y', trim($creates[1]));
                        if ($from && $to) {
                            $vars['create'] = $from->format("d/m/Y").' - '.$to->format("d/m/Y");
                        }
                    } else {
                        $create = DateTime::createFromFormat('d/m/Y', trim($creates[0]));
                        if ($create) {
                            $vars['create'] = $create->format("d/m/Y").' - '.$create->format("d/m/Y");
                        }
                    }
                } else {
                    $from = new DateTime("-1 year");
                    $to   = new DateTime("now");
                    $vars['create'] = $from->format("d/m/Y").' - '.$to->format("d/m/Y");
                }

                echo $app->render($template.'/proposal/proposal.html', $vars);
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
                $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
                $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
                $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
                $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
                $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
                $status = isset($_POST['status']) ? $_POST['status'] : [0,1,2,3,4,5,10,20];
                $stores = isset($_POST['stores']) ? $_POST['stores'] : $jatbi->stores();
                $IDProposal = isset($_POST['id']) ? $_POST['id'] : '';
                $category = isset($_POST['category']) ? $_POST['category'] : '';
                $type = isset($_POST['type']) ? $_POST['type'] : '';
                $workflows = isset($_POST['workflows']) ? $_POST['workflows'] : '';
                $form = isset($_POST['form']) ? $_POST['form'] : '';
                $customers = isset($_POST['customers']) ? $_POST['customers'] : '';
                $vendors = isset($_POST['vendors']) ? $_POST['vendors'] : '';
                $objects = isset($_POST['objects']) ? $_POST['objects'] : '';
                $accounts_search = isset($_POST['accounts']) ? $_POST['accounts'] : '';
                $Searchaccount = isset($_POST['account']) ? $_POST['account'] : '';
                $where = [
                    "AND" => [
                        "OR" => [
                            "proposals.content[~]" => $searchValue,
                            "proposals.code[~]" => $searchValue,
                        ],
                        "proposals.status" => $status,
                        "proposals.deleted" => 0,
                    ],
                    "LIMIT" => [$start, $length],
                    "ORDER" => [$orderName => strtoupper($orderDir)],
                ];
                if ($jatbi->permission(['proposal.full']) != 'true') {
                    $where["AND"]["OR"] = [
                        "proposal_accounts.account" => $account['id'],
                        "AND" => [
                            "proposals.account" => $account['id'],
                            "proposals.status" => 0
                        ]
                    ];
                }
                else {
                    $where["AND"]["OR"] = [
                        "proposals.status[!]" => 0,
                        "AND" => [
                            "proposals.account" => $account['id'],
                            "proposals.status" => 0
                        ]
                    ];
                }
                $where["GROUP"] = "proposals.id";
                if (!empty($_POST['date'])) {
                    $dates = explode(" - ", $_POST['date']);
                    if (count($dates) == 2) {
                        $from = DateTime::createFromFormat('d/m/Y', trim($dates[0]));
                        $to   = DateTime::createFromFormat('d/m/Y', trim($dates[1]));
                        if ($from && $to) {
                            $where["proposals.date[<>]"] = [
                                $from->format("Y-m-d 00:00:00"),
                                $to->format("Y-m-d 23:59:59")
                            ];
                        }
                    } else {
                        $date = DateTime::createFromFormat('d/m/Y', trim($dates[0]));
                        if ($date) {
                            $where["proposals.date[<>]"] = [
                                $date->format("Y-m-d 00:00:00"),
                                $date->format("Y-m-d 23:59:59")
                            ];
                        }
                    }
                } 
                else {
                    $from = new DateTime("-1 year");
                    $to   = new DateTime("+1 year");
                    $where["proposals.date[<>]"] = [
                        $from->format("Y-m-d 00:00:00"),
                        $to->format("Y-m-d 23:59:59")
                    ];
                }
                if (!empty($_POST['create'])) {
                    $creates = explode(" - ", $_POST['create']);
                    if (count($creates) == 2) {
                        $from = DateTime::createFromFormat('d/m/Y', trim($creates[0]));
                        $to   = DateTime::createFromFormat('d/m/Y', trim($creates[1]));
                        if ($from && $to) {
                            $where["proposals.create[<>]"] = [
                                $from->format("Y-m-d 00:00:00"),
                                $to->format("Y-m-d 23:59:59")
                            ];
                        }
                    } else {
                        $create = DateTime::createFromFormat('d/m/Y', trim($creates[0]));
                        if ($create) {
                            $where["proposals.create[<>]"] = [
                                $create->format("Y-m-d 00:00:00"),
                                $create->format("Y-m-d 23:59:59")
                            ];
                        }
                    }
                } else {
                    $from = new DateTime("-1 year");
                    $to   = new DateTime("now");
                    $where["proposals.create[<>]"] = [
                        $from->format("Y-m-d 00:00:00"),
                        $to->format("Y-m-d 23:59:59")
                    ];
                }
                if (!empty($IDProposal)) {
                    $where["AND"]["proposals.id"] = $IDProposal;
                }
                if (!empty($status)) {
                    $where["AND"]["proposals.status"] = $status;
                }
                if (!empty($type)) {
                    $where["AND"]["proposals.type"] = $type;
                }
                if (!empty($Searchaccount)) {
                    $where["AND"]["proposals.account"] = $Searchaccount;
                }
                if (!empty($form)) {
                    $where["AND"]["proposals.form"] = $form;
                }
                if (!empty($workflows)) {
                    $where["AND"]["proposals.workflows"] = $workflows;
                }
                if (!empty($category)) {
                    $where["AND"]["proposals.category"] = $category;
                }
                if (!empty($customers)) {
                    $where["AND"]["proposals.customers"] = $customers;
                }
                if (!empty($vendors)) {
                    $where["AND"]["proposals.vendors"] = $vendors;
                }
                if (!empty($objects)) {
                    $where["AND"]["proposals.objects"] = $objects;
                }
                if (!empty($accounts_search)) {
                    $where["AND"]["proposals.accounts"] = $accounts_search;
                }
                $join = [
                    "[>]accounts"=>["account"=>"id"],
                    "[>]proposal_accounts"=>["id"=>"proposal"],
                    "[>]proposal_form"=>["form"=>"id"],
                    "[>]proposal_target"=>["category"=>"id"],
                    "[>]proposal_workflows"=>["workflows"=>"id"],
                    // "[>]proposals_reality"=>["id"=>"proposal"],
                ];
                $count = $app->count("proposals",$join,[
                    "@proposals.id"
                ],[
                    "AND" => $where['AND'],
                    // "GROUP" => "proposals.id"
                ]);
                $app->select("proposals",$join,[
                    "@proposals.id",
                    "proposals.active",
                    "proposals.code",
                    "proposals.content",
                    "proposals.type",
                    "proposals.form",
                    "proposals.status",
                    "proposals.date",
                    "proposals.process",
                    "proposals.modify",
                    "proposals.create",
                    // "reality" => App::raw("SUM(CASE WHEN proposals_reality.deleted = 0 THEN proposals_reality.reality ELSE 0 END)"),
                    "proposals.price",
                    "proposals.target",
                    "proposals.account (account_id)",
                    "accounts.name (accounts)",
                    "accounts.avatar (avatar)",
                    "proposal_workflows.name (workflows)",
                    "proposal_target.name (category)",
                    "proposal_form.name (form)",
                ], $where, function ($data) use (&$datas, $jatbi,$app,$common,$account) {
                    $type = $common['proposal'][$data['type']];
                    $status = $common['proposal-status'][$data['status']];
                    // if($data['status']==4 && $data['reality']!=$data['price']){
                    //     $price = '<span class="text-muted fst-italic fw-normal text-decoration-line-through">'.number_format($data['price']).'</span> # '.number_format($data['reality']);
                    // }
                    // else {
                        $price = number_format($data['price']);
                    // }
                    $status = '<span class="p-2 py-1 rounded-pill small fw-bold text-nowrap bg-'.$status['color'].'">'.$status['name'].'</span>';
                    $content  = '<a data-pjax class="text-body" href="/proposal/views/'.$data['active'].'"><div><strong class="text-'.$type['color'].'">#'.$data['id'].'  '.$type['name'].' '.$data['form'].' <br> '.$price.'</strong><br><small class="fst-italic">'.$data['content'].'</small></div></a>';
                    if($data['process']>0){
                        $status_process = $app->get("proposal_workflows_nodes","*",["id"=>$data['process']]);
                        $GetProcess = '<span class="badge  rounded-pill small fw-bold text-nowrap" style="background: '.$status_process['background'].';color:'.$status_process['color'].'">'.$status_process['name'].'</span>';
                    }
                    $workflows = '<a data-pjax class="text-body" href="/proposal/views/'.$data['active'].'"><div class="mb-1">'.($GetProcess ?? '').'</div>'.($data['workflows'] ?? '').'</a>';
                    $avatar = '<img data-src="/'.$data['avatar'].'" class="rounded-circle width height lazyload" style="--width:30px;--height:30px;">';
                    $button[] = [
                        'type' => 'link',
                        'name' => $jatbi->lang("Xem"),
                        'permission' => ['proposal'],
                        'action' => ['href' => '/proposal/views/'.$data['active'], 'data-pjax' => '']
                    ];
                    $checkbox = '';
                    if($data['account_id']==$account['id'] && $data['status']==0){
                        $button[] = [
                            'type' => 'link',
                            'name' => $jatbi->lang("Sửa"),
                            'permission' => ['proposal.edit'],
                            'action' => ['href' => '/proposal/edit/'.$data['active'], 'data-pjax' => '']
                        ];
                    }
                    if ($jatbi->permission(['proposal.deleted.full']) == 'true' || $data['account_id']==$account['id'] && $data['status']==0) {
                        $button[] = [
                            'type' => 'button',
                            'name' => $jatbi->lang("Xóa"),
                            'permission' => ['proposal.deleted'],
                            'action' => ['data-url' => '/proposal/deleted?box='.$data['active'], 'data-action' => 'modal']
                        ];
                        $checkbox = $app->component("box",["data"=>$data['active']]);
                    }
                    if ($jatbi->permission(['proposal.reality']) == 'true' && $data['status']==2) {
                        $button[] = [
                            'type' => 'button',
                            'name' => $jatbi->lang("bút toán"),
                            'permission' => ['proposal.reality'],
                            'action' => ['data-url' => '/proposal/reality'.$data['active'], 'data-action' => 'modal']
                        ];
                    }
                    if($data['account_id']==$account['id'] && $data['status']==3){
                        $button[] = [
                            'type' => 'link',
                            'name' => $jatbi->lang("Sửa"),
                            'permission' => ['proposal.edit'],
                            'action' => ['href' => '/proposal/edit/'.$data['active'], 'data-pjax' => '']
                        ];
                    }

                    $datas[] = [
                        "checkbox" => $checkbox,
                        "content" => $content ?? '',
                        "process" => $GetProcess ?? '',
                        "workflows" => $workflows ?? '',
                        "date" => '<strong>'.$jatbi->date($data['date']).'</strong><br>'.$jatbi->datetime($data['create']),
                        "status" => $status ?? '',
                        "accounts" => $avatar ?? '',
                        "action" => $app->component("action",[
                            "button" => $button
                        ]),
                    ];
                });
                echo json_encode([
                    "draw" => $draw,
                    "recordsTotal" => $count ?? 0,
                    "recordsFiltered" => $count ?? 0,
                    "data" => $datas ?? []
                ]);
            }
        })->setPermissions(['proposal']);

        $app->router("/search/update", ['GET','POST'], function($vars) use ($app, $jatbi) {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $insert = [
                "type" => 1,
                "date" => date("Y-m-d"),
                "modify" => date("Y-m-d H:i:s"),
                "create" => date("Y-m-d H:i:s"),
                "account" => $account['id'],
                "status" => 0,
                "code" => time(),
                "stores" => $jatbi->stores('ID'),
                "stores_id" => $jatbi->stores('ID'),
                "active" => $jatbi->active(),
            ];
            $app->insert("proposals",$insert);
            $GetID = $app->id();
            $jatbi->logs('proposal','proposal-create',$insert);
            $insert_accounts = [
                "account" => $account['id'],
                "proposal" => $GetID,
                "date" => date("Y-m-d H:i:s"),
            ];
            $app->insert("proposal_accounts",$insert_accounts);
            echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công"),'load'=>'ajax','url'=>"/proposal/edit/".$insert['active']]);
        })->setPermissions(['proposal.add']);

        $app->router("/create", 'POST', function($vars) use ($app, $jatbi, $template,$account) {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $insert = [
                "type" => 1,
                "date" => date("Y-m-d"),
                "modify" => date("Y-m-d H:i:s"),
                "create" => date("Y-m-d H:i:s"),
                "account" => $account['id'],
                "status" => 0,
                "code" => time(),
                "stores" => $jatbi->stores('ID'),
                "stores_id" => $jatbi->stores('ID'),
                "active" => $jatbi->active(),
            ];
            $app->insert("proposals",$insert);
            $GetID = $app->id();
            $jatbi->logs('proposal','proposal-create',$insert);
            $insert_accounts = [
                "account" => $account['id'],
                "proposal" => $GetID,
                "date" => date("Y-m-d H:i:s"),
            ];
            $app->insert("proposal_accounts",$insert_accounts);
            echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công"),'load'=>'ajax','url'=>"/proposal/edit/".$insert['active']]);
        })->setPermissions(['proposal.add']);

        $app->router("/edit/{active}", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$account,$setting,$common) {
            $data = $app->get("proposals","*",["active"=>$app->xss($vars['active']),"status"=>[0,3],"deleted"=>0,"account"=>$account['id']]);
            if($data>1){
                if($app->method()==='GET'){
                    $vars['data'] = $data;
                    $vars['title'] = $jatbi->lang("Đề xuất").' #'.$data['id'];
                    $vars['types'] = array_map(function($item) {
                            return [
                                'text' => $item['name'],
                                'value' => $item['id']
                            ];
                        }, $common['proposal']);
                    $vars['forms'][] = $app->get("proposal_form",["id (value)","name (text)"],["id"=>$data['form']]);
                    $vars['workflows'][] = $app->get("proposal_workflows",["id (value)","name (text)"],["id"=>$data['workflows']]);
                    $vars['categorys'][] = $app->get("proposal_target",["id (value)","name (text)"],["id"=>$data['category']]);
                    $vars['customers'][] = $app->get("customers",["id (value)","name (text)"],["id"=>$data['customers']]);
                    $vars['vendors'][] = $app->get("vendors",["id (value)","name (text)"],["id"=>$data['vendors']]);
                    $vars['accounts'][] = $app->get("accounts",["id (value)","name (text)"],["id"=>$data['accounts']]);
                    $vars['proposals'][] = $app->get("proposals",["id (value)","content (text)"],["id"=>$data['proposals']]);
                    $vars['accountants'][] = $app->get("accountants",["id (value)","name (text)"],["id"=>$data['accountants']]);
                    $vars['stores'][] = $app->get("stores",["id (value)","name (text)"],["id"=>$data['stores']]);
                    $vars['objects'][] = $app->get("proposal_objects",["id (value)","name (text)"],["id"=>$data['objects']]);
                    $files = $app->select("proposal_files",[
                        "[>]file" => ["files"=>"id"],
                    ],[
                        "file.active",
                        "file.name",
                    ],[
                        "proposal_files.deleted"=>0,
                        "proposal_files.proposal"=>$data['id']
                    ]);
                    $vars['files'] = $files;
                    echo $app->render($template.'/proposal/proposal-post.html', $vars);
                }
                elseif($app->method()==='POST'){
                    $app->header([
                        'Content-Type' => 'application/json',
                    ]);
                    $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
                    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
                    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
                    $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
                    $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
                    $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'ASC';
                    $where = [
                        "AND" => [
                            "OR" => [
                                "content[~]" => $searchValue,
                            ],
                            "proposal" => $data['id'],
                            "deleted" => 0,
                        ],
                    ];
                    $count = $app->count("proposal_details",[
                        "id"
                    ],[
                        "AND" => $where['AND']
                    ]);
                    $proposal = $data['active'];
                    $total = 0;
                    $app->select("proposal_details","*",$where, function ($data) use (&$datas, &$total, $jatbi, $app, $common, $proposal) {
                        $total = ($total ?? 0) + ($data['total'] ?? 0);
                        $datas[] = [
                            "id" => $data['active'],
                            // data-form=\'{"type":"details","details":"'.$data['active'].'",value":"this.val","code":"edit","col":"content"}\'
                            "content" => '<input type="text" data-action="blur" data-pjax-scrollTo="false" data-url="/proposal/update/'.$proposal.'" data-form=\'{"type":"details","details":"'.$data['active'].'","code":"edit","col":"content","value":"this.val"}\' class="form-control p-1 border-0 bg-transparent" value="'.($data['content'] ?? 0).'" placeholder="Nội dung">',
                            "type" => '',
                            "price" => '<input type="tel" data-table-load="datatable-details" data-pjax-scrollTo="false" data-action="blur" data-url="/proposal/update/'.$proposal.'" data-form=\'{"type":"details","details":"'.$data['active'].'","code":"edit","col":"price","value":"this.val"}\' data-number="money" data-currency="" data-decimals="0" data-thousands="." data-decimal="," class="form-control p-1 border-0 bg-transparent" value="'.($data['price'] ?? 0).'" placeholder="Đơn giá">',
                            "amount" => '<input type="tel" data-table-load="datatable-details" data-pjax-scrollTo="false" data-action="blur" data-url="/proposal/update/'.$proposal.'" data-form=\'{"type":"details","details":"'.$data['active'].'","code":"edit","col":"amount","value":"this.val"}\' class="form-control p-1 border-0 bg-transparent" value="'.($data['amount'] ?? 0).'" placeholder="Số lượng">',
                            "units" => '<input type="text" data-action="blur" data-pjax-scrollTo="false" data-url="/proposal/update/'.$proposal.'" data-form=\'{"type":"details","details":"'.$data['active'].'","code":"edit","col":"units","value":"this.val"}\' class="form-control p-1 border-0 bg-transparent" value="'.($data['units'] ?? 0).'" placeholder="Đơn vị">',
                            "total" => number_format($data['total'] ?? 0),
                            "notes" => '<input type="text" data-action="blur" data-pjax-scrollTo="false" data-url="/proposal/update/'.$proposal.'" data-form=\'{"type":"details","details":"'.$data['active'].'","code":"edit","col":"notes","value":"this.val"}\' class="form-control p-1 border-0 bg-transparent" value="'.($data['notes'] ?? 0).'" placeholder="Ghi chú">',
                            "action" => '<button type="button" data-pjax-scrollTo="false" class="btn btn-sm p-0 text-eclo" data-action="click" data-confirm="Bạn có muốn xóa nó không?" data-url="/proposal/update/'.$proposal.'" data-toast="true" data-form=\'{"type":"details","details":"'.$data['active'].'","code":"deleted"}\' data-table-load="datatable-details" ><i class="ti ti-trash fs-4"></i></button>',
                        ];
                    });
                    echo json_encode([
                        "draw" => $draw,
                        "recordsTotal" => $count ?? 0,
                        "recordsFiltered" => $count ?? 0,
                        "data" => $datas ?? [],
                        "footerData" => [
                            "total" => number_format($total ?? 0),
                            "update" => '<button type="button" data-pjax-scrollTo="false" class="btn btn-sm p-1 rounded-pill btn-eclo" data-action="click" data-confirm="Bạn có muốn cập nhật nó lên số tiền không?" data-url="/proposal/update/'.$proposal.'" data-toast="true" data-form=\'{"type":"price","value":"'.$total.'"}\' data-table-load="datatable-details" data-selector="[data-vat-price],[data-update-price]" data-load="this">'.$jatbi->lang("Cập nhật số tiền").'</button>',
                        ],
                    ]);
                }
            }
            else {
                echo $app->render($setting['template'].'/pages/error.html', $vars, $jatbi->ajax());
            }
        })->setPermissions(['proposal.add']);

        $app->router("/deleted", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$setting,$account) {
            $vars['title'] = $jatbi->lang("Xóa Hình thức đề xuất");
            if($app->method()==='GET'){
                echo $app->render($setting['template'].'/common/deleted.html', $vars, $jatbi->ajax());
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $boxid = explode(',', $app->xss($_GET['box']));
                if ($jatbi->permission(['proposal.deleted.full']) == 'true') {
                    $datas = $app->select("proposals","*",["active"=>$boxid,"deleted"=>0]);
                }
                else {
                    $datas = $app->select("proposals","*",["active"=>$boxid,"deleted"=>0,"status"=>0,"account"=>$account['id']]);
                }
                if(count($datas)>0){
                    foreach($datas as $data){
                        $app->update("proposals",["deleted"=> 1],["id"=>$data['id']]);
                        $app->update("proposals_reality",["deleted"=> 1],["proposal"=>$data['id']]);
                        $name[] = $data['id'];
                    }
                    $jatbi->logs('proposals','proposals-deleted',$datas);
                    $jatbi->trash('/proposal/restore',"Đề xuất với mã: ".implode(', ',$name),["database"=>'proposals',"data"=>$boxid]);
                    echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra")]);
                }
            }
        })->setPermissions(['proposal.config.deleted']);

        $app->router("/restore/{id}", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$setting,$account) {
            if($app->method()==='GET'){
                $vars['data'] = $app->get("trashs","*",["active"=>$vars['id'],"deleted"=>0]);
                if($vars['data']>1){
                    echo $app->render($setting['template'].'/common/restore.html', $vars, $jatbi->ajax());
                }
                else {
                    echo $app->render($setting['template'].'/pages/error.html', $vars, $jatbi->ajax());
                }
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $trash = $app->get("trashs","*",["active"=>$vars['id'],"deleted"=>0]);
                if($trash>1){
                    $datas = json_decode($trash['data']);
                    foreach($datas->data as $active) {
                        $data = $app->get("proposals","id",["active"=>$active]);
                        $app->update("proposals",["deleted"=>0],["id"=>$data]);
                        $app->update("proposals_reality",["deleted"=> 0],["proposal"=>$data]);
                    }
                    $app->delete("trashs",["id"=>$trash['id']]);
                    $jatbi->logs('form','form-restore',$datas);
                    echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra")]);
                }
            }
        })->setPermissions(['proposal.config.deleted']);

        $app->router("/views/{active}", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$account,$setting,$common,$process) {
            $where = [
                "proposals.active"=>$app->xss($vars['active']),
                "proposals.deleted"=>0,
            ];
            if ($jatbi->permission(['proposal.full']) != 'true') {
                $where["AND"]["OR"] = [
                    "proposal_accounts.account" => $account['id'],
                    "AND" => [
                        "proposals.account" => $account['id'],
                    ]
                ];
            }
            $where["GROUP"] = "proposals.id";
            $data = $app->get("proposals",[
                "[>]accounts" => ["account"=>"id"],
                "[>]proposal_form" => ["form"=>"id"],
                "[>]proposal_accounts"=>["id"=>"proposal"],
                "[>]proposal_workflows" => ["workflows"=>"id"],
                "[>]proposal_target" => ["category"=>"id"],
                "[>]proposal_objects" => ["objects"=>"id"],
            ],[
                "proposals.id",
                "proposals.active",
                "proposals.modify",
                "proposals.create",
                "proposals.type",
                "proposals.target",
                "proposals.notes",
                "proposals.date",
                "proposals.status",
                "proposals.price",
                "proposals.vat_type",
                "proposals.vat_price",
                "proposals.reality",
                "proposals.reality_content",
                "proposals.reality_date",
                "proposals.reality_account",
                "proposals.process",
                "proposals.vendors",
                "proposals.customers",
                "proposals.purchase",
                "proposals.shifts",
                "proposals.invoices",
                "proposals.account (account_id)",
                "proposals.accounts",
                "proposals.proposals",
                "proposals.accountants",
                "proposals.stores",
                "proposal_form.name (form)",
                "proposal_workflows.name (workflows)",
                "proposal_target.name (category)",
                "proposal_objects.name (objects)",
                "proposals.content",
                "accounts.name (account)",
            ],$where);
            if($data>1){
                if($app->method()==='GET'){
                    $type = $common['proposal'][$data['type']];
                    $vars['data'] = $data;
                    $vars['title'] = $jatbi->lang("Đề xuất").' #'.$data['id'];
                    $vars['data']['type_name'] = $type['name'];
                    $vars['data']['type_color'] = $type['color'];
                    $vars['data']['total_price'] = $data['price'];
                    $status = $common['proposal-status'][$data['status']];
                    $status = '<span class="p-2 py-1 rounded-pill small fw-bold text-nowrap bg-'.$status['color'].'">'.$status['name'].'</span>';
                    $vars['data']['status_id'] = $data['status'];
                    $vars['data']['status'] = $status;
                    if($data['vat_type']==0){
                        $vars['data']['total_price'] = $data['price']+$data['vat_price'];
                    }
                    $files = $app->select("proposal_files",[
                        "[>]file" => ["files"=>"id"],
                    ],[
                        "file.active",
                        "file.name",
                    ],[
                        "proposal_files.deleted"=>0,
                        "proposal_files.proposal"=>$data['id']
                    ]);
                    $vars['files'] = $files;
                    $vars['process'] = [];
                    $DataProcess = $process->workflows($data['id'],$data['account_id']);
                    if (isset($data['process']) ) {
                        $vars['process'] = $DataProcess;
                    }
                    $status_process = $app->get("proposal_workflows_nodes","*",["id"=>$data['process']]);
                    $vars['data']['process'] = '<span class="p-2 py-1 rounded-pill small fw-bold text-nowrap" style="background: '.($status_process['background'] ?? '').';color:'.($status_process['color'] ?? '').'">'.($status_process['name'] ?? '').'</span>';
                    $vars['proposals_logs'] = $app->select("proposal_logs","*",["proposal"=>$data['id'],"ORDER"=>["id"=>"DESC"]]);
                    $node_approval = null;
                    $vars['active_approval'] = false;
                    $vars['edit_proposal'] = false;
                    if($data['type']==1){
                        $vars['color'] = 'text-success';
                    }
                    elseif($data['type']==2){
                        $vars['color'] = 'text-danger';
                    }
                    elseif($data['type']==3){
                        $vars['color'] = 'text-primary';
                    }
                    if (isset($data['process']) && is_array($DataProcess)) {
                        foreach ($DataProcess as $key => $item) {
                            if (is_array($item) && 
                                ($item['node_id'] ?? null) == ($data['process'] ?? '') && 
                                isset($DataProcess[$key + 1]) && is_array($DataProcess[$key + 1])) {
                                
                                $node_approval = $DataProcess[$key + 1];
                                break;
                            }
                        }
                        if (!empty($node_approval) && is_array($node_approval)) {
                            if (($node_approval['approver_account_id'] ?? null) == ($account['id'] ?? null) 
                                && ($data['status'] ?? null) == 1) {
                                $vars['active_approval'] = true;
                            }
                            $vars['node_approval'] = $node_approval;
                        }
                    }
                    if(($data['status']==0 || $data['status']==3) && $data['account_id']==$account['id']){
                        $vars['edit_proposal'] = true;
                    }
                    $vars['consultations'] = $app->select("proposal_consultation", "*", [
                        "proposal" => $data['id'],
                        "OR" => [
                            "account" => $account['id'],
                            "account_consultation" => $account['id']
                        ]
                    ]);
                    $vars['realitys'] = $app->select("proposals_reality", [
                        "[>]proposal_form"=>["form"=>"id"],
                        "[>]proposal_target"=>["category"=>"id"],
                    ],[
                        "proposals_reality.reality",
                        "proposals_reality.reality_date",
                        "proposals_reality.reality_content",
                        "proposals_reality.reality_account",
                        "proposals_reality.active",
                        "proposal_form.name (form)",
                        "proposal_target.name (category)",
                    ],[
                        "proposals_reality.proposal" => $data['id'],
                        "proposals_reality.deleted" => 0,
                    ]);
                    $vars['checkReality'] = (float)$app->sum("proposals_reality","reality",["deleted"=>0,"proposal"=>$data['id']]);
                    echo $app->render($template.'/proposal/proposal-views.html', $vars);
                }
                elseif($app->method()==='POST'){
                    $app->header([
                        'Content-Type' => 'application/json',
                    ]);
                    $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
                    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
                    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
                    $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
                    $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
                    $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'ASC';
                    $where = [
                        "AND" => [
                            "OR" => [
                                "content[~]" => $searchValue,
                            ],
                            "proposal" => $data['id'],
                            "deleted" => 0,
                        ],
                        "LIMIT" => [$start, $length],
                        "ORDER" => [$orderName => strtoupper($orderDir)]
                    ];
                    $count = $app->count("proposal_details",[
                        "id"
                    ],[
                        "AND" => $where['AND']
                    ]);
                    $proposal = $data['active'];
                    $total = 0;
                    $app->select("proposal_details","*",$where, function ($data) use (&$datas, &$total, $jatbi, $app, $common, $proposal) {
                        $total = ($total ?? 0) + ($data['total'] ?? 0);
                        $datas[] = [
                            "id" => $data['active'],
                            "content" => ($data['content'] ?? 0),
                            "type" => '',
                            "price" => number_format($data['price'] ?? 0),
                            "amount" => number_format($data['amount'] ?? 0),
                            "units" =>($data['units'] ?? 0),
                            "total" => number_format($data['total'] ?? 0),
                            "notes" => ($data['notes'] ?? 0),
                        ];
                    });
                    echo json_encode([
                        "draw" => $draw,
                        "recordsTotal" => $count ?? 0,
                        "recordsFiltered" => $count ?? 0,
                        "data" => $datas ?? [],
                        "footerData" => [
                            "total" => number_format($total ?? 0),
                        ],
                    ]);
                }
            }
            else {
                echo $app->render($setting['template'].'/pages/error.html', $vars, $jatbi->ajax());
            }
        })->setPermissions(['proposal.add','proposal.edit']);

        $app->router("/approval/{active}", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$account,$process,$setting) {
            $data = $app->get("proposals","*",["active"=>$app->xss($vars['active']),"deleted"=>0]);
            if($app->method()==='GET'){
                if($data>1){
                    $vars['title'] = $jatbi->lang("Xét duyệt");
                    echo $app->render($template.'/proposal/approval.html', $vars, $jatbi->ajax());
                }
                else {
                    echo $app->render($setting['template'].'/pages/error.html', $vars, $jatbi->ajax());
                }
            }
            elseif($app->method()==='POST') {
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                if($data>1){
                    $DataProcess = $process->workflows($data['id'],$data['account']);
                    $node_old = null;
                    $node_approval = null;
                    $node_next = null;
                    $active_approval = false;
                    foreach ($DataProcess as $key => $item) {
                        if ($item['node_id'] == $data['process'] && isset($DataProcess[$key + 1])) {
                            $node_old = $DataProcess[$key];
                            $node_approval = $DataProcess[$key + 1];
                            $node_next = $DataProcess[$key + 2];
                            break;
                        }
                    }
                    if ($node_approval) {
                        if($node_approval['approver_account_id']==$account['id']){
                            $active_approval = true;
                        }
                    }
                    $content = $app->xss($_POST['content']);
                    $approval = $app->xss($_POST['approval']);
                    if($content=='' || $approval==''){
                        $error = ["status"=>"error","content"=>$jatbi->lang("Vui lòng không để trống")];
                    }
                    elseif($active_approval==false){
                        $error = ["status"=>"error","content"=>$jatbi->lang("Bạn không có quyền duyệt đề xuất này")];
                    }
                    if(empty($error)){
                        $proposal_process = [
                            "proposal" => $data['id'],
                            "workflows" => $data['workflows'],
                            "date" => date("Y-m-d H:i:s"),
                            "account" => $account['id'],
                            "node"  => $node_approval['node_id'],
                            "approval" => $approval,
                            "approval_content" => $content,
                            "approval_date" => date("Y-m-d H:i:s"),
                        ];
                        $app->insert("proposal_process",$proposal_process);
                        $ProcessID = $app->id();
                        if($approval==1){
                            $content = 'Đã duyệt đề xuất';
                            $status = 1;
                            $update_Process = $node_approval['node_id'];
                            $content_notification = $account['name'].' '.$content;
                            $account_notification = $node_old['approver_account_id']==0?$data['account']:$node_old['approver_account_id'];
                            $jatbi->notification($account['id'],$account_notification,'Đề xuất #'.$data['id'],$content_notification,'/proposal/views/'.$data['active'],'');
                            if($node_next['type']=='approval'){
                                $content_notification_next = $account['name'].' yêu cầu duyệt đề xuất: '.$data['content'];
                                $account_notification_next = $node_next['approver_account_id'];
                                $jatbi->notification($account['id'],$account_notification_next,'Đề xuất #'.$data['id'],$content_notification_next,'/proposal/views/'.$data['active'],'');
                                $insert_accounts = [
                                    "account" => $node_next['approver_account_id'],
                                    "proposal" => $data['id'],
                                    "date" => date("Y-m-d H:i:s"),
                                ];
                                $app->insert("proposal_accounts",$insert_accounts);
                            }
                            if($node_next['type']=='finish'){
                                $status = 2;
                                $content = 'Đã hoàn thành duyệt đề xuất';
                                $content_notification_finish = $account['name'].' '.$content;
                                $account_notification_finish = $data['account'];
                                $jatbi->notification($account['id'],$account_notification_finish,'Đề xuất #'.$data['id'],$content_notification_finish,'/proposal/views/'.$data['active'],'');
                                $proposal_process_finish = [
                                    "proposal" => $data['id'],
                                    "workflows" => $data['workflows'],
                                    "date" => date("Y-m-d H:i:s"),
                                    "account" => $account['id'],
                                    "node"  => $node_next['node_id'],
                                    "approval" => 1,
                                    "approval_content" => 'Hoàn tất quy trình',
                                    "approval_date" => date("Y-m-d H:i:s"),
                                ];
                                $app->insert("proposal_process",$proposal_process_finish);
                                if($data['auto']==1){
                                    // $update['reality'] = $data['price'];
                                    // $update['reality_content'] = 'Bút toán tự động';
                                    // $update['reality_date'] = $data['date'];
                                    // $update['reality_account'] = $account['id'];

                                    $reality = [
                                        "proposal" => $data['id'],
                                        "form" => $data['form'],
                                        "category" => $data['category'],
                                        "reality" => str_replace(",","", $data['price']),
                                        "reality_content" => 'Bút toán tự động',
                                        "reality_date" => $data['date'],
                                        "reality_account" => $account['id'],
                                        "modify" => date("Y-m-d H:i:s"),
                                        "active" => $jatbi->active(),
                                    ];
                                    $app->insert("proposals_reality",$reality);
                                    $status = 4;
                                }
                                if($data['purchase']>0){
                                    $app->update("warehouses_invoices",["status"=>3],["id"=>$data['purchase']]);
                                }
                            }
                        }
                        else{
                            $content = 'Không duyệt đề xuất';
                            $status = 3;
                            $update_Process = $node_approval['node_id'];
                            $content_notification = $account['name'].' '.$content;
                            $account_notification = $node_old['approver_account_id']==0?$data['account']:$node_old['approver_account_id'];
                            $jatbi->notification($account['id'],$account_notification,'Đề xuất #'.$data['id'],$content_notification,'/proposal/views/'.$data['active'],'');
                            $content_notification_finish = $account['name'].' '.$content;
                            $account_notification_finish = $data['account'];

                            if($account_notification!=$account_notification_finish){
                                $jatbi->notification($account['id'],$account_notification_finish,'Đề xuất #'.$data['id'],$content_notification_finish,'/proposal/views/'.$data['active'],'');
                            }
                        }
                        // $update[] = [
                        //     "status" => $status,
                        //     "process" => $node_approval['node_id'],
                        //     "modify" => date("Y-m-d H:i:s"),
                        // ];
                        $update['status'] = $status;
                        $update['process'] = $node_approval['node_id'];
                        $update['modify'] = date("Y-m-d H:i:s");
                        $app->update("proposals",$update,["id"=>$data['id']]);
                        $proposal_process_logs = [
                            "proposal" => $data['id'],
                            "date" => date("Y-m-d H:i:s"),
                            "account" => $account['id'],
                            "content" => $content,
                            "notes" => $proposal_process['approval_content'],
                            "process" => $ProcessID,
                            "data" => json_encode($proposal_process),
                        ];
                        $app->insert("proposal_logs",$proposal_process_logs);
                        echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công"),"load"=>"ajax","url"=>"/proposal/views/".$data['active']]);
                    }
                    else {
                        echo json_encode($error);
                    }
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra")]);
                }
            }
        })->setPermissions(['proposal.add']);

        $app->router("/reality/{active}", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$account,$process,$setting) {
            $data = $app->get("proposals","*",["active"=>$app->xss($vars['active']),"deleted"=>0,"status"=>[2,4]]);
            if($app->method()==='GET'){
                if($data>1){
                    $vars['title'] = $jatbi->lang("Bút toán");
                    $vars['data'] = $data;
                    $vars['forms'][] = $app->get("proposal_form",["id (value)","name (text)"],["id"=>$data['form']]);
                    $vars['categorys'][] = $app->get("proposal_target",["id (value)","name (text)"],["id"=>$data['category']]);
                    echo $app->render($template.'/proposal/reality.html', $vars, $jatbi->ajax());
                }
                else {
                    echo $app->render($setting['template'].'/pages/error.html', $vars, $jatbi->ajax());
                }
            }
            elseif($app->method()==='POST') {
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                if($data>1){
                    $price = (float)str_replace(",","", $app->xss($_POST['price']));
                    $content = $app->xss($_POST['content']);
                    $date = $app->xss($_POST['date']);
                    $form = $app->xss($_POST['form']);
                    $category = $app->xss($_POST['category']);
                    if($price=='' || $date==''){
                        $error = ["status"=>"error","content"=>$jatbi->lang("Vui lòng không để trống")];
                    }
                    if(empty($error)){
                        $app->update("proposals",["status" => 4],["id"=>$data['id']]);
                        $reality = [
                            "proposal" => $data['id'],
                            "reality" => $price,
                            "reality_content" => $content,
                            "reality_date" => $date,
                            "form" => $form,
                            "category" => $category,
                            "reality_account" => $account['id'],
                            "modify" => date("Y-m-d H:i:s"),
                            "active" => $jatbi->active(),
                        ];
                        $app->insert("proposals_reality",$reality);
                        $proposal_process_logs = [
                            "proposal" => $data['id'],
                            "date" => date("Y-m-d H:i:s"),
                            "account" => $account['id'],
                            "content" => 'Đã bút toán',
                            "notes" => $reality['reality_content'],
                            "process" => $data['process'],
                            "data" => json_encode($data),
                        ];
                        $app->insert("proposal_logs",$proposal_process_logs);
                        echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
                    }
                    else {
                        echo json_encode($error);
                    }
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra")]);
                }
            }
        })->setPermissions(['proposal.reality']);

        $app->router("/reality-update", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$account,$process,$setting) {
            $data = $app->get("proposals","*",["active"=>$app->xss($vars['active']),"deleted"=>0,"status"=>[2,4]]);
            if($app->method()==='GET'){
                $proposals = $app->select("proposals","*",["deleted"=>0,"status"=>4]);
                foreach($proposals as $data){
                    $reality = [
                        "proposal" => $data['id'],
                        "form"  => $data['form'],
                        "category"  => $data['category'],
                        "reality" => str_replace(",","", $data['reality']),
                        "reality_content" => $data['reality_content'],
                        "reality_date" => $data['reality_date'],
                        "reality_account" => $data['reality_account'],
                        "modify" => date("Y-m-d H:i:s"),
                        "active" => $jatbi->active(),
                    ];
                    $app->insert("proposals_reality",$reality);
                }
            }
        })->setPermissions(['proposal.reality']);

        $app->router("/reality-deleted", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$setting) {
            $vars['title'] = $jatbi->lang("Xóa Bút toán");
            if($app->method()==='GET'){
                echo $app->render($setting['template'].'/common/deleted.html', $vars, $jatbi->ajax());
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $boxid = explode(',', $app->xss($_GET['box']));
                $datas = $app->select("proposals_reality","*",["active"=>$boxid,"deleted"=>0]);
                if(count($datas)>0){
                    foreach($datas as $data){
                        $app->update("proposals_reality",["deleted"=> 1],["id"=>$data['id']]);
                        $name[] = $data['reality'];
                    }
                    echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra")]);
                }
            }
        })->setPermissions(['proposal.config.deleted']);

        $app->router("/completed/{active}", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$account,$process,$setting) {
            $data = $app->get("proposals","*",["active"=>$app->xss($vars['active']),"status"=>[0,3],"deleted"=>0,"account"=>$account['id']]);
            if($app->method()==='GET'){
                if($data>1){
                    echo $app->render($template.'/proposal/completed.html', $vars, $jatbi->ajax());
                }
                else {
                    echo $app->render($setting['template'].'/pages/error.html', $vars, $jatbi->ajax());
                }
            }
            elseif($app->method()==='POST') {
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                if($data>1){
                    $checkAccount = $app->count("proposal_diagram_accounts","*",["account"=>$account['id'],"deleted"=>0]);
                    if($data['price']=='' || $data['type']=='' || $data['form']=='' || $data['content']=='' || $data['workflows']=='' || $data['category']=='' || $data['price']==''){
                        $error = ["status"=>"error","content"=>$jatbi->lang("Vui lòng không để trống")];
                    }
                    elseif($checkAccount==0) {
                        $error = ["status"=>"error","content"=>$jatbi->lang("Tài khoản của bạn không thuộc sơ đồ tổ tài khoản, không thể gửi đề xuất")];
                    }
                    if(empty($error)){
                        $process = $process->workflows($data['id'],$data['account']);
                        if($process[0]){
                            $app->update("proposal_process",["deleted"=>1],["proposal"=>$data['id'],"workflows"=>$data['workflows']]);
                            $proposal_process = [
                                "proposal" => $data['id'],
                                "workflows" => $data['workflows'],
                                "date" => date("Y-m-d H:i:s"),
                                "account" => $account['id'],
                                "node"  => $process[0]['node_id'],
                                "approval" => 1,
                                "approval_date" => date("Y-m-d H:i:s"),
                            ];
                            $app->insert("proposal_process",$proposal_process);
                            $ProcessID = $app->id();
                            $proposal_process_logs = [
                                "proposal" => $data['id'],
                                "date" => date("Y-m-d H:i:s"),
                                "account" => $account['id'],
                                "content" => 'Gửi đề xuất',
                                "process" => $ProcessID,
                                "data" => json_encode($proposal_process),
                            ];
                            $app->insert("proposal_logs",$proposal_process_logs);
                            $update = [
                                "status" => 1,
                                "process" => $process[0]['node_id'],
                                "modify" => date("Y-m-d H:i:s"),
                            ];
                            $app->update("proposals",$update,["id"=>$data['id']]);
                            $content_notification = $account['name'].' đề xuất: '.$data['content'];
                            $jatbi->notification($account['id'],$process[1]['approver_account_id'],'Đề xuất #'.$data['id'],$content_notification,'/proposal/views/'.$data['active'],'');
                            $insert_accounts = [
                                "account" => $process[1]['approver_account_id'],
                                "proposal" => $data['id'],
                                "date" => date("Y-m-d H:i:s"),
                            ];
                            $app->insert("proposal_accounts",$insert_accounts);
                        }
                        echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công"),"load"=>"ajax","url"=>"/proposal/views/".$data['active']]);
                    }
                    else {
                        echo json_encode($error);
                    }
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra")]);
                }
            }
        })->setPermissions(['proposal.add']);

        $app->router("/search/form", ['GET','POST'], function($vars) use ($app, $jatbi) {
            if ($app->method() === 'POST') {
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $searchValue = isset($_POST['keyword']) ? $_POST['keyword'] : '';
                $parent = isset($_POST['parent']) ? $_POST['parent'] : '';
                $where = [
                    "AND" => [
                        "OR" => [
                            "proposal_form.name[~]" => $searchValue,
                        ],
                        "proposal_form.status" => 'A',
                        "proposal_form.deleted" => 0,
                        "stores_linkables.stores" => $jatbi->stores(),
                    ]
                ];
                
                $app->select("proposal_form", [
                    "[>]stores_linkables" => ["id" => "data","AND"=>["stores_linkables.type"=>"proposal_form"]]
                ],[
                    "@proposal_form.id",
                    "proposal_form.name",

                ], $where, function ($data) use (&$datas,$jatbi,$app) {
                    $datas[] = [
                        "value" => $data['id'],
                        "text" => $data['name'],
                    ];
                });
                echo json_encode($datas);
            }
        })->setPermissions(['proposal']);

        $app->router("/search/workflows", ['GET','POST'], function($vars) use ($app, $jatbi) {
            if ($app->method() === 'POST') {
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $searchValue = isset($_POST['keyword']) ? $_POST['keyword'] : '';
                $parent = isset($_POST['parent']) ? $_POST['parent'] : '';
                $where = [
                    "AND" => [
                        "OR" => [
                            "proposal_workflows.name[~]" => $searchValue,
                        ],
                        "proposal_workflows.status" => 'A',
                        "proposal_workflows.deleted" => 0,
                        "stores_linkables.stores" => $jatbi->stores(),
                    ]
                ];
                if($parent){
                    $where['AND']['proposal_workflows_form.form'] = $parent;
                }
                $app->select("proposal_workflows",[
                    "[>]proposal_workflows_form" => ["id"=>"workflows"],
                    "[>]stores_linkables" => ["id" => "data","AND"=>["stores_linkables.type"=>"proposal_workflows"]]
                ],[
                    "@proposal_workflows.id",
                    "proposal_workflows.name",
                ], $where, function ($data) use (&$datas,$jatbi,$app) {
                    $datas[] = [
                        "value" => $data['id'],
                        "text" => $data['name'],
                    ];
                });
                echo json_encode($datas);
            }
        })->setPermissions(['proposal']);

        $app->router("/search/categorys", ['GET','POST'], function($vars) use ($app, $jatbi) {
            if ($app->method() === 'POST') {
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $searchValue = isset($_POST['keyword']) ? $_POST['keyword'] : '';
                $parent = isset($_POST['parent']) ? $_POST['parent'] : '';
                $where = [
                    "AND" => [
                        "OR" => [
                            "proposal_target.name[~]" => $searchValue,
                        ],
                        "proposal_target.status" => 'A',
                        "proposal_target.deleted" => 0,
                        "stores_linkables.stores" => $jatbi->stores(),
                    ]
                ];
                if($parent){
                    $where['AND']['proposal_target_workflows.workflows'] = $parent;
                }
                $app->select("proposal_target",[
                    "[>]proposal_target_workflows" => ["id"=>"target"],
                    "[>]stores_linkables" => ["id" => "data","AND"=>["stores_linkables.type"=>"proposal_target"]]
                ],[
                    "@proposal_target.id",
                    "proposal_target.name",
                ], $where, function ($data) use (&$datas,$jatbi,$app) {
                    $datas[] = [
                        "value" => $data['id'],
                        "text" => $data['name'],
                    ];
                });
                echo json_encode($datas);
            }
        })->setPermissions(['proposal']);

        $app->router("/search/vendors", ['GET', 'POST'], function ($vars) use ($app, $jatbi) {
            if ($app->method() === 'POST') {
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $searchValue = isset($_POST['keyword']) ? $_POST['keyword'] : '';
                $parent = isset($_POST['parent']) ? $_POST['parent'] : '';
                $where = [
                    "AND" => [
                        "OR" => [
                            "vendors.name[~]" => $searchValue,
                        ],
                        "vendors.status" => 'A',
                        "vendors.deleted" => 0,
                    ]
                ];
                $app->select("vendors", [
                    "vendors.id",
                    "vendors.name",
                ], $where, function ($data) use (&$datas, $jatbi, $app) {
                    $datas[] = [
                        "value" => $data['id'],
                        "text" => $data['name'],
                    ];
                });
                echo json_encode($datas);
            }
        })->setPermissions(['proposal']);

        $app->router("/search/accounts", ['GET', 'POST'], function ($vars) use ($app, $jatbi) {
            if ($app->method() === 'POST') {
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $searchValue = isset($_POST['keyword']) ? $_POST['keyword'] : '';
                $where = [
                    "AND" => [
                        "OR" => [
                            "accounts.name[~]" => $searchValue,
                            "accounts.email[~]" => $searchValue,
                            "accounts.account[~]" => $searchValue,
                        ],
                        "accounts.deleted" => 0,
                        "stores_linkables.stores" => $jatbi->stores(),
                    ]
                ];
                // var_dump($jatbi->stores());
                $app->select("accounts", [
                    "[>]stores_linkables" => ["id" => "data", "AND" => ["stores_linkables.type" => "accounts"]]
                ], [
                    "@accounts.id",
                    "accounts.name",
                ], $where, function ($data) use (&$datas, $jatbi, $app) {
                    $datas[] = [
                        "value" => $data['id'],
                        "text" => $data['name'],
                    ];
                });
                echo json_encode($datas);
            }
    })->setPermissions(['proposal']);

        $app->router("/search/proposals", ['GET','POST'], function($vars) use ($app, $jatbi) {
            if ($app->method() === 'POST') {
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $searchValue = isset($_POST['keyword']) ? $_POST['keyword'] : '';
                $where = [
                    "AND" => [
                        "OR" => [
                            "proposals.content[~]" => $searchValue,
                            "proposals.id[~]" => $searchValue,
                            "proposals.code[~]" => $searchValue,
                        ],
                        "proposals.deleted" => 0,
                        "proposals.status[!]" => 0,
                    ]
                ];
                $app->select("proposals",[
                    "proposals.id",
                    "proposals.content",
                ], $where, function ($data) use (&$datas,$jatbi,$app) {
                    $datas[] = [
                        "value" => $data['id'],
                        "text" => '#'.$data['id'].' - '.$data['content'],
                    ];
                });
                echo json_encode($datas);
            }
        })->setPermissions(['proposal']);

        $app->router("/search/accountants", ['GET','POST'], function($vars) use ($app, $jatbi) {
            if ($app->method() === 'POST') {
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $searchValue = isset($_POST['keyword']) ? $_POST['keyword'] : '';
                $parent = isset($_POST['parent']) ? $_POST['parent'] : '';
                $where = [
                    "AND" => [
                        "OR" => [
                            "proposal_target.name[~]" => $searchValue,
                        ],
                        "proposal_target.status" => 'A',
                        "proposal_target.deleted" => 0,
                    ]
                ];
                if($parent){
                    $where['AND']['proposal_target_workflows.workflows'] = $parent;
                }
                $app->select("proposal_target",[
                    "[>]proposal_target_workflows" => ["id"=>"target"],
                ],[
                    "proposal_target.id",
                    "proposal_target.name",
                ], $where, function ($data) use (&$datas,$jatbi,$app) {
                    $datas[] = [
                        "value" => $data['id'],
                        "text" => $data['name'],
                    ];
                });
                echo json_encode($datas);
            }
        })->setPermissions(['proposal']);

        $app->router("/search/objects", ['GET','POST'], function($vars) use ($app, $jatbi) {
            if ($app->method() === 'POST') {
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $searchValue = isset($_POST['keyword']) ? $_POST['keyword'] : '';
                $where = [
                    "AND" => [
                        "OR" => [
                            "proposal_objects.name[~]" => $searchValue,
                        ],
                        "proposal_objects.status" => 'A',
                        "proposal_objects.deleted" => 0,
                    ]
                ];
                $app->select("proposal_objects",[
                    "proposal_objects.id",
                    "proposal_objects.name",
                ], $where, function ($data) use (&$datas,$jatbi,$app) {
                    $datas[] = [
                        "value" => $data['id'],
                        "text" => $data['name'],
                    ];
                });
                echo json_encode($datas);
            }
        })->setPermissions(['proposal']);

        $app->router("/search/stores", ['GET', 'POST'], function ($vars) use ($app, $jatbi) {
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
                $app->select("stores", [
                    "stores.id",
                    "stores.name",
                ], $where, function ($data) use (&$datas, $jatbi, $app) {
                    $datas[] = [
                        "value" => $data['id'],
                        "text" => $data['name'],
                    ];
                });
                echo json_encode($datas);
            }
        })->setPermissions(['proposal']);

        $app->router("/buzz/{active}", 'POST', function($vars) use ($app, $jatbi, $template,$common,$account,$process) {
            $data = $app->get("proposals","*",["active"=>$app->xss($vars['active']),"deleted"=>0]);
            if($data>1){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $DataProcess = $process->workflows($data['id'],$data['account']);
                $node_approval = null;
                foreach ($DataProcess as $key => $item) {
                    if ($item['node_id'] == $data['process'] && isset($DataProcess[$key + 1])) {
                        $node_approval = $DataProcess[$key + 1];
                        break;
                    }
                }
                $insert = [
                    "proposal" => $data['id'],
                    "account" => $account['id'],
                    "account_buzz" => $node_approval['approver_account_id'],
                    "date" => date("Y-m-d H:i:s"),
                ];
                $app->insert("proposal_buzz",$insert);
                $content_notification = $account['name'].' đã gửi thông báo nhắc nhở từ đề xuất #'.$data['id'];
                $account_notification = $insert['account_buzz'];
                $jatbi->notification($account['id'],$account_notification,'Nhắc nhở Đề xuất #'.$data['id'],$content_notification,'/proposal/views/'.$data['active'],'');
                echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
            }
            else {
                echo json_encode(['status'=>'error','content'=>$jatbi->lang("Không tìm thấy đề xuất")]);
            }
        })->setPermissions(['proposal.add']);

        $app->router("/buzz-views/{active}", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$common,$account) {
            $vars['title'] = $jatbi->lang("Lịch sử Buzz");
            $data = $app->get("proposals","*",["active"=>$app->xss($vars['active']),"deleted"=>0]);
            if($data>1){
               $vars['buzzs'] = $app->select("proposal_buzz", [
                    "[>]accounts" => ["account" => "id"],
                    "[>]accounts(buzz)" => ["account_buzz" => "id"], // alias "buzz"
                ], [
                    "proposal_buzz.id",
                    "proposal_buzz.date",
                    "buzz.name(buzz)",        // lấy name của bảng accounts alias buzz
                    "accounts.name(account)", // lấy name của bảng accounts alias mặc định
                ], [
                    "proposal" => $data['id']
                ]);
                echo $app->render($template.'/proposal/buzz-views.html', $vars, $jatbi->ajax());
            }
        })->setPermissions(['proposal.add']);

        $app->router("/remind/{active}",  ['GET','POST'], function($vars) use ($app, $jatbi, $template,$common,$account) {
            $data = $app->get("proposals","*",["active"=>$app->xss($vars['active']),"deleted"=>0]);
            if($data>1){
                if($app->method()==='GET'){
                    $vars['title'] = $jatbi->lang("Nhắc nhở");
                    $vars['data'] = $data;
                    echo $app->render($template.'/proposal/remind-post.html', $vars, $jatbi->ajax());
                }
                if($app->method()==='POST'){
                    $app->header([
                        'Content-Type' => 'application/json',
                    ]);
                    $insert = [
                        "proposal"  => $data['id'],
                        "account"   => $account['id'],
                        "date"      => $app->xss($_POST['date']),
                        "active"    => $jatbi->active(),
                        "modify"    => date("Y-m-d H:i:s"),
                    ];
                    $app->insert("proposal_remind",$insert);
                    echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                }
            }
            else {
                echo json_encode(['status'=>'error','content'=>$jatbi->lang("Không tìm thấy đề xuất")]);
            }
        })->setPermissions(['proposal.add']);

        $app->router("/remind-views/{active}", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$common,$account) {
            $vars['title'] = $jatbi->lang("Lịch sử nhắc nhở");
            $data = $app->get("proposals","*",["active"=>$app->xss($vars['active']),"deleted"=>0]);
            if($data>1){
               $vars['reminds'] = $app->select("proposal_remind","*", [
                    "proposal" => $data['id'],
                    "account" => $account['id'],
                    "deleted" => 0,
                ]);
                echo $app->render($template.'/proposal/remind-views.html', $vars, $jatbi->ajax());
            }
        })->setPermissions(['proposal.add']);

        $app->router("/remind-deleted/{active}", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$setting,$account) {
            $vars['title'] = $jatbi->lang("Xóa nhắc nhở");
            if($app->method()==='GET'){
                echo $app->render($setting['template'].'/common/deleted.html', $vars, $jatbi->ajax());
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $data = $app->get("proposal_remind","*",["active"=>$vars['active'],"deleted"=>0,"account"=>$account['id']]);
                if(isset($data['id'])){
                    $app->update("proposal_remind",["deleted"=> 1],["id"=>$data['id']]);
                    echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra")]);
                }
            }
        })->setPermissions(['proposal.config.deleted']);

        $app->router("/update/{active}", 'POST', function($vars) use ($app, $jatbi, $template, $setting) {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $data = $app->get("proposals", "*", ["active" => $app->xss($vars['active']), "deleted" => 0,"status"=>[0,3]]);
            if ($data) {
                $type = $app->xss($_POST['type'] ?? '');
                $value = $app->xss($_POST['value'] ?? '');
                if($type==''){
                    $error = ['status' => 'error', 'content' => $jatbi->lang("Có lỗi xảy ra, không tìm thấy đề xuất")];
                }
                if (empty($error)) {
                    if($type=='details'){
                        $detailID = $app->xss($_POST['details'] ?? '');
                        $code = $app->xss($_POST['code'] ?? '');
                        $col = $app->xss($_POST['col'] ?? '');
                        if($code=='create'){
                            $insert = [
                                "type" => 0,
                                "content" => '',
                                "data" => 0,
                                "total" => 0,
                                "proposal" => $data['id'],
                                "active" => $jatbi->active(),
                            ];
                            $app->insert("proposal_details",$insert);
                            echo json_encode(["status"=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
                        }
                        elseif($code=='deleted'){
                            $detail = $app->get("proposal_details","*",["active"=>$detailID,"deleted"=>0,"proposal"=>$data['id']]);
                            if($detail>1){
                                $app->update("proposal_details",["deleted"=>1],["id"=>$detail['id']]);
                                $response['status'] = 'success';
                                $response['content'] = $jatbi->lang("Cập nhật thành công");
                                echo json_encode($response);
                            }
                            else{
                                echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
                            }
                        }
                        elseif($code=='edit'){
                            $detail = $app->get("proposal_details","*",["active"=>$detailID,"deleted"=>0,"proposal"=>$data['id']]);
                            if($detail>1){
                                $update['total'] = $detail['total'];
                                if($col=='price'){
                                    $value = str_replace(".","",$value);
                                    $update['total'] = ($detail['amount'] ?? 0)*$value;
                                }
                                elseif($col=='amount'){
                                    $value = str_replace(".","",$value);
                                    $update['total'] = ($detail['price'] ?? 0)*$value;
                                }
                                $update[$col] = $value;
                                $app->update("proposal_details",$update,["id"=>$detail['id']]);
                                $response['status'] = 'success';
                                $response['content'] = $jatbi->lang("Cập nhật thành công");
                                $response['data'] = [
                                    ["id"=>$detail['active'],"total"=>'999999'],
                                ];
                                echo json_encode($response);
                            }
                            else{
                                echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
                            }
                        }
                    }
                    elseif($type=='price'){
                        $vat_price = $data['vat_price'];
                        $update[$type] = str_replace(",","", $value);
                        if($data['vat_type']==0){
                            $vat_price = ($update[$type]*$data['vat_percent'])/100;
                        }
                        else {
                            $vat_price = $update[$type]-($update[$type]/(1+($data['vat_percent']/100)));
                        }
                        $update['vat_price'] = $vat_price;
                        $update['modify'] = date("Y-m-d H:i:s");
                        $app->update("proposals", $update, ["id" => $data['id']]);
                        echo json_encode(["status"=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
                    }
                    elseif($type=='vat_percent'){
                        $vat_price = $data['vat_price'];
                        $update[$type] = str_replace(",","", $value);
                        if($data['vat_type']==0){
                            $vat_price = ($data['price']*$update[$type])/100;
                        }
                        else {
                            $vat_price = $data['price']-($data['price']/(1+($update[$type]/100)));
                        }
                        $update['vat_price'] = $vat_price;
                        $update['modify'] = date("Y-m-d H:i:s");
                        $app->update("proposals", $update, ["id" => $data['id']]);
                        echo json_encode(["status"=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
                    }
                    elseif($type=='vat_type'){
                        $vat_price = $data['vat_price'];
                        $update[$type] = $value;
                        if($update[$type]==0){
                            $vat_price = ($data['price']*$data['vat_percent'])/100;
                        }
                        else {
                            $vat_price = $data['price']-($data['price']/(1+($data['vat_percent']/100)));
                        }
                        $update['vat_price'] = $vat_price;
                        $update['modify'] = date("Y-m-d H:i:s");
                        $app->update("proposals", $update, ["id" => $data['id']]);
                        echo json_encode(["status"=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
                    }
                    else {
                        $update[$type] = $value;
                        $update['modify'] = date("Y-m-d H:i:s");
                        $app->update("proposals", $update, ["id" => $data['id']]);
                        echo json_encode(["status"=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
                    }
                } else {
                    echo json_encode($error);
                }
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xảy ra, không tìm thấy đề xuất")]);
            }
        })->setPermissions(['proposal.add']);

        $app->router("/comments/{active}", 'POST', function($vars) use ($app, $jatbi, $template,$common,$account) {
            $data = $app->get("proposals","*",["active"=>$app->xss($vars['active']),"deleted"=>0]);
            if($data>1){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
                $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
                $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
                $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
                $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'proposal_comments.date';
                $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
                $status = isset($_POST['status']) ? [$_POST['status'],$_POST['status']] : '';
                $stores = isset($_POST['stores']) ? $_POST['stores'] : $jatbi->stores();
                $categorys = isset($_POST['categorys']) ? $_POST['categorys'] : '';
                $units = isset($_POST['units']) ? $_POST['units'] : '';
                $where = [
                    "AND" => [
                        "OR" => [
                            "proposal_comments.content[~]" => $searchValue,
                        ],
                        "proposal_comments.deleted" => 0,
                        "proposal_comments.proposal" => $data['id'],
                    ],
                    "LIMIT" => [$start, $length],
                    "ORDER" => [$orderName => strtoupper($orderDir)]
                ];
                $count = $app->count("proposal_comments",[
                    "[>]accounts"=>["account"=>"id"],
                ],[
                    "proposal_comments.id"
                ],[
                    "AND" => $where['AND']
                ]);
                $app->select("proposal_comments",[
                    "[>]accounts"=>["account"=>"id"],
                ],[
                    "proposal_comments.id",
                    "proposal_comments.active",
                    "proposal_comments.content",
                    "proposal_comments.date",
                    "accounts.name (accounts)",
                    "accounts.avatar (avatar)",
                ], $where, function ($data) use (&$datas, $jatbi,$app,$common) {
                    $content = '<div class="d-flex justify-content-start align-items-start">
                                    <div class="me-2"><img data-src="/'.$data['avatar'].'" class="width height rounded-2 lazyload" style="--width:30px;--height:30px;"></div>
                                    <div class="d-flex justify-content-between align-items-center w-100">
                                        <div>
                                            <div><strong>'.$data['accounts'].'</strong> <span class="ms-3 text-muted fst-italic">'.$jatbi->datetime($data['date']).'</span></div>
                                            <div>'.$data['content'].'</div>
                                        </div>
                                        <div>
                                            <button class="btn text-eclo" data-table-load="datatable-comments" data-action="modal" data-url="/proposal/comments-deleted?box='.$data['active'].'"><i class="ti ti-trash fs-5"></i></button>
                                        </div>
                                    </div>
                                </div>';
                    $datas[] = [
                        "content" => $content ?? '',
                    ];
                });
                echo json_encode([
                    "draw" => $draw,
                    "recordsTotal" => $count ?? 0,
                    "recordsFiltered" => $count ?? 0,
                    "data" => $datas ?? []
                ]);
            }
        })->setPermissions(['proposal.add']);

        $app->router("/comments-add/{active}", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$common,$account) {
            $vars['title'] = $jatbi->lang("Thêm bình luận");
            $data = $app->get("proposals","*",["active"=>$app->xss($vars['active']),"deleted"=>0]);
            if($data>1){
                if($app->method()==='GET'){
                    echo $app->render($template.'/proposal/comments-post.html', $vars, $jatbi->ajax());
                }
                elseif($app->method()==='POST'){
                    $app->header([
                        'Content-Type' => 'application/json',
                    ]);
                    if($app->xss($_POST['content'])==''){
                        $error = ["status"=>"error","content"=>$jatbi->lang("Vui lòng không để trống")];
                    }
                    if(empty($error)){
                        $insert = [
                            "active"        => $jatbi->active(),
                            "proposal"      => $data['id'],
                            "date"          => date("Y-m-d H:i:s"),
                            "content"       => $app->xss($_POST['content']),
                            "account"       => $account['id'],
                        ];
                        $app->insert("proposal_comments",$insert);
                        $getAccounts = $app->select("proposal_accounts","account",["proposal"=>$data['id'],"deleted"=>0,"account[!]"=>$account['id']]);
                        $content_notification = $account['name'].' đã bình luận đề xuất #'.$data['id'];
                        foreach($getAccounts as $account_notification){
                            // $account_notification = $acc;
                            $jatbi->notification($account['id'],$account_notification,'Bình luận Đề xuất #'.$data['id'],$content_notification,'/proposal/views/'.$data['active'],'');
                        }
                        echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                    }
                    else {
                        echo json_encode($error);
                    }
                }
            }
        })->setPermissions(['proposal.add']);

        $app->router("/comments-deleted", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$setting) {
            $vars['title'] = $jatbi->lang("Xóa Bình luận");
            if($app->method()==='GET'){
                echo $app->render($setting['template'].'/common/deleted.html', $vars, $jatbi->ajax());
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $boxid = explode(',', $app->xss($_GET['box']));
                $datas = $app->select("proposal_comments","*",["active"=>$boxid,"deleted"=>0]);
                if(count($datas)>0){
                    foreach($datas as $data){
                        $app->update("proposal_comments",["deleted"=> 1],["id"=>$data['id']]);
                    }
                    echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra")]);
                }
            }
        })->setPermissions(['proposal.config.deleted']);

        $app->router("/consultation/{active}", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$common,$account) {
            $vars['title'] = $jatbi->lang("Tham vấn");
            $data = $app->get("proposals","*",["active"=>$app->xss($vars['active']),"deleted"=>0]);
            if($data>1){
                if($app->method()==='GET'){
                    $vars['accounts'] =$app->select("accounts",["id (value)","name(text)"],["deleted"=>0,"status"=>'A',"type"=>1,"id[!]"=>$account['id']]);
                    echo $app->render($template.'/proposal/consultation-post.html', $vars, $jatbi->ajax());
                }
                elseif($app->method()==='POST'){
                    $app->header([
                        'Content-Type' => 'application/json',
                    ]);
                    $getdata = $app->count("proposal_consultation", "id",[
                        "proposal"=>$data['id'],
                        "status"=>0,
                        "account_consultation"=>$app->xss($_POST['consultation']),
                        "account" => $account['id'],
                        "process" => $data['process'],
                    ]);
                    if($app->xss($_POST['content'])=='' || $app->xss($_POST['consultation'])==''){
                        $error = ["status"=>"error","content"=>$jatbi->lang("Vui lòng không để trống")];
                    }
                    elseif($getdata>0){
                        $error = ["status"=>"error","content"=>$jatbi->lang("Người này bạn đã gửi tham vấn vui lòng chờ trả lời")];
                    }
                    if(empty($error)){
                        $insert = [
                            "active"        => $jatbi->active(),
                            "proposal"      => $data['id'],
                            "date"          => date("Y-m-d H:i:s"),
                            "date_reply"          => date("Y-m-d H:i:s"),
                            "content"       => $app->xss($_POST['content']),
                            "account"       => $account['id'],
                            "status"        => 0,
                            "process"       =>  $data['process'],
                            "account_consultation" => $app->xss($_POST['consultation']),
                        ];
                        $app->insert("proposal_consultation",$insert);
                        $getaccounts = $app->count("proposal_accounts","*",["proposal"=>$data['id'],"account" => $insert['account_consultation'],]);
                        if($getaccounts==0){
                            $app->insert("proposal_accounts",[
                                "proposal"  => $data['id'],
                                "account"  => $insert['account_consultation'],
                            ]);
                            $content_notification = $account['name'].' Yêu cầu tham vấn cho đề xuất #'.$data['id'];
                            $account_notification = $insert['account_consultation'];
                            $jatbi->notification($account['id'],$account_notification,'Tham vấn đề xuất #'.$data['id'],$content_notification,'/proposal/views/'.$data['active'],'');
                        }
                        echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                    }
                    else {
                        echo json_encode($error);
                    }
                }
            }
        })->setPermissions(['proposal.add']);

        $app->router("/consultation-reply/{active}", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$common,$account) {
            $vars['title'] = $jatbi->lang("Trả lời Tham vấn");
            $data = $app->get("proposals","*",["active"=>$app->xss($vars['active']),"deleted"=>0]);
            if($data>1){
                if($app->method()==='GET'){
                    $vars['accounts'] =$app->select("accounts",["id (value)","name(text)"],["deleted"=>0,"status"=>'A',"type"=>1,"id[!]"=>$account['id']]);
                    echo $app->render($template.'/proposal/consultation-reply.html', $vars, $jatbi->ajax());
                }
                elseif($app->method()==='POST'){
                    $app->header([
                        'Content-Type' => 'application/json',
                    ]);
                    $getdata = $app->get("proposal_consultation", "*",["proposal"=>$data['id'],"status"=>0,"account_consultation"=>$account['id']]);
                    if($getdata>1){
                        if($_POST['content'] == ""){
                            echo json_encode(['status'=>'error','content'=>$lang['loi-trong']]);
                        }
                        if ($_POST['content']){
                            $insert = [
                                "content_reply" => $app->xss($_POST['content']),
                                "status" => 1,
                                "date_reply" => date("Y-m-d H:i:s"),
                            ];
                            $app->update("proposal_consultation",$insert,["id"=>$getdata['id']]);
                            $content_notification = $account['name'].' Đã trả lời tham vấn đề xuất #'.$data['id'];
                            $account_notification = $getdata['account'];
                            $jatbi->notification($account['id'],$account_notification,'Tham vấn đề xuất #'.$data['id'],$content_notification,'/proposal/views/'.$data['active'],'');
                            echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                        }
                    }
                    else {
                        echo json_encode(['status'=>'error','content'=>$jatbi->lang("Không tìm thấy")]);
                    }
                }
            }
        })->setPermissions(['proposal.add']);
    })->middleware('login');
 ?>