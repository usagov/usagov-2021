var pattern = /[0-9]?[-. ]?\(?([0-9]{3})\)?[-. ]([0-9]{3})[-. ]([0-9]{4})/g;
var safariCheck = /<a href="tel:[0-9]?[-. ]\(?([0-9]{3})\)?[-. ]([0-9]{3})[-. ]([0-9]{4})">[0-9]?[-. ]\(?([0-9]{3})\)?[-. ]([0-9]{3})[-. ]([0-9]{4})<\/a>/g;

if (window.innerWidth <= 480) {
  if (document.body.innerHTML.search(safariCheck) === -1) {
    document.body.getElementsByTagName("main")[0].innerHTML = document.body.getElementsByTagName("main")[0].innerHTML.replace(pattern, '<a href="tel:$&">$&</a>');
  }
}
