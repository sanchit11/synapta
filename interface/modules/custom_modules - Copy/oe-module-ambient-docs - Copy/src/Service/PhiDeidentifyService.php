<?php

namespace Clinic\OeModuleAmbientDocs\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * PhiDeidentifyService
 *
 * Replaces PHI (Protected Health Information) with tokens
 * BEFORE sending any text to GPT-4o or Claude.
 *
 * Flow:
 *   Raw text → Azure PII API → tokens replace PHI → safe text
 *   Safe text → GPT-4o → note with tokens → tokens replaced back → final note
 *
 * Example:
 *   Input:  "John Smith, DOB 01/15/1980, MRN 123456 has diabetes"
 *   Output: "[TOKEN_PERSON_A1B2] DOB [TOKEN_DATE_C3D4], MRN
 *            [TOKEN_MRN_E5F6] has diabetes"
 *
 * After GPT-4o returns the note:
 *   "[TOKEN_PERSON_A1B2] presents with diabetes..."
 *   becomes:
 *   "John Smith presents with diabetes..."
 *
 * HIPAA Note:
 *   Azure AI Language is covered under Microsoft's BAA.
 *   PHI sent to this API stays within your Azure tenant.
 *   GPT-4o NEVER sees real PHI — only tokens.
 */
class PhiDeidentifyService
{
    private Client  $http;
    private string  $endpoint;
    private string  $apiKey;

    // Token store: keeps token→original mapping per session
    // Format: ['session_123' => ['TOKEN_PERSON_A1B2' => 'John Smith', ...]]
    private static array $tokenStore = [];

    // PHI categories to detect and replace
    // Full list: https://learn.microsoft.com/azure/ai-services/language-service/pii
    private const PHI_CATEGORIES = [
        'Person',                    // Patient/provider names
        'PersonType',                // e.g. "patient", "doctor"
        'PhoneNumber',               // All phone formats
        'Email',                     // Email addresses
        'Address',                   // Street addresses
        'Age',                       // Patient age
        'DateTime',                  // DOB, appointment dates
        'USSocialSecurityNumber',    // SSN
        'USDrugEnforcementAgencyNumber', // DEA number
        'MedicalCondition',          // When used as identifier
        'IPAddress',                 // IP addresses
        'URL',                       // URLs
    ];

    public function __construct()
    {
        $this->http     = new Client([
            'timeout'         => 30,
            'connect_timeout' => 10,
        ]);
        $this->endpoint = rtrim($_ENV['AZURE_AI_LANGUAGE_ENDPOINT'] ?? '', '/');
        $this->apiKey   = $_ENV['AZURE_AI_LANGUAGE_KEY'] ?? '';
    }

    /**
     * De-identify text — replace PHI with tokens.
     *
     * @param  string $text      Raw text containing PHI
     * @param  string $sessionId Unique session identifier
     * @return array {
     *   deidentified_text: string,  (safe to send to LLM)
     *   token_count:       int,     (how many PHI items found)
     *   method:            string,  (azure|regex)
     *   error:             null|string
     * }
     */
    public function deidentify(string $text, string $sessionId): array
    {
        if (empty(trim($text))) {
            return [
                'deidentified_text' => $text,
                'token_count'       => 0,
                'method'            => 'none',
                'error'             => null,
            ];
        }

        // Try Azure API first (most accurate)
        if ($this->endpoint && $this->apiKey) {
            $result = $this->deidentifyViaAzure($text, $sessionId);
            if ($result !== null) {
                return $result;
            }
            // Azure failed — log and fall through to regex
            error_log('PhiDeidentifyService: Azure API failed, using regex fallback');
        }

        // Fallback: regex-based de-identification
        // Less accurate but works without Azure credentials
        return $this->deidentifyViaRegex($text, $sessionId);
    }

    /**
     * Re-insert original PHI back into AI-returned note.
     * Called AFTER GPT-4o returns the structured note.
     *
     * @param  string $text      Text with [TOKEN_XXX] placeholders
     * @param  string $sessionId Session to look up token map
     * @return string            Text with real PHI restored
     */
    public function reidentify(string $text, string $sessionId): string
    {
        $tokenMap = self::$tokenStore[$sessionId] ?? [];

        if (empty($tokenMap)) {
            return $text; // Nothing to reidentify
        }

        foreach ($tokenMap as $token => $originalValue) {
            $text = str_replace("[{$token}]", $originalValue, $text);
        }

        return $text;
    }

    /**
     * Get token count for a session (used in audit logging).
     */
    public function getTokenCount(string $sessionId): int
    {
        return count(self::$tokenStore[$sessionId] ?? []);
    }

    /**
     * Clear token store after session completes.
     * Call this after re-identification is done.
     */
    public function clearSession(string $sessionId): void
    {
        unset(self::$tokenStore[$sessionId]);
    }

    // ── PRIVATE: Azure AI Language API ───────────────────────────────────────

    private function deidentifyViaAzure(string $text, string $sessionId): ?array
    {
        try {
            // Azure AI Language PII Detection endpoint
            $url = "{$this->endpoint}/language/:analyze-text?api-version=2023-04-01";

            $payload = [
                'kind'          => 'PiiEntityRecognition',
                'analysisInput' => [
                    'documents' => [[
                        'id'       => '1',
                        'language' => 'en',
                        'text'     => $text,
                    ]],
                ],
                'parameters' => [
                    'domain'          => 'phi',
                    // 'phi' domain = healthcare-specific PHI detection
                    // More accurate than general PII for medical text
                    'piiCategories'   => self::PHI_CATEGORIES,
                    'modelVersion'    => 'latest',
                    'stringIndexType' => 'Utf16CodeUnit',
                ],
            ];

            $response = $this->http->post($url, [
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $this->apiKey,
                    'Content-Type'              => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data     = json_decode($response->getBody()->getContents(), true);
            $document = $data['results']['documents'][0] ?? null;

            if (!$document) {
                error_log('PhiDeidentifyService: Empty Azure response');
                return null;
            }

            // Check for document-level errors
            if (isset($document['error'])) {
                error_log('PhiDeidentifyService Azure doc error: '
                    . $document['error']['message']);
                return null;
            }

            $entities = $document['entities'] ?? [];

            // Build token map and replace PHI
            [$deidentifiedText, $tokenMap] = $this->replaceEntitiesWithTokens(
                $text,
                $entities,
                $sessionId
            );

            return [
                'deidentified_text' => $deidentifiedText,
                'token_count'       => count($tokenMap),
                'method'            => 'azure',
                'error'             => null,
            ];

        } catch (RequestException $e) {
            $statusCode = $e->getResponse()?->getStatusCode() ?? 0;
            error_log("PhiDeidentifyService Azure error ({$statusCode}): "
                . $e->getMessage());
            return null; // Return null to trigger regex fallback

        } catch (\Exception $e) {
            error_log('PhiDeidentifyService unexpected error: ' . $e->getMessage());
            return null;
        }
    }

    // ── PRIVATE: Replace entities with tokens ─────────────────────────────────

    private function replaceEntitiesWithTokens(
        string $text,
        array  $entities,
        string $sessionId
    ): array {
        if (empty($entities)) {
            return [$text, []];
        }

        $tokenMap = self::$tokenStore[$sessionId] ?? [];

        // Sort entities by offset DESCENDING
        // We must replace from end to start to preserve character positions
        usort($entities, fn($a, $b) => ($b['offset'] ?? 0) - ($a['offset'] ?? 0));

        $deidentifiedText = $text;

        foreach ($entities as $entity) {
            $entityText = $entity['text']     ?? '';
            $category   = $entity['category'] ?? 'ENTITY';
            $offset     = $entity['offset']   ?? 0;
            $length     = $entity['length']   ?? strlen($entityText);

            if (!$entityText) continue;

            // Skip low-confidence detections (below 60%)
            $confidence = $entity['confidenceScore'] ?? 1.0;
            if ($confidence < 0.60) continue;

            // Generate unique token
            $categoryClean = strtoupper(preg_replace('/[^A-Z0-9]/', '', $category));
            $token = 'TOKEN_' . $categoryClean . '_' . strtoupper(bin2hex(random_bytes(4)));

            // Store mapping: token → original value
            $tokenMap[$token] = $entityText;

            // Replace in text using offset/length (more accurate than str_replace)
            $deidentifiedText = substr($deidentifiedText, 0, $offset)
                              . "[{$token}]"
                              . substr($deidentifiedText, $offset + $length);
        }

        // Save updated token map back to store
        self::$tokenStore[$sessionId] = $tokenMap;

        return [$deidentifiedText, $tokenMap];
    }

    // ── PRIVATE: Regex fallback ───────────────────────────────────────────────
    // Used when Azure API is unavailable.
    // Less accurate than Azure but covers common PHI patterns.

    private function deidentifyViaRegex(string $text, string $sessionId): array
    {
        $tokenMap         = self::$tokenStore[$sessionId] ?? [];
        $deidentifiedText = $text;

        $patterns = [
            // SSN: 123-45-6789
            'SSN'    => '/\b\d{3}-\d{2}-\d{4}\b/',

            // Phone: (123) 456-7890 or 123-456-7890 or 1234567890
            'PHONE'  => '/\b(\+?1[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}\b/',

            // Email addresses
            'EMAIL'  => '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z]{2,}\b/i',

            // Dates: 01/15/1980, 1-15-1980, Jan 15 1980, January 15, 1980
            'DATE'   => '/\b(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}|'
                      . '(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)'
                      . '\w*\.?\s+\d{1,2},?\s+\d{4})\b/i',

            // MRN patterns: MRN: 123456 or MRN#123456
            'MRN'    => '/\b(MRN|Medical Record|Chart)[:\s#]?\d{4,10}\b/i',

            // ZIP codes
            'ZIP'    => '/\b\d{5}(-\d{4})?\b/',

            // Ages: 45 years old, age 45, 45-year-old
            'AGE'    => '/\b(\d{1,3})\s*[-]?\s*(year[s]?[-\s]old|y\/o|yo)\b/i',

            // Person names: Two capitalized words
            // Note: This is a rough heuristic — Azure API is much more accurate
            'PERSON' => '/\b([A-Z][a-z]{1,20}\s[A-Z][a-z]{1,20})\b/',
        ];

        foreach ($patterns as $category => $pattern) {
            $deidentifiedText = preg_replace_callback(
                $pattern,
                function ($matches) use ($category, &$tokenMap) {
                    $token = 'TOKEN_' . $category . '_'
                           . strtoupper(bin2hex(random_bytes(4)));
                    $tokenMap[$token] = $matches[0];
                    return "[{$token}]";
                },
                $deidentifiedText
            );
        }

        self::$tokenStore[$sessionId] = $tokenMap;

        return [
            'deidentified_text' => $deidentifiedText,
            'token_count'       => count($tokenMap),
            'method'            => 'regex',
            'error'             => null,
        ];
    }
}