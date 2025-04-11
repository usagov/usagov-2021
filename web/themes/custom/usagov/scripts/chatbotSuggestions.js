(function () {

    const suggestionBox = document.createElement("div");
    suggestionBox.classList.add("deep-chat-temporary-message");
    suggestionBox.innerHTML = '<button class="deep-chat-button deep-chat-suggestion-button" style="margin-top: 5px">Suggestion1</button>';

    // document.querySelector(".chat-element.chat-collapsed").appendChild(suggestionBox);
    
    document.querySelector("#block-usagov-aideepchatchatbot > div > div.ai-deepchat--header").style.paddingInline = "16px"
    document.querySelector("#block-usagov-aideepchatchatbot > div > div.ai-deepchat--header").style.backgroundColor = "#00BDE3";
    document.querySelector("#block-usagov-aideepchatchatbot > div > div.ai-deepchat--header").style.borderRadius = "0";
    document.querySelector("#block-usagov-aideepchatchatbot > div > div.ai-deepchat--header").style.borderWidth = "2px";
    document.querySelector("#block-usagov-aideepchatchatbot > div > div.ai-deepchat--header").style.borderColor = "#1B1B1B";
    document.querySelector("#block-usagov-aideepchatchatbot > div > div.ai-deepchat--header").style.outline = "none";
    document.querySelector("#block-usagov-aideepchatchatbot > div > div.ai-deepchat--header").style.color = "#1B1B1B";

    document.querySelector("#block-usagov-aideepchatchatbot > div > div.ai-deepchat--header > div.chat-dropdown > button").style.color = "#1B1B1B";
    document.querySelector("#chevron").style.fill = "#1B1B1B";
    document.querySelector("#chevron > path").setAttribute("fill", "")
    
    document.querySelector("#block-usagov-aideepchatchatbot > div").style.overflow = "visible";

    // // html string
    // const htmlStr = '<button class="button">Experimental</button>';

    // // make a new parser
    // const parser = new DOMParser();

    // // convert html string into DOM
    // const experimentalTag = parser.parseFromString(htmlStr, "text/html");

    document.querySelector("#block-usagov-aideepchatchatbot > div > div.ai-deepchat--header > div.chat-dropdown > button").style.paddingLeft = "0px"
    document.querySelector("#block-usagov-aideepchatchatbot > div > div.ai-deepchat--header > div.chat-dropdown > button").style.boxShadow = "none"

    document.querySelector("#block-usagov-aideepchatchatbot > div > div.ai-deepchat--header > div.ai-deepchat--label").style.paddingInline = "0px"

    document.querySelector("#block-usagov-aideepchatchatbot > div > div.ai-deepchat--header > div.ai-deepchat--label > .ai-deepchat--bullet").remove()
})();


document.addEventListener('DOMContentLoaded', function() {

    document.querySelector("#block-usagov-aideepchatchatbot > div > div.ai-deepchat--header").style.justifyContent = "left";

    const experimentalTag = document.createElement("button");
    experimentalTag.classList.add("chatbot-tag");
    experimentalTag.innerHTML = 'Experimental';
    experimentalTag.setAttribute("disabled", "")

    document.querySelector("#block-usagov-aideepchatchatbot > div > div.ai-deepchat--header > div.ai-deepchat--label").style.width = "max-content";
    document.querySelector("#block-usagov-aideepchatchatbot > div > div.ai-deepchat--header > div.ai-deepchat--label").after(experimentalTag);   
 }, false);