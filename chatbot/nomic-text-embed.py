__import__('pysqlite3')
import sys
sys.modules['sqlite3'] = sys.modules.pop('pysqlite3')

import os
import re
from ollama import embed
import chromadb

def readtextfiles(path):
    text_contents = {}
    directory = os.path.join(path)

    for filename in os.listdir(directory):
        if filename.endswith(".dat"):
            file_path = os.path.join(directory, filename)

            with open(file_path, "r", encoding="utf-8") as file:
                content = file.read()

            text_contents[filename] = content

    return text_contents


def chunksplitter(text, chunk_size=100):
    words = re.findall(r'\S+', text)

    chunks = []
    current_chunk = []
    word_count = 0

    for word in words:
        current_chunk.append(word)
        word_count += 1

        if word_count >= chunk_size:
            chunks.append(' '.join(current_chunk))
            current_chunk = []
            word_count = 0

    if current_chunk:
        chunks.append(' '.join(current_chunk))
    return chunks


def getembedding(chunks):
    embeds = embed(model="nomic-embed-text", input=chunks)
    return embeds.get('embeddings', [])


chromaclient = chromadb.HttpClient(host="localhost", port=8000)
textdocspath = "output"
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

"""
"""
