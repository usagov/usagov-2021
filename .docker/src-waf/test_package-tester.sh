#!/bin/bash

# Mock dpkg -l output
mock_dpkg_output() {
  echo "ii  package1 1.0-1 amd64 description"
  echo "ii  package2 1.0-1 amd64 description"
  echo "ii  package3 1.0-1 amd64 description"
}

# Mock ps aux output
mock_ps_output() {
  case $1 in
    package1)
      echo "root       123  0.0  0.1  123456  1234 ?        S    00:00   0:00 /bin/package1"
      ;;
    package2)
      echo "root       456  0.0  0.1  123456  1234 ?        S    00:00   0:00 /bin/package2"
      ;;
    package3)
      # package3 is not running
      ;;
  esac
}

# Override dpkg -l command
dpkg() {
  if [ "$1" == "-l" ]; then
    mock_dpkg_output
  fi
}

# Override ps aux command
ps() {
  if [ "$1" == "aux" ]; then
    mock_ps_output $2
  fi
}

# Run the package-tester.sh script and capture the output
output=$(bash .docker/src-waf/package-tester.sh)

# Check the output
if [[ "$output" == *"package1 is currently being used by a running process"* ]] &&
   [[ "$output" == *"package2 is currently being used by a running process"* ]] &&
   [[ "$output" != *"package3 is currently being used by a running process"* ]]; then
  echo "Test passed"
else
  echo "Test failed"
  echo "Output:"
  echo "$output"
fi