const paths = {};
paths["home"]="https://www.usa.gov/";
paths["home_es"]="https://www.usa.gov/es";
paths["state_governments"]="https://www.usa.gov/state-governments";
paths["state_governments_es"]="https://www.usa.gov/es/gobiernos-estatales";

context("Visual Regression Tests", () => {
  for (let name in paths){
    it("Create snapshots for visual tests", () => {
      cy.visit(paths[name]);
      cy.scrollTo('bottom', { duration: 1500 });
      cy.compareSnapshot(name);
    });
  }
});
