describe('Footer', () => {
    beforeEach(() => {
        // Set viewport size and base URL
        cy.viewport('macbook-13')
        cy.visit('/')
    })
    it('screenshot test', () => {
        cy.argosScreenshot("home")
    })
    it('Footer links appear and work appropriately', () => {
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
    it('Email subscription form appears in footer and works appropriately', () => {
        const validEmail = 'test@usa.gov'
        const invalidEmail = 'test'

        // Test invalid email
        cy.get('#footer-email')
            .type(invalidEmail)
            .should('have.value', invalidEmail)
            .type('{enter}')

        cy.get('input:invalid').should('have.length', 1)
        cy.get('input:invalid').clear()

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
    it.only('Social media icons appear in footer and link to correct places', () => {
        // Facebook
        cy.get('.usa-footer__contact-links')
            .find('[alt="Facebook USAGov"]')
            .should('have.attr', 'src', '/themes/custom/usagov/images/footer_icon_facebook.svg')
        
        cy.get('.usa-footer__contact-links')
            .find('[alt="Facebook USAGov"]')
            .parent()
            .as('link')
            .should('have.attr', 'href', 'https://www.facebook.com/USAgov')

        // Twitter
        cy.get('.usa-footer__contact-links')
            .find('[alt="Twitter USAGov"]')
            .should('have.attr', 'src', '/themes/custom/usagov/images/footer_icon_twitter.svg')
        
        cy.get('.usa-footer__contact-links')
            .find('[alt="Twitter USAGov"]')
            .parent()
            .should('have.attr', 'href', 'https://twitter.com/USAgov')
        
        // Youtube
        cy.get('.usa-footer__contact-links')
            .find('[alt="Youtube USAGov"]')
            .should('have.attr', 'src', '/themes/custom/usagov/images/footer_icon_youtube.svg')
        
        cy.get('.usa-footer__contact-links')
            .find('[alt="Youtube USAGov"]')
            .parent()
            .should('have.attr', 'href', 'https://www.youtube.com/usagov1')

        // Instagram
        cy.get('.usa-footer__contact-links')
            .find('[alt="Instagram USAGov"]')
            .should('have.attr', 'src', '/themes/custom/usagov/images/footer_icon_instagram.svg')
        
        cy.get('.usa-footer__contact-links')
            .find('[alt="Instagram USAGov"]')
            .parent()
            .should('have.attr', 'href', 'https://www.instagram.com/usagov/')
    })
})