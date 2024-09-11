const paths = {};
paths["home"]="/";
paths["spanish_home"]="/es";
paths["state_governments"]="/state-governments"


context("Visual Regression Tests", () => {
  for (let name in paths){
    it("Create snapshots for visual tests", () => {
      cy.visit(paths[name]);
      cy.compareSnapshot(name)
    });
  }
});
