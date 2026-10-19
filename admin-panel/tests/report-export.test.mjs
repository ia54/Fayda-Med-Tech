import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import ts from 'typescript';
const source = await readFile(new URL('../lib/reportExport.ts', import.meta.url), 'utf8');
const compiled = ts.transpileModule(source, {compilerOptions:{module:ts.ModuleKind.ESNext,target:ts.ScriptTarget.ES2022}}).outputText;
const {reportRows, reportCsv} = await import(`data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}`);
test('report export preserves unknowns, zero amounts, nested rows and text', () => {
  assert.deepEqual(reportRows({net:null,paid:0,cases:[{title:'Synthetic, "case"'}]}), [['net','Not recorded'],['paid','0'],['cases / Record 1 / title','Synthetic, "case"']]);
  assert.ok(reportCsv({title:'Synthetic, "case"'}).includes('"Synthetic, ""case"""'));
  assert.deepEqual(reportRows({outstanding:125.35-25.15}), [['outstanding','100.2']]);
  assert.deepEqual(reportRows([]), [['Results','No records']]);
});
test('spreadsheet formulas in both field names and values are inert', () => {
  for (const attack of ['=HYPERLINK("x")',' +SUM(1,2)','-1+2','@SUM(1)','\t=1','\r=1']) {
    const csv=reportCsv({[attack]:attack});
    const escaped=attack.replaceAll('"','""');
    assert.ok(csv.includes(`"'${escaped}","'${escaped}"`));
  }
});
