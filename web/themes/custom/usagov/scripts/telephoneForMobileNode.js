function reformatNumForMobile(toReformat) {
  //get phone number and description from innerText
  const numAndDesc = toReformat.innerText;
  const numberSplitter = numAndDesc.split(" ");
  const onlyNum = numberSplitter[0];
  const cleanNumber = onlyNum.replace(/\D/g, "");
  const onlyDesc = numberSplitter.slice(1, numberSplitter.length).join(" ");
  return `<a href="tel: ${cleanNumber}"> ${onlyNum} </a> ${onlyDesc} `;
}

const telephoneNumbers = document.querySelectorAll(".phoneNumberField");
for (let i = 0; i < telephoneNumbers.length; i++) {
  const numAndDesc = telephoneNumbers[i].innerText;
  if (window.innerWidth <= 480) {
    telephoneNumbers[i].innerHTML = reformatNumForMobile(telephoneNumbers[i]);
  } else {
    telephoneNumbers[i].innerHTML = `${numAndDesc}`;
  }
}
