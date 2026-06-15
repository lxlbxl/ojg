Create a personalised 90-Day GlowClear Acne Protocol for {{NAME}}.

## Client Profile
- **Name:** {{NAME}}
- **Acne Type:** {{ACNE_TYPE}}
- **Age:** {{AGE}}
- **Sex:** {{SEX}}
- **Skin Type:** {{SKIN_TYPE}}
- **Primary Symptoms / Breakout Pattern:** {{SYMPTOMS}}
- **Known Triggers:** {{TRIGGER_HISTORY}}
- **Goals:** {{GOALS}}
- **Dietary Restrictions:** {{DIETARY_RESTRICTIONS}}
- **Current Medications:** {{MEDICATIONS}}
- **Stress Level:** {{STRESS_LEVEL}}
- **Sleep Quality:** {{SLEEP_QUALITY}}
- **Cycle Status (if female + hormonal type):** {{CYCLE_STATUS}}
- **Enable Cycle Module:** {{ENABLE_CYCLE_MODULE}}

## Region Profile (MANDATORY — use for all localisation)
```json
{{REGION_PROFILE}}
```

## Instructions
1. Address {{NAME}} directly and compassionately throughout — acknowledge the emotional weight of acne first
2. Root every recommendation in their specific acne type: {{ACNE_TYPE}}
3. ALL meals must use foods locally available in {{COUNTRY}} per the Region Profile
4. Refer to herbs by English name AND local name from the Region Profile
5. Use {{MEASUREMENT_SYSTEM}} units throughout
6. CRITICAL — No workout block: this protocol has NO exercise/movement module
   - If stress/cortisol type: mention gentle walking once as a stress tip only — never as a workout
7. skincare_routine (AM and PM) is a REQUIRED core section — personalise actives for {{ACNE_TYPE}}:
   - Hormonal: niacinamide + salicylic acid + SPF; spearmint tea daily
   - Inflammatory: gentle barrier-repair first; azelaic acid; probiotics
   - Comedonal: BHA (salicylic acid) priority; AHA for texture; niacinamide
   - Fungal: zinc pyrithione or selenium sulfide cleanser; NO comedogenic oils; anti-fungal focus
   - Stress/Cortisol: simple gentle routine; no actives that strip; calming herbs
8. Cycle module ({{ENABLE_CYCLE_MODULE}}): include ONLY if "yes" — add cycle-phase dietary tips for hormonal-acne management
9. tracking_guidance MUST use the structured schema with skin_photo as first entry
10. flare_playbook must include responses specific to {{ACNE_TYPE}}
11. If {{ACNE_TYPE}} is Fungal: explicitly warn that bacterial acne protocols WORSEN fungal acne; anti-fungal approach only
12. Flag any prescription options (isotretinoin, antibiotics) as "see a dermatologist for this" — do not prescribe
13. quick_win must be free and doable today (Day 1 photo, changing pillowcase, starting spearmint tea)

Output ONLY valid JSON. No text before or after. Follow the exact schema in the system prompt.
