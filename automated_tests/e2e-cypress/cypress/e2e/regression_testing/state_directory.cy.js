const paths = ["/", "/es"];
const state_directory_paths = ["/state-governments", "/es/gobiernos-estatales"];
const alaska_paths = ["/states/alaska", "/es/estados/alaska"];

paths.forEach((path, idx) => {
  let lang;
  if (path === "/") {
    lang = "English";
  } else {
    lang = "Español";
  }

  describe(`${lang} State Directories`, () => {
    context("State Directory", () => {
      beforeEach(() => {
        cy.visit(state_directory_paths[idx]);
      });
      it("BTE 50A: Landing page: state drop-down", () => {
        // testing dropdown menu on click
        cy.get("[data-test='stateInput']")
          .click()
          .then(($p) => {
            cy.get("[data-test='stateDropDown']").should("be.visible");
          });
      });

      it("BTE 50B: Landing page: state drop-down", () => {
        //testing typing in dropdown menu
        cy.get("[data-test='stateInput']")
          .type("Ne")
          .then(($p) => {
            cy.get("[data-test='stateDropDown']").should("be.visible");
          });

        cy.get("[data-test='stateInput']")
          .type("bras")
          .then(($p) => {
            cy.get("[data-test='stateDropDown']")
              .find("li")
              .contains("Nebraska");
          });
      });

      it("BTE 50C: Landing page: state drop-down", () => {
        cy.get("[data-test='stateInput']")
          .type("1")
          .then(($p) => {
            cy.get("[data-test='stateDropDown']")
              .find("li")
              .contains("No results found");
          });
      });
    });

    context("State Page", () => {
      it("BTE 51: Test Alaska Page", () => {
        cy.visit(alaska_paths[idx]);

        // Test links on page.
        cy.get("#State-Directory-Table")
          .find("a")
          .then((regLink) => {
            cy.get(regLink[0])
              .should("have.attr", "href")
              .and("include", "https://alaska.gov/");
          });
      });
    });
  });
});

//50 state pages are only in English
context("50 State Pages", () => {
  beforeEach(() => {
    cy.visit("/state-motor-vehicle-services");
  });

  it("BTE 52A: 50-state pages: state drop-down", () => {
    // testing dropdown menu on click
    cy.get("[data-test='stateInput']")
      .click()
      .then(($p) => {
        cy.get("[data-test='stateDropDown']").should("be.visible");
      });
  });

  it("BTE 52B: 50-state pages: state drop-down", () => {
    //testing typing in dropdown menu
    cy.get("[data-test='stateInput']")
      .type("Ne")
      .then(($p) => {
        cy.get("[data-test='stateDropDown']").should("be.visible");
      });

    cy.get("[data-test='stateInput']")
      .type("bras")
      .then(($p) => {
        cy.get("[data-test='stateDropDown']").find("li").contains("Nebraska");
      });
  });

  it("BTE 52C: 50-state pages: state drop-down", () => {
    cy.get("[data-test='stateInput']")
      .type("1")
      .then(($p) => {
        cy.get("[data-test='stateDropDown']")
          .find("li")
          .contains("No results found");
      });
  });
});