<?php
    if (!defined('ECLO')) die("Hacking attempt");
    $jatbi = $app->getValueData('jatbi');
    $setting = $app->getValueData('setting');
    $app->setComponent('header', function($vars) use ($app, $setting, $jatbi) {
        $account = [];
        if($app->getSession("accounts")){
            $account = $app->get("accounts","*",["id"=>$app->getSession("accounts")['id'],"status"=>"A"]);
        }
        require_once($setting['template'].'/components/header.html'); 
    });
    $app->setComponent('footer', function($vars) use ($app, $setting, $jatbi) {
        require_once($setting['template'].'/components/footer.html');
    }); 
    $app->setComponent('sidebar', function($vars) use ($app, $setting, $jatbi) {
        if($app->getSession("accounts")){
            $SelectMenu = $app->getValueData('menu');
            $account = $app->getValueData("account");
            $getsetting = $app->get("settings","*",["account"=>$account['id']]);
            $notification = $app->count("notifications","id",["account"=>$account['id'],"views"=>0]);
            $getRouter = explode("/",$app->getRoute());
            if (isset($getRouter[1]) && $getRouter[1] === trim($setting['manager'], '/')) {
                array_shift($getRouter);
            }
        }
        require_once($setting['template'].'/components/sidebar.html');
    });
    //status Component
    $app->setComponent('status', function($vars) use ($app, $setting, $jatbi) {
        $url = isset($vars['url']) ? $vars['url'] : '';
        $data = isset($vars['data']) ? $vars['data'] : '';
        $permissions = isset($vars['permission']) ? $vars['permission'] : [];
        $hasPermission = empty($permissions) || array_reduce($permissions, fn($carry, $perm) => $carry || $jatbi->permission($perm) == 'true', false);
        $placeholder = isset($vars['placeholder']) ? $vars['placeholder'] : '';
        $class = isset($vars['class']) ? ' ' . htmlspecialchars($vars['class']) : '';
        $text_placeholder = '';
        if($placeholder){
            $text_placeholder = '<label class="form-check-label mt-1 ms-3" for="switchCheckDefault">'.$placeholder.'</label>';
        }
        if ($hasPermission) {
            echo '<div class="form-check form-switch '.$class.'">
                  <input class="form-check-input" data-action="click" data-url="'.$jatbi->url($url).'" data-alert="true" type="checkbox" role="switch" ' . ($data=='A' ? 'checked' : '') . '>
                  '.$text_placeholder.'
               </div>';
        }
        else {
            echo '<div class="form-check form-switch '.$class.'">
                  <input class="form-check-input" disabled type="checkbox" role="switch" ' . ($data=='A' ? 'checked' : '') . '>
                  '.$text_placeholder.'
               </div>';
        }
    });
    //box Component
    $app->setComponent('box', function($vars) use ($app, $setting, $jatbi) {
        $data = isset($vars['data']) ? $vars['data'] : '';
        echo '<div class="form-check"><input class="form-check-input checker" type="checkbox" value="'.$data.'"></div>';
    });
    //action Component
    $app->setComponent('action', function($vars) use ($app, $setting, $jatbi) {
        if (!is_array($vars) || !isset($vars['button']) || !is_array($vars['button'])) {
            return;
        }
        $buttons = $vars['button'];
        $class = isset($vars['class']) ? $vars['class'] : '';
        $output = '';
        foreach ($buttons as $button) {
            if (!is_array($button) || !isset($button['type'])) {
                continue;
            }
            $name = htmlspecialchars($button['name'] ?? '');
            $hidden = $button['hidden'] ?? true;
            $icon = $button['icon'] ?? '';
            $class = htmlspecialchars($button['class'] ?? '');
            $type = $button['type'] ?? 'link';
            $permissions = $button['permission'] ?? [];
            $action = '';

            if (isset($button['action']) && is_array($button['action'])) {
                $pairs = [];
                foreach ($button['action'] as $key => $value) {
                    $keyEscaped = htmlspecialchars($key);
                    if ($key === 'data-url' || $key === 'href') {
                        $valueEscaped = $jatbi->url(htmlspecialchars($value));
                    } else {
                        $valueEscaped = htmlspecialchars($value);
                    }
                    $pairs[] = $keyEscaped . '="' . $valueEscaped . '"';
                }
                $action = implode(' ', $pairs);
            }
            $hasPermission = empty($permissions) || array_reduce($permissions, fn($carry, $perm) => $carry || $jatbi->permission($perm) == 'true', false);
            if ($hasPermission && $hidden===true) {
                if ($type === 'button') { 
                    $output .= '<li><button ' . $action . ' class="btn dropdown-item ' . $class . '">'.$icon . $name . '</button></li>';
                }
                elseif ($type === 'divider') { 
                    $output .= '<li><hr class="dropdown-divider"></li>';
                } else {
                    $output .= '<li><a ' . $action . ' class="btn dropdown-item ' . $class . '">'.$icon . $name . '</a></li>';
                }
            }
        }
        if (empty($output)) {
            return;
        }
        echo '<div class="dropdown">
                <button class="btn btn-eclo-light btn-sm border-0 py-1 px-2 rounded-3 small fw-bold fs-6 '.$class.'" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="ti ti-dots"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end border-0 bg-blur bg-body bg-opacity-25 shadow-lg rounded-4 min-width" style="--blur:10px;--min-width:100px">'
                . $output .
                '</ul>
            </div>';
    });
    // Brands Component
    $app->setComponent('brands', function($vars) use ($app, $setting, $jatbi) {
        $name = isset($vars['name']) ? $vars['name'] : '';
        $selected = isset($vars['selected']) ? $vars['selected'] : [];
        $multi = isset($vars['multi']) ? 'multiple' : null; 
        $cookie = $app->getCookie('brands') ?? null;
        $placeholder = isset($vars['placeholder']) ? $vars['placeholder'] : '';
        $options = $jatbi->brands("SELECT");
        $class = isset($vars['class']) ? ' ' . htmlspecialchars($vars['class']) : '';
        $id = isset($vars['id']) ? ' id="' . htmlspecialchars($vars['id']) . '"' : '';
        $attr = isset($vars['attr']) ? $vars['attr'] : '';
        $required = isset($vars['required']) ? $vars['required'] : '';

        // Nếu không có cookie hoặc cookie là 0
        if (!$cookie || $cookie == 0) {
            echo '<div class="mb-3">
                <label for="' . htmlspecialchars($name) . '" class="form-label fw-bold">'.$placeholder.' '. ($required?'<span class="text-danger">*</span>':'') .'</label>
                <select data-select data-style="form-select py-3 rounded-4 w-100 bg-body" data-live-search="true" data-width="100%" class="'  . $class . '"' . $id . ' ' . $attr . ' name="brands' . ($multi ? '[]' : '') . '" '.$multi.' data-actions-box="true">';
                foreach ($options as $option) {
                    $isSelected = is_array($selected) ? in_array($option['id'], $selected) : $selected == $option['id'];
                    echo '<option value="' . htmlspecialchars($option['id']) . '"' . ($isSelected ? ' selected' : '') . '>' . htmlspecialchars($option['name']) . '</option>';
                }

            echo '</select></div>';
        }
    });
    // Checkbox Component
    $app->setComponent('checkbox', function($vars) {
        $name = isset($vars['name']) ? $vars['name'] : '';
        $label = isset($vars['label']) ? $vars['label'] : '';
        $checked = isset($vars['checked']) ? 'checked' : '';
        $placeholder = isset($vars['placeholder']) ? $vars['placeholder'] : '';
        $class = isset($vars['class']) ? ' ' . htmlspecialchars($vars['class']) : '';
        $id = isset($vars['id']) ? ' id="' . htmlspecialchars($vars['id']) . '"' : '';
        $attr = isset($vars['attr']) ? $vars['attr'] : '';
        $options = isset($vars['options']) ? $vars['options'] : [];
        $required = isset($vars['required']) ? $vars['required'] : '';
        
        if ($options) {
            echo '<div class="mb-3">
            <label for="' . htmlspecialchars($name) . '" class="form-label">'.$placeholder.'</label>';
            
            foreach ($options as $option) {
                // Kiểm tra nếu giá trị checkbox đã được chọn
                $isChecked = isset($vars['checked']) && in_array($option['value'], $vars['checked']) ? 'checked' : '';

                $attrs = '';
                if (!empty($option['attr'])) {
                    if (is_array($option['attr'])) {
                        foreach ($option['attr'] as $key => $val) {
                            $attrs .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($val) . '"';
                        }
                    } else {
                        $attrs = ' ' . $option['attr'];
                    }
                }

                echo '<div class="form-check mb-3' . $class . '"' . $id . $attr . '>
                        <input class="form-check-input" value="'.$option['value'].'" type="checkbox" id="' . htmlspecialchars($option['value']) . '" name="' . htmlspecialchars($name) . '[]" ' . $isChecked . '>
                        <label class="form-check-label mt-1 ms-1" for="' . htmlspecialchars($option['value']) . '">
                            ' . htmlspecialchars($option['text']) . '
                        </label>
                    </div>';
            }
            echo '</div>';
        } else {
            $text_placeholder = '';
            if($placeholder){
                $text_placeholder = '<label for="' . htmlspecialchars($name) . '" class="form-label">'.$placeholder.'</label>';
            }
            echo '
            <div class="mb-3">
                '.$text_placeholder.'
                <div class="form-check' . $class . '"' . $id . '>
                    <input class="form-check-input" '.$attr.' type="checkbox" id="' . htmlspecialchars($name) . '" name="' . htmlspecialchars($name) . '" ' . $checked . '>
                    <label class="form-check-label mt-1 ms-1" for="' . htmlspecialchars($name) . '">
                        ' . htmlspecialchars($label) . '
                    </label>
                </div>
            </div>';
        }
    });
    // Upload Component
    $app->setComponent('upload-images', function($vars) use ($app, $jatbi) {
        $name        = isset($vars['name']) ? htmlspecialchars($vars['name']) : '';
        $value       = isset($vars['value']) ? htmlspecialchars($vars['value']) : '';
        $router      = isset($vars['router']) ? htmlspecialchars($vars['router']) : '';
        $placeholder = isset($vars['placeholder']) ? htmlspecialchars($vars['placeholder']) : '';
        $choices      = isset($vars['choices']) ? htmlspecialchars($vars['choices']) : '';
        $id          = isset($vars['id']) ? ' id="' . htmlspecialchars($vars['id']) . '"' : '';
        $required    = !empty($vars['required']);
        $requiredMark = $required ? '<span class="text-danger">*</span>' : '';
        $inputStyle = $value !== '' ? 'style="display: none;"' : '';
        $showStyle  = $value === '' ? 'style="display: none;"' : '';
        echo '<label class="fw-bold text-body mb-2">' . $placeholder . ' ' . $requiredMark . '</label>';
        echo '<div class="rounded-4 border border-eclo p-3 bg-body">';
        echo '  <div class="d-flex justify-content-center align-items-center upload-files rounded-4">';
        echo '    <div class="text-center input-file" ' . $inputStyle . '>';
        echo '      <input type="file" id="btn-check" ' . ($router !== '' ? 'data-router="' . $router . '"' : 'name="' . $name . '"') . ' autocomplete="off" class="btn-check upload-file"' . $id . '>';
        echo '      <label class="btn me-2 p-0" for="btn-check">';
        echo '        <div class="mb-2 text-center drop-area">';
        echo '          <img src="/assets/img/upload.svg" class="w-50">';
        echo '          <span class="d-block fw-bold">' . $jatbi->lang("Nhấn vào để tải hình ảnh của bạn lên") . '</span>';
        echo '        </div>';
        if ($choices === 'true') {
        echo '        <div class="py-2 mb-3">'.$jatbi->lang("Hoặc").'</div>';
        echo '              <button class="btn btn-eclo py-2 mb-2 rounded-pill px-5" data-action="modal" data-pjax-history="false" data-url="/files/assets?type=images" data-multi="files">';
        echo $jatbi->lang("Chọn từ dữ liệu");
        echo '                </button>';
        }
        echo '      </label>';
        echo '    </div>';
        echo '    <div class="show-file text-center position-relative" ' . $showStyle . '>';
        echo '      <img src="/' . $value . '" class="w-100 rounded-4 shadow images-container">';
        echo '      <div class="position-absolute end-0 bottom-0 p-2">';
        echo '        <button type="button" class="btn btn-sm btn-danger d-block rounded-circle width height" style="--width:50px;--height:50px;" id="remove-upload-file">';
        echo '          <i class="ti ti-trash fs-5"></i>';
        echo '        </button>';
        echo '      </div>';
        if ($router !== '') {
            echo '      <div class="progress upload-progress position-absolute w-100 start-0 top-0" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="display: none">';
            echo '        <div class="progress-bar bg-danger progress-bar-striped progress-bar-animated" style="width: 0%;"></div>';
            echo '      </div>';
            echo '      <input type="hidden" name="' . $name . '" class="value-file" value="' . $value . '">';
        }
        echo '    </div>';
        echo '  </div>'; 
        echo '</div>';
    });
    // Input Component
    $app->setComponent('input', function($vars) {
        $type = isset($vars['type']) ? $vars['type'] : 'text';
        $name = isset($vars['name']) ? $vars['name'] : '';
        $value = isset($vars['value']) ? $vars['value'] : '';
        $div = isset($vars['div']) ? $vars['div'] : true;
        $placeholder = isset($vars['placeholder']) ? $vars['placeholder'] : '';
        $class = isset($vars['class']) ? ' ' . htmlspecialchars($vars['class']) : '';
        $id = isset($vars['id']) ? ' id="' . htmlspecialchars($vars['id']) . '"' : '';
        $attr = isset($vars['attr']) ? $vars['attr'] : '';
        $required = isset($vars['required']) ? $vars['required'] : '';
        if($div==true){
            echo ' <div class="mb-3">';
        }
        echo '
            <label for="' . htmlspecialchars($name) . '" class="form-label">' . htmlspecialchars($placeholder) . ' '. ($required?'<span class="text-danger">*</span>':'') .'</label>
            <input type="' . htmlspecialchars($type) . '" class="form-control rounded-4 p-3 bg-body' . $class . '"' . $id . ' name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '" placeholder="' . htmlspecialchars($placeholder) . '"' . $attr . ' '.$required.'>
        ';
        if($div==true){
            echo ' </div>';
        }
    });
    // Textarea Component
    $app->setComponent('textarea', function($vars) {
        $type = isset($vars['type']) ? $vars['type'] : 'text';
        $name = isset($vars['name']) ? $vars['name'] : '';
        $value = isset($vars['value']) ? $vars['value'] : '';
        $placeholder = isset($vars['placeholder']) ? $vars['placeholder'] : '';
        $class = isset($vars['class']) ? ' ' . htmlspecialchars($vars['class']) : '';
        $id = isset($vars['id']) ? ' id="' . htmlspecialchars($vars['id']) . '"' : '';
        $attr = isset($vars['attr']) ? $vars['attr'] : '';
        $required = isset($vars['required']) ? $vars['required'] : '';

        echo '
        <div class="mb-3">
            <label for="' . htmlspecialchars($name) . '" class="form-label">' . htmlspecialchars($placeholder) . ' '. ($required?'<span class="text-danger">*</span>':'') .'</label>
            <textarea type="' . htmlspecialchars($type) . '" class="form-control rounded-4 p-3 bg-body' . $class . '"' . $id . ' name="' . htmlspecialchars($name) . '" placeholder="' . htmlspecialchars($placeholder) . '"' . $attr . '>' . htmlspecialchars($value) . '</textarea>
        </div>';
    });
    // Button Component
    $app->setComponent('button', function($vars) {
        $label = isset($vars['label']) ? $vars['label'] : 'Click Me';
        $type = isset($vars['type']) ? $vars['type'] : 'button';
        $color = isset($vars['color']) ? $vars['color'] : 'danger';
        $id = isset($vars['id']) ? ' id="' . htmlspecialchars($vars['id']) . '"' : '';
        $class = isset($vars['class']) ? ' ' . htmlspecialchars($vars['class']) : '';
        $attr = isset($vars['attr']) ? $vars['attr'] : '';

        echo '
            <button type="'.$type.'" class="btn rounded-pill btn-' . htmlspecialchars($color) . $class . '"' . $id . $attr . '>' . ($label) . '</button>
        ';
    });
    // Input Group Component
    $app->setComponent('input-group', function($vars) {
        $inputs = isset($vars['inputs']) ? $vars['inputs'] : [];
        $class = isset($vars['class']) ? ' ' . htmlspecialchars($vars['class']) : '';
        $id = isset($vars['id']) ? ' id="' . htmlspecialchars($vars['id']) . '"' : '';
        $attr = isset($vars['attr']) ? $vars['attr'] : '';

        echo '<div class="input-group' . $class . '"' . $id . $attr . '>';
            foreach ($inputs as $input) {
                echo '<span class="input-group-text">' . htmlspecialchars($input['text']) . '</span>';
                echo '<input type="' . htmlspecialchars($input['type']) . '" class="form-control p-3" placeholder="' . htmlspecialchars($input['placeholder']) . '">';
            }
        echo '</div>';
    });
    // Input Group Component
    $app->setComponent('input-group-button', function($vars) {
        $button_label = isset($vars['button']['label']) ? $vars['button']['label'] : 'Click Me';
        $button_type = isset($vars['button']['type']) ? $vars['button']['type'] : 'danger';
        $button_color = isset($vars['button']['color']) ? $vars['button']['color'] : 'danger';
        $button_id = isset($vars['button']['id']) ? ' id="' . htmlspecialchars($vars['button']['id']) . '"' : '';
        $button_class = isset($vars['button']['class']) ? ' ' . htmlspecialchars($vars['button']['class']) : '';
        $button_attr = isset($vars['button']['attr']) ? $vars['button']['attr'] : '';

        $type = isset($vars['type']) ? $vars['type'] : 'text';
        $name = isset($vars['name']) ? $vars['name'] : '';
        $value = isset($vars['value']) ? $vars['value'] : '';
        $placeholder = isset($vars['placeholder']) ? $vars['placeholder'] : '';
        $class = isset($vars['class']) ? ' ' . htmlspecialchars($vars['class']) : '';
        $id = isset($vars['id']) ? ' id="' . htmlspecialchars($vars['id']) . '"' : '';
        $attr = isset($vars['attr']) ? $vars['attr'] : '';

        echo '
            <div class="mb-3">
                <label for="' . htmlspecialchars($name) . '" class="form-label">' . htmlspecialchars($placeholder) . '</label>
                <div class="input-group">
                    <input type="' . htmlspecialchars($type) . '" class="form-control p-3 ' . $class . '"' . $id . ' name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '" placeholder="' . htmlspecialchars($placeholder) . '"' . $attr . '>
                    <button type="'.$button_type.'" class="btn btn-' . htmlspecialchars($button_color) . $button_class . '"' . $button_id . $button_attr . '>' . ($button_label) . '</button>
                </div>
            </div>';
    });
    // Select Component
    $app->setComponent('select', function($vars) {
        $name = $vars['name'] ?? '';
        $div = $vars['div'] ?? true;
        $options = $vars['options'] ?? [];
        $selected = $vars['selected'] ?? [];
        $placeholder = $vars['placeholder'] ?? '';
        $class = !empty($vars['class']) ? ' ' . htmlspecialchars($vars['class']) : 'form-select py-3 rounded-4 w-100 bg-body';
        $id = !empty($vars['id']) ? ' id="' . htmlspecialchars($vars['id']) . '"' : '';
        $attr = $vars['attr'] ?? '';
        $required = !empty($vars['required']);
        $isMultiple = (is_string($attr) && strtolower($attr) === 'multiple') || (is_array($attr) && in_array('multiple', $attr));
        $emptyOption = $vars['emptyOption'] ?? false; // ✅ thêm tùy chọn option rỗng

        if($div==true){
            echo '<div class="mb-3">';
            echo '<label for="' . htmlspecialchars($name) . '" class="form-label">'
                . htmlspecialchars($placeholder)
                . ($required ? ' <span class="text-danger">*</span>' : '') .
                '</label>';
        }

        echo '
            <select 
                data-select 
                data-style="'.$class.'" 
                data-live-search="true" 
                data-width="100%" 
                class="' . $class . '"'
                . $id .
                ' name="' . htmlspecialchars($name) . '"'
                . ($attr ? ' ' . (is_array($attr) ? implode(' ', $attr) : $attr) : '')
                . ($isMultiple ? ' multiple' : '') .
            '>';

        // ✅ option rỗng
        if ($emptyOption) {
            echo '<option value=""' . (!$selected ? ' selected' : '') . '></option>';
        }

        // Nếu multiple thì giữ placeholder dạng disabled selected
        if($isMultiple && !$emptyOption){
            echo '<option value="" disabled selected>' . htmlspecialchars($placeholder) . '</option>';
        }

        foreach ($options as $option) {
            $attrs = '';

            if (!empty($option['attr'])) {
                if (is_array($option['attr'])) {
                    foreach ($option['attr'] as $key => $val) {
                        $attrs .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($val) . '"';
                    }
                } else {
                    $attrs = ' ' . htmlspecialchars($option['attr']);
                }
            }

            $isSelected = is_array($selected) ? in_array($option['value'], $selected) : $selected == $option['value'];
            echo '<option' . $attrs . ' value="' . htmlspecialchars($option['value']) . '"' . ($isSelected ? ' selected' : '') . '>' . htmlspecialchars($option['text']) . '</option>';
        }
        echo '</select>';

        if($div==true){
            echo '</div>';
        }
    });

    $app->setComponent("select-status", function($vars) use($app,$jatbi){
        $value = isset($vars['value']) ? $vars['value'] : '';
        echo $app->component('select',[
            "name"=>'status',
            "placeholder"=>$jatbi->lang("Trạng thái"),
            "selected" => $value ?? '',
            "class" => 'filter-name',
            "attr" => 'data-width="100%"',
            "options" => [
                ["value"=>"A","text"=>$jatbi->lang("Kích hoạt")],
                ["value"=>"D","text"=>$jatbi->lang("Không Kích hoạt")],
            ]
        ]);
    });
    // Radio Component
    $app->setComponent('radio', function($vars) {
        $name = isset($vars['name']) ? $vars['name'] : '';
        $options = isset($vars['options']) ? $vars['options'] : [];
        $selected = isset($vars['selected']) ? $vars['selected'] : ''; 
        $class = isset($vars['class']) ? ' ' . htmlspecialchars($vars['class']) : '';
        $placeholder = isset($vars['placeholder']) ? $vars['placeholder'] : '';
        $id = isset($vars['id']) ? ' id="' . htmlspecialchars($vars['id']) . '"' : '';
        $attr = isset($vars['attr']) ? $vars['attr'] : '';
        $required = isset($vars['required']) ? $vars['required'] : '';

        echo  '<div class="mb-3">
        <label for="' . htmlspecialchars($name) . '" class="form-label">'.$placeholder.' '. ($required?'<span class="text-danger">*</span>':'') .'</label>';
        
        foreach ($options as $option) {
            // Kiểm tra nếu giá trị của radio được chọn
            $isChecked = (is_array($selected) && in_array($option['value'], $selected)) || ($selected == $option['value']) ? 'checked' : ''; // Kiểm tra nếu selected trùng với option['value']

            echo '
            <div class="form-check mb-3' . $class . '"' . $id . $attr . '>
                <input class="form-check-input" type="radio" id="' . htmlspecialchars($option['value']) . '" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($option['value']) . '" ' . $isChecked . '>
                <label class="form-check-label mt-1 ms-1" for="' . htmlspecialchars($option['value']) . '">
                    ' . htmlspecialchars($option['text']) . '
                </label>
            </div>';
        }

        echo '</div>';
    });
?>