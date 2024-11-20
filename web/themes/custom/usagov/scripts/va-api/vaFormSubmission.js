const key = ``

function JSONtoBase64(jData) {
  const utf8Str = unescape(encodeURIComponent
      (JSON.stringify(jData)));
  const res = btoa(utf8Str);
  return res;
}

// audience = https://deptva-eval.okta.com/oauth2/ausdg7guis2TYDlFe2p7/v1/token
// clientId = The client ID, need to use JWT

function getAssertionPrivatekey(clientId, key, audience) {
  clientId = JSONtoBase64(clientId);

  let secondsSinceEpoch = Math.round(Date.now() / 1000);
  const claims = { 
    "aud": audience,
    "iss": clientId,
    "sub": clientId,
    "iat": secondsSinceEpoch,
    "exp": secondsSinceEpoch + 3600,
    "jti": crypto.randomUUID()
  };
  
  let secret = key;
  let algorithm = "RS256";
  const token = jwt.create(claims, secret, algorithm); 
  return token.compact();
}

// TO-DO: Crear funcionar para conseguir el Veteran ID


async function getBearerToken() {
    const requestHeaders = new Headers();
    requestHeaders.append("Content-Type", "application/x-www-form-urlencoded");

    // TO-DO: Hay que conseguir el veteran ID con el call que existe y convertir eso "{"patient": "Aqui va el ID"}" a Base 64. 
    // Eso va en la variable "launch"
    
    const urlencoded = new URLSearchParams();
    urlencoded.append("grant_type", "client_credentials");
    urlencoded.append("client_assertion_type", "urn:ietf:params:oauth:client-assertion-type:jwt-bearer");
    urlencoded.append("client_assertion", getAssertionPrivatekey("0oazwnq78bgF2Y4gA2p7", key, "https://deptva-eval.okta.com/oauth2/ausi3u00gw66b9Ojk2p7/v1/token"));
    urlencoded.append("scope", "disability_rating.read enrolled_benefits.read flashes.read launch service_history.read veteran_status.read");
    urlencoded.append("launch", JSONtoBase64({"patient": "1012667145V762142"}));
    
    const requestOptions = {
      method: "POST",
      headers: requestHeaders,
      body: urlencoded,
      redirect: "follow"
    };
    
    const response = await fetch("https://sandbox-api.va.gov/oauth2/veteran-verification/system/v1/token", requestOptions)
      .then((response) => response.text())
      .then((result) => console.log(result))
      .catch((error) => console.error(error));

    var responseText = response.text();

    if (!response.ok || (await responseText).includes("<Error>")) {
        return (responseText);
    }

    return await responseText;
}