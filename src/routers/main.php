<?php
	if (!defined('ECLO')) die("Hacking attempt");
    use ECLO\App;
	$app->group($setting['manager'],function($app) use ($jatbi,$setting,$common){
        $app->router(($setting['manager']==''?'/':''),'GET', function($vars) use ($app,$setting,$common,$jatbi) {
            $vars['title'] = $jatbi->lang("Trang chủ");
    
            // === BƯỚC 1: CHUẨN BỊ CÁC BIẾN CẦN THIẾT ===
            $account_id = $app->getSession("accounts")['id'] ?? null;
            $vars['account'] = $account_id ? $app->get("accounts", "*", ["id" => $account_id]) : [];

            // Lấy thông tin tháng/năm và tính toán các mốc thời gian
            $month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
            $year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
            
            $current_date = new DateTime("$year-$month-01");
            $month_start = $current_date->format('Y-m-01 00:00:00');
            $month_end = $current_date->format('Y-m-t 23:59:59');
            // $today_start = date('Y-m-d 00:00:00');
            // $today_end = date('Y-m-d 23:59:59');

            [$today_start, $today_end] = $jatbi->parseDateRange();
            
            $brands_ids = $jatbi->brands('array_id');
            if (empty($brands_ids)) { $brands_ids = [0]; } // Tránh lỗi SQL nếu không có chi nhánh

            // === BƯỚC 2: TRUY VẤN TỔNG HỢP TẤT CẢ KPI TRONG 1 LẦN ===
            // $kpi_conditions = [ "deleted" => 0, "brands" => $brands_ids ];
            
            // $kpis = $app->get("invoices", [
            //     "tongdoanhso"         => App::raw("SUM(CASE WHEN status IN (1,2,20) AND date_start BETWEEN :today_start AND :today_end THEN total ELSE 0 END)", [':today_start' => $today_start, ':today_end' => $today_end]),
            //     "tongdoanhthu"        => App::raw("SUM(CASE WHEN status = 2 AND date_start BETWEEN :today_start AND :today_end THEN payments ELSE 0 END)", [':today_start' => $today_start, ':today_end' => $today_end]),
            //     "khachmoithang"       => App::raw("SUM(CASE WHEN status IN (2,20) AND date_start BETWEEN :today_start AND :today_end THEN amount ELSE 0 END)", [':today_start' => $today_start, ':today_end' => $today_end]),
            //     "doanhthuhomnay"      => App::raw("SUM(CASE WHEN status = 2 AND date_start BETWEEN :today_start AND :today_end THEN payments ELSE 0 END)", [':today_start' => $today_start, ':today_end' => $today_end]),
            //     "ghinoihomnay"        => App::raw("SUM(CASE WHEN status = 20 AND date_start BETWEEN :today_start AND :today_end THEN payments - total_pay ELSE 0 END)", [':today_start' => $today_start, ':today_end' => $today_end]),
            //     "bookinghomnaydanhan" => App::raw("COUNT(CASE WHEN status IN (1,2,20) AND process IN (2,200,100) AND date_start BETWEEN :today_start AND :today_end THEN id ELSE NULL END)")
            // ], $kpi_conditions);
            
            // $vars = array_merge($vars, $kpis ?? []);

            // // (Giữ nguyên các truy vấn cho Đề xuất và Kho hàng)
            // $proposal_kpis = $app->get("proposals", [
            //     "cho_duyet" => App::raw("COUNT(CASE WHEN status = 1 THEN id ELSE NULL END)"),
            //     "da_duyet_thang" => App::raw("COUNT(CASE WHEN status = 2 AND `date` BETWEEN :month_start AND :month_end THEN id ELSE NULL END)", [':month_start' => $month_start, ':month_end' => $month_end])
            // ], ["deleted" => 0]);
            // $vars = array_merge($vars, $proposal_kpis ?? []);

            // $warehouse_kpis = $app->get("warehouses_summary", [
            //     "tong_gia_tri_ton" => App::raw("SUM(total)")
            // ], ["brands" => $brands_ids]);
            // $date_30_days = date('Y-m-d', strtotime('+30 days'));
            // $vars['sap_het_han'] = $app->count("warehouses_inventory", ["expiry[<=]" => $date_30_days, "brands" => $brands_ids]);
            // $vars = array_merge($vars, $warehouse_kpis ?? []);

            // === BƯỚC 3: LẤY DỮ LIỆU LỊCH & BOOKING HÔM NAY ===
            // Lấy dữ liệu đã được gom nhóm sẵn cho lịch
            $calendar_data = $app->select("invoices", [
                "day" => App::raw("DATE(date_start)"),
                "total" => App::raw("COUNT(id)"),
                "payment" => App::raw("COUNT(CASE WHEN status = 2 THEN 1 ELSE NULL END)"),
                "cancel" => App::raw("COUNT(CASE WHEN status = 3 THEN 1 ELSE NULL END)"),
                "primary" => App::raw("COUNT(CASE WHEN process = 2 THEN 1 ELSE NULL END)"),
                "payments" => App::raw("SUM(CASE WHEN status = 2 THEN payments ELSE 0 END)")
            ], [
                "deleted" => 0,
                "brands" => $brands_ids,
                "date_start[<>]" => [$month_start, $month_end],
                "GROUP" => App::raw("DATE(date_start)")
            ]);

            $count = ['month' => []];
            foreach($calendar_data as $row) {
                $count['month'][$row['day']] = $row;
            }
            $vars['count'] = $count;

            // Lấy danh sách booking đang hoạt động của hôm nay
            $bookings_raw = $app->select("invoices", ["[>]customers" => ["customers" => 'id'], "[>]rooms" => ["room" => 'id'], "[>]accounts" => ["booking" => 'id']], ["invoices.total", "invoices.code", "invoices.status", "invoices.active", "invoices.payments", "invoices.date", "invoices.date (date_invoices)", "invoices.date_start (date_start)", "invoices.process", "rooms.active (rooms)", "customers.name (name)", "accounts.name (bookings)"], ["invoices.deleted"=>0, "invoices.date_start[<>]" => [$today_start, $today_end], "invoices.brands" => $brands_ids, "invoices.status" => [1,20], "invoices.process" => [1,2], "ORDER" => ["invoices.process"=>"ASC", "invoices.date"=>"DESC"]]);
            
            $bookings = [];
            foreach($bookings_raw as $data){
                $countdown_params = [];
                if ($data['process'] == 1) { // Chờ nhận phòng
                    $countdown_params = ["countdown_start" => $data['date_invoices'], "countdown_end" => $data['date_invoices'], "countdown_downcolor" => 'text-warning', "countdown_upcolor" => 'text-danger', "countdown_timedown" => 10*60, "countdown_timeup" => 1];
                } elseif ($data['process'] == 2) { // Đang phục vụ
                    $countdown_params = ["countdown_start" => $data['date_start'], "countdown_end" => $data['date_start'], "countdown_downcolor" => 'text-primary', "countdown_upcolor" => 'text-primary', "countdown_timedown" => 1, "countdown_timeup" => 1];
                }
                $bookings[] = array_merge($data, $countdown_params, ['color' => $data['status'] == 2 ? 'bg-success' : $common['process'][$data['process']]['color']]);
            }
            $vars['bookings'] = $bookings;

            // === BƯỚC 4: LẤY CÁC DANH SÁCH TOP ===
            $top_where = ["GROUP" => "customers", "ORDER" => ["total_payment" => "DESC"], "LIMIT" => 5, "invoices.status" => 2, "invoices.deleted" => 0, "invoices.date_start[<>]" => [$month_start,$month_end], "invoices.brands"=>$brands_ids];
            $vars['top_customers'] = $app->select("invoices", ["[>]customers" => ["customers" => "id"]], ["customers.name", "customers.date", "total_payment" => App::raw("SUM(invoices.payments)")], $top_where);
            
            // (Các truy vấn top khác giữ nguyên vì đã tối ưu)
            $vars['top_new_customers'] = $app->select("invoices", ["[>]customers" => ["customers" => "id"]], ["customers.id", "customers.name", "customers.date", "invoice_count" => App::raw("COUNT(invoices.id)"), "total_payment" => App::raw("SUM(invoices.payments)")], ["GROUP" => "customers.id", "ORDER" => ["invoice_count" => "DESC"], "LIMIT" => 5, "invoices.status" => 2, "invoices.deleted" => 0, "customers.status" => 'A', "invoices.date_start[<>]" =>[$month_start,$month_end], "customers.deleted" => 0, "invoices.brands"=>$brands_ids]);
            $vars['top_customers_month'] = $vars['top_customers']; // Tận dụng lại vì logic giống nhau

            // === BƯỚC 5: CHUẨN BỊ BIẾN CHO VIEW & RENDER ===
            $vars['month'] = $current_date->format('m');
            $vars['year'] = $current_date->format('Y');
            
            $prev_date = (clone $current_date)->modify('-1 month');
            $prev_month = $prev_date->format('m');
            $prev_year = $prev_date->format('Y');

            $next_date = (clone $current_date)->modify('+1 month');
            $next_month = $next_date->format('m');
            $next_year = $next_date->format('Y');
            
            $vars['dayLastMonth'] = $current_date->format('t');
            $startDay = (int)$current_date->format('w');
            $vars['startDay'] = $startDay == 0 ? 7 : $startDay;
            $vars['weeks'] = $common['weeks'];

            $queryStringPrevMonth = '?month=' . $prev_month . '&year=' . $prev_year;
            $queryStringNextMonth = '?month=' . $next_month . '&year=' . $next_year;

            $vars["nextMonth"] = $queryStringNextMonth;
            $vars["prevMonth"] = $queryStringPrevMonth;
            echo $app->render($setting['template'].'/pages/home.html', $vars);
        });
    })->middleware('login');
    $app->router("::404",'GET', function($vars) use ($app,$jatbi,$setting) {
        echo $app->render($setting['template'].'/pages/error.html', $vars, $jatbi->ajax());
    });
    $app->router('::500', 'GET', function() use ($app) {
        echo '<h1>ACCESS DENIED FOR ADMIN AREA</h1>';
    });
?>