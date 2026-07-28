(() => {
  "use strict";

  const config = window.SVTimedLyricsAdmin || {};
  const workspace = document.querySelector("[data-sv-timed-lyrics-workspace]");

  if (!workspace || !config.trackId || !config.document) {
    return;
  }

  const strings = config.strings || {};
  const audio = workspace.querySelector("[data-sv-timed-lyrics-audio]");
  const currentLyric = workspace.querySelector("[data-sv-current-lyric]");
  const nextLyric = workspace.querySelector("[data-sv-next-lyric]");
  const currentTime = workspace.querySelector("[data-sv-current-time]");
  const selectedTime = workspace.querySelector("[data-sv-selected-time]");
  const modeNotice = workspace.querySelector("[data-sv-sync-mode]");
  const saveStatus = workspace.querySelector("[data-sv-save-status]");
  const startButton = workspace.querySelector("[data-sv-start-sync]");
  const playButton = workspace.querySelector("[data-sv-toggle-play]");
  const reviewButton = workspace.querySelector("[data-sv-review]");
  const undoButton = workspace.querySelector("[data-sv-undo]");
  const clearButton = workspace.querySelector("[data-sv-clear-line]");
  const resetButton = workspace.querySelector("[data-sv-reset-timings]");
  const saveDraftButton = workspace.querySelector("[data-sv-save-draft]");
  const saveCompleteButton = workspace.querySelector("[data-sv-save-complete]");
  const nudgeButtons = Array.from(workspace.querySelectorAll("[data-sv-nudge]"));
  const rowElements = Array.from(workspace.querySelectorAll("[data-sv-lyric-row]"));
  const statusCard = workspace.querySelector('[data-sv-summary-card="status"]');
  const timedCountCard = workspace.querySelector('[data-sv-summary-card="timed-count"]');

  if (!audio || !Array.isArray(config.document.lines)) {
    return;
  }

  const clone = (value) => JSON.parse(JSON.stringify(value));
  const roundTime = (value) => Math.round(Number(value) * 1000) / 1000;

  const state = {
    document: clone(config.document),
    selectedIndex: -1,
    armed: false,
    reviewing: false,
    dirty: false,
    saving: false,
    history: [],
    reviewIndex: -1,
  };

  const syncableIndices = state.document.lines
    .map((line, index) => (
      line
      && line.type === "line"
      && String(line.text || "").trim() !== ""
        ? index
        : -1
    ))
    .filter((index) => index >= 0);

  const rowByIndex = new Map(
    rowElements.map((row) => [Number(row.dataset.lineIndex), row])
  );

  const isNumber = (value) => (
    typeof value === "number"
    && Number.isFinite(value)
  );

  const isEditableTarget = (target) => {
    if (!(target instanceof Element)) {
      return false;
    }

    return Boolean(
      target.closest(
        'input, textarea, select, button, a, audio, [contenteditable="true"]'
      )
    );
  };

  const formatTime = (seconds) => {
    if (!isNumber(seconds) || seconds < 0) {
      return "—";
    }

    const minutes = Math.floor(seconds / 60);
    const remainder = seconds - (minutes * 60);

    return `${String(minutes).padStart(2, "0")}:${remainder
      .toFixed(3)
      .padStart(6, "0")}`;
  };

  const lineAt = (index) => state.document.lines[index] || null;

  const syncablePosition = (index) => syncableIndices.indexOf(index);

  const previousSyncableIndex = (index) => {
    const position = syncablePosition(index);
    return position > 0 ? syncableIndices[position - 1] : -1;
  };

  const nextSyncableIndex = (index) => {
    const position = syncablePosition(index);
    return position >= 0 && position < syncableIndices.length - 1
      ? syncableIndices[position + 1]
      : -1;
  };

  const firstUntimedIndex = () => {
    const untimed = syncableIndices.find((index) => {
      const line = lineAt(index);
      return !line || !isNumber(line.start);
    });

    return typeof untimed === "number"
      ? untimed
      : (syncableIndices[0] ?? -1);
  };

  const countTimedLines = () => syncableIndices.reduce((count, index) => {
    const line = lineAt(index);
    return count + (line && isNumber(line.start) ? 1 : 0);
  }, 0);

  const allLinesTimed = () => (
    syncableIndices.length > 0
    && countTimedLines() === syncableIndices.length
  );

  const setMode = (message, type = "") => {
    if (!modeNotice) {
      return;
    }

    modeNotice.textContent = message || "";
    modeNotice.dataset.state = type;
  };

  const setSaveStatus = (message, type = "") => {
    if (!saveStatus) {
      return;
    }

    saveStatus.textContent = message || "";
    saveStatus.dataset.state = type;
  };

  const updateSummary = (status = null) => {
    const timedCount = countTimedLines();

    if (timedCountCard) {
      const value = timedCountCard.querySelector(
        ".sv-timed-lyrics-summary-card__value"
      );

      if (value) {
        value.textContent = `${timedCount} / ${syncableIndices.length}`;
        value.className = [
          "sv-timed-lyrics-summary-card__value",
          `sv-timed-lyrics-summary-card__value--${timedCount > 0 ? "draft" : "none"}`,
        ].join(" ");
      }
    }

    if (statusCard && status) {
      const value = statusCard.querySelector(
        ".sv-timed-lyrics-summary-card__value"
      );

      if (value) {
        value.textContent = status.label || status.value || "";
        value.className = [
          "sv-timed-lyrics-summary-card__value",
          `sv-timed-lyrics-summary-card__value--${status.className || status.value || "none"}`,
        ].join(" ");
      }
    }
  };

  const updateRows = () => {
    rowByIndex.forEach((row, index) => {
      const line = lineAt(index);
      const time = row.querySelector("[data-sv-line-time]");
      const isSelected = index === state.selectedIndex;
      const isReviewActive = state.reviewing && index === state.reviewIndex;
      const timed = Boolean(line && isNumber(line.start));

      row.classList.toggle("is-selected", isSelected);
      row.classList.toggle("is-timed", timed);
      row.classList.toggle("is-review-active", isReviewActive);

      if (time) {
        time.textContent = timed ? formatTime(line.start) : "—";
      }

      if (row.getAttribute("role") === "button") {
        row.setAttribute("aria-current", isSelected ? "true" : "false");
      }
    });
  };

  const updatePreview = () => {
    const line = lineAt(state.selectedIndex);
    const nextIndex = nextSyncableIndex(state.selectedIndex);
    const next = lineAt(nextIndex);

    if (currentLyric) {
      currentLyric.textContent = line && line.type === "line"
        ? String(line.text || "")
        : "—";
    }

    if (nextLyric) {
      nextLyric.textContent = next && next.type === "line"
        ? String(next.text || "")
        : "—";
    }

    if (selectedTime) {
      selectedTime.textContent = line && isNumber(line.start)
        ? formatTime(line.start)
        : "—";
    }

    updateRows();
  };

  const selectLine = (
    index,
    { seek = false, scroll = false, focus = false } = {}
  ) => {
    if (!syncableIndices.includes(index)) {
      return;
    }

    state.selectedIndex = index;
    const line = lineAt(index);
    const row = rowByIndex.get(index);

    if (seek && line && isNumber(line.start)) {
      audio.currentTime = Math.max(0, line.start);
    }

    if (scroll && row) {
      row.scrollIntoView({ behavior: "smooth", block: "center" });
    }

    if (focus && row) {
      row.focus({ preventScroll: true });
    }

    updatePreview();
  };

  const markDirty = () => {
    state.dirty = true;
    setSaveStatus(strings.dirty || "Unsaved timing changes.", "dirty");
    updateSummary();
  };

  const timestampFits = (index, timestamp) => {
    const previousIndex = previousSyncableIndex(index);
    const nextIndex = nextSyncableIndex(index);
    const previous = lineAt(previousIndex);
    const next = lineAt(nextIndex);

    if (
      previous
      && isNumber(previous.start)
      && timestamp <= previous.start
    ) {
      return false;
    }

    if (
      next
      && isNumber(next.start)
      && timestamp >= next.start
    ) {
      return false;
    }

    return true;
  };

  const markSelectedLine = () => {
    const line = lineAt(state.selectedIndex);

    if (!line || line.type !== "line") {
      return;
    }

    const timestamp = roundTime(Math.max(0, audio.currentTime || 0));

    if (!timestampFits(state.selectedIndex, timestamp)) {
      setMode(
        strings.orderConflict
          || "That timestamp would overlap another lyric line.",
        "error"
      );
      return;
    }

    state.history.push({
      index: state.selectedIndex,
      previousStart: isNumber(line.start) ? line.start : null,
    });

    line.start = timestamp;
    markDirty();

    const nextIndex = nextSyncableIndex(state.selectedIndex);

    if (nextIndex >= 0) {
      selectLine(nextIndex, { scroll: true });
      setMode(
        strings.armed
          || "Sync armed. Press Space slightly before each lyric should activate.",
        "armed"
      );
      return;
    }

    state.armed = false;
    startButton.textContent = strings.resumeSync || "Resume Sync";
    setMode(
      strings.finished
        || "Timing pass reached the final lyric. Review or save your work.",
      "finished"
    );
    updatePreview();
  };

  const undoLatest = () => {
    let entry = state.history.pop();

    if (!entry) {
      const timedIndices = syncableIndices.filter((index) => {
        const line = lineAt(index);
        return line && isNumber(line.start);
      });

      const latestIndex = timedIndices[timedIndices.length - 1];

      if (typeof latestIndex === "number") {
        entry = { index: latestIndex, previousStart: null };
      }
    }

    if (!entry) {
      return;
    }

    const line = lineAt(entry.index);

    if (!line) {
      return;
    }

    line.start = isNumber(entry.previousStart)
      ? entry.previousStart
      : null;

    state.armed = false;
    startButton.textContent = strings.resumeSync || "Resume Sync";
    selectLine(entry.index, { seek: isNumber(line.start), scroll: true });
    markDirty();
  };

  const nudgeSelected = (delta) => {
    const line = lineAt(state.selectedIndex);

    if (!line || !isNumber(line.start)) {
      setMode(
        strings.noTimestamp
          || "Select a timed lyric line before adjusting it.",
        "error"
      );
      return;
    }

    const timestamp = roundTime(Math.max(0, line.start + delta));

    if (!timestampFits(state.selectedIndex, timestamp)) {
      setMode(
        strings.orderConflict
          || "That timestamp would overlap another lyric line.",
        "error"
      );
      return;
    }

    line.start = timestamp;
    audio.currentTime = timestamp;
    markDirty();
    updatePreview();
  };

  const clearSelected = () => {
    const line = lineAt(state.selectedIndex);

    if (!line || !isNumber(line.start)) {
      return;
    }

    state.history.push({
      index: state.selectedIndex,
      previousStart: line.start,
    });

    line.start = null;
    markDirty();
    updatePreview();
  };

  const resetTimings = () => {
    const confirmed = window.confirm(
      strings.confirmReset
        || "Clear every lyric timestamp in this workspace?"
    );

    if (!confirmed) {
      return;
    }

    syncableIndices.forEach((index) => {
      const line = lineAt(index);
      if (line) {
        line.start = null;
      }
    });

    state.history = [];
    state.armed = false;
    state.reviewing = false;
    state.reviewIndex = -1;
    audio.pause();
    startButton.textContent = strings.startSync || "Start Sync";
    reviewButton.textContent = strings.review || "Review";
    selectLine(syncableIndices[0] ?? -1, { scroll: true });
    markDirty();
    setMode(strings.ready || "Ready.", "ready");
  };

  const stopSync = () => {
    state.armed = false;
    startButton.textContent = strings.resumeSync || "Resume Sync";
    setMode(strings.ready || "Ready.", "ready");
  };

  const startSync = async () => {
    if (state.armed) {
      stopSync();
      return;
    }

    state.reviewing = false;
    state.reviewIndex = -1;
    reviewButton.textContent = strings.review || "Review";

    if (!syncableIndices.includes(state.selectedIndex)) {
      selectLine(firstUntimedIndex(), { scroll: true });
    } else if (
      countTimedLines() < syncableIndices.length
      && allLinesTimed() === false
    ) {
      const firstUntimed = firstUntimedIndex();
      const selected = lineAt(state.selectedIndex);

      if (selected && isNumber(selected.start) && firstUntimed >= 0) {
        selectLine(firstUntimed, { scroll: true });
      }
    }

    state.armed = true;
    startButton.textContent = strings.stopSync || "Stop Sync";
    setMode(
      strings.armed
        || "Sync armed. Press Space slightly before each lyric should activate.",
      "armed"
    );

    try {
      await audio.play();
    } catch (error) {
      state.armed = false;
      startButton.textContent = strings.resumeSync || "Resume Sync";
      setMode(
        strings.audioUnavailable || "The audio source is unavailable.",
        "error"
      );
    }
  };

  const togglePlay = async () => {
    if (audio.paused) {
      try {
        await audio.play();
      } catch (error) {
        setMode(
          strings.audioUnavailable || "The audio source is unavailable.",
          "error"
        );
      }
      return;
    }

    audio.pause();
  };

  const activeReviewIndex = (time) => {
    let active = -1;

    syncableIndices.forEach((index) => {
      const line = lineAt(index);

      if (line && isNumber(line.start) && line.start <= time) {
        active = index;
      }
    });

    return active;
  };

  const toggleReview = async () => {
    if (state.reviewing) {
      state.reviewing = false;
      state.reviewIndex = -1;
      reviewButton.textContent = strings.review || "Review";
      setMode(strings.ready || "Ready.", "ready");
      updateRows();
      return;
    }

    state.armed = false;
    startButton.textContent = strings.resumeSync || "Resume Sync";
    state.reviewing = true;
    reviewButton.textContent = strings.stopReview || "Stop Review";
    setMode(
      strings.reviewing
        || "Review mode. Playback follows the saved timestamps.",
      "reviewing"
    );

    try {
      await audio.play();
    } catch (error) {
      state.reviewing = false;
      reviewButton.textContent = strings.review || "Review";
      setMode(
        strings.audioUnavailable || "The audio source is unavailable.",
        "error"
      );
    }
  };

  const setSaving = (saving) => {
    state.saving = saving;

    [saveDraftButton, saveCompleteButton, resetButton].forEach((button) => {
      if (button) {
        button.disabled = saving;
      }
    });
  };

  const saveDocument = async (status) => {
    if (state.saving) {
      return;
    }

    if (status === "complete" && !allLinesTimed()) {
      setSaveStatus(
        strings.allLinesRequired
          || "Every lyric line needs a timestamp before completion.",
        "error"
      );
      return;
    }

    const duration = Number.isFinite(audio.duration)
      ? roundTime(audio.duration)
      : 0;

    const payload = clone(state.document);
    payload.status = status;
    payload.audio = payload.audio || {};
    payload.audio.duration = duration;
    payload.lines = state.document.lines;

    setSaving(true);
    setSaveStatus(strings.saving || "Saving timed lyrics…", "saving");

    const body = new URLSearchParams();
    body.set("action", config.action);
    body.set("nonce", config.nonce);
    body.set("track_id", String(config.trackId));
    body.set("document", JSON.stringify(payload));

    try {
      const response = await fetch(config.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        },
        body: body.toString(),
      });

      const result = await response.json();

      if (!response.ok || !result.success) {
        const message = result
          && result.data
          && result.data.message
          ? result.data.message
          : (strings.saveFailed || "Timed lyrics could not be saved.");

        throw new Error(message);
      }

      const data = result.data || {};

      if (data.document && Array.isArray(data.document.lines)) {
        state.document = clone(data.document);
      }

      state.dirty = false;
      state.history = [];

      setSaveStatus(
        data.message
          || (
            status === "complete"
              ? (strings.savedComplete || "Timed lyrics are complete.")
              : (strings.savedDraft || "Timed lyrics draft saved.")
          ),
        "success"
      );

      updateSummary({
        value: data.status || status,
        label: data.statusLabel || data.status || status,
        className: data.statusClass || data.status || status,
      });
      updatePreview();
    } catch (error) {
      setSaveStatus(
        error instanceof Error
          ? error.message
          : (strings.saveFailed || "Timed lyrics could not be saved."),
        "error"
      );
    } finally {
      setSaving(false);
    }
  };

  rowElements.forEach((row) => {
    const index = Number(row.dataset.lineIndex);

    if (!syncableIndices.includes(index)) {
      return;
    }

    row.addEventListener("click", () => {
      selectLine(index, { seek: true });
    });

    row.addEventListener("keydown", (event) => {
      if (event.key !== "Enter") {
        return;
      }

      event.preventDefault();
      event.stopPropagation();
      selectLine(index, { seek: true });
    });
  });

  startButton?.addEventListener("click", () => {
    startSync();

    window.requestAnimationFrame(() => {
      startButton.blur();
    });
  });
  playButton?.addEventListener("click", togglePlay);
  reviewButton?.addEventListener("click", toggleReview);
  undoButton?.addEventListener("click", undoLatest);
  clearButton?.addEventListener("click", clearSelected);
  resetButton?.addEventListener("click", resetTimings);
  saveDraftButton?.addEventListener("click", () => saveDocument("draft"));
  saveCompleteButton?.addEventListener("click", () => saveDocument("complete"));

  nudgeButtons.forEach((button) => {
    button.addEventListener("click", () => {
      nudgeSelected(Number(button.dataset.svNudge || 0));
    });
  });

  audio.addEventListener("timeupdate", () => {
    if (currentTime) {
      currentTime.textContent = formatTime(audio.currentTime);
    }

    if (!state.reviewing) {
      return;
    }

    const activeIndex = activeReviewIndex(audio.currentTime);

    if (activeIndex >= 0 && activeIndex !== state.reviewIndex) {
      state.reviewIndex = activeIndex;
      selectLine(activeIndex, { scroll: true });
    }
  });

  audio.addEventListener("ended", () => {
    state.armed = false;
    state.reviewing = false;
    state.reviewIndex = -1;
    startButton.textContent = strings.resumeSync || "Resume Sync";
    reviewButton.textContent = strings.review || "Review";
    setMode(strings.finished || "Playback ended.", "finished");
    updateRows();
  });

  document.addEventListener("keydown", (event) => {
    if (isEditableTarget(event.target)) {
      return;
    }

    if (event.code === "Space") {
      if (!state.armed) {
        return;
      }

      event.preventDefault();
      markSelectedLine();
      return;
    }

    if (event.key === "Enter") {
      event.preventDefault();
      togglePlay();
      return;
    }

    if (event.key === "Backspace") {
      event.preventDefault();
      undoLatest();
      return;
    }

    if (event.key === "ArrowLeft" || event.key === "ArrowRight") {
      event.preventDefault();
      const magnitude = event.shiftKey ? 0.5 : 0.1;
      nudgeSelected(event.key === "ArrowLeft" ? -magnitude : magnitude);
    }
  });

  window.addEventListener("beforeunload", (event) => {
    if (!state.dirty) {
      return;
    }

    event.preventDefault();
    event.returnValue = strings.unsavedWarning || "";
  });

  const initialIndex = firstUntimedIndex();
  selectLine(initialIndex >= 0 ? initialIndex : (syncableIndices[0] ?? -1));
  updateSummary();
  setMode(strings.ready || "Ready.", "ready");
})();
