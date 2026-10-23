moodle-customfield_pattern
==========================

[![Moodle Plugin CI](https://github.com/uaiblaine/moodle-customfield_pattern/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/uaiblaine/moodle-customfield_pattern/actions/workflows/ci.yml?query=branch%3Amain)

A single-line custom field with an input mask and validation: CPF, CNPJ, CEP, Brazilian phone
numbers, or a mask and regular expression of your own.

The field works wherever core custom fields do (course custom fields, and any other component
that uses the core custom field API). It behaves like core's "Short text" field, adds a mask
that formats the value while the user types, and validates the value on the server before it
is stored. It owns no tables: values live in core's `customfield_data`.


Requirements
------------

| Moodle | Supported |
|---|---|
| 5.1 | yes |
| 5.2 | yes |
| 5.3 | yes |

`$plugin->requires` is Moodle 5.1 (2025100600).


Installation
------------

Install the plugin like any other plugin to folder `/customfield/field/pattern`.

See http://docs.moodle.org/en/Installing_plugins for details on installing Moodle plugins.

Nothing needs configuring after installation: the type "Pattern" appears in every custom field
area's "Add field" menu.


Usage
-----

### Settings of a field

- **Default value**, **Form input size**, **Maximum number of characters**: as in "Short text".
  The default value obeys the same rules as typed input.
- **Format** (preset):

  | Preset | Mask | Extra check |
  |---|---|---|
  | None | no mask | length and regex only |
  | CPF | `999.999.999-99` | check digits; repeated digits rejected |
  | CNPJ | `**.***.***/****-99` | check digits, numeric or alphanumeric |
  | CEP | `99999-999` | |
  | Phone (Brazil) | `(99) 9999-9999\|(99) 99999-9999` | |
  | Custom mask | the **Custom mask** setting | |

  CNPJ is alphanumeric since July 2026 (Receita Federal, IN RFB 2.229/2024): the first twelve
  positions take `0-9` and `A-Z`, the last two are numeric check digits computed by the classic
  mod-11 algorithm with each character valued as its ASCII code minus 48. Numeric CNPJs remain
  valid. Letters are converted to upper case.
- **Custom mask**, required when the format is Custom mask.
- **Regular expression** and **Regular expression error message**, both optional.
- **Store as**: formatted (`529.982.247-25`, the default) or typed characters only
  (`52998224725`). Values are always shown formatted.

### Mask syntax

| Character | Meaning |
|---|---|
| `9` | a digit |
| `A` | an ASCII letter, kept in the case typed |
| `*` | an ASCII letter or digit, letters converted to upper case |
| `\|` | separates alternative masks |
| anything else | a literal, inserted into the value |

There is no escape: `9`, `A`, `*` and `|` cannot be literals. With alternatives, the one that
fits the number of characters typed wins (`(99) 9999-9999|(99) 99999-9999` takes ten or eleven
digits). Users may type the value with or without the literals and with spaces; anything else
is rejected.

### Regular expression

Write the expression body only, without delimiters or flags. It is anchored at both ends
(`\A(?:...)\z`, flag `u`) and must match the whole value **as it is stored**, that is after
the mask is applied (with the literals, unless "Store as" is "Typed characters only"). An
expression that does not compile is refused on the settings form. When it does not match,
the field shows the configured error message, or a generic one.

The expression is set by an administrator but runs on what users type, so its run is bounded:
it is evaluated with PCRE's match limit lowered to 100,000 and the JIT off, and a value it cannot
answer for within that bound is refused with "The value could not be checked against the
pattern." rather than the error message. The settings form refuses an expression longer than 512
characters, and one that already gives up on a probe value (64 letters followed by `!`, the shape
that makes nested repetitions such as `(a+)+` explode); that probe is a smoke test, not a proof,
so keep expressions free of nested repetitions.

### For developers

`\customfield_pattern\api` is the public API for components that keep values outside
`customfield_data`, such as a profile field with several columns or an application form. Every
function is a pure function of a configuration array and its argument; the field type itself goes
through the same functions, so the same configuration accepts, stores and shows the same values
everywhere.

The configuration is an array of strings, every key optional; unknown keys are ignored, so it can
sit beside the consumer's own settings:

| Key | Values |
|---|---|
| `preset` | `none` (default), `cpf`, `cnpj`, `cep`, `phone_br`, `custom` |
| `mask` | the mask when `preset` is `custom` (see "Mask syntax"); ignored otherwise |
| `regex` | optional expression body, anchored, matched against the stored value |
| `regexerror` | optional message for a value the regex rejects |
| `storeformat` | `masked` (default) or `raw` |
| `maxlength` | optional, the longest stored value |

```php
use customfield_pattern\api;

api::CONFIG_VERSION;                                // 1; raised only when a key changes meaning.
api::config_errors(array $config): array;           // ['mask' => 'message', ...]; empty when valid.
api::validate(array $config, string $value): ?string;   // Null when valid, else the message (HTML-safe).
api::normalise(array $config, string $raw): string;     // The value as it would be stored.
api::format(array $config, string $stored): string;     // Plain text, mask restored; escape it.
api::presets(): array;                              // Preset => localised name.
api::masks(array $config): array;                   // The alternative masks in force.
api::input_attributes(array $config): array;        // Attributes of the text input (mask, placeholder, ...).
api::js_init(): void;                               // Load the typing formatter, once per page.
```

A consumer validates typed input with `validate()`, stores `normalise()`'s result, shows
`format()`'s result escaped, renders its text inputs with `input_attributes()` and calls
`js_init()` while rendering the page or the modal form: the formatter listens on the document, so
inputs the consumer's JavaScript adds later are formatted too. Check whether a stored value is
unique on the normalised value. Install-time checks such as
`class_exists('\customfield_pattern\api')` let a consumer treat this plugin as optional.

The field controller keeps a value API of its own for consumers that hold one
(`normalise_value()`, `validate_value()`, `format_for_display()`, `get_masks()`); it delegates
to `api` with the field's saved configuration.


Settings and capabilities
-------------------------

The plugin has no site settings and defines no capabilities. Each field is configured on its own
settings form (see "Settings of a field"), and who may create, edit or fill a field is decided by
the custom field area that uses it (for course custom fields, core's
`moodle/course:configurecustomfields` and `moodle/course:changelockedcustomfields`).


Data stored
-----------

The plugin owns no database tables and stores no personal data of its own. It uses two core
tables, through core's custom field API:

- `customfield_field`: the field's settings (`configdata`: format, mask, regular expression,
  storage format and lengths), kept until an administrator deletes the field.
- `customfield_data`: one value per field and instance (for course custom fields, per course), in
  `charvalue`, as stored by "Store as". Core removes the values with the field or with the
  instance they belong to (a course, for example).

Like core's "Short text" field, its privacy provider is a `null_provider` that also implements
core's `customfield_provider`: when the component that owns a custom field area exports a user's
data through `core_customfield\privacy\provider`, each value of this type is exported as it is
shown, with its mask restored; deletion is done by core, and the plugin keeps no files or other
rows that would need deleting with it.


Translations
------------

The English strings ship in the package. The Brazilian Portuguese translation is maintained in
this repository (`lang/pt_br`) and reaches sites through [AMOS](https://lang.moodle.org/), as the
plugins directory requires; it is left out of the release package.


Troubleshooting
---------------

- **The mask does not format while typing**: the AMD build is missing or stale; the server
  still normalises and validates the value on save.
- **A value stored under an earlier mask shows unformatted**: values are stored when saved;
  changing a field's mask or storage format does not rewrite existing values, and a raw value
  that does not fit the current mask is shown as stored.


License
-------

This plugin is licensed under the [GNU GPL v3 or later](http://www.gnu.org/copyleft/gpl.html).
The full text ships in `LICENSE` and `COPYING.txt`.

Copyright: 2026 Anderson Blaine
