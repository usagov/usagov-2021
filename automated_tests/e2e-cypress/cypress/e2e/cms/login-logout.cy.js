describe('Local cms login', () => {
  it('Logs in and logs out', () => {
    //log into local cms
    Cypress.on('uncaught:exception', () => false)
    cy.logIn()
    cy.logOut()
  })
})
