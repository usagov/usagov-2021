jQuery(document).ready(async function () {
  "use strict";

  // Array of paths to check
  const targetPaths = [
    "/elected-officials",
    "/elected-officials-results",
    "/elected-officials-email",
    "/es/funcionarios-electos",
    "/es/funcionarios-electos-resultados",
    "/es/funcionarios-electos-correo-electronico",
    "/site-issue-report-form",
    "/thank-you-issue-report",
    "/es/reporte-problemas-en-este-sitio-web",
    "/es/gracias-por-reportar-problemas-en-este-sitio-web"
  ];

  /**
   * Check if the current page path matches any of the target paths.
   * @returns {boolean}
   */
  function isTargetPage() {
    return targetPaths.includes(window.location.pathname);
  }
  /**
   * Hide the #last-updated element if it exists.
   */
  function hideLastUpdated() {
    const element = document.querySelector("#last-updated");
    if (element) {
      element.style.display = "none"; // Inline styling for simplicity
    }
  }
  // If the current page matches a target path, hide #last-updated
  if (isTargetPage()) {
    hideLastUpdated();
  }
});
