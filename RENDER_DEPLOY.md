# Render Free Deployment

This API is prepared for Render using Docker.

## Important Free Limits

- Free web services sleep after 15 minutes without traffic.
- Waking a sleeping API can take about one minute.
- Free web service files are ephemeral. Uploaded files can disappear after restart/redeploy.
- Free Render Postgres databases expire after 30 days.

## Deploy API

1. Push this `boukkuk-api` repo to GitHub.
2. In Render, create a new Blueprint from this repo, or create a Web Service manually.
3. If using the Blueprint, Render reads `render.yaml`.
4. Set `APP_KEY` to:

   ```bash
   php artisan key:generate --show
   ```

5. Set `APP_URL` to your Render API URL after Render creates it:

   ```text
   https://your-api-name.onrender.com
   ```

6. After first deploy, seed demo data once from Render Shell:

   ```bash
   php artisan db:seed --force
   ```

Demo accounts use password `password`:

- `user@boukuk.test`
- `admin@boukuk.test`
- `provider@boukuk.test`

## Deploy Frontend

1. Push the `BoukKuk` frontend repo/folder to GitHub.
2. Create a Render Static Site.
3. Use no build command.
4. Set publish directory to the project root.
5. Replace local API URLs in:

   ```text
   assets/js/baseUrl.js
   assets/js/signup.js
   ```

   with your Render API URL.
