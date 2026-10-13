# instance_form_definition(): a masked field gets a plain text input, which never asks for the formatter.
s~if \(api::masks\(\$config\)\) \{~if (false) {~s;
