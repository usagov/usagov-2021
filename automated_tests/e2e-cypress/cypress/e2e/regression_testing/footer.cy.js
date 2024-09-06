const socials = require("../../fixtures/socials.json");
const paths = ["/", "/es"];

paths.forEach((path, idx) => {
  let lang;
  if (path === "/") {
    lang = "English";
  } else {
    lang = "Español";
  }
  describe(`${lang} Footer`, () => {
    beforeEach(() => {
      // Set base URL
      cy.visit(path);
    });
    it(`BTE/S 12: Footer links appear and work appropriately`, () => {
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
    it("BTE/S 13: Email subscription form appears in footer and works appropriately", () => {
      const validEmail = "test@usa.gov";
      const invalidEmails = ["test@#$1123", "test2@", "@test3.com"];
      const emails = [
        "https://connect.usa.gov/",
        "https://conectate.gobiernousa.gov",
      ];
      // Test invalid emails
      for (const email of invalidEmails) {
        cy.get("#footer-email")
          .type(email)
          .should("have.value", email)
          .type("{enter}");

        cy.get("input:invalid").should("have.length", 1);
        cy.get("input:invalid").clear();
      }

      // Test valid email
      cy.get("#footer-email").type(validEmail).type("{enter}");

      // Origin URL should now be connect.usa.gov
      const sentArgs = { email: validEmail };
      cy.origin(emails[idx], { args: sentArgs }, ({ email }) => {
        cy.get("input").filter('[name="email"]').should("have.value", email);
      });

      // Go back to localhost to test submit button
      cy.visit(path);
      cy.get("#footer-email").type(validEmail).should("have.value", validEmail);

      cy.get(".usa-sign-up").find('button[type="submit"]').click();

      // Origin URL should now be connect.usa.gov
      cy.origin(emails[idx], { args: sentArgs }, ({ email }) => {
        cy.get("input").filter('[name="email"]').should("have.value", email);
      });
    });
    it("BTE/S 14: Social media icons appear in footer and link to correct places", () => {
      for (const social of socials) {
        //if spanish check that there are links
        if (path === "/es" && social.linkEs.length <= 0) {
          continue;
        } else {
          cy.get(".usa-footer__contact-links")
            .find(`[alt="${social.alt_text} USAGov"]`)
            .should(
              "have.attr",
              "src",
              `/themes/custom/usagov/images/social-media-icons/${social.name}_Icon.svg`,
            );

          let socialLink;
          if (path === "/es") {
            socialLink = social.linkEs;
          } else {
            socialLink = social.link;
          }
          cy.get(".usa-footer__contact-links")
            .find(`[alt="${social.alt_text} USAGov"]`)
            .parent()
            .as("link")
            .should("have.attr", "href", socialLink);
        }
      }
    });
    it("BTE/S 15: Contact Center information appears in footer and phone number links to /phone", () => {
      const phones = ["/phone", "/es/llamenos"];
      cy.get("#footer-phone").find("a").click();

      cy.url().should("include", phones[idx]);
    });
    it("BTE/S 16: Subfooter indicating USAGov is official site appears at very bottom", () => {
      const identifier = ["official guide", "la guía oficial"];
      cy.get(".usa-footer")
        .find(".usa-identifier")
        .should("contain", "USAGov")
        .should("contain", identifier[idx]);
    });
  });
});
