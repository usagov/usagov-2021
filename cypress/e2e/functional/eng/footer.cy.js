const socials = require('../../../fixtures/socials.json')

describe('Footer [ENG]', () => {
    beforeEach(() => {
        // Set base URL
        cy.visit('/')
    })
    it('BTE 12: Footer links appear and work appropriately', () => {
        cy.get('.usa-footer__nav')
            .find('a')
            .not('[href="/website-analytics/"]')
            .each((link) => {
                cy.wrap(link).invoke('attr', 'href')
                    .then(href => {
                        cy.request(href)
                            .its('status')
                            .should('eq', 200)
                    })
            })
    })
    it('BTE 13: Email subscription form appears in footer and works appropriately', () => {
        const validEmail = 'test@usa.gov'
        const invalidEmails = ['test@#$1123', 'test2@', '@test3.com']

        // Test invalid emails
        for (let i = 0; i < invalidEmails.length; i++) {
            cy.get('#footer-email')
                .type(invalidEmails[i])
                .should('have.value', invalidEmails[i])
                .type('{enter}')

            cy.get('input:invalid').should('have.length', 1)
            cy.get('input:invalid').clear()
        }

        // Test valid email
        cy.get('#footer-email')
            .type(validEmail)
            .type('{enter}')

        // Origin URL should now be connect.usa.gov
        const sentArgs = { email: validEmail }
        cy.origin(
            'https://connect.usa.gov/', 
            { args: sentArgs }, 
            ({ email }) => {
                cy.get('input')
                    .filter('[name="email"]')
                    .should('have.value', email)
            }
        )

        // Go back to localhost to test submit button
        cy.visit('/')
        cy.get('#footer-email')
            .type(validEmail)
            .should('have.value', validEmail)
        
        cy.get('.usa-sign-up')
            .find('button[type="submit"]')
            .click()

        // Origin URL should now be connect.usa.gov
        cy.origin(
            'https://connect.usa.gov/', 
            { args: sentArgs }, 
            ({ email }) => {
                cy.get('input')
                    .filter('[name="email"]')
                    .should('have.value', email)
            }
        )
    })
    it('BTE 14: Social media icons appear in footer and link to correct places', () => {
        for (let i = 0; i < socials.length; i++) {
            cy.get('.usa-footer__contact-links')
                .find(`[alt="${socials[i].name} USAGov"]`)
                .should('have.attr', 'src', `/themes/custom/usagov/images/footer_icon_${socials[i].name.toLowerCase()}.svg`)
        
            cy.get('.usa-footer__contact-links')
                .find(`[alt="${socials[i].name} USAGov"]`)
                .parent()
                .as('link')
                .should('have.attr', 'href', socials[i].link)
        }
    })
    it('BTE 15: Contact Center information appears in footer and phone number links to /phone', () => {
        cy.get('#footer-phone')
            .find('a')
            .click()

        cy.url().should('include', '/phone')
    })
    it('BTE 16: Subfooter indicating USAGov is official site appears at very bottom', () => {
        cy.get('.usa-footer')
            .find('.usa-identifier__usagov-description')
            .should('contain', 'USAGov')
            .should('contain', 'official guide')
    })
})