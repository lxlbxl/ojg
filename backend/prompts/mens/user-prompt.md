Create a personalised 90-Day Vitale Men's Protocol for {{NAME}}.

## Client Profile
- **Name:** {{NAME}}
- **Health Type:** {{MENS_TYPE}}
- **Age:** {{AGE}}
- **Height:** {{HEIGHT}}
- **Current Weight:** {{CURRENT_WEIGHT}}
- **Primary Symptoms / Concerns:** {{SYMPTOMS}}
- **Goals:** {{GOALS}}
- **Dietary Restrictions:** {{DIETARY_RESTRICTIONS}}
- **Current Medications:** {{MEDICATIONS}}
- **Exercise History:** {{EXERCISE_LEVEL}}
- **Sleep Quality:** {{SLEEP_QUALITY}}
- **Sleep Hours (typical):** {{SLEEP_HOURS}}
- **Stress Level:** {{STRESS_LEVEL}}
- **Energy Pattern:** {{ENERGY_PATTERN}}

## Region Profile (MANDATORY — use for all localisation)
```json
{{REGION_PROFILE}}
```

## Instructions
1. Address {{NAME}} directly — direct, confident, science-grounded tone throughout
2. Root every recommendation in their specific type: {{MENS_TYPE}}
   - Low Energy/Fatigue: sleep quality + mitochondrial nutrients + ashwagandha + B12/D3
   - Low-T Markers: zinc + cholesterol foods + compound lifting + ashwagandha + boron/tongkat ali
   - Stress/Burnout: reduce training volume (NOT stop training), ashwagandha, phosphatidylserine, daily walk priority
   - Body Composition: creatine 5g/day, progressive overload, protein 1.8–2.2g/kg, sleep quality
3. ALL meals must use foods locally available in {{COUNTRY}} per the Region Profile — zinc-rich LOCAL foods prioritised
4. Refer to herbs by English name AND local name from Region Profile
5. Use {{MEASUREMENT_SYSTEM}} units throughout
6. movement_plan MUST include 4 progressive weekly blocks (Weeks 1–2, 3–4, 5–8, 9–12)
   - Low Energy: start lighter, 3×/week; recovery focus first 2 weeks
   - Low-T Markers: compound movements prioritised (squat, deadlift, bench, row); progressive overload mandatory
   - Stress/Burnout: 3 focused 45-min sessions max per week; daily 30-min walk is equally important; NO high-volume training
   - Body Composition: 4–5 sessions/week; hypertrophy focus (8–12 reps, progressive); creatine mandatory
7. sleep_recovery_protocol MUST be included with the full 7 steps from the system prompt (adapt to their local context where relevant)
8. tracking_guidance MUST include: energy, libido, focus, sleep_hours, sleep_quality, strength_done, mood
9. Creatine Monohydrate (5g/day) MUST appear in supplements for Body Composition and Low-T types
10. If Low-T Markers type: explicitly recommend GP testosterone panel (Total T, Free T, SHBG, LH, FSH) before or alongside protocol
11. If Ashwagandha recommended: confirm ≤600mg/day; state caution for thyroid conditions
12. quick_win must be immediate and physical — push-ups, a cold shower finish, or a walk RIGHT NOW

Output ONLY valid JSON. No text before or after. Follow the exact schema in the system prompt.
