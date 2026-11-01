# Runtime and memory profile

ContactPanda uses a small bootstrap and resolves feature classes only when WordPress invokes their callbacks.

## Supported profiles

- **Basic:** ordinary public, admin-list, AJAX, REST, and cron requests are designed for a PHP memory limit of **128 MB**. Large imports remain batched and resumable.
- **Family products:** Plus, Business, the Legacy Bridge, and Panda Seasons are designed for a PHP memory limit of **256 MB**.

## Context rules

- Public requests do not initialize administration pages, editors, importers, reports, or MCP handlers.
- Administration assets are loaded only for their own screens.
- AJAX, REST, and cron callbacks load the class that owns the callback, not the complete product.
- Selectors and background workers use explicit limits. Large data sets use pagination or repeated batches.
- Shared family classes are resolved through one class map and are guarded against duplicate initialization.
- Protected update metadata is cached briefly; repeated failures use exponential backoff and an explicit update check clears the cache.

## Diagnostics

Defining `CONTACTPANDA_MEMORY_PROFILING` as `true` records sanitized current and peak memory samples for administrators in the `contactpanda_runtime_profiles` option. No customer data is recorded.
