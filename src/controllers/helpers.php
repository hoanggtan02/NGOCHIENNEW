<?php
if (!defined('ECLO')) die("Hacking attempt");
class Jatbi
{
	protected $app;
	protected $cachedUser = null;
	protected $cachedPermissions = null;
	protected $cachedConfig = null;
	protected $isUserChecked = false;
	protected $cachedBrands = [];
	private $cachedAccountBrands = null;
	public function __construct($app)
	{
		$this->app = $app;
	}
	private function _getConfig()
	{
		if ($this->cachedConfig === null) {
			$this->cachedConfig = $this->app->get("config", "*");
		}
		return $this->cachedConfig;
	}
	public function date($date)
	{
		$getConfig = $this->app->get("config", "*");
		return date($getConfig['date'], strtotime($date ?? ''));
	}
	public function time($time)
	{
		$getConfig = $this->app->get("config", "*");
		return date($getConfig['time'], strtotime($time ?? ''));
	}
	public function datetime($date)
	{
		$getConfig = $this->app->get("config", "*");
		return date($getConfig['date'] . ' ' . $getConfig['time'], strtotime($date ?? ''));
	}
	private function _getAuthenticatedUser()
	{
		if ($this->cachedUser !== null) {
			return $this->cachedUser;
		}
		if ($this->isUserChecked) {
			return false;
		}
		$this->isUserChecked = true;
		$session = $this->app->getSession("accounts");
		if (!$session || !isset($session['id'])) {
			$this->cachedUser = false;
			return false;
		}
		$user = $this->app->get("accounts", ["id", "type", "permission", "active", "name", "avatar", "email"], [
			"deleted" => 0,
			"status" => 'A',
			"id" => $session['id']
		]);
		$this->cachedUser = $user ?: false; // Lưu kết quả vào cache
		return $this->cachedUser;
	}
	private function _getUserPermissions()
	{
		if ($this->cachedPermissions !== null) {
			return $this->cachedPermissions;
		}
		$user = $this->_getAuthenticatedUser();
		if (!$user || empty($user['permission'])) {
			$this->cachedPermissions = [];
			return [];
		}
		$permissionRow = $this->app->get("permission", ["group"], [
			"deleted" => 0,
			"status" => 'A',
			"id" => $user['permission']
		]);
		$permissions = !empty($permissionRow['group']) ? unserialize($permissionRow['group']) : [];
		$this->cachedPermissions = $permissions ?: [];
		return $this->cachedPermissions;
	}
	private function _getAccountBrands()
	{
		if ($this->cachedAccountBrands !== null) {
			return $this->cachedAccountBrands;
		}

		$user = $this->_getAuthenticatedUser();
		if (!$user) {
			$this->cachedAccountBrands = [];
			return [];
		}

		$this->cachedAccountBrands = $this->app->select("brands_linkables", [
			"[>]brands" => ["brands" => "id"]
		], [
			"brands.id",
			"brands.name",
			"brands.active",
			"brands.address",
		], [
			"brands_linkables.data" => $user['id'],
			"brands_linkables.type" => "accounts",
			"brands.status" => "A",
			"brands.deleted" => 0,
			"ORDER" => ["brands_linkables.id" => "ASC"]
		]);

		return $this->cachedAccountBrands;
	}
	public function brands($type = null, $post = null)
	{
		// Sử dụng $post làm một phần của key cache nếu nó tồn tại
		$cacheKey = is_null($type) ? 'default' : $type;
		if ($type === 'CHECK' || $type === 'POST') {
			$cacheKey .= '_' . serialize($post);
		}

		if (isset($this->cachedBrands[$cacheKey])) {
			return $this->cachedBrands[$cacheKey];
		}

		$checkuser = $this->_getAuthenticatedUser();
		if (!$checkuser) {
			return null; // Không có user, không có brand
		}

		$cookie = $this->app->getCookie('brands') ?? null;
		$result = null;

		switch ($type) {
			case 'SELECT':
				$accountBrands = $this->_getAccountBrands();
				$result = array_map(function ($brand) {
					return [
						'name' => $brand['name'],
						'id' => $brand['id'],
						'value' => $brand['id'],
						'address' => $brand['address'],
						'text' => $brand['name'],
						'active' => $brand['active'],
					];
				}, $accountBrands);
				break;

			case 'SET':
				$accountBrands = $this->_getAccountBrands();
				$activeList = array_column($accountBrands, 'active');

				if (empty($cookie)) {
					$newCookieValue = (count($accountBrands) === 1) ? $accountBrands[0]['active'] : 0;
					$this->app->setCookie("brands", $newCookieValue, time() + ((3600 * 24 * 30) * 12), '/');
				} elseif (count($accountBrands) === 1 && $cookie != $accountBrands[0]['active']) {
					$this->app->setCookie("brands", $accountBrands[0]['active'], time() + ((3600 * 24 * 30) * 12), '/');
				} elseif (count($accountBrands) > 1 && !in_array($cookie, $activeList)) {
					$this->app->setCookie("brands", 0, time() + ((3600 * 24 * 30) * 12), '/');
				}
				$result = ''; // Giữ nguyên hành vi gốc
				break;

			case 'CHECK':
				$accountBrands = $this->_getAccountBrands();
				$activeList = array_column($accountBrands, 'active');
				$result = in_array($post, $activeList);
				break;

			case 'GET':
				if (!empty($cookie) && $cookie != 0) {
					$result = $this->app->get("brands", "*", ["deleted" => 0, "status" => 'A', "active" => $cookie]);
				} else {
					$result = ["name" => $this->lang("Tất cả"), "active" => 0];
				}
				break;

			case 'ID':
				if (!empty($cookie) && $cookie != 0) {
					$result = $this->app->get("brands", "id", ["deleted" => 0, "status" => 'A', "active" => $cookie]);
				} else {
					$result = 0;
				}
				break;

			case 'POST':
				$allBrands = $this->app->select("brands", "*", ["deleted" => 0, "status" => 'A']);
				if (count($allBrands) === 1) {
					$result = $allBrands[0]['id'];
				} elseif ($post != null) {
					$result = $post;
				} elseif (!empty($cookie) && $cookie != 0 && in_array($cookie, array_column($allBrands, 'active'))) {
					$result = $this->app->get("brands", "id", ["active" => $cookie]);
				}
				break;

			default: // Trường hợp $type = null hoặc không khớp
				$accountBrands = $this->_getAccountBrands();
				if (!empty($cookie) && $cookie != 0) {
					// Tìm id brand tương ứng với active trong cookie
					$brandId = null;
					foreach ($accountBrands as $brand) {
						if ($brand['active'] == $cookie) {
							$brandId = $brand['id'];
							break;
						}
					}
					$result[] = $brandId;
				} else {
					$result = array_column($accountBrands, 'id');
				}
				break;
		}

		$this->cachedBrands[$cacheKey] = $result;
		return $result;
	}
	public function parseDateRange($dateRange = null)
	{
		$dateStart = null;
		$dateEnd = null;

		if (!empty($dateRange) && strpos($dateRange, ' - ') !== false) {
			[$startStr, $endStr] = explode(' - ', $dateRange);
			$startDate = DateTime::createFromFormat('d/m/Y', trim($startStr));
			$endDate = DateTime::createFromFormat('d/m/Y', trim($endStr));

			if ($startDate && $endDate) {
				// Ngày bắt đầu từ 12:00 của startDate
				$startDate->setTime(12, 0, 0);
				// Ngày kết thúc là 12:00 của ngày tiếp theo sau endDate
				$endDate->modify('+1 day')->setTime(12, 0, 0);

				$dateStart = $startDate->format('Y-m-d H:i:s');
				$dateEnd = $endDate->format('Y-m-d H:i:s');
			}
		} else {
			// Không có dateRange, xác định theo thời gian hiện tại
			$now = new DateTime();
			$noonToday = (clone $now)->setTime(12, 0, 0);

			if ($now < $noonToday) {
				// Trước 12h → thuộc về ngày hôm trước
				$dateStartObj = (clone $now)->modify('-1 day')->setTime(12, 0, 0);
				$dateEndObj = (clone $now)->setTime(12, 0, 0);
			} else {
				// Sau 12h → thuộc về hôm nay
				$dateStartObj = (clone $now)->setTime(12, 0, 0);
				$dateEndObj = (clone $now)->modify('+1 day')->setTime(12, 0, 0);
			}

			$dateStart = $dateStartObj->format('Y-m-d H:i:s');
			$dateEnd = $dateEndObj->format('Y-m-d H:i:s');
		}

		return [$dateStart, $dateEnd];
	}
	public function invoices_details($invoices = [], $detail = [])
	{
		$brands = $invoices['brands'] ?? $this->brands();
		$settingBrans = $this->app->get("brands", ["steps", "start_one"], ["id" => $brands]);
		$amount = $detail['amount'] ?? 0;
		$disabel = 'false';
		$date_start = '';
		$date_end = '';
		$date_end_full = '';
		if (($detail['type'] ?? '') === 'services' && ($detail['form'] ?? '') == 1) {
			if (($detail['stop_time'] ?? '') == 1) {
				$amount = $this->getTime($detail['date_start'], $detail['date_end'], $settingBrans['start_one'], $settingBrans['steps']);
				$date_start = date("H:i", strtotime($detail['date_start']));
				$date_end = date("H:i", strtotime($detail['date_end']));
				$date_end_full = date("Y-m-d H:i:00", strtotime($detail['date_end']));
				$disabel = 'true';
			} elseif (($invoices['process'] ?? '') == '2' && ($detail['stop_time'] ?? '') == 0) {
				$amount = $this->getTime($detail['date_start'], date("Y-m-d H:i:s"), $settingBrans['start_one'], $settingBrans['steps']);
				$date_start = date("H:i", strtotime($detail['date_start']));
				$date_end = date("H:i");
				$date_end_full = date("Y-m-d H:i:00");
				$disabel = 'true';
			} elseif (($invoices['status'] ?? '') == '1' && ($invoices['process'] ?? '') == 200 && ($detail['stop_time'] ?? '') == 0) {
				$amount = $this->getTime($detail['date_start'], $invoices['completed_date'], $settingBrans['start_one'], $settingBrans['steps']);
				$date_start = date("H:i", strtotime($detail['date_start']));
				$date_end = date("H:i", strtotime($invoices['completed_date']));
				$date_end_full = date("Y-m-d H:i:00", strtotime($invoices['completed_date']));
				$disabel = 'true';
			}
		}
		$price = $detail['price'] ?? 0;
		$discount_price = ($detail['discount_price'] ?? 0) * $amount;
		$discount_amount = ($detail['discount_amount'] ?? 0) * $price;
		$minus = $detail['minus'] ?? 0;
		$total_price = $amount * $price;
		$total_discount = $discount_price + $discount_amount + $minus;
		$total = $total_price - $total_discount;
		return [
			"disabel" => $disabel,
			"amount" => $amount,
			"date_start" => $date_start,
			"date_end" => $date_end,
			"date_end_full" => $date_end_full,
			"discount_price" => $discount_price,
			"discount_amount" => $discount_amount,
			"minus" => $minus,
			"price" => $price,
			"total_price" => $total_price,
			"total_discount" => $total_discount,
			"total" => $total,
		];
	}
	public function invoices($invoices = [], $details = [], $payments = [])
	{
		$data = $invoices ?? [];
		$tax['10'] = 0;
		$total = [
			'total' => 0,
			'menu' => 0,
			'services' => 0,
			'combos' => 0,
			'discount_amount' => 0,
			'discount_products' => 0,
		];
		$total_pay = 0;
		$details_payments = [];
		$giam_gia_tren_hoa_don = 0;
		$services_price = 0;
		$intro = [];

		if (count($payments) == 0) {
			$payments = $this->app->select("invoices_payments", "*", [
				"invoices" => $data['id'],
				"deleted" => 0
			]) ?? [];
		}
		if (count($details) == 0) {
			$details = $this->app->select("invoices_details", "*", [
				"deleted" => 0,
				"invoices" => $data['id']
			]);
		}
		foreach ($details as $detail) {
			$getDetails = $this->invoices_details($data, $detail);
			$total['total'] += $getDetails['total_price'] ?? 0;
			$total['discount_products'] += $getDetails['total_discount'] ?? 0;
			$total['discount_amount'] += $getDetails['discount_amount'] ?? 0;
		}

		foreach ($payments as $payment) {
			if ($payment['total']) {
				$total_pay += $payment['total'];
				$form = $this->app->get("payments", ["name", "id"], ["id" => $payment['payment']]);
				$details_payments[] = [
					"form" => $form['name'] ?? '',
					"id" => $form['id'] ?? 0,
					"total" => $payment['total'] ?? 0,
				];
			}
		}

		if (isset($_SESSION['prepayment'][$data['id']])) {
			foreach ($_SESSION['prepayment'][$data['id']] as $payment) {
				$total_pay +=  (float) $payment['total'];
			}
		}

		if (isset($data['discount'])) {
			$giam_gia_tren_hoa_don = (($total['total'] - $total['discount_products']) * $data['discount']) / 100;
		}
		$tong_giam_gia =  ($giam_gia_tren_hoa_don ?? 0) + ($total['discount_products'] ?? 0) + ($data['price'] ?? 0);

		$tam_tinh_sau_giam_gia = ($total['total'] ?? 0) - $tong_giam_gia;


		if (isset($data['services_fee'])) {
			$services_price = ($tam_tinh_sau_giam_gia * $data['services_fee']) / 100;
		}


		$tam_tinh_sau_phi_dich_vu = $tam_tinh_sau_giam_gia + $services_price;

		$tax['10'] += (($tam_tinh_sau_phi_dich_vu ?? 0) * 10 / 100);

		$getpayment = $tam_tinh_sau_phi_dich_vu + array_sum($tax);

		if ($total_pay < $getpayment) {
			$intro = [
				"name" => $this->lang("Còn Nợ"),
				"price" => $total_pay - $getpayment,
			];
		}

		$surcharge = $invoices['surcharge'] ?? 0;

		$total_payments = $getpayment + $surcharge;

		return [
			"total" => round($total['total']),
			"total_services" => round($total['services']),
			"total_menu" => round($total['menu']),
			"total_combos" => round($total['combos']),
			"tax" => $tax,
			"total_tax" => round(array_sum($tax)),
			"discount" => round($data['discount'] ?? 0),
			"discount_price" => round($giam_gia_tren_hoa_don),
			"discount_products" => round($total['discount_products'] ?? 0),
			"discount_amount" => round($total['discount_amount'] ?? 0),
			"total_discount" => round($tong_giam_gia ?? 0),
			"services_fee" => round($data['services_fee'] ?? 0),
			"services_price" => round($services_price),
			"price" => round($data['price'] ?? 0),
			"provisional_before" => round($tam_tinh_sau_giam_gia),
			"provisional" => round($tam_tinh_sau_phi_dich_vu),
			"surcharge_payments" => round($getpayment),
			"total_payments" => round($total_payments),
			"total_pay" => round($total_pay),
			"details_payments" => $details_payments,
			"surcharge" => $surcharge,
			"into-money" => $intro,
		];
	}
	// public function getTime($start, $end, $startFromOne = 0, $Steps = '10:0,30:0,59:0') {
	// 	$stepConfig = [];
	//     $pairs = explode(',', $Steps); // Tách chuỗi thành các cặp '10:0', '30:0', '59:0'
	//     foreach ($pairs as $pair) {
	//         $parts = explode(':', $pair); // Tách mỗi cặp thành [limit, extra]
	//         if (count($parts) === 2) {
	//             $limit = (int)trim($parts[0]);
	//             $extra = (int)trim($parts[1]); // Dùng float để linh hoạt hơn
	//             $stepConfig[$limit] = $extra;
	//         }
	//     }
	//     ksort($stepConfig); // Đảm bảo thứ tự tăng dần

	//     $startTime = new DateTime($start ?? '00:00');
	//     $endTime   = new DateTime($end ?? '00:00');

	//     // Đặt giây = 0 để không bị lẻ giây
	//     $startTime->setTime((int)$startTime->format('H'), (int)$startTime->format('i'), 0);
	//     $endTime->setTime((int)$endTime->format('H'), (int)$endTime->format('i'), 0);

	//     $totalMinutes = ($endTime->getTimestamp() - $startTime->getTimestamp()) / 60;

	//     if ($totalMinutes <= 0) return 0; // Không tính âm hoặc 0

	//     $baseHours    = floor($totalMinutes / 60);
	//     $extraMinutes = $totalMinutes - ($baseHours * 60);

	//     // Nếu bật chế độ bắt đầu từ 1 giờ
	//     if ($startFromOne==1 && $totalMinutes < 60) {
	//         return 1;
	//     }

	//     // Tính phần dư theo step
	//     $extraCharge = null;
	//     foreach ($stepConfig as $limit => $extra) {
	//         if ($extraMinutes < $limit) {
	//             $extraCharge = $extra;
	//             break;
	//         }
	//     }

	//     // Nếu vượt mốc lớn nhất -> cộng thêm 1h
	//     if ($extraCharge === null) {
	//         $extraCharge = 1;
	//     }

	//     // Nếu extraCharge = 0 thì vẫn giữ phút thành số lẻ
	//     if ($extraCharge === 0) {
	//         return round($baseHours + ($extraMinutes / 60), 2);
	//     }

	//     // Nếu extraCharge = 1 thì nhảy tròn giờ
	//     return $baseHours + $extraCharge;
	// }
	public function getTime($start, $end, $startFromOne = 0, $Steps = '10:0,30:0,59:0')
	{
		$stepConfig = [];
		$pairs = explode(',', $Steps);
		foreach ($pairs as $pair) {
			$parts = explode(':', $pair);
			if (count($parts) === 2) {
				$limit = (int)trim($parts[0]);
				$extra = (int)trim($parts[1]);
				$stepConfig[$limit] = $extra;
			}
		}
		ksort($stepConfig);

		$startTime = new DateTime($start ?? '00:00');
		$endTime   = new DateTime($end ?? '00:00');

		$startTime->setTime((int)$startTime->format('H'), (int)$startTime->format('i'), 0);
		$endTime->setTime((int)$endTime->format('H'), (int)$endTime->format('i'), 0);

		$totalMinutes = ($endTime->getTimestamp() - $startTime->getTimestamp()) / 60;
		if ($totalMinutes <= 0) return 0;

		$baseHours    = floor($totalMinutes / 60);
		$extraMinutes = $totalMinutes - ($baseHours * 60);

		// Nếu bật chế độ bắt đầu từ 1 giờ
		if ($startFromOne == 1) {
			if ($totalMinutes <= 60) {
				return 1; // Luôn tối thiểu 1 giờ
			}

			// Dùng step để nhảy giờ, không tính số lẻ phút
			$extraCharge = null;
			foreach ($stepConfig as $limit => $extra) {
				if ($extraMinutes < $limit) {
					$extraCharge = $extra;
					break;
				}
			}
			if ($extraCharge === null) {
				$extraCharge = 1;
			}
			return $baseHours + $extraCharge;
		}

		// Trường hợp bình thường (vẫn cho phép tính lẻ phút khi step = 0)
		$extraCharge = null;
		foreach ($stepConfig as $limit => $extra) {
			if ($extraMinutes < $limit) {
				$extraCharge = $extra;
				break;
			}
		}
		if ($extraCharge === null) {
			$extraCharge = 1;
		}

		if ($extraCharge === 0) {
			return round($baseHours + ($extraMinutes / 60), 2);
		}

		return $baseHours + $extraCharge;
	}

	public function setBrands($action, $type, $data, $brands = null)
	{
		if (empty($brands) && $action == 'edit') {
			$brands = $this->app->select("brands_linkables", "brands", [
				"type" => $type,
				"data" => $data
			]) ?? [];
		}
		$PostBrand = $this->brands('POST', $brands ?? '');
		$PostBrand = is_array($PostBrand) ? $PostBrand : [$PostBrand];
		if ($action === 'add') {
			foreach ($PostBrand as $option) {
				$insert_option = [
					"brands" => $option,
					"type"   => $type,
					"data"   => $data,
				];
				$this->app->insert("brands_linkables", $insert_option);
				$insert['brands'][] = $insert_option;
			}
		} elseif ($action === 'edit') {
			$existing_options = $this->app->select("brands_linkables", "*", [
				"data" => $data,
				"type" => $type
			]);

			$existing_map = [];
			foreach ($existing_options as $opt) {
				$existing_map[$opt['brands']] = $opt['id'];
			}

			$current_brands = [];

			foreach ($PostBrand as $option) {
				if (isset($existing_map[$option])) {
					// Cập nhật nếu cần (có thể mở rộng logic)
					$current_brands[] = $option;
				} else {
					$this->app->insert("brands_linkables", [
						"brands" => $option,
						"type"   => $type,
						"data"   => $data,
					]);
					$current_brands[] = $option;
				}
			}
			foreach ($existing_map as $brand => $id) {
				if (!in_array($brand, $current_brands)) {
					$this->app->delete("brands_linkables", ["id" => $id]);
				}
			}
		} elseif ($action === 'select') {
			return  $this->app->select("brands_linkables", "brands", ["data" => $data, "type" => $type]);
		}
	}
	private function _getAccountStores()
	{
		if ($this->cachedAccountBrands !== null) { // Vẫn dùng cache cũ để tránh lỗi
			return $this->cachedAccountBrands;
		}

		$user = $this->_getAuthenticatedUser();
		if (!$user) {
			$this->cachedAccountBrands = [];
			return [];
		}

		$where = [
			"status" => "A",
			"deleted" => 0
		];

		// Logic mới: Đọc quyền từ cột 'stores' của bảng 'accounts'
		if (!empty($user['stores'])) {
			$store_ids = json_decode($user['stores'], true);
			if (is_array($store_ids) && !empty($store_ids)) {
				$where['id'] = $store_ids;
			} else {
				// Nếu cột stores có dữ liệu nhưng không phải JSON hợp lệ, không trả về gì
				$this->cachedAccountBrands = [];
				return [];
			}
		}
		// Nếu $user['stores'] rỗng, không thêm điều kiện 'id', tức là lấy tất cả (dành cho super admin)

		$this->cachedAccountBrands = $this->app->select("stores", [
			"id",
			"name",
			"address",
		], $where);

		return $this->cachedAccountBrands;
	}
	public function stores($type = null, $post = null)
	{
		$cacheKey = 'stores_' . ($type ?? 'default') . serialize($post);
		if (isset($this->cachedBrands[$cacheKey])) { // Vẫn dùng cache cũ để tránh lỗi
			return $this->cachedBrands[$cacheKey];
		}

		$checkuser = $this->_getAuthenticatedUser();
		if (!$checkuser) return null;

		$cookie = $this->app->getCookie('stores') ?? null;
		$result = null;

		switch ($type) {
			case 'SELECT':
				$accountStores = $this->_getAccountStores();
				$result = array_map(function ($store) {
					return [
						'name' => $store['name'],
						'id' => $store['id'],
						'value' => $store['id'],
						'address' => $store['address'],
						'text' => $store['name'],
					];
				}, $accountStores);
				break;

			case 'SET':
				$accountStores = $this->_getAccountStores();
				$idList = array_column($accountStores, 'id');

				if (empty($cookie)) {
					$newCookieValue = (count($accountStores) === 1) ? $accountStores[0]['id'] : 0;
					$this->app->setCookie("stores", $newCookieValue, time() + ((3600 * 24 * 30) * 12), '/');
				} elseif (count($accountStores) === 1 && $cookie != $accountStores[0]['id']) {
					$this->app->setCookie("stores", $accountStores[0]['id'], time() + ((3600 * 24 * 30) * 12), '/');
				} elseif (count($accountStores) > 1 && !in_array($cookie, $idList)) {
					$this->app->setCookie("stores", 0, time() + ((3600 * 24 * 30) * 12), '/');
				}
				$this->app->setCookie("branch", 0, -1, '/');
				$result = '';
				break;

			case 'GET':
				if (!empty($cookie) && $cookie != 0) {
					$result = $this->app->get("stores", "*", ["deleted" => 0, "status" => 'A', "id" => $cookie]);
				} else {
					$result = ["name" => $this->lang("Tất cả cửa hàng"), "id" => 0];
				}
				break;

			case 'ID':
				if (!empty($cookie) && $cookie != 0) {
					$result = (int)$cookie;
				} else {
					$result = 0;
				}
				break;

			case 'POST': // Dùng cho hàm setStores, đảm bảo luôn trả về một mảng ID
				if ($post === null || $post === '') {
					$result = [];
				} else {
					$result = is_array($post) ? $post : [$post];
				}
				break;

			default:
				$accountStores = $this->_getAccountStores();
				if (!empty($cookie) && $cookie != 0) {
					if (in_array($cookie, array_column($accountStores, 'id'))) {
						$result = [$cookie];
					} else {
						$result = array_column($accountStores, 'id');
					}
				} else {
					$result = array_column($accountStores, 'id');
				}
				break;
		}

		$this->cachedBrands[$cacheKey] = $result;
		return $result;
	}
	public function setStores($action, $type, $data, $stores = null)
	{
		// Lấy danh sách stores hiện tại nếu là edit và không có stores mới được truyền vào
		if (empty($stores) && $action == 'edit') {
			$stores = $this->app->select("stores_linkables", "stores", [
				"type" => $type,
				"data" => $data
			]) ?? [];
		}

		// Giả định bạn đã có hàm $this->stores() thay thế cho $this->brands()
		$PostStore = $this->stores('POST', $stores ?? '');
		$PostStore = is_array($PostStore) ? $PostStore : [$PostStore];

		if ($action === 'add') {
			foreach ($PostStore as $option) {
				$insert_option = [
					"stores" => $option, // Thay 'brands' bằng 'stores'
					"type"   => $type,
					"data"   => $data,
				];
				$this->app->insert("stores_linkables", $insert_option); // Thay bảng
			}
		} elseif ($action === 'edit') {
			// 1. Lấy tất cả các liên kết stores đang có của đối tượng
			$existing_options = $this->app->select("stores_linkables", "*", [ // Thay bảng
				"data" => $data,
				"type" => $type
			]);

			// 2. Tạo một map để tra cứu nhanh: [store_id => link_id]
			$existing_map = [];
			foreach ($existing_options as $opt) {
				$existing_map[$opt['stores']] = $opt['id']; // Thay 'brands' bằng 'stores'
			}

			$current_stores = [];

			// 3. Lặp qua danh sách stores mới để thêm hoặc giữ lại
			foreach ($PostStore as $option) {
				if (isset($existing_map[$option])) {
					// Giữ lại: store này đã có, không cần làm gì
					$current_stores[] = $option;
				} else {
					// Thêm mới: store này chưa có, insert vào DB
					$this->app->insert("stores_linkables", [ // Thay bảng
						"stores" => $option, // Thay 'brands' bằng 'stores'
						"type"   => $type,
						"data"   => $data,
					]);
					$current_stores[] = $option;
				}
			}

			// 4. Lặp qua danh sách stores cũ để xóa những liên kết không còn tồn tại
			foreach ($existing_map as $store => $id) {
				if (!in_array($store, $current_stores)) {
					// Xóa: store cũ này không có trong danh sách mới, cần xóa đi
					$this->app->delete("stores_linkables", ["id" => $id]); // Thay bảng
				}
			}
		} elseif ($action === 'select') {
			// Trả về danh sách ID các stores được liên kết
			return $this->app->select("stores_linkables", "stores", ["data" => $data, "type" => $type]); // Thay bảng và cột
		}
	}
	public function ajax($type = null)
	{
		if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
			return 'global';
		}
		if (isset($type)) {
			if ($type == 'show') {
				return ' show';
			} else {
				return ' show d-block position-static';
			}
		}
	}
	public function csrfToken()
	{
		return $_SESSION['csrf_token'] ?? '';
	}
	public function csrfField()
	{
		return '<input type="hidden" name="csrf_token" value="' . $this->csrfToken() . '">';
	}
	public function verifyCsrfToken()
	{
		if (!isset($_POST['csrf_token']) || !hash_equals($this->app->getSession('csrf_token'), $_POST['csrf_token'])) {
			http_response_code(403);
			die('Invalid CSRF token.');
		}
	}
	public function lang($key)
	{
		global $lang;
		return isset($lang[$key]) ? $lang[$key] : $key;
	}
	public function url($path = '')
	{
		$setting = $this->app->getValueData('setting');
		$manager = rtrim($setting['manager'] ?? '', '/');
		$path = ($path === '/') ? '' : $path;

		if ($manager === '' && $path === '') {
			return '/';
		}

		return $manager . $path;
	}
	public function totalStorage()
	{
		$account = $this->_getAuthenticatedUser();
		$totalStorage = 5 * 1024 * 1024 * 1024; // 5GB = 5 * 1024^3 bytes
		$where = [
			"AND" => [
				"account" => $account['id'],
				"deleted" => 0,
			]
		];
		// Đảm bảo $sum luôn là số
		$sum = floatval($this->app->sum("files", "size", ["AND" => $where['AND']]) ?? 0);
		$data['total_size'] = $this->formatFileSize($sum);
		$usedPercentage = ($sum / $totalStorage) * 100;
		$data['used_percentage'] = round($usedPercentage, 2);

		return $data;
	}
	public function checkFiles($active)
	{
		$account = $this->_getAuthenticatedUser();
		if (!$account) return false;
		$files = $this->app->get("files", "*", [
			"active" => $active,
			// "deleted" => 0,
		]);
		if (!$files) {
			return false; // Trả về 0 nếu file không tồn tại
		}
		$getPermission = $this->app->count("files_accounts", "id", [
			"data" => $files['id'],
			"type" => 'files',
			"account" => $account['id'],
			// "deleted" => 0,
		]);
		if ($files['account'] == $account['id']) { // Nếu là chủ sở hữu
			$viewer = true; // Chủ sở hữu luôn có quyền
		} else { // Nếu không phải chủ sở hữu
			if ($files['permission'] == 0) {
				$viewer = false; // Không chia sẻ, chỉ chủ sở hữu có quyền
			} else if ($files['permission'] == 1 && $getPermission > 0) {
				$viewer = false; // Private và có quyền trong files_accounts => Không có quyền
			} else if ($files['permission'] == 2 && $getPermission == 0) {
				$viewer = false; // Public và không có quyền trong files_accounts => Không có quyền
			} else {
				$viewer = true; // Trường hợp còn lại (Public và có quyền, Private và không có quyền) => Có quyền
			}
		}
		if (!$viewer && $files['category'] != 0) { // Nếu không có quyền trực tiếp và file có category
			$folder = $this->folders($files['category']);
			foreach ($folder as $item) {
				if ($item['id'] == $files['category']) {
					$viewer = true;
					break;
				}
			}
		}
		return $viewer;
	}
	public function checkFolder($active)
	{
		$folder = $this->app->get("files_folders", "*", [
			"active" => $active,
			"deleted" => 0,
		]);
		$getFolder = $this->folders($folder['id']);
		$found = false;
		foreach ($getFolder as $item) {
			if ($item['id'] == $folder['id']) {
				$found = true;
				break;
			}
		}
		return $found;
	}
	public function getParentFolders($folder_id)
	{
		$parentFolders = [];
		$currentFolderId = $folder_id;

		while ($currentFolderId > 0) {
			$folder = $this->app->get("files_folders", ["id", "main"], ["id" => $currentFolderId]);
			if (!$folder) break;

			$parentFolders[] = $folder["id"];
			$currentFolderId = $folder["main"];
		}

		return array_reverse($parentFolders); // Đảo ngược để có thứ tự từ gốc đến con
	}
	public function getChildFolders($folder_id)
	{
		$childFolders = [];
		$folders = $this->app->select("files_folders", ["id"], ["main" => $folder_id]);
		foreach ($folders as $folder) {
			$childFolders[] = $folder["id"];
			$childFolders = array_merge($childFolders, $this->getChildFolders($folder["id"]));
		}
		return $childFolders;
	}
	public function getFilesInFolder($folder_id)
	{
		return $this->app->select("files", "*", ["category" => $folder_id]);
	}
	public function duplicateFolderStructure($folder_id, $new_parent_id = 0)
	{
		$account = $this->_getAuthenticatedUser();
		if (!$account) return false;
		// Lấy thông tin thư mục gốc
		$originalFolder = $this->app->get("files_folders", ["name"], ["id" => $folder_id]);
		if (!$originalFolder) return false;

		// Tạo thư mục mới
		$this->app->insert("files_folders", [
			"name" => $originalFolder["name"],
			"main" => $new_parent_id,
			"account" => $account['id'],
			"active" => $this->active(),
			"modify" => date("Y-m-d H:i:s"),
			"date" => date("Y-m-d H:i:s"),
			"permission" => 0,
		]);
		$newFolderId = $this->app->id();
		// Lấy tất cả file của thư mục gốc và nhân bản
		$files = $this->getFilesInFolder($folder_id);
		foreach ($files as $data) {
			$geturl = $data['url']; // URL của file cần sao chép
			$folder_path = 'datas/' . $account['active'];
			$name_file = $this->active();
			$destination = $folder_path . '/' . $name_file . '.' . $data['extension']; // Đổi tên file khi lưu
			if (copy($geturl, $destination)) {
				$url = $destination;
			}
			$insert = [
				"category" => $newFolderId,
				"account" => $account['id'],
				"name" => $data['name'],
				"extension" => $data['extension'],
				"url" => $url,
				"data" => $data['data'],
				"size" => $data['size'],
				"mime" => $data['mime'],
				"permission" => 0,
				"modify" => date("Y-m-d H:i:s"),
				"date" => date("Y-m-d H:i:s"),
				"active" => $this->active(),
			];
			$this->app->insert("files", $insert);
		}
		$childFolders = $this->getChildFolders($folder_id);
		foreach ($childFolders as $childFolderId) {
			$this->duplicateFolderStructure($childFolderId, $newFolderId);
		}
		return $newFolderId;
	}
	public function folders($folder_id)
	{
		$breadcrumb = [];
		$account = $this->app->get("accounts", "*", ["id" => $this->app->getSession("accounts")]);
		$parentFolders = $this->getParentFolders($folder_id);
		// Kiểm tra xem người dùng có phải là chủ sở hữu không
		$isOwner = false;
		if (!empty($parentFolders)) {
			$firstFolder = $this->app->get("files_folders", ["account"], ["id" => $parentFolders[0]]);
			if ($firstFolder && $firstFolder["account"] == $account['id']) {
				$isOwner = true;
			}
		}
		if ($isOwner) {
			// Nếu là chủ sở hữu, hiển thị breadcrumb đầy đủ
			foreach ($parentFolders as $id) {
				$folder = $this->app->get("files_folders", ["id", "name", "active"], ["id" => $id]);
				if ($folder) {
					$breadcrumb[] = ["id" => $folder["id"], "name" => $folder["name"], "active" => $folder['active']];
				}
			}
		} else {
			// Nếu không phải chủ sở hữu, áp dụng logic chia sẻ
			$sharedFolderIndex = -1;
			foreach ($parentFolders as $index => $id) {
				$folder = $this->app->get("files_folders", ["id", "name", "permission", "account", "active"], ["id" => $id]);
				if (!$folder) continue;

				$hasPermission = $this->app->count("files_accounts", "id", [
					"data" => $folder["id"],
					"type" => "folder",
					"account" => $account['id'],
					"deleted" => 0
				]);

				$viewer = ($folder["account"] == $account['id']); // Chủ sở hữu luôn có quyền
				if (!$viewer) {
					$viewer = ($folder["permission"] == 1 && $hasPermission == 0) || ($folder["permission"] == 2 && $hasPermission > 0);
				}

				if ($viewer && $folder["permission"] > 0) {
					$sharedFolderIndex = $index;
				}
			}

			if ($sharedFolderIndex >= 0) {
				for ($i = $sharedFolderIndex; $i < count($parentFolders); $i++) {
					$folder = $this->app->get("files_folders", ["id", "name", "active"], ["id" => $parentFolders[$i]]);
					if ($folder) {
						$breadcrumb[] = ["id" => $folder["id"], "name" => $folder["name"], "active" => $folder['active']];
					}
				}
			}
		}

		return $breadcrumb;
	}
	public function formatResponse($response)
	{
		$response = htmlspecialchars($response, ENT_QUOTES, 'UTF-8');
		if (substr_count($response, '```') % 2 != 0) {
			$response .= "\n```";
		}
		$pattern = '/```(\w*)\n([\s\S]*?)```/';
		$replacement = '<pre><code class="language-$1">$2</code></pre>';
		$formattedResponse = preg_replace($pattern, $replacement, $response);
		$formattedResponse = nl2br($formattedResponse);
		return $formattedResponse;
	}
	public function active(int $version = 4): string
	{
		switch ($version) {
			case 7:
				$time = (int)(microtime(true) * 1000);
				$timestampBytes = substr(pack('J', $time), 2);
				$randomBytes = random_bytes(10);
				$data = $timestampBytes . $randomBytes;
				$data[6] = chr(ord($data[6]) & 0x0f | 0x70);
				$data[8] = chr(ord($data[8]) & 0x3f | 0x80);
				break;

			case 4:
			default:
				$data = random_bytes(16);
				$data[6] = chr(ord($data[6]) & 0x0f | 0x40);
				$data[8] = chr(ord($data[8]) & 0x3f | 0x80);
				break;
		}
		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}
	public function searchFiles($getType)
	{
		$mimeTypes = [];
		switch ($getType) {
			case 'images':
				$mimeTypes = [
					'image/jpeg',
					'image/png',
					'image/gif',
					'image/webp',
					'image/bmp',
					'image/tiff',
					'image/svg+xml',
					'image/x-icon'
				];
				break;

			case 'doc':
				$mimeTypes = [
					'application/msword',
					'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
					'application/pdf',
					'application/vnd.ms-excel',
					'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
					'application/vnd.ms-powerpoint',
					'application/vnd.openxmlformats-officedocument.presentationml.presentation',
					'text/plain',
					'application/rtf'
				];
				break;

			case 'audio':
				$mimeTypes = [
					'audio/mpeg',
					'audio/wav',
					'audio/ogg',
					'audio/aac',
					'audio/mp4',
					'audio/webm',
					'audio/flac'
				];
				break;
		}
		return $mimeTypes;
	}
	public function getFileIcon($filePath)
	{
		$files = $this->app->get("files", "*", ["active" => $this->app->xss($filePath)]);
		$url = '/files/views/';
		$fileName = strtolower($files['url']);
		$iconsPath = "assets/icons/";

		// Định dạng ảnh -> Trả về chính URL của file
		$imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
		if ($this->hasExtension($fileName, $imageExtensions)) {
			return $url . $files['active']; // Trả về chính URL file hình ảnh
		}

		// Định dạng PDF
		if ($this->hasExtension($fileName, ['pdf'])) {
			return '/' . $iconsPath . "pdf.png";
		}

		// Định dạng văn bản
		if ($this->hasExtension($fileName, ['txt'])) {
			return '/' . $iconsPath . "files.png";
		}

		// Định dạng nén (RAR, ZIP)
		if ($this->hasExtension($fileName, ['rar'])) {
			return '/' . $iconsPath . "rar.png";
		}
		if ($this->hasExtension($fileName, ['zip'])) {
			return '/' . $iconsPath . "zip.png";
		}

		// Định dạng âm thanh
		$audioExtensions = ['mp3', 'wav', 'aac', 'flac', 'ogg', 'wma'];
		if ($this->hasExtension($fileName, $audioExtensions)) {
			return '/' . $iconsPath . "audio.png";
		}

		// Định dạng PowerPoint
		$pptExtensions = ['ppt', 'pptx', 'pps', 'ppsx'];
		if ($this->hasExtension($fileName, $pptExtensions)) {
			return '/' . $iconsPath . "ppt.png";
		}

		// Định dạng Word
		$wordExtensions = ['doc', 'docx', 'dot', 'dotx', 'rtf'];
		if ($this->hasExtension($fileName, $wordExtensions)) {
			return '/' . $iconsPath . "doc.png";
		}

		// Định dạng Excel
		$excelExtensions = ['xls', 'xlsx', 'xlsm', 'csv'];
		if ($this->hasExtension($fileName, $excelExtensions)) {
			return '/' . $iconsPath . "xls.png";
		}

		// Trả về icon mặc định nếu không tìm thấy loại file
		return '/' . $iconsPath . "files.png";
	}
	public function hasExtension($fileName, $extensions)
	{
		foreach ($extensions as $ext) {
			if (str_ends_with($fileName, "." . $ext)) {
				return true;
			}
		}
		return false;
	}
	public function createZipFromFolder($folder_id)
	{
		$downloadDir = "download/";
		if (!is_dir($downloadDir)) {
			mkdir($downloadDir, 0777, true);
		}
		$files = $this->getAllFilesFromFolder($folder_id);
		if (empty($files)) return false;
		// Lấy tên thư mục gốc
		$folder_name = $this->app->get("files_folders", "name", ["id" => $folder_id]) ?? "folder_" . $folder_id;
		$zipFilePath = $downloadDir . $folder_name . "_" . time() . ".zip";

		$zip = new ZipArchive();
		if ($zip->open($zipFilePath, ZipArchive::CREATE) !== TRUE) {
			return false;
		}
		// Danh sách file đã thêm để kiểm tra trùng lặp
		$addedFiles = [];
		// Thêm file vào ZIP giữ nguyên cấu trúc thư mục
		foreach ($files as $file) {
			$file_path = $file["url"];
			if (file_exists($file_path)) {
				$relative_path = $this->buildRelativePath($file["category"], $file["name"]);

				// Kiểm tra nếu file bị trùng, thêm timestamp
				if (isset($addedFiles[$relative_path])) {
					$pathInfo = pathinfo($relative_path);
					$relative_path = $pathInfo['dirname'] . "/" . $pathInfo['filename'] . "_" . time() . "." . $pathInfo['extension'];
				}

				$zip->addFile($file_path, $relative_path);
				$addedFiles[$relative_path] = true;
			}
		}
		$zip->close();
		return $zipFilePath;
	}
	public function getAllFilesFromFolder($folder_id)
	{
		$folders = [$folder_id]; // Danh sách thư mục cần lấy file
		$allFiles = [];
		// Lấy tất cả thư mục con đệ quy
		$subFolders = $this->app->select("files_folders", ["id"], ["main" => $folder_id]);
		foreach ($subFolders as $subFolder) {
			$folders[] = $subFolder["id"];
		}
		// Lấy tất cả file trong các thư mục
		foreach ($folders as $fid) {
			$files = $this->app->select("files", ["id", "url", "name", "category"], ["category" => $fid]);
			$allFiles = array_merge($allFiles, $files);
		}
		return $allFiles;
	}
	public function buildRelativePath($folder_id, $file_name)
	{
		$path_parts = [];
		while ($folder_id > 0) {
			$folder = $this->app->get("files_folders", ["id", "name", "main"], ["id" => $folder_id]);
			if (!$folder) break;
			$path_parts[] = $folder["name"];
			$folder_id = $folder["main"];
		}
		return implode("/", array_reverse($path_parts)) . "/" . $file_name;
	}
	public function viewsFile($filePath, $token)
	{
		$setting = $this->app->getValueData("setting");
		$files = $this->app->get("files", "*", ["active" => $this->app->xss($filePath)]);
		$fileName = strtolower($files['url']);
		$iconsPath = "assets/icons/";
		$domain = "https://" . $_SERVER['HTTP_HOST'] . "/files/data";
		$path = $domain . '/' . $files['active'] . '?token=' . $token;
		$imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
		if ($this->hasExtension($fileName, $imageExtensions)) {
			return '<img src=' . $path . ' class="h-100">'; // Trả về chính URL file hình ảnh
		}

		// Định dạng PDF
		if ($this->hasExtension($fileName, ['pdf'])) {
			return "<iframe src='https://docs.google.com/viewer?url=" . $path . "&embedded=true' width='100%' style='height: calc(100vh - 125px);'' frameborder='0'></iframe>";
		}

		// Định dạng âm thanh
		$audioExtensions = ['mp3', 'wav', 'aac', 'flac', 'ogg', 'wma'];
		if ($this->hasExtension($fileName, $audioExtensions)) {
			return '/' . $iconsPath . "audio.png";
		}

		// Định dạng PowerPoint
		$pptExtensions = ['ppt', 'pptx', 'pps', 'ppsx'];
		if ($this->hasExtension($fileName, $pptExtensions)) {
			return "<iframe src='https://view.officeapps.live.com/op/embed.aspx?src=" . $path . "' width='100%' style='height: calc(100vh - 125px);'' frameborder='0'></iframe>";
		}

		// Định dạng Word
		$wordExtensions = ['doc', 'docx', 'dot', 'dotx', 'rtf'];
		if ($this->hasExtension($fileName, $wordExtensions)) {
			return "<iframe src='https://view.officeapps.live.com/op/embed.aspx?src=" . $path . "' width='100%' style='height: calc(100vh - 125px);'' frameborder='0'></iframe>";
		}

		// Định dạng Excel
		$excelExtensions = ['xls', 'xlsx', 'xlsm', 'csv'];
		if ($this->hasExtension($fileName, $excelExtensions)) {
			return "<iframe src='https://view.officeapps.live.com/op/embed.aspx?src=" . $path . "' width='100%' style='height: calc(100vh - 125px);'' frameborder='0'></iframe>";
		}

		// Trả về icon mặc định nếu không tìm thấy loại file
		return 'tải về';
	}
	public function formatFileSize($sizeInKB)
	{
		$units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
		$size = is_numeric($sizeInKB) ? floatval($sizeInKB) : 1000;
		$unitIndex = 0;
		while ($size >= 1024 && $unitIndex < count($units) - 1) {
			$size /= 1024;
			$unitIndex++;
		}
		return round($size ?? 0, 2) . " " . $units[$unitIndex];
	}
	public function getFileExtension($filename)
	{
		return pathinfo($filename, PATHINFO_EXTENSION);
	}
	public function deleteFolder($folderPath)
	{
		if (!is_dir($folderPath)) {
			return false;
		}
		$files = array_diff(scandir($folderPath), ['.', '..']);
		foreach ($files as $file) {
			$filePath = $folderPath . DIRECTORY_SEPARATOR . $file;
			if (is_dir($filePath)) {
				$this->deleteFolder($filePath);
			} else {
				unlink($filePath);
			}
		}
		return rmdir($folderPath);
	}
	public function checkAuthenticated($requests, $setting)
	{
		$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
		if ($this->app->getCookie('token') && !$this->app->getSession("accounts")) {
			$this->restoreSessionFromToken();
		}
		$user = $this->_getAuthenticatedUser();
		if (!isset($user['id'])) {
			return $this->clearAuth();
		}
		$this->app->setValueData('account', $user);
		$session = $this->app->getSession("accounts");

		$loginExists = $this->app->has("accounts_login", [
			"accounts" => $user['id'],
			"token" => $session['token'],
			"agent" => $session['agent'],
			"deleted" => 0
		]);
	}
	public function checkPermissionMenu($requests)
	{
		$permissionsArray = $this->_getUserPermissions();
		$permissions = array_merge(["login" => 'login'], $permissionsArray);
		$this->app->setUserPermissions($permissions);
		$this->app->setValueData('menu', $this->buildMenu($requests, $permissions));
	}
	private function restoreSessionFromToken()
	{
		$decoded = $this->app->decodeJWT($this->app->getCookie('token'), '');
		if (!$decoded) return;

		$accountId = $this->app->get("accounts", "id", ["active" => $decoded->id]);
		$login = $this->app->get("accounts_login", "*", [
			"accounts" => $accountId,
			"token" => $decoded->token,
			"agent" => $decoded->agent,
			"deleted" => 0,
			"ORDER" => ["id" => "DESC"]
		]);
		if ($login <= 1) return;

		$user = $this->app->get("accounts", "*", [
			"id" => $login['accounts'],
			"status" => "A",
			"deleted" => 0
		]);
		if ($user <= 1) return $this->clearAuth();

		$payload = [
			"ip"    => $this->app->xss($_SERVER['REMOTE_ADDR']),
			"id"    => $user['active'],
			"email" => $user['email'],
			"token" => $this->app->randomString(256),
			"agent" => $_SERVER["HTTP_USER_AGENT"],
		];
		$token = $this->app->addJWT($payload);

		// Kiểm tra login cũ
		$existingLogin = $this->app->get("accounts_login", "*", [
			"accounts" => $user['id'],
			"agent" => $payload['agent'],
			"deleted" => 0
		]);
		$loginData = [
			"accounts" => $user['id'],
			"ip" => $payload['ip'],
			"token" => $payload['token'],
			"agent" => $payload["agent"],
			"date" => date("Y-m-d H:i:s"),
		];
		if ($existingLogin > 1) {
			$this->app->update("accounts_login", $loginData, ["id" => $existingLogin['id']]);
		} else {
			$this->app->insert("accounts_login", $loginData);
		}

		// Lưu session + cookie
		$this->app->setSession('accounts', [
			"id" => $user['id'],
			"agent" => $payload['agent'],
			"token" => $payload['token'],
			"active" => $user['active'],
		]);
		$this->app->setCookie('token', $token, time() + ((3600 * 24 * 30) * 12), '/');
	}
	private function buildMenu($requests, $permissions)
	{
		$menusOut = [];
		foreach ($requests as $k => $menu) {
			$menusOut[$k] = ["name" => $menu['name'], "items" => []];
			if (empty($menu['item']) || !is_array($menu['item'])) continue;

			foreach ($menu['item'] as $ik => $item) {
				$url = !empty($item['url']) ? $this->url($item['url']) : '';
				$menusOut[$k]['items'][$ik] = [
					"menu" => $item['menu'] ?? '',
					"url" => $url,
					"main" => $item['main'] ?? '',
					"icon" => $item['icon'] ?? '',
					"action" => $item['action'] ?? '',
				];

				if (isset($item['permission'])) {
					$validSub = false;
					if (!empty($item['sub'])) {
						foreach ($item['sub'] as $sk => $sub) {
							if (isset($item['permission'][$sk], $permissions[$sk])) {
								$sub['router'] = $sub['router'] ?? null ? $this->url($sub['router']) : null;
								$menusOut[$k]['items'][$ik]["sub"][$sk] = $sub;
								$validSub = true;
							}
						}
					}
					if (!empty($item['sub']) && !$validSub) unset($menusOut[$k]['items'][$ik]);
					if (empty($item['sub']) && isset($item['permission'][$ik]) && !isset($permissions[$ik])) {
						unset($menusOut[$k]['items'][$ik]);
					}
				}
			}
		}
		return $menusOut;
	}
	private function clearAuth()
	{
		$this->app->deleteSession('accounts');
		$this->app->deleteCookie('token');
	}
	public function permission($permissions)
	{
		$user = $this->_getAuthenticatedUser();
		if (!$user) {
			return false;
		}
		if (empty($permissions)) {
			return true;
		}
		$userPermissions = $this->_getUserPermissions();
		if (empty($userPermissions)) {
			return false;
		}
		return count(array_intersect((array) $permissions, $userPermissions)) > 0;
	}
	public function notification($user, $account, $title, $body, $click_action, $template = null, $type = null, $data = null)
	{
		global $setting;
		if ($template == '') {
			$template = 'url';
		}
		$insert = [
			"user" => $user,
			"account" => $account,
			"title" => $title,
			"content" => $body,
			"url" => $click_action,
			"date" => date("Y-m-d H:i:s"),
			"template" => $template,
			"active" => $this->active(),
			"type" =>  $type ?? 'content',
			"data" => $data,
		];
		$this->app->insert("notifications", $insert);
		// $getsetting = $this->app->get("settings","*",["account"=>$account]);
		// if($getsetting['notification']==1){
		// $cmd = 'php /www/wwwroot/ellm.io/dev/run/notification.php ' . escapeshellarg(json_encode($insert));
		// exec($cmd . ' > /dev/null 2>&1 &', $output, $return_var);
		// }
	}
	public function logs($dispatch, $action, $content, $account = null)
	{
		if ($account === null) {
			$user = $this->_getAuthenticatedUser();
			$account = $user ? $user['id'] : 0; // Gán 0 nếu không có user
		}
		$ip = $_SERVER['REMOTE_ADDR'];
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		}
		$this->app->insert("logs", [
			"user" 		=> $account,
			"dispatch" 	=> $dispatch,
			"action" 	=> $action,
			"date" 		=> date('Y-m-d H:i:s'),
			"url" 		=> 'http://' . $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"],
			"ip" 		=> $ip,
			// "active"    => $this->active(),
			"browsers"	=> $_SERVER["HTTP_USER_AGENT"] ?? '',
			"content"   => json_encode($content),
		]);
	}
	public function trash($router, $content, $data)
	{
		$user = $this->_getAuthenticatedUser();
		$account = $user ? $user['id'] : 0;
		$ip = $_SERVER['REMOTE_ADDR'];
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		}
		$this->app->insert("trashs", [
			"account" 	=> $account,
			"content" 		=> $content,
			"router"    => $router,
			"data"		=> json_encode($data),
			"date" 		=> date('Y-m-d H:i:s'),
			"url" 		=> 'http://' . $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"],
			"ip" 		=> $ip,
			"active"    => $this->active(),
		]);
	}
	public function pages_ajax($count, $limit, $page, $class = null, $last = null)
	{
		global $view, $lang, $router, $detect;
		$total = ceil($count / $limit);
		$return = null;
		$url = $_SERVER['REQUEST_URI'];
		$urlParts = parse_url($url);
		parse_str($urlParts['query'] ?? '', $queryParams);
		if ($page < $total) {
			$queryParams['pg'] = $page + 1;
		} else {
			unset($queryParams['pg']);
		}
		$queryString = http_build_query($queryParams);
		$return .= '<div class="' . $class . '">';
		$return .= '<div class="pagination text-center w-100">';
		$getlast = '';

		if ($last) {
			$getlast = '&last=' . $last;
		}

		if ($total > 1) {
			if ($page != $total) {
				$return .= '<a href="' . $urlParts['path'] . '?' . $queryString . $getlast . '" class="page-link next pjax-load btn border-0 bg-light text-dark mx-auto">Xem thêm</a>';
			}
		}
		$return .= '</div>';
		$return .= '</div>';
		return $return;
	}
	public function pages($count, $limit, $page, $name = null)
	{
		global $view, $lang, $router, $detect;
		$total = ceil($count / $limit);
		$return = null;
		$getpage = null;
		$name = $name == '' ? '&pg' : $name;
		$return .= '<ul class="pagination">';
		if ($total > 1) {
			$url = $_SERVER['REQUEST_URI'];
			if ($_SERVER['QUERY_STRING'] == '') {
				$view = $url . '?';
			} else {
				$view = '?' . $_SERVER['QUERY_STRING'] . '';
			}
			$view = preg_replace("#(/?|&)" . $name . "=([0-9]{1,})#", "", $view);
			if ($page != 1) {
				$return .= '<li class="page-item mx-1"><a href="' . $view . $name . '=1" class="page-link rounded-3 bg-opacity-10 bg-secondary border-0" data-pjax >&laquo;&laquo;</a></li>';
				$return .= '<li class="page-item mx-1 d-none d-md-block"><a href="' . $view . $name . '=' . ($page - 1) . '" class="page-link rounded-3 bg-opacity-10 bg-secondary border-0" data-pjax >&laquo;</a></li>';
			}
			for ($number = 1; $number <= $total; $number++) {
				if ($page > 4 && $number == 1 || $page < $total - 1 && $number == $total) {
					$return .= '<li class="page-item mx-1 d-none d-md-block"><a href="#" class="page-link rounded-3 bg-opacity-10 bg-secondary border-0 page-link-hide">...</a><li>';
				}
				if ($number < $page + 4 && $number > $page - 4) {
					$return .= '<li class="page-item mx-1"><a href="' . $view . $name . '=' . $number . '" class="page-link rounded-3 bg-' . ($page == $number ? 'primary text-light' : 'secondary bg-opacity-10') . ' border-0" data-pjax >' . $number . '</a></li>';
				}
				$getnumber = $number;
			}
			if ($page != $total) {
				$return .= '<li class="page-item mx-1 d-none d-md-block"><a href="' . $view . $name . '=' . ($page + 1) . '" class="page-link rounded-3 bg-opacity-10 bg-secondary border-0" data-pjax >&raquo;</a></li>';
				$return .= '<li class="page-item mx-1"><a href="' . $view . $name . '=' . $total . '" class="page-link rounded-3 bg-opacity-10 bg-secondary border-0" data-pjax >&raquo;&raquo;</a></li>';
			}
		}
		$return .= '</ul>';
		return $return;
	}
	public function updateVersionInFile($filePath, $type, &$newVersion)
	{
		$content = file_get_contents($filePath);

		if ($type === 'js') {
			$pattern = '/main\.bundle\.js\?=v(\d+)\.(\d+)/';
		} else {
			$pattern = '/style\.bundle\.css\?=v(\d+)\.(\d+)/';
		}

		$content = preg_replace_callback($pattern, function ($matches) use ($type, &$newVersion) {
			$major = (int)$matches[1];
			$minor = (int)$matches[2];

			if ($minor < 9) {
				$minor++;
			} else {
				$minor = 0;
				$major++;
			}

			$newVersion = "v{$major}.{$minor}";
			$filename = ($type === 'js') ? 'main.bundle.js' : 'style.bundle.css';
			return "{$filename}?={$newVersion}";
		}, $content);

		file_put_contents($filePath, $content);
	}
	public function minifyHtml($html)
	{
		$html = preg_replace('/<!--(?!\[if).*?-->/s', '', $html);
		$html = preg_replace('/\s+/', ' ', $html);
		$html = preg_replace('/>\s+</', '><', $html);
		$html = trim($html);
		return $html;
	}
	public function getCommon($key = null, $default = null)
	{
		global $app;
		$common = $app->getValueData('common');

		if ($key === null) {
			return $common;
		}

		return isset($common[$key]) ? $common[$key] : $default;
	}
	public function getPluginCommon($pluginId, $key = null, $default = null)
	{
		global $app;
		$commonManager = $app->getValueData('commonManager');

		if (!$commonManager) {
			return $default;
		}

		$pluginCommon = $commonManager->getPluginCommon($pluginId);

		if ($key === null) {
			return $pluginCommon;
		}

		return isset($pluginCommon[$key]) ? $pluginCommon[$key] : $default;
	}
	public function hasPluginCommon($pluginId)
	{
		global $app;
		$commonManager = $app->getValueData('commonManager');

		if (!$commonManager) {
			return false;
		}

		return $commonManager->hasPluginCommon($pluginId);
	}
	public function getPluginsWithCommon()
	{
		global $app;
		$commonManager = $app->getValueData('commonManager');

		if (!$commonManager) {
			return [];
		}

		return $commonManager->getPluginsWithCommon();
	}

	public function getcode($datas)
	{
		global $app;
		$getdai = '';
		$getngoc = [
			'pearl'   => [],
			'sizes'   => [],
			'colors'  => [],
			'number'  => [],
			'quality' => [],
		];
		$getcode = '';
		foreach ($datas as $key => $value) {
			if (empty($value['deleted'])) {
				if ($value['type'] == 1) {
					$getdai = $app->get("ingredient", "code", ["id" => $value['ingredient']]);
				}
				if ($value['type'] == 2) {
					if (isset($value['pearl']))   $getngoc['pearl'][$value['pearl']] = $value['pearl'];
					if (isset($value['sizes']))   $getngoc['sizes'][$value['sizes']] = $value['sizes'];
					if (isset($value['colors']))  $getngoc['colors'][$value['colors']] = $value['colors'];

					if (isset($value['number'])) {
						$getngoc['number'][$value['number']] = $value['number'];
					}

					if (isset($value['quality'])) {
						$getngoc['quality'][$value['quality']] = $value['quality'];
					}
				}
			}
		}
		if (count($getngoc['pearl']) > 1 && count($getngoc['pearl']) <= 2) {
			foreach ($getngoc['pearl'] as $key2 => $value2) {
				$getcode .= $app->get("pearl", "code", ["id" => $value2])[0];
			}
			$getPearl = $getcode;
		} elseif (count($getngoc['pearl']) > 2) {
			$getPearl = "ZZ";
		} else {
			$getPearl = $app->get("pearl", "code", ["id" => reset($getngoc['pearl'])]);
		}
		if (count($getngoc['sizes']) > 1) {
			$getSize = "000";
		} else {
			$getSize = $app->get("sizes", "code", ["id" => reset($getngoc['sizes'])]);
		}
		if (count($getngoc['colors']) > 1) {
			$getColor = "Z";
		} else {
			$getColor = $app->get("colors", "code", ["id" => reset($getngoc['colors'])]);
		}
		if (count($getngoc['number']) > 1) {
			$getNumber = "Z";
		} else {
			$getNumber = $app->get("ingredient", "number", ["id" => $value['ingredient'], "type" => 2]);
		}
		if (count($getngoc['quality']) > 1) {
			$getQuality = "Z";
		} else {
			$getQuality = $app->get("ingredient", "quality", ["id" => $value['ingredient'], "type" => 2]);
		}
		return $getdai . $getPearl . $getColor . $getSize . $getNumber . $getQuality;
	}
	public function random($length = 16)
	{
		$random = bin2hex(random_bytes($length));
		return $random . time();
	}
	public function count_date($date_from, $date_to, $options = [])
	{
		$type = $options['type'] ?? 'full';
		$color = $options['color'] ?? false;

		try {
			$start = new DateTime($date_from);
			$end   = new DateTime($date_to);
		} catch (Exception $e) {
			return '';
		}

		$interval = $start->diff($end);

		$total_days = (int)$interval->format('%r%a');

		if ($total_days < 0) {
			$text = $this->lang('Hết hạn');
			if ($color) {
				return '<span class="text-danger">' . $text . '</span>';
			}
			return $text;
		}

		$color_class = 'text-secondary';
		if ($total_days <= 0) {
			$color_class = 'text-danger';
		} elseif ($total_days <= 2) {
			$color_class = 'text-warning';
		} elseif ($total_days <= 5) {
			$color_class = 'text-primary';
		}

		$text = '';
		if ($type === 'day') {
			$text = abs($total_days) . ' ' . $this->lang('ngay');
		} else {
			$parts = [];
			if ($interval->y > 0) $parts[] = $interval->y . ' ' . $this->lang('Năm');
			if ($interval->m > 0) $parts[] = $interval->m . ' ' . $this->lang('Tháng');
			if ($interval->d > 0) $parts[] = $interval->d . ' ' . $this->lang('Ngày');

			$text = empty($parts) ? $this->lang('Hôm nay') : implode(' ', $parts);
		}

		if ($total_days < 0) {
			$text = '-' . $text;
		}

		if ($color) {
			return '<span class="' . $color_class . '">' . $text . '</span>';
		}

		return $text;
	}
	public function week($day_number)
	{
		return match ((int)$day_number) {
			1 => 'T2',
			2 => 'T3',
			3 => 'T4',
			4 => 'T5',
			5 => 'T6',
			6 => 'T7',
			7 => 'CN',
			default => '',
		};
	}
	public function list_month()
	{
		$months = [];
		for ($i = 1; $i <= 12; $i++) {
			$months[] = [
				"value" => str_pad($i, 2, "0", STR_PAD_LEFT),
				"text"  => 'Tháng ' . $i,
			];
		}
		return $months;
	}

	public function list_year()
	{
		$years = [];
		$start_year = 2020;
		$current_year = date('Y');
		for ($i = $start_year; $i <= $current_year; $i++) {
			$years[] = [
				"value" => $i,
				"text"  => $i,
			];
		}
		return $years;
	}

	function callCameraApi(string $endpoint, array $payload): array
	{
		$curl = curl_init();
		curl_setopt_array($curl, [
			CURLOPT_URL => 'http://camera.ellm.io:8190/api/' . $endpoint,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0, // hoặc 30
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => http_build_query($payload),
			CURLOPT_HTTPHEADER => [
				'Content-Type: application/x-www-form-urlencoded'
			],
		]);
		$response = curl_exec($curl);
		$error = curl_error($curl);
		curl_close($curl);

		if ($error) {
			return ['success' => false, 'msg' => 'cURL Error: ' . $error];
		}

		return json_decode($response, true) ?? ['success' => false, 'msg' => 'Invalid JSON response from API.'];
	}
}
