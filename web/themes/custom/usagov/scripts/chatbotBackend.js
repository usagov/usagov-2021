import ollama from 'https://cdn.jsdelivr.net/npm/ollama-js-client/dist/browser/index.js';
import * as chromadb from 'https://esm.run/chromadb';

// default stream version
// const ollama_instance = new ollama({
//   model: "llama3.2",
//   url: "https://ob.straypacket.com/api/",
// });

// const response = await ollama_instance.prompt("Hello my ai friend")

// console.log(response);

const chroma = new chromadb.ChromaClient({ path: "https://cd.straypacket.com:443" });

const collection = await chroma.createCollection({ name: "usagovsite" });
for (let i = 0; i < 20; i++) {
  await collection.add({
    ids: ["test-id-" + i.toString()],
    embeddings: [[1, 2, 3, 4, 5]],
    documents: ["test"],
  });
}
const queryData = await collection.query({
  queryEmbeddings: [[1, 2, 3, 4, 5]],
  queryTexts: ["test"],
});

