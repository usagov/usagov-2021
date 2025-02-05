# API Proxy for Cloud.gov

## 📌 Overview

This project is a **Flask-based API Proxy** designed to securely **relay API requests** while **hiding API credentials** from users. It enables a **test client** to send API queries via the proxy, ensuring credentials remain **server-side only, meaning, ONLY on the api-proxy buildpack, NOT the client-test, it NEVER has credentials**.

The **proxy application** intercepts API calls and appends the required API key **before forwarding requests** to the external API (e.g., `NASA.gov`). It is deployed using **Cloud Foundry** on **Cloud.gov**.

This project was tested with **NASA.gov's** open **APOD API**, as well as **SAM.gov's** API.

**Sign up for an instant NASA API Key at https://api.nasa.gov, export variables like example below.**

## 🏗️ Architecture

```
┌───────────────┐        ┌───────────────┐        ┌─────────────────┐
│ Test Client   │  --->  │ API Proxy     │  --->  │ External API    │
│ (requests)    │        │ (forwards)    │        │ (e.g., NASA.gov)│
└───────────────┘        └───────────────┘        └─────────────────┘
```

- This project utilizes Cloud.gov Python buildpack and **NOT DOCKER CONTAINERS**.
  - This means there is no need to have a container build step in a deploy script or pipeline, nor do we need a Dockerfile.
  - Version of Python and other libraries in Cloud.gov buildpacks are updated upon restart to ensure we have the most recent version of Python.
- **Encrypted Container-to-Container Communication**: **This setup utilizes the automatic C2C network traffic encryption provided by Cloud.gov's Envoy proxy over port 61443**
  - As detailed in: https://cloud.gov/docs/management/container-to-container/
- **Test Client**: Python buildpack with nothing running in it but a forever sleep to keep it up.
  - Makes API requests but **lacks direct API credentials**.
- **API Proxy**: Relays requests, checks formatting, and appends `API_KEY`, and forwards them securely.
- **External API**: The **actual API** (e.g., `NASA.gov`) that receives requests.
  - Code will have to be added to properly handle different APIs that may have different formatting requirements but the code in place can be used as a good template.

## 🚀 Deployment

### **1️⃣ Prerequisites**

- Cloud Foundry CLI (`cf`) must be installed
- Access to **Cloud.gov** environment
- A Cloud.gov **org & space targeted** (`cf login && cf target`)
- You **MUST** manually export Environment variables:
  - `API_ENDPOINT` (Base API URL)
  - `API_KEY` (Secret API Key)

### **2️⃣ Setup**

Set environment variables:

To test with NASA's API Sign up for a key: https://api.nasa.gov/

```bash
export API_ENDPOINT="https://api.nasa.gov/planetary/apod"
export API_KEY="your-secret-key"
```

To test with SAM.gov (you may need some additional permissions you might not be able to request that I have)

```bash
export API_ENDPOINT="https://api.sam.gov/opportunities/v2/search"
export API_KEY="your-secret-key"
```

Please try the API of your choice and report back!

### **3️⃣ Deploy**

To deploy the API Proxy & Test Client, run:

```bash
./deploy.sh
```

- The script will confirm **your Cloud Foundry org & space** before proceeding.
- It **creates routes**, **deploys applications**, and **sets network policies**.
- The expected output:
  ```plaintext
  🔍 You are deploying to:
     🏢 Org:              sandbox-gsa
     📌 Space:          xavier.metichecchia
  ⚠️  Please verify this is the correct target before proceeding.
  ❓ Proceed with deployment? (Y/N): Y
  ✅ Configuration prepared
  🔄 Checking routes...
  ✅ All required routes already exist.
  🚀 Deploying API Proxy...
  ✅ API Proxy deployed successfully.
  🚀 Deploying Test Client...
  ✅ Test Client deployed successfully.
  🔒 Configuring network policies...
  ✅ Network policies added.
  ✅ Cleanup complete.
  🎉 Deployment completed successfully!
  ```

## 🔧 Usage

### **1️⃣ Make a request from the Test Client**

To test NASA.gov:

```bash
cf ssh test-client
curl -v "https://api-proxy.apps.internal:61443/proxy"
```

This request:

- Routes through `api-proxy.apps.internal`
- Appends `API_KEY`
- Sends the request to `NASA.gov`

To test SAM.gov:

```bash
cf ssh test-client
curl -v "https://api-proxy.apps.internal:61443/proxy?postedFrom=01/01/2024&postedTo=01/31/2024"
```

This request:

- Routes through `api-proxy.apps.internal`
- Appends `API_KEY`
- Sends the request to `SAM.gov`

## 🌎 Environment Variables

| Variable       | Description        | Example Value                         |
| -------------- | ------------------ | ------------------------------------- |
| `API_ENDPOINT` | The base API URL   | `https://api.nasa.gov/planetary/apod` |
| `API_KEY`      | The secret API key | `your-secret-key`                     |

## Expected Results

![image](https://github-production-user-asset-6210df.s3.amazonaws.com/90001763/409210411-b75901b6-118f-436c-a373-ac846fba0d2d.jpg?X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Credential=AKIAVCODYLSA53PQK4ZA%2F20250203%2Fus-east-1%2Fs3%2Faws4_request&X-Amz-Date=20250203T172951Z&X-Amz-Expires=300&X-Amz-Signature=65f57391f1cad892b3e1d6aa2250c2443ef172ca8672cd2084fc7bf57374edc0&X-Amz-SignedHeaders=host)

## 🛠️ Troubleshooting

### **❌ `API_KEY` Not Found**

Check that `API_KEY` is set:

```bash
echo $API_KEY
```

If empty, export it:

```bash
export API_KEY="your-secret-key"
```

### **❌ Cloud Foundry Org/Space Not Set**

Run:

```bash
cf target
```

Ensure it matches the expected **org & space**.

### **❌ Deployment Fails**

Try redeploying:

```bash
cf push -f api_proxy_manifest.yml
cf push -f test_client_manifest.yml
```

### **❌ Proxy Returns 404**

- Confirm the request URL is correct.
- Ensure `api-proxy` is running:
  ```bash
  cf apps
  ```

## 📌 Future Enhancements

- [ ] **Convert deploy.sh to CI/CD Pipeline** To deploy in a more modern, supportable way.
- [ ] **Add authentication** to restrict access to `api-proxy`
- [ ] **Enable logging aggregation** for API requests
