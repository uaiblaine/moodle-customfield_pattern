# config_errors(): a regex of any length is accepted.
s~if \(\\core_text::strlen\(\$regex\) > pattern::REGEX_MAX_LENGTH\) \{~if (false) {~s;
