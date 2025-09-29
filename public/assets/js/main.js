$(function () {
    window.workflowManager = window.workflowManager || {};
    if (typeof window.workflowManager === 'undefined') {
        window.workflowManager = {};
    }
    let formatTimeout;
    let active = $('.page-content').attr("data-active");
    let pjaxContainer = ["[pjax-load-content]","header"];
    var pjax = new Pjax({
      elements: "[data-pjax]",
      selectors: pjaxContainer,
      cacheBust: false,
      scrollTo: false,
      history: true,
    });
    document.addEventListener('pjax:send', pjaxSend);
    document.addEventListener('pjax:complete', pjaxComplete);
    document.addEventListener('pjax:success', whenDOMReady);
    document.addEventListener('pjax:error',pjaxError);
    $(document).ready(function() {
        document.addEventListener("click", function(event) {
              const link = event.target.closest("a[download]");
              if (!link) return;
              event.preventDefault();
              const url = link.href;
              const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
              const isPWA = window.matchMedia("(display-mode: standalone)").matches || window.navigator.standalone;

              if (isIOS && isPWA) {
                window.location.href = url;
              } else {
                const tempLink = document.createElement("a");
                tempLink.href = url;
                tempLink.setAttribute("download", link.getAttribute("download") || "file.png");
                document.body.appendChild(tempLink);
                tempLink.click();
                document.body.removeChild(tempLink);
              }
        });
        $(document).on("click", "[data-pjax]", function (e) {
            var $this = $(this);
            pjaxConfig($this);
            if ($this.is('[data-dismiss="offcanvas"]')) {
                var offcanvas = document.querySelector('.offcanvas.show') || document.querySelector('.offcanvas-lg.show');
                if (offcanvas) {
                    var bsOffcanvas = bootstrap.Offcanvas.getInstance(offcanvas);
                    if (bsOffcanvas) bsOffcanvas.hide();
                }
            }
            if ($this.is('[data-dismiss="modal"]')) {
                var Modal = document.querySelector('.modal.show');
                if (Modal) {
                    var bsModal = bootstrap.Modal.getInstance(Modal);
                    if (bsModal) bsModal.hide();
                }
            }
        });
        $(document).off('click change', '[data-action="load-table"]').on('click change', '[data-action="load-table"]', function (e) {
            const $trigger = $(this);
            const action = $trigger.data('action');
            const triggerType = action === 'change-load' ? 'change' : 'click';
            if (e.type !== triggerType) return;
            if ($trigger.data('url')) {
                return; 
            }
            const targetTableId = $trigger.data('table-load');
            if (targetTableId) {
                const tableSelector = `#${targetTableId}`;
                const resetPaging = $trigger.data('table-reset-paging') !== false;
                
                if ($.fn.dataTable.isDataTable(tableSelector)) {
                    $(tableSelector).DataTable().ajax.reload(null, resetPaging);
                } else {
                    console.warn(`[click-load] Không tìm thấy DataTable: ${tableSelector}`);
                }
            }
        });
        $(document).off('click change', '[data-action="show-hide"]').on('click change', '[data-action="show-hide"]', function (e) {
            const $trigger = $(this);
            const targetSelector = $trigger.data('show-hide-target');
            if (!targetSelector) return;

            const $target = $(targetSelector);
            if (!$target.length) return;

            const mode = $trigger.data('show-hide-mode'); // có thể là "reverse"

            // Checkbox
            if ($trigger.is(':checkbox')) {
                if (e.type === 'change') {
                    const isChecked = $trigger.is(':checked');
                    if (mode === 'reverse') {
                        $target.toggle(!isChecked); // đảo ngược
                    } else {
                        $target.toggle(isChecked);
                    }
                }
            }
            // Select
            else if ($trigger.is('select')) {
                if (e.type === 'change') {
                    const showValue = $trigger.data('show-hide-value');
                    if (showValue !== undefined) {
                        let match = $trigger.val() == showValue;
                        if (mode === 'reverse') match = !match;
                        $target.toggle(match);
                    } else {
                        let hasValue = !!$trigger.val();
                        if (mode === 'reverse') hasValue = !hasValue;
                        $target.toggle(hasValue);
                    }
                }
            }
            // Button hoặc link
            else {
                if (e.type === 'click') {
                    $target.toggle();
                }
            }
        });
        $(document).off('click change', '[data-transfer-target]').on('click change', '[data-transfer-target]', function (e) {
            const $trigger = $(this);
            let targetSelector = '';
            let valueToTransfer = '';

            // Xử lý riêng cho thẻ <select>
            if ($trigger.is('select')) {
                const $selectedOption = $trigger.find('option:selected');
                // Target luôn được lấy từ thẻ <select>
                targetSelector = $trigger.data('transfer-target');

                // Value được ưu tiên lấy từ data-transfer-value của <option>
                valueToTransfer = $selectedOption.data('transfer-value');
                if (typeof valueToTransfer === 'undefined') {
                    valueToTransfer = $selectedOption.val(); // Nếu không có thì lấy value mặc định
                }
            } 
            // Xử lý cho các thẻ còn lại (button, input, etc.)
            else {
                targetSelector = $trigger.data('transfer-target');
                valueToTransfer = $trigger.data('transfer-value');
                if (typeof valueToTransfer === 'undefined') {
                    valueToTransfer = $trigger.val();
                }
            }

            // Nếu không có target thì dừng lại
            if (!targetSelector) return;

            const $target = $(targetSelector);
            if (!$target.length) {
                console.error('Lỗi: Không tìm thấy phần tử target với selector:', targetSelector);
                return;
            }
            
            // Đảm bảo giá trị không phải null/undefined
            valueToTransfer = valueToTransfer ?? '';

            // Gán giá trị vào target
            if ($target.is('input, select, textarea')) {
                $target.val(valueToTransfer);

                // TÍCH HỢP: Kiểm tra và định dạng số nếu cần
                const numberType = $target.data('number');
                if (numberType === 'money' || numberType === 'number') {
                    formatElement($target);
                }
            } else {
                $target.text(valueToTransfer);
            }
        });

        if($('body').find('.modal-notification-register').length){
          $('body').find('.modal-notification-register').modal('show');
        }
        $(document).on("click",'#subscribe-btn',function() {
            $.getJSON('/users/vapid-public-key', function (response) {
                navigator.serviceWorker.ready.then(function (registration) {
                    return registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(response.key)
                    });
                }).then(function (subscription) {
                    $.ajax({
                        url: '/users/notification-register',
                        method: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify(subscription),
                        success: function (response) {
                            console.log(response);
                            swal_success(response.content, $('#subscribe-btn'));
                        }
                    });
                }).catch(function (error) {
                    swal_error(error);
                });
            });
        });
        $(document).on('click','.btn-print', function () {
          window.print();
        });
        $(document).on('change', '.checkall', function() {
            var checkbox = $(this).attr('data-checkbox');
            $(checkbox).prop('checked', this.checked);
        });
        themeLayout();
        whenDOMReady();
        initializeRegisteredPlugins();
    });
    function pjaxConfig($element) {
        let selector = $element.data("selector");
        let scrollTo = $element.data("pjax-scrollTo") !== "false" && ($element.data("scrollTo") ?? true);
        let historyState = $element.data("pjax-history") !== "false" && ($element.data("pjax-history") ?? true);
        pjax.options.history = historyState ?? pjax.options.history;
        pjax.options.scrollTo = scrollTo ?? pjax.options.scrollTo;
        pjax.options.selectors = selector ? selector.split(",").map(s => s.trim()) : pjaxContainer;
        const animationData = {};
        if ($element.data("pjax-animate") !== undefined) {
             animationData.animateEnabled = $element.data("pjax-animate") === true;
        }
        if ($element.data("pjax-right") !== undefined) {
            animationData.animateRightClass = $element.data("pjax-right");
        }
        if ($element.data("pjax-left") !== undefined) {
            animationData.animateLeftClass = $element.data("pjax-left");
        }
        if ($element.data("pjax-faster") !== undefined) {
            animationData.useFaster = $element.data("pjax-faster") !== undefined;
        }
        pjax._nextAnimationData = animationData;
    }
    function pjaxSend(){
      topbar.show();
    }
    function pjaxComplete(){
        topbar.hide();
        initializeRegisteredPlugins();
        const $canvas = $(this).find('[data-workflows]');
        if ($canvas.length) {
            const canvasId = '#' + $canvas.attr('id');
            const workflowInstance = window.workflowManager[canvasId];
            if (workflowInstance && typeof workflowInstance.destroy === 'function') {
                workflowInstance.destroy();
            }
        }
    }
    function pjaxError(){
      topbar.hide();
    }
    function whenDOMReady(){
        // mqttSocket();
        // mqttvideo();
        datatable();
        dataAction();
        workflowsLoad();
        modalOffcanvasload();
    }
    function modalOffcanvasload(){
        upload();
      selected();
      uploadImages();
      DomDataAction();
      editor();
      number();
      Countdown();
      initSearchBoxes();
      swiper();
      step();
      chartjs();
      datapicker();
      workflowsLoad();
      handleConditionalDisplay();
    }
    function themeLayout() {
      function setTheme(theme) {
        if (theme === 'system') {
          theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        $("html").attr("data-bs-theme", theme);
        $("body").attr("data-bs-theme", theme);
      }
      function toggleSidebar() {
        const currentLayout = $("body").attr("data-sidebar");
        const newLayout = currentLayout === 'full' ? 'small' : 'full';
        $("body").attr("data-sidebar", newLayout);
        localStorage.setItem('layout', newLayout);
      }
      let theme = localStorage.getItem('theme') || 'system';
      localStorage.setItem('theme', theme);
      setTheme(theme);
      if (theme === 'system') {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (event) => {
          setTheme('system');
        });
      }
      $(document).on("click", '[data-toggle-theme]', function () {
        const newTheme = $(this).attr("data-theme");
        localStorage.setItem('theme', newTheme);
        setTheme(newTheme);
      });
      $(document).on("click", '[data-toggle-sidebar]', toggleSidebar);
      const savedLayout = localStorage.getItem('layout') || 'full';
      $("body").attr("data-sidebar", savedLayout);
        const $hoverEffect = $('.hover-effect');
        const $menuContainer = $('.menu-container');
        const $menuContainerMobile = $('.menu-container-mobile');
        function updateHoverEffect($element, $container) {
          if ($element.length === 0 || $container.length === 0) return;
          const containerOffset = $container.offset().left;
          const elementOffset = $element.offset().left;
          const leftPos = elementOffset - containerOffset;
          const width = $element.outerWidth();
          $hoverEffect.css({
            left: leftPos,
            width: width,
          });
          $hoverEffect.addClass('show');
          $container.find('.header-menu').removeClass('hover');
          $element.addClass('hover');
        }
        function updateHoverForAllActive() {
          const $activeElements = $('.header-menu.active');
          $activeElements.each(function () {
            const $currentElement = $(this);
            const $currentContainer = $currentElement.closest('.menu-container, .menu-container-mobile');
            if ($currentContainer.length) {
              updateHoverEffect($currentElement, $currentContainer);
            }
          });
        }
        updateHoverForAllActive();
        $('.header-menu').hover(
          function () {
            const $currentContainer = $(this).closest('.menu-container, .menu-container-mobile');
            if ($currentContainer.length) {
              updateHoverEffect($(this), $currentContainer);
            }
          },
          function () {
            updateHoverForAllActive();
          }
        );
        $('.header-menu').on('click', function (e) {
          e.preventDefault();
          $('.header-menu').removeClass('active');
          $(this).addClass('active');
          updateHoverForAllActive();
        });
        $(window).on('resize', function () {
          updateHoverForAllActive();
        });
      if (!getCookie('did')) {
        setCookie('did', generateUUID(), 365);
      }
    }
    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }
    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            var r = Math.random() * 16 | 0,
                v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }
    function setCookie(name, value, days) {
        var expires = "";
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + (value || "") + expires + "; path=/";
    }
    function getCookie(name) {
        var nameEQ = name + "=";
        var ca = document.cookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) == ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    }
    function swal_success(text, $this = null) {
      Swal.fire({
        title: 'Success',
        text: text,
        icon: 'success',
        showCancelButton: false,
        buttonsStyling: false,
        confirmButtonText: 'Ok',
        customClass: {
          confirmButton: "btn fw-bold btn-success rounded-pill px-5"
        }
      }).then(function (result) {
        if ($this) {
            let $modal = $this.closest('.modal');
            let $offcanvas = $this.closest('.offcanvas');

            if ($modal.length) {
                let modalInstance = bootstrap.Modal.getInstance($modal[0]) || new bootstrap.Modal($modal[0]);
                modalInstance.hide();
            }
            if ($offcanvas.length) {
                let offcanvasInstance = bootstrap.Offcanvas.getInstance($offcanvas[0]) || new bootstrap.Offcanvas($offcanvas[0]);
                offcanvasInstance.hide();
            }
        }
      });
    }
    function swal_error(text) {
      Swal.fire({
        title: 'Error!',
        text: text,
        icon: 'error',
        showCancelButton: false,
        buttonsStyling: false,
        confirmButtonText: 'Ok',
        customClass: {
          confirmButton: "btn fw-bold btn-danger rounded-pill px-5"
        }
      });
    }
    function showToast(message, type = 'secondary', position = 'top-right') {
        const positionMap = {
            'top-right': 'top-0 end-0',
            'top-left': 'top-0 start-0',
            'top-center': 'top-0 start-50 translate-middle-x',
            'bottom-right': 'bottom-0 end-0',
            'bottom-left': 'bottom-0 start-0',
            'bottom-center': 'bottom-0 start-50 translate-middle-x',
            'middle-center': 'top-50 start-50 translate-middle'
        };
        const validColors = ['primary', 'secondary', 'success', 'danger', 'body', 'info', 'light', 'dark'];
        const toastColor = validColors.includes(type) ? type : 'danger';
        const toastClass = `text-bg-${toastColor}`;
        const containerClasses = positionMap[position] || positionMap['top-right'];
        const containerId = `toast-container-${position.replace('-', '')}`;
        if ($(`#${containerId}`).length === 0) {
            const containerHtml = `<div id="${containerId}" class="toast-container position-fixed p-3 ${containerClasses}" style="z-index: 1100"></div>`;
            $('body').append(containerHtml);
        }
        const toastHtml = `
            <div class="toast align-items-center ${toastClass} border-0 shadow-lg rounded-pill" role="alert" aria-live="assertive" aria-atomic="true">
              <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close border-0 btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
              </div>
            </div>`;
        
        const $toast = $(toastHtml);
        $(`#${containerId}`).append($toast);
        const toastInstance = new bootstrap.Toast($toast[0], { delay: 5000 });
        $toast.on('hidden.bs.toast', function () {
            $(this).remove();
        });
        toastInstance.show();
    }
    function number() {
        $('body').on('input', '[data-number="number"], [data-number="money"]', function() {
            formatElement($(this));
        });
        $('body').on('blur', '[data-number="number"], [data-number="money"]', function() {
            formatElement($(this), true);
        });
        $('[data-number="number"], [data-number="money"]').each(function() {
            formatElement($(this), true);
        });
    }
    function formatElement($el, onBlur = false) {
        let type = $el.data('number');
        if (!['number', 'money'].includes(type)) return;

        const currency = $el.data('currency') || '';
        const decimals = parseInt($el.data('decimals')) || 0;
        const sepThousands = $el.data('thousands') === '' ? '' : ($el.data('thousands') || '.');
        const sepDecimal = $el.data('decimal') || ',';

        const el = $el[0];
        const originalValue = $el.val();
        const caretStart = el.selectionStart;

        // --- BƯỚC 1: LÀM SẠCH GIÁ TRỊ ĐẦU VÀO ---
        let cleanValue = originalValue;
        let hasMinusSign = cleanValue.startsWith('-');
        if (hasMinusSign) {
            cleanValue = cleanValue.substring(1);
        }
        
        // Chỉ cho phép nhập dấu thập phân nếu decimals > 0
        if (decimals === 0) {
            cleanValue = cleanValue.replace(/[^0-9]/g, '');
        } else {
            const regexClean = new RegExp(`[^0-9\\${sepDecimal}]`, 'g');
            cleanValue = cleanValue.replace(regexClean, '');
            
            // Đảm bảo chỉ có một dấu thập phân
            const decimalParts = cleanValue.split(sepDecimal);
            if (decimalParts.length > 2) {
                cleanValue = decimalParts[0] + sepDecimal + decimalParts.slice(1).join('');
            }
        }

        // --- BƯỚC 2: XỬ LÝ LOGIC DỰA TRÊN SỰ KIỆN (INPUT hay BLUR) ---
        let formattedValue = '';

        if (onBlur) {
            // --- LOGIC KHI BLUR: ĐỊNH DẠNG ĐẦY ĐỦ ---
            let rawNumberStr = cleanValue.replace(sepDecimal, '.');
            let number = parseFloat(rawNumberStr);

            if (isNaN(number)) number = 0;
            if (hasMinusSign) number = -number;

            const min = parseFloat($el.data('min'));
            const max = parseFloat($el.data('max'));
            if (!isNaN(min) && number < min) number = min;
            if (!isNaN(max) && number > max) number = max;
            
            // Dùng toFixed để thêm số 0
            let parts = Math.abs(number).toFixed(decimals).split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, sepThousands);
            formattedValue = parts.join(decimals > 0 ? sepDecimal : '');

            if (number < 0) {
                formattedValue = '-' + formattedValue;
            }

            if (type === 'money' && currency) {
                formattedValue += ' ' + currency;
            }
        } else {
            // --- LOGIC KHI INPUT: ĐỊNH DẠNG NHẸ NHÀNG ---
            const [integerPart, decimalPart] = cleanValue.split(sepDecimal);
            
            let formattedInteger = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, sepThousands);
            if(formattedInteger === "" && (decimalPart !== undefined || cleanValue.endsWith(sepDecimal))) {
                formattedInteger = "0"; // Nếu người dùng nhập ",5" -> "0,5"
            }

            if (decimalPart !== undefined) {
                const truncatedDecimal = decimalPart.substring(0, decimals);
                formattedValue = formattedInteger + sepDecimal + truncatedDecimal;
            } else {
                formattedValue = formattedInteger;
            }
            
            if (hasMinusSign) {
                formattedValue = '-' + formattedValue;
            }
            // Trường hợp người dùng chỉ mới gõ dấu "-"
            if(originalValue === "-"){
                 formattedValue = "-";
            }
        }
        
        // --- BƯỚC 3: CẬP NHẬT GIÁ TRỊ VÀ VỊ TRÍ CON TRỎ ---
        if (originalValue !== formattedValue) {
            // Đếm số dấu phân cách hàng ngàn trước và sau khi format
            const separatorsBefore = (originalValue.substring(0, caretStart).match(new RegExp(`\\${sepThousands}`, 'g')) || []).length;
            const separatorsAfter = (formattedValue.substring(0, caretStart).match(new RegExp(`\\${sepThousands}`, 'g')) || []).length;
            const separatorDiff = separatorsAfter - separatorsBefore;

            const lengthDiff = formattedValue.length - originalValue.length;
            let newCaretPos = caretStart + lengthDiff;
            
            // Nếu độ dài không đổi nhưng số dấu phân cách thay đổi (ví dụ xóa số), cần điều chỉnh
            if(lengthDiff === 0 && separatorDiff !== 0) {
                 newCaretPos += separatorDiff;
            }

            $el.val(formattedValue);
            setTimeout(() => el.setSelectionRange(newCaretPos, newCaretPos), 0);
        }
    }
    function step(){
      $('[data-step="true"]').each(function () {
        const $container = $(this);
        const activeClass = $container.attr('data-step-active').replace('.', '');
        const $steps = $container.find('[data-step-index]');
        function updateStepDisplay() {
          $steps.each(function () {
            const $step = $(this);
            if ($step.hasClass(activeClass)) {
              $step.css({ display: 'block' });
              if ($step.is('[data-step-animate]')) {
                const animateClass = $step.attr('data-step-animate');
                $step.addClass(animateClass);
              }
            } else {
              $step.css({ display: 'none' });
              if ($step.is('[data-step-animate]')) {
                const animateClass = $step.attr('data-step-animate');
                $step.removeClass(animateClass);
              }
            }
          });
        }
        function showStep(index) {
          $steps.removeClass(activeClass);
          const $target = $steps.filter('[data-step-index="' + index + '"]');
          $target.addClass(activeClass);
          updateStepDisplay();
        }
        if ($steps.length > 0) {
          if (!$steps.hasClass(activeClass)) {
            $steps.first().addClass(activeClass);
          }
          updateStepDisplay();
        }
        $container.on('click', '[data-step-next]', function () {
          const $current = $steps.filter('.' + activeClass);
          const currentIndex = parseInt($current.attr('data-step-index'));
          const nextIndex = currentIndex + 1;
          if ($steps.filter('[data-step-index="' + nextIndex + '"]').length) {
            showStep(nextIndex);
          }
        });
        $container.on('click', '[data-step-prev]', function () {
          const $current = $steps.filter('.' + activeClass);
          const currentIndex = parseInt($current.attr('data-step-index'));
          const prevIndex = currentIndex - 1;
          if ($steps.filter('[data-step-index="' + prevIndex + '"]').length) {
            showStep(prevIndex);
          }
        });
      });
    }
    function swiper(){
        function parseValue(value) {
          if (value === 'true') return true;
          if (value === 'false') return false;
          if (!isNaN(value) && value.trim() !== '') return Number(value);
          try {
            return JSON.parse(value);
          } catch (e) {
            return value;
          }
        }
        function setDeep(obj, path, value) {
          const keys = path.split('.');
          let current = obj;
          keys.forEach((key, index) => {
            if (index === keys.length - 1) {
              current[key] = value;
            } else {
              current[key] = current[key] || {};
              current = current[key];
            }
          });
        }
        document.querySelectorAll('[data-swiper]').forEach(el => {
          const options = {};
          Object.entries(el.dataset).forEach(([key, value]) => {
            if (key === 'swiper') return;
            let parsedKey = key.replace(/-./g, x => x[1].toUpperCase());
            setDeep(options, parsedKey, parseValue(value));
          });
          const paginationEl = el.querySelector('.swiper-pagination');
          if (paginationEl) {
            options.pagination = options.pagination || {};
            options.pagination.el = options.pagination.el || paginationEl;
            if (typeof options.pagination.clickable === 'undefined') {
              options.pagination.clickable = true;
            }
          }
          const nextEl = el.querySelector('.swiper-button-next');
          const prevEl = el.querySelector('.swiper-button-prev');
          if (nextEl && prevEl) {
            options.navigation = options.navigation || {};
            options.navigation.nextEl = options.navigation.nextEl || nextEl;
            options.navigation.prevEl = options.navigation.prevEl || prevEl;
          }
          if (options.autoplay === true) {
            options.autoplay = { delay: 3000, disableOnInteraction: false };
          } else if (typeof options.autoplay === 'number') {
            options.autoplay = { delay: options.autoplay, disableOnInteraction: false };
          }
          new Swiper(el, options);
        });
    }
    function selected() {
        $('[data-select]').each(function () {
          const $select = $(this);
          let dataUrl;
          let initialLoad;
          if ($select.data('action')) {
            dataUrl = $select.data('select-url') || '';
            initialLoad = $select.data('select-load') || '';
          } else {
            dataUrl = $select.data('select-url') || $select.data('url');
            initialLoad = $select.data('select-load') || $select.data('load');
          }
          const method = ($select.data('method') || 'GET').toUpperCase();
          const keywordKey = $select.data('keyword') || 'keyword';
          const placeholder = $select.data('placeholder') || 'Chọn một mục';
          const minLength = parseInt($select.data('max-length')) || 0;
          const parentSelector = $select.data('parent');
          // const initialLoad = $select.data('load') === true;
          const ajaxEnabled = !!dataUrl;
          const dropdownParentSelector = $select.data('dropdown-parent');
          const isMultiple = $select.prop('multiple');
          function initSelect($el) {
            const config = {
              placeholder: placeholder,
              allowClear: true,
              multiple: isMultiple,
              dropdownParent: dropdownParentSelector ? $(dropdownParentSelector) : $el.parent(),
              escapeMarkup: function (markup) { return markup; },
              templateResult: function (data, container) {
                if (data.element) {
                  $(container).addClass($(data.element).attr("class"));
                  const content = $(data.element).data("content");
                  if (content) {
                    return content; // HTML từ data-content
                  }
                }
                return data.text;
              },
              templateSelection: function (data) {
                if (data.element) {
                  const content = $(data.element).data("content");
                  if (content) {
                    return content; // HTML khi đã chọn
                  }
                }
                return data.text;
              },
              ajax: ajaxEnabled ? {
                url: dataUrl,
                type: method,
                dataType: 'json',
                delay: 300,
                data: function (params) {
                  const data = {};
                  data[keywordKey] = params.term || '';
                  if (parentSelector) {
                    const $parentSelect = $el.closest('tr').find(parentSelector);
                    const parentVal = $(parentSelector).val();
                    if (parentVal) {
                      data['parent'] = parentVal;
                    }
                  }
                  return data;
                },
                transport: function (params, success, failure) {
                  const term = params.data[keywordKey] || '';
                  const parentVal = parentSelector ? $(parentSelector).val() : null;
                  const shouldLoadInitial = initialLoad && parentSelector && parentVal && term === '';
                  const shouldLoadSearch = term.length >= minLength;

                  if (shouldLoadInitial || shouldLoadSearch) {
                    $.ajax(params).done(success).fail(failure);
                  } else {
                    success({ results: [] });
                  }
                },
                processResults: function (data) {
                  return {
                    results: (data || []).map(item => ({
                      id: item.id ?? item.value,
                      text: item.text ?? item.label ?? item.value,
                      content: item.content || null // giữ thêm content
                    }))
                  };
                },
                cache: true,
              } : undefined
            };
            if ($el.data('select2')) {
                $el.select2(config);
              } else {
                $el.select2(config);
              }
            if (isMultiple) {
              $el.on('select2:open', function () {
                const results = $('.select2-dropdown');
                if (results.find('.select2-actions').length === 0) {
                  results.prepend(`
                    <div class="select2-actions d-flex border-bottom p-2 mb-2">
                      <button type="button" class="btn btn-sm btn-light py-1 px-2 w-100 rounded-pill btn-select-all" style="margin-right:5px;">Chọn tất cả</button>
                      <button type="button" class="btn btn-sm btn-light py-1 px-2 w-100 rounded-pill btn-deselect-all">Bỏ tất cả</button>
                    </div>
                  `);
                  results.find('.btn-select-all').on('click', function (e) {
                    e.stopPropagation();
                    selectAllOptions($el);
                    repositionSelect2($el);
                  });

                  results.find('.btn-deselect-all').on('click', function (e) {
                    e.stopPropagation();
                    deselectAllOptions($el);
                    repositionSelect2($el);
                  });
                }
              });
            }
          }
          initSelect($select);
          if (ajaxEnabled && parentSelector) {
            const $parentSelect = $select.closest('tr').find(parentSelector);
            $parentSelect.on('change', function () {
                const newParentVal = $(this).val();
                $select.val(null).trigger('change');
                $select.prop('disabled', !newParentVal);
                if (!newParentVal) {
                    initSelect($select);
                }
            });
            if (!$parentSelect.val()) {
                $select.prop('disabled', true);
            }
        }
          function repositionSelect2($select) {
            const select2Instance = $select.data('select2');
            if (select2Instance && typeof select2Instance.dropdown._positionDropdown === 'function') {
              select2Instance.dropdown._positionDropdown();
            }
            if (select2Instance && select2Instance.dropdown && typeof select2Instance.dropdown._resizeDropdown === 'function') {
              select2Instance.trigger('query', {});
            }
          }
          function selectAllOptions($el) {
            $el.find('option').each(function () {
              if (!$(this).prop('selected')) {
                $(this).prop('selected', true);
                $el.trigger({
                  type: 'select2:select',
                  params: { data: { id: $(this).val(), text: $(this).text() } }
                });
              }
            });
            $el.trigger('change');
          }

          function deselectAllOptions($el) {
            $el.find('option').each(function () {
              if ($(this).prop('selected')) {
                $(this).prop('selected', false);
                $el.trigger({
                  type: 'select2:unselect',
                  params: { data: { id: $(this).val(), text: $(this).text() } }
                });
              }
            });
            $el.trigger('change');
          }
          if (ajaxEnabled) {
            if (parentSelector) {
              const $parent = $(parentSelector);
              $parent.on('change', function () {
                const newParentVal = $parent.val();
                $select.prop('disabled', !newParentVal);

                if (newParentVal) {
                  $select.empty().trigger('change');
                  initSelect($select);
                } else {
                    $select.empty().trigger('change');
                    initSelect($select);
                }
              });

              if (initialLoad && $parent.val()) {
                $select.prop('disabled', false);
                $select.trigger({
                  type: 'select2:open',
                  params: { data: { [keywordKey]: '' } }
                });
              } else {
                $select.prop('disabled', !$parent.val());
              }
            } else if (initialLoad) {
              $select.trigger({
                type: 'select2:open',
                params: { data: { [keywordKey]: '' } }
              });
            }
          }
        });
    }
    function handleConditionalDisplay() {
      // Loop through each select element that has the data-conditional attribute
      $('[data-conditional]').each(function() {
        const $select = $(this);
        const targetSelector = $select.data('conditional');

        // Hide all target elements initially to prevent flicker or incorrect display
        $(targetSelector).hide();
        
        // Immediately check the current value of the select box and show the corresponding element
        const currentValue = $select.val();
        if (currentValue) {
          $(`${targetSelector}[data-show-on="${currentValue}"]`).show();
        }
        
        // Listen for changes on the select element to dynamically update the display
        $select.on('change', function() {
          const selectedValue = $(this).val();
          
          // Hide all related elements first
          $(targetSelector).hide();
          
          // Then, show only the element that matches the newly selected value
          if (selectedValue) {
            $(`${targetSelector}[data-show-on="${selectedValue}"]`).show();
          }
        });
      });
    }
    function editor() {
      $('[data-editor]').each(function () {
        var el = this;
        if (!$(el).attr('data-editor-initialized')) {
          new RichTextEditor(el, {
            width: $(el).width() + 'px',
            height: $(el).height() + 'px'
          });
          $(el).attr('data-editor-initialized', 'true');
        }
      });
    }
    function Countdown() {
        $('[data-countdown]').each(function () {
            const $el = $(this);
            if ($el.data('countdown-started')) return;
            $el.data('countdown-started', true);
            const startStr = $el.data('countdown-start');
            const endStr = $el.data('countdown-end');
            const color = $el.data('countdown-color');
            const timeDownThreshold = parseInt($el.data('countdown-timedown'), 10);
            const timeDownColor = $el.data('countdown-timedown-color');
            const timeUpThreshold = parseInt($el.data('countdown-timeup'), 10);
            const timeUpColor = $el.data('countdown-timeup-color');
            if (!startStr || !endStr) return;
            const startTime = new Date(startStr.replace(/-/g, '/')).getTime();
            const endTime = new Date(endStr.replace(/-/g, '/')).getTime();
            if (isNaN(startTime) || isNaN(endTime)) {
                $el.text('Invalid time format');
                return;
            }
            $el.css('color', color);
            function applyColorConditionally(distance, isPast) {
                $el.css('color', color);
                if (timeDownColor?.startsWith('.')) $el.removeClass(timeDownColor.slice(1));
                if (timeUpColor?.startsWith('.')) $el.removeClass(timeUpColor.slice(1));

                const seconds = Math.floor(distance / 1000);

                if (!isPast && timeDownThreshold && seconds <= timeDownThreshold) {
                    if (timeDownColor?.startsWith('.')) {
                        $el.addClass(timeDownColor.slice(1));
                    } else {
                        $el.css('color', timeDownColor);
                    }
                } else if (isPast && timeUpThreshold && seconds >= timeUpThreshold) {
                    if (timeUpColor?.startsWith('.')) {
                        $el.addClass(timeUpColor.slice(1));
                    } else {
                        $el.css('color', timeUpColor);
                    }
                }
            }
            function update() {
                const now = new Date().getTime();
                let distance, prefix, isPast = false;

                if (now < endTime) {
                    distance = endTime - now;
                    prefix = '- ';
                } else {
                    distance = now - endTime;
                    prefix = '+ ';
                    isPast = true;
                }
                applyColorConditionally(distance, isPast);
                const months = Math.floor(distance / (1000 * 60 * 60 * 24 * 30));
                const days = Math.floor((distance % (1000 * 60 * 60 * 24 * 30)) / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                let display = prefix;
                if (months > 0) display += `${months} tháng `;
                if (days > 0 || months > 0) display += `${days} ngày `;

                if (hours > 0 || minutes > 0) {
                    display += `${hours.toString().padStart(2, '0')}:` +
                                `${minutes.toString().padStart(2, '0')}:` +
                                `${seconds.toString().padStart(2, '0')}`;
                } else if (seconds > 0 || distance === 0) {
                    display += `00:00:${seconds.toString().padStart(2, '0')}`;
                } else {
                    display = '';
                }
                $el.text(display.trim());
            }
            update();
            setInterval(update, 1000);
        });
    }
    function chartjs(){
        window.chartInstances = window.chartInstances || {};
        $('[data-chart]').each(function () {
            const $canvas = $(this);
            const canvasEl = this; // HTMLCanvasElement
            const ctx = canvasEl.getContext && canvasEl.getContext('2d');

            // Lấy tên chart (raw). Nếu attribute tồn tại nhưng rỗng -> coi là "unnamed"
            const rawChartName = $canvas.attr('data-chart'); 
            const chartName = rawChartName ? String(rawChartName).trim() : '';

            const type = $canvas.data('type') || 'bar';
            const width = $canvas.data('width');
            const height = $canvas.data('height');
            if (width) $canvas.attr('width', width);
            if (height) $canvas.attr('height', height);

            // --- Labels ---
            let labels = [];
            const rawLabels = $canvas.attr('data-labels');
            if (rawLabels && rawLabels.trim() !== '') {
                try { labels = JSON.parse(rawLabels); } catch (e) { labels = []; }
            }

            // --- Datasets ---
            let datasets = [];
            const rawDatasets = $canvas.attr('data-datasets');
            const rawDataset = $canvas.attr('data-dataset');
            const datasetName = $canvas.data('name') || '';
            let colors = $canvas.data('colors') || $canvas.data('color');
            if (typeof colors === 'string') {
                try { colors = JSON.parse(colors); } catch {}
            }

            if (rawDatasets && rawDatasets.trim() !== '') {
                try {
                    const parsedData = JSON.parse(rawDatasets);
                    if (Array.isArray(parsedData)) {
                        datasets = parsedData.map(ds => ({ type: ds.type || type, ...ds }));
                    }
                } catch (e) { datasets = []; }
            } else if (rawDataset && rawDataset.trim() !== '') {
                try {
                    const data = JSON.parse(rawDataset);
                    datasets = [{
                        type: type,
                        label: datasetName,
                        data: data,
                        backgroundColor: colors || 'rgba(75, 192, 192, 0.2)',
                        borderColor: type === 'line' ? (colors || 'rgba(75, 192, 192, 1)') : undefined,
                        borderWidth: 1,
                        fill: type === 'line' ? false : true,
                        tension: type === 'line' ? 0.4 : undefined
                    }];
                } catch (e) { datasets = []; }
            }

            const isCircular = ['doughnut', 'pie'].includes(type);
            const gridXDisplay = $canvas.data('grid-x') !== false;
            const gridYDisplay = $canvas.data('grid-y') !== false;
            const gridColor = $canvas.data('grid-color') || 'rgba(0,0,0,0.1)';
            const gridLineWidth = $canvas.data('grid-linewidth') || 1;

            if (datasets.length > 0 && ctx) {
                // 1) Nếu chart có tên và đã lưu global -> destroy và xóa
                if (chartName && window.chartInstances[chartName]) {
                    try { window.chartInstances[chartName].destroy(); } catch (e) {}
                    delete window.chartInstances[chartName];
                }

                // 2) Nếu canvas có instance lưu trên DOM (unnamed) -> destroy
                const stored = $canvas.data('chartInstance');
                if (stored && typeof stored.destroy === 'function') {
                    try { stored.destroy(); } catch (e) {}
                    $canvas.removeData('chartInstance');
                }

                // 3) Kiểm tra Chart.getChart(canvasEl) (Chart.js v3+)
                const existingChart = (typeof Chart !== 'undefined' && Chart.getChart) ? Chart.getChart(canvasEl) : null;
                if (existingChart && typeof existingChart.destroy === 'function') {
                    try { existingChart.destroy(); } catch (e) {}
                    // nếu nó cũng được lưu trong window.chartInstances, xóa reference
                    for (const k in window.chartInstances) {
                        if (window.chartInstances[k] === existingChart) {
                            delete window.chartInstances[k];
                            break;
                        }
                    }
                }

                // Tạo chart mới
                const chartInstance = new Chart(canvasEl, {
                    type: type,
                    data: { labels: labels, datasets: datasets },
                    options: {
                        responsive: !width && !height,
                        maintainAspectRatio: !width && !height,
                        plugins: {
                            legend: { display: true, position: isCircular ? 'bottom' : 'top' },
                            title: { display: !!chartName, text: chartName }
                        },
                        scales: isCircular ? {} : {
                            x: { grid: { display: gridXDisplay, color: gridColor, lineWidth: gridLineWidth } },
                            y: { beginAtZero: true, grid: { display: gridYDisplay, color: gridColor, lineWidth: gridLineWidth } }
                        }
                    }
                });

                // Lưu instance: nếu có tên -> global store, không thì lưu trên DOM (unnamed)
                if (chartName) {
                    window.chartInstances[chartName] = chartInstance;
                } else {
                    $canvas.data('chartInstance', chartInstance);
                }
            } // end if datasets
        });
      // data-chart="..."  Tên biểu đồ (hiển thị title)
      // data-type="..." Loại biểu đồ (line, bar)
      // data-labels Nhãn trục X
      // data-dataset  Dữ liệu đơn, dùng kèm data-name
      // data-datasets Dữ liệu nhiều datasets
    }
    function datapicker(){
        $('.daterangepicker').remove();
        $('[data-datepicker]').each(function () {
            const $el = $(this);
            if ($el.data('daterangepicker')) {
                return;
            }
            const dateFormat = $el.data('format') || "DD/MM/YYYY";
            $el.daterangepicker({
                showDropdowns: true,
                showWeekNumbers: true,
                showISOWeekNumbers: true,
                autoApply: true,
                locale: {
                    format: dateFormat,
                    applyLabel: "Áp dụng",
                    cancelLabel: "Hủy",
                    fromLabel: "Từ",
                    toLabel: "Đến",
                    customRangeLabel: "Tùy chọn",
                    weekLabel: "Tu",
                    daysOfWeek: ["CN", "T2", "T3", "T4", "T5", "T6", "T7"],
                    monthNames: [
                        "Tháng 1", "Tháng 2", "Tháng 3", "Tháng 4", "Tháng 5", "Tháng 6",
                        "Tháng 7", "Tháng 8", "Tháng 9", "Tháng 10", "Tháng 11", "Tháng 12"
                    ],
                    firstDay: 1
                },
                ranges: {
                    'Hôm nay': [moment(), moment()],
                    'Hôm qua': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    '7 Ngày qua': [moment().subtract(6, 'days'), moment()],
                    '30 Ngày qua': [moment().subtract(29, 'days'), moment()],
                    'Trong tháng': [moment().startOf('month'), moment().endOf('month')],
                    'Tháng trước': [
                        moment().subtract(1, 'month').startOf('month'),
                        moment().subtract(1, 'month').endOf('month')
                    ]
                },
                opens: "left",
                drops: "auto"
            });
        });
    }
    function initSearchBoxes() {
      $('[data-search="true"]').each(function () {
        const container = $(this);
        if (container.data('init-search')) return;
        const input = container.find('input');
        const resultBox = container.find('.search-results-box');
        const url = container.data('url');
        const template = container.find('.search-item-template')[0];
        const minLength = container.data('minlength') || 2;
        const highlightColor = container.data('search-color') || 'rgb(0 0 0 / 10%)';
        container.css('position', 'relative');
        container.data('init-search', true);
        let debounceTimer;
        let lastQuery = '';
        let lastFetchedQuery = '';
        let lastResults = null;
        function renderResults(data) {
          resultBox.empty();
          if (Array.isArray(data) && data.length > 0) {
            data.forEach(item => {
              const clone = $(template.content.cloneNode(true));
              clone.find('[data-name]').each(function () {
                const field = $(this).data('name');
                if (item[field] !== undefined) {
                  if (this.tagName === 'IMG') {
                    $(this).attr('src', item[field]);
                  } else {
                    $(this).text(item[field]);
                  }
                } else {
                  if (this.tagName === 'IMG') {
                    $(this).attr('src', '');
                  } else {
                    $(this).text('');
                  }
                }
              });
              clone.find('*').each(function () {
                const el = $(this);
                $.each(this.attributes, function () {
                  const attrName = this.name;
                  const attrValue = this.value;
                  if (attrName.startsWith('data-attr-')) {
                    const realAttr = 'data-' + attrName.slice('data-attr-'.length);
                    const field = attrValue;
                    if (item[field] !== undefined) {
                      el.attr(realAttr, item[field]);
                    } else {
                      el.attr(realAttr, '');
                    }
                    el.removeAttr(attrName);
                  }
                });
              });
              clone.addClass('search-item');
              resultBox.append(clone);
            });
            resultBox.show();
          } else {
            resultBox.html('<div class="search-item p-4 text-center"><img src="/assets/img/no-data.svg" class="w-25"> <strong class="d-block">Không có kết quả</strong></div>').show();
          }
        }
        function performSearch(query) {
          if (query === lastFetchedQuery) return;
          lastFetchedQuery = query;
          resultBox.html('<div class="search-item p-4 text-center"><strong>Đang tìm kiếm...</strong></div>').show();
          $.ajax({
            url: url,
            method: 'POST',
            dataType: 'json',
            data: { search: query },
            success: function (data) {
              lastResults = data;  // lưu kết quả
              renderResults(data);
            },
            error: function () {
              resultBox.html('<div class="search-item p-4 text-center"><img src="/assets/img/no-data.svg" class="w-25"> <strong class="d-block">Lỗi khi tải dữ liệu</strong></div>').show();
              lastResults = null; // reset kết quả
            }
          });
        }
        function highlightItem(index) {
          const items = resultBox.find('.search-item');
          if (!items.length) return;
          items.css('background', ''); 
          if (index >= 0 && index < items.length) {
            const item = items.eq(index);
            item.css('background', highlightColor);
            const containerTop = resultBox.scrollTop();
            const containerBottom = containerTop + resultBox.innerHeight();
            const itemTop = item.position().top + containerTop;
            const itemBottom = itemTop + item.outerHeight();
            if (itemBottom > containerBottom) {
              resultBox.scrollTop(itemBottom - resultBox.innerHeight());
            } else if (itemTop < containerTop) {
              resultBox.scrollTop(itemTop);
            }
          }
          container.data('highlight-index', index);
        }
        function getHighlightedIndex() {
          const idx = container.data('highlight-index');
          return typeof idx === 'number' ? idx : -1;
        }
        input.on('keyup', function () {
          const query = $(this).val().trim();
          lastQuery = query;
          clearTimeout(debounceTimer);

          debounceTimer = setTimeout(() => {
            if (query.length < minLength) {
              resultBox.hide();
              lastResults = null;
              return;
            }
            performSearch(query);
          }, 300);
        });
        input.on('focus', function () {
          const query = $(this).val().trim();
          if (query.length >= minLength) {
            if (lastResults && lastFetchedQuery === query) {
              renderResults(lastResults);
            } else {
              performSearch(query);
            }
          }
        });
        input.on('keydown', function (e) {
          const items = resultBox.find('.search-item');
          if (!items.length) return;
          let currentIndex = getHighlightedIndex();
          if (e.key === 'ArrowDown') {
            e.preventDefault();
            currentIndex++;
            if (currentIndex >= items.length) currentIndex = 0;
            highlightItem(currentIndex);
          } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            currentIndex--;
            if (currentIndex < 0) currentIndex = items.length - 1;
            highlightItem(currentIndex);
          } else if (e.key === 'Enter') {
            e.preventDefault();
            if (currentIndex === -1 && items.length > 0) {
              const firstLink = items.eq(0).find('a').first();
              if (firstLink.length) {
                firstLink[0].click();
              } else {
                items.eq(0)[0].click();
              }
            } else if (currentIndex >= 0 && currentIndex < items.length) {
              const link = items.eq(currentIndex).find('a').first();
              if (link.length) {
                link[0].click();
              } else {
                items.eq(currentIndex)[0].click();
              }
            }
          }
        });
        $(document).on('click', function (e) {
          if (!container.is(e.target) && container.has(e.target).length === 0) {
            resultBox.hide();
          }
        });
      });
    }
    function upload(){
        let dropArea = $("#drop-area");
        let fileInput = $("#file-input");
        let uploadBtn = $('[data-action="upload"]');
        let url = uploadBtn.attr("data-url");
        let fileListContainer = $("#file-list");
        let selectedFiles = [];
        let uploadedFiles = new Set();
        dropArea.on("dragover", function (e) {
            e.preventDefault();
            $(this).addClass("bg-light");
        });
        dropArea.on("dragleave", function (e) {
            e.preventDefault();
            $(this).removeClass("bg-light");
        });
        dropArea.on("drop", async function (e) {
            e.preventDefault();
            $(this).removeClass("bg-light");
            let items = e.originalEvent.dataTransfer.items;
            let files = e.originalEvent.dataTransfer.files;
            if (items && items.length > 0) {
                let hasFolder = false;
                let promises = [];
                for (let item of items) {
                    let entry = item.webkitGetAsEntry();
                    if (entry) {
                        if (entry.isDirectory) hasFolder = true;
                        promises.push(traverseFileTree(entry, ""));
                    }
                }
                await Promise.all(promises);
                if (!hasFolder && files.length > 0) {
                    handleFiles(files);
                }
            } else if (files.length > 0) {
                handleFiles(files);
            }
        });
        dropArea.on("click", function () {
            fileInput.click();
        });
        fileInput.on("change", function () {
            handleFiles(this.files);
        });
        async function processDataTransferItems(items) {
            for (let item of items) {
                let entry = item.webkitGetAsEntry();
                if (entry) {
                    await traverseFileTree(entry, "");
                }
            }
            if (selectedFiles.length > 0) {
                uploadBtn.show();
            }
        }
        async function traverseFileTree(item, path) {
            return new Promise((resolve) => {
                if (item.isFile) {
                    item.file((file) => {
                        let relativePath = path + file.name;
                        if (!uploadedFiles.has(relativePath)) { // Kiểm tra file đã upload chưa
                            selectedFiles.push({ file, relativePath });
                            displayFile(file, relativePath);
                        }
                        resolve();
                    });
                } else if (item.isDirectory) {
                    let dirReader = item.createReader();
                    let newPath = path + item.name + "/";
                    dirReader.readEntries(async (entries) => {
                        if (entries.length === 0) {
                            selectedFiles.push({ file: null, relativePath: newPath }); // Đánh dấu thư mục rỗng
                            displayFile(null, newPath);
                        }
                        for (let entry of entries) {
                            await traverseFileTree(entry, newPath);
                        }
                        resolve();
                    });
                } else {
                    resolve();
                }
            });
        }
        function handleFiles(files) {
            Array.from(files).forEach(file => {
                if (!uploadedFiles.has(file.name) && !selectedFiles.some(f => f.file?.name === file.name)) {
                    selectedFiles.push({ file, relativePath: file.name });
                    displayFile(file, file.name);
                }
            });

            if (selectedFiles.length > 0) {
                uploadBtn.show();
            }
        }
        function getFileIcon(file) {
            let fileType = file.type.toLowerCase();
            let fileName = file.name.toLowerCase();

            if (fileType.startsWith("image/")) return URL.createObjectURL(file);
            if (fileType === "application/pdf") return "/assets/icons/pdf.png";
            if (fileType.includes("text")) return "/assets/icons/files.png";
            if (fileType.includes("rar")) return "/assets/icons/rar.png";
            if (fileType.includes("zip")) return "/assets/icons/zip.png";
            if (fileType.includes("audio/")) return "/assets/icons/audio.png";

            // Kiểm tra tất cả định dạng PowerPoint
            if (
                fileName.endsWith(".ppt") ||
                fileName.endsWith(".pptx") ||
                fileName.endsWith(".pps") ||
                fileName.endsWith(".ppsx")
            ) {
                return "/assets/icons/ppt.png";
            }

            // Kiểm tra tất cả định dạng Word
            if (
                fileName.endsWith(".doc") ||
                fileName.endsWith(".docx") ||
                fileName.endsWith(".dot") ||
                fileName.endsWith(".dotx") ||
                fileName.endsWith(".rtf")
            ) {
                return "/assets/icons/doc.png";
            }

            // Kiểm tra tất cả định dạng Excel
            if (
                fileName.endsWith(".xls") ||
                fileName.endsWith(".xlsx") ||
                fileName.endsWith(".xlsm") ||
                fileName.endsWith(".csv")
            ) {
                return "/assets/icons/xls.png";
            }

            // Mặc định là files.png nếu không thuộc các loại trên
            return "/assets/icons/files.png";
        }
        function displayFile(file, displayPath) {
            let fileItem = $("<div>").addClass("file-item border position-relative p-2 rounded-4 w-100 mb-2");

            let fileHtml = `<div class="d-flex justify-content-between align-items-center position-relative z-2">
                <div class="d-flex align-items-center w-75 col-12 text-truncate">
                    ${file ? `<img src="${getFileIcon(file)}" class="width me-2" style="--width:30px;">` : '<i class="ti ti-folder"></i>'}
                    <div class="col-12 text-truncate"><span>${displayPath}</span><span class="text-danger small file-error d-block"></span></div>
                </div>
                <div class="file-action">
                    <button class="removeItem btn p-0 border-0"><i class="ti ti-trash fs-4 text-danger"></i></button>
                </div>
            </div>`;

            fileItem.append(fileHtml);
            fileListContainer.append(fileItem);

            fileItem.find(".removeItem").on("click", function (e) {
                e.stopPropagation();
                selectedFiles = selectedFiles.filter(f => f.relativePath !== displayPath);
                fileItem.remove();
                if (selectedFiles.length === 0) {
                    uploadBtn.hide();
                }
            });
        }
        uploadBtn.on("click", function () {
            if (selectedFiles.length === 0) return;
            uploadBtn.prop("disabled", true);
            let newFilesToUpload = selectedFiles.filter(f => !uploadedFiles.has(f.relativePath));

            if (newFilesToUpload.length === 0) {
                uploadBtn.prop("disabled", false);
                return;
            }
            uploadFiles(selectedFiles.indexOf(newFilesToUpload[0]));
        });
        function uploadFiles(index) {
            if (index >= selectedFiles.length) {
                uploadBtn.prop("disabled", false);
                let load = uploadBtn.attr("data-load");
                pjaxConfig(uploadBtn);
                pjax.loadUrl(load === 'this' ? '' : load);
                return;
            }
            let { file, relativePath } = selectedFiles[index];
            if (uploadedFiles.has(relativePath)) {
                uploadFiles(index + 1);
                return;
            }
            let formData = new FormData();
            formData.append("path", relativePath); 
            if (file) {
                formData.append("file", file);
            }
            let progressBar = $("<div>").addClass("progress position-absolute bg-body top-0 start-0 w-100 h-100 rounded-4")
                .append($("<div>").addClass("progress-bar bg-primary bg-opacity-10 progress-bar-striped progress-bar-animated"));
            fileListContainer.children().eq(index).append(progressBar);
            progressBar.show();
            fileListContainer.children().eq(index).find(".removeItem").hide();
            $.ajax({
                url: url,
                type: "POST",
                data: formData,
                contentType: false,
                processData: false,
                xhr: function () {
                    let xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener("progress", function (e) {
                        if (e.lengthComputable) {
                            let percent = Math.round((e.loaded / e.total) * 100);
                            progressBar.children(".progress-bar").css("width", percent + "%");
                            let progressText = fileListContainer.children().eq(index).find(".file-action .progress-text");
                            if (progressText.length) {
                                progressText.text(percent + "%");
                            } else {
                                fileListContainer.children().eq(index).find(".file-action").append('<span class="fs-6 fw-bold text-primary progress-text">' + percent + '%</span>');
                            }
                        }
                    }, false);
                    return xhr;
                },

                success: function (response) {
                    if (response.status === 'success') {
                        progressBar.children(".progress-bar").removeClass("bg-primary").addClass("bg-success");
                        uploadedFiles.add(relativePath);
                        fileListContainer.children().eq(index).find(".removeItem").remove();
                        fileListContainer.children().eq(index).find(".file-action .progress-text").remove();
                        fileListContainer.children().eq(index).find(".file-action").append('<i class="ti ti-circle-check fs-2 text-success"></i>');
                    }
                    else {
                        progressBar.children(".progress-bar").removeClass("bg-primary").addClass("bg-danger");
                        fileListContainer.children().eq(index).find(".removeItem").show();
                    fileListContainer.children().eq(index).find(".file-error").text(response.content);
                        fileListContainer.children().eq(index).find(".file-action .progress-text").remove();
                        fileListContainer.children().eq(index).find(".file-action .removeItem").html('<i class="ti ti-xbox-x fs-2 text-danger"></i>');
                    }
                    uploadFiles(index + 1);
                    
                },
                error: function () {
                    progressBar.children(".progress-bar").css("width", "100%");
                    progressBar.children(".progress-bar").removeClass("bg-primary").addClass("bg-danger");
                    fileListContainer.children().eq(index).find(".removeItem").show();
                    fileListContainer.children().eq(index).find(".file-error").text('Error Connection');
                    fileListContainer.children().eq(index).find(".file-action .progress-text").remove();
                    fileListContainer.children().eq(index).find(".file-action .removeItem").html('<i class="ti ti-xbox-x fs-2 text-danger"></i>');
                    uploadFiles(index + 1);
                }
            });
        }
    }
    function uploadImages() {
        $(".upload-file").off("change").on("change", function (event) {
            handleFileUpload(event, $(this));
        });
        $("#remove-upload-file").off("click").on("click", function () {
            resetFileInput($(this));
        });
        function handleFileUpload(event, $input) {
            let files = event.type === "drop" ? event.originalEvent.dataTransfer.files : event.target.files;
            if (!files || files.length === 0) return;

            let file = files[0];
            let reader = new FileReader();
            let $uploadBox = $input.parents(".upload-files");
            let $showFile = $uploadBox.find(".show-file");
            $uploadBox.addClass("bg-light");
            $input.prop("disabled", false);
            $uploadBox.find(".input-file").hide();
            $uploadBox.find(".upload-progress").show();
            $showFile.show().find(".progress-bar").css("width", "0%").show();
            reader.onloadend = function () {
                let base64String = reader.result;
                $showFile.find(".images-container").attr("src", base64String);
                $showFile.find(".audio-container").attr("data-audio", base64String);
                let router = $input.attr("data-router");
                if (router) {
                    let formData = new FormData();
                    formData.append("file", file);
                    $.ajax({
                        type: "POST",
                        url: router,
                        data: formData,
                        cache: false,
                        contentType: false,
                        processData: false,
                        xhr: function () {
                            let xhr = new XMLHttpRequest();
                            xhr.upload.addEventListener("progress", function (evt) {
                                if (evt.lengthComputable) {
                                    let percentComplete = (evt.loaded / evt.total) * 100;
                                    $showFile.find(".progress-bar").css("width", percentComplete + "%");
                                }
                            });
                            return xhr;
                        },
                        success: function (response) {
                            topbar.hide();
                            if (response.status === "error") {
                                swal_error(response.content);
                                $input.prop("disabled", false);
                            } else if (response.status === "success") {
                                let fileUrl = "/" + response.url;
                                $showFile.find(".audio-container").attr("data-audio", fileUrl);
                                $showFile.find(".value-file").val(response.url);
                                $uploadBox.find(".upload-progress").hide();
                                $input.val("");
                            }
                        },
                        error: function (xhr, ajaxOptions, thrownError) {
                            console.error("Upload error:", thrownError);
                        },
                        complete: function () {
                            $uploadBox.removeClass("bg-light");
                        },
                    });
                }
            };
            reader.onerror = function () {
                console.error("FileReader Error");
                topbar.hide();
                swal_error("Failed to read file. Please try again.");
                $uploadBox.removeClass("bg-light");
            };
            reader.readAsDataURL(file);
        }
        function resetFileInput($button) {
            let $uploadBox = $button.parents(".upload-files");
            let $showFile = $uploadBox.find(".show-file");

            $uploadBox.find(".input-file").show();
            $showFile.hide().find(".images-container, .audio-container").attr({ src: "", "data-audio": "" });
            $showFile.find(".value-file").val("");
            $uploadBox.removeClass("bg-light");
        }
        $(".upload-files").on("dragover dragleave drop", function (event) {
            event.preventDefault();
            event.stopPropagation();
            if (event.type === "dragover") {
                $(this).addClass("bg-light");
            } else if (event.type === "dragleave") {
                $(this).removeClass("bg-light");
            } else if (event.type === "drop") {
                let $input = $(this).find(".upload-file");
                handleFileUpload(event, $input);
            }
        });
    }
    function uploadImagesMulti() {
        $(".upload-file-multi").off("change").on("change", function (event) {
            handleFileUploadMulti(event, $(this));
        });
        $(document).off("click", '.remove-upload-file-multi').on("click", '.remove-upload-file-multi', function () {
            let imageContainer = $(this).parents(".images-container");
            imageContainer.hide().find("img").attr("src",'');
            imageContainer.find(".value-file").val("");
        });
        function handleFileUploadMulti(event, $input) {
            let files = event.type === "drop" ? event.originalEvent.dataTransfer.files : event.target.files;
            if (!files || files.length === 0) return;

            // let file = files[0];
            let $uploadBox = $input.parents(".upload-files-multi");
            let $showFile = $uploadBox.find(".show-file");
            $uploadBox.addClass("bg-light");
            $input.prop("disabled", false);
            // $uploadBox.find(".input-file").hide();
            $showFile.show().find(".progress-bar").css("width", "0%").show();
            Array.from(files).forEach((file) => {
              let reader = new FileReader();
              reader.onloadend = function () {
                  let base64String = reader.result;
                  let $imgTemplate = $showFile.find(".images-container").last().clone().show();
                  $imgTemplate.find("img").attr("src", base64String);
                  $showFile.append($imgTemplate);
                  let router = $input.attr("data-router");
                  if (router) {
                      let formData = new FormData();
                      formData.append("file", file);
                      $.ajax({
                          type: "POST",
                          url: router,
                          data: formData,
                          cache: false,
                          contentType: false,
                          processData: false,
                          xhr: function () {
                              let xhr = new XMLHttpRequest();
                              xhr.upload.addEventListener("progress", function (evt) {
                                  if (evt.lengthComputable) {
                                      let percentComplete = (evt.loaded / evt.total) * 100;
                                      $imgTemplate.find(".progress-bar").css("width", percentComplete + "%");
                                  }
                              });
                              return xhr;
                          },
                          success: function (response) {
                              topbar.hide();
                              if (response.status === "error") {
                                  swal_error(response.content);
                                  $input.prop("disabled", false);
                              } else if (response.status === "success") {
                                  let fileUrl = "/" + response.url;
                                  $imgTemplate.find(".value-file").val(response.url);
                                  $imgTemplate.find(".upload-progress").hide();
                                  $input.val("");
                              }
                          },
                          error: function (xhr, ajaxOptions, thrownError) {
                              console.error("Upload error:", thrownError);
                          },
                          complete: function () {
                              $uploadBox.removeClass("bg-light");
                          },
                      });
                  }
              };
              reader.onerror = function () {
                  console.error("FileReader Error");
                  topbar.hide();
                  swal_error("Failed to read file. Please try again.");
                  $uploadBox.removeClass("bg-light");
              };
              reader.readAsDataURL(file);
            });
        }
        $(".upload-files-multi").on("dragover dragleave drop", function (event) {
            event.preventDefault();
            event.stopPropagation();
            if (event.type === "dragover") {
                $(this).addClass("bg-light");
            } else if (event.type === "dragleave") {
                $(this).removeClass("bg-light");
            } else if (event.type === "drop") {
                let $input = $(this).find(".upload-file-multi");
                handleFileUploadMulti(event, $input);
            }
        });
    }
    function DomDataAction($scope = $(document)) {
        $scope.find('[data-action="load"]').each(function () {
            handleLoad($(this));
        });
    }
    function dataAction() {
        $(document).off('change', '[data-action="change-load"], [data-action="click-load"]').on('change click', '[data-action="change-load"], [data-action="click-load"]', function (e) {
            const $trigger = $(this);
            const action = $trigger.data('action');
            const triggerType = action === 'change-load' ? 'change' : 'click';
            if (e.type !== triggerType) return;
            handleLoad($trigger);
        });

        $(document).off('click', '[data-action="clone"]').on('click', '[data-action="clone"]', function () {
            const $trigger = $(this);
            const $target = $($trigger.data('target'));
            const $template = $target.find($trigger.data('clone')).last();
            if ($template.length === 0) {
                console.warn('Không tìm thấy phần tử để clone');
                return;
            }
            $template.find('select[data-select]').select2('destroy');
            const $newRow = $template.clone();
            $newRow.find('input, select, textarea').each(function () {
                const $input = $(this);
                if (!$input.attr('data-keep')) {
                    $input.val('');
                }
                const name = $input.attr('name');
                if (name && name.includes('[')) {
                    const newName = name.replace(/\[[^\]]*\]/, '[]');
                    $input.attr('name', newName);
                }
            });
            $target.append($newRow);
            selected();
        });

        $(document).off('click', '[data-action="deleted-clone"]').on('click', '[data-action="deleted-clone"]', function () {
            const $btn = $(this);
            const targetSelector = $btn.data('target');
            const removeType = $btn.data('remove') || 'remove';
            const valueTarget = $btn.data('value');
            const closestSelector = $btn.data('closest') || '.row';
            let $target;
            if (targetSelector === 'this') {
                $target = $btn.closest(closestSelector);
            } else {
                $target = $(targetSelector).first();
            }
            if ($target.length === 0) {
                console.warn('Không tìm thấy phần tử để xóa/ẩn');
                return;
            }
            if (removeType === 'remove') {
                $target.remove();
            } else if (removeType === 'hidden') {
                $target.hide();
                if (valueTarget) {
                    const value = $(valueTarget).val() || $(valueTarget).text();
                    $(valueTarget).val(value + ' (đã ẩn)');
                }
            }
        });

        $(document).off('click', '[data-action="copy"]').on('click', '[data-action="copy"]', function () {
            let $target = $(this).data('target');
            let $attr = $(this).data('attr');
            let $copyElement = $($target);
            let textToCopy = '';
            if ($attr) {
                textToCopy = $copyElement.attr($attr) || '';
            } else {
                textToCopy = $copyElement.text().trim() || $copyElement.html().trim();
            }
            if (textToCopy) {
                navigator.clipboard.writeText(textToCopy).then(() => {
                    alert('Copied: ' + textToCopy);
                }).catch(err => {
                    console.error('Failed to copy:', err);
                });
            }
        });

        $(document).off('click submit', '[data-action]').on('click submit', '[data-action]', function (event) {
            let $this = $(this);

            const confirmMessage = $this.data('confirm');
            if (confirmMessage && !confirm(confirmMessage)) {
                event.preventDefault();
                return;
            }
            $this.attr("disabled");
            let type = $this.data('action');
            let $url = $this.data('url');
            let form = $this.closest('form');
            let checkbox = $this.data('checkbox');
            if (type === 'blur' || type === 'change' || type === 'change-load' || type === 'click-load') return;
            if (!$this.is(':checkbox, :radio')) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
            if (checkbox) {
                let updatedUrl = handleCheckboxSelection(checkbox, $this, $url);
                if (!updatedUrl) return;
                $url = updatedUrl;
            }
            let options = extractOptions($this);
            let formData = new FormData();
            let dsPOST = false;
            if (type === 'submit' || type === 'click' || type === 'social') {
                dsPOST = true;
                $url = $url || (form.length ? form.attr('action') : '');
                formData = handleFormSubmission(type, form, options, $this);
            } else if (type === 'modal' || type === 'offcanvas') {
                handleModalOrOffcanvas(type, $url, options, $this);
                return;
            }
            if (options.socket && dsPOST) sendSocketData(formData, options);
            if ($url && dsPOST) sendAjaxRequest($url, formData, options, $this);
            if (!$url && options.function && dsPOST) {
                handleTargetFunction(options, $this);
                return;
            }
            $this.removeAttr("disabled");
        });
        $(document).off('focus', '[data-action="blur"], [data-action="change"]').on('focus', '[data-action="blur"], [data-action="change"]', function () {
            const $this = $(this);
            $this.data('original-value', $this.val());
        });
        ['blur', 'change'].forEach(action => {
            $(document).off(action, `[data-action="${action}"]`).on(action, `[data-action="${action}"]`, function () {
                const $this = $(this);
                const originalValue = $this.data('original-value');
                const currentValue = $this.val();
                if (currentValue === originalValue) {
                    return;
                }
                $this.data('original-value', currentValue);
                const $url = $this.data('url');
                if (!$url) return;
                const formData = new FormData();
                const options = extractOptions($this);
                const formAttr = options.form;
                if (formAttr) handleFormData(formData, formAttr, $this);
                sendAjaxRequest($url, formData, options, $this);
            });
        });
    }
    function handleLoad($el) {
        const url = $el.data('url') || $el.data('load-url');
        const targetSelector = $el.data('target') || $el.data('load-target') || $el.data('html');
        let dataSend = {};
        if ($el.is('select') || $el.is('input') || $el.is('textarea')) {
            const name = $el.attr('name') || 'value';
            dataSend[name] = $el.val();
        }
        if (!url) {
            console.warn('Không có URL để load.');
            return;
        }
        const $target = targetSelector ? $(targetSelector) : $el;
        if ($target.length === 0) {
            console.warn('Không tìm thấy selector mục tiêu:', targetSelector);
            return;
        }
        $target.html('<div class="spinner-load">Loading...</div>');
        $.post(url, dataSend, function (response) {
            $target.html(response.content || response);
            const $newContent = $(response.content || response);
            DomDataAction($newContent);
            if (typeof pjax !== 'undefined' && typeof pjax.refresh === 'function') {
                selected();
                upload();
                uploadImages();
            }
        }).fail(function (xhr, status, error) {
            $target.html('<div class="text-danger">Tải dữ liệu thất bại</div>');
            console.error('Lỗi khi load:', error);
        });
    }
    function extractOptions($element) {
        return {
            alert: $element.data("alert"),
            load: $element.data("load"),
            form: $element.data("form"),
            function: $element.data("function"),
            history: $element.data("pjax-history") !== false,
            selector: $element.data("selector"),
            multi: $element.data("multi"),
            socket: $element.data("socket"),
            socketCode: $element.data("socket-code") || 'send',
            stream: $element.data("stream") || 'false',
            remove: $element.data("remove"),
            print: $element.data("print"),
            printTime: $element.data("print-time"),
            tableLoad : $element.data('table-load') || null,
            tableTargetLoad: $element.data('table-target-load') || null,
            tableResetPaging : $element.data('table-reset-paging') !== false,
            preserveFocus: $element.data('focus') === true, 
            workflowsLoad : $element.data('workflows-load'),
            targetCanvas: $element.data('target-canvas'),
            pjaxScrollTo: $element.data('pjax-scrollto'),
            toast: $element.data('toast'),
            toastPosition: $element.data('toast-position') || 'top-right',
        };
    }
    function handleFormSubmission(type, form, options, $this) {
        if (type === 'submit' && form.length) {
            return new FormData(form[0]); // Trả về FormData đúng cách
        } 
        let formData = new FormData();
        if (type === 'click' && options.form) {
            handleFormData(formData, options.form, $this);
        } else if (type === 'social') {
            handleSocialData(formData, $this);
        }
        return formData;
    }
    function handleCheckboxSelection(checkbox, $this, $url) {
        let selected = $(`${checkbox}:checked`);
        if (selected.length === 0) {
            swal_error('Vui lòng chọn dữ liệu');
            topbar.hide();
            $this.removeAttr('disabled');
            return false;
        }
        let boxid = selected.map((_, el) => el.value).get().join(',');
        let updatedUrl = `${$url}?box=${boxid}`;
        $this.data('url', updatedUrl);
        return updatedUrl;
    }
    function handleFormData(formData, dataForm, $this) {
      try {
        let dataFormString = typeof dataForm === 'object' ? JSON.stringify(dataForm) : dataForm;
        let parsedData = JSON.parse(dataFormString);
        for (let key in parsedData) {
            let value = parsedData[key];
            let $el;
            const getByMethod = ($el, method) => {
                if (!$el || $el.length === 0) return '';
                return $el[method] ? $el[method]() : '';
            };
            if (value.startsWith("closest:")) {
                let expr = value.replace(/^closest:/, '').trim();
                let attrMatch = expr.match(/^\[data-([^\]]+)\]\.attr$/);
                let methodMatch = expr.match(/^(.+)\.(val|html|text)$/);
                if (attrMatch) {
                    let attr = attrMatch[1];
                    $el = $this.closest(`[data-${attr}]`);
                    formData.append(key, $el.attr(`data-${attr}`));
                } else if (methodMatch) {
                    let selector = methodMatch[1];
                    let method = methodMatch[2];
                    $el = $this.closest(selector);
                    formData.append(key, getByMethod($el, method));
                } else {
                    $el = $this.closest(expr);
                    formData.append(key, $el.text().trim());
                }
                continue;
            }
            if (value.startsWith("parent:")) {
                let expr = value.replace(/^parent:/, '').trim();
                let attrMatch = expr.match(/^\[data-([^\]]+)\]\.attr$/);
                let methodMatch = expr.match(/^(.+)\.(val|html|text)$/);
                if (attrMatch) {
                    let attr = attrMatch[1];
                    $el = $this.parent().find(`[data-${attr}]`);
                    formData.append(key, $el.attr(`data-${attr}`));
                } else if (methodMatch) {
                    let selector = methodMatch[1];
                    let method = methodMatch[2];
                    $el = $this.parent().find(selector);
                    formData.append(key, getByMethod($el, method));
                } else {
                    $el = $this.parent().find(expr);
                    formData.append(key, $el.text().trim());
                }
                continue;
            }
            if (value.startsWith("find:")) {
                let expr = value.replace(/^find:/, '').trim();
                let attrMatch = expr.match(/^\[data-([^\]]+)\]\.attr$/);
                let methodMatch = expr.match(/^(.+)\.(val|html|text)$/);
                if (attrMatch) {
                    let attr = attrMatch[1];
                    $el = $this.find(`[data-${attr}]`);
                    formData.append(key, $el.attr(`data-${attr}`));
                } else if (methodMatch) {
                    let selector = methodMatch[1];
                    let method = methodMatch[2];
                    $el = $this.find(selector);
                    formData.append(key, getByMethod($el, method));
                } else {
                    $el = $this.find(expr);
                    formData.append(key, $el.text().trim());
                }
                continue;
            }
            let methodMatch = value.match(/^this\.(val|html|text)$/);
            if (methodMatch) {
                let method = methodMatch[1];
                formData.append(key, $this[method]());
                continue;
            }
            let attrMatch = value.match(/^(.*)\.attr\(([^)]+)\)$/);
            if (attrMatch) {
                let selector = attrMatch[1] === 'this' ? $this : $(attrMatch[1]);
                let attrName = attrMatch[2].replace(/['"]/g, '');
                formData.append(key, selector.attr(attrName));
                continue;
            }
            let normalMethodMatch = value.match(/^(.*)\.(val|html|text)$/);
            if (normalMethodMatch) {
                let selector = normalMethodMatch[1] === 'this' ? $this : $(normalMethodMatch[1]);
                let method = normalMethodMatch[2];
                formData.append(key, selector[method]());
                continue;
            }
            if (/^\[data-[^\]]+\]$/.test(value)) {
                let attrName = value.match(/^\[data-([^\]]+)\]$/)[1];
                $el = $this.find(`[data-${attrName}]`);
                if ($el.length === 0) $el = $(`[data-${attrName}]`);
                formData.append(key, $el.attr(`data-${attrName}`));
                continue;
            }
            formData.append(key, value);
        }
      } catch (error) {
        console.error("Error parsing data-form:", error, "Data received:", dataForm);
      }
        // "key": "this.text"  Lấy nội dung .text() từ chính $this
        // "key": "this.val" Lấy giá trị .val() từ chính $this
        // "key": "selector.text"  Lấy .text() từ phần tử được chỉ định
        // "key": "selector.val" Lấy .val() từ phần tử được chỉ định
        // "key": "selector.attr('name')"  Lấy thuộc tính từ selector
        // "key": "[data-abc]" Lấy data-abc từ phần tử có attribute data-abc
        // "key": "closest:selector.text"  Tìm phần tử cha gần nhất rồi lấy .text()
        // "key": "closest:[data-abc].attr"  Tìm phần tử cha gần nhất có data-abc, lấy data-abc
        // "key": "parent:selector.val"  Từ phần tử cha trực tiếp, tìm selector con và lấy .val()
        // "key": "parent:[data-abc].attr" Tìm phần tử con trong phần tử cha gần nhất và lấy data-abc
        // "key": "find:selector.html" Tìm selector bên trong $this rồi lấy .html()
    }
    function handleSocialData(formData, $this) {
        let parent = $this.parents(".social-create");
        formData.append('content', parent.find(".social-post").html());
        formData.append('type', parent.find("input[name='type']").val());
        formData.append('access', parent.find("input[name='access']:checked").val());
        let mediaType = formData.get('type');
        if (mediaType === 'audio') {
            formData.append('voice', parent.find("input[name='voice']").val());
        } else if (mediaType === 'video') {
            formData.append('video', parent.find("input[name='video']").val());
        } else if (mediaType === 'images') {
            parent.find("input[name='images[]']").each(function () {
                let imageUrl = $(this).val();
                if (imageUrl) formData.append('images[]', imageUrl);
            });
        }
    }
    function handleModalOrOffcanvas(type, $url, options = {}, $this) {
        let viewClass = `${type}-view${options?.multi ? '-' + options.multi : '-views'}`;
        if (!$(`.${viewClass}`).length) $('<div>').addClass(viewClass).appendTo('body');
        if (!$url) return;
        let maxZIndex = Math.max(
            ...$('.modal:visible, .offcanvas.show').map(function () {
                return parseInt($(this).css('z-index')) || 1040;
            }).get(),
            1040
        );
        let zIndex = maxZIndex + 20;
        let backdropClass = `${type}-backdrop-${Date.now()}`;

        $(`.${viewClass}`).load($url, function (response, status) {
            if (status === "error") {
                $(`.${viewClass}`).remove();
                swal_error('Failed to load content');
            } else {
                let $target = $(`.${viewClass} .${type}-load`);
                pjax.refresh();
                $target.css('z-index', zIndex);
                setTimeout(() => {
                    let $backdrop = $('.modal-backdrop, .offcanvas-backdrop').not(`.${backdropClass}`).last();
                    if ($backdrop.length) {
                        $backdrop.addClass(backdropClass).css('z-index', zIndex - 10);
                    }
                }, 50);
                if ($target.length) {
                    // $target.removeAttr('aria-hidden');
                    if (type === 'modal') {
                        let modalInstance = bootstrap.Modal.getOrCreateInstance($target[0]);
                        modalInstance.show();
                        $target.on('shown.bs.modal', () => topbar.hide());
                    } else if (type === 'offcanvas') {
                        let offcanvasInstance = bootstrap.Offcanvas.getOrCreateInstance($target[0]);
                        offcanvasInstance.show();
                        $target.on('shown.bs.offcanvas', () => topbar.hide());
                    }
                }
                modalOffcanvasload();
                pjaxConfig($this);
                $target.on(`hidden.bs.${type}`, function () {
                    $target.find('[data-table]').each(function () {
                        if ($.fn.dataTable.isDataTable(this)) {
                            $(this).DataTable().destroy(true);
                        }
                    });

                   $target.find('[data-chart]').each(function () {
                        const $canvas = $(this);
                        const rawChartName = $canvas.attr('data-chart');
                        const chartName = rawChartName ? String(rawChartName).trim() : '';

                        // named chart in global store
                        if (chartName && window.chartInstances && window.chartInstances[chartName]) {
                            try { window.chartInstances[chartName].destroy(); } catch (e) {}
                            delete window.chartInstances[chartName];
                        }

                        // instance stored on DOM
                        const stored = $canvas.data('chartInstance');
                        if (stored && typeof stored.destroy === 'function') {
                            try { stored.destroy(); } catch (e) {}
                            $canvas.removeData('chartInstance');
                        }

                        // Chart.getChart fallback
                        const existingChart = (typeof Chart !== 'undefined' && Chart.getChart) ? Chart.getChart(this) : null;
                        if (existingChart && typeof existingChart.destroy === 'function') {
                            try { existingChart.destroy(); } catch (e) {}
                            // remove from global map if referenced there
                            for (const k in window.chartInstances) {
                                if (window.chartInstances[k] === existingChart) delete window.chartInstances[k];
                            }
                        }
                    });


                    const $canvas = $(this).find('[data-workflows]');
                    if ($canvas.length) {
                        const canvasId = '#' + $canvas.attr('id');
                        const workflowInstance = window.workflowManager[canvasId];
                        if (workflowInstance && typeof workflowInstance.destroy === 'function') {
                            workflowInstance.destroy();
                        }
                    }

                    $(`.${viewClass}`).remove();
                    $(`.${backdropClass}`).remove();
                    $(document).find('rte-floatpanel').remove();
                    $this?.removeAttr('disabled');
                    pjax.options.history = true;
                });
            }
        });
    }
    function handleAjaxResponse(response, options, $this) {
        if (response.status === 'success') {
            pjaxConfig($this);
            if (options.remove) $(options.remove).remove();
            if (options.load) {
                const pjaxOptions = {};
                if (options.pjaxScrollTo !== undefined && String(options.pjaxScrollTo).toLowerCase() === 'false') {
                    pjaxOptions.scrollTo = false;
                }
                if (response.load === 'true') {
                    window.location.href = options.load;
                } else if (response.load === 'url') {
                    window.location.href = response.url;
                } else if (response.load === 'ajax') {
                    pjax.loadUrl(response.url, pjaxOptions);
                } else {
                    const targetUrl = options.load === 'this' ? window.location.href : options.load;
                    pjax.loadUrl(targetUrl, pjaxOptions);
                }
            }
            if (options.alert) swal_success(response.content, $this);
            if (options.toast && response.content) {
                showToast(response.content, response.status, options.toastPosition);
            }
            if(options.print=== 'modal'){
                $('<div class="modal-views-print"></div>').appendTo('body');
                $('.modal-views-print').load(response.print, function(response, status, req) {
                    let modalEl = document.querySelector('.modal-views-print .modal-load');
                    let modal = new bootstrap.Modal(modalEl);
                    modal.show();
                    modalEl.addEventListener('shown.bs.modal', function (e) {
                        topbar.hide();
                        window.print();
                    });
                }).on('hidden.bs.modal', function (e) {
                  $('.modal-load').modal('hide');
                  $('.modal-views-print').remove();
                });
            }
            if (options.tableLoad) {
                const tableSelector = `#${options.tableLoad}`;
                if (!$.fn.dataTable.isDataTable(tableSelector)) return;
                const table = $(tableSelector).DataTable();

                // CHẾ ĐỘ CHUYÊN BIỆT: Vẫn hoạt động với JSON tối giản từ server
                if (options.tableTargetLoad) {
                    const newRowsData = response.data;
                    if (!newRowsData || !Array.isArray(newRowsData)) {
                        // ... (xử lý lỗi)
                        return;
                    }

                    const newDataMap = new Map(newRowsData.map(item => [String(item.id), item]));

                    table.rows({ page: 'current' }).every(function () {
                        const row = this;
                        // 1. Dữ liệu cũ, đầy đủ, đã có sẵn trên trình duyệt
                        const oldRowData = row.data(); 
                        
                        if (oldRowData && oldRowData.id && newDataMap.has(String(oldRowData.id))) {
                            // 2. Dữ liệu mới, gọn nhẹ từ server
                            const updatedRowPartialData = newDataMap.get(String(oldRowData.id));
                            
                            // 3. Trộn chúng lại trên trình duyệt để có dữ liệu hàng hoàn chỉnh
                            const newFullRowData = { ...oldRowData, ...updatedRowPartialData };
                            
                            // 4. Cập nhật dữ liệu nguồn cho cả hàng -> an toàn và nhất quán
                            row.data(newFullRowData);
                        }
                    });

                    // 5. Vẽ lại giao diện một lần duy nhất
                    table.draw('page');
                    
                    // 6. Dừng thực thi để không bị reload lần hai
                    return; 

                } else {
                    // CHẾ ĐỘ MẶC ĐỊNH: Reload toàn bộ
                    // table.ajax.reload(null, options.tableResetPaging);
                    table.draw('page');
                }
            }

            if (options.workflowsLoad && options.targetCanvas) {
                let canvasSelector = options.targetCanvas;
                
                // Tự động thêm '#' nếu cần để tạo selector ID hợp lệ
                if (canvasSelector.charAt(0) !== '#' && canvasSelector.charAt(0) !== '.') {
                    canvasSelector = '#' + canvasSelector;
                }

                // Lấy instance từ "trung tâm quản lý" toàn cục
                const workflowInstance = window.workflowManager[canvasSelector];

                if (workflowInstance && response.nodeData) {
                    // Gọi đến phương thức của canvas để thêm hoặc cập nhật node
                    workflowInstance.updateOrAddNode(response.nodeData);
                } else {
                    if (!workflowInstance) {
                        console.warn(`Không tìm thấy workflow instance cho canvas: ${canvasSelector}`);
                    }
                    if (!response.nodeData) {
                        console.warn('Kết quả AJAX không chứa "nodeData" để cập nhật workflow.');
                    }
                }
            }
        }
        else {
            if (options.toast && response.content) {
                showToast(response.content, response.status, options.toastPosition);
            } else {
                swal_error(response.content);
            }
            return;
        }
    }
    function sendSocketData(formData, options) {
        let jsonObject = Object.fromEntries(formData.entries());
        let socketPayload = {
            status: "success",
            sender: active,
            token: getToken,
            stream: options.stream,
            router: options.socket,
            data: jsonObject,
            code: options.socketCode
        };
        send(socketPayload);
    }
    function sendAjaxRequest(url, formData, options, $this) {
        let focusedElementId = null;
        let selectionStart, selectionEnd;
        if (options.preserveFocus) {
            const focusedElement = document.activeElement;
            if (focusedElement && focusedElement.id) {
                focusedElementId = focusedElement.id;
                if (typeof focusedElement.selectionStart !== 'undefined') {
                    selectionStart = focusedElement.selectionStart;
                    selectionEnd = focusedElement.selectionEnd;
                }
            }
        }
        $.ajax({
            type: 'POST',
            url: url,
            data: formData,
            cache: false,
            contentType: false,
            processData: false,
            success: function (response) {
                handleAjaxResponse(response, options, $this);
                if (focusedElementId) {
                    const newElement = document.getElementById(focusedElementId);
                    if (newElement) {
                        newElement.focus();
                        if (typeof selectionStart !== 'undefined') {
                            try {
                               newElement.selectionStart = selectionStart;
                               newElement.selectionEnd = selectionEnd;
                            } catch (e) {
                            }
                        }
                    }
                }
            },
            error: function () {
                console.error("AJAX request failed");
            },
            complete: function () {
                topbar.hide();
                $this.removeAttr('disabled');
            }
        });
    }
    function handleTargetFunction(options, $element) {
        try {
            if (options.function.endsWith("()")) {
                let functionName = options.function.replace("()", "").trim();
                let allowedFunctions = {
                    assets_images: assets_images,
                };
                if (allowedFunctions[functionName]) {
                    allowedFunctions[functionName](options, $element);
                } else {
                    console.warn(`Function ${functionName} is not allowed`);
                }
            }
        } catch (error) {
            console.error("Error executing function from data-function:", error);
        }
    }
    function assets_images(options, $element) {
        $('.upload-files').find('.input-file').hide();
        $('.upload-files').find('.show-file').show();
        $('.upload-files').find('.show-file').find('.images-container').attr("src", "");
        $('.upload-files').find('.show-file').find(".value-file").val("");
        let formData = new FormData();
        handleFormData(formData, options.form, $element);
        let base64String = formData.get("data");
        if (base64String) {
            $('.upload-files').find('.show-file').find('.images-container').attr("src", '/'+base64String);
            $('.upload-files').find('.show-file').find(".value-file").val(base64String);
        } else {
            console.warn("No 'data' key found in formData.");
        }
    }
    function datatable(context = document) {
        $(context).find('[data-table]').each(function () {
            const $table = $(this);
            if (!$.fn.dataTable.isDataTable($table)) {
                const columns = $table.find('thead th').map(function () {
                    const $th = $(this);
                    return {
                        data: $th.attr('data-name') || null,
                        orderable: $th.attr('data-orderable') === "true",
                        visible: $th.attr('data-visible') !== "false",
                        className: $th.attr('data-class') || '',
                        render: function (data, type, row) {
                            if ($th.attr('data-name') === 'actions') {
                                return $th.attr('data-render');
                            }
                            return data;
                        },
                        createdCell: function (td, cellData, rowData) {
                            const dataAttr = $th.attr('data-attr');
                            if (dataAttr) {
                                try {
                                    const attributesArray = JSON.parse(dataAttr);
                                    if (Array.isArray(attributesArray)) {
                                        attributesArray.forEach(attrObj => {
                                            for (const key in attrObj) {
                                                if (attrObj.hasOwnProperty(key)) {
                                                    const attrValue = attrObj[key] === '{{data}}' ? cellData : attrObj[key];
                                                    $(td).attr(`data-${key}`, attrValue);
                                                }
                                            }
                                        });
                                    } else {
                                        for (const key in attributesArray) {
                                            if (attributesArray.hasOwnProperty(key)) {
                                                const attrValue = attributesArray[key] === '{{data}}' ? cellData : attributesArray[key];
                                                $(td).attr(`data-${key}`, attrValue);
                                            }
                                        }
                                    }
                                } catch (e) {
                                    console.error('Lỗi khi phân tích data-attr JSON:', e);
                                }
                            }
                        }
                    };
                }).get();
                let originalButtonsHtml = $('.custom-buttons').html();
                const searchableColumns = columns.map((col, index) => (col.visible ? index : null)).filter(index => index !== null);
                const options = {
                    ajax: {
                        url: $table.attr('data-url') || null,
                        type: $table.attr('data-type') || 'POST',
                        data: function(d) {
                            let searchParams = {};
                            return $.extend({}, d, searchParams);
                            Countdown();
                            number();
                            selected();
                        }
                    },
                    columns: columns,
                    processing: $table.attr('data-processing') === "true",
                    serverSide: $table.attr('data-server') === "true",
                    pageLength: parseInt($table.attr('data-page-length')) || 10,
                    searching: $table.attr('data-searching') === "true",
                    order: JSON.parse($table.attr('data-order') || '[]'),
                    lengthMenu: JSON.parse($table.attr('data-length-menu') || '[[10, 25, 50, 100, 200 , 500], ["10", "25", "50", "100", "200", "500"]]'),
                    paging: $table.attr('data-paging') !== "false",
                    info: $table.attr('data-info') !== "false",
                    language: JSON.parse($table.attr('data-lang') || '{"search": "","searchPlaceholder": "Nhập để tìm kiếm...","lengthMenu": "_MENU_", "info": "Hiển thị _START_ đến _END_ của tổng _TOTAL_", "infoEmpty":"Hiển thị 0 đến 0 của tổng 0","emptyTable": "Không tìm thấy dữ liệu"}'),
                    scrollX: $table.attr('data-scroll-x') || null,
                    scrollY: $table.attr('data-scroll-y') || null,
                    stateSave: $table.attr('data-state-save') || null,
                    dom: "<'row p-2 align-items-center g-2'<'col-md-6 col-lg-5 col-12 text-start order-2 order-md-1'f><'col-md-6 col-lg-7 col-12 order-1 order-md-2 text-end custom-buttons-display'>>" +
                        "<'row mb-4'<'col-md-12't>>" +
                        "<'row mb-2 px-2 align-items-center justify-content-between'<'col-md-6 justify-content-start'p><'col-md-6 align-items-center justify-content-md-end d-flex'i l>>",
                    button: [],
                    initComplete: function () {
                        let $originalButtons = $('.custom-buttons').children().clone(false, false);

                        // Xoá toàn bộ DOM mà select2 đã render ra trong bản clone
                        $originalButtons.find('select[data-select]').each(function () {
                            $(this).removeData('select2'); // xoá instance
                            $(this).removeClass('select2-hidden-accessible'); // bỏ class select2
                            $(this).next('.select2').remove(); // xoá phần span.select2 đã render
                        });

                        $('.custom-buttons').remove();
                        $originalButtons.find('[data-pjax]').removeAttr('data-pjax-state');
                        $('.custom-buttons-display').empty().append($originalButtons);

                        // giờ init lại bình thường
                        selected();
                        pjax.refresh();
                        pjaxConfig($(this));
                    },
                    drawCallback: function () {
                        Countdown();
                        number();
                        selected();
                    },
                    footerCallback: function (row, data, start, end, display) {
                        const api = this.api();
                        const response = api.ajax.json();
                        if (response.footerData) {
                            $(api.table().footer()).find('th').each(function (index) {
                                const name = $(this).data('name');
                                if (name && response.footerData[name] !== undefined) {
                                    $(this).html(response.footerData[name]);
                                }
                            });
                        }
                    }
                };
                var dataTableInstance = $table.DataTable(options);
                if ($table.attr('data-scroll-x') || $table.attr('data-scroll-y')) {
                    const $wrapper = $table.closest('.dataTables_wrapper');
                    $wrapper.find('.dataTables_scrollBody').on('scroll.select2', function() {
                        $wrapper.find('[data-select]').select2('close');
                    });
                }
            }
            $(document).off("click", ".button-filter").on("click", ".button-filter", function() {
                let table = dataTableInstance;
                let filterData = {};
                let params = new URLSearchParams(window.location.search);
                $(".filter-name").each(function() {
                    let $el = $(this);
                    let name = $el.attr("name");
                    let value = "";
                    if ($el.is("select") || $el.is("input")) {
                        value = $el.val();
                    } else if ($el.is("input[type='checkbox'], input[type='radio']")) {
                        if ($el.is(":checked")) {
                            value = $el.val();
                        }
                    }
                    if (value !== "") {
                        filterData[name] = value;
                        params.set(name, value);
                    } else {
                        params.delete(name);
                    }
                });
                table.settings()[0].ajax.data = function(d) {
                    return $.extend({}, d, filterData);
                };
                history.pushState({}, "", "?" + params.toString());
                table.ajax.reload();
            });
            $(document).off("click", ".reset-filter").on("click", ".reset-filter", function() {
                let table = dataTableInstance;
                let params = new URLSearchParams(window.location.search);
                $(".filter-name").each(function() {
                    $(this).val("").trigger("change");
                    params.delete($(this).attr("name"));
                });
                history.replaceState({}, "", window.location.pathname);
                table.settings()[0].ajax.data = function (d) {
                    return d;
                };
                table.ajax.reload(null, false);
            });
            $(document).ready(function() {
                let params = new URLSearchParams(window.location.search);
                let filterData = {};
                $(".filter-name").each(function() {
                    let $el = $(this);
                    let name = $el.attr("name");
                    if (name.endsWith("[]")) {
                        let value = params.get(name);
                        if (value && value.length > 0) {
                            value = value.split(",").map(item => item.trim()).filter(item => item !== "");
                            $el.val(value).trigger("change");
                            filterData[name] = value;
                        }
                    }
                    else if (params.has(name)) {
                        let value = params.get(name);
                        $el.val(value).trigger("change");
                        filterData[name] = value;
                    }
                });
                if (Object.keys(filterData).length > 0) {
                    dataTableInstance.settings()[0].ajax.data = function(d) {
                        return $.extend({}, d, filterData);
                    };
                    dataTableInstance.ajax.reload();
                }
            });
        });
        $(context).find('[data-table]').on('show.bs.dropdown', '.dropdown', function () {
            let $dropdownMenu = $(this).find('.dropdown-menu');
            if (!$dropdownMenu.data('original-style')) {
                $dropdownMenu.data('original-style', $dropdownMenu.attr('style') || '');
            }
            $('body').append($dropdownMenu.detach());
            let newStyle = `${$dropdownMenu.data('original-style')}; display: block; position: absolute; top: ${$(this).offset().top + $(this).outerHeight()}px; left: ${$(this).offset().left}px;`;
            $dropdownMenu.attr('style', newStyle);
            $(this).data('dropdown-menu', $dropdownMenu);
            pjax.refresh();
            pjaxConfig($(this));
        });
        $(context).find('[data-table]').on('hidden.bs.dropdown', '.dropdown', function () {
            let $dropdownMenu = $(this).data('dropdown-menu');
            if ($dropdownMenu) {
                $(this).append($dropdownMenu.detach());
                $dropdownMenu.attr('style', $dropdownMenu.data('original-style'));
                $(this).removeData('dropdown-menu');
                pjax.refresh();
                pjaxConfig($(this));
            }
        });
    }
    function workflowsLoad(){
        $('[data-workflows]').each(function() {
            workflows(this);
        });
    }
    function workflows(canvasElement) {
        const $canvas = $(canvasElement);
        const canvasId = '#' + $canvas.attr('id');
        if (window.workflowManager[canvasId]) { return; }

        const WORKFLOW_ID = $canvas.data('workflow-id');
        const allowMultipleChildren = $canvas.data('allow-multiple-children') === true;

        const API_URLS = {
            load: $canvas.data('url-load'),
            nodeUpdatePos: $canvas.data('url-node-update-pos'),
            nodeDelete: $canvas.data('url-node-delete'),
            connCreate: $canvas.data('url-connection-create'),
            connDelete: $canvas.data('url-connection-delete')
        };
        const api = {};

        jsPlumb.ready(function() {
            let localNodesData = {};
            let instance = jsPlumb.getInstance({
                Container: canvasElement, DragOptions: { cursor: 'pointer', zIndex: 2000 }, Connector: ["Bezier", { curviness: 50 }],
                ConnectionOverlays: [
                    ["Arrow", { location: 1, id: "ARROW" }],
                    ["Label", { location: 0.5, id: "delete_label", cssClass: "connection-delete-label", label: "&times;", events: { click: (label) => deleteConnection(label.component) } }]
                ]
            });
            let currentDragState = null; // <-- THÊM VÀO ĐÂY

            // ====================================================================
            // CÁC HÀM API VÀ RENDER NODE (KHÔNG THAY ĐỔI)
            // ====================================================================
            function apiUpdateNodePosition(nodeId, position) {
                $.ajax({
                    url: API_URLS.nodeUpdatePos, type: 'POST',
                    data: { workflow_id: WORKFLOW_ID, node_id: nodeId, position_top: position.top, position_left: position.left },
                    dataType: 'json',
                    success: (response) => console.log(`Vị trí của node ${nodeId} đã được cập nhật.`),
                    error: () => console.error(`Lỗi khi cập nhật vị trí node ${nodeId}.`)
                });
            }
            function apiDeleteNode(nodeId) {
                $.ajax({
                    url: API_URLS.nodeDelete, type: 'POST',
                    data: { workflow_id: WORKFLOW_ID, node_id: nodeId },
                    dataType: 'json',
                    success: (response) => console.log(`Node ${nodeId} đã được xóa.`),
                    error: () => console.error(`Lỗi khi xóa node ${nodeId}.`)
                });
            }
            function apiCreateConnection(sourceId, targetId) {
                $.ajax({
                    url: API_URLS.connCreate, type: 'POST',
                    data: { workflow_id: WORKFLOW_ID, source_node_id: sourceId, target_node_id: targetId },
                    dataType: 'json',
                    success: (response) => console.log(`Đã tạo kết nối từ ${sourceId} đến ${targetId}.`),
                    error: () => console.error(`Lỗi khi tạo kết nối.`)
                });
            }
            function apiDeleteConnection(sourceId, targetId) {
                $.ajax({
                    url: API_URLS.connDelete, type: 'POST',
                    data: { workflow_id: WORKFLOW_ID, source_node_id: sourceId, target_node_id: targetId },
                    dataType: 'json',
                    success: (response) => console.log(`Đã xóa kết nối từ ${sourceId} đến ${targetId}.`),
                    error: () => console.error(`Lỗi khi xóa kết nối.`)
                });
            }
            function renderNodeContent(type, data) {
                const templateId = `#template-${type}`;
                let $template = $(templateId);
                if (!$template.length) { $template = $('#template-default'); }
                const $content = $($template.html());
                for (const key in data) {
                    if (data.hasOwnProperty(key) && data[key]) {
                        $content.find(`[data-text='${key}']`).text(data[key]);
                        $content.find(`[data-html='${key}']`).html(data[key]);
                    }
                }
                return $content;
            }
            function findAllDescendants(startNodeEl, instance) {
                const descendants = [];
                const queue = [startNodeEl]; // Hàng đợi bắt đầu với node được kéo
                const visited = new Set([startNodeEl.id]); // Lưu các node đã duyệt để tránh lặp vô hạn

                while (queue.length > 0) {
                    const currentNodeEl = queue.shift(); // Lấy node đầu tiên trong hàng đợi
                    const connections = instance.getConnections({ source: currentNodeEl.id });

                    connections.forEach(conn => {
                        const childEl = conn.target;
                        if (!visited.has(childEl.id)) { // Nếu node con này chưa được duyệt
                            visited.add(childEl.id);
                            descendants.push(childEl); // Thêm vào danh sách con cháu
                            queue.push(childEl);       // Thêm vào hàng đợi để tiếp tục duyệt các con của nó
                        }
                    });
                }
                return descendants;
            }
            function addNode(nodeData, isNew = false) {
                localNodesData[nodeData.id] = nodeData;
                const $content = renderNodeContent(nodeData.type, nodeData.data);
                const nodeId = `node-db-${nodeData.id}`;
                const nodeHtml = `<div class="workflow-node entering" id="${nodeId}" data-db-id="${nodeData.id}" data-type="${nodeData.type}"><div class="node-content"></div><div class="delete-node" title="Xóa khối">&times;</div></div>`;
                $canvas.append(nodeHtml);
                const $newNode = $(`#${nodeId}`);
                $newNode.find('.node-content').append($content);
                if (isNew) {
                    // ====================================================================
                    // ĐOẠN CODE MỚI ĐỂ ĐƯA NODE VÀO GIỮA MÀN HÌNH
                    // ====================================================================

                    // 1. Lấy kích thước và tọa độ trung tâm của khung nhìn (viewport)
                    const viewportWidth = $(viewport).width();
                    const viewportHeight = $(viewport).height();
                    const viewportCenterX = viewportWidth / 2;
                    const viewportCenterY = viewportHeight / 2;

                    // 2. Chuyển đổi tọa độ trung tâm của khung nhìn sang tọa độ của canvas
                    //    Bằng cách tính ngược lại giá trị pan (pointX, pointY) và zoom (scale)
                    const canvasTargetX = (viewportCenterX - pointX) / scale;
                    const canvasTargetY = (viewportCenterY - pointY) / scale;

                    // 3. Gán vị trí đã tính cho node mới
                    $newNode.css({ top: canvasTargetY, left: canvasTargetX });

                    // Lưu lại vị trí mới này vào dữ liệu local để API có thể cập nhật
                    nodeData.top = canvasTargetY;
                    nodeData.left = canvasTargetX;
                    
                } else {
                    // Giữ nguyên logic cho các node đã có sẵn
                    $newNode.css({ top: nodeData.top, left: nodeData.left });
                }
                
                addEndpoints(nodeId);

                instance.draggable(nodeId, { 
                    containment: canvasElement, 
                    filter: ".delete-node, .jtk-endpoint",
                    grid: [20, 20],

                    start: (params) => {
                        const draggedEl = params.el;
                        const descendantElements = findAllDescendants(draggedEl, instance);

                        // ====================================================================
                        // SỬA LỖI TẠI ĐÂY: Thay thế .position()
                        // ====================================================================
                        // Cách cũ (Gây lỗi khi có scale): const pos = $(el).position();
                        // Cách mới (Chính xác): Đọc trực tiếp từ style
                        const getReliablePosition = (el) => ({
                            top: parseFloat(el.style.top),
                            left: parseFloat(el.style.left)
                        });
                        // ====================================================================

                        const descendantStates = descendantElements.map(el => ({
                            el: el,
                            initialPos: getReliablePosition(el) // Áp dụng cách lấy vị trí mới
                        }));

                        currentDragState = {
                            parentInitialPos: getReliablePosition(draggedEl), // Áp dụng cách lấy vị trí mới
                            descendants: descendantStates
                        };
                        
                        $(draggedEl).addClass('dragging-parent');
                        descendantElements.forEach(el => $(el).addClass('dragging-child'));
                    },

                    drag: (params) => {
                        // Phần này không thay đổi, vì bây giờ nó đã nhận được initialPos chính xác
                        if (!currentDragState) return;

                        const parentCurrentPos = { left: params.pos[0], top: params.pos[1] };
                        const dx = parentCurrentPos.left - currentDragState.parentInitialPos.left;
                        const dy = parentCurrentPos.top - currentDragState.parentInitialPos.top;

                        currentDragState.descendants.forEach(desc => {
                            const newLeft = desc.initialPos.left + dx;
                            const newTop = desc.initialPos.top + dy;
                            $(desc.el).css({ left: newLeft, top: newTop });
                            instance.revalidate(desc.el);
                        });
                    },

                    stop: (params) => {
                        // Phần này không thay đổi
                        const draggedEl = params.el;
                        const parentNodeId = $(draggedEl).data('db-id');
                        apiUpdateNodePosition(parentNodeId, { top: params.pos[1], left: params.pos[0] });

                        if (currentDragState) {
                            currentDragState.descendants.forEach(desc => {
                                const childNodeId = $(desc.el).data('db-id');
                                const childFinalPos = { // Dùng lại cách lấy vị trí mới để đảm bảo an toàn
                                    top: parseFloat(desc.el.style.top), 
                                    left: parseFloat(desc.el.style.left)
                                };
                                apiUpdateNodePosition(childNodeId, childFinalPos);
                            });
                        }
                        
                        $(draggedEl).removeClass('dragging-parent');
                        if (currentDragState) {
                           currentDragState.descendants.forEach(desc => $(desc.el).removeClass('dragging-child'));
                        }
                        
                        currentDragState = null;
                    }
                });

                setTimeout(() => $newNode.removeClass('entering'), 50);
            }
            function updateNodeContent(nodeId, data, type) {
                localNodesData[nodeId].data = data;
                localNodesData[nodeId].type = type;
                const $content = renderNodeContent(type, data);
                $(`#node-db-${nodeId} .node-content`).html($content);
            }
            function addEndpoints(nodeId) {
                instance.addEndpoint(nodeId, { endpoint: "Dot", isTarget: true, maxConnections: 1, anchor: "TopCenter" });
                instance.addEndpoint(nodeId, { 
                    endpoint: "Dot", isSource: true, 
                    maxConnections: allowMultipleChildren ? -1 : 1, 
                    anchor: "BottomCenter" 
                });
            }
            function clearCanvas() {
                instance.deleteEveryConnection(); instance.deleteEveryEndpoint(); $canvas.empty(); localNodesData = {};
            }

            // SỬA ĐỔI HÀM NÀY
            function renderWorkflow(workflowData) {
                clearCanvas();
                $('#current-workflow-id-display').text(workflowData.id);
                instance.batch(() => {
                    workflowData.nodes.forEach(node => addNode(node));
                    workflowData.connections.forEach(conn => createConnection(conn.source, conn.target));
                });

                // GỌI HÀM MỚI Ở ĐÂY
                // Đợi một chút để DOM cập nhật kích thước node rồi mới tính toán
                setTimeout(() => {
                    zoomToFit(); 
                    instance.repaintEverything();
                }, 150);
            }

            function deleteConnection(conn) {
                const sourceId = $(conn.source).data('db-id'); const targetId = $(conn.target).data('db-id');
                apiDeleteConnection(sourceId, targetId); instance.deleteConnection(conn);
            }
            function createConnection(sourceId, targetId) {
                instance.connect({ 
                    source: `node-db-${sourceId}`, 
                    target: `node-db-${targetId}`, 
                    anchors: ["BottomCenter", "TopCenter"] 
                });
            }
            
            $canvas.off('click', '.delete-node').on('click', '.delete-node', function() {
                const $node = $(this).closest('.workflow-node');
                const nodeId = $node.data('db-id');
                if (confirm(`Bạn có chắc muốn xóa khối này (ID: ${nodeId})?`)) {
                    $node.addClass('exiting');
                    setTimeout(() => {
                        apiDeleteNode(nodeId); 
                        instance.remove($node.attr('id')); 
                        delete localNodesData[nodeId];
                    }, 300);
                }
            });
            
            instance.bind("connection", (info, originalEvent) => {
                if (originalEvent) apiCreateConnection($(info.source).data('db-id'), $(info.target).data('db-id'));
            });
            instance.bind("connectionDetached", (info, originalEvent) => {
                if (originalEvent) apiDeleteConnection($(info.source).data('db-id'), $(info.target).data('db-id'));
            });
            
            function loadInitialWorkflow(id) {
                const apiUrl = `${API_URLS.load}`;
                console.log(`API: Tải quy trình từ ${apiUrl}...`);
                $.ajax({
                    url: apiUrl, type: 'POST', dataType: 'json',
                    data: { workflow_id: WORKFLOW_ID },
                    success: (response) => {
                        if (response && response.nodes) renderWorkflow(response);
                        else {
                            console.error("Dữ liệu quy trình không hợp lệ:", response);
                            renderWorkflow({id: id, nodes: [], connections: []});
                        }
                    },
                    error: (xhr, status, error) => {
                        console.error("Lỗi khi tải quy trình:", error);
                        $canvas.html(`<div class='alert alert-danger m-5'>Không thể tải dữ liệu quy trình. Vui lòng thử lại.</div>`);
                    }
                });
            }

            api.updateOrAddNode = function(nodeData) {
                if (!nodeData || !nodeData.id) {
                    console.error("Dữ liệu không hợp lệ, thiếu ID.", nodeData); return;
                }
                if (localNodesData[nodeData.id]) updateNodeContent(nodeData.id, nodeData.data, nodeData.type);
                else addNode(nodeData, true);
                setTimeout(() => instance.repaintEverything(), 50);
            };
            
            api.destroy = function() {
                console.log(`WORKFLOW: Dọn dẹp instance cho ${canvasId}`);
                instance.deleteEveryConnection();
                instance.deleteEveryEndpoint();
                $canvas.empty();
                delete window.workflowManager[canvasId];
            };

            loadInitialWorkflow(WORKFLOW_ID);
            
            // === START: PHẦN TỐI ƯU CHO DI CHUYỂN VÀ ZOOM (PAN & ZOOM) ===
            let scale = 1, panning = false, pointX = 0, pointY = 0, start = { x: 0, y: 0 };
            let lastScale = 1;
            let initialPinchDistance = 0;
            const viewport = document.getElementById('canvas-viewport');

            function setTransform() {
                $canvas.css('transform', `translate(${pointX}px, ${pointY}px) scale(${scale})`);
                instance.setZoom(scale);
            }
            
            // === HÀM MỚI: TỰ ĐỘNG CĂN CHỈNH KHUNG NHÌN ===
            function zoomToFit() {
                const $nodes = $canvas.find('.workflow-node');
                if ($nodes.length === 0) {
                    // Nếu không có node nào, reset view về mặc định
                    scale = 1;
                    pointX = 0;
                    pointY = 0;
                    setTransform();
                    return;
                }

                // 1. Tìm vùng bao của tất cả các node
                let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
                $nodes.each(function() {
                    const $node = $(this);
                    const pos = $node.position();
                    const width = $node.outerWidth();
                    const height = $node.outerHeight();
                    
                    minX = Math.min(minX, pos.left);
                    minY = Math.min(minY, pos.top);
                    maxX = Math.max(maxX, pos.left + width);
                    maxY = Math.max(maxY, pos.top + height);
                });

                // 2. Lấy kích thước khung nhìn
                const viewportWidth = $(viewport).width();
                const viewportHeight = $(viewport).height();
                const contentWidth = maxX - minX;
                const contentHeight = maxY - minY;

                if (contentWidth <= 0 || contentHeight <= 0) return;

                // 3. Tính toán tỷ lệ (scale)
                const padding = 100; // Khoảng đệm 100px xung quanh
                const scaleX = (viewportWidth - padding) / contentWidth;
                const scaleY = (viewportHeight - padding) / contentHeight;
                let newScale = Math.min(scaleX, scaleY, 2); // Giới hạn scale tối đa là 2
                scale = Math.max(0.2, newScale); // Giới hạn scale tối thiểu là 0.2

                // 4. Tính toán vị trí để căn giữa
                const contentCenterX = minX + contentWidth / 2;
                const contentCenterY = minY + contentHeight / 2;
                
                pointX = (viewportWidth / 2) - (contentCenterX * scale);
                pointY = (viewportHeight / 2) - (contentCenterY * scale);

                // 5. Áp dụng transform
                setTransform();
            }


            function getDistance(p1, p2) {
                return Math.sqrt(Math.pow(p2.clientX - p1.clientX, 2) + Math.pow(p2.clientY - p1.clientY, 2));
            }

            // Hỗ trợ Mouse Wheel Zoom cho Desktop
            $(viewport).on('wheel', function (e) {
                e.preventDefault();
                const delta = e.originalEvent.deltaY ? -e.originalEvent.deltaY : e.originalEvent.wheelDelta;
                const zoomIntensity = 0.1;
                const rect = viewport.getBoundingClientRect();
                const mouseX = e.clientX - rect.left;
                const mouseY = e.clientY - rect.top;
                
                const newScale = scale + (delta > 0 ? zoomIntensity : -zoomIntensity) * scale;
                const oldScale = scale;
                scale = Math.max(0.2, Math.min(2, newScale));

                pointX = mouseX - (mouseX - pointX) * (scale / oldScale);
                pointY = mouseY - (mouseY - pointY) * (scale / oldScale);

                setTransform();
            });

            // Hỗ trợ Mouse Pan cho Desktop
            $(viewport).on('mousedown', function (e) {
                if ((e.target !== viewport && e.target !== canvasElement) || e.button !== 0) return;
                e.preventDefault();
                start = { x: e.clientX - pointX, y: e.clientY - pointY };
                panning = true;
                $(viewport).css('cursor', 'grabbing');
            });

            $(viewport).on('mouseup', function () {
                panning = false;
                $(viewport).css('cursor', 'grab');
            });

            $(viewport).on('mouseleave', function () { // Thêm để tránh bị kẹt grabbing
                 panning = false;
                 $(viewport).css('cursor', 'grab');
            });

            $(viewport).on('mousemove', function (e) {
                if (!panning) return;
                e.preventDefault();
                pointX = e.clientX - start.x;
                pointY = e.clientY - start.y;
                setTransform();
            });
            
            // Hỗ trợ Touch cho Mobile
            $(viewport).on('touchstart', function(e) {
                if (e.target !== viewport && e.target !== canvasElement) return;
                const touches = e.originalEvent.touches;
                
                if (touches.length === 1) { // Pan 1 ngón
                    e.preventDefault();
                    start = { x: touches[0].clientX - pointX, y: touches[0].clientY - pointY };
                    panning = true;
                } else if (touches.length === 2) { // Zoom 2 ngón
                    e.preventDefault();
                    panning = false; // Tắt pan khi đang zoom
                    initialPinchDistance = getDistance(touches[0], touches[1]);
                    lastScale = scale;
                }
            });

            $(viewport).on('touchmove', function(e) {
                if (e.target !== viewport && e.target !== canvasElement) return;
                e.preventDefault();
                const touches = e.originalEvent.touches;

                if (touches.length === 1 && panning) { // Pan 1 ngón
                    pointX = touches[0].clientX - start.x;
                    pointY = touches[0].clientY - start.y;
                    setTransform();
                } else if (touches.length === 2) { // Zoom 2 ngón
                    const currentDistance = getDistance(touches[0], touches[1]);
                    const newScale = (currentDistance / initialPinchDistance) * lastScale;
                    scale = Math.max(0.2, Math.min(2, newScale));
                    setTransform();
                }
            });

            $(viewport).on('touchend', function(e) {
                panning = false;
                initialPinchDistance = 0;
                const touches = e.originalEvent.touches;
                if(touches.length === 1){
                      start = { x: touches[0].clientX - pointX, y: touches[0].clientY - pointY };
                      panning = true;
                }
            });

            // === END: PHẦN TỐI ƯU CHO DI CHUYỂN VÀ ZOOM (PAN & ZOOM) ===
            $(viewport).css('cursor', 'grab'); // Thêm cursor ban đầu
        });
        
        window.workflowManager[canvasId] = api;
        $canvas.data('workflowInstance', api);
    }
    function handleWorkflowUpdate(response, triggerElement) {
        const $trigger = $(triggerElement);
        let canvasSelector = $trigger.data('target-canvas'); 
        if (!canvasSelector) return;
        
        if (canvasSelector.charAt(0) !== '#' && canvasSelector.charAt(0) !== '.') {
            canvasSelector = '#' + canvasSelector;
        }
        
        let workflowInstance = window.workflowManager[canvasSelector];
        if (!workflowInstance) {
            console.warn(`Không tìm thấy instance trong manager, đang thử tìm trên element: ${canvasSelector}`);
            const $canvas = $(canvasSelector);
            if ($canvas.length) {
                workflowInstance = $canvas.data('workflowInstance');
            }
        }

        if (workflowInstance && response.nodeData) {
            workflowInstance.updateOrAddNode(response.nodeData);
        } else {
             if (!workflowInstance) {
                console.error(`LỖI NGHIÊM TRỌNG: Không thể tìm thấy workflow instance cho canvas: ${canvasSelector}`);
            }
        }
    }
    // function mqttvideo(){
    //     const video = document.getElementById('Mqtt-video');
    //     if (!video) return;
    //      // Đây là URL ngrok của bạn, trỏ đến file .m3u8
    //     var videoSrc = 'https://rtsp.ellm.io/7e88a9d5e6ae5330cf75f2433b78ab63/hls/k8Tad4G9Me/IbiDgsSoW7/s.m3u8'; 
    //     if (Hls.isSupported()) {
    //         var hls = new Hls();
    //         hls.loadSource(videoSrc);
    //         hls.attachMedia(video);
    //         hls.on(Hls.Events.MANIFEST_PARSED, function() {
    //             video.play();
    //         });
    //     } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
    //         // Hỗ trợ HLS gốc trên các thiết bị Apple (Safari)
    //         video.src = videoSrc;
    //         video.addEventListener('loadedmetadata', function() {
    //             video.play();
    //         });
    //     }
    // }
    // function mqttSocket() {
    //   const messagesDiv = document.getElementById('Mqtt-messages');
    //   if (!messagesDiv) return; // Không có #Mqtt-messages thì không làm gì

    //   const client = mqtt.connect('wss://mqtt.ellm.io/mqtt', {
    //     clientId: 'web-client-' + Math.random().toString(16).substr(2, 8),
    //     username: 'eclo',
    //     password: 'Eclo@123'
    //   });

    //   client.on('connect', () => {
    //     client.subscribe([
    //       'mqtt/face/1018656',
    //       'mqtt/face/1018656/Rec',
    //       'mqtt/face/1018656/Snap',
    //     ], (err) => {
    //       if (err) {
    //         messagesDiv.innerHTML += `❌ Subscribe error: ${err.message}<br>`;
    //       }
    //     });
    //   });

    //   client.on('message', (topic, message) => {
    //     if (!document.getElementById('Mqtt-messages')) return; 
        
    //     const time = new Date().toLocaleTimeString();
    //     let content = '';
    //     try {
    //       const payload = JSON.parse(message.toString());
    //       if (payload && payload.info) {
    //         const operator = payload.operator;
    //         const { SnapID, time: snapTime, pic, customId, persionName } = payload.info;
    //         if(operator=='RecPush'){
    //             content = `<div class="position-relative mb-2 row bg-success rounded-4 g-0 align-items-center">
    //                       <div class="col-4">
    //                         <div style="background: url(${pic});background-size:cover;background-position: top center;--width:100px;--height:100px;    background-repeat: no-repeat;" class="width height rounded-4"></div>
    //                       </div>
    //                       <div class="col-8 text-light">
    //                         <div class="p-2">
    //                             <div>Ngày: ${snapTime} </div>
    //                             <div class="fw-bold">${persionName}</div>
    //                             <a data-action="modal" data-url="/customers/face-views/${customId}" class="btn p-0 stretched-link"></a>
    //                         </div>
    //                       </div>
    //                     </div>`;
    //         }
    //         if(operator=='StrSnapPush'){
    //             content = `<div class="position-relative mb-2 row bg-danger rounded-4 g-0 align-items-center">
    //                       <div class="col-4">
    //                         <div style="background: url(${pic});background-size:cover;background-position: top center;--width:100px;--height:100px;    background-repeat: no-repeat;" class="width height rounded-4"></div>
    //                       </div>
    //                       <div class="col-8 text-light">
    //                         <div class="p-2">
    //                             <div>Ngày: ${snapTime} </div>
    //                             <div class="fw-bold">Người lạ hoặc chưa nhận diện được</div>
    //                         </div>
    //                       </div>
    //                     </div>`;
    //         }
    //       }
    //     } catch (e) {
    //       content = `🕒 [${time}] Topic: ${topic}<br>❌ Error parsing JSON:<br><pre>${message.toString()}</pre><br>`;
    //     }
    //     messagesDiv.innerHTML = content + messagesDiv.innerHTML;
    //   });

    //   client.on('error', (err) => {
    //     if (document.getElementById('Mqtt-messages')) {
    //       messagesDiv.innerHTML += `❌ Connection error: ${err.message}<br>`;
    //     }
    //   });
    // }
    if ('serviceWorker' in navigator && 'PushManager' in window) {
        navigator.serviceWorker.register('/sw.js')
        .then(function(registration) {
            // console.log('Service Worker đã được đăng ký:', registration);
        })
        .catch(function(error) {
            // console.error('Lỗi đăng ký Service Worker:', error);
        });
    }
});