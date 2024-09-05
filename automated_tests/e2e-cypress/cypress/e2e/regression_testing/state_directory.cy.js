describe("State Directories", () => {
  context("State Directory", () => {
    beforeEach(() => {
      cy.visit("/state-governments");
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
          cy.get("[data-test='stateDropDown']").find("li").contains("Nebraska");
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
      cy.visit("/states/alaska");

      // Test links on page.
      cy.get("#State-Directory-Table")
        .find("a")
        .then((regLink) => {
          cy.get(regLink[0])
            .should("have.attr", "href")
            .and("include", "https://alaska.gov/");
          cy.get(regLink[1])
            .should("have.attr", "href")
            .and("include", "https://gov.alaska.gov/");
        });
    });
  });

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
});
