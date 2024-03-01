describe('Local cms login', () => {
  it('Gets, types and clicks', () => {
    //log into local cms
    Cypress.on('uncaught:exception', () => false)

    cy.logIn()

    //navigate menu to add content to a basic page
    //cy.get('div > a#toolbar-item-administration')
    //cy.get('ul.toolbar-menu:first > li.menu-item:nth-of-type(2) > a ~ ul.toolbar-menu:first > li.menu-item:first > a ~ ul.toolbar-menu:first > li.menu-item:first > a').focus().click()
    cy.get('div > a#toolbar-item-administration')
    cy.get('ul.toolbar-menu:first > li.menu-item:nth-of-type(2) > a').trigger('hover','center')


  })
})
