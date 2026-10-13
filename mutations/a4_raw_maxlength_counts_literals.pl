# config_errors(): raw storage is measured with the mask's literals, as masked storage is.
s~\$needed = mask::max_token_count\(\$masks\);~\$needed = mask::max_length(\$masks);~s;
