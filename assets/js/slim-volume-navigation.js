(function () {
  "use strict";

  const config = window.SVNavigationConfig || {};

  const Nav = {
    contentSelector: config.contentSelector || "[data-sv-page-content]",
    musicBaseUrl: config.musicBaseUrl || "/music/",

    init() {
      const content = document.querySelector(this.contentSelector);

      if (!content) {
        return;
      }

      console.log("[SVNavigation] Ready", {
        contentSelector: this.contentSelector,
        musicBaseUrl: this.musicBaseUrl,
      });
    },
  };

  document.addEventListener("DOMContentLoaded", function () {
    Nav.init();
  });
})();