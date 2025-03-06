# API Proxy for Cloud.gov

## 📌 Overview

This project is a **Flask-based API Proxy** designed to securely **relay API requests** while **hiding API credentials** from users. It enables a **client** to send API queries via the proxy, ensuring credentials remain **server-side only**, meaning, **ONLY on the api-proxy buildpack, NOT the client, ever has credentials**.

The **proxy application** intercepts API calls and appends the required API key **before forwarding requests**--through the egress proxy--to the external API (e.g., `NASA.gov`). It is deployed using **Cloud Foundry** on **Cloud.gov**.

**Sign up for an instant NASA API Key at [https://api.nasa.gov](https://api.nasa.gov), export variables like example below.**

## 🏗️ Architecture

```plaintext
┌───────────────┐        ┌───────────────┐        ┌─────────────────┐
│               │        │ API Proxy     │        │                 │
│    Client     │  --->  │ (forwards)    │  --->  │ External API    │
│  (requests)   │        │ ^             │        │ (e.g., NASA.gov)│
│               │        │ Key Store     │        │                 │
└───────────────┘        └───────────────┘        └─────────────────┘
```

- This project utilizes Cloud.gov Python buildpack and **NOT DOCKER CONTAINERS**.
  - This means there is no need to have a container build step in a deploy script or pipeline, nor do we need a Dockerfile.
  - Version of Python and other libraries in Cloud.gov buildpacks are updated upon restart to ensure we have the most recent version of Python.
- **Encrypted Container-to-Container Communication**: **This setup utilizes the automatic C2C network traffic encryption provided by Cloud.gov's Envoy proxy over port 61443**
  - As detailed in: [https://cloud.gov/docs/management/container-to-container/](https://cloud.gov/docs/management/container-to-container/)
  - Makes API requests but **lacks direct API credentials**.
- **API Proxy**: Relays requests, checks formatting, and appends `API_KEY` from key store based upon API domain and key name, and forwards them securely.
- **Key Store**: User provided service that contains key information for any given API with the option to attach option data for more complex API calls.
- **External API**: The **actual API** (e.g., `NASA.gov`) that receives requests.
  - Code will have to be added to properly handle different APIs that may have different formatting requirements but the code in place can be used as a good template.

## 🚀 Deployment

### **1️⃣ Prerequisites**

- Cloud Foundry CLI (`cf`) must be installed
- Access to **Cloud.gov** environment
- A Cloud.gov **org & space targeted** (`cf login && cf target`)

### **2️⃣ Deploy**

To deploy the API Proxy, run:

```bash
bin/cloudgov/deploy-api-proxy
```

- It **deploys the api-proxy**, **creates routes**, **maps routes**, **creates the key store**, and **sets network policies**.

### **3️⃣ Setup**

#### 1) Egress exception

For each api you wish to communicate with, you must add the domain to the egress whitelist by adding it to `bin/cloudgov/apps-egress-allow.acl`.  Be as specific as makes sense for the api; for example, for NASA's api, I added `api.nasa.gov`.

#### 2) Add key to key store

You **MUST** push a key to the key store on the space the api-proxy lives on with the following data for the API you wish to call:

- `DOMAIN` - Base API Domain
- `NAME` - API Key Name
- `API_KEY` - Secret API Key
- `OPTIONS` - Optional data for complex API calls

Pushing a key will look something like the following:
`$ bin/cloudgov/api-proxy/add-key https://api.nasa.gov my_key xx12345xx {"optional": "data"}`

You must perform this step after deploying the api-proxy since deployment actually creates the ups.

Please try the API of your choice and report back!

## 🔧 Usage

### **1️⃣ Make a request**

To test NASA.gov:

```bash
curl -v "https://api-proxy.app.cloud.gov/proxy?domain=https://api.nasa.gov&endpoint=/planetary/apod&keyname=jacob_yeager"
```

This request:

- Routes through `api-proxy.app.cloud.gov`
- Appends `API_KEY` from key store
- Sends the request to `api.nasa.gov`

To test SAM.gov:

```bash
curl -v "https://api-proxy.app.cloud.gov/proxy?domain=https://api.sam.gov&endpoint=/opportunities/v2/search&keyname=jacob_yeager&postedFrom=01/01/2024&postedTo=01/31/2024"
```

This request:

- Routes through `api-proxy.app.cloud.gov`
- Appends `API_KEY` from key store
- Appends extra parameters, `postedFrom=01/01/2024&postedTo=01/31/2024`
- Sends the request to `api.sam.gov`
