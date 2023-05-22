var pattern = /[0-9]?[-. ]\(?([0-9]{3})\)?[-. ]([0-9]{3})[-. ]([0-9]{4})/g;
if (window.innerWidth <= 480) {
  document.body.innerHTML = document.body.innerHTML.replace(pattern, '<a href="tel: $&"> $& </a>');
}
