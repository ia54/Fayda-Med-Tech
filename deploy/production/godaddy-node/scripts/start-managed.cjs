'use strict';
// Platform injects its internal PORT. Never substitute a new fixed host port.
const port=process.env.PORT;
if(!port||!/^\d+$/.test(port)||Number(port)<1||Number(port)>65535)throw Error('Valid managed-host injected PORT is required');
process.env.NODE_ENV='production';
process.env.HOSTNAME='0.0.0.0';
require('../.next/standalone/server.js');
