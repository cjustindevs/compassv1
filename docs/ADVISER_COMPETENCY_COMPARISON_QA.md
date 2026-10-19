# Adviser competency comparison and training checks

## Where to test

Sign in as an Adviser and open **Dashboard → Helper competency comparison**.
Only the current Adviser's supervised Helpers appear. No new migration or dummy-data import is required.

## Comparison

1. Choose a dashboard date range. The comparison uses evaluation dates in Philippine Time.
2. Compare **Overall competency**, then each of the five individual skills. Check that the exact score and bar agree on a fixed 0–5 axis.
3. Check **Lowest score first**, **Highest score first**, and **Helper name**. Unevaluated Helpers follow rated Helpers when sorting by score.
4. With more than eight Helpers, use **Next** and **Previous**. The dates, selected skill, and sorting must be retained. Changing dates or comparison controls returns to page one.
5. Check two Helpers with the same name: their Helper numbers, evidence, and actions must remain separate.
6. Expand **View skill breakdown and evaluations**. Check average skill scores, weights, the latest evaluation date, evaluation count, and links to the documented evaluations. Up to five recent evaluations are shown; Helper history contains the remainder.
7. A Helper with only one evaluation must show **Based on 1 evaluation**. A Helper with none in the period shows **Not evaluated**, never zero.

The lowest mean skill is identified even when the overall score is strong. Any mean skill below 2.5/5 produces a training-review indicator, using the existing rubric thresholds. The coverage summary checks all five skills, independent of the selected comparison skill.

## Calculation evidence

- Use the current versioned rubric: Listening 25%, Empathy 25%, Respect and Professionalism 20%, Ethics 20%, Referral Judgment 10%.
- For ratings **5, 4, 3, 2, 1**, the weighted evaluation score is **3.35/5**. With another evaluation of **5 in every skill**, the Helper's average is **4.18/5**.
- Correct the first evaluation's Listening rating from 5 to 4 using the existing correction process. Its result becomes **3.10/5**, and the two-evaluation mean becomes **4.05/5**. The evaluation count stays **2**; correction-history snapshots are not extra observations.
- An overall score of **4.60/5** can still contain Referral Judgment **1/5**. The graph must retain the skill-gap indicator.
- Legacy/other-rubric evaluations are counted separately and remain in Helper history. They are not converted into current-rubric skill comparisons.
- Current-rubric records require a concluded session, reviewed report, matching evidence, valid independent ratings, and a consistent weighted total. Incomplete, future, or out-of-period records are excluded.
- The general competency KPI retains its existing definition and can include normalized historical results. Its sample can differ from this current-rubric graph; the graph's definition explains this.

## Training handoff

1. Click **Recommend training** for a Helper. For Overall, the weakest skill is suggested; when comparing an individual skill, that skill is suggested.
2. Confirm the training form opens with the correct Helper, skill, and supporting evaluation selected. The suggested evidence is an actual low-rated evaluation for that skill.
3. Enter a reason, activity, priority, and optional due date. Submit. Verify the Helper receives the existing training notification.
4. Retry while leaving a required field blank. The entered values and selection must remain available after validation.
5. Return to the graph. **Review existing training** replaces the recommendation link for that skill. A second ongoing activity for the same Helper and skill is blocked even if a different evaluation is submitted.
6. As Helper, open **Training recommendations → Acknowledge and start**, then submit completion evidence.
7. Confirm the activity stays awaiting Adviser review; Helper completion alone does not complete supervision. The Adviser receives the existing update notification.
8. As Adviser, review the evidence. **Confirm reviewed** ends that activity; **Return for follow-up** keeps it in progress. Training completion does not modify competency ratings. Subsequent documented evaluations show later performance.

## Permissions and layout

- Another Adviser's Helpers, evaluation selections, and training filters must not be accessible by changing URL IDs.
- Retain the existing dashboard KPI cards, other charts, and role navigation.
- Manually verify desktop and mobile widths, long Helper names, keyboard focus, expanded evidence, and pagination. Exact values and rubric labels must convey meaning without color alone.

## Automated verification

`tests/Feature/AdviserCompetencyComparisonTest.php` covers weighted averages, corrections, weak-skill detection, missing/legacy/invalid evidence, date boundaries, filtering, sorting, pagination, same-name Helpers, access restrictions, form preselection, retained validation input, duplicate training, notifications, and the Helper-to-Adviser completion flow.

The relevant dashboard, KPI, Adviser, Moderator, navigation, and training regression run passed **77 tests (615 assertions)**. The production asset build and Blade compilation also passed.

No browser surface was available for an interactive visual preview in this environment. Automated rendering and Blade compilation are checked separately; desktop/mobile appearance still needs manual confirmation after deployment.
