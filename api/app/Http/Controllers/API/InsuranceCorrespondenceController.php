<?php
namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InsuranceCorrespondenceController extends InsuranceClaimController
{
    public function index(Request $request)
    {
        $data = $request->validate(['claim_id'=>'required|integer','page'=>'nullable|integer|min:1','per_page'=>'nullable|integer|min:1|max:100']);
        $claim = $this->scoped($request)->findOrFail($data['claim_id']);
        $entries = $claim->correspondence_log ?? [];
        abort_unless(is_array($entries) && array_is_list($entries), 409, 'Legacy correspondence requires review before using this log.');
        $entries = array_reverse($entries);
        $perPage = $data['per_page'] ?? 10;
        $page = $data['page'] ?? 1;
        return response()->json(['data'=>['data'=>array_slice($entries, ($page-1)*$perPage, $perPage), 'total'=>count($entries), 'current_page'=>$page, 'last_page'=>max(1,(int)ceil(count($entries)/$perPage))]]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['claim_id'=>'required|integer','request_id'=>'required|uuid',
            'channel'=>'required|in:phone,email,letter,meeting,other','occurred_on'=>'required|date_format:Y-m-d|before_or_equal:today',
            'note'=>'required|string|max:10000']);
        return DB::transaction(function () use ($request, $data) {
            $claim = $this->scoped($request)->lockForUpdate()->findOrFail($data['claim_id']);
            $entries = $claim->correspondence_log ?? [];
            abort_unless(is_array($entries) && array_is_list($entries), 409, 'Legacy correspondence requires review before adding an entry.');
            foreach ($entries as $entry) {
                if (is_array($entry) && ($entry['request_id'] ?? null) === $data['request_id']) {
                    abort_unless(($entry['recorded_by'] ?? null) === $request->user()->id && ($entry['channel'] ?? null) === $data['channel'] && ($entry['occurred_on'] ?? null) === $data['occurred_on'] && ($entry['note'] ?? null) === $data['note'], 409, 'This save reference was already used for different correspondence.');
                    return response()->json(['data'=>$entry]);
                }
            }
            abort_if(count($entries) >= 1000, 409, 'This claim has reached the correspondence log capacity. Existing entries are retained.');
            $entry = ['id'=>(string)Str::uuid(),'request_id'=>$data['request_id'],'channel'=>$data['channel'],
                'occurred_on'=>$data['occurred_on'],'note'=>$data['note'],'recorded_by'=>$request->user()->id,'recorded_at'=>now()->toIso8601String()];
            $entries[] = $entry;
            $claim->update(['correspondence_log'=>$entries]);
            $this->logTimeline((int)$claim->case_id, 'legal', 'Insurance Correspondence Recorded', 'A correspondence note was recorded; no message was sent.', ['claim_id'=>$claim->id,'entry_id'=>$entry['id'],'channel'=>$entry['channel']]);
            return response()->json(['data'=>$entry], 201);
        });
    }
}
