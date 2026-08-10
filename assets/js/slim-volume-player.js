(function () {
  "use strict";

  const SV = {
    root: null,
    audio: null,

    playlist: [],
    albumTracklist: [],
    currentIndex: -1,
    currentTrack: null,
    drawerOpen: false,

    storageKey: "slimVolumePlayerState:v1",
    saveStateTimer: null,
    pendingRestoreTime: null,

    queueDragIndex: null,

    timedLyrics: {
      root: null,
      trackId: 0,
      lines: [],
      activeIndex: -1,
      suppressAutoFollowUntil: 0,
    },
    pendingTimedLyricsSeek: null,

    visualizer: {
      context: null,
      analyser: null,
      source: null,
      data: null,
      frame: null,
      canvasContext: null,
      initialized: false,
      failed: false,

      butterchurnInstance: null,
      butterchurnFailed: false,
    },

    visualizerController: null,
    visualizerVisible: true,
    visualizerVisibilityStorageKey: "slimVolumeVisualizerVisible:v1",
    visualizerResizeObserver: null,
    visualizerResizeFrame: null,
    visualizerResizeTimer: null,

    playerTitleResizeObserver: null,
    playerTitlePanFrame: null,

    els: {},

    init() {
      this.root = document.querySelector("[data-sv-player]");
      if (!this.root) return;

      if (this.root.__svPlayerInitialized) return;
      this.root.__svPlayerInitialized = true;

      this.audio = this.root.querySelector("[data-sv-audio]");
      if (!this.audio) return;

      this.cacheEls();
      this.setupPlayerTitlePanHandling();
      this.setupVisualizerController();
      this.restoreVisualizerVisibility();
      this.setupVisualizerResizeHandling();
      this.setupVisualizerFullscreenHandling();
      this.bindCoreControls();
      this.bindAudioEvents();

      const restored = this.restoreState();

      if (!restored) {
        this.configureFromPage();
      }

      this.bindTrackPlayButtons();
      this.bindTrackQueueButtons();
      this.bindPageQueueButtons();
      this.bindTimedLyrics();
      this.syncNowPlayingUi();
      this.syncPlayButtonState();
      this.renderDrawer();
      this.drawVisualizerIdle();

      document.body.classList.add("sv-player-ready");

      const publicApi = this.publicApi();

      if (this.isDebugEnabled()) {
        publicApi.debugSnapshot = () => {
          return this.getDebugSnapshot();
        };
      }

      window.SVPlayer = publicApi;
    },

    refreshVisualizerEls() {
      if (!this.root) {
        return;
      }

      this.els.visualizer = this.root.querySelector("[data-sv-visualizer]");
      this.els.visualizerCanvas = this.root.querySelector(
        "[data-sv-visualizer-canvas]",
      );
      this.els.visualizerToggle = this.root.querySelector(
        "[data-sv-visualizer-toggle]",
      );
      this.els.visualizerPresetName = this.root.querySelector(
        "[data-sv-visualizer-preset-name]",
      );
      this.els.visualizerNextPreset = this.root.querySelector(
        "[data-sv-visualizer-next-preset]",
      );
      this.els.visualizerFullscreen = this.root.querySelector(
        "[data-sv-visualizer-fullscreen]",
      );
    },

    cacheEls() {
      this.els.title = this.root.querySelector("[data-sv-player-title]");
      this.els.release = this.root.querySelector("[data-sv-player-release]");
      this.els.art = this.root.querySelector("[data-sv-player-art]");

      this.els.playToggle = this.root.querySelector("[data-sv-play-toggle]");
      this.els.playToggleIcon = this.root.querySelector(
        "[data-sv-play-toggle-icon]",
      );
      this.els.prev = this.root.querySelector("[data-sv-prev]");
      this.els.next = this.root.querySelector("[data-sv-next]");

      this.els.seek = this.root.querySelector("[data-sv-seek]");
      this.els.progress = this.root.querySelector("[data-sv-progress]");
      this.els.currentTime = this.root.querySelector("[data-sv-current-time]");
      this.els.duration = this.root.querySelector("[data-sv-duration]");

      this.els.drawer = this.root.querySelector("[data-sv-drawer]");
      this.els.drawerToggle = this.root.querySelector(
        "[data-sv-drawer-toggle]",
      );
      this.els.drawerToggleLabel = this.root.querySelector(
        "[data-sv-drawer-toggle-label]",
      );
      this.els.queueCount = this.root.querySelector("[data-sv-queue-count]");
      this.els.drawerClose = this.root.querySelector("[data-sv-drawer-close]");
      this.els.drawerArt = this.root.querySelector("[data-sv-drawer-art]");
      this.els.drawerTitle = this.root.querySelector("[data-sv-drawer-title]");
      this.els.drawerRelease = this.root.querySelector(
        "[data-sv-drawer-release]",
      );
      this.els.drawerTrackLink = this.root.querySelector(
        "[data-sv-drawer-track-link]",
      );
      this.els.drawerReleaseLink = this.root.querySelector(
        "[data-sv-drawer-release-link]",
      );
      this.els.drawerLinks = this.root.querySelector("[data-sv-drawer-links]");
      this.els.queue = this.root.querySelector("[data-sv-queue]");
      this.els.clearQueue = this.root.querySelector("[data-sv-clear-queue]");

      this.els.visualizerPresetName = this.root.querySelector(
        "[data-sv-visualizer-preset-name]",
      );
      this.els.visualizerNextPreset = this.root.querySelector(
        "[data-sv-visualizer-next-preset]",
      );

      this.refreshVisualizerEls();
    },

    setupPlayerTitlePanHandling() {
      if (!this.els.title) {
        return;
      }

      const schedule = () => {
        this.schedulePlayerTitlePan();
      };

      if (typeof ResizeObserver === "function") {
        this.playerTitleResizeObserver = new ResizeObserver(schedule);
        this.playerTitleResizeObserver.observe(this.els.title);
      } else {
        window.addEventListener("resize", schedule);
      }

      window.addEventListener("orientationchange", schedule);

      if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(schedule).catch(() => {});
      }

      this.schedulePlayerTitlePan();
    },

    schedulePlayerTitlePan() {
      if (!this.els.title) {
        return;
      }

      /*
       * Removing the state before measuring also restarts the animation when
       * the current track changes.
       */
      this.els.title.classList.remove("is-overflowing");
      this.els.title.style.removeProperty("--sv-player-title-travel");
      this.els.title.style.removeProperty("--sv-player-title-duration");

      if (this.playerTitlePanFrame) {
        window.cancelAnimationFrame(this.playerTitlePanFrame);
      }

      this.playerTitlePanFrame = window.requestAnimationFrame(() => {
        this.playerTitlePanFrame = null;
        this.updatePlayerTitlePan();
      });
    },

    updatePlayerTitlePan() {
      const title = this.els.title;

      if (!title) {
        return;
      }

      const isMobile = window.matchMedia(
        "(max-width: 760px)",
      ).matches;

      const reduceMotion = window.matchMedia(
        "(prefers-reduced-motion: reduce)",
      ).matches;

      if (!isMobile || reduceMotion) {
        return;
      }

      const availableWidth = title.clientWidth;
      const contentWidth = title.scrollWidth;
      const overflow = Math.ceil(
        contentWidth - availableWidth,
      );

      /*
       * A few pixels of tolerance prevents tiny font-rendering differences
       * from triggering an unnecessary animation.
       */
      if (!availableWidth || overflow <= 8) {
        return;
      }

      const travel = overflow + 8;

      /*
       * Longer titles receive proportionally more travel time while keeping
       * the movement comfortably slow.
       */
      const duration = Math.min(
        18,
        Math.max(
          8,
          6 + travel / 24,
        ),
      );

      title.style.setProperty(
        "--sv-player-title-travel",
        `-${travel}px`,
      );

      title.style.setProperty(
        "--sv-player-title-duration",
        `${duration.toFixed(2)}s`,
      );

      title.classList.add("is-overflowing");
    },

    publicApi() {
      const app = this;

      return {
        get audioElement() {
          return app.audio;
        },

        play() {
          return app.play();
        },

        pause() {
          return app.pause();
        },

        isPlaying() {
          return !!app.audio && !app.audio.paused && !app.audio.ended;
        },

        seek(seconds) {
          app.seek(seconds);
        },

        getCurrentTime() {
          return app.audio ? app.audio.currentTime || 0 : 0;
        },

        getDuration() {
          return app.audio && Number.isFinite(app.audio.duration)
            ? app.audio.duration
            : 0;
        },

        loadTrack(track, options = {}) {
          app.loadTrack(track, options);
        },

        loadPlaylist(tracks, options = {}) {
          app.loadPlaylist(tracks, options);
        },

        getPlaylist() {
          return app.playlist.slice();
        },

        getAlbumTracklist() {
          return app.albumTracklist.slice();
        },

        getCurrentTrack() {
          return app.getCurrentTrack();
        },

        getPlaylistIndex() {
          return app.currentIndex;
        },

        configureFromPage(options = {}) {
          app.configureFromPage(options);
        },

        refreshPage(options = {}) {
          app.refreshPage(options);
        },

        syncNowPlayingUi() {
          app.syncNowPlayingUi();
        },

        openDrawer() {
          app.setDrawerOpen(true);
        },

        closeDrawer() {
          app.setDrawerOpen(false);
        },

        toggleDrawer() {
          app.setDrawerOpen(!app.drawerOpen);
        },

        renderDrawer() {
          app.renderDrawer();
        },

        startVisualizer() {
          app.startVisualizer();
        },

        stopVisualizer() {
          app.stopVisualizer();
        },
      };
    },

    configureFromPage(options = {}) {
      const preserveActive = !!options.preserveActive;
      const hasActiveAudio = this.hasActiveAudio();

      const script = document.querySelector(
        'script[type="application/json"][data-sv-player-config]',
      );

      if (!script) {
        this.albumTracklist = [];

        if (!preserveActive || !hasActiveAudio) {
          this.playlist = [];
          this.currentIndex = -1;
          this.currentTrack = null;
        }

        return;
      }

      let config = null;

      try {
        config = JSON.parse(script.textContent || "{}");
      } catch (err) {
        console.warn("[SVPlayer] Could not parse page config JSON.", err);
        return;
      }

      if (Array.isArray(config.playlist) && config.playlist.length) {
        this.albumTracklist = config.playlist.slice();

        if (preserveActive && hasActiveAudio) {
          return;
        }

        this.loadPlaylist(config.playlist, {
          startIndex:
            typeof config.currentIndex === "number" ? config.currentIndex : 0,
          autoplay: !!config.autoplay,
          load: false,
        });

        return;
      }

      if (config.track) {
        this.albumTracklist = [config.track];

        if (preserveActive && hasActiveAudio) {
          return;
        }

        this.loadPlaylist([config.track], {
          startIndex: 0,
          autoplay: !!config.autoplay,
          load: false,
        });
      }
    },

    hasActiveAudio() {
      return !!(this.audio && (this.audio.currentSrc || this.audio.src));
    },

    refreshPage(options = {}) {
      this.configureFromPage({
        preserveActive: !!options.preserveActive,
      });

      this.bindTrackPlayButtons();
      this.bindTrackQueueButtons();
      this.bindPageQueueButtons();
      this.bindTimedLyrics();
      this.syncNowPlayingUi();
      this.syncPlayButtonState();
      this.renderDrawer();

      if (!this.hasActiveAudio()) {
        this.drawVisualizerIdle();
      }

      if (this.drawerOpen) {
        this.scheduleVisualizerResize(80);
      }
    },

    bindCoreControls() {
      if (this.els.playToggle) {
        this.els.playToggle.addEventListener("click", () => {
          if (this.audio && !this.audio.paused && !this.audio.ended) {
            this.pause();
          } else {
            this.play();
          }
        });
      }

      if (this.els.prev) {
        this.els.prev.addEventListener("click", (event) => {
          event.preventDefault();
          this.previous();
        });
      }

      if (this.els.next) {
        this.els.next.addEventListener("click", (event) => {
          event.preventDefault();
          this.next();
        });
      }

      if (this.els.seek) {
        this.els.seek.addEventListener("click", (event) => {
          if (!this.audio || !this.audio.duration) return;

          const rect = this.els.seek.getBoundingClientRect();
          if (!rect.width) return;

          const percent = Math.max(
            0,
            Math.min(1, (event.clientX - rect.left) / rect.width),
          );

          this.seek(percent * this.audio.duration);
        });

        this.els.seek.addEventListener("keydown", (event) => {
          if (!this.audio || !this.audio.duration) return;

          const duration = this.audio.duration;
          const current = this.audio.currentTime || 0;
          const smallStep = 5;
          const largeStep = 15;

          let nextTime = null;

          switch (event.key) {
            case "ArrowLeft":
              nextTime = current - smallStep;
              break;

            case "ArrowRight":
              nextTime = current + smallStep;
              break;

            case "PageDown":
              nextTime = current - largeStep;
              break;

            case "PageUp":
              nextTime = current + largeStep;
              break;

            case "Home":
              nextTime = 0;
              break;

            case "End":
              nextTime = duration;
              break;

            default:
              return;
          }

          event.preventDefault();
          this.seek(Math.max(0, Math.min(duration, nextTime)));
        });
      }

      if (this.els.drawerToggle) {
        this.els.drawerToggle.addEventListener("click", () => {
          this.setDrawerOpen(!this.drawerOpen);
        });
      }

      if (this.els.drawerClose) {
        this.els.drawerClose.addEventListener("click", () => {
          this.setDrawerOpen(false);
        });
      }

      //visualizerToggle
      this.root.addEventListener("click", (event) => {
        const toggleButton = event.target.closest(
          "[data-sv-visualizer-toggle]",
        );

        if (toggleButton && this.root.contains(toggleButton)) {
          event.preventDefault();
          this.refreshVisualizerEls();
          this.setVisualizerVisible(!this.visualizerVisible);
          return;
        }

        const nextPresetButton = event.target.closest(
          "[data-sv-visualizer-next-preset]",
        );

        if (nextPresetButton && this.root.contains(nextPresetButton)) {
          event.preventDefault();
          this.refreshVisualizerEls();
          this.nextButterchurnPreset();
        }

        const fullscreenButton = event.target.closest(
          "[data-sv-visualizer-fullscreen]",
        );

        if (fullscreenButton && this.root.contains(fullscreenButton)) {
          event.preventDefault();
          this.refreshVisualizerEls();
          this.toggleVisualizerFullscreen();
          return;
        }

      });

      if (this.els.queue) {
        this.els.queue.addEventListener("click", (event) => {
          const target = event.target instanceof Element ? event.target : null;

          if (!target) return;

          const removeButton = target.closest("[data-sv-remove-queue-index]");

          if (removeButton) {
            event.preventDefault();
            event.stopPropagation();

            const removeIndex = parseInt(
              removeButton.getAttribute("data-sv-remove-queue-index") || "-1",
              10,
            );

            this.removeTrackFromQueue(removeIndex);
            return;
          }

          const button = target.closest("[data-sv-queue-index]");
          if (!button) return;

          event.preventDefault();

          const index = parseInt(
            button.getAttribute("data-sv-queue-index") || "-1",
            10,
          );

          if (!Number.isFinite(index) || index < 0) return;
          if (!this.playlist[index]) return;

          this.loadPlaylist(this.playlist, {
            startIndex: index,
            autoplay: true,
            load: true,
          });
        });
      }

      if (this.els.queue) {
        this.els.queue.addEventListener("dragstart", (event) => {
          const target = event.target instanceof Element ? event.target : null;
          if (!target) return;

          const handle = target.closest("[data-sv-queue-drag-index]");
          if (!handle) return;

          const index = parseInt(
            handle.getAttribute("data-sv-queue-drag-index") || "-1",
            10,
          );

          if (!Number.isFinite(index) || index < 0) return;

          this.queueDragIndex = index;

          const item = handle.closest("[data-sv-queue-item-index]");
          if (item) {
            item.classList.add("is-dragging");
          }

          if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = "move";
            event.dataTransfer.setData("text/plain", String(index));
          }
        });

        this.els.queue.addEventListener("dragover", (event) => {
          if (this.queueDragIndex === null) return;

          const target = event.target instanceof Element ? event.target : null;
          if (!target) return;

          const item = target.closest("[data-sv-queue-item-index]");
          if (!item) return;

          event.preventDefault();

          item.classList.add("is-drop-target");

          if (event.dataTransfer) {
            event.dataTransfer.dropEffect = "move";
          }
        });

        this.els.queue.addEventListener("dragleave", (event) => {
          const target = event.target instanceof Element ? event.target : null;
          if (!target) return;

          const item = target.closest("[data-sv-queue-item-index]");
          if (!item) return;

          item.classList.remove("is-drop-target");
        });

        this.els.queue.addEventListener("drop", (event) => {
          if (this.queueDragIndex === null) return;

          const target = event.target instanceof Element ? event.target : null;
          if (!target) return;

          const item = target.closest("[data-sv-queue-item-index]");
          if (!item) return;

          event.preventDefault();

          const toIndex = parseInt(
            item.getAttribute("data-sv-queue-item-index") || "-1",
            10,
          );

          const fromIndex = this.queueDragIndex;

          this.queueDragIndex = null;
          this.clearQueueDragClasses();

          this.moveQueueTrack(fromIndex, toIndex);
        });

        this.els.queue.addEventListener("dragend", () => {
          this.queueDragIndex = null;
          this.clearQueueDragClasses();
        });
      }

      if (this.els.clearQueue) {
        this.els.clearQueue.addEventListener("click", (event) => {
          event.preventDefault();
          this.clearQueue();
        });
      }

      document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && this.drawerOpen) {
          this.setDrawerOpen(false);
        }

        if (
          this.timedLyrics.root &&
          ["ArrowUp", "ArrowDown", "PageUp", "PageDown", "Home", "End"].includes(
            event.key,
          )
        ) {
          this.suppressTimedLyricsAutoFollow();
        }
      });

      window.addEventListener(
        "wheel",
        () => {
          this.suppressTimedLyricsAutoFollow();
        },
        { passive: true },
      );

      window.addEventListener(
        "touchmove",
        () => {
          this.suppressTimedLyricsAutoFollow();
        },
        { passive: true },
      );
    },

    bindAudioEvents() {
      this.audio.addEventListener("play", () => {
        this.syncPlayButtonState();
        this.syncNowPlayingUi();
        this.syncTimedLyrics({ force: true });
        this.startVisualizer();
      });

      this.audio.addEventListener("pause", () => {
        this.syncPlayButtonState();
        this.renderDrawer();
        this.syncTimedLyrics({ force: true });
        this.stopVisualizer();
        this.drawVisualizerIdle();
        this.scheduleSaveState();
      });

      this.audio.addEventListener("ended", () => {
        this.syncPlayButtonState();
        this.syncTimedLyrics({ force: true });
        this.stopVisualizer();
        this.next();
      });

      this.audio.addEventListener("loadedmetadata", () => {
        this.applyPendingRestoreTime();
        this.applyPendingTimedLyricsSeek();
        this.updateDurationUi();
        this.updateProgressUi();
        this.syncTimedLyrics({ force: true });
        this.renderDrawer();
        this.scheduleSaveState();
      });

      this.audio.addEventListener("timeupdate", () => {
        this.updateProgressUi();
        this.syncTimedLyrics();
        this.scheduleSaveState();
      });
      window.addEventListener("beforeunload", () => {
        if (this.saveStateTimer) {
          window.clearTimeout(this.saveStateTimer);
          this.saveStateTimer = null;
        }

        this.saveState();
      });
    },

    bindTrackPlayButtons() {
      const buttons = document.querySelectorAll('[data-sv-play-button="true"]');

      buttons.forEach((button) => {
        if (button.__svPlayButtonBound) {
          return;
        }

        button.__svPlayButtonBound = true;

        button.addEventListener("click", (event) => {
          if (
            event.defaultPrevented ||
            event.button !== 0 ||
            event.metaKey ||
            event.ctrlKey ||
            event.shiftKey ||
            event.altKey
          ) {
            return;
          }

          event.preventDefault();
          event.stopPropagation();

          const indexSource = button.closest("[data-sv-track-index]") || button;
          const rawIndex = indexSource.getAttribute("data-sv-track-index");
          const index = parseInt(rawIndex || "0", 10);

          if (!Number.isFinite(index) || index < 0) return;

          const tracks = this.albumTracklist.length
            ? this.albumTracklist
            : this.playlist;

          if (!tracks.length || !tracks[index]) return;

          const clickedTrack = tracks[index];
          const currentTrack = this.getCurrentTrack();

          const isSameTrack =
            currentTrack &&
            clickedTrack &&
            String(currentTrack.id) === String(clickedTrack.id);

          const hasLoadedAudio = !!(
            this.audio &&
            (this.audio.currentSrc || this.audio.src)
          );

          if (isSameTrack && hasLoadedAudio) {
            if (this.audio.paused || this.audio.ended) {
              this.play();
            } else {
              this.pause();
            }

            return;
          }

          this.loadPlaylist(tracks, {
            startIndex: index,
            autoplay: true,
            load: true,
          });
        });
      });
    },

    bindTrackQueueButtons() {
      const buttons = document.querySelectorAll(
        '[data-sv-track-queue-button="true"]',
      );

      buttons.forEach((button) => {
        if (button.__svTrackQueueButtonBound) {
          return;
        }

        button.__svTrackQueueButtonBound = true;

        button.addEventListener("click", (event) => {
          event.preventDefault();
          event.stopPropagation();

          const indexSource = button.closest("[data-sv-track-index]") || button;
          const rawIndex = indexSource.getAttribute("data-sv-track-index");
          const index = parseInt(rawIndex || "-1", 10);

          if (!Number.isFinite(index) || index < 0) {
            return;
          }

          const tracks = this.albumTracklist.length
            ? this.albumTracklist
            : this.playlist;

          const track = tracks[index];

          if (!track || !track.audioUrl) {
            return;
          }

          this.appendTrackToQueue(track);
          this.syncTrackQueueButtons();
        });
      });

      this.syncTrackQueueButtons();
    },

    syncTrackQueueButtons() {
      const buttons = document.querySelectorAll(
        '[data-sv-track-queue-button="true"]',
      );

      if (!buttons.length) {
        return;
      }

      const tracks = this.albumTracklist.length
        ? this.albumTracklist
        : this.playlist;

      buttons.forEach((button) => {
        const indexSource = button.closest("[data-sv-track-index]") || button;
        const rawIndex = indexSource.getAttribute("data-sv-track-index");
        const index = parseInt(rawIndex || "-1", 10);

        const track =
          Number.isFinite(index) && index >= 0 ? tracks[index] : null;

        const hasAudio = !!(track && track.audioUrl);
        const isQueued = hasAudio && this.isTrackInQueue(track);

        button.disabled = !hasAudio || isQueued;
        button.classList.toggle("is-disabled", !hasAudio || isQueued);
        button.classList.toggle("is-queued", !!isQueued);

        const isHeroQueueButton = button.classList.contains("sv-track-hero__queue");

        if (!hasAudio) {
          button.textContent = "No Audio";
          button.setAttribute("aria-label", "No audio available");
          return;
        }

        if (isQueued) {
          button.textContent = "Queued";
          button.setAttribute(
            "aria-label",
            "This track is already in the queue",
          );
          return;
        }

        button.textContent = isHeroQueueButton ? "Queue Track" : "Queue";
        button.setAttribute(
          "aria-label",
          `Add ${track.title || "track"} to queue`,
        );
      });
    },

    bindPageQueueButtons() {
      const buttons = document.querySelectorAll(
        '[data-sv-page-queue-button="true"]',
      );

      buttons.forEach((button) => {
        if (button.__svPageQueueButtonBound) {
          return;
        }

        button.__svPageQueueButtonBound = true;

        button.addEventListener("click", (event) => {
          event.preventDefault();

          const action =
            button.getAttribute("data-sv-page-queue-action") || "play";

          const pageQueue = this.albumTracklist.length
            ? this.albumTracklist
            : [];

          if (!pageQueue.length) {
            return;
          }

          if (action === "append") {
            this.appendTracksToQueue(pageQueue);
            this.syncPageQueueButtons();
            return;
          }

          const rawStartIndex = button.getAttribute(
            "data-sv-page-queue-start-index",
          );
          const preferredIndex = parseInt(rawStartIndex || "0", 10);

          const playableIndex = this.getFirstPlayableIndex(
            pageQueue,
            Number.isFinite(preferredIndex) ? preferredIndex : 0,
          );

          if (playableIndex < 0) {
            return;
          }

          this.loadPlaylist(pageQueue, {
            startIndex: playableIndex,
            autoplay: true,
            load: true,
          });

          this.syncPageQueueButtons();
        });
      });

      this.syncPageQueueButtons();
    },

    syncPageQueueButtons() {
      const buttons = document.querySelectorAll(
        '[data-sv-page-queue-button="true"]',
      );

      if (!buttons.length) {
        return;
      }

      const pageQueue = this.albumTracklist.length ? this.albumTracklist : [];

      const playablePageTracks = pageQueue.filter((track) => {
        return !!(track && track.audioUrl);
      });

      const hasPlayableTrack = playablePageTracks.length > 0;
      const activeQueueMatchesPage = this.isSameQueue(this.playlist, pageQueue);

      const allPlayableTracksAlreadyQueued =
        hasPlayableTrack &&
        playablePageTracks.every((track) => {
          return this.isTrackInQueue(track);
        });

      buttons.forEach((button) => {
        const action =
          button.getAttribute("data-sv-page-queue-action") || "play";

        button.disabled = !hasPlayableTrack;
        button.classList.toggle("is-disabled", !hasPlayableTrack);

        if (!hasPlayableTrack) {
          button.textContent = "No Audio Available";
          button.setAttribute(
            "aria-label",
            "No audio available for this release",
          );
          return;
        }

        if (action === "append") {
          button.disabled = allPlayableTracksAlreadyQueued;
          button.classList.toggle(
            "is-disabled",
            allPlayableTracksAlreadyQueued,
          );

        button.classList.toggle("is-queued", allPlayableTracksAlreadyQueued);

        if (allPlayableTracksAlreadyQueued) {
          button.textContent = "Queued";
          button.setAttribute(
            "aria-label",
            "This release is already in the queue",
          );
          return;
        }

        button.textContent = "Queue Release";
        button.setAttribute("aria-label", "Add this release to the queue");
        return;
        }

        if (activeQueueMatchesPage) {
          button.textContent = "Restart Release";
          button.setAttribute("aria-label", "Restart this release");
          return;
        }

        button.textContent = "Play Release";
        button.setAttribute("aria-label", "Play this release");
      });
    },

    getFirstPlayableIndex(tracks, preferredIndex = 0) {
      if (!Array.isArray(tracks) || !tracks.length) {
        return -1;
      }

      const safePreferred = Number.isFinite(preferredIndex)
        ? Math.max(0, Math.min(preferredIndex, tracks.length - 1))
        : 0;

      if (tracks[safePreferred] && tracks[safePreferred].audioUrl) {
        return safePreferred;
      }

      return tracks.findIndex((track) => {
        return !!(track && track.audioUrl);
      });
    },

    isSameQueue(queueA, queueB) {
      if (!Array.isArray(queueA) || !Array.isArray(queueB)) {
        return false;
      }

      if (!queueA.length || queueA.length !== queueB.length) {
        return false;
      }

      return queueA.every((track, index) => {
        return (
          track &&
          queueB[index] &&
          String(track.id) === String(queueB[index].id)
        );
      });
    },

    isTrackInQueue(track) {
      if (!track || !track.id || !Array.isArray(this.playlist)) {
        return false;
      }

      return this.playlist.some((queuedTrack) => {
        return queuedTrack && String(queuedTrack.id) === String(track.id);
      });
    },

    appendTracksToQueue(tracks) {
      if (!Array.isArray(tracks) || !tracks.length) {
        return false;
      }

      const playableTracks = tracks.filter((track) => {
        return !!(track && track.audioUrl);
      });

      if (!playableTracks.length) {
        return false;
      }

      const existingIds = new Set(
        this.playlist
          .filter((track) => track && track.id)
          .map((track) => String(track.id)),
      );

      const newTracks = playableTracks.filter((track) => {
        return !existingIds.has(String(track.id));
      });

      if (!newTracks.length) {
        return false;
      }

      const wasEmpty = !this.playlist.length;

      this.playlist = this.playlist.concat(newTracks);

      if (wasEmpty) {
        this.currentIndex = this.getFirstPlayableIndex(this.playlist, 0);
      }

      this.syncNowPlayingUi();
      this.syncPlayButtonState();
      this.renderDrawer();
      this.scheduleSaveState();

      return true;
    },

    appendTrackToQueue(track) {
      return this.appendTracksToQueue([track]);
    },

    removeTrackFromQueue(index) {
      if (!Array.isArray(this.playlist) || !this.playlist.length) {
        return false;
      }

      if (
        !Number.isFinite(index) ||
        index < 0 ||
        index >= this.playlist.length
      ) {
        return false;
      }

      /*
       * Do not remove the currently playing track.
       * That keeps queue editing from abruptly stopping audio.
       */
      if (index === this.currentIndex && this.hasActiveAudio()) {
        return false;
      }

      this.playlist.splice(index, 1);

      if (!this.playlist.length) {
        this.currentIndex = -1;
        this.currentTrack = null;
      } else if (index < this.currentIndex) {
        this.currentIndex -= 1;
      } else if (this.currentIndex >= this.playlist.length) {
        this.currentIndex = this.playlist.length - 1;
      }

      this.syncNowPlayingUi();
      this.syncPlayButtonState();
      this.renderDrawer();
      this.scheduleSaveState();

      return true;
    },

    clearQueue() {
      if (!Array.isArray(this.playlist) || !this.playlist.length) {
        return false;
      }

      const currentTrack = this.getCurrentTrack();

      /*
       * If something is currently loaded/playing, keep only that track.
       * This clears upcoming/extra queued tracks without stopping playback.
       */
      if (currentTrack && this.hasActiveAudio()) {
        this.playlist = [currentTrack];
        this.currentIndex = 0;
        this.currentTrack = currentTrack;
      } else {
        this.playlist = [];
        this.currentIndex = -1;
        this.currentTrack = null;
      }

      this.syncNowPlayingUi();
      this.syncPlayButtonState();
      this.renderDrawer();
      this.scheduleSaveState();

      return true;
    },

    moveQueueTrack(fromIndex, toIndex) {
      if (!Array.isArray(this.playlist) || this.playlist.length < 2) {
        return false;
      }

      if (
        !Number.isFinite(fromIndex) ||
        !Number.isFinite(toIndex) ||
        fromIndex < 0 ||
        toIndex < 0 ||
        fromIndex >= this.playlist.length ||
        toIndex >= this.playlist.length ||
        fromIndex === toIndex
      ) {
        return false;
      }

      const currentTrack = this.getCurrentTrack();

      const movedItems = this.playlist.splice(fromIndex, 1);
      const movedTrack = movedItems[0];

      if (!movedTrack) {
        return false;
      }

      this.playlist.splice(toIndex, 0, movedTrack);

      if (currentTrack && currentTrack.id) {
        const nextCurrentIndex = this.playlist.findIndex((track) => {
          return track && String(track.id) === String(currentTrack.id);
        });

        this.currentIndex = nextCurrentIndex >= 0 ? nextCurrentIndex : 0;
        this.currentTrack = currentTrack;
      } else {
        this.currentIndex = Math.max(
          0,
          Math.min(this.currentIndex, this.playlist.length - 1),
        );
      }

      this.syncNowPlayingUi();
      this.syncPlayButtonState();
      this.renderDrawer();
      this.scheduleSaveState();

      return true;
    },

    clearQueueDragClasses() {
      if (!this.els.queue) {
        return;
      }

      this.els.queue
        .querySelectorAll(".is-dragging, .is-drop-target")
        .forEach((item) => {
          item.classList.remove("is-dragging", "is-drop-target");
        });
    },

    loadPlaylist(tracks, options = {}) {
      if (!Array.isArray(tracks) || !tracks.length) return;

      this.playlist = tracks.slice();

      const startIndex =
        typeof options.startIndex === "number" ? options.startIndex : 0;

      this.currentIndex = Math.max(
        0,
        Math.min(startIndex, this.playlist.length - 1),
      );

      if (options.load !== false) {
        this.loadTrack(this.playlist[this.currentIndex], {
          autoplay: options.autoplay !== false,
          reset: true,
        });
      }

      this.syncPlayButtonState();
      this.renderDrawer();
      this.scheduleSaveState();
    },

    loadTrack(track, options = {}) {
      if (!track || !track.audioUrl) {
        console.warn("[SVPlayer] Track has no playable audio URL.", track);
        return;
      }

      this.currentTrack = track;

      const existingIndex = this.playlist.findIndex((item) => {
        return item && String(item.id) === String(track.id);
      });

      if (existingIndex !== -1) {
        this.currentIndex = existingIndex;
      } else {
        this.playlist = [track];
        this.currentIndex = 0;
      }

      const cleanCurrentSrc = (this.audio.currentSrc || this.audio.src || "")
        .split(/[?#]/)[0]
        .trim();

      const cleanNextSrc = String(track.audioUrl || "")
        .split(/[?#]/)[0]
        .trim();

      if (cleanCurrentSrc !== cleanNextSrc) {
        this.audio.src = track.audioUrl;
        this.audio.load();
        this.drawVisualizerIdle();
      }

      if (options.reset === true) {
        try {
          this.audio.currentTime = 0;
        } catch (err) {
          console.warn("[SVPlayer] Could not reset currentTime.", err);
        }
      }

      this.syncNowPlayingUi();
      this.syncPlayButtonState();
      this.renderDrawer();
      this.scheduleSaveState();
      this.applyPendingTimedLyricsSeek();

      if (options.autoplay) {
        this.play();
      }
    },

    play() {
      if (!this.audio) return Promise.resolve();

      const hasSrc = !!(this.audio.currentSrc || this.audio.src);

      if (!hasSrc && this.playlist.length) {
        const index =
          this.currentIndex >= 0 && this.currentIndex < this.playlist.length
            ? this.currentIndex
            : 0;

        this.loadTrack(this.playlist[index], {
          autoplay: true,
          reset: true,
        });

        return Promise.resolve();
      }

      if (!hasSrc) return Promise.resolve();

      try {
        const result = this.audio.play();

        if (result && typeof result.catch === "function") {
          result.catch((err) => {
            console.warn("[SVPlayer] Playback failed.", err);
          });
        }

        return result || Promise.resolve();
      } catch (err) {
        console.warn("[SVPlayer] Playback failed.", err);
        return Promise.resolve();
      }
    },

    pause() {
      if (this.audio) {
        this.audio.pause();
      }
    },

    /**
     * Find the next playable track in either direction.
     *
     * Tracks without audio remain visible in album tracklists, but transport
     * controls and automatic advancement skip them.
     *
     * @param {number} startIndex
     * @param {number} direction Use 1 for next or -1 for previous.
     * @returns {number}
     * get or -1 for previous.
     * @returns {number}
     */
    getAdjacentPlayableIndex(startIndex, direction) {
      if (!Array.isArray(this.playlist) || !this.playlist.length) {
        return -1;
      }

      const step = direction < 0 ? -1 : 1;
      let index = Number.isFinite(startIndex)
        ? startIndex + step
        : step > 0
          ? 0
          : this.playlist.length - 1;

      while (index >= 0 && index < this.playlist.length) {
        const track = this.playlist[index];

        if (track && track.audioUrl) {
          return index;
        }

        index += step;
      }

      return -1;
    },

    previous() {
      const previousIndex = this.getAdjacentPlayableIndex(
        this.currentIndex,
        -1,
      );

      if (previousIndex < 0) {
        return;
      }

      this.currentIndex = previousIndex;

      this.loadTrack(this.playlist[this.currentIndex], {
        autoplay: true,
        reset: true,
      });
    },

    next() {
      const nextIndex = this.getAdjacentPlayableIndex(
        this.currentIndex,
        1,
      );

      if (nextIndex < 0) {
        return;
      }

      this.currentIndex = nextIndex;

      this.loadTrack(this.playlist[this.currentIndex], {
        autoplay: true,
        reset: true,
      });
    },

    seek(seconds) {
      if (!this.audio) return;
      if (!Number.isFinite(seconds)) return;

      try {
        this.audio.currentTime = Math.max(0, seconds);
        this.syncTimedLyrics({ force: true });
      } catch (err) {
        console.warn("[SVPlayer] Seek failed.", err);
      }
    },

    getCurrentTrack() {
      if (this.currentTrack) return this.currentTrack;

      const rawSrc = (this.audio.currentSrc || this.audio.src || "")
        .split(/[?#]/)[0]
        .trim();

      if (!rawSrc) return null;

      const match = this.playlist.find((track) => {
        const audioUrl = String(track.audioUrl || "")
          .split(/[?#]/)[0]
          .trim();

        return audioUrl && audioUrl === rawSrc;
      });

      return match || null;
    },

    syncNowPlayingUi() {
      const track = this.getCurrentTrack();

      this.updateMetaUi(track);
      this.syncActiveTrackRows(track);
      this.syncTrackPlayButtons(track);
      this.updateProgressUi();
      this.updateDurationUi();
      this.renderDrawer();
      this.syncTimedLyrics({ force: true });
    },

    bindTimedLyrics() {
      const root = document.querySelector("[data-sv-timed-lyrics]");

      if (this.timedLyrics.root === root && root) {
        this.syncTimedLyrics({ force: true });
        return;
      }

      this.resetTimedLyrics();

      if (!root) {
        return;
      }

      const trackId = parseInt(
        root.getAttribute("data-sv-track-id") || "0",
        10,
      );

      if (!Number.isFinite(trackId) || trackId <= 0) {
        return;
      }

      const lines = Array.from(
        root.querySelectorAll("[data-sv-lyric-start]"),
      )
        .map((element) => {
          const start = Number.parseFloat(
            element.getAttribute("data-sv-lyric-start") || "",
          );

          return {
            element,
            start,
          };
        })
        .filter((line) => Number.isFinite(line.start));

      if (!lines.length) {
        return;
      }

      this.timedLyrics.root = root;
      this.timedLyrics.trackId = trackId;
      this.timedLyrics.lines = lines;
      this.timedLyrics.activeIndex = -1;

      root.classList.add("is-enhanced");

      lines.forEach((line) => {
        const labelText = (line.element.textContent || "").trim();
        const timeLabel = this.formatTime(line.start);

        line.element.setAttribute("role", "button");
        line.element.setAttribute("tabindex", "0");
        line.element.setAttribute(
          "aria-label",
          `Play from ${timeLabel}: ${labelText}`,
        );

        const activate = (event) => {
          event.preventDefault();
          this.playFromTimedLyric(line.start);
        };

        line.element.addEventListener("click", activate);
        line.element.addEventListener("keydown", (event) => {
          if (event.key !== "Enter" && event.key !== " ") {
            return;
          }

          activate(event);
        });
      });

      this.syncTimedLyrics({ force: true });
    },

    resetTimedLyrics() {
      const state = this.timedLyrics;

      if (state.root) {
        state.root.classList.remove(
          "is-enhanced",
          "is-current-track",
          "is-following",
        );
        state.root.removeAttribute("data-sv-lyric-state");
      }

      state.lines.forEach((line) => {
        line.element.classList.remove("is-active", "is-past", "is-upcoming");
        line.element.removeAttribute("aria-current");
      });

      state.root = null;
      state.trackId = 0;
      state.lines = [];
      state.activeIndex = -1;
      state.suppressAutoFollowUntil = 0;
    },

    playFromTimedLyric(start) {
      if (!Number.isFinite(start) || !this.timedLyrics.trackId) {
        return;
      }

      const trackId = this.timedLyrics.trackId;
      const currentTrack = this.getCurrentTrack();
      const isCurrentTrack =
        currentTrack && String(currentTrack.id) === String(trackId);

      this.timedLyrics.suppressAutoFollowUntil = 0;

      if (isCurrentTrack && this.hasActiveAudio()) {
        this.seek(start);
        this.play();
        return;
      }

      const tracks = this.albumTracklist.length
        ? this.albumTracklist
        : this.playlist;

      const index = tracks.findIndex((track) => {
        return track && String(track.id) === String(trackId);
      });

      if (index < 0 || !tracks[index] || !tracks[index].audioUrl) {
        return;
      }

      this.pendingTimedLyricsSeek = {
        trackId,
        start,
        autoplay: true,
      };

      this.loadPlaylist(tracks, {
        startIndex: index,
        autoplay: false,
        load: true,
      });
    },

    applyPendingTimedLyricsSeek() {
      const pending = this.pendingTimedLyricsSeek;

      if (!pending || !this.audio) {
        return;
      }

      const track = this.getCurrentTrack();

      if (!track || String(track.id) !== String(pending.trackId)) {
        return;
      }

      if (this.audio.readyState < 1) {
        return;
      }

      this.pendingTimedLyricsSeek = null;
      this.seek(pending.start);

      if (pending.autoplay) {
        this.play();
      }
    },

    suppressTimedLyricsAutoFollow(duration = 6000) {
      if (!this.timedLyrics.root) {
        return;
      }

      this.timedLyrics.suppressAutoFollowUntil = Date.now() + duration;
    },

    syncTimedLyrics(options = {}) {
      const state = this.timedLyrics;

      if (!state.root || !state.lines.length || !this.audio) {
        return;
      }

      const currentTrack = this.getCurrentTrack();
      const matchesTrack =
        currentTrack &&
        String(currentTrack.id) === String(state.trackId);
      const isPlaying =
        matchesTrack && !this.audio.paused && !this.audio.ended;

      state.root.classList.toggle("is-current-track", !!matchesTrack);
      state.root.classList.toggle("is-following", !!isPlaying);
      state.root.setAttribute(
        "data-sv-lyric-state",
        isPlaying ? "playing" : matchesTrack ? "paused" : "idle",
      );

      if (!matchesTrack) {
        state.activeIndex = -1;

        state.lines.forEach((line) => {
          line.element.classList.remove(
            "is-active",
            "is-past",
            "is-upcoming",
          );
          line.element.removeAttribute("aria-current");
        });

        return;
      }

      const activeIndex = this.getActiveTimedLyricIndex(
        this.audio.currentTime || 0,
      );
      const activeChanged = activeIndex !== state.activeIndex;

      if (!activeChanged && !options.force) {
        return;
      }

      state.activeIndex = activeIndex;

      state.lines.forEach((line, index) => {
        const isActive = index === activeIndex;
        const isPast = activeIndex >= 0 && index < activeIndex;
        const isUpcoming = activeIndex < 0 || index > activeIndex;

        line.element.classList.toggle("is-active", isActive);
        line.element.classList.toggle("is-past", isPast);
        line.element.classList.toggle("is-upcoming", isUpcoming);

        if (isActive) {
          line.element.setAttribute("aria-current", "true");
        } else {
          line.element.removeAttribute("aria-current");
        }
      });

      if (isPlaying && activeChanged && activeIndex >= 0) {
        this.maybeAutoFollowTimedLyric(state.lines[activeIndex].element);
      }
    },

    getActiveTimedLyricIndex(currentTime) {
      const lines = this.timedLyrics.lines;
      let low = 0;
      let high = lines.length - 1;
      let match = -1;

      while (low <= high) {
        const middle = Math.floor((low + high) / 2);

        if (lines[middle].start <= currentTime) {
          match = middle;
          low = middle + 1;
        } else {
          high = middle - 1;
        }
      }

      return match;
    },

    maybeAutoFollowTimedLyric(element) {
      if (
        !element ||
        Date.now() < this.timedLyrics.suppressAutoFollowUntil
      ) {
        return;
      }

      const rect = element.getBoundingClientRect();
      const viewportHeight =
        window.innerHeight || document.documentElement.clientHeight || 0;
      const upperBoundary = viewportHeight * 0.24;
      const lowerBoundary = viewportHeight * 0.72;

      if (rect.top >= upperBoundary && rect.bottom <= lowerBoundary) {
        return;
      }

      const reduceMotion = window.matchMedia(
        "(prefers-reduced-motion: reduce)",
      ).matches;

      element.scrollIntoView({
        behavior: reduceMotion ? "auto" : "smooth",
        block: "center",
        inline: "nearest",
      });
    },

    updateMetaUi(track) {
      if (!track) {
        if (this.els.title) {
          this.els.title.textContent = "Nothing playing";
        }

        if (this.els.release) {
          this.els.release.textContent = "";
        }

        if (this.els.art) {
          this.els.art.innerHTML = "";
        }

        this.schedulePlayerTitlePan();

        return;
      }

      if (this.els.title) {
        this.els.title.textContent =
          track.title || "";
      }

      if (this.els.release) {
        this.els.release.textContent =
          track.release && track.release.title
            ? track.release.title
            : "";
      }

      if (this.els.art) {
        this.els.art.innerHTML = "";

        const artworkUrl =
          track.artwork && track.artwork.url
            ? track.artwork.url
            : "";

        if (artworkUrl) {
          const img = document.createElement("img");

          img.src = artworkUrl;
          img.alt =
            track.artwork && track.artwork.alt
              ? track.artwork.alt
              : track.title || "";

          img.loading = "lazy";
          img.decoding = "async";

          this.els.art.appendChild(img);
        }
      }

      this.schedulePlayerTitlePan();
    },

    syncActiveTrackRows(track) {
      const rows = document.querySelectorAll("[data-sv-track-row]");

      rows.forEach((row) => {
        let isCurrent = false;

        if (track) {
          const rowTrackId = row.getAttribute("data-sv-track-id");
          const rowReleaseId = row.getAttribute("data-sv-release-id");
          const rowTrackSlug = row.getAttribute("data-sv-track-slug");

          const trackIdMatches =
            rowTrackId && String(rowTrackId) === String(track.id);

          const scopedSlugMatches =
            track.release &&
            rowReleaseId &&
            rowTrackSlug &&
            String(rowReleaseId) === String(track.release.id) &&
            String(rowTrackSlug) === String(track.slug);

          isCurrent = !!trackIdMatches || !!scopedSlugMatches;
        }

        row.classList.toggle("sv-track-row--active", isCurrent);
        row.classList.toggle("is-current", isCurrent);

        if (isCurrent) {
          row.setAttribute("aria-current", "true");
        } else {
          row.removeAttribute("aria-current");
        }
      });
    },

    syncTrackPlayButtons(track) {
      const buttons = document.querySelectorAll('[data-sv-play-button="true"]');
      const isPlaying = !!this.audio && !this.audio.paused && !this.audio.ended;

      buttons.forEach((button) => {
        const indexSource = button.closest("[data-sv-track-index]") || button;
        const rawIndex = indexSource.getAttribute("data-sv-track-index");
        const index = parseInt(rawIndex || "0", 10);

        const tracks = this.albumTracklist.length
          ? this.albumTracklist
          : this.playlist;

        const buttonTrack =
          Number.isFinite(index) && index >= 0 ? tracks[index] : null;

        const isCurrent =
          track && buttonTrack && String(track.id) === String(buttonTrack.id);

        const hasAudio = !!(buttonTrack && buttonTrack.audioUrl);

        button.disabled = !hasAudio;
        button.classList.toggle("is-disabled", !hasAudio);

const isHeroPlayButton = button.classList.contains("sv-track-hero__play");

if (!hasAudio) {
  button.textContent = "No Audio";
  button.setAttribute("aria-label", "No audio available");
  button.classList.remove("is-current", "is-playing");
  return;
}

if (isCurrent && isPlaying) {
  button.textContent = isHeroPlayButton ? "Pause Track" : "Pause";
  button.setAttribute("aria-label", `Pause ${track.title || "track"}`);
  button.classList.add("is-playing");
  button.classList.remove("is-current");
  return;
}

if (isCurrent) {
  button.textContent = isHeroPlayButton ? "Play Track" : "Play";
  button.setAttribute("aria-label", `Play ${track.title || "track"}`);
  button.classList.add("is-current");
  button.classList.remove("is-playing");
  return;
}

button.textContent = isHeroPlayButton ? "Play Track" : "Play";
button.setAttribute("aria-label", "Play track");
button.classList.remove("is-current", "is-playing");
      });
    },

    syncPlayButtonState() {
      const isPlaying = !!this.audio && !this.audio.paused && !this.audio.ended;
      const canPlay =
        !!(this.audio && (this.audio.currentSrc || this.audio.src)) ||
        this.playlist.length > 0;

      if (this.els.playToggle) {
        this.els.playToggle.setAttribute(
          "aria-label",
          isPlaying ? "Pause" : "Play",
        );
        this.els.playToggle.disabled = !canPlay;
        this.els.playToggle.classList.toggle("is-disabled", !canPlay);
        this.els.playToggle.classList.toggle("is-playing", isPlaying);
      }

      if (this.els.playToggleIcon) {
        this.els.playToggleIcon.textContent = isPlaying ? "⏸" : "▶";
      }

      const previousPlayableIndex = this.getAdjacentPlayableIndex(
        this.currentIndex,
        -1,
      );

      const nextPlayableIndex = this.getAdjacentPlayableIndex(
        this.currentIndex,
        1,
      );

      if (this.els.prev) {
        const canGoPrevious = previousPlayableIndex >= 0;

        this.els.prev.disabled = !canGoPrevious;
        this.els.prev.classList.toggle(
          "is-disabled",
          !canGoPrevious,
        );

        this.els.prev.setAttribute(
          "aria-label",
          canGoPrevious
            ? "Previous track"
            : "No previous playable track",
        );
      }

      if (this.els.next) {
        const canGoNext = nextPlayableIndex >= 0;

        this.els.next.disabled = !canGoNext;
        this.els.next.classList.toggle(
          "is-disabled",
          !canGoNext,
        );

        this.els.next.setAttribute(
          "aria-label",
          canGoNext
            ? "Next track"
            : "No next playable track",
        );
      }

      this.syncTrackPlayButtons(this.getCurrentTrack());
      this.syncTrackQueueButtons();
      this.syncPageQueueButtons();
      this.renderDrawer();
    },

    updateProgressUi() {
      if (!this.audio || !this.els.progress || !this.els.seek) return;

      const duration = this.audio.duration || 0;
      const current = this.audio.currentTime || 0;

      let percent = 0;

      if (duration && Number.isFinite(duration)) {
        percent = Math.max(0, Math.min(100, (current / duration) * 100));
      }

      this.els.progress.style.width = `${percent}%`;
      this.els.seek.setAttribute("aria-valuenow", String(Math.round(percent)));

      if (this.els.currentTime) {
        this.els.currentTime.textContent = this.formatTime(current);
      }
    },

    updateDurationUi() {
      if (!this.audio || !this.els.duration) return;

      const duration = Number.isFinite(this.audio.duration)
        ? this.audio.duration
        : 0;

      const track = this.getCurrentTrack();

      if (!duration && track && track.duration) {
        this.els.duration.textContent = track.duration;
        return;
      }

      this.els.duration.textContent = this.formatTime(duration);
    },

    setDrawerOpen(open) {
      this.drawerOpen = !!open;

      this.root.classList.toggle("sv-player--drawer-open", this.drawerOpen);
      this.root.setAttribute(
        "data-sv-drawer-state",
        this.drawerOpen ? "open" : "closed",
      );

      document.body.classList.toggle("sv-player-drawer-open", this.drawerOpen);

      if (this.els.drawer) {
        this.els.drawer.hidden = !this.drawerOpen;
      }

      if (this.els.drawerToggle) {
        this.els.drawerToggle.setAttribute(
          "aria-expanded",
          this.drawerOpen ? "true" : "false",
        );
      }

      if (this.drawerOpen) {
        this.scheduleVisualizerResize(60);

        window.setTimeout(() => {
          this.scheduleVisualizerResize();
        }, 260);
      }

      if (this.els.drawerToggleLabel) {
        this.els.drawerToggleLabel.textContent = this.drawerOpen
          ? "Close"
          : "Queue";
      }

      this.renderDrawer();
      this.scheduleSaveState();
    },

    renderDrawer() {
      this.renderDrawerCurrent();
      this.renderDrawerQueue();
    },

    renderDrawerCurrent() {
      const track = this.getCurrentTrack();

      if (!track) {
        if (this.els.drawerArt) this.els.drawerArt.innerHTML = "";

        if (this.els.drawerTitle) {
        this.els.drawerTitle.textContent = this.playlist.length
          ? "Ready to play"
          : "Nothing playing";
        }

        if (this.els.drawerRelease) {
        this.els.drawerRelease.textContent = this.playlist.length
          ? `${this.playlist.length} track${this.playlist.length === 1 ? "" : "s"} in queue`
          : "Add tracks from a release or track page";
        }

        if (this.els.drawerTrackLink) this.els.drawerTrackLink.hidden = true;
        if (this.els.drawerReleaseLink)
          this.els.drawerReleaseLink.hidden = true;
        if (this.els.drawerLinks) this.els.drawerLinks.innerHTML = "";

        return;
      }

      if (this.els.drawerArt) {
        this.els.drawerArt.innerHTML = "";

        const artworkUrl =
          track.artwork && track.artwork.url ? track.artwork.url : "";

        if (artworkUrl) {
          const img = document.createElement("img");
          img.src = artworkUrl;
          img.alt =
            track.artwork && track.artwork.alt
              ? track.artwork.alt
              : track.title || "";
          img.loading = "lazy";
          img.decoding = "async";
          this.els.drawerArt.appendChild(img);
        }
      }

      if (this.els.drawerTitle) {
        this.els.drawerTitle.textContent = track.title || "";
      }

      if (this.els.drawerRelease) {
        this.els.drawerRelease.textContent =
          track.release && track.release.title ? track.release.title : "";
      }

      if (this.els.drawerTrackLink) {
        if (track.trackUrl) {
          this.els.drawerTrackLink.href = track.trackUrl;
          this.els.drawerTrackLink.hidden = false;
        } else {
          this.els.drawerTrackLink.hidden = true;
        }
      }

      if (this.els.drawerReleaseLink) {
        if (track.release && track.release.url) {
          this.els.drawerReleaseLink.href = track.release.url;
          this.els.drawerReleaseLink.hidden = false;
        } else {
          this.els.drawerReleaseLink.hidden = true;
        }
      }

      if (this.els.drawerLinks) {
        this.els.drawerLinks.innerHTML = "";

        const services = {
          spotify: {
            label: "Listen on Spotify",
            icon: `
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <circle cx="12" cy="12" r="9.25"></circle>
                <path d="M6.8 9.15c3.55-1.05 7.75-.75 10.65.85"></path>
                <path d="M7.4 12.25c3.05-.78 6.55-.48 9.05.82"></path>
                <path d="M8.05 15.25c2.5-.58 5.3-.28 7.35.78"></path>
              </svg>
            `,
          },

          appleMusic: {
            label: "Listen on Apple Music",
            icon: `
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <rect x="4.25" y="3.75" width="15.5" height="16.5" rx="3"></rect>
                <path d="M14.75 7.15v8.1"></path>
                <path d="M14.75 8.1 9.6 9.2v6.6"></path>
                <circle cx="8.25" cy="16.25" r="1.7"></circle>
                <circle cx="13.4" cy="14.95" r="1.7"></circle>
              </svg>
            `,
          },

          youtube: {
            label: "Watch on YouTube",
            icon: `
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <rect x="3.25" y="6.25" width="17.5" height="11.5" rx="3.25"></rect>
                <path
                  class="sv-player__service-icon-fill"
                  d="m10 9.25 5 2.75-5 2.75Z"
                ></path>
              </svg>
            `,
          },

          bandcamp: {
            label: "Listen on Bandcamp",
            icon: `
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path
                  class="sv-player__service-icon-fill"
                  d="M7.15 7.25h12.1l-4.4 9.5H2.75Z"
                ></path>
              </svg>
            `,
          },

          purchase: {
            label: "Purchase this track",
            icon: `
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M6.5 8.25h11l1 11h-13Z"></path>
                <path d="M9 8.25V6.8a3 3 0 0 1 6 0v1.45"></path>
              </svg>
            `,
          },

          download: {
            label: "Download this track",
            icon: `
              <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M12 4.5v10.25"></path>
                <path d="m8.25 11.25 3.75 3.75 3.75-3.75"></path>
                <path d="M5.25 18.5h13.5"></path>
              </svg>
            `,
          },
        };

        const links = track.links || {};

        Object.keys(services).forEach((key) => {
          if (!links[key]) return;

          const service = services[key];
          const link = document.createElement("a");

          link.className =
            `sv-player__service-link sv-player__service-link--${key}`;

          link.href = links[key];
          link.innerHTML = service.icon;
          link.setAttribute("aria-label", service.label);
          link.setAttribute("title", service.label);
          link.target = "_blank";
          link.rel = "noopener noreferrer";

          if (key === "download") {
            link.setAttribute("download", "");
          }

          this.els.drawerLinks.appendChild(link);
        });
      }
    },

    renderDrawerQueue() {
      if (!this.els.queue) return;

      const tracks = this.playlist.length ? this.playlist : this.albumTracklist;

      if (this.els.clearQueue) {
        const hasActiveAudio = this.hasActiveAudio();
        const canClear =
          this.playlist.length > 1 ||
          (!hasActiveAudio && this.playlist.length > 0);

        const label = hasActiveAudio ? "Clear Upcoming" : "Clear Queue";

        this.els.clearQueue.hidden = !canClear;
        this.els.clearQueue.disabled = !canClear;
        this.els.clearQueue.textContent = label;
        this.els.clearQueue.setAttribute("aria-label", label);
      }

      if (this.els.queueCount) {
        if (tracks.length) {
          this.els.queueCount.hidden = false;
          this.els.queueCount.textContent = String(tracks.length);
        } else {
          this.els.queueCount.hidden = true;
          this.els.queueCount.textContent = "0";
        }
      }

      this.els.queue.innerHTML = "";

      if (!tracks.length) {
        const empty = document.createElement("li");
        empty.className = "sv-player__queue-empty";
        empty.textContent = "Your queue is empty. Add tracks from a release or track page.";
        this.els.queue.appendChild(empty);
        return;
      }

      const currentTrack = this.getCurrentTrack();
      const isPlaying = !!this.audio && !this.audio.paused && !this.audio.ended;

      tracks.forEach((track, index) => {
        const item = document.createElement("li");
        item.className = "sv-player__queue-item";
        item.draggable = false;
        item.setAttribute("data-sv-queue-item-index", String(index));

        const isCurrent =
          currentTrack && String(currentTrack.id) === String(track.id);

        item.classList.toggle("is-current", !!isCurrent);
        item.classList.toggle("is-playing", !!isCurrent && isPlaying);
        item.classList.toggle(
          "is-queued",
          !currentTrack && index === this.currentIndex,
        );

        const dragHandle = document.createElement("span");
        dragHandle.className = "sv-player__queue-drag";
        dragHandle.setAttribute("aria-hidden", "true");
        dragHandle.setAttribute("draggable", "true");
        dragHandle.setAttribute("data-sv-queue-drag-index", String(index));
        dragHandle.title = "Drag to reorder";
        dragHandle.textContent = "⋮⋮";

        const button = document.createElement("button");
        button.type = "button";
        button.className = "sv-player__queue-button";
        button.setAttribute("data-sv-queue-index", String(index));

        if (!track.audioUrl) {
          button.disabled = true;
          button.classList.add("is-disabled");
        }

        const art = document.createElement("span");
        art.className = "sv-player__queue-art";

        const artworkUrl =
          track.artwork && track.artwork.url ? track.artwork.url : "";

        if (artworkUrl) {
          const img = document.createElement("img");
          img.src = artworkUrl;
          img.alt =
            track.artwork && track.artwork.alt
              ? track.artwork.alt
              : track.title || "";
          img.loading = "lazy";
          img.decoding = "async";
          art.appendChild(img);
        }

        const body = document.createElement("span");
        body.className = "sv-player__queue-body";

        const title = document.createElement("span");
        title.className = "sv-player__queue-title";
        title.textContent = track.title || "";

        const meta = document.createElement("span");
        meta.className = "sv-player__queue-meta";

        const metaParts = [];

        if (track.trackNumber) {
          metaParts.push(`#${track.trackNumber}`);
        }

        if (track.duration) {
          metaParts.push(track.duration);
        }

        if (track.release && track.release.title) {
          metaParts.push(track.release.title);
        }

        meta.textContent = metaParts.join(" • ");

        const status = document.createElement("span");
        status.className = "sv-player__queue-status";

        const isQueuedList = this.playlist.length > 0;

        if (!track.audioUrl) {
          status.textContent = "No audio";
        } else if (isCurrent && isPlaying) {
          status.textContent = "Playing";
        } else if (isCurrent) {
          status.textContent = "Paused";
        } else if (isQueuedList && !currentTrack && index === this.currentIndex) {
          status.textContent = "Ready";
        } else if (isQueuedList && currentTrack && index === this.currentIndex + 1) {
          status.textContent = "Next";
        } else if (isQueuedList) {
          status.textContent = "Queued";
        } else {
          status.textContent = "";
        }

        body.appendChild(title);
        body.appendChild(meta);

        button.appendChild(art);
        button.appendChild(body);
        button.appendChild(status);

        item.appendChild(dragHandle);
        item.appendChild(button);

        const canRemove = !isCurrent || !this.hasActiveAudio();

        if (canRemove) {
          const removeButton = document.createElement("button");
          removeButton.type = "button";
          removeButton.className = "sv-player__queue-remove";
          removeButton.setAttribute(
            "data-sv-remove-queue-index",
            String(index),
          );
          removeButton.setAttribute(
            "aria-label",
            `Remove ${track.title || "track"} from queue`,
          );
          removeButton.textContent = "Remove";

          item.appendChild(removeButton);
        }

        this.els.queue.appendChild(item);
      });
    },

    restoreVisualizerVisibility() {
      let visible = true;

      try {
        const saved = window.localStorage.getItem(
          this.visualizerVisibilityStorageKey,
        );

        if (saved === "hidden") {
          visible = false;
        }
      } catch (err) {
        visible = true;
      }

      this.setVisualizerVisible(visible, { persist: false });
    },

    saveVisualizerVisibility() {
      try {
        window.localStorage.setItem(
          this.visualizerVisibilityStorageKey,
          this.visualizerVisible ? "visible" : "hidden",
        );
      } catch (err) {
        // Ignore storage failures.
      }
    },

    setVisualizerVisible(visible, options = {}) {
      this.refreshVisualizerEls();

      const shouldPersist = options.persist !== false;

      this.visualizerVisible = !!visible;

      if (this.els.visualizer) {
        this.els.visualizer.classList.toggle(
          "is-hidden",
          !this.visualizerVisible,
        );
      }

      this.updateVisualizerToggle();

      if (shouldPersist) {
        this.saveVisualizerVisibility();
      }

      if (!this.visualizerVisible) {
        this.stopVisualizer();
        return;
      }

      this.scheduleVisualizerResize(60);

      if (this.audio && !this.audio.paused && !this.audio.ended) {
        this.startVisualizer();
      } else {
        this.drawVisualizerIdle();
      }
    },

    updateVisualizerToggle() {
      if (!this.els.visualizerToggle) {
        return;
      }

      const isEnabled = this.isVisualizerEnabled();

      this.els.visualizerToggle.disabled = !isEnabled;
      this.els.visualizerToggle.classList.toggle("is-disabled", !isEnabled);
      this.els.visualizerToggle.setAttribute(
        "aria-pressed",
        this.visualizerVisible ? "true" : "false",
      );

      if (!isEnabled) {
        this.els.visualizerToggle.textContent = "Viz Off";
        return;
      }

      this.els.visualizerToggle.textContent = this.visualizerVisible
        ? "Hide Viz"
        : "Show Viz";
    },

    isVisualizerVisible() {
      return this.visualizerVisible && this.isVisualizerEnabled();
    },

    setupVisualizerResizeHandling() {
      window.addEventListener("resize", () => {
        this.scheduleVisualizerResize(120);
      });

      window.addEventListener("orientationchange", () => {
        this.scheduleVisualizerResize(220);
      });

      if (window.ResizeObserver) {
        const observerTarget =
          this.els.visualizer || this.els.visualizerCanvas || this.root;

        if (observerTarget) {
          this.visualizerResizeObserver = new ResizeObserver(() => {
            this.scheduleVisualizerResize(80);
          });

          this.visualizerResizeObserver.observe(observerTarget);
        }
      }
    },

    scheduleVisualizerResize(delay = 0) {
      if (this.visualizerResizeTimer) {
        window.clearTimeout(this.visualizerResizeTimer);
        this.visualizerResizeTimer = null;
      }

      if (this.visualizerResizeFrame) {
        window.cancelAnimationFrame(this.visualizerResizeFrame);
        this.visualizerResizeFrame = null;
      }

      const run = () => {
        this.visualizerResizeFrame = window.requestAnimationFrame(() => {
          this.visualizerResizeFrame = null;
          this.runVisualizerResize();
        });
      };

      if (delay > 0) {
        this.visualizerResizeTimer = window.setTimeout(() => {
          this.visualizerResizeTimer = null;
          run();
        }, delay);

        return;
      }

      run();
    },

    runVisualizerResize() {
      this.refreshVisualizerEls();

      if (!this.isVisualizerVisible()) {
        return;
      }

      this.resizeVisualizer();

      if (this.audio && !this.audio.paused && !this.audio.ended) {
        this.startVisualizer();
        return;
      }

      this.drawVisualizerIdle();
    },

    normalizeVisualizerMode(mode) {
      const allowedModes = ["bars", "butterchurn"];
      const normalized =
        typeof mode === "string" ? mode.toLowerCase().trim() : "bars";

      if (!allowedModes.includes(normalized)) {
        return "bars";
      }

      return normalized;
    },

    getVisualizerMode() {
      const config = window.SVConfig || {};
      const configuredMode =
        typeof config.visualizerMode === "string"
          ? config.visualizerMode
          : "bars";

      return this.normalizeVisualizerMode(configuredMode);
    },

    startVisualizerMode(mode) {
      const normalizedMode = this.normalizeVisualizerMode(mode);

      switch (normalizedMode) {
        case "butterchurn":
          this.startButterchurnVisualizer();
          break;

        case "bars":
        default:
          this.startBarsVisualizer();
          break;
      }
    },

    stopVisualizerMode(mode) {
      const normalizedMode = this.normalizeVisualizerMode(mode);

      switch (normalizedMode) {
        case "butterchurn":
          this.stopButterchurnVisualizer();
          break;

        case "bars":
        default:
          this.stopBarsVisualizer();
          break;
      }
    },

    resizeVisualizerMode(mode) {
      const normalizedMode = this.normalizeVisualizerMode(mode);

      switch (normalizedMode) {
        case "butterchurn":
          this.resizeButterchurnVisualizer();
          break;

        case "bars":
        default:
          this.drawBarsVisualizerIdle();
          break;
      }
    },

    drawVisualizerModeIdle(mode) {
      const normalizedMode = this.normalizeVisualizerMode(mode);

      switch (normalizedMode) {
        case "butterchurn":
          this.drawButterchurnVisualizerIdle();
          break;

        case "bars":
        default:
          this.drawBarsVisualizerIdle();
          break;
      }
    },

    markVisualizerModeUnavailable(mode) {
      const normalizedMode = this.normalizeVisualizerMode(mode);

      switch (normalizedMode) {
        case "butterchurn":
          this.markButterchurnVisualizerUnavailable();
          break;

        case "bars":
        default:
          this.markBarsVisualizerUnavailable();
          break;
      }
    },

    setupVisualizerController() {
      if (this.visualizerController) {
        return;
      }

      this.visualizerController = {
        mode: this.getVisualizerMode(),

        setMode: (mode) => {
          this.visualizerController.mode = this.normalizeVisualizerMode(mode);
        },

        start: () => {
          this.startVisualizerMode(this.visualizerController.mode);
        },

        stop: () => {
          this.stopVisualizerMode(this.visualizerController.mode);
        },

        resize: () => {
          this.resizeVisualizerMode(this.visualizerController.mode);
        },

        destroy: () => {
          this.stopVisualizerMode(this.visualizerController.mode);
          this.destroyButterchurnVisualizer();

          this.visualizer.context = null;
          this.visualizer.analyser = null;
          this.visualizer.source = null;
          this.visualizer.data = null;
          this.visualizer.canvasContext = null;
          this.visualizer.initialized = false;
          this.visualizer.failed = false;
          this.visualizer.butterchurnInstance = null;
          this.visualizer.butterchurnFailed = false;

          if (this.root) {
            this.root.classList.remove("sv-player--visualizer-ready");
            this.root.classList.remove("sv-player--butterchurn-ready");
          }

          if (this.els.visualizer) {
            this.els.visualizer.classList.remove("is-unavailable");
          }
        },

        drawIdle: () => {
          this.drawVisualizerModeIdle(this.visualizerController.mode);
        },

        markUnavailable: () => {
          this.markVisualizerModeUnavailable(this.visualizerController.mode);
        },
      };
    },

    getVisualizerController() {
      if (!this.visualizerController) {
        this.setupVisualizerController();
      }

      return this.visualizerController;
    },

    initVisualizer() {
      if (!this.isVisualizerEnabled()) {
        this.markVisualizerUnavailable();
        return;
      }

      if (this.visualizer.initialized || this.visualizer.failed) {
        return;
      }

      if (!this.audio || !this.els.visualizerCanvas) {
        return;
      }

      const graph = this.getAudioGraph();

      if (!graph || !graph.context || !graph.analyser || !graph.source) {
        this.visualizer.failed = true;
        this.markVisualizerUnavailable();
        return;
      }

      try {
        this.visualizer.context = graph.context;
        this.visualizer.analyser = graph.analyser;
        this.visualizer.source = graph.source;
        this.visualizer.data = new Uint8Array(graph.analyser.frequencyBinCount);
        this.visualizer.canvasContext =
          this.els.visualizerCanvas.getContext("2d");
        this.visualizer.initialized = true;

        this.root.classList.add("sv-player--visualizer-ready");
      } catch (err) {
        console.warn("[SVPlayer] Visualizer unavailable.", err);

        this.visualizer.failed = true;
        this.markVisualizerUnavailable();
      }
    },

    getAudioGraph() {
      if (!this.audio) {
        return null;
      }

      if (this.audio.__svAudioGraph && !this.audio.__svAudioGraph.failed) {
        return this.audio.__svAudioGraph;
      }

      const AudioContext = window.AudioContext || window.webkitAudioContext;

      if (!AudioContext) {
        this.audio.__svAudioGraph = {
          failed: true,
          reason: "web-audio-unavailable",
        };

        return null;
      }

      try {
        const context = new AudioContext();
        const analyser = context.createAnalyser();

        analyser.fftSize = 128;
        analyser.smoothingTimeConstant = 0.82;

        const source = context.createMediaElementSource(this.audio);

        source.connect(analyser);
        analyser.connect(context.destination);

        const graph = {
          context,
          analyser,
          source,
          failed: false,
        };

        this.audio.__svAudioGraph = graph;

        return graph;
      } catch (err) {
        console.warn("[SVPlayer] Audio graph unavailable.", err);

        this.audio.__svAudioGraph = {
          failed: true,
          reason: "audio-graph-unavailable",
          error: err,
        };

        return null;
      }
    },

    startVisualizer() {
      if (!this.isVisualizerVisible()) {
        this.stopVisualizer();
        return;
      }

      const controller = this.getVisualizerController();

      if (controller) {
        controller.start();
      }
    },

    stopVisualizer() {
      const controller = this.getVisualizerController();

      if (controller) {
        controller.stop();
      }
    },

    resizeVisualizer() {
      const controller = this.getVisualizerController();

      if (controller) {
        controller.resize();
      }
    },

    setupVisualizerFullscreenHandling() {
  this.updateVisualizerFullscreenUI();

  document.addEventListener("fullscreenchange", () => {
    this.handleVisualizerFullscreenChange();
  });

  document.addEventListener("webkitfullscreenchange", () => {
    this.handleVisualizerFullscreenChange();
  });
},

getFullscreenElement() {
  return (
    document.fullscreenElement ||
    document.webkitFullscreenElement ||
    document.msFullscreenElement ||
    null
  );
},

canUseFullscreen() {
  return Boolean(
    document.fullscreenEnabled ||
      document.webkitFullscreenEnabled ||
      document.msFullscreenEnabled,
  );
},

isVisualizerFullscreen() {
  this.refreshVisualizerEls();

  return Boolean(
    this.els.visualizer &&
      this.getFullscreenElement() === this.els.visualizer,
  );
},

toggleVisualizerFullscreen() {
  if (this.isVisualizerFullscreen()) {
    this.exitVisualizerFullscreen();
    return;
  }

  this.requestVisualizerFullscreen();
},

requestVisualizerFullscreen() {
  this.refreshVisualizerEls();

  const target = this.els.visualizer;

  if (!target || !this.canUseFullscreen()) {
    return;
  }

  const request =
    target.requestFullscreen ||
    target.webkitRequestFullscreen ||
    target.msRequestFullscreen;

  if (!request) {
    return;
  }

  const result = request.call(target);

  if (result && typeof result.catch === "function") {
    result.catch(() => {
      this.handleVisualizerFullscreenChange();
    });
  }
},

exitVisualizerFullscreen() {
  const exit =
    document.exitFullscreen ||
    document.webkitExitFullscreen ||
    document.msExitFullscreen;

  if (!exit) {
    return;
  }

  const result = exit.call(document);

  if (result && typeof result.catch === "function") {
    result.catch(() => {
      this.handleVisualizerFullscreenChange();
    });
  }
},

handleVisualizerFullscreenChange() {
  const isFullscreen = this.isVisualizerFullscreen();

  document.body.classList.toggle(
    "sv-visualizer-fullscreen-active",
    isFullscreen,
  );

  this.updateVisualizerFullscreenUI();
  this.scheduleVisualizerResize(80);

  window.setTimeout(() => {
    this.scheduleVisualizerResize();
  }, 260);
},

updateVisualizerFullscreenUI() {
  this.refreshVisualizerEls();

  const button = this.els.visualizerFullscreen;

  if (!button) {
    return;
  }

  const available = this.canUseFullscreen();

  button.hidden = !available;

  if (!available) {
    return;
  }

  const isFullscreen = this.isVisualizerFullscreen();
  const enterLabel = button.dataset.svFullscreenLabel || "Fullscreen";
  const exitLabel = button.dataset.svExitFullscreenLabel || "Exit Fullscreen";
  const label = isFullscreen ? exitLabel : enterLabel;

  button.textContent = label;
  button.setAttribute("aria-pressed", isFullscreen ? "true" : "false");
  button.setAttribute("aria-label", label);
},

    destroyVisualizer() {
      const controller = this.getVisualizerController();

      if (controller) {
        controller.destroy();
      }
    },

    drawVisualizerFrame() {
      this.drawBarsVisualizerFrame();
    },

    drawVisualizerIdle() {
      if (!this.isVisualizerVisible()) {
        return;
      }
      const controller = this.getVisualizerController();

      if (controller) {
        controller.drawIdle();
      }
    },

    markVisualizerUnavailable() {
      const controller = this.getVisualizerController();

      if (controller) {
        controller.markUnavailable();
      }
    },

    isButterchurnAdapterAvailable() {
      return !!(
        window.SVButterchurn &&
        typeof window.SVButterchurn.create === "function" &&
        typeof window.SVButterchurn.isAvailable === "function" &&
        window.SVButterchurn.isAvailable()
      );
    },

    formatVisualizerPresetName(name) {
  if (!name || typeof name !== "string") {
    return "";
  }

  let formatted = name
    .replace(/\s+/g, " ")
    .replace(/\s+-\s+/g, " — ")
    .replace(/\s*\[\s*/g, " [")
    .replace(/\s*\]\s*/g, "]")
    .trim();

  const maxLength = 72;

  if (formatted.length > maxLength) {
    formatted = `${formatted.slice(0, maxLength - 1).trim()}…`;
  }

  return formatted;
},

    updateVisualizerPresetUI(presetName = "") {
      this.refreshVisualizerEls();

if (this.els.visualizerPresetName) {
  const mode = this.getVisualizerMode();

  let label = presetName;

  if (!label) {
    label = mode === "butterchurn" ? "Butterchurn" : "Bars";
  }

  const displayLabel = this.formatVisualizerPresetName(label);

  this.els.visualizerPresetName.textContent = displayLabel;
  this.els.visualizerPresetName.title = label;
}

      if (this.els.visualizerNextPreset) {
        const canChangePreset = !!(
          this.getVisualizerMode() === "butterchurn" &&
          this.visualizer.butterchurnInstance &&
          typeof this.visualizer.butterchurnInstance.loadRandomPreset ===
            "function"
        );

        this.els.visualizerNextPreset.hidden = !canChangePreset;
        this.els.visualizerNextPreset.disabled = !canChangePreset;
        this.els.visualizerNextPreset.classList.toggle(
          "is-disabled",
          !canChangePreset,
        );
      }
    },

    nextButterchurnPreset() {
      const instance = this.visualizer.butterchurnInstance;

      if (!instance || typeof instance.loadRandomPreset !== "function") {
        return;
      }

      try {
        const presetName = instance.loadRandomPreset(2.7);
        this.updateVisualizerPresetUI(presetName);
      } catch (err) {
        console.warn("[SVPlayer] Could not load next Butterchurn preset.", err);
      }
    },

    createButterchurnVisualizer() {
      if (this.visualizer.butterchurnInstance) {
        return this.visualizer.butterchurnInstance;
      }

      if (this.visualizer.butterchurnFailed) {
        return null;
      }

      if (!this.isVisualizerEnabled()) {
        return null;
      }

      if (!this.isButterchurnAdapterAvailable()) {
        this.visualizer.butterchurnFailed = true;
        return null;
      }

      if (!this.els.visualizerCanvas && this.root) {
        this.els.visualizerCanvas = this.root.querySelector(
          "[data-sv-visualizer-canvas]",
        );
      }

      if (!this.els.visualizer && this.root) {
        this.els.visualizer = this.root.querySelector("[data-sv-visualizer]");
      }

      if (!this.audio || !this.els.visualizerCanvas) {
        return null;
      }

      const graph = this.getAudioGraph();

      if (!graph || !graph.context || !graph.source) {
        this.visualizer.butterchurnFailed = true;
        return null;
      }

      try {
        this.stopBarsVisualizer();

        const instance = window.SVButterchurn.create({
          canvas: this.els.visualizerCanvas,
          audio: this.audio,
          audioGraph: graph,
        });

        this.visualizer.butterchurnInstance = instance;
        this.refreshVisualizerEls();
        this.updateVisualizerPresetUI(
          typeof instance.getPresetName === "function"
            ? instance.getPresetName()
            : "",
        );
        this.visualizer.butterchurnFailed = false;

        if (this.root) {
          this.root.classList.add("sv-player--visualizer-ready");
          this.root.classList.add("sv-player--butterchurn-ready");
        }

        if (this.els.visualizer) {
          this.els.visualizer.classList.remove("is-unavailable");
        }

        this.resizeButterchurnVisualizer();

        return instance;
      } catch (err) {
        console.warn(
          "[SVPlayer] Butterchurn unavailable. Falling back to bars.",
          err,
        );

        this.visualizer.butterchurnFailed = true;
        this.visualizer.butterchurnInstance = null;

        return null;
      }
    },

    startButterchurnVisualizer() {
      if (!this.isVisualizerEnabled()) {
        return;
      }

      const instance = this.createButterchurnVisualizer();

      if (!instance) {
        this.startBarsVisualizer();
        return;
      }

      try {
        instance.start();
      } catch (err) {
        console.warn(
          "[SVPlayer] Could not start Butterchurn. Falling back to bars.",
          err,
        );

        this.visualizer.butterchurnFailed = true;
        this.destroyButterchurnVisualizer();
        this.startBarsVisualizer();
      }
    },

    stopButterchurnVisualizer() {
      if (this.visualizer.butterchurnInstance) {
        try {
          this.visualizer.butterchurnInstance.stop();
        } catch (err) {
          console.warn("[SVPlayer] Could not stop Butterchurn.", err);
        }
      }
    },

    resizeButterchurnVisualizer() {
      if (!this.visualizer.butterchurnInstance) {
        this.drawBarsVisualizerIdle();
        return;
      }

      try {
        this.visualizer.butterchurnInstance.resize();
      } catch (err) {
        console.warn("[SVPlayer] Could not resize Butterchurn.", err);
      }
    },

    destroyButterchurnVisualizer() {
      if (!this.visualizer.butterchurnInstance) {
        return;
      }

      try {
        this.visualizer.butterchurnInstance.destroy();
      } catch (err) {
        console.warn("[SVPlayer] Could not destroy Butterchurn.", err);
      }

      this.visualizer.butterchurnInstance = null;

      if (this.root) {
        this.root.classList.remove("sv-player--butterchurn-ready");
      }
    },

    drawButterchurnVisualizerIdle() {
      if (this.visualizer.butterchurnInstance) {
        this.resizeButterchurnVisualizer();
        return;
      }

      this.drawBarsVisualizerIdle();
    },

    markButterchurnVisualizerUnavailable() {
      this.visualizer.butterchurnFailed = true;
      this.destroyButterchurnVisualizer();
      this.markBarsVisualizerUnavailable();
    },

    startBarsVisualizer() {
      if (!this.isVisualizerEnabled()) {
        return;
      }

      if (!this.els.visualizerCanvas) {
        return;
      }

      this.updateVisualizerPresetUI(
        this.getVisualizerMode() === "butterchurn" ? "Bars fallback" : "Bars",
      );

      this.initVisualizer();

      if (this.visualizer.failed || !this.visualizer.initialized) {
        this.drawBarsVisualizerIdle();
        return;
      }

      const context = this.visualizer.context;

      if (context && context.state === "suspended") {
        context.resume().catch((err) => {
          console.warn("[SVPlayer] Could not resume AudioContext.", err);
        });
      }

      if (this.visualizer.frame) {
        cancelAnimationFrame(this.visualizer.frame);
        this.visualizer.frame = null;
      }

      const draw = () => {
        this.drawBarsVisualizerFrame();
        this.visualizer.frame = requestAnimationFrame(draw);
      };

      draw();
    },

    stopBarsVisualizer() {
      if (this.visualizer.frame) {
        cancelAnimationFrame(this.visualizer.frame);
        this.visualizer.frame = null;
      }
    },

    drawBarsVisualizerFrame() {
      const canvas = this.els.visualizerCanvas;
      const ctx = this.visualizer.canvasContext;
      const analyser = this.visualizer.analyser;
      const data = this.visualizer.data;

      if (!canvas || !ctx || !analyser || !data) {
        return;
      }

      const width = canvas.width;
      const height = canvas.height;

      analyser.getByteFrequencyData(data);

      ctx.clearRect(0, 0, width, height);

      const bars = 36;
      const gap = 4;
      const barWidth = Math.max(
        3,
        Math.floor((width - gap * (bars - 1)) / bars),
      );
      const step = Math.max(1, Math.floor(data.length / bars));

      for (let i = 0; i < bars; i += 1) {
        let sum = 0;

        for (let j = 0; j < step; j += 1) {
          sum += data[i * step + j] || 0;
        }

        const value = sum / step;
        const normalized = value / 255;
        const eased = Math.pow(normalized, 0.72);

        const barHeight = Math.max(4, eased * height);
        const x = i * (barWidth + gap);
        const y = height - barHeight;

        ctx.fillStyle = "rgba(255, 255, 255, 0.9)";
        this.roundRect(
          ctx,
          x,
          y,
          barWidth,
          barHeight,
          Math.min(8, barWidth / 2),
        );
        ctx.fill();
      }
    },

    drawBarsVisualizerIdle() {
      if (!this.isVisualizerEnabled()) {
        return;
      }

      const canvas = this.els.visualizerCanvas;

      if (!canvas) {
        return;
      }

      const ctx = this.visualizer.canvasContext || canvas.getContext("2d");

      if (!ctx) {
        return;
      }

      this.visualizer.canvasContext = ctx;

      const width = canvas.width;
      const height = canvas.height;
      const bars = 36;
      const gap = 4;
      const barWidth = Math.max(
        3,
        Math.floor((width - gap * (bars - 1)) / bars),
      );

      ctx.clearRect(0, 0, width, height);

      for (let i = 0; i < bars; i += 1) {
        const wave = Math.sin(i * 0.72) * 0.5 + 0.5;
        const barHeight = 8 + wave * 22;
        const x = i * (barWidth + gap);
        const y = height - barHeight;

        ctx.fillStyle = "rgba(255, 255, 255, 0.22)";
        this.roundRect(
          ctx,
          x,
          y,
          barWidth,
          barHeight,
          Math.min(8, barWidth / 2),
        );
        ctx.fill();
      }
    },

    markBarsVisualizerUnavailable() {
      if (this.els.visualizer) {
        this.els.visualizer.classList.add("is-unavailable");
      }

      this.drawBarsVisualizerIdle();
    },

    roundRect(ctx, x, y, width, height, radius) {
      const safeRadius = Math.min(radius, width / 2, height / 2);

      ctx.beginPath();
      ctx.moveTo(x + safeRadius, y);
      ctx.lineTo(x + width - safeRadius, y);
      ctx.quadraticCurveTo(x + width, y, x + width, y + safeRadius);
      ctx.lineTo(x + width, y + height - safeRadius);
      ctx.quadraticCurveTo(
        x + width,
        y + height,
        x + width - safeRadius,
        y + height,
      );
      ctx.lineTo(x + safeRadius, y + height);
      ctx.quadraticCurveTo(x, y + height, x, y + height - safeRadius);
      ctx.lineTo(x, y + safeRadius);
      ctx.quadraticCurveTo(x, y, x + safeRadius, y);
      ctx.closePath();
    },

    isDebugEnabled() {
      return !!(window.SVConfig && window.SVConfig.debug);
    },

    isPersistenceEnabled() {
      return !(window.SVConfig && window.SVConfig.persistence === false);
    },

    isVisualizerEnabled() {
      return !(window.SVConfig && window.SVConfig.visualizer === false);
    },

    getDebugSnapshot() {
      const track = this.getCurrentTrack();

      return {
        playerMounted: !!this.root,
        audioMounted: !!this.audio,
        pageContentCount: document.querySelectorAll("[data-sv-page-content]")
          .length,
        playerCount: document.querySelectorAll("[data-sv-player]").length,

        hasActiveAudio: this.hasActiveAudio(),
        isPlaying: !!this.audio && !this.audio.paused && !this.audio.ended,

        currentTrack: track
          ? {
              id: track.id,
              title: track.title,
              audioUrl: track.audioUrl,
            }
          : null,

        currentIndex: this.currentIndex,
        playlistLength: this.playlist.length,
        albumTracklistLength: this.albumTracklist.length,

        playlistIds: this.playlist.map((item) => item && item.id),
        albumTracklistIds: this.albumTracklist.map((item) => item && item.id),

        drawerOpen: this.drawerOpen,
        pendingRestoreTime: this.pendingRestoreTime,

        queueButtonCount: document.querySelectorAll(
          "[data-sv-track-queue-button='true']",
        ).length,
        playButtonCount: document.querySelectorAll(
          "[data-sv-play-button='true']",
        ).length,

        storageKey: this.storageKey,
        savedStateExists: !!window.localStorage.getItem(this.storageKey),
      };
    },

    formatTime(seconds) {
      if (!Number.isFinite(seconds) || seconds < 0) return "0:00";

      const mins = Math.floor(seconds / 60);
      const secs = Math.floor(seconds % 60);

      return `${mins}:${String(secs).padStart(2, "0")}`;
    },

    restoreState() {
      if (!this.isPersistenceEnabled()) {
        return false;
      }

      if (!window.localStorage) {
        return false;
      }

      let state = null;

      try {
        state = JSON.parse(
          window.localStorage.getItem(this.storageKey) || "null",
        );
      } catch (err) {
        console.warn("[SVPlayer] Could not parse saved player state.", err);
        this.clearSavedState();
        return false;
      }

      if (!state || !Array.isArray(state.playlist) || !state.playlist.length) {
        return false;
      }

      /*
       * Ignore very old state. This keeps the player from restoring something
       * surprising days later.
       */
      const maxAge = 24 * 60 * 60 * 1000;
      const savedAt = typeof state.savedAt === "number" ? state.savedAt : 0;

      if (!savedAt || Date.now() - savedAt > maxAge) {
        this.clearSavedState();
        return false;
      }

      this.playlist = state.playlist.slice();

      if (Array.isArray(state.albumTracklist) && state.albumTracklist.length) {
        this.albumTracklist = state.albumTracklist.slice();
      }

      this.currentIndex =
        typeof state.currentIndex === "number" ? state.currentIndex : 0;

      this.currentIndex = Math.max(
        0,
        Math.min(this.currentIndex, this.playlist.length - 1),
      );

      let track = null;

      if (state.currentTrackId) {
        track = this.playlist.find((item) => {
          return item && String(item.id) === String(state.currentTrackId);
        });
      }

      if (!track) {
        track = this.playlist[this.currentIndex] || null;
      }

      if (!track || !track.audioUrl) {
        this.clearSavedState();
        return false;
      }

      this.currentTrack = track;

      this.audio.src = track.audioUrl;
      this.audio.load();

      this.pendingRestoreTime =
        typeof state.currentTime === "number" && state.currentTime > 0
          ? state.currentTime
          : null;

      this.syncNowPlayingUi();
      this.syncPlayButtonState();
      this.renderDrawer();

      if (state.drawerOpen) {
        this.setDrawerOpen(true);
      }

      return true;
    },

    applyPendingRestoreTime() {
      if (!this.audio || this.pendingRestoreTime === null) {
        return;
      }

      if (!Number.isFinite(this.audio.duration) || this.audio.duration <= 0) {
        return;
      }

      const restoreTime = Math.max(
        0,
        Math.min(this.pendingRestoreTime, this.audio.duration - 1),
      );

      try {
        this.audio.currentTime = restoreTime;
      } catch (err) {
        console.warn("[SVPlayer] Could not restore playback position.", err);
      }

      this.pendingRestoreTime = null;
    },

    clearSavedState() {
      if (!this.isPersistenceEnabled()) {
        return;
      }

      if (!window.localStorage) {
        return;
      }

      try {
        window.localStorage.removeItem(this.storageKey);
      } catch (err) {
        console.warn("[SVPlayer] Could not clear saved player state.", err);
      }
    },

    scheduleSaveState() {
      if (!this.isPersistenceEnabled()) {
        return;
      }

      if (this.saveStateTimer) {
        window.clearTimeout(this.saveStateTimer);
      }

      this.saveStateTimer = window.setTimeout(() => {
        this.saveState();
      }, 250);
    },

    saveState() {
      if (!this.isPersistenceEnabled()) {
        return;
      }

      if (!window.localStorage) {
        return;
      }

      const track = this.getCurrentTrack();
      const hasPlaylist =
        Array.isArray(this.playlist) && this.playlist.length > 0;
      const hasAlbumTracklist =
        Array.isArray(this.albumTracklist) && this.albumTracklist.length > 0;
      const hasAudio = this.hasActiveAudio();

      /*
       * Do not overwrite an existing useful saved state with an empty state.
       * This can happen on pages like /music where no queue is loaded by default.
       */
      if (!track && !hasPlaylist && !hasAlbumTracklist && !hasAudio) {
        return;
      }

      const state = {
        playlist: this.playlist,
        albumTracklist: this.albumTracklist,
        currentIndex: this.currentIndex,
        currentTrackId: track && track.id ? track.id : null,
        currentTime: this.audio ? this.audio.currentTime || 0 : 0,
        drawerOpen: this.drawerOpen,
        savedAt: Date.now(),
      };

      try {
        window.localStorage.setItem(this.storageKey, JSON.stringify(state));
      } catch (err) {
        console.warn("[SVPlayer] Could not save player state.", err);
      }
    },
  };

  document.addEventListener("DOMContentLoaded", function () {
    SV.init();
  });
})();
