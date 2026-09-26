# REQ-004: Keep Dial readable on wide desktop displays

Status: verified in both previews; publication pending
Risk: L1 visual correction, no data or behavior change
Delivery: next unpublished public candidate and the existing private Braun deployment

## Outcome
The header and dashboard share a centered reading width on wide desktop windows. At ordinary desktop widths the current layout remains close to the approved design. Browser zoom does not produce horizontal overflow or misalign the date dial and payment table.

## Scope and non-goals
Adjust desktop CSS container width, max-width, gutter, and alignment in the shared theme stylesheet. Keep the existing dial marks, payment rows, logos, interactions, and all Wallos functions. No separate mobile redesign.

## Acceptance evidence
At desktop CSS viewport widths around 1420, 1920, and 2400 pixels, the header and dashboard have aligned left/right edges, the content caps near the reference design width, and the document has no horizontal overflow. Inspect the main dashboard, the timeline, payment table, and navigation. Verify the result in both the private preview and the generic public candidate.

## Constraints and delivery impact
CSS-only change, no database writes or migrations. The public package is still awaiting publication approval. The private site requires its usual backup and a new image before replacing the current image.

## Follow-up verification
The four main pages were measured at 1920, 1440, 1100, and 900px CSS widths. Only the dashboard showed a 1px horizontal overflow at 900px, traced to an invisible date tooltip near the end of the timeline. Aligning the last 12 tooltips to the right at widths up to 1100px removed it. The dashboard was then retested at 1100, 900, 800, 768, and 720px with no horizontal overflow in both private and public previews. Subscriptions, calendar, and statistics had no horizontal overflow at 800, 768, or 720px. Settings, profile, admin, and about were checked from 720 to 1920px. The add-subscription dialog retained visible Save/Cancel controls in a 1280×700 viewport. These are desktop-width and zoom-equivalent checks; a separate phone layout was not requested.
