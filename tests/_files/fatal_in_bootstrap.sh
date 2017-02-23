#!/bin/bash

# Trigger PHP fatal errors in phpunit bootstrap to ensure it is handled
output_file=$TMPDIR/fatal_in_bootstrap.json
rm -f $output_file
./solano-phpunit \
  --configuration tests/_files/phpunit_fatal_in_bootstrap.xml \
  --rerun-fatal-max-count 3 \
  --tddium-output-file $output_file
exit_code=$?

echo "### output BOF ###"
cat $output_file; echo ""
echo "### output EOF ###"

# Test results
set -o errexit -o pipefail

if [[ "1" != `jq '.byfile | length' $output_file` ]]; then
  echo "FAIL: There should only be 1 'byfile'!"
  exit 1
fi


if [[ "3" != `jq '.fatal_errors.init | length' $output_file` ]]; then
  echo "FAIL: There should be 3 'fatal_errors.init'!"
  exit 2
fi

echo "PASS: Testing for php fatal errors in bootstrap"
