function chatbotToogle() {
    'use strict';

    const chatbotContainer = document.getElementsByClassName("usagov-ai-chatbot-container")[0];
    const chatbotBody = document.getElementsByClassName("usagov-ai-chatbot-body")[0];
    const chatbotToogle = document.getElementById("usagov-ai-chatbot-toogle");

    if (chatbotContainer.classList.contains("chat-open")) {
        chatbotContainer.classList.remove("chat-open");
        chatbotContainer.classList.add("chat-collapsed");
        chatbotContainer.style.transform = "translateY(" + chatbotBody.offsetHeight + "px)";
        chatbotToogle.innerHTML = "+";
        chatbotToogle.style.fontSize = "24px";
    }
    else {
        chatbotContainer.classList.remove("chat-collapsed");
        chatbotContainer.classList.add("chat-open");
        chatbotContainer.style.transform = "translateY(0)";
        chatbotToogle.innerHTML = "-";
        chatbotToogle.style.fontSize = "30px";
    }

}

function sendMessage() {
    'use strict';

    const inputValue = document.getElementById("userMessage").value;

    if (inputValue) {
        addMessage(inputValue);
        document.getElementById("userMessage").value = "";
    }

}

function sendSuggestion(element) {
    'use strict';

    const selectedSuggestion = element.innerHTML;

    if (selectedSuggestion) {
        addMessage(selectedSuggestion);
        const suggestionsContainer = document.getElementsByClassName('usagov-ai-chatbot-suggestions')[0];
        suggestionsContainer.style.display = 'none';
    }
}

async function addMessage(message) {
    'use strict';

    const messageContainer = document.getElementsByClassName("usagov-ai-chatbot-messages")[0];

    // make a new parser
    const parser = new DOMParser();
    const newMessage = parser.parseFromString("<div class='usagov-ai-chatbot-message user'><div class='text'>" + message +"</div><img class='message-image user' src='/themes/custom/usagov/images/chatbot/usagov-user-avatar.png' alt='USA.gov User Avatar' /></div>", "text/html");

    messageContainer.appendChild(newMessage.body.firstChild);

    if (document.getElementsByClassName("usagov-ai-chatbot-suggestions")[0].style.display !== "none"){
        document.getElementsByClassName("usagov-ai-chatbot-suggestions")[0].style.display = "none";
    } 

    const aiResponse = await getAIResponse(message, messageContainer);
    document.getElementById("loader-container").remove();
    addBotMessage(aiResponse);

}

function addBotMessage(message) {
    'use strict';

    const messageContainer = document.getElementsByClassName("usagov-ai-chatbot-messages")[0];

    // make a new parser
    const parser = new DOMParser();
    const newMessage = parser.parseFromString("<div class='usagov-ai-chatbot-message bot'><div class='text'>" + message +"</div></div>", "text/html");

    messageContainer.appendChild(newMessage.body.firstChild);

}

async function getAIResponse(userMessage, messageContainer) {
    'use strict';
    
    // make a new parser
    const parser = new DOMParser();
    const loaderElement = parser.parseFromString('<div id="loader-container" class="usagov-ai-chatbot-message bot"><div class="text"><div class="loader"></div></div></div>', "text/html");

    messageContainer.appendChild(loaderElement.body.firstChild);

    const myHeaders = new Headers();
    myHeaders.append("Content-Type", "application/json");

    const raw = JSON.stringify({
    "model": "llama3.2",
    "prompt": userMessage,
    "stream": false,
    "options": {
    "num_thread": 8,
    "num_ctx": 2024
    }
    });

    const requestOptions = {
        method: "POST",
        headers: myHeaders,
        body: raw,
        redirect: "follow"
    };

    try {
        const response = await fetch("http://127.0.0.1:11434/api/generate", requestOptions);

        const result = await response.text();
        return JSON.parse(result).response;

    } catch (error) {
        console.error(error);
    };
}

