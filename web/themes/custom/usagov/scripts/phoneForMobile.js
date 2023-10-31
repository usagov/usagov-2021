var pattern = /[0-9]?[-. ]?\(?([0-9]{3})\)?[-. ]([0-9]{3})[-. ]([0-9]{4})/g;
var safariCheck = /<a href="tel:[0-9]?[-. ]\(?([0-9]{3})\)?[-. ]([0-9]{3})[-. ]([0-9]{4})">[0-9]?[-. ]\(?([0-9]{3})\)?[-. ]([0-9]{3})[-. ]([0-9]{4})<\/a>/g;

if (window.innerWidth <= 480) {
  if (document.body.innerHTML.search(safariCheck) === -1) {
    document.body.innerHTML = document.body.innerHTML.replace(pattern, '<a href="tel:$&">$&</a>');

    var footerPattern = /<ahref="\/phone">1-844-USAGOV1\(<\/a><ahref="tel:1-844-872-4681">1-844-872-4681<\/a>\)/g;
    var footerPhoneWithSpaces = document.getElementsByClassName("usa-footer__contact-info")[0].innerHTML;
    var footerPhoneNoSpaces = footerPhoneWithSpaces.replace(/\s/g, "");

    if (footerPhoneNoSpaces.match(footerPattern)) {
      document.getElementsByClassName("usa-footer__contact-info")[0].innerHTML = '<a href="/phone"> 1-844-USAGOV1 (1-844-872-4681) </a>';
    }
  }
}
