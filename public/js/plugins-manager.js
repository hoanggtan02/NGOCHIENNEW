window.registeredPlugins = window.registeredPlugins || {};
window.registerPlugin = function(pluginName, initializationFunction) {
    if (!window.registeredPlugins[pluginName]) {
        window.registeredPlugins[pluginName] = initializationFunction;
        // console.log(`Plugin "${pluginName}" đã được đăng ký.`);
    } else {
        // console.warn(`Plugin "${pluginName}" đã được đăng ký trước đó.`);
    }
};
function initializeRegisteredPlugins() {
    for (const pluginName in window.registeredPlugins) {
        if (window.registeredPlugins.hasOwnProperty(pluginName)) {
            const initializationFunction = window.registeredPlugins[pluginName];
            if (typeof initializationFunction === 'function') {
                // console.log(`Đang khởi tạo plugin: ${pluginName}`);
                initializationFunction();
            } else {
                // console.warn(`Plugin "${pluginName}" không phải là một hàm.`);
            }
        }
    }
}