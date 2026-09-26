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
