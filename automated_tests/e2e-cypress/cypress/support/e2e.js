// ***********************************************************
// This example support/e2e.js is processed and
// loaded automatically before your test files.
//
// This is a great place to put global configuration and
// behavior that modifies Cypress.
//
// You can change the location of this file or turn off
// automatically serving support files with the
// 'supportFile' configuration option.
//
// You can read more here:
// https://on.cypress.io/configuration
// ***********************************************************

// Import commands.js using ES2015 syntax:
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
// const compareSnapshotCommand = require('cypress-image-diff-js/dist/command')
import compareSnapshotCommand from 'cypress-image-diff-js/command';
compareSnapshotCommand()
