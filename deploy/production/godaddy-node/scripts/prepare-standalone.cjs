'use strict';
const fs=require('node:fs'),path=require('node:path');
const target=path.resolve('.next/standalone');
if(!fs.existsSync(path.join(target,'server.js')))throw Error('Next standalone output missing');
for(const [src,dst]of [['.next/static','.next/static'],['public','public']])if(fs.existsSync(src))fs.cpSync(src,path.join(target,dst),{recursive:true});
