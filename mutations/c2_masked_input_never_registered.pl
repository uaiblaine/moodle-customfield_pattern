# api::element_type(): a masked configuration gets a plain text input, which never asks for the formatter.
s~if \(!self::masks\(\$config\)\) \{~if (true) {~s;
