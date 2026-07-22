## ADDED Requirements

### Requirement: Site-configurable cache lifetime, disabled by default
`LlmsTxtGeneratorService` SHALL read an optional `llmsTxtCacheLifetime` site configuration value (integer seconds) to control how long generated llms.txt content is cached, defaulting to `0` (caching disabled) when unconfigured.

#### Scenario: Configured lifetime enables caching for that duration
- **WHEN** a site configures `llmsTxtCacheLifetime: 900`
- **THEN** generated llms.txt content is cached for 900 seconds before being regenerated on the next request past that window

#### Scenario: Unconfigured sites have no caching
- **WHEN** a site has no `llmsTxtCacheLifetime` configured
- **THEN** llms.txt content is regenerated on every request and nothing is written to or read from the cache

#### Scenario: A configured lifetime of zero disables caching
- **WHEN** a site configures `llmsTxtCacheLifetime: 0`
- **THEN** llms.txt content is regenerated on every request and nothing is written to or read from the cache, identical to the unconfigured state
