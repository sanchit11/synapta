<?php

namespace Clinic\OeModuleAmbientDocs\Service;

/**
 * AuditService
 * Logs every event to ambient_audit_log for HIPAA compliance.
 * Call this for EVERY action — recording, STT, AI call, clinician edit.
 */
class AuditService
{
    /**
     * Log any event to the audit table.
     * This is the single method everything else calls.
     */
    public function log(array $data): void
    {
        try {
            sqlInsert(
                "INSERT INTO ambient_audit_log
                 (session_id, encounter_id, event_type, event_details,
                  model_version, prompt_hash, clinician_action,
                  edit_distance, duration_ms, ip_address,
                  user_id, user_name, logged_at)
                 VALUES
                 (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $data['session_id']       ?? null,
                    $data['encounter_id']     ?? null,
                    $data['event_type']       ?? 'unknown',
                    isset($data['event_details'])
                        ? json_encode($data['event_details'])
                        : null,
                    $data['model_version']    ?? null,
                    $data['prompt_hash']      ?? null,
                    $data['clinician_action'] ?? null,
                    $data['edit_distance']    ?? null,
                    $data['duration_ms']      ?? null,
                    $this->getClientIp(),
                    $data['user_id']          ?? ($_SESSION['authUserID'] ?? null),
                    $data['user_name']        ?? ($_SESSION['authUser']   ?? null),
                ]
            );
        } catch (\Exception $e) {
            // Audit logging must NEVER crash the main application
            // Log to PHP error log instead
            error_log('AuditService failed: ' . $e->getMessage());
        }
    }

    // ── Convenience methods ───────────────────────────────────────────────────
    // These make the code readable — just call the right method
    // instead of remembering event_type strings

    public function recordingStarted(int $sessionId, int $encounterId, int $patientId): void
    {
        $this->log([
            'session_id'     => $sessionId,
            'encounter_id'   => $encounterId,
            'event_type'     => 'recording_start',
            'event_details'  => ['patient_id' => $patientId],
        ]);
    }

    public function recordingStopped(int $sessionId, int $durationSeconds): void
    {
        $this->log([
            'session_id'  => $sessionId,
            'event_type'  => 'recording_stop',
            'event_details' => ['duration_seconds' => $durationSeconds],
        ]);
    }

    public function sttCompleted(int $sessionId, int $durationMs, int $wordCount): void
    {
        $this->log([
            'session_id'    => $sessionId,
            'event_type'    => 'stt_complete',
            'duration_ms'   => $durationMs,
            'event_details' => ['word_count' => $wordCount],
        ]);
    }

    public function phiDeidentified(int $sessionId, int $tokenCount): void
    {
        $this->log([
            'session_id'    => $sessionId,
            'event_type'    => 'phi_deidentified',
            'event_details' => ['token_count' => $tokenCount],
        ]);
    }

    public function noteGenerated(
        int    $sessionId,
        string $modelVersion,
        string $promptHash,
        int    $durationMs
    ): void {
        $this->log([
            'session_id'    => $sessionId,
            'event_type'    => 'note_generated',
            'model_version' => $modelVersion,
            'prompt_hash'   => $promptHash,
            'duration_ms'   => $durationMs,
        ]);
    }

    public function clinicianActed(
        int    $sessionId,
        int    $encounterId,
        string $action,
        int    $editDistance,
        float  $editPercent
    ): void {
        $this->log([
            'session_id'      => $sessionId,
            'encounter_id'    => $encounterId,
            'event_type'      => 'clinician_action',
            'clinician_action' => $action,
            'edit_distance'   => $editDistance,
            'event_details'   => ['edit_percent' => $editPercent],
        ]);
    }

    public function errorOccurred(int $sessionId, string $errorMessage, string $context = ''): void
    {
        $this->log([
            'session_id'    => $sessionId,
            'event_type'    => 'error',
            'event_details' => [
                'message' => $errorMessage,
                'context' => $context,
            ],
        ]);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function getClientIp(): string
    {
        // Check for proxy headers first
        foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                return $_SERVER[$key];
            }
        }
        return 'unknown';
    }
}