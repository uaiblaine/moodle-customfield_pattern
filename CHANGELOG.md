# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## Unreleased

### Added

- **A public API, `\customfield_pattern\api`, for consumers that keep values outside
  `customfield_data`**: configuration checks (`config_errors()`), `validate()`, `normalise()`,
  `format()`, `presets()`, `masks()`, `input_attributes()` and `js_init()`, each a function of a
  documented configuration array, versioned by `api::CONFIG_VERSION`. The field controller's
  configuration validation, its value API and the data controller's input go through it, so there
  is one implementation of every rule.
- The configuration form refuses an unknown format or storage format.

### Security

- **The regular expression runs bounded.** An administrator's expression runs on user input, so it
  is evaluated with a lowered PCRE match limit (100,000) and the JIT off, both restored after the
  call; an expression that cannot answer within that bound refuses the value with "The value could
  not be checked against the pattern." instead of reading as a mismatch. The settings refuse an
  expression longer than 512 characters, or one that already gives up on a probe value (a smoke
  test for catastrophic backtracking such as `(a+)+$`).

### Fixed

- **The mask now works in a modal form**, such as enrol_apply's application form. A dynamic form
  is defined first and rendered inside a fresh JavaScript requirements manager, so the mask
  module, required while the form was being defined, was lost and the field accepted any text.
  A masked input is now its own form element and asks for the module when it is rendered.
- **Editing a saved field shows its saved Format and Store as.** Both selects had an
  unconditional default that outranked the saved configuration, so every saved field reopened as
  "None" / "Masked" (and saving it again would have overwritten the real setting).

### Added

- Pattern custom field type: a single-line text field with presets for CPF, CNPJ (numeric and
  alphanumeric), CEP and Brazilian phone numbers, custom masks with alternatives, an optional
  anchored regular expression with its own error message, and masked or raw storage.
- Client-side mask formatter (`customfield_pattern/mask`), bound by event delegation so it also
  serves modal forms.
- Public value API on the field controller (`normalise_value()`, `validate_value()`,
  `format_for_display()`) for consumers that store values outside `customfield_data`.
