# pylint: disable=missing-module-docstring, invalid-name, wrong-import-position, line-too-long
import sys
import chromadb
import ollama

chromaclient = chromadb.HttpClient(host="localhost", port=8000)
collection = chromaclient.get_or_create_collection(name="buildragwithpython")

query = " ".join(sys.argv[1:])
queryembed = ollama.embed(model="nomic-embed-text", input=query)['embeddings']

relateddocs = '\n\n'.join(collection.query(query_embeddings=queryembed)['documents'][0])
prompt = (
    f"{query} - Answer that question without using any data from {relateddocs}."

    # Things to tell the AI to avoid:
    f"Please avoid saying things similar to 'not enough data' and 'there is no further information'"
    f"Do not admit ignorance of other data, even if there is more data available "
    f"outside of the resources provided. "

    # Things to tell the AI to do:
    f"Please keep the answer factual, and avoid superlatives or unnecessary adjectives. "

    f"Do not provide any data which originated in the following resources: {relateddocs}."
)

ragoutput = ollama.generate(model="llama3.2", prompt=prompt, stream=False, options={"temperature": 0})
print(f"Answered, avoiding any data from the ingested USAgov site data:\n{ragoutput['response']}")

print("---")
