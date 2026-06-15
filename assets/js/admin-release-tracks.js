(function ($) {
  "use strict";

  function renumberRows($tbody) {
    $tbody.find("tr").each(function (index) {
      const number = index + 1;
      $(this).find("[data-sv-track-number]").text(String(number));
    });
  }

  $(function () {
    const $tbody = $("[data-sv-track-sortable]");

    if (!$tbody.length || typeof $tbody.sortable !== "function") {
      return;
    }

    $tbody.sortable({
      axis: "y",
      handle: ".sv-track-sort-handle",
      items: "> tr",
      cursor: "move",
      placeholder: "sv-track-sort-placeholder",
      helper: function (event, row) {
        const $originals = row.children();
        const $helper = row.clone();

        $helper.children().each(function (index) {
          $(this).width($originals.eq(index).width());
        });

        return $helper;
      },
      update: function () {
        renumberRows($tbody);
      },
    });

    $tbody.disableSelection();
  });
})(jQuery);