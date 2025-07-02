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
        chatbotToogle.style.backgroundImage = "url(./themes/custom/usagov/assets/img/usa-icons/add.svg)";
    }
    else {
        chatbotContainer.classList.remove("chat-collapsed");
        chatbotContainer.classList.add("chat-open");
        chatbotContainer.style.transform = "translateY(0)";
        chatbotToogle.style.backgroundImage = "url(./themes/custom/usagov/assets/img/usa-icons/remove.svg)";
    }

}

/**
 * Checks if two date JSON objects represent the same date (day, month, and year).
 *
 * @param {Date} dateJsonToSend - The first date object or JSON string to compare.
 * @param {Object|string} dateJsonToCompare - The second date object or JSON string to compare.
 * @returns {boolean} True if both dates are the same (day, month, and year), false otherwise.
 * This function:
 * 1. Converts the input parameters to JSON objects if they are strings.
 * 2. Extracts the date from each JSON object and converts them to Date objects.
 * 3. Compares the day, month, and year of both Date objects.
 * 4. Returns true if all components match, false otherwise.
 */
function checkDate(dateJsonToSend, dateJsonToCompare) {
    'use strict';
    // Convert string to JSON.
    if (typeof dateJsonToCompare === "string") {
        dateJsonToCompare = JSON.parse(dateJsonToCompare);
    }

    // Get date argument from JSON and convert to Date objects.
    dateJsonToCompare = new Date(dateJsonToCompare.date);

    // Compare the date, month, and year of both dates.
    return dateJsonToSend.getDate() === dateJsonToCompare.getDate() &&
           dateJsonToSend.getMonth() === dateJsonToCompare.getMonth() &&
           dateJsonToSend.getFullYear() === dateJsonToCompare.getFullYear();
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

    const inputValue = document.getElementById("user-message").value;

    if (inputValue) {
        handleUserMessage(inputValue);
        document.getElementById("user-message").value = "";
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
 * Observes the last message in the chat interface to determine if it is visible in the viewport.
 *
 * This function:
 * 1. Uses the Intersection Observer API to monitor the last message element.
 * 2. If the last message is visible, it hides the "last message" element.
 * 3. If the last message is not visible, it shows the "last message" element.
 *
 * The "last message" element is used to indicate that the user has reached the end of the chat history.
 */
const lastMessageObserver = new IntersectionObserver((entries) => {
    'use strict';
    entries.forEach((entry) => {
        const scrollElement = document.getElementById('usagov-ai-chatbot-view-last-message');
        if (entry.isIntersecting) {
            // Last message is visible
            scrollElement.style.display = "none";
        }
        else {
            // Last message is hidden
            scrollElement.style.display = "flex";
        }
    });
},
{
    "root": null, // Use the viewport as the root
    "threshold": 0.0, // Trigger when any part of the element is visible
});

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
    const newUserMessageElement = createMessage(true, userMessage, false);

    // Remove the message suggestions after the first message.
    if (document.getElementsByClassName("usagov-ai-chatbot-suggestions")[0].style.display !== "none") {
        document.getElementsByClassName("usagov-ai-chatbot-suggestions")[0].style.display = "none";
    }

    // Add the user's message element to the chatbot.
    messageContainer.appendChild(newUserMessageElement);

    // Make the request to the AI server to get the response.
    const aiResponse = await getAIResponse(userMessage, messageContainer);

    // Create a message element for the user's message.
    const newBotMessageElement = createMessage(false, aiResponse, false);

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

    const requestBody = JSON.stringify({"userMessage": userMessage});

    const requestOptions = {
        "method": "POST",
        "headers": {"Content-Type": "application/json"},
        "body": requestBody
    };

    try {
        const response = await fetch("/usagov-ai", requestOptions);
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
 * @param {boolean} [fromLocalStorage] - Indicates if the message is being created from localStorage data.
 * @returns {HTMLElement} A DOM element containing the avatar and message bubble, ready to be added into the chat container.
 */
function createMessage(isUser, message, fromLocalStorage) {
    'use strict';
    // Convert the text to html since it has the format of a Markdown.
    const converter = new showdown.Converter();
    const htmlMessage = converter.makeHtml(message);

    // Create all the components of a message.
    const messageElement = document.createElement("div");
    const messageAvatarElement = document.createElement('img');
    const messageTextElement = document.createElement('div');
    const messageTimeElement = document.createElement('div');
    const messageInnerContainerElement = document.createElement('div');

    // Configure the text of the message.
    messageTextElement.classList.add("text");
    messageTextElement.innerHTML = htmlMessage;

    var dateConstructor = new Date();
    var time = dateConstructor.toLocaleString('en-US', {'hour': 'numeric', 'minute': 'numeric', 'hour12': true});
    messageTimeElement.classList.add("usagov-ai-chatbot-message-time");
    messageTimeElement.innerHTML = time;

    messageInnerContainerElement.classList.add("usagov-ai-chatbot-inner-container");

    if (isUser) {

        if (!fromLocalStorage) {
            if (localStorage.getItem("usagov_chatbot_session") !== null &&
                !checkDate(dateConstructor, localStorage.getItem("usagov_chatbot_session"))) {
                // If the date is different, delete the localStorage.
                localStorage.removeItem("usagov_chatbot_session");
                // Add the message to localStorage.
                addMessageLocalStorage("user", message);
            }
            else {
                // If there is no localStorage, add the message to localStorage.
                addMessageLocalStorage("user", message);
            }
        }

        // Configure the user message container.
        messageElement.classList.add("usagov-ai-chatbot-message", "user");

        // Configure the avatar for the user.
        messageAvatarElement.classList.add("message-image", "user");
        messageAvatarElement.src = "/themes/custom/usagov/images/chatbot/usagov-user-avatar.png";
        messageAvatarElement.alt = "USA.gov User Avatar";

        // Add the avatar and text in the correct order for the user's messages.
        messageInnerContainerElement.appendChild(messageTextElement);
        messageInnerContainerElement.appendChild(messageAvatarElement);
    }
    else {
        if (!fromLocalStorage) {
            if (localStorage.getItem("usagov_chatbot_session") !== null &&
                !checkDate(dateConstructor, localStorage.getItem("usagov_chatbot_session"))) {
                // If the date is different, delete the localStorage.
                localStorage.removeItem("usagov_chatbot_session");
                // Add the message to localStorage.
                addMessageLocalStorage("bot", message);
            }
            else {
                // If there is no localStorage, add the message to localStorage.
                addMessageLocalStorage("bot", message);
            }
        }


        // Configure the bot message container.
        messageElement.classList.add("usagov-ai-chatbot-message", "bot");

        // Configure the avatar for the bot.
        messageAvatarElement.classList.add("message-image", "bot");
        messageAvatarElement.src = "/themes/custom/usagov/images/chatbot/usagov-bot-avatar.png";
        messageAvatarElement.alt = "USA.gov Chatbot Avatar";

        // Add the avatar and text in the correct order for the bot's messages.
        messageElement.appendChild(messageAvatarElement);
        messageElement.appendChild(messageTextElement);

        // Add the avatar and text in the correct order for the user's messages.
        messageInnerContainerElement.appendChild(messageAvatarElement);
        messageInnerContainerElement.appendChild(messageTextElement);

        const lastMessage = document.getElementById('usagov-ai-chatbot-last-message');
        if (lastMessage) {
            lastMessage.removeAttribute('id');
        }

        lastMessageObserver.unobserve(lastMessage);

        messageElement.id = 'usagov-ai-chatbot-last-message';

        lastMessageObserver.observe(messageElement);

    }

    messageElement.appendChild(messageInnerContainerElement);
    messageElement.appendChild(messageTimeElement);

    return messageElement;
}

/**
 * Handles the Enter key press event in the message input field.
 *
 * This function:
 * 1. Listens for the Enter key press (key code 13).
 * 2. Calls the `sendMessage()` function to send the message when Enter is pressed.
 *
 * @param {Event} event - The keydown event triggered by the user pressing a key.
 */
function handleEnter(event) {
    'use strict';

    var key = event.which || event.keyCode;

    if (key === 13) {
        sendMessage();
    }
}

// Load session from localStorage or create a new one
function loadSession() {
    'use strict';
    var sessionStored = localStorage.getItem("usagov_chatbot_session");

    if (sessionStored) {
        return JSON.parse(sessionStored);
    }

    var newSessionObject = {
        'date': new Date().toISOString(),
        'messages': []
    };

    return newSessionObject;
}

// Save session back to localStorage
function saveSession(session) {
    'use strict';
    localStorage.setItem("usagov_chatbot_session", JSON.stringify(session));
}

/**
 * Adds a new message to the session stored in localStorage.
 *
 * @param {string} type - The type of the message, either "user" or "bot".
 * @param {string} content - The content of the message to be stored.
 *
 * This function:
 * 1. Loads the current session from localStorage.
 * 2. Creates a new message object with the current date and content.
 * 3. Appends the new message to the session's messages array.
 * 4. Saves the updated session back to localStorage.
 */
function addMessageLocalStorage(type, content) {
    'use strict';
    if (content) {
        const session = loadSession();

        const newMessage = {
            'type': type,
            'date': new Date().toISOString(),
            'content': content
        };

        session.messages.push(newMessage);
        saveSession(session);
    }

}

// Load the session from localStorage when the page is loaded.
document.addEventListener("DOMContentLoaded", () => {
    'use strict';
    const storedSession = localStorage.getItem("usagov_chatbot_session");

    if (storedSession) {
        try {
            // Hide the suggestions box if it exists.
            document.getElementsByClassName("usagov-ai-chatbot-suggestions")[0].style.display = "none";

            // Parse the stored session from localStorage.
            const sessionObject = JSON.parse(storedSession);

            // Check if the session has messages and is an array.
            if (!sessionObject.messages || !Array.isArray(sessionObject.messages))
                return;

            // Iterate through the stored messages and create message elements.
            sessionObject.messages.forEach((message) => {
                var isUser = message.type === "user" ? true : false;

                // Get the message container.
                const messageContainer = document.getElementsByClassName("usagov-ai-chatbot-messages")[0];

                // Create a message element for the user's message.
                const newUserMessageElement = createMessage(isUser, message.content, true);

                // Add the user's message element to the chatbot.
                messageContainer.appendChild(newUserMessageElement);
            });

        }
        catch (e) {
            console.error("Failed to parse stored session:", e);
        }
    }
});
