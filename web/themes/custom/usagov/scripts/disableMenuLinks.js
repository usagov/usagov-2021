let path = window.location.pathname;
console.log(`THE PATH IS: ${path}`);

let menu_paths = [
  "/",
  "/about-the-us",
  "/money",
  "/laws-and-legal-issues",
  "/scams-and-fraud",
  "/es/",
  "/es",
  "/es/acerca-de-estados-unidos",
  "/es/dinero",
  "/es/leyes-y-asuntos-legales",
  "/es/estafas-y-fraudes",
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
  } else if (path == "/es/acerca-de-estados-unidos") {
    let listItem = document.getElementById("usa-nav__acerca");
    listItem.innerHTML =
      "<a role='link' aria-disabled='true' class='usa-nav__link'><span> Acerca de EE. UU. y directorios del Gobierno</span></a>";
  } else if (path == "/es/dinero") {
    let listItem = document.getElementById("usa-nav__dinero");
    listItem.innerHTML =
      "<a role='link' aria-disabled='true' class='usa-nav__link'><span>Dinero</span></a>";
  } else if (path == "/es/leyes-y-asuntos-legales") {
    let listItem = document.getElementById("usa-nav__leyes");
    listItem.innerHTML =
      "<a role='link' aria-disabled='true' class='usa-nav__link'><span>Leyes y asuntos legales</span></a>";
  } else if (path == "/es/estafas-y-fraudes") {
    let listItem = document.getElementById("usa-nav__estafas");
    listItem.innerHTML =
      "<a role='link' aria-disabled='true' class='usa-nav__link'><span>Estafas y fraudes</span></a>";
  } else if (path == "/es" || path == "/es/") {
    let listItem = document.getElementById("usagov__sobre");
    listItem.innerHTML =
      "<a role='link' aria-disabled='true' title='USAGov Logo'><img class='es' src='/themes/custom/usagov/images/LOGO_betasite_USAGOV_SP.png' alt='USAGov en Español Logo'></a>";
  } else {
    //console.log(`Should never hit me`);
  }
}
