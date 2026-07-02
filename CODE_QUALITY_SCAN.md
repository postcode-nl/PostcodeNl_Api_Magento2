# Code quality and PHP 8.1-8.5 compatibility scan

## Scope

- Module: `postcode-nl/api-magento2-module`
- Scan date: 2026-06-12
- Runtime available in this environment: PHP 8.4.18

## Validation performed

1. `php -l` across every PHP file in the module
2. `composer validate --no-check-lock --no-check-publish`
3. Static review focused on PHP 8.1-8.5 compatibility risks and runtime errors
4. Follow-up code review after the fixes in this branch

## Result

The module is in good shape for PHP 8.1-8.5 after the fixes in this branch.

## Findings addressed in this branch

| Severity | File | Issue | Status |
| --- | --- | --- | --- |
| High | `Service/PostcodeApiClient.php` | Invalid JSON error path used `sprintf()` incorrectly, which could raise `ArgumentCountError` on PHP 8.1+ instead of the intended module exception. | Fixed |
| High | `Helper/ApiClientHelper.php` | ISO-2 country codes that do not map to ISO-3 could flow into a strictly typed method as `null`, causing `TypeError` on PHP 8.1+. | Fixed |
| Medium | `Controller/Adminhtml/Address/Api.php` | Missing or malformed admin request parameters could lead to PHP argument errors instead of a handled bad-request response. | Fixed |

## Notes

- The reported nullable-parameter deprecation is already addressed in this fork: `Helper/ApiClientHelper.php` uses an explicit nullable type for `$labelSuffix`.
- `composer validate` reports one metadata warning only: the package keeps a `"version"` field in `composer.json`. That is not a PHP 8.1-8.5 compatibility problem.
- This environment only provided PHP 8.4 for execution, so the compatibility conclusion is based on PHP 8.4 validation plus static review against known PHP 8.1-8.5 incompatibility patterns.

## Remaining concerns

No further actionable PHP 8.1-8.5 compatibility issues were identified during this scan.
