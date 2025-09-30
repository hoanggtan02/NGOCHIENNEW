<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\IOFactory;

if (!defined('ECLO'))
    die("Hacking attempt");

use ECLO\App;

$template = __DIR__ . '/../templates';
$jatbi = $app->getValueData('jatbi');
$common = $jatbi->getPluginCommon('io.eclo.proposal');
$setting = $app->getValueData('setting');
$app->group($setting['manager'] . "/scan", function ($app) use ($jatbi, $setting,$template) {

    $app->router('/ticket', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting,$template) {
        if ($app->method() === 'GET') {
            $vars['title'] = $jatbi->lang("Vé");
            echo $app->render($template . '/scan/ticket.html', $vars);
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
            $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
            $length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
            $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';

            $orderName = isset($_POST['order'][0]['type_name']) ? $_POST['order'][0]['type_name'] : 'id';
            $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';
            $status = isset($_POST['status']) ? [$_POST['status'], $_POST['status']] : '';
            $price = isset($_POST['price']) ? [$_POST['price'], $_POST['price']] : '';
            $type = isset($_POST['type']) ? [$_POST['type'], $_POST['type']] : '';



            $where = [
                "AND" => [
                    "OR" => [
                        'type_name[~]' => $searchValue,
                    ],
                    "deleted" => 0,
                    "status[<>]" => $status,
                    'price[<>]' => $price,
                    "type[<>]" => $type,

                ],
                "LIMIT" => [$start, $length],
                "ORDER" => [$orderName => strtoupper($orderDir)],
            ];

            $count = $app->count("ticket", [
                "AND" => $where['AND'],
            ]);
            $datas = [];

            $app->select("ticket", "*", $where, function ($data) use (&$datas, $jatbi, $app) {
                $datas[] = [
                    "checkbox" => $app->component("box", ["data" => $data['id']]),
                    "type_name" => $data['type_name'],
                    "code" => $data['active'],
                    "guest_information" => $data['guest_information'],
                    "price" => number_format($data['price']),
                    "date" => date('d/m/Y H:i:s', timestamp: strtotime($data['date_created'])),
                    "status" => ($data['status'] == 'D' ? '<span class="text-success fw-bold">' . $jatbi->lang("Chưa sử dụng") . '</span>' : '<span class="text-danger fw-bold">' . $jatbi->lang("Đã sử dụng") . '</span>'),
                    "action" => $app->component("action", [
                        "button" => [
                            // [
                            //     'type' => 'button',
                            //     'name' => $jatbi->lang("Sửa"),
                            //     'permission' => ['ticket.edit'],
                            //     'action' => ['data-url' => '/scan/ticket-edit/' . $data['id'], 'data-action' => 'modal']
                            // ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Mã có giao diện"),
                                'permission' => ['ticket'],
                                'action' => ['data-url' => '/scan/ticket-barcode-display/' . $data['id'], 'data-action' => 'modal']
                            ],
                            [
                                'type' => 'button',
                                'name' => $jatbi->lang("Mã không giao diện"),
                                'permission' => ['ticket'],
                                'action' => ['data-url' => '/scan/ticket-barcode/' . $data['id'], 'data-action' => 'modal']
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
    })->setPermissions(['ticket']);

    $app->router("/ticket-add", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting,$template) {
        $vars['title'] = $jatbi->lang("Thêm vé");

        if ($app->method() === 'GET') {
            $vars['data'] = [];
            $ticket_type_raw = $setting['ticket_types'] ?? [];
            $vars['ticket_type_options'] = [];


            // Xử lý mảng dựa trên cấu trúc
            if (!empty($ticket_type_raw)) {
                foreach ($ticket_type_raw as $key => $item) {
                    // Nếu $item là mảng con với 'id', 'name', 'price'
                    if (isset($item['id']) && isset($item['name']) && isset($item['price'])) {
                        $vars['ticket_type_options'][] = ['value' => $item['id'], 'text' => $item['name'] . ' - ' . number_format($item['price'], 0, ',', '.') . ' VNĐ'];
                    } else {
                        // Nếu key là id, $item là mảng con
                        $vars['ticket_type_options'][] = ['value' => $key, 'text' => $item['name'] . ' - ' . number_format($item['price'], 0, ',', '.') . ' VNĐ'];
                    }
                }
            }

            echo $app->render($template . '/scan/ticket-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            // BỎ QUA KIỂM TRA TOKEN NẾU BẠN KHÔNG DÙNG
            // if (!isset($_POST['token']) || $_POST['token'] !== $_SESSION['csrf-token']) { ... }

            $error = [];
            $type = $app->xss($_POST['type'] ?? '');

            // SỬA LẠI LOGIC KIỂM TRA LỖI
            if ($type == "") {
                // Gán lỗi vào mảng thay vì return ngay
                $error = ['status' => 'error', 'content' => $jatbi->lang('Lỗi trống'), 'sound' => $setting['site_sound']];
            }

            // Chỉ thực hiện tiếp nếu không có lỗi
            if (empty($error)) {
                if ($type == 1)
                    $code_prefix = 'VI';
                elseif ($type == 2)
                    $code_prefix = 'TC';
                else
                    $code_prefix = 'TN';

                $sequenceNumber = 100000;
                do {
                    $activationCode = $code_prefix . '-' . $sequenceNumber;
                    $hasNumber = $app->has('ticket', ['active' => $activationCode]);
                    if (!$hasNumber)
                        break;
                    $sequenceNumber++;
                } while (true);

                $insert = [
                    "type" => $type,
                    // Lấy name và price từ mảng $setting['ticket_types'] (giống code cũ)
                    "type_name" => $setting['ticket_types'][$type]['name'] ?? '',
                    "price" => $setting['ticket_types'][$type]['price'] ?? 0,
                    "guest_information" => $app->xss($_POST['code'] ?? ''), // HTML mới dùng name="code"
                    "date_created" => date('Y-m-d H:i:s'),
                    "active" => $activationCode,
                    "user" => $app->getSession("accounts")['id'] ?? 0,
                    "status" => $app->xss($_POST['status'] ?? 'D'),
                ];

                $app->insert("ticket", $insert);
                $jatbi->logs('ticket', 'add', $insert);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công'), 'url' => $_SERVER['HTTP_REFERER']]);
            } else {
                // Nếu có lỗi, trả về mảng lỗi
                echo json_encode($error);
            }
        }
    })->setPermissions(['ticket.add']);

    $app->router("/ticket-edit/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting,$template) {

        $vars['title'] = $jatbi->lang("Sửa vé");
        if ($app->method() === 'GET') {

            $vars['data'] = $app->get('ticket', '*', ['id' => $vars['id']]);

            if (!$vars['data']) {

                die("Không tìm thấy vé!");
            }
            $ticket_type_raw = $setting['ticket_types'] ?? [];
            $vars['ticket_type_options'] = [];
            if (!empty($ticket_type_raw)) {
                foreach ($ticket_type_raw as $key => $item) {
                    if (isset($item['id']) && isset($item['name']) && isset($item['price'])) {
                        $vars['ticket_type_options'][] = ['value' => $item['id'], 'text' => $item['name'] . ' - ' . number_format($item['price'], 0, ',', '.') . ' VNĐ'];
                    }
                }
            }
            echo $app->render($template . '/scan/ticket-post.html', $vars, $jatbi->ajax());
        } elseif ($app->method() === 'POST') {
            $app->header(['Content-Type' => 'application/json']);

            $error = [];
            $type = $app->xss($_POST['type'] ?? '');

            if ($type == "") {
                $error = ['status' => 'error', 'content' => $jatbi->lang('Lỗi trống'), 'sound' => $setting['site_sound']];
            }

            if (empty($error)) {

                $update = [
                    "type" => $type,
                    "type_name" => $setting['ticket_types'][$type]['name'] ?? '',
                    "price" => $setting['ticket_types'][$type]['price'] ?? 0,
                    "guest_information" => $app->xss($_POST['guest_information'] ?? ''),
                    "status" => $app->xss($_POST['status'] ?? 'D'),

                ];


                $app->update("ticket", $update, ["id" => $vars['id']]);

                $jatbi->logs('ticket', 'edit', $update);
                echo json_encode(['status' => 'success', 'content' => $jatbi->lang('Cập nhật thành công'), 'url' => $_SERVER['HTTP_REFERER']]);
            } else {
                echo json_encode($error);
            }
        }
    })->setPermissions(['ticket.edit']);

    $app->router('/check_ticket', 'GET', function ($vars) use ($app, $jatbi, $template) {
        $vars['title'] = $jatbi->lang("Kiểm tra vé");

        // Lấy vé cuối cùng đã check trong session (nếu có)
        $last_checked_ticket_id = $_SESSION['ticket']['check']['last_id'] ?? null;
        $vars['last_ticket'] = $last_checked_ticket_id ? $app->get("ticket", "*", ["id" => $last_checked_ticket_id]) : null;

        echo $app->render($template . '/scan/check-ticket.html', $vars);
    })->setPermissions(['check_ticket']);

    $app->router('/ticket-barcode-views/{id}', 'GET', function ($vars) use ($app, $jatbi, $template) {
        $id = $vars['id'] ?? 0;
        $data = $app->get("ticket", "*", ["id" => $id]);
        if (!$data) {
            return;
        }

        $vars['data'] = $data;
        $vars['title'] = 'QR Code - ' . $data['active'];

        echo $app->render($template . '/scan/ticket-barcode-views.html', $vars, $jatbi->ajax());
    });

    $app->router('/ticket-barcode/{id}', 'GET', function ($vars) use ($app, $jatbi, $template) {
        $id = $vars['id'] ?? 0;

        // Tối ưu: Chỉ lấy các cột cần thiết
        $data = $app->get("ticket", [
            "id",
            "type_name",
            "active",
            "price"
        ], ["id" => $id]);

        if (!$data) {
            echo $app->render($template . '/error.html', ['content' => 'Không tìm thấy vé.'], $jatbi->ajax());
            return;
        }

        $vars['data'] = $data;
        $vars['title'] = $jatbi->lang("Mã vé") . " - " . $data['active'];

        // Render ra file giao diện modal mới
        echo $app->render($template . '/scan/ticket-barcode.html', $vars, $jatbi->ajax());
    })->setPermissions(['ticket']);

    $app->router('/ticket-barcode-display/{id}', 'GET', function ($vars) use ($app, $jatbi, $template) {
        $id = $vars['id'] ?? 0;

        // Tối ưu: Chỉ lấy các cột cần thiết
        $data = $app->get("ticket", ["id", "type", "active"], ["id" => $id]);

        if (!$data) {
            echo $app->render($template . '/error.html', ['content' => 'Không tìm thấy vé.'], $jatbi->ajax());
            return;
        }

        // Chuẩn bị đường dẫn ảnh nền dựa trên loại vé
        $image_map = [
            1 => '/assets/img/icon/vip.png',
            2 => '/assets/img/icon/tieuchuan.png',
            3 => '/assets/img/icon/thuong.png'

        ];
        $data['background_image'] = $image_map[$data['type']] ?? '';

        $vars['data'] = $data;
        $vars['title'] = $jatbi->lang("Hiển thị Mã vé");

        echo $app->render($template . '/scan/ticket-barcode-display.html', $vars, $jatbi->ajax());
    })->setPermissions(['ticket']);
    ob_start();
    $app->router('/ticket-excel', 'GET', function ($vars) use ($app) {

        try {
            // --- BƯỚC 1: LẤY VÀ XỬ LÝ CÁC BỘ LỌC TỪ URL ---
            $searchValue = $_GET['search']['value'] ?? '';
            $status = $_GET['status'] ?? '';
            $type = $_GET['type'] ?? '';
            $price = str_replace([","], [""], $_GET['price'] ?? '');

            $where = ["AND" => ["deleted" => 0]];
            if (!empty($searchValue)) {
                $where['AND']['type_name[~]'] = $searchValue;
            }
            if ($status !== '') {
                $where['AND']['status'] = $status;
            }
            if (!empty($type)) {
                $where['AND']['type'] = $type;
            }
            if (!empty($price)) {
                $where['AND']['price'] = $price;
            }
            $where["ORDER"] = ['id' => 'DESC'];

            // --- BƯỚC 2: LẤY DỮ LIỆU CHÍNH ---
            $datas = $app->select("ticket", "*", $where);

            if (empty($datas)) {
                exit("Không có dữ liệu để xuất file.");
            }

            // --- BƯỚC 3: TẠO FILE EXCEL ---
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('DanhSachVe');

            // Ghi tiêu đề
            $headers = ['LOẠI VÉ', 'MÃ VÉ', 'GIÁ VÉ', 'THÔNG TIN KHÁCH', 'TRẠNG THÁI'];
            $sheet->fromArray($headers, null, 'A1');

            // Ghi dữ liệu
            $row_index = 2;
            foreach ($datas as $data) {
                $sheet->setCellValue('A' . $row_index, $data['type_name']);
                $sheet->setCellValue('B' . $row_index, $data['active']);
                $sheet->setCellValue('C' . $row_index, $data['price']);
                $sheet->setCellValue('D' . $row_index, $data['guest_information']);
                $sheet->setCellValue('E' . $row_index, ($data['status'] === 'A' ? 'Đã sử dụng' : 'Chưa sử dụng'));
                $row_index++;
            }

            // Định dạng cột và Tự động điều chỉnh kích thước
            $sheet->getStyle('C2:C' . $row_index)->getNumberFormat()->setFormatCode('#,##0');
            foreach (range('A', 'E') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            // --- BƯỚC 4: XUẤT FILE ---
            ob_end_clean();
            $file_name = 'danh-sach-ve_' . date('d-m-Y') . '.xls';
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="' . $file_name . '"');
            header('Cache-Control: max-age=0');

            $writer = new Xls($spreadsheet);
            $writer->save('php://output');
            exit;
        } catch (Exception $e) {
            // Xử lý lỗi nếu có
            http_response_code(500);
            exit("Lỗi: " . $e->getMessage());
        }
    })->setPermissions(['ticket']);
})->middleware('login');
