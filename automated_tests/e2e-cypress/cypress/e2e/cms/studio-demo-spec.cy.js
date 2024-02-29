// Code from Real World App (RWA)
describe('Cypress Studio Demo', () => {
  beforeEach(() => {
    // Seed database with test data
    cy.task('db:seed')

    // Login test user
    cy.database('find', 'users').then((user) => {
      cy.login(user.username, 's3cret', true)
    })
  })

  it('create new transaction', () => {
    // Extend test with Cypress Studio
  })
})