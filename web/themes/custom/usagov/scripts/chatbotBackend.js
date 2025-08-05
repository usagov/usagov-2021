// import ollama from 'https://cdn.jsdelivr.net/npm/ollama-js-client/dist/browser/index.js';
// import * as ollama from 'https://esm.run/ollama';
// import * as ollama from 'https://cdn.jsdelivr.net/npm/ollama@0.5.16/+esm';
// import * as ollama from 'https://cdn.jsdelivr.net/npm/ollama@0.5.16/browser/index.js';
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
        this.chromaHost = 'cd.straypacket.com';
        this.chromaPort = 443;
        this.ollamaHost = 'https://ob.straypacket.com';
        
        // Initialize ChromaDB and Ollama client
        this.chroma = new chromadb.ChromaClient({ "host": `${this.chromaHost}`, "port":`${this.chromaPort}`, "ssl" : true });
    }

    async listModels() {
        try {
            const modelsUrl = `${this.ollamaHost}/api/tags`;
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
            // const collection = await this.chroma.createCollection({ name: 'usagovsite' });
            const collections = await this.chroma.listCollections();
            return collections.map(collection => {
                return {
                    'name': collection.name,
                    'count': collection.id ?? ''
                };
            });
        } catch (error) {
            console.error('Error listing collections:', error);
            throw error;
        }
    }

    async ollamaEmbed(input) {
        try {
            const embedUrl = `${this.ollamaHost}/api/embed`;
            const embedRequest = await fetch(embedUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    'model': 'nomic-embed-text:latest',
                    'input': input
                })
            });
            const embedJson = await embedRequest.json();
            return embedJson.embeddings;
        } catch (error) {
            console.error('Error embed request:', error);
            throw error;
        }
    }

    async ollamaGenerate(prompt) {
        try {
            const body = {
                'model': 'llama3.2',
                'prompt': JSON.stringify(prompt),
                'stream': false,
            };
            const generateUrl = `${this.ollamaHost}/api/generate`;
            const generateRequest = await fetch(generateUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(body),
            });
            const generateJson = await generateRequest.json();
            return generateJson;
        } catch (error) {
            console.error('Error embed request:', error);
            throw error;
        }
    }

    async askChat(collectionName, query, toJSON) {
        try {
            const collection = await this.chroma.getCollection({ "name": collectionName });
            const embeddings = await this.ollamaEmbed(query);

            const queryData = await collection.query({
                queryEmbeddings: embeddings
            });
            const relatedDocuments = queryData.ids[0].join(', ');

            let jsonInstructions = '';
            if (toJSON) {
                jsonInstructions = `You must format the answer as a JSON array, with the the information from each resource as an element in the array. 
                                    Do not include any explanatory text outside of the JSON array - the output should only contain the JSON array. `;
            }

            let prompt =`${query} - Answer that question using ONLY the resources provided.
                    If the query is not in the form of a question, prefix the query with "Tell me about".
                    ${jsonInstructions} .
                    You must include the following information, if the information is present, about each resource: 
                    name, description, telephone number, email and URL. " .
                    Please avoid saying things similar to 'not enough data' and 'there is no further information'.
                    Do not admit ignorance of other data, even if there is more data available, outside of the resources provided.
                    "You must keep the answer factual, and avoid superlatives or unnecessary adjectives.
                    "Do not provide any data, or make any suggestions unless it comes from the resources provided.
                    The resources to use in your answer are these:
                    ${relatedDocuments}`;
            
            const completion = await this.ollamaGenerate(prompt);
            console.log('Chatbot response:', completion);
            return completion;

        } catch (error) {
            console.error('Error asking chat:', error);
            throw error;
        }
    }
        
}

// Example usage of the ChatbotService listModels() function.
// (async () => {
//     const chatbotService = new ChatbotService();
//     try {
//         const models = await chatbotService.listModels();
//         console.log('Available models:', models);
//     } catch (error) {
//         console.error('Failed to fetch models:', error);
//     }
// })();

// // Example usage of the ChatbotService listCollections() function.
// (async () => {
//     const chatbotService = new ChatbotService();
//     try {
//         const collections = await chatbotService.listCollections();
//         console.log('Available collections:', collections);
//     } catch (error) {
//         console.error('Failed to fetch collections:', error);
//     }
// })();

// (async () => {
//     const chatbotService = new ChatbotService();
//     try {
//         const response = await chatbotService.askChat('usagovsite', 'What is the contact information for the USAGov site?', true);
//     } catch (error) {
//         console.error('Failed to get chat response:', error);
//     }
// })();

window.ChatbotService = ChatbotService; // Expose the service globally for use in other scripts