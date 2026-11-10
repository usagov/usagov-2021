// USAGOV-2946: Spanish pages now redirect to English (Presidential directive 2026-11-10); /es paths removed.
const paths = ["/agency-index"];

paths.forEach(path => {
  let lang;
  if (path === "/agency-index") {
    lang = "English";
  } else {
    lang = "Español";
  }
  describe(`${lang} Federal Directory`, () => {
    // Set base URL
    beforeEach(() => {
      cy.visit(path);
    });
    it('BTE 48: Landing page: letter name navigation', () => {
        // test navigating with letter names
        if (lang == "English") {
        cy.get('ul.usagov-directory-container-az')
            .find('li')
            .should('have.length', 22)
        } else {
            cy.get('ul.usagov-directory-container-az')
            .find('li')
            .should('have.length', 16)
        }
        cy.get('ul.usagov-directory-container-az')
        .find('li>a').not('.is-active')
            .each((el) => {
                cy.wrap(el)
                    .should('have.attr', 'href')
                    .then(href => {
                        cy.request(href)
                            .its('status')
                            .should('eq', 200)
                    })
            })
         })
    })
})