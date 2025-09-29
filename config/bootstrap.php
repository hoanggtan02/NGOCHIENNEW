<?php

	if (!defined('ECLO')) die("Hacking attempt");

	use ECLO\App;
    use WebSocket\Client;

	$config = require_once __DIR__ . '/config.php';
	require_once __DIR__ . '/../src/controllers/helpers.php';
	require_once __DIR__ . '/../src/controllers/plugins.php';
	require_once __DIR__ . '/../src/controllers/CommonManager.php';

	$socket = new Client("wss://ws.eclo.io", [
        'timeout' => 2,
        'headers' => [
            'Sec-WebSocket-Protocol' => 'happy-printer'
        ]
    ]);
	$app = new App($config['database']);
	$jatbi = new Jatbi($app);
	$app->JWT($config['app']['secret-key'], 'HS256');
	$app->setGlobalFile(__DIR__ . '/../src/controllers/global.php');
	$app->setValueData('setting', $config['app']);
	$app->setValueData('jatbi', $jatbi);
	$app->setValueData('socket', $socket);
	require_once __DIR__ . '/../src/controllers/requests.php'; 
	require_once __DIR__ . '/../src/controllers/middleware.php';
	

	$checkuser = $jatbi->checkAuthenticated($requests, $config['app']);

	// Khởi tạo CommonManager
	$commonManager = new CommonManager($app, $jatbi);
	$app->setValueData('commonManager', $commonManager);
	
	// Load common chính từ hệ thống core
	$coreCommon = require_once __DIR__ . '/../src/controllers/common.php';
	$commonManager->registerCoreCommon($coreCommon);
	
	// Khởi tạo plugins với CommonManager
	$plugins = new plugin(__DIR__ .'/'.$config['app']['plugins'], $app, $jatbi, $commonManager);
	
	// Load common từ plugins
	$plugins->loadPluginCommon();
	
	// Load requests từ plugins
	$plugins->loadRequests($requests);
	
	// Set common tổng hợp vào app
	$allCommon = $commonManager->getAllCommon();
	$app->setValueData('common', $allCommon);

	$jatbi->checkPermissionMenu($requests);

	require_once __DIR__ . '/../src/controllers/components.php';

	$userPermissions = [];
	if (!empty($requests) && is_array($requests)) {
	    foreach ($requests as $request) {
	        if (!isset($request['item']) || !is_array($request['item'])) {
	            continue;
	        }
	        foreach ($request['item'] as $key_item => $items) {
	            if (!empty($items['main']) && $items['main'] != 'true') {
	                $SelectPermission[$key_item]['permissions'] = $items['permission'] ?? [];
	                $SelectPermission[$key_item]['name'] = $items['menu'] ?? '';
	            }
	        }
	    }
	}
	$app->setValueData('permission', $SelectPermission);

    $app->doHook('before_config');

	foreach (glob(__DIR__ . '/../src/routers/*.php') as $routeFile) {
	    require_once $routeFile;
	}