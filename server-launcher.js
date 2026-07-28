#!/usr/bin/env node
/**
 * Combined server launcher - runs Next.js standalone server + IPv6 proxy
 * Keeps both alive and auto-restarts Next.js on crash
 */
const { spawn } = require('child_process');
const net = require('net');

const DATABASE_URL = 'file:/home/z/my-project/db/custom.db';

// Start IPv6 proxy (bridges ::1:3000 -> 127.0.0.1:3000)
function startProxy() {
  const proxy = net.createServer((clientSocket) => {
    const proxySocket = net.createConnection({ host: '127.0.0.1', port: 3000 }, () => {
      clientSocket.pipe(proxySocket);
      proxySocket.pipe(clientSocket);
    });
    proxySocket.on('error', () => clientSocket.destroy());
    clientSocket.on('error', () => proxySocket.destroy());
  });

  proxy.on('error', (err) => {
    if (err.code === 'EADDRINUSE') {
      console.log('[Proxy] Port already in use, skipping');
    } else {
      console.error('[Proxy] Error:', err.message);
    }
  });

  proxy.listen(3000, '::1', () => {
    console.log('[Proxy] IPv6 proxy: [::1]:3000 -> 127.0.0.1:3000');
  });

  return proxy;
}

// Start Next.js standalone server
function startServer() {
  const server = spawn('node', ['.next/standalone/server.js', '-H', '0.0.0.0'], {
    cwd: '/home/z/my-project',
    env: { ...process.env, DATABASE_URL, NODE_OPTIONS: '--max-old-space-size=512' },
    stdio: ['pipe', 'pipe', 'pipe']
  });

  server.stdout.on('data', (data) => process.stdout.write(data));
  server.stderr.on('data', (data) => process.stderr.write(data));

  server.on('exit', (code) => {
    console.log(`[Server] Exited with code ${code}, restarting in 2s...`);
    setTimeout(() => {
      nextServer = startServer();
    }, 2000);
  });

  return server;
}

// Main
process.chdir('/home/z/my-project');
console.log('[Launcher] Starting BusinessVance admin server...');
startProxy();
let nextServer = startServer();

// Graceful shutdown
process.on('SIGTERM', () => {
  console.log('[Launcher] Shutting down...');
  if (nextServer) nextServer.kill();
  process.exit(0);
});

process.on('SIGINT', () => {
  console.log('[Launcher] Interrupted, shutting down...');
  if (nextServer) nextServer.kill();
  process.exit(0);
});
