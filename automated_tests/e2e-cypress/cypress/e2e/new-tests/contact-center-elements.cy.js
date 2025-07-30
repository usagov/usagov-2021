// A sampling of paths for pages that SHOULD HAVE the phone number and "have a question" box
const paths_with_all_contact_info = [
  "/travel-documents-children",
];

// A sampling of paths for pages that SHOULD NOT HAVE the phone number and "have a question" box
const paths_without_contact_info = [
  "/tax-refunds",
];

// A sampling of paths for pages that SHOULD HAVE the phone number but NOT the "have a question" box
const paths_phone_only = [
  "/",
  "/es",
];


describe('Contact Center phone number is present in header and Have a Question box is on pages (but may be hidden by CSS)', () => {
  paths_with_all_contact_info.forEach((path, idx) => {

    it(`${path} has all Contact Center elements`, () => {
      cy.visit(path);
      cy.get("#top-phone-mobile-menu").should("exist");
      cy.get("#top-phone").should("exist");
      cy.get("#question-box").should("exist");
    });
  });
});

describe('Contact Center phone number is in header; Have a Question box does not', () => {
  paths_phone_only.forEach((path, idx) => {

    it(`${path} has phone number in header, does NOT have Have a Question box`, () => {
      cy.visit(path);
      cy.get("#top-phone-mobile-menu").should("exist");
      cy.get("#top-phone").should("exist");
      cy.get("#question-box").should("not.exist");
    });
  });
});

describe('Neither phone number in header nor Have a Question box is present', () => {
  paths_without_contact_info.forEach((path, idx) => {

    it(`${path} does NOT have phone number in header, and does NOT have Have a Question box`, () => {
      cy.visit(path);
      cy.get("#top-phone-mobile-menu").should("not.exist");
      cy.get("#top-phone").should("not.exist");
      cy.get("#question-box").should("not.exist");
    });
  });
});

