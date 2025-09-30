<?php
use ECLO\App;
$template = __DIR__.'/../templates';
$jatbi = $app->getValueData('jatbi');
$common = $jatbi->getPluginCommon('io.eclo.proposal');
$setting = $app->getValueData('setting');
if (!defined('ECLO')) die("Hacking attempt");
// Handle session and stores
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
    if($account['stores'] == '') {
        $accStore[0] = "0"; // chỉ thêm 1 lần
    }

    foreach ($stores as $itemStore) {
        $accStore[$itemStore['value']] = $itemStore['value'];
    }
}
$app->group($setting['manager'] . "/accountants", function ($app) use ($jatbi, $setting, $accStore, $stores, $common) {
    $app->router("/expenditure", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $accStore, $stores) {
        $vars['title'] = $jatbi->lang("Thu chi");

        if ($app->method() === 'GET') {
            $expenditure_types = $setting['expenditure_type'];
            $expenditure_types_formatted = array_map(function($item) use ($jatbi) {
                return [
                    'value' => $item['id'],
                    'text' => $jatbi->lang($item['name'])
                ];
            }, $expenditure_types);
            array_unshift($expenditure_types_formatted, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars["expenditure_types"] = $expenditure_types_formatted;
            $accountants = $app->select("accountants_code", ["id ", "name", "code"], ["deleted" => 0, "status" => 'A']);
            $accountants_formatted = array_map(function($item) {
                return [
                    'value' => $item['code'],
                    'text' => $item['code'] . ' - ' . $item['name']
                ];
            }, $accountants);
            array_unshift($accountants_formatted, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars["accountants"] = $accountants_formatted;
            // Lấy danh sách stores từ cookie
            if(count($stores) > 1){
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
            // echo $app->render($setting['template'] . '/accountants/expenditure.html', $vars);
            echo $app->render($template.'/config/expenditure.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            // Get DataTables parameters
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? $app->xss($_POST['search']['value']) : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'ASC';

            // Get filter parameters
            $debt = isset($_POST['debt']) ? $app->xss($_POST['debt']) : '';
            $has = isset($_POST['has']) ? $app->xss($_POST['has']) : '';
            $user = isset($_POST['user']) ? $app->xss($_POST['user']) : '';
            $expenditure_type = isset($_POST['expenditure_type']) ? $app->xss($_POST['expenditure_type']) : '';
            $date_string = isset($_POST['date']) ? $app->xss($_POST['date']) : '';

            // Process date range
            if ($date_string) {
                $date = explode('-', $date_string);
                $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date[0]))));
                $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date[1]))));
            } else {
                $date_from = date('Y-m-01 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }

            
            $store = isset($_POST['stores']) ? $app->xss($_POST['stores']) : $accStore;

            // Build where clause
            $where = [
                "AND" => [
                    "OR" => [
                        "expenditure.ballot[~]" => $searchValue ?: '%',
                        "expenditure.content[~]" => $searchValue ?: '%',
                    ],
                    "expenditure.type[<>]" => [1, 2],
                    "expenditure.deleted" => 0,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => ["expenditure.id" => strtoupper($orderDir)],
            ];

            if ($expenditure_type != '') {
                $where['AND']['expenditure.type'] = $expenditure_type;
            }
            if ($debt != '') {
                $where['AND']['expenditure.debt'] = $debt;
            }
            if ($has != '') {
                $where['AND']['expenditure.has'] = $has;
            }
            if ($user != '') {
                $where['AND']['expenditure.user'] = $user;
            }
            if ($store != [0]) {
                $where['AND']['expenditure.stores'] = $store;
            }
            $where['AND']['expenditure.date[<>]'] = [$date_from, $date_to];

            // Count total records
            $count = $app->count("expenditure", ["AND" => $where['AND']]);
            // Calculate page totals
            $total_first_thu_page = $app->select("expenditure", ["price", "type"], [
                "AND" => $where['AND'],
                "LIMIT" => $start,
                "ORDER" => ["expenditure.id" => strtoupper($orderDir)],
            ]);
            $total_page = [];
            if (!empty($total_first_thu_page)) {
                foreach ($total_first_thu_page as $value) {
                    if (isset($value['type'])) {
                        $total_page[$value['type']][] = (float)($value['price'] ?? 0);
                    }
                }
            }
            
            // Calculate initial and final totals
            $total_first_thu = (float)$app->sum("expenditure", "price", [
                "AND" => [
                    "OR" => [
                        "expenditure.ballot[~]" => $searchValue ?: '%',
                        "expenditure.content[~]" => $searchValue ?: '%',
                    ],
                    "expenditure.type" => 1,
                    "expenditure.date[<]" => $date_from,
                    "expenditure.stores" => $store,
                    "expenditure.deleted" => 0,
                    "expenditure.debt[<>]" => $debt ?: '',
                    "expenditure.has[<>]" => $has ?: '',
                    "expenditure.user[<>]" => $user ?: '',
                ],
                "ORDER" => ["expenditure.id" => strtoupper($orderDir)],
            ]);

            $total_first_chi = (float)$app->sum("expenditure", "price", [
                "AND" => [
                    "OR" => [
                        "expenditure.ballot[~]" => $searchValue ?: '%',
                        "expenditure.content[~]" => $searchValue ?: '%',
                    ],
                    "expenditure.type" => 2,
                    "expenditure.date[<]" => $date_from,
                    "expenditure.stores" => $store,
                    "expenditure.deleted" => 0,
                    "expenditure.debt[<>]" => $debt ?: '',
                    "expenditure.has[<>]" => $has ?: '',
                    "expenditure.user[<>]" => $user ?: '',
                ],
                "ORDER" => ["expenditure.id" => strtoupper($orderDir)],
            ]);
            $total_last_thu = (float)$app->sum("expenditure", "price", [
                "AND" => [
                    "OR" => [
                        "expenditure.ballot[~]" => $searchValue ?: '%',
                        "expenditure.content[~]" => $searchValue ?: '%',
                    ],
                    "expenditure.type" => 1,
                    "expenditure.date[<=]" => $date_to,
                    "expenditure.stores" => $store,
                    "expenditure.deleted" => 0,
                    "expenditure.debt[<>]" => $debt ?: '',
                    "expenditure.has[<>]" => $has ?: '',
                    "expenditure.user[<>]" => $user ?: '',
                ],
                "ORDER" => ["expenditure.id" => strtoupper($orderDir)],
            ]);

            $total_last_chi = (float)$app->sum("expenditure", "price", [
                "AND" => [
                    "OR" => [
                        "expenditure.ballot[~]" => $searchValue ?: '%',
                        "expenditure.content[~]" => $searchValue ?: '%',
                    ],
                    "expenditure.type" => 2,
                    "expenditure.date[<=]" => $date_to,
                    "expenditure.stores" => $store,
                    "expenditure.deleted" => 0,
                    "expenditure.debt[<>]" => $debt ?: '',
                    "expenditure.has[<>]" => $has ?: '',
                    "expenditure.user[<>]" => $user ?: '',
                ],
                "ORDER" => ["expenditure.id" => strtoupper($orderDir)],
            ]);

            // Calculate initial total
            $total_first = (float)$total_first_thu + (float)$total_first_chi + (float)(array_sum($total_page[1] ?? [0])) + (float)(array_sum($total_page[2] ?? [0]));

            // Fetch expenditure data
            $datas = [];
            $expenditure = 0;
            $total_chi = 0;
            $total_thu = 0;
            $key = 0;
            $test[0] = "expenditure_report";

            $app->select("expenditure", "*", $where, function ($data) use (&$datas, &$key, &$expenditure, &$total_chi, &$total_thu, &$total_first, $app, $jatbi, $setting, $test) {
                if (!isset($data['type'], $data['price'], $data['has'], $data['debt'])) {
                    error_log("Invalid expenditure record: " . print_r($data, true));
                    return;
                }

                $thu = 0;
                $chi = 0;

                if ($data['type'] == 1) {
                    $thu = (float)$data['price'];
                    $chi = 0;
                    $total_thu += $thu;
                } elseif ($data['type'] == 2) {
                    $thu = 0;
                    $chi = (float)$data['price'];
                    $total_chi += $chi;
                }

                $expenditure = ($expenditure == 0 && $test[0] == "expenditure_report" ? (float)$total_first : (float)$expenditure);
                if ($key > 0 && isset($datas[$key-1]['price'])) {
                    $expenditure += (float)$datas[$key-1]['price'];
                }

                $doi_tuong_html = '';
                // if ($data['orders'] != 0) {
                //     $Getorders = $app->get("orders", "*", ["id" => $data['orders'], "deleted" => 0]);
                //     if ($Getorders) {
                //         $doi_tuong_html .= '<p class="mb-0"><a href="#!" class="modal-url" data-url="/invoices/orders-views/' . $Getorders['id'] . '">#' . $setting['ballot_code']['orders'] . '-' . $Getorders['code'] . $Getorders['id'] . '</a></p>';
                //     }
                // }
                if ($data['invoices'] != 0) {
                    $GetInvoices = $app->get("invoices", ["id", "code"], ["id" => $data['invoices'], "deleted" => 0]);
                    if ($GetInvoices) {
                        $doi_tuong_html .= '<p class="mb-0"><a class="pjax-load" href="/invoices/invoices-views/' . $GetInvoices['id'] . '">#' . $setting['ballot_code']['invoices'] . '-' . $GetInvoices['code'] . $GetInvoices['id'] . '</a></p>';
                    }
                }
                if ($data['customers'] != 0) {
                    $GetCustomers = $app->get("customers", ["id", "name"], ["id" => $data['customers'], "deleted" => 0]);
                    if ($GetCustomers) {
                        $doi_tuong_html .= '<p class="mb-0">' . $GetCustomers['name'] . '</p>';
                    }
                }
                if ($data['personnels'] != 0) {
                    $GetPersonnel = $app->get("personnels", ["id", "name"], ["id" => $data['personnels'], "deleted" => 0]);
                    if ($GetPersonnel) {
                        $doi_tuong_html .= '<p class="mb-0">' . $GetPersonnel['name'] . '</p>';
                    }
                }
                if ($data['purchase'] != 0) {
                    $Getpurchase = $app->get("purchase", ["id", "code"], ["id" => $data['purchase'], "deleted" => 0]);
                    if ($Getpurchase) {
                        $doi_tuong_html .= '<p class="mb-0"><a href="#!" class="modal-url" data-url="/purchases/purchase-views/' . $Getpurchase['id'] . '">#' . $setting['ballot_code']['purchase'] . '-' . $Getpurchase['code'] . '</a></p>';
                    }
                }
                if ($data['vendor'] != 0) {
                    $Getvendor = $app->get("vendors", ["id", "name"], ["id" => $data['vendor'], "deleted" => 0]);
                    if ($Getvendor) {
                        $doi_tuong_html .= '<p class="mb-0">' . $Getvendor['name'] . '</p>';
                    }
                }

                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "ngay" => '<a href="#!" data-action="modal" class="modal-url" data-url="/accountants/expenditure-views/' . $data['id'] . '">' . date($setting['site_date'] ?? "d/m/Y", strtotime($data['date'])) . '</a>',
                    "thu" => $data['type'] == 1 ? ($data['ballot'] ?? '') : '',
                    "chi" => $data['type'] == 2 ? ($data['ballot'] ?? '') : '',
                    "dien-giai" => $data['content'] ?? '',
                    "doi-tuong" => $doi_tuong_html,
                    "tai-khoan-no" => $app->get("accountants_code", "code", ["code" => $data['debt']]) ?? '',
                    "tai-khoan-co" => $app->get("accountants_code", "code", ["code" => $data['has']]) ?? '',
                    "thuu" => number_format($thu, 1),
                    "chii" => number_format($chi, 1),
                    "ton" => number_format($expenditure + $thu + $chi, 1),
                    "action" => $app->component("action", [
                            "button" => [
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("in"),
                                    'action' => ['data-url' => '/accountants/expenditure-views/' . $data['id'], 'data-action' => 'modal']
                                ],
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Sửa"),
                                    'permission' => ['expenditure.edit'],
                                    'action' => ['data-url' => '/accountants/expenditure-edit/' . $data['id'], 'data-action' => 'modal']
                                ],
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Chuyển dữ liệu kế toán"),
                                    'permission' => ['expenditure.move'],
                                    'action' => ['data-url' => '/accountants/expenditure-move/' . $data['id'], 'data-action' => 'modal']
                                ],
                            ]
                        ]),
                    "id" => $data['id'],
                    "type" => $data['type'],
                    "has" => $data['has'],
                    "debt" => $data['debt'],
                    "price" => (float)$data['price'],
                ];
                $key++;
            });
            // $ac = number_format(array_sum($total_page[1]), 1);
            // var_dump($ac);
            // var_dump($total_first_chi);
            // var_dump($total_first_thu);
            // var_dump(number_format((float)$total_first_thu + (float)$total_first_chi + (float)(array_sum($total_page[1] ?? [0])) + (float)(array_sum($total_page[2] ?? [0])), 1));
            $totals = [
                "dau-ky-thu" => number_format((float)$total_first_thu + (float)(array_sum($total_page[1] ?? [0])), 1),
                "dau-ky-chi" => number_format((float)$total_first_chi + (float)(array_sum($total_page[2] ?? [0])), 1),
                "dau-ky-ton" => number_format((float)$total_first_thu + (float)$total_first_chi + (float)(array_sum($total_page[1] ?? [0])) + (float)(array_sum($total_page[2] ?? [0])), 1),
                "tong-cong-thu" => number_format((float)$total_thu, 1),
                "tong-cong-chi" => number_format((float)$total_chi, 1),
                "tong-cong-ton" => number_format((float)$total_first + (float)$total_thu + (float)$total_chi, 1),
                "cuoi-ky-thu" => number_format((float)$total_last_thu, 1),
                "cuoi-ky-chi" => number_format((float)$total_last_chi, 1),
                "cuoi-ky-ton" => number_format((float)$total_last_thu + (float)$total_last_chi, 1),
            ];

            // Return JSON data
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas ?? [],
                "footerData" => $totals,
            ]);
        }
    })->setPermissions(['expenditure']);

    $app->router("/expenditure-add", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $accStore, $stores) {
        $vars['title'] = $jatbi->lang("Thêm thu chi");

        if ($app->method() === 'GET') {
            $expenditure_types = $setting['expenditure_type'];
            $expenditure_types_formatted = array_map(function($item) use ($jatbi) {
                return [
                    'value' => $item['id'],
                    'text' => $jatbi->lang($item['name'])
                ];
            }, $expenditure_types);
            array_unshift($expenditure_types_formatted, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars["expenditure_types"] = $expenditure_types_formatted;

            $accountants = $app->select("accountants_code", ["id ", "name", "code"], ["deleted" => 0, "status" => 'A']);
            $accountants_formatted = array_map(function($item) {
                return [
                    'value' => $item['code'],
                    'text' => $item['code'] . ' - ' . $item['name']
                ];
            }, $accountants);
            array_unshift($accountants_formatted, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars["accountants"] = $accountants_formatted;

            $personnels = $app->select("personnels", ["id (value)","name (text)"],["deleted"=> 0,"status"=>'A',"stores"=>$accStore]);
            array_unshift($personnels, [
                'value' => '',
                'text' => $jatbi->lang('Nhân viên')
            ]);
            $vars["personnels"] = $personnels;

            $vendors = $app->select("vendors", ["id (value)","name (text)"],["deleted"=> 0,"status"=>'A',]);
            array_unshift($vendors, [
                'value' => '',
                'text' => $jatbi->lang('Nhà cung cấp')
            ]);
            $vars["vendors"] = $vendors;

            array_unshift($stores, [
                'value' => '',
                'text' => $jatbi->lang('Cửa hàng')
            ]);
            $vars['stores'] = $stores;

            echo $app->render($setting['template'] . '/accountants/expenditure-post.html', $vars , $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $error = [];
            if(count($stores)>1){
				$input_stores = $app->xss($_POST['stores']);
			}
			else {
				$input_stores = $app->get("stores","id",["id"=>$accStore,"status"=>'A',"deleted"=>0,"ORDER"=>["id"=>"ASC"]]);
			}
            if ($app->xss($_POST['expenditure_types']) == '' 
            || $app->xss($_POST['has']) == '' 
            || $app->xss($_POST['debt']) == '' 
            || $app->xss($_POST['price']) == '' 
            || $app->xss($_POST['content']) == '' 
            || $app->xss($_POST['date']) == ''
            || $input_stores == "") {
                $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")];
            }
            if (empty($error)) {
                if($app->xss($_POST['expenditure_types'])==1){
					$price = $app->xss(str_replace([','],'',$_POST['price']));
				}
				if($app->xss($_POST['expenditure_types'])==2){
					$price = '-'.$app->xss(str_replace([','],'',$_POST['price']));
				}
                $insert = [
                    "type" 			=> $app->xss($_POST['expenditure_types']),
					"debt" 			=> $app->xss($_POST['debt']),
					"has" 			=> $app->xss($_POST['has']),
					"price" 		=> $price,
					"content" 		=> $app->xss($_POST['content']),
					"date" 			=> $app->xss($_POST['date']),
					"ballot" 		=> $app->xss($_POST['ballot']),
					// "projects" 		=> $app->xss($_POST['projects']),
					"customers" 	=> $app->xss($_POST['customers']),
					"personnels" 	=> $app->xss($_POST['personnels']),
					// "purchase" 		=> $app->xss($_POST['purchase']),
					"vendor" 		=> $app->xss($_POST['vendors']),
					"notes" 		=> $app->xss($_POST['notes']),
					"user"			=> $app->getSession("accounts")['id'] ?? 0,
					"date_poster"	=> date("Y-m-d H:i:s"),
					"stores"		=> $input_stores,
                ];

                $app->insert("expenditure", $insert);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
                $jatbi->logs('expenditure','expenditure-add',$insert);
            }else {
                echo json_encode($error);
            }
            
        }
    })->setPermissions(['expenditure.add']);

    $app->router("/expenditure-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $accStore, $stores, $common) {
        $vars['title'] = $jatbi->lang("Chỉnh sửa thu chi");

        if ($app->method() === 'GET') {
            $vars['data'] = $app->get("expenditure", [
                "[>]customers" => ["customers" => "id"], // JOIN ON expenditure.customer_id = customers.id
            ], [
                "expenditure.type",
                "expenditure.date",
                "expenditure.has",
                "expenditure.debt",
                "expenditure.price",
                "expenditure.ballot",
                "expenditure.content",
                "expenditure.personnels",
                "expenditure.vendor",
                "expenditure.purchase",
                "expenditure.notes",
                "expenditure.stores",
                "expenditure.customers",
                "customers.phone",
                "customers.name"
            ], [
                "expenditure.id" => $vars['id'],
                "expenditure.deleted" => 0
            ]);
            if ($vars['data'] > 1) {
                $expenditure_types = $setting['expenditure_type'];
                $expenditure_types_formatted = array_map(function($item) use ($jatbi) {
                    return [
                        'value' => $item['id'],
                        'text' => $jatbi->lang($item['name'])
                    ];
                }, $expenditure_types);
                array_unshift($expenditure_types_formatted, [
                    'value' => '',
                    'text' => $jatbi->lang('Tất cả')
                ]);
                $vars["expenditure_types"] = $expenditure_types_formatted;

                $accountants = $app->select("accountants_code", ["id ", "name", "code"], ["deleted" => 0, "status" => 'A']);
                $accountants_formatted = array_map(function($item) {
                    return [
                        'value' => $item['code'],
                        'text' => $item['code'] . ' - ' . $item['name']
                    ];
                }, $accountants);
                array_unshift($accountants_formatted, [
                    'value' => '',
                    'text' => $jatbi->lang('Tất cả')
                ]);
                $vars["accountants"] = $accountants_formatted;

                $personnels = $app->select("personnels", ["id (value)","name (text)"],["deleted"=> 0,"status"=>'A',"stores"=>$accStore]);
                array_unshift($personnels, [
                    'value' => '',
                    'text' => $jatbi->lang('Nhân viên')
                ]);
                $vars["personnels"] = $personnels;

                $vendors = $app->select("vendors", ["id (value)","name (text)"],["deleted"=> 0,"status"=>'A',]);
                array_unshift($vendors, [
                    'value' => '',
                    'text' => $jatbi->lang('Nhà cung cấp')
                ]);
                $vars["vendors"] = $vendors;

                array_unshift($stores, [
                    'value' => '',
                    'text' => $jatbi->lang('Cửa hàng')
                ]);
                $vars['stores'] = $stores;

                echo $app->render($setting['template'] . '/accountants/expenditure-post.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $error = [];
            if(count($stores)>1){
				$input_stores = $app->xss($_POST['stores']);
			}
			else {
				$input_stores = $app->get("stores","id",["id"=>$accStore,"status"=>'A',"deleted"=>0,"ORDER"=>["id"=>"ASC"]]);
			}
            if ($app->xss($_POST['expenditure_types']) == '' 
            || $app->xss($_POST['has']) == '' 
            || $app->xss($_POST['debt']) == '' 
            || $app->xss($_POST['price']) == '' 
            || $app->xss($_POST['content']) == '' 
            || $app->xss($_POST['date']) == ''
            || $input_stores == "") {
                $error = ["status" => "error", "content" => $jatbi->lang("Vui lòng không để trống")];
            }
            if (empty($error)) {
                if($app->xss($_POST['expenditure_types'])==1){
					$price = $app->xss(str_replace([','],'',$_POST['price']));
				}
				if($app->xss($_POST['expenditure_types'])==2){
					$price = '-'.$app->xss(str_replace([','],'',$_POST['price']));
				}
                $update = [
                    "type" 			=> $app->xss($_POST['expenditure_types']),
					"debt" 			=> $app->xss($_POST['debt']),
					"has" 			=> $app->xss($_POST['has']),
					"price" 		=> $price,
					"content" 		=> $app->xss($_POST['content']),
					"date" 			=> $app->xss($_POST['date']),
					"ballot" 		=> $app->xss($_POST['ballot']),
					// "projects" 		=> $app->xss($_POST['projects']),
					"customers" 	=> $app->xss($_POST['customers']),
					"personnels" 	=> $app->xss($_POST['personnels']),
					// "purchase" 		=> $app->xss($_POST['purchase']),
					"vendor" 		=> $app->xss($_POST['vendors']),
					"notes" 		=> $app->xss($_POST['notes']),
					"user"			=> $app->getSession("accounts")['id'] ?? 0,
					"date_poster"	=> date("Y-m-d H:i:s"),
					"stores"		=> $input_stores,
                ];

                $app->update("expenditure", $update, ["id" => $vars['id']]);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
                $jatbi->logs('expenditure','expenditure-edit',$update);
            }else {
                echo json_encode($error);
            }
        }
    })->setPermissions(['expenditure.edit']);

    $app->router("/expenditure-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        $vars['title'] = $jatbi->lang("Xóa chi tiêu");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("expenditure", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("expenditure", ["deleted" => 1], ["id" => $data['id']]);
                }
                $jatbi->logs('expenditure', 'expenditure-deleted', $datas);
                $jatbi->trash('/expenditure/expenditure-restore', "Xóa chi tiêu: ", ["database" => 'expenditure', "data" => $boxid]);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['expenditure.deleted']);
    
    $app->router("/expenditure-move/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $accStore, $stores, $common) {
        $vars['title'] = $jatbi->lang("Chuyển dữ liệu kế toán");

        if ($app->method() === 'GET') {
            $vars['data'] = $app->get("expenditure", [
                "[>]customers" => ["customers" => "id"], // JOIN ON expenditure.customer_id = customers.id
            ], [
                "expenditure.type",
                "expenditure.date",
                "expenditure.has",
                "expenditure.debt",
                "expenditure.price",
                "expenditure.ballot",
                "expenditure.content",
                "expenditure.personnels",
                "expenditure.vendor",
                "expenditure.purchase",
                "expenditure.notes",
                "expenditure.stores",
                "expenditure.customers",
                "customers.phone",
                "customers.name"
            ], [
                "expenditure.id" => $vars['id'],
                "expenditure.deleted" => 0
            ]);
            if ($vars['data'] > 1) {
                $expenditure_types = $setting['expenditure_type'];
                $expenditure_types_formatted = array_map(function($item) use ($jatbi) {
                    return [
                        'value' => $item['id'],
                        'text' => $jatbi->lang($item['name'])
                    ];
                }, $expenditure_types);
                array_unshift($expenditure_types_formatted, [
                    'value' => '',
                    'text' => $jatbi->lang('Tất cả')
                ]);
                $vars["expenditure_types"] = $expenditure_types_formatted;

                $accountants = $app->select("accountants_code", ["id ", "name", "code"], ["deleted" => 0, "status" => 'A']);
                $accountants_formatted = array_map(function($item) {
                    return [
                        'value' => $item['code'],
                        'text' => $item['code'] . ' - ' . $item['name']
                    ];
                }, $accountants);
                array_unshift($accountants_formatted, [
                    'value' => '',
                    'text' => $jatbi->lang('Tất cả')
                ]);
                $vars["accountants"] = $accountants_formatted;

                $personnels = $app->select("personnels", ["id (value)","name (text)"],["deleted"=> 0,"status"=>'A',"stores"=>$accStore]);
                array_unshift($personnels, [
                    'value' => '',
                    'text' => $jatbi->lang('Nhân viên')
                ]);
                $vars["personnels"] = $personnels;

                $vendors = $app->select("vendors", ["id (value)","name (text)"],["deleted"=> 0,"status"=>'A',]);
                array_unshift($vendors, [
                    'value' => '',
                    'text' => $jatbi->lang('Nhà cung cấp')
                ]);
                $vars["vendors"] = $vendors;

                array_unshift($stores, [
                    'value' => '',
                    'text' => $jatbi->lang('Cửa hàng')
                ]);
                $vars['stores'] = $stores;
                $accountantss = $app->select("type_accountant", ["id(value)","name(text)"],["deleted"=> 0,"status"=>'A']);
                array_unshift($accountantss, [
                    'value' => '',
                    'text' => $jatbi->lang('Chọn kế toán')
                ]);
                $vars["accountantss"] = $accountantss;
                echo $app->render($setting['template'] . '/accountants/expenditure-move.html', $vars, $jatbi->ajax());
            } else {
                echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
            }
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $error = [];

            if (count($stores) > 1) {
                $input_stores = $app->xss($_POST['stores']);
            } else {
                $input_stores = $app->get("stores", "id", ["id" => $accStore, "status" => 'A', "deleted" => 0, "ORDER" => ["id" => "ASC"]]);
            }

            $data = $app->get("expenditure", "*", ["id" => $vars['id'], "deleted" => 0]);

            if (
                $app->xss($_POST['expenditure_types']) == '' ||
                $app->xss($_POST['price']) == '' ||
                $app->xss($_POST['content']) == '' ||
                $app->xss($_POST['date']) == '' ||
                $input_stores == '' ||
                $app->xss($_POST['type_accountant']) == ''
            ) {
                $error = ['status' => 'error', 'content' => $jatbi->lang('Vui lòng không để trống')];
            }

            if (empty($error)) {
                $price = $app->xss(str_replace(',', '', $_POST['price']));
                $insert = [
                    "type" => $app->xss($_POST['expenditure_types']),
                    "debt" => $app->xss($_POST['debt']),
                    "has" => $app->xss($_POST['has']),
                    "price" => $price,
                    "content" => $app->xss($_POST['content']),
                    "date" => $app->xss($_POST['date']),
                    "invoices" => $app->xss($data['invoices']),
                    "ballot" => $app->xss($_POST['ballot']),
                    "projects" => $app->xss($data['projects']),
                    "customers" => $app->xss($data['customers']),
                    "personnels" => $app->xss($data['personnels']),
                    "vendor" => $app->xss($data['vendor']),
                    "notes" => $app->xss($_POST['notes']),
                    "user" => $app->getSession("accounts")['id'] ?? 0,
                    "purchase" => $data['purchase'],
                    "date_poster" => date("Y-m-d H:i:s"),
                    "stores" => $input_stores,
                    "type_accountant" => $app->xss($_POST['type_accountant']),
                    "expenditure" => $data['id'],
                ];

                $app->insert("accountant", $insert);
                $jatbi->logs('accountant', 'add', $insert);

                if ($insert['invoices']) {
                    $accountant_invoices = $app->get("invoices", "*", ["id" => $insert['invoices'], "deleted" => 0]);
                    if ($accountant_invoices) {
                        $insert_accountant_invoices = [
                            "type" => $accountant_invoices["type"],
                            "type_payments" => $accountant_invoices["type_payments"],
                            "customers" => $accountant_invoices["customers"],
                            "code" => $accountant_invoices["code"],
                            "total" => $accountant_invoices["total"],
                            "vat" => $accountant_invoices["vat"],
                            "discount" => $accountant_invoices["discount"],
                            "discount_card" => $accountant_invoices["discount_card"],
                            "discount_customers" => $accountant_invoices["discount_customers"],
                            "discount_customers_price" => $accountant_invoices["discount_customers_price"],
                            "discount_price" => $accountant_invoices["discount_price"],
                            "minus" => $accountant_invoices["minus"],
                            "surcharge" => $accountant_invoices["surcharge"],
                            "transport" => $accountant_invoices["transport"],
                            "prepay" => $accountant_invoices["prepay"],
                            "payments" => $accountant_invoices["payments"],
                            "status" => $accountant_invoices["status"],
                            "date" => $accountant_invoices["date"],
                            "date_poster" => $accountant_invoices["date_poster"],
                            "user" => $accountant_invoices["user"],
                            "notes" => $accountant_invoices["notes"],
                            "active" => $accountant_invoices["active"],
                            "deleted" => $accountant_invoices["deleted"],
                            "stores" => $accountant_invoices["stores"],
                            "code_group" => $accountant_invoices["code_group"],
                            "branch" => $accountant_invoices["branch"],
                            "invoice" => $insert["invoices"],
                        ];
                        $app->insert("accountant_invoices", $insert_accountant_invoices);

                        $invoices_acc_pro = $app->select("invoices_products", "*", ["invoices" => $insert["invoices"], "deleted" => 0]);
                        foreach ($invoices_acc_pro as $invoice_acc) {
                            $insert_accountant_invoices_products = [
                                "type" => $invoice_acc["type"],
                                "invoices" => $insert["invoices"],
                                "products" => $invoice_acc["products"],
                                "nearsightedness" => $invoice_acc["nearsightedness"],
                                "farsightedness" => $invoice_acc["farsightedness"],
                                "astigmatism" => $invoice_acc["astigmatism"],
                                "colors" => $invoice_acc["colors"],
                                "categorys" => $invoice_acc["categorys"],
                                "cost" => $invoice_acc["cost"],
                                "price" => $invoice_acc["price"],
                                "price_old" => $invoice_acc["price_old"],
                                "vat" => $invoice_acc["vat"],
                                "vat_type" => $invoice_acc["vat_type"],
                                "vat_price" => $invoice_acc["vat_price"],
                                "price_vat" => $invoice_acc["price_vat"],
                                "amount" => $invoice_acc["amount"],
                                "warehouses" => $invoice_acc["warehouses"],
                                "customers" => $invoice_acc["customers"],
                                "vendor" => $invoice_acc["vendor"],
                                "total" => $invoice_acc["total"],
                                "total_cost" => $invoice_acc["total_cost"],
                                "user" => $invoice_acc["user"],
                                "date" => $invoice_acc["date"],
                                "date_poster" => $invoice_acc["date_poster"],
                                "active" => $invoice_acc["active"],
                                "stores" => $invoice_acc["stores"],
                                "discount" => $invoice_acc["discount"],
                                "discount_price" => $invoice_acc["discount_price"],
                                "minus" => $invoice_acc["minus"],
                            ];
                            $app->insert("accountant_invoices_products", $insert_accountant_invoices_products);
                        }
                    }
                }

                echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Chuyển dữ liệu thành công")]);
            } else {
                echo json_encode($error);
            }
        }
    })->setPermissions(['expenditure.move']);

    $app->router("/expenditure-views/{id}", ['GET'], function ($vars) use ($app, $jatbi, $setting, $accStore) {
        $vars['title'] = $jatbi->lang("Xem chi tiết thu chi");

        $vars['data'] = $app->get("expenditure", ["type", "id","ballot", "invoices", "customers", "personnels", "accounts", "drivers_payment", "purchase", "vendor", "user", "debt", "has", "price", "date", "content", "stores"], ["id" => $vars['id'], "deleted" => 0]);

        if ($vars['data']) {
            $vars['store'] = $app->get("stores", "name", ["id" => $vars['data']['stores'], "deleted" => 0]);
            $vars['user'] = $vars['data']['user'] ? $app->get("accounts", ["id", "name"], ["id" => $vars['data']['user'], "deleted" => 0]) : null;
            $vars['vendor'] = $vars['data']['vendor'] ? $app->get("vendors", ["id", "name"], ["id" => $vars['data']['vendor'], "deleted" => 0]) : null;
            $vars['customer'] = $vars['data']['customers'] ? $app->get("customers", ["id", "name"], ["id" => $vars['data']['customers'], "deleted" => 0]) : null;
            $vars['personnel'] = $vars['data']['personnels'] ? $app->get("personnels", ["id", "name"], ["id" => $vars['data']['personnels'], "deleted" => 0]) : null;
            $vars['driver_payment'] = $vars['data']['drivers_payment'] ? $app->get("drivers_payment", "*", ["id" => $vars['data']['drivers_payment'], "deleted" => 0]) : null;

            if ($vars['driver_payment']) {
                $vars['driver'] = $app->get("drivers", "user", ["id" => $vars['driver_payment']['drivers']]);
                $vars['driver_name'] = $vars['driver'] ? $app->get("accounts", "name", ["id" => $vars['driver'], "deleted" => 0]) : '';
            }

            $vars['debt_code'] = $vars['data']['debt'] ? $app->get("accountants_code", "code", ["code" => $vars['data']['debt']]) : '';
            $vars['has_code'] = $vars['data']['has'] ? $app->get("accountants_code", "code", ["code" => $vars['data']['has']]) : '';

            echo $app->render($setting['template'] . '/accountants/expenditure-view.html', $vars, $jatbi->ajax());
        } else {
            echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
        }
    })->setPermissions(['expenditure']);

    $app->router("/expenditure/expenditure_excel", ['GET'], function ($vars) use ($app, $jatbi, $setting, $accStore) {
        $app->header([
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => 'attachment;filename="thuchi ' . date('d-m-Y') . '.xls"',
            'Cache-Control' => 'max-age=0',
            'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
            'Last-Modified' => gmdate('D, d M Y H:i:s') . ' GMT',
            'Cache-Control' => 'cache, must-revalidate',
            'Pragma' => 'public'
        ]);

        $searchValue = $app->xss($_GET['name'] ?? '');
        $expenditure_type = $app->xss($_GET['type'] ?? '');
        $debt = $app->xss($_GET['debt'] ?? '');
        $has = $app->xss($_GET['has'] ?? '');
        $user = $app->xss($_GET['user'] ?? '');
        $date_string = $app->xss($_GET['date'] ?? '');

        if ($date_string) {
            $date = explode('-', $date_string);
            $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date[0]))));
            $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date[1]))));
        } else {
            $date_from = date('Y-m-d 00:00:00');
            $date_to = date('Y-m-d 23:59:59');
        }

        $store = $app->xss($_GET['stores'] ?? $accStore);

        $where = [
            "AND" => [
                "OR" => [
                    "ballot[~]" => $searchValue ?: '%',
                    "content[~]" => $searchValue ?: '%',
                ],
                "type[<>]" => [1, 2],
                "deleted" => 0,
                "stores" => $store,
                "date[<>]" => [$date_from, $date_to],
            ],
        ];

        if ($expenditure_type != '') {
            $where['AND']['type'] = $expenditure_type;
        }
        if ($debt != '') {
            $where['AND']['debt'] = $debt;
        }
        if ($has != '') {
            $where['AND']['has'] = $has;
        }
        if ($user != '') {
            $where['AND']['user'] = $user;
        }

        $count = $app->select("expenditure", ["id", "type", "price", "stores", "invoices", "ballot", "customers", "personnels", "purchase", "vendor", "debt", "has", "date_poster", "date", "notes", "content"], $where);

        $total_first_thu_page = $app->select("expenditure", ["price", "type"], $where);
        $total_page = [];
        foreach ($total_first_thu_page as $value) {
            $total_page[$value['type']][] = (float)$value['price'];
        }

        $total_first_thu = (float)$app->sum("expenditure", "price", [
            "AND" => [
                "OR" => [
                    "ballot[~]" => $searchValue ?: '%',
                    "content[~]" => $searchValue ?: '%',
                ],
                "type" => 1,
                "date[<]" => $date_from,
                "stores" => $store,
                "deleted" => 0,
                "debt[<>]" => $debt ?: '',
                "has[<>]" => $has ?: '',
                "user[<>]" => $user ?: '',
            ],
        ]);

        $total_first_chi = (float)$app->sum("expenditure", "price", [
            "AND" => [
                "OR" => [
                    "ballot[~]" => $searchValue ?: '%',
                    "content[~]" => $searchValue ?: '%',
                ],
                "type" => 2,
                "date[<]" => $date_from,
                "stores" => $store,
                "deleted" => 0,
                "debt[<>]" => $debt ?: '',
                "has[<>]" => $has ?: '',
                "user[<>]" => $user ?: '',
            ],
        ]);

        $total_last_thu = (float)$app->sum("expenditure", "price", [
            "AND" => [
                "OR" => [
                    "ballot[~]" => $searchValue ?: '%',
                    "content[~]" => $searchValue ?: '%',
                ],
                "type" => 1,
                "date[<=]" => $date_to,
                "stores" => $store,
                "deleted" => 0,
                "debt[<>]" => $debt ?: '',
                "has[<>]" => $has ?: '',
                "user[<>]" => $user ?: '',
            ],
        ]);

        $total_last_chi = (float)$app->sum("expenditure", "price", [
            "AND" => [
                "OR" => [
                    "ballot[~]" => $searchValue ?: '%',
                    "content[~]" => $searchValue ?: '%',
                ],
                "type" => 2,
                "date[<=]" => $date_to,
                "stores" => $store,
                "deleted" => 0,
                "debt[<>]" => $debt ?: '',
                "has[<>]" => $has ?: '',
                "user[<>]" => $user ?: '',
            ],
        ]);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('Maarten Balliauw')
            ->setLastModifiedBy('Maarten Balliauw')
            ->setTitle('Office 2007 XLSX Test Document')
            ->setSubject('Office 2007 XLSX Test Document')
            ->setDescription('Test document for Office 2007 XLSX, generated using PHP classes.')
            ->setKeywords('office 2007 openxml php')
            ->setCategory('Test result file');

        $sheet = $spreadsheet->setActiveSheetIndex(0);
        $sheet->setCellValue('A1', 'CHỨNG TỪ')
            ->setCellValue('D1', 'DIỄN GIẢI')
            ->setCellValue('E1', 'ĐỐI TƯỢNG')
            ->setCellValue('F1', 'TÀI KHOẢN')
            ->setCellValue('H1', 'SỐ TIỀN')
            ->setCellValue('A2', 'NGÀY')
            ->setCellValue('B2', 'THU')
            ->setCellValue('C2', 'CHI')
            ->setCellValue('F2', 'TÀI KHOẢN NỢ')
            ->setCellValue('G2', 'TÀI KHOẢN CÓ')
            ->setCellValue('H2', 'THU')
            ->setCellValue('I2', 'CHI')
            ->setCellValue('J2', 'TỒN')
            ->setCellValue('G3', 'ĐẦU KỲ')
            ->setCellValue('H3', number_format($total_first_thu + (array_sum($total_page[1] ?? [0])), 1))
            ->setCellValue('I3', number_format($total_first_chi + (array_sum($total_page[2] ?? [0])), 1))
            ->setCellValue('J3', number_format($total_first_thu + $total_first_chi + (array_sum($total_page[1] ?? [0])) + (array_sum($total_page[2] ?? [0])), 1));

        $sheet->mergeCells('A1:C1')->mergeCells('F1:G1')->mergeCells('H1:J1')->mergeCells('E1:E2')->mergeCells('D1:D2');

        $rowcount = 3;
        $total_first = $total_first_thu + $total_first_chi + (array_sum($total_page[1] ?? [0])) + (array_sum($total_page[2] ?? [0]));
        $expenditure = 0;
        $total_thu = 0;
        $total_chi = 0;
        $key = 0;
        $test[0] = "expenditure_report";

        foreach ($count as $data) {
            $thu = $data['type'] == 1 ? (float)$data['price'] : 0;
            $chi = $data['type'] == 2 ? (float)$data['price'] : 0;
            $total_thu += $thu;
            $total_chi += $chi;

            $expenditure = ($expenditure == 0 && $test[0] == "expenditure_report" ? $total_first : $expenditure);
            if ($key > 0 && isset($count[$key - 1]['price'])) {
                $expenditure += (float)$count[$key - 1]['price'];
            }

            $doi_tuong = '';
            if ($data['orders']) {
                $Getorders = $app->get("orders", "*", ["id" => $data['orders'], "deleted" => 0]);
                if ($Getorders) {
                    $doi_tuong .= $setting['ballot_code']['orders'] . $Getorders['code'] . $Getorders['id'] . ' ';
                }
            }
            if ($data['invoices']) {
                $GetInvoices = $app->get("invoices", "*", ["id" => $data['invoices'], "deleted" => 0]);
                if ($GetInvoices) {
                    $doi_tuong .= $setting['ballot_code']['invoices'] . $GetInvoices['code'] . $GetInvoices['id'] . ' ';
                }
            }
            if ($data['customers']) {
                $GetCustomers = $app->get("customers", "*", ["id" => $data['customers'], "deleted" => 0]);
                if ($GetCustomers) {
                    $doi_tuong .= $GetCustomers['name'] . ' ';
                }
            }
            if ($data['personnels']) {
                $GetPersonnel = $app->get("personnels", "*", ["id" => $data['personnels'], "deleted" => 0]);
                if ($GetPersonnel) {
                    $doi_tuong .= $GetPersonnel['name'] . ' ';
                }
            }
            if ($data['purchase']) {
                $Getpurchase = $app->get("purchase", "*", ["id" => $data['purchase'], "deleted" => 0]);
                if ($Getpurchase) {
                    $doi_tuong .= $setting['ballot_code']['purchase'] . $Getpurchase['code'] . ' ';
                }
            }
            if ($data['vendor']) {
                $Getvendor = $app->get("vendors", "*", ["id" => $data['vendor'], "deleted" => 0]);
                if ($Getvendor) {
                    $doi_tuong .= $Getvendor['name'];
                }
            }

            $rowcount++;
            $sheet->setCellValue('A' . $rowcount, date($setting['site_date'], strtotime($data['date'])))
                ->setCellValue('B' . $rowcount, $data['type'] == 1 ? $data['ballot'] : '')
                ->setCellValue('C' . $rowcount, $data['type'] == 2 ? $data['ballot'] : '')
                ->setCellValue('D' . $rowcount, $data['content'])
                ->setCellValue('E' . $rowcount, $doi_tuong)
                ->setCellValue('F' . $rowcount, $app->get("accountants_code", "code", ["code" => $data['debt']]) ?? '')
                ->setCellValue('G' . $rowcount, $app->get("accountants_code", "code", ["code" => $data['has']]) ?? '')
                ->setCellValue('H' . $rowcount, number_format($thu, 1))
                ->setCellValue('I' . $rowcount, number_format($chi, 1))
                ->setCellValue('J' . $rowcount, number_format($expenditure + $thu + $chi, 1));
            $key++;
        }

        $tong = count($count) + 4;
        $sheet->setCellValue('G' . $tong, 'Cuối kỳ')
            ->setCellValue('H' . $tong, number_format($total_last_thu, 1))
            ->setCellValue('I' . $tong, number_format($total_last_chi, 1))
            ->setCellValue('J' . $tong, number_format($total_last_thu + $total_last_chi, 1));

        $spreadsheet->getActiveSheet()->setTitle('thuchi');
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xls');
        $writer->save('php://output');
        exit;
    })->setPermissions(['expenditure']);

    $app->router("/expenditure_report", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $accStore, $stores) {
        $vars['title'] = $jatbi->lang("Báo cáo thu chi");

        if ($app->method() === 'GET') {
            $expenditure_types = $setting['expenditure_type'];
            $expenditure_types_formatted = array_map(function($item) use ($jatbi) {
                return [
                    'value' => $item['id'],
                    'text' => $jatbi->lang($item['name'])
                ];
            }, $expenditure_types);
            array_unshift($expenditure_types_formatted, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars["expenditure_types"] = $expenditure_types_formatted;
            if(Count($stores) > 1){
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
            echo $app->render($setting['template'] . '/accountants/expenditure_report.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            // Get DataTables parameters
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? $app->xss($_POST['search']['value']) : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'date';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'ASC';

            // Get filter parameters
            $user = isset($_POST['user']) ? $app->xss($_POST['user']) : '';
            $expenditure_type = isset($_POST['expenditure_type']) ? $app->xss($_POST['expenditure_type']) : '';
            $date_string = isset($_POST['date']) ? $app->xss($_POST['date']) : '';

            // Process date range
            if ($date_string) {
                $date = explode('-', $date_string);
                $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date[0]))));
                $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date[1]))));
            } else {
                $date_from = date('Y-m-d 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }

            // Handle session and stores
            
            $store = isset($_POST['stores']) ? $app->xss($_POST['stores']) : $accStore;

            // Build where clause
            $where = [
                "AND" => [
                    "OR" => [
                        "expenditure.ballot[~]" => $searchValue ?: '%',
                        "expenditure.content[~]" => $searchValue ?: '%',
                    ],
                    "OR" => [
                        "expenditure.debt" => [111, 1111],
                        "expenditure.has" => [111, 1111],
                    ],
                    "expenditure.deleted" => 0,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => ["expenditure.date" => strtoupper($orderDir)],
            ];

            if ($expenditure_type != '') {
                $where['AND']['expenditure.type'] = $expenditure_type;
            }
            if ($user != '') {
                $where['AND']['expenditure.user'] = $user;
            }
            if ($store != [0]) {
                $where['AND']['expenditure.stores'] = $store;
            }
            $where['AND']['expenditure.date[<>]'] = [$date_from, $date_to];

            // Count total records
            $count = $app->count("expenditure", ["AND" => $where['AND']]);

            // Calculate page totals
            $total_first_thu_page = $app->select("expenditure", ["price", "type"], [
                "AND" => $where['AND'],
                "LIMIT" => $start,
                "ORDER" => ["expenditure.date" => strtoupper($orderDir)],
            ]);
            $total_page = [];
            if (!empty($total_first_thu_page)) {
                foreach ($total_first_thu_page as $value) {
                    if (isset($value['type'])) {
                        $total_page[$value['type']][] = (float)($value['price'] ?? 0);
                    }
                }
            }

            // Calculate initial transfer adjustments
            $dauky112_111 = $app->select("expenditure", ["price", "debt", "has"], [
                "AND" => [
                    "OR" => [
                        "expenditure.ballot[~]" => $searchValue ?: '%',
                        "expenditure.content[~]" => $searchValue ?: '%',
                    ],
                    "expenditure.type" => 1,
                    "expenditure.date[<]" => $date_from,
                    "expenditure.stores" => $store,
                    "expenditure.deleted" => 0,
                    "OR" => [
                        "expenditure.debt" => [111, 1111],
                        "expenditure.has" => [111, 1111],
                    ],
                    "expenditure.user[<>]" => $user ?: '',
                    "expenditure.type[<>]" => $expenditure_type ?: '',
                ],
                "ORDER" => ["expenditure.date" => "ASC"],
            ]);
            $total_dk112_111 = 0;
            if (!empty($dauky112_111)) {
                foreach ($dauky112_111 as $dk) {
                    if (
                        ($dk['debt'] == 112 && $dk['has'] == 1111) ||
                        ($dk['debt'] == 112 && $dk['has'] == 111) ||
                        ($dk['debt'] == 1121 && $dk['has'] == 1111) ||
                        ($dk['debt'] == 1121 && $dk['has'] == 111)
                    ) {
                        $total_dk112_111 += (float)$dk['price'];
                    }
                }
            }

            // Calculate final transfer adjustments
            $total_last_thu1 = 0;
            $count_data = $app->select("expenditure", ["price", "debt", "has"], [
                "AND" => $where['AND'],
            ]);
            if (!empty($count_data)) {
                foreach ($count_data as $thu1) {
                    if (
                        ($thu1['debt'] == 112 && $thu1['has'] == 1111) ||
                        ($thu1['debt'] == 112 && $thu1['has'] == 111) ||
                        ($thu1['debt'] == 1121 && $thu1['has'] == 1111) ||
                        ($thu1['debt'] == 1121 && $thu1['has'] == 111)
                    ) {
                        $total_last_thu1 += (float)$thu1['price'];
                    }
                }
            }

            // Calculate initial and final totals
            $total_first_thu = (float)$app->sum("expenditure", "price", [
                "AND" => [
                    "OR" => [
                        "expenditure.ballot[~]" => $searchValue ?: '%',
                        "expenditure.content[~]" => $searchValue ?: '%',
                    ],
                    "expenditure.type" => 1,
                    "expenditure.date[<]" => $date_from,
                    "expenditure.stores" => $store,
                    "expenditure.deleted" => 0,
                    "OR" => [
                        "expenditure.debt" => [111, 1111],
                        "expenditure.has" => [111, 1111],
                    ],
                    "expenditure.user[<>]" => $user ?: '',
                    "expenditure.type[<>]" => $expenditure_type ?: '',
                ],
                "ORDER" => ["expenditure.date" => "ASC"],
            ]);

            $total_first_chi = (float)$app->sum("expenditure", "price", [
                "AND" => [
                    "OR" => [
                        "expenditure.ballot[~]" => $searchValue ?: '%',
                        "expenditure.content[~]" => $searchValue ?: '%',
                    ],
                    "expenditure.type" => 2,
                    "expenditure.date[<]" => $date_from,
                    "expenditure.stores" => $store,
                    "expenditure.deleted" => 0,
                    "OR" => [
                        "expenditure.debt" => [111, 1111],
                        "expenditure.has" => [111, 1111],
                    ],
                    "expenditure.user[<>]" => $user ?: '',
                    "expenditure.type[<>]" => $expenditure_type ?: '',
                ],
                "ORDER" => ["expenditure.date" => "ASC"],
            ]);



            $total_last_thu = $app->sum("expenditure", "price", [
                "AND" => [
                    "OR" => [
                        "expenditure.ballot[~]" => $searchValue ?: '%',
                        "expenditure.content[~]" => $searchValue ?: '',
                    ],
                    "expenditure.type" => 1,
                    "expenditure.date[<=]" => $date_to,
                    "expenditure.stores" => $store,
                    "expenditure.deleted" => 0,
                    "OR" => [
                        "expenditure.debt" => [111, 1111],
                        "expenditure.has" => [111, 1111],
                    ],
                    "expenditure.user[<>]" => $user ?: '',
                    "expenditure.type[<>]" => $expenditure_type ?: '',
                ],
                "ORDER" => ["expenditure.date" => "ASC"],
            ]);

            $total_last_chi = (float)$app->sum("expenditure", "price", [
                "AND" => [
                    "OR" => [
                        "expenditure.ballot[~]" => $searchValue ?: '%',
                        "expenditure.content[~]" => $searchValue ?: '',
                    ],
                    "expenditure.type" => 2,
                    "expenditure.date[<=]" => $date_to,
                    "expenditure.stores" => $store,
                    "expenditure.deleted" => 0,
                    "OR" => [
                        "expenditure.debt" => [111, 1111],
                        "expenditure.has" => [111, 1111],
                    ],
                    "expenditure.user[<>]" => $user ?: '',
                    "expenditure.type[<>]" => $expenditure_type ?: '',
                ],
                "ORDER" => ["expenditure.date" => "ASC"],
            ]);

            // Calculate initial total
            $total_first = (float)$total_first_thu - (float)$total_dk112_111 + (float)$total_first_chi - (float)$total_dk112_111 + (float)(array_sum($total_page[1] ?? [0])) + (float)(array_sum($total_page[2] ?? [0]));

            // Fetch expenditure data
            $datas = [];
            $expenditure = 0;
            $total_chi = 0;
            $total_thu = 0;
            $total_chi1 = 0;
            $key = 0;
            $test[0] = "expenditure_report";

            $app->select("expenditure", "*", $where, function ($data) use (&$datas, &$key, &$expenditure, &$total_chi, &$total_thu, &$total_chi1, &$total_first, $app, $jatbi, $setting, $test) {
                if (!isset($data['type'], $data['price'], $data['has'], $data['debt'])) {
                    error_log("Invalid expenditure record: " . print_r($data, true));
                    return;
                }

                $thu = 0;
                $chi = 0;
                $chi1 = 0;

                if ($data['type'] == 1) {
                    if (
                        ($data['has'] == 1111 && $data['debt'] == 112) ||
                        ($data['has'] == 111 && $data['debt'] == 112) ||
                        ($data['has'] == 111 && $data['debt'] == 1121) ||
                        ($data['has'] == 1111 && $data['debt'] == 1121)
                    ) {
                        $thu = 0;
                        $chi1 = -(float)$data['price'];
                        $total_chi1 += $chi1;
                    } else {
                        $thu = (float)$data['price'];
                        $chi = 0;
                        $total_thu += $thu;
                    }
                } elseif ($data['type'] == 2) {
                    $thu = 0;
                    $chi = (float)$data['price'];
                    $total_chi += $chi;
                }

                $expenditure = ($expenditure == 0 && $test[0] == "expenditure_report" ? (float)$total_first : (float)$expenditure);
                if ($key > 0 && isset($datas[$key-1]['price'], $datas[$key-1]['type'], $datas[$key-1]['debt'], $datas[$key-1]['has'])) {
                    if (
                        $datas[$key-1]['type'] == 1 &&
                        (
                            ($datas[$key-1]['debt'] == 112 && $datas[$key-1]['has'] == 1111) ||
                            ($datas[$key-1]['debt'] == 112 && $datas[$key-1]['has'] == 111) ||
                            ($datas[$key-1]['debt'] == 1121 && $datas[$key-1]['has'] == 111) ||
                            ($datas[$key-1]['debt'] == 1121 && $datas[$key-1]['has'] == 1111)
                        )
                    ) {
                        $expenditure += -(float)$datas[$key-1]['price'];
                    } else {
                        $expenditure += (float)$datas[$key-1]['price'];
                    }
                }

                $doi_tuong_html = '';
                // if ($data['orders'] != 0) {
                //     $Getorders = $app->get("orders", ["id", "code"], ["id" => $data['orders'], "deleted" => 0]);
                //     if ($Getorders) {
                //         $doi_tuong_html .= '<p class="mb-0"><a href="#!" class="modal-url" data-url="/invoices/orders-views/' . $Getorders['id'] . '">#' . $setting['ballot_code']['orders'] . '-' . $Getorders['code'] . $Getorders['id'] . '</a></p>';
                //     }
                // }
                if ($data['invoices'] != 0) {
                    $GetInvoices = $app->get("invoices", ["id", "code"], ["id" => $data['invoices'], "deleted" => 0]);
                    if ($GetInvoices) {
                        $doi_tuong_html .= '<p class="mb-0"><a class="pjax-load" href="/invoices/invoices-views/' . $GetInvoices['id'] . '">#' . $setting['ballot_code']['invoices'] . '-' . $GetInvoices['code'] . $GetInvoices['id'] . '</a></p>';
                    }
                }
                if ($data['customers'] != 0) {
                    $GetCustomers = $app->get("customers", ["id", "name"], ["id" => $data['customers'], "deleted" => 0]);
                    if ($GetCustomers) {
                        $doi_tuong_html .= '<p class="mb-0">' . $GetCustomers['name'] . '</p>';
                    }
                }
                if ($data['personnels'] != 0) {
                    $GetPersonnel = $app->get("personnels", ["id", "name"], ["id" => $data['personnels'], "deleted" => 0]);
                    if ($GetPersonnel) {
                        $doi_tuong_html .= '<p class="mb-0">' . $GetPersonnel['name'] . '</p>';
                    }
                }
                if ($data['purchase'] != 0) {
                    $Getpurchase = $app->get("purchase", ["id", "code"], ["id" => $data['purchase'], "deleted" => 0]);
                    if ($Getpurchase) {
                        $doi_tuong_html .= '<p class="mb-0"><a href="#!" class="modal-url" data-url="/purchases/purchase-views/' . $Getpurchase['id'] . '">#' . $setting['ballot_code']['purchase'] . '-' . $Getpurchase['code'] . '</a></p>';
                    }
                }
                if ($data['vendor'] != 0) {
                    $Getvendor = $app->get("vendors", ["id", "name"], ["id" => $data['vendor'], "deleted" => 0]);
                    if ($Getvendor) {
                        $doi_tuong_html .= '<p class="mb-0">' . $Getvendor['name'] . '</p>';
                    }
                }

                $chi_display = (
                    $data['type'] == 1 &&
                    (
                        ($data['has'] == 1111 && $data['debt'] == 1121) ||
                        ($data['has'] == 1111 && $data['debt'] == 112) ||
                        ($data['has'] == 111 && $data['debt'] == 112) ||
                        ($data['has'] == 111 && $data['debt'] == 1121)
                    )
                ) ? $chi1 : $chi;

                $ton_adjustment = (
                    (
                        ($data['has'] == 1111 && $data['debt'] == 1121) ||
                        ($data['has'] == 1111 && $data['debt'] == 112) ||
                        ($data['has'] == 111 && $data['debt'] == 112) ||
                        ($data['has'] == 111 && $data['debt'] == 1121)
                    )
                ) ? $chi1 : 0;

                $datas[] = [
                    "ngay" => '<a href="#!" data-action="modal" class="modal-url" data-url="/accountants/expenditure-views/' . $data['id'] . '">' . date($setting['site_date'] ?? "d/m/Y", strtotime($data['date'])) . '</a>',
                    "thu" => $data['type'] == 1 ? ($data['ballot'] ?? '') : '',
                    "chi" => $data['type'] == 2 ? ($data['ballot'] ?? '') : '',
                    "dien-giai" => $data['content'] ?? '',
                    "doi-tuong" => $doi_tuong_html,
                    "tai-khoan-no" => $app->get("accountants_code", "code", ["code" => $data['debt']]) ?? '',
                    "tai-khoan-co" => $app->get("accountants_code", "code", ["code" => $data['has']]) ?? '',
                    "thuu" => number_format($thu, 1),
                    "chii" => number_format($chi_display, 1),
                    "ton" => number_format($expenditure + $thu + $chi + $ton_adjustment, 1),
                    "id" => $data['id'],
                    "type" => $data['type'],
                    "has" => $data['has'],
                    "debt" => $data['debt'],
                    "price" => (float)$data['price'],
                ];
                $key++;
            });

            $totals = [
                "dau-ky-thu" => number_format((float)$total_first_thu - (float)$total_dk112_111 + (float)(array_sum($total_page[1] ?? [0])), 1),
                "dau-ky-chi" => number_format((float)$total_first_chi - (float)$total_dk112_111 + (float)(array_sum($total_page[2] ?? [0])), 1),
                "dau-ky-ton" => number_format((float)$total_first_thu - (float)$total_dk112_111 + (float)$total_first_chi - (float)$total_dk112_111 + (float)(array_sum($total_page[1] ?? [0])) + (float)(array_sum($total_page[2] ?? [0])), 1),
                "tong-cong-thu" => number_format((float)$total_thu, 1),
                "tong-cong-chi" => number_format((float)$total_chi + (float)$total_chi1, 1),
                "tong-cong-ton" => number_format((float)$total_first + (float)$total_thu + (float)$total_chi + (float)$total_chi1, 1),
                "cuoi-ky-thu" => number_format((float)$total_last_thu - (float)$total_last_thu1 - (float)$total_dk112_111, 1),
                "cuoi-ky-chi" => number_format((float)$total_last_chi - (float)$total_last_thu1 - (float)$total_dk112_111, 1),
                "cuoi-ky-ton" => number_format((float)$total_last_thu - (float)$total_last_thu1 - (float)$total_dk112_111 + (float)$total_last_chi - (float)$total_last_thu1 - (float)$total_dk112_111, 1),
            ];

            // Return JSON data
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas ?? [],
                "footerData" => $totals,
            ]);
        }
    })->setPermissions(['expenditure_report']);
    
    $app->router("/deposit_book", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores, $accStore) {
        $vars['title'] = $jatbi->lang("Sổ tiền gửi");

        if ($app->method() === 'GET') {
            $expenditure_types = $setting['expenditure_type'];
            $expenditure_types_formatted = array_map(function($item) use ($jatbi) {
                return [
                    'value' => $item['id'],
                    'text' => $jatbi->lang($item['name'])
                ];
            }, $expenditure_types);
            array_unshift($expenditure_types_formatted, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars["expenditure_types"] = $expenditure_types_formatted;
            if(count($stores) > 1){
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
            $accountants = $app->select("accountants_code", ["id ", "name", "code"], ["deleted" => 0, "status" => 'A']);
            $accountants_formatted = array_map(function($item) {
                return [
                    'value' => $item['code'],
                    'text' => $item['code'] . ' - ' . $item['name']
                ];
            }, $accountants);
            array_unshift($accountants_formatted, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars["accountants"] = $accountants_formatted;
            
            echo $app->render($setting['template'] . '/accountants/deposit_book.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            // Get DataTables parameters
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? $app->xss($_POST['search']['value']) : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'date';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'ASC';

            // Get filter parameters
            $debt = isset($_POST['debt']) ? $app->xss($_POST['debt']) : '';
            $has = isset($_POST['has']) ? $app->xss($_POST['has']) : '';
            $user = isset($_POST['user']) ? $app->xss($_POST['user']) : '';
            $expenditure_type = isset($_POST['expenditure_type']) ? $app->xss($_POST['expenditure_type']) : '';
            $date_string = isset($_POST['date']) ? $app->xss($_POST['date']) : '';

            // Process date range
            if ($date_string) {
                $date = explode('-', $date_string);
                $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date[0]))));
                $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date[1]))));
            } else {
                $date_from = date('Y-m-d 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }

            // Handle session and stores
            
            $store = isset($_POST['stores']) ? $app->xss($_POST['stores']) : $accStore;

            // Handle debt and has conditions
            if ($debt != '' && $has != '') {
                $search_debt = "";
                $search_has = "";
            } elseif ($debt != '' && $has == '') {
                $search_debt = "";
                $search_has = "<>";
                $has = "";
            } elseif ($debt == '' && $has != '') {
                $search_debt = "<>";
                $debt = "";
                $search_has = "";
            } else {
                $search_debt = "<>";
                $debt = "";
                $search_has = "<>";
                $has = "";
            }

            // Build where clause
            $where = [
                "AND" => [
                    "OR" => [
                        "expenditure.ballot[~]" => $searchValue ?: '%',
                        "expenditure.content[~]" => $searchValue ?: '%',
                    ],
                    "expenditure.deleted" => 0,
                    "OR" => [
                        "expenditure.debt" => [1121, 112],
                        "expenditure.has" => [1121, 112],
                    ],
                    "expenditure.debt[$search_debt]" => $debt,
                    "expenditure.has[$search_has]" => $has,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => ["expenditure.date" => strtoupper($orderDir)],
            ];

            if ($expenditure_type != '') {
                $where['AND']['expenditure.type'] = $expenditure_type;
            } else {
                $where['AND']['expenditure.type'] = [1, 2];
            }
            if ($user != '') {
                $where['AND']['expenditure.user'] = $user;
            }
            if ($store != [0]) {
                $where['AND']['expenditure.stores'] = $store;
            }
            $where['AND']['expenditure.date[<>]'] = [$date_from, $date_to];

            // Count total records
            $count = $app->count("expenditure", [
                "AND" => $where['AND'],
                "ORDER" => ["expenditure.date" => strtoupper("DESC")],
            ]);

            // Calculate page totals
            $total_first_thu_page = $app->select("expenditure", ["price", "type"], [
                "AND" => $where['AND'],
                "LIMIT" => $start,
                "ORDER" => ["expenditure.date" => strtoupper($orderDir)],
            ]);
            
            $total_page = [];
            if (!empty($total_first_thu_page)) {
                foreach ($total_first_thu_page as $value) {
                    if (isset($value['type'])) {
                        $total_page[$value['type']][] = (float)($value['price'] ?? 0);
                    }
                }
            }
            
            // Calculate special totals for debt and has
            $dauky111_1121 = $app->select("expenditure", ["price", "id", "debt", "has"], [
                "AND" => [
                    "OR" => [
                        "expenditure.ballot[~]" => $searchValue ?: '%',
                        "expenditure.content[~]" => $searchValue ?: '%',
                    ],
                    "expenditure.type" => 1,
                    "expenditure.date[<]" => $date_from,
                    "expenditure.stores" => $store,
                    "expenditure.deleted" => 0,
                    "OR" => [
                        "expenditure.debt" => [1121, 112],
                        "expenditure.has" => [1121, 112],
                    ],
                    "expenditure.debt[$search_debt]" => $debt,
                    "expenditure.has[$search_has]" => $has,
                ],
                "ORDER" => ["expenditure.date" => strtoupper($orderDir)],
            ]);
            
            $total_dk111_1121 = 0;
            foreach ($dauky111_1121 as $dk) {
                $debt = (int)$dk['debt'];  // ép về số
                $has  = (int)$dk['has'];   // ép về số

                if (in_array($debt, [111, 1111]) && in_array($has, [1121, 112])) {
                    $total_dk111_1121 += (float)$dk['price'];
                }
            }
            
            $count_data = $app->select("expenditure", "*", [
                "AND" => $where['AND'],
                "ORDER" => ["expenditure.date" => strtoupper("DESC")],
            ]);
            $total_last_thu1 = 0;
            foreach ($count_data as $thu1) {
                $debt = (int) $thu1['debt'];  // ép thành số nguyên
                $has  = (int) $thu1['has'];   // ép thành số nguyên

                if (in_array($debt, [111, 1111]) && in_array($has, [1121, 112])) {
                    $total_last_thu1 += (float)$thu1['price'];
                }
            }
            
            $total_first_thu = (float)$app->sum("expenditure", "price", [
                "AND" => [
                    "OR" => [
                        "expenditure.ballot[~]" => $searchValue ?: '%',
                        "expenditure.content[~]" => $searchValue ?: '%',
                    ],
                    "expenditure.type" => 1,
                    "expenditure.date[<]" => $date_from,
                    "expenditure.stores" => $store,
                    "expenditure.deleted" => 0,
                    "OR" => [
                        "expenditure.debt" => [1121, 112],
                        "expenditure.has" => [1121, 112],
                    ],
                    "expenditure.debt[$search_debt]" => $debt,
                    "expenditure.has[$search_has]" => $has,
                ],
                "ORDER" => ["expenditure.date" => strtoupper($orderDir)],
            ]);

            $total_first_chi = (float)$app->sum("expenditure", "price", [
                "AND" => [
                    "OR" => [
                        "expenditure.ballot[~]" => $searchValue ?: '%',
                        "expenditure.content[~]" => $searchValue ?: '%',
                    ],
                    "expenditure.type" => 2,
                    "expenditure.date[<]" => $date_from,
                    "expenditure.stores" => $store,
                    "expenditure.deleted" => 0,
                    "OR" => [
                        "expenditure.debt" => [1121, 112],
                        "expenditure.has" => [1121, 112],
                    ],
                    "expenditure.debt[$search_debt]" => $debt,
                    "expenditure.has[$search_has]" => $has,
                ],
                "ORDER" => ["expenditure.date" => strtoupper($orderDir)],
            ]);

            $total_last_thu = (float)$app->sum("expenditure", "price", [
                "AND" => [
                    "OR" => [
                        "expenditure.ballot[~]" => $searchValue ?: '%',
                        "expenditure.content[~]" => $searchValue ?: '%',
                    ],
                    "expenditure.type" => 1,
                    "expenditure.date[<=]" => $date_to,
                    "expenditure.stores" => $store,
                    "expenditure.deleted" => 0,
                    "OR" => [
                        "expenditure.debt" => [1121, 112],
                        "expenditure.has" => [1121, 112],
                    ],
                    "expenditure.debt[$search_debt]" => $debt,
                    "expenditure.has[$search_has]" => $has,
                ],
                "ORDER" => ["expenditure.date" => strtoupper($orderDir)],
            ]);

            $total_last_chi = (float)$app->sum("expenditure", "price", [
                "AND" => [
                    "OR" => [
                        "expenditure.ballot[~]" => $searchValue ?: '%',
                        "expenditure.content[~]" => $searchValue ?: '%',
                    ],
                    "expenditure.type" => 2,
                    "expenditure.date[<=]" => $date_to,
                    "expenditure.stores" => $store,
                    "expenditure.deleted" => 0,
                    "OR" => [
                        "expenditure.debt" => [1121, 112],
                        "expenditure.has" => [1121, 112],
                    ],
                    "expenditure.debt[$search_debt]" => $debt,
                    "expenditure.has[$search_has]" => $has,
                ],
                "ORDER" => ["expenditure.date" => strtoupper($orderDir)],
            ]);

            // Calculate initial total
            $total_first = (float)$total_first_thu - (float)$total_dk111_1121 + (float)$total_first_chi - (float)$total_dk111_1121 + (float)(array_sum($total_page[1] ?? [0])) + (float)(array_sum($total_page[2] ?? [0]));
            // Fetch expenditure data
            $datas = [];
            $expenditure = 0;
            $total_chi = 0;
            $total_thu = 0;
            $total_chi1 = 0;
            $key = 0;
            $test[0] = "deposit_book";

            $app->select("expenditure", "*", $where, function ($data) use (&$datas, &$key, &$expenditure, &$total_chi, &$total_thu, &$total_chi1, &$total_first, $app, $jatbi, $setting, $test) {
                if (!isset($data['type'], $data['price'], $data['has'], $data['debt'])) {
                    error_log("Invalid expenditure record: " . print_r($data, true));
                    return;
                }

                $thu = 0;
                $chi = 0;
                $chi1 = 0;

                if ($data['type'] == 1) {
                    if (in_array($data['has'], [1121, 112]) && in_array($data['debt'], [111, 1111])) {
                        $thu = 0;
                        $chi1 = -(float)$data['price'];
                        $total_chi1 += $chi1;
                    } else {
                        $thu = (float)$data['price'];
                        $chi = 0;
                        $total_thu += $thu;
                    }
                } elseif ($data['type'] == 2) {
                    $thu = 0;
                    $chi = (float)$data['price'];
                    $total_chi += $chi;
                }

                $expenditure = ($expenditure == 0 && $test[0] == "deposit_book" ? (float)$total_first : (float)$expenditure);
                if ($key > 0 && isset($datas[$key-1]['type'], $datas[$key-1]['has'], $datas[$key-1]['debt'], $datas[$key-1]['price'])) {
                    $expenditure += ($datas[$key-1]['type'] == 1 && in_array($datas[$key-1]['has'], [1121, 112]) && in_array($datas[$key-1]['debt'], [111, 1111]) ? -(float)$datas[$key-1]['price'] : (float)$datas[$key-1]['price']);
                }

                $doi_tuong_html = '';
                // if ($data['orders'] != 0) {
                //     $Getorders = $app->get("orders", "*", ["id" => $data['orders'], "deleted" => 0]);
                //     if ($Getorders) {
                //         $doi_tuong_html .= '<p class="mb-0"><a href="#!" class="modal-url" data-url="/invoices/orders-views/' . $Getorders['id'] . '">#' . $setting['ballot_code']['orders'] . '-' . $Getorders['code'] . $Getorders['id'] . '</a></p>';
                //     }
                // }
                if ($data['invoices'] != 0) {
                    $GetInvoices = $app->get("invoices", ["id", "code"], ["id" => $data['invoices'], "deleted" => 0]);
                    if ($GetInvoices) {
                        $doi_tuong_html .= '<p class="mb-0"><a class="pjax-load" href="/invoices/invoices-views/' . $GetInvoices['id'] . '">#' . $setting['ballot_code']['invoices'] . '-' . $GetInvoices['code'] . $GetInvoices['id'] . '</a></p>';
                    }
                }
                if ($data['customers'] != 0) {
                    $GetCustomers = $app->get("customers", ["id", "name"], ["id" => $data['customers'], "deleted" => 0]);
                    if ($GetCustomers) {
                        $doi_tuong_html .= '<p class="mb-0">' . $GetCustomers['name'] . '</p>';
                    }
                }
                if ($data['personnels'] != 0) {
                    $GetPersonnel = $app->get("personnels", ["id", "name"], ["id" => $data['personnels'], "deleted" => 0]);
                    if ($GetPersonnel) {
                        $doi_tuong_html .= '<p class="mb-0">' . $GetPersonnel['name'] . '</p>';
                    }
                }
                if ($data['purchase'] != 0) {
                    $Getpurchase = $app->get("purchase", ["id", "code"], ["id" => $data['purchase'], "deleted" => 0]);
                    if ($Getpurchase) {
                        $doi_tuong_html .= '<p class="mb-0"><a href="#!" class="modal-url" data-url="/purchases/purchase-views/' . $Getpurchase['id'] . '">#' . $setting['ballot_code']['purchase'] . '-' . $Getpurchase['code'] . '</a></p>';
                    }
                }
                if ($data['vendor'] != 0) {
                    $Getvendor = $app->get("vendors", ["id", "name"], ["id" => $data['vendor'], "deleted" => 0]);
                    if ($Getvendor) {
                        $doi_tuong_html .= '<p class="mb-0">' . $Getvendor['name'] . '</p>';
                    }
                }

                $datas[] = [
                    "ngay" => '<a href="#!" class="modal-url" data-action="modal" data-url="/accountants/expenditure-views/' . $data['id'] . '">' . date($setting['site_date'] ?? "d/m/Y", strtotime($data['date'])) . '</a>',
                    "thu" => $data['type'] == 1 ? ($data['ballot'] ?? '') : '',
                    "chi" => $data['type'] == 2 ? ($data['ballot'] ?? '') : '',
                    "dien-giai" => $data['content'] ?? '',
                    "doi-tuong" => $doi_tuong_html,
                    "tai-khoan-no" => $app->get("accountants_code", "code", ["code" => $data['debt']]) ?? '',
                    "tai-khoan-co" => $app->get("accountants_code", "code", ["code" => $data['has']]) ?? '',
                    "thuu" => number_format($thu, 1),
                    "chii" => number_format($chi1 != 0 ? $chi1 : $chi, 1),
                    "ton" => number_format($expenditure + $thu + ($chi1 != 0 ? $chi1 : $chi), 1),
                    "type" => $data['type'],
                    "has" => $data['has'],
                    "debt" => $data['debt'],
                    "price" => (float)$data['price'],
                ];
                $key++;
            });

            $totals = [
                "dau-ky-thu" => number_format((float)$total_first_thu - (float)$total_dk111_1121 + (float)(array_sum($total_page[1] ?? [0])), 1),
                "dau-ky-chi" => number_format((float)$total_first_chi - (float)$total_dk111_1121 + (float)(array_sum($total_page[2] ?? [0])), 1),
                "dau-ky-ton" => number_format((float)$total_first_thu - (float)$total_dk111_1121 + (float)$total_first_chi - (float)$total_dk111_1121 + (float)(array_sum($total_page[1] ?? [0])) + (float)(array_sum($total_page[2] ?? [0])), 1),
                "tong-cong-thu" => number_format((float)$total_thu, 1),
                "tong-cong-chi" => number_format((float)$total_chi + (float)$total_chi1, 1),
                "tong-cong-ton" => number_format((float)$total_first + (float)$total_thu + (float)$total_chi + (float)$total_chi1, 1),
                "cuoi-ky-thu" => number_format((float)$total_last_thu - (float)$total_last_thu1 - (float)$total_dk111_1121, 1),
                "cuoi-ky-chi" => number_format((float)$total_last_chi - (float)$total_last_thu1 - (float)$total_dk111_1121, 1),
                "cuoi-ky-ton" => number_format((float)$total_last_thu - (float)$total_last_thu1 - (float)$total_dk111_1121 + (float)$total_last_chi - (float)$total_last_thu1 - (float)$total_dk111_1121, 1),
            ];

            // Return JSON data
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas ?? [],
                "footerData" => $totals,
            ]);
        }
    })->setPermissions(['deposit_book']);

    $app->router("/accounts_receivable", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $accStore) {
        $vars['title'] = $jatbi->lang("Chi tiết công nợ phải thu");

        if ($app->method() === 'GET') {
            // $vars['customers'] = $app->select("customers", ["id", "name"], ["deleted" => 0, "status" => 'A']);
            echo $app->render($setting['template'] . '/accountants/accounts_receivable.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            // Get DataTables parameters
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? $app->xss($_POST['search']['value']) : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

            // Get filter parameters
            $customers = isset($_POST['customers']) ? $app->xss($_POST['customers']) : '';
            $date_string = isset($_POST['date']) ? $app->xss($_POST['date']) : '';

            // Process date range
            if ($date_string) {
                $date = explode('-', $date_string);
                $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date[0]))));
                $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date[1]))));
            } else {
                $date_from = date('Y-m-01 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }

            // Handle session and stores
            
            $store = isset($_POST['stores']) ? $app->xss($_POST['stores']) : $accStore;

            // Build where clause
            $where = [
                "AND" => [
                    "invoices.deleted" => 0,
                    "invoices.type" => 1,
                    "invoices.cancel" => [0, 1],
                    "invoices.status" => 2,
                    "invoices.date[<>]" => [$date_from, $date_to],
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => ["invoices.id" => strtoupper($orderDir)],
            ];

            if ($customers != '' && $customers != null) {
                $where['AND']['invoices.customers'] = $customers;
            }
            if ($store != [0]) {
                $where['AND']['invoices.stores'] = $store;
            }
            if ($searchValue) {
                $where['AND']['OR'] = [
                    "invoices.code[~]" => $searchValue,
                    "invoices.notes[~]" => $searchValue,
                ];
            }
            // Count total records
            $count = $app->count("invoices", ["AND" => $where['AND']]);

            // Calculate initial balance
            $total_first = (float)$app->sum("invoices", "payments", [
                "AND" => [
                    "invoices.stores" => $store,
                    "invoices.customers[<>]" => $customers ?: '',
                    "invoices.deleted" => 0,
                    "invoices.type" => 1,
                    "invoices.cancel" => [0, 1],
                    "invoices.status" => 2,
                    "invoices.date[<]" => $date_from,
                ],
                "ORDER" => ["invoices.id" => "ASC"],
            ]);

            // Fetch invoice data for totals
            $getDatas = $app->select("invoices", ["id", "payments", "prepay"], [
                "AND" => [
                    "invoices.stores" => $store,
                    "invoices.customers[<>]" => $customers ?: '',
                    "invoices.date[<>]" => [$date_from, $date_to],
                    "invoices.deleted" => 0,
                    "invoices.cancel" => [0, 1],
                    "invoices.status" => 2,
                ],
            ]);
            $totalreturn = ['prepay' => [], 'payments' => []];
            if (!empty($getDatas)) {
                foreach ($getDatas as $value) {
                    $invoice = $app->get("invoices", ["id", "payments", "prepay"], ["id" => $value["id"], "type" => [3]]);
                    $totalreturn['prepay'][] = (float)($invoice['prepay'] ?? 0);
                    $totalreturn['payments'][] = (float)($invoice['payments'] ?? 0);
                }
            }

            // Fetch invoice data
            $datas = [];
            $tongno = 0;
            $tongco = 0;
            $app->select("invoices", ["id", "date", "code", "notes", "amount", "payments", "prepay"], $where, function ($data) use (&$datas, &$tongno, &$tongco, $app, $jatbi, $setting) {

                $no = (float)$data['payments'];
                $co = (float)$data['prepay'];
                $tongno += $no;
                $tongco += $co;

                $expenditure = $app->select("expenditure", ["id", "date", "price"], [
                    "invoices" => $data["id"],
                    "deleted" => 0,
                    "type" => 1,
                ]);
                if (!empty($expenditure)) {
                    foreach ($expenditure as $key1 => $value) {
                        $datas[] = [
                            "ngay" => date($setting['site_date'] ?? "d/m/Y", strtotime($value['date'])),
                            "so-chung-tu" => $data['code'] . $data['id'],
                            "dien-giai" => $data['notes'],
                            "no" => number_format($key1 == 0 ? $no : 0, 1),
                            "co" => number_format($value['price'], 1),
                            "xu-ly" => '',
                        ];
                    }
                } else {
                    $datas[] = [
                        "ngay" => date($setting['site_date'] ?? "d/m/Y", strtotime($data['date'])),
                        "so-chung-tu" => $data['code'] . $data['id'],
                        "dien-giai" => $data['notes'],
                        "no" => number_format($no, 1),
                        "co" => number_format($co, 1),
                        "xu-ly" => '',
                    ];
                }
            });

            $totals = [
                "so-du-dau-ky" => number_format($total_first, 1),
                "phat-sinh-no" => number_format(array_sum($totalreturn['payments']) - array_sum($totalreturn['prepay']), 1),
                "phat-sinh-co" => number_format(0, 1), // $phatsinhco is commented out, so set to 0
                "tong-cong-no" => number_format($tongno - array_sum($totalreturn['payments']) + array_sum($totalreturn['prepay']), 1),
                "tong-cong-co" => number_format($tongco, 1), // $phatsinhco is commented out, so no addition
            ];

            // Return JSON data
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas ?? [],
                "footerData" => $totals,
            ]);
        }
    })->setPermissions(['accounts_receivable']);

    $app->router("/subsidiary_ledger", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores, $accStore) {
        $vars['title'] = $jatbi->lang("Sổ chi tiết tài khoản");

        if ($app->method() === 'GET') {
            if(count($stores) > 1){
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
            $accountants = $app->select("accountants_code", ["id ", "name", "code"], ["deleted" => 0, "status" => 'A']);
            $accountants_formatted = array_map(function($item) {
                return [
                    'value' => $item['code'],
                    'text' => $item['code'] . ' - ' . $item['name']
                ];
            }, $accountants);
            array_unshift($accountants_formatted, [
                'value' => '',
                'text' => $jatbi->lang('Tất cả')
            ]);
            $vars["accountants"] = $accountants_formatted;
            echo $app->render($setting['template'] . '/accountants/subsidiary_ledger.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            // Get DataTables parameters
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? $app->xss($_POST['search']['value']) : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'date';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'ASC';

            // Get filter parameters
            $debtInput = isset($_POST['debt']) ? $app->xss($_POST['debt']) : '';
            $hasInput = isset($_POST['has']) ? $app->xss($_POST['has']) : '';
            $user = isset($_POST['user']) ? $app->xss($_POST['user']) : '';
            $date_string = isset($_POST['date']) ? $app->xss($_POST['date']) : '';

            // Process date range
            if ($date_string) {
                $date = explode('-', $date_string);
                $date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date[0]))));
                $date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date[1]))));
            } else {
                $date_from = date('Y-m-d 00:00:00');
                $date_to = date('Y-m-d 23:59:59');
            }

            // Handle account filtering
            if ($debtInput != '' && $hasInput != '') {
                $search_debt = "";
                $debt = $debtInput;
                $search_has = "";
                $has = $hasInput;
            } elseif ($debtInput != '' && $hasInput == '') {
                $search_debt = "";
                $debt = $debtInput;
                $search_has = "<>";
                $has = "";
            } elseif ($debtInput == '' && $hasInput != '') {
                $search_debt = "<>";
                $debt = "";
                $search_has = "";
                $has = $hasInput;
            } else {
                $search_debt = "<>";
                $debt = "";
                $search_has = "<>";
                $has = "";
            }

            // Handle session and stores
            
            $store = isset($_POST['stores']) ? $app->xss($_POST['stores']) : $accStore;

            // Build where clause
            $where = [
                "AND" => [
                    "OR" => [
                        "expenditure.ballot[~]" => $searchValue ?: '%',
                        "expenditure.content[~]" => $searchValue ?: '%',
                    ],
                    "expenditure.type" => [4, 7],
                    "OR" => [
                        "expenditure.debt" => [1121, 131],
                        "expenditure.has" => [1121, 131],
                    ],
                    "expenditure.debt[$search_debt]" => $debt,
                    "expenditure.has[$search_has]" => $has,
                    "expenditure.deleted" => 0,
                    "expenditure.date[<>]" => [$date_from, $date_to],
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => ["expenditure.date" => strtoupper($orderDir)],
            ];

            if ($user != '') {
                $where['AND']['expenditure.user'] = $user;
            }
            if ($store != [0]) {
                $where['AND']['expenditure.stores'] = $store;
            }

            // Count total records
            $count = $app->count("expenditure", ["AND" => $where['AND']]);

            // Calculate page totals
            $total_first_thu_page = $app->select("expenditure", ["price", "type"], [
                "AND" => $where['AND'],
                "LIMIT" => $start,
                "ORDER" => ["expenditure.id" => "ASC"],
            ]);
            $total_page = [];
            if (!empty($total_first_thu_page)) {
                foreach ($total_first_thu_page as $value) {
                    if (isset($value['type'])) {
                        $total_page[$value['type']][] = (float)($value['price'] ?? 0);
                    }
                }
            }

            // Calculate initial totals
            $total_first_thu = (float)$app->sum("expenditure", "price", [
                "AND" => [
                    "OR" => [
                        "expenditure.ballot[~]" => $searchValue ?: '%',
                        "expenditure.content[~]" => $searchValue ?: '%',
                    ],
                    "expenditure.type" => 7,
                    "OR" => [
                        "expenditure.debt" => [1121, 131],
                        "expenditure.has" => [1121, 131],
                    ],
                    "expenditure.debt[$search_debt]" => $debt,
                    "expenditure.has[$search_has]" => $has,
                    "expenditure.user[<>]" => $user ?: '',
                    "expenditure.date[<]" => $date_from,
                    "expenditure.stores" => $store,
                    "expenditure.deleted" => 0,
                ],
                "ORDER" => ["expenditure.id" => "ASC"],
            ]);

            $total_first_chi = (float)$app->sum("expenditure", "price", [
                "AND" => [
                    "OR" => [
                        "expenditure.ballot[~]" => $searchValue ?: '%',
                        "expenditure.content[~]" => $searchValue ?: '%',
                    ],
                    "expenditure.type" => 4,
                    "OR" => [
                        "expenditure.debt" => [1121, 131],
                        "expenditure.has" => [1121, 131],
                    ],
                    "expenditure.debt[$search_debt]" => $debt,
                    "expenditure.has[$search_has]" => $has,
                    "expenditure.user[<>]" => $user ?: '',
                    "expenditure.date[<]" => $date_from,
                    "expenditure.stores" => $store,
                    "expenditure.deleted" => 0,
                ],
                "ORDER" => ["expenditure.id" => "ASC"],
            ]);

            // Calculate final totals
            $total_last_thu = (float)$app->sum("expenditure", "price", [
                "AND" => [
                    "OR" => [
                        "expenditure.ballot[~]" => $searchValue ?: '%',
                    ],
                    "expenditure.type" => 7,
                    "OR" => [
                        "expenditure.debt" => [1121, 131],
                        "expenditure.has" => [1121, 131],
                    ],
                    "expenditure.debt[$search_debt]" => $debt,
                    "expenditure.has[$search_has]" => $has,
                    "expenditure.user[<>]" => $user ?: '',
                    "expenditure.stores" => $store,
                    "expenditure.deleted" => 0,
                    "expenditure.date[<=]" => $date_to,
                ],
                "ORDER" => ["expenditure.id" => "ASC"],
            ]);

            $total_last_chi = (float)$app->sum("expenditure", "price", [
                "AND" => [
                    "OR" => [
                        "expenditure.ballot[~]" => $searchValue ?: '%',
                    ],
                    "expenditure.type" => 4,
                    "OR" => [
                        "expenditure.debt" => [1121, 131],
                        "expenditure.has" => [1121, 131],
                    ],
                    "expenditure.debt[$search_debt]" => $debt,
                    "expenditure.has[$search_has]" => $has,
                    "expenditure.user[<>]" => $user ?: '',
                    "expenditure.stores" => $store,
                    "expenditure.deleted" => 0,
                    "expenditure.date[<=]" => $date_to,
                ],
                "ORDER" => ["expenditure.id" => "ASC"],
            ]);

            // Fetch expenditure data
            $datas = [];
            $total_thu = 0;
            $total_chi = 0;
            $app->select("expenditure", ["id", "type", "price", "stores", "invoices", "ballot", "customers", "personnels", "purchase", "vendor", "debt", "has", "date_poster", "date", "content"], $where, function ($data) use (&$datas, &$total_thu, &$total_chi, $app, $jatbi, $setting) {
                if (!isset($data['id'], $data['type'], $data['price'], $data['has'], $data['content'], $data['date'])) {
                    error_log("Invalid expenditure record: " . print_r($data, true));
                    return;
                }

                $thu = $data['type'] == 7 ? (float)$data['price'] : 0;
                $chi = $data['type'] == 4 ? (float)$data['price'] : 0;
                $total_thu += $thu;
                $total_chi += $chi;

                $so_hieu_html = '';
                // if ($data['orders'] != 0) {
                //     $Getorders = $app->get("orders", ["id", "code", "date"], ["id" => $data['orders'], "deleted" => 0]);
                //     if ($Getorders) {
                //         $so_hieu_html .= '<p class="mb-0"><a href="#!" class="modal-url" data-url="/invoices/orders-views/' . $Getorders['id'] . '">#' . ($setting['ballot_code']['orders'] ?? '') . '-' . $Getorders['code'] . $Getorders['id'] . '</a></p>';
                //     }
                // }
                if ($data['invoices'] != 0) {
                    $GetInvoices = $app->get("invoices", ["id", "code", "date"], ["id" => $data['invoices'], "deleted" => 0]);
                    if ($GetInvoices) {
                        $so_hieu_html .= '<p class="mb-0"><a class="pjax-load" href="/invoices/invoices-views/' . $GetInvoices['id'] . '">#' . ($setting['ballot_code']['invoices'] ?? '') . '-' . $GetInvoices['code'] . $GetInvoices['id'] . '</a></p>';
                    }
                }
                if ($data['customers'] != 0) {
                    $GetCustomers = $app->get("customers", ["id", "name"], ["id" => $data['customers'], "deleted" => 0]);
                    if ($GetCustomers) {
                        $so_hieu_html .= '<p class="mb-0">' . $GetCustomers['name'] . '</p>';
                    }
                }
                if ($data['personnels'] != 0) {
                    $GetPersonnel = $app->get("personnels", ["id", "name"], ["id" => $data['personnels'], "deleted" => 0]);
                    if ($GetPersonnel) {
                        $so_hieu_html .= '<p class="mb-0">' . $GetPersonnel['name'] . '</p>';
                    }
                }
                if ($data['purchase'] != 0) {
                    $Getpurchase = $app->get("purchase", ["id", "code", "date"], ["id" => $data['purchase'], "deleted" => 0]);
                    if ($Getpurchase) {
                        $so_hieu_html .= '<p class="mb-0"><a href="#!" class="modal-url" data-url="/purchases/purchase-views/' . $Getpurchase['id'] . '">#' . ($setting['ballot_code']['purchase'] ?? '') . '-' . $Getpurchase['code'] . '</a></p>';
                    }
                }
                if ($data['vendor'] != 0) {
                    $Getvendor = $app->get("vendors", ["id", "name"], ["id" => $data['vendor'], "deleted" => 0]);
                    if ($Getvendor) {
                        $so_hieu_html .= '<p class="mb-0">' . $Getvendor['name'] . '</p>';
                    }
                }

                $ngay_chung_tu = '';
                if ($data['invoices'] != 0 && $GetInvoices) {
                    $ngay_chung_tu = date($setting['site_date'] ?? "d/m/Y", strtotime($GetInvoices['date']));
                } elseif ($data['purchase'] != 0 && $Getpurchase) {
                    $ngay_chung_tu = date($setting['site_date'] ?? "d/m/Y", strtotime($Getpurchase['date']));
                }

                $tai_khoan_doi_ung = $app->get("accountants_code", "code", ["code" => $data['has']]) ?? '';

                $datas[] = [
                    "ngay" => date($setting['site_date'] ?? "d/m/Y", strtotime($data['date'])),
                    "so-hieu" => $so_hieu_html,
                    "ngay-chung-tu" => $ngay_chung_tu,
                    "dien-giai" => $data['content'],
                    "tai-khoan-doi-ung" => $tai_khoan_doi_ung,
                    "no" => number_format($chi, 1),
                    "co" => number_format($thu, 1),
                ];
            });

            $totals = [
                "dau-ky-no" => number_format($total_first_chi + (array_sum($total_page[2] ?? [0])), 1),
                "dau-ky-co" => number_format($total_first_thu + (array_sum($total_page[1] ?? [0])), 1),
                "tong-cong-no" => number_format($total_chi + $total_first_chi, 1),
                "tong-cong-co" => number_format($total_thu + $total_first_thu, 1),
            ];

            // Return JSON data
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas ?? [],
                "footerData" => $totals,
            ]);
        }
    })->setPermissions(['subsidiary_ledger']);

    $app->router("/income_statement", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores, $accStore) {
        $jatbi->permission('income_statement'); // Kiểm tra quyền
        $vars['title'] = $jatbi->lang("Báo cáo kết quả kinh doanh");

        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/accountants/income_statement.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            // Xử lý khoảng thời gian
            $date_string = isset($_POST['date']) ? $app->xss($_POST['date']) : '';
            if ($date_string) {
                $date = explode('-', $date_string);
                $date_from = date('Y-m-d', strtotime(str_replace('/', '-', trim($date[0]))));
                $date_to = date('Y-m-d', strtotime(str_replace('/', '-', trim($date[1]))));
            } else {
                $date_from = date('Y-01-01');
                $date_to = date('Y-m-d');
            }

            // Xử lý stores
            
            $store = isset($_POST['stores']) ? $app->xss($_POST['stores']) : $accStore;

            // Tính toán chỉ số cho năm hiện tại
            $i01 = (float)$app->sum("invoices", "payments", [
                "type" => [1, 2],
                "deleted" => 0,
                "date[<>]" => [$date_from, $date_to],
                "stores" => $store,
            ]);

            $s01 = (float)$app->sum("invoices", "payments", [
                "type" => [1, 2],
                "deleted" => 0,
                "date[<]" => $date_from,
                "stores" => $store,
            ]);

            $i02 = (float)$app->sum("invoices", "payments", [
                "type" => 3,
                "deleted" => 0,
                "date[<>]" => [$date_from, $date_to],
                "stores" => $store,
            ]);

            $s02 = (float)$app->sum("invoices", "payments", [
                "type" => 3,
                "deleted" => 0,
                "date[<]" => $date_from,
                "stores" => $store,
            ]);

            $i10 = $i01 - $i02;
            $s10 = $s01 - $s02;

            $i11 = (float)$app->sum("expenditure", "price", [
                "debt" => 911,
                "has" => 632,
                "deleted" => 0,
                "date[<>]" => [$date_from, $date_to],
                "stores" => $store,
            ]);

            $s11 = (float)$app->sum("expenditure", "price", [
                "debt" => 911,
                "has" => 632,
                "deleted" => 0,
                "date[<]" => $date_from,
                "stores" => $store,
            ]);

            $i20 = $i10 - $i11;
            $s20 = $s10 - $s11;

            $i21 = (float)$app->sum("expenditure", "price", [
                "debt" => 515,
                "has" => 911,
                "deleted" => 0,
                "date[<>]" => [$date_from, $date_to],
                "stores" => $store,
            ]);

            $s21 = (float)$app->sum("expenditure", "price", [
                "debt" => 515,
                "has" => 911,
                "deleted" => 0,
                "date[<]" => $date_from,
                "stores" => $store,
            ]);

            $i22 = (float)$app->sum("expenditure", "price", [
                "debt" => 635,
                "has" => 911,
                "deleted" => 0,
                "date[<>]" => [$date_from, $date_to],
                "stores" => $store,
            ]);

            $s22 = (float)$app->sum("expenditure", "price", [
                "debt" => 635,
                "has" => 911,
                "deleted" => 0,
                "date[<]" => $date_from,
                "stores" => $store,
            ]);

            $i23 = (float)$app->sum("expenditure", "price", [
                "OR" => ["debt" => 635],
                "deleted" => 0,
                "date[<>]" => [$date_from, $date_to],
                "stores" => $store,
            ]);

            $s23 = (float)$app->sum("expenditure", "price", [
                "OR" => ["debt" => 635],
                "deleted" => 0,
                "date[<]" => $date_from,
                "stores" => $store,
            ]);

            $i24 = (float)$app->sum("expenditure", "price", [
                "type" => [1, 2],
                "debt" => [6421, 6422],
                "deleted" => 0,
                "date[<>]" => [$date_from, $date_to],
                "stores" => $store,
            ]);

            $s24 = (float)$app->sum("expenditure", "price", [
                "type" => [1, 2],
                "debt" => [6421, 6422],
                "deleted" => 0,
                "date[<]" => $date_from,
                "stores" => $store,
            ]);

            $ii24 = (float)$app->sum("expenditure", "price", [
                "type" => 3,
                "debt" => [6421, 6422],
                "deleted" => 0,
                "date[<>]" => [$date_from, $date_to],
                "stores" => $store,
            ]);

            $ss24 = (float)$app->sum("expenditure", "price", [
                "type" => 3,
                "debt" => [6421, 6422],
                "deleted" => 0,
                "date[<]" => $date_from,
                "stores" => $store,
            ]);

            $i30 = $i20 + $i21 + $i22 + $i24 - $ii24;
            $s30 = $s20 + $s21 + $s22 + $s24 - $ss24;

            $i31 = (float)$app->sum("expenditure", "price", [
                "debt" => 711,
                "has" => 911,
                "deleted" => 0,
                "date[<>]" => [$date_from, $date_to],
                "stores" => $store,
            ]);

            $s31 = (float)$app->sum("expenditure", "price", [
                "debt" => 711,
                "has" => 911,
                "deleted" => 0,
                "date[<]" => $date_from,
                "stores" => $store,
            ]);

            $i32 = (float)$app->sum("expenditure", "price", [
                "debt" => 911,
                "has" => [811, 821], // Sửa lỗi: thêm 821 cho i51
                "deleted" => 0,
                "date[<>]" => [$date_from, $date_to],
                "stores" => $store,
            ]);

            $s32 = (float)$app->sum("expenditure", "price", [
                "debt" => 911,
                "has" => [811, 821], // Sửa lỗi: thêm 821 cho s51
                "deleted" => 0,
                "date[<]" => $date_from,
                "stores" => $store,
            ]);

            $i40 = $i31 + $i32;
            $s40 = $s31 + $s32;

            $i50 = $i30 + $i40;
            $s50 = $s30 + $s40;

            // Tính i51 và s51 (chi phí thuế TNDN)
            $i51 = (float)$app->sum("expenditure", "price", [
                "debt" => 911,
                "has" => 821,
                "deleted" => 0,
                "date[<>]" => [$date_from, $date_to],
                "stores" => $store,
            ]);

            $s51 = (float)$app->sum("expenditure", "price", [
                "debt" => 911,
                "has" => 821,
                "deleted" => 0,
                "date[<]" => $date_from,
                "stores" => $store,
            ]);

            $i60 = $i50 - $i51; // Sửa lỗi: 60 = 50 - 51
            $s60 = $s50 - $s51;

            // Chuẩn bị dữ liệu cho bảng
            $datas = [
                [
                    "stt" => "1",
                    "chi-tieu" => $jatbi->lang("Doanh thu bán hàng và cung cấp dịch vụ"),
                    "ma" => "01",
                    "thuyet-minh" => "VI.25",
                    "so-nam-nay" => number_format($i01),
                    "so-nam-truoc" => number_format($s01),
                    "nguon-so-lieu" => $jatbi->lang("Tổng phát sinh bên Có TK 5111 (không bao gồm các loại thuế gián thu như thuế GTGT, thuế tiêu thụ đặc biệt, thuế xuất khẩu và các loại thuế gián thu khác"),
                ],
                [
                    "stt" => "2",
                    "chi-tieu" => $jatbi->lang("Các khoản trừ doanh thu"),
                    "ma" => "02",
                    "thuyet-minh" => "",
                    "so-nam-nay" => number_format($i02),
                    "so-nam-truoc" => number_format($s02),
                    "nguon-so-lieu" => $jatbi->lang("Tổng phát sinh bên Nợ TK 511 đối ứng với bên có các TK 111, 112, 131"),
                ],
                [
                    "stt" => "3",
                    "chi-tieu" => $jatbi->lang("Doanh thu thuần về bán hàng và cung cấp dịch vụ (10 = 01 - 02)"),
                    "ma" => "10",
                    "thuyet-minh" => "",
                    "so-nam-nay" => number_format($i10),
                    "so-nam-truoc" => number_format($s10),
                    "nguon-so-lieu" => "",
                ],
                [
                    "stt" => "4",
                    "chi-tieu" => $jatbi->lang("Giá vốn hàng bán"),
                    "ma" => "11",
                    "thuyet-minh" => "VI.27",
                    "so-nam-nay" => number_format($i11),
                    "so-nam-truoc" => number_format($s11),
                    "nguon-so-lieu" => $jatbi->lang("Tổng phát sinh bên Có của TK 632 đối ứng với bên Nợ của TK 911"),
                ],
                [
                    "stt" => "5",
                    "chi-tieu" => $jatbi->lang("Lợi nhuận gộp về bán hàng và cung cấp dịch vụ (20 = 10 - 11)"),
                    "ma" => "20",
                    "thuyet-minh" => "",
                    "so-nam-nay" => number_format($i20),
                    "so-nam-truoc" => number_format($s20),
                    "nguon-so-lieu" => "",
                ],
                [
                    "stt" => "6",
                    "chi-tieu" => $jatbi->lang("Doanh thu hoạt động tài chính"),
                    "ma" => "21",
                    "thuyet-minh" => "VI.26",
                    "so-nam-nay" => number_format($i21),
                    "so-nam-truoc" => number_format($s21),
                    "nguon-so-lieu" => $jatbi->lang("Tổng phát sinh bên Nợ của TK 515 đối ứng với bên Có của TK 911"),
                ],
                [
                    "stt" => "7",
                    "chi-tieu" => $jatbi->lang("Chi phí tài chính"),
                    "ma" => "22",
                    "thuyet-minh" => "VI.28",
                    "so-nam-nay" => number_format($i22),
                    "so-nam-truoc" => number_format($s22),
                    "nguon-so-lieu" => $jatbi->lang("Tổng phát sinh bên Có của TK 635 đối ứng với bên Nợ của TK 911"),
                ],
                [
                    "stt" => "",
                    "chi-tieu" => $jatbi->lang("Trong đó: Chi phí lãi vay"),
                    "ma" => "23",
                    "thuyet-minh" => "",
                    "so-nam-nay" => number_format($i23),
                    "so-nam-truoc" => number_format($s23),
                    "nguon-so-lieu" => $jatbi->lang("Căn cứ vào Sổ kế toán chi tiết TK 635: chi tiết cho chi phí lãi vay"),
                ],
                [
                    "stt" => "8",
                    "chi-tieu" => $jatbi->lang("Chi phí quản lý kinh doanh"),
                    "ma" => "24",
                    "thuyet-minh" => "",
                    "so-nam-nay" => str_replace('-', '', number_format($i24 - $ii24)),
                    "so-nam-truoc" => str_replace('-', '', number_format($s24 - $ss24)),
                    "nguon-so-lieu" => $jatbi->lang("Tổng phát sinh bên Có của TK 642 đối ứng với bên Nợ của TK 911"),
                ],
                [
                    "stt" => "9",
                    "chi-tieu" => $jatbi->lang("Lợi nhuận thuần từ hoạt động kinh doanh (30 = 20 + 21 - 22 – 24)"),
                    "ma" => "30",
                    "thuyet-minh" => "",
                    "so-nam-nay" => number_format($i30),
                    "so-nam-truoc" => number_format($s30),
                    "nguon-so-lieu" => "",
                ],
                [
                    "stt" => "10",
                    "chi-tieu" => $jatbi->lang("Thu nhập khác"),
                    "ma" => "31",
                    "thuyet-minh" => "",
                    "so-nam-nay" => number_format($i31),
                    "so-nam-truoc" => number_format($s31),
                    "nguon-so-lieu" => $jatbi->lang("Tổng phát sinh bên Nợ của TK 711 (sau khi đã trừ đi phần thu nhập khác từ Thanh lý, Nhượng bán TSCĐ) đối ứng với bên Có của TK 911 Cộng với phần chênh lệch LÃI từ Thanh lý Nhượng bán TSCĐ"),
                ],
                [
                    "stt" => "11",
                    "chi-tieu" => $jatbi->lang("Chi phí khác"),
                    "ma" => "32",
                    "thuyet-minh" => "",
                    "so-nam-nay" => number_format($i32),
                    "so-nam-truoc" => number_format($s32),
                    "nguon-so-lieu" => $jatbi->lang("Tổng phát sinh bên Có của TK 811 đối ứng với bên Nợ của TK 911 Cộng với phần Chênh lệch LỖ từ Thanh lý, Nhượng bán TSCĐ"),
                ],
                [
                    "stt" => "12",
                    "chi-tieu" => $jatbi->lang("Lợi nhuận khác (40 = 31 - 32)"),
                    "ma" => "40",
                    "thuyet-minh" => "",
                    "so-nam-nay" => number_format($i40),
                    "so-nam-truoc" => number_format($s40),
                    "nguon-so-lieu" => "",
                ],
                [
                    "stt" => "13",
                    "chi-tieu" => $jatbi->lang("Tổng lợi nhuận kế toán trước thuế (50 = 30 + 40)"),
                    "ma" => "50",
                    "thuyet-minh" => "",
                    "so-nam-nay" => number_format($i50),
                    "so-nam-truoc" => number_format($s50),
                    "nguon-so-lieu" => "",
                ],
                [
                    "stt" => "14",
                    "chi-tieu" => $jatbi->lang("Chi phí thuế TNDN"),
                    "ma" => "51",
                    "thuyet-minh" => "VI.30",
                    "so-nam-nay" => number_format($i51),
                    "so-nam-truoc" => number_format($s51),
                    "nguon-so-lieu" => $jatbi->lang("Tổng phát sinh bên Có TK 821 đối ứng với bên Nợ TK 911"),
                ],
                [
                    "stt" => "15",
                    "chi-tieu" => $jatbi->lang("Lợi nhuận sau thuế thu nhập doanh nghiệp (60 = 50 – 51)"),
                    "ma" => "60",
                    "thuyet-minh" => "",
                    "so-nam-nay" => number_format($i60),
                    "so-nam-truoc" => number_format($s60),
                    "nguon-so-lieu" => "",
                ],
            ];

            // Trả về dữ liệu JSON
            echo json_encode([
                "draw" => isset($_POST['draw']) ? intval($_POST['draw']) : 0,
                "recordsTotal" => count($datas),
                "recordsFiltered" => count($datas),
                "data" => $datas,
            ]);
        }
    })->setPermissions(['income_statement']);

    $app->router("/inventory_table", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores, $accStore) {
        $vars['title'] = $jatbi->lang("Bảng kiểm kê");

        if ($app->method() === 'GET') {
            // Lấy danh sách cửa hàng (stores)
            if(count($stores) > 1){
                array_unshift($stores, [
                    'value' => '',
                    'text' => $jatbi->lang('Tất cả')
                ]);
            }
            $vars['stores'] = $stores;

            // Tạo danh sách tháng và năm cho bộ lọc
            // Tạo danh sách tháng
            $vars['months'] = array_map(function ($m) use ($jatbi) {
                return [
                    'value' => $m,
                    'text' => $jatbi->lang("Tháng") . " $m"
                ];
            }, range(1, 12));
            array_unshift($vars['months'], [
                'value' => '',
                'text' => $jatbi->lang('Tháng')
            ]);

            // Tạo danh sách năm
            $vars['years'] = array_map(function ($y) {
                return [
                    'value' => $y,
                    'text' => $y
                ];
            }, range(2021, date("Y")));
            array_unshift($vars['years'], [
                'value' => '',
                'text' => $jatbi->lang('Năm')
            ]);

            echo $app->render($setting['template'] . '/accountants/inventory_table.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            // Lấy tham số DataTables
            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? $app->xss($_POST['search']['value']) : '';
            $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

            // Lấy tham số lọc
            $branch = isset($_POST['branch']) ? $app->xss($_POST['branch']) : '';
            $year = isset($_POST['year']) ? $app->xss($_POST['year']) : date("Y");
            $month = isset($_POST['month']) ? $app->xss($_POST['month']) : date("m");

            // Xử lý khoảng thời gian
            $date_from = date("Y-m-d", strtotime("$year-$month-01"));
            $date_to = date("Y-m-t", strtotime("$year-$month-01"));

            // Xử lý cửa hàng (stores)
            
            $store = isset($_POST['stores']) ? $app->xss($_POST['stores']) : $accStore;

            // Tạo điều kiện WHERE
            $where = [
                "AND" => [
                    "OR" => [
                        "inventory_table.name_product[~]" => $searchValue ?: '%',
                        "inventory_table.code_product[~]" => $searchValue ?: '%',
                    ],
                    "inventory_table.date[<>]" => [$date_from, $date_to],
                    "inventory_table.deleted" => 0,
                ],
                "LIMIT" => [$start, $length],
                "ORDER" => ["inventory_table.$orderName" => strtoupper($orderDir)],
            ];

            if ($branch != '') {
                $where['AND']['inventory_table.branch'] = $branch;
            }
            if ($store != [0]) {
                $where['AND']['inventory_table.stores'] = $store;
            }

            // Đếm tổng số bản ghi
            $count = $app->count("inventory_table", ["AND" => $where['AND']]);

            // Lấy dữ liệu
            $datas = [];
            $sum_ketoan = 0;
            $sum_kiemke = 0;
            $sum_chenhlech = 0;
            $sum_giatriketoan = 0;
            $sum_giatrikiemke = 0;
            $sum_chenhlechdongia = 0;
            $key = $start;

            $app->select("inventory_table", "*", $where, function ($data) use (&$datas, &$key, &$sum_ketoan, &$sum_kiemke, &$sum_chenhlech, &$sum_giatriketoan, &$sum_giatrikiemke, &$sum_chenhlechdongia, $app, $jatbi, $setting) {
                if (!isset($data['id'], $data['amount_product'], $data['amount'], $data['price_product'], $data['price'], $data['code_product'], $data['name_product'], $data['date'])) {
                    error_log("Invalid inventory_table record: " . print_r($data, true));
                    return;
                }

                // Tính toán chênh lệch
                $chenhlechsoluong = (float)$data['amount_product'] - (float)$data['amount'];
                $giatriketoan = (float)$data['price_product'] * (float)$data['amount_product'];
                $giatrikiemke = (float)$data['price'] * (float)$data['amount'];
                $chenhlechdongia = $giatriketoan - $giatrikiemke;

                // Tổng hợp
                $sum_ketoan += (float)$data['amount_product'];
                $sum_kiemke += (float)$data['amount'];
                $sum_chenhlech += $chenhlechsoluong;
                $sum_giatriketoan += $giatriketoan;
                $sum_giatrikiemke += $giatrikiemke;
                $sum_chenhlechdongia += $chenhlechdongia;

                // Tạo HTML cho cột xử lý
                $xuLy = $chenhlechsoluong != 0
                ? $app->component("action", [
                    "button" => [
                        [
                            'type' => 'button',
                            'name' => $jatbi->lang("Sửa"),
                            'action' => [
                                'data-url' => '/accountants/handle/' . $data['id'],
                                'data-action' => 'modal'
                            ]
                        ],
                    ]
                ])
                : '<div class="text-success">Đã xử lý</div>';
                
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "stt" => ++$key,
                    "thang-kiem-ke" => date('d-m-Y', strtotime($data['date'])),
                    "ma-hang" => $data['code_product'] ?? '',
                    "ten-hang" => $data['name_product'] ?? '',
                    "soluong-ketoan" => number_format($data['amount_product'], 1),
                    "soluong-kiemke" => number_format($data['amount'], 1),
                    "soluong-chenhlech" => number_format($chenhlechsoluong, 1),
                    "giatri-ketoan" => number_format($giatriketoan),
                    "giatri-kiemke" => number_format($giatrikiemke),
                    "giatri-chenhlech" => number_format($chenhlechdongia),
                    "xu-ly" => $xuLy
                ];
            });

            // Tạo dữ liệu tổng hợp cho footer
            $totals = [
                "sum_ketoan" => number_format($sum_ketoan, 1),
                "sum_kiemke" => number_format($sum_kiemke, 1),
                "sum_chenhlech" => number_format($sum_chenhlech, 1),
                "sum_giatriketoan" => number_format($sum_giatriketoan),
                "sum_giatrikiemke" => number_format($sum_giatrikiemke),
                "sum_chenhlechdongia" => number_format($sum_chenhlechdongia),
            ];

            // Trả về JSON cho DataTables
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $count,
                "recordsFiltered" => $count,
                "data" => $datas ?? [],
                "footerData" => $totals,
            ]);
        }
    })->setPermissions(['inventory_table']);

    $app->router("/inventory_table-add", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores, $accStore) {
        $vars['title'] = $jatbi->lang("Bảng kiểm kê");
        $action = "add";
        if(!isset($_SESSION['inventory_table'][$action]['date'])){
            $_SESSION['inventory_table'][$action]['date'] = date("Y-m-d");
        }
        if(count($stores)==1){
            $_SESSION['inventory_table']['add']['stores'] = ["id"=>$app->get("stores","id",["id"=>$accStore,"status"=>'A',"deleted"=>0,"ORDER"=>["id"=>"ASC"]])];
        }
        $data = [
            "date" => $_SESSION['inventory_table'][$action]['date'],
            "content" => $_SESSION['inventory_table'][$action]['content'] ?? "",
            "stores" => $_SESSION['inventory_table'][$action]['stores'] ?? "",
        ];	
        $storess = $app->select("stores", "*",["deleted"=> 0,"status"=>'A',"id"=>$accStore]);
        $storess_formatted = array_map(function($item) {
            return [
                'value' => $item['id'],
                'text' => $item['name']
            ];
        }, $storess);
        array_unshift($storess_formatted, [
            'value' => '',
            'text' => $jatbi->lang('Tất cả')
        ]);
        $vars["storess"] = $storess_formatted;
        $SelectProducts = $_SESSION['inventory_table'][$action]['products'] ?? [];
        $vars['action'] = $action;
        $vars['data'] = $data;
        $vars['SelectProducts'] = $SelectProducts;
        echo $app->render($setting['template'] . '/accountants/inventory_table-add.html', $vars);

    })->setPermissions(['inventory_table']);

    $app->router("/inventory_table-deleted", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores, $accStore) {
        $vars['title'] = $jatbi->lang("Xóa chi tiêu");
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/deleted.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $boxid = explode(',', $app->xss($_GET['box']));
            $datas = $app->select("inventory_table", "*", ["id" => $boxid, "deleted" => 0]);
            if (count($datas) > 0) {
                foreach ($datas as $data) {
                    $app->update("inventory_table", ["deleted" => 1], ["id" => $data['id']]);
                }
                $jatbi->logs('inventory_table', 'inventory_table-deleted', $datas);
                $jatbi->trash('/inventory_table/inventory_table-restore', "Xóa chi tiêu: ", ["database" => 'inventory_table', "data" => $boxid]);
                echo json_encode(['status' => 'success', "content" => $jatbi->lang("Cập nhật thành công")]);
            } else {
                echo json_encode(['status' => 'error', 'content' => $jatbi->lang("Có lỗi xẩy ra")]);
            }
        }
    })->setPermissions(['inventory_table']);
    
    $app->router("/products-inventory_table", ['POST'], function ($vars) use ($app) {
        $app->header([
            'Content-Type' => 'application/json',
        ]);

        $searchValue = isset($_POST['search']) ? $app->xss($_POST['search']) : '';
        $SearchStore = $_SESSION['inventory_table']['add']['stores'] ?? "";

        $where = [
            "AND"=>[
				"OR"=>[
					"name[~]"=>$searchValue,
					"code[~]"=>$searchValue,
				],
				"status"=>'A',
				"deleted"=>0,
				"stores" => $SearchStore,
			],
            "LIMIT" => 15
        ];

        $datas = [];
        $app->select("products", [
            "id","code","name","stores","branch","price"
        ], $where, function ($data) use (&$datas, $vars, $app) {
            $datas[] = [
                "text" => $data['code'].' - '.$data['name'].' - '.$app->get("stores","name",["id"=>$data['stores']]).' - '.$app->get("branch","name",["id"=>$data['branch']]),
                "url" => "/accountants/inventory_table-update/add/products/add/" . $data['id'],
                "value" => $data['id'],
            ];
        });

        echo json_encode($datas);
    });

    $app->router('/inventory_table-update/add/products/add/{id}', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
        $app->header(['Content-Type' => 'application/json']);
        $action = "add";
        $data = $app->get("products", "*",["id"=>$app->xss($vars['id']),"status"=>"A","deleted"=>0,]);
        if($data>1){
            $_SESSION['inventory_table'][$action]['products'][] = [
                "products"=>$data['id'],
                "amount"=>1,
                "price"=>$data['price'],
                "code"=>$data['code'],
                "name"=>$data['name'],
            ];
            echo json_encode(['status'=>'success','content'=> "Cập nhật thành công"]);
        }
        else {
            echo json_encode(['status'=>'error','content'=> "Cập nhật thất bại"]);
        }
    });

    $app->router('/inventory_table-update/add/products/deleted/{id}', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
        $app->header(['Content-Type' => 'application/json']);
        $action = "add";
        unset($_SESSION['inventory_table'][$action]['products'][$vars['id']]);
        echo json_encode(['status'=>'success','content'=> "Cập nhật thành công"]);
    });

    $app->router('/inventory_table-update/add/products/amount/{id}', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
        $app->header(['Content-Type' => 'application/json']);
        $action = "add";
        $_SESSION['inventory_table'][$action]['products'][$vars['id']]['amount'] = $app->xss(str_replace([','],'',$_POST['value']));
        echo json_encode(['status'=>'success','content'=> "Cập nhật thành công"]);
    });

    $app->router('/inventory_table-update/add/stores', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
        $app->header(['Content-Type' => 'application/json']);
        $action = "add";

        $data = $app->get("stores", "*",["id"=>$app->xss($_POST['value'])]);
        if($data>1){
            unset($_SESSION['inventory_table'][$action]['products']);
            $_SESSION['inventory_table'][$action]['stores'] = [
                "id"=>$data['id'],
                "name"=>$data['name'],
            ];
            echo json_encode(['status'=>'success','content'=> "Cập nhật thành công"]);
        }
        else {
            echo json_encode(['status'=>'error','content'=> "Cập nhật thất bại"]);
        }
    });

    $app->router('/inventory_table-update/add/date', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
        $app->header(['Content-Type' => 'application/json']);
        $action = "add";
        $_SESSION['inventory_table'][$action]['date'] = $app->xss($_POST['value']);
        echo json_encode(['status'=>'success','content'=> "Cập nhật thành công"]);
    });

    $app->router('/inventory_table-update/add/content', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
        $app->header(['Content-Type' => 'application/json']);
        $action = "add";
        $_SESSION['inventory_table'][$action]['content'] = $app->xss($_POST['value']);
        echo json_encode(['status'=>'success','content'=> "Cập nhật thành công"]);
    });

    $app->router('/inventory_table-update/add/cancel', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'GET') {
            echo $app->render($setting['template'] . '/common/comfirm-modal.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $action = "add";
            unset($_SESSION['inventory_table'][$action]);
            echo json_encode(['status'=>'success','content'=> "Cập nhật thành công"]);
        }
    });

    $app->router('/inventory_table-update/add/completed', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting) {
        if ($app->method() === 'GET') {
            $vars['url'] = "/accountants/inventory_table";
            echo $app->render($setting['template'] . '/common/comfirm-modal.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            $action = "add";
            $error_warehouses = false;
            $error = [];
            $nhap = $xuat = $chuyen = $huy = $slkt = $insert_logs = [];
            $inventory_table = $_SESSION['inventory_table'][$action] ?? [];

            if (!isset($inventory_table['products']) || !is_array($inventory_table['products'])) {
                echo json_encode(['status'=>'error','content'=> "Không có sản phẩm để cập nhật"]);
                return;
            }

            foreach ($inventory_table['products'] as $value1) {
                if (!isset($value1['amount']) || $value1['amount'] === ''){
                    $error_warehouses = 'true';
                }
            }
            if($error_warehouses){
                $error = ['status'=>'error','content'=> "Vui lòng nhập số lượng"];
            }
            if(count($error)==0){
                foreach ($inventory_table['products'] as $key => $value) {
                    $product = $app->get("products",["id","name","code","price","branch","stores"],["id"=>$value['products']]);
                    $soluong = $app->select("warehouses_logs",["products","amount","type","data"],["products"=>$value['products'],"date[<=]"=>$inventory_table['date']]);
                    foreach ($soluong as $as => $sl) {
                        if ($sl['data'] !== "products") continue;

                        $productId = $value['products'];

                        if ($sl['type'] == 'import') {
                            if (!isset($nhap[$productId])) $nhap[$productId] = 0;
                            $nhap[$productId] += $sl['amount'];
                        }
                        elseif ($sl['type'] == 'export') {
                            if (!isset($xuat[$productId])) $xuat[$productId] = 0;
                            $xuat[$productId] += $sl['amount'];
                        }
                        elseif ($sl['type'] == 'move') {
                            if (!isset($chuyen[$productId])) $chuyen[$productId] = 0;
                            $chuyen[$productId] += $sl['amount'];
                        }
                        elseif ($sl['type'] == 'error') {
                            if (!isset($huy[$productId])) $huy[$productId] = 0;
                            $huy[$productId] += $sl['amount'];
                        }
                    } 
                    $slkt[$value['products']] = $nhap[$value['products']]-$xuat[$value['products']]-$chuyen[$value['products']]-$huy[$value['products']];
                    $insert = [
                        "date" 			=> $inventory_table['date'],
                        "name_product"	=> $product["name"],
                        "code_product"	=> $product["code"],
                        "amount_product"=> $slkt[$value['products']],
                        "price_product"	=> $product["price"],
                        "amount" 		=> $app->xss(str_replace([','],'',$value['amount'])),
                        "price" 		=> $product["price"],
                        "branch"		=> $product["branch"],
                        "stores"		=> $product["stores"],
                        "notes" 		=> $app->xss($inventory_table['content']),
                        "date_poster" 	=> date('Y-m-d H:i:s'),
                        "products"		=> $app->xss($value['products']),
                        "user"			=> $app->getSession("accounts")['id'] ?? 0,
                    ];
                    $app->insert("inventory_table",$insert);
                    $insert_logs[] = $insert;
                }
                $jatbi->logs('inventory_table','add',$insert_logs);
                unset($_SESSION['inventory_table'][$action]);
                echo json_encode(['status'=>'success','content'=> "Cập nhật thành công"]);
            }
            else {
                echo json_encode(['status'=>'error','content'=>$error['content']]);
            }
        }
    });

    $app->router("/handle/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $stores, $accStore) {
        $vars['title'] = $jatbi->lang("Xử lý");
        $inventory_table = $app->get("inventory_table", "*",["id"=>$app->xss($vars['id'])]);
        if ($app->method() === 'GET') {
            $vars['inventory_table'] = $inventory_table;
            echo $app->render($setting['template'] . '/accountants/inventory_table-post.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);
            if(empty($_POST['type'])){
                echo json_encode(['status'=>'error','content'=> "Lỗi trống"]);
            }				
            else if($_POST['type']) {
                if($_POST['type']=='import'){
                    $code = 'PN';
                    $amounts = $inventory_table['amount']-$inventory_table['amount_product'];
                    $amount = $app->xss(str_replace(['-'],'',$amounts));
                    $inventory_table_amounts = $inventory_table['amount_product']+$amount;
                    $inventory_table_amount = $app->xss(str_replace(['-'],'',$inventory_table_amounts));
                    $receive_status = 1;
                }else{
                    $code = 'PX';
                    $amounts = $inventory_table['amount_product']-$inventory_table['amount'];
                    $amount = $app->xss(str_replace(['-'],'',$amounts));
                    $inventory_table_amounts = $inventory_table['amount_product']-$amount;
                    $inventory_table_amount = $app->xss(str_replace(['-'],'',$inventory_table_amounts));
                    $receive_status = 3;
                }
                $app->update("inventory_table",["amount_product"=>$inventory_table_amount],["id"=>$inventory_table["id"]]);
                $insert = [
                    "code"			=> $code,
                    "type"			=> $app->xss($_POST['type']),
                    "data"			=> "products",
                    "stores"		=> $inventory_table['stores'],
                    "branch"		=> $inventory_table['branch'],
                    "content"		=> $app->xss($_POST['notes']),
                    "user"			=> $app->getSession("accounts")['id'] ?? 0,
                    "date"			=> date("Y-m-d"),
                    "active"		=> $jatbi->active(30),
                    "date_poster"	=> date("Y-m-d H:i:s"),
                    "receive_status"=> $receive_status,
                ];
                $app->insert("warehouses",$insert);
                $orderId = $app->id();
                $pro = [
                        "warehouses" 	=> $orderId,
                        "data"			=> $insert['data'],
                        "type"			=> $insert['type'],
                        "products"		=> $inventory_table['products'],
                        "amount"		=> $amount,
                        "amount_total"	=> $amount,
                        "price"			=> $inventory_table['price'],
                        "notes"			=> $insert['content'],
                        "date"			=> date("Y-m-d H:i:s"),
                        "user"			=> $app->getSession("accounts")['id'] ?? 0,
                        "stores"		=> $insert['stores'],
                        "branch"		=> $insert['branch'],
                    ];
                $app->insert("warehouses_details",$pro);
                $GetID = $app->id();
                $warehouses_logs = [
                        "type"		=> $insert['type'],
                        "data"		=> $insert['data'],
                        "warehouses"=> $orderId,
                        "details"	=> $GetID,
                        "products"	=> $inventory_table['products'],
                        "price"		=> $inventory_table['price'],
                        "amount"	=> $pro['amount'],
                        "total"		=> $pro['amount']*$inventory_table['price'],
                        "notes"		=> $pro['notes'],
                        "date" 		=> date('Y-m-d H:i:s'),
                        "user" 		=> $app->getSession("accounts")['id'] ?? 0,
                        "stores"	=> $insert['stores'],
                        "branch"	=> $insert['branch'],
                    ];
                $app->insert("warehouses_logs",$warehouses_logs);
                $product = $app->get("products",["id","amount"],["id"=>$inventory_table["products"]]);
                if($_POST['type']=='import'){
                    $amount2 = $product['amount']+$warehouses_logs['amount'];
                }else{
                    $amount2 = $product['amount']-$warehouses_logs['amount'];
                }
                $app->update("products",["amount"=>$amount2],["id"=>$product["id"]]);
                echo json_encode(['status'=>'success','content'=> "Cập nhật thành công"]);
            }
        }
    })->setPermissions(['inventory_table.add']);
});




$app->router("/customers/customers-search", ['POST'], function ($vars) use ($app, $jatbi, $setting) {
    $app->header(['Content-Type' => 'application/json; charset=UTF-8']);
    $search = isset($_POST['search']) ? $app->xss($_POST['search']) : '';
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $perPage = $setting['site_page'] ?? 10;

    $where = [
        "AND" => [
            "customers.deleted" => 0,
            "customers.status" => 'A'
        ]
    ];

    if ($search) {
        $where['AND']['OR'] = [
            "customers.name[~]" => $search,
            "customers.code[~]" => $search,
            "customers.phone[~]" => $search,
            "customers.email[~]" => $search,
        ];
    }

    $offset = ($page - 1) * $perPage;
    $where['LIMIT'] = [$offset, $perPage];

    $total = $app->count("customers", ["AND" => $where['AND']]);
    $customers = $app->select("customers", ["id", "name", "code", "phone"], $where);

    $items = [];
    foreach ($customers as $customer) {
        $items[] = [
            'id'   => $customer['id'],
            'name' => $customer['name'],
            'code' => $customer['code'],
            'phone' => $customer['phone']
        ];
    }

    // Trả về mảng items trực tiếp
    echo json_encode($items, JSON_UNESCAPED_UNICODE);
});