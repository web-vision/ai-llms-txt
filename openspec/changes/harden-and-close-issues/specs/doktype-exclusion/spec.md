## ADDED Requirements

### Requirement: Site-configurable doktype exclusion list
The extension SHALL read an optional `llmsTxtExcludeDoktypes` site configuration value (comma-separated integer doktypes, matching the `llmsTxtKeywords` parsing convention) and exclude matching pages from llms.txt navigation output, while still descending into their children.

#### Scenario: Configured doktypes are excluded from output but their children are promoted
- **WHEN** a site configures `llmsTxtExcludeDoktypes: "254,199"` and the page tree contains a doktype-254 folder with two visible child pages
- **THEN** the folder itself does not appear in the generated llms.txt navigation, and its two children appear at the position the folder would have occupied

#### Scenario: Unconfigured sites keep current default exclusions unchanged
- **WHEN** a site has no `llmsTxtExcludeDoktypes` configured
- **THEN** navigation output excludes exactly the same doktypes as before this change (sysfolder, spacer, shortcut), with no behavior difference for existing installations

#### Scenario: Configured list extends rather than replaces safety-critical exclusions
- **WHEN** a site configures `llmsTxtExcludeDoktypes` with a custom list that does not include sysfolder
- **THEN** sysfolder pages are still excluded from output (their exclusion is not solely dependent on site configuration), while the additional configured doktypes are also excluded

#### Scenario: Exclusion and promotion apply at every tree depth, not just the top level
- **WHEN** a page tree contains an excluded-doktype folder at tree level 2 or deeper (a child of a visible top-level page, not a direct child of the root), with its own children beneath it
- **THEN** the folder is excluded from output at that depth too, and its children are promoted to the folder's position in its parent's child list — the batch-fetching path used for depth 2+ applies the same exclusion+promotion logic as the top-level path, not a weaker or absent one
