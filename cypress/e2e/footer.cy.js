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
    it.only('Social media icons appear in footer and link to correct places', () => {
        const socials = [
            {
                name: 'Facebook',
                img: '/themes/custom/usagov/images/footer_icon_facebook.svg',
                link: 'https://www.facebook.com/USAgov'
            },
            {
                name: 'Twitter',
                img: '/themes/custom/usagov/images/footer_icon_twitter.svg',
                link: 'https://twitter.com/USAgov'
            },
            {
                name: 'Youtube',
                img: '/themes/custom/usagov/images/footer_icon_youtube.svg',
                link: 'https://www.youtube.com/usagov1'
            },
            {
                name: 'Instagram',
                img: '/themes/custom/usagov/images/footer_icon_instagram.svg',
                link: 'https://www.instagram.com/usagov/'
            },
        ]
        
        for (let i = 0; i < socials.length; i++) {
            cy.get('.usa-footer__contact-links')
                .find(`[alt="${socials[i].name} USAGov"]`)
                .should('have.attr', 'src', socials[i].img)
        
            cy.get('.usa-footer__contact-links')
                .find(`[alt="${socials[i].name} USAGov"]`)
                .parent()
                .as('link')
                .should('have.attr', 'href', socials[i].link)
        }
    })
})