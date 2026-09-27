# Poster source and provenance

- `hero-backdrop.jpg`, `compare-backdrop.jpg`, `features-backdrop.jpg`, and `showcase-backdrop.png` were generated with OpenAI ImageGen for this release. They contain no application interface, text, or third-party logos.
- `posters.html` arranges those backgrounds, typeset copy, and the direct browser captures in `../../screenshots/`. The comparison screenshots remain exact pixels from the demo browser, scaled by CSS; no model was asked to invent or alter UI details.
- Render at a 1920×1080 browser viewport with `?kind=hero`, `dashboard`, `subscriptions`, or `features`. Render `?kind=social` at 1280×640 for the GitHub repository's social preview. The resulting files live one directory above.
- The dedicated showcase variants are `?kind=showcase-overview`, `showcase-subscriptions`, and `principles`. Overview and subscription panels contain exact synthetic-data browser captures from the real Wallos Dial container; the principles poster contains deterministic text and palette samples.
- The demo account was isolated from production. It used fictitious subscription names, prices, user details, and generated monogram logos. The raw captures are included so readers can inspect the UI without poster framing.
