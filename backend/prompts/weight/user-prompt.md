Create a personalised 90-Day LeanFlow Weight Protocol for {{NAME}}.

## Client Profile
- **Name:** {{NAME}}
- **Weight Type:** {{WEIGHT_TYPE}}
- **Age:** {{AGE}}
- **Sex:** {{SEX}}
- **Height:** {{HEIGHT}}
- **Current Weight:** {{CURRENT_WEIGHT}}
- **Goal Weight:** {{GOAL_WEIGHT}}
- **Estimated TDEE:** {{TDEE}} {{MEASUREMENT_SYSTEM}} (calculated from provided stats)
- **Primary Symptoms / Barriers:** {{SYMPTOMS}}
- **Goals:** {{GOALS}}
- **Dietary Restrictions:** {{DIETARY_RESTRICTIONS}}
- **Current Medications:** {{MEDICATIONS}}
- **Exercise History:** {{EXERCISE_LEVEL}}
- **Sleep Quality:** {{SLEEP_QUALITY}}
- **Stress Level:** {{STRESS_LEVEL}}
- **Waist Circumference:** {{WAIST_CIRCUMFERENCE}} (if provided)

## Region Profile (MANDATORY — use for all localisation)
```json
{{REGION_PROFILE}}
```

## Instructions
1. Address {{NAME}} directly throughout — never use third person
2. Root every recommendation in their specific weight type: {{WEIGHT_TYPE}}
   - Insulin-Resistant: protein-first, low-GI, berberine, strength training + walks
   - Stress/Cortisol: no HIIT ever, regular meal timing, no fasting, ashwagandha
   - Hormonal: thyroid-supporting nutrition, selenium, avoid raw cruciferous, strength training
   - Habit/Lifestyle: whole-food crowding, progressive movement, no extreme restriction
3. ALL meals must use foods locally available in {{COUNTRY}} per the Region Profile
4. Refer to herbs by English name AND local name from Region Profile
5. Use {{MEASUREMENT_SYSTEM}} units throughout — for weight ({{WEIGHT_UNIT}}), height ({{HEIGHT_UNIT}}), TDEE, and measurements
6. movement_plan MUST include 4 progressive weekly blocks (Weeks 1–2, 3–4, 5–8, 9–12)
   - Stress/Cortisol type: NEVER include HIIT; gentle strength + walking only for all 12 weeks
   - All other types: progress intensity appropriately, HIIT may appear Weeks 5+ for insulin-resistant
7. tracking_guidance MUST include weight_daily AND weight_7day tracking; emphasise the 7-day average
8. plateau_protocol MUST be present (6-step protocol per schema)
9. If {{WEIGHT_TYPE}} is Hormonal (Thyroid): explicitly recommend TSH + Free T3 + Free T4 panel before starting if not already done
10. If Berberine is recommended: clearly note ≤1500mg limit and no Metformin combination
11. quick_win must be actionable today before buying anything (protein at breakfast, evening walk, water)
12. Meal plan calorie guidance: aim for approximately {{TDEE}} minus 300–400 calories using whole foods — NO explicit calorie counting instruction; emphasis on food quality and satiety

Output ONLY valid JSON. No text before or after. Follow the exact schema in the system prompt.
