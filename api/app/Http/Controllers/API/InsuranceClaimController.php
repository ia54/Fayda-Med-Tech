<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\{InsuranceClaim, InsuranceCompany, InsuranceAdjuster, CaseModel};
use App\Traits\LogsTimeline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InsuranceClaimController extends Controller
{
    use LogsTimeline;

    private function cases(Request $request)
    {
        abort_unless($request->user()->organization_id, 403);
        $query = CaseModel::where('organization_id', $request->user()->organization_id);
        if ($request->user()->role === 'attorney') $query->assignedToAttorney($request->user()->id);
        return $query;
    }

    protected function scoped(Request $request)
    {
        return InsuranceClaim::where('organization_id', $request->user()->organization_id)
            ->whereIn('case_id', $this->cases($request)->select('id'))
            ->whereHas('insuranceCompany', fn ($q) => $q->where('organization_id', $request->user()->organization_id));
    }

    public function index(Request $request)
    {
        $data = $request->validate(['case_id'=>'nullable|integer', 'insurance_company_id'=>'nullable|integer',
            'status'=>'nullable|in:open,pending,settled,denied', 'page'=>'nullable|integer|min:1', 'per_page'=>'nullable|integer|min:1|max:100']);
        $query = $this->scoped($request)->with(['insuranceCompany:id,name', 'case:id,case_number,title']);
        foreach (['case_id', 'insurance_company_id'] as $field) if (!empty($data[$field])) $query->where($field, $data[$field]);
        if (!empty($data['status'])) $query->where('claim_status', $data['status']);
        return response()->json(['status'=>true, 'data'=>$query->latest('id')->paginate($data['per_page'] ?? 15)]);
    }

    private function payload(Request $request, ?InsuranceClaim $claim = null): array
    {
        $required = $claim ? 'sometimes|required|' : 'required|';
        $rules = [
            'case_id'=>$claim ? ['sometimes','required','integer',Rule::in([$claim->case_id])] : 'required|integer',
            'insurance_company_id'=>$required.'integer',
            'claim_number'=>[$claim ? 'sometimes':'required','required','string','max:100',Rule::unique('insurance_claims','claim_number')->ignore($claim?->id)],
            'coverage_type'=>$required.'in:liability,pip,medpay,uninsured_motorist',
            'claim_status'=>'sometimes|required|in:open,pending,settled,denied',
            'adjuster_id'=>'nullable|integer', 'adjuster_notes'=>'nullable|string|max:10000',
        ];
        foreach (['coverage_limit','demand_amount','settlement_offer','final_settlement'] as $field) $rules[$field]='nullable|numeric|min:0|max:99999999.99|decimal:0,2';
        $data = $request->validate($rules);
        $this->cases($request)->findOrFail($claim?->case_id ?? $data['case_id']);
        $companyId = $data['insurance_company_id'] ?? $claim?->insurance_company_id;
        InsuranceCompany::where('organization_id', $request->user()->organization_id)->lockForUpdate()->findOrFail($companyId);
        $adjusterId = array_key_exists('adjuster_id', $data) ? $data['adjuster_id'] : $claim?->adjuster_id;
        if ($adjusterId) InsuranceAdjuster::where('insurance_company_id', $companyId)->findOrFail($adjusterId);
        return $data;
    }

    public function store(Request $request)
    {
        $claim = DB::transaction(function () use ($request) {
            $data = $this->payload($request);
            $claim = InsuranceClaim::create(array_merge(['claim_status'=>'open'], $data, ['organization_id'=>$request->user()->organization_id]));
            $this->logTimeline((int)$claim->case_id, 'legal', 'Insurance Claim Added', 'Coverage information recorded; insurer acceptance is not confirmed.', ['claim_id'=>$claim->id,'details'=>$claim->getAttributes()]);
            return $claim;
        });
        return response()->json(['status'=>true,'data'=>$claim], 201);
    }

    public function show(Request $request, $id)
    {
        return response()->json(['status'=>true,'data'=>$this->scoped($request)->with(['insuranceCompany:id,name','case:id,case_number,title'])->findOrFail($id)]);
    }

    public function update(Request $request, $id)
    {
        $claim = DB::transaction(function () use ($request, $id) {
            $claim = $this->scoped($request)->lockForUpdate()->findOrFail($id);
            $data = $this->payload($request, $claim);
            $previous = $claim->getAttributes();
            $claim->fill($data);
            if ($claim->isDirty()) {
                $claim->save();
                $this->logTimeline((int)$claim->case_id, 'legal', 'Insurance Claim Updated', 'Recorded coverage information updated.', ['claim_id'=>$claim->id,'previous_details'=>$previous,'details'=>$claim->getAttributes()]);
            }
            return $claim;
        });
        return response()->json(['status'=>true,'data'=>$claim]);
    }
}
