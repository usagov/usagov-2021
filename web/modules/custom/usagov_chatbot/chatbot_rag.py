import sys
import chromadb
import ollama

# Initialize ChromaDB
chromaclient = chromadb.HttpClient(host="localhost", port=8000)
collection = chromaclient.get_or_create_collection(name="buildragwithpython")

# Read user query
query = sys.argv[1:]

# Generate query embedding
queryembed = ollama.embed(model="nomic-embed-text", input=query)['embeddings']

# Retrieve related documents
relateddocs = '\n\n'.join(collection.query(query_embeddings=queryembed)['documents'][0])

print(relateddocs)