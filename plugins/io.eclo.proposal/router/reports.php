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

        $app->router("/statistics", 'GET', function($vars) use ($app, $jatbi, $template,$account,$common) {
            $vars['title'] = $jatbi->lang("Thống kê");
            $where = [
                "proposals.deleted" => 0,
                "proposals.status" => [2,4,5]
            ];
            if (!empty($_GET['date'])) {
                $dates = explode(" - ", $_GET['date']);
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
            } else {
                // Mặc định 1 năm gần nhất
                $from = new DateTime("-1 year");
                $to   = new DateTime("now");
                $where["proposals.date[<>]"] = [
                    $from->format("Y-m-d 00:00:00"),
                    $to->format("Y-m-d 23:59:59")
                ];
            }
            $summary = $app->get("proposals", [
                "tongdexuat"      => App::raw("COUNT(CASE WHEN status IN (1,2,3,4,5,10,20) AND deleted = 0 THEN id ELSE NULL END)"),
                "tongdaduyet"     => App::raw("COUNT(CASE WHEN status IN (2,4,5) AND deleted = 0 THEN id ELSE NULL END)"),
                "tongchi"         => App::raw("SUM(CASE WHEN status IN (1,10,2,4,5) AND type = 2 AND deleted = 0 THEN price ELSE 0 END)"),
                "tongthu"         => App::raw("SUM(CASE WHEN status IN (1,10,2,4,5) AND type = 1 AND deleted = 0 THEN price ELSE 0 END)"),
                "duyetchi"         => App::raw("SUM(CASE WHEN status IN (2,4,5) AND type = 2 AND deleted = 0 THEN price ELSE 0 END)"),
                "duyetthu"         => App::raw("SUM(CASE WHEN status IN (2,4,5) AND type = 1 AND deleted = 0 THEN price ELSE 0 END)"),
                "tong-0"      => App::raw("COUNT(CASE WHEN status = 0 AND deleted = 0 AND account = ".$account['id']." THEN id ELSE NULL END)"),
                "tong-1"      => App::raw("COUNT(CASE WHEN status = 1 AND deleted = 0 THEN id ELSE NULL END)"),
                "tong-2"      => App::raw("COUNT(CASE WHEN status = 2 AND deleted = 0 THEN id ELSE NULL END)"),
                "tong-3"      => App::raw("COUNT(CASE WHEN status = 3 AND deleted = 0 THEN id ELSE NULL END)"),
                "tong-4"      => App::raw("COUNT(CASE WHEN status = 4 AND deleted = 0 THEN id ELSE NULL END)"),
                "tong-10"      => App::raw("COUNT(CASE WHEN status = 10 AND deleted = 0 THEN id ELSE NULL END)"),
                "tong-20"      => App::raw("COUNT(CASE WHEN status = 20 AND deleted = 0 THEN id ELSE NULL END)"),
                "tongsothu"      => App::raw("COUNT(CASE WHEN status IN (1,2,3,4,5,10,20) AND type = 1 AND deleted = 0 THEN id ELSE NULL END)"),
                "tongsochi"      => App::raw("COUNT(CASE WHEN status IN (1,2,3,4,5,10,20) AND type = 2 AND deleted = 0 THEN id ELSE NULL END)"),
                "tongsokhac"      => App::raw("COUNT(CASE WHEN status IN (1,2,3,4,5,10,20) AND type = 3 AND deleted = 0 THEN id ELSE NULL END)"),
                "over_5"  => App::raw("COUNT(CASE WHEN status IN (1,2,3,4,5,10,20) AND deleted = 0 AND DATEDIFF(NOW(), proposals.date) >= 5  THEN id END)"),
                "over_10" => App::raw("COUNT(CASE WHEN status IN (1,2,3,4,5,10,20) AND deleted = 0 AND DATEDIFF(NOW(), proposals.date) >= 10 THEN id END)"),
                "over_15" => App::raw("COUNT(CASE WHEN status IN (1,2,3,4,5,10,20) AND deleted = 0 AND DATEDIFF(NOW(), proposals.date) >= 15 THEN id END)"),
                "over_20" => App::raw("COUNT(CASE WHEN status IN (1,2,3,4,5,10,20) AND deleted = 0 AND DATEDIFF(NOW(), proposals.date) >= 20 THEN id END)"),
                "over_25" => App::raw("COUNT(CASE WHEN status IN (1,2,3,4,5,10,20) AND deleted = 0 AND DATEDIFF(NOW(), proposals.date) >= 25 THEN id END)"),
                "over_30" => App::raw("COUNT(CASE WHEN status IN (1,2,3,4,5,10,20) AND deleted = 0 AND DATEDIFF(NOW(), proposals.date) >= 30 THEN id END)")
            ], $where);
            $vars['hinhthuc'] = $app->select("proposals", [
                "[>]proposal_form" => ["proposals.form" => "id"]
            ], [
                "proposals.id",
                "proposal_form.name",
                "count" => App::raw("COUNT(proposals.id)"),
                "total" => App::raw("SUM(proposals.price)"),
            ], array_merge($where, [
                "GROUP" => "proposals.form",
                "ORDER" => ["count" => "DESC"],
                "LIMIT" => 5
            ]));
            $vars['hangmuc'] = $app->select("proposals", [
                "[>]proposal_target" => ["proposals.category" => "id"]
            ], [
                "proposals.id",
                "proposal_target.name",
                "count" => App::raw("COUNT(proposals.id)"),
                "total" => App::raw("SUM(proposals.price)"),
            ],array_merge($where, [
                "GROUP" => "proposals.category",
                "ORDER" => ["count" => "DESC"],
                "LIMIT" => 5
            ]));
            $vars['quytrinh'] = $app->select("proposals", [
                "[>]proposal_workflows" => ["proposals.workflows" => "id"]
            ], [
                "proposals.id",
                "proposal_workflows.name",
                "count" => App::raw("COUNT(proposals.id)"),
                "total" => App::raw("SUM(proposals.price)"),
            ], array_merge($where, [
                "GROUP" => "proposals.workflows",
                "ORDER" => ["count" => "DESC"],
                "LIMIT" => 5
            ]));
            $vars['taikhoan'] = $app->select("proposals", [
                "[>]accounts" => ["proposals.account" => "id"]
            ], [
                "proposals.id",
                "accounts.name",
                "count"      => App::raw("COUNT(proposals.id)"),
                "total_thu"  => App::raw("SUM(CASE WHEN proposals.type = 1 THEN proposals.price ELSE 0 END)"),
                "total_chi"  => App::raw("SUM(CASE WHEN proposals.type = 2 THEN proposals.price ELSE 0 END)"),
                "total_khac" => App::raw("SUM(CASE WHEN proposals.type = 3 THEN proposals.price ELSE 0 END)"),
                "total"      => App::raw("SUM(proposals.price)"),
            ],array_merge($where, [
                "GROUP" => "proposals.account",
                "ORDER" => ["count" => "DESC"],
                "LIMIT" => 5
            ]));
            $vars['doituongtaikhoan'] = $app->select("proposals", [
                "[>]accounts" => ["proposals.accounts" => "id"]
            ], [
                "proposals.id",
                "accounts.name",
                "count"      => App::raw("COUNT(proposals.id)"),
                "total_thu"  => App::raw("SUM(CASE WHEN proposals.type = 1 THEN proposals.price ELSE 0 END)"),
                "total_chi"  => App::raw("SUM(CASE WHEN proposals.type = 2 THEN proposals.price ELSE 0 END)"),
                "total_khac" => App::raw("SUM(CASE WHEN proposals.type = 3 THEN proposals.price ELSE 0 END)"),
                "total"      => App::raw("SUM(proposals.price)"),
            ], array_merge($where, [
                "GROUP" => "proposals.account",
                "ORDER" => ["count" => "DESC"],
                "LIMIT" => 5,
                "accounts.name[!]" => "",
            ]));

            $vars['doituongkhachhang'] = $app->select("proposals", [
                "[>]customers" => ["proposals.customers" => "id"]
            ], [
                "proposals.id",
                "customers.name",
                "count"      => App::raw("COUNT(proposals.id)"),
                "total_thu"  => App::raw("SUM(CASE WHEN proposals.type = 1 THEN proposals.price ELSE 0 END)"),
                "total_chi"  => App::raw("SUM(CASE WHEN proposals.type = 2 THEN proposals.price ELSE 0 END)"),
                "total_khac" => App::raw("SUM(CASE WHEN proposals.type = 3 THEN proposals.price ELSE 0 END)"),
                "total"      => App::raw("SUM(proposals.price)"),
            ],array_merge($where, [
                "GROUP" => "proposals.customers",
                "ORDER" => ["count" => "DESC"],
                "LIMIT" => 5,
                "customers.name[!]" => "",
            ]));

            $vars['doituongncc'] = $app->select("proposals", [
                "[>]customers" => ["proposals.vendors" => "id"]
            ], [
                "proposals.id",
                "customers.name",
                "count"      => App::raw("COUNT(proposals.id)"),
                "total_thu"  => App::raw("SUM(CASE WHEN proposals.type = 1 THEN proposals.price ELSE 0 END)"),
                "total_chi"  => App::raw("SUM(CASE WHEN proposals.type = 2 THEN proposals.price ELSE 0 END)"),
                "total_khac" => App::raw("SUM(CASE WHEN proposals.type = 3 THEN proposals.price ELSE 0 END)"),
                "total"      => App::raw("SUM(proposals.price)"),
            ],array_merge($where, [
                "GROUP" => "proposals.vendors",
                "ORDER" => ["count" => "DESC"],
                "LIMIT" => 5,
                "customers.name[!]" => "",
            ]));

            $vars['doituongchinhanh'] = $app->select("proposals", [
                "[>]brands" => ["proposals.brands" => "id"]
            ], [
                "proposals.id",
                "brands.name",
                // "name" => App::raw("CASE 
                //    WHEN customers.name IS NULL OR customers.name = '' 
                //    THEN 'Không xác định' 
                //    ELSE customers.name 
                //  END"),
                "count"      => App::raw("COUNT(proposals.id)"),
                "total_thu"  => App::raw("SUM(CASE WHEN proposals.type = 1 THEN proposals.price ELSE 0 END)"),
                "total_chi"  => App::raw("SUM(CASE WHEN proposals.type = 2 THEN proposals.price ELSE 0 END)"),
                "total_khac" => App::raw("SUM(CASE WHEN proposals.type = 3 THEN proposals.price ELSE 0 END)"),
                "total"      => App::raw("SUM(proposals.price)"),
            ],array_merge($where, [
                "GROUP" => "proposals.brands",
                "ORDER" => ["count" => "DESC"],
                "LIMIT" => 5,
                "brands.name[!]" => "",
            ]));

            $vars['total'] = $summary;
            $vars['proposal_status'] = $common['proposal-status'];
            echo $app->render($template.'/proposal/statistics.html', $vars);
        })->setPermissions(['proposal']);

        $app->router("/cash-flow", ['GET','POST'], function($vars) use ($app, $jatbi, $template) {
            $vars['title'] = $jatbi->lang("Dòng tiền đề xuất");

            // 1. XÁC ĐỊNH KHUNG NGÀY XEM
            if (!empty($_GET['date'])) {
                [$from, $to] = explode(" - ", $_GET['date']);
                $startDate = DateTime::createFromFormat("d/m/Y", trim($from))->format("Y-m-d");
                $endDate   = DateTime::createFromFormat("d/m/Y", trim($to))->format("Y-m-d");
            } else {
                $today     = new DateTime();
                $startDate = (clone $today)->modify("-1 day")->format("Y-m-d");
                $endDate   = (clone $today)->modify("+2 day")->format("Y-m-d");
            }

            // 2. KHỞI TẠO CẤU TRÚC DỮ LIỆU DÒNG TIỀN (Làm trước)
            $period = new DatePeriod(
                new DateTime($startDate),
                new DateInterval("P1D"),
                (new DateTime($endDate))->modify("+1 day")
            );
            $dongtien = [];
            foreach ($period as $date) {
                $key = $date->format("Y-m-d");
                $dongtien[$key] = [
                    "dauky"  => ["kehoach"=>0, "thucthu"=>0, "tong"=>0, "tile"=>0],
                    "thu"    => ["kehoach"=>0, "thucthu"=>0, "tong"=>0, "tile"=>0],
                    "chi"    => ["kehoach"=>0, "thucthu"=>0, "tong"=>0, "tile"=>0],
                    "cuoiky" => ["kehoach"=>0, "thucthu"=>0, "tong"=>0, "tile"=>0],
                ];
            }

            // 3. TỔNG HỢP DỮ LIỆU KẾ HOẠCH (TỪ PROPOSALS)
            // Lấy tất cả proposals đã duyệt (status 2 hoặc 4) có *ngày kế hoạch* nằm trong khoảng
            $plannedData = $app->select("proposals", [
                "date", "type", "price"
            ], [
                "deleted" => 0,
                "date[<>]" => [$startDate, $endDate], // Lọc theo ngày của proposal
                "brands_id" => $jatbi->brands(),
                "status" => [2, 4] // 2 = Đã duyệt, 4 = Đã hoàn thành (cũng là đã duyệt)
            ]);

            foreach ($plannedData as $row) {
                $day = $row['date']; // Ngày kế hoạch
                if (!isset($dongtien[$day])) continue; // Bỏ qua nếu ngày nằm ngoài cấu trúc

                if ($row['type'] == 1) { // Thu kế hoạch
                    $dongtien[$day]['thu']['kehoach'] += $row['price'];
                } elseif ($row['type'] == 2) { // Chi kế hoạch
                    $dongtien[$day]['chi']['kehoach'] += $row['price'];
                }
            }

            // 4. TỔNG HỢP DỮ LIỆU THỰC TẾ (TỪ PROPOSALS_REALITY)
            // **LƯU Ý QUAN TRỌNG**:
            // Code này giả định bảng 'proposals_reality' có cột 'date' chứa ngày thực thu/chi.
            // Nếu tên cột là khác (ví dụ: 'payment_date' hoặc 'created_at'),
            // HÃY THAY THẾ 'pr.date' trong câu query bên dưới.

            $actualData = $app->select("proposals_reality(pr)", [
                "[>]proposals(p)" => ["proposal" => "id"] // JOIN với proposals
            ], [
                "pr.reality_date",         // Ngày THỰC TẾ từ bảng reality
                "pr.reality",      // Số tiền THỰC TẾ
                "p.type"           // Loại (thu/chi) từ bảng proposal
            ], [
                "pr.deleted" => 0,
                "p.deleted" => 0,
                "pr.reality_date[<>]" => [$startDate, $endDate], // Lọc theo ngày THỰC TẾ
                "p.brands_id" => $jatbi->brands(),
                "p.status" => 4 // Chỉ lấy thực tế của các proposals 'Đã hoàn thành' (theo logic code gốc)
            ]);

            foreach ($actualData as $row) {
                // Ngày thực tế (cần format về Y-m-d nếu nó là datetime)
                $day = date("Y-m-d", strtotime($row['reality_date'])); 
                
                if (!isset($dongtien[$day])) continue; // Ngày thực tế này nằm ngoài khoảng đang xem

                if ($row['type'] == 1) { // Thu thực tế
                    $dongtien[$day]['thu']['thucthu'] += $row['reality'];
                } elseif ($row['type'] == 2) { // Chi thực tế
                    $dongtien[$day]['chi']['thucthu'] += $row['reality'];
                }
            }

            // 5. TÍNH TOÁN DÒNG TIỀN - ĐÃ CẬP NHẬT LOGIC CỘT "TỔNG"
            // (Phần này giữ nguyên logic tính toán của bạn)
            $lastCuoikyKehoach = 0;
            $lastCuoikyThucthu = 0;
            foreach ($dongtien as &$row) {
                // --- A. TÍNH TOÁN ĐẦU KỲ ---
                $row['dauky']['kehoach'] = $lastCuoikyKehoach;
                $row['dauky']['thucthu'] = $lastCuoikyThucthu;
                $row['dauky']['tong']    = $row['dauky']['kehoach'] - $row['dauky']['thucthu'];
                $row['dauky']['tile']    = $row['dauky']['kehoach'] != 0 ? round(($row['dauky']['thucthu'] / $row['dauky']['kehoach']) * 100) : 0;

                // --- B. TÍNH TOÁN TỔNG VÀ TỶ LỆ CHO THU/CHI ---
                $row['thu']['tong'] = $row['thu']['kehoach'] - $row['thu']['thucthu'];
                $row['chi']['tong'] = $row['chi']['kehoach'] - $row['chi']['thucthu'];
                $row['thu']['tile'] = $row['thu']['kehoach'] > 0 ? round(($row['thu']['thucthu'] / $row['thu']['kehoach']) * 100) : 0;
                $row['chi']['tile'] = $row['chi']['kehoach'] > 0 ? round(($row['chi']['thucthu'] / $row['chi']['kehoach']) * 100) : 0;

                // --- C. TÍNH TOÁN CUỐI KỲ ---
                $cuoikyKehoach = $row['dauky']['kehoach'] + $row['thu']['kehoach'] - $row['chi']['kehoach'];
                $cuoikyThucthu = $row['dauky']['thucthu'] + $row['thu']['thucthu'] - $row['chi']['thucthu'];

                $row['cuoiky']['kehoach'] = $cuoikyKehoach;
                $row['cuoiky']['thucthu'] = $cuoikyThucthu;
                $row['cuoiky']['tong']    = $cuoikyKehoach - $cuoikyThucthu;
                $row['cuoiky']['tile']    = $cuoikyKehoach != 0 ? round(($cuoikyThucthu / $cuoikyKehoach) * 100) : 0;

                // --- D. CẬP NHẬT SỐ DƯ CHO NGÀY TIẾP THEO ---
                $lastCuoikyKehoach = $cuoikyKehoach;
                $lastCuoikyThucthu = $cuoikyThucthu;
            }
            unset($row);

            // 6. CHUẨN BỊ DỮ LIỆU CHO BIỂU ĐỒ (CHART)
            // (Phần này giữ nguyên)
            $chartLabels = [];
            $chartThuKehoachData = [];
            $chartThuThucthuData = [];
            $chartChiKehoachData = [];
            $chartChiThucthuData = [];
            $chartCuoikyData = [];
            foreach ($dongtien as $date => $data) {
                $chartLabels[] = date("d M", strtotime($date));
                $chartThuKehoachData[] = $data['thu']['kehoach'];
                $chartThuThucthuData[] = $data['thu']['thucthu'];
                $chartChiKehoachData[] = $data['chi']['kehoach'];
                $chartChiThucthuData[] = $data['chi']['thucthu'];
                $chartCuoikyData[] = $data['cuoiky']['thucthu'];
            }
            $vars['chartData'] = [
                'labels'        => $chartLabels,
                'thu_kehoach'   => $chartThuKehoachData,
                'thu_thucthu'   => $chartThuThucthuData,
                'chi_kehoach'   => $chartChiKehoachData,
                'chi_thucthu'   => $chartChiThucthuData,
                'cuoiky'        => $chartCuoikyData,
            ];

            // 7. TRUYỀN DỮ LIỆU RA VIEW
            // (Phần này giữ nguyên)
            $vars['dongtien'] = $dongtien;
            echo $app->render($template.'/proposal/cash-flow.html', $vars);
        })->setPermissions(['proposal']);

        $app->router("/cash", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$account,$common) {
            if($app->method()==='GET'){
                $vars['title'] = $jatbi->lang("Số tổng hợp");
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
                echo $app->render($template.'/proposal/cash-bank.html', $vars);
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
                $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'ASC';
                $brands = isset($_POST['brands']) ? $_POST['brands'] : $jatbi->brands();
                $category = isset($_POST['category']) ? $_POST['category'] : '';
                $type = isset($_POST['type']) ? $_POST['type'] : '';
                $workflows = isset($_POST['workflows']) ? $_POST['workflows'] : '';
                $form = isset($_POST['form']) ? $_POST['form'] : '';
                $Searchaccount = isset($_POST['account']) ? $_POST['account'] : '';
                $where = [
                    "AND" => [
                        "OR" => [
                            "proposals.content[~]" => $searchValue,
                            "proposals.code[~]" => $searchValue,
                        ],
                        "proposals.status" => 4,
                        "proposals.deleted" => 0,
                        "proposal_form.form" => 1,
                        "proposals_reality.deleted" => 0,
                    ],
                    "LIMIT" => [$start, $length],
                    "ORDER" => [$orderName => strtoupper($orderDir)],
                ];
                
                if (!empty($brands)) {
                    $where["AND"]["proposals.brands_id"] = $brands;
                }
                if ($jatbi->permission(['proposal.full']) != 'true') {
                    $where["AND"]["OR"] = [
                        "proposal_accounts.account" => $account['id'],
                        "AND" => [
                            "proposals.account" => $account['id'],
                            // "proposals.status" => 0
                        ]
                    ];
                }
                // // $where["GROUP"] = "proposals.id";
                if (!empty($_POST['date'])) {
                    $dates = explode(" - ", $_POST['date']);
                    if (count($dates) == 2) {
                        $from = DateTime::createFromFormat('d/m/Y', trim($dates[0]));
                        $to   = DateTime::createFromFormat('d/m/Y', trim($dates[1]));
                        if ($from && $to) {
                            $where["proposals_reality.reality_date[<>]"] = [
                                $from->format("Y-m-d 00:00:00"),
                                $to->format("Y-m-d 23:59:59")
                            ];
                        }
                    } else {
                        $date = DateTime::createFromFormat('d/m/Y', trim($dates[0]));
                        if ($date) {
                            $where["proposals_reality.reality_date[<>]"] = [
                                $date->format("Y-m-d 00:00:00"),
                                $date->format("Y-m-d 23:59:59")
                            ];
                        }
                    }
                } 
                else {
                    $from = new DateTime("first day of this month 00:00:00");
                    $to   = new DateTime("now");
                    $where["proposals_reality.reality_date[<>]"] = [
                        $from->format("Y-m-d 00:00:00"),
                        $to->format("Y-m-d 23:59:59")
                    ];
                }
                $from_date_sql = $from->format("Y-m-d 00:00:00");
                $to_date_sql = $to->format("Y-m-d 23:59:59");
                if (!empty($type)) {
                    $where["AND"]["proposals.type"] = $type;
                }
                if (!empty($Searchaccount)) {
                    $where["AND"]["proposals.account"] = $Searchaccount;
                }
                if (!empty($form)) {
                    $where["AND"]["proposals.form"] = $form;
                }
                if (!empty($category)) {
                    $where["AND"]["proposals.category"] = $category;
                }
                if (!empty($brands)) {
                    $where["AND"]["proposals.brands_id"] = $brands;
                }
                $joins = [
                    "[>]proposals"=>["proposal"=>"id"],
                    "[>]accounts"=>["proposals.account"=>"id"],
                    "[>]proposal_form"=>["proposals_reality.form"=>"id"],
                    "[>]proposal_target"=>["proposals_reality.category"=>"id"],
                ];
                $count = $app->count("proposals_reality",$joins,[
                    "proposals_reality.id"
                ],[
                    "AND" => $where['AND'],
                ]);
                $summary = $app->get("proposals_reality",$joins,[
                    "header_thu"         => App::raw("SUM(CASE WHEN proposals_reality.reality_date < '$from_date_sql' AND proposals.type = 1 AND proposals_reality.deleted = 0 THEN proposals_reality.reality ELSE 0 END)"),
                    "header_chi"         => App::raw("SUM(CASE WHEN proposals_reality.reality_date < '$from_date_sql' AND proposals.type = 2 AND proposals_reality.deleted = 0 THEN proposals_reality.reality ELSE 0 END)"),

                    "period_thu"         => App::raw("SUM(CASE WHEN proposals_reality.reality_date >= '$from_date_sql' AND proposals_reality.reality_date <='$to_date_sql' AND proposals.type = 1 AND proposals_reality.deleted = 0  THEN proposals_reality.reality ELSE 0 END)"),
                    "period_chi"         => App::raw("SUM(CASE WHEN proposals_reality.reality_date >= '$from_date_sql' AND proposals_reality.reality_date <='$to_date_sql' AND proposals.type = 2 AND proposals_reality.deleted = 0  THEN proposals_reality.reality ELSE 0 END)"),
                ],$where['AND']);

                $header_thu = $summary['header_thu'] ?? 0;
                $header_chi = $summary['header_chi'] ?? 0;
                $header_ton_numeric = $header_thu - $header_chi;
                
                $headerData = [
                    "thu" => $jatbi->money($header_thu),
                    "chi" => $jatbi->money($header_chi),
                    "ton" => $jatbi->money($header_ton_numeric)
                ];

                $footer_thu_period = $summary['period_thu'] ?? 0;
                $footer_chi_period = $summary['period_chi'] ?? 0;
                $footer_ton_numeric = $header_ton_numeric + $footer_thu_period - $footer_chi_period;

                $footerData = [
                    "thu" => $jatbi->money($footer_thu_period),
                    "chi" => $jatbi->money($footer_chi_period),
                    "ton" => $jatbi->money($footer_ton_numeric)
                ];


                $page_running_total = $header_ton_numeric;
                $is_date_asc_sort = ($orderName == 'date' && strtoupper($orderDir) == 'ASC');

                $app->select("proposals_reality",$joins,[
                    "proposals.id",
                    "proposals.content",
                    "proposals.type",
                    "proposals.active",
                    "proposals_reality.form",
                    "proposals_reality.category",
                    "proposals_reality.reality",
                    "proposals_reality.reality_date (date)",
                    "accounts.name (accounts)",
                    "proposal_target.name (category)",
                    "proposal_form.name (form)",
                ], $where, function ($data) use (&$datas, $jatbi,$app,$common,$account,&$page_running_total, $is_date_asc_sort) {
                    $thu_val = ($data['type'] == 1) ? $data['reality'] : 0;
                    $chi_val = ($data['type'] == 2) ? $data['reality'] : 0;
                    $ton = '...';

                    $type = $common['proposal'][$data['type']];
                    // $status = $common['proposal-status'][$data['status']];

                    if ($is_date_asc_sort) {
                        $page_running_total += ($thu_val - $chi_val);
                        $ton = $jatbi->money($page_running_total);
                    }

                    $datas[] = [
                        "content" => $data['content'] ?? '',
                        "form" => $data['form'] ?? '',
                        "category" => $data['category'] ?? '',
                        "account" => $data['accounts'] ?? '',
                        "thu" => $jatbi->money($thu_val),
                        "chi" => $jatbi->money($chi_val),
                        "ton" => $ton,
                        "date" => '<strong>'.$jatbi->date($data['date']).'</strong>',
                        "code" => '<a href="/proposal/views/'.$data['active'].'" data-pjax>#'.($data['id'] ?? '').'</a>',
                        "accounts" => $avatar ?? '',
                    ];
                });
                echo json_encode([
                    "draw" => $draw,
                    "recordsTotal" => $count ?? 0,
                    "recordsFiltered" => $count ?? 0,
                    "data" => $datas ?? [],
                    "footerData"=> $footerData ?? [],
                    "headerData"=> $headerData ?? [],
                ]);
            }
        })->setPermissions(['proposal.cash']);

        $app->router("/bank", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$account,$common) {
            if($app->method()==='GET'){
                $vars['title'] = $jatbi->lang("Số tổng hợp");
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
                echo $app->render($template.'/proposal/cash-bank.html', $vars);
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
                $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'ASC';
                $brands = isset($_POST['brands']) ? $_POST['brands'] : $jatbi->brands();
                $category = isset($_POST['category']) ? $_POST['category'] : '';
                $type = isset($_POST['type']) ? $_POST['type'] : '';
                $workflows = isset($_POST['workflows']) ? $_POST['workflows'] : '';
                $form = isset($_POST['form']) ? $_POST['form'] : '';
                $Searchaccount = isset($_POST['account']) ? $_POST['account'] : '';
                $where = [
                    "AND" => [
                        "OR" => [
                            "proposals.content[~]" => $searchValue,
                            "proposals.code[~]" => $searchValue,
                        ],
                        "proposals.status" => 4,
                        "proposals.deleted" => 0,
                        "proposal_form.form" => 2,
                        "proposals_reality.deleted" => 0,
                    ],
                    "LIMIT" => [$start, $length],
                    "ORDER" => [$orderName => strtoupper($orderDir)],
                ];
                
                if (!empty($brands)) {
                    $where["AND"]["proposals.brands_id"] = $brands;
                }
                if ($jatbi->permission(['proposal.full']) != 'true') {
                    $where["AND"]["OR"] = [
                        "proposal_accounts.account" => $account['id'],
                        "AND" => [
                            "proposals.account" => $account['id'],
                            // "proposals.status" => 0
                        ]
                    ];
                }
                // // $where["GROUP"] = "proposals.id";
                if (!empty($_POST['date'])) {
                    $dates = explode(" - ", $_POST['date']);
                    if (count($dates) == 2) {
                        $from = DateTime::createFromFormat('d/m/Y', trim($dates[0]));
                        $to   = DateTime::createFromFormat('d/m/Y', trim($dates[1]));
                        if ($from && $to) {
                            $where["proposals_reality.reality_date[<>]"] = [
                                $from->format("Y-m-d 00:00:00"),
                                $to->format("Y-m-d 23:59:59")
                            ];
                        }
                    } else {
                        $date = DateTime::createFromFormat('d/m/Y', trim($dates[0]));
                        if ($date) {
                            $where["proposals_reality.reality_date[<>]"] = [
                                $date->format("Y-m-d 00:00:00"),
                                $date->format("Y-m-d 23:59:59")
                            ];
                        }
                    }
                } 
                else {
                    $from = new DateTime("first day of this month 00:00:00");
                    $to   = new DateTime("now");
                    $where["proposals_reality.reality_date[<>]"] = [
                        $from->format("Y-m-d 00:00:00"),
                        $to->format("Y-m-d 23:59:59")
                    ];
                }
                $from_date_sql = $from->format("Y-m-d 00:00:00");
                $to_date_sql = $to->format("Y-m-d 23:59:59");
                if (!empty($type)) {
                    $where["AND"]["proposals.type"] = $type;
                }
                if (!empty($Searchaccount)) {
                    $where["AND"]["proposals.account"] = $Searchaccount;
                }
                if (!empty($form)) {
                    $where["AND"]["proposals.form"] = $form;
                }
                if (!empty($category)) {
                    $where["AND"]["proposals.category"] = $category;
                }
                if (!empty($brands)) {
                    $where["AND"]["proposals.brands_id"] = $brands;
                }
                $joins = [
                    "[>]proposals"=>["proposal"=>"id"],
                    "[>]accounts"=>["proposals.account"=>"id"],
                    "[>]proposal_form"=>["proposals_reality.form"=>"id"],
                    "[>]proposal_target"=>["proposals_reality.category"=>"id"],
                ];
                $count = $app->count("proposals_reality",$joins,[
                    "proposals_reality.id"
                ],[
                    "AND" => $where['AND'],
                ]);
                $summary = $app->get("proposals_reality",$joins,[
                    "header_thu"         => App::raw("SUM(CASE WHEN proposals_reality.reality_date < '$from_date_sql' AND proposals.type = 1 AND proposals_reality.deleted = 0 THEN proposals_reality.reality ELSE 0 END)"),
                    "header_chi"         => App::raw("SUM(CASE WHEN proposals_reality.reality_date < '$from_date_sql' AND proposals.type = 2 AND proposals_reality.deleted = 0 THEN proposals_reality.reality ELSE 0 END)"),

                    "period_thu"         => App::raw("SUM(CASE WHEN proposals_reality.reality_date >= '$from_date_sql' AND proposals_reality.reality_date <='$to_date_sql' AND proposals.type = 1 AND proposals_reality.deleted = 0  THEN proposals_reality.reality ELSE 0 END)"),
                    "period_chi"         => App::raw("SUM(CASE WHEN proposals_reality.reality_date >= '$from_date_sql' AND proposals_reality.reality_date <='$to_date_sql' AND proposals.type = 2 AND proposals_reality.deleted = 0  THEN proposals_reality.reality ELSE 0 END)"),
                ],$where['AND']);

                $header_thu = $summary['header_thu'] ?? 0;
                $header_chi = $summary['header_chi'] ?? 0;
                $header_ton_numeric = $header_thu - $header_chi;
                
                $headerData = [
                    "thu" => $jatbi->money($header_thu),
                    "chi" => $jatbi->money($header_chi),
                    "ton" => $jatbi->money($header_ton_numeric)
                ];

                $footer_thu_period = $summary['period_thu'] ?? 0;
                $footer_chi_period = $summary['period_chi'] ?? 0;
                $footer_ton_numeric = $header_ton_numeric + $footer_thu_period - $footer_chi_period;

                $footerData = [
                    "thu" => $jatbi->money($footer_thu_period),
                    "chi" => $jatbi->money($footer_chi_period),
                    "ton" => $jatbi->money($footer_ton_numeric)
                ];


                $page_running_total = $header_ton_numeric;
                $is_date_asc_sort = ($orderName == 'date' && strtoupper($orderDir) == 'ASC');

                $app->select("proposals_reality",$joins,[
                    "proposals.id",
                    "proposals.content",
                    "proposals.type",
                    "proposals.active",
                    "proposals_reality.form",
                    "proposals_reality.category",
                    "proposals_reality.reality",
                    "proposals_reality.reality_date (date)",
                    "accounts.name (accounts)",
                    "proposal_target.name (category)",
                    "proposal_form.name (form)",
                ], $where, function ($data) use (&$datas, $jatbi,$app,$common,$account,&$page_running_total, $is_date_asc_sort) {
                    $thu_val = ($data['type'] == 1) ? $data['reality'] : 0;
                    $chi_val = ($data['type'] == 2) ? $data['reality'] : 0;
                    $ton = '...';

                    $type = $common['proposal'][$data['type']];
                    // $status = $common['proposal-status'][$data['status']];

                    if ($is_date_asc_sort) {
                        $page_running_total += ($thu_val - $chi_val);
                        $ton = $jatbi->money($page_running_total);
                    }

                    $datas[] = [
                        "content" => $data['content'] ?? '',
                        "form" => $data['form'] ?? '',
                        "category" => $data['category'] ?? '',
                        "account" => $data['accounts'] ?? '',
                        "thu" => $jatbi->money($thu_val),
                        "chi" => $jatbi->money($chi_val),
                        "ton" => $ton,
                        "date" => '<strong>'.$jatbi->date($data['date']).'</strong>',
                        "code" => '<a href="/proposal/views/'.$data['active'].'" data-pjax>#'.($data['id'] ?? '').'</a>',
                        "accounts" => $avatar ?? '',
                    ];
                });
                echo json_encode([
                    "draw" => $draw,
                    "recordsTotal" => $count ?? 0,
                    "recordsFiltered" => $count ?? 0,
                    "data" => $datas ?? [],
                    "footerData"=> $footerData ?? [],
                    "headerData"=> $headerData ?? [],
                ]);
            }
        })->setPermissions(['proposal.cash']);

        $app->router("/cash-bank", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$account,$common) {
            if($app->method()==='GET'){
                $vars['title'] = $jatbi->lang("Số tổng hợp");
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
                echo $app->render($template.'/proposal/cash-bank.html', $vars);
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
                $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'ASC';
                $brands = isset($_POST['brands']) ? $_POST['brands'] : $jatbi->brands();
                $category = isset($_POST['category']) ? $_POST['category'] : '';
                $type = isset($_POST['type']) ? $_POST['type'] : '';
                $workflows = isset($_POST['workflows']) ? $_POST['workflows'] : '';
                $form = isset($_POST['form']) ? $_POST['form'] : '';
                $Searchaccount = isset($_POST['account']) ? $_POST['account'] : '';
                $where = [
                    "AND" => [
                        "OR" => [
                            "proposals.content[~]" => $searchValue,
                            "proposals.code[~]" => $searchValue,
                        ],
                        "proposals.status" => 4,
                        "proposals.deleted" => 0,
                        "proposals_reality.deleted" => 0,
                    ],
                    "LIMIT" => [$start, $length],
                    "ORDER" => [$orderName => strtoupper($orderDir)],
                ];
                
                if (!empty($brands)) {
                    $where["AND"]["proposals.brands_id"] = $brands;
                }
                if ($jatbi->permission(['proposal.full']) != 'true') {
                    $where["AND"]["OR"] = [
                        "proposal_accounts.account" => $account['id'],
                        "AND" => [
                            "proposals.account" => $account['id'],
                            // "proposals.status" => 0
                        ]
                    ];
                }
                // // $where["GROUP"] = "proposals.id";
                if (!empty($_POST['date'])) {
                    $dates = explode(" - ", $_POST['date']);
                    if (count($dates) == 2) {
                        $from = DateTime::createFromFormat('d/m/Y', trim($dates[0]));
                        $to   = DateTime::createFromFormat('d/m/Y', trim($dates[1]));
                        if ($from && $to) {
                            $where["proposals_reality.reality_date[<>]"] = [
                                $from->format("Y-m-d 00:00:00"),
                                $to->format("Y-m-d 23:59:59")
                            ];
                        }
                    } else {
                        $date = DateTime::createFromFormat('d/m/Y', trim($dates[0]));
                        if ($date) {
                            $where["proposals_reality.reality_date[<>]"] = [
                                $date->format("Y-m-d 00:00:00"),
                                $date->format("Y-m-d 23:59:59")
                            ];
                        }
                    }
                } 
                else {
                    $from = new DateTime("first day of this month 00:00:00");
                    $to   = new DateTime("now");
                    $where["proposals_reality.reality_date[<>]"] = [
                        $from->format("Y-m-d 00:00:00"),
                        $to->format("Y-m-d 23:59:59")
                    ];
                }
                $from_date_sql = $from->format("Y-m-d 00:00:00");
                $to_date_sql = $to->format("Y-m-d 23:59:59");
                if (!empty($type)) {
                    $where["AND"]["proposals.type"] = $type;
                }
                if (!empty($Searchaccount)) {
                    $where["AND"]["proposals.account"] = $Searchaccount;
                }
                if (!empty($form)) {
                    $where["AND"]["proposals.form"] = $form;
                }
                if (!empty($category)) {
                    $where["AND"]["proposals.category"] = $category;
                }
                if (!empty($brands)) {
                    $where["AND"]["proposals.brands_id"] = $brands;
                }
                $joins = [
                    "[>]proposals"=>["proposal"=>"id"],
                    "[>]accounts"=>["proposals.account"=>"id"],
                    "[>]proposal_form"=>["proposals_reality.form"=>"id"],
                    "[>]proposal_target"=>["proposals_reality.category"=>"id"],
                ];
                $count = $app->count("proposals_reality",$joins,[
                    "proposals_reality.id"
                ],[
                    "AND" => $where['AND'],
                ]);
                $summary = $app->get("proposals_reality",$joins,[
                    "header_thu"         => App::raw("SUM(CASE WHEN proposals_reality.reality_date < '$from_date_sql' AND proposals.type = 1 AND proposals_reality.deleted = 0 THEN proposals_reality.reality ELSE 0 END)"),
                    "header_chi"         => App::raw("SUM(CASE WHEN proposals_reality.reality_date < '$from_date_sql' AND proposals.type = 2 AND proposals_reality.deleted = 0 THEN proposals_reality.reality ELSE 0 END)"),

                    "period_thu"         => App::raw("SUM(CASE WHEN proposals_reality.reality_date >= '$from_date_sql' AND proposals_reality.reality_date <='$to_date_sql' AND proposals.type = 1 AND proposals_reality.deleted = 0  THEN proposals_reality.reality ELSE 0 END)"),
                    "period_chi"         => App::raw("SUM(CASE WHEN proposals_reality.reality_date >= '$from_date_sql' AND proposals_reality.reality_date <='$to_date_sql' AND proposals.type = 2 AND proposals_reality.deleted = 0  THEN proposals_reality.reality ELSE 0 END)"),
                ],$where['AND']);

                $header_thu = $summary['header_thu'] ?? 0;
                $header_chi = $summary['header_chi'] ?? 0;
                $header_ton_numeric = $header_thu - $header_chi;
                
                $headerData = [
                    "thu" => $jatbi->money($header_thu),
                    "chi" => $jatbi->money($header_chi),
                    "ton" => $jatbi->money($header_ton_numeric)
                ];

                $footer_thu_period = $summary['period_thu'] ?? 0;
                $footer_chi_period = $summary['period_chi'] ?? 0;
                $footer_ton_numeric = $header_ton_numeric + $footer_thu_period - $footer_chi_period;

                $footerData = [
                    "thu" => $jatbi->money($footer_thu_period),
                    "chi" => $jatbi->money($footer_chi_period),
                    "ton" => $jatbi->money($footer_ton_numeric)
                ];


                $page_running_total = $header_ton_numeric;
                $is_date_asc_sort = ($orderName == 'date' && strtoupper($orderDir) == 'ASC');

                $app->select("proposals_reality",$joins,[
                    "proposals.id",
                    "proposals.content",
                    "proposals.type",
                    "proposals.active",
                    "proposals_reality.form",
                    "proposals_reality.category",
                    "proposals_reality.reality",
                    "proposals_reality.reality_date (date)",
                    "accounts.name (accounts)",
                    "proposal_target.name (category)",
                    "proposal_form.name (form)",
                ], $where, function ($data) use (&$datas, $jatbi,$app,$common,$account,&$page_running_total, $is_date_asc_sort) {
                    $thu_val = ($data['type'] == 1) ? $data['reality'] : 0;
                    $chi_val = ($data['type'] == 2) ? $data['reality'] : 0;
                    $ton = '...';

                    $type = $common['proposal'][$data['type']];
                    // $status = $common['proposal-status'][$data['status']];

                    if ($is_date_asc_sort) {
                        $page_running_total += ($thu_val - $chi_val);
                        $ton = $jatbi->money($page_running_total);
                    }

                    $datas[] = [
                        "content" => $data['content'] ?? '',
                        "form" => $data['form'] ?? '',
                        "category" => $data['category'] ?? '',
                        "account" => $data['accounts'] ?? '',
                        "thu" => $jatbi->money($thu_val),
                        "chi" => $jatbi->money($chi_val),
                        "ton" => $ton,
                        "date" => '<strong>'.$jatbi->date($data['date']).'</strong>',
                        "code" => '<a href="/proposal/views/'.$data['active'].'" data-pjax>#'.($data['id'] ?? '').'</a>',
                        "accounts" => $avatar ?? '',
                    ];
                });
                echo json_encode([
                    "draw" => $draw,
                    "recordsTotal" => $count ?? 0,
                    "recordsFiltered" => $count ?? 0,
                    "data" => $datas ?? [],
                    "footerData"=> $footerData ?? [],
                    "headerData"=> $headerData ?? [],
                ]);
            }
        })->setPermissions(['proposal.cash']);

        $app->router("/journal", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$account,$common) {
            if($app->method()==='GET'){
                $vars['title'] = $jatbi->lang("Đối soát định khoản");
                echo $app->render($template.'/proposal/journal.html', $vars);
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
                $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
                $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
                $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
                $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'proposals.id';
                $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

                $where = [
                    "AND" => [
                        "proposals.status" => [2, 4, 5], // Approved, Booked
                        "proposals.deleted" => 0,
                    ],
                    "LIMIT" => [$start, $length],
                    "ORDER" => [$orderName => strtoupper($orderDir)]
                ];

                if (!empty($brands)) {
                    $where["AND"]["proposals.brands_id"] = $brands;
                }
                if (!empty($_POST['date'])) {
                    $dates = explode(" - ", $_POST['date']);
                    if (count($dates) == 2) {
                        $from = DateTime::createFromFormat('d/m/Y', trim($dates[0]));
                        $to   = DateTime::createFromFormat('d/m/Y', trim($dates[1]));
                        if ($from && $to) {
                            $where["AND"]["proposals.date[<>]"] = [
                                $from->format("Y-m-d 00:00:00"),
                                $to->format("Y-m-d 23:59:59")
                            ];
                        }
                    }
                }

                if (!empty($searchValue)) {
                    $where["AND"]["OR"] = [
                        "proposals.content[~]" => $searchValue,
                        "proposals.id[~]" => $searchValue,
                    ];
                }

                $joins = [
                    "[>]proposal_form" => ["form" => "id"],
                    "[>]proposal_target" => ["category" => "id"],
                    "[>]accountants_code(form_ac)" => ["proposal_form.record" => "id"],
                    "[>]accountants_code(target_ac)" => ["proposal_target.record" => "id"],
                ];

                $count = $app->count("proposals", $joins, "proposals.id", ["AND" => $where['AND']]);

                $datas = $app->select("proposals", $joins, [
                    "proposals.id (proposal_id)",
                    "proposals.date",
                    "proposals.content",
                    "proposal_target.name (target)",
                    "proposal_form.name (form)",
                    "proposals.price (amount)",
                    "debit" => App::raw("CASE WHEN proposals.type = 1 THEN form_ac.code WHEN proposals.type = 2 THEN target_ac.code ELSE NULL END"),
                    "credit" => App::raw("CASE WHEN proposals.type = 1 THEN target_ac.code WHEN proposals.type = 2 THEN form_ac.code ELSE NULL END"),
                ], $where);

                $formatted_datas = [];
                foreach($datas as $item) {
                    $formatted_datas[] = [
                        'proposal_id' => '#'.$item['proposal_id'],
                        'date' => $jatbi->date($item['date']),
                        'form' => $item['form'],
                        'target' => $item['target'],
                        'content' => $item['content'],
                        'amount' => $jatbi->money($item['amount']),
                        'debit' => $item['debit'],
                        'credit' => $item['credit'],
                    ];
                }

                echo json_encode([
                    "draw" => $draw,
                    "recordsTotal" => $count,
                    "recordsFiltered" => $count,
                    "data" => $formatted_datas
                ]);
            }
        })->setPermissions(['proposal.journal']);

        $app->router("/payable", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$account,$common) {
            if($app->method()==='GET'){
                $vars['title'] = $jatbi->lang("Phải trả");
                echo $app->render($template.'/proposal/payable.html', $vars);
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
                $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
                $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
                $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
                $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'proposals.id';
                $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
                $brands = isset($_POST['brands']) ? $_POST['brands'] : $jatbi->brands();

                $where = [
                    "AND" => [
                        "proposals.type" => 2, // THU
                        "proposals.status" => [2, 4, 5], // Approved, Booked
                        "proposals.deleted" => 0,
                        "proposals.customers[!]" => null,
                    ],
                    "ORDER" => [$orderName => strtoupper($orderDir)],
                    "LIMIT" => [$start, $length], 
                    "GROUP" => "proposals.id"
                ];

                if (!empty($brands)) {
                    $where["AND"]["proposals.brands_id"] = $brands;
                }
                if (!empty($_POST['date'])) {
                    $dates = explode(" - ", $_POST['date']);
                    if (count($dates) == 2) {
                        $from = DateTime::createFromFormat('d/m/Y', trim($dates[0]));
                        $to   = DateTime::createFromFormat('d/m/Y', trim($dates[1]));
                        if ($from && $to) {
                            $where["AND"]["proposals.date[<>]"] = [
                                $from->format("Y-m-d 00:00:00"),
                                $to->format("Y-m-d 23:59:59")
                            ];
                        }
                    }
                }

                if (!empty($searchValue)) {
                    $where["AND"]["OR"] = [
                        "customers.name[~]" => $searchValue,
                        "proposals.content[~]" => $searchValue,
                        "proposals.id[~]" => $searchValue,
                    ];
                }

                $joins = [
                    "[>]customers" => ["customers" => "id"],
                    "[>]proposals_reality" => ["id" => "proposal"],
                ];

                $count = $app->count("proposals", $joins, "proposals.id", ["AND" => $where['AND']]);

                $proposals = $app->select("proposals", $joins, [
                    "proposals.id (proposal_id)",
                    "proposals.date",
                    "proposals.content",
                    "proposals.price",
                    "reality" => App::raw("SUM(CASE WHEN proposals_reality.deleted = 0 THEN proposals_reality.reality ELSE 0 END)"),
                    "proposals.active",
                    "customers.name (customer_name)",
                ], $where);

                $datas = [];
                
                foreach($proposals as $item) {
                    $remaining = $item['price'] - $item['reality'];
                    $datas[] = [
                        'proposal_id' => '<a class="fw-bold" data-pjax href="/proposal/views/'.$item['active'].'">#'.$item['proposal_id'].'</a>',
                        'customer_name' => $item['customer_name'],
                        'date' => $jatbi->date($item['date']),
                        'content' => $item['content'],
                        'amount' => $jatbi->money($item['price'] ?? 0),
                        'paid' => $jatbi->money($item['reality'] ?? 0),
                        'remaining' => $jatbi->money($remaining ?? 0),
                    ];
                }
                
                $summary_where = $where['AND'];
                unset($summary_where['LIMIT']);
                $summary = $app->get("proposals", $joins, [
                    "total_amount" => App::raw("SUM(proposals.price)"),
                    "total_paid" => App::raw("SUM(CASE WHEN proposals_reality.deleted = 0 THEN proposals_reality.reality ELSE 0 END)"),
                ], ["AND" => $summary_where]);

                $footerData['amount'] = $jatbi->money($summary['total_amount'] ?? 0);
                $footerData['paid'] = $jatbi->money($summary['total_paid'] ?? 0);
                $footerData['remaining'] = $jatbi->money(($summary['total_amount'] - $summary['total_paid']) ?? 0);

                echo json_encode([
                    "draw" => $draw,
                    "recordsTotal" => $count,
                    "recordsFiltered" => $count,
                    "data" => $datas,
                    "footerData" => $footerData
                ]);
            }
        })->setPermissions(['proposal.payable']);

        $app->router("/receivable", ['GET','POST'], function($vars) use ($app, $jatbi, $template,$account,$common) {
            if($app->method()==='GET'){
                $vars['title'] = $jatbi->lang("Phải thu");
                echo $app->render($template.'/proposal/receivable.html', $vars);
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
                $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
                $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
                $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
                $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'proposals.id';
                $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
                $brands = isset($_POST['brands']) ? $_POST['brands'] : $jatbi->brands();

                $where = [
                    "AND" => [
                        "proposals.type" => 1, // THU
                        "proposals.status" => [2, 4, 5], // Approved, Booked
                        "proposals.deleted" => 0,
                        "proposals.customers[!]" => null,
                    ],
                    "ORDER" => [$orderName => strtoupper($orderDir)],
                    "LIMIT" => [$start, $length],
                    "GROUP" => "proposals.id"
                ];
                if (!empty($brands)) {
                    $where["AND"]["proposals.brands_id"] = $brands;
                }
                if (!empty($_POST['date'])) {
                    $dates = explode(" - ", $_POST['date']);
                    if (count($dates) == 2) {
                        $from = DateTime::createFromFormat('d/m/Y', trim($dates[0]));
                        $to   = DateTime::createFromFormat('d/m/Y', trim($dates[1]));
                        if ($from && $to) {
                            $where["AND"]["proposals.date[<>]"] = [
                                $from->format("Y-m-d 00:00:00"),
                                $to->format("Y-m-d 23:59:59")
                            ];
                        }
                    }
                }

                if (!empty($searchValue)) {
                    $where["AND"]["OR"] = [
                        "customers.name[~]" => $searchValue,
                        "proposals.content[~]" => $searchValue,
                        "proposals.id[~]" => $searchValue,
                    ];
                }

                $joins = [
                    "[>]customers" => ["customers" => "id"],
                    "[>]proposals_reality" => ["id" => "proposal"],
                ];

                $count = $app->count("proposals", $joins, "proposals.id", ["AND" => $where['AND']]);

                $proposals = $app->select("proposals", $joins, [
                    "proposals.id (proposal_id)",
                    "proposals.date",
                    "proposals.content",
                    "proposals.price",
                    "reality" => App::raw("SUM(CASE WHEN proposals_reality.deleted = 0 THEN proposals_reality.reality ELSE 0 END)"),
                    "proposals.active",
                    "customers.name (customer_name)",
                ], $where);

                $datas = [];
                
                foreach($proposals as $item) {
                    $remaining = $item['price'] - $item['reality'];
                    $datas[] = [
                        'proposal_id' => '<a class="fw-bold" data-pjax href="/proposal/views/'.$item['active'].'">#'.$item['proposal_id'].'</a>',
                        'customer_name' => $item['customer_name'],
                        'date' => $jatbi->date($item['date']),
                        'content' => $item['content'],
                        'amount' => $jatbi->money($item['price'] ?? 0),
                        'paid' => $jatbi->money($item['reality'] ?? 0),
                        'remaining' => $jatbi->money($remaining ?? 0),
                    ];
                }
                
                $summary_where = $where['AND'];
                unset($summary_where['LIMIT']);
                $summary = $app->get("proposals", $joins, [
                    "total_amount" => App::raw("SUM(proposals.price)"),
                    "total_paid" => App::raw("SUM(CASE WHEN proposals_reality.deleted = 0 THEN proposals_reality.reality ELSE 0 END)"),
                ], ["AND" => $summary_where]);

                $footerData['amount'] = $jatbi->money($summary['total_amount'] ?? 0);
                $footerData['paid'] = $jatbi->money($summary['total_paid'] ?? 0);
                $footerData['remaining'] = $jatbi->money(($summary['total_amount'] - $summary['total_paid']) ?? 0);

                echo json_encode([
                    "draw" => $draw,
                    "recordsTotal" => $count,
                    "recordsFiltered" => $count,
                    "data" => $datas,
                    "footerData" => $footerData
                ]);
            }
        })->setPermissions(['proposal.receivable']);

        // $app->router("/cash-flow", ['GET','POST'], function($vars) use ($app, $jatbi, $template) {
        //     $vars['title'] = $jatbi->lang("Dòng tiền đề xuất");
        //     if (!empty($_GET['date'])) {
        //         [$from, $to] = explode(" - ", $_GET['date']);
        //         $startDate = DateTime::createFromFormat("d/m/Y", trim($from))->format("Y-m-d");
        //         $endDate   = DateTime::createFromFormat("d/m/Y", trim($to))->format("Y-m-d");
        //     } else {
        //         $today     = new DateTime();
        //         $startDate = (clone $today)->modify("-1 day")->format("Y-m-d");
        //         $endDate   = (clone $today)->modify("+2 day")->format("Y-m-d");
        //     }
        //     // 2. LẤY DỮ LIỆU THẬT TỪ DATABASE
        //     $data = $app->select("proposals", [
        //         "date", "type", "price", "status","id"
        //     ], [
        //         "deleted" => 0,
        //         "date[<>]" => [$startDate, $endDate],
        //         "brands_id" => $jatbi->brands(),
        //     ]);
        //     // 3. KHỞI TẠO CẤU TRÚC DỮ LIỆU DÒNG TIỀN
        //     $period = new DatePeriod(
        //         new DateTime($startDate),
        //         new DateInterval("P1D"),
        //         (new DateTime($endDate))->modify("+1 day")
        //     );
        //     $dongtien = [];
        //     foreach ($period as $date) {
        //         $key = $date->format("Y-m-d");
        //         $dongtien[$key] = [
        //             "dauky"  => ["kehoach"=>0, "thucthu"=>0, "tong"=>0, "tile"=>0],
        //             "thu"    => ["kehoach"=>0, "thucthu"=>0, "tong"=>0, "tile"=>0],
        //             "chi"    => ["kehoach"=>0, "thucthu"=>0, "tong"=>0, "tile"=>0],
        //             "cuoiky" => ["kehoach"=>0, "thucthu"=>0, "tong"=>0, "tile"=>0],
        //         ];
        //     }
        //     // 4. TỔNG HỢP DỮ LIỆU THU/CHI VÀO CÁC NGÀY
        //     foreach ($data as $row) {
        //         $day = date("Y-m-d", strtotime($row['date']));
        //         if (!isset($dongtien[$day])) continue;
        //         $row['reality'] = (float)$app->sum("proposals_reality","reality",["proposal"=>$row['id'],"deleted"=>0]);
        //         $isThu = ($row['type'] == 1);
        //         $isChi = ($row['type'] == 2);
        //         $isActual = ($row['status'] == 4);
        //         $isApprol = ($row['status'] == 2 || $row['status'] == 4);

        //         if ($isThu) {
        //             if ($isApprol) {
        //                 $dongtien[$day]['thu']['kehoach'] += $row['price'];
        //             }
        //             if ($isActual) {
        //                 $dongtien[$day]['thu']['thucthu'] += $row['reality'];
        //             }
        //         } elseif ($isChi) {
        //             if ($isApprol) {
        //                 $dongtien[$day]['chi']['kehoach'] += $row['price'];
        //             }
        //             // $dongtien[$day]['chi']['kehoach'] += $row['price'];
        //             if ($isActual) {
        //                 $dongtien[$day]['chi']['thucthu'] += $row['reality'];
        //             }
        //         }
        //     }
        //     // 5. TÍNH TOÁN DÒNG TIỀN - ĐÃ CẬP NHẬT LOGIC CỘT "TỔNG"
        //     $lastCuoikyKehoach = 0;
        //     $lastCuoikyThucthu = 0;
        //     foreach ($dongtien as &$row) {
        //         // --- A. TÍNH TOÁN ĐẦU KỲ ---
        //         $row['dauky']['kehoach'] = $lastCuoikyKehoach;
        //         $row['dauky']['thucthu'] = $lastCuoikyThucthu;
        //         $row['dauky']['tong']    = $row['dauky']['kehoach'] - $row['dauky']['thucthu']; // CẬP NHẬT: Tổng = Kế hoạch - Thực tế
        //         $row['dauky']['tile']    = $row['dauky']['kehoach'] != 0 ? round(($row['dauky']['thucthu'] / $row['dauky']['kehoach']) * 100) : 0;

        //         // --- B. TÍNH TOÁN TỔNG VÀ TỶ LỆ CHO THU/CHI ---
        //         $row['thu']['tong'] = $row['thu']['kehoach'] - $row['thu']['thucthu']; // CẬP NHẬT: Tổng = Kế hoạch - Thực tế
        //         $row['chi']['tong'] = $row['chi']['kehoach'] - $row['chi']['thucthu']; // CẬP NHẬT: Tổng = Kế hoạch - Thực tế
        //         $row['thu']['tile'] = $row['thu']['kehoach'] > 0 ? round(($row['thu']['thucthu'] / $row['thu']['kehoach']) * 100) : 0;
        //         $row['chi']['tile'] = $row['chi']['kehoach'] > 0 ? round(($row['chi']['thucthu'] / $row['chi']['kehoach']) * 100) : 0;

        //         // --- C. TÍNH TOÁN CUỐI KỲ ---
        //         $cuoikyKehoach = $row['dauky']['kehoach'] + $row['thu']['kehoach'] - $row['chi']['kehoach'];
        //         $cuoikyThucthu = $row['dauky']['thucthu'] + $row['thu']['thucthu'] - $row['chi']['thucthu'];

        //         $row['cuoiky']['kehoach'] = $cuoikyKehoach;
        //         $row['cuoiky']['thucthu'] = $cuoikyThucthu;
        //         $row['cuoiky']['tong']    = $cuoikyKehoach - $cuoikyThucthu; // CẬP NHẬT: Tổng = Kế hoạch - Thực tế
        //         $row['cuoiky']['tile']    = $cuoikyKehoach != 0 ? round(($cuoikyThucthu / $cuoikyKehoach) * 100) : 0;

        //         // --- D. CẬP NHẬT SỐ DƯ CHO NGÀY TIẾP THEO ---
        //         $lastCuoikyKehoach = $cuoikyKehoach;
        //         $lastCuoikyThucthu = $cuoikyThucthu;
        //     }
        //     unset($row);
        //     // 6. CHUẨN BỊ DỮ LIỆU CHO BIỂU ĐỒ (CHART)
        //     $chartLabels = [];
        //     $chartThuKehoachData = [];
        //     $chartThuThucthuData = [];
        //     $chartChiKehoachData = [];
        //     $chartChiThucthuData = [];
        //     $chartCuoikyData = [];
        //     foreach ($dongtien as $date => $data) {
        //         $chartLabels[] = date("d M", strtotime($date));
        //         $chartThuKehoachData[] = $data['thu']['kehoach'];
        //         $chartThuThucthuData[] = $data['thu']['thucthu'];
        //         $chartChiKehoachData[] = $data['chi']['kehoach'];
        //         $chartChiThucthuData[] = $data['chi']['thucthu'];
        //         $chartCuoikyData[] = $data['cuoiky']['thucthu']; // "Tổng" trên chart vẫn là dòng tiền cuối kỳ thực tế
        //     }
        //     $vars['chartData'] = [
        //         'labels'       => $chartLabels,
        //         'thu_kehoach'  => $chartThuKehoachData,
        //         'thu_thucthu'  => $chartThuThucthuData,
        //         'chi_kehoach'  => $chartChiKehoachData,
        //         'chi_thucthu'  => $chartChiThucthuData,
        //         'cuoiky'       => $chartCuoikyData,
        //     ];
        //     // 7. TRUYỀN DỮ LIỆU RA VIEW
        //     $vars['dongtien'] = $dongtien;
        //     echo $app->render($template.'/proposal/cash-flow.html', $vars);
        // })->setPermissions(['proposal']);

    })->middleware('login');
 ?>