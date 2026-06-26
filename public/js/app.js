/**
 * fast-php-log-viewer - Main Vue App
 * Creates the Vue app, registers all components, and mounts to #app
 */
(function () {
    const F = window.FPLV;
    const app = Vue.createApp({});

    F.components.forEach(c => app.component(c.name, c));

    app.mount('#app');

    F.init();
})();
