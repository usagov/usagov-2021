/**
 * USAGOV-2946: Spanish pages redirect to English.
 *
 * Redirects are done by the www (static site) nginx, from
 * .docker/src-www/etc/nginx/partials/es-redirect-map.conf, with a catch-all for any
 * other /es path. The CMS (including local cms-usagov.docker.local) does not redirect,
 * so the redirect tests only run against the static sites.
 */

const isStaticSite = !/\/\/cms[-.]/.test(Cypress.config("baseUrl"));

// [Spanish path, expected English target]
const redirects = [
  ["/es", "/"],
  ["/es/", "/"],
  ["/es/requisitos-viaje-ninos-menores-de-edad", "/travel-documents-children"],
  ["/es/indice-agencias", "/agency-index"],
  ["/es/llamenos", "/phone"],
  ["/es/etapas-importantes-de-la-vida", "/life-events"],
  ["/es/buscador-beneficios", "/benefit-finder"],
  // State pages have no language toggle; paired by state abbreviation
  ["/es/estados/alaska", "/states/alaska"],
  // Wizard steps go to the English wizard start
  ["/es/donde-reportar-una-estafa/donde-ocurrio-estafa", "/where-report-scams"],
  // Case and trailing slash are normalized
  ["/ES/Estados/Alaska/", "/states/alaska"],
  // No English equivalent, or unknown /es path: English home page
  ["/es/no-such-page-usagov-2946", "/"],
];

describe("Spanish pages redirect to English (USAGOV-2946)", () => {
  redirects.forEach(([from, to]) => {
    it(`${from} redirects to ${to}`, function () {
      if (!isStaticSite) {
        this.skip();
      }
      cy.request({ url: from, followRedirect: false }).then((resp) => {
        expect(resp.status).to.eq(302);
        const location = new URL(resp.headers.location, Cypress.config("baseUrl"));
        expect(location.pathname.replace(/\/$/, "") || "/").to.eq(to);
      });
    });
  });

  it("English pages do not advertise a Spanish alternate (no hreflang links)", () => {
    ["/", "/disaster-assistance", "/states/alaska"].forEach((path) => {
      cy.visit(path);
      cy.get('head link[rel="alternate"][hreflang]').should("not.exist");
      cy.get("header a.language-link").should("not.exist");
    });
  });
});
