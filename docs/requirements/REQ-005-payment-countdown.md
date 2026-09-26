# REQ-005: Upcoming payment countdown

Status: verified in both local previews; private deployment and publication pending
Risk: L1 display change, no billing or subscription data changes
Delivery: private Braun deployment and next unpublished Dial candidate

## Outcome
Show the calendar days until each upcoming payment beside its next payment date. Use the account language for the column heading and values. Today's payment has an explicit label; a past date is labeled overdue if it reaches this view. Payments due within three days receive restrained red emphasis.

## Scope and acceptance
Add one column to the existing dashboard ledger without changing the upcoming-payment query, rows, click handlers, or subscription records. Count whole calendar days in the Wallos server timezone. Check English, Simplified Chinese, and Traditional Chinese labels, day singular/plural, today, overdue, accessibility text, and no horizontal overflow at desktop widths. No mobile redesign.

## Delivery constraint
The private site needs a backup and verified container image before the deployment is switched. The public package remains unpublished until separately approved.

## Verification
PHP lint passed for both dashboard templates and headers in their preview containers. The populated dashboards displayed 5, 14, and 19 calendar days for the three upcoming payments on 2026-09-26. English and both Chinese locale headings and values rendered correctly. The tested 1712px and 1100px desktop viewports had no horizontal document overflow. A PHP date calculation check confirmed signed day values for yesterday, today, tomorrow, and three days ahead.
