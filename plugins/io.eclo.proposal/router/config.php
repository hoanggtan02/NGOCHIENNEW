<?php
    use ECLO\App;
    $template = __DIR__.'/../templates';
    $jatbi = $app->getValueData('jatbi');
    $setting = $app->getValueData('setting');
    $account = $app->getValueData('account');
    $common = $jatbi->getPluginCommon('io.eclo.proposal');
    $app->group("/proposal/config",function($app) use($setting,$jatbi, $common, $template,$account) {
        $app->router("", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$account,$common) {
            if($app->method()==='GET'){
                $vars['title'] = $jatbi->lang("Cấu hình đề xuất");
                $vars['sub_title'] = $jatbi->lang("Hình thức");
                echo $app->render($template.'/config/form.html', $vars);
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
                $status = isset($_POST['status']) ? [$_POST['status'],$_POST['status']] : '';
                $stores = isset($_POST['stores']) ? $_POST['stores'] : $jatbi->stores();
                $where = [
                    "AND" => [
                        "OR" => [
                            "proposal_form.name[~]" => $searchValue,
                            "proposal_form.code[~]" => $searchValue,
                        ],
                        "proposal_form.status[<>]" => $status,
                        "proposal_form.deleted" => 0,
                    ],
                    "LIMIT" => [$start, $length],
                    "ORDER" => [$orderName => strtoupper($orderDir)]
                ];
                if (!empty($stores)) {
                    $where["AND"]["stores_linkables.stores"] = $stores;
                }
                $count = $app->count("proposal_form",[
                    "[>]accountants_code"=>["record"=>"id"],
                    "[>]proposal_groups"=>["group"=>"id"],
                    "[>]stores_linkables" => ["id" => "data","AND"=>["stores_linkables.type"=>"proposal_form"]],
                ],[
                    "@proposal_form.id"
                ],[
                    "AND" => $where['AND']
                ]);
                $app->select("proposal_form",[
                    "[>]accountants_code"=>["record"=>"id"],
                    "[>]proposal_groups"=>["group"=>"id"],
                    "[>]stores_linkables" => ["id" => "data","AND"=>["stores_linkables.type"=>"proposal_form"]],
                ],[
                    "@proposal_form.id",
                    "proposal_form.active",
                    "proposal_form.code",
                    "proposal_form.name",
                    "proposal_form.form",
                    "proposal_form.status",
                    "accountants_code.code (record)",
                    "proposal_groups.name (group)",
                ], $where, function ($data) use (&$datas, $jatbi,$app,$common) {
                    $stores = $app->select("stores_linkables", [
                        "[>]stores" => ["stores_linkables.stores" => "id",],
                    ], ['stores.name',], ['stores_linkables.data' => $data['id'],'stores_linkables.type'=>"proposal_form"]);
                    $storeNames = array_map(function($store) {return $store['name'];}, $stores);
                    $datas[] = [
                        "checkbox" => $app->component("box",["data"=>$data['active']]),
                        "name" => $data['name'],
                        "code" => $data['code'],
                        "group" => $data['group'],
                        "form" => $common['proposal-form'][$data['form']]['name'],
                        "record" => $data['record'],
                        "stores" => implode(', ',$storeNames),
                        "status" => $app->component("status",["url"=>"/proposal/config/form-status/".$data['active'],"data"=>$data['status'],"permission"=>['proposal.config.edit']]),
                        "action" => $app->component("action",[
                            "button" => [
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Sửa"),
                                    'permission' => ['proposal.config.edit'],
                                    'action' => ['data-url' => '/proposal/config/form-edit/'.$data['active'], 'data-action' => 'modal']
                                ],
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Xóa"),
                                    'permission' => ['proposal.config.deleted'],
                                    'action' => ['data-url' => '/proposal/config/form-deleted?box='.$data['active'], 'data-action' => 'modal']
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
        })->setPermissions(['proposal.config']);

        $app->router("/form-search", ['GET','POST'], function($vars) use ($app, $jatbi) {
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
                            "proposal_form.code[~]" => $searchValue,
                        ],
                        "proposal_form.status" => 'A',
                        "proposal_form.deleted" => 0,
                        "stores_linkables.stores" => $jatbi->stores(),
                    ]
                ];
                $app->select("proposal_form",  [
                    "[>]stores_linkables" => ["id" => "data","AND"=>["stores_linkables.type"=>"proposal_form"]]
                ],[
                    "@proposal_form.id",
                    "proposal_form.name"
                ], $where, function ($data) use (&$datas,$jatbi,$app) {
                    $datas[] = [
                        "value" => $data['id'],
                        "text" => $data['name'],
                    ];
                });
                echo json_encode($datas);
            }
        })->setPermissions(['warehouses']);

        $app->router("/form-add", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$common) {
            $vars['title'] = $jatbi->lang("Thêm Hình thức đề xuất");
            if($app->method()==='GET'){
                $forms = $common['proposal-form'];
                $vars['forms'] = array_map(function($item) {
                    return [
                        'text' => $item['name'],
                        'value' => $item['id']
                    ];
                }, $forms);
                $vars['records'] = $app->select("accountants_code",["id (value)","text"=>App::raw("CONCAT(code, ' - ', name)")],["deleted"=>0,"status"=>"A"]);
                $vars['groups'] = $app->select("proposal_groups",["id (value)","text"=>App::raw("CONCAT(code, ' - ', name)")],["deleted"=>0,"status"=>"A","type"=>1,]);
                $vars['data'] = [
                    "status" => 'A',
                    "type" => '',
                    "form" => '',
                    "record" => '',
                    'stores' => '',
                ];
                echo $app->render($template.'/config/form-post.html', $vars, $jatbi->ajax());
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                if($app->xss($_POST['name'])=='' || $app->xss($_POST['status'])=='' || $app->xss($_POST['form'])==''){
                    $error = ["status"=>"error","content"=>$jatbi->lang("Vui lòng không để trống")];
                }
                if(empty($error)){
                    $insert = [
                        "name"          => $app->xss($_POST['name']),
                        "code"          => $app->xss($_POST['code']),
                        "form"          => $app->xss($_POST['form']),
                        "record"        => $app->xss($_POST['record']),
                        "status"        => $app->xss($_POST['status']),
                        "notes"         => $app->xss($_POST['notes']),
                        "group"         => $app->xss($_POST['group']),
                        "active"        => $jatbi->active(),
                    ];
                    $app->insert("proposal_form",$insert);
                    $getID = $app->id();
                    $jatbi->setStores("add",'proposal_form',$getID,$_POST['stores'] ?? '');
                    echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                    $jatbi->logs('form','form-add',$insert);
                }
                else {
                    echo json_encode($error);
                }
            }
        })->setPermissions(['proposal.config.add']);

        $app->router("/form-edit/{id}", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$setting,$common) {
            $vars['title'] = $jatbi->lang("Sửa Hình thức đề xuất");
            $data = $app->get("proposal_form","*",["active"=>$vars['id'],"deleted"=>0]);
            if($app->method()==='GET'){
                if($data>1){
                    $vars['data'] = $data;
                    $vars['data']['stores'] = $jatbi->setStores("select",'proposal_form',$vars['data']['id']);
                    $forms = $common['proposal-form'];
                    $vars['forms'] = array_map(function($item) {
                        return [
                            'text' => $item['name'],
                            'value' => $item['id']
                        ];
                    }, $forms);
                    $vars['records'] = $app->select("accountants_code",["id (value)","text"=>App::raw("CONCAT(code, ' - ', name)")],["deleted"=>0,"status"=>"A"]);
                    $vars['groups'] = $app->select("proposal_groups",["id (value)","text"=>App::raw("CONCAT(code, ' - ', name)")],["deleted"=>0,"status"=>"A","type"=>1,]);
                    echo $app->render($template.'/config/form-post.html', $vars, $jatbi->ajax());
                }
                else {
                    echo $app->render($setting['template'].'/pages/error.html', $vars, $jatbi->ajax());
                }
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                if($data>1){
                    if($app->xss($_POST['name'])=='' || $app->xss($_POST['status'])=='' || $app->xss($_POST['form'])==''){
                        $error = ["status"=>"error","content"=>$jatbi->lang("Vui lòng không để trống")];
                    }
                    if(empty($error)){
                        $insert = [
                            "name"          => $app->xss($_POST['name']),
                            "code"          => $app->xss($_POST['code']),
                            "form"          => $app->xss($_POST['form']),
                            "record"        => $app->xss($_POST['record']),
                            "status"        => $app->xss($_POST['status']),
                            "notes"         => $app->xss($_POST['notes']),
                            "group"         => $app->xss($_POST['group']),
                        ];
                        $app->update("proposal_form",$insert,["id"=>$data['id']]);
                        $jatbi->setStores("edit",'proposal_form',$data['id'],$_POST['stores'] ?? '');
                        echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                        $jatbi->logs('form','form-edit',$insert);
                    }
                    else {
                        echo json_encode($error);
                    }
                }
                else {
                    echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
                }
            }
        })->setPermissions(['proposal.config.edit']);

        $app->router("/form-status/{id}", 'POST', function($vars) use ($app, $jatbi, $template) {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $data = $app->get("proposal_form","*",["active"=>$vars['id'],"deleted"=>0]);
            if($data>1){
                if($data>1){
                    if($data['status']==='A'){
                        $status = "D";
                    } 
                    elseif($data['status']==='D'){
                        $status = "A";
                    }
                    $app->update("proposal_form",["status"=>$status],["id"=>$data['id']]);
                    $jatbi->logs('form','form-status',$data);
                    echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Cập nhật thất bại"),]);
                }
            }
            else {
                echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
            }
        })->setPermissions(['proposal.config.edit']);

        $app->router("/form-deleted", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$setting) {
            $vars['title'] = $jatbi->lang("Xóa Hình thức đề xuất");
            if($app->method()==='GET'){
                echo $app->render($setting['template'].'/common/deleted.html', $vars, $jatbi->ajax());
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $boxid = explode(',', $app->xss($_GET['box']));
                $datas = $app->select("proposal_form","*",["active"=>$boxid,"deleted"=>0]);
                if(count($datas)>0){
                    foreach($datas as $data){
                        $app->update("proposal_form",["deleted"=> 1],["id"=>$data['id']]);
                        $name[] = $data['name'];
                    }
                    $jatbi->logs('form','form-deleted',$datas);
                    $jatbi->trash('/proposal/config/form-restore',"Hình thức đề xuất với tên: ".implode(', ',$name),["database"=>'proposal_form',"data"=>$boxid]);
                    echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra")]);
                }
            }
        })->setPermissions(['proposal.config.deleted']);

        $app->router("/form-restore/{id}", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$setting) {
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
                        $app->update("proposal_form",["deleted"=>0],["active"=>$active]);
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

        $app->router("/object", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$account,$common) {
            if($app->method()==='GET'){
                $vars['title'] = $jatbi->lang("Cấu hình đề xuất");
                $vars['sub_title'] = $jatbi->lang("Quy trình");
                echo $app->render($template.'/config/objects.html', $vars);
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
                $status = isset($_POST['status']) ? [$_POST['status'],$_POST['status']] : '';
                $stores = isset($_POST['stores']) ? $_POST['stores'] : $jatbi->stores();
                $categorys = isset($_POST['categorys']) ? $_POST['categorys'] : '';
                $units = isset($_POST['units']) ? $_POST['units'] : '';
                $where = [
                    "AND" => [
                        "OR" => [
                            "proposal_workflows.name[~]" => $searchValue,
                            "proposal_workflows.code[~]" => $searchValue,
                        ],
                        "proposal_workflows.status[<>]" => $status,
                        "proposal_workflows.deleted" => 0,
                    ],
                    "LIMIT" => [$start, $length],
                    "ORDER" => [$orderName => strtoupper($orderDir)]
                ];
                $count = $app->count("proposal_workflows",[
                    "[>]accountants_code"=>["record"=>"id"]
                ],[
                    "@proposal_workflows.id"
                ],[
                    "AND" => $where['AND']
                ]);
                $app->select("proposal_workflows",[
                    // "[>]accountants_code"=>["record"=>"id"]
                ],[
                    "proposal_workflows.id",
                    "proposal_workflows.active",
                    "proposal_workflows.code",
                    "proposal_workflows.name",
                    "proposal_workflows.type",
                    "proposal_workflows.workflows",
                    "proposal_workflows.status",
                    // "accountants_code.code (record)",
                ], $where, function ($data) use (&$datas, $jatbi,$app,$common) {
                    $datas[] = [
                        "checkbox" => $app->component("box",["data"=>$data['active']]),
                        "name" => $data['name'],
                        "code" => $data['code'],
                        "type" => $common['proposal'][$data['type']]['name'],
                        "workflows" => $common['proposal-workflows'][$data['workflows']]['name'],
                        // "record" => $data['record'],
                        "status" => $app->component("status",["url"=>"/proposal/config/workflows-status/".$data['active'],"data"=>$data['status'],"permission"=>['proposal.config.edit']]),
                        "action" => $app->component("action",[
                            "button" => [
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Sửa"),
                                    'permission' => ['proposal.config.edit'],
                                    'action' => ['data-url' => '/proposal/config/workflows-edit/'.$data['active'], 'data-action' => 'modal']
                                ],
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Xóa"),
                                    'permission' => ['proposal.config.deleted'],
                                    'action' => ['data-url' => '/proposal/config/workflows-deleted?box='.$data['active'], 'data-action' => 'modal']
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
        })->setPermissions(['proposal.config']);

        $app->router("/workflows", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$account,$common) {
            if($app->method()==='GET'){
                $vars['title'] = $jatbi->lang("Cấu hình đề xuất");
                $vars['sub_title'] = $jatbi->lang("Quy trình");
                echo $app->render($template.'/config/workflows.html', $vars);
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
                $status = isset($_POST['status']) ? [$_POST['status'],$_POST['status']] : '';
                $stores = isset($_POST['stores']) ? $_POST['stores'] : $jatbi->stores();
                $where = [
                    "AND" => [
                        "OR" => [
                            "proposal_workflows.name[~]" => $searchValue,
                            "proposal_workflows.code[~]" => $searchValue,
                        ],
                        "proposal_workflows.status[<>]" => $status,
                        "proposal_workflows.deleted" => 0,
                    ],
                    "LIMIT" => [$start, $length],
                    "ORDER" => [$orderName => strtoupper($orderDir)]
                ];
                if (!empty($stores)) {
                    $where["AND"]["stores_linkables.stores"] = $stores;
                }
                $count = $app->count("proposal_workflows",[
                    "[>]stores_linkables" => ["id" => "data","AND"=>["stores_linkables.type"=>"proposal_workflows"]],
                ],["@proposal_workflows.id",],[
                    "AND" => $where['AND']
                ]);
                $app->select("proposal_workflows",[
                    "[>]stores_linkables" => ["id" => "data","AND"=>["stores_linkables.type"=>"proposal_workflows"]],
                ],[
                    "@proposal_workflows.id",
                    "proposal_workflows.active",
                    "proposal_workflows.name",
                    "proposal_workflows.status",
                    "proposal_workflows.code",
                    "proposal_workflows.type",
                ], $where, function ($data) use (&$datas, $jatbi,$app,$common) {
                    $forms = $app->select("proposal_workflows_form",["[>]proposal_form"=>["form"=>"id"],],["proposal_form.name"],[ "proposal_workflows_form.workflows"=>$data['id'] ] );
                    $forms_name = array_map(function($form) {return $form['name'];}, $forms);
                    $stores = $app->select("stores_linkables", [
                        "[>]stores" => ["stores_linkables.stores" => "id",],
                    ], ['stores.name',], ['stores_linkables.data' => $data['id'],'stores_linkables.type'=>"proposal_workflows"]);
                    $storeNames = array_map(function($store) {return $store['name'];}, $stores);
                    $datas[] = [
                        "checkbox" => $app->component("box",["data"=>$data['active']]),
                        "name" => $data['name'],
                        "code" => $data['code'],
                        "type" => $common['proposal'][$data['type']]['name'],
                        "form" => implode(', ',$forms_name),
                        "stores" => implode(', ',$storeNames),
                        // "record" => $data['record'],
                        "status" => $app->component("status",["url"=>"/proposal/config/workflows-status/".$data['active'],"data"=>$data['status'],"permission"=>['proposal.config.edit']]),
                        "action" => $app->component("action",[
                            "button" => [
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Cấu hình"),
                                    'permission' => ['proposal.config'],
                                    'action' => ['data-url' => '/proposal/config/workflows-config/'.$data['active'], 'data-action' => 'modal']
                                ],
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Sửa"),
                                    'permission' => ['proposal.config.edit'],
                                    'action' => ['data-url' => '/proposal/config/workflows-edit/'.$data['active'], 'data-action' => 'modal']
                                ],
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Xóa"),
                                    'permission' => ['proposal.config.deleted'],
                                    'action' => ['data-url' => '/proposal/config/workflows-deleted?box='.$data['active'], 'data-action' => 'modal']
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
        })->setPermissions(['proposal.config']);

        $app->router("/workflows-add", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$common) {
            $vars['title'] = $jatbi->lang("Thêm Quy trình đề xuất");
            if($app->method()==='GET'){
                $types = $common['proposal'];
                $forms = $common['proposal-form'];
                $vars['types'] = array_map(function($item) {
                    return [
                        'text' => $item['name'],
                        'value' => $item['id']
                    ];
                }, $types);
                $vars['forms'] = [];
                $vars['records'] = $app->select("accountants_code",["id (value)","text"=>App::raw("CONCAT(code, ' - ', name)")],["deleted"=>0,"status"=>"A"]);
                $vars['data'] = [
                    "status" => 'A',
                    "type" => '',
                    "form" => '',
                    "stores" => '',
                ];
                $vars['formSelect'] = [];
                echo $app->render($template.'/config/workflows-post.html', $vars, $jatbi->ajax());
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                if($app->xss($_POST['name'])=='' || $app->xss($_POST['status'])==''  || $app->xss($_POST['type'])==''  || empty($_POST['form']) ){
                    $error = ["status"=>"error","content"=>$jatbi->lang("Vui lòng không để trống")];
                }
                if(empty($error)){
                    $insert = [
                        "name"          => $app->xss($_POST['name']),
                        "code"          => $app->xss($_POST['code']),
                        "type"          => $app->xss($_POST['type']),
                        "status"        => $app->xss($_POST['status']),
                        "notes"         => $app->xss($_POST['notes']),
                        "active"        => $jatbi->active(),
                    ];
                    $app->insert("proposal_workflows",$insert);
                    $getID = $app->id();
                    $jatbi->setStores("add",'proposal_workflows',$getID,$_POST['stores'] ?? '');
                    foreach($_POST['form'] as $form){
                        $insert_from = [
                            "workflows" => $getID,
                            "form" => $form,
                        ];
                        $app->insert("proposal_workflows_form",$insert_from);
                    }
                    echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                    $jatbi->logs('workflows','workflows-add',$insert);
                }
                else {
                    echo json_encode($error);
                }
            }
        })->setPermissions(['proposal.config.add']);

        $app->router("/workflows-edit/{id}", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$setting,$common) {
            $vars['title'] = $jatbi->lang("Sửa Quy trình đề xuất");
            $data = $app->get("proposal_workflows","*",["active"=>$vars['id'],"deleted"=>0]);
            if($app->method()==='GET'){
                if($data>1){
                    $vars['data'] = $data;
                    $vars['data']['stores'] = $jatbi->setStores("select",'proposal_workflows',$vars['data']['id']);
                    $types = $common['proposal'];
                    $forms = $common['proposal-form'];
                    $vars['types'] = array_map(function($item) {
                        return [
                            'text' => $item['name'],
                            'value' => $item['id']
                        ];
                    }, $types);
                    $vars['forms'] = $app->select("proposal_workflows_form",["[>]proposal_form"=>["form"=>"id"],],["proposal_form.name (text)","proposal_form.id (value)"],[ "proposal_workflows_form.workflows"=>$data['id'] ] );
                    $formSelect = array_map(function($form) {return $form['value'];}, $vars['forms']);
                    $vars['formSelect'] = $formSelect;
                    // $vars['forms'] = array_map(function($item) {
                    //     return [
                    //         'text' => $item['name'],
                    //         'value' => $item['id']
                    //     ];
                    // }, $forms);
                    $vars['records'] = $app->select("accountants_code",["id (value)","text"=>App::raw("CONCAT(code, ' - ', name)")],["deleted"=>0,"status"=>"A"]);
                    echo $app->render($template.'/config/workflows-post.html', $vars, $jatbi->ajax());
                }
                else {
                    echo $app->render($setting['template'].'/pages/error.html', $vars, $jatbi->ajax());
                }
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                if($data>1){
                    if($app->xss($_POST['name'])=='' || $app->xss($_POST['status'])=='' || $app->xss($_POST['type'])=='' || empty($_POST['form']) ){
                        $error = ["status"=>"error","content"=>$jatbi->lang("Vui lòng không để trống")];
                    }
                    if(empty($error)){
                        $insert = [
                            "name"          => $app->xss($_POST['name']),
                            "code"          => $app->xss($_POST['code']),
                            "type"          => $app->xss($_POST['type']),
                            "status"        => $app->xss($_POST['status']),
                            "notes"         => $app->xss($_POST['notes']),
                        ];
                        $app->update("proposal_workflows",$insert,["id"=>$data['id']]);
                        $jatbi->setStores("edit",'proposal_workflows',$data['id'],$_POST['stores'] ?? '');
                        $app->delete("proposal_workflows_form",["workflows"=>$data['id']]);
                        foreach($_POST['form'] as $form){
                            $insert_from = [
                                "workflows" => $data['id'],
                                "form" => $form,
                            ];
                            $app->insert("proposal_workflows_form",$insert_from);
                        }
                        echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                        $jatbi->logs('workflows','workflows-edit',$insert);
                    }
                    else {
                        echo json_encode($error);
                    }
                }
                else {
                    echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
                }
            }
        })->setPermissions(['proposal.config.edit']);

        $app->router("/workflows-config/{id}", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$setting,$common) {
            $vars['title'] = $jatbi->lang("Thiết lặp Quy trình đề xuất");
            $data = $app->get("proposal_workflows","*",["active"=>$vars['id'],"deleted"=>0]);
            if($app->method()==='GET'){
                if($data>1){
                    $vars['data'] = $data;
                    echo $app->render($template.'/config/workflows-config.html', $vars, $jatbi->ajax());
                }
                else {
                    echo $app->render($setting['template'].'/pages/error.html', $vars, $jatbi->ajax());
                }
            }
        })->setPermissions(['proposal.config.edit']);

        $app->router("/workflows-config-add/{id}", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$setting,$common) {
            $vars['title'] = $jatbi->lang("Thêm Tiến trình");
            $workflows = $app->get("proposal_workflows","*",["active"=>$vars['id'],"deleted"=>0]);
            if($app->method()==='GET'){
                if($workflows>1){
                    $vars['data'] = [
                        "type" => '',
                        "approval" => '',
                    ];
                    echo $app->render($template.'/config/workflows-config-post.html', $vars, $jatbi->ajax());
                }
                else {
                    echo $app->render($setting['template'].'/pages/error.html', $vars, $jatbi->ajax());
                }
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $type = $app->xss($_POST['type'] ?? '');
                $name = $app->xss($_POST['name'] ?? '');
                $code = $app->xss($_POST['code'] ?? '');
                $background = $app->xss($_POST['background'] ?? '');
                $color = $app->xss($_POST['color'] ?? '');
                $notes = $app->xss($_POST['notes'] ?? '');
                $status = $app->xss($_POST['status'] ?? '');
                $approval = $app->xss($_POST['approval'] ?? '');
                $account = $app->xss($_POST['account'] ?? '');
                $follows = json_encode($_POST['follows'] ?? []);
                if($name == '' || $code == '' || $background == '' || $color == '' || $status == ''  || $approval==''){
                    $error = ["status" => "error","content"=>$jatbi->lang("Vui lòng không để trống")];
                }
                if($type=='start'){
                    $checkstart = $app->count("proposal_workflows_nodes","id",["type"=>$type,"workflows"=>$workflows['id'],"deleted"=>0]);
                    if($checkstart>0){
                        $error = ["status" => "error","content"=>$jatbi->lang("Đã có tiến trình bắt đầu")];
                    }
                }
                if($type=='finish'){
                    $checkfinish = $app->count("proposal_workflows_nodes","id",["type"=>$type,"workflows"=>$workflows['id'],"deleted"=>0]);
                    if($checkfinish>0){
                        $error = ["status" => "error","content"=>$jatbi->lang("Đã có tiến trình kết thúc")];
                    }
                }
                if(empty($error)){
                    $insert = [
                        "workflows"=>$workflows['id'],
                        "type" => $type,
                        "data" => json_encode(["name"=>"Lê Hoài Nam","content"=>"Đã ok"]),
                        "position_top" => 0,
                        "position_left" => 0,
                        "date" => date("Y-m-d H:i:s"),
                        "modify" => date("Y-m-d H:i:s"),
                        "active"=>$jatbi->active(),
                        "name"=>$name,
                        "code"=>$code,
                        "background"=>$background,
                        "color"=>$color,
                        "notes"=>$notes,
                        "status"=>$status,
                        "approval"=>$approval,
                        "account"=>$account,
                        "follows"=>$follows,
                    ];
                    $app->insert("proposal_workflows_nodes",$insert);
                    $getID = $app->id();
                    $htmlaccount = '';
                    switch ($insert['approval']) {
                        case '2':
                            $approval = $jatbi->lang("Cấp quản lý");
                            break;
                        default:
                             $approval = $jatbi->lang("Tài khoản");
                             $user = $app->get("accounts",["name","avatar"],["id"=>$insert['account']]);
                             if(!empty($user)){
                                     $htmlaccount = '<div class="d-flex justify-content-start align-items-center">
                                        <div class="me-2"><img data-src="/'.$user['avatar'].'" class="lazyload width height rounded-circle" style="--widht:30px;--height:30px;"></div>
                                        <strong>'.$user['name'].'</strong>
                                    </div>';
                                }
                            break;
                    }
                    $header = '<div class="p-1 w-100 fw-bold px-2 py-3 rounded-top-4" style="background:'.$insert['background'].';color:'.$insert['color'].'">'.$insert['name'].'</div>';
                    
                    $body = '<div class="mb-2"><i>'.$jatbi->lang("Loại xét duyệt").'</i>: <strong>'.$approval.'</strong></div>'.$htmlaccount;
                    $footer = '<div><button class="btn btn-sm p-2" data-action="offcanvas" data-url="/proposal/config/workflows-config-edit/'.$insert['active'].'"><i class="ti ti-edit"></i></button></div>';
                    $content = [ 
                        "header" => $header,
                        "body" => $body,
                        "footer" => $footer,
                    ];
                    echo json_encode([
                        "status"=>"success",
                        "content"=>$jatbi->lang("Thêm tiến trình thành công"),
                        "nodeData" => [
                            "id"=>$getID,
                            "type"=>$insert['type'],
                            "data"=> $content,
                        ]
                    ]);
                }
                else {
                    echo json_encode($error);
                }
            }
        })->setPermissions(['proposal.config.add']);

        $app->router("/workflows-config-edit/{id}", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$setting,$common) {
            $vars['title'] = $jatbi->lang("Thêm Tiến trình");
            $data = $app->get("proposal_workflows_nodes","*",["active"=>$vars['id'],"deleted"=>0]);
            if($app->method()==='GET'){
                if($data>1){
                    $vars['data'] = $data;
                    $vars['accounts'][] = $app->get("accounts",["id (value)","name (text)"],["id"=>$data['account']]);
                    $vars['data']['follows'] = json_decode($data['follows']);
                    $vars['follows'] = $app->select("accounts",["id (value)","name (text)"],["id"=>$vars['data']['follows']]);
                    echo $app->render($template.'/config/workflows-config-post.html', $vars, $jatbi->ajax());
                }
                else {
                    echo $app->render($setting['template'].'/pages/error.html', $vars, $jatbi->ajax());
                }
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $type = $app->xss($_POST['type'] ?? '');
                $name = $app->xss($_POST['name'] ?? '');
                $code = $app->xss($_POST['code'] ?? '');
                $background = $app->xss($_POST['background'] ?? '');
                $color = $app->xss($_POST['color'] ?? '');
                $notes = $app->xss($_POST['notes'] ?? '');
                $status = $app->xss($_POST['status'] ?? '');
                $approval = $app->xss($_POST['approval'] ?? '');
                $account = $app->xss($_POST['account'] ?? '');
                $follows = json_encode($_POST['follows'] ?? []);
                if($name == '' || $code == '' || $background == '' || $color == '' || $status == ''  || $approval==''){
                    $error = ["status" => "error","content"=>$jatbi->lang("Vui lòng không để trống")];
                }
                if($type=='start' && $type!=$data['type']){
                    $checkstart = $app->count("proposal_workflows_nodes","id",["type"=>$type,"workflows"=>$data['workflows'],"deleted"=>0]);
                    if($checkstart>0){
                        $error = ["status" => "error","content"=>$jatbi->lang("Đã có tiến trình bắt đầu")];
                    }
                }
                if($type=='finish' && $type!=$data['type']){
                    $checkfinish = $app->count("proposal_workflows_nodes","id",["type"=>$type,"workflows"=>$data['workflows'],"deleted"=>0]);
                    if($checkfinish>0){
                        $error = ["status" => "error","content"=>$jatbi->lang("Đã có tiến trình kết thúc")];
                    }
                }
                if(empty($error)){
                    $insert = [
                        "type" => $type,
                        "modify" => date("Y-m-d H:i:s"),
                        "name"=>$name,
                        "code"=>$code,
                        "background"=>$background,
                        "color"=>$color,
                        "notes"=>$notes,
                        "status"=>$status,
                        "approval"=>$approval,
                        "account"=>$account,
                        "follows"=>$follows,
                    ];
                    $app->update("proposal_workflows_nodes",$insert,["id"=>$data['id']]);
                    $htmlaccount = '';
                    switch ($insert['approval']) {
                        case '2':
                            $approval = $jatbi->lang("Cấp quản lý");
                            break;
                        default:
                             $approval = $jatbi->lang("Tài khoản");
                             $user = $app->get("accounts",["name","avatar"],["id"=>$insert['account']]);
                             if(!empty($user)){
                                     $htmlaccount = '<div class="d-flex justify-content-start align-items-center">
                                        <div class="me-2"><img data-src="/'.$user['avatar'].'" class="lazyload width height rounded-circle" style="--widht:30px;--height:30px;"></div>
                                        <strong>'.$user['name'].'</strong>
                                    </div>';
                                }
                            break;
                    }
                    $header = '<div class="p-1 w-100 fw-bold px-2 py-3 rounded-top-4" style="background:'.$insert['background'].';color:'.$insert['color'].'">'.$insert['name'].'</div>';
                    
                    $body = '<div class="mb-2"><i>'.$jatbi->lang("Loại xét duyệt").'</i>: <strong>'.$approval.'</strong></div>'.$htmlaccount;
                    $footer = '<div><button class="btn btn-sm p-2" data-action="offcanvas" data-url="/proposal/config/workflows-config-edit/'.$data['active'].'"><i class="ti ti-edit"></i></button></div>';
                    $content = [ 
                        "header" => $header,
                        "body" => $body,
                        "footer" => $footer,
                    ];
                    echo json_encode([
                        "status"=>"success",
                        "content"=>$jatbi->lang("Sửa tiến trình thành công"),
                        "nodeData" => [
                            "id"=>$data['id'],
                            "type"=>$insert['type'],
                            "data"=> $content,
                        ]
                    ]);
                }
                else {
                    echo json_encode($error);
                }
            }
        })->setPermissions(['proposal.config.edit']);

        $app->router("/workflows-config-update/{type}", 'POST', function($vars) use ($app, $jatbi, $template,$setting,$common) {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $type = $app->xss($vars['type']);
            $workflows = $app->xss($_POST['workflow_id']);
            $data = $app->get("proposal_workflows","*",["active"=>$workflows,"deleted"=>0,"status"=>'A']);
            if(!empty($data)){
                if($type=='load'){
                    $json = [
                        "id" => $data['active'],
                        "name" => $data['name'],
                    ];
                    $selectNodes = $app->select("proposal_workflows_nodes","*",["workflows"=>$data['id'],"deleted"=>0]);
                    foreach($selectNodes as $node){
                        $htmlaccount = '';
                        switch ($node['approval']) {
                            case '2':
                                $approval = $jatbi->lang("Cấp quản lý");
                                break;
                            default:
                                 $approval = $jatbi->lang("Tài khoản");
                                 $user = $app->get("accounts",["name","avatar"],["id"=>$node['account']]);
                                 if(!empty($user)){
                                         $htmlaccount = '<div class="d-flex justify-content-start align-items-center">
                                            <div class="me-2"><img data-src="/'.$user['avatar'].'" class="lazyload width height rounded-circle" style="--widht:30px;--height:30px;"></div>
                                            <strong>'.$user['name'].'</strong>
                                        </div>';
                                    }
                                break;
                        }
                        $header = '<div class="p-1 w-100 fw-bold px-2 py-3 rounded-top-4" style="background:'.$node['background'].';color:'.$node['color'].'">'.$node['name'].'</div>';
                        
                        $body = '<div class="mb-2"><i>'.$jatbi->lang("Loại xét duyệt").'</i>: <strong>'.$approval.'</strong></div>'.$htmlaccount;
                        $footer = '<div><button class="btn btn-sm p-2" data-action="offcanvas" data-url="/proposal/config/workflows-config-edit/'.$node['active'].'"><i class="ti ti-edit"></i></button></div>';
                        $content = [ 
                            "header" => $header,
                            "body" => $body,
                            "footer" => $footer,
                        ];
                        $json["nodes"][] = [
                            "id" => $node['id'],
                            "workflow_id"=> $data['active'],
                            "type"=> $node['type'],
                            "data"=> $content,
                            "top"=> $node['position_top'],
                            "left"=> $node['position_left'],
                        ];
                    }
                    $connections = $app->select("proposal_workflows_connections","*",["workflows"=>$data['id'],"deleted"=>0]);
                    foreach($connections as $connect){
                        $json["connections"][] = [
                            "source" => $connect['source_node'],
                            "target"=> $connect['target_node'],
                            "source_endpoint_type" => $connect['endpoint_type'] ?? 'default',
                        ];
                    }
                    echo json_encode($json);
                }
                elseif($type=='node-position'){
                    $nodeID = $app->xss($_POST['node_id']);
                    $node = $app->get("proposal_workflows_nodes","*",["id"=> $nodeID,"deleted"=>0]);
                    if(!empty($node)){
                        $update = [
                            "position_top" => $app->xss($_POST['position_top']),
                            "position_left" => $app->xss($_POST['position_left']),
                        ];
                        $app->update("proposal_workflows_nodes",$update,["id"=>$node['id']]);
                        echo json_encode(["status"=>"success","content"=>$jatbi->lang("Cập nhật thành công")]);
                    }
                    else{
                       echo json_encode(["status"=>"error","content"=>$jatbi->lang("Lỗi: Node không tồn tại")]);
                    }
                }
                elseif($type=='node-delete'){
                    $nodeID = $app->xss($_POST['node_id']);
                    $node = $app->get("proposal_workflows_nodes","*",["id"=> $nodeID,"deleted"=>0]);
                    if(!empty($node)){
                        $app->update("proposal_workflows_nodes",["deleted"=>1],["id"=>$node['id']]);
                        echo json_encode(["status"=>"success","content"=>$jatbi->lang("Cập nhật thành công")]);
                    }
                    else{
                       echo json_encode(["status"=>"error","content"=>$jatbi->lang("Lỗi: Node không tồn tại")]);
                    }
                }
                elseif($type=='connect-create'){
                    $insert = [
                        "workflows" => $data['id'],
                        "source_node" => $app->xss($_POST['source_node_id']),
                        "target_node" => $app->xss($_POST['target_node_id']),
                        "target_node" => $app->xss($_POST['target_node_id']),
                        "endpoint_type" => $app->xss($_POST['source_endpoint_type'] ?? 'default'),
                        "date" => date("Y-m-d H:i:s"),
                        "active" => $jatbi->active(),
                    ];
                    $app->insert("proposal_workflows_connections",$insert);
                    echo json_encode(["status"=>"success","content"=>$jatbi->lang("Cập nhật thành công")]);
                }
                elseif($type=='connect-deleted'){
                    $app->update("proposal_workflows_connections",["deleted"=>1],[
                        "source_node" => $app->xss($_POST['source_node_id']),
                        "target_node" => $app->xss($_POST['target_node_id']),
                        "endpoint_type" => $app->xss($_POST['source_endpoint_type'] ?? 'default'),
                    ]);
                    echo json_encode(["status"=>"success","content"=>$jatbi->lang("Cập nhật thành công")]);
                }
            }
            else {
                echo json_encode(["status"=>"error","content"=>$jatbi->lang("Cập nhật thành công")]);
            }
        })->setPermissions(['proposal.config']);

        $app->router("/workflows-status/{id}", 'POST', function($vars) use ($app, $jatbi, $template) {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $data = $app->get("proposal_workflows","*",["active"=>$vars['id'],"deleted"=>0]);
            if($data>1){
                if($data>1){
                    if($data['status']==='A'){
                        $status = "D";
                    } 
                    elseif($data['status']==='D'){
                        $status = "A";
                    }
                    $app->update("proposal_workflows",["status"=>$status],["id"=>$data['id']]);
                    $jatbi->logs('workflows','workflows-status',$data);
                    echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Cập nhật thất bại"),]);
                }
            }
            else {
                echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
            }
        })->setPermissions(['proposal.config.edit']);

        $app->router("/workflows-deleted", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$setting) {
            $vars['title'] = $jatbi->lang("Xóa Quy trình đề xuất");
            if($app->method()==='GET'){
                echo $app->render($setting['template'].'/common/deleted.html', $vars, $jatbi->ajax());
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $boxid = explode(',', $app->xss($_GET['box']));
                $datas = $app->select("proposal_workflows","*",["active"=>$boxid,"deleted"=>0]);
                if(count($datas)>0){
                    foreach($datas as $data){
                        $app->update("proposal_workflows",["deleted"=> 1],["id"=>$data['id']]);
                        $name[] = $data['name'];
                    }
                    $jatbi->logs('workflows','workflows-deleted',$datas);
                    $jatbi->trash('/proposal/config/workflows-restore',"Quy trình đề xuất với tên: ".implode(', ',$name),["database"=>'proposal_workflows',"data"=>$boxid]);
                    echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra")]);
                }
            }
        })->setPermissions(['proposal.config.deleted']);

        $app->router("/workflows-restore/{id}", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$setting) {
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
                        $app->update("proposal_workflows",["deleted"=>0],["active"=>$active]);
                    }
                    $app->delete("trashs",["id"=>$trash['id']]);
                    $jatbi->logs('workflows','workflows-restore',$datas);
                    echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra")]);
                }
            }
        })->setPermissions(['proposal.config.deleted']);

        $app->router("/target", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$account,$common) {
            if($app->method()==='GET'){
                $vars['title'] = $jatbi->lang("Cấu hình đề xuất");
                $vars['sub_title'] = $jatbi->lang("Hạng mục");
                echo $app->render($template.'/config/target.html', $vars);
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
                $status = isset($_POST['status']) ? [$_POST['status'],$_POST['status']] : '';
                $stores = isset($_POST['stores']) ? $_POST['stores'] : $jatbi->stores();
                $where = [
                    "AND" => [
                        "OR" => [
                            "proposal_target.name[~]" => $searchValue,
                            "proposal_target.code[~]" => $searchValue,
                        ],
                        "proposal_target.status[<>]" => $status,
                        "proposal_target.deleted" => 0,
                    ],
                    "LIMIT" => [$start, $length],
                    "ORDER" => [$orderName => strtoupper($orderDir)]
                ];
                if (!empty($stores)) {
                    $where["AND"]["stores_linkables.stores"] = $stores;
                }
                $count = $app->count("proposal_target",[
                    "[>]accountants_code"=>["record"=>"id"],
                    "[>]proposal_groups"=>["group"=>"id"],
                    "[>]stores_linkables" => ["id" => "data","AND"=>["stores_linkables.type"=>"proposal_target"]],
                ],[
                    "@proposal_target.id"
                ],[
                    "AND" => $where['AND']
                ]);
                $app->select("proposal_target",[
                    "[>]accountants_code"=>["record"=>"id"],
                    "[>]proposal_groups"=>["group"=>"id"],
                    "[>]stores_linkables" => ["id" => "data","AND"=>["stores_linkables.type"=>"proposal_target"]],
                ],[
                    "@proposal_target.id",
                    "proposal_target.active",
                    "proposal_target.code",
                    "proposal_target.name",
                    "proposal_target.status",
                    "accountants_code.code (record)",
                    "proposal_groups.name (group)",
                ],$where,  function ($data) use (&$datas, $jatbi,$app,$common) {
                    $workflows = $app->select("proposal_target_workflows",["[>]proposal_workflows"=>["workflows"=>"id"],],["proposal_workflows.name"],[ "proposal_target_workflows.target"=>$data['id'] ] );
                    $workflows_name = array_map(function($workflow) {return $workflow['name'];}, $workflows);$stores = $app->select("stores_linkables", [
                        "[>]stores" => ["stores_linkables.stores" => "id",],
                    ], ['stores.name',], ['stores_linkables.data' => $data['id'],'stores_linkables.type'=>"proposal_target"]);
                    $storeNames = array_map(function($store) {return $store['name'];}, $stores);
                    $datas[] = [
                        "checkbox" => $app->component("box",["data"=>$data['active']]),
                        "name" => $data['name'],
                        "code" => $data['code'],
                        "group" => $data['group'],
                        "workflows" => implode(', ',$workflows_name),
                        "stores" => implode(', ',$storeNames),
                        "record" => $data['record'],
                        "status" => $app->component("status",["url"=>"/proposal/config/target-status/".$data['active'],"data"=>$data['status'],"permission"=>['proposal.config.edit']]),
                        "action" => $app->component("action",[
                            "button" => [
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Sửa"),
                                    'permission' => ['proposal.config.edit'],
                                    'action' => ['data-url' => '/proposal/config/target-edit/'.$data['active'], 'data-action' => 'modal']
                                ],
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Xóa"),
                                    'permission' => ['proposal.config.deleted'],
                                    'action' => ['data-url' => '/proposal/config/target-deleted?box='.$data['active'], 'data-action' => 'modal']
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
        })->setPermissions(['proposal.config']);

        $app->router("/target-add", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$common) {
            $vars['title'] = $jatbi->lang("Thêm Hạng mục đề xuất");
            if($app->method()==='GET'){
                $types = $common['proposal'];
                $forms = $common['proposal-form'];
                $vars['types'] = array_map(function($item) {
                    return [
                        'text' => $item['name'],
                        'value' => $item['id']
                    ];
                }, $types);
                $vars['workflows'] = $app->select("proposal_workflows", [
                    "[>]stores_linkables" => ["id" => "data","AND"=>["stores_linkables.type"=>"proposal_workflows"]]
                ],[
                    "@proposal_workflows.id (value)",
                    "proposal_workflows.name (text)"
                ], [
                    "proposal_workflows.deleted"=>0,
                    "proposal_workflows.status"=> "A",
                    "stores_linkables.stores" => $jatbi->stores(),
                ]);
                $vars['records'] = $app->select("accountants_code",["id (value)","text"=>App::raw("CONCAT(code, ' - ', name)")],["deleted"=>0,"status"=>"A"]);
                $vars['groups'] = $app->select("proposal_groups",["id (value)","text"=>App::raw("CONCAT(code, ' - ', name)")],["deleted"=>0,"status"=>"A","type"=>2,]);
                $vars['data'] = [
                    "status" => 'A',
                    "type" => '',
                    "record" => '',
                    "stores" => '',
                ];
                $vars['workflowsSelect'] = [];
                echo $app->render($template.'/config/target-post.html', $vars, $jatbi->ajax());
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                if($app->xss($_POST['name'])=='' || $app->xss($_POST['status'])=='' || $app->xss($_POST['group'])=='' || empty($_POST['workflows'])){
                    $error = ["status"=>"error","content"=>$jatbi->lang("Vui lòng không để trống")];
                }
                if(empty($error)){
                    $insert = [
                        "name"          => $app->xss($_POST['name']),
                        "code"          => $app->xss($_POST['code']),
                        "record"        => $app->xss($_POST['record']),
                        "status"        => $app->xss($_POST['status']),
                        "notes"         => $app->xss($_POST['notes']),
                        "group"         => $app->xss($_POST['group']),
                        "active"        => $jatbi->active(),
                    ];
                    $app->insert("proposal_target",$insert);
                    $getID = $app->id();
                    $jatbi->setStores("add",'proposal_target',$getID,$_POST['stores'] ?? '');
                    foreach($_POST['workflows'] as $workflows){
                        $insert_workflows = [
                            "target" => $getID,
                            "workflows" => $workflows,
                        ];
                        $app->insert("proposal_target_workflows",$insert_workflows);
                    }
                    echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                    $jatbi->logs('target','target-add',$insert);
                }
                else {
                    echo json_encode($error);
                }
            }
        })->setPermissions(['proposal.config.add']);

        $app->router("/target-edit/{id}", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$setting,$common) {
            $vars['title'] = $jatbi->lang("Sửa Hạng mục đề xuất");
            $data = $app->get("proposal_target","*",["active"=>$vars['id'],"deleted"=>0]);
            if($app->method()==='GET'){
                if($data>1){
                    $vars['data'] = $data;
                    $vars['data']['stores'] = $jatbi->setStores("select",'proposal_target',$vars['data']['id']);
                    $types = $common['proposal'];
                    $forms = $common['proposal-form'];
                    $vars['types'] = array_map(function($item) {
                        return [
                            'text' => $item['name'],
                            'value' => $item['id']
                        ];
                    }, $types);
                    $vars['workflows'] = $app->select("proposal_workflows", [
                        "[>]stores_linkables" => ["id" => "data","AND"=>["stores_linkables.type"=>"proposal_workflows"]]
                    ],[
                        "@proposal_workflows.id (value)",
                        "proposal_workflows.name (text)"
                    ], [
                        "proposal_workflows.deleted"=>0,
                        "proposal_workflows.status"=> "A",
                        "stores_linkables.stores" => $jatbi->stores(),
                    ]);
                    $workflowsSelect = $app->select("proposal_target_workflows",["[>]proposal_workflows"=>["workflows"=>"id"],],["proposal_workflows.name (text)","proposal_workflows.id (value)"],[ "proposal_target_workflows.target"=>$data['id'] ] );
                    $vars['workflowsSelect'] = array_map(function($workflows) {return $workflows['value'];}, $workflowsSelect);
                    // $vars['forms'] = array_map(function($item) {
                    //     return [
                    //         'text' => $item['name'],
                    //         'value' => $item['id']
                    //     ];
                    // }, $forms);
                    $vars['records'] = $app->select("accountants_code",["id (value)","text"=>App::raw("CONCAT(code, ' - ', name)")],["deleted"=>0,"status"=>"A"]);
                    $vars['groups'] = $app->select("proposal_groups",["id (value)","text"=>App::raw("CONCAT(code, ' - ', name)")],["deleted"=>0,"status"=>"A","type"=>2,]);
                    echo $app->render($template.'/config/target-post.html', $vars, $jatbi->ajax());
                }
                else {
                    echo $app->render($setting['template'].'/pages/error.html', $vars, $jatbi->ajax());
                }
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                if($data>1){
                    if($app->xss($_POST['name'])=='' || $app->xss($_POST['status'])=='' || $app->xss($_POST['group'])=='' || empty($_POST['workflows'])){
                        $error = ["status"=>"error","content"=>$jatbi->lang("Vui lòng không để trống")];
                    }
                    if(empty($error)){
                        $insert = [
                            "name"          => $app->xss($_POST['name']),
                            "code"          => $app->xss($_POST['code']),
                            "record"        => $app->xss($_POST['record']),
                            "status"        => $app->xss($_POST['status']),
                            "notes"         => $app->xss($_POST['notes']),
                            "group"         => $app->xss($_POST['group']),
                        ];
                        $app->update("proposal_target",$insert,["id"=>$data['id']]);
                        $jatbi->setStores("edit",'proposal_target',$data['id'],$_POST['stores'] ?? '');
                        $app->delete("proposal_target_workflows",["target"=>$data['id']]);
                        foreach($_POST['workflows'] as $workflows){
                            $insert_workflows = [
                                "target" => $data['id'],
                                "workflows" => $workflows,
                            ];
                            $app->insert("proposal_target_workflows",$insert_workflows);
                        }
                        echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                        $jatbi->logs('target','target-edit',$insert);
                    }
                    else {
                        echo json_encode($error);
                    }
                }
                else {
                    echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
                }
            }
        })->setPermissions(['proposal.config.edit']);

        $app->router("/target-status/{id}", 'POST', function($vars) use ($app, $jatbi, $template) {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $data = $app->get("proposal_target","*",["active"=>$vars['id'],"deleted"=>0]);
            if($data>1){
                if($data>1){
                    if($data['status']==='A'){
                        $status = "D";
                    } 
                    elseif($data['status']==='D'){
                        $status = "A";
                    }
                    $app->update("proposal_target",["status"=>$status],["id"=>$data['id']]);
                    $jatbi->logs('target','target-status',$data);
                    echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Cập nhật thất bại"),]);
                }
            }
            else {
                echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
            }
        })->setPermissions(['proposal.config.edit']);

        $app->router("/target-deleted", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$setting) {
            $vars['title'] = $jatbi->lang("Xóa Hạng mục đề xuất");
            if($app->method()==='GET'){
                echo $app->render($setting['template'].'/common/deleted.html', $vars, $jatbi->ajax());
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $boxid = explode(',', $app->xss($_GET['box']));
                $datas = $app->select("proposal_target","*",["active"=>$boxid,"deleted"=>0]);
                if(count($datas)>0){
                    foreach($datas as $data){
                        $app->update("proposal_target",["deleted"=> 1],["id"=>$data['id']]);
                        $name[] = $data['name'];
                    }
                    $jatbi->logs('target','target-deleted',$datas);
                    $jatbi->trash('/proposal/config/target-restore',"Hạng mục đề xuất với tên: ".implode(', ',$name),["database"=>'proposal_target',"data"=>$boxid]);
                    echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra")]);
                }
            }
        })->setPermissions(['proposal.config.deleted']);

        $app->router("/target-restore/{id}", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$setting) {
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
                        $app->update("proposal_target",["deleted"=>0],["active"=>$active]);
                    }
                    $app->delete("trashs",["id"=>$trash['id']]);
                    $jatbi->logs('target','target-restore',$datas);
                    echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra")]);
                }
            }
        })->setPermissions(['proposal.config.deleted']);

        $app->router("/accountants-code", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$account,$common) {
            if($app->method()==='GET'){
                $vars['title'] = $jatbi->lang("Cấu hình đề xuất");
                $vars['sub_title'] = $jatbi->lang("Mã tài khoản");
                echo $app->render($template.'/config/accountants-code.html', $vars);
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
                $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
                $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
                $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
                $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'code';
                $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'ASC';
                $status = isset($_POST['status']) ? [$_POST['status'],$_POST['status']] : '';
                $where = [
                    "AND" => [
                        "OR" => [
                            "name[~]" => $searchValue,
                            "code[~]" => $searchValue,
                        ],
                        "status[<>]" => $status,
                        "deleted" => 0,
                    ],
                    "LIMIT" => [$start, $length],
                    "ORDER" => [$orderName => strtoupper($orderDir)]
                ];
                $count = $app->count("accountants_code",["id"],["AND" => $where['AND']]);
                $app->select("accountants_code","*", $where, function ($data) use (&$datas, $jatbi,$app) {
                    $datas[] = [
                        "checkbox" => $app->component("box",["data"=>$data['id']]),
                        "code" => $data['code'] ?? '',
                        "name" => $data['name'],
                        "status" => $app->component("status",["url"=>"/proposal/config/accountants-code-status/".$data['id'],"data"=>$data['status'],"permission"=>['proposal.config.edit']]),
                        "action" => $app->component("action",[
                            "button" => [
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Sửa"),
                                    'permission' => ['proposal.config.edit'],
                                    'action' => ['data-url' => '/proposal/config/accountants-code-edit/'.$data['id'], 'data-action' => 'modal']
                                ],
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Xóa"),
                                    'permission' => ['proposal.config.deleted'],
                                    'action' => ['data-url' => '/proposal/config/accountants-code-deleted?box='.$data['id'], 'data-action' => 'modal']
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
        })->setPermissions(['proposal.config']);

        $app->router("/accountants-code-add", ['GET','POST'], function($vars) use ($app, $jatbi, $template) {
            $vars['title'] = $jatbi->lang("Thêm Mã tài khoản");
            if($app->method()==='GET'){
                $vars['data'] = [
                    "status" => 'A',
                    "main" => 0,
                ];
                echo $app->render($template.'/config/accountants-code-post.html', $vars, $jatbi->ajax());
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                if($app->xss($_POST['name'])=='' || $app->xss($_POST['status'])=='' || $app->xss($_POST['code'])==''){
                    $error = ["status"=>"error","content"=>$jatbi->lang("Vui lòng không để trống")];
                }
                if(empty($error)){
                    $insert = [
                        "name"          => $app->xss($_POST['name']),
                        "code"          => $app->xss($_POST['code']),
                        "main"          => $app->xss($_POST['main']),
                        "status"        => $app->xss($_POST['status']),
                        "notes"         => $app->xss($_POST['notes']),
                    ];
                    $app->insert("accountants_code",$insert);
                    echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                    $jatbi->logs('accountants-code','accountants-code-add',$insert);
                }
                else {
                    echo json_encode($error);
                }
            }
        })->setPermissions(['proposal.config.add']);

        $app->router("/accountants-code-edit/{id}", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$setting) {
            $vars['title'] = $jatbi->lang("Sửa Mã tài khoản");
            $data = $app->get("accountants_code","*",["id"=>$vars['id'],"deleted"=>0]);
            if($app->method()==='GET'){
                if($data>1){
                    $vars['data'] = $data;
                    echo $app->render($template.'/config/accountants-code-post.html', $vars, $jatbi->ajax());
                }
                else {
                    echo $app->render($setting['template'].'/pages/error.html', $vars, $jatbi->ajax());
                }
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                if($data>1){
                    if($app->xss($_POST['name'])=='' || $app->xss($_POST['status'])=='' || $app->xss($_POST['code'])==''){
                        $error = ["status"=>"error","content"=>$jatbi->lang("Vui lòng không để trống")];
                    }
                    if(empty($error)){
                        $insert = [
                            "name"          => $app->xss($_POST['name']),
                            "code"          => $app->xss($_POST['code']),
                            "main"          => $app->xss($_POST['main']),
                            "status"        => $app->xss($_POST['status']),
                            "notes"         => $app->xss($_POST['notes']),
                        ];
                        $app->update("accountants_code",$insert,["id"=>$data['id']]);
                        echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                        $jatbi->logs('accountants-code','accountants-code-edit',$insert);
                    }
                    else {
                        echo json_encode($error);
                    }
                }
                else {
                    echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
                }
            }
        })->setPermissions(['proposal.config.edit']);

        $app->router("/accountants-code-status/{id}", 'POST', function($vars) use ($app, $jatbi, $template) {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $data = $app->get("accountants_code","*",["id"=>$vars['id'],"deleted"=>0]);
            if($data>1){
                if($data>1){
                    if($data['status']==='A'){
                        $status = "D";
                    } 
                    elseif($data['status']==='D'){
                        $status = "A";
                    }
                    $app->update("accountants_code",["status"=>$status],["id"=>$data['id']]);
                    $jatbi->logs('accountants-code','accountants-code-status',$data);
                    echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Cập nhật thất bại"),]);
                }
            }
            else {
                echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
            }
        })->setPermissions(['proposal.config.edit']);

        $app->router("/accountants-code-deleted", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$setting) {
            $vars['title'] = $jatbi->lang("Xóa Mã tài khoản");
            if($app->method()==='GET'){
                echo $app->render($setting['template'].'/common/deleted.html', $vars, $jatbi->ajax());
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $boxid = explode(',', $app->xss($_GET['box']));
                $datas = $app->select("accountants_code","*",["id"=>$boxid,"deleted"=>0]);
                if(count($datas)>0){
                    foreach($datas as $data){
                        $app->update("accountants_code",["deleted"=> 1],["id"=>$data['id']]);
                        $name[] = $data['name'];
                    }
                    $jatbi->logs('accountants-code','accountants-code-deleted',$datas);
                    $jatbi->trash('/proposal/config/accountants-code-restore',"Mã tài khoản với tên: ".implode(', ',$name),["database"=>'accountants_code',"data"=>$boxid]);
                    echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra")]);
                }
            }
        })->setPermissions(['proposal.config.deleted']);

        $app->router("/accountants-code-restore/{id}", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$setting) {
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
                        $app->update("accountants_code",["deleted"=>0],["id"=>$active]);
                    }
                    $app->delete("trashs",["id"=>$trash['id']]);
                    $jatbi->logs('accountants-code','accountants-code-restore',$datas);
                    echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra")]);
                }
            }
        })->setPermissions(['proposal.config.deleted']);

        $app->router("/groups", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$account,$common) {
            if($app->method()==='GET'){
                $vars['title'] = $jatbi->lang("Cấu hình đề xuất");
                $vars['sub_title'] = $jatbi->lang("Nhóm");
                echo $app->render($template.'/config/groups.html', $vars);
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
                $status = isset($_POST['status']) ? [$_POST['status'],$_POST['status']] : '';
                $stores = isset($_POST['stores']) ? $_POST['stores'] : $jatbi->stores();
                $where = [
                    "AND" => [
                        "OR" => [
                            "proposal_groups.name[~]" => $searchValue,
                            "proposal_groups.code[~]" => $searchValue,
                        ],
                        "proposal_groups.status[<>]" => $status,
                        "proposal_groups.deleted" => 0,
                    ],
                    "LIMIT" => [$start, $length],
                    "ORDER" => [$orderName => strtoupper($orderDir)]
                ];
                $count = $app->count("proposal_groups",[
                    "@proposal_groups.id"
                ],[
                    "AND" => $where['AND']
                ]);
                $app->select("proposal_groups",[
                    "@proposal_groups.id",
                    "proposal_groups.active",
                    "proposal_groups.type",
                    "proposal_groups.code",
                    "proposal_groups.name",
                    "proposal_groups.status",
                ], $where, function ($data) use (&$datas, $jatbi,$app,$common) {
                    $type = $data['type']==1?$jatbi->lang("Hình thức"):$jatbi->lang("Hạng mục");
                    $datas[] = [
                        "checkbox" => $app->component("box",["data"=>$data['active']]),
                        "name" => $data['name'],
                        "id" => $data['id'],
                        "code" => $data['code'],
                        "type" => $type,
                        "status" => $app->component("status",["url"=>"/proposal/config/groups-status/".$data['active'],"data"=>$data['status'],"permission"=>['proposal.config.edit']]),
                        "action" => $app->component("action",[
                            "button" => [
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Sửa"),
                                    'permission' => ['proposal.config.edit'],
                                    'action' => ['data-url' => '/proposal/config/groups-edit/'.$data['active'], 'data-action' => 'modal']
                                ],
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Xóa"),
                                    'permission' => ['proposal.config.deleted'],
                                    'action' => ['data-url' => '/proposal/config/groups-deleted?box='.$data['active'], 'data-action' => 'modal']
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
        })->setPermissions(['proposal.config']);

        $app->router("/groups-add", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$common) {
            $vars['title'] = $jatbi->lang("Thêm Nhóm");
            if($app->method()==='GET'){
                $vars['data'] = [
                    "status" => 'A',
                ];
                echo $app->render($template.'/config/groups-post.html', $vars, $jatbi->ajax());
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                if($app->xss($_POST['name'])=='' || $app->xss($_POST['status'])=='' || $app->xss($_POST['type'])==''){
                    $error = ["status"=>"error","content"=>$jatbi->lang("Vui lòng không để trống")];
                }
                if(empty($error)){
                    $insert = [
                        "type"          => $app->xss($_POST['type']),
                        "name"          => $app->xss($_POST['name']),
                        "code"          => $app->xss($_POST['code']),
                        "position"       => $app->xss($_POST['position']),
                        "status"        => $app->xss($_POST['status']),
                        "notes"         => $app->xss($_POST['notes']),
                        "active"        => $jatbi->active(),
                    ];
                    $app->insert("proposal_groups",$insert);
                    $getID = $app->id();
                    $jatbi->setStores("add",'proposal_groups',$getID,$_POST['stores'] ?? '');
                    echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                    $jatbi->logs('groups','groups-add',$insert);
                }
                else {
                    echo json_encode($error);
                }
            }
        })->setPermissions(['proposal.config.add']);

        $app->router("/groups-edit/{id}", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$setting,$common) {
            $vars['title'] = $jatbi->lang("Sửa Nhóm");
            $data = $app->get("proposal_groups","*",["active"=>$vars['id'],"deleted"=>0]);
            if($app->method()==='GET'){
                if($data>1){
                    $vars['data'] = $data;
                    echo $app->render($template.'/config/groups-post.html', $vars, $jatbi->ajax());
                }
                else {
                    echo $app->render($setting['template'].'/pages/error.html', $vars, $jatbi->ajax());
                }
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                if($data>1){
                    if($app->xss($_POST['name'])=='' || $app->xss($_POST['status'])=='' || $app->xss($_POST['type'])==''){
                        $error = ["status"=>"error","content"=>$jatbi->lang("Vui lòng không để trống")];
                    }
                    if(empty($error)){
                        $insert = [
                            "type"          => $app->xss($_POST['type']),
                            "name"          => $app->xss($_POST['name']),
                            "code"          => $app->xss($_POST['code']),
                            "notes"         => $app->xss($_POST['notes']),
                            "position"       => $app->xss($_POST['position']),
                            "status"        => $app->xss($_POST['status']),
                        ];
                        $app->update("proposal_groups",$insert,["id"=>$data['id']]);
                        echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                        $jatbi->logs('groups','groups-edit',$insert);
                    }
                    else {
                        echo json_encode($error);
                    }
                }
                else {
                    echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
                }
            }
        })->setPermissions(['proposal.config.edit']);

        $app->router("/groups-status/{id}", 'POST', function($vars) use ($app, $jatbi, $template) {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $data = $app->get("proposal_groups","*",["active"=>$vars['id'],"deleted"=>0]);
            if($data>1){
                if($data>1){
                    if($data['status']==='A'){
                        $status = "D";
                    } 
                    elseif($data['status']==='D'){
                        $status = "A";
                    }
                    $app->update("proposal_groups",["status"=>$status],["id"=>$data['id']]);
                    $jatbi->logs('groups','groups-status',$data);
                    echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Cập nhật thất bại"),]);
                }
            }
            else {
                echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
            }
        })->setPermissions(['proposal.config.edit']);

        $app->router("/groups-deleted", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$setting) {
            $vars['title'] = $jatbi->lang("Xóa Nhóm");
            if($app->method()==='GET'){
                echo $app->render($setting['template'].'/common/deleted.html', $vars, $jatbi->ajax());
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $boxid = explode(',', $app->xss($_GET['box']));
                $datas = $app->select("proposal_groups","*",["active"=>$boxid,"deleted"=>0]);
                if(count($datas)>0){
                    foreach($datas as $data){
                        $app->update("proposal_groups",["deleted"=> 1],["id"=>$data['id']]);
                        $name[] = $data['name'];
                    }
                    $jatbi->logs('groups','groups-deleted',$datas);
                    $jatbi->trash('/proposal/config/groups-restore',"Nhóm với tên: ".implode(', ',$name),["database"=>'proposal_groups',"data"=>$boxid]);
                    echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra")]);
                }
            }
        })->setPermissions(['proposal.config.deleted']);

        $app->router("/groups-restore/{id}", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$setting) {
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
                        $app->update("proposal_groups",["deleted"=>0],["active"=>$active]);
                    }
                    $app->delete("trashs",["id"=>$trash['id']]);
                    $jatbi->logs('groups','groups-restore',$datas);
                    echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra")]);
                }
            }
        })->setPermissions(['proposal.config.deleted']);

        $app->router("/objects", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$account,$common) {
            if($app->method()==='GET'){
                $vars['title'] = $jatbi->lang("Cấu hình đề xuất");
                $vars['sub_title'] = $jatbi->lang("Đối tượng");
                echo $app->render($template.'/config/objects.html', $vars);
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
                $status = isset($_POST['status']) ? [$_POST['status'],$_POST['status']] : '';
                $stores = isset($_POST['stores']) ? $_POST['stores'] : $jatbi->stores();
                $where = [
                    "AND" => [
                        "OR" => [
                            "proposal_objects.name[~]" => $searchValue,
                            "proposal_objects.code[~]" => $searchValue,
                        ],
                        "proposal_objects.status[<>]" => $status,
                        "proposal_objects.deleted" => 0,
                    ],
                    "LIMIT" => [$start, $length],
                    "ORDER" => [$orderName => strtoupper($orderDir)]
                ];
                $count = $app->count("proposal_objects",[
                    "@proposal_objects.id"
                ],[
                    "AND" => $where['AND']
                ]);
                $app->select("proposal_objects",[
                    "@proposal_objects.id",
                    "proposal_objects.active",
                    "proposal_objects.code",
                    "proposal_objects.name",
                    "proposal_objects.status",
                ], $where, function ($data) use (&$datas, $jatbi,$app,$common) {
                    $datas[] = [
                        "checkbox" => $app->component("box",["data"=>$data['active']]),
                        "name" => $data['name'],
                        "id" => $data['id'],
                        "code" => $data['code'],
                        "status" => $app->component("status",["url"=>"/proposal/config/objects-status/".$data['active'],"data"=>$data['status'],"permission"=>['proposal.config.edit']]),
                        "action" => $app->component("action",[
                            "button" => [
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Sửa"),
                                    'permission' => ['proposal.config.edit'],
                                    'action' => ['data-url' => '/proposal/config/objects-edit/'.$data['active'], 'data-action' => 'modal']
                                ],
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Xóa"),
                                    'permission' => ['proposal.config.deleted'],
                                    'action' => ['data-url' => '/proposal/config/objects-deleted?box='.$data['active'], 'data-action' => 'modal']
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
        })->setPermissions(['proposal.config']);

        $app->router("/objects-add", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$common) {
            $vars['title'] = $jatbi->lang("Thêm Đối tượng");
            if($app->method()==='GET'){
                $vars['data'] = [
                    "status" => 'A',
                ];
                echo $app->render($template.'/config/objects-post.html', $vars, $jatbi->ajax());
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                if($app->xss($_POST['name'])=='' || $app->xss($_POST['status'])==''){
                    $error = ["status"=>"error","content"=>$jatbi->lang("Vui lòng không để trống")];
                }
                if(empty($error)){
                    $insert = [
                        "name"          => $app->xss($_POST['name']),
                        "code"          => $app->xss($_POST['code']),
                        "position"       => $app->xss($_POST['position']),
                        "status"        => $app->xss($_POST['status']),
                        "notes"         => $app->xss($_POST['notes']),
                        "active"        => $jatbi->active(),
                    ];
                    $app->insert("proposal_objects",$insert);
                    $getID = $app->id();
                    $jatbi->setStores("add",'proposal_objects',$getID,$_POST['stores'] ?? '');
                    echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                    $jatbi->logs('objects','objects-add',$insert);
                }
                else {
                    echo json_encode($error);
                }
            }
        })->setPermissions(['proposal.config.add']);

        $app->router("/objects-edit/{id}", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$setting,$common) {
            $vars['title'] = $jatbi->lang("Sửa Đối tượng");
            $data = $app->get("proposal_objects","*",["active"=>$vars['id'],"deleted"=>0]);
            if($app->method()==='GET'){
                if($data>1){
                    $vars['data'] = $data;
                    echo $app->render($template.'/config/objects-post.html', $vars, $jatbi->ajax());
                }
                else {
                    echo $app->render($setting['template'].'/pages/error.html', $vars, $jatbi->ajax());
                }
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                if($data>1){
                    if($app->xss($_POST['name'])=='' || $app->xss($_POST['status'])==''){
                        $error = ["status"=>"error","content"=>$jatbi->lang("Vui lòng không để trống")];
                    }
                    if(empty($error)){
                        $insert = [
                            "name"          => $app->xss($_POST['name']),
                            "code"          => $app->xss($_POST['code']),
                            "notes"         => $app->xss($_POST['notes']),
                            "position"       => $app->xss($_POST['position']),
                            "status"        => $app->xss($_POST['status']),
                        ];
                        $app->update("proposal_objects",$insert,["id"=>$data['id']]);
                        echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                        $jatbi->logs('objects','objects-edit',$insert);
                    }
                    else {
                        echo json_encode($error);
                    }
                }
                else {
                    echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
                }
            }
        })->setPermissions(['proposal.config.edit']);

        $app->router("/objects-status/{id}", 'POST', function($vars) use ($app, $jatbi, $template) {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $data = $app->get("proposal_objects","*",["active"=>$vars['id'],"deleted"=>0]);
            if($data>1){
                if($data>1){
                    if($data['status']==='A'){
                        $status = "D";
                    } 
                    elseif($data['status']==='D'){
                        $status = "A";
                    }
                    $app->update("proposal_objects",["status"=>$status],["id"=>$data['id']]);
                    $jatbi->logs('objects','objects-status',$data);
                    echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Cập nhật thất bại"),]);
                }
            }
            else {
                echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
            }
        })->setPermissions(['proposal.config.edit']);

        $app->router("/objects-deleted", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$setting) {
            $vars['title'] = $jatbi->lang("Xóa Đối tượng");
            if($app->method()==='GET'){
                echo $app->render($setting['template'].'/common/deleted.html', $vars, $jatbi->ajax());
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $boxid = explode(',', $app->xss($_GET['box']));
                $datas = $app->select("proposal_objects","*",["active"=>$boxid,"deleted"=>0]);
                if(count($datas)>0){
                    foreach($datas as $data){
                        $app->update("proposal_objects",["deleted"=> 1],["id"=>$data['id']]);
                        $name[] = $data['name'];
                    }
                    $jatbi->logs('objects','objects-deleted',$datas);
                    $jatbi->trash('/proposal/config/objects-restore',"Đối tượng với tên: ".implode(', ',$name),["database"=>'proposal_objects',"data"=>$boxid]);
                    echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra")]);
                }
            }
        })->setPermissions(['proposal.config.deleted']);

        $app->router("/objects-restore/{id}", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$setting) {
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
                        $app->update("proposal_objects",["deleted"=>0],["active"=>$active]);
                    }
                    $app->delete("trashs",["id"=>$trash['id']]);
                    $jatbi->logs('objects','objects-restore',$datas);
                    echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra")]);
                }
            }
        })->setPermissions(['proposal.config.deleted']);
    })->middleware('login');
 ?>