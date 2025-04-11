(function () {

    const suggestionBox = document.createElement("div");
    suggestionBox.classList.add("deep-chat-temporary-message");
    suggestionBox.innerHTML = '<button class="deep-chat-button deep-chat-suggestion-button" style="margin-top: 5px">Suggestion1</button>';

    document.querySelector("#chevron > path").setAttribute("fill", "")

})();

document.addEventListener('DOMContentLoaded', function() {
    const experimentalTag = document.createElement("button");
    experimentalTag.classList.add("chatbot-tag");
    experimentalTag.innerHTML = 'Experimental';
    experimentalTag.setAttribute("disabled", "")

    document.querySelector("#block-usagov-aideepchatchatbot > div > div.ai-deepchat--header > div.ai-deepchat--label").after(experimentalTag);   

 }, false);