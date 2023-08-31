function reformatNumForMobile(toReformat) {
  "use strict";
  const numAndDesc = toReformat.textContent || toReformat.innerText;
  const checkForBracket = numAndDesc.split(">");
  let numberSplitter;
  if (checkForBracket.length > 1) {
    const removedBracket = checkForBracket[1];
    numberSplitter = removedBracket.split(" ");
  }
  else {
    numberSplitter = numAndDesc.split(" ");
  }
  const onlyNum = numberSplitter[0];
  const cleanNumber = onlyNum.replace(/\D/g, "");
  const onlyDesc = numberSplitter.slice(1, numberSplitter.length).join(" ");
  return `<a href="tel: ${cleanNumber}"> ${onlyNum} </a> ${onlyDesc} `;
}

const telephoneNumbers = document.querySelectorAll(".phoneNumberField");
for (let i = 0; i < telephoneNumbers.length; i++) {
  const numAndDesc = telephoneNumbers[i].textContent || telephoneNumbers[i].innerText  ;
  if (window.innerWidth <= 480) {
    telephoneNumbers[i].innerHTML = reformatNumForMobile(telephoneNumbers[i]);
  }
 else {
    telephoneNumbers[i].innerHTML = `${numAndDesc}`;
  }
}
