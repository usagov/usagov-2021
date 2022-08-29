console.log("in the og");

function reformatNumForMobile(toReformat) {
  //get phone number and description from innerText
  const numAndDesc = toReformat.innerText;
  console.log("numAndDec: " + numAndDesc);
  const numberSplitter = numAndDesc.split(" ");
  console.log("numberSplitter: " + numberSplitter);
  const onlyNum = numberSplitter[0];
  const cleanNumber = onlyNum.replace(/\D/g, "");
  console.log("cleanNumber: " + cleanNumber);
  const onlyDesc = numberSplitter.slice(1, numberSplitter.length).join(" ");
  console.log("onlyDesc: " + onlyDesc);
  return `<a href="tel: ${cleanNumber}"> ${onlyNum} </a> ${onlyDesc} `;
}

if (window.innerWidth <= 480) {
  const telephoneNumbers = document.querySelectorAll(".phoneNumberField");
  for (let i = 0; i < telephoneNumbers.length; i++) {
    // replace innerHTML with format
    telephoneNumbers[i].innerHTML = reformatNumForMobile(telephoneNumbers[i]);
  }
} else {
  const telephoneNumbers = document.querySelectorAll(".phoneNumberField");
  for (let i = 0; i < telephoneNumbers.length; i++) {
    //get phone number and description from innerText
    const numAndDesc = telephoneNumbers[i].innerText;
    console.log("numAndDec: " + numAndDesc);

    // replace innerHTML with format
    telephoneNumbers[i].innerHTML = `<p"> ${numAndDesc} </p> `;
  }
}
