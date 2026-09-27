<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="Content-Security-Policy" content="default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'"><title>Synthetic label proof — fill {{ $s['context']['fill']['id'] }} revision {{ $revision }}</title>
<style>body{font:14px/1.45 Arial,sans-serif;color:#111;background:#fff;margin:20px}main{max-width:440px;border:2px solid #111;padding:18px;margin:auto;overflow-wrap:anywhere}h1{font-size:20px;margin:0 0 5px}.warning{font-weight:bold;border-bottom:2px solid #111;padding-bottom:8px}.patient,.directions{font-size:18px;font-weight:bold;white-space:pre-wrap}p{margin:8px 0}.meta{font-size:12px}.aux{white-space:pre-wrap}@media print{@page{margin:12mm}body{margin:0}main{max-width:none;border:2px solid #111}p{break-inside:avoid}}</style></head><body><main>
<p class="warning">SYNTHETIC PROOF — NOT FOR DISPENSING</p>
<h1>{{ $s['context']['location']['name'] }}</h1><p>{{ $s['context']['location']['address'] }}</p>
<p class="patient">{{ $s['context']['patient']['first_name'] }} {{ $s['context']['patient']['last_name'] }}</p>
<p>Rx {{ $s['context']['prescription']['rx_number'] }} · Dispensing date {{ $s['decisions']['dispensed_on'] }}</p>
@if (!$s['decisions']['do_not_label'])
<p>Prescribed: {{ $s['context']['prescription']['medication'] }}</p>
<p><strong>Dispensed: @if ($s['context']['product']['brand_name']){{ $s['context']['product']['brand_name'] }} ({{ $s['context']['product']['generic_name'] }})@else{{ $s['context']['product']['generic_name'] }}@endif · {{ $s['context']['product']['strength'] }} · {{ $s['context']['product']['dosage_form'] }}</strong></p>
<p>Manufacturer / supplier: {{ $s['context']['product']['manufacturer'] }}</p>
@endif
<p class="directions">{{ $s['context']['prescription']['directions'] }}</p>
<p>Quantity: {{ $s['context']['fill']['quantity'] }} {{ $s['context']['fill']['quantity_unit'] }}</p>
<p>Prescriber: {{ $s['context']['prescription']['prescriber_name'] }}</p>
<p>Use by: {{ $s['decisions']['use_by'] }}</p>
@if (!empty($s['decisions']['auxiliary_text']))<p class="aux">{{ $s['decisions']['auxiliary_text'] }}</p>@endif
<p class="meta">Fill record {{ $s['context']['fill']['id'] }} · Label revision {{ $revision }} · Created by pharmacist #{{ $s['actor_id'] }}</p>
<p class="meta">Retained proof. Check current label status in the application before use. Printer sizing, physical output and professional acceptance are not validated.</p>
</main></body></html>
