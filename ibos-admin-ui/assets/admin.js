/* Author: pablo rotem */
(function ($) {
  function applyResponsive() {
    const wide = $(window).width() >= 1200;
    if (wide) {
      $("#ibosFloatMenu").show();
      $("#ibosRecentBtn").hide();
    } else {
      $("#ibosFloatMenu").hide();
      $("#ibosRecentBtn").show();
    }
  }

  // FIXED: No more mock HTML. Now fetches real PHP from the server.
  function loadIntoFrame(pageKey) {
    const iframe = document.getElementById("ibosMainFrame");
    if (!iframe) return;

    // Directs the iframe to your AJAX bridge in WordPress
    const targetUrl = ajaxurl + "?action=ibos_cmd&cmd=" + pageKey;
    iframe.src = targetUrl;
  }

  $(document).ready(function () {
    if (window.IBOS_UI) {
      $("#ibosUserName").text(IBOS_UI.userName || "משתמש");
      $("#ibosLastLogin").text(IBOS_UI.lastLogin || "");
    }

    applyResponsive();
    $(window).on("resize", applyResponsive);

    $("#ibosRecentBtn").on("click", function () {
      $("#ibosFloatMenu").toggle();
    });

    // Sidebar Links
    $(".ibos-floatlink").on("click", function () {
      const page = $(this).data("page");
      if (page === "exit") {
          window.location.href = admin_url();
          return;
      }
      loadIntoFrame(page);
    });

    // Top Navigation Links
    $(".ibos-menubtn").on("click", function () {
      const page = $(this).data("page");
      if (page) loadIntoFrame(page);
    });

    // Initial default page
    loadIntoFrame("userHomePage");
  });
})(jQuery);