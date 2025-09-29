# ECLO Framework (eclo/app)

**ECLO App** là một micro-framework library được viết bằng PHP, cung cấp một bộ công cụ mạnh mẽ để xây dựng các ứng dụng web một cách nhanh chóng. Thư viện quản lý các tác vụ cốt lõi như định tuyến (routing), middleware, tương tác cơ sở dữ liệu, bảo mật, và nhiều tiện ích khác trong một lớp `App` duy nhất.

## Yêu cầu

  * PHP \>= 8.1

## Cài đặt

Sử dụng [Composer](https://getcomposer.org/) để cài đặt:

```bash
composer require eclo/app
```

## Khởi tạo

Đây là một ví dụ "Hello World" đơn giản để bắt đầu.

```php
// index.php
require 'vendor/autoload.php';

// Khởi tạo ứng dụng
$app = new ECLO\App([
    'database_type' => 'mysql',
    'database_name' => 'ten_database',
    'server' => 'localhost',
    'username' => 'root',
    'password' => ''
]);

// Định nghĩa một route cho trang chủ
$app->router('/', 'GET', function() {
    echo 'Hello, World!';
});

// Chạy ứng dụng
$app->run();
```

-----

## Định tuyến (Routing)

Hệ thống routing cho phép bạn ánh xạ các URL tới các hàm hoặc phương thức xử lý.

### Định tuyến cơ bản

Sử dụng phương thức `router()` để định nghĩa một route.

```php
// Route cho phương thức GET
$app->router('/about', 'GET', function() {
    echo 'Đây là trang giới thiệu.';
});

// Route cho phương thức POST
$app->router('/contact', 'POST', function() {
    // Xử lý dữ liệu form
});

// Route cho nhiều phương thức
$app->router('/news', ['GET', 'POST'], function() {
    // ...
});
```

### Route với tham số

Bạn có thể định nghĩa các tham số trong URL bằng cách đặt chúng trong dấu ngoặc nhọn `{}`.

```php
$app->router('/users/{id}', 'GET', function($params) {
    $userId = $params['id'];
    echo "Thông tin người dùng có ID: " . $app->xss($userId);
});
```

### Nhóm Route (Group)

Nhóm các route có cùng một tiền tố chung.

```php
$app->group('/admin', function($app) {
    
    $app->router('', 'GET', function() {
        echo 'Trang chủ Admin'; // URL: /admin
    });

    $app->router('/products', 'GET', function() {
        echo 'Quản lý sản phẩm'; // URL: /admin/products
    });

    // Group lồng nhau
    $app->group('/settings', function($app) {
        $app->router('/general', 'GET', function() {
            echo 'Cài đặt chung'; // URL: /admin/settings/general
        });
    });
});
```

### Route xử lý lỗi

Bạn có thể định nghĩa một route đặc biệt để xử lý các lỗi 404 hoặc lỗi truy cập trong một group.

```php
// Định nghĩa trang lỗi 404 chung cho toàn bộ trang web
$app->router('::404', 'GET', function($params) use ($app) {
    echo $app->render('views/errors/404.php', ['path' => $params['path']]);
});

// Định nghĩa trang lỗi 500 chung
$app->router('::500', 'GET', function() use ($app) {
    echo $app->render('views/errors/500.php');
});

// Tạo một group cho khu vực admin
$app->group('/admin', function($app) {
    
    // Các route admin bình thường
    $app->router('/dashboard', 'GET', function() { echo 'Admin Dashboard'; });

    // Ghi đè lại trang lỗi 404 chỉ riêng cho khu vực /admin
    $app->router('::404', 'GET', function() use ($app) {
        echo $app->render('views/admin/error_404.php');
    });

    // Định nghĩa trang lỗi 403 (cấm truy cập) cho khu vực admin
    $app->router('::403', 'GET', function() use ($app) {
        echo '<h1>ACCESS DENIED FOR ADMIN AREA</h1>';
    });
});
```
Kích hoạt lỗi một cách chủ động
```php
$app->router('/critical-operation', 'GET', function() use ($app) {
    try {
        // Một thao tác có thể gây lỗi nghiêm trọng
        $result = $app->get('some_table', '*');
        if (!$result) {
            // Giả sử đây là một lỗi không mong muốn
            throw new Exception("Cannot get data.");
        }
        echo "Operation successful!";
    } catch (Exception $e) {
        // Ghi log lỗi
        // error_log($e->getMessage());
        
        // Hiển thị trang lỗi 500 cho người dùng
        $app->triggerError(500);
    }
});
$app->router('/admin/secret-data', 'GET', function() use ($app) {
    $userRole = $app->getSession('role'); // Giả sử 'guest'
    
    if ($userRole !== 'admin') {
        // Kích hoạt trang lỗi 403 (Forbidden)
        $app->triggerError(403);
    }
    
    echo "Here is the secret data.";
})->middleware('auth');
```
-----

## Middleware

Middleware cho phép bạn thực thi một logic nào đó (ví dụ: kiểm tra xác thực) trước khi request được xử lý bởi route.

### Đăng ký Middleware

Sử dụng `setMiddleware()` để đăng ký. Hàm callback của middleware phải trả về `true` để tiếp tục, hoặc `false` để dừng lại.

```php
// Đăng ký middleware kiểm tra đăng nhập
$app->setMiddleware('auth', function() use ($app) {
    if (!$app->getSession('user_id')) {
        $app->redirect('/login');
        return false; // Dừng xử lý
    }
    return true; // Cho phép tiếp tục
});
```

### Áp dụng Middleware

Sử dụng phương thức `middleware()` ngay sau khi định nghĩa route hoặc group.

```php
// Áp dụng cho một route
$app->router('/dashboard', 'GET', function() { ... })->middleware('auth');

// Áp dụng cho cả một group
$app->group('/admin', function($app) {
    // Tất cả các route trong này sẽ được bảo vệ bởi middleware 'auth'
    $app->router('/posts', 'GET', function() { ... });
    $app->router('/users', 'GET', function() { ... });
})->middleware('auth');
```

-----

## Tương tác Cơ sở dữ liệu (Medoo)

ECLO App tích hợp sẵn [Medoo](https://medoo.in/) để làm việc với CSDL. Bạn có thể gọi các phương thức của Medoo trực tiếp từ đối tượng `$app`.

```php
// Lấy tất cả người dùng
$users = $app->select('users', '*');

// Lấy một người dùng theo điều kiện
$user = $app->get('users', '*', ['id' => 1]);

// Chèn dữ liệu
$app->insert('users', [
    'username' => 'eclo',
    'email' => 'info@eclo.vn'
]);

// Cập nhật
$app->update('users', ['email' => 'new.email@eclo.vn'], ['id' => 1]);

// Xóa
$app->delete('users', ['id' => 1]);

// Sử dụng cú pháp tĩnh
$count = ECLO\App::table('users')->count();
```

Để biết thêm các phương thức truy vấn, vui lòng tham khảo [tài liệu của Medoo](https://medoo.in/api/where).

-----

## Hệ thống Hooks (Actions) 🔌

### Khái niệm Hooks

**Hooks** (hay Actions) là các điểm đánh dấu cụ thể trong vòng đời của ứng dụng. Tại các điểm này, bạn có thể đăng ký để thực thi các hàm của riêng mình. Hooks không dùng để thay đổi dữ liệu, mà dùng để **thực hiện một hành động** tại một thời điểm nhất định (ví dụ: ghi log, gửi email, cập nhật CSDL, gọi API ngoài).

### API: `addHook()`

Dùng để đăng ký một hàm (callback) vào một hook cụ thể.

**Chữ ký (Signature):**

```php
$app->addHook(string $tag, callable $callback, int $priority = 10);
```

**Tham số:**

  * `$tag` (string): Tên của hook mà bạn muốn "móc" vào.
  * `$callback` (callable): Hàm sẽ được thực thi khi hook được kích hoạt.
  * `$priority` (int): Thứ tự ưu tiên thực thi. **Số nhỏ hơn sẽ được chạy trước**. Mặc định là `10`.

**Ví dụ:** Ghi log mỗi khi có lỗi 404 xảy ra.

```php
// Đăng ký hành động vào hook 'before_error_trigger'
$app->addHook('before_error_trigger', function($statusCode, $params) {
    if ($statusCode === 404) {
        $logMessage = date('Y-m-d H:i:s') . " - 404 Not Found at path: " . $params['path'] . "\n";
        file_put_contents('404_errors.log', $logMessage, FILE_APPEND);
    }
});
```

### API: `doHook()`

Dùng để **kích hoạt** một hook, thực thi tất cả các hàm đã được đăng ký với nó. Đây là công cụ để bạn có thể tạo ra các "điểm mở rộng" (extension points) trong chính logic ứng dụng của mình.

**Chữ ký (Signature):**

```php
$app->doHook(string $tag, ...$args);
```

**Tham số:**

  * `$tag` (string): Tên duy nhất của hook mà bạn muốn kích hoạt.
  * `...$args` (mixed): Một hoặc nhiều tham số mà bạn muốn truyền vào các hàm callback.

**Ví dụ:** Tạo hook `after_user_registration` để xử lý các tác vụ sau khi người dùng đăng ký thành công.

**1. Trong Controller, tạo điểm hook:**

```php
// Controller xử lý đăng ký
function handle_registration() {
    global $app;

    // ... Logic lưu người dùng vào database ...
    $newUser = ['id' => 123, 'email' => 'newuser@example.com']; // Dữ liệu người dùng mới

    if ($newUser) {
        // Kích hoạt hook và truyền dữ liệu người dùng mới vào
        $app->doHook('after_user_registration', $newUser);
        echo "Đăng ký thành công!";
    }
}
```

**2. Ở nơi khác, đăng ký các hành động:**

```php
// file functions.php

// Hành động 1: Gửi email chào mừng
$app->addHook('after_user_registration', function($user) {
    // Logic gửi mail đến $user['email']
    echo "Đã gửi mail chào mừng tới " . $user['email'];
}, 10);

// Hành động 2: Thêm người dùng vào danh sách Mailchimp
$app->addHook('after_user_registration', function($user) {
    // Logic gọi API của Mailchimp
    echo "Đã thêm " . $user['email'] . " vào Mailchimp.";
}, 20);
```

### Danh sách Hooks có sẵn

  * `before_run`: Chạy ngay khi `$app->run()` được gọi.
  * `after_run`: Chạy trước khi script kết thúc sau khi đã xử lý xong request.
  * `before_checks`: Chạy trước khi middleware và quyền được kiểm tra.
  * `after_checks`: Chạy sau khi middleware và quyền đã được kiểm tra xong.
  * `before_route_callback`: Chạy ngay trước khi hàm của route được thực thi.
  * `after_route_callback`: Chạy ngay sau khi hàm của route đã thực thi xong.
  * `before_error_trigger`: Chạy khi một lỗi được kích hoạt, trước khi trang lỗi được hiển thị.
  * `before_render`: Chạy trước khi một file template được render.
  * `after_render`: Chạy sau khi HTML của template đã được tạo nhưng trước khi trả về.
  * `before_component_render`: Chạy trước khi một component được render.
  * `after_component_render`: Chạy sau khi HTML của component đã được tạo.

-----

## Hệ thống Filters 💧

### Khái niệm Filters

**Filters** cung cấp một cách để **sửa đổi** dữ liệu trong quá trình ứng dụng xử lý. Một giá trị sẽ được truyền qua một chuỗi các hàm callback, mỗi hàm có thể thay đổi giá trị đó và **bắt buộc phải trả về** phiên bản đã sửa đổi để hàm tiếp theo trong chuỗi sử dụng.

### API: `addFilter()`

Dùng để đăng ký một hàm lọc dữ liệu.

**Chữ ký (Signature):**

```php
$app->addFilter(string $tag, callable $callback, int $priority = 10);
```

**Tham số:**

  * `$tag`, `$priority`: Tương tự `addHook`.
  * `$callback` (callable): Hàm xử lý dữ liệu. **QUAN TRỌNG**: Hàm này phải nhận giá trị cần lọc làm tham số đầu tiên và **bắt buộc phải `return` một giá trị**.

**Ví dụ:** Thêm tên website vào sau tiêu đề trang.

```php
// Đăng ký filter cho 'page_title'
$app->addFilter('page_title', function($title) {
    // Nối thêm tên website và trả về
    return $title . ' | My Awesome Site';
});
```

### API: `applyFilters()`

Dùng để áp dụng tất cả các filter đã đăng ký cho một giá trị cụ thể.

**Chữ ký (Signature):**

```php
$app->applyFilters(string $tag, $value, ...$args);
```

**Tham số:**

  * `$tag` (string): Tên của filter cần áp dụng.
  * `$value` (mixed): Giá trị ban đầu cần được lọc/sửa đổi.
  * `...$args` (mixed): Các tham số bổ sung không bị sửa đổi, dùng để cung cấp thêm ngữ cảnh cho các hàm callback.

**Ví dụ:** Tạo một filter cho phép thay đổi giá sản phẩm trước khi hiển thị.

**1. Trong hàm hiển thị giá, tạo điểm filter:**

```php
function display_product_price($product) {
    global $app;
    $basePrice = $product['price'];

    // Áp dụng filter 'product_price', truyền giá gốc và cả đối tượng sản phẩm làm ngữ cảnh
    $finalPrice = $app->applyFilters('product_price', $basePrice, $product);

    return 'Giá: ' . number_format($finalPrice) . ' VND';
}
```

**2. Ở nơi khác, đăng ký các hàm lọc:**

```php
// file functions.php

// Hàm 1: Giảm giá 10% cho tất cả sản phẩm
$app->addFilter('product_price', function($price) {
    return $price * 0.9; // Giảm 10%
}, 10);

// Hàm 2: Giảm thêm 50,000 VND cho sản phẩm có danh mục 'sale'
$app->addFilter('product_price', function($price, $product) {
    // Tham số $product là ngữ cảnh được truyền vào từ applyFilters
    if ($product['category'] === 'sale') {
        return $price - 50000;
    }
    return $price;
}, 20); // Chạy sau khi đã giảm 10%
```

### Danh sách Filters có sẵn

  * `before_render_vars`: Áp dụng cho mảng `$vars` trước khi truyền vào template.
  * `after_render_output`: Áp dụng cho chuỗi HTML sau khi template đã được render xong.
  * `component_vars`: Tương tự `before_render_vars` nhưng dành riêng cho component.
    
-----

## Bảo mật

### Lọc XSS

Sử dụng phương thức `xss()` để làm sạch dữ liệu đầu vào từ người dùng.

```php
/**
 * @param string      $string        Chuỗi cần làm sạch.
 * @param bool        $allowHtml     Mặc định là false (loại bỏ toàn bộ HTML). Đặt là `true` để cho phép HTML an toàn.
 * @param array|null  $customConfig  Mảng cấu hình HTMLPurifier tùy chỉnh.
 */
public function xss($string, $allowHtml = false, $customConfig = null)
```

**Ví dụ:**

```php
// 1. Mặc định: Loại bỏ tất cả HTML (an toàn nhất)
$username = "<h1>Admin</h1>";
$safeUsername = $app->xss($username); // Kết quả: "&lt;h1&gt;Admin&lt;/h1&gt;"

// 2. Cho phép HTML: Sử dụng cấu hình mặc định trong __construct
$comment = "<p onclick='alert(1)'>Nội dung <b>an toàn</b></p>";
$safeComment = $app->xss($comment, true); // Kết quả: "<p>Nội dung <b>an toàn</b></p>"

// 3. Cho phép HTML với cấu hình tùy chỉnh
$articleContent = '<h2>Bài viết</h2>';
$config = ['HTML.Allowed' => 'h2,p'];
$safeArticle = $app->xss($articleContent, true, $config); // Kết quả: "<h2>Bài viết</h2>"
```

### JSON Web Tokens (JWT)

Dễ dàng tạo và xác thực JWT.

```php
// Cấu hình key bí mật một lần
$app->JWT('your-secret-key');

// Tạo token
$payload = [
    'iss' => 'https://eclo.vn',
    'aud' => 'https://eclo.vn',
    'iat' => time(),
    'exp' => time() + 3600, // Hết hạn sau 1 giờ
    'user_id' => 123
];
$token = $app->addJWT($payload);

// Giải mã và xác thực token
$decoded = $app->decodeJWT($token);
if ($decoded) {
    echo "Xin chào user " . $decoded->user_id;
} else {
    echo "Token không hợp lệ hoặc đã hết hạn.";
}
```

### Giới hạn truy cập (Rate Limiting)

Chống brute-force bằng cách giới hạn số lần request từ một IP.

```php
// Giới hạn việc đăng nhập: 5 lần mỗi 60 giây
$app->rateLimit('login_attempt', 5, 60);

// Nếu vượt quá, ứng dụng sẽ tự động dừng và trả về lỗi 429
```

-----

## Views & Templates

### Render một View

Sử dụng `render()` để hiển thị một file view và truyền dữ liệu vào nó.

```php
// Trong route của bạn
$app->router('/profile', 'GET', function() use ($app) {
    $data = [
        'title' => 'Trang cá nhân',
        'user' => ['name' => 'ECLO']
    ];
    // Sẽ render file views/profile.php
    echo $app->render('views/profile.php', $data);
});

// Trong views/profile.php
// <h1><?php echo $title; ?></h1>
// <p>Xin chào, <?php echo $user['name']; ?></p>
```

### Sử dụng Layout chung

Bạn có thể định nghĩa một file layout chung để bao bọc các view.

```php
// Thiết lập file layout
$app->setGlobalFile('views/layouts/main.php');

// Trong views/layouts/main.php
// <html>
// <head><title><?php echo $title ?? 'Trang web'; ?></title></head>
// <body>
//   <header>...</header>
//   <?php include $templatePath; // Dòng này sẽ nạp view con ?>
//   <footer>...</footer>
// </body>
// </html>
```

### Components

Tạo các thành phần view có thể tái sử dụng.

```php
// Đăng ký component
$app->setComponent('userCard', function($vars) {
    $user = $vars['user'];
    echo "<div class='card'><h3>{$user['name']}</h3><p>{$user['email']}</p></div>";
});

// Render component trong view
// echo $app->component('userCard', ['user' => ['name' => 'ECLO', 'email' => 'info@eclo.vn']]);
```

-----

## Tiện ích khác

### Gửi Email (PHPMailer)

```php
$mailConfig = [
    'host' => 'smtp.example.com',
    'username' => 'user@example.com',
    'password' => 'password',
    'encryption' => 'tls', // tls hoặc ssl
    'port' => 587,
    'from_email' => 'noreply@example.com',
    'from_name' => 'ECLO App'
];

try {
    $mailer = $app->Mail($mailConfig);
    $mailer->addAddress('recipient@example.net', 'Joe User');
    $mailer->isHTML(true);
    $mailer->Subject = 'Here is the subject';
    $mailer->Body    = 'This is the HTML message body <b>in bold!</b>';
    $mailer->send();
    echo 'Message has been sent';
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mailer->ErrorInfo}";
}
```

### Tải lên file (Upload)

```php
if (!empty($_FILES['my_file'])) {
    $handle = $app->upload($_FILES['my_file']);
    if ($handle->uploaded) {
        $handle->process('/path/to/save/');
        if ($handle->processed) {
            echo 'File uploaded: ' . $handle->file_dst_name;
            $handle->clean();
        } else {
            echo 'Error: ' . $handle->error;
        }
    }
}
```

### Nén file (Minify)

```php
// Nén CSS
$app->minifyCSS('path/to/style.css', 'path/to/style.min.css');

// Nén JS
$app->minifyJS(['path/to/script1.js', 'path/to/script2.js'], 'path/to/all.min.js');
```

### Session và Cookie

```php
// Session
$app->setSession('user_id', 123);
$userId = $app->getSession('user_id');
$app->deleteSession('user_id');

// Cookie
$app->setCookie('remember_me', 'some_token', time() + (86400 * 30)); // 30 ngày
$token = $app->getCookie('remember_me');
$app->deleteCookie('remember_me');
```

### Điều hướng (Redirect)

```php
// Chuyển hướng đến URL cụ thể
$app->redirect('/login');

// Quay lại trang trước đó
$app->back();
```

-----

## Bản quyền

Thư viện này được phát hành dưới giấy phép **MIT**. Xem file `LICENSE` để biết thêm chi tiết.
