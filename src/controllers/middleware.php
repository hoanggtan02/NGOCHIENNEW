<?php 
    if (!defined('ECLO')) die("Hacking attempt");
	$app->setMiddleware('login', function() use ($app) {
        if(!$app->getSession("accounts")){
            $setting = $app->getValueData('setting');
            $app->redirect($setting['manager'].'/login');
        }
    });
?>