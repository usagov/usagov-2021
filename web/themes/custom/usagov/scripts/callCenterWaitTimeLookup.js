jQuery(document).ready(async function () {
  "use strict";
  var loadJS = function(url, implementationCode, location) {
    // url is URL of external file, implementationCode is the code
    // to be called from the file, location is the location to
    // insert the <script> element
    console.log('loadJS');
    var scriptTag = document.createElement('script');
    scriptTag.src = url;

    scriptTag.onload = implementationCode;
    scriptTag.onreadystatechange = implementationCode;

    location.appendChild(scriptTag);
  };


  var waittimes = function() {
    // const AWS = require('aws-sdk');
    AWS.config.update({"region": 'us-east-1'});

    const s3 = new AWS.S3();

    if (jQuery("#callCenterTime").length > 0) {
      console.log('element found');
      s3.getObject({"Bucket": 'cg-8463e88a-3e82-4860-9515-edfb3f47ae0f',
                    "Key": 'waittime.json'},
        function(err, data) {
          if (err) {
            console.log('fail');
            console.log(err);
            jQuery('#callCenterTime').html(err);
          }
          else {
            console.log('success');
            console.log(data.Body.toString());
            jQuery('#callCenterTime').html(data.Body.toString());
          }
        }
      );
    }
  };
  loadJS('https://sdk.amazonaws.com/js/aws-sdk-2.1.12.min.js', waittimes, document.body);
});