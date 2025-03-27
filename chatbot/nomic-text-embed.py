# pylint: disable=missing-module-docstring, missing-function-docstring, invalid-name, wrong-import-position, line-too-long
import os
import re
from ollama import embed
import chromadb

def readtextfiles(path):
    text_contents = {}
    directory = os.path.join(path)

    for local_filename in os.listdir(directory):
        if local_filename.endswith(".dat"):
            file_path = os.path.join(directory, local_filename)

            with open(file_path, "r", encoding="utf-8") as file:
                content = file.read()

            text_contents[local_filename] = content

    return text_contents


def chunksplitter(local_text, chunk_size=100):
    words = re.findall(r'\S+', local_text)

    local_chunks = []
    current_chunk = []
    word_count = 0

    for word in words:
        current_chunk.append(word)
        word_count += 1

        if word_count >= chunk_size:
            local_chunks.append(' '.join(current_chunk))
            current_chunk = []
            word_count = 0

    if current_chunk:
        local_chunks.append(' '.join(current_chunk))
    return local_chunks


def getembedding(local_chunks):
    local_embeds = embed(model="nomic-embed-text", input=local_chunks)
    return local_embeds.get('embeddings', [])


chromaclient = chromadb.HttpClient(host="localhost", port=8000)
textdocspath = "./chatbot/output"
text_data = readtextfiles(textdocspath)

collection = chromaclient.get_or_create_collection(name="buildragwithpython", metadata={"hnsw:space": "cosine"})

if chromaclient.get_collection("buildragwithpython"):
    print("Collection already exists")
    for collection in chromaclient.list_collections():
        chromaclient.delete_collection("buildragwithpython")

collection = chromaclient.get_or_create_collection(name="buildragwithpython", metadata={"hnsw:space": "cosine"})

for filename, text in text_data.items():
    print(F"Embedding: {filename}")
    chunks = chunksplitter(text)
    embeds = getembedding(chunks)
    chunknumber = list(range(len(chunks)))
    ids = [filename + str(index) for index in chunknumber]
    metadatas = [{"source": filename} for index in chunknumber]
    collection.add(ids=ids, documents=chunks, embeddings=embeds,
                   metadatas=metadatas)
