# REQ-006: Browser favicon matches Dial

Status: preview assets verified; publication pending
Risk: L1 asset and metadata change, no account data change

## Outcome
Replace the default blue Wallos tab icon on themed pages with an original warm-white and black W monogram. It should read clearly at 16px, have a sharp vector source, and use distinct asset paths with a versioned URL so an existing browser or service worker does not keep displaying the old icon.

## Scope and acceptance
Update the shared header to reference SVG and 16px/32px PNG variants; bundle those assets in both the private image and generic public candidate. Verify returned content types, valid image dimensions, page link URLs, and appearance in a browser tab. Keep the public package free of private branding and uploaded logos. The PWA manifest and mobile install icons are outside this browser-tab change.

## Verification
The vector and raster variants share the same original geometry. Generated 16px/32px PNG dimensions and a 4× inspection sheet were checked. Both local preview servers returned HTTP 200 with the expected `image/svg+xml` and `image/png` content types. The header references new versioned asset URLs. The browser automation extension draws its own badge over controlled tab icons, so the final native tab-strip rendering remains a user visual check after deployment.
