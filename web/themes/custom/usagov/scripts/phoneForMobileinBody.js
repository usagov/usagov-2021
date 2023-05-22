console.log("yo.");
var pattern =/\(?([0-9]{3})\)?[-. ]?([0-9]{3})[-. ]?([0-9]{4})/g;
var match;
var mainContent = document.getElementById("main-content").innerText;
while ((match = pattern.exec(mainContent)) != null) {
  console.log(match);
  console.log(match[0]);
}