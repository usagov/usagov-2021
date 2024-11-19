# Cloud Foundry Event API - events available from the API


## There are two files in this directory, containing info on the events available from the API

1. all-event-names.txt

    This is just a text file containing all the event names available.  This was used to when upgrading the `get-events` from API v2 to v3. (compared the deprecated event names in the script against the new event names in order to implement and changes/additions/deletions).

1. v3-events.json

    This is a formatted JSON file, with the category names, as well as the event names. This could theoretically be used to build event queries programmatically, rather than have them hardcoded into the `get-events` (or any other) script.
