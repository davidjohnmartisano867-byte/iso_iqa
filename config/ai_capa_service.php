<?php
function generate_fast_ai_capa(array $finding): array {
    $finding_type = trim($finding['finding_type'] ?? 'Minor Nonconformity');
    $findings_text = trim($finding['findings'] ?? '');
    $root_cause_input = trim($finding['root_cause'] ?? '');
    
    $cache_key = 'ai_capa_' . md5($finding_type . '|' . $findings_text . '|' . $root_cause_input);
    if (!empty($_SESSION[$cache_key]) && is_array($_SESSION[$cache_key])) {
        return $_SESSION[$cache_key];
    }

    $api_key = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? '');
    if (!$api_key) {
        $env_file = __DIR__ . '/../.env';
        if (file_exists($env_file)) {
            $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), 'GEMINI_API_KEY=') === 0) {
                    $api_key = trim(substr(trim($line), strlen('GEMINI_API_KEY=')));
                    break;
                }
            }
        }
    }

    if (!empty($api_key)) {
        if (!empty($root_cause_input)) {
            $prompt = "You are an ISO 9001:2015 Lead Auditor and QMS Consultant.
Audit Finding:
- Finding Type: {$finding_type}
- Finding Description: {$findings_text}
- Selected/Entered Root Cause: {$root_cause_input}

TASK:
1. Generate 3 to 4 distinct, highly actionable Corrective & Preventive Action (CAPA) plans that DIRECTLY TARGET, RESOLVE, AND ELIMINATE the specified root cause (\"{$root_cause_input}\") and the audit finding.
2. Generate 3 to 4 refined/alternative root causes explaining why the breakdown occurred.

Respond strictly in raw JSON schema:
{\"root_causes\":[\"string\",\"string\",\"string\"],\"corrective_actions\":[\"string\",\"string\",\"string\"]}";
        } else {
            $prompt = "You are an ISO 9001:2015 Lead Auditor and QMS Consultant.
Audit Finding:
- Finding Type: {$finding_type}
- Finding Description: {$findings_text}

TASK:
1. Generate 3 distinct, professional root causes analyzing the breakdown.
2. Generate 3 actionable Corrective & Preventive Action (CAPA) plans directly resolving the finding and root causes.

Respond strictly in raw JSON schema:
{\"root_causes\":[\"string\",\"string\",\"string\"],\"corrective_actions\":[\"string\",\"string\",\"string\"]}";
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . urlencode($api_key);

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 500,
                'responseMimeType' => 'application/json',
                'thinkingConfig' => [
                    'thinkingBudget' => 0
                ]
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200 && $response) {
            $json = json_decode($response, true);
            $raw_text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
            if ($raw_text) {
                $res_data = json_decode($raw_text, true);
                if (is_array($res_data) && (!empty($res_data['root_causes']) || !empty($res_data['corrective_actions']))) {
                    $root_causes = $res_data['root_causes'] ?? [];
                    $corrective_actions = $res_data['corrective_actions'] ?? [];

                    $result = [
                        'root_causes' => is_array($root_causes) ? array_values(array_filter($root_causes)) : [],
                        'root_cause' => $root_causes[0] ?? '',
                        'corrective_actions' => is_array($corrective_actions) ? array_values(array_filter($corrective_actions)) : [],
                        'corrective_action' => $corrective_actions[0] ?? ''
                    ];

                    if (!empty($result['corrective_actions'])) {
                        $_SESSION[$cache_key] = $result;
                        return $result;
                    }
                }
            }
        }
    }

    $fallback = get_iso_contextual_capa($finding_type, $findings_text, $root_cause_input);
    $_SESSION[$cache_key] = $fallback;
    return $fallback;
}

function get_iso_contextual_capa(string $type, string $text, string $root_input = ''): array {
    $combined = strtolower($text . ' ' . $root_input);
    $root_lower = strtolower($root_input);

    if (!empty($root_lower)) {
        if (strpos($root_lower, 'train') !== false || strpos($root_lower, 'competen') !== false || strpos($root_lower, 'aware') !== false || strpos($root_lower, 'onboard') !== false) {
            $actions = [
                "Conduct immediate competency-based training for all responsible staff on the correct procedures and document signed training evaluation records.",
                "Establish a departmental Training & Competency Matrix with scheduled annual refresher workshops and new personnel onboarding checklists.",
                "Implement a supervised verification period before newly trained staff execute quality-critical tasks independently."
            ];
        } elseif (strpos($root_lower, 'sign') !== false || strpos($root_lower, 'approv') !== false || strpos($root_lower, 'authoriz') !== false) {
            $actions = [
                "Audit all recent records to secure missing approvals, establish a digital sign-off routing protocol, and verify complete authorization on 100% of archived documents.",
                "Implement a mandatory pre-release approval checklist preventing documents from being finalized without verified designated reviewer signatures.",
                "Update the Document Control Matrix with explicit primary and alternate authorized signatories and issue orientation notices to all approvers."
            ];
        } elseif (strpos($root_lower, 'document') !== false || strpos($root_lower, 'record') !== false || strpos($root_lower, 'evidence') !== false || strpos($root_lower, 'storage') !== false || strpos($root_lower, 'custodian') !== false) {
            $actions = [
                "Conduct an immediate evidence inventory to locate and catalog missing records into a secure, centralized ISO-structured digital repository.",
                "Establish standard naming, versioning, and metadata tagging guidelines for all departmental documents with mandatory custodian sign-off.",
                "Designate certified Document Custodians for each section responsible for quarterly record lifecycle and retention compliance spot checks."
            ];
        } elseif (strpos($root_lower, 'calibrat') !== false || strpos($root_lower, 'equip') !== false || strpos($root_lower, 'mainten') !== false || strpos($root_lower, 'tool') !== false) {
            $actions = [
                "Immediately tag, inspect, and recalibrate all affected measurement equipment against traceable national standards before further operational use.",
                "Deploy a centralized preventive maintenance and calibration tracking register with automated alerts 30 days prior to expiration.",
                "Conduct quarterly spot-audits on all active measurement devices to ensure valid calibration stickers and maintenance logs are verified."
            ];
        } elseif (strpos($root_lower, 'sop') !== false || strpos($root_lower, 'procedure') !== false || strpos($root_lower, 'process') !== false || strpos($root_lower, 'flow') !== false) {
            $actions = [
                "Establish a cross-functional task force to document active workflows, update standard operating procedures (SOP), and issue controlled revisions.",
                "Implement structured process-mapping guidelines with explicit handoff checklists and verification gates between operational stages.",
                "Introduce a mandatory document and workflow revision schedule requiring formal management sign-off before operational adjustments."
            ];
        } else {
            $actions = [
                "Execute direct containment to remediate the identified root cause (" . htmlspecialchars($root_input) . ") and update associated operational workflows.",
                "Establish a monthly operational compliance audit schedule with designated checklists to inspect compliance and prevent recurrence.",
                "Implement a centralized Corrective and Preventive Action (CAPA) tracking log to monitor milestone completion and effectiveness reviews."
            ];
        }
    } else {
        if (strpos($combined, 'sign') !== false || strpos($combined, 'approv') !== false || strpos($combined, 'authoriz') !== false) {
            $actions = [
                "Audit all recent records to secure missing approvals, establish a digital sign-off routing protocol, and verify complete authorization on 100% of archived documents.",
                "Implement a mandatory pre-release approval checklist preventing documents from being finalized without verified designated reviewer signatures.",
                "Update the departmental Document Control Matrix with explicit sign-off authorities and conduct mandatory orientation for all approvers."
            ];
        } elseif (strpos($combined, 'document') !== false || strpos($combined, 'record') !== false || strpos($combined, 'evidence') !== false || strpos($combined, 'file') !== false) {
            $actions = [
                "Conduct an immediate evidence inventory to locate and catalog missing records into a secure, centralized ISO-structured digital repository.",
                "Establish standard naming, versioning, and metadata tagging guidelines for all departmental documents with mandatory custodian sign-off.",
                "Designate certified Document Custodians for each section responsible for quarterly record lifecycle and retention compliance spot checks."
            ];
        } elseif (strpos($combined, 'train') !== false || strpos($combined, 'competen') !== false || strpos($combined, 'aware') !== false || strpos($combined, 'staff') !== false) {
            $actions = [
                "Conduct immediate training sessions on relevant standard operating procedures and maintain verified competency assessment records.",
                "Establish an annual training matrix mapping required competencies for each operational role with scheduled refresher courses.",
                "Implement periodic quality awareness briefings and include ISO compliance review in monthly departmental meetings."
            ];
        } elseif (strpos($combined, 'calibrat') !== false || strpos($combined, 'equip') !== false || strpos($combined, 'mainten') !== false) {
            $actions = [
                "Immediately tag and recalibrate all affected equipment against traceable national standards before further operational use.",
                "Deploy a centralized preventive maintenance and calibration schedule with automated alerts 30 days prior to expiration.",
                "Conduct quarterly spot-audits on all active measurement devices to ensure valid calibration stickers and logs are maintained."
            ];
        } else {
            $actions = [
                "Perform a comprehensive review of affected operational areas, update relevant standard operating procedures, and conduct re-training for personnel.",
                "Establish a monthly operational compliance audit schedule with designated checklists to identify and remediate deviations early.",
                "Implement a centralized Corrective and Preventive Action (CAPA) tracking dashboard to monitor milestone completion and effectiveness reviews."
            ];
        }
    }

    if (strpos($combined, 'sign') !== false || strpos($combined, 'approv') !== false) {
        $root_causes = [
            "Absence of an automated approval routing workflow and pending signature reminder system.",
            "Incomplete pre-archiving document sign-off verification protocols across departmental operational units.",
            "Lack of clearly designated primary and alternate approval authorities defined in the Document Control Matrix."
        ];
    } elseif (strpos($combined, 'document') !== false || strpos($combined, 'record') !== false) {
        $root_causes = [
            "Decentralized document storage practices leading to gaps in traceability and record retention management.",
            "Absence of standardized document archiving and cataloging protocols structured by ISO clauses.",
            "Lack of designated trained document custodians responsible for record lifecycle and archiving controls."
        ];
    } elseif (strpos($combined, 'train') !== false || strpos($combined, 'competen') !== false) {
        $root_causes = [
            "Inadequate staff onboarding and lack of structured role-specific procedure training across operational workflows.",
            "Absence of formal training evaluation and competency assessment records for personnel performing quality-critical tasks.",
            "Insufficient communication of QMS objectives and ISO 9001:2015 standard awareness among department members."
        ];
    } else {
        $root_causes = [
            "Absence of formalized standard operating procedure documentation and lack of structured process-mapping guidelines.",
            "Insufficient operational compliance monitoring and lack of standardized periodic review cycles.",
            "Inadequate verification spot-checks and absence of centralized tracking for nonconformity resolutions."
        ];
    }

    return [
        'root_causes' => $root_causes,
        'root_cause' => $root_causes[0],
        'corrective_actions' => $actions,
        'corrective_action' => $actions[0]
    ];
}
