## ADDED Requirements

### Requirement: Localized title and description fields in single-page markdown output
When rendering a page as markdown (`.md` suffix / typeNum 1701) for a non-default language, `LlmsTxtController` SHALL display the values from that language's translated page record (`seo_title`, `title`, `description`) rather than falling back to the default-language record's values, whenever a translation exists.

#### Scenario: Translated seo_title and description are used for a translated page
- **WHEN** a page has a French translation with its own `seo_title` and `description` values, and a French visitor requests that page's `.md` output
- **THEN** the rendered markdown's heading and description use the French translation's values, not the default-language page's values

#### Scenario: Falls back to default language when no translation exists
- **WHEN** a page has no French translation record
- **THEN** the `.md` output for that page under the French language context falls back to the default-language `title`/`description`, matching current behavior

### Requirement: Bodytext content is preserved across mixed content element types
`HtmlCleanerService`/`MarkdownConverterService` SHALL preserve the bodytext of a content element that follows an image+text (e.g. `textmedia`) content element on the same page, rather than truncating or dropping it during HTML cleanup.

#### Scenario: Plain-text content element after an image+text element retains its content
- **WHEN** a page contains a `textmedia` content element followed by a plain `text` content element with non-empty bodytext
- **THEN** the generated markdown for that page includes the plain text content element's bodytext in full

#### Scenario: Documented negative result if unreproducible
- **WHEN** the described content-loss scenario is built against current `main` using realistic `fluid_styled_content` markup and does not reproduce
- **THEN** this requirement is recorded as satisfied by existing behavior, with the reproduction fixture kept as a permanent regression test rather than a fix being invented for a non-existent bug
