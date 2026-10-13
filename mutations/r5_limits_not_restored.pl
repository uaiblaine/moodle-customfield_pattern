# regex_matches(): the lowered limit and the JIT setting outlive the call.
s~\n\s+ini_set\('pcre\.backtrack_limit', \(string\) \$backtracklimit\);\n\s+ini_set\('pcre\.jit', \(string\) \$jit\);~~s;
