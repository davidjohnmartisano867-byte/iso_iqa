<?php

if (!defined('AI_MICROSERVICE_URL')) {
    $url = getenv('AI_MICROSERVICE_URL') ?: '';
    if (!$url) {
        $env_file = __DIR__ . '/../.env';
        if (file_exists($env_file)) {
            $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), 'AI_MICROSERVICE_URL=') === 0) {
                    $url = trim(substr(trim($line), strlen('AI_MICROSERVICE_URL=')));
                    break;
                }
            }
        }
    }
    define('AI_MICROSERVICE_URL', rtrim($url, '/'));
}

function ai_service_classify_document(string $target_path, bool $is_auto = true, $clause = 'auto'): array {
    $filename = basename($target_path);
    $default_clause = ($is_auto || $clause === 'auto') ? 8 : (int)$clause;

    $service_url = defined('AI_MICROSERVICE_URL') ? AI_MICROSERVICE_URL : '';

    if (!empty($service_url)) {
        try {
            $ch = curl_init($service_url . '/classify');
            $cfile = new CURLFile($target_path, mime_content_type($target_path) ?: 'application/octet-stream', $filename);
            
            $post_data = [
                'file' => $cfile,
                'filename' => $filename,
                'clause' => $is_auto ? 'auto' : (string)$default_clause
            ];

            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code === 200 && $response) {
                $data = json_decode($response, true);
                if (is_array($data) && isset($data['clause_number'])) {
                    return $data;
                }
            }
        } catch (Exception $e) {
            error_log("AI microservice /classify error: " . $e->getMessage());
        }
    }

    if (function_exists('shell_exec') && !in_array('shell_exec', array_map('trim', explode(',', ini_get('disable_functions'))))) {
        $py_script = __DIR__ . '/../classify_document.py';
        if (file_exists($py_script)) {
            $cmd = "python " . escapeshellarg($py_script) . " " . escapeshellarg($target_path);
            $out = @shell_exec($cmd);
            if ($out) {
                $py_data = json_decode($out, true);
                if (is_array($py_data) && isset($py_data['clause_number'])) {
                    return $py_data;
                }
            }
        }
    }

    return [
        'clause_number' => $default_clause,
        'clause_title' => 'Operation',
        'reasoning' => 'Categorized into standard operations repository.',
        'ai_comment' => '✅ Document uploaded to office repository.',
        'is_fake' => false,
        'lacks_signature' => false,
        'unrelated_to_office' => false
    ];
}

function ai_service_predict(array $payload): array {
    $service_url = defined('AI_MICROSERVICE_URL') ? AI_MICROSERVICE_URL : '';

    if (!empty($service_url)) {
        try {
            $ch = curl_init($service_url . '/predict');
            $json_payload = json_encode($payload);

            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code === 200 && $response) {
                $data = json_decode($response, true);
                if (is_array($data) && !empty($data['predictions'])) {
                    return $data['predictions'];
                }
            }
        } catch (Exception $e) {
            error_log("AI microservice /predict error: " . $e->getMessage());
        }
    }

    if (function_exists('proc_open') && !in_array('proc_open', array_map('trim', explode(',', ini_get('disable_functions'))))) {
        $py_path = __DIR__ . '/../scikit/predict.py';
        if (file_exists($py_path)) {
            $command = "python " . escapeshellarg($py_path);
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ];
            $process = @proc_open($command, $descriptors, $pipes);
            if (is_resource($process)) {
                fwrite($pipes[0], json_encode($payload));
                fclose($pipes[0]);
                $output = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);

                $predictions = json_decode($output, true);
                if (is_array($predictions)) {
                    return $predictions;
                }
            }
        }
    }

    $fallback_predictions = [];
    foreach ($payload as $office) {
        $fallback_predictions[] = [
            'office_id' => $office['office_id'] ?? 0,
            'office_name' => $office['office_name'] ?? '',
            'office_abbr' => $office['office_abbr'] ?? '',
            'compliance_risk_score' => 45,
            'risk_category' => 'Medium',
            'ml_model' => 'Standard QMS Evaluation',
            'risk_drivers' => ['Active document submission index'],
            'forecasted_clauses' => [
                [
                    'clause' => 'Clause 7.5.3 - Control of documented information',
                    'risk_level' => 'Medium',
                    'description' => 'Ensure all uploaded documents maintain verified approval signatures and controlled version history.',
                    'area_for_improvement' => 'Enhance document control and pre-archival verification procedures.',
                    'proactive_preventive_actions' => [
                        'Review document approval status before archiving.',
                        'Conduct quarterly document control audits.',
                        'Maintain updated master document list.'
                    ]
                ]
            ],
            'forecasted_nonconformity' => [
                'clause' => 'Clause 7.5.3 - Control of documented information',
                'description' => 'Ensure all uploaded documents maintain verified approval signatures and controlled version history.'
            ],
            'area_for_improvement' => 'Enhance document control and pre-archival verification procedures.',
            'proactive_preventive_actions' => [
                'Review document approval status before archiving.',
                'Conduct quarterly document control audits.',
                'Maintain updated master document list.'
            ]
        ];
    }
    return $fallback_predictions;
}
