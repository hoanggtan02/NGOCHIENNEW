<?php
    if (!defined('ECLO')) die("Hacking attempt");
    $app->group($setting['manager']."/admin",function($app) use ($jatbi,$setting){

        $app->router("/search", ['GET', 'POST'], function($vars) use ($app, $jatbi,$setting) {
            $vars['title'] = $jatbi->lang("Tìm kiếm");
            if ($app->method() === 'GET') {
                echo $app->render($setting['template'].'/admin/search.html', $vars, $jatbi->ajax());
            }
        });

        $app->router("/plugins", ['GET','POST'], function($vars) use ($app, $jatbi,$setting) {
            $vars['title'] = $jatbi->lang("Tiện ích mở rộng");
            if($app->method()==='GET'){
                echo $app->render($setting['template'].'/admin/plugins.html', $vars);
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
                $where = [
                    "AND" => [
                        "OR" => [
                            "name[~]" => $searchValue,
                        ],
                        "status[<>]" => $status,
                        "deleted" => 0,
                    ],
                    "LIMIT" => [$start, $length],
                    "ORDER" => [$orderName => strtoupper($orderDir)]
                ];
                $count = $app->count("plugins",["AND" => $where['AND']]);
                $app->select("plugins", "*", $where, function ($data) use (&$datas, $jatbi,$app) {
                    $getConfig = json_decode($data['config'], true); // Chuyển JSON thành mảng
                    $config = [];
                    foreach ($getConfig as $key => $value) {
                        $config[] = $key.': '.$value;
                    }
                    $output = implode("\n", $config);
                    if($data['install']==0){
                        $install = [
                            'type' => 'button',
                            'name' => $jatbi->lang("Cài đặt"),
                            'permission' => ['plugins.add'],
                            'icon' => '<i class="ti me-2 ti-settings-cog"></i>',
                            'action' => ['data-url' => '/admin/plugins-install/'.$data['active'], 'data-action' => 'modal']
                        ];
                    }
                    $datas[] = [
                        "checkbox" => $app->component("box",["data"=>$data['active']]),
                        "name" => $data['name'] .' <br><small>'.$data['uid'].'</small>',
                        "version" => $data['version'],
                        "author" => $data['author'],
                        "requires" => $data['requires'],
                        "description" => $data['description'],
                        "date" => $jatbi->datetime($data['date']),
                        "install" => $data['install'] == 0 ? '<span class="text-danger fw-bold">'.$jatbi->lang("Chưa cài đặt").'</span>':'<span class="text-success fw-bold">'.$jatbi->lang("Đã cài").'</span>',
                        "status" => $app->component("status",["url"=>"/admin/plugins-status/".$data['active'],"data"=>$data['status'],"permission"=>['plugins.edit']]),
                        "action" => $app->component("action",[
                            "button" => [
                                $install ?? [],
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Cập nhật"),
                                    'permission' => ['plugins.edit'],
                                    'icon' => '<i class="ti me-2 ti-rotate-rectangle"></i>',
                                    'action' => ['data-url' => '/admin/plugins-update/'.$data['active'], 'data-action' => 'modal']
                                ],
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Sửa"),
                                    'permission' => ['plugins.edit'],
                                    'icon' => '<i class="ti me-2 ti-edit"></i>',
                                    'action' => ['data-url' => '/admin/plugins-edit/'.$data['active'], 'data-action' => 'modal']
                                ],
                                [
                                    'type' => 'button',
                                    'name' => $jatbi->lang("Xóa"),
                                    'permission' => ['plugins.deleted'],
                                    'icon' => '<i class="ti me-2 ti-trash"></i>',
                                    'action' => ['data-url' => '/admin/plugins-deleted?box='.$data['active'], 'data-action' => 'modal']
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
        })->setPermissions(['plugins']);

        $app->router("/plugins-add", ['GET', 'POST'], function($vars) use ($app, $jatbi,$setting) {
            $vars['title'] = $jatbi->lang("Thêm tiện ích mở rộng");
            if ($app->method() === 'GET') {
                $vars['data'] = [
                    "status" => 'A',
                ];
                echo $app->render($setting['template'].'/admin/plugins-post.html', $vars, $jatbi->ajax());
            } elseif ($app->method() === 'POST') {
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng chọn tệp plugin để tải lên.")]);
                    return;
                }
                $pluginFile = $_FILES['file'];
                $filename = $jatbi->active();
                $uploadDir = $setting['plugins'].'/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $handle = $app->upload($pluginFile);
                if ($handle->file_src_mime !== 'application/zip') {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Chỉ hỗ trợ tệp zip.")]);
                    return;
                }
                $path_upload = $uploadDir . basename($pluginFile['name']);
                $handle->allowed        = array('application/zip');
                $handle->Process($uploadDir);
                if ($handle->uploaded) {
                    $path_zip = $handle->file_dst_pathname;
                    $zip = new ZipArchive;
                    if ($zip->open($path_zip) === TRUE) {
                        $extractPath = $uploadDir . $filename;
                        if (!file_exists($extractPath)) {
                            mkdir($extractPath, 0755, true);
                        }
                        $zip->extractTo($extractPath);
                        $zip->close();
                        unlink($path_zip);
                        $configFile = $extractPath . '/config.json';
                        if (file_exists($configFile)) {
                            $config = json_decode(file_get_contents($configFile), true);
                        }
                        $getPlugin = $app->get("plugins",["id"],["uid"=>$config['id']]);
                        if($getPlugin){
                            echo json_encode(["status" => "error", "content" => $jatbi->lang("Mã tiện ích đã có vui lòng thay đổi hoặc cập nhật")]);
                            $jatbi->deleteFolder($extractPath);
                            return;
                        }
                        $pluginFolder = $uploadDir . $config['id'];
                        rename($extractPath, $pluginFolder);
                        $insert = [
                            "name"    => $config['name'],
                            "uid"    => $config['id'],
                            "version"    => $config['version'],
                            "requires"    => $config['requires'],
                            "description"    => $config['description'],
                            "author"    => $config['author'],
                            "install"    => $config['install'] == 'true' ? 1 : 0,
                            "status"  => 'A',
                            "config"  => json_encode($config),
                            "plugins" => $config['id'],
                            "date"    => date("Y-m-d H:i:s"),
                            "active"  => $jatbi->active(),
                        ];
                        $app->insert("plugins", $insert);
                        echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
                        $jatbi->logs('plugins', 'plugins-add', $insert);
                    } else {
                        echo json_encode(["status" => "error", "content" => $jatbi->lang("Giải nén tệp thất bại.")]);
                    }
                } else {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Tải lên tệp thất bại.")]);
                }
            }
        })->setPermissions(['plugins.add']);

        $app->router("/plugins-edit/{id}", ['GET','POST'], function($vars) use ($app, $jatbi,$setting) {
            $vars['title'] = $jatbi->lang("Sửa tiện ích mở rộng");
            if($app->method()==='GET'){
                $vars['data'] = $app->get("plugins","*",["active"=>$vars['id'],"deleted"=>0]);
                if($vars['data']>1){
                    echo $app->render($setting['template'].'/admin/plugins-edit.html', $vars, $jatbi->ajax());
                }
                else {
                    echo $app->render($setting['template'].'/pages/error.html', $vars, $jatbi->ajax());
                }
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $jatbi->verifyCsrfToken();
                $data = $app->get("plugins","*",["active"=>$vars['id'],"deleted"=>0]);
                if($data>1){
                    if($app->xss($_POST['name'])=='' || $app->xss($_POST['status'])==''){
                        $error = ["status"=>"error","content"=>$jatbi->lang("Vui lòng không để trống")];
                    }
                    if(empty($error)){
                        $insert = [
                            "name"          => $app->xss($_POST['name']),
                            "status"        => $app->xss($_POST['status']),
                            "version"       => $app->xss($_POST['version']),
                            "author"        => $app->xss($_POST['author']),
                            "position"      => $app->xss($_POST['position']),
                            "description"   => $app->xss($_POST['description']),
                        ];
                        $app->update("plugins",$insert,["id"=>$data['id']]);
                        echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                        $jatbi->logs('plugins','plugins-edit',$insert);
                    }
                    else {
                        echo json_encode($error);
                    }
                }
                else {
                    echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
                }
            }
        })->setPermissions(['plugins.edit']);

        $app->router("/plugins-update/{id}", ['GET', 'POST'], function($vars) use ($app, $jatbi, $setting) {
            $vars['title'] = $jatbi->lang("Cập nhật tiện ích mở rộng");

            if ($app->method() === 'GET') {
                $vars['data'] = $app->get("plugins", "*", ["active" => $vars['id']]);
                if ($vars['data'] > 1) {
                    echo $app->render($setting['template'] . '/admin/plugins-post.html', $vars, $jatbi->ajax());
                } else {
                    echo $app->render($setting['template'] . '/error.html', $vars, $jatbi->ajax());
                }
            } elseif ($app->method() === 'POST') {
                $app->header([
                    'Content-Type' => 'application/json',
                ]);

                $data = $app->get("plugins", "*", ["active" => $vars['id']]);
                if ($data > 1) {
                    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                        echo json_encode(["status" => "error", "content" => $jatbi->lang("Vui lòng chọn tệp plugin để tải lên.")]);
                        return;
                    }

                    $pluginFile = $_FILES['file'];
                    $uploadDir = $setting['plugins'] . '/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $handle = $app->upload($pluginFile);
                    if ($handle->file_src_mime !== 'application/zip') {
                        echo json_encode(["status" => "error", "content" => $jatbi->lang("Chỉ hỗ trợ tệp zip.")]);
                        return;
                    }

                    $handle->allowed = ['application/zip'];
                    $handle->Process($uploadDir);

                    if ($handle->uploaded) {
                        $path_zip = $handle->file_dst_pathname;
                        $zip = new ZipArchive;
                        if ($zip->open($path_zip) === TRUE) {
                            $pluginFolder = $uploadDir . $data['plugins'];

                            // Xóa thư mục cũ trước khi cập nhật
                            $jatbi->deleteFolder($pluginFolder);

                            // Tạo lại thư mục và giải nén
                            if (!file_exists($pluginFolder)) {
                                mkdir($pluginFolder, 0755, true);
                            }

                            $zip->extractTo($pluginFolder);
                            $zip->close();
                            unlink($path_zip);

                            // Đọc lại config mới
                            $configFile = $pluginFolder . '/config.json';
                            if (!file_exists($configFile)) {
                                echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy tệp cấu hình.")]);
                                return;
                            }

                            $config = json_decode(file_get_contents($configFile), true);
                            if (!$config || empty($config['id'])) {
                                echo json_encode(["status" => "error", "content" => $jatbi->lang("Cấu hình plugin không hợp lệ.")]);
                                return;
                            }

                            // Cập nhật lại dữ liệu plugin
                            $update = [
                                "name"        => $config['name'],
                                "uid"         => $config['id'],
                                "version"     => $config['version'],
                                "requires"    => $config['requires'],
                                "description" => $config['description'],
                                "author"      => $config['author'],
                                "status"      => 'A',
                                "config"      => json_encode($config),
                                "plugins"     => $data['plugins'],
                                "date"        => date("Y-m-d H:i:s"),
                                "active"      => $jatbi->active(),
                            ];

                            $app->update("plugins", $update, ["id" => $data['id']]);
                            echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cập nhật thành công")]);
                            $jatbi->logs('plugins', 'plugins-update', $update);
                        } else {
                            echo json_encode(["status" => "error", "content" => $jatbi->lang("Giải nén tệp thất bại.")]);
                        }
                    } else {
                        echo json_encode(["status" => "error", "content" => $jatbi->lang("Tải lên tệp thất bại.")]);
                    }
                } else {
                    echo json_encode(["status" => "error", "content" => $jatbi->lang("Không tìm thấy dữ liệu")]);
                }
            }
        })->setPermissions(['plugins.add']);

        $app->router("/plugins-install/{id}", ['GET', 'POST'], function($vars) use ($app, $jatbi,$setting) {
            $vars['title'] = $jatbi->lang("Cài tiện ích mở rộng");
            if ($app->method() === 'GET') {
                $vars['data'] = $app->get("plugins","*",["active"=>$vars['id'],'install' => 0]);
                if($vars['data']>1){
                    echo $app->render($setting['template'].'/admin/plugins-install.html', $vars, $jatbi->ajax());
                }
                else {
                    echo $app->render($setting['template'].'/pages/error.html', $vars, $jatbi->ajax());
                }
            } 
            elseif ($app->method() === 'POST') {
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $data = $app->get("plugins","*",["active"=>$vars['id'], 'install' => 0]);
                if($data>1){
                    $app->update("plugins", ["install" => 1],["id"=>$data['id']]);
                    echo json_encode(['status' => 'success', 'content' => $jatbi->lang("Cài đặt thành công")]);
                }
                else {
                    echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
                }
            }
        })->setPermissions(['plugins.add']);

        $app->router("/plugins-status/{id}", 'POST', function($vars) use ($app, $jatbi,$setting) {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $data = $app->get("plugins","*",["active"=>$vars['id'],"deleted"=>0]);
            if($data>1){
                if($data>1){
                    if($data['status']==='A'){
                        $status = "D";
                    } 
                    elseif($data['status']==='D'){
                        $status = "A";
                    }
                    $app->update("plugins",["status"=>$status],["id"=>$data['id']]);
                    $jatbi->logs('plugins','plugins-status',$data);
                    echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Cập nhật thất bại"),]);
                }
            }
            else {
                echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
            }
        })->setPermissions(['plugins.edit']);

        $app->router("/plugins-deleted", ['GET','POST'], function($vars) use ($app, $jatbi,$setting) {
            $vars['title'] = $jatbi->lang("Xóa Tài khoản");
            if($app->method()==='GET'){
                echo $app->render($setting['template'].'/common/deleted.html', $vars, $jatbi->ajax());
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $boxid = explode(',', $app->xss($_GET['box']));
                $datas = $app->select("plugins","*",["active"=>$boxid,"deleted"=>0]);
                if(count($datas)>0){
                    foreach($datas as $data){
                        $app->delete("plugins",["id"=>$data['id']]);
                        $jatbi->deleteFolder($data['plugins']);
                    }
                    $jatbi->logs('plugins','plugins-deleted',$datas);
                    echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra")]);
                }
            }
        })->setPermissions(['blockip.deleted']);

        $app->router("/blockip", ['GET','POST'], function($vars) use ($app, $jatbi,$setting) {
            $vars['title'] = $jatbi->lang("Chặn truy cập");
            if($app->method()==='GET'){
                echo $app->render($setting['template'].'/admin/blockip.html', $vars);
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
                $where = [
                    "AND" => [
                        "OR" => [
                            "ip[~]" => $searchValue,
                            "notes[~]" => $searchValue,
                        ],
                        "status[<>]" => $status,
                        "deleted" => 0,
                    ],
                    "LIMIT" => [$start, $length],
                    "ORDER" => [$orderName => strtoupper($orderDir)]
                ];
                $count = $app->count("blockip",["AND" => $where['AND']]);
                $app->select("blockip", "*", $where, function ($data) use (&$datas, $jatbi,$app) {
                    $datas[] = [
                        "checkbox" => $app->component("box",["data"=>$data['active']]),
                        "ip" => $data['ip'],
                        "date" => $jatbi->datetime($data['date']),
                        "status" => $app->component("status",["url"=>"/admin/blockip-status/".$data['active'],"data"=>$data['status'],"permission"=>['blockip.edit']]),
                        "action" => $app->component("action",[
                                    "button" => [
                                        [
                                            'type' => 'button',
                                            'name' => $jatbi->lang("Sửa"),
                                            'permission' => ['blockip.edit'],
                                            'action' => ['data-url' => '/admin/blockip-edit/'.$data['active'], 'data-action' => 'modal']
                                        ],
                                        [
                                            'type' => 'button',
                                            'name' => $jatbi->lang("Xóa"),
                                            'permission' => ['blockip.deleted'],
                                            'action' => ['data-url' => '/admin/blockip-deleted?box='.$data['active'], 'data-action' => 'modal']
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
        })->setPermissions(['blockip']);

        $app->router("/blockip-add", ['GET','POST'], function($vars) use ($app, $jatbi,$setting) {
            $vars['title'] = $jatbi->lang("Thêm Chặn truy cập");
            if($app->method()==='GET'){
                $vars['data'] = [
                    "status" => 'A',
                ];
                echo $app->render($setting['template'].'/admin/blockip-post.html', $vars, $jatbi->ajax());
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $jatbi->verifyCsrfToken();
                if($app->xss($_POST['ip'])=='' || $app->xss($_POST['status'])==''){
                    $error = ["status"=>"error","content"=>$jatbi->lang("Vui lòng không để trống")];
                }
                if(empty($error)){
                    $insert = [
                        "ip"          => $app->xss($_POST['ip']),
                        "status"        => $app->xss($_POST['status']),
                        "notes"         => $app->xss($_POST['notes']),
                        "date"          => date("Y-m-d H:i:s"),
                        "active"        => $jatbi->active(),
                    ];
                    $app->insert("blockip",$insert);
                    echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                    $jatbi->logs('blockip','blockip-add',$insert);
                }
                else {
                    echo json_encode($error);
                }
            }
        })->setPermissions(['blockip.add']);

        $app->router("/blockip-edit/{id}", ['GET','POST'], function($vars) use ($app, $jatbi,$setting) {
            $vars['title'] = $jatbi->lang("Sửa Chặn truy cập");
            if($app->method()==='GET'){
                $vars['data'] = $app->get("blockip","*",["active"=>$vars['id'],"deleted"=>0]);
                if($vars['data']>1){
                    echo $app->render($setting['template'].'/admin/blockip-post.html', $vars, $jatbi->ajax());
                }
                else {
                    echo $app->render($setting['template'].'/pages/error.html', $vars, $jatbi->ajax());
                }
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $jatbi->verifyCsrfToken();
                $data = $app->get("blockip","*",["active"=>$vars['id'],"deleted"=>0]);
                if($data>1){
                    if($app->xss($_POST['ip'])=='' || $app->xss($_POST['status'])==''){
                        $error = ["status"=>"error","content"=>$jatbi->lang("Vui lòng không để trống")];
                    }
                    if(empty($error)){
                        $insert = [
                            "ip"          => $app->xss($_POST['ip']),
                            "status"        => $app->xss($_POST['status']),
                            "notes"         => $app->xss($_POST['notes']),
                            "date"          => date("Y-m-d H:i:s"),
                        ];
                        $app->update("blockip",$insert,["id"=>$data['id']]);
                        echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                        $jatbi->logs('blockip','blockip-edit',$insert);
                    }
                    else {
                        echo json_encode($error);
                    }
                }
                else {
                    echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
                }
            }
        })->setPermissions(['blockip.edit']);

        $app->router("/blockip-status/{id}", 'POST', function($vars) use ($app, $jatbi,$setting) {
            $app->header([
                'Content-Type' => 'application/json',
            ]);
            $data = $app->get("blockip","*",["active"=>$vars['id'],"deleted"=>0]);
            if($data>1){
                if($data>1){
                    if($data['status']==='A'){
                        $status = "D";
                    } 
                    elseif($data['status']==='D'){
                        $status = "A";
                    }
                    $app->update("blockip",["status"=>$status],["id"=>$data['id']]);
                    $jatbi->logs('blockip','blockip-status',$data);
                    echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Cập nhật thất bại"),]);
                }
            }
            else {
                echo json_encode(["status"=>"error","content"=>$jatbi->lang("Không tìm thấy dữ liệu")]);
            }
        })->setPermissions(['blockip.edit']);

        $app->router("/blockip-deleted", ['GET','POST'], function($vars) use ($app, $jatbi,$setting) {
            $vars['title'] = $jatbi->lang("Xóa Tài khoản");
            if($app->method()==='GET'){
                echo $app->render($setting['template'].'/common/deleted.html', $vars, $jatbi->ajax());
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $boxid = explode(',', $app->xss($_GET['box']));
                $datas = $app->select("blockip","*",["active"=>$boxid,"deleted"=>0]);
                if(count($datas)>0){
                    foreach($datas as $data){
                        $app->update("blockip",["deleted"=> 1],["id"=>$data['id']]);
                        $name[] = $data['ip'];
                    }
                    $jatbi->logs('blockip','blockip-deleted',$datas);
                    $jatbi->trash('/admin/blockip-restore',"Chặn truy cập với ip: ".implode(', ',$name),["database"=>'blockip',"data"=>$boxid]);
                    echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra")]);
                }
            }
        })->setPermissions(['blockip.deleted']);

        $app->router("/blockip-restore/{id}", ['GET','POST'], function($vars) use ($app, $jatbi,$setting) {
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
                        $app->update("blockip",["deleted"=>0],["active"=>$active]);
                    }
                    $app->delete("trashs",["id"=>$trash['id']]);
                    $jatbi->logs('blockip','blockip-restore',$datas);
                    echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra")]);
                }
            }
        })->setPermissions(['blockip.deleted']);

        $app->router("/logs", ['GET','POST'], function($vars) use ($app, $jatbi,$setting) {
            $vars['title'] = $jatbi->lang("Nhật ký");
            if($app->method()==='GET'){
                echo $app->render($setting['template'].'/admin/logs.html', $vars);
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
                $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'desc';
                $dateRange = isset($_GET['date']) ? $_GET['date'] : null;
                $date_from = null;
                $date_to = null;
                if ($dateRange) {
                    if (is_array($dateRange) && count($dateRange) == 2) {
                        $date_from = date('Y-m-d 00:00:00', strtotime($dateRange[0]));
                        $date_to = date('Y-m-d 23:59:59', strtotime($dateRange[1]));
                    } elseif (is_string($dateRange)) {
                        $date_from = date('Y-m-d 00:00:00', strtotime($dateRange));
                        $date_to = date('Y-m-d 23:59:59', strtotime($dateRange));
                    }
                }
                $where = [
                    "AND" => [
                        "OR" => [
                            "logs.dispatch[~]" => $searchValue,
                            "logs.action[~]" => $searchValue,
                            "logs.content[~]" => $searchValue,
                            "logs.url[~]" => $searchValue,
                            "logs.ip[~]" => $searchValue,
                            "accounts.name[~]" => $searchValue,
                        ],
                    ],
                    "LIMIT" => [$start, $length],
                    "ORDER" => [$orderName => strtoupper($orderDir)]
                ];
                if ($date_from && $date_to) {
                    $where['AND']["logs.date[<>]"] = [$date_from, $date_to];
                }
                $count = $app->count("logs", [
                    "[>]accounts" => ["user" => "id"]
                ], [
                    "logs.id"
                ], $where['AND']);
                $app->select("logs", [
                        "[>]accounts" => ["user" => "id"]
                    ], 
                    [
                    'logs.id',
                    'logs.dispatch',
                    'logs.action',
                    'logs.url',
                    'logs.ip',
                    'logs.date',
                    'logs.user',
                    'logs.active',
                    'accounts.name',
                    'accounts.avatar',
                    ], $where, function ($data) use (&$datas,$jatbi) {
                        $datas[] = [
                            "user" => '<img data-src="/' . $data['avatar'] . '?type=thumb" class="width rounded-circle lazyload me-2" style="--width:40px"> '.$data['name'],
                            "dispatch" => $data['dispatch'],
                            "action" => $data['action'],
                            "url" => $data['url'],
                            "ip" => $data['ip'],
                            "date" => $jatbi->datetime($data['date']),
                            "views" => '<button data-action="modal" data-url="/admin/logs-views/'.$data['active'].'" class="btn btn-eclo-light btn-sm border-0 py-1 px-2 rounded-3" aria-label="'.$jatbi->lang('Xem').'"><i class="ti ti-eye"></i></button>',
                        ];
                });
                echo json_encode([
                    "draw" => $draw,
                    "recordsTotal" => $count,
                    "recordsFiltered" => $count,
                    "data" => $datas ?? []
                ]);
            }
        })->setPermissions(['logs']);

        $app->router("/logs-views/{id}", 'GET', function($vars) use ($app, $jatbi,$setting) {
            $vars['data'] = $app->get("logs","*",["active"=>$vars['id'],"deleted"=>0]);
            if($vars['data']>1){
                echo $app->render($setting['template'].'/admin/logs-views.html', $vars, $jatbi->ajax());
            }
            else {
                echo $app->render($setting['template'].'/pages/error.html', $vars, $jatbi->ajax());
            }
        })->setPermissions(['blockip.edit']);

        $app->router("/trash", ['GET','POST'], function($vars) use ($app, $jatbi,$setting) {
            $vars['title'] = $jatbi->lang("Thùng rác");
            if($app->method()==='GET'){
                echo $app->render($setting['template'].'/admin/trash.html', $vars);
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
                $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'desc';
                $dateRange = isset($_GET['date']) ? $_GET['date'] : null;
                $date_from = null;
                $date_to = null;
                if ($dateRange) {
                    if (is_array($dateRange) && count($dateRange) == 2) {
                        $date_from = date('Y-m-d 00:00:00', strtotime($dateRange[0]));
                        $date_to = date('Y-m-d 23:59:59', strtotime($dateRange[1]));
                    } elseif (is_string($dateRange)) {
                        $date_from = date('Y-m-d 00:00:00', strtotime($dateRange));
                        $date_to = date('Y-m-d 23:59:59', strtotime($dateRange));
                    }
                }
                $where = [
                    "AND" => [
                        "OR" => [
                            "trashs.content[~]" => $searchValue,
                            "accounts.name[~]" => $searchValue,
                        ],
                    ],
                    "LIMIT" => [$start, $length],
                    "ORDER" => [$orderName => strtoupper($orderDir)]
                ];
                if ($date_from && $date_to) {
                    $where['AND']["trashs.date[<>]"] = [$date_from, $date_to];
                }
                $count = $app->count("trashs", [
                    "[>]accounts" => ["account" => "id"]
                ], [
                    "trashs.id"
                ], $where['AND']);
                $app->select("trashs", [
                        "[>]accounts" => ["account" => "id"]
                    ], 
                    [
                    'trashs.id',
                    'trashs.content',
                    'trashs.url',
                    'trashs.ip',
                    'trashs.date',
                    'trashs.active',
                    'trashs.router',
                    'accounts.name',
                    'accounts.avatar',
                    ], $where, function ($data) use (&$datas,$jatbi,$app) {
                        $datas[] = [
                            "checkbox" => $app->component("box",["data"=>$data['active']]),
                            "user" => '<img data-src="/' . $data['avatar'] . '?type=thumb" class="width rounded-circle me-2 lazyload" style="--width:40px"> '.$data['name'],
                            "content" => $data['content'],
                            "ip" => $data['ip'],
                            "date" => $jatbi->datetime($data['date']),
                            "action" => $app->component("action",[
                                "button" => [
                                    [
                                        'type' => 'button',
                                        'name' => $jatbi->lang("Phục hồi"),
                                        'permission' => ['trash'],
                                        'action' => ['data-url' => $data['router'].'/'.$data['active'], 'data-action' => 'modal']
                                    ],
                                    [
                                        'type' => 'button',
                                        'name' => $jatbi->lang("Xóa vĩnh viễn"),
                                        'permission' => ['trash'],
                                        'action' => ['data-url' => '/admin/trash-deleted?box='.$data['active'], 'data-action' => 'modal']
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
        })->setPermissions(['trash']);

        $app->router("/trash-deleted", ['GET','POST'], function($vars) use ($app, $jatbi,$setting) {
            $vars['title'] = $jatbi->lang("Xóa thùng rác");
            if($app->method()==='GET'){
                echo $app->render($setting['template'].'/common/deleted.html', $vars, $jatbi->ajax());
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                $boxid = explode(',', $app->xss($_GET['box']));
                $datas = $app->select("trashs","*",["active"=>$boxid,"deleted"=>0]);
                if(count($datas)>0){
                    foreach($datas as $data){
                        $app->delete("trashs",["id"=>$data['id']]);
                    }
                    $jatbi->logs('trash','deleted',$datas);
                    echo json_encode(['status'=>'success',"content"=>$jatbi->lang("Cập nhật thành công")]);
                }
                else {
                    echo json_encode(['status'=>'error','content'=>$jatbi->lang("Có lỗi xẩy ra")]);
                }
            }
        })->setPermissions(['trash']);

        $app->router("/config", ['GET','POST'], function($vars) use ($app, $jatbi,$setting) {
            $vars['title'] = $jatbi->lang("Cấu hình");
            if($app->method()==='GET'){
                $vars['data'] = $app->get("config","*") ?? ["logo"=>''];
                echo $app->render($setting['template'].'/admin/config.html', $vars, $jatbi->ajax());
            }
            elseif($app->method()==='POST'){
                $app->header([
                    'Content-Type' => 'application/json',
                ]);
                if($app->xss($_POST['name'])==''){
                    $error = ["status"=>"error","content"=>$jatbi->lang("Vui lòng không để trống")];
                }
                if(empty($error)){
                    $getConfig = $app->get("config","*");
                    $insert = [
                        "name"          => $app->xss($_POST['name']),
                        "email"         => $app->xss($_POST['email']),
                        "phone"         => $app->xss($_POST['phone']),
                        "address"       => $app->xss($_POST['address']),
                        "date"          => $app->xss($_POST['date']),
                        "time"          => $app->xss($_POST['time']),
                        "page"          => $app->xss($_POST['page']),
                        "url"           => $app->xss($_POST['url']),
                        "logo"          => $app->xss($_POST['logo']),
                    ];
                    if($getConfig){
                        $app->update("config",$insert);
                    }
                    else {
                        $app->insert("config",$insert);
                    }
                    
                    echo json_encode(['status'=>'success','content'=>$jatbi->lang("Cập nhật thành công")]);
                    $jatbi->logs('admin','config',$insert);
                }
                else {
                    echo json_encode($error);
                }
            }
        })->setPermissions(['config']);
        
        $app->router("/build-assets", 'GET', function($vars) use ($app, $jatbi,$setting) {
            $common = $app->getValueData('common');
            $commonJs = $common['js'];
            $commonCss = $common['css'];
            $app->minifyCSS($commonCss,'css/style.bundle.css');
            $app->minifyJS($commonJs,'js/main.bundle.js');
            $jsVersion = '';
            $cssVersion = '';
            $jatbi->updateVersionInFile($setting['template'].'/components/footer.html', 'js', $jsVersion);
            $jatbi->updateVersionInFile($setting['template'].'/components/header.html', 'css', $cssVersion);
            $log = [];
            $logFile = 'version.json';
            if (file_exists($logFile)) {
                $json = file_get_contents($logFile);
                $log = json_decode($json, true);
                if (!is_array($log)) $log = [];
            }
            $log[] = [
                'time' => date('Y-m-d H:i:s'),
                'js' => $jsVersion,
                'css' => $cssVersion
            ];
            file_put_contents($logFile, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $app->redirect($_SERVER['HTTP_REFERER']);
            // echo var_dump($commonJs)

        });
        $app->router("/excel", 'GET', function($vars) use ($app, $jatbi) {

            $datas = $app->select("invoices",["id","code"]);

            foreach($datas as $data){
                $app->update("invoices",["code"=>$data['id']],["id"=>$data['id'] ]);
            }
            print_r($datas);

        });
    })->middleware('login');
?>