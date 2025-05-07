/**
 * Toggles the visibility of the chatbot window.
 *
 * This function:
 * 1. Is triggered when the user clicks the chatbot toggle icon in the header.
 * 2. Adds or removes CSS class to show or hide the chatbot interface.
 * 3. Creates the visual effect of opening and closing the chatbot without removing it from the DOM.
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
 * Sends the user's typed message from the input field to the chatbot.
 *
 * This function:
 * 1. Retrieves the current value from the message input field.
 * 2. Calls the `handleUserMessage(inputValue)` function to process and display the message.
 * 3. Clears the input field after seding the message.
 *
 * This functions is triggered by clicking the "Send" button.
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
 * Handles a user clicking on a suggestion button by sending the suggested message to the chatbot.
 *
 * @param {HTMLElement} element - The HTML element that contains the suggestion text that the user has selected.
 *
 * This function:
 * 1. Retrieves the inner HTML/text of the clicked suggestion element.
 * 2. Passes that text to the `handleUserMessage(selectedSuggestion)` function to process it as if the user typed it manually.
 */
function sendSuggestion(element) {
    'use strict';

    const selectedSuggestion = element.innerHTML;

    if (selectedSuggestion) {
        handleUserMessage(selectedSuggestion);
    }
}

/**
 * Handles the full lifecycle of processing a user message in the chatbot interface.
 *
 * @param {string} userMessage - The message text input from the user.
 *
 * This function:
 * 1. Retrieves the main message container from the DOM.
 * 2. Uses a helper function to create and return a DOM element for the user's message.
 * 3. Removes any suggeston boxes.
 * 4. Appends the user's message to the chat container.
 * 5. Calls the async function that sends the message to the local Ollama server and waits for the AI's response.
 * 6. Uses the same message-creating helper function to create the AI's response element from the returned text.
 * 7. Removes the loader after receiving the AI response.
 * 8. Appends the AI's message element to the chat interface.
 */

async function handleUserMessage(userMessage) {
    'use strict';

    // Get the message container.
    const messageContainer = document.getElementsByClassName("usagov-ai-chatbot-messages")[0];

    // Create a message element for the user's message.
    const newUserMessageElement = createMessage(true, userMessage);

    // Remove the message suggestions after the first message.
    if (document.getElementsByClassName("usagov-ai-chatbot-suggestions")[0].style.display !== "none") {
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
 * Sends the user's message to the local Ollama server and returns a DOM element containing the AI's response.
 *
 * @param {string} userMessage - The user's input message to be sent to the AI.
 * @param {HTMLElement} messageContainer - The container where the loading indicator is temporarily appended during the request.
 *
 * This function:
 * 1. Creates and appends a loader element to indicate the AI is thinking.
 * 2. Sends a POST request to the local Ollama server with the user message.
 * 3. Waits for a response and then it returns just the AI message as a string.
 *
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

/**
 * Creates a DOM element representing a chat message bubble with an avatar and text.
 *
 * @param {boolean} isUser - Wheter the message is from the user (true) or the bot (false).
 * @param {string} message - The message content, supports markdown.
 * @returns {HTMLElement} A DOM element containing the avatar and message bubble, ready to be added into the chat container.
 */
function createMessage(isUser, message) {
    'use strict';

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