console.log("testing disable menu");
let path = window.location.pathname;
console.log(path);

let menu_paths = [
  "/",
  "/about-the-us",
  "/money",
  "/laws-and-legal-issues",
  "/scams-and-fraud",
];

if (menu_paths.includes(path)) {
  if (path == "/") {
    let listItem = document.getElementById("usagov__logo");
    listItem.innerHTML =
      "<a role='link' aria-disabled='true' title='USAGov Logo'><img src='/themes/custom/usagov/images/LOGO_betasite_USAGOV_v2.png' alt='USAGov Logo'></a>";

  } else if (path == "/about-the-us") {
    let listItem = document.getElementById("usa-nav__about");
    listItem.innerHTML =
      "<a role='link' aria-disabled='true' class='usa-nav__link'><span>About the U.S. and its government</span></a>";
  } else if (path == "/money") {
    let listItem = document.getElementById("usa-nav__money");
    listItem.innerHTML =
      "<a role='link' aria-disabled='true' class='usa-nav__link'><span>Money</span></a>";
  } else if (path == "/laws-and-legal-issues") {
    let listItem = document.getElementById("usa-nav__law");
    listItem.innerHTML =
      "<a role='link' aria-disabled='true' class='usa-nav__link'><span>Laws and legal issues</span></a>";
  } else if (path == "/scams-and-fraud") {
    let listItem = document.getElementById("usa-nav__scams");
    listItem.innerHTML =
      "<a role='link' aria-disabled='true' class='usa-nav__link'><span>Scams and fraud</span></a>";
  } else {
    //console.log(`Should never hit me`);
  }
}
