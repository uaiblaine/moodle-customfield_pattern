# Claude instructions for `customfield_pattern`

This file is auto-loaded as context whenever Claude works in this plugin's
directory tree. **Fleet-wide standards live in `~/dev/CLAUDE.md`** (coding
style, CI gates, lang-string rules, the `mdl` environment, git rules) — do not
repeat them here. This file keeps only what is true for this plugin, as rules
with a one-line reason each; keep it under ~250 lines, because every session
and every subagent pays for it on every cache write. Measurements, incidents
and the long version of a gotcha go to `docs/` (export-ignored) with a pointer
from the rule.

Plugin context: a Moodle **custom field type** plugin ("Pattern") that adds a single-line text
field with an input mask and validation (CPF, CNPJ numeric and alphanumeric, CEP, Brazilian
phone, custom masks, an optional anchored regex), shaped like core's `customfield_text`. It owns
no tables: values live in core's `customfield_data.charvalue`. Consumers: course custom fields,
enrol_apply's application questions (the field controller's value API, without a data row) and
profilefield_repeatable's pattern columns (`\customfield_pattern\api`). Supports Moodle **5.1 through 5.3**
(`$plugin->requires = 2025100600`, `$plugin->supported = [501, 503]`).
CI is the moodle-an-hochschulen reusable workflow, one job per supported
branch in `.github/workflows/ci.yml` — **update those jobs when `supported`
changes**. Development happens on the m502 stack; this repo is mounted into the 5.1, 5.2 and
5.3 stacks at `customfield/field/pattern` (see `~/dev/moodle-dev/plugins.conf`).

## Agent orchestration budget (fleet rule, repeated here on purpose)

Section 6 of `~/dev/CLAUDE.md` (`moodle-dev/CLAUDE.fleet.md`) is the authority and says why.
This short copy reaches sessions that do not load that file: cloud sessions and checkouts
outside `~/dev`. Every subagent gets the model and effort of its role from its agent
definition, and none runs on the session model.

| Role | model | effort | agent |
|---|---|---|---|
| Mechanical sweeps, greps, renames, counts, log reading | `haiku` | `medium` | `fleet-sweeper` |
| Checklists against evidence (handoff counts, spec lines against a sweep log, lang lockstep) | `haiku` | `high` | `fleet-checker` |
| Readers, measurers, graders | `sonnet` | `medium` | `fleet-reader` |
| Refuters and verifiers of a blocking finding | `sonnet` | `high` | `fleet-verifier` |
| Well-scoped implementation (established cause, settled design, written recipe) | `sonnet` | `medium` | `fleet-fixer` |
| Non-trivial implementation (open design, several files, long tasks) | `opus` | `high` | `fleet-implementer` |
| Consolidators, critics, estimators, ADR and documentation drafters | `opus` | `high` | `fleet-synthesist` |

- Launch the `Agent` tool with `subagent_type: "fleet-*"`; it has no `effort` parameter, so
  the role's effort comes from that definition (`mdl claude-setup` installs them). Where they
  are not installed, pass `model`. Aliases only; never `fable`; `xhigh` only for a long-horizon
  implementer whose prompt says why; never `xhigh`/`max` on Sonnet or Haiku.
- No long command inside a subagent: `mdl ci --matrix`, `mdl mutate` and Behat run from the
  main session in a background Bash command; a subagent runs the fast gate its prompt names and
  reports the command with its counts.
- Workflows only on the user's opt-in, every `agent()` with `agentType: 'fleet-*'`, under 10
  agents. Advisor off by default.

## Commands

```sh
mdl ci moodle-customfield_pattern --matrix     # every CI leg locally before any push
mdl phpunit m502 customfield_pattern           # targeted tests
mdl grunt m502 customfield/field/pattern       # rebuild amd/build (commit with src)
mdl purge m502                                 # after PHP changes that affect output
# Mutation sweep: absolute spec path, and both database families.
mdl mutate moodle-customfield_pattern /Users/uaiblaine/dev/moodle-customfield_pattern/mutations/gates.conf --fast --db pgsql
```

In a Claude Code cloud session none of `mdl`, the stacks or `~/dev/CLAUDE.md` exist. The cloud
environment's setup script (`moodle-dev/cloud/setup.sh`) installs `moodle-plugin-ci` and the `mpci`
runner instead: `mpci` runs every gate on the highest branch in `ci.yml`, `mpci --branch <branch>`
another one, `mpci --reuse --only phpunit --filter <test>` iterates. It cannot run Behat, PHP 8.4 or
MariaDB, so the owner runs `mdl ci moodle-customfield_pattern --matrix --behat` before merging: push a branch, open
the pull request, leave the merge to them. If `which mpci` prints nothing, the session was resumed on
a container that skipped the setup script: attach `moodle-dev` and run `bash cloud/setup.sh` from it.
See `moodle-dev/cloud/README.md`.

## Code layout

```
classes/api.php                public API for consumers outside customfield_data: config_errors, validate,
                               normalise, format, presets, masks, input_attributes, js_init
classes/field_controller.php   config form + validation; value API (normalise/validate/format_for_display) over api
classes/data_controller.php    instance form element, validation, normalised save, export
classes/local/pattern.php      the rules of one configuration (presets, storage, regex), pure
classes/local/mask.php         mask parsing and strict application, pure static
classes/local/checkdigit.php   CPF and CNPJ (alphanumeric) check digits, pure static
amd/src/mask.js                lenient typing aid, document-level input listener
```

## Versions

`$plugin->version` is a date-based number (`YYYYMMDDXX`) while the plugin ships one branch for
every Moodle version it supports. When it splits into one branch per Moodle version, each branch
numbers itself in the namespace of its Moodle version (`20241007XX` for 4.5, `20251006XX` for 5.1,
`20260420XX` for 5.2), starting at its `$plugin->requires` value and counting up on that branch
alone, because moodle.org needs a different number for each Moodle-specific release. The rule and
its consequences for upgrade steps and cherry-picks are in the fleet file (`~/dev/CLAUDE.md`,
"Versioning / upgrade discipline").

## Architecture gotchas

- The server is the authority: `local\mask::apply()` is strict (an unexpected character or a
  missing token rejects the value) while `amd/src/mask.js` drops what does not fit. Keep the
  token classes of the two in step; nothing tests the JS.
- `api` is a published contract: its configuration keys and signatures change only with
  `api::CONFIG_VERSION` (raised when a key changes meaning, never for a new optional key), and the
  controllers take every rule from it (`local\pattern` only for its constants), so a rule has one
  implementation.
- The value API must stay a pure function of configdata and its argument: enrol_apply calls it
  on a controller with no data row, and possibly one built with `new` (no category, so
  `get_handler()` would fatal; the context falls back to system).
- Uniqueness (`uniquevalues`) is checked on the normalised value: stored values are normalised,
  so comparing the typed shape would let `31987654321` duplicate `(31) 98765-4321`.
- The regex is anchored and must also match the whole value: a body such as `a)|(b` closes the
  wrapping group and would otherwise defeat `\A...\z`. The delimiter is chr(1), so `/` needs no
  escape.
- Changing a field's mask or storage format does not rewrite stored values; display falls back
  to the stored string when it does not fit the current mask.

## Testing notes

The pure classes are tested directly (`tests/local/`); the controllers through the course
handler with the core_customfield generator, whose `add_instance_data()` goes through
`instance_form_save()` and therefore normalises. No Behat yet.

## When in doubt

Follow the patterns in existing files. The codebase is internally
consistent — if a new file feels like it matches no existing shape,
re-examine the approach.
