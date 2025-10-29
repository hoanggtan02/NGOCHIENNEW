<?php

use Picqer\Barcode\BarcodeGeneratorPNG;

if (!defined('ECLO'))
	die("Hacking attempt");

use ECLO\App;

$template = __DIR__ . '/../templates';
$jatbi = $app->getValueData('jatbi');
$common = $jatbi->getPluginCommon('io.eclo.proposal');
$setting = $app->getValueData('setting');

$stores_json = $app->getCookie('stores') ?? json_encode([]);
$stores = json_decode($stores_json, true);
$session = $app->getSession("accounts");
$accStore = [];
if (isset($session['id'])) {
	$account = $app->get("accounts", "*", [
		"id" => $session['id'],
		"deleted" => 0,
		"status" => "A",
	]);
	if ($account['stores'] == '') {
		$accStore[0] = "0";
	}

	foreach ($stores as $itemStore) {
		$accStore[$itemStore['value']] = $itemStore['value'];
	}
}


//mã cố định
$app->group($setting['manager'] . "/warehouses", function ($app) use ($jatbi, $setting, $accStore,$template, $stores) {
	$app->router('/warehouses-move', ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting, $accStore, $stores,$template) {
		$vars['title'] = "Chuyển hàng";
		$action = "move";
		if (!isset($_SESSION['warehouses'][$action]['type'])) {
			$_SESSION['warehouses'][$action]['type'] = $action;
		}
		if (!isset($_SESSION['warehouses'][$action]['data'])) {
			$_SESSION['warehouses'][$action]['data'] = 'products';
		}
		if (!isset($_SESSION['warehouses'][$action]['date'])) {
			$_SESSION['warehouses'][$action]['date'] = date("Y-m-d");
		}
		if (count($stores) == 1) {
			$_SESSION['warehouses'][$action]['stores'] = ["id" => $app->get("stores", "id", ["id" => $accStore, "status" => 'A', "deleted" => 0, "ORDER" => ["id" => "ASC"]])];
		}
		$data = [
			"type" => $_SESSION['warehouses'][$action]['type'],
			"data" => $_SESSION['warehouses'][$action]['data'],
			"vendor" => $_SESSION['warehouses'][$action]['vendor'] ?? "",
			"stores" => $_SESSION['warehouses'][$action]['stores'] ?? "",
			"branch" => $_SESSION['warehouses'][$action]['branch'] ?? "",
			"stores_receive" => $_SESSION['warehouses'][$action]['stores_receive'] ?? "",
			"branch_receive" => $_SESSION['warehouses'][$action]['branch_receive'] ?? "",
			"date" => $_SESSION['warehouses'][$action]['date'],
			"content" => $_SESSION['warehouses'][$action]['content'] ?? "",
		];
		$SelectProducts = $_SESSION['warehouses'][$action]['products'] ?? [];
		$branchs = $app->select("branch", ["id(value)", "code", "name (text)"], ["deleted" => 0, "stores" => $data['stores'], "status" => 'A']);
		array_unshift($stores, ["value" => "", "code" => "", "text" => ""]);
		array_unshift($branchs, ["value" => "", "code" => "", "text" => ""]);
		$getbranchs = $app->select("branch", ["id(value) ", "code", "name(text)"], ["deleted" => 0, "stores" => $data['stores_receive'], "status" => 'A']);
		array_unshift($getbranchs, ["value" => "", "code" => "", "text" => ""]);
		$vars['data'] = $data;
		$stores_full = $app->select("stores", ["id (value)", "name(text)", "code"], ["deleted" => 0, "status" => 'A']);
		array_unshift($stores_full, ["value" => "", "code" => "", "text" => ""]);
		$vars['stores_full'] = $stores_full;
		$vars['SelectProducts'] = $SelectProducts;
		$vars['branchs'] = $branchs;
		$vars['getbranchs'] = $getbranchs;
		$vars['action'] = $action;
		$vars['stores'] = $stores;
		$store_options = [];
		if (!empty($stores)) {
			foreach ($stores as $store) {
				if (isset($store['value']) && isset($store['text'])) {
					$store_options[] = ['value' => $store['value'], 'text' => $store['text']];
				}
			}
		}
		$vars['store_options'] = $store_options;
		// Render template
		echo $app->render($template . '/warehouses/warehouses-move.html', $vars);
	});

	$app->router('/warehouses-update-2/move/stores', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
		$app->header(['Content-Type' => 'application/json']);
		$action = "move";
		$data = $app->get("stores", ["id", "name"], ["id" => $app->xss($_POST['value'])]);
		if ($data > 1) {
			$_SESSION['warehouses'][$action]['stores'] = [
				"id" => $data['id'],
				"name" => $data['name'],
			];
			if ($action == 'cancel') {
				$datas = $app->get("products", ["id", "units"], ["id" => $_SESSION['warehouses'][$action]['products']]);
				unset($_SESSION['warehouses'][$action]['warehouses']);
				$warehouses = $app->select("warehouses_details", ["id", "products", "vendor", "amount_total", "price", 'stores'], [
					"type" => 'import',
					"products" => $datas['id'],
					"stores" => $_SESSION['warehouses'][$action]['stores'],
					"deleted" => 0,
					"amount_total[>]" => 0
				]);
				foreach ($warehouses as $key => $value) {
					$_SESSION['warehouses'][$action]['warehouses'][$value['id']] = [
						"id" => $value['id'],
						"products" => $value['products'],
						"vendor" => $value['vendor'],
						"duration" => $value['duration'],
						"amount_total" => $value['amount_total'],
						"amount" => '',
						"price" => $value['price'],
						"units" => $datas['units'],
						"date" => $value['date'],
						"stores" => $value['stores'],
					];
				}
			}
			if ($action == 'move') {
				unset($_SESSION['warehouses'][$action]['products']);
				unset($_SESSION['warehouses'][$action]['branch']);
			}
			echo json_encode(['status' => 'success', 'content' => "Cập nhật thành công"]);
		} else {
			echo json_encode(['status' => 'error', 'content' => "Cập nhật thất bại"]);
		}
	});

	$app->router('/warehouses-update-2/move/branch', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
		$app->header(['Content-Type' => 'application/json']);
		$action = "move";
		$data = $app->get("branch", ["id", "name"], ["id" => $app->xss($_POST['value'])]);
		if ($data > 1) {
			$_SESSION['warehouses'][$action]['branch'] = [
				"id" => $data['id'],
				"name" => $data['name'],
			];
			if ($action == 'move') {
				unset($_SESSION['warehouses'][$action]['products']);
			}
			echo json_encode(['status' => 'success', 'content' => "Cập nhật thành công"]);
		} else {
			echo json_encode(['status' => 'error', 'content' => "Cập nhật thất bại"]);
		}
	});

	$app->router('/warehouses-update-2/move/branch_receive', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
		$app->header(['Content-Type' => 'application/json']);
		$action = "move";
		$data = $app->get("branch", ["id", "name"], ["id" => $app->xss($_POST['value'])]);
		if ($data > 1) {
			$_SESSION['warehouses'][$action]['branch_receive'] = [
				"id" => $data['id'],
				"name" => $data['name'],
			];
			echo json_encode(['status' => 'success', 'content' => "Cập nhật thành công"]);
		} else {
			echo json_encode(['status' => 'error', 'content' => "Cập nhật thất bại"]);
		}
	});

	$app->router('/warehouses-update-2/move/stores_receive', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
		$app->header(['Content-Type' => 'application/json']);
		$action = "move";
		$data = $app->get("stores", ["id", "name"], ["id" => $app->xss($_POST['value'])]);
		if ($data > 1) {
			$_SESSION['warehouses'][$action]['stores_receive'] = [
				"id" => $data['id'],
				"name" => $data['name'],
			];
			echo json_encode(['status' => 'success', 'content' => "Cập nhật thành công"]);
		} else {
			echo json_encode(['status' => 'error', 'content' => "Cập nhật thất bại"]);
		}
	});

	$app->router('/warehouses-update-2/move/{req}', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
		$app->header(['Content-Type' => 'application/json']);
		$action = "move";
		$_SESSION['warehouses'][$action][$vars['req']] = $app->xss($_POST['value']);
		echo json_encode(['status' => 'success', 'content' => "Cập nhật thành công"]);
	});


	$app->router('/warehouses-update-2/move/products/add/{id}', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
		$app->header(['Content-Type' => 'application/json']);
		$action = "move";
		$id = $vars['id'];
		$data = $app->get("products", ["id", "images", "code", "name", "categorys", "default_code", "units", "notes", "crafting", "amount"], ["id" => $app->xss($id)]);
		if ($data > 1) {
			if ($data['amount'] <= 0 && $action == 'move') {
				$error = "Số lượng không đủ";
			}
			if (!isset($error)) {
				if (!in_array($data['id'], $_SESSION['warehouses'][$action]['products'] ?? [])) {
					$_SESSION['warehouses'][$action]['products'][$data['id']] = [
						"products" => $data['id'],
						"amount_buy" => 0,
						"amount" => 1,
						"price" => 0,
						"images" => $data['images'],
						"code" => $data['code'],
						"name" => $data['name'],
						"categorys" => $data['categorys'],
						"default_code" => $data['default_code'],
						"units" => $data['units'],
						"notes" => $data['notes'],
						"crafting" => $data['crafting'],
						"warehouses" => $data['amount'],
					];
					echo json_encode(['status' => 'success', 'content' => "Cập nhật thành công"]);
				} else {
					$getPro = $_SESSION['warehouses'][$action]['products'][$data['id']];
					$value = $getPro['amount'] + 1;
					if ($value > $data['amount'] && $action == 'move') {
						$_SESSION['warehouses'][$action]['products'][$data['id']]['amount'] = $data['amount'];
						echo json_encode(['status' => 'error', 'content' => "Số lượng không đủ"]);
					} else {
						$_SESSION['warehouses'][$action]['products'][$data['id']]['amount'] = $value;
						echo json_encode(['status' => 'success', 'content' => "Cập nhật thành công"]);
					}
				}
			} else {
				echo json_encode(['status' => 'error', 'content' => $error,]);
			}
		} else {
			echo json_encode(['status' => 'error', 'content' => "Cập nhật thất bại"]);
		}
	});

	$app->router('/warehouses-update-2/move/products/amount/{id}', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
		$app->header(['Content-Type' => 'application/json']);
		$action = "move";
		$id = $vars['id'];
		$value = $app->xss(str_replace([','], '', $_POST['value']));
		if ($value < 0) {
			echo json_encode(['status' => 'error', 'content' => "Số lượng không âm"]);
		} else {
			$getAmount = $app->get("products", ["id", "amount"], ["id" => $_SESSION["warehouses"][$action]['products'][$id]['products']]);
			if ($value > $getAmount['amount'] && $action == 'move' || $value > $getAmount['amount'] && $action == 'cancel') {
				$_SESSION["warehouses"][$action]['products'][$id]['amount'] = $getAmount['amount'];
				echo json_encode(['status' => 'error', 'content' => "Số lượng không đủ"]);
			} else {
				$_SESSION["warehouses"][$action]['products'][$id]['amount'] = $app->xss(str_replace([','], '', $_POST['value']));
				echo json_encode(['status' => 'success', 'content' => "Cập nhật thành công"]);
			}
		}
	});

	$app->router('/warehouses-update-2/move/products/notes/{id}', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
		$app->header(['Content-Type' => 'application/json']);
		$action = "move";
		$id = $vars['id'];
		$_SESSION['warehouses'][$action]['products'][$id]["notes"] = $app->xss($_POST['value']);
		echo json_encode(['status' => 'success', 'content' => "Cập nhật thành công"]);
	});

	$app->router('/warehouses-update-2/move/products/deleted/{id}', ['POST'], function ($vars) use ($app, $jatbi, $setting) {
		$app->header(['Content-Type' => 'application/json']);
		$action = "move";
		$id = $vars['id'];
		unset($_SESSION['warehouses'][$action]['products'][$id]);
		echo json_encode(['status' => 'success', 'content' => "Cập nhật thành công"]);
	});

	$app->router('/warehouses-updatee-2/move/cancel', ['GET', 'POST'], function ($vars) use ($app, $jatbi,$setting ,$template) {
		$action = "move";
		if ($app->method() === 'GET') {
			echo $app->render($setting['template'] . '/common/comfirm-modal.html', $vars, $jatbi->ajax());
		} elseif ($app->method() === 'POST') {
			$app->header([
				'Content-Type' => 'application/json',
			]);
			unset($_SESSION['warehouses'][$action]);
			echo json_encode(['status' => 'success', 'content' => "Cập nhật thành công"]);
		}
	});

	$app->router('/warehouses-update-2/move/completed/moves', ['GET', 'POST'], function ($vars) use ($app, $jatbi,$setting, $template) {
		$action = "move";
		$router['4'] = 'moves';
		if ($app->method() === 'GET') {
			$vars['url'] = "/warehouses/warehouses-history/move";
			echo $app->render($setting['template'] . '/common/comfirm-modal.html', $vars, $jatbi->ajax());
		} elseif ($app->method() === 'POST') {
			$app->header([
				'Content-Type' => 'application/json',
			]);
			$datas = $_SESSION['warehouses'][$action];
			$warehouses1 = !empty($datas['move'])
				? $app->get("warehouses", "receive_status", ["id" => $datas['move'], "deleted" => 0])
				: null;
			$kiemtra = 'false';
			$sanpham_kiemtra = [];
			$total_products = 0;
			$kiemtracuahang = 'false';
			$cancel_amount = 0;
			$AmountMinus = '';
			$error = [];
			$pro_logs = [];
			foreach ($datas['products'] as $value_amount) {
				$name_po = $app->get("products", ["amount", "code", "stores", "branch"], ["id" => $value_amount['products']]);
				$total_products += $value_amount['amount'] * $value_amount['price'];
				if ($value_amount['amount'] == '' || $value_amount['amount'] == 0  || $value_amount['amount'] < 0) {
					$error_warehouses = 'true';
				}
				if ($action == 'move') {
					if ($name_po['amount'] <= 0) {
						$kiemtra = 'true';
						$sanpham_kiemtra[] = $name_po['code'];
					}
				}
				if ($action == 'import') {
					if ($name_po['stores'] != $datas['stores']['id'] || $name_po['branch'] != $datas['branch']['id']) {
						$kiemtracuahang = 'true';
					}
				}
			}
			if ($action == 'move' && $kiemtra == 'true') {
				$error = [
					'status' => 'error',
					'content' => 'Những mã hiện hết số lượng, hãy kiểm tra lại: ' . implode(", ", $sanpham_kiemtra)
				];
			}
			if ($action == 'import' && $kiemtracuahang == 'true' && (empty($datas['move'] ?? null) && empty($datas['purchase'] ?? null))) {
				$error = [
					'status' => 'error',
					'content' => 'Cửa hàng hoặc quầy hàng chọn khác với Cửa hàng hoặc quầy hàng của sản phẩm'
				];
			}
			if ($action == 'cancel') {
				foreach ($datas['warehouses'] as $AmounCancel) {
					$cancel_amount += $AmounCancel['amount'];
					if ($AmounCancel['amount'] < 0) {
						$AmountMinus = "true";
					}
				}
			}
			if ($cancel_amount <= 0 && $action == 'cancel') {
				$error = ["status" => 'error', 'content' => "Phải có số lượng huỷ"];
			} elseif ($AmountMinus == "true" && $action == 'cancel') {
				$error = ["status" => 'error', 'content' => "Số lượng không âm"];
			}
			if ($router['4'] == 'moves') {
				if ($datas['stores_receive']['id'] == '' || $datas['branch_receive']['id'] == '') {
					$error = ["status" => 'error', 'content' => "Lỗi trống"];
				}
			}
			if ($datas['stores']['id'] == '' || $datas['branch']['id'] == '' || $datas['type'] == '' || $datas['content'] == '' || count($datas['products'] ?? []) == 0 || (count($datas['warehouses'] ?? []) == 0 && $action == 'cancel')) {
				$error = ["status" => 'error', 'content' => "Lỗi trống"];
			} elseif ($warehouses1 == 2) {
				$error = ['status' => 'error', 'content' => "Phiếu này đã được nhập"];
			}
			if (count($error) == 0) {
				$insert = [
					"code"			=> $datas['type'] == 'import' ? 'PN' : 'PX',
					"type"			=> $datas['type'],
					"data"			=> $datas['data'],
					"stores"		=> $datas['type'] == 'import' ? $datas['stores']['id'] : $_SESSION['warehouses']['move']['stores']['id'],
					"branch"		=> $datas['branch']['id'],
					"stores_receive" => $datas['stores_receive']['id'],
					"branch_receive" => $datas['branch_receive']['id'],
					"content"		=> $datas['content'],
					"vendor"		=> $datas['vendor']['id'] ?? null,
					"user"			=> $app->getSession("accounts")['id'] ?? 0,
					"date"			=> $datas['date'],
					"active"		=> $jatbi->active(30),
					"date_poster"	=> date("Y-m-d H:i:s"),
					"purchase"		=> $datas['purchase'] ?? null,
					"move"			=> $datas['move'] ?? null,
					"receive_status" => 1,
				];
				$app->insert("warehouses", $insert);
				$orderId = $app->id();
				if (isset($datas['purchase'])) {
					$app->update("purchase", ["status" => 5], ["id" => $datas['purchase']]);
					$app->insert("purchase_logs", [
						"purchase" 	=> $datas['purchase'],
						"user"		=> $app->getSession("accounts")['id'] ?? 0,
						"content" 	=> $app->xss('Nhập hàng'),
						"status" 	=> 5,
						"date"		=> date('Y-m-d H:i:s'),
					]);
				}
				if (isset($datas['move'])) {
					foreach ($datas['products'] as $key => $value_move) {
						if ($value_move['amount'] < $value_move['amount_old']) {
							$move_error[] = $value_move['amount_old'] - $value_move['amount'];
						}
						$app->update("warehouses_details", ["amount_move" => $value_move['amount']], ["id" => $value_move['details']]);
					}
					if (count($move_error) > 0) {
						$receive_status = 3;
					} else {
						$receive_status = 2;
					}
					$app->update("warehouses", [
						"receive_status" => $receive_status,
						"receive_date" => date("Y-m-d H:i:s"),
					], ["id" => $datas['move']]);
				}
				foreach ($datas['products'] as $key1 => $value) {
					$getProducts = $app->get("products", ["id", "code_products", "code", "name", "categorys", "group", "vendor", "price", "cost", "units", "tkco", "tkno", "default_code", "amount"], ["id" => $value['products'], "deleted" => 0]);
					if ($insert['type'] == "move") {
						$storeget = $app->get("stores", "*", ["id" => $insert['stores_receive']]);
						$branchget = $app->get("branch", "*", ["id" => $insert['branch_receive']]);
						if ($getProducts['code_products'] == NULL) {
							$code = $getProducts['code'];
						} else {
							if ($insert['stores_receive'] == $insert['stores']) {
								$code = $branchget['code'] . $getProducts['code_products'];
							} else {
								$code = $storeget['code'] . $branchget['code'] . $getProducts['code_products'];
							}
						}
						$checkcode = $app->get("products", ["id", "code"], ["code" => $code, "deleted" => 0, 'status' => 'A', "stores" => $insert['stores_receive'], "branch" => $insert['branch_receive']]);
						if ($checkcode && $code == $checkcode['code'] && $getProducts['code_products'] != NULL) {
							$tam = [
								"products" => $checkcode['id'],
								"value" => $value['products'],
								"warehouses" => $orderId,
								"date" 			=> date('Y-m-d H:i:s'),
							];
							$app->insert("tam", $tam);
						} else {
							$insert_products = [
								"type" 			=> $app->get("products", "type", ["id" => $value['products']]),
								"main" 			=> 0,
								"code" 			=> $code,
								"name" 			=> $getProducts['name'],
								"categorys" 	=> $getProducts['categorys'],
								"vat" 			=> $setting['site_vat'],
								"vat_type" 		=> 1,
								"amount"		=> 0,
								"content"		=> $value['content'] ?? "",
								"vendor" 		=> $getProducts['vendor'],
								"group" 		=> $getProducts['group'],
								"price" 		=> $app->xss(str_replace([','], '', $getProducts['price'] ?? '0')),
								"cost" 			=> $app->xss(str_replace([','], '', $getProducts['cost'] ?? '0')),
								"units" 		=> $getProducts['units'],
								"notes" 		=> $app->xss($crafting['notes'] ?? ""),
								"active"		=> $jatbi->active(32),
								// "images"		=> $handle->file_dst_name==''?'no-images.jpg':$handle->file_dst_name,
								"images"		=> 'no-images.jpg',
								"date" 			=> date('Y-m-d H:i:s'),
								"status" 		=> 'A',
								"user" 			=> $app->getSession("accounts")['id'] ?? 0,
								"new"			=> 1,
								"crafting"		=> $value['crafting'],
								"stores"		=> $insert['stores_receive'],
								"branch"		=> $insert['branch_receive'],
								"code_products"	=> $getProducts['code_products'] == NULL ? NULL : $getProducts['code_products'],
								"tkno"			=> $getProducts['tkco'],
								"tkco"			=> $getProducts['tkno'],
								"default_code" 	=> $getProducts['default_code'],
							];
							$app->insert("products", $insert_products);
							$Gid = $app->id();
							$getDetails = $app->select("products_details", ["id", "ingredient", "code", "type", "group", "categorys", "pearl", "sizes", "colors", "units", "price", "cost", "amount", "total"], ["products" => $value['products'], "deleted" => 0]);
							foreach ($getDetails as $detail) {
								$products_details = [
									"products"		=> $Gid,
									"ingredient" 	=> $detail['ingredient'],
									"code"			=> $detail['code'],
									"type"			=> $detail['type'],
									"group"			=> $detail['group'],
									"categorys"		=> $detail['categorys'],
									"pearl"			=> $detail['pearl'],
									"sizes"			=> $detail['sizes'],
									"colors"		=> $detail['colors'],
									"units"			=> $detail['units'],
									"price"			=> $detail['price'],
									"cost"			=> $detail['cost'],
									"amount"		=> $detail['amount'],
									"total"			=> $detail['total'],
									"user"			=> $app->getSession("accounts")['id'] ?? 0,
									"date" 			=> date("Y-m-d H:i:s"),
								];
								$app->insert("products_details", $products_details);
							}
							$tam = [
								"products"	=> $Gid,
								"value" 	=> $value['products'],
								"warehouses" => $orderId,
								"date" 		=> date('Y-m-d H:i:s'),
							];
							$app->insert("tam", $tam);
						}
					}
					if ($action == 'import') {
						if (!empty($datas['move'])) {
							$checktam = $app->get("tam", "products", ["value" => $value['products'], "warehouses" => $app->get("warehouses", "move", ["id" => $orderId])]);
							$pro = [
								"warehouses" => $orderId,
								"data" => $insert['data'],
								"type" => $insert['type'],
								"products" => $value['status'] == 0 ? $checktam : $value['products'],
								"amount_buy" => $value['amount_buy'],
								"amount" => $value['amount'],
								"amount_total" => $value['amount'],
								"price" => $getProducts['price'],
								"notes" => $value['notes'],
								"date" => date("Y-m-d H:i:s"),
								"user" => $app->getSession("accounts")['id'] ?? 0,
								"stores"	=> $value['status'] == 0 ? $insert['stores'] : $value['stores'],
								"branch"	=> $value['status'] == 0 ? $insert['branch'] : $value['branch'],
							];
							$app->insert("warehouses_details", $pro);
							$GetID = $app->id();
							$warehouses_logs = [
								"type" => $insert['type'],
								"data" => $insert['data'],
								"warehouses" => $orderId,
								"details" => $GetID,
								"products" => $pro['products'],
								"price" => $pro['price'],
								"amount" => str_replace([','], '', $value['amount']),
								"total" => $value['amount'] * $value['price'],
								"notes" => $value['notes'],
								"date" 	=> date('Y-m-d H:i:s'),
								"user" 	=> $app->getSession("accounts")['id'] ?? 0,
								"stores"	=> $value['status'] == 0 ? $insert['stores'] : $value['stores'],
							];
							$app->insert("warehouses_logs", $warehouses_logs);
							$sanpham = $app->get("products", ["id", "amount"], ["id" => $pro['products']]);
							$app->update("products", ["amount" => $sanpham['amount'] + $value['amount']], ["id" => $sanpham['id']]);
							$pro_logs[] = $pro;
						} else {
							$app->update("products", ["amount" => $getProducts['amount'] + str_replace([','], '', $value['amount'])], ["id" => $getProducts['id']]);
							$pro = [
								"warehouses" => $orderId,
								"data" => $insert['data'],
								"type" => $insert['type'],
								"vendor" => $value['vendor'],
								"products" => $value['products'],
								"amount_buy" => $value['amount_buy'],
								"amount" => str_replace([','], '', $value['amount']),
								"amount_total" => str_replace([','], '', $value['amount']),
								"price" => $value['price'],
								"notes" => $value['notes'],
								"date" => date("Y-m-d H:i:s"),
								"user" => $app->getSession("accounts")['id'] ?? 0,
								"stores"	=> $value['status'] == 0 ? $insert['stores'] : $value['stores'],
								"branch"	=> $value['status'] == 0 ? $insert['branch'] : $value['branch'],
							];
							$app->insert("warehouses_details", $pro);
							$GetID = $app->id();
							$warehouses_logs = [
								"type" => $insert['type'],
								"data" => $insert['data'],
								"warehouses" => $orderId,
								"details" => $GetID,
								"products" => $value['products'],
								"price" => $value['price'],
								"amount" => str_replace([','], '', $value['amount']),
								"total" => $value['amount'] * $value['price'],
								"notes" => $value['notes'],
								"date" 	=> date('Y-m-d H:i:s'),
								"user" 	=> $app->getSession("accounts")['id'] ?? 0,
								"stores"	=> $value['status'] == 0 ? $insert['stores'] : $value['stores'],
							];
							$app->insert("warehouses_logs", $warehouses_logs);
							$pro_logs[] = $pro;
						}
					}
					if ($action == 'move') {
						if ($value['amount'] > 0) {
							$app->update("products", ["amount" => $getProducts['amount'] - str_replace([','], '', $value['amount'])], ["id" => $getProducts['id']]);
							$getAmounts = $app->select("warehouses_details", ["id", "amount_total", "amount_move", "price"], ["products" => $value['products'], "stores" => $insert['stores'], "type" => "import", "deleted" => 0, "amount_total[>]" => 0]);
							$numWarehouse = 0;
							$buyAmount = $value['amount'];
							foreach ($getAmounts as $keyAmount => $getAmount) {
								if ($keyAmount <= $numWarehouse) {
									$conlai = $getAmount['amount_total'] - $buyAmount;
									if ($buyAmount > $getAmount['amount_total']) {
										$getbuy = $getAmount['amount_total'];
									} else {
										$getbuy = $buyAmount;
									}
									$getwarehousess[$value['products']][] = [
										"id" => $getAmount['id'],
										"amount_move" => $getAmount['amount_move'],
										"amount_total" => $getAmount['amount_total'],
										"price" => $getAmount['price'],
										"duration" => $getAmount['duration'] ?? null,
										"amount" => $getbuy,
									];
									if ($conlai < 0) {
										$numWarehouse = $numWarehouse + 1;
										$buyAmount = -$conlai;
									}
								}
							}
							foreach ($getwarehousess[$value['products']] as $getwarehouses) {
								$update_ware = [
									"amount_move" => $getwarehouses['amount_move'] + $getwarehouses['amount'],
									"amount_total" => $getwarehouses['amount_total'] - $getwarehouses['amount'],
								];
								$app->update("warehouses_details", $update_ware, ["id" => $getwarehouses['id']]);
							}
							$pro = [
								"warehouses" => $orderId,
								"data" => $insert['data'],
								"type" => $insert['type'],
								"vendor" => $value['vendor'] ?? null,
								"products" => $value['products'],
								"amount_buy" => $value['amount_buy'],
								"amount" => $value['amount'],
								"amount_total" => $value['amount'],
								"price" => $getProducts['price'],
								"notes" => $value['notes'],
								"date" => date("Y-m-d H:i:s"),
								"user" => $app->getSession("accounts")['id'] ?? 0,
								"stores" => $insert['stores'],
								"branch" => $insert['branch'],
							];
							$app->insert("warehouses_details", $pro);
							$get_details = $app->id();
							$warehouses_logs = [
								"data" => $insert['data'],
								"type" => $insert['type'],
								"warehouses" => $orderId,
								"details" => $get_details,
								"products" => $value['products'],
								"price" => $getProducts['price'],
								"amount" => $value['amount'],
								"total" => $value['amount'] * $getProducts['price'],
								"notes" => $insert['notes'] ?? "",
								"date" 	=> date('Y-m-d H:i:s'),
								"user" 	=> $app->getSession("accounts")['id'] ?? 0,
								"stores"	=> $insert['stores'],
								"stores_receive" => $insert['stores_receive'],
								"branch"	=> $insert['branch'],
								"branch_receive"	=> $insert['branch_receive'],
							];
							$app->insert("warehouses_logs", $warehouses_logs);
						}
					}
				}
				if ($insert['data'] != '') {
					$app->insert("purchase_logs", [
						"purchase" 	=> $insert['data'],
						"user"		=> $app->getSession("accounts")['id'] ?? 0,
						"content" 	=> $app->xss('Nhập hàng'),
						"status" 	=> $app->xss(5),
						"date"		=> date('Y-m-d H:i:s'),
					]);
					$app->update("purchase", ["status" => 5], ["id" => $insert['data']]);
				}
				if ($action == 'cancel') {
					$amount = 0;
					foreach ($datas['warehouses'] as $key => $value) {
						if ($value['amount'] > 0) {
							$getwarehouses = $app->get("warehouses_details", ["id", "amount_cancel", "amount_total", "vendor", "products", "price"], ["id" => $value['id'], "deleted" => 0, "amount_total[>]" => 0]);
							$update = [
								"amount_cancel" => $getwarehouses['amount_cancel'] + $value['amount'],
								"amount_total" => $getwarehouses['amount_total'] - $value['amount'],
							];
							$app->update("warehouses_details", $update, ["id" => $getwarehouses['id']]);
							$total_amount = $value['amount_total'] - $value['amount'];
							$pro = [
								"warehouses" => $orderId,
								"data" => $insert['data'],
								"type" => $insert['type'],
								"vendor" => $getwarehouses['vendor'],
								"products" => $getwarehouses['products'],
								"amount_buy" => $value['amount_buy'],
								"amount" => $value['amount'],
								"amount_total" => $value['amount'],
								"price" => $getwarehouses['price'],
								"notes" => $value['notes'],
								"date" => date("Y-m-d H:i:s"),
								"user" => $app->getSession("accounts")['id'] ?? 0,
								"stores" => $insert['stores'],
								"branch"	=> $insert['branch'],
							];
							$app->insert("warehouses_details", $pro);
							$warehouses_logs = [
								"type"		=> $insert['type'],
								"data"		=> $insert['data'],
								"warehouses" => $orderId,
								"details"	=> $getwarehouses['id'],
								"products"	=> $getwarehouses['products'],
								"amount"	=> $app->xss($value['amount']),
								"price"		=> $value['price'],
								"total"		=> $value['amount'] * $value['price'],
								"date" 		=> date('Y-m-d H:i:s'),
								"user" 		=> $app->getSession("accounts")['id'] ?? 0,
								"stores"	=> $insert['stores'],
								"branch"	=> $insert['branch'],
							];
							$app->insert("warehouses_logs", $warehouses_logs);
							$pro_logs[] = $pro;
							$amount += $value['amount'];
						}
					}
					$getProducts = $app->get("products", ["id", "amount"], ["id" => $datas['products']]);
					$app->update("products", ["amount" => $getProducts['amount'] - $amount], ["id" => $getProducts['id']]);
				}
				$jatbi->logs('warehouses', $action, [$insert, $pro_logs, $_SESSION['warehouses'][$action]['products'], $_SESSION['warehouses'][$action]]);
				if ($insert['type'] == 'import') {
					$jatbi->notification($app->getSession("accounts")['id'] ?? 0, '', 'products', 'Nhập hàng', 'Tài khoản ' . ($app->getSession("accounts")['name'] ?? "") . ' Đã nhập hàng phiếu #PN' . $orderId, '/warehouses/warehouses-history-views/' . $orderId . '/', 'modal-url');
				} elseif ($insert['type'] == 'move') {
					$jatbi->notification($app->getSession("accounts")['id'] ?? 0, '', 'products', 'Xuất hàng', 'Tài khoản ' . ($app->getSession("accounts")['name'] ?? "") . ' Đã xuất hàng phiếu #PX' . $orderId, '/warehouses/warehouses-history-views/' . $orderId . '/', 'modal-url');
				}
				unset($_SESSION['warehouses'][$action]);
				echo json_encode(['status' => 'success', 'content' => "Cập nhật thành công"]);
			} else {
				echo json_encode(['status' => 'error', 'content' => $error['content']]);
			}
		}
	});

	//api search
	$app->router("/products-move", ['POST'], function ($vars) use ($app) {
		$app->header([
			'Content-Type' => 'application/json',
		]);

		$searchValue = isset($_POST['search']) ? $app->xss($_POST['search']) : '';
		$SearchStore = $_SESSION['warehouses']['move']['stores'] ?? "";
		$Searchbranch = $_SESSION['warehouses']['move']['branch'] ?? "";

		$where = [
			"AND" => [
				"OR" => [
					"name[~]" => $searchValue,
					"code[~]" => $searchValue,
				],
				"amount[>]" => 0,
				"status" => 'A',
				"deleted" => 0,
				"stores" => $SearchStore,
				"branch" => $Searchbranch,
			],
			"LIMIT" => 15
		];

		$datas = [];
		$app->select("products", [
			"id",
			"code",
			"name",
			"stores",
			"branch",
			"price"
		], $where, function ($data) use (&$datas, $vars) {
			$datas[] = [
				"text" => $data['code'] . ' - ' . $data['name'] . ' - ' . $data['price'],
				"url" => "/warehouses/warehouses-update/move/products/add/" . $data['id'],
				"value" => $data['id'],
			];
		});

		echo json_encode($datas);
	});

	$app->router("/warehouses-import-move", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting,$template, $accStore, $stores) {
		$vars['title'] = $jatbi->lang("Nhập hàng từ chuyển hàng");

		if ($app->method() === 'GET') {
			$stores_full = $app->select("stores", ["id (value)", "name(text)", "code"], ["deleted" => 0, "status" => 'A']);
			array_unshift($stores_full, ["value" => "", "code" => "", "text" => ""]);
			$vars['stores_full'] = $stores_full;

			$branchs = $app->select("branch", ["id(value)", "name(text)", "code"], ["deleted" => 0, "status" => 'A']);
			array_unshift($branchs, ["value" => "", "code" => "", "text" => ""]);
			$vars['branchs'] = $branchs;

			// if(count($stores) > 1){
			//     array_unshift($stores, [
			//         'value' => '',
			//         'text' => $jatbi->lang('Tất cả')
			//     ]);
			// }
			// $vars['stores'] = $stores;

			$stores = $app->select("stores", ["id(value)", "name(text)", "code"], ["deleted" => 0, "status" => 'A', "id" => $accStore]);
			array_unshift($stores, ["value" => "", "code" => "", "text" => ""]);
			$vars['stores'] = $stores;

			$vars['accStore'] = $accStore;

			$vars['branch_receive_options'] = $_SESSION['branch_receive_options'] ?? [];

			echo $app->render($template . '/warehouses/warehouses-import-move.html', $vars);
		} elseif ($app->method() === 'POST') {
			$app->header(['Content-Type' => 'application/json']);

			// Get DataTables parameters
			$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
			$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
			$length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
			$searchValue = isset($_POST['search']['value']) ? $app->xss($_POST['search']['value']) : '';
			$orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
			$orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

			// Get filter parameters
			$stores = isset($_POST['stores']) ? $app->xss($_POST['stores']) : '';
			$stores_receive = isset($_POST['stores_receive']) ? $app->xss($_POST['stores_receive']) : $accStore;
			$branch = isset($_POST['branch']) ? $app->xss($_POST['branch']) : '';
			$branch_receive = isset($_POST['branch_receive']) ? $app->xss($_POST['branch_receive']) : '';
			$date_string = isset($_POST['date']) ? $app->xss($_POST['date']) : '';

			// Process date range
			if ($date_string) {
				$date = explode('-', $date_string);
				$date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date[0]))));
				$date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date[1]))));
			} else {
				$date_from = date('Y-m-01 00:00:00');
				$date_to = date('Y-m-d 23:59:59');
			}

			// Build where clause
			$where = [
				"AND" => [
					"OR" => [
						"warehouses.code[~]" => $searchValue ?: '%',
						"warehouses.id[~]" => $searchValue ?: '%',
					],
					"warehouses.type" => "move",
					"warehouses.deleted" => 0,
					"warehouses.receive_status"	=> 1,
				],
				"LIMIT" => [$start, $length],
				"ORDER" => ["warehouses.id" => strtoupper($orderDir)],
			];

			if ($stores != '') {
				$where['AND']['warehouses.stores'] = $stores;
			}
			if ($branch != '') {
				$where['AND']['warehouses.branch'] = $branch;
			}
			if ($branch_receive != '') {
				$where['AND']['warehouses.branch_receive'] = $branch_receive;
			}
			if ($stores_receive != [0]) {
				$where['AND']['warehouses.stores_receive'] = $stores_receive;
			}
			$where['AND']['warehouses.date[<>]'] = [$date_from, $date_to];

			// Count total records
			$count = $app->count("warehouses", ["AND" => $where['AND']]);

			$app->select("warehouses", "*", $where, function ($data) use (&$datas, $app, $jatbi, $setting) {
				$buttons = [];

				// Nếu receive_status == 1 => Nhập hàng
				if ($data['receive_status'] == 1) {
					$buttons[] = [
						'type' => 'link',
						'name' => $jatbi->lang("Nhập hàng"),
						'action' => [
							'href' => '/warehouses/warehouses-import/move/' . $data['id'],
							'data-pjax' => ''
						]
					];
				}

				// Nếu receive_status == 3 => Trả hàng
				if ($data['receive_status'] == 3) {
					$buttons[] = [
						'type' => 'link',
						'name' => $jatbi->lang("Trả hàng"),
						'action' => [
							'href' => '/warehouses/warehouses-return/' . $data['id'],
							'data-pjax' => ''
						]
					];
				}

				// Nếu receive_status != 3 => Trả về kho
				if ($data['receive_status'] != 3) {
					$buttons[] = [
						'type' => 'button',
						'name' => $jatbi->lang("Trả về kho"),
						'action' => [
							'data-url' => '/warehouses/products-move-warehouse/' . $data['id'],
							'data-action' => 'modal'
						]
					];
				}

				// Luôn luôn có nút Xem
				$buttons[] = [
					'type' => 'button',
					'name' => $jatbi->lang("Xem"),
					'action' => [
						'data-url' => '/warehouses/warehouses-move-views/' . $data['id'],
						'data-action' => 'modal'
					]
				];
				$datas[] = [
					"id" => '<a href="!#" class="pjax-load" data-action="modal" data-url="/warehouses/warehouses-move-views/' . $data['id'] . '">#' . ($data['code'] ?? '') . $data['id'] . '</a>',
					"content" => $data['content'] ?? '',
					"stores" =>
					$app->get("stores", "name", ["id" => $data['stores']]) . " - " .
						$app->get("branch", "name", ["id" => $data['branch']]) . " -> " .
						$app->get("stores", "name", ["id" => $data['stores_receive']]) . " - " .
						$app->get("branch", "name", ["id" => $data['branch_receive']]),
					"date_poster" => date('d/m/Y H:i:s', strtotime($data['date_poster'])),
					"user" => $app->get("accounts", "name", ["id" => $data['user']]),
					"action" => $app->component("action", [
						"button" => $buttons
					]),
				];
			});

			// Return JSON data
			echo json_encode([
				"draw" => $draw,
				"recordsTotal" => $count,
				"recordsFiltered" => $count,
				"data" => $datas ?? [],
			]);
		}
	})->setPermissions(['warehouses-import']);

	$app->router("/warehouses-move-views/{id}", ['GET'], function ($vars) use ($app, $jatbi, $template) {
		$vars['title'] = $jatbi->lang("Xem chi tiết thu chi");

		$data = $app->get("warehouses", ["id", "code", "date", "date_poster", "user", "content"], ["id" => $app->xss($vars['id'])]);
		$vars['data'] = $data;

		if ($vars['data']) {
			$details = $app->select("warehouses_details", ["id", "products", "amount", "price", "notes"], ["warehouses" => $data['id'], "deleted" => 0]);
			$vars['details'] = $details;
			echo $app->render($template . '/warehouses/warehouses-move-views.html', $vars, $jatbi->ajax());
		} else {
			echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
		}
	})->setPermissions(['warehouses-import-history']);

	$app->router("/warehouses-import/move/{id}", ['GET'], function ($vars) use ($app, $jatbi, $template, $accStore, $stores) {
		$vars['title'] = $jatbi->lang("Nhập hàng");
		$action = "import";
		$router['2'] = "move";
		$router['3'] = $app->xss($vars['id']);
		$vars['action'] = $action;
		$getMoveS = $app->get("warehouses", "id", ["id" => $app->xss($router['3']), "deleted" => 0, "receive_status" => 2]);
		$vars['getMoveS'] = $getMoveS;
		if ($_SESSION['warehouses'][$action]['import'] == '') {
			if ($router['2'] == "purchase") {
				$_SESSION['warehouses'][$action]['import'] = "purchase";
			} elseif ($router['2'] == "move") {
				$_SESSION['warehouses'][$action]['import'] = "move";
			} elseif (empty($router['2'])) {
				$_SESSION['warehouses'][$action]['import'] = "";
			}
		} else {
			if ($_SESSION['warehouses'][$action]['import'] != $router['2']) {
				unset($_SESSION['warehouses'][$action]);
			}
		}
		if ($_SESSION['warehouses'][$action]['move'] != $router['3']) {
			unset($_SESSION['warehouses'][$action]);
		}
		if (!isset($_SESSION['warehouses'][$action]['move'])) {
			// $_SESSION['warehouses'][$action]['import'] = "move";
			$getMove = $app->get("warehouses", ['id', 'stores', 'branch', 'stores_receive', 'branch_receive'], ["id" => $app->xss($router['3']), "deleted" => 0, "receive_status" => [1, 3], "stores_receive" => $accStore]);

			$getPros = $app->select("warehouses_details", ['id', 'products', 'amount', 'price', 'vendor'], ["warehouses" => $getMove['id'], "deleted" => 0]);
			foreach ($getPros as $key => $value) {
				$getPro = $app->get("products", ['id', 'name', 'code', 'images', 'categorys', 'default_code', 'units', 'notes'], ["id" => $value['products']]);
				$_SESSION['warehouses'][$action]['products'][] = [
					"products" => $value['products'],
					"amount_buy" => 0,
					"amount" => str_replace([','], '', $value['amount']),
					"amount_old" => str_replace([','], '', $value['amount']),
					"details" => $value['id'],
					"price" => $value['price'],
					"code" => $getPro['code'],
					"name" => $getPro['name'],
					"categorys" => $getPro['categorys'],
					"default_code" => $getPro['default_code'],
					"units" => $getPro['units'],
					"notes" => $getPro['notes'],
					"status" => 0,
					"stores" => $getMove['stores'],
					"branch" => $getMove['branch'],
				];
			}
			$_SESSION['warehouses'][$action]['move'] = $getMove['id'];
			$_SESSION['warehouses'][$action]['stores_receive_re'] = $getMove['stores_receive'];
			$_SESSION['warehouses'][$action]['content'] = "nhập hàng từ mã chuyển hàng #" . $getMove['code'] . $getMove['id'];
			$_SESSION['warehouses'][$action]['vendor'] = $app->get("vendors", "*", ["id" => $getMove['vendor']]);
			$_SESSION['warehouses'][$action]['stores'] = $app->get("stores", "*", ["id" => $getMove['stores_receive']]);
			$_SESSION['warehouses'][$action]['branch'] = $app->get("branch", "*", ["id" => $getMove['branch_receive']]);
		}
		if (!isset($_SESSION['warehouses'][$action]['type'])) {
			$_SESSION['warehouses'][$action]['type'] = $action;
		}
		if (!isset($_SESSION['warehouses'][$action]['data'])) {
			$_SESSION['warehouses'][$action]['data'] = 'products';
		}
		if (count($stores) == 1) {
			$_SESSION['warehouses'][$action]['stores'] = ["id" => $app->get("stores", "id", ["id" => $accStore, "status" => 'A', "deleted" => 0, "ORDER" => ["id" => "ASC"]])];
		}
		if (!isset($_SESSION['warehouses'][$action]['date'])) {
			$_SESSION['warehouses'][$action]['date'] = date("Y-m-d");
		}
		$data = [
			"type" => $_SESSION['warehouses'][$action]['type'],
			"data" => $_SESSION['warehouses'][$action]['data'],
			"stores" => $_SESSION['warehouses'][$action]['stores'],
			"branch" => $_SESSION['warehouses'][$action]['branch'],
			"date" => $_SESSION['warehouses'][$action]['date'],
			"content" => $_SESSION['warehouses'][$action]['content'],
			"import" => $_SESSION['warehouses'][$action]['import'],
			"products" => $_SESSION['warehouses'][$action]['products'],
		];
		$SelectProducts = $_SESSION['warehouses'][$action]['products'];
		$products = $app->select("products", ['id', 'name', 'code'], ["deleted" => 0, "status" => 'A', "stores" => $data["stores"]['id'], "branch" => $data["branch"]['id']]);
		$branchsssss = $app->select("branch", ['id', 'name', "code"], ["deleted" => 0, "stores" => $data['stores']['id'], "status" => 'A']);

		$vars['data'] = $data;
		$vars['products'] = $products;
		$vars['branchsssss'] = $branchsssss;
		$vars['SelectProducts'] = $SelectProducts;


		echo $app->render($template . '/warehouses/warehouses-importmove.html', $vars);
	})->setPermissions(['warehouses-import']);

	$app->router("/products-move-warehouse/{id}", ['GET', 'POST'], function ($vars) use ($app, $jatbi,$setting ,$template, $accStore, $stores) {
		if ($app->method() === 'GET') {
			$vars['modalContent'] = $jatbi->lang("Bạn có thật sự muốn trả sản phẩm về kho cũ");

			echo $app->render($setting['template'] . '/common/comfirm-modal.html', $vars, $jatbi->ajax());
		} elseif ($app->method() === 'POST') {
			$app->header(['Content-Type' => 'application/json']);
			$router['2'] = $app->xss($vars['id']);
			$data = $app->get("warehouses", ["id", "stores", "branch"], ["id" => $router["2"]]);
			$stores = $app->get("stores", "mame", ["id" => $data['stores']]);
			$branch = $app->get("branch", "mame", ["id" => $data['stores']]);
			$jatbi->logs('products', 'trả lại kho', $data);
			$app->update("warehouses", ["receive_status" => 5,], ["id" => $data['id']]);
			$insert = [
				"code"			=> "PN",
				"type"			=> "import",
				"data"			=> "products",
				"stores"		=> $data['stores'],
				"branch"		=> $data['branch'],
				"content"		=> "tra ve kho",
				"user"			=> $app->getSession("accounts")['id'] ?? 0,
				"date"			=> date("Y-m-d H:i:s"),
				"active"		=> $jatbi->active(30),
				"date_poster"	=> date("Y-m-d H:i:s"),
			];
			$app->insert("warehouses", $insert);
			$GetWareId = $app->id();
			$details = $app->select("warehouses_details", ["id", "products", "price", "amount"], ["warehouses" => $data['id']]);
			foreach ($details as $detail) {
				$products_move = $app->get("products", ["id", "amount"], ["id" => $detail['products']]);
				$insert_detail = [
					"warehouses" => $GetWareId,
					"type"		=> "import",
					"data"		=> "products",
					"products"	=> $detail['products'],
					"price"		=> $detail['price'],
					"amount"	=> $detail['amount'],
					"amount_total" => $detail['amount'],
					"notes"		=> "Chuyen lai kho",
					"user"		=> $app->getSession("accounts")['id'] ?? 0,
					"stores"	=> $insert['stores'],
					"branch"	=> $insert['branch'],
					"date"		=> date("Y-m-d H:i:s"),

				];
				$app->insert("warehouses_details", $insert_detail);
				$GetWareDetalId = $app->id();
				$insert_log = [
					"warehouses" => $insert_detail['warehouses'],
					"details"	=> $GetWareDetalId,
					"products"	=> $insert_detail['products'],
					"price"		=> $insert_detail['price'],
					"amount"	=> $insert_detail['amount'],
					"total"		=> $insert_detail['price'] * $insert_detail['amount'],
					"type"		=> "import",
					"data"		=> "products",
					"notes"		=> "hang tra lai",
					"date"		=> date("Y-m-d H:i:s"),
					"user"		=> $app->getSession("accounts")['id'] ?? 0,
					"stores"	=> $insert_detail['stores'],
				];
				$app->insert("warehouses_logs", $insert_log);
				$app->update("products", ["amount" => $products_move['amount'] + $insert_log['amount']], ["id" => $products_move['id']]);
			}
			echo json_encode(['status' => 'success', 'content' => "Cập nhật thành công"]);
		}
	})->setPermissions(['warehouses-import']);

	$app->router("/warehouses-import-return", ['GET', 'POST'], function ($vars) use ($app, $jatbi, $setting,$template ,$accStore, $stores) {
		$vars['title'] = $jatbi->lang("Nhập hàng từ trả hàng");

		if ($app->method() === 'GET') {
			$stores_full = $app->select("stores", ["id (value)", "name(text)", "code"], ["deleted" => 0, "status" => 'A']);
			array_unshift($stores_full, ["value" => "", "code" => "", "text" => ""]);
			$vars['stores_full'] = $stores_full;

			$branchs = $app->select("branch", ["id", "name", "code"], ["deleted" => 0, "status" => 'A']);
			array_unshift($branchs, ["value" => "", "code" => "", "text" => ""]);
			$vars['branchs'] = $branchs;

			if (count($stores) > 1) {
				array_unshift($stores, [
					'value' => '',
					'text' => $jatbi->lang('Tất cả')
				]);
			}
			$vars['stores'] = $stores;



			echo $app->render($template . '/warehouses/warehouses-import-return.html', $vars);
		} elseif ($app->method() === 'POST') {
			$app->header(['Content-Type' => 'application/json']);

			// Get DataTables parameters
			$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
			$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
			$length = isset($_POST['length']) ? intval($_POST['length']) : ($setting['site_page'] ?? 10);
			$searchValue = isset($_POST['search']['value']) ? $app->xss($_POST['search']['value']) : '';
			$orderName = isset($_POST['order'][0]['name']) ? $_POST['order'][0]['name'] : 'id';
			$orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'DESC';

			// Get filter parameters
			$stores = isset($_POST['stores']) ? $app->xss($_POST['stores']) : $accStore;
			$stores_receive = isset($_POST['stores_receive']) ? $app->xss($_POST['stores_receive']) : '';
			$branch = isset($_POST['branch']) ? $app->xss($_POST['branch']) : '';
			$branch_receive = isset($_POST['branch_receive']) ? $app->xss($_POST['branch_receive']) : '';
			$date_string = isset($_POST['date']) ? $app->xss($_POST['date']) : '';

			// Process date range
			if ($date_string) {
				$date = explode('-', $date_string);
				$date_from = date('Y-m-d 00:00:00', strtotime(str_replace('/', '-', trim($date[0]))));
				$date_to = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', trim($date[1]))));
			} else {
				$date_from = date('Y-m-01 00:00:00');
				$date_to = date('Y-m-d 23:59:59');
			}

			// Build where clause
			$where = [
				"AND" => [
					"OR" => [
						"warehouses.code[~]" => $searchValue ?: '%',
						"warehouses.id[~]" => $searchValue ?: '%',
					],
					"warehouses.type" => "move",
					"warehouses.deleted" => 0,
					"warehouses.receive_status"	=> 3,
				],
				"LIMIT" => [$start, $length],
				"ORDER" => ["warehouses.id" => strtoupper($orderDir)],
			];

			if ($stores != '') {
				$where['AND']['warehouses.stores'] = $stores;
			}
			if ($branch != '') {
				$where['AND']['warehouses.branch'] = $branch;
			}
			if ($branch_receive != '') {
				$where['AND']['warehouses.branch_receive'] = $branch_receive;
			}
			if ($stores_receive != '') {
				$where['AND']['warehouses.stores_receive'] = $stores_receive;
			}
			$where['AND']['warehouses.receive_date[<>]'] = [$date_from, $date_to];
			// var_dump($where);
			// Count total records
			$count = $app->count("warehouses", ["AND" => $where['AND']]);

			$app->select("warehouses", [
				"id",
				"code",
				"stores",
				"branch",
				"stores_receive",
				"branch_receive",
				"receive_date",
				"user",
				"receive_status",
				"content"
			], $where, function ($data) use (&$datas, $app, $jatbi, $setting) {
				$buttons = [];

				$buttons[] = [
					'type' => 'link',
					'name' => $jatbi->lang("Nhập hàng"),
					'action' => [
						'href' => '/warehouses/warehouses-return/' . $data['id'],
						'data-pjax' => ''
					]
				];

				$buttons[] = [
					'type' => 'button',
					'name' => $jatbi->lang("Xem"),
					'action' => [
						'data-url' => '/warehouses/warehouses-return-views/' . $data['id'],
						'data-action' => 'modal'
					]
				];
				$datas[] = [
					"id" => '<a href="!#" class="pjax-load" data-action="modal" data-url="/warehouses/warehouses-return-views/' . $data['id'] . '">#' . ($data['code'] ?? '') . $data['id'] . '</a>',
					"content" => "Trả hàng " . date("d/m/Y", strtotime($data['receive_date'])),
					"stores" =>
					$app->get("stores", "name", ["id" => $data['stores_receive']]) . " - " .
						$app->get("branch", "name", ["id" => $data['branch_receive']]) . " -> " .
						$app->get("stores", "name", ["id" => $data['stores']]) . " - " .
						$app->get("branch", "name", ["id" => $data['branch']]),
					"date_poster" => date('d/m/Y H:i:s', strtotime($data['receive_date'])),
					"user" => $app->get("accounts", "name", ["id" => $data['user']]),
					"action" => $app->component("action", [
						"button" => $buttons
					]),
				];
			});

			// Return JSON data
			echo json_encode([
				"draw" => $draw,
				"recordsTotal" => $count,
				"recordsFiltered" => $count,
				"data" => $datas ?? [],
			]);
		}
	})->setPermissions(['warehouses-import']);

	$app->router("/warehouses-return-views/{id}", ['GET'], function ($vars) use ($app, $jatbi, $template, $accStore) {
		$vars['title'] = $jatbi->lang("Xem chi tiết thu chi");

		$data = $app->get("warehouses", ["id", "code", "date", "date_poster", "user", "content", "receive_date"], ["id" => $app->xss($vars['id'])]);
		$user = $app->get("warehouses", "user", ["move" => $data['id']]);
		$vars['data'] = $data;
		$vars['user'] = $user;
		if ($vars['data']) {
			$details = $app->select("warehouses_details", ["id", "products", "amount_total", "amount_move", "price"], ["warehouses" => $data['id'], "deleted" => 0]);
			$vars['details'] = $details;
			echo $app->render($template . '/warehouses/warehouses-return-views.html', $vars, $jatbi->ajax());
		} else {
			echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
		}
	})->setPermissions(['warehouses-import-history']);

	$app->router("/warehouses-return/{id}", ['GET'], function ($vars) use ($app, $jatbi, $template, $accStore, $stores) {
		$vars['title'] = $jatbi->lang("Nhập trả hàng");
		$data = $app->get("warehouses", ["id", "stores", "branch", "vendor", "data"], ["id" => $app->xss($vars['id'])]);
		if ($data > 1) {
			$warehouses_details = $app->select("warehouses_details", ["id", "products", "amount_total", "amount_move", "price"], ["warehouses" => $data["id"]]);
			$vars['warehouses_details'] = $warehouses_details;
			$vars['data'] = $data;
			echo $app->render($template . '/warehouses/warehouses-return.html', $vars);
		} else {
			echo $app->render($template . '/error.html', $vars, $jatbi->ajax());
		}
	});

	$app->router("/warehouses-return/{id}/completed", ['POST'], function ($vars) use ($app, $jatbi, $setting, $accStore, $stores) {
		$app->header(['Content-Type' => 'application/json']);
		$vars['title'] = $jatbi->lang("Nhập trả hàng");
		$data = $app->get("warehouses", ["id", "stores", "branch", "vendor", "data"], ["id" => $app->xss($vars['id'])]);
		if ($data > 1) {
			$warehouses_details = $app->select("warehouses_details", ["id", "products", "amount_total", "amount_move", "price"], ["warehouses" => $data["id"]]);
			$vars['warehouses_details'] = $warehouses_details;
			$vars['data'] = $data;
			$insert = [
				"code"			=> "return",
				"type"			=> "return",
				"data"			=> "products",
				"stores"		=> $data['stores'],
				"branch"		=> $data['branch'],
				"content"		=> "Trả hàng",
				"vendor"		=> $data['vendor'],
				"user"			=> $app->getSession("accounts")['id'] ?? 0,
				"date"			=> date("Y-m-d H:i:s"),
				"active"		=> $jatbi->active(30),
				"date_poster"	=> date("Y-m-d H:i:s"),
				"receive_status" => 4,
			];
			$app->insert("warehouses", $insert);
			$orderId = $app->id();
			foreach ($warehouses_details as $warehouse_detail) {
				$product = $app->get("products", ["id", "price", "amount"], ["id" => $warehouse_detail["products"]]);
				$tongtra = $warehouse_detail['amount_total'] - $warehouse_detail['amount_move'];
				if ($tongtra > 0) {
					$pro = [
						"warehouses" 	=> $orderId,
						"data"			=> $data['data'],
						"type"			=> 'import',
						"vendor"		=> $data['vendor'],
						"products"		=> $product['id'],
						"amount"		=> $tongtra,
						"amount_total"	=> $tongtra,
						"price"			=> $product['price'],
						"notes"			=> 'Trả hàng từ phiếu #' . $data['id'],
						"date"			=> date("Y-m-d H:i:s"),
						"user"			=> $app->getSession("accounts")['id'] ?? 0,
						"stores"		=> $data['stores'],
						"branch"		=> $data['branch'],
					];
					$app->insert("warehouses_details", $pro);
					$GetID = $app->id();
					$warehouses_logs = [
						"type"		=> $pro['type'],
						"data"		=> $pro['data'],
						"warehouses" => $pro['warehouses'],
						"details"	=> $GetID,
						"products"	=> $product['id'],
						"price"		=> $product['price'],
						"amount"	=> $pro['amount'],
						"total"		=> $pro['amount'] * $product['price'],
						"notes"		=> $pro['notes'],
						"date" 		=> date('Y-m-d H:i:s'),
						"user" 		=> $app->getSession("accounts")['id'] ?? 0,
						"stores"	=> $pro['stores'],
						"branch"	=> $pro['branch'],
					];
					$app->insert("warehouses_logs", $warehouses_logs);
				}
				$amount = [
					'amount' => $product['amount'] + $warehouses_logs['amount'],
				];
				$app->update("products", $amount, ['id' => $product["id"]]);
				$app->update("warehouses", ["receive_status" => 2], ["id" => $data["id"]]);
			}
			echo json_encode(['status' => 'success', 'content' => "Cập nhật thành công"]);
		} else {
			echo json_encode(['status' => 'error', 'content' => "Dữ liệu không tồn tại"]);
		}
	});

	$app->router("/warehouses-import/purchase/{id}", ['GET'], function ($vars) use ($app, $jatbi, $template, $accStore, $stores) {
		$vars['title'] = $jatbi->lang("Nhập hàng");
		$action = "import";
		$router['2'] = "purchase";
		$router['3'] = $app->xss($vars['id']);
		$getMoveS = $app->get("warehouses", "id", ["id" => $app->xss($router['3']), "deleted" => 0, "receive_status" => 2]);
		$vars['getMoveS'] = $getMoveS;
		$vars['action'] = $action;
		if (!isset($_SESSION['warehouses'][$action]['import'])) {
			if ($router['2'] == "purchase") {
				$_SESSION['warehouses'][$action]['import'] = "purchase";
			} elseif ($router['2'] == "move") {
				$_SESSION['warehouses'][$action]['import'] = "move";
			} elseif (!isset($router['2'])) {
				$_SESSION['warehouses'][$action]['import'] = "";
			}
		} else {
			if ($_SESSION['warehouses'][$action]['import'] != $router['2']) {
				unset($_SESSION['warehouses'][$action]);
			}
		}

		if ($router['2'] == "purchase") {
			if ($_SESSION['warehouses'][$action]['purchase'] != $router['3']) {
				unset($_SESSION['warehouses'][$action]);
			}
			if (!isset($_SESSION['warehouses'][$action]['purchase'])) {
				// $_SESSION['warehouses'][$action]['import'] = "purchase";
				$getPur = $app->get("purchase", ['id', 'code', 'vendor'], ["id" => $app->xss($router['3']), "deleted" => 0, "status" => 3]);
				$getPros = $app->select("purchase_products", ['id', 'products', 'amount', 'price', 'vendor'], ["purchase" => $getPur['id'], "deleted" => 0]);
				foreach ($getPros as $key => $value) {
					$getPro = $app->get("products", ['id', 'code', 'name', 'categorys', 'units', 'notes'], ["id" => $value['products']]);
					$_SESSION['warehouses'][$action]['products'][] = [
						"products" => $value['products'],
						"amount_buy" => str_replace([','], '', $value['amount']),
						"amount" => str_replace([','], '', $value['amount']),
						"price" => $value['price'] ?? 0,
						"vendor" => $value['vendor'] ?? 0,
						"images" => $getPro['images'] ?? "",
						"code" => $getPro['code'] ?? "",
						"name" => $getPro['name'] ?? "",
						"categorys" => $getPro['categorys'] ?? "",
						"units" => $getPro['units'] ?? "",
						"notes" => $getPro['notes'] ?? "",
					];
				}
				$_SESSION['warehouses'][$action]['purchase'] = $getPur['id'];
				$_SESSION['warehouses'][$action]['content'] = "nhập hàng từ phiếu mua #" . $getPur['code'];
				$_SESSION['warehouses'][$action]['vendor'] = $app->get("vendors", "*", ["id" => $getPur['vendor']]);
			}
		}

		if (!isset($_SESSION['warehouses'][$action]['type'])) {
			$_SESSION['warehouses'][$action]['type'] = $action;
		}
		if (!isset($_SESSION['warehouses'][$action]['data'])) {
			$_SESSION['warehouses'][$action]['data'] = 'products';
		}
		if (count($stores) == 1) {
			$_SESSION['warehouses'][$action]['stores'] = ["id" => $app->get("stores", "id", ["id" => $accStore, "status" => 'A', "deleted" => 0, "ORDER" => ["id" => "ASC"]])];
		}
		if (!isset($_SESSION['warehouses'][$action]['date'])) {
			$_SESSION['warehouses'][$action]['date'] = date("Y-m-d");
		}
		$data = [
			"type" => $_SESSION['warehouses'][$action]['type'],
			"data" => $_SESSION['warehouses'][$action]['data'],
			"stores" => $_SESSION['warehouses'][$action]['stores'] ?? "",
			"branch" => $_SESSION['warehouses'][$action]['branch'] ?? "",
			"date" => $_SESSION['warehouses'][$action]['date'],
			"content" => $_SESSION['warehouses'][$action]['content'],
			"import" => $_SESSION['warehouses'][$action]['import'] ?? "",
			"products" => $_SESSION['warehouses'][$action]['products'] ?? "",
		];
		$SelectProducts = $_SESSION['warehouses'][$action]['products'] ?? [];
		$storeId  = is_array($data["stores"]) ? ($data["stores"]["id"] ?? null) : $data["stores"];
		$branchId = is_array($data["branch"]) ? ($data["branch"]["id"] ?? null) : $data["branch"];

		$products = $app->select("products", ['id', 'name', 'code'], [
			"deleted" => 0,
			"status" => 'A',
			"stores" => $storeId,
			"branch" => $branchId
		]);

		$branchsssss = $app->select("branch", ['id(value)', 'name(text)', "code"], [
			"deleted" => 0,
			"stores" => $storeId,
			"status" => 'A'
		]);

		array_unshift($branchsssss, ["value" => "", "code" => "", "text" => ""]);
		array_unshift($stores, ["value" => "", "code" => "", "text" => ""]);
		$vars['stores'] = $stores;
		$vars['data'] = $data;
		$vars['products'] = $products;
		$vars['branchsssss'] = $branchsssss;
		$vars['SelectProducts'] = $SelectProducts;
		$vars['accStore'] = $accStore;
		echo $app->render($template . '/warehouses/warehouses-importpurchase.html', $vars);
	})->setPermissions(['warehouses-import']);
})->middleware('login');
