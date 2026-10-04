<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Services\PharmacyAccess;
use App\Services\PharmacyRecall;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Records staff follow-up facts. Never sends messages, changes fills or releases stock. */
class PharmacyRecallFollowUpController extends Controller
{
    private function target(Request $r, $noticeId, $fillId): array
    {
        abort_unless($r->user()->organization_id && in_array($r->user()->role, ['pharmacist', 'pharmacy_technician'], true), 403);
        $notice = DB::table('pharmacy_recall_notices')->where('organization_id', $r->user()->organization_id)->where('id', $noticeId)->first();
        abort_unless($notice, 404);
        $query = DB::table('pharmacy_fills as f')->join('pharmacy_stock_lots as stock', 'stock.id', '=', 'f.stock_lot_id')
            ->join('pharmacy_prescriptions as rx', 'rx.id', '=', 'f.prescription_id')
            ->where('rx.organization_id', $r->user()->organization_id)->whereColumn('rx.location_id', 'stock.location_id')->where('f.id', $fillId);
        app(PharmacyRecall::class)->matchNotice($query, $notice); app(PharmacyAccess::class)->scope($query, $r->user(), 'stock.location_id');
        $fill = $query->select('f.id', 'f.prescription_id', 'f.fill_number', 'f.fulfillment_status', 'f.created_at', 'rx.rx_number', 'stock.id as stock_lot_id', 'stock.location_id')->first();
        abort_unless($fill, 404);
        return [$notice, $fill];
    }
    public function show(Request $r, $noticeId, $fillId)
    {
        $r->validate(['page' => 'nullable|integer|min:1']);
        [$notice, $fill] = $this->target($r, $noticeId, $fillId);
        $follow = DB::table('pharmacy_recall_follow_ups')->where('notice_id', $notice->id)->where('fill_id', $fill->id)->first();
        $events = DB::table('pharmacy_recall_follow_up_events')->where('follow_up_id', $follow?->id ?? 0)
            ->select('id', 'actor_id', 'version', 'action', 'occurred_on', 'method', 'note', 'evidence', 'created_at')->orderByDesc('version')->paginate(25);
        return response()->json(['data' => ['notice_id' => $notice->id, 'reference' => $notice->reference, 'fill' => $fill,
            'status' => $follow?->status ?? 'not_started', 'version' => $follow?->version ?? 0, 'events' => $events]]);
    }
    public function store(Request $r, $noticeId, $fillId)
    {
        [$notice, $fill] = $this->target($r, $noticeId, $fillId);
        $d = $r->validate(['request_id' => 'required|uuid', 'version' => 'required|integer|min:0',
            'action' => 'required|in:assessment,contact_attempt,response,follow_up,complete,reopen',
            'occurred_on' => 'required|date_format:Y-m-d|before_or_equal:today|after_or_equal:'.substr($fill->created_at, 0, 10),
            'method' => 'required_if:action,contact_attempt,response|nullable|in:phone,secure_message,in_person,other',
            'note' => 'required|string|max:5000', 'evidence' => 'required|string|max:2000']);
        if (in_array($d['action'], ['assessment', 'complete', 'reopen'], true)) { abort_unless($r->user()->role === 'pharmacist', 403); }
        $hash = hash('sha256', json_encode($d, JSON_THROW_ON_ERROR));
        $follow = DB::table('pharmacy_recall_follow_ups')->where('notice_id', $notice->id)->where('fill_id', $fill->id)->first();
        $old = $follow ? DB::table('pharmacy_recall_follow_up_events')->where('follow_up_id', $follow->id)->where('request_id', $d['request_id'])->first() : null;
        if ($old) {
            abort_unless((int) $old->actor_id === (int) $r->user()->id && hash_equals($old->request_hash, $hash), 409, 'This request identifier belongs to a different entry.');
            return $this->show($r, $noticeId, $fillId);
        }
        abort_unless((int) ($follow?->version ?? 0) === $d['version'], 409, 'Follow-up changed. Refresh before adding an entry.');
        $closed = $follow?->status === 'completed';
        abort_if($closed && $d['action'] !== 'reopen', 422, 'A pharmacist must reopen this follow-up before adding more entries.');
        abort_if(! $closed && $d['action'] === 'reopen', 422, 'This follow-up is not completed.');
        if ($d['action'] === 'complete') {
            abort_unless(in_array($fill->fulfillment_status, ['cancelled', 'collected', 'delivered'], true), 422, 'Resolve the outstanding fill reservation before completing follow-up.');
        }
        $status = $d['action'] === 'complete' ? 'completed' : 'open';
        if (! $follow) {
            $id = DB::table('pharmacy_recall_follow_ups')->insertGetId(['notice_id' => $notice->id, 'fill_id' => $fill->id, 'status' => $status, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        } else {
            $id = $follow->id;
            DB::table('pharmacy_recall_follow_ups')->where('id', $id)->update(['status' => $status, 'version' => $follow->version + 1, 'updated_at' => now()]);
        }
        DB::table('pharmacy_recall_follow_up_events')->insert(['follow_up_id' => $id, 'actor_id' => $r->user()->id, 'request_id' => $d['request_id'], 'request_hash' => $hash,
            'version' => $d['version'] + 1, 'action' => $d['action'], 'occurred_on' => $d['occurred_on'], 'method' => $d['method'] ?? null, 'note' => $d['note'], 'evidence' => $d['evidence'], 'created_at' => now()]);
        return $this->show($r, $noticeId, $fillId)->setStatusCode(201);
    }
}
