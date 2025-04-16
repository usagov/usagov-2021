(function () {

    document.querySelector("#chevron > path").setAttribute("fill", "");
    
    const targetSelector = '#block-usagov-aideepchatchatbot > div > div.ai-deepchat--header > .toggle-icon';

    const toogleWatcher = (selector, callback) => {
        const interval = setInterval(() => {
            const element = document.querySelector(selector);

            if (element) {
                clearInterval(interval);
                callback(element);
            }
        }, 200);
    };

    toogleWatcher(targetSelector, (targetNode) => {
        const observer = new MutationObserver((mutationsList) => {
            mutationsList.forEach((mutation) => {
                if (mutation.attributeName === 'class') {
                    const currentClass = mutation.target.className;
                    
                    const isOpen = currentClass.includes("is-closed");
                    
                    const openChatbotIcon = document.getElementsByClassName("open-chatbot-icon")[0];
                    const closeChatbotIcon = document.getElementsByClassName("close-chatbot-icon")[0];

                    if (isOpen) {
                        closeChatbotIcon.style.display = 'inline';
                        openChatbotIcon.style.display = 'none';
                    }   
                    else {
                        openChatbotIcon.style.display = 'inline';
                        closeChatbotIcon.style.display = 'none';
                    }

                }
            })
        })
        observer.observe(targetNode, { attributes: true });
    });

})();

document.addEventListener('DOMContentLoaded', function() {

    // Adding the tag "Experimental".
    const experimentalTag = document.createElement("button");
    experimentalTag.classList.add("chatbot-tag");
    experimentalTag.innerHTML = 'Experimental';
    experimentalTag.setAttribute("disabled", "")

    document.querySelector("#block-usagov-aideepchatchatbot > div > div.ai-deepchat--header > div.ai-deepchat--label").after(experimentalTag);   

 }, false);