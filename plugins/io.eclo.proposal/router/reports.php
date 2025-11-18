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
                "[>]stores" => ["proposals.stores" => "id"]
            ], [
                "proposals.id",
                "stores.name",
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
                "GROUP" => "proposals.stores",
                "ORDER" => ["count" => "DESC"],
                "LIMIT" => 5,
                "stores.name[!]" => "",
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
            $summary_kehoach = $app->get("proposals",[
                "header_thu"         => App::raw("SUM(CASE WHEN proposals.date < '$startDate' AND proposals.type = 1 AND proposals.deleted = 0 THEN proposals.price ELSE 0 END)"),
                "header_chi"         => App::raw("SUM(CASE WHEN proposals.date < '$startDate' AND proposals.type = 2 AND proposals.deleted = 0 THEN proposals.price ELSE 0 END)"),
            ],[
                "proposals.deleted" => 0,
                "proposals.status" => [2,4],
                "proposals.stores_id" => $jatbi->stores(),
            ]);

            $summary_thuc = $app->get("proposals_reality",[
                "[>]proposals" => ["proposal"=>"id"],
            ],[
                "header_thu"         => App::raw("SUM(CASE WHEN proposals_reality.reality_date < '$startDate' AND proposals.type = 1 AND proposals_reality.deleted = 0 THEN proposals_reality.reality ELSE 0 END)"),
                "header_chi"         => App::raw("SUM(CASE WHEN proposals_reality.reality_date < '$startDate' AND proposals.type = 2 AND proposals_reality.deleted = 0 THEN proposals_reality.reality ELSE 0 END)"),
            ],[
                "proposals_reality.deleted" => 0,
                "proposals.deleted" => 0,
                "proposals.status" => 4,
                "proposals.stores_id" => $jatbi->stores(),
            ]);
            // 3. TỔNG HỢP DỮ LIỆU KẾ HOẠCH (TỪ PROPOSALS)
            // Lấy tất cả proposals đã duyệt (status 2 hoặc 4) có *ngày kế hoạch* nằm trong khoảng

            $plannedData = $app->select("proposals", [
                "date", "type", "price"
            ], [
                "deleted" => 0,
                "date[<>]" => [$startDate, $endDate], // Lọc theo ngày của proposal
                "stores_id" => $jatbi->stores(),
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
                "p.stores_id" => $jatbi->stores(),
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
            $lastCuoikyKehoach = ($summary_kehoach['header_thu'] ?? 0) - ($summary_kehoach['header_chi'] ?? 0) ?? 0;
            $lastCuoikyThucthu = ($summary_thuc['header_thu'] ?? 0) - ($summary_thuc['header_chi'] ?? 0) ?? 0;

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
                $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
                $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'ASC';
                $stores = isset($_POST['stores']) ? $_POST['stores'] : $jatbi->stores();
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
                    ];
                }
                // // $where["GROUP"] = "proposals.id";
                if (!empty($_POST['date'])) {
                    $dates = explode(" - ", $_POST['date']);
                    if (count($dates) == 2) {
                        $from = DateTime::createFromFormat('d/m/Y', trim($dates[0]));
                        $to   = DateTime::createFromFormat('d/m/Y', trim($dates[1]));
                    }
                } 
                else {
                    $from = new DateTime("first day of this month 00:00:00");
                    $to   = new DateTime("now");
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
                if (!empty($stores)) {
                    $where["AND"]["proposals.stores_id"] = $stores;
                }
                $joins = [
                    "[>]proposals"=>["proposal"=>"id"],
                    "[>]accounts"=>["proposals.account"=>"id"],
                    "[>]proposal_form"=>["proposals_reality.form"=>"id"],
                    "[>]proposal_target"=>["proposals_reality.category"=>"id"],
                ];
                $summary = $app->get("proposals_reality",$joins,[
                    "header_thu"         => App::raw("SUM(CASE WHEN proposals_reality.reality_date < '$from_date_sql' AND proposals.type = 1 THEN proposals_reality.reality ELSE 0 END)"),
                    "header_chi"         => App::raw("SUM(CASE WHEN proposals_reality.reality_date < '$from_date_sql' AND proposals.type = 2 THEN proposals_reality.reality ELSE 0 END)"),

                    "period_thu"         => App::raw("SUM(CASE WHEN proposals_reality.reality_date >= '$from_date_sql' AND proposals_reality.reality_date <='$to_date_sql' AND proposals.type = 1  THEN proposals_reality.reality ELSE 0 END)"),
                    "period_chi"         => App::raw("SUM(CASE WHEN proposals_reality.reality_date >= '$from_date_sql' AND proposals_reality.reality_date <='$to_date_sql' AND proposals.type = 2  THEN proposals_reality.reality ELSE 0 END)"),
                ],$where['AND']);

                $joins["[>]proposal_accounts"] = ["proposal"=>"proposal"];

                $where['AND']["proposals_reality.reality_date[<>]"] = [$from_date_sql,$to_date_sql];

                $count = $app->count("proposals_reality",$joins,[
                    "@proposals_reality.id"
                ],[
                    "AND" => $where['AND'],
                ]);

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
                $is_date_asc_sort = ($orderName == 'id' && strtoupper($orderDir) == 'ASC');
                $stt = 1;
                $app->select("proposals_reality",$joins,[
                    "@proposals_reality.id",
                    "proposals.id (proposal_id)",
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
                ], $where, function ($data) use (&$datas,&$stt, $jatbi,$app,$common,$account,&$page_running_total, $is_date_asc_sort) {
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
                        "stt" => $stt++,
                        "content" => $data['content'] ?? '',
                        "form" => $data['form'] ?? '',
                        "category" => $data['category'] ?? '',
                        "account" => $data['accounts'] ?? '',
                        "thu" => $jatbi->money($thu_val),
                        "chi" => $jatbi->money($chi_val),
                        "ton" => $ton,
                        "date" => '<strong>'.$jatbi->date($data['date']).'</strong>',
                        "code" => '<a href="/proposal/views/'.$data['active'].'" data-pjax>#'.($data['proposal_id'] ?? '').'</a>',
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
                $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
                $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'ASC';
                $stores = isset($_POST['stores']) ? $_POST['stores'] : $jatbi->stores();
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
                    ];
                }
                // // $where["GROUP"] = "proposals.id";
                if (!empty($_POST['date'])) {
                    $dates = explode(" - ", $_POST['date']);
                    if (count($dates) == 2) {
                        $from = DateTime::createFromFormat('d/m/Y', trim($dates[0]));
                        $to   = DateTime::createFromFormat('d/m/Y', trim($dates[1]));
                    }
                } 
                else {
                    $from = new DateTime("first day of this month 00:00:00");
                    $to   = new DateTime("now");
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
                if (!empty($stores)) {
                    $where["AND"]["proposals.stores_id"] = $stores;
                }
                $joins = [
                    "[>]proposals"=>["proposal"=>"id"],
                    "[>]accounts"=>["proposals.account"=>"id"],
                    "[>]proposal_form"=>["proposals_reality.form"=>"id"],
                    "[>]proposal_target"=>["proposals_reality.category"=>"id"],
                ];
                $summary = $app->get("proposals_reality",$joins,[
                    "header_thu"         => App::raw("SUM(CASE WHEN proposals_reality.reality_date < '$from_date_sql' AND proposals.type = 1 THEN proposals_reality.reality ELSE 0 END)"),
                    "header_chi"         => App::raw("SUM(CASE WHEN proposals_reality.reality_date < '$from_date_sql' AND proposals.type = 2 THEN proposals_reality.reality ELSE 0 END)"),

                    "period_thu"         => App::raw("SUM(CASE WHEN proposals_reality.reality_date >= '$from_date_sql' AND proposals_reality.reality_date <='$to_date_sql' AND proposals.type = 1  THEN proposals_reality.reality ELSE 0 END)"),
                    "period_chi"         => App::raw("SUM(CASE WHEN proposals_reality.reality_date >= '$from_date_sql' AND proposals_reality.reality_date <='$to_date_sql' AND proposals.type = 2  THEN proposals_reality.reality ELSE 0 END)"),
                ],$where['AND']);

                $joins["[>]proposal_accounts"] = ["proposal"=>"proposal"];

                $where['AND']["proposals_reality.reality_date[<>]"] = [$from_date_sql,$to_date_sql];

                $count = $app->count("proposals_reality",$joins,[
                    "@proposals_reality.id"
                ],[
                    "AND" => $where['AND'],
                ]);

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
                $is_date_asc_sort = ($orderName == 'id' && strtoupper($orderDir) == 'ASC');
                $stt = 1;
                $app->select("proposals_reality",$joins,[
                    "@proposals_reality.id",
                    "proposals.id (proposal_id)",
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
                ], $where, function ($data) use (&$datas,&$stt, $jatbi,$app,$common,$account,&$page_running_total, $is_date_asc_sort) {
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
                        "stt" => $stt++,
                        "content" => $data['content'] ?? '',
                        "form" => $data['form'] ?? '',
                        "category" => $data['category'] ?? '',
                        "account" => $data['accounts'] ?? '',
                        "thu" => $jatbi->money($thu_val),
                        "chi" => $jatbi->money($chi_val),
                        "ton" => $ton,
                        "date" => '<strong>'.$jatbi->date($data['date']).'</strong>',
                        "code" => '<a href="/proposal/views/'.$data['active'].'" data-pjax>#'.($data['proposal_id'] ?? '').'</a>',
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
                $orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
                $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'ASC';
                $stores = isset($_POST['stores']) ? $_POST['stores'] : $jatbi->stores();
                $category = isset($_POST['category']) ? $_POST['category'] : '';
                $type = isset($_POST['type']) ? $_POST['type'] : '';
                $workflows = isset($_POST['workflows']) ? $_POST['workflows'] : '';
                $form = isset($_POST['form']) ? $_POST['form'] : '';
                $Searchaccount = isset($_POST['account']) ? $_POST['account'] : '';
                $where = [
                    "AND" => [
                        "OR" => [
                            "proposals.content[~]" => $searchValue,
                            // "proposals.code[~]" => $searchValue,
                        ],
                        "proposals.status" => 4,
                        "proposals.deleted" => 0,
                        "proposals_reality.deleted" => 0,
                    ],
                    "LIMIT" => [$start, $length],
                    "ORDER" => [$orderName => strtoupper($orderDir)],
                ];
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
                    ];
                }
                // // $where["GROUP"] = "proposals.id";
                if (!empty($_POST['date'])) {
                    $dates = explode(" - ", $_POST['date']);
                    if (count($dates) == 2) {
                        $from = DateTime::createFromFormat('d/m/Y', trim($dates[0]));
                        $to   = DateTime::createFromFormat('d/m/Y', trim($dates[1]));
                    }
                } 
                else {
                    $from = new DateTime("first day of this month 00:00:00");
                    $to   = new DateTime("now");
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
                    $where["AND"]["proposals_reality.form"] = $form;
                }
                if (!empty($category)) {
                    $where["AND"]["proposals_reality.category"] = $category;
                }
                if (!empty($stores)) {
                    $where["AND"]["proposals.stores_id"] = $stores;
                }
                $joins = [
                    "[>]proposals"=>["proposal"=>"id"],
                    "[>]accounts"=>["proposals.account"=>"id"],
                    "[>]proposal_form"=>["proposals_reality.form"=>"id"],
                    "[>]proposal_target"=>["proposals_reality.category"=>"id"],
                ];
                $summary = $app->get("proposals_reality",$joins,[
                    "header_thu"         => App::raw("SUM(CASE WHEN proposals_reality.reality_date < '$from_date_sql' AND proposals.type = 1 THEN proposals_reality.reality ELSE 0 END)"),
                    "header_chi"         => App::raw("SUM(CASE WHEN proposals_reality.reality_date < '$from_date_sql' AND proposals.type = 2 THEN proposals_reality.reality ELSE 0 END)"),

                    "period_thu"         => App::raw("SUM(CASE WHEN proposals_reality.reality_date >= '$from_date_sql' AND proposals_reality.reality_date <='$to_date_sql' AND proposals.type = 1  THEN proposals_reality.reality ELSE 0 END)"),
                    "period_chi"         => App::raw("SUM(CASE WHEN proposals_reality.reality_date >= '$from_date_sql' AND proposals_reality.reality_date <='$to_date_sql' AND proposals.type = 2  THEN proposals_reality.reality ELSE 0 END)"),
                ],$where['AND']);

                $joins["[>]proposal_accounts"] = ["proposal"=>"proposal"];

                $where['AND']["proposals_reality.reality_date[<>]"] = [$from_date_sql,$to_date_sql];

                $count = $app->count("proposals_reality",$joins,[
                    "@proposals_reality.id"
                ],[
                    "AND" => $where['AND'],
                ]);

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
                $is_date_asc_sort = ($orderName == 'id' && strtoupper($orderDir) == 'ASC');
                $stt = 1;
                $app->select("proposals_reality",$joins,[
                    "@proposals_reality.id",
                    "proposals.id (proposal_id)",
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
                ], $where, function ($data) use (&$datas,&$stt, $jatbi,$app,$common,$account,&$page_running_total, $is_date_asc_sort) {
                    $thu_val = ($data['type'] == 1) ? $data['reality'] : 0;
                    $chi_val = ($data['type'] == 2) ? $data['reality'] : 0;
                    $ton = '...';

                    // $type = $common['proposal'][$data['type']];
                    // $status = $common['proposal-status'][$data['status']];

                    // if ($is_date_asc_sort) {
                        $page_running_total += ($thu_val - $chi_val);
                        $ton = $jatbi->money($page_running_total);
                    // }

                    $datas[] = [
                        "stt" => $stt++,
                        "content" => $data['content'] ?? '',
                        "form" => $data['form'] ?? '',
                        "category" => $data['category'] ?? '',
                        "account" => $data['accounts'] ?? '',
                        "thu" => $jatbi->money($thu_val),
                        "chi" => $jatbi->money($chi_val),
                        "ton" => $ton,
                        "date" => '<strong>'.$jatbi->date($data['date']).'</strong>',
                        "code" => '<a href="/proposal/views/'.$data['active'].'" data-pjax>#'.($data['proposal_id'] ?? '').'</a>',
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
                    "date" => [
                        "start" => $from_date_sql,
                        "end" => $to_date_sql,
                    ]
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

                if (!empty($stores)) {
                    $where["AND"]["proposals.stores_id"] = $stores;
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
                $stores = isset($_POST['stores']) ? $_POST['stores'] : $jatbi->stores();

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

                if (!empty($stores)) {
                    $where["AND"]["proposals.stores_id"] = $stores;
                }
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
                    "[>]proposal_accounts"=>["id"=>"proposal"],
                    "[>]stores"=>["stores_id"=>"id"],
                    "[>]proposals_reality" => ["id" => "proposal"],
                ];

                $count = $app->count("proposals", $joins, "@proposals.id", ["AND" => $where['AND']]);

                $proposals = $app->select("proposals", $joins, [
                    "@proposals.id (proposal_id)",
                    "proposals.date",
                    "proposals.content",
                    "stores.name (stores)",
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
                        'stores' => $item['stores'],
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
                $stores = isset($_POST['stores']) ? $_POST['stores'] : $jatbi->stores();

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
                if (!empty($stores)) {
                    $where["AND"]["proposals.stores_id"] = $stores;
                }
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
                    "[>]stores"=>["stores_id"=>"id"],
                    "[>]proposal_accounts"=>["id"=>"proposal"],
                    "[>]proposals_reality" => ["id" => "proposal"],
                ];

                $count = $app->count("proposals", $joins, "@proposals.id", ["AND" => $where['AND']]);

                $proposals = $app->select("proposals", $joins, [
                    "@proposals.id (proposal_id)",
                    "proposals.date",
                    "proposals.content",
                    "stores.name (stores)",
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
                        'stores' => $item['stores'],
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

        $app->router("/overview-cash-flow", ['GET','POST'], function($vars) use ($app, $jatbi, $template) {
            $vars['title'] = $jatbi->lang("Tổng quan dòng tiền");

            // --- 1. Xử lý ngày ---
            if (!empty($_GET['date'])) { 
                $dates = explode(" - ", $_GET['date']);
                if (count($dates) == 2) {
                    $from = DateTime::createFromFormat('d/m/Y', trim($dates[0]));
                    $to   = DateTime::createFromFormat('d/m/Y', trim($dates[1]));
                }
            }
            
            if (!isset($from) || !isset($to) || !$from || !$to) { // Mặc định
                $from = new DateTime("first day of this month 00:00:00");
                $to   = new DateTime("now");
            }
            
            $startDate = $from->format("Y-m-d 00:00:00");
            $endDate = $to->format("Y-m-d 23:59:59");

            $vars['date'] = $from->format("d/m/Y") . " - " . $to->format("d/m/Y");

            // --- 2. Lấy dữ liệu KẾ HOẠCH (Trong kỳ) ---
            $stores = [];
            $summary = [];
            $plannedData = $app->select("proposals", [
                "[>]proposal_form" => ["form" => "id"],
                "[>]proposal_target" => ["category" => "id"],
                "[>]proposal_groups (group_form)" => ["proposal_form.group" => "id"],
                "[>]stores" => ["stores_id" => "id"],
            ], [
                "proposals.price", "proposals.type",
                "proposal_form.name(form_name)", "proposal_form.id(form_id)",
                "group_form.name(form_group_name)", "group_form.id(form_group_id)",
                "proposal_target.name(target_name)", "proposal_target.id(target_id)",
                "stores.name(store_name)", "stores.id(store_id)"
            ], [
                "proposals.deleted" => 0,
                "proposals.date[<>]" => [$startDate, $endDate],
                "proposals.status" => 4,
                "proposals.stores_id" => $jatbi->stores(),
            ]);

            // Hàm khởi tạo mới, phức tạp hơn để xử lý ID và Name
            $initSummary = function(&$summary, $type, $group_id, $group_name, $form_id, $form_name, $target_id, $target_name, $store_id) {
                if (!isset($summary[$type])) $summary[$type] = [];
                
                if (!isset($summary[$type][$group_id])) {
                    $summary[$type][$group_id] = ['name' => $group_name, 'forms' => []];
                }
                if (!isset($summary[$type][$group_id]['forms'][$form_id])) {
                    $summary[$type][$group_id]['forms'][$form_id] = ['name' => $form_name, 'targets' => []];
                }
                if (!isset($summary[$type][$group_id]['forms'][$form_id]['targets'][$target_id])) {
                    $summary[$type][$group_id]['forms'][$form_id]['targets'][$target_id] = ['name' => $target_name, 'stores' => []];
                }
                if (!isset($summary[$type][$group_id]['forms'][$form_id]['targets'][$target_id]['stores'][$store_id])) {
                    $summary[$type][$group_id]['forms'][$form_id]['targets'][$target_id]['stores'][$store_id] = ['KH' => 0, 'TT' => 0];
                }
            };

            foreach ($plannedData as $row) {
                $store_id = $row['store_id'] ?? 0;
                $store_name = $row['store_name'] ?? 'Không xác định';
                $type = ($row['type'] == 1) ? 'Thu' : 'Chi';
                $group_id = $row['form_group_id'] ?? 0;
                $group_name = $row['form_group_name'] ?? 'Không xác định';
                $form_id = $row['form_id'] ?? 0;
                $form_name = $row['form_name'] ?? 'Không xác định';
                $target_id = $row['target_id'] ?? 0;
                $target_name = $row['target_name'] ?? 'Không xác định';
                $amount = (float)($row['price'] ?? 0);

                $stores[$store_id] = $store_name; // Lấy danh sách stores [id => name]
                $initSummary($summary, $type, $group_id, $group_name, $form_id, $form_name, $target_id, $target_name, $store_id);
                $summary[$type][$group_id]['forms'][$form_id]['targets'][$target_id]['stores'][$store_id]['KH'] += $amount;
            }

            // --- 3. Lấy dữ liệu THỰC TẾ (Trong kỳ) ---
            $actualData = $app->select("proposals_reality", [
                "[>]proposals" => ["proposal" => "id"],
                "[>]proposal_form" => ["proposals_reality.form" => "id"],
                "[>]proposal_target" => ["proposals_reality.category" => "id"],
                "[>]proposal_groups (group_form)" => ["proposal_form.group" => "id"],
                "[>]stores" => ["proposals.stores_id" => "id"],
            ], [
                "proposals_reality.reality", "proposals.type",
                "proposal_form.name(form_name)", "proposal_form.id(form_id)",
                "group_form.name(form_group_name)", "group_form.id(form_group_id)",
                "proposal_target.name(target_name)", "proposal_target.id(target_id)",
                "stores.name(store_name)", "stores.id(store_id)"
            ], [
                "proposals_reality.deleted" => 0,
                "proposals.deleted" => 0,
                "proposals_reality.reality_date[<>]" => [$startDate, $endDate],
                "proposals.status" => 4,
                "proposals.stores_id" => $jatbi->stores(),
            ]);

            $formNetFlow = [];
            $topSpending = [];

            foreach ($actualData as $row) {
                $store_id = $row['store_id'] ?? 0;
                $store_name = $row['store_name'] ?? 'Không xác định';
                $type = ($row['type'] == 1) ? 'Thu' : 'Chi';
                $group_id = $row['form_group_id'] ?? 0;
                $group_name = $row['form_group_name'] ?? 'Không xác định';
                $form_id = $row['form_id'] ?? 0;
                $form_name = $row['form_name'] ?? 'Không xác định';
                $target_id = $row['target_id'] ?? 0;
                $target_name = $row['target_name'] ?? 'Không xác định';
                $amount = (float)($row['reality'] ?? 0);

                $stores[$store_id] = $store_name; // Cập nhật thêm store nếu có
                $initSummary($summary, $type, $group_id, $group_name, $form_id, $form_name, $target_id, $target_name, $store_id);
                $summary[$type][$group_id]['forms'][$form_id]['targets'][$target_id]['stores'][$store_id]['TT'] += $amount;

                // Nạp dữ liệu cho phân tích (theo ID)
                if (!isset($formNetFlow[$form_id])) {
                    $formNetFlow[$form_id] = ['name' => $form_name, 'Thu' => 0, 'Chi' => 0];
                }
                $formNetFlow[$form_id][$type] += $amount;
                
                if ($type == 'Chi') {
                    if (!isset($topSpending[$target_id])) {
                        $topSpending[$target_id] = ['name' => $target_name, 'total' => 0];
                    }
                    $topSpending[$target_id]['total'] += $amount;
                }
            }
            
            // Sắp xếp $stores theo ID
            ksort($stores);
            if (!isset($stores[0])) { // Đảm bảo store "Không xác định" (ID 0) tồn tại nếu có dữ liệu
                if(isset($summary['Thu'][0]) || isset($summary['Chi'][0])) {
                    $stores[0] = 'Không xác định';
                }
            }

            $vars['stores'] = $stores; // Danh sách stores [id => name]
            uasort($topSpending, fn($a, $b) => $b['total'] <=> $a['total']); // Sắp xếp $topSpending theo 'total'

            // --- 4. TÍNH TỒN ĐẦU KỲ (CHO TỪNG STORE) ---
            $openingBalances = array_fill_keys(array_keys($vars['stores']), 0);
            $openingData = $app->select("proposals_reality", [
                "[>]proposals" => ["proposal" => "id"],
                "[>]stores" => ["proposals.stores_id" => "id"],
            ], [
                "proposals.type",
                "proposals_reality.reality",
                "stores.id(store_id)" // Lấy store_id
            ], [
                "proposals_reality.deleted" => 0,
                "proposals.deleted" => 0,
                "proposals.status" => 4,
                "proposals_reality.reality_date[<]" => $startDate,
                "proposals.stores_id" => $jatbi->stores(),
            ]);

            foreach ($openingData as $row) {
                $store_id = $row['store_id'] ?? 0;
                $amount = (float)$row['reality'];
                
                if (!isset($openingBalances[$store_id])) $openingBalances[$store_id] = 0; // An toàn
                
                if ($row['type'] == 1) { // Thu
                    $openingBalances[$store_id] += $amount;
                } else { // Chi
                    $openingBalances[$store_id] -= $amount;
                }
            }

            // --- 5. Truyền dữ liệu ra View ---
            $vars['summary'] = $summary;
            $vars['topSpending'] = array_slice($topSpending, 0, 10, true);
            $vars['formNetFlow'] = $formNetFlow;
            $vars['openingBalances'] = $openingBalances;

            echo $app->render($template.'/proposal/overview-cash-flow.html', $vars);
        })->setPermissions(['proposal.overview-cash-flow']);

        $app->router("/report-cash-flow", ['GET','POST'], function($vars) use ($app, $jatbi, $template) {
            $vars['title'] = $jatbi->lang("Báo cáo dòng tiền đề xuất");

            // 1. XỬ LÝ NGÀY THÁNG
            if (!empty($_GET['date'])) {
                [$from, $to] = explode(" - ", $_GET['date']);
                $startDate = DateTime::createFromFormat("d/m/Y", trim($from))->format("Y-m-d");
                $endDate   = DateTime::createFromFormat("d/m/Y", trim($to))->format("Y-m-d");
            } else {
                $today     = new DateTime();
                $startDate = (clone $today)->modify("-6 day")->format("Y-m-d"); // Mặc định lấy 7 ngày gần nhất
                $endDate   = (clone $today)->format("Y-m-d");
            }
            // Truyền ngày đã chọn ra view để hiển thị lại trên input
            $vars['dateRange'] = (new DateTime($startDate))->format("d/m/Y") . " - " . (new DateTime($endDate))->format("d/m/Y");


            // 2. KHỞI TẠO CẤU TRÚC DỮ LIỆU DÒNG TIỀN (Làm trước)
            $period = new DatePeriod(
                new DateTime($startDate),
                new DateInterval("P1D"),
                (new DateTime($endDate))->modify("+1 day")
            );

            $reportData = [];
            foreach ($period as $date) {
                $dateString = $date->format("Y-m-d");
                $reportData[$dateString] = [
                    'date_formatted' => $date->format("d/m/Y"),
                    'summary' => ['thu' => 0, 'chi' => 0, 'profit' => 0],
                    'details' => [
                        'thu' => ['by_form' => [], 'by_target' => []], // Thu: nhóm theo Hình thức & Hạng mục
                        'chi' => ['by_form' => [], 'by_target' => []]  // Chi: nhóm theo Hình thức & Hạng mục
                    ]
                ];
            }

            // 3. TRUY VẤN DỮ LIỆU THỰC TẾ
            // ================== THAY ĐỔI 1: Thêm form_id và target_id vào SELECT ==================
            $actualData = $app->select("proposals_reality", [
                "[>]proposals" => ["proposal" => "id"],
                "[>]proposal_form" => ["proposals_reality.form" => "id"], 
                "[>]proposal_target" => ["proposals_reality.category" => "id"], 
                "[>]proposal_groups (group_form)" => ["proposal_form.group" => "id" , "AND"=> ["group_form.type"=>1]], 
                "[>]proposal_groups (group_target)" => ["proposal_target.group" => "id" , "AND"=> ["group_target.type"=>2]], 
            ], [
                "proposals_reality.reality_date",  
                "proposals_reality.reality",       // Số tiền THỰC TẾ
                "proposals.type",                  // Loại (1 = thu, 2 = chi)
                "proposal_form.name(form_name)",
                "proposal_form.id(form_id)", // <-- THÊM ID
                "proposal_target.name(target_name)",
                "proposal_target.id(target_id)", // <-- THÊM ID
                "group_form.name(group_form_name)",
                "group_target.name(group_target_name)"
            ], [
                "proposals_reality.deleted" => 0,
                "proposals.deleted" => 0,
                "proposals_reality.reality_date[<>]" => [$startDate, $endDate], // Lọc theo ngày THỰC TẾ
                "proposals.stores_id" => $jatbi->stores(),
                "proposals.status" => 4 
            ]);

            // 4. XỬ LÝ VÀ TỔNG HỢP DỮ LIỆU
            foreach ($actualData as $item) {
                $date = $item['reality_date'];
                // Chỉ xử lý nếu ngày nằm trong $reportData (đã được khởi tạo ở bước 2)
                if (!isset($reportData[$date])) continue; 

                $amount = (float)$item['reality'];
                $typeKey = $item['type'] == 1 ? 'thu' : 'chi';

                // 4.1. Cập nhật TỔNG QUAN (Summary)
                $reportData[$date]['summary'][$typeKey] += $amount;

                // 4.2. Xử lý chi tiết THEO HÌNH THỨC (by_form)
                $groupFormName = $item['group_form_name'] ?? 'Chưa phân nhóm';
                $formName = $item['form_name'] ?? 'Chưa có hình thức';
                // ================== THAY ĐỔI 2: Lấy form_id ==================
                $formId = $item['form_id'] ?? 0; // 0 là ID cho mục "chưa có"
               
                // Khởi tạo nếu chưa có
                if (!isset($reportData[$date]['details'][$typeKey]['by_form'][$groupFormName])) {
                    $reportData[$date]['details'][$typeKey]['by_form'][$groupFormName] = ['total' => 0, 'items' => []];
                }
                // ================== THAY ĐỔI 3: Dùng $formId làm key và lưu 'name', 'total' ==================
                if (!isset($reportData[$date]['details'][$typeKey]['by_form'][$groupFormName]['items'][$formId])) {
                    $reportData[$date]['details'][$typeKey]['by_form'][$groupFormName]['items'][$formId] = ['name' => $formName, 'total' => 0];
                }
                // Cộng dồn
                $reportData[$date]['details'][$typeKey]['by_form'][$groupFormName]['total'] += $amount;
                $reportData[$date]['details'][$typeKey]['by_form'][$groupFormName]['items'][$formId]['total'] += $amount;

                // 4.3. Xử lý chi tiết THEO HẠNG MỤC (by_target)
                $groupTargetName = $item['group_target_name'] ?? 'Chưa phân nhóm';
                $targetName = $item['target_name'] ?? 'Chưa có hạng mục';
                // ================== THAY ĐỔI 4: Lấy target_id ==================
                $targetId = $item['target_id'] ?? 0; // 0 là ID cho mục "chưa có"

                // Khởi tạo nếu chưa có
                if (!isset($reportData[$date]['details'][$typeKey]['by_target'][$groupTargetName])) {
                    $reportData[$date]['details'][$typeKey]['by_target'][$groupTargetName] = ['total' => 0, 'items' => []];
                }
                 // ================== THAY ĐỔI 5: Dùng $targetId làm key và lưu 'name', 'total' ==================
                if (!isset($reportData[$date]['details'][$typeKey]['by_target'][$groupTargetName]['items'][$targetId])) {
                    $reportData[$date]['details'][$typeKey]['by_target'][$groupTargetName]['items'][$targetId] = ['name' => $targetName, 'total' => 0];
                }
                // Cộng dồn
                $reportData[$date]['details'][$typeKey]['by_target'][$groupTargetName]['total'] += $amount;
                $reportData[$date]['details'][$typeKey]['by_target'][$groupTargetName]['items'][$targetId]['total'] += $amount;
            }

            // 5. TÍNH LỢI NHUẬN VÀ TRUYỀN DỮ LIỆU RA VIEW
            foreach ($reportData as $date => &$data) { // Dùng tham chiếu &
                $data['summary']['profit'] = $data['summary']['thu'] - $data['summary']['chi'];
            }
            unset($data); // Hủy tham chiếu

            $vars['reportData'] = $reportData; // Truyền dữ liệu đã xử lý sang view

            echo $app->render($template.'/proposal/report-cash-flow.html', $vars);
        })->setPermissions(['proposal.report-cash-flow']);

    })->middleware('login');
 ?>