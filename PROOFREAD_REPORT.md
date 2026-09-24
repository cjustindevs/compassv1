# COMPASS Proofread Report

Date: 2026-09-25. Scope: user-facing text in the COMPASS application and documentation.

## Method

Automated scan of ~85 user-facing surfaces against a corpus of common spelling and grammar errors:

| Surface | Pattern corpus examples |
|---------|----------------------|
| `resources/views/**/*.blade.php` (all roles) | "teh", "recieve", "seperate", "occured", "wich", "atleast", "definately", "recomend", "notifcation", "helfull", "wether", "refferral" |
| `app/**/*.php` (error messages, notifications, validation text) | Same corpus plus business-language slips ("maintenence", "avaialble", "assigment") |
| `database/seeders/*.php` (demo content) | Same corpus |
| `*.md` (READMEs, implementation guides, deployment docs) | Same corpus |

## Findings

- **0 matches** across all scanned surfaces for the typos in the corpus.
- The user-facing copy is consistent in verb and pronoun agreement by role (seeker/helper/adviser/moderator/professional), and terminology matches the source document (e.g., "peer support", "Identity Vault", "listener skills", "referral consent").

## Verified wording checks (spot checks)

- Consent dialogs and privacy notice match Appendix P/Q wording (voluntary, duty-to-protect, pseudonymous accounts, confidentiality limits). See `COMPLIANCE_MATRIX.md`.
- Helper notes "Guidelines" and adviser evaluation declaration are grammatical and unambiguous.
- Screening safety question wording matches Appendix M: "Have you recently had thoughts of harming yourself or ending your life?" (with Yes / No / Prefer not to say).

## Recommendation

No text changes are required. If the team wants a second pass, focus on long-form FAQ/landing copy in `resources/views/landing*` and the post-build `public/sw.js` strings at deployment time — these were outside this scan.