<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\{Lien, CaseModel, Provider};
use App\Traits\LogsTimeline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LienController extends Controller
{
    use LogsTimeline;

    private function cases(Request $request)
    {
        abort_unless($request->user()->organization_id, 403);
        $query = CaseModel::where('organization_id', $request->user()->organization_id);
        if ($request->user()->role === 'attorney') $query->assignedToAttorney($request->user()->id);
        return $query;
    }

    private function scoped(Request $request)
    {
        return Lien::where('organization_id', $request->user()->organization_id)
            ->whereIn('case_id', $this->cases($request)->select('id'));
    }

    public function providerOptions(Request $request)
    {
        abort_unless($request->user()->organization_id, 403);
        $data = $request->validate(['search' => 'nullable|string|max:200']);
        $query = Provider::where('organization_id', $request->user()->organization_id)->select('id', 'name');
        if (!empty($data['search'])) $query->where('name', 'like', '%'.$data['search'].'%');
        return response()->json(['data' => $query->orderBy('name')->limit(100)->get()]);
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'search' => 'nullable|string|max:200', 'case_id' => 'nullable|integer',
            'status' => 'nullable|in:pending,negotiated,settled,released',
            'lien_type' => 'nullable|in:medical,attorney,government_medicare,government_medicaid,health_insurance',
            'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $query = $this->scoped($request)->with([
            'case:id,case_number,title',
            'provider' => fn ($q) => $q->where('organization_id', $request->user()->organization_id)->select('id', 'name'),
        ]);
        foreach (['case_id', 'status', 'lien_type'] as $field) {
            if (!empty($data[$field])) $query->where($field, $data[$field]);
        }
        if (!empty($data['search'])) {
            $term = '%'.$data['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('notes', 'like', $term)->orWhereHas('case', fn ($c) => $c->where('case_number', 'like', $term)->orWhere('title', 'like', $term));
            });
        }
        return response()->json(['status' => true, 'data' => $query->latest('id')->paginate($data['per_page'] ?? 15)]);
    }

    private function payload(Request $request, ?Lien $lien = null): array
    {
        $data = $request->validate([
            'case_id' => $lien ? ['sometimes', 'required', 'integer', Rule::in([$lien->case_id])] : 'required|integer',
            'provider_id' => 'nullable|integer',
            'lien_type' => ($lien ? 'sometimes|' : '').'required|in:medical,attorney,government_medicare,government_medicaid,health_insurance',
            'amount' => ($lien ? 'sometimes|' : '').'required|numeric|min:0|max:99999999.99|decimal:0,2',
            'status' => 'sometimes|required|in:pending,negotiated,settled,released',
            'negotiated_amount' => 'nullable|numeric|min:0|max:99999999.99|decimal:0,2',
            'reduction_amount' => 'nullable|numeric|min:0|max:99999999.99|decimal:0,2',
            'payoff_date' => 'nullable|date_format:Y-m-d',
            'release_document_url' => 'nullable|url:http,https|max:255',
            'notes' => 'nullable|string|max:10000',
        ]);
        $this->cases($request)->findOrFail($lien?->case_id ?? $data['case_id']);
        if (!empty($data['provider_id'])) Provider::where('organization_id', $request->user()->organization_id)->findOrFail($data['provider_id']);
        return $data;
    }

    public function store(Request $request)
    {
        $data = $this->payload($request);
        $lien = DB::transaction(function () use ($request, $data) {
            $lien = Lien::create(array_merge(['status' => 'pending'], $data, ['organization_id' => $request->user()->organization_id]));
            $this->logTimeline((int) $lien->case_id, 'legal', 'Lien Added', 'Lien record added; no payment or legal release is confirmed.', ['lien_id' => $lien->id, 'details' => $lien->getAttributes()]);
            return $lien;
        });
        return response()->json(['status' => true, 'data' => $lien], 201);
    }

    public function show(Request $request, $id)
    {
        return response()->json(['status' => true, 'data' => $this->scoped($request)->findOrFail($id)]);
    }

    public function update(Request $request, $id)
    {
        $lien = $this->scoped($request)->findOrFail($id);
        $data = $this->payload($request, $lien);
        $lien = DB::transaction(function () use ($request, $id, $data) {
            $lien = $this->scoped($request)->lockForUpdate()->findOrFail($id);
            if (in_array($lien->status, ['settled', 'released'], true) && isset($data['status'])) {
                $allowed = $lien->status === 'released' ? ['released'] : ['settled', 'released'];
                abort_unless(in_array($data['status'], $allowed, true), 409, 'Settled or released records cannot return to a deletable status.');
            }
            $before = $lien->getAttributes();
            $lien->fill($data);
            if ($lien->isDirty()) {
                $lien->save();
                $this->logTimeline((int) $lien->case_id, 'legal', 'Lien Updated', 'Lien tracking record updated.', ['lien_id' => $lien->id, 'previous_details' => $before, 'details' => $lien->getAttributes()]);
            }
            return $lien;
        });
        return response()->json(['status' => true, 'data' => $lien]);
    }

    public function destroy(Request $request, $id)
    {
        DB::transaction(function () use ($request, $id) {
            $lien = $this->scoped($request)->lockForUpdate()->findOrFail($id);
            abort_if(in_array($lien->status, ['settled', 'released'], true), 409, 'Settled and released lien records must be retained.');
            $this->logTimeline((int) $lien->case_id, 'legal', 'Lien Deleted', 'Lien tracking record deleted.', ['lien_id' => $lien->id, 'previous_details' => $lien->getAttributes()]);
            $lien->delete();
        });
        return response()->json(['status' => true, 'message' => 'Lien record deleted.']);
    }
}
