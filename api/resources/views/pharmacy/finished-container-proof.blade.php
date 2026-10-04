<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="Content-Security-Policy" content="default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'"><title>Synthetic container proof {{ $s['container']['identifier'] }} · revision {{ $revision }}</title>
<style>body{font:15px/1.5 Arial,sans-serif;color:#111;background:#fff;margin:20px}main{max-width:480px;margin:auto;border:2px solid #111;padding:20px;overflow-wrap:anywhere}h1{font-size:21px;margin:8px 0}.warning{font-weight:bold;border:3px solid #111;padding:10px}.patient,.directions{font-size:19px;font-weight:bold;white-space:pre-wrap}p{margin:10px 0}.meta{font-size:12px}.storage{white-space:pre-wrap}@media print{@page{margin:12mm}body{margin:0}main{max-width:none}p{break-inside:avoid}}</style></head><body><main>
<p class="warning">SYNTHETIC PROOF — NOT FOR DISPENSING<br>QUARANTINED — NO MEDICATION RELEASE</p>
<h1>{{ $s['location']['name'] }}</h1><p>{{ $s['location']['address'] }}</p>
<p class="patient">{{ $s['patient']['first_name'] }} {{ $s['patient']['last_name'] }}</p>
<p>Rx {{ $s['prescription']['rx_number'] }} · Prescriber {{ $s['prescription']['prescriber_name'] }}</p>
<p><strong>{{ $s['prescription']['medication'] }} · {{ $s['prescription']['strength'] }} · {{ $s['prescription']['dosage_form'] }}</strong></p>
<p class="directions">{{ $s['prescription']['directions'] }}</p>
<p>Container {{ $s['container']['identifier'] }} · Quantity {{ $s['container']['quantity'] }} {{ $s['unit'] }}</p>
<p>Prepared: {{ $s['dating_evidence']['prepared_at'] }}<br>Reviewed proposed beyond-use: {{ $s['dating_evidence']['proposed_bud_at'] }}<br>Timezone: {{ $s['dating_evidence']['timezone'] }}</p>
<p class="storage">Reviewed storage: {{ $s['dating_evidence']['storage_conditions'] }}</p>
<p class="meta">Formulation {{ $s['formulation']['code'] }} · revision {{ $s['formulation']['revision'] }}<br>Suitability record {{ $s['reviewed_suitability']['suitability_proposal']['id'] }} · Proof revision {{ $revision }}</p>
<p class="meta">Documentary proof only. Dates and instructions are retained source evidence, not newly selected by this document. Printer sizing, physical output, clinical adequacy and professional acceptance remain unverified.</p>
</main></body></html>
