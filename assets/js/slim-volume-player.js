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

    els: {},

    init() {
      this.root = document.querySelector("[data-sv-player]");
      if (!this.root) return;

      if (this.root.__svPlayerInitialized) return;
      this.root.__svPlayerInitialized = true;

      this.audio = this.root.querySelector("[data-sv-audio]");
      if (!this.audio) return;

      this.cacheEls();
      this.bindCoreControls();
      this.bindAudioEvents();
      this.configureFromPage();
      this.bindTrackPlayButtons();
      this.syncNowPlayingUi();
      this.syncPlayButtonState();
      this.renderDrawer();

      document.body.classList.add("sv-player-ready");

      window.SVPlayer = this.publicApi();
    },

    cacheEls() {
      this.els.title = this.root.querySelector("[data-sv-player-title]");
      this.els.release = this.root.querySelector("[data-sv-player-release]");
      this.els.art = this.root.querySelector("[data-sv-player-art]");

      this.els.playToggle = this.root.querySelector("[data-sv-play-toggle]");
      this.els.playToggleIcon = this.root.querySelector("[data-sv-play-toggle-icon]");
      this.els.prev = this.root.querySelector("[data-sv-prev]");
      this.els.next = this.root.querySelector("[data-sv-next]");

      this.els.seek = this.root.querySelector("[data-sv-seek]");
      this.els.progress = this.root.querySelector("[data-sv-progress]");
      this.els.currentTime = this.root.querySelector("[data-sv-current-time]");
      this.els.duration = this.root.querySelector("[data-sv-duration]");

      this.els.drawer = this.root.querySelector("[data-sv-drawer]");
      this.els.drawerToggle = this.root.querySelector("[data-sv-drawer-toggle]");
      this.els.drawerToggleLabel = this.root.querySelector("[data-sv-drawer-toggle-label]");
      this.els.queueCount = this.root.querySelector("[data-sv-queue-count]");
      this.els.drawerClose = this.root.querySelector("[data-sv-drawer-close]");
      this.els.drawerArt = this.root.querySelector("[data-sv-drawer-art]");
      this.els.drawerTitle = this.root.querySelector("[data-sv-drawer-title]");
      this.els.drawerRelease = this.root.querySelector("[data-sv-drawer-release]");
      this.els.drawerTrackLink = this.root.querySelector("[data-sv-drawer-track-link]");
      this.els.drawerReleaseLink = this.root.querySelector("[data-sv-drawer-release-link]");
      this.els.drawerLinks = this.root.querySelector("[data-sv-drawer-links]");
      this.els.queue = this.root.querySelector("[data-sv-queue]");
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

        configureFromPage() {
          app.configureFromPage();
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
      };
    },

    configureFromPage() {
      const script = document.querySelector(
        'script[type="application/json"][data-sv-player-config]'
      );

      if (!script) return;

      let config = null;

      try {
        config = JSON.parse(script.textContent || "{}");
      } catch (err) {
        console.warn("[SVPlayer] Could not parse page config JSON.", err);
        return;
      }

      if (Array.isArray(config.playlist) && config.playlist.length) {
        this.albumTracklist = config.playlist.slice();

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

        this.loadPlaylist([config.track], {
          startIndex: 0,
          autoplay: !!config.autoplay,
          load: false,
        });
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
            Math.min(1, (event.clientX - rect.left) / rect.width)
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

      if (this.els.queue) {
        this.els.queue.addEventListener("click", (event) => {
          const target = event.target instanceof Element ? event.target : null;

          if (!target) return;

          const button = target.closest("[data-sv-queue-index]");
          if (!button) return;

          event.preventDefault();

          const index = parseInt(
            button.getAttribute("data-sv-queue-index") || "-1",
            10
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

      document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && this.drawerOpen) {
          this.setDrawerOpen(false);
        }
      });
    },

    bindAudioEvents() {
      this.audio.addEventListener("play", () => {
        this.syncPlayButtonState();
        this.syncNowPlayingUi();
      });

      this.audio.addEventListener("pause", () => {
        this.syncPlayButtonState();
        this.renderDrawer();
      });

      this.audio.addEventListener("ended", () => {
        this.syncPlayButtonState();
        this.next();
      });

      this.audio.addEventListener("loadedmetadata", () => {
        this.updateDurationUi();
        this.renderDrawer();
      });

      this.audio.addEventListener("timeupdate", () => {
        this.updateProgressUi();
      });
    },

    bindTrackPlayButtons() {
      const buttons = document.querySelectorAll('[data-sv-play-button="true"]');

      buttons.forEach((button) => {
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

    loadPlaylist(tracks, options = {}) {
      if (!Array.isArray(tracks) || !tracks.length) return;

      this.playlist = tracks.slice();

      const startIndex =
        typeof options.startIndex === "number" ? options.startIndex : 0;

      this.currentIndex = Math.max(
        0,
        Math.min(startIndex, this.playlist.length - 1)
      );

      if (options.load !== false) {
        this.loadTrack(this.playlist[this.currentIndex], {
          autoplay: options.autoplay !== false,
          reset: true,
        });
      }

      this.syncPlayButtonState();
      this.renderDrawer();
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

    previous() {
      if (!this.playlist.length || this.currentIndex <= 0) return;

      this.currentIndex -= 1;

      this.loadTrack(this.playlist[this.currentIndex], {
        autoplay: true,
        reset: true,
      });
    },

    next() {
      if (!this.playlist.length) return;
      if (this.currentIndex < 0) return;
      if (this.currentIndex >= this.playlist.length - 1) return;

      this.currentIndex += 1;

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
    },

    updateMetaUi(track) {
      if (!track) {
        if (this.els.title) this.els.title.textContent = "Nothing playing";
        if (this.els.release) this.els.release.textContent = "";
        if (this.els.art) this.els.art.innerHTML = "";
        return;
      }

      if (this.els.title) {
        this.els.title.textContent = track.title || "";
      }

      if (this.els.release) {
        this.els.release.textContent =
          track.release && track.release.title ? track.release.title : "";
      }

      if (this.els.art) {
        this.els.art.innerHTML = "";

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
          this.els.art.appendChild(img);
        }
      }
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

        if (!hasAudio) {
        button.textContent = "No Audio";
        button.setAttribute("aria-label", "No audio available");
        button.classList.remove("is-current", "is-playing");
        return;
        }

        if (isCurrent && isPlaying) {
          button.textContent = "Pause";
          button.setAttribute("aria-label", `Pause ${track.title || "track"}`);
          button.classList.add("is-playing");
          button.classList.remove("is-current");
          return;
        }

        if (isCurrent) {
          button.textContent = "Play";
          button.setAttribute("aria-label", `Play ${track.title || "track"}`);
          button.classList.add("is-current");
          button.classList.remove("is-playing");
          return;
        }

        button.textContent = "Play";
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
            isPlaying ? "Pause" : "Play"
        );
        this.els.playToggle.disabled = !canPlay;
        this.els.playToggle.classList.toggle("is-disabled", !canPlay);
        this.els.playToggle.classList.toggle("is-playing", isPlaying);
        }

        if (this.els.playToggleIcon) {
        this.els.playToggleIcon.textContent = isPlaying ? "⏸" : "▶";
        }

      if (this.els.prev) {
        this.els.prev.disabled = this.currentIndex <= 0;
      }

      if (this.els.next) {
        this.els.next.disabled =
          !this.playlist.length ||
          this.currentIndex < 0 ||
          this.currentIndex >= this.playlist.length - 1;
      }

      this.syncTrackPlayButtons(this.getCurrentTrack());
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
        this.drawerOpen ? "open" : "closed"
      );

      document.body.classList.toggle(
        "sv-player-drawer-open",
        this.drawerOpen
      );

      if (this.els.drawer) {
        this.els.drawer.hidden = !this.drawerOpen;
      }

    if (this.els.drawerToggle) {
    this.els.drawerToggle.setAttribute(
        "aria-expanded",
        this.drawerOpen ? "true" : "false"
    );
    }

    if (this.els.drawerToggleLabel) {
    this.els.drawerToggleLabel.textContent = this.drawerOpen
        ? "Close"
        : "Queue";
    }

      this.renderDrawer();
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
            ? "Queue ready"
            : "Nothing playing";
        }

        if (this.els.drawerRelease) {
          this.els.drawerRelease.textContent = this.playlist.length
            ? `${this.playlist.length} track${this.playlist.length === 1 ? "" : "s"} loaded`
            : "";
        }

        if (this.els.drawerTrackLink) this.els.drawerTrackLink.hidden = true;
        if (this.els.drawerReleaseLink) this.els.drawerReleaseLink.hidden = true;
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

        const labels = {
          spotify: "Spotify",
          appleMusic: "Apple Music",
          youtube: "YouTube",
          bandcamp: "Bandcamp",
          purchase: "Purchase",
          download: "Download",
        };

        const links = track.links || {};

        Object.keys(labels).forEach((key) => {
          if (!links[key]) return;

          const link = document.createElement("a");
          link.className = "sv-link-pill";
          link.href = links[key];
          link.textContent = labels[key];

          if (key === "download") {
            link.setAttribute("download", "");
          } else {
            link.target = "_blank";
            link.rel = "noopener noreferrer";
          }

          this.els.drawerLinks.appendChild(link);
        });
      }
    },

    renderDrawerQueue() {
      if (!this.els.queue) return;

      const tracks = this.playlist.length ? this.playlist : this.albumTracklist;

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
        empty.textContent = "No queue loaded";
        this.els.queue.appendChild(empty);
        return;
      }

      const currentTrack = this.getCurrentTrack();
      const isPlaying = !!this.audio && !this.audio.paused && !this.audio.ended;

      tracks.forEach((track, index) => {
        const item = document.createElement("li");
        item.className = "sv-player__queue-item";

        const isCurrent =
          currentTrack && String(currentTrack.id) === String(track.id);

        item.classList.toggle("is-current", !!isCurrent);
        item.classList.toggle("is-playing", !!isCurrent && isPlaying);
        item.classList.toggle(
          "is-queued",
          !currentTrack && index === this.currentIndex
        );

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

        if (!track.audioUrl) {
        status.textContent = "No audio";
        } else if (isCurrent && isPlaying) {
        status.textContent = "Playing";
        } else if (isCurrent) {
        status.textContent = "Paused";
        } else {
        status.textContent = "";
        }

        body.appendChild(title);
        body.appendChild(meta);

        button.appendChild(art);
        button.appendChild(body);
        button.appendChild(status);

        item.appendChild(button);
        this.els.queue.appendChild(item);
      });
    },

    formatTime(seconds) {
      if (!Number.isFinite(seconds) || seconds < 0) return "0:00";

      const mins = Math.floor(seconds / 60);
      const secs = Math.floor(seconds % 60);

      return `${mins}:${String(secs).padStart(2, "0")}`;
    },
  };

  document.addEventListener("DOMContentLoaded", function () {
    SV.init();
  });
})();