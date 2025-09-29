<?php
    if (!defined('ECLO')) die("Hacking attempt");
    ob_start();
    echo $app->component('header');
    require_once $templatePath;
    echo $app->component('footer');
    // $html = ob_get_clean();
    // $htmlMinified = $jatbi->minifyHtml($html);
    // echo $htmlMinified;
?>