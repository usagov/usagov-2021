const paths = ["/", "/es"];

paths.forEach(path => {
  let lang;
  if (path === "/") {
    lang = "English";
  } else {
    lang = "Español";
  }
  describe(`${lang} home page`, () => {
    // Set base URL
    beforeEach(() => {
      cy.visit(path);
    });

    it("BTE 1: Sitewide banner for official government site appears at the top, accordion can be expanded", () => {
      cy.get("header")
        .find(".usa-banner__header")
        .should("be.visible");

      // Accordion content should not be visible
      cy.get("header")
        .find(".usa-banner__content")
        .should("not.be.visible");

      // Expand accordion
      cy.get("header")
        .find(".usa-accordion__button")
        .click();

      // Accordion content should be visible
      cy.get(".usa-banner__content")
        .should("be.visible");
    });
    it("BTE 2: USAGov logo appears in the header area", () => {
      cy.get("header")
        .find(".usa-logo")
        .find("img")
        .then($img => {
          const imgUrl = $img.prop("src");
          cy.request(imgUrl)
            .its("status").should("eq", 200);
          expect($img).to.have.attr("alt");
        });
    });
    it("BTE 3: Link with Contact Center number appears in header area and links to contact page", () => {
      let expectedHref;

      if (path === "/") {
        expectedHref = "/phone";
      } else {
        expectedHref = "/es/llamenos";
      }

      cy.get("header")
        .find("#top-phone")
        .find("a")
        .invoke("attr", "href")
        .then(href => {
          expect(href).to.equal(expectedHref);
        });
    });
    it("BTE 4: Español toggle appears and links to Spanish homepage", () => {
      let expectedHref;

      if (path === "/") {
        expectedHref = "/es";
      } else {
        expectedHref = "/";
      }

      cy.get("header")
        .find("a.language-link")
        .invoke("attr", "href")
        .then((href) => {
          expect(href).to.equal(expectedHref);
        });
    });
    it("BTE 5: Search bar appears with search icon in header region; can successfully complete search", () => {
      const typedText = "housing";

      // Enters query into search input
      cy.get("header")
        .find("#search-field-small")
        .then((input) => {
          cy.wrap(input).type(typedText);
          cy.wrap(input).should("have.value", typedText);
          cy.wrap(input).type("{enter}");
        });

      // Origin URL should now be search.gov
      const sentArgs = { query: typedText };
      cy.origin(
        "https://search.usa.gov/",
        { args: sentArgs },
        ({ query }) => {
          cy.get("#search-field").should("have.value", "housing");
        }
      );

      // Go back to localhost to test search icon
      cy.visit("/");
      cy.get("header")
        .find("#search-field-small")
        .next()
        .find("img")
        .should("have.attr", "alt", "Search");

      cy.get("header")
        .find("#search-field-small")
        .next()
        .click();

      // Verify URL is search.gov
      cy.origin("https://search.usa.gov/", () => {
        cy.url().should("include", "search.usa.gov");
      });
    });
    it("BTE 6: Main menu appears after header; links work appropriately. All topics link goes down the page.", () => {
      // Main menu appears
      cy.get(".usa-nav__primary")
        .should("be.visible");

      // Test All Topics link
      cy.get("li.usa-nav__primary-item a").each(($el) => {
        cy.request($el.prop("href")).its("status").should("eq", 200);
      });
    });
    it("BTE 7: Banner area/image appears with Welcome text box", () => {
      // TODO: test this in other viewports
      cy.get(".banner-div")
        .should("be.visible")
        .then($el => {
          const url = $el.css('background-image').match(/url\("(.*)"\)/)[1]
          cy.request(url).its("status").should("be.lessThan", 400);
        })

      cy.get(".welcome-box")
        .should("be.visible");
    });
    it("BTE 8: How do I area appears correctly with links to four pages/topics", () => {
      let expectedText;

      if (path === "/") {
        expectedText = "How do I";
      } else {
        expectedText = "Cómo puedo...";
      }

      cy.get(".how-box")
        .contains(expectedText)
        .should("be.visible");

      // Verify there are 4 links
      cy.get(".how-box")
        .find("a")
        .as("links")
        .should("be.visible")
        .should("have.length", 4);

      // Check each link is valid
      cy.get("@links")
        .each((link) => {
          cy.visit(link.attr("href"));
          cy.contains("Page not found").should("not.exist");

          cy.go("back");
        });
    });
    it("BTE 9: Jump to All topics and services link/button appears and jumps to correct place on page", () => {
      let expectedText;

      if (path === "/") {
        expectedText = "Jump to";
      } else {
        expectedText = "Vaya a todos";
      }

      // Check text and button
      cy.get(".jump")
        .contains(expectedText);

      cy.get(".jump")
        .find("img")
        .should("be.visible")
        .then($img => {
          const imgUrl = $img.prop("src");
          cy.request(imgUrl)
            .its("status").should("eq", 200);
          expect($img).to.have.attr("alt");
        });

      // Verify link is valid
      cy.get(".jump")
        .each((el) => {
          cy.visit(el.find("a").attr("href"));
          cy.url().should("include", "#all-topics-header");

          cy.visit("/");
        });
    });
    it("BTE 10: Cards under \"All topics and services\" appear correctly (icon, title, text, hover state) and are clickable", () => {
      cy.get(".all-topics-background")
        .find(".homepage-card")
        .each((el) => {
          // Validate link
          cy.wrap(el).find("a")
            .invoke("attr", "href")
            .then(href => {
              cy.request(href)
                .its("status")
                .should("eq", 200);
            });

          // Verify number of children
          cy.wrap(el).find("a")
            .children()
            .should("have.length", 3);

          // Css check for hover state
          cy.wrap(el)
            .realHover()
            .should("have.css", "background-color", "rgb(204, 236, 242)");
        });
    });
  });
});
