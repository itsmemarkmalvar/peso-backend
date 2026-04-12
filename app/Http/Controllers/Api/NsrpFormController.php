<?php

namespace App\Http\Controllers\Api;

use App\Models\Intern;
use App\Models\NsrpForm;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;

class NsrpFormController extends BaseController
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $form = NsrpForm::where('user_id', $user->id)->first();
        $payload = $form ? $this->mergeFormWithDefaults($form, $user) : $this->defaultPayloadForUser($user);

        return $this->success($payload, 'NSRP form');
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $form = NsrpForm::where('user_id', $user->id)->first();

        return $this->success([
            'is_completed' => (bool) optional($form)->is_completed,
            'submitted_at' => optional(optional($form)->submitted_at)?->toISOString(),
        ], 'NSRP status');
    }

    public function saveDraft(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isInternOrGip()) {
            return $this->forbidden('Only intern and GIP users can submit NSRP forms.');
        }

        $payload = $this->normalizePayload($request->all(), $user);
        $form = NsrpForm::updateOrCreate(
            ['user_id' => $user->id],
            array_merge($payload, [
                'is_completed' => false,
                'submitted_at' => null,
            ])
        );

        return $this->success($this->mergeFormWithDefaults($form, $user), 'NSRP draft saved');
    }

    public function submit(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isInternOrGip()) {
            return $this->forbidden('Only intern and GIP users can submit NSRP forms.');
        }

        $payload = $this->normalizePayload($request->all(), $user);
        $this->validateSubmitPayload($payload);

        $form = NsrpForm::updateOrCreate(
            ['user_id' => $user->id],
            array_merge($payload, [
                'is_completed' => true,
                'submitted_at' => now(),
            ])
        );

        return $this->success($this->mergeFormWithDefaults($form, $user), 'NSRP form submitted', 201);
    }

    public function show(Request $request, int $userId): JsonResponse
    {
        $target = User::find($userId);
        if (!$target) {
            return $this->notFound('User not found.');
        }

        if (!$this->canViewUserNsrp($request, $target)) {
            return $this->forbidden('You do not have permission to view this NSRP form.');
        }

        $form = NsrpForm::where('user_id', $target->id)->first();
        if (!$form) {
            return $this->success(null, 'NSRP form not found.');
        }

        return $this->success($this->mergeFormWithDefaults($form, $target), 'NSRP form details');
    }

    public function pdf(Request $request, int $userId)
    {
        $target = User::find($userId);
        if (!$target) {
            return $this->notFound('User not found.');
        }

        if (!$this->canViewUserNsrp($request, $target)) {
            return $this->forbidden('You do not have permission to view this NSRP form.');
        }

        $form = NsrpForm::where('user_id', $target->id)->first();
        if (!$form) {
            return $this->notFound('NSRP form not found.');
        }

        $payload = $this->mergeFormWithDefaults($form, $target);

        $fmtBool = function (mixed $value): string {
            return (bool) $value ? 'Yes' : 'No';
        };

        $isNA = function (mixed $value): bool {
            if ($value === null) return true;
            if (is_string($value)) {
                $t = trim($value);
                if ($t === '') return true;
                return strtolower($t) === 'n/a' || strtoupper($t) === 'N/A';
            }
            return false;
        };

        $fmt = function (mixed $value) use ($isNA, $fmtBool): string {
            if ($value === null) return 'Not Applicable';
            if (is_bool($value)) return $fmtBool($value);
            if (is_numeric($value)) {
                // Keep numeric values as-is (0 is meaningful for some fields; filtering is done per-section).
                return (string) $value;
            }
            if (is_string($value)) {
                $t = trim($value);
                if ($isNA($t)) return 'Not Applicable';
                return $t;
            }
            return 'Not Applicable';
        };

        $fmtDate = function (?string $value): string {
            if ($value === null) return 'Not Applicable';
            $t = trim((string) $value);
            if ($t === '' || strtolower($t) === 'n/a') return 'Not Applicable';
            try {
                return Carbon::parse($t)->format('F j, Y');
            } catch (\Exception $e) {
                return $t;
            }
        };

        $joinAddress = function (array $address) use ($fmt): string {
            $parts = [
                $fmt($address['house_no'] ?? null),
                $fmt($address['barangay'] ?? null),
                $fmt($address['city'] ?? null),
                $fmt($address['province'] ?? null),
            ];
            // Remove "Not Applicable" parts for readability, but keep at least one token.
            $filtered = array_values(array_filter($parts, fn ($p) => $p !== 'Not Applicable'));
            if (count($filtered) === 0) return 'Not Applicable';
            return implode(', ', $filtered);
        };

        $meaningful = function (mixed $value) use ($isNA): bool {
            if ($value === null) return false;
            if (is_bool($value)) return true;
            if (is_numeric($value)) return ((float) $value) !== 0.0;
            if (is_string($value)) return !$isNA($value);
            return false;
        };

        $html = '<!doctype html><html><head><meta charset="utf-8">';
        $html .= '<style>
            body{font-family:DejaVu Sans, Arial, sans-serif; font-size:11px; color:#0f172a;}
            .meta{margin:0 0 10px; color:#475569; font-size:10px;}
            h1{font-size:16px; margin:0 0 6px; letter-spacing:0.2px;}
            h2{font-size:12px; margin:14px 0 6px; padding:6px 8px; background:#f1f5f9; border:1px solid #cbd5e1;}
            h3{font-size:11px; margin:10px 0 4px; font-weight:700;}
            table{width:100%; border-collapse:collapse;}
            th,td{border:1px solid #cbd5e1; padding:6px 8px; vertical-align:top;}
            th{background:#f8fafc; text-align:left; font-weight:700;}
            .kv{margin:0 0 3px;}
            .label{font-weight:700;}
            ul{margin:6px 0 0 16px; padding:0;}
            li{margin:0 0 2px;}
            .small{font-size:10px; color:#475569;}
        </style></head><body>';
        $html .= '<h1>NSRP Form 1</h1>';
        $html .= '<div class="meta">Name: ' . htmlspecialchars($target->name ?? '') . ' &nbsp;&nbsp; Email: ' . htmlspecialchars($target->email ?? '') . '</div>';
        $html .= '<div class="meta">Submitted: ' . htmlspecialchars($form->submitted_at ? $form->submitted_at->format('F j, Y') : 'Not Applicable') . '</div>';

        // I. Personal Information
        $pi = (array) ($payload['personal_information'] ?? []);
        $address = (array) ($pi['address'] ?? []);
        $emp = (array) ($pi['employment_status'] ?? []);
        $empEmployed = (array) ($emp['employed_details'] ?? []);
        $empUnemployed = (array) ($emp['unemployed_details'] ?? []);
        $ofw = (array) ($pi['ofw'] ?? []);
        $fourPs = (array) ($pi['four_ps'] ?? []);

        $html .= '<h2>I. Personal Information</h2>';
        $html .= '<table>';
        $html .= '<tr><th>Surname</th><td>' . htmlspecialchars($fmt($pi['surname'] ?? null)) . '</td></tr>';
        $html .= '<tr><th>First Name</th><td>' . htmlspecialchars($fmt($pi['first_name'] ?? null)) . '</td></tr>';
        $html .= '<tr><th>Middle Name</th><td>' . htmlspecialchars($fmt($pi['middle_name'] ?? null)) . '</td></tr>';
        $html .= '<tr><th>Suffix</th><td>' . htmlspecialchars($fmt($pi['suffix'] ?? null)) . '</td></tr>';
        $html .= '<tr><th>Date of Birth</th><td>' . htmlspecialchars($fmtDate($pi['date_of_birth'] ?? null)) . '</td></tr>';
        $html .= '<tr><th>Sex</th><td>' . htmlspecialchars(($pi['sex'] ?? '') === 'female' ? 'Female' : 'Male') . '</td></tr>';
        $html .= '<tr><th>Civil Status</th><td>' . htmlspecialchars($fmt($pi['civil_status'] ?? null)) . '</td></tr>';
        $html .= '<tr><th>Religion</th><td>' . htmlspecialchars($fmt($pi['religion'] ?? null)) . '</td></tr>';
        $html .= '<tr><th>TIN</th><td>' . htmlspecialchars($fmt($pi['tin'] ?? null)) . '</td></tr>';
        $html .= '<tr><th>Height (FT.)</th><td>' . htmlspecialchars($fmt($pi['height'] ?? null)) . '</td></tr>';
        $html .= '<tr><th>Disability</th><td>' . htmlspecialchars($fmt($pi['disability'] ?? null)) . '</td></tr>';
        $html .= '<tr><th>Contact Number/s</th><td>' . htmlspecialchars($fmt($pi['contact_number'] ?? null)) . '</td></tr>';
        $html .= '<tr><th>E-mail</th><td>' . htmlspecialchars($fmt($pi['email'] ?? null)) . '</td></tr>';
        $html .= '<tr><th>Address</th><td>' . htmlspecialchars($joinAddress($address)) . '</td></tr>';

        // Employment status paragraph
        $empStatus = (string) ($emp['status'] ?? 'N/A');
        if ($empStatus === 'unemployed') {
            $months = $fmt($empUnemployed['months_looking'] ?? null);
            $reasons = (array) ($empUnemployed['reasons'] ?? []);
            $reasons = array_values(array_filter($reasons, fn ($r) => !$isNA($r)));
            $reasonText = count($reasons) ? implode(', ', array_map('trim', $reasons)) : 'Not Applicable';
            $other = $fmt($empUnemployed['others_specify'] ?? null);
            $html .= '<tr><th>Employment Status</th><td>'
                . 'Unemployed. The applicant has been seeking employment for '
                . htmlspecialchars($months) . ' month(s). '
                . 'Reason(s): ' . htmlspecialchars($reasonText) . '. '
                . 'Others: ' . htmlspecialchars($other)
                . '</td></tr>';
        } else {
            $wage = $fmtBool($empEmployed['wage_employed'] ?? false);
            $self = $fmtBool($empEmployed['self_employed'] ?? false);
            $cats = (array) ($empEmployed['self_employed_categories'] ?? []);
            $cats = array_values(array_filter($cats, fn ($c) => !$isNA($c)));
            $catsText = count($cats) ? implode(', ', $cats) : 'Not Applicable';
            $other = $fmt($empEmployed['self_employed_other'] ?? null);
            $html .= '<tr><th>Employment Status</th><td>'
                . 'Employed. Wage employed: ' . htmlspecialchars($wage) . '. '
                . 'Self-employed: ' . htmlspecialchars($self) . '. '
                . 'Self-employed category: ' . htmlspecialchars($catsText) . '. '
                . 'Others: ' . htmlspecialchars($other)
                . '</td></tr>';
        }

        // OFW / 4Ps
        $html .= '<tr><th>Are you an OFW?</th><td>' . htmlspecialchars($fmtBool($ofw['is_ofw'] ?? false)) . '</td></tr>';
        $html .= '<tr><th>Specify Country</th><td>' . htmlspecialchars($fmt($ofw['country'] ?? null)) . '</td></tr>';
        $html .= '<tr><th>Are you a former OFW?</th><td>' . htmlspecialchars($fmtBool($ofw['former_ofw'] ?? false)) . '</td></tr>';
        $html .= '<tr><th>Latest Country of Deployment</th><td>' . htmlspecialchars($fmt($ofw['latest_country_of_deployment'] ?? null)) . '</td></tr>';
        $html .= '<tr><th>Month and Year of Return to Philippines</th><td>' . htmlspecialchars($fmt($ofw['return_month_year'] ?? null)) . '</td></tr>';
        $html .= '<tr><th>Are you a 4Ps beneficiary?</th><td>' . htmlspecialchars($fmtBool($fourPs['is_beneficiary'] ?? false)) . '</td></tr>';
        $html .= '<tr><th>Household ID No.</th><td>' . htmlspecialchars($fmt($fourPs['household_id'] ?? null)) . '</td></tr>';
        $html .= '</table>';

        // II. Job Preference
        $jp = (array) ($payload['job_preferences'] ?? []);
        $occ = (array) ($jp['preferred_occupations'] ?? []);
        $loc = (array) ($jp['preferred_locations'] ?? []);
        $workType = (array) ($jp['work_type'] ?? []);

        $html .= '<h2>II. Job Preference</h2>';
        $html .= '<div class="kv"><span class="label">Work Type:</span> ' . htmlspecialchars(count($workType) ? implode(', ', array_map('ucwords', $workType)) : 'Not Applicable') . '</div>';
        $html .= '<div class="kv"><span class="label">Preferred Occupations:</span></div><ul>';
        foreach ($occ as $v) {
            $html .= '<li>' . htmlspecialchars($fmt($v)) . '</li>';
        }
        $html .= '</ul>';
        $html .= '<div class="kv"><span class="label">Preferred Work Locations:</span></div><ul>';
        foreach ($loc as $v) {
            $html .= '<li>' . htmlspecialchars($fmt($v)) . '</li>';
        }
        $html .= '</ul>';

        // III. Language Proficiency
        $lp = (array) ($payload['language_proficiency'] ?? []);
        $langs = (array) ($lp['languages'] ?? []);
        $otherLabel = $fmt($lp['others_label'] ?? null);

        $html .= '<h2>III. Language Proficiency</h2>';
        $html .= '<table>';
        $html .= '<tr><th>Language</th><th>Read</th><th>Write</th><th>Speak</th><th>Understand</th></tr>';
        foreach (['english' => 'English', 'filipino' => 'Filipino', 'mandarin' => 'Mandarin', 'others' => ($otherLabel !== 'Not Applicable' ? $otherLabel : 'Others')] as $k => $label) {
            $row = (array) ($langs[$k] ?? []);
            $html .= '<tr>'
                . '<td>' . htmlspecialchars($label) . '</td>'
                . '<td>' . htmlspecialchars($fmtBool($row['read'] ?? false)) . '</td>'
                . '<td>' . htmlspecialchars($fmtBool($row['write'] ?? false)) . '</td>'
                . '<td>' . htmlspecialchars($fmtBool($row['speak'] ?? false)) . '</td>'
                . '<td>' . htmlspecialchars($fmtBool($row['understand'] ?? false)) . '</td>'
                . '</tr>';
        }
        $html .= '</table>';

        // IV. Educational Background
        $eb = (array) ($payload['educational_background'] ?? []);
        $html .= '<h2>IV. Educational Background</h2>';
        $html .= '<div class="kv"><span class="label">Currently in school?</span> ' . htmlspecialchars($fmtBool($eb['currently_in_school'] ?? false)) . '</div>';

        $eduRow = function (string $key) use ($eb): array {
            return (array) ($eb[$key] ?? []);
        };

        $eduSchool = function (array $row) use ($fmt): string {
            // Newer schema (if added later): school_attended. Fallback to existing "course" for basic education.
            return $fmt($row['school_attended'] ?? $row['course'] ?? null);
        };

        // Elementary Education (only show School Attended + Year Graduated)
        $elRow = $eduRow('elementary');
        $html .= '<h3>Elementary Education</h3>';
        $html .= '<div class="kv"><span class="label">School Attended:</span> ' . htmlspecialchars($eduSchool($elRow)) . '</div>';
        $html .= '<div class="kv"><span class="label">Year Graduated:</span> ' . htmlspecialchars($fmt($elRow['year_graduated'] ?? null)) . '</div>';

        // Secondary Education (High School) - applies to Non-K12 and K-12
        $secNon = $eduRow('secondary');
        $secK12 = $eduRow('secondary_k12');
        $secSchoolNon = $eduSchool($secNon);
        $secSchoolK12 = $eduSchool($secK12);
        $secYearNon = $fmt($secNon['year_graduated'] ?? null);
        $secYearK12 = $fmt($secK12['year_graduated'] ?? null);

        $html .= '<h3>Secondary Education (High School)</h3>';
        $html .= '<div class="small">(Applies to both Non-K12 and K-12)</div>';
        $secondarySchool = $secSchoolK12 !== 'Not Applicable' ? $secSchoolK12 : $secSchoolNon;
        if ($secSchoolNon !== 'Not Applicable' && $secSchoolK12 !== 'Not Applicable' && $secSchoolNon !== $secSchoolK12) {
            $secondarySchool = 'Non-K12: ' . $secSchoolNon . ' | K-12: ' . $secSchoolK12;
        }
        $secondaryYear = $secYearK12 !== 'Not Applicable' ? $secYearK12 : $secYearNon;
        if ($secYearNon !== 'Not Applicable' && $secYearK12 !== 'Not Applicable' && $secYearNon !== $secYearK12) {
            $secondaryYear = 'Non-K12: ' . $secYearNon . ' | K-12: ' . $secYearK12;
        }
        $html .= '<div class="kv"><span class="label">School Attended:</span> ' . htmlspecialchars($secondarySchool) . '</div>';
        $html .= '<div class="kv"><span class="label">Year Graduated:</span> ' . htmlspecialchars($secondaryYear) . '</div>';

        // Senior High School (Strand + School + Year Graduated only)
        $sh = $eduRow('senior_high');
        $strand = $fmt($sh['strand'] ?? $sh['course'] ?? null);
        $shSchool = $fmt($sh['school_attended'] ?? null);
        if ($shSchool === 'Not Applicable') {
            // Backward-compatible fallback: some users may have used "level_reached" for the school name.
            $shSchool = $fmt($sh['level_reached'] ?? null);
        }
        $html .= '<h3>Senior High School</h3>';
        $html .= '<div class="kv"><span class="label">Strand:</span> ' . htmlspecialchars($strand) . '</div>';
        $html .= '<div class="kv"><span class="label">School Attended:</span> ' . htmlspecialchars($shSchool) . '</div>';
        $html .= '<div class="kv"><span class="label">Year Graduated:</span> ' . htmlspecialchars($fmt($sh['year_graduated'] ?? null)) . '</div>';

        // Tertiary Education (conditional: if graduated show Year Graduated; else show Level Reached + Year Last Attended)
        $ter = $eduRow('tertiary');
        $terCourse = $fmt($ter['course'] ?? null);
        $terSchool = $fmt($ter['school_attended'] ?? null);
        $terYearGrad = $fmt($ter['year_graduated'] ?? null);
        $html .= '<h3>Tertiary Education</h3>';
        $html .= '<div class="kv"><span class="label">Course/Degree:</span> ' . htmlspecialchars($terCourse) . '</div>';
        $html .= '<div class="kv"><span class="label">School Attended:</span> ' . htmlspecialchars($terSchool) . '</div>';
        if ($terYearGrad !== 'Not Applicable') {
            $html .= '<div class="kv"><span class="label">Year Graduated:</span> ' . htmlspecialchars($terYearGrad) . '</div>';
        } else {
            $html .= '<div class="kv"><span class="label">Level Reached:</span> ' . htmlspecialchars($fmt($ter['level_reached'] ?? null)) . '</div>';
            $html .= '<div class="kv"><span class="label">Year Last Attended:</span> ' . htmlspecialchars($fmt($ter['year_last_attended'] ?? null)) . '</div>';
        }

        // Graduate Studies / Post-Graduate (same conditional rules as tertiary)
        $gr = $eduRow('graduate');
        $grCourse = $fmt($gr['course'] ?? null);
        $grSchool = $fmt($gr['school_attended'] ?? null);
        $grYearGrad = $fmt($gr['year_graduated'] ?? null);
        $html .= '<h3>Graduate Studies / Post-Graduate</h3>';
        $html .= '<div class="kv"><span class="label">Course/Degree:</span> ' . htmlspecialchars($grCourse) . '</div>';
        $html .= '<div class="kv"><span class="label">School Attended:</span> ' . htmlspecialchars($grSchool) . '</div>';
        if ($grYearGrad !== 'Not Applicable') {
            $html .= '<div class="kv"><span class="label">Year Graduated:</span> ' . htmlspecialchars($grYearGrad) . '</div>';
        } else {
            $html .= '<div class="kv"><span class="label">Level Reached:</span> ' . htmlspecialchars($fmt($gr['level_reached'] ?? null)) . '</div>';
            $html .= '<div class="kv"><span class="label">Year Last Attended:</span> ' . htmlspecialchars($fmt($gr['year_last_attended'] ?? null)) . '</div>';
        }

        // V. Technical/Vocational Training
        $tv = (array) ($payload['technical_vocational_training'] ?? []);
        $html .= '<h2>V. Technical/Vocational Training</h2>';
        $tvValid = [];
        foreach ($tv as $row) {
            $row = (array) $row;
            $has = $meaningful($row['course'] ?? null) || $meaningful($row['institution'] ?? null) || $meaningful($row['skills_acquired'] ?? null) || $meaningful($row['certificates_received'] ?? null) || $meaningful($row['hours'] ?? 0);
            if ($has) $tvValid[] = $row;
        }
        if (count($tvValid) === 0) {
            $html .= '<div class="small">Not Applicable</div>';
        } else {
            foreach ($tvValid as $i => $row) {
                $html .= '<h3>Training Entry ' . ($i + 1) . '</h3>';
                $html .= '<div class="kv"><span class="label">Course:</span> ' . htmlspecialchars($fmt($row['course'] ?? null)) . '</div>';
                $html .= '<div class="kv"><span class="label">Hours of Training:</span> ' . htmlspecialchars($fmt($row['hours'] ?? 0)) . '</div>';
                $html .= '<div class="kv"><span class="label">Training Institution:</span> ' . htmlspecialchars($fmt($row['institution'] ?? null)) . '</div>';
                $html .= '<div class="kv"><span class="label">Skills Acquired:</span> ' . htmlspecialchars($fmt($row['skills_acquired'] ?? null)) . '</div>';
                $html .= '<div class="kv"><span class="label">Certificates Received:</span> ' . htmlspecialchars($fmt($row['certificates_received'] ?? null)) . '</div>';
            }
        }

        // VI. Eligibility/Professional License
        $el = (array) ($payload['eligibility_license'] ?? []);
        $html .= '<h2>VI. Eligibility/Professional License</h2>';
        $elValid = [];
        foreach ($el as $row) {
            $row = (array) $row;
            $has = $meaningful($row['civil_service_eligibility'] ?? null) || $meaningful($row['civil_service_date_taken'] ?? null) || $meaningful($row['prc_license'] ?? null) || $meaningful($row['prc_validity'] ?? null);
            if ($has) $elValid[] = $row;
        }
        if (count($elValid) === 0) {
            $html .= '<div class="small">Not Applicable</div>';
        } else {
            foreach ($elValid as $i => $row) {
                $html .= '<h3>Entry ' . ($i + 1) . '</h3>';
                $html .= '<div class="kv"><span class="label">Eligibility (Civil Service):</span> ' . htmlspecialchars($fmt($row['civil_service_eligibility'] ?? null)) . '</div>';
                $html .= '<div class="kv"><span class="label">Date Taken:</span> ' . htmlspecialchars($fmt($row['civil_service_date_taken'] ?? null)) . '</div>';
                $html .= '<div class="kv"><span class="label">Professional License (PRC):</span> ' . htmlspecialchars($fmt($row['prc_license'] ?? null)) . '</div>';
                $html .= '<div class="kv"><span class="label">Valid Until:</span> ' . htmlspecialchars($fmt($row['prc_validity'] ?? null)) . '</div>';
            }
        }

        // VII. Work Experience
        $we = (array) ($payload['work_experience'] ?? []);
        $html .= '<h2>VII. Work Experience</h2>';
        $weValid = [];
        foreach ($we as $row) {
            $row = (array) $row;
            $has = $meaningful($row['company_name'] ?? null) || $meaningful($row['address'] ?? null) || $meaningful($row['position'] ?? null) || $meaningful($row['employment_status'] ?? null) || $meaningful($row['months_worked'] ?? 0);
            if ($has) $weValid[] = $row;
        }
        if (count($weValid) === 0) {
            $html .= '<div class="small">Not Applicable</div>';
        } else {
            foreach ($weValid as $i => $row) {
                $html .= '<h3>Employment ' . ($i + 1) . '</h3>';
                $html .= '<div class="kv"><span class="label">Company Name:</span> ' . htmlspecialchars($fmt($row['company_name'] ?? null)) . '</div>';
                $html .= '<div class="kv"><span class="label">Address:</span> ' . htmlspecialchars($fmt($row['address'] ?? null)) . '</div>';
                $html .= '<div class="kv"><span class="label">Position:</span> ' . htmlspecialchars($fmt($row['position'] ?? null)) . '</div>';
                $html .= '<div class="kv"><span class="label">Duration:</span> ' . htmlspecialchars($fmt($row['months_worked'] ?? 0)) . ' month(s)</div>';
                $html .= '<div class="kv"><span class="label">Status:</span> ' . htmlspecialchars($fmt($row['employment_status'] ?? null)) . '</div>';
            }
        }

        // VIII. Other Skills and Certification
        $os = (array) ($payload['other_skills'] ?? []);
        $cert = (array) ($payload['certification'] ?? []);
        $skills = (array) ($os['selected_skills'] ?? []);
        $skills = array_values(array_filter($skills, fn ($s) => !$isNA($s)));
        $othersSkill = $fmt($os['others'] ?? null);

        $html .= '<h2>VIII. Other Skills and Certification</h2>';
        $html .= '<h3>Other Skills Acquired Without Certificate</h3>';
        if (count($skills) === 0 && $othersSkill === 'Not Applicable') {
            $html .= '<div class="small">Not Applicable</div>';
        } else {
            if (count($skills)) {
                $html .= '<ul>';
                foreach ($skills as $s) {
                    $html .= '<li>' . htmlspecialchars($s) . '</li>';
                }
                $html .= '</ul>';
            }
            if ($othersSkill !== 'Not Applicable') {
                $html .= '<div class="kv"><span class="label">Others:</span> ' . htmlspecialchars($othersSkill) . '</div>';
            }
        }

        $typedName = $fmt($cert['typed_name'] ?? null);
        $certDate = $fmtDate($cert['date'] ?? null);
        $html .= '<h3>Certification</h3>';
        $html .= '<div class="kv">I hereby certify that the information provided above is true and correct to the best of my knowledge.</div>';
        $html .= '<div class="kv"><span class="label">Name:</span> ' . htmlspecialchars($typedName) . '</div>';
        $html .= '<div class="kv"><span class="label">Date:</span> ' . htmlspecialchars($certDate) . '</div>';

        $html .= '</body></html>';

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'nsrp_form_' . $target->id . '.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    private function canViewUserNsrp(Request $request, User $target): bool
    {
        $viewer = $request->user();
        if (!$viewer) {
            return false;
        }
        if ($viewer->isAdmin()) {
            return true;
        }
        if ($viewer->isSupervisor()) {
            return Intern::where('user_id', $target->id)
                ->where('supervisor_user_id', $viewer->id)
                ->exists();
        }
        if ($viewer->isInternOrGip()) {
            return (int) $viewer->id === (int) $target->id;
        }

        return false;
    }

    private function validateSubmitPayload(array $payload): void
    {
        validator($payload, [
            'personal_information.surname' => ['required', 'string', 'max:255'],
            'personal_information.first_name' => ['required', 'string', 'max:255'],
            'personal_information.middle_name' => ['required', 'string', 'max:255'],
            'personal_information.date_of_birth' => ['required', 'date'],
            'personal_information.sex' => ['required', 'in:male,female'],
            'personal_information.civil_status' => ['required', 'string', 'max:100'],
            'personal_information.contact_number' => ['required', 'string', 'max:50'],
            'personal_information.email' => ['required', 'email', 'max:255'],
            'personal_information.address.house_no' => ['required', 'string', 'max:255'],
            'personal_information.address.barangay' => ['required', 'string', 'max:255'],
            'personal_information.address.city' => ['required', 'string', 'max:255'],
            'personal_information.address.province' => ['required', 'string', 'max:255'],
            'personal_information.employment_status.status' => ['required', 'in:employed,unemployed'],
            'personal_information.employment_status.employed_details.wage_employed' => ['required', 'boolean'],
            'personal_information.employment_status.employed_details.self_employed' => ['required', 'boolean'],
            'personal_information.employment_status.employed_details.self_employed_categories' => ['array'],
            'personal_information.employment_status.employed_details.self_employed_other' => ['required', 'string', 'max:255'],
            'personal_information.employment_status.unemployed_details.months_looking' => [
                'required_if:personal_information.employment_status.status,unemployed',
                'string',
                'max:50',
            ],
            'personal_information.employment_status.unemployed_details.reasons' => ['array'],
            'personal_information.employment_status.unemployed_details.others_specify' => ['required', 'string', 'max:255'],
            'personal_information.ofw.is_ofw' => ['required', 'boolean'],
            'personal_information.ofw.former_ofw' => ['required', 'boolean'],
            'personal_information.ofw.country' => ['required', 'string', 'max:255'],
            'personal_information.ofw.country_of_destination' => ['required', 'string', 'max:255'],
            'personal_information.ofw.latest_country_of_deployment' => ['required', 'string', 'max:255'],
            'personal_information.ofw.return_month_year' => ['required', 'string', 'max:255'],
            'personal_information.four_ps.is_beneficiary' => ['required', 'boolean'],
            'personal_information.four_ps.household_id' => ['required', 'string', 'max:255'],

            'job_preferences.preferred_occupations' => [
                'required',
                'array',
                'min:1',
                'max:3',
            ],
            'job_preferences.preferred_occupations.*' => ['required', 'string', 'max:255'],
            'job_preferences.preferred_locations' => [
                'required',
                'array',
                'min:1',
                'max:3',
            ],
            'job_preferences.preferred_locations.*' => ['required', 'string', 'max:255'],
            'job_preferences.work_type' => ['required', 'array', 'min:1'],

            'language_proficiency.languages' => ['required', 'array'],
            'language_proficiency.languages.english.read' => ['required', 'boolean'],
            'language_proficiency.languages.english.write' => ['required', 'boolean'],
            'language_proficiency.languages.english.speak' => ['required', 'boolean'],
            'language_proficiency.languages.english.understand' => ['required', 'boolean'],
            'language_proficiency.languages.filipino.read' => ['required', 'boolean'],
            'language_proficiency.languages.filipino.write' => ['required', 'boolean'],
            'language_proficiency.languages.filipino.speak' => ['required', 'boolean'],
            'language_proficiency.languages.filipino.understand' => ['required', 'boolean'],
            'language_proficiency.languages.mandarin.read' => ['required', 'boolean'],
            'language_proficiency.languages.mandarin.write' => ['required', 'boolean'],
            'language_proficiency.languages.mandarin.speak' => ['required', 'boolean'],
            'language_proficiency.languages.mandarin.understand' => ['required', 'boolean'],
            'language_proficiency.languages.others.read' => ['required', 'boolean'],
            'language_proficiency.languages.others.write' => ['required', 'boolean'],
            'language_proficiency.languages.others.speak' => ['required', 'boolean'],
            'language_proficiency.languages.others.understand' => ['required', 'boolean'],

            'educational_background' => ['required', 'array'],
            'educational_background.currently_in_school' => ['required', 'boolean'],
            'educational_background.elementary.school_attended' => ['required', 'string', 'max:255'],
            'educational_background.elementary.year_graduated' => ['required', 'string', 'max:255'],
            'educational_background.secondary.school_attended' => ['required', 'string', 'max:255'],
            'educational_background.secondary.year_graduated' => ['required', 'string', 'max:255'],
            'educational_background.secondary_k12.school_attended' => ['required', 'string', 'max:255'],
            'educational_background.secondary_k12.year_graduated' => ['required', 'string', 'max:255'],
            'educational_background.senior_high.strand' => ['required', 'string', 'max:255'],
            'educational_background.senior_high.school_attended' => ['required', 'string', 'max:255'],
            'educational_background.senior_high.year_graduated' => ['required', 'string', 'max:255'],
            'educational_background.tertiary.course' => ['required', 'string', 'max:255'],
            'educational_background.tertiary.school_attended' => ['required', 'string', 'max:255'],
            'educational_background.tertiary.year_graduated' => ['required', 'string', 'max:255'],
            'educational_background.tertiary.level_reached' => ['required', 'string', 'max:255'],
            'educational_background.tertiary.year_last_attended' => ['required', 'string', 'max:255'],
            'educational_background.graduate.course' => ['required', 'string', 'max:255'],
            'educational_background.graduate.school_attended' => ['required', 'string', 'max:255'],
            'educational_background.graduate.year_graduated' => ['required', 'string', 'max:255'],
            'educational_background.graduate.level_reached' => ['required', 'string', 'max:255'],
            'educational_background.graduate.year_last_attended' => ['required', 'string', 'max:255'],

            'technical_vocational_training' => ['required', 'array', 'min:1'],
            'technical_vocational_training.*.course' => ['required', 'string', 'max:255'],
            'technical_vocational_training.*.hours' => ['required', 'numeric', 'min:0'],
            'technical_vocational_training.*.institution' => ['required', 'string', 'max:255'],
            'technical_vocational_training.*.skills_acquired' => ['required', 'string'],
            'technical_vocational_training.*.certificates_received' => ['required', 'string'],

            'eligibility_license' => ['required', 'array', 'min:2'],
            'eligibility_license.*.civil_service_eligibility' => ['required', 'string', 'max:255'],
            'eligibility_license.*.civil_service_date_taken' => ['required', 'string', 'max:255'],
            'eligibility_license.*.prc_license' => ['required', 'string', 'max:255'],
            'eligibility_license.*.prc_validity' => ['required', 'string', 'max:255'],

            'work_experience' => ['required', 'array', 'min:1'],
            'work_experience.*.company_name' => ['required', 'string', 'max:255'],
            'work_experience.*.address' => ['required', 'string', 'max:255'],
            'work_experience.*.position' => ['required', 'string', 'max:255'],
            'work_experience.*.months_worked' => ['required', 'numeric', 'min:0'],
            'work_experience.*.employment_status' => ['required', 'string', 'max:100'],

            'other_skills.selected_skills' => ['required', 'array'],
            'other_skills.others' => ['required', 'string'],
            'certification.certify_true' => ['accepted'],
            'certification.typed_name' => ['required', 'string', 'max:255'],
            'certification.date' => ['required', 'date'],
        ], [
            'certification.certify_true.accepted' => 'Certification is required before submission.',
        ])->validate();
    }

    private function formatForm(NsrpForm $form): array
    {
        return [
            'id' => $form->id,
            'user_id' => $form->user_id,
            'personal_information' => $form->personal_information,
            'job_preferences' => $form->job_preferences,
            'language_proficiency' => $form->language_proficiency,
            'educational_background' => $form->educational_background,
            'technical_vocational_training' => $form->technical_vocational_training,
            'eligibility_license' => $form->eligibility_license,
            'work_experience' => $form->work_experience,
            'other_skills' => $form->other_skills,
            'certification' => $form->certification,
            'is_completed' => (bool) $form->is_completed,
            'submitted_at' => optional($form->submitted_at)->toISOString(),
            'updated_at' => optional($form->updated_at)->toISOString(),
        ];
    }

    private function mergeFormWithDefaults(NsrpForm $form, User $user): array
    {
        $defaults = $this->defaultPayloadForUser($user);
        $formatted = $this->formatForm($form);

        $eligibility = $this->ensureMinRows(
            $this->normalizeArrayOfRows($formatted['eligibility_license'] ?? [], $defaults['eligibility_license'][0]),
            2,
            $defaults['eligibility_license'][0]
        );

        return [
            'id' => $form->id,
            'user_id' => $form->user_id,
            'personal_information' => $this->mergeRecursive($defaults['personal_information'], (array) ($formatted['personal_information'] ?? [])),
            'job_preferences' => $this->mergeRecursive($defaults['job_preferences'], (array) ($formatted['job_preferences'] ?? [])),
            'language_proficiency' => $this->mergeRecursive($defaults['language_proficiency'], (array) ($formatted['language_proficiency'] ?? [])),
            'educational_background' => $this->mergeRecursive($defaults['educational_background'], (array) ($formatted['educational_background'] ?? [])),
            'technical_vocational_training' => $this->normalizeArrayOfRows(
                $formatted['technical_vocational_training'] ?? [],
                $defaults['technical_vocational_training'][0]
            ),
            'eligibility_license' => $eligibility,
            'work_experience' => $this->normalizeArrayOfRows(
                $formatted['work_experience'] ?? [],
                $defaults['work_experience'][0]
            ),
            'other_skills' => $this->mergeRecursive($defaults['other_skills'], (array) ($formatted['other_skills'] ?? [])),
            'certification' => $this->mergeRecursive($defaults['certification'], (array) ($formatted['certification'] ?? [])),
            'is_completed' => (bool) $form->is_completed,
            'submitted_at' => optional($form->submitted_at)->toISOString(),
            'updated_at' => optional($form->updated_at)->toISOString(),
        ];
    }

    private function defaultPayloadForUser(User $user): array
    {
        $intern = Intern::where('user_id', $user->id)->first();

        return [
            'id' => null,
            'user_id' => $user->id,
            'personal_information' => [
                'surname' => $this->extractSurname($intern?->full_name ?? $user->name ?? 'N/A'),
                'first_name' => $this->extractFirstName($intern?->full_name ?? $user->name ?? 'N/A'),
                'middle_name' => 'N/A',
                'suffix' => 'N/A',
                'date_of_birth' => null,
                'sex' => 'male',
                'civil_status' => 'N/A',
                'religion' => 'N/A',
                'tin' => 'N/A',
                'height' => 'N/A',
                'disability' => 'N/A',
                'contact_number' => $intern?->phone ?? 'N/A',
                'email' => $user->email ?? 'N/A',
                'address' => [
                    'house_no' => 'N/A',
                    'barangay' => 'N/A',
                    'city' => 'N/A',
                    'province' => 'N/A',
                ],
                'employment_status' => [
                    'status' => 'unemployed',
                    'employed_details' => [
                        'wage_employed' => false,
                        'self_employed' => false,
                        'self_employed_categories' => [],
                        'self_employed_other' => 'N/A',
                    ],
                    'unemployed_details' => [
                        'months_looking' => '',
                        'reasons' => [],
                        'others_specify' => 'N/A',
                    ],
                ],
                'ofw' => [
                    'is_ofw' => false,
                    'country' => 'N/A',
                    'country_of_destination' => 'N/A',
                    'former_ofw' => false,
                    'latest_country_of_deployment' => 'N/A',
                    'return_month_year' => 'N/A',
                ],
                'four_ps' => [
                    'is_beneficiary' => false,
                    'household_id' => 'N/A',
                ],
            ],
            'job_preferences' => [
                'preferred_occupations' => ['', '', ''],
                'preferred_locations' => ['', '', ''],
                'work_type' => ['local'],
            ],
            'language_proficiency' => [
                'others_label' => 'N/A',
                'languages' => [
                    'english' => ['read' => false, 'write' => false, 'speak' => false, 'understand' => false],
                    'filipino' => ['read' => false, 'write' => false, 'speak' => false, 'understand' => false],
                    'mandarin' => ['read' => false, 'write' => false, 'speak' => false, 'understand' => false],
                    'others' => ['read' => false, 'write' => false, 'speak' => false, 'understand' => false],
                ],
            ],
            'educational_background' => [
                'currently_in_school' => false,
                'elementary' => ['school_attended' => '', 'year_graduated' => ''],
                'secondary' => ['school_attended' => '', 'year_graduated' => ''],
                'secondary_k12' => ['school_attended' => '', 'year_graduated' => ''],
                'senior_high' => ['strand' => '', 'school_attended' => '', 'year_graduated' => ''],
                'tertiary' => [
                    'course' => $intern?->course ?? '',
                    'school_attended' => '',
                    'year_graduated' => '',
                    'level_reached' => '',
                    'year_last_attended' => '',
                ],
                'graduate' => [
                    'course' => '',
                    'school_attended' => '',
                    'year_graduated' => '',
                    'level_reached' => '',
                    'year_last_attended' => '',
                ],
            ],
            'technical_vocational_training' => [
                ['course' => 'N/A', 'hours' => 0, 'institution' => 'N/A', 'skills_acquired' => 'N/A', 'certificates_received' => 'N/A'],
            ],
            'eligibility_license' => [
                ['civil_service_eligibility' => '', 'civil_service_date_taken' => '', 'prc_license' => '', 'prc_validity' => ''],
                ['civil_service_eligibility' => '', 'civil_service_date_taken' => '', 'prc_license' => '', 'prc_validity' => ''],
            ],
            'work_experience' => [
                ['company_name' => 'N/A', 'address' => 'N/A', 'position' => 'N/A', 'months_worked' => 0, 'employment_status' => 'N/A'],
            ],
            'other_skills' => [
                'selected_skills' => [],
                'others' => 'N/A',
            ],
            'certification' => [
                'certify_true' => false,
                'typed_name' => $intern?->full_name ?? $user->name ?? 'N/A',
                'date' => now()->toDateString(),
            ],
            'is_completed' => false,
            'submitted_at' => null,
            'updated_at' => null,
        ];
    }

    private function normalizePayload(array $input, User $user): array
    {
        $defaults = $this->defaultPayloadForUser($user);
        $eligibility = $this->ensureMinRows(
            $this->normalizeArrayOfRows($input['eligibility_license'] ?? [], $defaults['eligibility_license'][0]),
            2,
            $defaults['eligibility_license'][0]
        );

        return [
            'personal_information' => $this->mergeRecursive($defaults['personal_information'], (array) ($input['personal_information'] ?? [])),
            'job_preferences' => $this->mergeRecursive($defaults['job_preferences'], (array) ($input['job_preferences'] ?? [])),
            'language_proficiency' => $this->mergeRecursive($defaults['language_proficiency'], (array) ($input['language_proficiency'] ?? [])),
            'educational_background' => $this->mergeRecursive($defaults['educational_background'], (array) ($input['educational_background'] ?? [])),
            'technical_vocational_training' => $this->normalizeArrayOfRows(
                $input['technical_vocational_training'] ?? [],
                $defaults['technical_vocational_training'][0]
            ),
            'eligibility_license' => $eligibility,
            'work_experience' => $this->normalizeArrayOfRows(
                $input['work_experience'] ?? [],
                $defaults['work_experience'][0]
            ),
            'other_skills' => $this->mergeRecursive($defaults['other_skills'], (array) ($input['other_skills'] ?? [])),
            'certification' => $this->mergeRecursive($defaults['certification'], (array) ($input['certification'] ?? [])),
        ];
    }

    private function ensureMinRows(array $rows, int $min, array $rowDefaults): array
    {
        while (count($rows) < $min) {
            $rows[] = $rowDefaults;
        }

        return $rows;
    }

    private function normalizeArrayOfRows(mixed $rows, array $rowDefaults): array
    {
        if (!is_array($rows) || count($rows) === 0) {
            return [$rowDefaults];
        }

        $normalized = [];
        foreach ($rows as $row) {
            $normalized[] = $this->mergeRecursive($rowDefaults, (array) $row);
        }

        return $normalized;
    }

    private function mergeRecursive(array $defaults, array $overrides): array
    {
        $merged = $defaults;
        foreach ($overrides as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = $this->mergeRecursive($merged[$key], $value);
                continue;
            }
            $merged[$key] = $value;
        }

        return $merged;
    }

    private function extractSurname(string $fullName): string
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];
        if (count($parts) <= 1) {
            return 'N/A';
        }
        return end($parts) ?: 'N/A';
    }

    private function extractFirstName(string $fullName): string
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];
        return $parts[0] ?? 'N/A';
    }
}
