# config_form_validation(): the rules shared with the API are not applied to the field.
s~foreach \(api::config_errors\(\$config\) as \$setting => \$error\) \{~foreach ([] as \$setting => \$error) {~s;
