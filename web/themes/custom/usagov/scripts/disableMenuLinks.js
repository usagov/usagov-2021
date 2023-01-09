let path = window.location.pathname;

let menu_paths = [
  "/about-the-us",
  "/money",
  "/laws-and-legal-issues",
  "/scams-and-fraud",
  "/es/acerca-de-estados-unidos",
  "/es/dinero",
  "/es/leyes-y-asuntos-legales",
  "/es/estafas-y-fraudes",
];

if (menu_paths.includes(path)) {
  let listItem;
  if (path == "/about-the-us") {
    listItem = document.getElementById("usa-nav__about");
  } else if (path == "/money") {
    listItem = document.getElementById("usa-nav__money");
  } else if (path == "/laws-and-legal-issues") {
    listItem = document.getElementById("usa-nav__law");
  } else if (path == "/scams-and-fraud") {
    listItem = document.getElementById("usa-nav__scams");
  } else if (path == "/es/acerca-de-estados-unidos") {
    listItem = document.getElementById("usa-nav__acerca");
  } else if (path == "/es/dinero") {
    listItem = document.getElementById("usa-nav__dinero");
  } else if (path == "/es/leyes-y-asuntos-legales") {
    listItem = document.getElementById("usa-nav__leyes");
  } else if (path == "/es/estafas-y-fraudes") {
    listItem = document.getElementById("usa-nav__estafas");
  } else {
    //console.log(`Should never hit me`);
  }
  let aElem = listItem.getElementsByTagName("a")[0];
  aElem.setAttribute("href", "#skip-to-h1");
  aElem.classList.add("currentMenuItem");
}
