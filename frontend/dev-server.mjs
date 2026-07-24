import http from 'node:http';
import process from 'node:process';

const host = process.env.FRONTEND_HOST || '127.0.0.1';
const port = Number(process.env.FRONTEND_PORT || 5173);
const backendUrl = new URL(process.env.BACKEND_URL || 'http://127.0.0.1:8080');

const server = http.createServer((request, response) => {
    const headers = { ...request.headers, host: backendUrl.host };
    headers['x-forwarded-host'] = request.headers.host || `${host}:${port}`;
    headers['x-forwarded-proto'] = 'http';

    const proxyRequest = http.request({
        protocol: backendUrl.protocol,
        hostname: backendUrl.hostname,
        port: backendUrl.port,
        method: request.method,
        path: request.url,
        headers,
    }, (proxyResponse) => {
        response.writeHead(proxyResponse.statusCode || 502, proxyResponse.headers);
        proxyResponse.pipe(response);
    });

    proxyRequest.on('error', (error) => {
        if (!response.headersSent) {
            response.writeHead(502, { 'content-type': 'text/plain; charset=utf-8' });
        }
        response.end(`Frontend gateway could not reach the backend: ${error.message}`);
    });

    request.pipe(proxyRequest);
});

server.listen(port, host, () => {
    console.log(`[frontend] http://${host}:${port}`);
    console.log(`[frontend] proxying to ${backendUrl.origin}`);
});

const shutdown = () => server.close(() => process.exit(0));
process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);
