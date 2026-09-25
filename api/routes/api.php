<?php

use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\Auth\PasswordResetController;
use App\Http\Controllers\API\BlogCategoryController;
use App\Http\Controllers\API\BlogController;
use App\Http\Controllers\API\DocumentController;
use App\Http\Controllers\API\FaqController;
use App\Http\Controllers\API\OcrController;
use App\Http\Controllers\API\OrganizationController;
use App\Http\Controllers\API\OrganizationTypeController;
use App\Http\Controllers\API\SignatureController;
use App\Http\Controllers\API\SubscriptionPlanController;
use App\Http\Controllers\API\TestimonialController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\WhyWeDifferentController;
use App\Http\Controllers\API\SecurityController;
use App\Http\Controllers\API\IpAllowlistController;
use App\Http\Controllers\API\RoleController;
use App\Http\Controllers\API\PermissionController;
use App\Http\Controllers\API\InvoiceController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\AppealController;
use App\Http\Controllers\API\ValidationController;
use App\Http\Controllers\API\BillingAnalyticsController;
use App\Http\Controllers\AppSettingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public authentication routes
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('auth/mfa/setup', [\App\Http\Controllers\API\Auth\MfaController::class, 'setup'])->middleware('throttle:mfa-setup');
Route::post('auth/mfa/verify', [\App\Http\Controllers\API\Auth\MfaController::class, 'verify'])->middleware('throttle:mfa-verify');
Route::post('refresh', [AuthController::class, 'refreshToken'])->middleware('throttle:token-refresh');
Route::post('refresh-token', [AuthController::class, 'refreshToken'])->middleware('throttle:token-refresh');

// Password reset routes
Route::post('forgot-password', [PasswordResetController::class, 'forgotPassword'])->middleware('throttle:password-recovery');
Route::post('reset-password', [PasswordResetController::class, 'resetPassword'])->middleware('throttle:password-reset');

Route::get('get-setting-values', [AppSettingController::class, 'getSettingValues']);
Route::get('faqs', [FaqController::class, 'index']);
Route::get('testimonials', [TestimonialController::class, 'index']);
Route::get('whywedifferent', [WhyWeDifferentController::class, 'index']);
Route::post('signatures/docusign/webhook', [SignatureController::class, 'docusignWebhook']);

// Protected routes
Route::middleware(['auth:api', 'tenant', '2fa'])->group(function () {
    Route::middleware(['role:pharmacist,pharmacy_technician,medical_biller,admin', \App\Http\Middleware\PharmacyPreviewOnly::class, \App\Http\Middleware\PharmacyWriteTransaction::class])->prefix('pharmacy')->group(function () {
        $compound = \App\Http\Controllers\API\PharmacyCompoundingController::class;
        Route::get('/formulations', [$compound, 'formulas']);
        Route::post('/formulations', [$compound, 'createFormula']);
        Route::get('/formulations/{id}', [$compound, 'showFormula']);
        Route::post('/formulations/{id}/review', [$compound, 'reviewFormula']);
        Route::get('/batch-worksheets', [$compound, 'batches']);
        Route::post('/batch-worksheets', [$compound, 'createBatch']);
        Route::get('/batch-worksheets/{id}', [$compound, 'showBatch']);
        Route::post('/batch-worksheets/{id}/review', [$compound, 'reviewBatch']);
        Route::get('/patients', [\App\Http\Controllers\API\PharmacyPatientController::class, 'index']);
        Route::post('/patients', [\App\Http\Controllers\API\PharmacyPatientController::class, 'store']);
        Route::get('/patients/{id}', [\App\Http\Controllers\API\PharmacyPatientController::class, 'show']);
        Route::put('/patients/{id}/clinical', [\App\Http\Controllers\API\PharmacyPatientController::class, 'clinical']);
        Route::get('/staff', [\App\Http\Controllers\API\PharmacyStaffController::class, 'index']);
        Route::put('/staff', [\App\Http\Controllers\API\PharmacyStaffController::class, 'save']);
        Route::get('/locations', [\App\Http\Controllers\API\PharmacyInventoryController::class, 'locations']);
        Route::post('/locations', [\App\Http\Controllers\API\PharmacyInventoryController::class, 'storeLocation']);
        Route::get('/stock', [\App\Http\Controllers\API\PharmacyInventoryController::class, 'index']);
        Route::post('/stock', [\App\Http\Controllers\API\PharmacyInventoryController::class, 'store']);
        Route::put('/stock/{id}/status', [\App\Http\Controllers\API\PharmacyInventoryController::class, 'status']);

        $controller = \App\Http\Controllers\API\PharmacyController::class;
        Route::get('cases', [$controller, 'cases']);
        Route::get('prescriptions', [$controller, 'index']);
        Route::post('prescriptions', [$controller, 'store']);
        Route::get('prescriptions/{id}', [$controller, 'show']);
        Route::put('prescriptions/{id}/coverage', [$controller, 'coverage']);
        Route::post('prescriptions/{id}/fills', [$controller, 'createFill']);
        Route::post('prescriptions/{id}/fills/{fillId}/actions', [$controller, 'fillAction']);
        Route::get('prescriptions/{id}/assistant', [$controller, 'assistant']);
    });
    Route::get('/auth/mfa/status', [\App\Http\Controllers\API\Auth\MfaController::class, 'status']);
    Route::post('/auth/mfa/manage', [\App\Http\Controllers\API\Auth\MfaController::class, 'manage'])->middleware('throttle:mfa-manage');
    Route::post('/logout', [AuthController::class, 'logout']);

    // Routes accessible to all authenticated users (admin, manager, and user)
    Route::get('/profile', function (Request $request) {
        return response()->json([
            'status' => true,
            'message' => 'Profile retrieved successfully',
            'user' => $request->user(),
        ]);
    });

    // 1. Super Admin (Platform Management)
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/users', [AuthController::class, 'user']);
        Route::get('/admin/dashboard', function () {
            return response()->json(['status' => true, 'message' => 'Admin dashboard accessed']);
        });

        // Organization Management
        Route::get('/organizations', [OrganizationController::class, 'index']);
        Route::post('/organizations', [OrganizationController::class, 'store']);
        Route::get('/organizations/{id}', [OrganizationController::class, 'show']);
        Route::put('/organizations/{id}', [OrganizationController::class, 'update']);
        Route::post('/organizations/{id}', [OrganizationController::class, 'update']);
        Route::delete('/organizations/{id}', [OrganizationController::class, 'destroy']);

        // Organization Type Management
        Route::get('/organization-types', [OrganizationTypeController::class, 'index']);
        Route::post('/organization-types', [OrganizationTypeController::class, 'store']);
        Route::get('/organization-types/{id}', [OrganizationTypeController::class, 'show']);
        Route::put('/organization-types/{id}', [OrganizationTypeController::class, 'update']);
        Route::delete('/organization-types/{id}', [OrganizationTypeController::class, 'destroy']);


        // CMS / Platform Content (Super Admin)
        Route::get('/admin/blogs', [BlogController::class, 'index']);
        Route::post('/admin/blogs', [BlogController::class, 'store']);
        Route::get('/admin/blogs/{id}', [BlogController::class, 'show']);
        Route::put('/admin/blogs/{id}', [BlogController::class, 'update']);
        Route::post('/admin/blogs/{id}', [BlogController::class, 'update']); // For file uploads
        Route::delete('/admin/blogs/{id}', [BlogController::class, 'destroy']);

        Route::get('/admin/blog-categories', [BlogCategoryController::class, 'index']);
        Route::post('/admin/blog-categories', [BlogCategoryController::class, 'store']);
        Route::get('/admin/blog-categories/{id}', [BlogCategoryController::class, 'show']);
        Route::put('/admin/blog-categories/{id}', [BlogCategoryController::class, 'update']);
        Route::delete('/admin/blog-categories/{id}', [BlogCategoryController::class, 'destroy']);

        Route::get('/admin/faqs', [FaqController::class, 'index']);
        Route::post('/admin/faq', [FaqController::class, 'store']);
        Route::put('/admin/faq/{id}', [FaqController::class, 'update']);
        Route::delete('/admin/faq/{id}', [FaqController::class, 'destroy']);

        Route::get('/admin/testimonials', [TestimonialController::class, 'index']);
        Route::post('/admin/testimonial', [TestimonialController::class, 'store']);
        Route::put('/admin/testimonial/{id}', [TestimonialController::class, 'update']);
        Route::delete('/admin/testimonial/{id}', [TestimonialController::class, 'destroy']);

        Route::post('/admin/whywedifferent', [WhyWeDifferentController::class, 'store']);
        Route::put('/admin/whywedifferent/{id}', [WhyWeDifferentController::class, 'update']);
        Route::delete('/admin/whywedifferent/{id}', [WhyWeDifferentController::class, 'destroy']);
    });

    // 2. Attorney, Firm Admin, Medical Biller & Provider (Legal/Case/Document Management)
    // PDF Roles: attorney, firm_admin, medical_biller, provider_staff can access cases
    Route::middleware(['role:admin,firm_admin,attorney,medical_biller,provider_staff,client'])->group(function () {
        // Case Management - All roles can view, limited roles can edit
        Route::get('/cases', [\App\Http\Controllers\API\CaseController::class, 'index']);
        Route::post('/cases', [\App\Http\Controllers\API\CaseController::class, 'store'])->middleware('role:admin,firm_admin,attorney');
        Route::get('/cases/{id}', [\App\Http\Controllers\API\CaseController::class, 'show']);
        Route::put('/cases/{id}', [\App\Http\Controllers\API\CaseController::class, 'update'])->middleware('role:admin,firm_admin,attorney');
        Route::delete('/cases/{id}', [\App\Http\Controllers\API\CaseController::class, 'destroy'])->middleware('role:admin,firm_admin,attorney');
        Route::post('/cases/{id}/parties', [\App\Http\Controllers\API\CaseController::class, 'addParty'])->middleware('role:admin,firm_admin,attorney');

        // Document Management
        Route::get('/documents', [DocumentController::class, 'index']);
        Route::post('/documents', [DocumentController::class, 'store']);
        Route::get('/documents/{id}', [DocumentController::class, 'show']);
        Route::get('/documents/{id}/preview', [DocumentController::class, 'preview']);
        Route::get('/documents/{id}/completion-certificate', [DocumentController::class, 'completionCertificate']);
        Route::delete('/documents/{id}', [DocumentController::class, 'destroy'])->middleware('role:admin,firm_admin,attorney');
        
        Route::get('/documents/{id}/eligible-signers', [DocumentController::class, 'eligibleSigners'])->middleware('role:admin,firm_admin,attorney');
        Route::post('/documents/{id}/signers', [DocumentController::class, 'assignSigners'])->middleware('role:admin,firm_admin,attorney');
        Route::post('/documents/{id}/send-for-signature', [DocumentController::class, 'sendForSignature'])->middleware('role:admin,firm_admin,attorney');
        Route::post('/documents/{id}/sign-in-app', [DocumentController::class, 'signInApp']);
        Route::get('/documents/{id}/signature-status', [DocumentController::class, 'signatureStatus']);
        Route::get('/signatures/document/{documentId}', [SignatureController::class, 'historyByDocument']);
    });

    // 3. Medical Biller, Firm Admin & Provider Staff (Billing Management)
    // PDF Roles: medical_biller, firm_admin, provider_staff can access billing
    Route::middleware(['role:admin,firm_admin,medical_biller,provider_staff,client'])->group(function () {
        Route::get('/billing/stats', [\App\Http\Controllers\API\BillingDashboardController::class, 'index']);
        
        // Invoices - All can view, only biller/firm_admin can create/edit
        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::post('/invoices', [InvoiceController::class, 'store'])->middleware('role:admin,firm_admin,medical_biller,provider_staff');
        Route::post('/invoices/{id}/review', [InvoiceController::class, 'review'])->middleware('role:admin,firm_admin,medical_biller');
        Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
        Route::put('/invoices/{id}', [InvoiceController::class, 'update'])->middleware('role:admin,firm_admin,medical_biller');
        Route::delete('/invoices/{id}', [InvoiceController::class, 'destroy'])->middleware('role:admin,firm_admin,medical_biller');

        // Payments
        Route::get('/payments', [PaymentController::class, 'index']);
        Route::post('/payments', [PaymentController::class, 'store'])->middleware('role:admin,firm_admin,medical_biller');
        Route::get('/payments/{id}', [PaymentController::class, 'show']);
        Route::post('/payments/{id}/reverse', [PaymentController::class, 'reverse'])->middleware('role:admin,firm_admin,medical_biller');
        Route::delete('/payments/{id}', [PaymentController::class, 'destroy'])->middleware('role:admin,firm_admin,medical_biller');

        // AI Appeals
        Route::get('/billing/appeals', [AppealController::class, 'index']);
        Route::post('/billing/appeals/generate', [AppealController::class, 'generate'])->middleware('role:admin,firm_admin,medical_biller');

        // Validation Issues
        Route::get('/billing/validation', [ValidationController::class, 'index']);
        Route::put('/billing/validation/{id}', [ValidationController::class, 'update'])->middleware('role:admin,firm_admin,medical_biller');

        // Analytics
        Route::get('/billing/analytics', [BillingAnalyticsController::class, 'index']);

        // OCR & Billing process
        Route::post('/ocr/documents/{documentId}/process', [OcrController::class, 'process'])->middleware('role:admin,firm_admin,medical_biller,provider_staff');
        Route::get('/ocr/documents/{documentId}/latest', [OcrController::class, 'latest']);
    });

    // 4. Provider Staff (Provider Portal)
    Route::middleware(['role:admin,firm_admin,provider_staff'])->group(function () {
        Route::put('/provider/invoices/{id}/draft', [InvoiceController::class, 'updateProviderDraft'])->middleware('role:provider_staff');
        Route::get('/provider/stats', [\App\Http\Controllers\API\ProviderDashboardController::class, 'index']);
        Route::get('/provider/claims', [\App\Http\Controllers\API\ProviderClaimController::class, 'index']);
        Route::post('/provider/claims', [\App\Http\Controllers\API\ProviderClaimController::class, 'store']);
        Route::get('/provider/treatment-records', [\App\Http\Controllers\API\TreatmentRecordController::class, 'index']);
        Route::post('/provider/treatment-records', [\App\Http\Controllers\API\TreatmentRecordController::class, 'store']);
    });

    // 5. Client (Self-service Portal) — PDF Section 2: Self-service access
    Route::middleware(['role:admin,firm_admin,client'])->group(function () {
        // Dashboard
        Route::get('/client/stats', [\App\Http\Controllers\API\ClientDashboardController::class, 'index']);

        // Cases — Read-only (PDF: Client can view case status)
        Route::get('/client/cases', [\App\Http\Controllers\API\CaseController::class, 'index']);
        Route::get('/client/cases/{id}', [\App\Http\Controllers\API\CaseController::class, 'show']);

        // Documents — Upload + View (PDF: Client can upload records, sign documents)
        Route::get('/client/documents', [DocumentController::class, 'index']);
        Route::post('/client/documents', [DocumentController::class, 'store']);
        Route::get('/client/documents/{id}', [DocumentController::class, 'show']);
        Route::get('/client/documents/{id}/preview', [DocumentController::class, 'preview']);
        Route::get('/client/documents/{id}/completion-certificate', [DocumentController::class, 'completionCertificate']);

        // Invoices — Read-only (PDF: Client can view invoices)
        Route::get('/client/invoices', [InvoiceController::class, 'index']);
        Route::get('/client/invoices/{id}', [InvoiceController::class, 'show']);

        // Clients can view recorded receipts; only billing roles may create them.
        Route::get('/client/payments', [PaymentController::class, 'index']);
        Route::post('/client/payments', [PaymentController::class, 'store']);

        // Signatures — View pending + status (PDF: Client can sign documents)
        Route::get('/client/signatures/pending', [\App\Http\Controllers\API\ClientDashboardController::class, 'pendingSignatures']);
        Route::get('/client/documents/{id}/signature-status', [DocumentController::class, 'signatureStatus']);
        Route::get('/client/signatures/document/{documentId}', [SignatureController::class, 'historyByDocument']);

        // Profile — Self-service (PDF: Client Portal Access)
        Route::get('/client/profile', [\App\Http\Controllers\API\ClientDashboardController::class, 'profile']);
        Route::put('/client/profile', [\App\Http\Controllers\API\ClientDashboardController::class, 'updateProfile']);
    });

    // 6. User & System Management (Firm Admin & Admin)
    Route::middleware(['role:admin,firm_admin,attorney'])->group(function () {
        Route::get('/firm/stats', [\App\Http\Controllers\API\FirmDashboardController::class, 'index']);

        // 8. Demand Letter Management (PDF Section 8)
        Route::get('/demand-letters', [\App\Http\Controllers\API\DemandLetterController::class, 'index']);
        Route::post('/demand-letters', [\App\Http\Controllers\API\DemandLetterController::class, 'store']);
        Route::get('/demand-letters/{id}', [\App\Http\Controllers\API\DemandLetterController::class, 'show']);
        Route::put('/demand-letters/{id}', [\App\Http\Controllers\API\DemandLetterController::class, 'update']);
        Route::delete('/demand-letters/{id}', [\App\Http\Controllers\API\DemandLetterController::class, 'destroy']);

        // 9. Case Settlements (PDF Section 9/Attorney specific)
        Route::get('/settlements', [\App\Http\Controllers\API\CaseSettlementController::class, 'index']);
        Route::post('/settlements', [\App\Http\Controllers\API\CaseSettlementController::class, 'store']);
        Route::get('/settlements/{id}', [\App\Http\Controllers\API\CaseSettlementController::class, 'show']);
        Route::post('/settlements/{id}/corrections', [\App\Http\Controllers\API\CaseSettlementController::class, 'correct']);
        Route::put('/settlements/{id}', [\App\Http\Controllers\API\CaseSettlementController::class, 'update']);
        Route::delete('/settlements/{id}', [\App\Http\Controllers\API\CaseSettlementController::class, 'destroy']);
    });

    Route::middleware(['role:admin,firm_admin,attorney'])->group(function () {
            Route::get('/insurance/companies', [\App\Http\Controllers\API\InsuranceController::class, 'index']);
            Route::get('/insurance/claims', [\App\Http\Controllers\API\InsuranceClaimController::class, 'index']);
            Route::post('/insurance/claims', [\App\Http\Controllers\API\InsuranceClaimController::class, 'store']);
            Route::get('/insurance/claims/{id}', [\App\Http\Controllers\API\InsuranceClaimController::class, 'show']);
            Route::put('/insurance/claims/{id}', [\App\Http\Controllers\API\InsuranceClaimController::class, 'update']);
            Route::get('/insurance/correspondence', [\App\Http\Controllers\API\InsuranceCorrespondenceController::class, 'index']);
            Route::post('/insurance/correspondence', [\App\Http\Controllers\API\InsuranceCorrespondenceController::class, 'store']);
    });

    // EOB Processing (PDF Section 6)
    Route::middleware(['role:admin,firm_admin,medical_biller'])->group(function () {
        Route::get('/eobs', [\App\Http\Controllers\API\EobController::class, 'index']);
        Route::get('/eobs/stats', [\App\Http\Controllers\API\EobController::class, 'stats']);
        Route::post('/eobs', [\App\Http\Controllers\API\EobController::class, 'store']);
        Route::get('/eobs/{id}', [\App\Http\Controllers\API\EobController::class, 'show']);
        Route::put('/eobs/{id}', [\App\Http\Controllers\API\EobController::class, 'update']);
        Route::delete('/eobs/{id}', [\App\Http\Controllers\API\EobController::class, 'destroy']);
    });

    Route::middleware(['role:admin,firm_admin'])->group(function () {
        Route::get('/firm/organization', [\App\Http\Controllers\API\FirmOrganizationController::class, 'show']);
        Route::post('/firm/organization', [\App\Http\Controllers\API\FirmOrganizationController::class, 'update']);
        
        // 7. Insurance Management (PDF Section 7)
        Route::middleware(['role:admin,firm_admin,attorney'])->group(function () {
            Route::post('/insurance/companies', [\App\Http\Controllers\API\InsuranceController::class, 'store']);
            Route::get('/insurance/companies/{id}', [\App\Http\Controllers\API\InsuranceController::class, 'show']);
            Route::put('/insurance/companies/{id}', [\App\Http\Controllers\API\InsuranceController::class, 'update']);
            Route::delete('/insurance/companies/{id}', [\App\Http\Controllers\API\InsuranceController::class, 'destroy']);
        });

        // 10. Provider & Lien Management (PDF Section 10)
        Route::get('/providers', [\App\Http\Controllers\API\ProviderController::class, 'index']);
        Route::post('/providers', [\App\Http\Controllers\API\ProviderController::class, 'store']);
        Route::get('/providers/{id}', [\App\Http\Controllers\API\ProviderController::class, 'show']);
        Route::put('/providers/{id}', [\App\Http\Controllers\API\ProviderController::class, 'update']);
        Route::delete('/providers/{id}', [\App\Http\Controllers\API\ProviderController::class, 'destroy']);
        
        Route::middleware(['role:admin,firm_admin,attorney'])->group(function () {
            Route::get('/letters-of-protection', [\App\Http\Controllers\API\LetterOfProtectionController::class, 'index']);
            Route::post('/letters-of-protection', [\App\Http\Controllers\API\LetterOfProtectionController::class, 'store']);
        });

        Route::get('/treatment-records', [\App\Http\Controllers\API\TreatmentRecordController::class, 'index']);
        Route::post('/treatment-records', [\App\Http\Controllers\API\TreatmentRecordController::class, 'store']);

        // Subscription Plan Management
        Route::get('/subscription-plans', [SubscriptionPlanController::class, 'index']);
        Route::post('/subscription-plans', [SubscriptionPlanController::class, 'store']);
        Route::get('/subscription-plans/{id}', [SubscriptionPlanController::class, 'show']);
        Route::put('/subscription-plans/{id}', [SubscriptionPlanController::class, 'update']);
        Route::delete('/subscription-plans/{id}', [SubscriptionPlanController::class, 'destroy']);

        // API Credential Management
        Route::get('/api-credentials', [\App\Http\Controllers\API\ApiCredentialController::class, 'index']);
        Route::post('/api-credentials', [\App\Http\Controllers\API\ApiCredentialController::class, 'store']);
        Route::get('/api-credentials/{id}', [\App\Http\Controllers\API\ApiCredentialController::class, 'show']);
        Route::put('/api-credentials/{id}', [\App\Http\Controllers\API\ApiCredentialController::class, 'update']);
        Route::delete('/api-credentials/{id}', [\App\Http\Controllers\API\ApiCredentialController::class, 'destroy']);

        // Role & Permission Management
        Route::get('/admin/roles', [RoleController::class, 'index'])->middleware('role:admin');
        Route::post('/admin/roles', [RoleController::class, 'store'])->middleware('role:admin');
        Route::get('/admin/roles/{id}', [RoleController::class, 'show'])->middleware('role:admin');
        Route::put('/admin/roles/{id}', [RoleController::class, 'update'])->middleware('role:admin');
        Route::delete('/admin/roles/{id}', [RoleController::class, 'destroy'])->middleware('role:admin');

        Route::get('/admin/permissions', [PermissionController::class, 'index'])->middleware('role:admin');
        Route::post('/admin/permissions', [PermissionController::class, 'store'])->middleware('role:admin');
        Route::get('/admin/permissions/{id}', [PermissionController::class, 'show'])->middleware('role:admin');
        Route::put('/admin/permissions/{id}', [PermissionController::class, 'update'])->middleware('role:admin');
        Route::delete('/admin/permissions/{id}', [PermissionController::class, 'destroy'])->middleware('role:admin');

        // Audit Logs
        Route::get('/admin/audit-logs', [\App\Http\Controllers\API\AuditLogController::class, 'index']);
        Route::get('/admin/audit-logs/{id}', [\App\Http\Controllers\API\AuditLogController::class, 'show']);

        // Security Management
        Route::get('/admin/security/settings', [SecurityController::class, 'getSettings'])->middleware('role:admin');
        Route::put('/admin/security/settings', [SecurityController::class, 'updateSettings'])->middleware('role:admin');
        Route::get('/admin/security/stats', [SecurityController::class, 'getSecurityStats'])->middleware('role:admin');
        Route::get('/admin/security/events', [SecurityController::class, 'getSecurityEvents'])->middleware('role:admin');

        // IP Allowlist
        Route::get('/admin/security/ip-allowlist', [IpAllowlistController::class, 'index'])->middleware('role:admin');
        Route::post('/admin/security/ip-allowlist', [IpAllowlistController::class, 'store'])->middleware('role:admin');
        Route::put('/admin/security/ip-allowlist/{id}', [IpAllowlistController::class, 'update'])->middleware('role:admin');
        Route::delete('/admin/security/ip-allowlist/{id}', [IpAllowlistController::class, 'destroy'])->middleware('role:admin');

        Route::get('/admin/users', [UserController::class, 'index']);
        Route::post('/admin/users', [UserController::class, 'store']);
        Route::get('/admin/users/{id}', [UserController::class, 'show']);
        Route::put('/admin/users/{id}', [UserController::class, 'update']);
        Route::delete('/admin/users/{id}', [UserController::class, 'destroy']);
        
        Route::post('setting-update', [AppSettingController::class, 'settingUpdate'])->middleware('role:admin');
        Route::get('get-env-values', [AppSettingController::class, 'getEnvValues'])->middleware('role:admin');
        Route::post('setting-env-update', [AppSettingController::class, 'settingEnvUpdate'])->middleware('role:admin');
        // GDPR Tools (PDF Section 16 - Security & Compliance)
        Route::get('/gdpr/export/{userId}', [GdprController::class, 'exportUserData'])->middleware('role:admin,firm_admin');
        Route::delete('/gdpr/delete/{userId}', [GdprController::class, 'deleteUserData'])->middleware('role:admin,firm_admin');
        Route::get('/gdpr/audit-trail/{userId}', [GdprController::class, 'userAuditTrail'])->middleware('role:admin,firm_admin');


    });

    Route::middleware(['role:admin,firm_admin,attorney'])->group(function () {
        Route::get('/liens/provider-options', [\App\Http\Controllers\API\LienController::class, 'providerOptions']);
            Route::get('/liens', [\App\Http\Controllers\API\LienController::class, 'index']);
            Route::get('/liens/{id}', [\App\Http\Controllers\API\LienController::class, 'show']);
            Route::post('/liens', [\App\Http\Controllers\API\LienController::class, 'store']);
            Route::put('/liens/{id}', [\App\Http\Controllers\API\LienController::class, 'update']);
            Route::delete('/liens/{id}', [\App\Http\Controllers\API\LienController::class, 'destroy']);
    });

        // Notifications — accessible by ALL authenticated roles (PDF Section 14)
        Route::get('/notifications', [\App\Http\Controllers\API\NotificationController::class, 'index']);
        Route::post('/notifications/{id}/read', [\App\Http\Controllers\API\NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [\App\Http\Controllers\API\NotificationController::class, 'markAllAsRead']);

        // ===== NEW: MEDICAL HISTORY (PDF Section 5) =====
        Route::middleware(['role:admin,firm_admin,attorney,medical_biller,provider_staff'])->group(function () {
            Route::get('/medical-histories', [\App\Http\Controllers\API\MedicalHistoryController::class, 'index']);
            Route::post('/medical-histories', [\App\Http\Controllers\API\MedicalHistoryController::class, 'store']);
            Route::get('/medical-histories/{id}', [\App\Http\Controllers\API\MedicalHistoryController::class, 'show']);
            Route::put('/medical-histories/{id}', [\App\Http\Controllers\API\MedicalHistoryController::class, 'update']);
            Route::delete('/medical-histories/{id}', [\App\Http\Controllers\API\MedicalHistoryController::class, 'destroy']);
        });

        // Client can view their own medical history
        Route::middleware(['role:admin,firm_admin,client'])->group(function () {
            Route::get('/client/medical-histories', [\App\Http\Controllers\API\MedicalHistoryController::class, 'index']);
        });

        // ===== NEW: HIPAA AUTHORIZATIONS (PDF Section 5) =====
        Route::middleware(['role:admin,firm_admin,attorney,medical_biller'])->group(function () {
            Route::get('/hipaa-authorizations', [\App\Http\Controllers\API\HipaaAuthorizationController::class, 'index']);
            Route::post('/hipaa-authorizations', [\App\Http\Controllers\API\HipaaAuthorizationController::class, 'store']);
            Route::get('/hipaa-authorizations/{id}', [\App\Http\Controllers\API\HipaaAuthorizationController::class, 'show']);
            Route::put('/hipaa-authorizations/{id}', [\App\Http\Controllers\API\HipaaAuthorizationController::class, 'update']);
            Route::delete('/hipaa-authorizations/{id}', [\App\Http\Controllers\API\HipaaAuthorizationController::class, 'destroy']);
        });

        // ===== NEW: DOCUMENT CATEGORIES / TAGS (PDF Section 8) =====
        Route::middleware(['role:admin,firm_admin'])->group(function () {
            Route::get('/document-categories', [\App\Http\Controllers\API\DocumentCategoryController::class, 'index']);
            Route::post('/document-categories', [\App\Http\Controllers\API\DocumentCategoryController::class, 'store']);
            Route::get('/document-categories/{id}', [\App\Http\Controllers\API\DocumentCategoryController::class, 'show']);
            Route::put('/document-categories/{id}', [\App\Http\Controllers\API\DocumentCategoryController::class, 'update']);
            Route::delete('/document-categories/{id}', [\App\Http\Controllers\API\DocumentCategoryController::class, 'destroy']);

            // Assign categories to document
            Route::post('/documents/{id}/categories', [\App\Http\Controllers\API\DocumentController::class, 'assignCategories']);
        });

        // ===== NEW: MEDICAL RECORD REQUESTS (PDF Section 8) =====
        Route::middleware(['role:admin,firm_admin,attorney,medical_biller'])->group(function () {
            Route::get('/medical-record-requests', [\App\Http\Controllers\API\MedicalRecordRequestController::class, 'index']);
            Route::post('/medical-record-requests', [\App\Http\Controllers\API\MedicalRecordRequestController::class, 'store']);
            Route::get('/medical-record-requests/{id}', [\App\Http\Controllers\API\MedicalRecordRequestController::class, 'show']);
            Route::put('/medical-record-requests/{id}', [\App\Http\Controllers\API\MedicalRecordRequestController::class, 'update']);
            Route::delete('/medical-record-requests/{id}', [\App\Http\Controllers\API\MedicalRecordRequestController::class, 'destroy']);
        });

        // ===== NEW: CLIENT PORTAL SETTINGS (PDF Section 5) =====
        Route::middleware(['role:admin,firm_admin'])->group(function () {
            Route::get('/client-portal-settings', [\App\Http\Controllers\API\ClientPortalSettingsController::class, 'index']);
            Route::get('/client-portal-settings/{userId}', [\App\Http\Controllers\API\ClientPortalSettingsController::class, 'show']);
            Route::put('/client-portal-settings/{userId}', [\App\Http\Controllers\API\ClientPortalSettingsController::class, 'update']);
        });

        // ===== NEW: REPORTS SYSTEM (PDF Section 11 - All 14 Reports) =====
        Route::middleware(['role:admin,firm_admin,attorney,medical_biller,provider_staff'])->group(function () {
            Route::get('/reports/case-status', [\App\Http\Controllers\API\ReportsController::class, 'caseStatus']);
            Route::get('/reports/revenue-by-period', [\App\Http\Controllers\API\ReportsController::class, 'revenueByPeriod']);
            Route::get('/reports/insurance-aging', [\App\Http\Controllers\API\ReportsController::class, 'insuranceAging']);
            Route::get('/reports/settlement-summary', [\App\Http\Controllers\API\ReportsController::class, 'settlementSummary']);
            Route::get('/reports/attorney-production', [\App\Http\Controllers\API\ReportsController::class, 'attorneyProduction']);
            Route::get('/reports/provider-billing', [\App\Http\Controllers\API\ReportsController::class, 'providerBilling']);
            Route::get('/reports/lien-summary', [\App\Http\Controllers\API\ReportsController::class, 'lienSummary']);
            Route::get('/reports/ocr-processing-log', [\App\Http\Controllers\API\ReportsController::class, 'ocrProcessingLog']);
            Route::get('/reports/signature-activity', [\App\Http\Controllers\API\ReportsController::class, 'signatureActivity']);
            Route::get('/reports/collection-rate', [\App\Http\Controllers\API\ReportsController::class, 'collectionRateReport']);
            Route::get('/reports/referral-source', [\App\Http\Controllers\API\ReportsController::class, 'referralSourceReport']);

            // Admin/Compliance specific reports
            Route::middleware(['role:admin,firm_admin'])->group(function () {
                Route::get('/reports/user-activity-log', [\App\Http\Controllers\API\ReportsController::class, 'userActivityLog']);
                Route::get('/reports/document-audit-trail', [\App\Http\Controllers\API\ReportsController::class, 'documentAuditTrail']);
                Route::get('/reports/hipaa-compliance-log', [\App\Http\Controllers\API\ReportsController::class, 'hipaaComplianceLog']);
            });

            // Report history
            Route::get('/reports/history', [\App\Http\Controllers\API\ReportsController::class, 'reportHistory']);
            Route::get('/reports/{id}', [\App\Http\Controllers\API\ReportsController::class, 'showReport']);
        });

    });
