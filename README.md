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

### For developers

`\customfield_pattern\field_controller` exposes the field's rules as functions of its
configuration and their argument, for components that keep values outside
`customfield_data`:

```php
$field->normalise_value(string $raw): string;     // The value as it would be stored.
$field->validate_value(string $raw): ?string;     // Null when valid, else the error message.
$field->format_for_display(string $stored): string; // Plain text, mask restored; escape it.
```

The client-side formatter is the AMD module `customfield_pattern/mask`; it binds by event
delegation to inputs with a `data-customfield-pattern-mask` attribute.


Privacy
-------

The plugin stores no personal data of its own; values live in core's `customfield_data` and are
exported by the core custom field privacy provider, formatted as they are shown.


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

Copyright: 2026 Anderson Blaine
