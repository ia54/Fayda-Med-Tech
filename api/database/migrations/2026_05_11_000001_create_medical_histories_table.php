<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('user_id')->comment('Client/patient');
            $table->unsignedBigInteger('case_id')->nullable();
            $table->date('record_date');
            $table->string('provider_name')->nullable();
            $table->string('diagnosis')->nullable();
            $table->text('treatment_description')->nullable();
            $table->string('medications')->nullable();
            $table->text('notes')->nullable();
            $table->json('attachments')->nullable();
            $table->string('record_type')->default('treatment')->comment('pre_existing, treatment, surgery, medication, imaging, other');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('case_id')->references('id')->on('cases')->onDelete('set null');
            $table->index(['organization_id', 'user_id']);
        });

        Schema::create('hipaa_authorizations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('user_id')->comment('Client/patient who authorized');
            $table->unsignedBigInteger('case_id')->nullable();
            $table->string('authorization_type')->comment('hipaa_release, medical_records, treatment_consent, disclosure');
            $table->string('status')->default('pending')->comment('pending, signed, expired, revoked');
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('recipient_name')->nullable()->comment('Entity receiving the authorization');
            $table->text('purpose')->nullable();
            $table->text('restrictions')->nullable();
            $table->string('signed_document_path')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->unsignedBigInteger('signed_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('case_id')->references('id')->on('cases')->onDelete('set null');
            $table->foreign('signed_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['organization_id', 'user_id', 'status']);
        });

        Schema::create('document_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('color')->nullable();
            $table->string('icon')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
        });

        Schema::create('document_category_document', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('document_category_id');
            $table->timestamps();

            $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
            $table->foreign('document_category_id')->references('id')->on('document_categories')->onDelete('cascade');
            $table->unique(['document_id', 'document_category_id'], 'doc_cat_unique');
        });

        Schema::create('medical_record_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('case_id')->nullable();
            $table->unsignedBigInteger('user_id')->comment('Client/patient');
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('provider_id')->nullable();
            $table->string('provider_name');
            $table->string('provider_fax')->nullable();
            $table->string('provider_email')->nullable();
            $table->string('provider_address')->nullable();
            $table->date('request_date');
            $table->date('followup_date')->nullable();
            $table->string('status')->default('pending')->comment('pending, sent, received, partially_received, closed');
            $table->text('records_requested')->nullable();
            $table->text('notes')->nullable();
            $table->string('authorization_form_path')->nullable();
            $table->json('received_document_ids')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('case_id')->references('id')->on('cases')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('requested_by')->references('id')->on('users');
            $table->foreign('provider_id')->references('id')->on('providers')->onDelete('set null');
            $table->index(['organization_id', 'status']);
        });

        Schema::create('client_portal_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('user_id')->unique();
            $table->boolean('case_status_visible')->default(true);
            $table->boolean('documents_visible')->default(true);
            $table->boolean('invoices_visible')->default(true);
            $table->boolean('payments_visible')->default(true);
            $table->boolean('signatures_visible')->default(true);
            $table->boolean('medical_history_visible')->default(false);
            $table->boolean('allow_document_upload')->default(true);
            $table->boolean('allow_messaging')->default(true);
            $table->string('theme')->default('light');
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('report_definitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category')->comment('cases, billing, insurance, provider, ocr, signatures, compliance');
            $table->text('description')->nullable();
            $table->json('default_columns')->nullable();
            $table->json('filters')->nullable();
            // MySQL 8 requires JSON defaults to be expressions, even for literals.
            $table->json('available_formats')->default(new Expression("('[\"pdf\",\"excel\",\"csv\"]')"));
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
        });

        Schema::create('report_generations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('report_definition_id')->nullable();
            $table->unsignedBigInteger('generated_by');
            $table->string('report_name');
            $table->string('report_type')->comment('case_status, revenue, insurance_aging, settlement, attorney_production, provider_billing, lien_summary, ocr_log, signature_activity, user_activity, document_audit, hipaa_compliance, collection_rate, referral_source');
            $table->json('parameters')->nullable();
            $table->string('format')->default('pdf')->comment('pdf, excel, csv');
            $table->string('status')->default('pending')->comment('pending, generating, completed, failed');
            $table->string('file_path')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('result_summary')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('generated_by')->references('id')->on('users');
            $table->index(['organization_id', 'report_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_generations');
        Schema::dropIfExists('report_definitions');
        Schema::dropIfExists('client_portal_settings');
        Schema::dropIfExists('medical_record_requests');
        Schema::dropIfExists('document_category_document');
        Schema::dropIfExists('document_categories');
        Schema::dropIfExists('hipaa_authorizations');
        Schema::dropIfExists('medical_histories');
    }
};
