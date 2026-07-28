(() => {
  "use strict";

  let frame = null;
  let activeRoot = null;

  const getRoot = (element) => element?.closest("[data-sv-project-image]") || null;

  const setImage = (root, attachment) => {
    if (!root || !attachment) return;

    const input = root.querySelector("[data-sv-project-image-id]");
    const previewWrap = root.querySelector("[data-sv-project-image-preview-wrap]");
    const preview = root.querySelector("[data-sv-project-image-preview]");
    const removeButton = root.querySelector("[data-sv-project-image-remove]");

    const sizes = attachment.sizes || {};
    const previewUrl =
      sizes.thumbnail?.url ||
      sizes.medium?.url ||
      attachment.url ||
      "";

    if (input) input.value = String(attachment.id || "");
    if (preview) preview.src = previewUrl;
    if (previewWrap) previewWrap.style.display = previewUrl ? "" : "none";
    if (removeButton) removeButton.hidden = !previewUrl;
  };

  const clearImage = (root) => {
    if (!root) return;

    const input = root.querySelector("[data-sv-project-image-id]");
    const previewWrap = root.querySelector("[data-sv-project-image-preview-wrap]");
    const preview = root.querySelector("[data-sv-project-image-preview]");
    const removeButton = root.querySelector("[data-sv-project-image-remove]");

    if (input) input.value = "";
    if (preview) preview.src = "";
    if (previewWrap) previewWrap.style.display = "none";
    if (removeButton) removeButton.hidden = true;
  };

  const openMediaFrame = (root) => {
    if (!window.wp?.media) return;

    activeRoot = root;

    if (!frame) {
      frame = window.wp.media({
        title: "Choose artist/project image",
        button: {
          text: "Use this image",
        },
        library: {
          type: "image",
        },
        multiple: false,
      });

      frame.on("select", () => {
        const attachment = frame.state().get("selection").first()?.toJSON();

        if (attachment && activeRoot) {
          setImage(activeRoot, attachment);
        }
      });
    }

    frame.open();
  };

  document.addEventListener("click", (event) => {
    const selectButton = event.target.closest("[data-sv-project-image-select]");

    if (selectButton) {
      event.preventDefault();
      openMediaFrame(getRoot(selectButton));
      return;
    }

    const removeButton = event.target.closest("[data-sv-project-image-remove]");

    if (removeButton) {
      event.preventDefault();
      clearImage(getRoot(removeButton));
    }
  });
})();
