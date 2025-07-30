/**
 * These tests check whether these elements are present in the HTML for appropriate
 * pages:
 *   - Contact Center phone number in header, mobile header, and footer
 *   - "Have a question" box
 * We use javascript to hide these elements on a portion of pages, and when the contact
 * center is closed, so we're not testing for visibility here, just presence in the HTML.
 */


// A sampling of paths for pages that SHOULD HAVE the phone number and "have a question" box'
// This is the typical case, so we want this list to include every variation we can think of
// that should match, but not an exhaustive list.
const paths_with_all_contact_info = [
  // Ordinary "leaf" pages
  "/branches-of-government",
  "/naturalization",
  "/es/ramas-gobierno-estados-unidos",
  "/es/ciudadania-estados-unidos-naturalizacion",
  // Also "leaf" pages, but special in our navigation:
  "/privacy-security",
  "/es/privacidad-seguridad",
  "/accessibility-policy",
  "/es/politica-accesibilidad",
];

// A sampling of paths for pages that SHOULD NOT HAVE the phone number and "have a question" box
// This includes samples of pages by rule, and every path that's explicitly configured in the
// Block Visibility Group rules in Drupal.
const paths_without_contact_info = [
    // Pages excluded by path: chat and phone
    "/chat",
    "/phone",
    "/es/chat",
    "/es/llamenos",
    // Pages excluded by path: agency index views
    "/agency-index",
    "/es/indice-agencias",
    // Pages excluded by path: tax related
    "/check-tax-status",
    "/contact-irs",
    "/tax-refund-direct-deposit",
    "/tax-refund-offset",
    "/tax-refunds",
    "/unclaimed-tax-refunds",
    "/es/averiguar-estado-reembolso-impuestos",
    "/es/contactar-irs",
    "/es/obtener-reembolso-de-impuestos-por-deposito-directo",
    "/es/compensacion-reembolsos-impuestos",
    "/es/reembolsos-impuestos-no-entregados",
    "/es/reembolsos-impuestos",
    // Pages excluded by path: contact elected officials
    "/elected-officials-email",
    "/elected-officials-results",
    "/es/funcionarios-electos-correo-electronico",
    "/es/funcionarios-electos-resultados",
    // Federal directory records (agency pages)
    "/agencies/u-s-air-force",
    "/es/agencias/fuerza-aerea-de-ee-uu",
    // State pages, 50-stage pages
    "/states/alaska",
    "/es/estados/alabama",
    "/state-governments",
    "/state-governor",
    "/es/gobiernos-estatales",
    "/es/estatal-consumidor",
    // "Scam wizard" pages (rule is actually "exclude taxonomy terms")
    "/where-report-scams",
    "/where-report-scams/where-did-scam-take-place",
    "/es/donde-reportar-una-estafa",
    "/es/donde-reportar-una-estafa/donde-ocurrio-estafa",
    // Benefits Category search pages
    "/benefit-finder",
    "/es/buscador-beneficios",
    // Benefit-finder tool pages
    "/benefit-finder/disability",
    "/es/buscador-beneficios/discapacidad",
];

// Paths for pages that SHOULD HAVE the phone number but NOT the "have a question" box.
const paths_phone_only = [
  // Home pages
  "/",
  "/es",
  // Navigation cards pages
  "/about-the-us",
  "/es/acerca-de-estados-unidos",
  // Navigation pages
  "/food-help",
  "/es/asistencia-alimentaria",
];

// All of these tests use "exist" instead of "be.visible" because we use javascript to hide them
// when the contact center is closed, and on a percentage of views when it is open.
describe('Contact Center phone number is present in header/footer, and Have a Question box is on pages (but may be hidden by CSS)', () => {
  paths_with_all_contact_info.forEach((path, idx) => {

    it(`${path} has all Contact Center elements`, () => {
      cy.visit(path);
      cy.get("#top-phone-mobile-menu").should("exist");
      cy.get("#top-phone").should("exist");
      cy.get("#footer-phone").should("exist");
      cy.get("#question-box").should("exist");
    });
  });
});

describe('Contact Center phone number is in header; Have a Question box is not present', () => {
  paths_phone_only.forEach((path, idx) => {

    it(`${path} has phone number in header/footer, and does NOT have Have a Question box`, () => {
      cy.visit(path);
      cy.get("#top-phone-mobile-menu").should("exist");
      cy.get("#top-phone").should("exist");
      cy.get("#footer-phone").should("exist");
      cy.get("#question-box").should("not.exist");
    });
  });
});

describe('Neither phone number in header nor Have a Question box is present', () => {
  paths_without_contact_info.forEach((path, idx) => {

    it(`${path} does NOT have phone number in header/footer, and does NOT have Have a Question box`, () => {
      cy.visit(path);
      cy.get("#top-phone-mobile-menu").should("not.exist");
      cy.get("#top-phone").should("not.exist");
      cy.get("#footer-phone").should("not.exist");
      cy.get("#question-box").should("not.exist");
    });
  });
});
