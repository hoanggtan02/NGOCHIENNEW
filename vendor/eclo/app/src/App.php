<?php
namespace ECLO;

use Medoo\Medoo;
use Verot\Upload\Upload;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use MatthiasMullie\Minify;
use HTMLPurifier;
use HTMLPurifier_Config;
/**
* Class App
* Lớp ứng dụng chính, hoạt động như một micro-framework.
* Quản lý routing, middleware, kết nối cơ sở dữ liệu, và cung cấp nhiều tiện ích khác.
* @package ECLO
*/
class App
{
  // --- CÁC THUỘC TÍNH QUẢN LÝ ROUTING VÀ MIDDLEWARE ---

  /** @var array Lưu trữ tất cả các route đã được đăng ký. Cấu trúc: [METHOD][ROUTE_PATTERN] => [callback, middlewares, permissions] */
  private $routes = [];

  /** @var array Lưu trữ các route được định nghĩa trong group gần nhất, dùng để áp dụng middleware cho group. */
  private $lastGroupRoutes = [];

  /** @var array Stack lưu trữ các tiền tố của các group route lồng nhau. */
  private $routeGroups = [];

  /** @var array Stack lưu trữ các middleware được áp dụng cho group. */
  private $groupMiddlewares = [];

  /** @var array Lưu trữ các route xử lý lỗi theo mã trạng thái. Cấu trúc: [PREFIX][STATUS_CODE] => routeInfo */
  private $errorRoutes = [];

  /** @var string|null Route pattern hiện tại đang được xử lý (để áp dụng middleware/permissions). */
  private $currentRoute = null;

  /** @var array Các middleware đã được đăng ký với tên và callback tương ứng. */
  private $registeredMiddlewares = [];


  // --- CÁC THUỘC TÍNH CẤU HÌNH VÀ DỮ LIỆU ---

  /** @var Medoo|null Đối tượng kết nối cơ sở dữ liệu (singleton). */
  private static $database = null;

  /** @var array Quyền của người dùng hiện tại. */
  private $userPermissions = [];

  /** @var array Danh sách các component có thể render. */
  private $components = [];

  /** @var array Các controller được yêu cầu. */
  private $controllers = [];

  /** @var string|null Đường dẫn đến file global layout. */
  private $globalFile = null;

  /** @var array Quản lý cookie. */
  private $cookies = [];

  /** @var array Quản lý session. */
  private $sessions = [];

  /** @var array Danh sách các địa chỉ IP bị chặn. */
  private $blockedIps = [];

  /** @var array Lưu trữ dữ liệu tạm thời để truyền giữa các phần của ứng dụng. */
  private $valueData = [];

  /** @var HTMLPurifier|null Đối tượng làm sạch HTML (singleton). */
  private $purifier = null;

    // --- CÁC THUỘC TÍNH HOOKS VÀ FILTERS ---

    /** @var array Lưu trữ các callback của hook. Cấu trúc: [tag][priority][] => callback */
    private $hooks = [];

    /** @var array Lưu trữ các callback của filter. Cấu trúc: [tag][priority][] => callback */
    private $filters = [];


  // --- CÁC THUỘC TÍNH BẢO MẬT VÀ JWT ---

  /** @var string|null Khóa bí mật cho JWT. */
  private $jwtKey;

  /** @var string|null Thuật toán mã hóa cho JWT. */
  private $jwtAlgorithm;

  /**
  * Hàm khởi tạo của ứng dụng.
  *
  * @param array|null $dbConfig Cấu hình kết nối cơ sở dữ liệu cho Medoo.
  */
  public function __construct($dbConfig = null)
  {
    if ($dbConfig && self::$database === null) {
      self::$database = new Medoo($dbConfig);
    }
    // 1. Tạo cấu hình cho HTMLPurifier
    $config = HTMLPurifier_Config::createDefault();

    // 2. Tùy chỉnh danh sách trắng (whitelist) theo ý bạn
    // Ví dụ: Cho phép các thẻ cơ bản, link và hình ảnh
    $config->set('HTML.Allowed', 'p,b,i,em,strong,a[href|title],ul,ol,li,br,img[src|alt|width|height]');
   
    // Tự động chuyển link sang target="_blank" một cách an toàn
    $config->set('HTML.TargetBlank', true);
   
    // Cho phép một số thuộc tính CSS an toàn
    $config->set('CSS.AllowedProperties', 'font-weight,font-style,text-decoration');
   
    // Tự động thêm thẻ <p> cho các đoạn văn bản
    $config->set('AutoFormat.AutoParagraph', true);

    // 3. Khởi tạo đối tượng Purifier và lưu lại
    $this->purifier = new HTMLPurifier($config);
  }

  /**
  * Cho phép gọi các phương thức tĩnh của Medoo một cách trực tiếp từ class App.
  * Ví dụ: App::table('users')->select('*');
  *
  * @param string $method Tên phương thức tĩnh.
  * @param array $args Các tham số của phương thức.
  * @return mixed
  * @throws \BadMethodCallException Nếu phương thức không tồn tại trong Medoo.
  */
  public static function __callStatic($method, $args)
  {
    if (self::$database && method_exists(self::$database, $method)) {
      return call_user_func_array([self::$database, $method], $args);
    }
    throw new \BadMethodCallException("Static method $method does not exist in Medoo.");
  }

  /**
  * Cho phép gọi các phương thức (không tĩnh) của Medoo một cách trực tiếp từ đối tượng App.
  * Ví dụ: $app->select('users', '*');
  *
  * @param string $method Tên phương thức.
  * @param array $args Các tham số của phương thức.
  * @return mixed
  * @throws \BadMethodCallException Nếu phương thức không tồn tại trong Medoo.
  */
  public function __call($method, $args)
  {
    if (self::$database instanceof Medoo && method_exists(self::$database, $method)) {
      return call_user_func_array([self::$database, $method], $args);
    }
    throw new \BadMethodCallException("Method '$method' does not exist in Medoo or Upload.");
  }

    // --- CÁC PHƯƠNG THỨC QUẢN LÝ HOOKS VÀ FILTERS ---

    /**
     * Thêm một hàm callback vào một hook cụ thể (tương tự add_action).
     * @param string   $tag      Tên của hook.
     * @param callable $callback Hàm sẽ được gọi khi hook được kích hoạt.
     * @param int      $priority Thứ tự ưu tiên thực thi. Số nhỏ hơn chạy trước.
     * @return self
     */
    public function addHook(string $tag, callable $callback, int $priority = 10): self
    {
        $this->hooks[$tag][$priority][] = $callback;
        return $this;
    }

    /**
     * Thực thi tất cả các hàm callback đã được đăng ký cho một hook (tương tự do_action).
     * @param string $tag  Tên của hook cần kích hoạt.
     * @param mixed  ...$args Các tham số sẽ được truyền vào hàm callback.
     */
    public function doHook(string $tag, ...$args)
    {
        if (!isset($this->hooks[$tag])) {
            return;
        }
        // Sắp xếp các hook theo thứ tự ưu tiên
        ksort($this->hooks[$tag]);
        foreach ($this->hooks[$tag] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                call_user_func_array($callback, $args);
            }
        }
    }
    
    public function doHookArgs(string $tag, array $args)
    {
        if (!isset($this->hooks[$tag])) {
            return;
        }
        ksort($this->hooks[$tag]);
        foreach ($this->hooks[$tag] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                call_user_func_array($callback, $args);
            }
        }
    }

    /**
     * Thêm một hàm callback vào một filter cụ thể (tương tự add_filter).
     * @param string   $tag      Tên của filter.
     * @param callable $callback Hàm sẽ được gọi để sửa đổi giá trị.
     * @param int      $priority Thứ tự ưu tiên. Số nhỏ hơn chạy trước.
     * @return self
     */
    public function addFilter(string $tag, callable $callback, int $priority = 10): self
    {
        $this->filters[$tag][$priority][] = $callback;
        return $this;
    }

    /**
     * Áp dụng tất cả các filter đã đăng ký cho một giá trị (tương tự apply_filters).
     * @param string $tag   Tên của filter.
     * @param mixed  $value Giá trị ban đầu cần được lọc.
     * @param mixed  ...$args Các tham số bổ sung sẽ được truyền vào hàm callback.
     * @return mixed Giá trị sau khi đã được áp dụng các filter.
     */
    public function applyFilters(string $tag, $value, ...$args)
    {
        if (!isset($this->filters[$tag])) {
            return $value;
        }
        // Sắp xếp các filter theo thứ tự ưu tiên
        ksort($this->filters[$tag]);
        foreach ($this->filters[$tag] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                $value = call_user_func_array($callback, array_merge([$value], $args));
            }
        }
        return $value;
    }

  /**
  * Tạo một nhóm các route với một tiền tố chung.
  *
  * @param string $prefix Tiền tố cho các route trong nhóm.
  * @param callable $callback Hàm chứa định nghĩa các route của nhóm.
  * @return self
  */
  public function group($prefix, callable $callback)
  {
    // Reset bộ nhớ đệm cho các route trong group mới để áp dụng middleware chính xác.
    $this->lastGroupRoutes = [];

    // Đưa tiền tố và middleware của group cha vào stack
    $this->routeGroups[] = $prefix;
    $parentGroupMiddlewares = end($this->groupMiddlewares) ?: [];
    $this->groupMiddlewares[] = $parentGroupMiddlewares;

    // Gọi hàm callback để định nghĩa các route bên trong group
    $callback($this);

    // Sau khi định nghĩa xong, đưa tiền tố và middleware ra khỏi stack để kết thúc group
    array_pop($this->routeGroups);
    array_pop($this->groupMiddlewares);

    return $this;
  }

  /**
  * Lấy tiền tố của group route hiện tại bằng cách nối các tiền tố trong stack.
  *
  * @return string
  */
  private function getCurrentGroup()
  {
    return implode('', $this->routeGroups);
  }

  /**
  * Đăng ký một route mới với các phương thức HTTP và callback xử lý.
  *
  * @param string $route URL pattern của route (ví dụ: '/users/{id}').
  * @param string|array $methods Phương thức HTTP cho phép ('GET', 'POST', ...).
  * @param callable|null $callback Hàm sẽ được gọi khi route được khớp.
  * @return self
  */
  public function router($route, $methods, $callback = null)
  {
    $prefix = $this->getCurrentGroup();
    $fullRoute = rtrim($prefix . $route, '/');
    if (empty($fullRoute)) $fullRoute = '/'; // Dành cho trang chủ

    // Nâng cấp để nhận diện các route lỗi động như ::404, ::500, v.v.
    if (preg_match('/::(\d{3})$/', $fullRoute, $matches)) {
      $statusCode = (int)$matches[1];
      $mainRoute = str_replace("::{$statusCode}", '', $fullRoute);
      // Lưu trữ thông tin route lỗi
      $this->errorRoutes[$mainRoute][$statusCode] = [
        'callback' => $callback,
        'middlewares' => [], // Có thể mở rộng để áp dụng middleware cho trang lỗi
      ];
      // Không đăng ký như một route thông thường
      return $this;
    }

    $methods = is_array($methods) ? $methods : [$methods];
    foreach ($methods as $method) {
      $this->registerRoute(strtoupper($method), $fullRoute, $callback);
    }
    return $this;
  }

  /**
  * Hàm nội bộ để đăng ký chi tiết một route.
  *
  * @param string|array $methods Phương thức HTTP.
  * @param string $route URL pattern.
  * @param callable|null $callback Hàm xử lý.
  * @return self
  */
  private function registerRoute($methods, $route, $callback = null)
  {
    if ($callback === null && is_callable($route)) {
      $callback = $route;
      $route = $this->currentRoute;
    }

    if ($route && $callback) {
      // Chuyển đổi route param {id} thành regex (?P<id>[a-zA-Z0-9_-]+)
      $routePattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<\1>[a-zA-Z0-9_-]+)', $route);
      $methods = is_array($methods) ? $methods : [$methods];

      // Lấy middleware từ group hiện tại để kế thừa
      $middlewaresForRoute = end($this->groupMiddlewares) ?: [];

      // Nếu đang trong một group, lưu lại route vừa tạo vào bộ nhớ đệm để có thể áp dụng middleware cho cả group
      if (!empty($this->routeGroups)) {
        foreach ($methods as $method) {
          $this->lastGroupRoutes[strtoupper($method)][] = $routePattern;
        }
      }

      // Lưu thông tin route vào mảng $routes
      foreach ($methods as $method) {
        $this->routes[strtoupper($method)][$routePattern] = [
          'callback' => function ($vars) use ($callback) {
            $callback($vars);
          },
          'middlewares' => $middlewaresForRoute,
          'permissions' => []
        ];
      }
      $this->currentRoute = $routePattern;
    } else {
      // Nếu không có callback, chỉ lưu lại pattern của route
      $this->currentRoute = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<\1>[a-zA-Z0-9_-]+)', $route);
    }
    return $this;
  }

  /**
  * Đăng ký một middleware mới với một tên định danh.
  *
  * @param string $name Tên của middleware.
  * @param callable $callback Logic của middleware. Phải trả về true để tiếp tục, false để dừng lại.
  * @return self
  */
  public function setMiddleware(string $name, callable $callback)
  {
    $this->registeredMiddlewares[$name] = $callback;
    return $this;
  }

  /**
  * Áp dụng một hoặc nhiều middleware cho route hoặc group route gần nhất.
  *
  * @param string|array $names Tên của middleware(s) để áp dụng.
  * @return self
  */
  public function middleware($names)
  {
    $names = is_array($names) ? $names : [$names];

    // Ưu tiên áp dụng cho group route vừa được định nghĩa
    if (!empty($this->lastGroupRoutes)) {
      foreach ($this->lastGroupRoutes as $method => $routePatterns) {
        foreach ($routePatterns as $pattern) {
          if (isset($this->routes[$method][$pattern])) {
            $this->routes[$method][$pattern]['middlewares'] = array_merge(
              $this->routes[$method][$pattern]['middlewares'],
              $names
            );
          }
        }
      }
      // Xóa bộ nhớ đệm sau khi đã áp dụng
      $this->lastGroupRoutes = [];
    }
    // Nếu không, áp dụng cho route đơn lẻ vừa được định nghĩa
    else if ($this->currentRoute) {
      foreach ($this->routes as $method => &$methodRoutes) {
        if (isset($methodRoutes[$this->currentRoute])) {
          $methodRoutes[$this->currentRoute]['middlewares'] = array_merge($methodRoutes[$this->currentRoute]['middlewares'], $names);
        }
      }
    }
    return $this;
  }

  /**
  * Gán quyền truy cập cho route hiện tại.
  *
  * @param string|array $permissions Quyền hoặc danh sách quyền yêu cầu.
  * @return self
  */
  public function setPermissions($permissions)
  {
    if ($this->currentRoute) {
      foreach ($this->routes as $method => &$methodRoutes) {
        if (isset($methodRoutes[$this->currentRoute])) {
          $methodRoutes[$this->currentRoute]['permissions'] = is_array($permissions) ? $permissions : [$permissions];
        }
      }
    }
    return $this;
  }

  /**
  * Thiết lập danh sách các quyền mà người dùng hiện tại có.
  *
  * @param array $userPermissions Mảng các quyền của người dùng.
  */
  public function setUserPermissions($userPermissions)
  {
    $this->userPermissions = $userPermissions;
  }

  /**
  * Khởi chạy ứng dụng, xử lý request và gọi route tương ứng.
  */
  public function run()
  {
        $this->doHook('before_run', $this);

    $this->checkBlockedIp();

    $method = $_SERVER['REQUEST_METHOD'];
    $path = strtok($_SERVER['REQUEST_URI'], '?');
    $this->currentRoute = $path; // Cập nhật route hiện tại

    $routeData = $this->matchRoute($method, $path);

    if ($routeData !== false) {
      $routePattern = $routeData['route'];
      $routeInfo = $this->routes[$method][$routePattern];
      $allChecksPassed = true;

            $this->doHook('before_checks', $routeInfo, $this);

      // BƯỚC 1: Thực thi tất cả các middleware.
      $middlewaresToRun = $routeInfo['middlewares'] ?? [];
      foreach ($middlewaresToRun as $middlewareName) {
        if (isset($this->registeredMiddlewares[$middlewareName])) {
          // Nếu bất kỳ middleware nào trả về false, dừng lại ngay lập tức.
          if (call_user_func($this->registeredMiddlewares[$middlewareName]) === false) {
            $allChecksPassed = false;
            break;
          }
        }
      }

      // BƯỚC 2: Nếu tất cả middleware đã qua, MỚI kiểm tra quyền.
      if ($allChecksPassed) {
        $requiredPermissions = $routeInfo['permissions'] ?? [];
        if (!empty($requiredPermissions)) {
          foreach ($requiredPermissions as $permission) {
            // Nếu người dùng thiếu bất kỳ quyền nào, dừng lại.
            if (!in_array($permission, $this->userPermissions)) {
              $allChecksPassed = false;
              break;
            }
          }
        }
      }
            
            $this->doHook('after_checks', $allChecksPassed, $routeInfo, $this);

      // BƯỚC 3: Nếu tất cả kiểm tra đều thành công, thực thi callback của route.
      if ($allChecksPassed) {
                $this->doHook('before_route_callback', $routeData['vars'], $routeInfo, $this);
        call_user_func($routeInfo['callback'], $routeData['vars']);
                $this->doHook('after_route_callback', $routeData['vars'], $routeInfo, $this);
      } else {
        // Nếu middleware hoặc permission thất bại, xử lý như một lỗi (route không tìm thấy).
        $this->handleWildcardRoute($path);
      }

    } else {
      // Nếu không có route nào khớp, xử lý lỗi.
      $this->handleWildcardRoute($path);
    }

        $this->doHook('after_run', $this);
  }

  /**
  * Tìm kiếm route khớp với phương thức và đường dẫn request.
  *
  * @param string $method Phương thức HTTP.
  * @param string $path Đường dẫn request.
  * @return array|false Thông tin route nếu khớp, ngược lại là false.
  */
  private function matchRoute($method, $path)
  {
    if (!isset($this->routes[$method])) {
      return false;
    }

    foreach ($this->routes[$method] as $route => $data) {
      $pattern = '#^' . $route . '$#';
      if (preg_match($pattern, $path, $matches)) {
        return [
          'route' => $route,
          'vars' => array_merge(
            ['_method' => $method],
            array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY) // Lọc lấy các param có tên
          )
        ];
      }
    }
    return false;
  }

  /**
  * Xử lý các request không khớp với bất kỳ route nào.
  * Tự động gọi trình xử lý lỗi 404.
  *
  * @param string $path Đường dẫn gây ra lỗi.
  */
  private function handleWildcardRoute($path)
  {
    // Khi không tìm thấy route, đó là lỗi 404.
    // Gọi trình xử lý lỗi tập trung với mã 404.
    $this->triggerError(404, ['path' => $path]);
  }

  /**
  * Kích hoạt và hiển thị một trang lỗi theo mã trạng thái HTTP.
  *
  * @param int $statusCode Mã trạng thái HTTP (ví dụ: 404, 500, 403).
  * @param array $params  Các tham số tùy chọn để truyền vào callback của trang lỗi.
  */
  public function triggerError($statusCode, $params = [])
  {
        $this->doHook('before_error_trigger', $statusCode, $params, $this);

    $path = $this->currentRoute ?? '/';
    $applicableHandler = null;
    $bestMatchLength = -1;
    // Lặp qua tất cả các prefix của các route lỗi đã được định nghĩa
    foreach ($this->errorRoutes as $prefix => $handlers) {
      // Kiểm tra 2 điều kiện:
      // 1. URL hiện tại có bắt đầu bằng prefix này không.
      // 2. Có tồn tại handler cho mã lỗi ($statusCode) này không.
      if (strpos($path, $prefix) === 0 && isset($handlers[$statusCode])) {
        // Nếu prefix này "khớp" hơn (dài hơn) cái đã tìm thấy trước đó,
        // thì chọn nó làm handler tốt nhất.
        if (strlen($prefix) > $bestMatchLength) {
          $bestMatchLength = strlen($prefix);
          $applicableHandler = $handlers[$statusCode];
        }
      }
    }
    if ($applicableHandler && is_callable($applicableHandler['callback'])) {
      // Nếu tìm thấy handler tùy chỉnh, gọi nó
      call_user_func($applicableHandler['callback'], $params);
    } else {
      // Nếu không, hiển thị trang lỗi mặc định của framework
      switch ($statusCode) {
        case 403:
          echo "<h1>403 Forbidden</h1><p>You don't have permission to access this resource.</p>";
          break;
        case 404:
          echo "<h1>404 Not Found</h1><p>The page you requested could not be found.</p>";
          break;
        case 500:
          echo "<h1>500 Internal Server Error</h1><p>An unexpected error occurred. Please try again later.</p>";
          break;
        default:
          echo "<h1>Error {$statusCode}</h1><p>An error occurred.</p>";
      }
    }
    // Dừng thực thi sau khi hiển thị lỗi
    exit();
  }

  /**
  * Lưu trữ một giá trị dữ liệu tạm thời để truyền giữa các thành phần.
  * @param string $key
  * @param mixed $value
  */
  public function setValueData(string $key, $value)
  {
    $this->valueData[$key] = $value;
  }

  /**
  * Lấy một giá trị dữ liệu đã lưu.
  * @param string $key
  * @return mixed|null
  */
  public function getValueData($key)
  {
    return $this->valueData[$key] ?? null;
  }

  /**
  * Đăng ký một component (một đoạn view có thể tái sử dụng).
  * @param string $name Tên component.
  * @param callable $callback Logic để render component.
  * @return self
  */
  public function setComponent($name, $callback)
  {
    $this->components[$name] = $callback;
    return $this;
  }

  /**
  * Render một component đã đăng ký và trả về HTML.
  * @param string $name Tên component.
  * @param array $vars Dữ liệu truyền vào component.
  * @return string HTML của component hoặc thông báo lỗi.
  */
  public function component($name, $vars = [])
  {
    $vars = array_merge($this->valueData, $vars);
        $vars = $this->applyFilters('component_vars', $vars, $name);
    if (isset($this->components[$name])) {
      ob_start();
            $this->doHook('before_component_render', $name, $vars);
      $callback = $this->components[$name];
      $callback($vars);
            $output = ob_get_clean();
            $this->doHook('after_component_render', $name, $output);
            return $output;
    }
    return "Component not found";
  }

  /**
  * Lấy phương thức request hiện tại ('GET', 'POST', ...).
  * @return string
  */
  public function method()
  {
    return $_SERVER['REQUEST_METHOD'] ?? 'GET';
  }

  /**
  * Tải và thực thi một file controller.
  * @param string $prefix
  * @param string $controllerPath Đường dẫn file controller.
  * @return self
  * @throws \RuntimeException Nếu file controller không tồn tại.
  */
  public function request($prefix, $controllerPath)
  {
    if (file_exists($controllerPath)) {
      $app = $this;
      $controller = function () use ($app, $controllerPath) {
        require $controllerPath;
      };
      if (is_callable($controller)) {
        $this->controllers[$prefix] = $controller;
        $controller($this);
      }
    } else {
      throw new \RuntimeException("Controller not found: $controllerPath");
    }
    return $this;
  }

  /**
  * Thiết lập file layout global để bao bọc các template khác.
  * @param string $filePath Đường dẫn file.
  * @return self
  */
  public function setGlobalFile($filePath)
  {
    $this->globalFile = $filePath;
    return $this;
  }

  /**
  * Render một file template với dữ liệu.
  * @param string $templatePath Đường dẫn file template.
  * @param array $vars Dữ liệu truyền vào view.
  * @param string|null $ajax Nếu là 'global', sẽ chỉ render template mà không có layout global.
  * @return string HTML đã render hoặc thông báo lỗi.
  */
  public function render($templatePath, $vars = [], $ajax = null)
  {
        // Áp dụng filter cho các biến trước khi render
        $final_vars = $this->applyFilters('before_render_vars', array_merge($this->valueData, $vars), $templatePath);
        
    if (file_exists($templatePath)) {
      extract($final_vars, EXTR_SKIP);
      ob_start();
      $app = $this;
            $this->doHook('before_render', $templatePath, $final_vars);
      if ($ajax !== 'global') {
        if ($this->globalFile && file_exists($this->globalFile)) {
          include $this->globalFile;
        } else {
          include $templatePath;
        }
      } else {
        include $templatePath;
      }
            $output = ob_get_clean();
            $this->doHook('after_render', $output, $templatePath);
            return $this->applyFilters('after_render_output', $output, $templatePath);
    }
    return "Template not found";
  }

  /**
  * Lấy route hiện tại (đường dẫn URL).
  * @return string|null
  */
  public function getRoute()
  {
    return $this->currentRoute;
  }

  /**
  * Nén một hoặc nhiều file CSS.
  * @param string|array $inputFiles Đường dẫn file(s) CSS đầu vào.
  * @param string|null $outputPath Đường dẫn file đầu ra. Nếu null, trả về nội dung đã nén.
  * @return string|void
  */
  public function minifyCSS($inputFiles, $outputPath = null)
  {
    $minifier = new Minify\CSS($inputFiles);
    return $minifier->minify($outputPath);
  }

  /**
  * Nén một hoặc nhiều file JS.
  * @param string|array $inputFiles Đường dẫn file(s) JS đầu vào.
  * @param string|null $outputPath Đường dẫn file đầu ra. Nếu null, trả về nội dung đã nén.
  * @return string|void
  */
  public function minifyJS($inputFiles, $outputPath = null)
  {
    $minifier = new Minify\JS($inputFiles);
    return $minifier->minify($outputPath);
  }

  /**
  * Nén và Gzip file JS.
  * @param string|array $inputFiles Đường dẫn file(s) JS đầu vào.
  * @param string|null $outputPath Đường dẫn file .js.gz đầu ra.
  * @param int $level Mức độ nén Gzip (0-9).
  */
  public function minifyAndGzipJS($inputFiles, $outputPath = null, $level = 9)
  {
    $minifier = new Minify\JS($inputFiles);
    $minifiedContent = $minifier->minify();
    $gzippedContent = gzencode($minifiedContent, $level);
    if ($outputPath) {
      file_put_contents($outputPath, $gzippedContent);
    }
  }

  /**
  * Thiết lập các header cho response.
  * @param array $headers Mảng header key-value.
  */
  public function header($headers)
  {
    foreach ($headers as $key => $value) {
      header("$key: $value");
    }
  }

  /**
  * Xử lý việc tải lên file bằng thư viện Verot/Upload.
  * @param mixed $fileField Tên trường file trong form (ví dụ: $_FILES['avatar']).
  * @return Upload Đối tượng Upload đã được cấu hình.
  */
  public function upload($fileField)
  {
    $handle = new Upload($fileField);
    $handle->allowed = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
    $handle->file_safe_name = true;
    $handle->file_overwrite = false;
    $handle->file_max_size = '50M'; // 50 Megabytes
    $handle->forbidden = ['php', 'php3', 'php4', 'phtml', 'exe', 'pl', 'cgi', 'html', 'htm', 'js', 'sh'];
    return $handle;
  }

  /**
  * Khởi tạo và cấu hình đối tượng PHPMailer để gửi email.
  * @param array $options Cấu hình SMTP.
  * @return PHPMailer
  * @throws Exception
  */
  public function Mail($options)
  {
    $mail = new PHPMailer(true);
    try {
      $mail->isSMTP();
      $mail->Host = $options['host'];
      $mail->SMTPAuth = true;
      $mail->Username = $options['username'];
      $mail->Password = $options['password'];
      if (isset($options['encryption']) && in_array($options['encryption'], ['tls', 'ssl', 'smtp'])) {
        if ($options['encryption'] == 'smtp') {
          $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
          $mail->SMTPSecure = $options['encryption'];
        }
      } else {
        throw new Exception('Invalid encryption type. Must be tls, ssl, or smtp.');
      }
      $mail->Port = $options['port'] ?? 587;
      $mail->setFrom($options['from_email'], $options['from_name']);
      return $mail;
    } catch (Exception $e) {
      throw new Exception("Mailer Error: " . $mail->ErrorInfo);
    }
  }

  /**
  * Cấu hình JWT key và thuật toán mặc định.
  * @param string $key Khóa bí mật.
  * @param string $algorithm Thuật toán (mặc định 'HS256').
  */
  public function JWT($key, $algorithm = 'HS256')
  {
    $this->jwtKey = $key;
    $this->jwtAlgorithm = $algorithm;
  }

  /**
  * Tạo một chuỗi JWT.
  * @param array $payload Dữ liệu cần mã hóa.
  * @param string|null $key
  * @param string|null $algorithm
  * @param string|null $keyId
  * @param array|null $head
  * @return string Token JWT.
  * @throws \Exception
  */
  public function addJWT($payload, $key = null, $algorithm = null, $keyId = null, $head = null)
  {
    $jwtKey = $key ?? $this->jwtKey;
    $jwtAlgorithm = $algorithm ?? $this->jwtAlgorithm;
    if (!$jwtKey) {
      throw new \Exception('JWT key not configured');
    }
    return JWT::encode($payload, $jwtKey, $jwtAlgorithm, $keyId, $head);
  }

  /**
  * Giải mã một chuỗi JWT.
  * @param string $token Token JWT.
  * @param bool|null $header Nếu true, trả về cả decoded payload và header.
  * @return mixed|null Dữ liệu đã giải mã hoặc null nếu thất bại.
  * @throws \Exception
  */
  public function decodeJWT($token, $header = null)
  {
    if (!$this->jwtKey) {
      throw new \Exception('JWT key not configured');
    }
    try {
      if ($header) {
        $headers = new \stdClass();
        $decoded = JWT::decode($token, new Key($this->jwtKey, $this->jwtAlgorithm), $headers);
        return ['decoded' => $decoded, 'headers' => $headers];
      } else {
        return JWT::decode($token, new Key($this->jwtKey, $this->jwtAlgorithm));
      }
    } catch (\Exception $e) {
      return null; // Token không hợp lệ hoặc hết hạn
    }
  }

  /**
  * Kiểm tra xem một token JWT có hợp lệ hay không.
  * @param string $token
  * @return bool
  */
  public function validateJWT($token)
  {
    return $this->decodeJWT($token) !== null;
  }

  /**
  * Thiết lập một cookie.
  * @param string $name Tên cookie.
  * @param string $value Giá trị.
  * @param int $expire Thời gian hết hạn (timestamp).
  * @param string $path
  * @param string $domain
  * @param bool $secure
  * @param bool $httponly
  */
  public function setCookie($name, $value, $expire = 0, $path = '/', $domain = '', $secure = false, $httponly = true)
  {
    setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
    $this->cookies[$name] = $value;
  }

  /**
  * Lấy giá trị của một cookie.
  * @param string $name
  * @return string|null
  */
  public function getCookie($name)
  {
    return $_COOKIE[$name] ?? null;
  }

  /**
  * Xóa một cookie.
  * @param string $name
  */
  public function deleteCookie($name)
  {
    if (isset($_COOKIE[$name])) {
      unset($_COOKIE[$name]);
      unset($this->cookies[$name]);
      setcookie($name, '', time() - 3600, '/');
    }
  }

  /**
  * Thiết lập một giá trị trong session.
  * @param string $key
  * @param mixed $value
  */
  public function setSession($key, $value)
  {
    $_SESSION[$key] = $value;
    $this->sessions[$key] = $value;
  }

  /**
  * Lấy giá trị từ session.
  * @param string $key
  * @return mixed|null
  */
  public function getSession($key)
  {
    return $_SESSION[$key] ?? null;
  }

  /**
  * Xóa một giá trị khỏi session.
  * @param string $key
  */
  public function deleteSession($key)
  {
    if (isset($_SESSION[$key])) {
      unset($_SESSION[$key]);
      unset($this->sessions[$key]);
    }
  }
 
  // --- CÁC HÀM TIỆN ÍCH VỀ API (cURL) ---

  /**
  * Gửi một request GET đến một URL bằng cURL.
  *
  * @param string $url URL của API.
  * @param array $headers Mảng các header cho request.
  * @return string|false Phản hồi từ server hoặc false nếu có lỗi.
  */
  public function apiGet($url, $headers = [])
  {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
  }

  /**
  * Gửi một request POST đến một URL bằng cURL.
  *
  * @param string $url URL của API.
  * @param array $data Dữ liệu gửi đi (sẽ được chuyển thành query string).
  * @param array $headers Mảng các header cho request.
  * @return string|false Phản hồi từ server hoặc false nếu có lỗi.
  */
  public function apiPost($url, $data = [], $headers = [])
  {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
  }

  /**
  * Gửi một request PUT đến một URL bằng cURL.
  *
  * @param string $url URL của API.
  * @param array $data Dữ liệu gửi đi.
  * @param array $headers Mảng các header cho request.
  * @return string|false Phản hồi từ server hoặc false nếu có lỗi.
  */
  public function apiPut($url, $data = [], $headers = [])
  {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
  }

  /**
  * Gửi một request DELETE đến một URL bằng cURL.
  *
  * @param string $url URL của API.
  * @param array $headers Mảng các header cho request.
  * @return string|false Phản hồi từ server hoặc false nếu có lỗi.
  */
  public function apiDelete($url, $headers = [])
  {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
  }

  // --- CÁC HÀM TIỆN ÍCH VỀ ĐIỀU HƯỚNG VÀ THỜI GIAN ---

  /**
  * Chuyển hướng người dùng đến một URL khác.
  *
  * @param string $url URL đích.
  * @param int $statusCode Mã trạng thái HTTP (mặc định là 302).
  * @return void
  */
  public function redirect($url, $statusCode = 302)
  {
    header('Location: ' . $url, true, $statusCode);
    exit();
  }

  /**
  * Chuyển hướng người dùng về trang trước đó.
  *
  * @param int $statusCode Mã trạng thái HTTP (mặc định là 302).
  * @return void
  */
  public function back($statusCode = 302)
  {
    $url = $_SERVER['HTTP_REFERER'] ?? '/';
    $this->redirect($url, $statusCode);
  }

  /**
  * Lấy thời gian hiện tại theo định dạng.
  *
  * @param string $format Định dạng ngày tháng (theo hàm date()).
  * @return string
  */
  public function currentTime($format = 'Y-m-d H:i:s')
  {
    return date($format);
  }
    /**
     * Định dạng một timestamp thành chuỗi ngày tháng.
     *
     * @param int $timestamp Dấu thời gian Unix.
     * @param string $format Định dạng ngày tháng.
     * @return string
     */
    public function formatDate($timestamp, $format = 'Y-m-d H:i:s')
    {
        return date($format, $timestamp);
    }

    /**
     * Cộng thêm một khoảng thời gian vào một timestamp.
     *
     * @param int $timestamp Dấu thời gian Unix ban đầu.
     * @param string $interval Khoảng thời gian (định dạng của DateInterval, ví dụ: 'P1M' cho 1 tháng).
     * @param string $format Định dạng ngày tháng trả về.
     * @return string
     */
    public function addTime($timestamp, $interval, $format = 'Y-m-d H:i:s')
    {
        $date = new \DateTime();
        $date->setTimestamp($timestamp);
        $date->add(new \DateInterval($interval));
        return $date->format($format);
    }

    /**
     * Tính toán sự khác biệt giữa hai timestamp.
     *
     * @param int $start Dấu thời gian bắt đầu.
     * @param int $end Dấu thời gian kết thúc.
     * @return string Chuỗi mô tả sự khác biệt.
     */
  public function diffTime($start, $end)
  {
    $startDate = new \DateTime();
    $startDate->setTimestamp($start);
    $endDate = new \DateTime();
    $endDate->setTimestamp($end);
    $diff = $startDate->diff($endDate);
    return $diff->format('%y năm %m tháng %d ngày %h giờ %i phút %s giây');
  }

  // --- CÁC HÀM TIỆN ÍCH VỀ DỮ LIỆU NGẪU NHIÊN VÀ CHUỖI ---

  /**
  * Tạo một chuỗi số ngẫu nhiên.
  *
  * @param int $length Độ dài của chuỗi số.
  * @return string
  */
  public function randomNumber($length = 11)
  {
    $randomString = '';
    // Đảm bảo chữ số đầu tiên không phải là 0
    $randomString .= mt_rand(1, 9);
    for ($i = 1; $i < $length; $i++) {
      $randomString .= mt_rand(0, 9);
    }
    return $randomString;
  }

  /**
  * Tạo một chuỗi ký tự ngẫu nhiên.
  *
  * @param int $length Độ dài chuỗi.
  * @param string $chars Các ký tự được phép sử dụng.
  * @return string
  */
  public function randomString($length = 10, $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789')
  {
    $str = '';
    $charLength = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
      $str .= $chars[mt_rand(0, $charLength)];
    }
    return $str;
  }

  /**
  * Chuyển đổi một chuỗi thành định dạng URL thân thiện (slug).
  *
  * @param string $string Chuỗi đầu vào.
  * @return string Chuỗi đã được định dạng.
  */
  public function formatUrl($string)
  {
    // Chuyển thành chữ thường và xóa khoảng trắng đầu cuối
    $string = strtolower(trim($string));
    // Thay thế các ký tự không phải chữ, số bằng dấu gạch ngang
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    // Thay thế nhiều dấu gạch ngang liên tiếp bằng một dấu
    $string = preg_replace('/-+/', '-', $string);
    // Xóa dấu gạch ngang ở đầu và cuối chuỗi
    return trim($string, '-');
  }

  /**
  * Cắt ngắn một chuỗi (hỗ trợ UTF-8).
  *
  * @param string $string Chuỗi đầu vào.
  * @param int $length Độ dài tối đa.
  * @param string $suffix Hậu tố thêm vào nếu chuỗi bị cắt.
  * @return string
  */
  public function truncateString($string, int $length, $suffix = '...')
  {
    if (mb_strlen($string, 'UTF-8') > $length) {
      return mb_substr($string, 0, $length, 'UTF-8') . $suffix;
    }
    return $string;
  }

  /**
  * Cắt một đoạn ký tự từ chuỗi (hỗ trợ UTF-8).
  *
  * @param string $string Chuỗi đầu vào.
  * @param int $start Vị trí bắt đầu.
  * @param int $length Độ dài cần cắt.
  * @return string
  */
  public function cutCharacters($string, $start, $length)
  {
    return substr($string, $start, $length, "utf-8");
  }

  // --- CÁC HÀM TIỆN ÍCH VỀ BẢO MẬT VÀ REQUEST ---

  /**
  * Kiểm tra một địa chỉ IP có hợp lệ không.
  *
  * @param string $ip Địa chỉ IP.
  * @return bool
  */
  public function isValidIp($ip)
  {
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
  }

  /**
  * Lấy địa chỉ IP của client.
  *
  * @return string
  */
  public function checkClientIp()
  {
    return $_SERVER['REMOTE_ADDR'];
  }

  /**
  * Kiểm tra thiết bị của người dùng (mobile hoặc desktop) dựa trên User Agent.
  *
  * @return string 'mobile' hoặc 'desktop'.
  */
  public function checkDevice()
  {
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    if (preg_match('/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/', $userAgent)) {
      return 'mobile';
    }
    return 'desktop';
  }

  /**
  * Thêm một IP vào danh sách chặn.
  *
  * @param string $ip Địa chỉ IP cần chặn.
  * @return self
  */
  public function blockIp($ip)
  {
    $this->blockedIps[] = $ip;
    return $this;
  }

  /**
  * Kiểm tra IP của client có trong danh sách bị chặn không. Nếu có, dừng thực thi.
  *
  * @return void
  */
  public function checkBlockedIp()
  {
    $clientIp = $this->checkClientIp();
    if (in_array($clientIp, $this->blockedIps)) {
      http_response_code(403);
      die("403 - Forbidden: Your IP is blocked.");
    }
  }

  /**
  * Giới hạn tần suất request từ một IP dựa trên session.
  *
  * @param string $key Khóa định danh cho hành động này (ví dụ: 'login_attempt').
  * @param int $limit Số lần cho phép.
  * @param int $time Khung thời gian (giây).
  * @return void
  */
  public function rateLimit($key, $limit = 10, $time = 60)
  {
    $ip = $_SERVER['REMOTE_ADDR'];
    $cacheKey = "rate_limit:{$key}:{$ip}";

    // Khởi tạo nếu chưa tồn tại trong session
    if (!isset($_SESSION[$cacheKey])) {
      $_SESSION[$cacheKey] = ["count" => 0, "expires" => time() + $time];
    }
   
    // Reset nếu đã hết hạn
    if ($_SESSION[$cacheKey]["expires"] < time()) {
      $_SESSION[$cacheKey] = ["count" => 0, "expires" => time() + $time];
    }

    // Tăng bộ đếm
    $_SESSION[$cacheKey]["count"]++;

    // Kiểm tra nếu vượt quá giới hạn
    if ($_SESSION[$cacheKey]["count"] > $limit) {
      http_response_code(429);
      echo json_encode(["error" => "Too many requests. Try again later."]);
      exit;
    }
  }

  /**
  * Làm sạch một chuỗi để chống lại các cuộc tấn công XSS.
  *
  * @param string   $string    Chuỗi đầu vào cần làm sạch.
  * @param bool    $allowHtml  Đặt là `true` để cho phép các thẻ HTML an toàn. Mặc định là `false` (loại bỏ tất cả HTML).
  * @param array|null $customConfig Một mảng chứa cấu hình HTMLPurifier tùy chỉnh.
  * @return string Chuỗi an toàn.
  */
  public function xss($string, $allowHtml = false, $customConfig = null)
  {
    // Chế độ 1 (Mặc định): Cho phép HTML an toàn.
    if ($allowHtml === true) {
      // Kiểm tra xem có cấu hình tùy chỉnh được cung cấp hay không.
      if (is_array($customConfig)) {
        // Nếu có, tạo một bộ lọc tạm thời với cấu hình tùy chỉnh này.
        $config = HTMLPurifier_Config::createDefault();
        foreach ($customConfig as $key => $value) {
          $config->set($key, $value);
        }
        $customPurifier = new HTMLPurifier($config);
        return $customPurifier->purify($string);

      } else {
        // Nếu không, dùng bộ lọc mặc định đã được khởi tạo sẵn.
        return $this->purifier->purify($string);
      }
    }

    // Chế độ 2 (Mặc định khi $allowHtml = false): Loại bỏ tất cả HTML.
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
  }
}
?>
