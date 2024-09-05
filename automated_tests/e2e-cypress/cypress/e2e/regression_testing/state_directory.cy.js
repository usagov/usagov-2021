describe("State Directory [ENG]", () => {
  beforeEach(() => {
    cy.visit("/state-governments");
  });

  // it('BTE 50: Landing page: state drop-down', () => {
  //     // testing dropdown menu
  //     cy.get('#block-usagov-content')
  //         .find('[id=stateForm]')
  //         .find('ul')
  //         .should('[role=listbox]')
  //         .each((el) => {
  //             cy.wrap(el)
  //                 .invoke('data-value')
  //                 .then(href => {
  //                     cy.request(href)
  //                         .its('status')
  //                         .should('eq', 200)
  //                 })
  //         })
  // })

  // it('BTE 51: Test Alaska Page', () => {
  //     cy.visit('/states/alaska')

  //     // Test links on page.
  //     cy.get('#State-Directory-Table').find('a').then(regLink => {
  //         cy.get(regLink[0]).should('have.attr', 'href').and('include', 'https://alaska.gov/')
  //         cy.get(regLink[1]).should('have.attr', 'href').and('include', 'https://gov.alaska.gov/')
  //     })
  // })

  it("BTE 52A: Landing page: state drop-down", () => {
    // testing dropdown menu on click
    cy.get("[data-test='stateInput']")
      .click()
      .then(($p) => {
        cy.get("[data-test='stateDropDown']").should("be.visible");
      });
  });
  it("BTE 52B: Landing page: state drop-down", () => {
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
  it("BTE 52C: Landing page: state drop-down", () => {
    cy.get("[data-test='stateInput']")
      .type("1")
      .then(($p) => {
        cy.get("[data-test='stateDropDown']")
          .find("li")
          .contains("No results found");
      });
  });
});
