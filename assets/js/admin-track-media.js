(function ($) {
  "use strict";

  /**
   * Convert a duration such as "3:42" or "1:03:42" into seconds.
   *
   * @param {string|number} value
   * @returns {number}
   */
  function durationToSeconds(value) {
    if (typeof value === "number" && Number.isFinite(value)) {
      return Math.max(0, Math.round(value));
    }

    const normalized = String(value || "").trim();

    if (/^\d+(?:\.\d+)?$/.test(normalized)) {
      return Math.max(0, Math.round(Number(normalized)));
    }

    const parts = normalized.split(":");

    if (
      parts.length < 2 ||
      parts.length > 3 ||
      parts.some((part) => !/^\d+$/.test(part))
    ) {
      return 0;
    }

    return parts.reduce(
      (total, part) => total * 60 + Number.parseInt(part, 10),
      0,
    );
  }

  /**
   * Format seconds as M:SS or H:MM:SS.
   *
   * @param {number} totalSeconds
   * @returns {string}
   */
  function formatDuration(totalSeconds) {
    const safeSeconds = Math.max(
      0,
      Math.round(Number(totalSeconds) || 0),
    );

    const hours = Math.floor(safeSeconds / 3600);
    const minutes = Math.floor((safeSeconds % 3600) / 60);
    const seconds = safeSeconds % 60;

    if (hours > 0) {
      return [
        hours,
        String(minutes).padStart(2, "0"),
        String(seconds).padStart(2, "0"),
      ].join(":");
    }

    return [
      minutes,
      String(seconds).padStart(2, "0"),
    ].join(":");
  }

  /**
   * Extract duration information from a WordPress media attachment.
   *
   * WordPress attachment responses can expose duration values in slightly
   * different properties depending on context and version, so this checks
   * the common formatted and numeric locations.
   *
   * @param {Object} attachment
   * @returns {{label: string, seconds: number}}
   */
  function getAttachmentDuration(attachment) {
    const metadata =
      attachment &&
      typeof attachment.metadata === "object" &&
      attachment.metadata
        ? attachment.metadata
        : {};

    const meta =
      attachment &&
      typeof attachment.meta === "object" &&
      attachment.meta
        ? attachment.meta
        : {};

    const formattedCandidates = [
      attachment.fileLengthFormatted,
      metadata.length_formatted,
      meta.length_formatted,
      typeof attachment.fileLength === "string"
        ? attachment.fileLength
        : "",
    ];

    let label = formattedCandidates
      .map((value) => String(value || "").trim())
      .find((value) => value.includes(":")) || "";

    const numericCandidates = [
      metadata.length,
      meta.length,
      typeof attachment.fileLength === "number"
        ? attachment.fileLength
        : 0,
      label,
    ];

    let seconds = 0;

    for (const candidate of numericCandidates) {
      seconds = durationToSeconds(candidate);

      if (seconds > 0) {
        break;
      }
    }

    if (!label && seconds > 0) {
      label = formatDuration(seconds);
    }

    return {
      label,
      seconds,
    };
  }

  /**
   * Apply a selected audio attachment's duration to the Track editor.
   *
   * @param {Object} attachment
   * @param {JQuery} $duration
   * @param {JQuery} $durationSeconds
   */
  function applyAttachmentDuration(
    attachment,
    $duration,
    $durationSeconds,
  ) {
    const attachmentDuration = getAttachmentDuration(attachment);

    if (attachmentDuration.seconds <= 0) {
      $duration.prop("readonly", false);
      $durationSeconds.prop("readonly", false);

      return;
    }

    $duration
      .val(attachmentDuration.label)
      .prop("readonly", true)
      .trigger("input")
      .trigger("change");

    $durationSeconds
      .val(attachmentDuration.seconds)
      .prop("readonly", true)
      .trigger("input")
      .trigger("change");
  }

  function initMediaField(field) {
    const $field = $(field);
    const $input = $field.find("[data-sv-media-input]");
    const $preview = $field.find("[data-sv-media-preview]");
    const $select = $field.find("[data-sv-media-select]");
    const $remove = $field.find("[data-sv-media-remove]");

    const updatesDuration = $field.is("[data-sv-duration-source]");
    const $duration = $("#sv_duration");
    const $durationSeconds = $("#sv_duration_seconds");

    if (!$input.length || !$select.length) {
      return;
    }

    let frame = null;

    $select.on("click", function (event) {
      event.preventDefault();

      const title =
        $select.data("sv-media-title") ||
        "Select Audio";

      const buttonText =
        $select.data("sv-media-button") ||
        "Use this audio file";

      frame = wp.media({
        title,
        button: {
          text: buttonText,
        },
        library: {
          type: "audio",
        },
        multiple: false,
      });

      frame.on("select", function () {
        const selected = frame
          .state()
          .get("selection")
          .first();

        if (!selected) {
          return;
        }

        const attachment = selected.toJSON();

        if (!attachment || !attachment.id) {
          return;
        }

        $input.val(attachment.id).trigger("change");

        const label =
          attachment.url ||
          attachment.filename ||
          `Attachment #${attachment.id}`;

        $preview.text(label);
        $remove.prop("disabled", false);

        if (
          updatesDuration &&
          $duration.length &&
          $durationSeconds.length
        ) {
          applyAttachmentDuration(
            attachment,
            $duration,
            $durationSeconds,
          );
        }
      });

      frame.open();
    });

    $remove.on("click", function (event) {
      event.preventDefault();

      $input.val("").trigger("change");
      $preview.text("No audio file selected.");
      $remove.prop("disabled", true);

      if (
        updatesDuration &&
        $duration.length &&
        $durationSeconds.length
      ) {
        /*
         * Preserve the existing duration values for teaser releases or
         * external audio, but return the fields to manual editing mode.
         */
        $duration.prop("readonly", false);
        $durationSeconds.prop("readonly", false);
      }
    });
  }

  $(function () {
    $("[data-sv-media-field]").each(function () {
      initMediaField(this);
    });
  });
})(jQuery);