# AI-College-Recruiting-Vibe-Code

**Live site:** https://d2vj1l9jn4pl3p.cloudfront.net

Female high school athletes navigating college recruiting often have to piece together information from recruiting platforms, college websites, coaches, parents, social media, and other resources to determine which college is actually the right fit for them. This project will explore an AI-powered college decision and recruiting assistant that learns what an athlete values in her overall college experience and helps her evaluate potential schools across factors such as athletics, academics, affordability, location, campus life, athletic-life balance, career opportunities, and personal priorities. Rather than only filtering schools or generating a generic match score, the AI will explain why schools may fit the individual athlete, identify important tradeoffs, and help her recognize questions or factors she may not have considered. The initial target user is a female high school athlete interested in competing in college, and success will mean creating a working application that can learn a user's priorities and use them to provide useful, personalized, and explainable college recommendations. 



## College Database

The app's college data lives in `database/data/colleges.json`: 41 women's college soccer programs (13 in NCAA Division I, 13 in Division II, 15 in Division III). The first 25 were researched from official sources on October 26, 2026, and 16 more were added on November 7, 2026. Each college records its verification status (verified, partially verified, or unverified) and the date it was checked, and each important fact keeps its official source link and academic year. Unknown values are left blank and never estimated. Costs are published prices before financial aid, not what a family will pay.

**Added November 7, 2026** to fill gaps in affordable public colleges, Texas, the Midwest, the West Coast, and the South:

| Division | Colleges |
|---|---|
| I | Ohio State (OH), Texas A&M (TX), UC Irvine (CA), James Madison (VA), Utah (UT) |
| II | Grand Valley State (MI), Minnesota State Mankato (MN), Central Missouri (MO), Midwestern State (TX), UT Dallas (TX), Western Oregon (OR) |
| III | UW-La Crosse (WI), SUNY Geneseo (NY), Christopher Newport (VA), Trinity University (TX), UC Santa Cruz (CA) |

How each new college was verified:

- **Women's soccer and NCAA division:** the official athletics site's 2026 schedule or schedule announcement, and its conference. UT Dallas now competes in Division II (Lone Star Conference).
- **Published cost:** the college's own 2026-27 cost of attendance page, at the in-state and out-of-state rates. When a page leaves out books, travel, or personal costs, the college is marked as not including every expense. Texas A&M's 2026-27 cost is only available through an online calculator, so it is recorded as unverified rather than estimated.
- **Enrollment, majors, admissions, and average net price:** the U.S. Department of Education's College Scorecard (Fall 2024 enrollment and admissions; bachelor's programs in federal field-of-study data; 2024-25 average net price).
- **Merit scholarships:** not verified yet for the new colleges, so they show as "Unknown".

To add or update a college, edit the JSON file, then run:

```sh
php artisan migrate
php artisan db:seed --class=CollegeSeeder
```

## College Recommendations

After an athlete submits the questionnaire, the results page sorts colleges from the database into two groups. Answers are used only to build that page and are never saved. The app is a demo for fictional athlete profiles only.

### Strong Matches vs. Other Options to Consider

Every college that passes the division and major filters is placed in exactly one group:

| | **Strong Matches** | **Other Options to Consider** |
|---|---|---|
| **Meaning** | Meets the athlete's division, major, size, and budget, each confirmed by verified data. | In her division and could be worth exploring, but misses or can't confirm at least one of those criteria. |
| **Major** | Confirmed in federal degree data. | Not confirmed ("We couldn't confirm that it offers…"). |
| **Size** | Verified enrollment is in her chosen size range. | Wrong size, or enrollment not verified. |
| **Budget** | Published cost at her correct in-state or out-of-state rate is within her budget range. Average net price never counts. | Above budget (shows by how much, and that financial aid could close some or all of the gap), or cost not verified. |
| **Card shows** | A green "Strong Match" label and what it matches. | An orange label with the number of tradeoffs, and every tradeoff listed. |
| **Order** | Best overall score first. | Fewest tradeoffs first. Among colleges with the same number: budget confirmed by published cost, then potentially affordable after aid, then the rest; then best score. |

- **Answers that turn a check off:** "Undecided" or "Other" for major and "No preference" for size are not checked, and the page says so.
- **"Unsure" budget:** affordability is not checked or confirmed. Colleges are not penalized for unknown costs, but Strong Match cards say "Budget not checked", and each card explains that the cost was not compared with a budget.
- **"$45,000+" budget:** it has no upper limit, so any verified published cost counts for the budget check. The card never calls an expensive college "within budget": it shows the actual published cost, labels it "Open-ended budget", and says the family decides whether it is affordable.
- **Campus setting and academic fit** only change the order inside a group. They never move a college between groups. A setting mismatch appears as "Also note".
- **No Strong Matches:** the page says so clearly and lists the closest alternatives, each with its tradeoffs. If fewer than 3 alternatives meet at least half of her preferences, the next closest are added (up to 3) and labeled as meeting fewer than half.
- **Full cards and expandable cards:** up to 5 Strong Matches and 3 Other Options (5 when there are no Strong Matches) are shown as full cards. The rest are in "More strong matches" and "More options to consider" lists that expand when selected, so no college is hidden.

### Published cost and federal average net price

Each card shows two different numbers, clearly labeled with their years:

- **Published cost, 2026-27 (before financial aid):** from each college's official cost page, at the athlete's in-state or out-of-state rate. This is the only number that can confirm a budget match.
- **Federal average net price, 2024-25 (after grants and scholarships):** from the U.S. Department of Education's [College Scorecard](https://collegescorecard.ed.gov/) (October 2026 data release, retrieved November 6, 2026). It is the average for full-time first-year students who received federal financial aid. At public colleges it covers only students who paid in-state tuition. It is not a personalized estimate or a scholarship guarantee, and students who did not receive federal aid are not included.

Every card's cost details get one label:

| Label | When |
|---|---|
| **Within budget (published cost)** | The verified published cost is within the budget. |
| **Potentially affordable after aid** | The published cost is above budget or unverified, but the federal average applies to her and is at or below the top of her budget. |
| **Above budget** | The published cost is above budget, and so is the federal average when it applies. |
| **Can't compare** | No verified cost and no applicable average, or a federal average that doesn't apply to her: a public college outside her home state (the average covers in-state students only), or an athlete living outside the U.S. (the average covers U.S. federal aid recipients). |
| **Not compared** | Her budget is "Unsure" or the open-ended "$45,000+". The average is still shown for information. |

- **Never a Strong Match:** "Potentially affordable after aid" keeps the published cost as a tradeoff, so the college stays under Other Options.
- **Ranking boost, not a bypass:** potentially affordable colleges get at least 75% of the budget credit and rank ahead of other colleges with the same number of tradeoffs. They still must be in her division, must not be known to lack her major, and must meet at least half of her preferences to be shown.
- **More cost details** (expandable): the average paid by families earning over $110,000, the number of students the average covers, the source link, and the college's Net Price Calculator. A calculator link is shown only when the college reported it to the U.S. Department of Education and the link worked when checked; otherwise the card points to the college's financial aid website.

### How colleges are filtered and ranked

1. **Eligibility:** only colleges with a verified active women's soccer program and a confirmed NCAA division can be recommended.
2. **Filters:** the athlete's division choice, and her major (colleges known not to offer it are removed; colleges where it is unknown stay and are flagged).
3. **Ranking:** budget, campus environment, size, and what matters most. Affordability counts the most. Published costs are prices before aid, so they are compared to the family's after-aid budget only to show how much grants or scholarships would need to cover.
4. **In-state vs. out-of-state:** the athlete's home state (question 10) decides which published price applies. A public college shows its in-state price only when she lives in the same state; otherwise, including athletes outside the U.S., it shows the out-of-state price. Private colleges charge everyone the same published price. Each college sets its own residency rules.
5. **Affordability warnings:** each result says whether the published cost is within or above the family's budget range (or, for "$45,000+", shows the cost for the family to judge), by how much, and that financial aid could change the actual cost. Unverified costs are labeled "Cost not verified" and are never called affordable. Scholarships are never estimated. See "Published cost and federal average net price" above for how the federal average is used.
6. **Academic fit:** an SAT or ACT score is compared with a college's middle 50% only when that range comes from the college's 2025-26 Common Data Set and at least 25% of first-year students submitted that test. Within or above the range earns full credit, and below it earns 75%. Academic fit can change the order of colleges but never decides whether a college can appear, and athletes without scores are never penalized. SAT and ACT are never converted. GPA is shown only as context, because colleges report it on different scales. The 28 colleges without a Common Data Set in our research (12 of the original 25 and all 16 added in November) show federal College Scorecard data (Fall 2024), which is never used for scoring. Admission rates are shown as facts, never as an athlete's chances.
7. **Not used:** position and club league, because the database has no roster data, and a club league cannot decide a player's NCAA division.
8. **Fewer than 3 colleges:** when the division and major leave fewer than 3 colleges, or when there is a Strong Match but few other colleges meet her preferences, the page explains why instead of adding poor matches.

The matching logic is in `app/Recommendations/CollegeMatcher.php`.

### AI explanations (optional)

Claude can write a short personalized explanation for each recommended college. Claude never chooses colleges: it only receives the matched colleges' verified, non-cost facts and the athlete's preferences (no name, GPA, test scores, home state, or budget). Explanations are written for the first 5 colleges on the page only.

**Cost stays out of AI text.** Claude never receives published costs, budgets, net prices, or aid details. All cost and affordability information appears only in the verified cost details on each card. When cost is a tradeoff, Claude may only end with "Check the cost details below."

**Each explanation is checked on its own** (`app/Recommendations/ClaudeRecommendationExplainer.php`). An explanation is rejected if it:

- names another college in the database, by full or shortened name (place names like "Maryland" or "Tampa" are allowed),
- mentions money in any form ("$", "dollars", "51K"), or words about cost, budget, or aid,
- uses a number of 100 or more that isn't in the facts sent for that college,
- predicts admission or labels a college a reach, match, target, or safety,
- calls an Other Option a perfect, ideal, or great fit, or says it meets all of her preferences.

A rejected explanation hides only that college's explanation; the others still appear. Rejected colleges are retried once, with the reason they were rejected, so a results page makes at most 2 AI requests. Each rejection is logged with the college and the rule it broke, never with athlete answers.

Without an API key, the app works normally and labels explanations as unavailable. To turn them on:

1. Create an API key at [platform.claude.com](https://platform.claude.com) (set a spending limit there).
2. Add it to your local `.env` file (never to `.env.example` or any committed file):

   ```sh
   ANTHROPIC_API_KEY=sk-ant-...
   ANTHROPIC_MODEL=claude-opus-5-5
   ```

3. Run `php artisan config:clear` and restart `php artisan serve`.

The automated tests always run with the key blank, so they never call the AI or cost money.

## Testing Locally

```sh
composer install
php artisan migrate
php artisan db:seed --class=CollegeSeeder
php artisan test
php artisan serve
```

Then open http://localhost:8000, click **Get Started**, and fill in the questionnaire with a fictional athlete.

## Deploying to AWS Elastic Beanstalk

The app runs on Elastic Beanstalk (PHP 8.4 or newer on Amazon Linux 2023) behind CloudFront.

- **Database:** the predeploy hook `.platform/hooks/predeploy/01_build_college_database.sh` rebuilds a SQLite database from `database/data/colleges.json` on every deployment and whenever AWS launches a replacement server. The app only reads college data and never saves athlete answers, so nothing is lost when it is rebuilt. If fewer than 25 colleges load, the deployment stops and the previous version keeps running.
- **Configuration updates:** changing an environment property (for example, adding `ANTHROPIC_API_KEY`) makes Elastic Beanstalk set the app up again without running `.platform/hooks`. The configuration hook `.platform/confighooks/predeploy/01_build_college_database.sh` runs the same database script then, so the college list is never lost. Without it, the questionnaire page still loads but submitting it shows a 500 error.
- **Build the upload ZIP from Git** so it never contains `.env`, the local database, or `vendor/`:

  ```sh
  git archive -o deploy.zip HEAD
  ```

- **Environment properties** (set in the Elastic Beanstalk console, never in files): `APP_KEY`, `APP_URL` (the public HTTPS CloudFront address), and later `ANTHROPIC_API_KEY`.
- **CloudFront** must allow POST requests, forward cookies, not cache pages, and redirect HTTP to HTTPS.

## Roadmap

- **Connect AI explanations:** add an API key, then review sample explanations with fictional profiles before the demo.
- **Finish partially verified records:** confirm 2026-27 costs for Penn State, Rollins, Western Washington, Susquehanna, Catholic University, and UChicago.
- **Net price guidance:** link each school's official Net Price Calculator so families can estimate cost after aid, instead of comparing their budget to published prices.
- **Yearly data refresh:** update costs each spring when schools publish the next academic year, and update enrollment when new federal data is released.
- **More precise matching:** use the detailed federal locale (for example, "City: Small" vs. "City: Large") and add more majors beyond the first five.
- **Admin review page:** let a reviewer edit college records and mark facts as verified without editing the JSON file.
- **More colleges and sports:** grow beyond the first 25 programs and add other women's sports.
- **Athletic context:** add conference strength, roster size, and coaching contacts from official athletics sites, without promising recruitment or scholarships.
- **Saved profiles:** optional accounts so athletes can save answers, once privacy protections are in place.
- **Tuition exchange programs:** verify regional discounts such as the Western Undergraduate Exchange (WUE) and show them only for eligible states.
- **Admissions and roster context:** add verified admissions ranges and roster data so GPA, test scores, and position can be used responsibly.
- **Faster results:** generate AI explanations in the background so the results page loads immediately.
- **Deployment:** run database migrations and the college seeder as part of the AWS deployment, and store the API key in AWS environment settings, never in the code.

CUA Busch School AI Vibe Coding Contest, Fall 2026
