 #!/bin/sh

# 1.  Find the name of the latest annotated git tag matching our production post-deployment tag format
# 2.  Query the content field of the reference attached to the tag, and make sure it contains correctly formated build number and digest hashes
# 3.  Profit

### Step 1.a  (also see TBD 1.)
ANNOTATED_TAGS=$(git for-each-ref refs/tags/USAGOV*prod*post* --sort='-*authordate' \
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
        echo $VAL
        ;;

      CMS_DIGEST=*)
        VAL=$(sed 's/^CMS_DIGEST=//' <<< $field)
        IFS=':' read -ra DIGEST <<< "$VAL"

        for field in "${DIGEST[@]}"; do
            re1='^\@(sha[0-9]+|md[2-5])$'
            re2='^[0-9A-Fa-f]+$'
            if ! [[ $field =~ $re1 ]]; then
                if ! [[ $field =~ $re2 ]]; then
                    echo "Invalid digest string ($field) in tag annotation for $at"
                    exit 1
                fi
            fi
        done
        echo $VAL
        ;;

      WAF_DIGEST=*)
        VAL=$(sed 's/^WAF_DIGEST=//' <<< $field)
        IFS=':' read -ra DIGEST <<< "$VAL"

        for field in "${DIGEST[@]}"; do
            re1='^\@(sha[0-9]+|md[2-5])$'
            re2='^[0-9A-Fa-f]+$'
            if ! [[ $field =~ $re1 ]]; then
                if ! [[ $field =~ $re2 ]]; then
                    echo "Invalid digest string ($field) in tag annotation for $at"
                    exit 1
                fi
            fi
        done
        echo $VAL
        ;;

      *)
        echo Unexpected content in tag annotation for $at
        exit 1
      esac

    done

    # Stop after processing first (latest) annotation
    break
done

# TBD:
#
# 1. Find some way to put boundaries on the for-each-ref results - what if there are 1200 matching results?  There shouldn't be, but - seatbelts please.
# 2. The annotation parsing works, but is "bulky" :)
