function chatbotToogle() {
    const chatbotContainer = document.getElementsByClassName("usagov-ai-chatbot-container")[0];
    const chatbotBody = document.getElementsByClassName("usagov-ai-chatbot-body")[0];
    const chatbotToogle = document.getElementById("usagov-ai-chatbot-toogle");
    
    if(chatbotContainer.classList.contains("chat-open")) {
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
    const messageContainer = document.getElementsByClassName("usagov-ai-chatbot-messages")[0];
    const inputValue = document.getElementById("userMessage").value;

    if(inputValue) {
        // make a new parser
        const parser = new DOMParser();
        const newMessage = parser.parseFromString("<div class='usagov-ai-chatbot-message user'><div class='text'>" + inputValue +"</div></div>", "text/html");

        messageContainer.appendChild(newMessage.body.firstChild);
    }

}

function sendSuggestion(element) {
    const selectedSuggestion = element.innerHTML;
    const messageContainer = document.getElementsByClassName("usagov-ai-chatbot-messages")[0];

    if(selectedSuggestion) {
        // make a new parser
        const parser = new DOMParser();
        const newMessage = parser.parseFromString("<div class='usagov-ai-chatbot-message user'><div class='text'>" + selectedSuggestion +"</div></div>", "text/html");

        messageContainer.appendChild(newMessage.body.firstChild);

        const suggestionsContainer = document.getElementsByClassName('usagov-ai-chatbot-suggestions')[0];
        suggestionsContainer.style.display = 'none';
    }
}
