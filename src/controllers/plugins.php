<?php
if (!defined('ECLO')) die("Hacking attempt");

class plugin {
    private $pluginDir;
    protected $app;
    protected $jatbi;
    protected $commonManager;
    protected $cachedPlugins = null;
    
    public function __construct($pluginDir, $app, $jatbi = null, $commonManager = null) {
        $this->pluginDir = realpath($pluginDir);
        $this->app = $app;
        $this->jatbi = $jatbi;
        $this->commonManager = $commonManager;
        if (!$this->pluginDir || !is_dir($this->pluginDir)) {
            throw new Exception("Thư mục plugin không tồn tại hoặc không có quyền truy cập.");
        }
    }
    private function _getActivePlugins() {
        if ($this->cachedPlugins === null) {
            $this->cachedPlugins = $this->app->select("plugins", "*", [
                "status" => 'A',
                "install" => 1,
                "ORDER" => ["position" => "ASC", "name" => "ASC"]
            ]);
        }
        return $this->cachedPlugins;
    }
    public function getPlugins() {
        $plugins = [];
        $pluginRecords = $this->_getActivePlugins();

        foreach ($pluginRecords as $record) {
            $pluginSlug = basename($record['plugins']);
            $potentialPath = $this->pluginDir . DIRECTORY_SEPARATOR . $pluginSlug;
            $pluginPath = realpath($potentialPath);
            if (!$pluginPath || !is_dir($pluginPath) || strpos($pluginPath, $this->pluginDir) !== 0) {
                error_log("Cảnh báo bảo mật: Cố gắng truy cập plugin ngoài thư mục cho phép. Plugin slug: " . $pluginSlug);
                continue;
            }
            $configFile = $pluginPath . '/config.json';
            if (file_exists($configFile)) {
                $configContent = file_get_contents($configFile);
                $config = json_decode($configContent, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $plugins[$pluginPath] = $config;
                } else {
                    error_log("Lỗi JSON trong plugin: $pluginPath - " . json_last_error_msg());
                }
            }
        }
        return $plugins;
    }
    public function loadRequests(&$requests) {
        if (!is_array($requests)) {
            $requests = [];
        }
        $loader = function($file, $app) {
            return require $file;
        };
        foreach ($this->getPlugins() as $pluginPath => $config) {
            $requestFile = $pluginPath . '/requests.php';
            if (file_exists($requestFile)) {
                $pluginRequest = $loader($requestFile, $this->app);
                if (is_array($pluginRequest)) {
                    $requests = array_replace_recursive($requests, $pluginRequest);
                }
                foreach (glob($pluginPath.'/controllers/*.php') as $routeFile) {
                    $loader($routeFile, $this->app);
                }
                foreach (glob($pluginPath.'/router/*.php') as $routeFile) {
                    $loader($routeFile, $this->app);
                }
            }
        }
    }
    
    /**
     * Load common từ tất cả plugins
     */
    public function loadPluginCommon() {
        if (!$this->commonManager) {
            return;
        }
        
        foreach ($this->getPlugins() as $pluginPath => $config) {
            $pluginId = $config['id'] ?? basename($pluginPath);
            $commonFile = $pluginPath . '/common.php';
            
            if (file_exists($commonFile)) {
                try {
                    // Truyền các biến cần thiết vào scope của file common.php
                    $app = $this->app;
                    $jatbi = $this->jatbi;
                    
                    $pluginCommon = require $commonFile;
                    if (is_array($pluginCommon)) {
                        $this->commonManager->registerPluginCommon($pluginId, $pluginCommon);
                    }
                } catch (Exception $e) {
                    error_log("Lỗi khi load common từ plugin {$pluginId}: " . $e->getMessage());
                }
            }
        }
    }
}
?>
