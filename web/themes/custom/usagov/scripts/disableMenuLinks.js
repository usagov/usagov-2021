let path = window.location.pathname;

let menu_id = [
  "usa_nav__about",
  "usa_nav__money",
  "usa_nav__law",
  "usa_nav__scams",
  "usa_nav__acerca",
  "usa_nav__dinero",
  "usa_nav__leyes",
  "usa_nav__estafas",
];

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

let dicts = {};
for (objId in menu_id) {
  dicts[menu_paths[objId]] = menu_id[objId];
}

if (path in dicts) {
  console.log("YUP");
  let listItem;
  listItem = document.getElementById(dicts[path]);
  let aElem = listItem.getElementsByTagName("a")[0];
  aElem.setAttribute("href", "#skip-to-h1");
  aElem.setAttribute("aria-current", "page");
  aElem.classList.add("currentMenuItem");
}else{
  console.log("NOPE");
};

// if (menu_paths.includes(path)) {
//   let listItem;
//   if (path == "/about-the-us") {
//     listItem = document.getElementById("usa-nav__about");
//   } else if (path == "/money") {
//     listItem = document.getElementById("usa-nav__money");
//   } else if (path == "/laws-and-legal-issues") {
//     listItem = document.getElementById("usa-nav__law");
//   } else if (path == "/scams-and-fraud") {
//     listItem = document.getElementById("usa-nav__scams");
//   } else if (path == "/es/acerca-de-estados-unidos") {
//     listItem = document.getElementById("usa-nav__acerca");
//   } else if (path == "/es/dinero") {
//     listItem = document.getElementById("usa-nav__dinero");
//   } else if (path == "/es/leyes-y-asuntos-legales") {
//     listItem = document.getElementById("usa-nav__leyes");
//   } else if (path == "/es/estafas-y-fraudes") {
//     listItem = document.getElementById("usa-nav__estafas");
//   } else {
//     //console.log(`Should never hit me`);
//   }
//   let aElem = listItem.getElementsByTagName("a")[0];
//   aElem.setAttribute("href", "#skip-to-h1");
//   aElem.setAttribute("aria-current", "page");
//   aElem.classList.add("currentMenuItem");
// }
