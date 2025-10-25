<?php
if (!defined('ECLO')) die("Hacking attempt");

// $app->router("/api/webhook-api", ['POST'], function ($vars) use ($app, $jatbi, $setting) {

//     // Gửi phản hồi nhanh để thiết bị không timeout
//     http_response_code(200);
//     echo json_encode(['status' => '200', 'content' => "success", 'code' => '000', 'success' => true]);
//     if (function_exists('fastcgi_finish_request')) {
//         fastcgi_finish_request();
//     }

//     $input = file_get_contents("php://input");
//     $decoded = json_decode($input, true);
//     if (!$decoded) parse_str($input, $decoded);
//     if (!$decoded) exit;

//     $id          = $decoded['recordId'] ?? null;
//     $sn          = $decoded['personSn'] ?? null;
//     $personName  = isset($decoded['personName']) ? urldecode($decoded['personName']) : null;
//     $personType  = $decoded['personType'] ?? null;
//     $createTime  = $decoded['recordTimeStr'] ?? null;
//     $flag        = $decoded['openDoorFlag'] ?? null;
//     $deviceKey   = $decoded['deviceKey'] ?? null;
//     $checkImgBase64 = $decoded['checkImgBase64'] ?? null;

//     if (empty($sn) || empty($createTime)) exit;

//     // Lưu ảnh nếu có
//     $photo_path = '';
//     if (!empty($checkImgBase64)) {
//         $image_data = base64_decode($checkImgBase64);
//         if ($image_data !== false) {
//             if (!is_dir('datas/face_logs')) mkdir('datas/face_logs', 0755, true);
//             $file_name = 'image_' . time() . '_' . $id . '.jpg';
//             $file_path = 'datas/face_logs/' . $file_name;
//             file_put_contents($file_path, $image_data);
//             $photo_path = '/datas/face_logs/' . $file_name;
//         }
//     }

//     // Kiểm tra trùng log
//     $web = $app->get("webhook", "*", [
//         "date_face" => $createTime,
//         "personSn" => $sn,
//         "devicekey" => $deviceKey
//     ]);
//     if (!empty($web)) exit;

//     // Lưu log webhook
//     $app->insert("webhook", [
//         "content"   => [$id, $sn, $personName, $personType, $createTime, $flag, $deviceKey],
//         "personSn"  => $sn,
//         "name"      => $personName,
//         "date"      => date("Y-m-d H:i:s"),
//         "date_face" => $createTime,
//         "photo"     => $photo_path,
//         "devicekey" => $deviceKey
//     ]);

//     // Lấy nhân viên
//     $getPer = $app->get("personnels", "*", [
//         "id" => $sn,
//         "deleted" => 0,
//         "status" => 'A'
//     ]);
//     if (!$getPer) exit;

//     // Kiểm tra dữ liệu chấm công
//     $date = date("Y-m-d", strtotime($createTime));
//     $gettime = $app->get("timekeeping", "*", [
//         "personnels" => $getPer['id'],
//         "date" => $date
//     ]);

//     if (!empty($gettime)) {
//         // Cập nhật checkout nếu đã có checkin
//         $app->update("timekeeping", [
//             "checkout" => date("H:i:s", strtotime($createTime))
//         ], ["id" => $gettime['id']]);
//         $status = 2;
//     } else {
//         // Thêm bản ghi mới (checkin)
//         $app->insert("timekeeping", [
//             "personnels" => $getPer['id'],
//             "date" => $date,
//             "checkin" => date("H:i:s", strtotime($createTime)),
//             "date_poster" => date("Y-m-d H:i:s")
//         ]);
//         $status = 1;
//     }

//     // Lưu chi tiết chấm công
//     $app->insert("timekeeping_details", [
//         "personnels"  => $getPer['id'],
//         "date"        => date("Y-m-d H:i:s", strtotime($createTime)),
//         "notes"       => '',
//         "status"      => $status,
//         "deleted"     => 0,
//         "user"        => 1,
//         "date_poster" => date("Y-m-d H:i:s")
//     ]);
// });

$app->router("/api/webhook-api", ['POST'], function ($vars) use ($app, $jatbi, $setting) {

    // Gửi phản hồi nhanh để thiết bị không timeout
    http_response_code(200);
    echo json_encode(['status' => '200', 'content' => "success", 'code' => '000', 'success' => true]);
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

    $input = file_get_contents("php://input");
    $decoded = json_decode($input, true);
    if (!$decoded) parse_str($input, $decoded);
    if (!$decoded) exit;

    $id         = $decoded['recordId'] ?? null;
    $sn         = $decoded['personSn'] ?? null; // Corresponds to personnels.id
    $personName = isset($decoded['personName']) ? urldecode($decoded['personName']) : null;
    $personType = $decoded['personType'] ?? null;
    $createTime = $decoded['recordTimeStr'] ?? null; // Format like '2025-10-26 08:05:30'
    $flag       = $decoded['openDoorFlag'] ?? null;
    $deviceKey  = $decoded['deviceKey'] ?? null;
    $checkImgBase64 = $decoded['checkImgBase64'] ?? null;
    $current_user_id = 1; // Default system user ID? Or fetch based on API key? Define this.

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

    // Kiểm tra trùng log webhook
    $web = $app->get("webhook", "id", [
        "date_face" => $createTime,
        "personSn" => $sn,
        "devicekey" => $deviceKey
    ]);
    if ($web) exit; // Exit if already processed

    // Lưu log webhook
    $webhook_insert_data = [
        "content"   => json_encode($decoded), // Store full payload as JSON string
        "personSn"  => $sn,
        "name"      => $personName,
        "date"      => date("Y-m-d H:i:s"),
        "date_face" => $createTime,
        "photo"     => $photo_path,
        "devicekey" => $deviceKey
    ];
    $app->insert("webhook", $webhook_insert_data);


    // Lấy nhân viên
    $getPer = $app->get("personnels", "*", [
        "id" => $sn,
        "deleted" => 0,
        "status" => 'A'
    ]);
    if (!$getPer) exit; // Exit if personnel not found or inactive

    // --- Process Timekeeping and Late/Early ---
    $date = date("Y-m-d", strtotime($createTime));
    $time = date("H:i:s", strtotime($createTime));
    $time_ts = strtotime($createTime); // Use the original timestamp string for accuracy
    $timekeeping_id = 0; // Initialize timekeeping record ID
    $status = 0; // 1 = checkin, 2 = checkout

    $gettime = $app->get("timekeeping", "*", [
        "personnels" => $getPer['id'],
        "date" => $date
    ]);

    if (!empty($gettime)) {
        // --- This is potentially a CHECKOUT ---
        if (empty($gettime['checkout']) || $time_ts > strtotime($date . ' ' . $gettime['checkout'])) {
            $app->update("timekeeping", [
                "checkout" => $time
            ], ["id" => $gettime['id']]);
            $status = 2; // Checkout
            $timekeeping_id = $gettime['id'];
        } else {
            // Redundant checkout or earlier than existing? Ignore.
            // Consider logging this scenario if needed for debugging.
            exit;
        }
    } else {
        // --- This is a CHECKIN ---
        $app->insert("timekeeping", [
            "personnels" => $getPer['id'],
            "date" => $date,
            "checkin" => $time,
            "date_poster" => date("Y-m-d H:i:s")
        ]);
        $timekeeping_id = $app->id(); // Get the ID of the newly inserted record
        $status = 1; // Checkin
    }

    // --- Insert Timekeeping Detail Log ---
    if ($status > 0) {
        $app->insert("timekeeping_details", [
            "personnels"  => $getPer['id'],
            "date"        => $createTime, // Use the exact timestamp from device
            "notes"       => 'Device: ' . $deviceKey,
            "status"      => $status, // 1=checkin, 2=checkout
            "deleted"     => 0,
            "user"        => $current_user_id,
            "date_poster" => date("Y-m-d H:i:s")
        ]);
    } else {
        exit; // Exit if no valid status
    }

    // --- Calculate and Insert Late/Early Record ---
    if ($timekeeping_id > 0) {
        $day_late_minutes = 0;
        $day_early_minutes = 0;

        // Find the schedule for this employee on this date
        $schedule = null;
        $active_roster = $app->get("rosters", ["timework"], [
            "personnels" => $getPer['id'],
            "date[<=]" => $date,
            "deleted" => 0,
            "ORDER" => ["date" => "DESC"]
        ]);
        if ($active_roster && $active_roster['timework']) {
            $week_day = date('N', strtotime($date));
            $schedule = $app->get("timework_details", ["time_from", "time_to", "off"], [
                "timework" => $active_roster['timework'],
                "week" => $week_day,
                "deleted" => 0
            ]);
        }

        // Check against schedule only if a valid schedule exists and it's not an OFF day
        if ($schedule && $schedule['off'] == 0) {
            $expected_start_ts = strtotime("$date {$schedule['time_from']}");
            $expected_end_ts = strtotime("$date {$schedule['time_to']}");

            if ($status == 1) { // This was a CHECKIN
                if ($time_ts > $expected_start_ts) { // Check if later than expected start
                    $day_late_minutes = ($time_ts - $expected_start_ts) / 60;
                }
            } elseif ($status == 2) { // This was a CHECKOUT
                if ($time_ts < $expected_end_ts) { // Check if earlier than expected end
                    $day_early_minutes = ($expected_end_ts - $time_ts) / 60;
                }
            }

            // --- START INSERT LATE/EARLY (Minimal fields) ---
            $incident_date_time = $createTime; // Use the timestamp from the device

            // Record Late Arrival
            if ($day_late_minutes > 0) {
                // Optional: Check if a late record for this timekeeping ID already exists
                $exists_late = $app->has("timekeeping_time_late", ["timekeeping" => $timekeeping_id, "type" => 1]);
                if (!$exists_late) {
                    $app->insert("timekeeping_time_late", [
                        "type" => 1, // Late
                        "personnels" => $getPer['id'],
                        "date" => $incident_date_time, // Date and Time of the incident
                        "time_late" => (int)round($day_late_minutes), // Minutes late
                        "timekeeping" => $timekeeping_id, // Link to the timekeeping record
                        // Minimal required fields added below based on table structure
                        "date_poster" => date("Y-m-d H:i:s"),
                        "user" => $current_user_id, // Assuming a system user ID or fetched ID
                        "status" => 0, // Default status, e.g., 0 = unprocessed
                        "deleted" => 0
                    ]);
                }
            }

            // Record Early Departure
            if ($day_early_minutes > 0) {
                // Optional: Check if an early record for this timekeeping ID already exists
                $exists_early = $app->has("timekeeping_time_late", ["timekeeping" => $timekeeping_id, "type" => 2]);
                if (!$exists_early) {
                    $app->insert("timekeeping_time_late", [
                        "type" => 2, // Early
                        "personnels" => $getPer['id'],
                        "date" => $incident_date_time, // Date and Time of the incident
                        "time_late" => (int)round($day_early_minutes), // Minutes early
                        "timekeeping" => $timekeeping_id, // Link to the timekeeping record
                        // Minimal required fields added below
                        "date_poster" => date("Y-m-d H:i:s"),
                        "user" => $current_user_id,
                        "status" => 0,
                        "deleted" => 0
                    ]);
                }
            }
            // --- END INSERT LATE/EARLY ---

        } // End if ($schedule && $schedule['off'] == 0)
    } // End if ($timekeeping_id > 0)

    // Final exit after all background processing is done
    exit;
});
