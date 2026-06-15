(function () {
  "use strict";

  const SV = {
    root: null,
    audio: null,

    playlist: [],
    albumTracklist: [],
    currentIndex: -1,
    currentTrack: null,

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

      document.body.classList.add("sv-player-ready");

      window.SVPlayer = this.publicApi();
    },

    cacheEls() {
      this.els.title = this.root.querySelector("[data-sv-player-title]");
      this.els.release = this.root.querySelector("[data-sv-player-release]");
      this.els.art = this.root.querySelector("[data-sv-player-art]");

      this.els.playToggle = this.root.querySelector("[data-sv-play-toggle]");
      this.els.prev = this.root.querySelector("[data-sv-prev]");
      this.els.next = this.root.querySelector("[data-sv-next]");

      this.els.seek = this.root.querySelector("[data-sv-seek]");
      this.els.progress = this.root.querySelector("[data-sv-progress]");
      this.els.currentTime = this.root.querySelector("[data-sv-current-time]");
      this.els.duration = this.root.querySelector("[data-sv-duration]");
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
      }
    },

    bindAudioEvents() {
      this.audio.addEventListener("play", () => {
        this.syncPlayButtonState();
        this.syncNowPlayingUi();
      });

      this.audio.addEventListener("pause", () => {
        this.syncPlayButtonState();
      });

      this.audio.addEventListener("ended", () => {
        this.syncPlayButtonState();
        this.next();
      });

      this.audio.addEventListener("loadedmetadata", () => {
        this.updateDurationUi();
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
        return this.audio.play();
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
      this.updateProgressUi();
      this.updateDurationUi();
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

    syncPlayButtonState() {
      const isPlaying = !!this.audio && !this.audio.paused && !this.audio.ended;
      const canPlay =
        !!(this.audio && (this.audio.currentSrc || this.audio.src)) ||
        this.playlist.length > 0;

      if (this.els.playToggle) {
        this.els.playToggle.textContent = isPlaying ? "Pause" : "Play";
        this.els.playToggle.setAttribute(
          "aria-label",
          isPlaying ? "Pause" : "Play"
        );
        this.els.playToggle.disabled = !canPlay;
        this.els.playToggle.classList.toggle("is-disabled", !canPlay);
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

      this.els.duration.textContent = this.formatTime(duration);
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