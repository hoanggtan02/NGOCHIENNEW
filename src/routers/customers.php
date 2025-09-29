<?php
    if (!defined('ECLO')) die("Hacking attempt");
    $common = $app->getValueData('common');
    $account = $app->getValueData('account');
    $app->group($setting['manager']."/customers",function($app) use($setting,$jatbi, $common,$account) {
        $app->router("", ['GET','POST'], function($vars) use ($app, $jatbi,$setting) {
            $vars['title'] = $jatbi->lang("Khách hàng");
            if ($app->method() === 'GET') {
                echo $app->render($setting['template'].'/customers/customers.html', $vars);
            }
            elseif ($app->method() === 'POST') {
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
                $type = isset($_POST['type']) ? $_POST['type'] : '';
                $where = [
                    "AND" => [
                        "OR" => [
                            "customers.name[~]" => $searchValue,
                            "customers.code[~]" => $searchValue,
                            "customers.phone[~]" => $searchValue,
                            "customers.email[~]" => $searchValue,
                        ],
                        "customers.status[<>]" => $status,
                        "customers.deleted" => 0,
                        "customers.form" => 0,
                    ],
                    "LIMIT" => [$start, $length],
                    "ORDER" => [$orderName => strtoupper($orderDir)]
                ];
                if (!empty($type)) {
                    $where["AND"]["customers.type"] = $type;
                }
                $count = $app->count("customers",[
                    "AND" => $where['AND'],
                ]);
                $app->select("customers", [
                        "[>]customers_type" => ["type" => "id"],
                        "[>]accounts" => ["account" => "id"],
                    ], 
                    [
                    'customers.id',
                    'customers.name',
                    'customers.active',
                    'customers.code',
                    'customers.date',
                    'customers.phone',
                    'customers.status',
                    'customers_type.name (type)',
                    'accounts.name (user)',
                    ], $where, function ($data) use (&$datas,$jatbi,$app) {
                    $datas[] = [
                        "checkbox" => $app->component("box",["data"=>$data['active']]),
                        "type" => $data['type'],
                        "code" => $data['code'],
                        "date" => $jatbi->datetime($data['date']),
                        "name" => $data['name'],
                        "user" => $data['user'],
                        "phone" => $data['phone'],
                        "status" => $app->component("status",["url"=>"/customers/customers-status/".$data['active'],"data"=>$data['status'],"permission"=>['customers.edit']]),
                        "action" => $app->component("action",[
                            "button" => [
                                [
                                    'type' => 'link',
                                    'name' => $jatbi->lang("Xem"),
                                    'permission' => ['customers'],
                                    'action' => ['href' => '/customers/customers-views/'.$data['active'], 'data-pjax' => '']
                                ],
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Sửa"),
                                    'permission' => ['customers.edit'],
                                    'action' => ['data-url' => '/customers/customers-edit/'.$data['active'], 'data-action' => 'modal']
                                ],
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Xóa"),
                                    'permission' => ['customers.deleted'],
                                    'action' => ['data-url' => '/customers/customers-deleted?box='.$data['active'], 'data-action' => 'modal']
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
        })->setPermissions(['customers']);

        $app->router("/vendors", ['GET','POST'], function($vars) use ($app, $jatbi,$setting) {
            $vars['title'] = $jatbi->lang("Nhà cung cấp");
            if ($app->method() === 'GET') {
                $vars['type'] = '?type=1';
                echo $app->render($setting['template'].'/customers/customers.html', $vars);
            }
            elseif ($app->method() === 'POST') {
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
                $type = isset($_POST['type']) ? $_POST['type'] : '';
                $where = [
                    "AND" => [
                        "OR" => [
                            "customers.name[~]" => $searchValue,
                            "customers.code[~]" => $searchValue,
                            "customers.phone[~]" => $searchValue,
                            "customers.email[~]" => $searchValue,
                        ],
                        "customers.status[<>]" => $status,
                        "customers.deleted" => 0,
                        "customers.form" => 1,
                    ],
                    "LIMIT" => [$start, $length],
                    "ORDER" => [$orderName => strtoupper($orderDir)]
                ];
                if (!empty($type)) {
                    $where["AND"]["customers.type"] = $type;
                }
                $count = $app->count("customers",[
                    "AND" => $where['AND'],
                ]);
                $app->select("customers", [
                        "[>]customers_type" => ["type" => "id"],
                        "[>]accounts" => ["account" => "id"],
                    ], 
                    [
                    'customers.id',
                    'customers.name',
                    'customers.active',
                    'customers.code',
                    'customers.date',
                    'customers.phone',
                    'customers.status',
                    'customers_type.name (type)',
                    'accounts.name (user)',
                    ], $where, function ($data) use (&$datas,$jatbi,$app) {
                    $datas[] = [
                        "checkbox" => $app->component("box",["data"=>$data['active']]),
                        "type" => $data['type'],
                        "code" => $data['code'],
                        "date" => $jatbi->datetime($data['date']),
                        "name" => $data['name'],
                        "user" => $data['user'],
                        "phone" => $data['phone'],
                        "status" => $app->component("status",["url"=>"/customers/customers-status/".$data['active'],"data"=>$data['status'],"permission"=>['customers.edit']]),
                        "action" => $app->component("action",[
                            "button" => [
                                [
                                    'type' => 'link',
                                    'name' => $jatbi->lang("Xem"),
                                    'permission' => ['customers'],
                                    'action' => ['href' => '/customers/customers-views/'.$data['active'], 'data-pjax' => '']
                                ],
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Sửa"),
                                    'permission' => ['customers.edit'],
                                    'action' => ['data-url' => '/customers/customers-edit/'.$data['active'], 'data-action' => 'modal']
                                ],
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Xóa"),
                                    'permission' => ['customers.deleted'],
                                    'action' => ['data-url' => '/customers/customers-deleted?box='.$data['active'], 'data-action' => 'modal']
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
        })->setPermissions(['customers']);

        $app->router("/search", ['GET','POST'], function($vars) use ($app, $jatbi,$setting) {
            if ($app->method() === 'POST') {
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $searchValue = isset($_POST['keyword']) ? $_POST['keyword'] : '';
                $where = [
                    "AND" => [
                        "OR" => [
                            "customers.name[~]" => $searchValue,
                            "customers.phone[~]" => $searchValue,
                            "customers.code[~]" => $searchValue,
                        ],
                        "customers.status" => 'A',
                        "customers.deleted" => 0,
                        "customers.form" => 0,
                    ]
                ];
                $app->select("customers", "*", $where, function ($data) use (&$datas,$jatbi,$app) {
                    $datas[] = [
                        "value" => $data['id'],
                        "text" => $data['name'],
                    ];
                });
                echo json_encode($datas);
            }
        })->setPermissions(['customers']);

        $app->router("/province", ['GET','POST'], function($vars) use ($app, $jatbi,$setting) {
            if ($app->method() === 'POST') {
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $searchValue = isset($_POST['keyword']) ? $_POST['keyword'] : '';
                $where = [
                    "AND" => [
                        "OR" => [
                            "province.name[~]" => $searchValue,
                        ],
                        "province.status" => 'A',
                        "province.deleted" => 0,
                    ]
                ];
                $app->select("province", "*", $where, function ($data) use (&$datas,$jatbi,$app) {
                        $datas[] = [
                            "value" => $data['id'],
                            "text" => $data['name'],
                        ];
                });
                echo json_encode($datas);
            }
        })->setPermissions(['customers']);

        $app->router("/district", ['GET','POST'], function($vars) use ($app, $jatbi,$setting) {
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
                if($parent){
                    $where['AND']['district.province'] = $parent;
                }
                $app->select("district", "*", $where, function ($data) use (&$datas,$jatbi,$app) {
                        $datas[] = [
                            "value" => $data['id'],
                            "text" => $data['name'],
                        ];
                });
                echo json_encode($datas);
            }
        })->setPermissions(['customers']);

        $app->router("/ward", ['GET','POST'], function($vars) use ($app, $jatbi,$setting) {
            if ($app->method() === 'POST') {
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $searchValue = isset($_POST['keyword']) ? $_POST['keyword'] : '';
                $parent = isset($_POST['parent']) ? $_POST['parent'] : '';
                $where = [
                    "AND" => [
                        "OR" => [
                            "ward.name[~]" => $searchValue,
                        ],
                        "ward.status" => 'A',
                        "ward.deleted" => 0,
                    ]
                ];
                if($parent){
                    $where['AND']['ward.district'] = $parent;
                }
                $app->select("ward", "*", $where, function ($data) use (&$datas,$jatbi,$app) {
                        $datas[] = [
                            "value" => $data['id'],
                            "text" => $data['name'],
                        ];
                });
                echo json_encode($datas);
            }
        })->setPermissions(['customers']);

        $app->router("/customers-add", ['GET','POST'], function($vars) use ($app, $jatbi,$setting,$account,$common) {
            $vars['title'] = $jatbi->lang("Thêm Khách hàng");
            if ($app->method() === 'GET') {
                $vars['types'] = $app->select("customers_type",["id (value)","name (text)"],["deleted"=>0,"status"=>"A"]);
                $vars['fields'] = $app->select("customers_fields","*",["deleted"=>0,"status"=>"A","ORDER"=>["position"=>"ASC"]]);
                $vars['province'] = $app->select("province",["id (value)","name (text)"],["deleted"=>0,"status"=>"A"]);
                $vars['data'] = [
                    "status" => 'A',
                    "type" => '',
                    "name" => '',
                    "code" => '',
                ];
                $vars['common'] = $common['data-field'];
                echo $app->render($setting['template'].'/customers/customers-post.html', $vars, $jatbi->ajax());
            }
            elseif ($app->method() === 'POST') {
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $error = [];
                if ($app->xss($_POST['name'] ?? '') == '' || $app->xss($_POST['status'] ?? '') == '') {
                    $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")];
                }
                if (empty($error)) {
                    $required_fields = $app->select("customers_fields", "*", [
                        "required" => 1,
                        "deleted" => 0
                    ]);
                    foreach ($required_fields as $field) {
                        $field_id = $field['id'];
                        if (!isset($_POST['option'][$field_id]) || (
                            is_array($_POST['option'][$field_id])
                                ? empty(array_filter($_POST['option'][$field_id], fn($v) => $v !== ''))
                                : $_POST['option'][$field_id] === ''
                        )) {
                            $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")];
                            break;
                        }
                    }
                }
                $form = 0;
                $type = $app->xss($_GET['type'] ?? '');
                if($type==1){
                    $form = 1;
                }
                if (empty($error)) {
                    $insert = [
                        "form"      => $form,
                        "type"      => $app->xss($_POST['type'] ?? ''),
                        "code"      => $app->xss($_POST['code'] ?? ''),
                        "name"      => $app->xss($_POST['name'] ?? ''),
                        "phone"     => $app->xss($_POST['phone'] ?? ''),
                        "email"     => $app->xss($_POST['email'] ?? ''),
                        "address"   => $app->xss($_POST['address'] ?? ''),
                        "province"  => $app->xss($_POST['province'] ?? 0),
                        "district"  => $app->xss($_POST['district'] ?? 0),
                        "ward"      => $app->xss($_POST['ward'] ?? 0),
                        "active"    => $jatbi->active(),
                        "date"      => date('Y-m-d H:i:s'),
                        "modify"    => date('Y-m-d H:i:s'),
                        "status"    => $app->xss($_POST['status'] ?? 'A'),
                        "account"   => $account['id'],
                    ];
                    $app->doHook('POST-customers-add-before-insert',$insert);
                    $app->insert("customers", $insert);
                    $getID = $app->id();
                    $insert['id'] = $getID;
                    $app->doHook('POST-customers-add-after-insert',$insert);
                    if (isset($_POST['option']) && is_array($_POST['option'])) {
                        foreach ($_POST['option'] as $field_id => $value) {
                            $field = $app->get("customers_fields", "*", [
                                "id"      => $field_id,
                                "deleted" => 0
                            ]);
                            if (!$field) continue;
                            if(is_array($value)){
                                foreach ($value as $item) {
                                    if ($item === '') continue;
                                    $app->insert("customers_values", [
                                        "customers" => $getID,
                                        "fields"    => $field_id,
                                        "options"   => $field_id,
                                        "value"     => $app->xss($item),
                                        "active"    => $jatbi->active(),
                                        "deleted"   => 0
                                    ]);
                                }
                            }
                            else {
                                if ($value === '') continue;
                                $app->insert("customers_values", [
                                    "customers" => $getID,
                                    "fields"    => $field_id,
                                    "options"   => 0,
                                    "value"     => $app->xss($value),
                                    "active"    => $jatbi->active(),
                                    "deleted"   => 0
                                ]);
                            }
                        }
                    }
                    $app->insert("customers_logs", [
                        "customers" => $getID,
                        "content"   => 'Khởi tạo',
                        "action"    => 'ADD',
                        "date"      => date('Y-m-d H:i:s'),
                        "account"   => $account['id'],
                    ]);
                    echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
                    $jatbi->logs('customers', 'customers-add', $insert);
                } else {
                    echo json_encode($error);
                }

            }
        })->setPermissions(['customers.add']);

        $app->router("/customers-edit/{id}", ['GET','POST'], function($vars) use ($app, $jatbi,$setting,$account,$common) {
            $vars['title'] = $jatbi->lang("Sửa khách hàng");
            if($app->method()==='GET'){
                $vars['data'] = $app->get("customers","*",["active"=>$vars['id'],"deleted"=>0]);
                if($vars['data']>1){
                    $vars['types'] = $app->select("customers_type",["id (value)","name (text)"],["deleted"=>0,"status"=>"A"]);
                    $vars['fields'] = $app->select("customers_fields","*",["deleted"=>0,"status"=>"A","ORDER"=>["position"=>"ASC"]]);
                    $values = $app->select("customers_values", "*", [
                        "customers" => $vars['data']['id'],
                        "deleted" => 0
                    ]);
                    $field_values = [];
                    foreach ($values as $v) {
                        if ($v['options']!=0) {
                            $field_values[$v['fields']][] = $v['value'];
                        } else {
                            $field_values[$v['fields']] = $v['value'];
                        }
                    }
                    $vars['field_values'] = $field_values;
                    $vars['common'] = $common['data-field'];
                    echo $app->render($setting['template'].'/customers/customers-post.html', $vars, $jatbi->ajax());
                }
                else {
                    echo $app->render($setting['template'].'/pages/error.html', $vars, $jatbi->ajax());
                }
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $data = $app->get("customers","*",["active"=>$vars['id'],"deleted"=>0]);
                if($data>1){
                    $error = [];
                    if ($app->xss($_POST['name']) == '' || $app->xss($_POST['type']) == '' || $app->xss($_POST['status']) == '') {
                        $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")];
                    }
                    if (empty($error)) {
                        $required_fields = $app->select("customers_fields", "*", [
                            "required" => 1,
                            "deleted" => 0
                        ]);
                        foreach ($required_fields as $field) {
                            $field_id = $field['id'];
                            if (!isset($_POST['option'][$field_id]) || (
                                is_array($_POST['option'][$field_id])
                                    ? empty(array_filter($_POST['option'][$field_id], fn($v) => $v !== ''))
                                    : $_POST['option'][$field_id] === ''
                            )) {
                                $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")];
                                break;
                            }
                        }
                    }
                    if(empty($error)){
                        $insert = [
                            "code"          => $app->xss($_POST['code'] ?? ''),
                            "name"          => $app->xss($_POST['name'] ?? ''),
                            "type"          => $app->xss($_POST['type'] ?? ''),
                            "phone"         => $app->xss($_POST['phone'] ?? ''),
                            "email"         => $app->xss($_POST['email'] ?? ''),
                            "address"       => $app->xss($_POST['address'] ?? ''),
                            "province"      => $app->xss($_POST['province'] ?? 0),
                            "district"      => $app->xss($_POST['district'] ?? 0),
                            "ward"          => $app->xss($_POST['ward'] ?? 0),
                            "status"        => $app->xss($_POST['status'] ?? 'A'),
                            "modify"        => date('Y-m-d H:i:s'),
                        ];
                        $app->doHook('POST-customers-edit-before-insert',$insert);
                        $app->update("customers",$insert,["id"=>$data['id']]);
                        $insert['id'] = $data['id'];
                        $app->doHook('POST-customers-edit-after-insert',$insert);
                        $app->update("customers_values",["deleted"=>1],["customers" => $data['id']]);
                        if (isset($_POST['option']) && is_array($_POST['option'])) {
                            foreach ($_POST['option'] as $field_id => $value) {
                                $field = $app->get("customers_fields", "*", [
                                    "id"      => $field_id,
                                    "deleted" => 0
                                ]);
                                if (!$field) continue;
                                if(is_array($value)){
                                    foreach ($value as $item) {
                                        if ($item === '') continue;
                                        $app->insert("customers_values", [
                                            "customers" => $data['id'],
                                            "fields"    => $field_id,
                                            "options"   => $field_id,
                                            "value"     => $app->xss($item),
                                            "active"    => $jatbi->active(),
                                            "deleted"   => 0
                                        ]);
                                    }
                                }
                                else {
                                    if ($value === '') continue;
                                    $app->insert("customers_values", [
                                        "customers" => $data['id'],
                                        "fields"    => $field_id,
                                        "options"   => 0,
                                        "value"     => $app->xss($value),
                                        "active"    => $jatbi->active(),
                                        "deleted"   => 0
                                    ]);
                                }
                            }
                        }
                        $app->insert("customers_logs", [
                            "customers" => $data['id'],
                            "content"   => 'Chỉnh sửa thông tin',
                            "action"    => 'EDIT',
                            "date"      => date('Y-m-d H:i:s'),
                            "account"   => $account['id'],
                        ]);
                        echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                        $jatbi->logs('customers','customers-edit',$insert);
                    }
                    else {
                        echo json_encode($error);
                    }
                }
                else {
                    echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
                }
            }
        })->setPermissions(['customers.edit']);

        $app->router("/customers-views/{id}", 'GET', function($vars) use ($app, $jatbi,$setting,$common) {
            $vars['data'] = $app->get("customers","*",["active"=>$vars['id'],"deleted"=>0]);
            if($vars['data']>1){
                $vars['title'] = $jatbi->lang("Chi tiết khách hàng");
                $vars['name'] = $vars['data']['name'];
                $vars['template'] = 'logs';
                $vars['types'] = $app->select("customers_type","*",["deleted"=>0,"status"=>"A"]);
                $values = $app->select("customers_values", "*", [
                    "customers" => $vars['data']['id'],
                    "deleted" => 0
                ]);
                $field_values = [];
                $field_cache = [];
                $option_cache = [];
                $connect_cache = [];
                foreach ($values as $v) {
                    $field_id = $v['fields'];
                    $value_id = $v['value'];
                    $is_option = $v['options'] != 0;
                    if (!isset($field_cache[$field_id])) {
                        $field_cache[$field_id] = $app->get("customers_fields", "*", ["id" => $field_id]);
                    }
                    $checkfield = $field_cache[$field_id];
                    $field_name = $checkfield['name'];
                    $source = $checkfield['source'];

                    $field_values[$field_id]['name'] = $field_name;
                    if ($source === 'choices') {
                        if (!isset($option_cache[$value_id])) {
                            $option_cache[$value_id] = $app->get("customers_fields_options", "name", ["id" => $value_id]);
                        }
                        $result_value = $option_cache[$value_id];
                    } elseif ($source === 'connect') {
                        $connect_table = $checkfield['database'];
                        if ($is_option) {
                            if (!isset($connect_cache[$connect_table][$value_id])) {
                                $connect_cache[$connect_table][$value_id] = $app->get($connect_table, "name", ["id" => $value_id]);
                            }
                            $result_value = $connect_cache[$connect_table][$value_id];
                        } else {
                            $result_value = $app->get($connect_table, "name", ["id" => $value_id]);
                        }
                    } else {
                        if ($is_option) {
                            if (!isset($option_cache[$value_id])) {
                                $option_cache[$value_id] = $app->get("customers_fields_options", "name", ["id" => $value_id]);
                            }
                            $result_value = $option_cache[$value_id];
                        } else {
                            $result_value = $value_id;
                        }
                    }
                    if ($is_option) {
                        $field_values[$field_id]['value'][] = $result_value;
                    } else {
                        $field_values[$field_id]['value'] = $result_value;
                    }
                }
                $vars['field_values'] = $field_values;
                $vars['common'] = $common['data-field'];
                $vars['logs'] = $app->select("customers_logs", "*", ["customers" => $vars['data']['id'],"ORDER"=>["id"=>"DESC"]]);
                echo $app->render($setting['template'].'/customers/customers-views.html', $vars);
            }
            else {
                echo $app->render($setting['template'].'/pages/error.html', $vars, $jatbi->ajax());
            }
        })->setPermissions(['customers']);

        $app->router("/customers-views/{id}/{type}", 'GET', function($vars) use ($app, $jatbi,$setting,$common) {
            $vars['data'] = $app->get("customers","*",["active"=>$vars['id'],"deleted"=>0]);
            if($vars['data']>1){
                $vars['title'] = $jatbi->lang("Chi tiết khách hàng");
                $vars['name'] = $vars['data']['name'];
                $vars['template'] = $vars['type'];
                $vars['types'] = $app->select("customers_type","*",["deleted"=>0,"status"=>"A"]);
                $values = $app->select("customers_values", "*", [
                    "customers" => $vars['data']['id'],
                    "deleted" => 0
                ]);
                $field_values = [];
                $field_cache = [];
                $option_cache = [];
                $connect_cache = [];
                foreach ($values as $v) {
                    $field_id = $v['fields'];
                    $value_id = $v['value'];
                    $is_option = $v['options'] != 0;
                    if (!isset($field_cache[$field_id])) {
                        $field_cache[$field_id] = $app->get("customers_fields", "*", ["id" => $field_id]);
                    }
                    $checkfield = $field_cache[$field_id];
                    $field_name = $checkfield['name'];
                    $source = $checkfield['source'];

                    $field_values[$field_id]['name'] = $field_name;
                    if ($source === 'choices') {
                        if (!isset($option_cache[$value_id])) {
                            $option_cache[$value_id] = $app->get("customers_fields_options", "name", ["id" => $value_id]);
                        }
                        $result_value = $option_cache[$value_id];
                    } elseif ($source === 'connect') {
                        $connect_table = $checkfield['database'];
                        if ($is_option) {
                            if (!isset($connect_cache[$connect_table][$value_id])) {
                                $connect_cache[$connect_table][$value_id] = $app->get($connect_table, "name", ["id" => $value_id]);
                            }
                            $result_value = $connect_cache[$connect_table][$value_id];
                        } else {
                            $result_value = $app->get($connect_table, "name", ["id" => $value_id]);
                        }
                    } else {
                        if ($is_option) {
                            if (!isset($option_cache[$value_id])) {
                                $option_cache[$value_id] = $app->get("customers_fields_options", "name", ["id" => $value_id]);
                            }
                            $result_value = $option_cache[$value_id];
                        } else {
                            $result_value = $value_id;
                        }
                    }
                    if ($is_option) {
                        $field_values[$field_id]['value'][] = $result_value;
                    } else {
                        $field_values[$field_id]['value'] = $result_value;
                    }
                }
                $vars['field_values'] = $field_values;
                $vars['common'] = $common['data-field'];
                $vars['logs'] = $app->select("customers_logs", "*", ["customers" => $vars['data']['id'],"ORDER"=>["id"=>"DESC"]]);
                echo $app->render($setting['template'].'/customers/customers-views.html', $vars);
            }
            else {
                echo $app->render($setting['template'].'/pages/error.html', $vars, $jatbi->ajax());
            }
        })->setPermissions(['customers.edit']);

        $app->router("/customers-status/{id}", 'POST', function($vars) use ($app, $jatbi,$setting) {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $data = $app->get("customers","*",["active"=>$vars['id'],"deleted"=>0]);
            if($data>1){
                if($data>1){
                    if($data['status']==='A'){
                        $status = "D";
                    } 
                    elseif($data['status']==='D'){
                        $status = "A";
                    }
                    $app->update("customers",["status"=>$status],["id"=>$data['id']]);
                    $jatbi->logs('customers','customers-status',$data);
                    echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Cập nhật thất bại"),]);
                }
            }
            else {
                echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
            }
        })->setPermissions(['customers.edit']);

        $app->router("/customers-deleted", ['GET','POST'], function($vars) use ($app, $jatbi,$setting) {
            $vars['title'] = $jatbi->lang("Xóa khách hàng");
            if($app->method()==='GET'){
                echo $app->render($setting['template'].'/common/deleted.html', $vars, $jatbi->ajax());
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $boxid = explode(',', $app->xss($_GET['box']));
                $datas = $app->select("customers","*",["active"=>$boxid,"deleted"=>0]);
                if(count($datas)>0){
                    foreach($datas as $data){
                        $app->update("customers",["deleted"=> 1],["id"=>$data['id']]);
                        $name[] = $data['name'];
                    }
                    $jatbi->logs('customers','customers-deleted',$datas);
                    $jatbi->trash('/customers/customers-restore',"Xóa khách hàng: ".implode(', ',$name),["database"=>'customers',"data"=>$boxid]);
                    echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra")]);
                }
            }
        })->setPermissions(['customers.deleted']);

        $app->router("/customers-restore/{id}", ['GET','POST'], function($vars) use ($app, $jatbi,$setting) {
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
                        $app->update("customers",["deleted"=>0],["active"=>$active]);
                    }
                    $app->delete("trashs",["id"=>$trash['id']]);
                    $jatbi->logs('customers','customers-restore',$datas);
                    echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra")]);
                }
            }
        })->setPermissions(['blockip.deleted']);

        $app->router("/config", ['GET','POST'], function($vars) use ($app, $jatbi,$setting) {
            $vars['title'] = $jatbi->lang("Cấu hình");
            $vars['name'] = $jatbi->lang("Loại khách hàng");
            $vars['template'] = 'type';
            if ($app->method() === 'GET') {
                echo $app->render($setting['template'].'/customers/config.html', $vars);
            }
            elseif ($app->method() === 'POST') {
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
                $count = $app->count("customers_type",[
                    "AND" => $where['AND'],
                ]);
                $app->select("customers_type","*", $where, function ($data) use (&$datas,$jatbi,$app) {
                    $datas[] = [
                        "checkbox" => $app->component("box",["data"=>$data['active']]),
                        "code" => $data['code'],
                        "name" => $data['name'],
                        "status" => $app->component("status",["url"=>"/customers/config-type-status/".$data['active'],"data"=>$data['status'],"permission"=>['customers.config.edit']]),
                        "action" => $app->component("action",[
                            "button" => [
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Sửa"),
                                    'permission' => ['customers.config.edit'],
                                    'action' => ['data-url' => '/customers/config-type-edit/'.$data['active'], 'data-action' => 'modal']
                                ],
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Xóa"),
                                    'permission' => ['customers.config.deleted'],
                                    'action' => ['data-url' => '/customers/config-type-deleted?box='.$data['active'], 'data-action' => 'modal']
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
        })->setPermissions(['customers.config']);

        $app->router("/config-type-add", ['GET','POST'], function($vars) use ($app, $jatbi,$setting) {
            $vars['title'] = $jatbi->lang("Thêm loại khách hàng");
            if($app->method()==='GET'){
                $vars['data'] = [
                    "status" => 'A',
                ];
                echo $app->render($setting['template'].'/customers/config-type-post.html', $vars, $jatbi->ajax());
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
                        "code"          => $app->xss($_POST['code']),
                        "name"          => $app->xss($_POST['name']),
                        "status"        => $app->xss($_POST['status']),
                        "active"        => $jatbi->active(),
                    ];
                    $app->insert("customers_type",$insert);
                    echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                    $jatbi->logs('customers','type-add',$insert);
                }
                else {
                    echo json_encode($error);
                }
            }
        })->setPermissions(['customers.config.add']);

        $app->router("/config-type-edit/{id}", ['GET','POST'], function($vars) use ($app, $jatbi,$setting) {
            $vars['title'] = $jatbi->lang("Sửa Loại khách hàng");
            if($app->method()==='GET'){
                $vars['data'] = $app->get("customers_type","*",["active"=>$vars['id'],"deleted"=>0]);
                if($vars['data']>1){
                    echo $app->render($setting['template'].'/customers/config-type-post.html', $vars, $jatbi->ajax());
                }
                else {
                    echo $app->render($setting['template'].'/pages/error.html', $vars, $jatbi->ajax());
                }
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $data = $app->get("customers_type","*",["active"=>$vars['id'],"deleted"=>0]);
                if($data>1){
                    if($app->xss($_POST['name'])=='' || $app->xss($_POST['status'])==''){
                        $error = ["status"=>"error","content"=>$jatbi->lang("Vui lòng không để trống")];
                    }
                    if(empty($error)){
                        $insert = [
                            "code"          => $app->xss($_POST['code']),
                            "name"          => $app->xss($_POST['name']),
                            "status"        => $app->xss($_POST['status']),
                        ];
                        $app->update("customers_type",$insert,["id"=>$data['id']]);
                        echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                        $jatbi->logs('customers','type-edit',$insert);
                    }
                    else {
                        echo json_encode($error);
                    }
                }
                else {
                    echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
                }
            }
        })->setPermissions(['customers.config.edit']);

        $app->router("/config-type-status/{id}", 'POST', function($vars) use ($app, $jatbi,$setting) {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $data = $app->get("customers_type","*",["active"=>$vars['id'],"deleted"=>0]);
            if($data>1){
                if($data>1){
                    if($data['status']==='A'){
                        $status = "D";
                    } 
                    elseif($data['status']==='D'){
                        $status = "A";
                    }
                    $app->update("customers_type",["status"=>$status],["id"=>$data['id']]);
                    $jatbi->logs('customers','type-status',$data);
                    echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Cập nhật thất bại"),]);
                }
            }
            else {
                echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
            }
        })->setPermissions(['customers.config.edit']);

        $app->router("/config-type-deleted", ['GET','POST'], function($vars) use ($app, $jatbi,$setting) {
            $vars['title'] = $jatbi->lang("Xóa Loại khách hàng");
            if($app->method()==='GET'){
                echo $app->render($setting['template'].'/common/deleted.html', $vars, $jatbi->ajax());
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $boxid = explode(',', $app->xss($_GET['box']));
                $datas = $app->select("customers_type","*",["active"=>$boxid,"deleted"=>0]);
                if(count($datas)>0){
                    foreach($datas as $data){
                        $app->update("customers_type",["deleted"=> 1],["id"=>$data['id']]);
                        $name[] = $data['name'];
                    }
                    $jatbi->logs('customers','type-deleted',$datas);
                    $jatbi->trash('/customers/config-type-restore',"Xóa loại khách hàng: ".implode(', ',$name),["database"=>'customers_type',"data"=>$boxid]);
                    echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra")]);
                }
            }
        })->setPermissions(['customers.config.deleted']);

        $app->router("/config-type-restore/{id}", ['GET','POST'], function($vars) use ($app, $jatbi,$setting) {
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
                        $app->update("customers_type",["deleted"=>0],["active"=>$active]);
                    }
                    $app->delete("trashs",["id"=>$trash['id']]);
                    $jatbi->logs('customers','type-restore',$datas);
                    echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra")]);
                }
            }
        })->setPermissions(['customers.config.deleted']);

        $app->router("/config/data-field", ['GET','POST'], function($vars) use ($app, $jatbi,$setting,$common) {
            $vars['title'] = $jatbi->lang("Cấu hình");
            $vars['name'] = $jatbi->lang("Trường thông tin");
            $vars['template'] = 'data-field';
            if ($app->method() === 'GET') {
                echo $app->render($setting['template'].'/customers/config.html', $vars);
            }
            elseif ($app->method() === 'POST') {
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
                $count = $app->count("customers_fields",[
                    "AND" => $where['AND'],
                ]);
                $app->select("customers_fields","*", $where, function ($data) use (&$datas,$jatbi,$app,$common) {
                    $datas[] = [
                        "checkbox" => $app->component("box",["data"=>$data['active']]),
                        "code" => $data['code'],
                        "position" => $data['position'],
                        "name" => $data['name'],
                        "required" => $data['required']=='0'?'<span class="text-secondary">'.$jatbi->lang("không kích hoạt").'</span>':'<span class="text-danger">'.$jatbi->lang("kích hoạt").'</span>',
                        "show_table" => $data['show_table']=='0'?'<span class="text-secondary">'.$jatbi->lang("không kích hoạt").'</span>':'<span class="text-primary">'.$jatbi->lang("kích hoạt").'</span>',
                        "type" => $common['data-field'][$data['type']]['label'],
                        "status" => $app->component("status",["url"=>"/customers/config-data-field-status/".$data['active'],"data"=>$data['status'],"permission"=>['customers.config.edit']]),
                        "action" => $app->component("action",[
                            "button" => [
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Sửa"),
                                    'permission' => ['customers.config.edit'],
                                    'action' => ['data-url' => '/customers/config-data-field-edit/'.$data['active'], 'data-action' => 'modal']
                                ],
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Xóa"),
                                    'permission' => ['customers.config.deleted'],
                                    'action' => ['data-url' => '/customers/config-data-field-deleted?box='.$data['active'], 'data-action' => 'modal']
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
        })->setPermissions(['customers.config']);

        $app->router("/config/field-load/{id}", 'POST', function($vars) use ($app, $jatbi,$setting,$common) {
            $vars['data'] = $app->get("customers_fields","*",["active"=>$vars['id'],"deleted"=>0]) ?? ["source" => ''];
            $vars['type'] = $app->xss($_POST['type'] ?? $vars['data']['type']);
            $vars['common'] = $common['data-field'][$vars['type']];
            echo $app->render($setting['template'].'/customers/config-field-load.html', $vars, $jatbi->ajax());
        })->setPermissions(['customers.config']);

        $app->router("/config/field-load-option/{id}", 'POST', function($vars) use ($app, $jatbi,$setting,$common) {
            $vars['data'] = $app->get("customers_fields","*",["active"=>$vars['id'],"deleted"=>0]) ?? ["source" => '', "id"=> ''];
            $vars['source'] = $app->xss($_POST['source'] ?? $vars['data']['source']);
            if($vars['data']){
                $vars['options'] = $app->select("customers_fields_options","*",["deleted"=>0,"fields"=>$vars['data']['id']]);
            }
            $vars['database'] = $common['database'];
            echo $app->render($setting['template'].'/customers/config-field-load-option.html', $vars, $jatbi->ajax());
        })->setPermissions(['customers.config']);

        $app->router("/config-data-field-add", ['GET','POST'], function($vars) use ($app, $jatbi,$setting,$common) {
            $vars['title'] = $jatbi->lang("Thêm Trường thông tin");
            if($app->method()==='GET'){
                $vars['data'] = [
                    "status" => 'A',
                    "type" => '',
                    "required" => '0',
                    "col" => '6',
                    "show_table" => '0',
                ];
                foreach ($common['data-field'] as $key => $field) {
                    $vars['fields'][$key] = [
                        'text'    => $field['label'] ?? '',
                        'type'   => $field['type'] ?? '',
                        'value'   => $key ?? '',
                        'options' => $field['options'] ?? [],
                    ];
                }
                echo $app->render($setting['template'].'/customers/config-data-field-post.html', $vars, $jatbi->ajax());
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
                        "code"          => $app->xss($_POST['code']),
                        "position"      => $app->xss($_POST['position']),
                        "name"          => $app->xss($_POST['name']),
                        "type"          => $app->xss($_POST['type']),
                        "required"      => $app->xss($_POST['required']),
                        "notes"         => $app->xss($_POST['notes']),
                        "status"        => $app->xss($_POST['status']),
                        "show_table"    => $app->xss($_POST['show_table']),
                        "col"           => $app->xss($_POST['col']),
                        "source"        => $app->xss($_POST['source'] ?? ''),
                        "database"        => $app->xss($_POST['database'] ?? ''),
                        "default_value" => $app->xss($_POST['default_value'] ?? ''),
                        "active"        => $jatbi->active(),
                    ];
                    $app->insert("customers_fields",$insert);
                    $GetID = $app->id();
                    $insert_option_log = [];
                    if (isset($_POST['options']) && is_array($_POST['options']) && count($_POST['options']) > 0) {
                        foreach ($_POST['options'] as $key => $option) {
                            $insert_option = [
                                "type" => $insert['type'],
                                "fields" => $GetID,
                                "name" => $option,
                                "active" => $jatbi->active(),
                                "status" => 'A',
                            ];
                            $app->insert("customers_fields_options", $insert_option);
                            $insert_option_log[] = $insert_option;
                        }
                    }
                    echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                    $jatbi->logs('customers','data-field-add',[$insert,$insert_option_log]);
                }
                else {
                    echo json_encode($error);
                }
            }
        })->setPermissions(['customers.config.add']);

        $app->router("/config-data-field-edit/{id}", ['GET','POST'], function($vars) use ($app, $jatbi,$setting, $common) {
            $vars['title'] = $jatbi->lang("Sửa Trường thông tin");
            if($app->method()==='GET'){
                $vars['data'] = $app->get("customers_fields","*",["active"=>$vars['id'],"deleted"=>0]);
                if($vars['data']>1){
                    foreach ($common['data-field'] as $key => $field) {
                        $vars['fields'][$key] = [
                            'text'    => $field['label'] ?? '',
                            'type'   => $field['type'] ?? '',
                            'value'   => $key ?? '',
                            'options' => $field['options'] ?? [],
                        ];
                    }
                    echo $app->render($setting['template'].'/customers/config-data-field-post.html', $vars, $jatbi->ajax());
                }
                else {
                    echo $app->render($setting['template'].'/pages/error.html', $vars, $jatbi->ajax());
                }
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $data = $app->get("customers_fields","*",["active"=>$vars['id'],"deleted"=>0]);
                if($data>1){
                    if($app->xss($_POST['name'])=='' || $app->xss($_POST['status'])==''){
                        $error = ["status"=>"error","content"=>$jatbi->lang("Vui lòng không để trống")];
                    }
                    if(empty($error)){
                        $insert = [
                            "code"          => $app->xss($_POST['code']),
                            "position"      => $app->xss($_POST['position']),
                            "name"          => $app->xss($_POST['name']),
                            "type"          => $app->xss($_POST['type']),
                            "required"      => $app->xss($_POST['required']),
                            "notes"         => $app->xss($_POST['notes']),
                            "status"        => $app->xss($_POST['status']),
                            "show_table"    => $app->xss($_POST['show_table']),
                            "col"           => $app->xss($_POST['col']),
                            "source"        => $app->xss($_POST['source'] ?? ''),
                            "database"        => $app->xss($_POST['database'] ?? ''),
                            "default_value" => $app->xss($_POST['default_value'] ?? ''),
                        ];
                        $app->update("customers_fields",$insert,["id"=>$data['id']]);
                        if (isset($_POST['options']) && is_array($_POST['options'])) {
                            // Lấy toàn bộ các option đang có trong DB cho field này
                            $existing_options = $app->select("customers_fields_options", "*", [
                                "deleted" => 0,
                                "fields" => $data['id']
                            ]);

                            // Tạo mảng để tra nhanh
                            $existing_options_map = [];
                            foreach ($existing_options as $opt) {
                                $existing_options_map[$opt['active']] = $opt;
                            }

                            // Danh sách các active hiện có trong POST
                            $posted_keys = array_keys($_POST['options']);

                            // Xử lý cập nhật hoặc thêm mới
                            foreach ($_POST['options'] as $key => $option_name) {
                                if (isset($existing_options_map[$key])) {
                                    // Nếu tồn tại thì cập nhật (không cập nhật active)
                                    $app->update("customers_fields_options", [
                                        "name" => $option_name,
                                        "type" => $insert['type'],
                                    ], [
                                        "id" => $existing_options_map[$key]['id']
                                    ]);
                                } else {
                                    // Nếu chưa tồn tại thì thêm mới
                                    $app->insert("customers_fields_options", [
                                        "type" => $insert['type'],
                                        "fields" => $data['id'],
                                        "name" => $option_name,
                                        "active" => $jatbi->active(),
                                        "status" => 'A'
                                    ]);
                                }
                            }

                            // Xử lý xóa những cái đã bị bỏ đi
                            foreach ($existing_options_map as $active => $opt) {
                                if (!in_array($active, $posted_keys)) {
                                    $app->update("customers_fields_options", [
                                        "deleted" => 1
                                    ], [
                                        "id" => $opt['id']
                                    ]);
                                }
                            }
                        }
                        echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                        $jatbi->logs('customers','data-field-edit',$insert);
                    }
                    else {
                        echo json_encode($error);
                    }
                }
                else {
                    echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
                }
            }
        })->setPermissions(['customers.config.edit']);

        $app->router("/config-data-field-status/{id}", 'POST', function($vars) use ($app, $jatbi,$setting) {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $data = $app->get("customers_fields","*",["active"=>$vars['id'],"deleted"=>0]);
            if($data>1){
                if($data>1){
                    if($data['status']==='A'){
                        $status = "D";
                    } 
                    elseif($data['status']==='D'){
                        $status = "A";
                    }
                    $app->update("customers_fields",["status"=>$status],["id"=>$data['id']]);
                    $jatbi->logs('customers','data-field-status',$data);
                    echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Cập nhật thất bại"),]);
                }
            }
            else {
                echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
            }
        })->setPermissions(['customers.config.edit']);

        $app->router("/config-data-field-deleted", ['GET','POST'], function($vars) use ($app, $jatbi,$setting) {
            $vars['title'] = $jatbi->lang("Xóa Trường thông tin");
            if($app->method()==='GET'){
                echo $app->render($setting['template'].'/common/deleted.html', $vars, $jatbi->ajax());
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $boxid = explode(',', $app->xss($_GET['box']));
                $datas = $app->select("customers_fields","*",["active"=>$boxid,"deleted"=>0]);
                if(count($datas)>0){
                    foreach($datas as $data){
                        $app->update("customers_fields",["deleted"=> 1],["id"=>$data['id']]);
                        $name[] = $data['name'];
                    }
                    $jatbi->logs('customers','data-field-deleted',$datas);
                    $jatbi->trash('/customers/config-data-field-restore',"Xóa loại khách hàng: ".implode(', ',$name),["database"=>'customers_fields',"data"=>$boxid]);
                    echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra")]);
                }
            }
        })->setPermissions(['customers.config.deleted']);

        $app->router("/config-data-field-restore/{id}", ['GET','POST'], function($vars) use ($app, $jatbi,$setting) {
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
                        $app->update("customers_type",["deleted"=>0],["active"=>$active]);
                    }
                    $app->delete("trashs",["id"=>$trash['id']]);
                    $jatbi->logs('customers','type-restore',$datas);
                    echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra")]);
                }
            }
        })->setPermissions(['customers.config.deleted']);
    })->middleware('login');
 ?>