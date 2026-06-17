(function () {
  "use strict";

  const SVButterchurn = {
    getButterchurnLibrary() {
      if (
        window.butterchurn &&
        typeof window.butterchurn.createVisualizer === "function"
      ) {
        return window.butterchurn;
      }

      if (
        window.butterchurn &&
        window.butterchurn.default &&
        typeof window.butterchurn.default.createVisualizer === "function"
      ) {
        return window.butterchurn.default;
      }

      return null;
    },

    isAvailable() {
      return !!(
        this.getButterchurnLibrary() &&
        window.butterchurnPresets &&
        typeof window.butterchurnPresets.getPresets === "function"
      );
    },

    getPresets() {
      if (
        !window.butterchurnPresets ||
        typeof window.butterchurnPresets.getPresets !== "function"
      ) {
        return {};
      }

      try {
        return window.butterchurnPresets.getPresets() || {};
      } catch (err) {
        console.warn("[SVButterchurn] Could not load presets.", err);
        return {};
      }
    },

    getRandomPresetName(presets) {
      const names = Object.keys(presets || {});

      if (!names.length) {
        return "";
      }

      return names[Math.floor(Math.random() * names.length)];
    },

create({ canvas, audioGraph }) {
  if (!this.isAvailable()) {
    throw new Error("Butterchurn vendor scripts are unavailable.");
  }

  if (!canvas) {
    throw new Error("Butterchurn canvas is missing.");
  }

  if (!audioGraph || !audioGraph.context || (!audioGraph.analyser && !audioGraph.source)) {
    throw new Error("Butterchurn audio graph is missing.");
  }

  const context = audioGraph.context;
  const audioNode = audioGraph.source || audioGraph.analyser;
  const presets = this.getPresets();
  const presetName = this.getRandomPresetName(presets);
  const preset = presetName ? presets[presetName] : null;

  const butterchurn = this.getButterchurnLibrary();

  if (!butterchurn) {
    throw new Error("Butterchurn visualizer library is unavailable.");
  }

  /**
   * Butterchurn needs a WebGL canvas.
   * The existing Slim Volume bars canvas may already have a 2D context,
   * and a canvas cannot switch from 2D to WebGL after that.
   */
  const originalCanvas = canvas;
  const rendererCanvas = document.createElement("canvas");
  const pixelRatio = Math.min(window.devicePixelRatio || 1, 2);

  rendererCanvas.className = `${originalCanvas.className || ""} sv-player__visualizer-canvas--butterchurn`.trim();
  rendererCanvas.setAttribute("aria-hidden", "true");

  rendererCanvas.style.display = "block";
  rendererCanvas.style.width = "100%";
  rendererCanvas.style.height = `${originalCanvas.clientHeight || originalCanvas.height || 120}px`;

  originalCanvas.style.display = "none";
  originalCanvas.insertAdjacentElement("afterend", rendererCanvas);

  const getRendererSize = () => {
    const rect = rendererCanvas.getBoundingClientRect();

    const width = Math.max(
      1,
      Math.floor(rect.width || originalCanvas.clientWidth || originalCanvas.width || 640),
    );

    const height = Math.max(
      1,
      Math.floor(rect.height || originalCanvas.clientHeight || originalCanvas.height || 360),
    );

    return {
      cssWidth: width,
      cssHeight: height,
      renderWidth: Math.max(1, Math.round(width * pixelRatio)),
      renderHeight: Math.max(1, Math.round(height * pixelRatio)),
    };
  };

  const applyRendererSize = () => {
    const size = getRendererSize();

    rendererCanvas.width = size.renderWidth;
    rendererCanvas.height = size.renderHeight;

    return size;
  };

  const initialSize = applyRendererSize();

  const visualizer = butterchurn.createVisualizer(context, rendererCanvas, {
    width: initialSize.renderWidth,
    height: initialSize.renderHeight,
    pixelRatio: 1,
    textureRatio: 1,
  });

  visualizer.connectAudio(audioNode);

  if (preset) {
    visualizer.loadPreset(preset, 0.0);
  }

  if (typeof visualizer.setRendererSize === "function") {
    visualizer.setRendererSize(initialSize.renderWidth, initialSize.renderHeight);
  }

  try {
    visualizer.render();
  } catch (err) {
    console.warn("[SVButterchurn] Initial render failed.", err);
  }

  let frame = null;
  let running = false;

  const render = () => {
    if (!running) {
      return;
    }

    visualizer.render();
    frame = window.requestAnimationFrame(render);
  };

  return {
    type: "butterchurn",
    presetName,
    visualizer,

    start() {
      running = true;

      if (context.state === "suspended") {
        context.resume().catch((err) => {
          console.warn("[SVButterchurn] Could not resume AudioContext.", err);
        });
      }

      if (!frame) {
        render();
      }
    },

    stop() {
      running = false;

      if (frame) {
        window.cancelAnimationFrame(frame);
        frame = null;
      }
    },

    resize() {
      const size = applyRendererSize();

      if (typeof visualizer.setRendererSize === "function") {
        visualizer.setRendererSize(size.renderWidth, size.renderHeight);
      }

      try {
        visualizer.render();
      } catch (err) {
        console.warn("[SVButterchurn] Render after resize failed.", err);
      }
    },

    destroy() {
      this.stop();

      if (typeof visualizer.disconnectAudio === "function") {
        try {
          visualizer.disconnectAudio(audioNode);
        } catch (err) {
          // Some builds may not support disconnecting the exact node.
        }
      }

      if (rendererCanvas.parentNode) {
        rendererCanvas.parentNode.removeChild(rendererCanvas);
      }

      originalCanvas.style.display = "";
    },
  };
},
  };

  window.SVButterchurn = SVButterchurn;
})();
