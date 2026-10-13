# config_errors(): a regex is never tried on the probe value.
s~\} else if \(\$regex !== '' && !pattern::regex_survives_probe\(\$regex\)\) \{~} else if (false) {~s;
