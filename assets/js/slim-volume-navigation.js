(function () {
  "use strict";

  const config = window.SVNavigationConfig || {};

  const Nav = {
    contentSelector: config.contentSelector || "[data-sv-page-content]",
    musicBaseUrl: config.musicBaseUrl || "/music/",
    currentRequest: null,
    isNavigating: false,
    lastUrl: window.location.href,
    scrollPositions: {},

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
        window.location.href,
      );

      document.addEventListener("click", (event) => {
        this.handleClick(event);
      });

      window.addEventListener("popstate", () => {
        this.saveScrollPosition(this.lastUrl || window.location.href);

        this.navigate(window.location.href, {
          push: false,
          scrollToTop: false,
          restoreScroll: true,
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

      this.saveScrollPosition(this.lastUrl || window.location.href);

      this.navigate(link.href, {
        push: true,
        scrollToTop: true,
      });
    },

    isIgnoredLink(link) {
      if (!link) return true;

      if (link.closest("#wpadminbar")) return true;
      if (link.closest(".comment-navigation")) return true;
      if (link.closest(".comments-area")) return true;

      if (link.classList.contains("post-edit-link")) return true;
      if (link.classList.contains("comment-reply-link")) return true;

      const href = link.getAttribute("href") || "";

      if (!href) return true;

      const ignoredFragments = [
        "wp-admin",
        "wp-login.php",
        "wp-login",
        "wp-register.php",
        "wp-comments-post.php",
        "customize.php",
        "preview=true",
        "replytocom=",
        "unapproved=",
        "moderation-hash=",
      ];

      return ignoredFragments.some((fragment) => href.includes(fragment));
    },

    shouldHandleLink(link) {
      if (link.hasAttribute("download")) return false;
      if (link.hasAttribute("data-sv-no-ajax")) return false;
      if (this.isIgnoredLink(link)) return false;

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

      if (url.pathname === current.pathname && url.search === current.search) {
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

    getScrollKey(url) {
      try {
        const parsed = new URL(url, window.location.origin);
        return parsed.pathname + parsed.search;
      } catch (err) {
        return String(url);
      }
    },

    saveScrollPosition(url) {
      this.scrollPositions[this.getScrollKey(url)] = {
        x: window.scrollX || window.pageXOffset || 0,
        y: window.scrollY || window.pageYOffset || 0,
      };
    },

    restoreScrollPosition(url) {
      const position = this.scrollPositions[this.getScrollKey(url)] || {
        x: 0,
        y: 0,
      };

      const applyScroll = () => {
        window.scrollTo({
          top: position.y,
          left: position.x,
          behavior: "auto",
        });
      };

      /*
       * Apply more than once because after AJAX content swap,
       * images/fonts/layout can shift slightly after the first paint.
       */
      requestAnimationFrame(() => {
        applyScroll();

        window.setTimeout(applyScroll, 75);
        window.setTimeout(applyScroll, 250);
      });
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
          url,
        );
      }

      this.afterSwap(url, options);
    },

    updateBodyClasses(nextDocument) {
      if (!nextDocument.body) return;

      const keepClasses = ["sv-player-ready", "sv-player-drawer-open"].filter(
        (className) => document.body.classList.contains(className),
      );

      document.body.className = nextDocument.body.className;

      keepClasses.forEach((className) => {
        document.body.classList.add(className);
      });
    },

    afterSwap(url, options = {}) {
      if (
        window.SVPlayer &&
        typeof window.SVPlayer.refreshPage === "function"
      ) {
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

      if (options.restoreScroll) {
        this.restoreScrollPosition(url);
      } else if (options.scrollToTop !== false) {
        window.scrollTo({
          top: 0,
          left: 0,
          behavior: "auto",
        });
      }

      this.lastUrl = url;

      document.dispatchEvent(
        new CustomEvent("slimVolume:navigated", {
          detail: {
            url,
          },
        }),
      );
    },
  };

  document.addEventListener("DOMContentLoaded", function () {
    Nav.init();
  });
})();
