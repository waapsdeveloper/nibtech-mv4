# Frontend assets (Mix) – server build

## Do **not** run `npm run production` on the server

The Mix config expects **source** files in `resources/assets/` (JS, SCSS, CSS, img, plugins, switcher). That folder is **not** in the repo (only built output is). So on the server:

- **Do not run** `npm run production` (it will fail with “Module not found” for `resources/assets/...`).
- Use the **pre-built** assets that are already in the repo:
  - `public/assets/` (JS, CSS, img, plugins, etc.)
  - `mix-manifest.json` (in project root) if any view uses `mix()`.

Ensure your deploy includes `public/assets/` and `mix-manifest.json` from the repo (or from a build artifact). The app uses `asset('assets/...')`, which serves from `public/assets/`.

## When to run Mix

Run `npm run production` only where the **full source** exists:

- On a **local** or **CI** machine that has `resources/assets/` (with plain `.js`, `.scss`, `.css` and the rest).  
- Then commit and deploy the generated `public/assets/` and `mix-manifest.json`, or copy them to the server.

If `resources/assets/` is missing everywhere, you need to restore that source tree (e.g. from backup or another branch) before Mix can run.
