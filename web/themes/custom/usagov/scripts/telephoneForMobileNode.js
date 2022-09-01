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
  const phoneNumberFieldExists =
    document.getElementsByClassName("phoneNumberField").length > 0;

  if (phoneNumberFieldExists) {
    // for /agency-index
    const telephoneNumbers = document.querySelectorAll(".phoneNumberField");
    for (let i = 0; i < telephoneNumbers.length; i++) {
      telephoneNumbers[i].innerHTML = reformatNumForMobile(telephoneNumbers[i]);
    }
  } else {
    //for agency pages
    const telly = document.querySelectorAll(".field--type-telephone");
    for (let i = 0; i < telly.length; i++) {
      telly[i].innerHTML = reformatNumForMobile(telly[i]);
    }
  }
} else {
  const phoneNumberFieldExists =
    document.getElementsByClassName("phoneNumberField").length > 0;
  if (phoneNumberFieldExists) {
    const telephoneNumbers = document.querySelectorAll(".phoneNumberField");
    for (let i = 0; i < telephoneNumbers.length; i++) {
      const numAndDesc = telephoneNumbers[i].innerText;
      telephoneNumbers[i].innerHTML = `${numAndDesc}`;
    }
  } else {
    const telly = document.querySelectorAll(".field--type-telephone");
    for (let i = 0; i < telly.length; i++) {
      const numAndDesc = telly[i].innerText;
      console.log("numAndDesc: " + numAndDesc);
      telly[i].innerHTML = `<p> ${numAndDesc} </p> `;
    }
  }
}
