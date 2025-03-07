# API Proxy for Cloud.gov

## 📌 Overview

This project is a **Flask-based API Proxy** designed to securely **relay API requests** while **obfuscating API credentials** from users. It enables a **client** to send API queries via the proxy, ensuring credentials remain **server-side only**, meaning, **ONLY on the api-proxy buildpack, NOT the client, ever has credentials**.

The **proxy application** intercepts API calls and appends the required API key **before forwarding requests**--through the egress proxy--to the external API (e.g., `NASA.gov`). It is deployed using **Cloud Foundry** on **Cloud.gov**.

**Sign up for an instant NASA API Key at [https://api.nasa.gov](https://api.nasa.gov), export variables like example below.**

## 🏗️ Architecture

```plaintext
┌───────────────┐        ┌───────────────┐        ┌─────────────────┐        ┌─────────────────┐
│               │  WAF   │ API Proxy     │        │                 │        │                 │
│    Client     │  --->  │ (forwards)    │  --->  │ Egress Proxy    │  --->  │ External API    │
│  (requests)   │        │ ^             │        │                 │        │ (e.g., NASA.gov)│
│               │  <---  │ Key Store     │        │                 │        │                 │
└───────────────┘        └───────────────┘        └─────────────────┘        └─────────────────┘---↴
                                ꜛ                                                                  |
                               WAF                                                                 |
                                ꜛ------------------------------------------------------------------↵
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

It **deploys the api-proxy**, **creates routes**, **maps routes**, **creates the key store**, **sets network policies**, and **sets up proxy environment variables**.

**NOTE**: This step is part of a standard CircleCi deployment and will usually not need to be done manually.

### **3️⃣ Setup**

#### 1) Egress exception

For each api you wish to communicate with, you must add the domain to the egress whitelist by adding it to `bin/cloudgov/apps-egress-allow.acl`.  Be as specific as makes sense for the api; for example, for NASA's api, I added `api.nasa.gov`.  This requires you to run `bin/cloudgov/setup-egress-for-space` on the space that the api-proxy lives to update the acl in the egress.  Then, you either need to re-deploy or run `bin/cloudgov/setup-egress-for-apps` to rotate the proxy passwords for the attached apps.

#### 2) Add key to key store

You **MUST** push a key to the key store on the space the api-proxy lives on with the following data for the API you wish to call:

- `NAME` - API Key Name
- `DOMAIN` - Base API Domain
- `API_KEY` - Secret API Key
- `OPTIONS` - Optional data for complex API calls

Pushing a key will look something like the following:`$ bin/cloudgov/api-proxy/add-key my_key https://api.nasa.gov xx12345xx {"optional": "data"}`

You must perform this step after deploying the api-proxy since deployment actually creates the key store service.

Please try the API of your choice and report back!

## 🔧 Usage

### 1) How it works

1. The proxy captures everything sent to it (including headers and data in the cases of PUT and POST).
2. It checks the key store for the key name provided and gets the api domain and key (and any optional data) from it.
3. It then checks for an extension and modifies data based on extension if found.
4. It attaches all headers and data as well as attaching the api key to the request and sends it on to the domain provided by the key store through the egress proxy.
5. Finally, it returns the response from the request back to the user.

### 2) Make a request

When you make a request, you **must** pass at least two parameters:

1. **endpoint**: the endpoint on the api you wish to request.
2. **keyname**: the name of the key in the key store corresponding to the api.

To test NASA.gov:

```bash
curl -v "https://api-proxy-dev.app.cloud.gov/proxy?endpoint=/planetary/apod&keyname=[replace with your api key name]"
```

This request:

- Routes through `api-proxy-dev.app.cloud.gov`
- Appends `API_KEY` from key store
- Sends the request through the egress to `api.nasa.gov` (domain stored in the key store)
- Passes response back to user

To test SAM.gov:

```bash
curl -v "https://api-proxy-dev.app.cloud.gov/proxy?endpoint=/opportunities/v2/search&keyname=[replace with your api key name]&postedFrom=01/01/2024&postedTo=01/31/2024"
```

This request:

- Routes through `api-proxy-dev.app.cloud.gov`
- Appends `API_KEY` from key store
- Appends extra parameters, `postedFrom=01/01/2024&postedTo=01/31/2024`
- Sends the request through the egress to `api.sam.gov` (domain stored in the key store)
- Passes response back to user

## ⛭ Extensions

In a case where an api call requires more complex rules that what are covered by the base application, you can add an extension on a per domain basis.

1. Create or duplicate a file in the `extensions` directory and rename it [api domain name].py.  Note that the this is the domain name **without** `http(s)://`.
2. This script is loaded dynamically whenever the app detects that a file with the api domain name is in the directory.  You have access to all of the variables from the script, and no returns are required as the extension is loaded in place.
