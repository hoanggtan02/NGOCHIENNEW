<?php
if (!defined('ECLO')) die("Hacking attempt");

$app->router("/api/webhook-api", ['POST'], function ($vars) use ($app, $jatbi, $setting) {

    // Gửi phản hồi nhanh để thiết bị không timeout
    http_response_code(200);
    echo json_encode(['status'=>'200','content'=>"success",'code'=>'000','success'=>true]);
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

    $input = file_get_contents("php://input");
    $decoded = json_decode($input, true);
    if (!$decoded) parse_str($input, $decoded);
    if (!$decoded) exit;

    $id          = $decoded['recordId'] ?? null;
    $sn          = $decoded['personSn'] ?? null;
    $personName  = isset($decoded['personName']) ? urldecode($decoded['personName']) : null;
    $personType  = $decoded['personType'] ?? null;
    $createTime  = $decoded['recordTimeStr'] ?? null;
    $flag        = $decoded['openDoorFlag'] ?? null;
    $deviceKey   = $decoded['deviceKey'] ?? null;
    $checkImgBase64 = $decoded['checkImgBase64'] ?? null;

    if (empty($sn) || empty($createTime)) exit;

    // Lưu ảnh nếu có
    $photo_path = '';
    if (!empty($checkImgBase64)) {
        $image_data = base64_decode($checkImgBase64);
        if ($image_data !== false) {
            if (!is_dir('datas/face_logs')) mkdir('datas/face_logs', 0755, true);
            $file_name = 'image_' . time() . '_' . $id . '.jpg';
            $file_path = 'datas/face_logs/' . $file_name;
            file_put_contents($file_path, $image_data);
            $photo_path = '/datas/face_logs/' . $file_name;
        }
    }

    // Kiểm tra trùng log
    $web = $app->get("webhook","*",[
        "date_face"=>$createTime,
        "personSn"=>$sn,
        "devicekey"=>$deviceKey
    ]);
    if(!empty($web)) exit;

    // Lưu log webhook
    $app->insert("webhook", [
        "content"   => [$id,$sn,$personName,$personType,$createTime,$flag,$deviceKey],
        "personSn"  => $sn,
        "name"      => $personName,
        "date"      => date("Y-m-d H:i:s"),
        "date_face" => $createTime,
        "photo"     => $photo_path,
        "devicekey" => $deviceKey
    ]);

    // Lấy nhân viên
    $getPer = $app->get("personnels","*",[
        "id" => $sn,
        "deleted" => 0,
        "status" => 'A'
    ]);
    if(!$getPer) exit;

    // Kiểm tra dữ liệu chấm công
    $date = date("Y-m-d", strtotime($createTime));
    $gettime = $app->get("timekeeping","*",[
        "personnels" => $getPer['id'],
        "date" => $date
    ]);

    if(!empty($gettime)){
        // Cập nhật checkout nếu đã có checkin
        $app->update("timekeeping", [
            "checkout" => date("H:i:s", strtotime($createTime))
        ], ["id" => $gettime['id']]);
        $status = 2;
    } else {
        // Thêm bản ghi mới (checkin)
        $app->insert("timekeeping", [
            "personnels" => $getPer['id'],
            "date" => $date,
            "checkin" => date("H:i:s", strtotime($createTime)),
            "date_poster" => date("Y-m-d H:i:s")
        ]);
        $status = 1;
    }

    // Lưu chi tiết chấm công
    $app->insert("timekeeping_details", [
        "personnels"  => $getPer['id'],
        "date"        => date("Y-m-d H:i:s", strtotime($createTime)),
        "notes"       => '',
        "status"      => $status,
        "deleted"     => 0,
        "user"        => 1,
        "date_poster" => date("Y-m-d H:i:s")
    ]);
});
