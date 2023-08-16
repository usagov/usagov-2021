// Import statements
import './commands'
import "cypress-real-events"
import 'cypress-axe'
import 'cypress-mochawesome-reporter/register'

Cypress.on('uncaught:exception', (err, runnable) => {
    // returning false here prevents Cypress from
    // failing the test
    return false
})

// Import and add Cypress image command
const compareSnapshotCommand = require('cypress-image-diff-js/dist/command')
compareSnapshotCommand()