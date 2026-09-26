# REQ-003: Language switch in the Dial header

Status: verified in disposable instance; public publication pending
Risk: L2 (account preference update)
Delivery: included in the next unpublished Dial candidate

## Outcome
An authenticated user can switch between Simplified Chinese and English from the Dial header, and can return to Traditional Chinese or any Wallos-supported language. All pages then use Wallos's existing translation resources. The choice persists on refresh and in the user's profile.

## Scope and non-goals
Add a compact native selector in the shared header, a focused CSRF-protected endpoint that updates only the current user's language, and a small client script. Keep user-created names and category labels unchanged; these are data, not interface translations. No live production account language is changed for verification.

## Acceptance evidence
- Selector renders on dashboard, subscriptions, calendar, statistics, and settings; current language selected.
- Choosing `zh_cn` and `en` changes navigation and page labels, updates user.language and the existing language cookie, and survives reload.
- Unsupported language codes, missing authentication, invalid method, and invalid CSRF are rejected without changing the user.
- PHP/JS syntax, image build, and representative local browser flows pass.

## Constraints and version
Reuse Wallos `languages.php`, `validate_endpoint.php`, session, and cookie path conventions. No duplicate translation database. The selector must not affect subscription data. Candidate remains pre-publication `0.1.0`; release identity will be updated after final verification and approval.
