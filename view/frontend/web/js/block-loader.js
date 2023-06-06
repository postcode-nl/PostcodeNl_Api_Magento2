define([
    'rjsResolver',
], function (resolver) {
    'use strict';

    /**
     * Initializes assets loading process listener.
     *
     * @param {Object} config - Optional configuration default data after loaded component.
     * @param {HTMLElement} $loader - Loader DOM element.
     */
    function init(config, $loader)
    {
        resolver(hideLoader.bind(config, $loader));
    }

    /**
     * Removes provided loader element from DOM.
     *
     * @param {HTMLElement} $loader - Loader DOM element.
     */
    function hideLoader($loader)
    {
        $loader.parentNode.removeChild($loader);
    }

    return init;
});
