# Poster source and provenance

- `hero-backdrop.jpg`, `compare-backdrop.jpg`, and `features-backdrop.jpg` were generated with OpenAI ImageGen for this release. They contain no application interface, text, or third-party logos.
- `posters.html` arranges those backgrounds, typeset copy, and the direct browser captures in `../../screenshots/`. The comparison screenshots remain exact pixels from the demo browser, scaled by CSS; no model was asked to invent or alter UI details.
- Render at a 1920×1080 browser viewport with `?kind=hero`, `dashboard`, `subscriptions`, or `features`. Render `?kind=social` at 1280×640 for the GitHub repository's social preview. The resulting files live one directory above.
- The demo account was isolated from production. It used fictitious subscription names, prices, user details, and generated monogram logos. The raw captures are included so readers can inspect the UI without poster framing.
