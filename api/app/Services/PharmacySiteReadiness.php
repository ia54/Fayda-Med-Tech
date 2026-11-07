<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Documentary inventory only. This report never supplies clinical or dispensing authority. */
class PharmacySiteReadiness
{
    public function report(object $site): array
    {
        $staff = DB::table('pharmacy_staff_assignments as a')->join('users as u', 'u.id', '=', 'a.user_id')
            ->where('a.location_id', $site->id)->where('a.active', true)->whereDate('a.valid_until', '>=', now()->toDateString())
            ->where('u.organization_id', $site->organization_id)->where('u.status', 'active')
            ->whereIn('u.role', ['pharmacist', 'pharmacy_technician'])->select('u.role')->get();
        $licenseRecorded = trim((string) $site->license_reference) !== '';
        $requirements = [
            ['id' => 'jurisdiction', 'label' => 'Site jurisdiction and applicable workflow contracts', 'status' => 'not_recorded'],
            ['id' => 'facility_license', 'label' => 'Facility license verification', 'status' => $licenseRecorded ? 'reference_only' : 'not_recorded'],
            ['id' => 'professional_credentials', 'label' => 'Professional credentials and site privileges', 'status' => 'not_verified'],
            ['id' => 'catalogue', 'label' => 'Licensed catalogue, formulary and unit conventions', 'status' => 'not_verified'],
            ['id' => 'provider_acceptance', 'label' => 'Prescription, clinical reference, PDMP and payer interfaces', 'status' => 'not_verified'],
            ['id' => 'controlled_policy', 'label' => 'Schedule-specific controlled medication policy', 'status' => 'not_verified'],
            ['id' => 'sterile_acceptance', 'label' => 'Sterile facility, process and clinical acceptance', 'status' => 'not_verified'],
            ['id' => 'nonsterile_acceptance', 'label' => 'Nonsterile process and clinical acceptance', 'status' => 'not_verified'],
            ['id' => 'hazardous_classification', 'label' => 'Separate hazardous handling classification and acceptance', 'status' => 'not_verified'],
            ['id' => 'device_acceptance', 'label' => 'Physical labels, printer, scanner and handover acceptance', 'status' => 'not_verified'],
            ['id' => 'release_approval', 'label' => 'Accountable owner and per-site professional release approval', 'status' => 'not_recorded'],
        ];
        return [
            'site' => ['id' => (int) $site->id, 'name' => $site->name, 'active' => (bool) $site->active],
            'recorded' => ['facility_license_reference_present' => $licenseRecorded,
                'active_pharmacist_assignments' => $staff->where('role', 'pharmacist')->count(),
                'active_technician_assignments' => $staff->where('role', 'pharmacy_technician')->count()],
            'requirements' => $requirements,
            'workflows' => array_map(fn ($kind) => ['kind' => $kind, 'operational_release_enabled' => false, 'clinical_acceptance_verified' => false], ['ordinary', 'controlled', 'sterile_compounding', 'nonsterile_compounding']),
            'synthetic_preview_only' => true, 'operational_release_enabled' => false,
            'basis' => 'Recorded license text and current assignment rows are not verified credentials or clinical acceptance. Requirements are an outstanding decision inventory, not an approved policy matrix.',
        ];
    }
}
