const http = require('http');
const os = require('os');
const port = process.env.PORT || 5000;
const app = require('./public/app');
http.createServer( (req, res) => {
  res.writeHead(200, {'Content-Type': 'text/plain'});
  res.end(`Hello World from Node JS on port ${port} from container ${os.hostname()}`);
}).listen(port, () => {
  console.log("Listening on " + port);
});
