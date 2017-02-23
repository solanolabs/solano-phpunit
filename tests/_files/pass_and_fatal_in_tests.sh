#!/bin/bash

# Trigger PHP fatal errors in phpunit test to ensure it is handled
output_file=$TMPDIR/pass_and_fatal_in_tests.json
rm -f $output_file
./solano-phpunit \
  --configuration tests/_files/phpunit_pass_and_fatal_in_tests.xml \
  --rerun-fatal-max-count 3 \
  --tddium-output-file $output_file \
  --alpha
exit_code=$?

echo "### output BOF ###"
cat $output_file; echo ""
echo "### output EOF ###"

# Test results
set -o errexit -o pipefail

if [[ "2" != `jq '.byfile | length' $output_file` ]]; then
  echo "FAIL: There should be 2 'byfile' items!"
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

# And run the tests in the opposite order
set +o errexit +o pipefail
rm -f $output_file
./solano-phpunit \
  --configuration tests/_files/phpunit_pass_and_fatal_in_tests.xml \
  --rerun-fatal-max-count 3 \
  --tddium-output-file $output_file \
  --rev-alpha

echo "### output BOF ###"
cat $output_file; echo ""
echo "### output EOF ###"

# Test results
set -o errexit -o pipefail

if [[ "2" != `jq '.byfile | length' $output_file` ]]; then
  echo "FAIL: There should be 2 'byfile' items!"
  exit 4
fi

if [[ "1" != `jq '.fatal_errors.tests | length' $output_file` ]]; then
  echo "FAIL: There should only be 1 'fatal_errors.tests'!"
  exit 5
fi

if [[ "3" != `jq '.fatal_errors.tests[] | length' $output_file` ]]; then
  echo "FAIL: There should be 3 fatal errors recorded!"
  exit 6
fi

echo "PASS: Testing for php fatal errors in test"
