<?php
// Destructive rehearsal restricted to the disposable GitHub Actions database.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\{Artisan, DB, Schema};
if (getenv('GITHUB_ACTIONS') !== 'true' || !app()->environment('testing') || config('database.default') !== 'mysql'
    || config('database.connections.mysql.database') !== 'faydamed_ci' || config('database.connections.mysql.host') !== '127.0.0.1') {
    throw new RuntimeException('Disposable CI MySQL only; refusing migration rehearsal.');
}
$files = [
    '2026_09_23_000001_add_payment_corrections',
    '2026_09_23_000002_add_settlement_allocations',
    '2026_09_23_000003_add_settlement_request_keys',
    '2026_09_23_000004_add_settlement_corrections',
];
// Empty-schema rollback rehearses down() without discarding business data.
foreach (array_reverse($files) as $name) {
    (require database_path('migrations/'.$name.'.php'))->down();
    DB::table('migrations')->where('migration', $name)->delete();
}
$org = DB::table('organizations')->insertGetId(['org_name'=>'Synthetic upgrade','org_type'=>'law_firm','subscription_plan'=>'test','email'=>'upgrade@example.invalid']);
$user = DB::table('users')->insertGetId(['first_name'=>'Synthetic','last_name'=>'Upgrade','email'=>'upgrade@example.invalid','password'=>bcrypt('synthetic-only'),'role'=>'firm_admin','organization_id'=>$org,'status'=>'active']);
$case = DB::table('cases')->insertGetId(['organization_id'=>$org,'created_by'=>$user,'case_number'=>'CI-UPGRADE','title'=>'Synthetic only','status'=>'New']);
$settlement = DB::table('case_settlements')->insertGetId(['organization_id'=>$org,'case_id'=>$case,'created_by'=>$user,'settlement_amount'=>'123.45','attorney_fees'=>'20.05','costs'=>'3.40','settlement_date'=>'2026-09-23','status'=>'completed','notes'=>'Legacy preserved']);
$invoice = DB::table('invoices')->insertGetId(['organization_id'=>$org,'case_id'=>$case,'invoice_number'=>'CI-UPGRADE','amount'=>'123.45','status'=>'sent']);
$payment = DB::table('payments')->insertGetId(['organization_id'=>$org,'invoice_id'=>$invoice,'amount'=>'23.45','payment_date'=>'2026-09-23','transaction_id'=>'synthetic-upgrade']);
$beforeSettlement = (array) DB::table('case_settlements')->find($settlement);
$beforePayment = (array) DB::table('payments')->find($payment);
if (Artisan::call('migrate', ['--force'=>true,'--no-interaction'=>true]) !== 0) throw new RuntimeException('Upgrade failed.');
$afterSettlement = (array) DB::table('case_settlements')->find($settlement);
$afterPayment = (array) DB::table('payments')->find($payment);
foreach ($beforeSettlement as $key=>$value) if ($afterSettlement[$key] !== $value) throw new RuntimeException('Legacy settlement changed: '.$key);
foreach ($beforePayment as $key=>$value) if ($afterPayment[$key] !== $value) throw new RuntimeException('Legacy payment changed: '.$key);
foreach (['other_deductions','request_id','request_hash','supersedes_id','correction_reason'] as $key) if ($afterSettlement[$key] !== null) throw new RuntimeException('Legacy settlement default is not unknown: '.$key);
foreach (['reversal_of_id','recorded_by'] as $key) if ($afterPayment[$key] !== null) throw new RuntimeException('Legacy payment default changed: '.$key);
if (Artisan::call('migrate', ['--force'=>true,'--no-interaction'=>true]) !== 0) throw new RuntimeException('Repeated migration failed.');
echo "PASS: four additive migrations preserve legacy payment and settlement values; unknown fields remain null; repeat migration succeeds.\n";
