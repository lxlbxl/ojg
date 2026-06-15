<?php
/**
 * RegionProfile — Geo-adaptive localisation layer
 *
 * Resolves a user's location to a structured region profile:
 *   - Locally available staple foods
 *   - Locally available herbs (with local-language names)
 *   - Where to source ingredients
 *   - Measurement system (metric/imperial)
 *   - Climate zone
 *   - Dietary norms
 *
 * Source priority:
 *   1. Curated region pack (JSON files in backend/data/regions/)
 *   2. Cached AI-generated profile (region_profiles table, flagged unreviewed)
 *   3. AI-generated on demand (cached for next time)
 *
 * Herb safety is GLOBAL — this class only surfaces herbs that pass
 * the master herb safety table. It never relaxes safety for local availability.
 */

class RegionProfile
{
    private $db;
    private $ai;
    private string $packsDir;
    private string $safetyTablePath;

    public function __construct()
    {
        $this->db              = Database::getInstance();
        $this->ai              = new AIOrchestrator();
        $this->packsDir        = __DIR__ . '/../data/regions';
        $this->safetyTablePath = __DIR__ . '/../data/herb_safety_table.json';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUBLIC: Resolve for a user record (reads country from member_profiles/users)
    // ─────────────────────────────────────────────────────────────────────────

    public function resolveForUser(int $userId): array
    {
        try {
            $user = $this->db->fetch(
                "SELECT country_code, country_name, region_city, measurement_system, cuisine_pref, locale
                 FROM users WHERE id = :id LIMIT 1",
                [':id' => $userId]
            );
        } catch (Exception $e) {
            $user = null;
        }

        if (!$user || empty($user['country_code'])) {
            // Fall back to Nigeria — the largest existing market
            return $this->resolveByCode('NG');
        }

        return $this->resolveFromData([
            'country_code'       => $user['country_code'],
            'country'            => $user['country_name'],
            'region_city'        => $user['region_city'],
            'measurement_system' => $user['measurement_system'],
            'cuisine_pref'       => $user['cuisine_pref'],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUBLIC: Resolve from explicit data array (from request payload)
    // ─────────────────────────────────────────────────────────────────────────

    public function resolveFromData(array $data): array
    {
        $code    = strtoupper(trim($data['country_code'] ?? ''));
        $country = trim($data['country'] ?? '');
        $city    = trim($data['region_city'] ?? '');
        $prefs   = trim($data['cuisine_pref'] ?? '');

        // Normalise: derive code from country name if missing
        if (empty($code) && !empty($country)) {
            $code = $this->countryNameToCode($country);
        }

        $profile = $this->resolveByCode($code, $city);

        // Apply user cuisine overrides
        if (!empty($prefs)) {
            $profile['cuisine_pref']  = $prefs;
            $profile['dietary_norms'] = $profile['dietary_norms'] . ' User preference: ' . $prefs;
        }

        // Override measurement system if provided
        if (!empty($data['measurement_system'])) {
            $profile['measurement_system'] = $data['measurement_system'];
        }

        return $profile;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUBLIC: Best-effort IP geolocation (used only as initial guess)
    // ─────────────────────────────────────────────────────────────────────────

    public function resolveFromIp(string $ip): array
    {
        // Skip private/loopback IPs
        if (empty($ip) || $ip === '127.0.0.1' || str_starts_with($ip, '192.168.')
            || str_starts_with($ip, '10.') || str_starts_with($ip, '172.')) {
            return $this->resolveByCode('NG'); // safe default
        }

        try {
            // Use ip-api.com (free, no key required, 45 req/min limit)
            $url  = 'http://ip-api.com/json/' . urlencode($ip) . '?fields=countryCode,country,regionName';
            $ch   = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 3,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $resp = curl_exec($ch);
            curl_close($ch);

            $geo  = json_decode($resp, true);
            $code = $geo['countryCode'] ?? '';
            $city = $geo['regionName']  ?? '';

            if ($code) {
                return $this->resolveByCode(strtoupper($code), $city);
            }
        } catch (Exception $e) {
            error_log('[RegionProfile] IP geo failed: ' . $e->getMessage());
        }

        return $this->resolveByCode('NG');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CORE: Resolve by ISO country code
    // ─────────────────────────────────────────────────────────────────────────

    public function resolveByCode(string $code, string $city = ''): array
    {
        $code = strtoupper(trim($code));
        if (empty($code)) $code = 'NG';

        // 1. Try curated pack file
        $packFile = $this->packsDir . '/' . $code . '.json';
        if (file_exists($packFile)) {
            $pack = json_decode(file_get_contents($packFile), true);
            if ($pack) {
                $pack = $this->filterHerbsBySafety($pack);
                return $pack;
            }
        }

        // 2. Try DB cache
        try {
            $cached = $this->db->fetch(
                "SELECT profile_data FROM region_profiles WHERE country_code = :code LIMIT 1",
                [':code' => $code]
            );
            if ($cached) {
                $profile = json_decode($cached['profile_data'], true);
                if ($profile) {
                    return $this->filterHerbsBySafety($profile);
                }
            }
        } catch (Exception $e) {
            // Table may not exist yet — continue to AI generation
        }

        // 3. AI-generate and cache
        return $this->generateAndCacheProfile($code, $city);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AI-generate region profile for uncovered countries
    // ─────────────────────────────────────────────────────────────────────────

    private function generateAndCacheProfile(string $code, string $city): array
    {
        $countryName = $this->codeToCountryName($code);
        $cityHint    = $city ? " (city/region: $city)" : '';

        $systemPrompt = "You are a nutritionist and herbal medicine expert with global knowledge. Output ONLY valid JSON.";
        $userPrompt   = "Generate a region profile for: {$countryName}{$cityHint} (ISO code: {$code}).

Output this exact JSON structure:
{
  \"country\": \"{$countryName}\",
  \"country_code\": \"{$code}\",
  \"climate_zone\": \"tropical|temperate|continental|arid|mediterranean\",
  \"measurement_system\": \"metric|imperial\",
  \"staple_foods\": [\"food1\", \"food2\", \"food3\", \"food4\", \"food5\", \"food6\"],
  \"common_proteins\": [\"protein1\", \"protein2\", \"protein3\"],
  \"locally_available_herbs\": [
    {\"name\": \"English name\", \"local_name\": \"local language name\", \"use\": \"brief therapeutic use\"},
    {\"name\": \"...\", \"local_name\": \"...\", \"use\": \"...\"}
  ],
  \"where_to_source\": \"description of where to buy herbs/food (markets, pharmacies, etc.)\",
  \"typical_cost_band\": \"low|medium|high (relative to local income)\",
  \"dietary_norms\": \"brief description of typical dietary patterns, religious norms, common restrictions\",
  \"language_for_local_names\": \"primary local language name\"
}

Requirements:
- staple_foods: 6 real, commonly eaten foods in this country (not aspirational — what people actually eat)
- locally_available_herbs: 4–6 herbs actually available and used in this country
- Be specific to {$countryName}, not generic";

        try {
            $response = $this->callAI($systemPrompt, $userPrompt);
            if (is_string($response)) {
                $profile = json_decode($this->extractJson($response), true);
                if ($profile && !empty($profile['country'])) {
                    $profile = $this->filterHerbsBySafety($profile);
                    $this->cacheProfile($code, $profile);
                    return $profile;
                }
            }
        } catch (Exception $e) {
            error_log('[RegionProfile] AI generation failed for ' . $code . ': ' . $e->getMessage());
        }

        // Ultimate fallback — Nigeria profile
        return $this->resolveByCode('NG');
    }

    private function cacheProfile(string $code, array $profile): void
    {
        try {
            $existing = $this->db->fetch(
                "SELECT id FROM region_profiles WHERE country_code = :code",
                [':code' => $code]
            );
            if ($existing) {
                $this->db->update('region_profiles', [
                    'profile_data' => json_encode($profile),
                    'reviewed'     => 0,
                    'updated_at'   => date('Y-m-d H:i:s'),
                ], "country_code = :code", [':code' => $code]);
            } else {
                $this->db->insert('region_profiles', [
                    'country_code' => $code,
                    'country_name' => $profile['country'] ?? $code,
                    'profile_data' => json_encode($profile),
                    'reviewed'     => 0,
                    'created_at'   => date('Y-m-d H:i:s'),
                    'updated_at'   => date('Y-m-d H:i:s'),
                ]);
            }
        } catch (Exception $e) {
            error_log('[RegionProfile] Cache write failed: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Herb safety gate — global rules, never relaxed for local availability
    // ─────────────────────────────────────────────────────────────────────────

    private function filterHerbsBySafety(array $profile): array
    {
        $safetyTable = $this->loadSafetyTable();
        if (empty($safetyTable) || empty($profile['locally_available_herbs'])) {
            return $profile;
        }

        $filtered = [];
        foreach ($profile['locally_available_herbs'] as $herb) {
            $name  = strtolower($herb['name'] ?? '');
            $entry = $safetyTable[$name] ?? null;

            if ($entry && !empty($entry['globally_blocked'])) {
                // Never suggest this herb regardless of local availability
                continue;
            }
            $filtered[] = $herb;
        }

        $profile['locally_available_herbs'] = $filtered;
        return $profile;
    }

    private function loadSafetyTable(): array
    {
        if (!file_exists($this->safetyTablePath)) return [];
        $raw = file_get_contents($this->safetyTablePath);
        return json_decode($raw, true) ?? [];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function extractJson(string $text): string
    {
        $t = trim($text);
        if (str_starts_with($t, '{')) return $t;
        if (preg_match('/```(?:json)?\s*(\{.+?\})\s*```/s', $text, $m)) return $m[1];
        $s = strpos($text, '{');
        $e = strrpos($text, '}');
        if ($s !== false && $e !== false && $e > $s) return substr($text, $s, $e - $s + 1);
        return $text;
    }

    private function callAI(string $systemPrompt, string $userPrompt)
    {
        $settings = Settings::getInstance();
        $apiKey   = $settings->get('ai_api_key', '');
        $model    = $settings->get('ai_model', 'google/gemini-2.0-flash-exp:free');

        if (empty($apiKey)) return null;

        $body = [
            'model'       => $model,
            'messages'    => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userPrompt],
            ],
            'max_tokens'  => 1500,
            'temperature' => 0.3,
        ];

        $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST           => 1,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer $apiKey",
                "HTTP-Referer: https://ojgherbal.com",
                "Content-Type: application/json",
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $resp = curl_exec($ch);
        curl_close($ch);

        $json = json_decode($resp, true);
        return $json['choices'][0]['message']['content'] ?? null;
    }

    private function countryNameToCode(string $name): string
    {
        $map = [
            'nigeria' => 'NG', 'kenya' => 'KE', 'ghana' => 'GH', 'south africa' => 'ZA',
            'ethiopia' => 'ET', 'tanzania' => 'TZ', 'uganda' => 'UG', 'senegal' => 'SN',
            'cameroon' => 'CM', 'ivory coast' => 'CI', "côte d'ivoire" => 'CI',
            'united states' => 'US', 'usa' => 'US', 'united kingdom' => 'GB', 'uk' => 'GB',
            'germany' => 'DE', 'france' => 'FR', 'spain' => 'ES', 'italy' => 'IT',
            'netherlands' => 'NL', 'sweden' => 'SE', 'norway' => 'NO', 'denmark' => 'DK',
            'serbia' => 'RS', 'croatia' => 'HR', 'poland' => 'PL', 'romania' => 'RO',
            'philippines' => 'PH', 'indonesia' => 'ID', 'malaysia' => 'MY', 'singapore' => 'SG',
            'india' => 'IN', 'pakistan' => 'PK', 'bangladesh' => 'BD',
            'brazil' => 'BR', 'colombia' => 'CO', 'mexico' => 'MX', 'argentina' => 'AR',
            'canada' => 'CA', 'australia' => 'AU', 'new zealand' => 'NZ',
            'egypt' => 'EG', 'morocco' => 'MA', 'algeria' => 'DZ', 'sudan' => 'SD',
            'saudi arabia' => 'SA', 'uae' => 'AE', 'united arab emirates' => 'AE',
        ];
        return $map[strtolower(trim($name))] ?? 'NG';
    }

    private function codeToCountryName(string $code): string
    {
        $map = [
            'NG' => 'Nigeria', 'KE' => 'Kenya', 'GH' => 'Ghana', 'ZA' => 'South Africa',
            'ET' => 'Ethiopia', 'TZ' => 'Tanzania', 'UG' => 'Uganda', 'SN' => 'Senegal',
            'CM' => 'Cameroon', 'CI' => "Côte d'Ivoire",
            'US' => 'United States', 'GB' => 'United Kingdom', 'DE' => 'Germany',
            'FR' => 'France', 'ES' => 'Spain', 'IT' => 'Italy', 'NL' => 'Netherlands',
            'SE' => 'Sweden', 'NO' => 'Norway', 'DK' => 'Denmark',
            'RS' => 'Serbia', 'HR' => 'Croatia', 'PL' => 'Poland', 'RO' => 'Romania',
            'PH' => 'Philippines', 'ID' => 'Indonesia', 'MY' => 'Malaysia', 'SG' => 'Singapore',
            'IN' => 'India', 'PK' => 'Pakistan', 'BD' => 'Bangladesh',
            'BR' => 'Brazil', 'CO' => 'Colombia', 'MX' => 'Mexico', 'AR' => 'Argentina',
            'CA' => 'Canada', 'AU' => 'Australia', 'NZ' => 'New Zealand',
            'EG' => 'Egypt', 'MA' => 'Morocco', 'SA' => 'Saudi Arabia', 'AE' => 'UAE',
        ];
        return $map[$code] ?? $code;
    }
}
