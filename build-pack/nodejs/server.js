const express = require('express');
const cors = require('cors');
const rateLimit = require('express-rate-limit');
const helmet = require('helmet');
const NodeCache = require('node-cache');
const axios = require('axios');
const app = express();
// const app = require('./public/app');
require('dotenv').config();

const DOG_API_KEY = process.env.DOG_API_KEY;
// console.log(`API key is  ${DOG_API_KEY}`);

if (!DOG_API_KEY) {
    // console.error('Missing DOG_API_KEY');
    process.exit(1);
}
const PORT = process.env.PORT;
// console.log(`Server will run on port ${PORT}`);
if (!PORT) {
    // console.log('Missing PORT environment variable');
    process.exit(1);
}
// Security middleware
app.use(helmet());
app.use(cors({ origin: 'http://localhost:3000', credentials: true }));
app.use(express.json());

// Request rate limiting
const apiLimiter = rateLimit({
    windowMs: 15 * 60 * 1000,
    max: 100,
    standardHeaders: true,
    legacyHeaders: false,
});
app.use('/api/', apiLimiter);

// In-memory cache
const cache = new NodeCache({
    stdTTL: 3600, // 1-hour cache for dog API data
    checkperiod: 3720
});

app.post('/api/dogs', async (req, res) => {
    try {
        const { endpoint, params, method = 'GET', data } = req.body;
        const BASE_URL = 'https://api.thedogapi.com/v1';

        if (!endpoint) {
            return res.status(400).json({ error: 'Missing endpoint parameter' });
        }

        // Log request headers to check if 'x-api-key' is being sent
        // console.log('Request Headers:', req.headers);
        // const axiosConfig = {
        //     method,
        //     url: `${BASE_URL}${endpoint}`,
        //     params,
        //     data: method === 'POST' ? data : undefined,
        //     headers: {
        //         'x-api-key': DOG_API_KEY, // Attaching API key before sending request
        //         'Content-Type': 'application/json'
        //     }
        // };

        // console.log('Axios request config:', axiosConfig);
        const cacheKey = `dog-${endpoint}-${JSON.stringify(params)}`;
        const cachedResponse = cache.get(cacheKey);

        if (cachedResponse) {
            console.log(`Cache hit: ${cacheKey}`);
            return res.json(cachedResponse);
        }

        // console.log(`Fetching from API: ${BASE_URL}${endpoint}`);

        const response = await axios({
            method,
            url: `${BASE_URL}${endpoint}`,
            params,
            data: method === 'POST' ? data : undefined,
            headers: {
                'x-api-key': DOG_API_KEY, // Required for API access
            }
        });

        cache.set(cacheKey, response.data);
        res.json(response.data);
    } catch (error) {
        console.error('Dog API Proxy Error:', error.message);
        res.status(500).json({
            error: 'Failed to fetch dog data',
            details: error.response?.data || error.message
        });
    }
});

// Serve static files
app.use(express.static('public'));

app.listen(PORT, () => {
    console.log(`Server running on port ${PORT}`);
});