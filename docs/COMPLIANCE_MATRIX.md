# COMPASS Appendix-to-System Compliance Matrix

Audit source: `storage/logs/compass-v4.txt` (project document), appendix letters A–U.
Audit date: 2026-09-25. Scope: map every appendix to the implemented system surface and record the form-alignment changes applied during this revision.

Legend:
- **Aligned** – system surface already implements the appendix requirement.
- **Aligned (this revision)** – surface existed but required an alignment change; the change is marked with the revision code (R1–R7).
- **Not applicable** – appendix describes capability that the deployed system explicitly does not offer.
- **System artifact** – appendix is architecture/design material, not a user-facing form.

## Form-level compliance

| App. | Appendix title | System surface | Status | Notes |
|------|----------------|----------------|--------|-------|
| M (seeker pre) | Area-of-concern selection + suicidal screening question | `request/screening.blade.php` – area of concern dropdown; Safety Check radio "Have you recently had thoughts of harming yourself or ending your life?" with Yes / No / Prefer not to say; Yes auto-maps to risk tags and emergency resources | Aligned | Compact screening instrument drives risk classification |
| M (seeker post) | Post-session evaluation (5 items + comments) | `session/evaluation.blade.php` via `EvaluationInstrument::LABELS` (helpfulness, comfort, feeling after, understood, reuse) + comments box | Aligned | Options match appendix exactly |
| M (helper pre) | Listener readiness check | `helper/readiness.blade.php` – emotionally ready, willingness to listen without judgment, current stress level + 5 listener skills | Aligned | |
| M (helper post) | Session documentation incl. **Skills Applied: Active Listening, Empathy, Reflection, Clarification, Summarizing** | `helper/notes.blade.php` + `HelperSessionController` | Aligned (R5) | Skills restored to the 5 appendix skills; `skills_applied.*` validated against `HelperReadinessService::SKILLS`; evaluation display simplified |
| N | Adviser Evaluation Form (5 competencies, weights 25/25/20/20/10, evidence checklist, declaration) | `adviser/evaluate.blade.php` + `CompetencyRubric` | Aligned (R7) | "Referral Accuracy" relabelled to appendix term **Referral Judgment** with the appendix descriptor; evidence-considered list and attestation checkbox already present |
| O | Referral Form (reason category checkboxes, session info, risk) | helper chat referral modal + `session/referral-prompt.blade.php` (consent dialog) + `adviser/referral-detail.blade.php` (review/approve) + professional pipeline | Aligned (R6, R7) | Adviser approval now captures required `review_notes` (R6). Appendix O reason-indicator checkboxes added to helper referral modal and merged into the stored reason (R7) |
| P | Consent Form (voluntary, confidentiality, referral consent, duty to protect) | `auth/seeker-consent.blade.php`, `seeker/privacy.blade.php`, referral consent dialog, `ConsentService` records | Aligned | Consent-first referral flow + withdrawal controls gated by `Referral::canProvideIdentity()` (R6) |
| Q | Privacy Notice | `seeker/privacy.blade.php` + on-boarding privacy notice (versioned via `ConsentService::VERSION`) | Aligned | |
| H | Business Policy (operating hours 18:00–22:30 Mon–Sat, no-show 10 min, caps) | `OperatingHoursService`, `SeekerWorkflowService` no-show 10-min wait, `Helper::MAX_HELPERS_PER_ADVISER = 15` | Aligned | |
| I | Competency Rubrics | `CompetencyRubric::VERSION` shown on evaluation form; 1–5 descriptor table | Aligned | |
| J | Risk Classification Rubrics | screening classification + `adviser/screenings.blade.php` review workflow | Aligned | |
| T | Incident Report Form (category, description, immediate actions, risk, follow-up) | helper emergency flag modal (`description`, `immediate_action`) + `EmergencyAlert` incidents + adviser/moderator review | Aligned | Requires a factual description; immediate actions/escalation steps recorded |
| R | Voice Recording & Transcript Consent | None (feature disabled) | Not applicable | Deployed system explicitly states in the privacy notice: "Voice calling, recording and automatic transcription are unavailable." A consent offer is therefore neither offered nor needed; no voice data is collected |
| L | Helpers' Script | helper readiness/checklist + training materials | Aligned | Training artifact used by Adviser helper training module |
| S | Confidentiality Agreement / Policy | Adviser scope authorization, `SupervisionVersions`, audit logs, Identity Vault restricted access model | Aligned | |

## Non-form appendices (system artifacts)

| App. | Title | Coverage note |
|------|-------|---------------|
| A | System Features | Implemented feature set (dashboard, chat, scheduling, referrals, monitoring) |
| B | Current Operational Process | Baseline analysis; not a runtime artifact |
| C | Activity Diagram (Proposed) | Informational |
| D | Entity Relationship Diagram | Mirrored by Laravel schema/migrations |
| E | Data Dictionary | Mirrored by models + `IdentityVaultService::FIELDS` |
| F | User Interface | Implemented Blade views |
| G | Use Case Diagram | Informational |
| K | System Architecture | Implemented architecture (Laravel + SQLite/Postgres + Identity Vault) |
| U | Sprint Backlogs | Project-management artifact |

## Alignment changes applied in this revision

| Revision | Change | Surface |
|----------|--------|---------|
| R5 | Restored the 5 appendix listener skills (active listening, empathy, reflection, clarification, summarizing) in post-session notes; validated via `Rule::in(HelperReadinessService::SKILLS)` in `storeNotes` and `storeReflection`; aligned the adviser's skills display | helper notes form, evaluate form |
| R6 | Adviser approve flow now requires and renders `review_notes`; seeker contact-details/withdraw actions gated on `Referral::canProvideIdentity()` (approved + consent + open); professional identity link gated on active case status; `IdentityVaultController::form()` gated the same way; identity release returns a clear 422 when the seeker has not stored details; `IdentityVaultService::approved()` refactored to status constants | adviser/seeker/professional referral views, Identity Vault controller + service |
| R7 | Evaluation competency 5 renamed to Appendix N term **Referral Judgment** (same `referral_accuracy` field); Appendix O reason-indicator checkboxes added to the helper referral modal and merged into `referral_reason` | evaluate form, helper chat referral modal |

## Residual gaps (accepted, documented)

- **Cannot record voice sessions** (Appendix R): no recording occurs, so no consent is elicited; this is consistent with the deployed privacy notice.
- **No standalone incident form with file uploads** (Appendix T "Supporting information"): incidents are captured as structured emergency flags with description/action; file upload is out of scope for the current deployment.
- **No printed/PDF form export** of the paper instruments; the system surfaces are interactive equivalents.