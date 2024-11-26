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


    it(`${testName} 13: Footer: Email subscription form appears in footer and works appropriately`, () => {
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
      cy.visit(emails[idx], { args: sentArgs }, ({ email }) => {
        cy.get("input").filter('[name="email"]').should("have.value", email);
      });

      // Go back to localhost to test submit button
      cy.visit(path);
      cy.get("#footer-email").type(validEmail).should("have.value", validEmail);

      cy.get(".usa-sign-up").find('button[type="submit"]').click();

      // Origin URL should now be connect.usa.gov
      cy.visit(emails[idx], { args: sentArgs }, ({ email }) => {
        cy.get("input").filter('[name="email"]').should("have.value", email);
      });
    });


    it(`${testName} 14: Footer: Social media icons appear in footer and link to correct places`, () => {
      cy.get(".usa-footer__contact-links")
      .within(() => {
        // Verify correct text in social media heading
        cy.get('h4')
          .should("have.text", fixtures.socials_heading[idx])
        // Verify correct number of social media links
        cy.get('.usa-social-link')
          .should('have.length', fixtures.num_socials[idx])
      });

      // Verify correct social media links, images, alt texts, and accessible names
      for (const social of fixtures.socials) {
        let socialLink = social.link[idx];
        let imgSrc = `${fixtures.iconDir}${social.icon}`

        if (socialLink.length > 0) {
          cy.get(`.usa-footer__social-links`)
            .find(`[href="${socialLink}"]`)
            .as("link")
            .should("have.attr", "href", socialLink)
            .within(() => {
              cy.get('img')
                .should("have.attr", "src", imgSrc)
                .should("have.attr", "alt", social.alt_text)
              cy.get('span')
                .should("have.text", social.name)
            });
        }
      }
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
      cy.get(".usa-identifier__section--required-links")
        .should("have.attr", "aria-label", fixtures.important_links[idx])
        .find("a")
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
