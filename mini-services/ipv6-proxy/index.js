const net = require('net');

// Next.js standalone binds to 0.0.0.0:3000 (IPv4)
// Caddy connects via localhost -> ::1 (IPv6) 
// This proxy bridges IPv6 loopback to IPv4 loopback
const TARGET_HOST = '127.0.0.1';
const TARGET_PORT = 3000; // Next.js actual port

const server = net.createServer((clientSocket) => {
  const proxySocket = net.createConnection({ host: TARGET_HOST, port: TARGET_PORT }, () => {
    clientSocket.pipe(proxySocket);
    proxySocket.pipe(clientSocket);
  });
  
  proxySocket.on('error', (err) => {
    console.error('Proxy target error:', err.message);
    clientSocket.destroy();
  });
  
  clientSocket.on('error', () => {
    proxySocket.destroy();
  });
});

server.on('error', (err) => {
  if (err.code === 'EADDRINUSE') {
    console.error('Port already in use');
  } else {
    console.error('Server error:', err.message);
  }
  process.exit(1);
});

// Listen on IPv6 loopback on port 3000
// This works because Next.js is on 0.0.0.0:3000 (IPv4) and this is ::1:3000 (IPv6)
server.listen(3000, '::1', () => {
  console.log('IPv6 proxy: [::1]:3000 -> 127.0.0.1:3000');
});
