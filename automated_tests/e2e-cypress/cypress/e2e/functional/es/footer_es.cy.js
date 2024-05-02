const socials = require('../../../fixtures/socials.json')

describe('Footer [ES]', () => {
    beforeEach(() => {
        // Set base URL
        cy.visit('/es')
    })
    it('BTS 12: Footer links appear and work appropriately', () => {
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
    it('BTS 13: Email subscription form appears in footer and works appropriately', () => {
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
            'https://conectate.gobiernousa.gov', 
            { args: sentArgs }, 
            ({ email }) => {
                cy.get('input')
                    .filter('[name="email"]')
                    .should('have.value', email)
            }
        )

        // Go back to localhost to test submit button
        cy.visit('/es')
        cy.get('#footer-email')
            .type(validEmail)
            .should('have.value', validEmail)
        
        cy.get('.usa-sign-up')
            .find('button[type="submit"]')
            .click()

        // Origin URL should now be connect.usa.gov
        cy.origin(
            'https://conectate.gobiernousa.gov', 
            { args: sentArgs }, 
            ({ email }) => {
                cy.get('input')
                    .filter('[name="email"]')
                    .should('have.value', email)
            }
        )
    })
    it('BTS 14: Social media icons appear in footer and link to correct places', () => {
        for (let i = 0; i < socials.length; i++) {
            if (socials[i].linkEs.length > 0) { 
                cy.get('.usa-footer__contact-links')
                .find(`[alt="${socials[i].name} USAGov"]`)
                .should('have.attr', 'src', `/themes/custom/usagov/images/footer_icon_${socials[i].name.toLowerCase()}.svg`)
        
                cy.get('.usa-footer__contact-links')
                    .find(`[alt="${socials[i].name} USAGov"]`)
                    .parent()
                    .as('link')
                    .should('have.attr', 'href', socials[i].linkEs)
            }
        }
    })
    it('BTS 15: Contact Center information appears in footer and phone number links to /phone', () => {
        cy.get('#footer-phone')
            .find('a')
            .click()

        cy.url().should('include', '/es/llamenos')
    })
    it('BTS 16: Subfooter indicating USAGov is official site appears at very bottom', () => {
        cy.get('.usa-footer')
            .find('.usa-identifier__usagov-description')
            .should('contain', 'USAGov')
            .should('contain', 'la guía oficial')
    })
})