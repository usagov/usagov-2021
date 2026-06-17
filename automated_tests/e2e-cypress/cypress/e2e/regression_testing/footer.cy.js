const fixtures = require("../../fixtures/footer.json");
const paths = ["/", "/es"];

paths.forEach((path, idx) => {
  let lang;
  let testName;
  if (path === "/") {
    lang = "English";
    testName = "BTE";
  } else {
    lang = "Español";
    testName = "BTS";
  }
  describe(`${lang} Footer`, () => {
    beforeEach(() => {
      // Set base URL
      cy.visit(path);
    });


    it(`${testName} 12: Footer links appear and work appropriately`, () => {
      cy.get(".usa-footer__nav")
        .find("a")
        .not('[href="/website-analytics/"]')
        .each((link) => {
          cy.wrap(link)
            .invoke("attr", "href")
            .then((href) => {
              cy.request(href).its("status").should("eq", 200);
            });
        });
    });

    it(`${testName} 15: Footer: Contact Center information appears in footer and phone number links are correct`, () => {
      cy.get("#footer-phone").within(() => {
        cy.get("h4")
          .should("have.text", fixtures.contact_heading[idx])
        cy.get(".footer-question")
          .should("have.text", fixtures.ask_a_question[idx])
        cy.get(".usa-footer__contact-info a")
          .as("link")
          .should("have.text", fixtures.phone_number)
          .should("have.attr", "href", fixtures.phone_path[idx])
          .click();
        cy.url().should("include", fixtures.phone_path[idx]);
      });
    });


    it(`${testName} 16: Footer: Subfooter indicating USAGov is official site appears at very bottom`, () => {
      cy.get(".usa-identifier__section--usagov")
        .should("have.attr", "aria-label", fixtures.official_guide[idx])
        .find(".usa-identifier__identity")
        .should("have.attr", "aria-label", fixtures.official_site[idx])
        .within(() => {
          cy.get(".usa-identifier__identity-disclaimer:nth-of-type(1)")
            .should("have.text", fixtures.official_guide[idx])
          cy.get(".usa-identifier__identity-disclaimer:nth-of-type(2)")
            .should("have.text", fixtures.official_site[idx])
            .find("a")
            .as("link")
            .should("have.attr", "href", fixtures.gsa_url).click();
          cy.url().should("include", fixtures.gsa_url);
          cy.go('back')
        });
      // Verify identifier footer structure and links work (flexible about text content)
      cy.get(".usa-identifier__section--required-links")
        .should("have.attr", "aria-label", fixtures.important_links[idx])
        .should("be.visible")
        .find("a")
        .should("have.length.greaterThan", 0)
        .each((link) => {
          cy.wrap(link)
            .invoke("attr", "href")
            .then((href) => {
              cy.request(href).its("status").should("eq", 200);
            });
        });
    });
  });
});
