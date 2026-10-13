# validate(): a regex that could not run reads as a value it rejects.
s~if \(\$matches === null\) \{\n(\s+// [^\n]+\n)\s+return get_string\('errorregexunchecked', 'customfield_pattern'\);\n\s+\}\n\s+if \(\$matches === false\) \{~if (\$matches !== true) {~s;
