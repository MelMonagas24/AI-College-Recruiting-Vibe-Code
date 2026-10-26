# AI-College-Recruiting-Vibe-Code

**Live site:** http://ai-college-recruiting-env.eba-zyu3dbwm.us-east-2.elasticbeanstalk.com/

Female high school athletes navigating college recruiting often have to piece together information from recruiting platforms, college websites, coaches, parents, social media, and other resources to determine which college is actually the right fit for them. This project will explore an AI-powered college decision and recruiting assistant that learns what an athlete values in her overall college experience and helps her evaluate potential schools across factors such as athletics, academics, affordability, location, campus life, athletic-life balance, career opportunities, and personal priorities. Rather than only filtering schools or generating a generic match score, the AI will explain why schools may fit the individual athlete, identify important tradeoffs, and help her recognize questions or factors she may not have considered. The initial target user is a female high school athlete interested in competing in college, and success will mean creating a working application that can learn a user's priorities and use them to provide useful, personalized, and explainable college recommendations. 



## College Database

The app's college data lives in `database/data/colleges.json`: 25 women's college soccer programs researched from official sources on October 26, 2026. Each college records its verification status (verified, partially verified, or unverified), and each important fact keeps its official source link and academic year. Unknown values are left blank and never estimated. Costs are published prices before financial aid, not what a family will pay.

To add or update a college, edit the JSON file, then run:

```sh
php artisan migrate
php artisan db:seed --class=CollegeSeeder
```

## College Recommendations

After an athlete submits the questionnaire, the results page shows 3–5 colleges from the database. Answers are used only to build that page and are never saved. The app is a demo for fictional athlete profiles only.

1. **Eligibility:** only colleges with a verified active women's soccer program and a confirmed NCAA division can be recommended.
2. **Filters:** the athlete's division choice, and her major (colleges known not to offer it are removed; colleges where it is unknown stay and are flagged).
3. **Ranking:** budget, campus environment, size, and what matters most. Affordability counts the most. Published costs are prices before aid, so they are compared to the family's after-aid budget only to show how much grants or scholarships would need to cover.
4. **In-state vs. out-of-state:** the athlete's home state (question 10) decides which published price applies. A public college shows its in-state price only when she lives in the same state; otherwise, including athletes outside the U.S., it shows the out-of-state price. Private colleges charge everyone the same published price. Each college sets its own residency rules.
5. **Affordability warnings:** each result says whether the published cost is within, above, or possibly above the family's budget range, by how much, and that financial aid could change the actual cost. Unverified costs are labeled "Cost not verified" and are never called affordable. Scholarships are never estimated.
6. **Not used:** GPA, test scores, position, and club league, because the database has no admissions or roster data, and a club league cannot decide a player's NCAA division.
7. **Fewer than 3 matches:** the page shows only the colleges that fit and explains why, instead of adding poor matches.

The matching logic is in `app/Recommendations/CollegeMatcher.php`.

### AI explanations (optional)

Claude can write a short personalized explanation for each recommended college. Claude never chooses colleges: it only receives the matched colleges' verified facts and the athlete's preferences (no name, GPA, test scores, or home state; only whether the in-state or out-of-state price applies). Its answer is rejected if it mentions a college that was not recommended or a dollar amount that is not in the verified data.

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
