import ollama from 'https://cdn.jsdelivr.net/npm/ollama-js-client/dist/browser/index.js';
import * as chromadb from 'https://esm.run/chromadb';

// const ollama_instance = new ollama({
//   model: "llama3.2",
//   url: "https://ob.straypacket.com/api/",
// });

// const response = await ollama_instance.prompt("Hello my ai friend")

// console.log(response);

// const chroma = new chromadb.ChromaClient({ path: "https://cd.straypacket.com:443" });

// const collection = await chroma.createCollection({ name: "usagovsite" });
// for (let i = 0; i < 20; i++) {
//   await collection.add({
//     ids: ["test-id-" + i.toString()],
//     embeddings: [[1, 2, 3, 4, 5]],
//     documents: ["test"],
//   });
// }
// const queryData = await collection.query({
//   queryEmbeddings: [[1, 2, 3, 4, 5]],
//   queryTexts: ["test"],
// });

export class ChatbotService {
    constructor() {
        // this.chromaHost = 'https://cd.straypacket.com';
        this.chromaHost = 'http://localhost';
        this.chromaPort = 8000;
        // this.ollamaHost = 'https://ob.straypacket.com/api/';
        this.ollamaHost = 'http://127.0.0.1:11434/api/';
        
        // Initialize ChromaDB and Ollama client
        this.chroma = new chromadb.ChromaClient({ path: `${this.chromaHost}:${this.chromaPort}` });
        this.ollama = new ollama({
                                    model: "llama3.2",
                                    url: this.ollamaHost,
                                });
    }

    async listModels() {
        try {
            const modelsUrl = `${this.ollamaHost}tags`;
            const modelsRequest = await fetch(modelsUrl, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                },
            });
            const modelsJson = await modelsRequest.json();
            return modelsJson.models.map(model => {
                return { 
                    'name': model.name, 
                    'size': model.size, 
                    'updated': model.modified_at 
                };
            });
        } catch (error) {
            console.error('Error listing models:', error);
            throw error;
        }
    }

    async listCollections() {
        try {
            try {
                const collection = await this.chroma.createCollection({ name: 'usagovsite' });
                // const collections = await this.chroma.listCollections();
                return collection;
            } catch (error) {
                console.error('Error listing models:', error);
                throw error;
            }
        } catch (error) {
            console.error('Error listing collections:', error);
            throw error;
        }
    }
}

// Example usage of the ChatbotService listModels() function.
(async () => {
    const chatbotService = new ChatbotService();
    try {
        const models = await chatbotService.listModels();
        console.log('Available models:', models);
    } catch (error) {
        console.error('Failed to fetch models:', error);
    }
})();

// Example usage of the ChatbotService listCollections() function.
(async () => {
    const chatbotService = new ChatbotService();
    try {
        const collections = await chatbotService.listCollections();
        console.log('Available collections:', collections);
    } catch (error) {
        console.error('Failed to fetch collections:', error);
    }
})();
