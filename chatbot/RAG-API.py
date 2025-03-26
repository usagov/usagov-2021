from flask import Flask, request, jsonify, make_response
import chromadb
import ollama

chromaclient = chromadb.HttpClient(host="localhost", port=8000)
collection = chromaclient.get_or_create_collection(name="buildragwithpython")


# app =  Flask(__name__)

app = Flask(__name__)

@app.route("/v1/chat/completions", methods=["POST"])
def chatbotQuestion():
    try:
        requestJSON = request.get_json() 
        userQuestion = requestJSON.get("messages", [])

    except:
        return "Sorry, we are having issues with the chatbot (except)."
    
    if not userQuestion or not isinstance(userQuestion, list):
        return "Sorry, we are having issues with the chatbot (if not)."

    userQuestionExtracted = userQuestion[-1].get("text", "").strip()

    if not userQuestionExtracted:
        return "Sorry, we are having issues with the chatbot (if not extracted)."

    # Embed the query
    queryembed = ollama.embed(model="nomic-embed-text", input=userQuestionExtracted)['embeddings']

    # Retrieve relevant documents
    results = collection.query(query_embeddings=queryembed, n_results=5)

    if not results['documents'] or not results['documents'][0]:
        return jsonify({"response": "No relevant documents found."})


    relateddocs = '\n\n'.join(results['documents'][0])

    # Construct prompt for chatbot model
    prompt = (
        f"Context:\n{relateddocs}\n\n"
        f"Question: {userQuestion}\n\n"
        f"Answer using only the provided context. Avoid speculation."
    )

    RAGoutput = ollama.generate(model="llama3.2", prompt=prompt, stream=False, options={"temperature": 0})

    response = make_response(RAGoutput['response'])
    response.headers['Content-Type'] = 'text/plain'
    return response

    # return jsonify({"response": RAGoutput['response']})

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)

