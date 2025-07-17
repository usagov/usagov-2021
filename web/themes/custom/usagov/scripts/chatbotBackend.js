import Ollama from 'https://cdn.jsdelivr.net/npm/ollama-js-client/dist/browser/index.js';

// default stream version
const ollama_instance = new Ollama({
  model: "llama3.2",
  url: "https://ob.straypacket.com/api/",
});

const response = await ollama_instance.prompt("Hello my ai friend")

console.log(response);