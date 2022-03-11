#!/bin/bash

echo "TESTING permission when NOT forcing ids"

rm -f ./root
rm -f ./nginx

echo "building custom container"
docker build -q --force-rm \
    -t usagov-perms \
    -f ./Dockerfile-perms ./

echo "::: Running non-forced-id tests"

docker run -v $(pwd):/perms --rm --user root  usagov-perms /in-container-test.sh
#docker run --mount type=bind,source="$(pwd)",target=/perms --rm --user root  usagov-perms /in-container-test.sh
echo;
echo "Host permissions for 'root'"
ls -al ./root
ls -n  ./root


docker run -v $(pwd):/app --rm --user nginx usagov-perms /in-container-test.sh
#docker run --mount type=bind,source="$(pwd)",target=/perms --rm --user nginx  usagov-perms /in-container-test.sh
echo
echo "Host permissions for 'nginx'"
ls -al ./nginx
ls -n  ./nginx

echo; echo; echo; echo;

rm -f ./root-forced
rm -f ./nginx-forced

echo "TESTING permission when forcing ids"

echo "building custom container"
docker build -q --force-rm \
    -t usagov-perms-force \
    --build-arg FORCEIDS=1 \
    --build-arg UID=$(id -u) \
    --build-arg GID=$(id -g) \
    -f ./Dockerfile-perms ./

echo "::: Running forced-id tests"

docker run -v $(pwd):/app --rm --user root  usagov-perms /in-container-test.sh
#docker run --mount type=bind,source="$(pwd)",target=/perms --rm --user root  usagov-perms-force /in-container-test.sh
echo;
echo "Host permissions for 'root'"
ls -al ./root-forced
ls -n  ./root-forced

docker run -v $(pwd):/app --rm --user nginx usagov-perms /in-container-test.sh
#docker run --mount type=bind,source="$(pwd)",target=/perms --rm --user nginx  usagov-perms-force /in-container-test.sh
echo
echo "Host permissions for 'nginx'"
ls -al ./nginx-forced
ls -n  ./nginx-forced
