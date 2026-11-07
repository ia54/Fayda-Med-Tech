import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {createRequire} from 'node:module';
import test from 'node:test';
import React from 'react';
import {renderToStaticMarkup} from 'react-dom/server';
import ts from 'typescript';
const source=await readFile(new URL('../components/pharmacy/recall-follow-up-summary.tsx',import.meta.url),'utf8');
const compiled=ts.transpileModule(source,{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2022,jsx:ts.JsxEmit.ReactJSX}}).outputText;
const require=createRequire(import.meta.url),exports={};
new Function('require','exports',compiled)(name=>name==='@/components/ui/button'?{Button:({variant,...props})=>React.createElement('button',props)}:require(name),exports);
const summary={affected_fills:32,unfinished:30,completed:2,unresolved_fills:1,not_started:28,open:2};
const render=(props={})=>renderToStaticMarkup(React.createElement(exports.RecallFollowUpSummary,{summary,refreshing:false,select:()=>{},...props}));
test('overview presents scope, complete counts and separate fulfillment attention without implying release',()=>{
 const html=render();for(const text of ['assigned locations','all pages and follow-up filters','Affected fills','Unfinished follow-up','Completed follow-up','Unresolved fills','Not started (28)','Open follow-up (2)','Completed (2)','does not terminate the recall or release stock','No patient communication'])assert.ok(html.includes(text),text);
 assert.equal((html.match(/<button/g)||[]).length,4);
});
test('refresh disables filters and empty views remain explicitly scoped',()=>{
 const refreshing=render({refreshing:true});assert.match(refreshing,/aria-busy="true"/);assert.equal((refreshing.match(/disabled=""/g)||[]).length,4);
 const empty=render({summary:Object.fromEntries(Object.keys(summary).map(k=>[k,0]))});assert.match(empty,/Not started \(0\)/);assert.doesNotMatch(empty,/Recall closed|Safe to dispense|All patients contacted/);
});
