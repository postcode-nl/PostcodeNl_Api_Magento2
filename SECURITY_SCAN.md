# Security scan

## Scope

- Module: `postcode-nl/api-magento2-module`
- Scan date: 2026-06-12

## Review performed

1. Static review of request handling, outbound API usage, credential handling, and exception paths
2. Pattern scan for high-risk PHP functions and insecure legacy APIs
3. Follow-up code review after the fixes in this branch

## Findings addressed in this branch

| Severity | File | Issue | Status |
| --- | --- | --- | --- |
| High | `Service/PostcodeApiClient.php` | The module forwarded the incoming `HTTP_REFERER` header to `api.postcode.eu`, which could leak storefront or admin URLs and query strings to a third party. | Fixed by removing referer forwarding |
| Medium | `Controller/Adminhtml/Address/Api.php` | The admin endpoint accepted unchecked parameter shapes before dispatching them into typed service methods. | Fixed by validating presence and scalar input before dispatch |

## Reviewed items

- API credentials remain stored through Magento configuration and decrypted through Magento's encryptor.
- Cached status data uses Magento's serializer interface, not native PHP `unserialize()`.
- No dangerous command-execution helpers or similar high-risk sinks were found in the module source during the scan.

## Remaining concerns

No additional meaningful security issues were identified after the fixes in this branch.
