'use strict';
// Private synthetic preview only; the platform chooses the listening port.
const port = process.env.PORT;
if (!port || !/^\d+$/.test(port) || Number(port) < 1 || Number(port) > 65535) {
  throw Error('Valid managed-host injected PORT is required');
}
process.env.NODE_ENV = 'development';
process.env.HOSTNAME = '0.0.0.0';
require('./apply-braces-guard.cjs');
const cli = require.resolve('next/dist/bin/next');
process.argv = [process.execPath, cli, 'dev', '--hostname', '0.0.0.0', '--port', port];
require(cli);
