(function () {

    // const suggestionBox = document.createElement("div");
    // suggestionBox.classList.add("deep-chat-temporary-message");
    // suggestionBox.innerHTML = '<button class="deep-chat-button deep-chat-suggestion-button" style="margin-top: 5px">Suggestion1</button>';

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

                    svgContainer = document.querySelector('#block-usagov-aideepchatchatbot > div > div.ai-deepchat--header > .toggle-icon');

                    if (isOpen) {
                        svgContainer.innerHTML = '<svg class="toogle-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path fill="#1b1b1b" d="M432 256c0 17.7-14.3 32-32 32L48 288c-17.7 0-32-14.3-32-32s14.3-32 32-32l352 0c17.7 0 32 14.3 32 32z"/></svg>';
                    }   
                    else {
                        svgContainer.innerHTML = '<svg class="toogle-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3 297.4 406.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256 342.6 150.6z"/></svg>'
                    }

                }
            })
        })
        observer.observe(targetNode, { attributes: true });
    });

})();

document.addEventListener('DOMContentLoaded', function() {
    const experimentalTag = document.createElement("button");
    experimentalTag.classList.add("chatbot-tag");
    experimentalTag.innerHTML = 'Experimental';
    experimentalTag.setAttribute("disabled", "")

    document.querySelector("#block-usagov-aideepchatchatbot > div > div.ai-deepchat--header > div.ai-deepchat--label").after(experimentalTag);   

 }, false);