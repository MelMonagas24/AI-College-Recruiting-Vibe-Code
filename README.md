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

## Roadmap

- **AI recommendations (next):** recommend only eligible colleges (verified active women's soccer program and confirmed NCAA division), and explain why each one fits the athlete's answers.
- **Finish partially verified records:** confirm 2026-27 costs for Penn State, Rollins, Western Washington, Susquehanna, Catholic University, and UChicago.
- **Net price guidance:** link each school's official Net Price Calculator so families can estimate cost after aid, instead of comparing their budget to published prices.
- **Yearly data refresh:** update costs each spring when schools publish the next academic year, and update enrollment when new federal data is released.
- **More precise matching:** use the detailed federal locale (for example, "City: Small" vs. "City: Large") and add more majors beyond the first five.
- **Admin review page:** let a reviewer edit college records and mark facts as verified without editing the JSON file.
- **More colleges and sports:** grow beyond the first 25 programs and add other women's sports.
- **Athletic context:** add conference strength, roster size, and coaching contacts from official athletics sites, without promising recruitment or scholarships.
- **Saved profiles:** optional accounts so athletes can save answers, once privacy protections are in place.
- **Deployment:** run database migrations and the college seeder as part of the AWS deployment.

CUA Busch School AI Vibe Coding Contest, Fall 2026
