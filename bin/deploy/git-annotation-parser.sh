 #!/bin/sh

# we might be running in circleci
if [ -f /home/circleci/project/env.local ]; then
  . /home/circleci/project/env.local
fi
# we might be running from a local dev machine
SCRIPT_DIR="$(dirname "$0")"
if [ -f $SCRIPT_DIR/env.local ]; then
  . $SCRIPT_DIR/env.local
fi
if [ -f ./env.local ]; then
  . ./env.local
fi

SCRIPT_DIR="$( cd -- "$(dirname "$0")" >/dev/null 2>&1 ; pwd -P )"
if [ -f $SCRIPT_DIR/../deploy/includes ]; then
  . $SCRIPT_DIR/../deploy/includes
else
    echo "File does not exist: $SCRIPT_DIR/../deploy/includes"
    exit 1
fi

# just testing?
if [ x$1 == x"--dryrun" ]; then
  export echo=echo
  shift
fi

SPACE=${1:-please-provide-space-as-first-argument}
SSPACE=$(echo "$SPACE" | tr '[:upper:]' '[:lower:]') ## lowercase, so tags are properly formatted
#assertCurSpace "$SPACE"  ### <-- no need to assert that we're actually in $SPACE, because we're not doing anything w/ CF - just git
shift

# 1.  Find the name of the latest annotated git tag matching our production post-deployment tag format
# 2.  Query the content field of the reference attached to the tag, and make sure it contains correctly formated build number and digest hashes
# 3.  Profit

# TBD:
#
# 1. Find some way to put boundaries on the for-each-ref results - what if there are 1200 matching results?  There shouldn't be, but - seatbelts please.
# 2. The annotation parsing works, but is "bulky" :)

### Step 1.a  (also see TBD 1.)
ANNOTATED_TAGS=$(git for-each-ref refs/tags/usagov-cci-build-*-${SPACE} --sort='-*authordate' \
    --format '%(objecttype) %(refname:short)' |
    while read ty name; do [ $ty = tag ] && echo $name; done)

### Step 1.b
for at in $ANNOTATED_TAGS; do

    # Step 2.  Get the annotation content and validate the formats.
    #
    # The annotation content is formatted like so:
    #   CCI_BUILD=5936
    #   CMS_DIGEST='@md5:d971a1d9d90ef9b20fd53adfd1c5772636ae682f5faa1c6d9ac9cbe8eb2750cd'
    #   WAF_DIGEST='@sha256:69d3fe9c373562ad42c8d8d0efe99d187957e45e4968dd43a4539198b15d12a'
    #   TAG_MESSAGE="'CCI_BUILD=${CCI_BUILD}|CMS_DIGEST=${CMS_DIGEST}|WAF_DIGEST=${WAF_DIGEST}'"
    #
    # The TAG_MESSAGE is what we are deconstructing below
    TAG_RESULTS=$(git for-each-ref refs/tags/$at --format "%(contents)" | sed "s/'//g")
    IFS='|' read -ra ANNOTATION <<< "$TAG_RESULTS"

    for field in "${ANNOTATION[@]}"; do
      case $field in
      CCI_BUILD=*)
        VAL=$(sed 's/^CCI_BUILD=//' <<< $field)
        re='^[0-9]+$'
        if ! [[ $VAL =~ $re ]] ; then
            echo Invalid CircleCI build number in tag annotation for $at
            exit 1
        fi
        CCI_BUILD=$VAL
        #echo "$CCI_BUILD "
        ;;

      CMS_DIGEST=*)
        VAL=$(sed 's/^CMS_DIGEST=//' <<< $field)

        # $VAL will be the hash string, eg:  @sha256:<hex string>.  The following
        # code will split on ':' and do basic validation on both parts the the hash string
        IFS=':' read -ra DIGEST <<< "$VAL"
        for fieldpart in "${DIGEST[@]}"; do
            re1='^\@(sha[0-9]+|md[2-5])$'
            re2='^[0-9A-Fa-f]+$'
            if ! [[ $fieldpart =~ $re1 ]]; then
                if ! [[ $fieldpart =~ $re2 ]]; then
                    echo "Invalid digest string ($fieldpart) in tag annotation for $at"
                    exit 1
                fi
            fi
        done
        field=$(sed -E 's/=(.*)/="\1"/' <<< $field)
        CMS_DIGEST=$VAL
        #echo "$CMS_DIGEST "
        ;;

      WAF_DIGEST=*)
        VAL=$(sed 's/^WAF_DIGEST=//' <<< $field)

        # $VAL will be the hash string, eg:  @sha256:<hex string>.  The following
        # code will split on ':' and do basic validation on both parts the the hash string
        IFS=':' read -ra DIGEST <<< "$VAL"
        for fieldpart in "${DIGEST[@]}"; do
            re1='^\@(sha[0-9]+|md[2-5])$'
            re2='^[0-9A-Fa-f]+$'
            if ! [[ $fieldpart =~ $re1 ]]; then
                if ! [[ $fieldpart =~ $re2 ]]; then
                    echo "Invalid digest string ($fieldpart) in tag annotation for $at"
                    exit 1
                fi
            fi
        done
        field=$(sed -E 's/=(.*)/="\1"/' <<< $field)
        WAF_DIGEST=$VAL
        #echo "$WAF_DIGEST "
        ;;

      WWW_DIGEST=*)
        VAL=$(sed 's/^WWW_DIGEST=//' <<< $field)

        # $VAL will be the hash string, eg:  @sha256:<hex string>.  The following
        # code will split on ':' and do basic validation on both parts the the hash string
        IFS=':' read -ra DIGEST <<< "$VAL"
        for fieldpart in "${DIGEST[@]}"; do
            re1='^\@(sha[0-9]+|md[2-5])$'
            re2='^[0-9A-Fa-f]+$'
            if ! [[ $fieldpart =~ $re1 ]]; then
                if ! [[ $fieldpart =~ $re2 ]]; then
                    echo "Invalid digest string ($fieldpart) in tag annotation for $at"
                    exit 1
                fi
            fi
        done
        field=$(sed -E 's/=(.*)/="\1"/' <<< $field)
        WWW_DIGEST=$VAL
        #echo "$WWW_DIGEST "
        ;;

      *)
        echo Unexpected content in tag annotation for $at
        exit 1
      esac

    done

    ANNOTATED_TAG=$at
    # Stop after processing first (latest) annotation
    break
done

if [ -n $ANNOTATED_TAG ]; then
  if [ -n "$CCI_BUILD" ]; then
    if [ -n "$WAF_DIGEST" -a -n $"$CMS_DIGEST" -a -n $"$WWW_DIGEST" ]; then
      echo
      echo "To deploy the waf, please execute the following command:"
      echo
      echo "   ROUTE_SERVICE_APP_NAME=waf \\
       ROUTE_SERVICE_NAME=waf-route-${SPACE}-usagov \\
       PROTECTED_APP_NAME=cms \\
          bin/cloudgov/deploy-waf $CCI_BUILD $WAF_DIGEST"
      echo
      echo
      echo "To deploy the cms, please execute the following command:"
      echo "   bin/cloudgov/deploy-cms $CCI_BUILD $CMS_DIGEST"
      echo
      echo
      echo "To deploy the static site, please execute the following command:"
      echo "   bin/cloudgov/deploy-www $CCI_BUILD $WWW_DIGEST"
      echo
    else
       echo "Not all image digests were found in the git tag: $ANNOTATED_TAG"
       echo "WAF_DIGEST: $WAF_DIGEST"
       echo "CMS_DIGEST: $CMS_DIGEST"
       echo "WWW_DIGEST: $WWW_DIGEST"
       exit 2
    fi
  else
    echo "The CircleCI build identifier (CCI_BUILD) was found in the git tag: $ANNOTATED_TAG"
    exit 3
  fi
else
  echo "No git tag of the form 'usagov-cci-build-*-${SPACE}' found in the repository"
  exit 4
fi
