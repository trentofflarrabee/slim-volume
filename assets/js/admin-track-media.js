(function ($) {
  "use strict";

  function initMediaField(field) {
    const $field = $(field);
    const $input = $field.find("[data-sv-media-input]");
    const $preview = $field.find("[data-sv-media-preview]");
    const $select = $field.find("[data-sv-media-select]");
    const $remove = $field.find("[data-sv-media-remove]");

    if (!$input.length || !$select.length) {
      return;
    }

    let frame = null;

    $select.on("click", function (event) {
      event.preventDefault();

      const title = $select.data("sv-media-title") || "Select Audio";
      const buttonText = $select.data("sv-media-button") || "Use this audio file";

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
        const attachment = frame.state().get("selection").first().toJSON();

        if (!attachment || !attachment.id) {
          return;
        }

        $input.val(attachment.id);

        const label = attachment.url || attachment.filename || `Attachment #${attachment.id}`;
        $preview.text(label);

        $remove.prop("disabled", false);
      });

      frame.open();
    });

    $remove.on("click", function (event) {
      event.preventDefault();

      $input.val("");
      $preview.text("No audio file selected.");
      $remove.prop("disabled", true);
    });
  }

  $(function () {
    $("[data-sv-media-field]").each(function () {
      initMediaField(this);
    });
  });
})(jQuery);