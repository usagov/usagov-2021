/**
 * Handles the toggle button click event in the chatbot header, 
 * switching the chatbot's visibility or interaction state.
 */
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

/**
 * 
 */
function sendMessage() {
    'use strict';

    const inputValue = document.getElementById("userMessage").value;

    if (inputValue) {
        handleUserMessage(inputValue);
        document.getElementById("userMessage").value = "";
    }

}

/**
 * TO-DO: describe the function
 *
 * @param {string} userMessage - 
 * @returns {string} The new message element so it can be added to the chatbot. 
 */
function sendSuggestion(element) {
    'use strict';

    const selectedSuggestion = element.innerHTML;

    if (selectedSuggestion) {
        handleUserMessage(selectedSuggestion);
    }
}

/**
 * TO-DO: describe the function
 *
 * @param {string} userMessage - 
 */

async function handleUserMessage(userMessage) {
    'use strict';

    // Get the message container.
    const messageContainer = document.getElementsByClassName("usagov-ai-chatbot-messages")[0];

    // Create a message element for the user's message.
    const newUserMessageElement = createMessage(true, userMessage);

    // Remove the message suggestions after the first message.
    if (document.getElementsByClassName("usagov-ai-chatbot-suggestions")[0].style.display !== "none"){
        document.getElementsByClassName("usagov-ai-chatbot-suggestions")[0].style.display = "none";
    }

    // Add the user's message element to the chatbot.
    messageContainer.appendChild(newUserMessageElement);

    // Make the request to the AI server to get the response.
    const aiResponse = await getAIResponse(userMessage, messageContainer);

    // Create a message element for the user's message.
    const newBotMessageElement = createMessage(false, aiResponse);

    // Remove the loader so it can be replaced by the new message.
    document.getElementById("loader-container").remove();

    // Add the bot's message element to the chatbot.
    messageContainer.appendChild(newBotMessageElement);

}

/**
 * TO-DO: describe the function
 *
 * @param {string} userMessage - 
 * @returns {string} The new message element so it can be added to the chatbot. 
 */
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
        "method": "POST",
        "headers": myHeaders,
        "body": raw,
        "redirect": "follow"
    };

    try {
        const response = await fetch("http://127.0.0.1:11434/api/generate", requestOptions);
        const result = await response.text();

        // Return the AI response.
        return JSON.parse(result).response;

    } 
    catch (error) {
        console.error(error);
    };
}


function createMessage(isUser, message) {

    // Convert the text to html since it has the format of a Markdown.
    const converter = new showdown.Converter();
    const htmlMessage = converter.makeHtml(message);

    // Create all the components of a message.
    const messageElement = document.createElement("div");
    const messageAvatarElement = document.createElement('img');
    const messageTextElement = document.createElement('div');

    // Configure the text of the message.
    messageTextElement.classList.add("text");
    messageTextElement.innerHTML = htmlMessage;

    if (isUser) {
        // Configure the user message container.
        messageElement.classList.add("usagov-ai-chatbot-message", "user");

        // Configure the avatar for the user.
        messageAvatarElement.classList.add("message-image", "user");
        messageAvatarElement.src = "/themes/custom/usagov/images/chatbot/usagov-user-avatar.png";
        messageAvatarElement.alt = "USA.gov User Avatar";

        // Add the avatar and text in the correct order for the user's messages.
        messageElement.appendChild(messageTextElement);
        messageElement.appendChild(messageAvatarElement);
    }
    else {
        // Configure the bot message container.
        messageElement.classList.add("usagov-ai-chatbot-message", "bot");

        // Configure the avatar for the bot.
        messageAvatarElement.classList.add("message-image", "bot");
        messageAvatarElement.src = "/themes/custom/usagov/images/chatbot/usagov-bot-avatar.png";
        messageAvatarElement.alt = "USA.gov Chatbot Avatar";

        // Add the avatar and text in the correct order for the bot's messages.
        messageElement.appendChild(messageAvatarElement);
        messageElement.appendChild(messageTextElement);
    }
    
    return messageElement;
}