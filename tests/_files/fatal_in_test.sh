#!/bin/bash

# Trigger PHP fatal errors in phpunit test to ensure it is handled
output_file=$TMPDIR/fatal_in_test.json
rm -f $output_file
./solano-phpunit \
  --configuration tests/_files/phpunit_fatal_in_test.xml \
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

if [[ "1" != `jq '.fatal_errors.tests | length' $output_file` ]]; then
  echo "FAIL: There should only be 1 'fatal_errors.tests'!"
  exit 2
fi

if [[ "3" != `jq '.fatal_errors.tests[] | length' $output_file` ]]; then
  echo "FAIL: There should be 3 fatal errors recorded!"
  exit 3
fi

echo "PASS: Testing for php fatal errors in test"
