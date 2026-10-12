# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## Unreleased

### Added

- Pattern custom field type: a single-line text field with presets for CPF, CNPJ (numeric and
  alphanumeric), CEP and Brazilian phone numbers, custom masks with alternatives, an optional
  anchored regular expression with its own error message, and masked or raw storage.
- Client-side mask formatter (`customfield_pattern/mask`), bound by event delegation so it also
  serves modal forms.
- Public value API on the field controller (`normalise_value()`, `validate_value()`,
  `format_for_display()`) for consumers that store values outside `customfield_data`.
