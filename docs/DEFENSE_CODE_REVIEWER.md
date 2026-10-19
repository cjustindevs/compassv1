# COMPASS defense reviewer: saan bubuksan ang code

Code-navigation guide, checked against the repository on October 16, 2026. Para ito sa panel questions at code walkthrough. Hindi ito assurance na pareho na ang configuration o behavior ng deployed Render service; kailangan pa ring i-check ang actual demo.

## 1. Ang pattern na tatandaan mo

**Page/button → Route → Controller → Service → Model/database → View/JavaScript.**

Sample explanation:

> “Ang route po ang tumatanggap ng request. Ang controller ang nagva-validate at tumatawag sa business logic. Ang service ang nagpapatupad ng workflow rules. Ang model ang kumokonekta sa database, at ang Blade view at JavaScript ang nagpapakita ng result sa user.”

Hindi lahat ng feature ay may sariling service; may logic din sa models at controllers. Sundan ang actual method call sa file.

| Folder/file | Ano ang nandito? |
| --- | --- |
| [routes/web.php](../routes/web.php) | Main page/action routes; kasama rin ang ilang `/api/...` endpoints |
| [routes/auth.php](../routes/auth.php) | Login, logout, password reset, email verification routes |
| [routes/channels.php](../routes/channels.php) | Sino ang puwedeng mag-subscribe sa private real-time channels |
| [routes/console.php](../routes/console.php) | Scheduled matching, reminders, expiration, mail delivery |
| [app/Http/Controllers](../app/Http/Controllers) | Request handlers, organized by role |
| [app/Services](../app/Services) | Matching, readiness, referrals, emergency, identity, KPI rules |
| [app/Models](../app/Models) | Database table mappings, relationships, model rules |
| [app/Http/Requests](../app/Http/Requests) | Reusable request validation, including login and Admin account creation |
| [app/Http/Middleware](../app/Http/Middleware) and [app/Policies](../app/Policies) | Role/account checks and record access rules |
| [resources/views](../resources/views) | Blade pages, shared components, layouts |
| [resources/js](../resources/js) and [public/js](../public/js) | Browser behavior, chat, alerts, live updates |
| [database/migrations](../database/migrations) | Database schema changes; hindi ito ang actual online data |
| [tests/Feature](../tests/Feature) and [tests/js](../tests/js) | Backend workflow and JavaScript tests |
| [config](../config) and [.env.example](../.env.example) | Configuration structure and example variable names |

**VS Code shortcuts:** `Ctrl+P` para mag-open ng filename; `Ctrl+Shift+F` para mag-search sa project; `Ctrl+Shift+O` para pumunta sa method ng current file. Kapag maraming kapangalan na controller, tingnan ang buong role folder.

## 2. Quick lookup kapag nagpabuklat ang panel

| Kapag tinanong… | Unang buksan / hanapin | Susunod na ebidensiya |
| --- | --- | --- |
| “Saan pumupunta after login?” | [AuthenticatedSessionController](../app/Http/Controllers/Auth/AuthenticatedSessionController.php), `store`; [web routes](../routes/web.php), `/dashboard` | Role-based redirect and dashboard controllers |
| “Paano nakakagawa ng Seeker account?” | [SeekerOnboardingController](../app/Http/Controllers/SeekerOnboardingController.php), `create`, `store` | [HelpSeeker](../app/Models/HelpSeeker.php), [pseudonymous registration UI](../resources/views/auth/pseudonymous-register.blade.php) |
| “Saan ang validation ng login?” | [LoginRequest](../app/Http/Requests/Auth/LoginRequest.php), `authenticate`, `throttleKey` | [User](../app/Models/User.php), [LoginSessionService](../app/Services/LoginSessionService.php) |
| “Paano gumagawa/deactivate/reactivate ng staff?” | [Admin UserController](../app/Http/Controllers/Admin/UserController.php), `store`, `deactivate`, `reactivate` | [StoreUserRequest](../app/Http/Requests/Admin/StoreUserRequest.php), [Users UI](../resources/views/admin/users/index.blade.php) |
| “Saan ang pre-session screening?” | [RequestSupportController](../app/Http/Controllers/RequestSupportController.php), `processScreening` | [SeekerWorkflowService](../app/Services/SeekerWorkflowService.php), `screen`; [screening UI](../resources/views/request/screening.blade.php) |
| “Saan ang risk classification?” | [CompactScreening](../app/Services/CompactScreening.php), `classifySingle`, `classifyForm`, `classify` | [RiskClassificationService](../app/Services/RiskClassificationService.php), [ScreeningResponse](../app/Models/ScreeningResponse.php) |
| “Paano ang Prefer not to say?” | [SeekerWorkflowService](../app/Services/SeekerWorkflowService.php), `screen` | [ScreeningReviewController](../app/Http/Controllers/ScreeningReviewController.php), [ScreeningFollowUp](../app/Services/ScreeningFollowUp.php) |
| “Paano pumipili ng Helper?” | [HelperMatchingService](../app/Services/HelperMatchingService.php), `findBestMatch`, `rankHelpers` | [HelperEligibilityService](../app/Services/HelperEligibilityService.php), [Helper](../app/Models/Helper.php), `calculateMatchingScore` |
| “Paano ang manual assignment?” | [ModeratorQueueController](../app/Http/Controllers/Moderator/ModeratorQueueController.php) | [HelperMatchingService](../app/Services/HelperMatchingService.php), `manualAssign`; [QueueHelperOptions](../app/Services/QueueHelperOptions.php) |
| “Saan ang duty scheduling?” | [ModeratorScheduleController](../app/Http/Controllers/Moderator/ModeratorScheduleController.php), `store`, `destroy` | [DutySchedulingService](../app/Services/DutySchedulingService.php), [HelperSchedule](../app/Models/HelperSchedule.php) |
| “Saan ang readiness at cooldown?” | [HelperReadinessController](../app/Http/Controllers/Helper/HelperReadinessController.php), `store` | [HelperReadinessService](../app/Services/HelperReadinessService.php), `submit`, `retryAt` |
| “Bakit maximum two sessions?” | [Helper](../app/Models/Helper.php), `MAX_SESSIONS_PER_SHIFT`, `getRemainingCapacity` | [HelperEligibilityService](../app/Services/HelperEligibilityService.php), `reasons` |
| “Saan ang workload/pagination?” | [HelperWorkload](../app/Services/HelperWorkload.php), `page` | [shared workload UI](../resources/views/partials/helper-workload.blade.php), [helper-workload.js](../public/js/helper-workload.js) |
| “Saan makikita ng Helper ang concern?” | [HelperSessionController](../app/Http/Controllers/Helper/HelperSessionController.php), `preSessionAssessment`, `getScreeningSummary` | [pre-session brief](../resources/views/helper/pre-session-assessment.blade.php), [ScreeningResponse](../app/Models/ScreeningResponse.php) |
| “Paano real-time ang chat?” | [ChatController](../app/Http/Controllers/ChatController.php), `sendMessage`, `getMessages` | [chat.js](../resources/js/chat.js), [MessageSent](../app/Events/MessageSent.php), [private channels](../routes/channels.php) |
| “Paano nagre-refer ang Helper?” | [HelperSessionController](../app/Http/Controllers/Helper/HelperSessionController.php), `recommendReferral` | [ReferralManagementService](../app/Services/ReferralManagementService.php), `createReferral`; [ReferralForm](../app/Services/ReferralForm.php) |
| “Saan nag-aapprove/reject ang Adviser?” | [AdviserReferralController](../app/Http/Controllers/Adviser/AdviserReferralController.php), `approve`, `reject`, `requestInfo` | [ReferralManagementService](../app/Services/ReferralManagementService.php), `reviewReferral`, `clarify` |
| “Paano pumapayag/tumatanggi ang Seeker?” | [ReferralWorkflowController](../app/Http/Controllers/ReferralWorkflowController.php), `consent` | [ReferralManagementService](../app/Services/ReferralManagementService.php), `processConsent`; [Seeker referral UI](../resources/views/seeker/referrals.blade.php) |
| “Saan ang identity disclosure?” | [IdentityVaultController](../app/Http/Controllers/IdentityVaultController.php), `store`, `release`, `show` | [IdentityVaultService](../app/Services/IdentityVaultService.php), [identity popup](../resources/views/partials/referral-identity-modal.blade.php) |
| “Saan pipili ng Professional?” | [AdviserReferralController](../app/Http/Controllers/Adviser/AdviserReferralController.php), `assignProfessional` | [ReferralManagementService](../app/Services/ReferralManagementService.php), `assignProfessional` |
| “Paano ang emergency habang nagcha-chat?” | [HelperSessionController](../app/Http/Controllers/Helper/HelperSessionController.php), `flagEmergency` | [EmergencyEscalationService](../app/Services/EmergencyEscalationService.php), [EmergencyStaffNotifications](../app/Services/EmergencyStaffNotifications.php) |
| “Ano ang emergency actions ng staff?” | [AdviserEmergencyController](../app/Http/Controllers/Adviser/AdviserEmergencyController.php), `action`, `raiseToProfessional`, `resolve` | [AdviserEmergencyService](../app/Services/AdviserEmergencyService.php), [EmergencyCoordinationController](../app/Http/Controllers/EmergencyCoordinationController.php) |
| “Paano matapos ang referral?” | [ReferralCompletionController](../app/Http/Controllers/ReferralCompletionController.php), `submit`, `review` | [ReferralCompletionService](../app/Services/ReferralCompletionService.php), [ReferralCompletionReview](../app/Models/ReferralCompletionReview.php) |
| “Saan ang competency computation?” | [CompetencyRubric](../app/Services/CompetencyRubric.php), `score`, `level` | [AdviserEvaluationService](../app/Services/AdviserEvaluationService.php), [AdviserEvaluationController](../app/Http/Controllers/Adviser/AdviserEvaluationController.php) |
| “Saan nanggagaling ang KPIs?” | [SessionPerformance](../app/Services/SessionPerformance.php), `summarize`; [AnalyticsPeriod](../app/Services/AnalyticsPeriod.php) | [DashboardOverview](../app/Services/DashboardOverview.php), [role KPI definitions](ROLE_KPI_VALIDATION.md) |
| “Saan ang email notifications?” | [StaffMailService](../app/Services/StaffMailService.php), `enqueue`, `deliver` | [StaffMail](../app/Models/StaffMail.php), [email template](../resources/views/emails/staff-update.blade.php), [scheduler](../routes/console.php) |
| “Bakit hindi paulit-ulit ang popup?” | [important-notice.js](../public/js/important-notice.js), [notification-presentation.js](../public/js/notification-presentation.js) | [live-notifications.js](../public/js/live-notifications.js), [staff-emergency-notice.js](../public/js/staff-emergency-notice.js) |

## 3. Main workflows: paano ipaliwanag habang bukas ang code

### A. Normal request hanggang chat

**Seeker consent → screening → preferences → queue → eligible Helper assignment → acceptance/start → chat → end → documentation/evaluation.**

1. Sa [web routes](../routes/web.php), search `request.screening.process`.
2. Open [RequestSupportController](../app/Http/Controllers/RequestSupportController.php), `processScreening`, para sa input validation.
3. Open [SeekerWorkflowService](../app/Services/SeekerWorkflowService.php), `screen` then `submit`, para sa state changes at queue submission.
4. Open [HelperMatchingService](../app/Services/HelperMatchingService.php), `processQueueRequest`, para sa assignment.
5. Open [HelperCaseController](../app/Http/Controllers/Helper/HelperCaseController.php) and [HelperSessionController](../app/Http/Controllers/Helper/HelperSessionController.php), `startSessionFromPreAssessment`.
6. Open [ChatController](../app/Http/Controllers/ChatController.php), then [Message](../app/Models/Message.php).

> “Hindi po basta page-to-page redirect. May saved workflow state at server-side validation bago payagan ang susunod na action.”

Sa [Session model](../app/Models/Session.php), search `STATUS_` at `$table`. Ang support-session records ay nasa **`counseling_sessions`**; iba ito sa Laravel login-session storage.

Para sa current Seeker registration, sundan ang `/register-seeker` → `SeekerOnboardingController::create` at `/onboarding` → `store` sa routes. May older registration controller/forms pa sa repository, kaya hindi sapat ang filename lang para masabing iyon ang ginagamit ng current page.

### B. Screening: No, Yes, at Prefer not to say

Buksan ang [CompactScreening](../app/Services/CompactScreening.php), `classifySingle`, then [SeekerWorkflowService](../app/Services/SeekerWorkflowService.php), `screen`.

| Single-question answer | Actual handling na dapat ipaliwanag |
| --- | --- |
| No | Preliminary `low` routing; normal request flow. Hindi patunay na walang ibang concern. |
| Yes | Saved classification is `high`; `fastSafety` creates urgent emergency coordination with `preserve_classification`. Puwedeng magpatuloy sa prioritized peer-support queue habang may staff review. |
| Prefer not to say | Unresolved answer; hindi kino-convert sa No. Staff review/follow-up path muna. Hanapin ang `review_unresolved` at `pending_review`. |

> “Magkaiba po ang original screening classification at operational emergency priority. Hindi namin iniimbento ang sagot sa hindi tinanong, at hindi ito clinical diagnosis.”

May versioned older screening paths din sa code. Kapag tinanong ang current form, ipakita ang `SINGLE_VERSION` at actual [screening view](../resources/views/request/screening.blade.php); huwag paghaluin ang single-question at five-answer rules.

Ang staff review ay nasa [ScreeningReviewController](../app/Http/Controllers/ScreeningReviewController.php). Ang original responses ay source evidence; staff review/follow-up is a separate action.

### C. Duty, readiness, at matching

**Schedule → verification/training/supervision → completed readiness → eligible availability → matching.**

- [DutySchedulingService](../app/Services/DutySchedulingService.php), `save`: schedule creation rules; [HelperSchedule](../app/Models/HelperSchedule.php), `window`: applicable duty period.
- [HelperReadinessService](../app/Services/HelperReadinessService.php), `submit`: three completed breathing rounds; confirmed listening skills, emotional readiness, willingness, non-high stress, and Available preference para pumasa.
- `RETRY_MINUTES = 30` and `retryAt`: cooldown after a saved assessment, kasama ang failed readiness. May duty/capacity checks din.
- Readiness expiry: up to four hours, capped sa applicable duty end.
- [HelperEligibilityService](../app/Services/HelperEligibilityService.php), `reasons`: active account, verified/trained Helper, active primary Adviser, duty, readiness, availability, assignment blockers, remaining capacity.
- [Helper](../app/Models/Helper.php): maximum **two started sessions per duty**; hindi ibig sabihin na puwedeng dalawang sabay na live chat. Tingnan ang `assignmentBlockers` at `currentAssignedSessionsCount`.

Kapag “available pero hindi lumalabas” ang tanong: open `reasons` and `requestReasons`. Puwedeng available ang preference pero may eligibility blocker, gaya ng risk suitability o occupied capacity.

**Matching score**, nasa `Helper::calculateMatchingScore`:

| Factor | Weight |
| --- | --- |
| Competency, normalized from /5 to /100 | 30% |
| Workload | 20% |
| Concern specialty | 15% |
| Language match | 15% |
| Availability | 10% |
| Experience | 10% |

Eligible candidates muna bago ranking. Sa `HelperMatchingService::rankHelpers`, tie-breakers: score descending, shift sessions ascending, availability time ascending, then ID.

Testing-hours behavior is configurable. Sa [config/app.php](../config/app.php), magkahiwalay ang `relax_duty_hours` at `enforce_duty_hours`. I-check ang actual settings bago sabihing enforced ang oras; hindi nito awtomatikong inaalis ang duty/readiness requirement.

### D. Referral: Helper → Adviser → Seeker → Professional → Adviser

1. **Helper recommendation:** `HelperSessionController::recommendReferral` → `ReferralForm` validation → `ReferralManagementService::createReferral`.
2. **Adviser review:** `AdviserReferralController::approve`, `reject`, or `requestInfo`. Ang clarification/revision history ay may sariling records/actions.
3. **Seeker decision:** `ReferralWorkflowController::consent` → `ReferralManagementService::processConsent`. Approval ng Adviser does not record consent on behalf of the Seeker.
4. **Separate identity consent/submission:** `IdentityVaultController::store` → `IdentityVaultService::storeForReferral`.
5. **Professional selection:** `AdviserReferralController::assignProfessional` → `ReferralManagementService::assignProfessional`. Required ang approved referral, Seeker consent, current identity submission, at active/available selected Professional.
6. **Identity release/read:** `IdentityVaultService::releaseForReferral`, `readForReferral`. Hiwalay ito sa pag-save ng identity at referral approval.
7. **Appointment/acceptance:** [ProfessionalReferralController](../app/Http/Controllers/Professional/ProfessionalReferralController.php), [ReferralAppointmentService](../app/Services/ReferralAppointmentService.php), and `ReferralManagementService::acceptReferral`.
8. **Professional work/completion:** [ProfessionalCaseController](../app/Http/Controllers/Professional/ProfessionalCaseController.php); completion sent through `ReferralCompletionController::submit`.
9. **Adviser final review:** `ReferralCompletionService::review` → confirm completion or request follow-up.

> “May separate approval, consent, identity release, at completion review. Ang Professional completion report ay nire-review pa ng Adviser bago maging completed ang referral.”

Kung may outstanding appointments o follow-up, may checks bago mag-confirm ng completion. Ipakita ang `appointments_action` at `appointments_reason` sa [ReferralCompletionService](../app/Services/ReferralCompletionService.php).

**Decline vs withdrawal:** pending consent is decided through `processConsent`; withdrawal of previously granted consent is handled through [SeekerConsentController](../app/Http/Controllers/SeekerConsentController.php) and [ConsentService](../app/Services/ConsentService.php). Hindi pareho ang dalawang action.

### E. Emergency: pre-session vs ongoing chat

- **Pre-session single Yes:** `SeekerWorkflowService::screen` → `EmergencyEscalationService::escalateEmergency`; prioritized peer support and staff coordination run alongside each other.
- **During chat:** `HelperSessionController::flagEmergency` records the report and links emergency handling. Ipakita rin ang [IncidentReport](../app/Models/IncidentReport.php) and [EmergencyAlert](../app/Models/EmergencyAlert.php).
- **Notifications:** `EmergencyStaffNotifications::deliver` creates stored notices for active Advisers and Moderators. Emergency notices use short database polling; huwag sabihing lahat ng alerts ay WebSocket-only.
- **Moderator:** [ModeratorEmergencyController](../app/Http/Controllers/Moderator/ModeratorEmergencyController.php), [EmergencyCoordinationController](../app/Http/Controllers/EmergencyCoordinationController.php), and `HelperMatchingService::manualAssign` for operational coordination/assignment.
- **Adviser:** `AdviserEmergencyController::action`, `requestReferral`, `raiseToProfessional`, `resolve` and [AdviserEmergencyService](../app/Services/AdviserEmergencyService.php).
- **Professional:** receives the assigned urgent referral through the controlled referral path; hindi automatic unrestricted access sa lahat ng emergency cases.

> “Lahat ng active Advisers ay puwedeng ma-alert, pero hindi ibig sabihin na lahat ay puwedeng baguhin ang parehong case. May responsible reviewer at access checks pa rin.”

**Important distinction:** completed referral ≠ automatically resolved emergency. Separate ang emergency resolution. Sa [ModeratorEmergencyCases](../app/Services/ModeratorEmergencyCases.php) at [EmergencyNoticeState](../app/Services/EmergencyNoticeState.php), sundan kung paano ginagamit ang current active/resolved state sa list at notices. Ang pag-dismiss ng popup ay hindi pag-resolve ng case.

## 4. UI: alin ang bubuksan?

Blade notation example: `view('helper.chat')` means `resources/views/helper/chat.blade.php`.

| Screen | File |
| --- | --- |
| Helper layout/sidebar/header | [layouts/helper.blade.php](../resources/views/layouts/helper.blade.php) |
| Other role navigation | [layouts/partials](../resources/views/layouts/partials) |
| Role dashboards | [views/dashboard](../resources/views/dashboard) |
| Helper readiness | [helper/readiness.blade.php](../resources/views/helper/readiness.blade.php) |
| Breathing exercise behavior | [breathing-exercise.js](../resources/js/breathing-exercise.js) |
| Helper live chat | [helper/chat.blade.php](../resources/views/helper/chat.blade.php) and [helper/chat/show.blade.php](../resources/views/helper/chat/show.blade.php); confirm the route/controller return view |
| Chat referral modal/actions | [chat-referral.js](../public/js/chat-referral.js), [chat-workflow.js](../public/js/chat-workflow.js) |
| Moderator/shared duty screen | [moderator/schedules.blade.php](../resources/views/moderator/schedules.blade.php) |
| Adviser referral queue/detail | [adviser/referrals.blade.php](../resources/views/adviser/referrals.blade.php), [adviser/referral-detail.blade.php](../resources/views/adviser/referral-detail.blade.php) |
| Adviser emergency review | [adviser/emergency-detail.blade.php](../resources/views/adviser/emergency-detail.blade.php) |
| Seeker referrals/appointments | [seeker/referrals.blade.php](../resources/views/seeker/referrals.blade.php), [shared appointments](../resources/views/partials/referral-appointments.blade.php) |
| Identity disclosure popup | [referral-identity-modal.blade.php](../resources/views/partials/referral-identity-modal.blade.php) |
| Professional referral/case detail | [professional/referral-detail.blade.php](../resources/views/professional/referral-detail.blade.php), [professional/case-detail.blade.php](../resources/views/professional/case-detail.blade.php) |
| Shared completion form | [professional-completion.blade.php](../resources/views/partials/professional-completion.blade.php) |
| Shared referral presentation | [referral-recommendation.blade.php](../resources/views/partials/referral-recommendation.blade.php), [referral-ui-styles.blade.php](../resources/views/partials/referral-ui-styles.blade.php) |

## 5. Common technical questions at sagot

**“Ano ang technology stack?”**

PHP/Laravel 12 backend; Blade templates with JavaScript, Alpine, and Tailwind; Vite for assets; Chart.js for charts; Laravel Echo/Reverb integration for real-time communication. Dependencies: [composer.json](../composer.json) and [package.json](../package.json). Deployment setup: Render application and Neon PostgreSQL, configured through environment variables. Voice work is deferred from the current demo scope.

**“Saan ang database relationships?”**

Open [Session](../app/Models/Session.php), [Helper](../app/Models/Helper.php), [Referral](../app/Models/Referral.php), and [User](../app/Models/User.php). Search `belongsTo`, `hasMany`, `hasOne`, and `$table`. Schema lives in [migrations](../database/migrations); environment connection definitions live in [config/database.php](../config/database.php).

**“Paano ninyo pinoprotektahan ang accounts at records?”**

Show layers, not just hidden buttons:

1. [bootstrap/app.php](../bootstrap/app.php): middleware registration.
2. [EnsureUserRole](../app/Http/Middleware/EnsureUserRole.php) and [EnsureActiveAccount](../app/Http/Middleware/EnsureActiveAccount.php): role and account restrictions.
3. [AppServiceProvider](../app/Providers/AppServiceProvider.php): registered Gates/policies; [SeekerRecordPolicy](../app/Policies/SeekerRecordPolicy.php), [HelperRecordPolicy](../app/Policies/HelperRecordPolicy.php), [AdviserScope](../app/Services/AdviserScope.php): record access.
4. [LoginRequest](../app/Http/Requests/Auth/LoginRequest.php): normalized login identity and rate limiting; [User](../app/Models/User.php): hashed password cast.
5. [AuditLogger](../app/Services/AuditLogger.php), [SupportAudit](../app/Services/SupportAudit.php): recorded actions.

> “May server-side authorization po kahit direktang tawagin ang endpoint. Hindi UI visibility lang ang protection.”

Moderator operational access does not mean transcript access. Show [ChatController](../app/Http/Controllers/ChatController.php), `getTranscript`, and [AdviserTranscriptAccess](../app/Services/AdviserTranscriptAccess.php). Private live-chat channel access is restricted to active participants in [routes/channels.php](../routes/channels.php).

**“Anonymous ba talaga?”**

Use the precise term **pseudonymous**: may alias/pseudo identifier, at may controlled linking kapag may authorized identity submission/release. Huwag sabihing impossible nang ma-link ang identity. Show [IdentityVaultService](../app/Services/IdentityVaultService.php), `cipher`, `storeForReferral`, `releaseForReferral`, `readForReferral`, plus [config/identity_vault.php](../config/identity_vault.php). May separate `identity_vault` connection, encrypted payload handling, and access audit. Ang actual physical separation ng databases ay depende sa deployed configuration.

Huwag ding sabihing lahat ng database fields ay encrypted: halimbawa, ang [Message model](../app/Models/Message.php) ay walang encrypted cast para sa `message_text`.

**“Bakit may email kahit alias ang Seeker?”**

Staff notification emails are separate from Seeker private recovery email. Open [AccountRecoveryService](../app/Services/AccountRecoveryService.php) and [User](../app/Models/User.php): encrypted recovery email and lookup hash, separate from displayed alias. Password reset needs a configured, verified recovery path; an internal `.local` address is not a deliverable inbox.

**“Bakit minsan delayed ang email o notification?”**

Check [StaffMailService](../app/Services/StaffMailService.php), the [scheduler](../routes/console.php), provider configuration, and Render startup logs. Stored/in-app notification, popup display, provider delivery, and actual inbox receipt are different stages. A provider's delivered status does not prove the user opened or saw the email.

**“Saan ang chat real-time configuration?”**

Open [resources/js/bootstrap.js](../resources/js/bootstrap.js), [chat.js](../resources/js/chat.js), [config/broadcasting.php](../config/broadcasting.php), [config/reverb.php](../config/reverb.php), and [routes/channels.php](../routes/channels.php). May polling fallback; actual WebSocket availability depends on deployment. Huwag mag-claim na tested na ang live connection dahil lang pumasa ang local tests.

**“Paano pinipigilan ang duplicate popup?”**

Open [notification-presentation.js](../public/js/notification-presentation.js), then search `localStorage`, `storageKey`, and `claim`. Ito ang shared tracking na ginagamit ng [important-notice.js](../public/js/important-notice.js) at ibang popup handlers. Browser/user presentation tracking prevents repeated display across pages/tabs in that browser. Hindi ito guarantee na once globally across every device. The bell/inbox can retain the unread item independently.

## 6. Computation reviewer

### Competency score: hindi ito ang matching score

Source: [CompetencyRubric](../app/Services/CompetencyRubric.php), `CRITERIA`, `score`, `level`.

Each criterion is rated **1–5**, then:

`score = listening × .25 + empathy × .25 + respect/professionalism × .20 + ethics × .20 + referral judgment × .10`

Example: ratings `4, 5, 4, 5, 4` give **4.45/5**, or **89%** when expressed as `score / 5 × 100`. The rubric rounds to two decimal places.

Level thresholds: 4.5 Outstanding; 3.5 Very Good; 2.5 Satisfactory; 1.5 Needs Improvement; lower Unsatisfactory. Actual saving/evaluation handling: [AdviserEvaluationService](../app/Services/AdviserEvaluationService.php). Historical aggregate/normalization: [HelperCompetencyHistory](../app/Models/HelperCompetencyHistory.php).

### KPI definitions na madalas itanong

Source: [SessionPerformance](../app/Services/SessionPerformance.php), `summarize`, `average`, `ratio`; full definitions in [ROLE_KPI_VALIDATION.md](ROLE_KPI_VALIDATION.md).

| Metric | Meaning sa implementation |
| --- | --- |
| Average Helper response | Request submission, or legacy creation date, hanggang first message ng assigned Helper; average valid intervals in minutes |
| Average waiting time | Queue entry hanggang actual session start; hindi age ng still-waiting requests |
| Average session duration | Actual start hanggang end of completed/evaluated sessions with valid timestamps |
| Completion rate | Completed/evaluated sessions among started sessions in the selected cohort |
| Average competency | Average eligible normalized evaluation scores /5 |
| Satisfaction | Eligible Seeker evaluation scores normalized from 1–10 to 1–5 using `1 + (score - 1) × 4 / 9` |

No data should not be explained as a measured zero. Open `average`, `ratio`, and `display` for null/sample handling. [AnalyticsPeriod](../app/Services/AnalyticsPeriod.php) handles period filtering; historical period KPIs and current operational counts may use different scopes. Adviser data scope is not automatically the entire system.

## 7. Deployment, background work, at demo data

| Tanong | Buksan |
| --- | --- |
| “Paano na-deploy?” | [Dockerfile](../Dockerfile), [docker/start.sh](../docker/start.sh), [docker/nginx.conf](../docker/nginx.conf), [Render guide](RENDER_DEPLOYMENT.md) |
| “Ano ang nagpapatakbo ng reminders/matching?” | [routes/console.php](../routes/console.php); scheduler process in `docker/start.sh` |
| “Saan ang automatic session ending?” | [SessionDurationService](../app/Services/SessionDurationService.php), scheduled tasks in `routes/console.php` |
| “Paano kapag disconnect?” | [SessionReconnectionController](../app/Http/Controllers/SessionReconnectionController.php), [SessionReconnectionService](../app/Services/SessionReconnectionService.php) |
| “Saan galing ang demo records?” | [SeedDefenseDemo command](../app/Console/Commands/SeedDefenseDemo.php), [DefenseDemoData service](../app/Services/DefenseDemoData.php), [demo import guide](DEFENSE_DEMO_DATA.md) |
| “Saan ang backup/health/audit monitoring?” | [BackupRestoreController](../app/Http/Controllers/Admin/BackupRestoreController.php), [SystemHealthMonitor](../app/Services/SystemHealthMonitor.php), [AuditLogController](../app/Http/Controllers/Admin/AuditLogController.php) |

Show `.env.example` for configuration structure, not the actual `.env` or database URLs/passwords/API keys. Para sa panel walkthrough, read-only navigation and test output are enough; huwag mag-run ng database reset o re-import habang ginagamit ang online demo.

## 8. Tests na maipapakita bilang supporting evidence

| Workflow | Tests na puwedeng buksan |
| --- | --- |
| Login normalization / throttling | [LoginRateLimitingTest](../tests/Feature/Auth/LoginRateLimitingTest.php) |
| Account creation/reactivation | [UserManagementTest](../tests/Feature/Admin/UserManagementTest.php), [AccountSafeguardsTest](../tests/Feature/AccountSafeguardsTest.php) |
| Screening / Prefer not to say | [SeekerWorkflowSecurityTest](../tests/Feature/SeekerWorkflowSecurityTest.php), [ScreeningFollowUpTest](../tests/Feature/ScreeningFollowUpTest.php) |
| Duty/readiness/queue eligibility | [ModeratorReadinessSchedulingTest](../tests/Feature/ModeratorReadinessSchedulingTest.php), [HelperOperationalUiTest](../tests/Feature/HelperOperationalUiTest.php), [ModeratorIncomingQueueTest](../tests/Feature/ModeratorIncomingQueueTest.php) |
| Referral consent/appointments | [ReferralDeliveryWorkflowTest](../tests/Feature/ReferralDeliveryWorkflowTest.php), [SeekerReferralDecisionUiTest](../tests/Feature/SeekerReferralDecisionUiTest.php), [ReferralAppointmentTest](../tests/Feature/ReferralAppointmentTest.php) |
| Emergency distribution/resolution | [EmergencyAllAdvisersTest](../tests/Feature/EmergencyAllAdvisersTest.php), [EmergencyResolutionSyncTest](../tests/Feature/EmergencyResolutionSyncTest.php) |
| Identity access controls | [IdentityVaultTest](../tests/Feature/IdentityVaultTest.php), [ReferralIdentityFormTest](../tests/Feature/ReferralIdentityFormTest.php) |
| Professional → Adviser completion | [ReferralCompletionReviewTest](../tests/Feature/ReferralCompletionReviewTest.php) |
| KPI math / scope | [RoleKpiTest](../tests/Feature/RoleKpiTest.php), [DashboardAnalyticsTest](../tests/Feature/DashboardAnalyticsTest.php) |
| Chat/referral browser logic | [chat-workflow.test.cjs](../tests/js/chat-workflow.test.cjs), [chat-referral.test.cjs](../tests/js/chat-referral.test.cjs) |
| Popup behavior | [important-notice.test.cjs](../tests/js/important-notice.test.cjs), [notification-presentation.test.cjs](../tests/js/notification-presentation.test.cjs) |

Examples to run **locally** from the project root:

```powershell
php artisan route:list --path=referrals
php artisan test --filter=ReferralCompletionReviewTest
php artisan test --filter=EmergencyResolutionSyncTest
php artisan test --filter=RoleKpiTest
node --test --test-isolation=none tests/js/important-notice.test.cjs
npm run build
```

[phpunit.xml](../phpunit.xml) configures SQLite `:memory:` for tests. Do not repoint tests to the online database. This reviewer task did not rerun the application test suite; use the latest actual command output when presenting results.

## 9. Practice bago ang defense

1. **“Show the matching algorithm.”** `Ctrl+P` → `HelperMatchingService.php` → `rankHelpers` → `Helper::calculateMatchingScore` → `HelperEligibilityService::reasons`.
2. **“Show consent before identity disclosure.”** `ReferralManagementService::processConsent` → `IdentityVaultService::storeForReferral` → `releaseForReferral` → `readForReferral`.
3. **“Show emergency notification to Advisers.”** `HelperSessionController::flagEmergency` or `SeekerWorkflowService::screen` → `EmergencyStaffNotifications::deliver` → `Notification` → browser notice scripts.
4. **“Show how the case is completed.”** `ReferralCompletionController::submit` → `ReferralCompletionService::submit` → `review` → emergency's separate `resolve` action.
5. **“Prove the response-time KPI.”** `SessionPerformance::summarize` → first Helper-message query and `$response` → `RoleKpiTest`.

Kapag hindi agad makita: sabihin, **“I-trace ko po mula sa route para maipakita ko ang actual handler at validation.”** Search the visible button label in `resources/views`, inspect its form action or JavaScript request URL, find that route, then open the controller method. Mas useful ito kaysa mag-memorize ng line numbers na puwedeng magbago.
