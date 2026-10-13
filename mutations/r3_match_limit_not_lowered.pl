# regex_matches(): the site's match limit applies to the admin-set regex.
s~\n\s+ini_set\('pcre\.backtrack_limit', \(string\) self::REGEX_BACKTRACK_LIMIT\);~~s;
