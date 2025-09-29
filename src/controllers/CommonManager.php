<?php
if (!defined('ECLO')) die("Hacking attempt");

class CommonManager {
    private $app;
    private $jatbi;
    private $commonData = [];
    private $pluginCommonData = [];
    
    public function __construct($app, $jatbi) {
        $this->app = $app;
        $this->jatbi = $jatbi;
    }
    public function registerCoreCommon($commonData) {
        if (is_array($commonData)) {
            $this->commonData = array_merge_recursive($this->commonData, $commonData);
        }
    }
    public function registerPluginCommon($pluginId, $commonData) {
        if (is_array($commonData)) {
            if (!isset($this->pluginCommonData[$pluginId])) {
                $this->pluginCommonData[$pluginId] = [];
            }
            $this->pluginCommonData[$pluginId] = array_merge_recursive($this->pluginCommonData[$pluginId], $commonData);
        }
    }
    public function getAllCommon() {
        $allCommon = $this->commonData;
        
        // Merge common từ tất cả plugins
        foreach ($this->pluginCommonData as $pluginId => $pluginCommon) {
            $allCommon = array_merge_recursive($allCommon, $pluginCommon);
        }
        
        return $allCommon;
    }
    public function getPluginCommon($pluginId) {
        return isset($this->pluginCommonData[$pluginId]) ? $this->pluginCommonData[$pluginId] : [];
    }
    public function getCoreCommon() {
        return $this->commonData;
    }
    public function removePluginCommon($pluginId) {
        if (isset($this->pluginCommonData[$pluginId])) {
            unset($this->pluginCommonData[$pluginId]);
        }
    }
    public function hasPluginCommon($pluginId) {
        return isset($this->pluginCommonData[$pluginId]) && !empty($this->pluginCommonData[$pluginId]);
    }
    public function getPluginsWithCommon() {
        return array_keys($this->pluginCommonData);
    }
}