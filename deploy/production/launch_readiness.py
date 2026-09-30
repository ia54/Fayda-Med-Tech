#!/usr/bin/env python3
"""Render the evidence-based launch tracker. No network, server changes or status inference."""
import argparse
import html
import json
from pathlib import Path


def render(source, destination):
    data = json.loads(source.read_text())
    gates = data['gates']
    assert gates and len({g['id'] for g in gates}) == len(gates)
    assert all(g['status'] in {'passed', 'in_progress', 'blocked'} for g in gates)
    assert all(g['group'] in data['groups'] and g['source'] and g['acceptance'] for g in gates)
    passed = sum(g['status'] == 'passed' for g in gates)
    percent = round(100 * passed / len(gates))
    esc = lambda value: html.escape(str(value), quote=True)
    pilot = data.get('pilot')
    pilot_html = ''
    if pilot:
        pilot_html = '<section><h2>Client pilot · ' + esc(pilot['status']) + '</h2><p>' + esc(pilot['scope']) + '</p><p>' + esc(pilot['evidence']) + '</p><p><strong>Next:</strong> ' + esc(pilot['next_action']) + '</p><p class="muted">Separate demonstration track; no full-scope gates are waived.</p></section>'
    summaries = []
    for key, name in data['groups'].items():
        rows = [g for g in gates if g['group'] == key]
        done = sum(g['status'] == 'passed' for g in rows)
        summaries.append(f'<section><h2>{esc(name)}</h2><strong>{done}/{len(rows)} cleared</strong><progress value="{done}" max="{len(rows)}" aria-label="{esc(name)}"></progress></section>')
    details = []
    for gate in gates:
        status = gate['status'].replace('_', ' ')
        details.append(f'''<details data-group="{esc(gate['group'])}" data-status="{esc(gate['status'])}">
<summary><span>{esc(gate['id'])} · {esc(gate['title'])}</span><b class="status {esc(gate['status'])}">{esc(status)}</b></summary>
<dl><dt>Owner</dt><dd>{esc(gate['owner'])}</dd><dt>Verified position / evidence</dt><dd>{esc(gate['evidence'])}</dd><dt>Next action</dt><dd>{esc(gate['next_action'])}</dd><dt>Completion criterion</dt><dd>{esc(gate['acceptance'])}</dd><dt>Evidence source in workspace</dt><dd><code>{esc(gate['source'])}</code></dd></dl></details>''')
    options = ''.join(f'<option value="{esc(k)}">{esc(v)}</option>' for k, v in data['groups'].items())
    document = '''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>FaydaMedTech · Launch readiness</title>
<style>
:root{color-scheme:light dark;--bg:light-dark(#f7faf9,#101b1b);--fg:light-dark(#142c2c,#e6efed);--muted:light-dark(#425d5b,#b7ccc7);--line:light-dark(#b8cbc6,#47645e);--accent:light-dark(#076857,#59d7b2);--panel:light-dark(#ffffff,#172928)}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--fg);font:16px/1.5 system-ui,sans-serif}main{max-width:1060px;margin:auto;padding:32px 22px}h1{font-size:clamp(25px,4vw,38px);margin:8px 0}h2{font-size:18px;margin:0 0 8px}p{max-width:850px}.brand{font-weight:750;letter-spacing:.06em;color:var(--accent)}.muted{color:var(--muted)}.score{font-size:52px;font-weight:750}.metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:24px;margin:28px 0}.metrics section{border-top:2px solid var(--line);padding-top:16px}progress{display:block;width:100%;height:12px;margin:12px 0;accent-color:var(--accent)}.controls{display:flex;gap:16px;flex-wrap:wrap;margin:24px 0}label{display:grid;gap:6px}select,button{font:inherit;padding:9px 12px;color:var(--fg);background:var(--panel);border:1px solid var(--line);border-radius:5px;max-width:100%}details{border-top:1px solid var(--line);padding:15px 0}summary{display:flex;justify-content:space-between;gap:16px;cursor:pointer;align-items:start}summary span{font-weight:600}summary::before{content:'+';color:var(--accent)}details[open]>summary::before{content:'−'}.status{font-size:13px;white-space:nowrap;text-transform:uppercase}.passed{color:var(--accent)}dl{margin:20px 0 8px;padding-left:24px}dt{font-weight:650;margin-top:14px}dd{margin:3px 0;color:var(--muted);overflow-wrap:anywhere}code{font-size:13px}footer{border-top:1px solid var(--line);margin-top:24px;padding-top:16px;font-size:14px}li{margin:8px 0}[hidden]{display:none!important}@media(max-width:480px){main{padding:22px 16px}summary{flex-wrap:wrap}.status{margin-left:24px}dl{padding-left:0}}@media print{.controls{display:none}body{font-size:12px}details{break-inside:avoid}}
</style></head><body><main>
<div class="brand">FAYDAMEDTECH</div><h1>Launch readiness</h1>
<p class="muted">Snapshot: __DATE__ · __EVIDENCE_DATE__.</p>
<p><strong>Stage: tested development build → production rehearsal not started.</strong></p>
<div class="score">__PERCENT__%</div><p><strong>__PASSED__ of __TOTAL__ defined gates cleared.</strong> Full-scope launch remains blocked. Redevelopment is not deployed.</p>
<progress value="__PASSED__" max="__TOTAL__" aria-label="Completed launch gates"></progress>
<p class="muted">__METHOD__</p>
<div class="metrics">__SUMMARIES__</div>
__PILOT__
<h2>Critical path</h2><ol><li>Verify host identity and restore authenticated access; establish production inventory and recovery.</li><li>Freeze the selected release candidate, verify its evidence and rehearse the release.</li><li>Complete pharmacy operating requirements and provider dependencies, including controlled dispensing and compounding release.</li><li>Complete real-origin acceptance and controlled cutover with a recoverable rollback.</li></ol>
<p><strong>Timing:</strong> no reliable full-scope launch date is established. The authorized limited client pilot is tracked separately and does not complete this checklist. Provider and pharmacist dependencies prevent a defensible day-count for the requested full pharmacy launch.</p>
<div class="controls"><label>Workstream<select id="group"><option value="all">All workstreams</option>__OPTIONS__</select></label><label>Status<select id="status"><option value="all">All statuses</option><option value="blocked">Blocked</option><option value="in_progress">In progress</option><option value="passed">Passed</option></select></label><button id="expand" type="button">Expand visible evidence</button></div>
<p id="visible-count" aria-live="polite">__TOTAL__ gates shown</p><div id="gates">__DETAILS__</div>
<footer><p>Baseline __BASELINE__ · Scope: __SCOPE__</p><p>__UPDATE__</p><p>Published source: <code>__COMMIT__</code>. __LOCAL_STATUS__. No private records or credentials are included.</p></footer>
</main><script>
const group=document.getElementById('group'),status=document.getElementById('status'),rows=[...document.querySelectorAll('#gates details')],count=document.getElementById('visible-count');
function filter(){let n=0;for(const row of rows){row.hidden=!((group.value==='all'||row.dataset.group===group.value)&&(status.value==='all'||row.dataset.status===status.value));if(!row.hidden)n++;}count.textContent=n+' of '+rows.length+' gates shown';}
group.addEventListener('change',filter);status.addEventListener('change',filter);document.getElementById('expand').addEventListener('click',()=>{for(const row of rows)if(!row.hidden)row.open=true;});
</script></body></html>'''
    replacements = {'PILOT': pilot_html, 'DATE': esc(data['as_of']), 'PERCENT': percent, 'PASSED': passed, 'TOTAL': len(gates), 'METHOD': esc(data['method']), 'SUMMARIES': ''.join(summaries), 'OPTIONS': options, 'DETAILS': ''.join(details), 'BASELINE': esc(data['baseline']), 'SCOPE': esc(data['scope']), 'UPDATE': esc(data['update_policy']), 'COMMIT': esc(data['published_commit']), 'EVIDENCE_DATE': esc(data['evidence_through']), 'LOCAL_STATUS': 'New local changes remain unpublished' if data['local_unpublished_changes'] else 'No unpublished implementation changes recorded'}
    for key, value in replacements.items():
        document = document.replace('__' + key + '__', str(value))
    destination.parent.mkdir(parents=True, exist_ok=True)
    destination.write_text(document)
    print(json.dumps({'passed': passed, 'total': len(gates), 'percent': percent, 'deployed': data['deployed'], 'output': str(destination)}))


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--source', type=Path, default=Path(__file__).with_name('launch-readiness.json'))
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    render(args.source, args.output)
