(function () {
  "use strict";

  const config = window.SVNavigationConfig || {};

  const Nav = {
    contentSelector: config.contentSelector || "[data-sv-page-content]",
    musicBaseUrl: config.musicBaseUrl || "/music/",
    currentRequest: null,
    isNavigating: false,

    init() {
      const content = document.querySelector(this.contentSelector);

      if (!content) {
        return;
      }

      if ("scrollRestoration" in window.history) {
        window.history.scrollRestoration = "manual";
      }

      window.history.replaceState(
        {
          svAjax: true,
          url: window.location.href,
        },
        "",
        window.location.href
      );

      document.addEventListener("click", (event) => {
        this.handleClick(event);
      });

      window.addEventListener("popstate", () => {
        this.navigate(window.location.href, {
          push: false,
          scrollToTop: true,
        });
      });


    },

    handleClick(event) {
      if (event.defaultPrevented) return;
      if (event.button !== 0) return;
      if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
      }

      const target = event.target instanceof Element ? event.target : null;
      if (!target) return;

      const link = target.closest("a[href]");
      if (!link) return;

      if (!this.shouldHandleLink(link)) return;

      event.preventDefault();

      this.navigate(link.href, {
        push: true,
        scrollToTop: true,
      });
    },

    shouldHandleLink(link) {
      if (link.hasAttribute("download")) return false;
      if (link.hasAttribute("data-sv-no-ajax")) return false;

      const target = (link.getAttribute("target") || "").toLowerCase();

      if (target && target !== "_self") {
        return false;
      }

      let url;

      try {
        url = new URL(link.href, window.location.href);
      } catch (err) {
        return false;
      }

      if (url.origin !== window.location.origin) {
        return false;
      }

      const current = new URL(window.location.href);

      if (
        url.pathname === current.pathname &&
        url.search === current.search &&
        url.hash
      ) {
        return false;
      }

      return this.isMusicUrl(url);
    },

    isMusicUrl(url) {
      let base;

      try {
        base = new URL(this.musicBaseUrl, window.location.origin);
      } catch (err) {
        return url.pathname.startsWith("/music");
      }

      const basePath = base.pathname.replace(/\/+$/, "");
      const path = url.pathname.replace(/\/+$/, "");

      return path === basePath || path.startsWith(basePath + "/");
    },

    navigate(url, options = {}) {
      if (this.isNavigating && this.currentRequest) {
        this.currentRequest.abort();
      }

      this.isNavigating = true;
      document.body.classList.add("sv-ajax-loading");

      const controller = new AbortController();
      this.currentRequest = controller;

      fetch(url, {
        method: "GET",
        credentials: "same-origin",
        signal: controller.signal,
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      })
        .then((response) => {
          if (!response.ok) {
            throw new Error(`Request failed: ${response.status}`);
          }

          return response.text();
        })
        .then((html) => {
          this.swapPage(html, url, options);
        })
        .catch((err) => {
          if (err.name === "AbortError") {
            return;
          }

          console.warn("[SVNavigation] AJAX navigation failed.", err);
          window.location.href = url;
        })
        .finally(() => {
          this.isNavigating = false;
          this.currentRequest = null;
          document.body.classList.remove("sv-ajax-loading");
        });
    },

    swapPage(html, url, options = {}) {
      const parser = new DOMParser();
      const nextDocument = parser.parseFromString(html, "text/html");

      const currentContent = document.querySelector(this.contentSelector);
      const nextContent = nextDocument.querySelector(this.contentSelector);

      if (!currentContent || !nextContent) {
        window.location.href = url;
        return;
      }

      document.title = nextDocument.title || document.title;

      this.updateBodyClasses(nextDocument);

      currentContent.outerHTML = nextContent.outerHTML;

      if (options.push !== false) {
        window.history.pushState(
          {
            svAjax: true,
            url,
          },
          "",
          url
        );
      }

      this.afterSwap(url, options);
    },

    updateBodyClasses(nextDocument) {
      if (!nextDocument.body) return;

      const keepClasses = [
        "sv-player-ready",
        "sv-player-drawer-open",
      ].filter((className) => document.body.classList.contains(className));

      document.body.className = nextDocument.body.className;

      keepClasses.forEach((className) => {
        document.body.classList.add(className);
      });
    },

    afterSwap(url, options = {}) {
      if (window.SVPlayer && typeof window.SVPlayer.refreshPage === "function") {
        window.SVPlayer.refreshPage({
          preserveActive: true,
        });
      }

      const content = document.querySelector(this.contentSelector);

      if (content) {
        content.setAttribute("tabindex", "-1");
        content.focus({
          preventScroll: true,
        });
      }

      if (options.scrollToTop !== false) {
        window.scrollTo({
          top: 0,
          behavior: "auto",
        });
      }

      document.dispatchEvent(
        new CustomEvent("slimVolume:navigated", {
          detail: {
            url,
          },
        })
      );
    },
  };

  document.addEventListener("DOMContentLoaded", function () {
    Nav.init();
  });
})();