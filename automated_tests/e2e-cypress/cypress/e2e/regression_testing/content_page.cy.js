const paths = [
  "/disaster-assistance",
  "/es/requisitos-viaje-ninos-menores-de-edad",
];
const breadcrumb = ["Home", "Página principal"];
const languageTests = [
  "/solicitar-asistencia-desastre",
  "/travel-documents-children",
];

let env;
if (window.location.hostname == "cms-usagov.docker.local") {
  env = "local";
} else if (window.location.hostname == "beta-stage.usa") {
  env = "stage";
} else {
  env = "prod";
}

paths.forEach((path, idx) => {
  let lang;
  let testName;
  if (path === "/disaster-assistance") {
    lang = "English";
    testName = "BTE";
  } else {
    lang = "Español";
    testName = "BTS";
  }

  describe(`${lang} Content Page`, () => {
    beforeEach(() => {
      // Set base URL
      cy.visit(path);

      cy.injectAxe();
    });

    it(`${testName} 28: Left menu appears on page and indicates the page you are on`, () => {
      cy.get(".usa-sidenav").should("be.visible");

      // Menu indicates what page you are on
      cy.get(".usa-sidenav")
        .find(".usa-current")
        .then(($sideNav) => {
          // Grab page title and compare to breadcrumb text
          cy.get("h1")
            .invoke("text")
            .should((pageTitle) => {
              expect(pageTitle.trim().toLowerCase()).to.include(
                $sideNav[0].lastChild["wholeText"].trim().toLowerCase(),
              );
            });
        });
    });
    it(`${testName} 29: Breadcrumb appears at top of page and indicates correct section`, () => {
      cy.get(".usa-breadcrumb__list")
        .find("li")
        .first()
        .contains(breadcrumb[idx]);

      // Breadcrumb indicates correct section
      cy.get(".usa-breadcrumb__list")
        .find("li")
        .last()
        .invoke("text")
        .then((breadcrumb) => {
          // Grab page title and compare to breadcrumb text
          cy.get("h1")
            .invoke("text")
            .should((pageTitle) => {
              expect(pageTitle.trim().toLowerCase()).to.include(
                breadcrumb.trim().toLowerCase(),
              );
            });
        });
    });
    it(`${testName} 30: Page titles and headings are formatted correctly`, () => {
      // CSS style checks

      // h1
      // font-size: 2.44rem;
      cy.get("h1")
        .then((el) => {
          const win = cy.state("window");
          const styles = win.getComputedStyle(el[0]);

          const fontFamily = styles.getPropertyValue("font-family");
          expect(fontFamily).to.include("Merriweather Web");
        })
        .should("have.css", "font-weight", "700")
        .should("have.css", "color", "rgb(216, 57, 51)");

      // h2
      // font-size: 1.95rem;
      cy.get("h2")
        .not(".usa-card__heading")
        .not(".usa-accordion__heading")
        .each((h2) => {
          cy.wrap(h2)
            .then((el) => {
              const win = cy.state("window");
              const styles = win.getComputedStyle(el[0]);

              const fontFamily = styles.getPropertyValue("font-family");
              expect(fontFamily).to.include("Merriweather Web");
            })
            .should("have.css", "font-weight", "700")
            .should("have.css", "color", "rgb(27, 27, 27)");
        });

      if (path === "/disaster-assistance") {
        // h3
        // font-size: 1.34rem;
        cy.get(".content-wrapper")
          .find("h3")
          .each((h3) => {
            cy.wrap(h3)
              .then((el) => {
                const win = cy.state("window");
                const styles = win.getComputedStyle(el[0]);

                const fontFamily = styles.getPropertyValue("font-family");
                expect(fontFamily).to.include("Merriweather Web");
              })
              .should("have.css", "font-weight", "700")
              .should("have.css", "color", "rgb(27, 27, 27)");
          });
      }
    });
    it(`${testName} 31: ${lang} toggle appears on page and takes you to ${lang} page`, () => {
      cy.get(".language-link").click();
      cy.url().should("include", languageTests[idx]);
    });
    it(`${testName} 32: Last updated date appears at bottom of content with correct padding above it`, () => {
      // make sure date appears
      cy.get(".additional_body_info").find("#last-updated").should("exist");
    });
    it(`${testName} 33: Share this page function works correctly for facebook, twitter, and email`, () => {
      // test links for each social
      const facebook = [
        "disaster-assistance",
        "eses/requisitos-viaje-ninos-menores-de-edad",
      ];
      const twitter = [
        "disaster-assistance",
        "eses/requisitos-viaje-ninos-menores-de-edad",
      ];
      const mail = [
        "disaster-assistance",
        "eses/requisitos-viaje-ninos-menores-de-edad",
      ];

      var origin;
      cy.url().then((cyURL) => {
        if (cyURL.includes("cms-usagov.docker.local")) {
          origin = "http://cms-usagov.docker.local";
        } else if (cyURL.includes("beta-stage.usa")) {
          origin = "https://beta-stage.usa.gov";
        } else {
          origin = "https://www.usa.gov";
        }

        cy.get(".additional_body_info")
          .find("#sm-share")
          .should("exist")
          .get("div.share-icons>a")
          .eq(0)
          .should(
            "have.attr",
            "href",
            `https://www.facebook.com/sharer/sharer.php?u=${origin}/${facebook[idx]}&v=3`,
          )
          .get("div.share-icons>a")
          .eq(1)
          .should(
            "have.attr",
            "href",
            `https://twitter.com/intent/tweet?source=webclient&text=${origin}/${twitter[idx]}`,
          )
          .get("div.share-icons>a")
          .eq(2)
          .should(
            "have.attr",
            "href",
            `mailto:?subject=${origin}/${mail[idx]}`,
          );
      });
    });
    it(`${testName} 34: Do you have a question block appears at bottom of content page with icons and links to phone and chat`, () => {
      // test question box
      const phones = ["/phone", "/es/centro-de-llamadas"];
      cy.get(".additional_body_info")
        .find("#question-box")
        .should("exist")
        .find("a")
        .should("have.attr", "href", phones[idx]);
    });
    it(`${testName} 36: Back to top button`, () => {
      //test back to top button
      cy.scrollTo("bottom")
        .get("#back-to-top")
        .click()
        .url()
        .should("include", "#main-content");
    });
  });
});
