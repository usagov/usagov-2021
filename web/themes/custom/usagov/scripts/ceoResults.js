/**
 * Build and execute request to look up elected officials for provided address.
 * @param {string} address Address for which to fetch elected officials info.
 * @param {function(Object)} callback Function which takes the response object as a parameter.
 */
function lookup(address) {

    alert('This is a Proof-of-Concept');

    var searchLoc = '6017+Cypress+Cove+Dr,+The+Colony,+TX';
    var token = '6qv-f396635c14a14df1a32c';
    var user = 3149;
    var url = 'https://app.cicerodata.com/v3.1/official?search_address=search_loc=' + searchLoc + '&format=json&token=' + token + '&user=' + user;

    jQuery.get(url, function (data) {

        var html = '';
        var officials = data.response.results.candidates[0].officials;

        // Report on National Positions
        html += '<h3>Federal Level:</h3>'
        html += '<ul>';
        html += findAndRenderItem(officials, 'NATIONAL_EXEC', 'President');
        html += findAndRenderItem(officials, 'NATIONAL_EXEC', 'Vice President');
        html += findAndRenderItem(officials, 'NATIONAL_UPPER', 'Senator');
        html += findAndRenderItem(officials, 'NATIONAL_LOWER', 'Representative');
        html += '</ul>';

        // Report on State Positions
        html += '<h3>State Level:</h3>'
        html += '<ul>';
        html += findAndRenderItem(officials, 'STATE_EXEC', 'Governor');
        html += findAndRenderItem(officials, 'STATE_EXEC', 'Lieutenant Governor');
        html += findAndRenderItem(officials, 'STATE_UPPER', 'Senator');
        html += findAndRenderItem(officials, 'STATE_LOWER', 'Representative');
        html += '</ul>';

        // Everything else
        html += '<h3>Everything else:</h3>'
        html += '<ul>';
        html += findAndRenderItem(officials, '', '');
        html += '</ul>';

        debugger;
        jQuery('.content-wrapper').html(html);
    });
}

function findAndRenderItem(officials, filterDistrictType, filterTitle) {

    var html = '';

    for ( var x = 0 ; x < officials.length ; x++ ) {

        var thisOfficial = officials[x];
        if (typeof thisOfficial == 'undefined') continue;

        if (filterDistrictType != '') if (thisOfficial.office.district.district_type != filterDistrictType) continue;
        if (filterTitle != '') if (thisOfficial.office.title != filterTitle) continue;

        html += '<li>';
        html += '<b>' + thisOfficial.office.title + '</b><br/>';
        html += thisOfficial.first_name + ' ' + thisOfficial.last_name + '<br/>';
        html += 'Party: ' + thisOfficial.party + '<br/>';
        html += 'Adddress: ' + thisOfficial.addresses[0].address_1 + ' ' + thisOfficial.addresses[0].address_2 + ' ' + thisOfficial.addresses[0].city + ' ' + thisOfficial.addresses[0].state + '<br/>';
        html += 'Phone: ' + thisOfficial.addresses[0].phone_1 + '<br/>';
        html += 'Website: ' + thisOfficial.web_form_url + '<br/>';
        html += 'Social Media: <br/>';
        for (var i = 0 ; i < thisOfficial.identifiers.length ; i++) {
            html += '--' + thisOfficial.identifiers[i].identifier_type + ' = ' + thisOfficial.identifiers[i].identifier_value + '<br/>';
        }
        html += '<br/></li>';

        console.log(thisOfficial.office.district.district_type + ' => ' +  + ' => ' + thisOfficial.office.title);
        delete officials[x];
    }

    return html;
}

/**
 * Process form data, display the address, and search for elected officials.
 */
function load() {
    let searchParams = getSearchParams();

    let inputStreet = searchParams.get('input-street');
    let inputCity = searchParams.get('input-city');
    let inputState = searchParams.get('select-dropdown');

    let inputZip = searchParams.get('input-zip');
    let normalizedAddress = inputStreet + ", " + inputCity + ", " + inputState + " " + inputZip;
    let displayAddress = document.getElementById("display-address");
    displayAddress.innerHTML = DOMPurify.sanitize(normalizedAddress.replace(", ", "<br>"));

    lookup(normalizedAddress);
}

function getSearchParams() {
    const paramsString = window.location.search;
    const searchParams = new URLSearchParams(paramsString);
    return searchParams;
}

function replaceSpaces(string) {
    return string.toLowerCase().replaceAll(" ", "_");
}

// Load the GAPI Client Library
document.addEventListener('DOMContentLoaded', function() {
    load ();
});
