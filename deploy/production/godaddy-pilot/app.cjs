'use strict';
// Passenger's first-http-server interception is required. Never open a fallback host port.
if (!global.PhusionPassenger || typeof global.PhusionPassenger.configure !== 'function') {
  throw new Error('Passenger loader required; no standalone host port is authorized.');
}
global.PhusionPassenger.configure({autoInstall: true});
process.env.NODE_ENV = 'production';
require('./server.js');
