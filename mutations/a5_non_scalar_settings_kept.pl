# clean(): settings that are not scalars reach local\pattern, which casts them to string.
s~return array_filter\(\$config, 'is_scalar'\);~return \$config;~s;
