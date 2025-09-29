<?php
	if (!defined('ECLO')) die("Hacking attempt");
    $app->group(($setting['manager']==''?'':$setting['manager']),function($app) use ($jatbi,$setting){
        $app->router('/login','GET', function($vars) use ($app,$jatbi,$setting) {
            if(!$app->getSession("accounts")){
                $vars['templates'] = 'login';
                echo $app->render($setting['template'].'/pages/login.html', $vars);
            }
            else {
                $app->redirect('/');
            }
        });
    	$app->router('/login', 'POST', function($vars) use ($app, $jatbi,$setting) {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $jatbi->verifyCsrfToken();
            if($app->xss($_POST['email']) && $app->xss($_POST['password'])){
                $data = $app->get("accounts","*",[
                    "OR"=>[
                        "email"     => $app->xss($_POST['email']),
                        "account"   => $app->xss($_POST['email']),
                    ],
                    "status"=>"A",
                    "deleted"=>0
                ]);
                if(isset($data) && password_verify($app->xss($_POST['password']), $data['password'])) {
                    $gettoken = $app->randomString(256);
                    $payload = [
                        "ip"        => $app->xss($_SERVER['REMOTE_ADDR']),
                        "id"        => $data['active'],
                        "email"     => $data['email'],
                        "token"     => $gettoken,
                        "agent"     => $_SERVER["HTTP_USER_AGENT"],
                    ];
                    $token = $app->addJWT($payload);
                    $getLogins = $app->get("accounts_login","*",[
                        "accounts"  => $data['id'],
                        "agent"     => $payload['agent'],
                        "deleted"   => 0,
                    ]);
                    if($getLogins>1){
                        $app->update("accounts_login",[
                            "accounts" => $data['id'],
                            "ip"    =>  $payload['ip'],
                            "token" =>  $payload['token'],
                            "agent" =>  $payload["agent"],
                            "date"  => date("Y-m-d H:i:s"),
                        ],["id"=>$getLogins['id']]);
                    }
                    else {
                        $app->insert("accounts_login",[
                            "accounts" => $data['id'],
                            "ip"    =>  $payload['ip'],
                            "token" =>  $payload['token'],
                            "agent" =>  $payload["agent"],
                            "date"  => date("Y-m-d H:i:s"),
                        ]);
                    }
                    $app->setSession('accounts',[
                        "id" => $data['id'],
                        "name" => $data['name'],
                        "agent" => $payload['agent'],
                        "token" => $payload['token'],
                        "active" => $data['active'],
                    ]);

                    // Thêm logic gán $_SESSION['stores']
                    // $ListStoreCount = $app->count("stores", "id", ["deleted" => 0, "status" => 'A']);
                    // if ($ListStoreCount > 1) {
                    //     if ($data['stores'] == "" || count(unserialize($data['stores'])) > 1) {
                    //         $app->setSession('stores', 0);
                    //     } else {
                    //         $app->setSession('stores', unserialize($data['stores'])[0]);
                    //     }
                    // } else {
                    //     $app->setSession('stores', $app->get("stores", "id", ["deleted" => 0, "status" => 'A']));
                    // }
                    // $stores ="";
                    $ListStoreCount = $app->count("stores", "id", ["deleted" => 0, "status" => 'A']);
                    if ($ListStoreCount > 1) {
                        if ($data['stores'] == "") {
                            $stores = $app->select("stores", ["id(value)", "name(text)", "code"], ["deleted" => 0, "status" => 'A']);
                        }else if(count(unserialize($data['stores'])) > 1){
                            $store_id = unserialize($data['stores']);
                            $stores = $app->select("stores", ["id(value)", "name(text)", "code"], ["id" => $store_id, "deleted" => 0, "status" => 'A']);
                        }
                         else {
                            $store_id = unserialize($data['stores'])[0];
                            $stores = [$app->get("stores", ["id(value)", "name(text)", "code"], ["id" => $store_id, "deleted" => 0, "status" => 'A'])];
                        }
                    } else {
                        $stores = [$app->get("stores", ["id(value)", "name(text)", "code"], ["deleted" => 0, "status" => 'A'])];
                    }
                    // Lưu stores vào cookie dưới dạng JSON
                    $stores_json = json_encode($stores);
                    $app->setCookie('stores', $stores_json, time() + $setting['cookie'], '/');

                    if($app->xss($_POST['remember'] ?? '' )){
                        $app->setCookie('token', $token,time()+$setting['cookie'],'/');
                    }
                    echo json_encode(['status' => 'success','content' => $jatbi->lang('Đăng nhập thành công')]);
                    $payload['did'] = $app->getCookie('did');
                    $jatbi->logs('accounts','login',$payload);
                }
                else {
                    echo json_encode(['status' => 'error','content' => $jatbi->lang('Tài khoản hoặc mật khẩu không đúng')]);
                }
            }
            else {
                echo json_encode(['status' => 'error','content' => $jatbi->lang('Vui lòng không để trống')]);
            }
        });
        $app->router('/change-stores/{id}', 'GET', function($vars) use ($app, $jatbi, $setting) {
            // Lấy ID cửa hàng từ vars
            $id = $vars['id'] ?? '0';

            // Lấy thông tin tài khoản
            $account = $app->get("accounts", "*", [
                "id" => $app->getSession("accounts")['id'] ?? 0,
                "deleted" => 0,
                "status" => "A",
            ]);

            // Lấy danh sách cửa hàng hợp lệ
            if ($account['stores'] == "") {
                $List_Stores = $app->select("stores", ["id", "name", "code"], ["deleted" => 0, "status" => 'A']);
            } else {
                $store_ids = unserialize($account['stores']);
                $List_Stores = $app->select("stores", ["id", "name", "code"], ["id" => $store_ids, "deleted" => 0, "status" => 'A']);
            }
            

            // Kiểm tra ID cửa hàng có hợp lệ
            $is_valid = ($id == '0');
            foreach ($List_Stores as $store) {
                if ($store['id'] == $id) {
                    $is_valid = true;
                    break;
                }
            }

            if ($is_valid) {
                // Lưu ID cửa hàng được chọn vào cookie
                $app->setCookie('selected_store', $id, time() + $setting['cookie'], '/');

                // Cập nhật danh sách cửa hàng trong cookie
                if ($id == '0') {
                    // Chọn "Tất cả cửa hàng"
                    if ($account['stores'] == "") {
                        $stores = $app->select("stores", ["id(value)", "name(text)", "code"], ["deleted" => 0, "status" => 'A']);
                    } else {
                        $store_ids = unserialize($account['stores']);
                        $stores = $app->select("stores", ["id(value)", "name(text)", "code"], ["id" => $store_ids, "deleted" => 0, "status" => 'A']);
                    }
                } else {
                    // Chọn một cửa hàng cụ thể
                    $stores = [$app->get("stores", ["id(value)", "name(text)", "code"], ["id" => $id, "deleted" => 0, "status" => 'A'])];
                }

                // Lưu stores vào cookie dưới dạng JSON
                $stores_json = json_encode($stores);
                $app->setCookie('stores', $stores_json, time() + $setting['cookie'], '/');
                // Chuyển hướng về trang trước
                $referer = $_SERVER['HTTP_REFERER'] ?? '/';
                $parsedUrl = parse_url($referer);
                $path = $parsedUrl['path'] ?? '/';
                $app->redirect($path);
                
            } else {
                // Trả về lỗi 404 nếu ID không hợp lệ
                $app->response(404);
                die();
            }
        });
        $app->router('/select-store-modal', ['GET'], function ($vars) use ($app, $jatbi, $setting) {
            $vars['title'] = $jatbi->lang("Chọn cửa hàng");

            // Lấy thông tin tài khoản
            $vars['account'] = $app->get("accounts", "*", [
                "id" => $app->getSession("accounts")['id'] ?? 0,
                "deleted" => 0,
                "status" => "A",
            ]);

            // Lấy danh sách cửa hàng hợp lệ
            if ($vars['account']['stores'] == "") {
                $vars['stores'] = $app->select("stores", ["id", "name", "code"], ["deleted" => 0, "status" => 'A']);
            } else {
                $store_ids = unserialize($vars['account']['stores']);
                $vars['stores'] = $app->select("stores", ["id", "name", "code"], ["id" => $store_ids, "deleted" => 0, "status" => 'A']);
            }

            // Lấy cửa hàng đang chọn từ cookie
            $vars['selected_store'] = $app->getCookie('selected_store') ?? '0';

            // Render nội dung modal
            echo $app->render($setting['template'] . '/components/select-store-modal.html', $vars, $jatbi->ajax());
        });

        $app->router('/stores1', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $value = $app->xss($_POST['value']);
            $data = $app->get("stores","*",["id"=>$value,"status"=>'A',"deleted"=>0]);
            if ($data) { // Sửa lỗi $data>1 thành $data
                $Getstore = $app->select("branch",["id(value)","name(text)","code"],["stores"=>$data['id'],"status"=>'A',"deleted"=>0]);
                // Lưu $Getstore vào session
                $_SESSION['branch_receive_options'] = $Getstore;
                // Trả về status để báo thành công
                echo json_encode(['status' => 'success']);
            }
            if(empty($value)){
                unset($_SESSION['branch_receive_options']);
                echo json_encode(['status' => 'success']);
            }
        });

        $app->router("/logout", 'GET', function($vars) use ($app,$setting) {
            $app->deleteSession('accounts');
            $app->deleteCookie('token');
            $app->redirect(($setting['manager']==''?'/':$setting['manager']));
        });
        $app->router("/register", 'GET', function($vars) use ($app, $jatbi,$setting) {
            if(!$app->getSession("accounts")){
                $vars['templates'] = 'register';
                echo $app->render($setting['template'].'/pages/login.html', $vars);
            }
            else {
                $app->redirect('/');
            }
        });
        $app->router("/register", 'POST', function($vars) use ($app, $jatbi,$setting) {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $jatbi->verifyCsrfToken();
            $checkaccount = $app->get("accounts", ["email","date_deleted","deleted","status"],["email"=>$app->xss($_POST['email']),"ORDER"=>["id"=>"DESC"]]);
            $getcode = $app->get("account_code",["code","id"],["email"=>$app->xss($_POST['email']),"type"=>'register',"status"=>0,"date[>=]"=>date("Y-m-d H:i:s",strtotime("-5 minute")),"ORDER"=>["id"=>"DESC"]]);
            $date_deleted = strtotime(date("Y-m-d H:i:s",strtotime($checkaccount['date_deleted']. ' +7 days')));
            $date_now = strtotime(date("Y-m-d H:i:s"));
            if($app->xss($_POST['name'])=='' || $app->xss($_POST['email'])=='' || $app->xss($_POST['password'])=='' || $app->xss($_POST['password-comfirm'])=='' || $app->xss($_POST['email-comfirm'])=='' ){
                $error = ['status'=>'error','content'=>$jatbi->lang('Vui lòng không để trống')];
            }
            elseif($app->xss($_POST['password-comfirm'])!=$app->xss($_POST['password'])){
                $error = ['status'=>'error','content'=>$jatbi->lang('Mật khẩu không khớp')];
            }
            elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                $error = ['status'=>'error','content'=>$jatbi->lang('Email không đúng')];
            }
            elseif($app->xss($_POST['email']) == $checkaccount['email'] && $checkaccount['deleted']==0 && $checkaccount['status']=='A'){
                $error = ['status'=>'error','content'=>$jatbi->lang('Tài khoản đã có người sử dụng')];
            }
            elseif($checkaccount['status']=='D'){
                $error = ['status'=>'error','content'=>$jatbi->lang('Email này đã bị vô hiệu hóa. Vui lòng liên hệ bộ phần kỹ thuật của ELLM')];
            }
            elseif($date_now<$date_deleted && $checkaccount['deleted']==1){
                $error = ['status'=>'error','content'=>$jatbi->lang('Email này đã bị vô hiệu hóa. Vui lòng đợi sau 7 ngày để đăng ký mới')];
            }
            elseif($getcode['code']!=$app->xss($_POST['email-comfirm'])){
                $error = ['status'=>'error','content'=>$jatbi->lang('Mã xác thực không đúng')];
            }
            if (empty($error)) {
                $createuid =  $jatbi->generateRandomNumbers(12);
                $getuid = $app->get("accounts","id",["uid"=>$createuid]);
                if($getuid>0){
                    $uid = $createuid.'1';
                }
                else {
                    $uid = $createuid;
                }
                if($app->getCookie('invite-code')){
                    $getinvite = $app->get("accounts","id",["invite_code"=>$app->xss($app->getCookie('invite-code')),"deleted"=>0,"status"=>'A']);
                    if($getinvite>0){
                        $invite_code = $getinvite;
                    }
                }
                $insert = [
                    "uid"           => $uid,
                    "name"          => $app->xss($_POST['name']),
                    "email"         => $app->xss($_POST['email']),
                    "password"      => password_hash($app->xss($_POST['password']), PASSWORD_DEFAULT),
                    "type"          => 2,
                    "active"        => $jatbi->active(),
                    "avatar"        => 'no-image',
                    "date"          => date('Y-m-d H:i:s'),
                    "login"         => 'register',
                    "status"        => 'A',
                    "invite"        => $invite_code ?? 0,
                    "invite_code"   => $jatbi->generateRandomNumbers(9),
                    "lang"          => $_COOKIE['lang'] ?? 'vi',
                ];
                $app->insert("accounts",$insert);
                $getID = $app->id();
                $app->insert("settings",["account"=>$getID]);
                $directory = 'datas/'.$insert['active'];
                mkdir($directory, 0755, true);
                $imageUrl = 'images/accounts/avatar'.rand(1,10).'.png';
                $handle = $app->upload($imageUrl);
                $path_upload = 'datas/'.$insert['active'].'/images/';
                if (!is_dir($path_upload)) {
                    mkdir($path_upload, 0755, true);
                }
                $path_upload_thumb = 'datas/'.$insert['active'].'/images/thumb';
                if (!is_dir($path_upload_thumb)) {
                    mkdir($path_upload_thumb, 0755, true);
                }
                $newimages = $jatbi->active();
                if ($handle->uploaded) {
                    $handle->allowed        = array('image/*');
                    $handle->file_new_name_body = $newimages;
                    $handle->Process($path_upload);
                    $handle->image_resize   = true;
                    $handle->image_ratio_crop  = true;
                    $handle->image_y        = '200';
                    $handle->image_x        = '200';
                    $handle->allowed        = array('image/*');
                    $handle->file_new_name_body = $newimages;
                    $handle->Process($path_upload_thumb);
                }
                if($handle->processed ){
                    $getimage = 'upload/images/'.$newimages;
                    $data = [
                        "file_src_name" => $handle->file_src_name,
                        "file_src_name_body" => $handle->file_src_name_body,
                        "file_src_name_ext" => $handle->file_src_name_ext,
                        "file_src_pathname" => $handle->file_src_pathname,
                        "file_src_mime" => $handle->file_src_mime,
                        "file_src_size" => $handle->file_src_size,
                        "image_src_x" => $handle->image_src_x,
                        "image_src_y" => $handle->image_src_y,
                        "image_src_pixels" => $handle->image_src_pixels,
                    ];
                    $insert = [
                        "account" => $getID,
                        "type" => "images",
                        "content" => $path_upload.$handle->file_dst_name,
                        "date" => date("Y-m-d H:i:s"),
                        "active" => $newimages,
                        "size" => $data['file_src_size'],
                        "data" => json_encode($data),
                    ];
                    $app->insert("uploads",$insert);
                    $app->update("accounts",["avatar"=>$getimage],["id"=>$getID]);
                }
                $packages = [
                    "account"   => $getID,
                    "price"     => 2000,
                    "total"     => 2000,
                    "watermark" => 1,
                    "api"       => 1,
                    "date"      => $insert['date'],
                ];
                $app->insert("packages",$packages);
                $gettoken = $app->randomString(256);
                $payload = [
                    "ip"        => $app->xss($_SERVER['REMOTE_ADDR']),
                    "id"        => $insert['active'],
                    "email"     => $insert['email'],
                    "token"     => $gettoken,
                    "agent"     => $_SERVER["HTTP_USER_AGENT"],
                ];
                $token = $app->addJWT($payload);
                $getLogins = $app->get("accounts_login","*",[
                    "accounts"  => $getID,
                    "agent"     => $payload['agent'],
                    "deleted"   => 0,
                ]);
                $app->insert("accounts_login",[
                    "accounts" => $getID,
                    "ip"    =>  $payload['ip'],
                    "token" =>  $payload['token'],
                    "agent" =>  $payload["agent"],
                    "date"  => date("Y-m-d H:i:s"),
                ]);
                $app->setSession('accounts',[
                    "id" => $getID,
                    "agent" => $payload['agent'],
                    "token" => $payload['token'],
                    "active" => $insert['active'],
                ]);
                $app->update("account_code",["status"=>1],["id"=>$getcode['id']]);
                $app->setCookie('token', $token);
                $jatbi->notification($getID,$getID,'Chào mừng','Chào mừng bạn đến với ELLM','/action/text/welcome','modal-url');
                if($insert['invite']>0){
                    $jatbi->notification($insert['invite'],$insert['invite'],'Cảm ơn','Cám ơn bạn đã giới thiệu bạn bè của mình cho ELLM.','/action/text/thanks','modal-url');
                }
                $app->deleteCookie('invite-code');
                $jatbi->logs('account','register',$payload);
                echo json_encode(['status' => 'success','content' => $jatbi->lang('Đăng nhập thành công'),'load'=>"true"]);
            }
            else {
                echo json_encode($error);
            }
        });
        $app->router("/email-comfirm", 'POST', function($vars) use ($app, $jatbi,$setting) {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $jatbi->verifyCsrfToken();
            if($app->xss($_POST['email'])==''){
                echo json_encode(['status' => 'error','content' => $jatbi->lang('Vui lòng không để trống')]);
            }
            elseif (!filter_var($app->xss($_POST['email']), FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['status'=>'error','content'=>$jatbi->lang('Email không đúng')]);
            }
            if($app->xss($_POST['email'] && filter_var($app->xss($_POST['email']), FILTER_VALIDATE_EMAIL))){
                $checkaccount = $app->count("accounts", "id",["email"=>$app->xss($_POST['email']),"deleted"=>0]);
                if($checkaccount>0){
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Tài khoản đã có người sử dụng")]);
                }
                else {
                    $code = substr(str_shuffle("0123456789"), 0, 6);
                    try {
                        $mail = $app->Mail([
                            'username' => 'info@ellm.io',
                            'password' => 'obhf udlq gyhp ptwn',
                            'from_email' => 'info@ellm.io',
                            'from_name' => 'ELLM',
                            'host' => 'smtp.gmail.com',
                            'port' => 465,
                            'encryption' => 'smtp',
                        ]);
                        $mail->setFrom('info@ellm.io','No-reply');
                        $mail->addAddress($app->xss($_POST['email']));
                        $mail->CharSet = "utf-8";
                        $mail->isHTML(true);
                        $mail->Subject = $jatbi->lang('ELLM - Mã Xác nhận đăng ký');
                        $mail->Body    = '<div style="padding: 0 19px">
                            <h1>'.$jatbi->lang("Xin chào").'</h1>
                            <h2>'.$jatbi->lang("Chào mừng bạn đến với ELLM").'</h2>
                            <p>'.$jatbi->lang("Mã xác nhận để đăng ký tài khoản của bạn là").': <strong>'.$code.'</strong></p>
                            <p>'.$jatbi->lang("Vui lòng không cung cấp cho người khác.").'</p>
                            <p>'.$jatbi->lang("Cảm ơn bạn đã chọn ELLM làm đối tác để đạt được mục tiêu của mình. Chúng tôi mong được hợp tác với bạn và giúp bạn thành công.").'</p>
                        </div>';
                        $mail->send();
                        echo json_encode(['status'=>'success','content'=>$jatbi->lang("Đã gửi mã xác thực. Vui lòng kiểm tra email của bạn")]);
                        $app->insert("account_code",["email"=>$app->xss($_POST['email']),"code"=>$code,"date"=>date("Y-m-d H:i:s"),"type"=>'register']);
                    } catch (Exception $e) {
                        echo $jatbi->lang("Có lỗi xảy ra vui lòng thử lại");
                    }
                }
            }
        });
        $app->router("/forgot-password", 'GET', function($vars) use ($app, $jatbi,$setting) {
            if(!$app->getSession("accounts")){
                $vars['templates'] = 'forgot';
                echo $app->render($setting['template'].'/pages/login.html', $vars);
            }
            else {
                $app->redirect('/');
            }
        });
    });
    function setStoreCookie($app, $data, $cookieTime = 2592000) {
        // Đếm số lượng cửa hàng đang hoạt động
        $storeCount = $app->count("stores", "id", ["deleted" => 0, "status" => 'A']);

        if ($storeCount > 1) {
            if (empty($data['stores']) || count(unserialize($data['stores'])) > 1) {
                $app->setCookie('stores', 0, time() + $cookieTime, '/');
            } else {
                $storeList = unserialize($data['stores']);
                $app->setCookie('stores', $storeList[0], time() + $cookieTime, '/');
            }
        } else {
            $storeId = $app->get("stores", "id", ["deleted" => 0, "status" => 'A']);
            $app->setCookie('stores', $storeId, time() + $cookieTime, '/');
        }
    }

    // $app->router("/build-assets", 'GET', function($vars) use ($app, $jatbi,$setting) {
    //     $commonJs = $app->getValueData('commonJs');
    //     $commonCss = $app->getValueData('commonCss');
    //     $app->minifyCSS($commonCss,'css/style.bundle.css');
    //     $app->minifyJS($commonJs,'js/main.bundle.js');
    //     $jsVersion = '';
    //     $cssVersion = '';
    //     $jatbi->updateVersionInFile($setting['template'].'/components/footer.html', 'js', $jsVersion);
    //     $jatbi->updateVersionInFile($setting['template'].'/components/header.html', 'css', $cssVersion);
    //     $log = [];
    //     $logFile = 'version.json';
    //     if (file_exists($logFile)) {
    //         $json = file_get_contents($logFile);
    //         $log = json_decode($json, true);
    //         if (!is_array($log)) $log = [];
    //     }
    //     $log[] = [
    //         'time' => date('Y-m-d H:i:s'),
    //         'js' => $jsVersion,
    //         'css' => $cssVersion
    //     ];
    //     file_put_contents($logFile, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    //     // $app->redirect($_SERVER['HTTP_REFERER']);

    // })->middleware('login');
?>