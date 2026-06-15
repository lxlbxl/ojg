Create a personalised 90-Day CycleSync PCOS Protocol for {{NAME}}.

## Client Profile
- **Name:** {{NAME}}
- **PCOS Type:** {{PCOS_TYPE}}
- **Age:** {{AGE}}
- **Primary Symptoms:** {{SYMPTOMS}}
- **Goals:** {{GOALS}}
- **Weight/BMI:** {{BMI}}
- **Cycle Status:** {{CYCLE_STATUS}}
- **Dietary Restrictions:** {{DIETARY_RESTRICTIONS}}
- **Current Medications:** {{MEDICATIONS}}
- **Exercise Level:** {{EXERCISE_LEVEL}}
- **Sleep Quality:** {{SLEEP_QUALITY}}
- **Stress Level:** {{STRESS_LEVEL}}

## Region Profile (MANDATORY — use for all localisation)
```json
{{REGION_PROFILE}}
```

## Instructions
1. Address {{NAME}} directly and personally throughout
2. Root every recommendation in her specific PCOS type ({{PCOS_TYPE}}) — not generic PCOS advice
3. ALL meals must use foods locally available in {{COUNTRY}} per the Region Profile above
4. Refer to each herb by English name AND the local-language name from the Region Profile
5. Use {{MEASUREMENT_SYSTEM}} units throughout (weights, volumes, temperatures)
6. tracking_guidance MUST use the structured schema (key/label/type/frequency/how/why/chart) — not prose
7. Only include modules appropriate to PCOS:
   - ✅ meal_plan, movement (type-appropriate), herbal_protocols, supplements, cycle phases, sleep/stress, tracking
   - ❌ NO skincare routine, NO dermatology content
8. Movement must match her PCOS type:
   - Insulin-Resistant: strength training + walking (no HIIT)
   - Inflammatory: gentle movement only (yoga, swimming, walking)
   - Adrenal: absolutely NO intense cardio — walking and gentle stretching only
   - Post-Pill: moderate combination strength + cardio
9. quick_win must be a free, immediate action she can do before buying anything
10. sourcing_guide must reference where to buy in {{COUNTRY}} with local pricing cues

Output ONLY valid JSON. No text before or after. Follow the exact JSON schema in the system prompt.
