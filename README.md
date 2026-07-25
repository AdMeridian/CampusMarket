# CampusMarket

CampusMarket is a student-focused, campus-specific marketplace platform. It allows students to securely buy, sell, and trade items with their peers. Built with a modern serverless architecture, CampusMarket ensures safety through campus email verification and provides a rich feature set including real-time messaging, push notifications, and AI-powered listing moderation.

## Key Features

- **Secure Campus Access:** Registration requires a verified campus email address.
- **User-to-User Marketplace:** Create listings with image uploads, browse categories, and search for items.
- **Real-time Chat & Handshakes:** Integrated messaging system to coordinate meetups and confirm deals.
- **Push Notifications:** Web Push API integration for instant alerts on messages and order updates.
- **AI Moderation:** Automated listing review using Google Gemini AI to maintain a safe environment.
- **Payments & Promotions:** Stripe integration for optional donations and promoting listings.
- **Admin Dashboard:** Comprehensive reporting, user moderation, and audit logs.

## Tech Stack

- **Framework / Runtime:** PHP deployed on [Vercel](https://vercel.com/) via `vercel-php`.
- **Database & Authentication:** [Supabase](https://supabase.com/) (PostgreSQL, Row-Level Security, Auth).
- **Payments:** [Stripe](https://stripe.com/).
- **Transactional Emails:** [Resend](https://resend.com/).
- **AI Integration:** Google Gemini API.
- **Testing:** [Playwright](https://playwright.dev/) for end-to-end testing.

## Getting Started (Local Development)

To run this project locally, you will need to have [Vercel CLI](https://vercel.com/docs/cli) installed, as well as [Supabase CLI](https://supabase.com/docs/guides/cli) for managing the database.

1. **Clone the repository:**
   ```bash
   git clone https://github.com/Carlm832/CampusMarket.git
   cd CampusMarket
   ```

2. **Install dependencies:**
   ```bash
   npm install
   ```

3. **Set up environment variables:**
   Copy `.env.example` to `.env` and fill in the required keys. 
   ```bash
   cp .env.example .env
   ```
   *See [DEPLOYMENT.md](DEPLOYMENT.md) for a detailed list of all required environment variables.*

4. **Start the local development server:**
   Use the Vercel CLI to run the PHP environment locally.
   ```bash
   npx vercel dev
   ```

## Deployment & Production

Deployment is handled automatically via Vercel when pushing to the `main` or `member-1` branches. 

For detailed instructions on setting up Supabase, configuring Stripe webhooks, and pre-launch security checklists, please refer to the **[Deployment Runbook (DEPLOYMENT.md)](DEPLOYMENT.md)**.

## Testing

End-to-end tests are written using Playwright. To run the test suite:

```bash
# Run tests in UI mode or standard mode
npm run test

# Run tests in CI mode
npm run test:ci
```

## License

ISC
