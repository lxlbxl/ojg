<?php
/**
 * AbstractProtocolGenerator — shared scaffold for all per-condition plan generators.
 *
 * Each concrete subclass supplies:
 *   - getCondition()       : 'pcos'|'acne'|'weight'|'mens'
 *   - getModuleManifest()  : which blocks this condition emits (and which it must NOT)
 *   - getSystemPromptPath(): path to the condition-specific system-prompt.md
 *   - getUserPromptPath()  : path to the condition-specific user-prompt.md
 *   - buildUserPromptVars(): map assessment → prompt placeholder values
 *   - validateContent()    : condition-specific validation rules (required/forbidden modules)
 *   - getFallbackContent() : static fallback when AI fails
 *
 * The base class handles: AI call, JSON extract/repair, validation retry loop,
 * Region-Profile injection, PDF render, email dispatch.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

abstract class AbstractProtocolGenerator
{
    protected $ai;
    protected $settings;
    protected int $maxRetries;
    protected int $retryDelaySec = 2;
    protected string $promptsDir;
    protected string $templatesDir;

    // ── Module manifest constants ──────────────────────────────────────────────
    // Each condition's manifest lists which module keys it emits.
    // Any key absent from the manifest is BLOCKED from the generated JSON.
    const MODULE_MEAL_PLAN        = 'meal_plan';
    const MODULE_MOVEMENT         = 'movement';
    const MODULE_SKINCARE_ROUTINE = 'skincare_routine';
    const MODULE_HERBAL_PROTOCOL  = 'herbal_protocols';
    const MODULE_SUPPLEMENTS      = 'supplements';
    const MODULE_CYCLE_SYNC       = 'cycle_sync';
    const MODULE_SLEEP_STRESS     = 'sleep_stress';
    const MODULE_TRACKING         = 'tracking_guidance';
    const MODULE_PHASE_ARC        = 'phase_arc';

    public function __construct()
    {
        $this->ai          = new AIOrchestrator();
        $this->settings    = Settings::getInstance();
        $this->maxRetries  = (int) $this->settings->get('plan_max_retries', 3);
        $this->promptsDir  = __DIR__ . '/../prompts';
        $this->templatesDir = __DIR__ . '/../templates';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ABSTRACT: subclasses must implement
    // ─────────────────────────────────────────────────────────────────────────

    abstract public function getCondition(): string;

    /** Returns array of MODULE_* constants this condition emits. */
    abstract public function getModuleManifest(): array;

    /** Returns the prompt file dir, e.g. __DIR__.'/../prompts/acne' */
    abstract protected function getPromptsDir(): string;

    /**
     * Build placeholder→value map for the user-prompt template.
     * @param array $assessment
     * @param string $name
     * @param array $regionProfile  resolved geo context (empty array for v1 non-geo)
     */
    abstract protected function buildUserPromptVars(array $assessment, string $name, array $regionProfile): array;

    /**
     * Condition-specific validation. Returns array of error strings.
     * Base class calls this after parsing AI JSON.
     */
    abstract protected function validateConditionContent(array $content): array;

    /**
     * Static fallback content when all AI attempts fail.
     * Must match the condition's schema.
     */
    abstract protected function getFallbackContent(string $subType, string $name): array;

    /**
     * Return the plan filename prefix (used in PDF filename + email subject).
     * e.g. '90_Day_GlowClear_Acne_Protocol'
     */
    abstract protected function getPlanLabel(string $subType): string;

    /**
     * Render the HTML template from the AI content + assessment.
     * Each condition has its own template (or can share with condition-specific sections).
     */
    abstract protected function renderTemplate(callable $get, string $name, string $subType, array $assessment, array $regionProfile): string;

    // ─────────────────────────────────────────────────────────────────────────
    // PUBLIC: Main entry point
    // ─────────────────────────────────────────────────────────────────────────

    public function generate(array $assessment, string $name, string $email = '', array $regionProfile = []): string
    {
        $condition = $this->getCondition();
        $subType   = $this->resolveSubType($assessment);

        error_log("[{$condition}Generator] Starting generation for: $name (type: $subType)");

        $content  = $this->generateAIContent($assessment, $name, $regionProfile);
        $fallback = $this->getFallbackContent($subType, $name ?: 'Friend');

        // Merge: prefer AI content, fall back per field
        $get = function ($field) use ($content, $fallback) {
            if (isset($content[$field])) {
                if (is_array($content[$field]) && count($content[$field]) > 0) return $content[$field];
                if (is_string($content[$field]) && strlen($content[$field]) > 10) return $content[$field];
            }
            return $fallback[$field] ?? '';
        };

        $html      = $this->renderTemplate($get, $name, $subType, $assessment, $regionProfile);
        $pdfBinary = $this->generatePdf($html);

        error_log("[{$condition}Generator] PDF: " . round(strlen($pdfBinary) / 1024) . " KB");

        if ($email && $this->settings->get('plan_send_email', false)) {
            $this->sendEmail($email, $name, $subType, $pdfBinary, $regionProfile);
        }

        return $pdfBinary;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AI CONTENT GENERATION with region injection + retry loop
    // ─────────────────────────────────────────────────────────────────────────

    protected function generateAIContent(array $assessment, string $name, array $regionProfile): array
    {
        $condition = $this->getCondition();
        $subType   = $this->resolveSubType($assessment);

        $promptsDir  = $this->getPromptsDir();
        $systemPrompt = @file_get_contents($promptsDir . '/system-prompt.md');
        $userPromptTpl = @file_get_contents($promptsDir . '/user-prompt.md');

        if (!$systemPrompt || !$userPromptTpl) {
            error_log("[{$condition}Generator] ERROR: Could not load prompt files from $promptsDir");
            return $this->getFallbackContent($subType, $name);
        }

        // Inject shared localization block into system prompt if region provided
        if (!empty($regionProfile)) {
            $locBlock = $this->buildLocalizationBlock($regionProfile);
            $systemPrompt .= "\n\n" . $locBlock;
        }

        // Build user-prompt vars from assessment + region
        $vars = $this->buildUserPromptVars($assessment, $name, $regionProfile);
        $userPrompt = $userPromptTpl;
        foreach ($vars as $placeholder => $value) {
            $userPrompt = str_replace('{{' . $placeholder . '}}', (string) $value, $userPrompt);
        }

        // Inject region profile JSON into user prompt if geo-enabled
        if (!empty($regionProfile)) {
            $regionJson = json_encode($regionProfile, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $userPrompt = str_replace('{{REGION_PROFILE}}', $regionJson, $userPrompt);
        }

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                error_log("[{$condition}Generator] API attempt $attempt/{$this->maxRetries}...");

                $rawResponse = $this->callAIDirect($systemPrompt, $userPrompt);

                if (is_array($rawResponse) && isset($rawResponse['error'])) {
                    throw new Exception('AI API error: ' . $rawResponse['error']);
                }

                $responseStr = is_string($rawResponse) ? $rawResponse : json_encode($rawResponse);
                $jsonStr     = $this->extractJson($responseStr);
                $content     = json_decode($jsonStr, true);

                if (!$content || !is_array($content)) {
                    throw new Exception('Failed to parse AI response as JSON');
                }

                // Strip forbidden modules (manifest enforcement)
                $content = $this->enforceManifest($content);

                // Run validation
                $errors = array_merge(
                    $this->validateCommonFields($content),
                    $this->validateConditionContent($content)
                );

                if (!empty($errors)) {
                    error_log("[{$condition}Generator] Validation issues (attempt $attempt): " . implode(', ', $errors));
                    if ($attempt < $this->maxRetries) {
                        sleep($this->retryDelaySec * $attempt);
                        continue;
                    }
                    error_log("[{$condition}Generator] Proceeding with partial content after all retries.");
                }

                error_log("[{$condition}Generator] AI content generated successfully.");
                return $content;

            } catch (Exception $e) {
                error_log("[{$condition}Generator] API error (attempt $attempt): " . $e->getMessage());
                if ($attempt < $this->maxRetries) {
                    sleep($this->retryDelaySec * $attempt);
                }
            }
        }

        error_log("[{$condition}Generator] All API attempts failed. Using fallback.");
        return $this->getFallbackContent($subType, $name ?: 'Friend');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Manifest enforcement — strip any modules not declared for this condition
    // ─────────────────────────────────────────────────────────────────────────

    protected function enforceManifest(array $content): array
    {
        $manifest = $this->getModuleManifest();

        // If MOVEMENT is not in the manifest, remove workout/movement blocks
        if (!in_array(self::MODULE_MOVEMENT, $manifest)) {
            unset($content['workout'], $content['movement'], $content['exercise_plan']);
            // Also remove from daily plans if nested
            if (isset($content['daily_plans']) && is_array($content['daily_plans'])) {
                foreach ($content['daily_plans'] as &$day) {
                    unset($day['workout'], $day['movement'], $day['exercise']);
                }
            }
        }

        // If SKINCARE_ROUTINE is not in manifest, remove it
        if (!in_array(self::MODULE_SKINCARE_ROUTINE, $manifest)) {
            unset($content['skincare_routine'], $content['am_routine'], $content['pm_routine'], $content['topical_routine']);
        }

        // If CYCLE_SYNC is not in manifest, remove cycle phase content
        if (!in_array(self::MODULE_CYCLE_SYNC, $manifest)) {
            unset($content['cycle_sync'], $content['cycle_phases'], $content['menstrual_phase_guidance']);
        }

        return $content;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Common field validation (shared across all conditions)
    // ─────────────────────────────────────────────────────────────────────────

    protected function validateCommonFields(array $content): array
    {
        $errors = [];
        $requiredStrings = ['summary', 'root_cause', 'encouragement'];

        foreach ($requiredStrings as $field) {
            if (empty($content[$field]) || !is_string($content[$field]) || strlen($content[$field]) < 20) {
                $errors[] = "Missing or too short: $field";
            }
        }

        if (empty($content['goals']) || !is_array($content['goals'])) {
            $errors[] = "Missing goals array";
        }

        return $errors;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Localization block injected into system prompt when regionProfile present
    // ─────────────────────────────────────────────────────────────────────────

    protected function buildLocalizationBlock(array $regionProfile): string
    {
        $country   = $regionProfile['country'] ?? 'the user\'s country';
        $units     = $regionProfile['measurement_system'] ?? 'metric';
        $dietNorms = $regionProfile['dietary_norms'] ?? '';
        $sourcing  = $regionProfile['where_to_source'] ?? 'local markets and pharmacies';
        $langName  = $regionProfile['language_for_local_names'] ?? '';

        return "## LOCALIZATION RULES (MANDATORY — override any default behaviour)
- The user is located in: **{$country}**.
- Use measurement system: **{$units}** (kg/cm or lbs/inches as applicable).
- Every meal MUST use foods locally available and culturally familiar in {$country}.
  NEVER recommend ingredients the user cannot reasonably purchase where they live.
- For herbs: refer to each herb by its common English name AND the local-language name
  provided in the REGION PROFILE. Only suggest herbs listed in the region profile's
  `locally_available_herbs` array AND that pass the global herb safety rules for this user.
  If the clinically ideal herb is unavailable, suggest the closest safe local alternative.
- Source guidance: refer users to **{$sourcing}** for ingredients and herbs.
- Dietary norms to respect: {$dietNorms}
- Do NOT default to any single country's cuisine. This plan must read naturally for {$country}.
- Output all weight/volume/temperature units in the **{$units}** system.";
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Sub-type resolver — each condition overrides to extract from assessment
    // ─────────────────────────────────────────────────────────────────────────

    protected function resolveSubType(array $assessment): string
    {
        return $assessment['type'] ?? $assessment['subType'] ?? 'general';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Direct AI call (bypasses AIOrchestrator's DB system prompt)
    // ─────────────────────────────────────────────────────────────────────────

    protected function callAIDirect(string $systemPrompt, string $userPrompt)
    {
        $provider   = $this->settings->get('ai_provider', 'openrouter');
        $apiKey     = $this->settings->get('ai_api_key', '');
        $model      = $this->settings->get('ai_model', 'google/gemini-2.0-flash-exp:free');
        $maxTokens  = (int) $this->settings->get('plan_max_tokens', 16000);
        $temperature = (float) $this->settings->get('plan_temperature', 0.7);

        if (empty($apiKey)) {
            return ['error' => 'API Key missing. Configure ai_api_key in Settings.'];
        }

        if ($provider === 'openai') {
            $url     = 'https://api.openai.com/v1/chat/completions';
            $headers = ["Authorization: Bearer $apiKey", "Content-Type: application/json"];
            $body    = [
                'model'           => $model,
                'messages'        => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userPrompt],
                ],
                'response_format' => ['type' => 'json_object'],
                'max_tokens'      => $maxTokens,
                'temperature'     => $temperature,
            ];
        } else {
            // OpenRouter (default) or compatible
            $url     = 'https://openrouter.ai/api/v1/chat/completions';
            $headers = [
                "Authorization: Bearer $apiKey",
                "HTTP-Referer: https://ojgherbal.com",
                "X-Title: OJG Herbal Protocol Generator",
                "Content-Type: application/json",
            ];
            $body = [
                'model'       => $model,
                'messages'    => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userPrompt],
                ],
                'max_tokens'  => $maxTokens,
                'temperature' => $temperature,
            ];
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => 1,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 120,
        ]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['error' => 'Curl error: ' . $err];
        }
        curl_close($ch);

        $json = json_decode($response, true);

        if (isset($json['choices'][0]['message']['content'])) {
            return $json['choices'][0]['message']['content'];
        }
        if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
            return $json['candidates'][0]['content']['parts'][0]['text'];
        }

        return ['error' => 'Unexpected API response: ' . substr($response, 0, 500)];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // JSON extraction from AI response (handles markdown wrappers)
    // ─────────────────────────────────────────────────────────────────────────

    protected function extractJson(string $text): string
    {
        $trimmed = trim($text);
        if (str_starts_with($trimmed, '{')) return $trimmed;

        if (preg_match('/```(?:json)?\s*(\{.+?\})\s*```/s', $text, $m)) {
            return $m[1];
        }

        $start = strpos($text, '{');
        $end   = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            return substr($text, $start, $end - $start + 1);
        }

        return $text;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PDF generation via dompdf
    // ─────────────────────────────────────────────────────────────────────────

    protected function generatePdf(string $html): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'sans-serif');
        $options->set('isFontSubsettingEnabled', true);
        $options->set('defaultMediaType', 'print');
        $options->set('tempDir', sys_get_temp_dir());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Email dispatch (non-blocking, best-effort)
    // ─────────────────────────────────────────────────────────────────────────

    protected function sendEmail(string $email, string $name, string $subType, string $pdfBinary, array $regionProfile = []): void
    {
        try {
            $mailer    = new Mailer();
            $label     = $this->getPlanLabel($subType);
            $subject   = "Your $label — OJG Herbal";
            $nameEsc   = htmlspecialchars($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $body      = "<p>Hi $nameEsc,</p>"
                . "<p>Your personalized $label is attached.</p>"
                . "<p>Consistency is the key. Follow the plan one day at a time, and you WILL see results.</p>"
                . "<p>With love,<br>OJG Herbal Team</p>";

            $tempFile  = tempnam(sys_get_temp_dir(), 'ojg_plan_') . '.pdf';
            file_put_contents($tempFile, $pdfBinary);
            $mailer->send($email, $subject, $body, true, $tempFile, $label . '.pdf');
            error_log("[{$this->getCondition()}Generator] Email sent to: $email");
            @unlink($tempFile);
        } catch (Exception $e) {
            error_log("[{$this->getCondition()}Generator] Email failed (non-blocking): " . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SHARED HTML RENDER HELPERS (used by all condition templates)
    // ─────────────────────────────────────────────────────────────────────────

    protected function esc($text): string
    {
        if (!is_string($text)) return (string) $text;
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    protected function renderGoals($goals): string
    {
        if (!is_array($goals)) return '';
        $html = '';
        foreach ($goals as $g) {
            $html .= '<li style="display:flex;align-items:flex-start;gap:8px;margin-bottom:10px;">'
                . '<span style="color:#C77D63;font-weight:700;font-size:16px;">&#10022;</span> '
                . '<span>' . $this->esc($g) . '</span></li>';
        }
        return $html;
    }

    protected function renderWeeklyActions($weeks): string
    {
        if (!is_array($weeks)) return '';
        $html = '';
        foreach ($weeks as $w) {
            $actions = '';
            if (is_array($w['actions'] ?? null)) {
                foreach ($w['actions'] as $a) {
                    $actions .= '<li>' . $this->esc($a) . '</li>';
                }
            }
            $html .= '<tr>'
                . '<td class="week-num">Week ' . $this->esc($w['week'] ?? '') . '</td>'
                . '<td style="font-weight:600;color:#0f3922;">' . $this->esc($w['focus'] ?? '') . '</td>'
                . '<td><ul style="margin:0;padding-left:16px;line-height:1.8;">' . $actions . '</ul></td>'
                . '<td class="milestone">' . $this->esc($w['milestone'] ?? '') . '</td>'
                . '</tr>';
        }
        return $html;
    }

    protected function renderRoutine($items): string
    {
        if (!is_array($items)) return '';
        $html = '';
        foreach ($items as $item) {
            $html .= '<tr>'
                . '<td class="routine-time">' . $this->esc($item['time'] ?? '') . '</td>'
                . '<td><strong>' . $this->esc($item['action'] ?? '') . '</strong><br>'
                . '<span class="routine-why">' . $this->esc($item['why'] ?? '') . '</span></td>'
                . '</tr>';
        }
        return $html;
    }

    protected function renderMealDays($days): string
    {
        if (!is_array($days)) return '';
        $html = '';
        foreach ($days as $day) {
            $mealTypes = [
                ['key' => 'breakfast', 'emoji' => '&#127749;', 'label' => 'Breakfast'],
                ['key' => 'lunch',     'emoji' => '&#9728;&#65039;', 'label' => 'Lunch'],
                ['key' => 'dinner',    'emoji' => '&#127769;', 'label' => 'Dinner'],
                ['key' => 'snack',     'emoji' => '&#127822;', 'label' => 'Snack'],
            ];
            $mealsHtml = '';
            foreach ($mealTypes as $mt) {
                $meal = $day[$mt['key']] ?? [];
                $mealsHtml .= '<div class="meal-item">'
                    . '<div class="meal-type">' . $mt['emoji'] . ' ' . $mt['label'] . '</div>'
                    . '<div class="meal-name">' . $this->esc($meal['meal'] ?? '') . '</div>'
                    . '<div class="meal-desc">' . $this->esc($meal['description'] ?? '') . '</div>'
                    . '<div class="meal-benefit">' . $this->esc($meal['benefit'] ?? '') . '</div>'
                    . '</div>';
            }
            $html .= '<div class="meal-day" style="page-break-inside:avoid;">'
                . '<div class="meal-day-header">Day ' . $this->esc($day['day'] ?? '') . '</div>'
                . '<div class="meal-grid">' . $mealsHtml . '</div>'
                . '</div>';
        }
        return $html;
    }

    protected function renderSupplements($supplements): string
    {
        if (!is_array($supplements)) return '';
        $html = '';
        foreach ($supplements as $s) {
            $note = !empty($s['note'])
                ? '<div class="supp-caution">&#9888; ' . $this->esc($s['note']) . '</div>' : '';
            $html .= '<div class="supp-card" style="page-break-inside:avoid;">'
                . '<div class="supp-icon supp-icon-supplement">&#128138;</div>'
                . '<div style="flex:1;">'
                . '<div class="supp-name">' . $this->esc($s['name'] ?? '') . '</div>'
                . '<div style="margin-top:6px;">'
                . '<span class="supp-dosage">' . $this->esc($s['dosage'] ?? '') . '</span> '
                . '<span class="supp-timing">' . $this->esc($s['timing'] ?? '') . '</span>'
                . '</div>'
                . '<div class="supp-benefit">&#10022; ' . $this->esc($s['benefit'] ?? '') . '</div>'
                . $note . '</div></div>';
        }
        return $html;
    }

    protected function renderHerbalProtocols($herbs): string
    {
        if (!is_array($herbs)) return '';
        $html = '';
        foreach ($herbs as $h) {
            // Support both 'yoruba_name' (legacy) and 'local_name' (new geo-aware)
            $localName = $h['local_name'] ?? $h['yoruba_name'] ?? '';
            $localHtml = $localName
                ? '<div class="supp-local-name">Local name: ' . $this->esc($localName) . '</div>' : '';
            $caution = !empty($h['caution'])
                ? '<div class="supp-caution">&#9888; ' . $this->esc($h['caution']) . '</div>' : '';
            $html .= '<div class="supp-card" style="page-break-inside:avoid;">'
                . '<div class="supp-icon supp-icon-herb">&#127807;</div>'
                . '<div style="flex:1;">'
                . '<div class="supp-name">' . $this->esc($h['herb'] ?? $h['name'] ?? '') . '</div>'
                . $localHtml
                . '<div class="supp-detail"><strong>Preparation:</strong> ' . $this->esc($h['preparation'] ?? '') . '</div>'
                . '<div style="margin-top:4px;"><span class="supp-dosage">' . $this->esc($h['dosage'] ?? '') . '</span></div>'
                . '<div class="supp-benefit">&#10022; ' . $this->esc($h['benefit'] ?? '') . '</div>'
                . $caution . '</div></div>';
        }
        return $html;
    }

    protected function renderLifestyleTips($tips): string
    {
        if (!is_array($tips)) return '';
        $html = '';
        foreach ($tips as $t) {
            $html .= '<div class="tip-card" style="page-break-inside:avoid;">'
                . '<span class="tip-category">' . $this->esc($t['category'] ?? '') . '</span>'
                . '<div><div class="tip-text">' . $this->esc($t['tip'] ?? '') . '</div>'
                . '<div class="tip-detail">' . $this->esc($t['detail'] ?? '') . '</div>'
                . '</div></div>';
        }
        return $html;
    }

    /**
     * Renders tracking_guidance as either structured machine-readable table rows (new format)
     * or legacy prose rows (old format) — supports both schemas.
     */
    protected function renderTrackingGuidance($items): string
    {
        if (!is_array($items)) return '';
        $html = '';
        foreach ($items as $t) {
            // New schema: key, label, type, frequency, why, chart
            if (isset($t['key'])) {
                $typeTag = !empty($t['type'])
                    ? '<span style="background:#EBF5FB;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:600;">' . $this->esc($t['type']) . '</span>' : '';
                $html .= '<tr>'
                    . '<td style="font-weight:600;color:#0f3922;">' . $this->esc($t['label'] ?? $t['key']) . ' ' . $typeTag . '</td>'
                    . '<td><span style="background:#EBF5FB;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;">' . $this->esc($t['frequency'] ?? '') . '</span></td>'
                    . '<td>' . $this->esc($t['how'] ?? '') . '</td>'
                    . '<td style="font-style:italic;color:#666;">' . $this->esc($t['why'] ?? '') . '</td>'
                    . '</tr>';
            } else {
                // Legacy: what, frequency, how, why
                $html .= '<tr>'
                    . '<td style="font-weight:600;color:#0f3922;">' . $this->esc($t['what'] ?? '') . '</td>'
                    . '<td><span style="background:#EBF5FB;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;">' . $this->esc($t['frequency'] ?? '') . '</span></td>'
                    . '<td>' . $this->esc($t['how'] ?? '') . '</td>'
                    . '<td style="font-style:italic;color:#666;">' . $this->esc($t['why'] ?? '') . '</td>'
                    . '</tr>';
            }
        }
        return $html;
    }

    protected function renderEncouragement($text): string
    {
        if (!is_string($text)) return '';
        $paragraphs = array_filter(explode("\n", $text), fn($p) => trim($p) !== '');
        $html = '';
        foreach ($paragraphs as $p) {
            $html .= '<p>' . $this->esc(trim($p)) . '</p>';
        }
        return $html;
    }

    /** Render skincare AM/PM routine (acne-specific module). */
    protected function renderSkincareRoutine($routine): string
    {
        if (!is_array($routine)) return '';
        $html = '';
        foreach (['am' => 'AM Routine', 'pm' => 'PM Routine'] as $period => $label) {
            if (empty($routine[$period]) || !is_array($routine[$period])) continue;
            $html .= '<div class="skincare-period"><h4>' . $label . '</h4><ol>';
            foreach ($routine[$period] as $step) {
                $ingredient = !empty($step['ingredient'])
                    ? ' <span class="skincare-ingredient">(' . $this->esc($step['ingredient']) . ')</span>' : '';
                $why = !empty($step['why'])
                    ? '<div class="skincare-why">' . $this->esc($step['why']) . '</div>' : '';
                $html .= '<li><strong>' . $this->esc($step['step'] ?? '') . '</strong>' . $ingredient . $why . '</li>';
            }
            $html .= '</ol></div>';
        }
        return $html;
    }

    /** Render strength/movement protocol (weight + men's). */
    protected function renderMovementProtocol($movement): string
    {
        if (!is_array($movement)) return '';
        $html = '<div class="movement-protocol">';
        if (!empty($movement['overview'])) {
            $html .= '<p>' . $this->esc($movement['overview']) . '</p>';
        }
        if (!empty($movement['weeks']) && is_array($movement['weeks'])) {
            $html .= '<table class="movement-table"><thead><tr>'
                . '<th>Week</th><th>Focus</th><th>Sessions</th><th>Progression</th></tr></thead><tbody>';
            foreach ($movement['weeks'] as $w) {
                $html .= '<tr>'
                    . '<td>' . $this->esc($w['week'] ?? '') . '</td>'
                    . '<td>' . $this->esc($w['focus'] ?? '') . '</td>'
                    . '<td>' . $this->esc($w['sessions'] ?? '') . '</td>'
                    . '<td>' . $this->esc($w['progression'] ?? '') . '</td>'
                    . '</tr>';
            }
            $html .= '</tbody></table>';
        }
        $html .= '</div>';
        return $html;
    }

    /** Render quick win for onboarding. */
    protected function renderQuickWin($quickWin): string
    {
        if (!is_array($quickWin)) {
            return is_string($quickWin) ? '<p>' . $this->esc($quickWin) . '</p>' : '';
        }
        return '<div class="quick-win">'
            . '<div class="qw-label">YOUR FIRST ACTION</div>'
            . '<div class="qw-title">' . $this->esc($quickWin['title'] ?? '') . '</div>'
            . '<div class="qw-detail">' . $this->esc($quickWin['detail'] ?? '') . '</div>'
            . '</div>';
    }

    /** Render local sourcing guide (geo layer over-delivery). */
    protected function renderSourcingGuide($sourcing, array $regionProfile = []): string
    {
        if (!is_array($sourcing)) return '';
        $html = '<div class="sourcing-guide">';
        if (!empty($regionProfile['where_to_source'])) {
            $html .= '<p><strong>Where to buy in ' . $this->esc($regionProfile['country'] ?? 'your area') . ':</strong> '
                . $this->esc($regionProfile['where_to_source']) . '</p>';
        }
        foreach ($sourcing as $item) {
            $html .= '<div class="sourcing-item">'
                . '<div class="sourcing-name">' . $this->esc($item['item'] ?? '') . '</div>'
                . '<div class="sourcing-where">' . $this->esc($item['where'] ?? '') . '</div>'
                . (!empty($item['cost']) ? '<div class="sourcing-cost">' . $this->esc($item['cost']) . '</div>' : '')
                . '</div>';
        }
        $html .= '</div>';
        return $html;
    }
}
