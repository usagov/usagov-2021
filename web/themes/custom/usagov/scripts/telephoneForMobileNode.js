console.log("in the og");

function reformatNumForMobile(toReformat) {
  //get phone number and description from innerText
  const numAndDesc = toReformat.innerText;
  const numberSplitter = numAndDesc.split(" ");
  const onlyNum = numberSplitter[0];
  const cleanNumber = onlyNum.replace(/\D/g, "");
  const onlyDesc = numberSplitter.slice(1, numberSplitter.length).join(" ");
  return `<a href="tel: ${cleanNumber}"> ${onlyNum} </a> ${onlyDesc} `;
}

if (window.innerWidth <= 480) {
  // for /agency-index
  const telephoneNumbers = document.querySelectorAll(".phoneNumberField");
  for (let i = 0; i < telephoneNumbers.length; i++) {
    telephoneNumbers[i].innerHTML = reformatNumForMobile(telephoneNumbers[i]);
  }

  //for agency pages
  const telly = document.querySelector(".field--type-telephone");
  for (let i = 0; i < telly.children.length; i++) {
    telly.children[i].innerHTML = reformatNumForMobile(telly.children[i]);
  }
} else {
  const telephoneNumbers = document.querySelectorAll(".phoneNumberField");
  for (let i = 0; i < telephoneNumbers.length; i++) {
    //get phone number and description from innerText
    const numAndDesc = telephoneNumbers[i].innerText;
    telephoneNumbers[i].innerHTML = `<p> ${numAndDesc} </p> `;
  }
  //for agency pages
  const telly = document.querySelector(".field--type-telephone");
  for (let i = 0; i < telly.children.length; i++) {
    const numAndDesc = telly.children[i].innerText;
    telly.children[i].innerHTML = `<p> ${numAndDesc} </p> `;
  }
}
