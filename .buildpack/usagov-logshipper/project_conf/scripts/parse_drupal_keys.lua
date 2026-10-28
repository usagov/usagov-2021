-- Gets drupal record and returns a record with a json string of the drupal attributes.

-- Expects the following format:
-- [
--  drupal "base_url":"@base_url","severity":"@severity","type":"@type","date":"@date",
--  "uid":"@uid","request_uri":"@request_uri","refer":"@referer","ip":"@ip",
--  "link":"@link","message":"@message"
-- ]

local drupal_attributes_json_string = function (orig_string)
    -- wrap drupal field in brackets to make it parsable json
    local final_string = "{" .. orig_string .. "}"
    return final_string
end

-- The --luacheck:ignore comment suppresses a warning about setting
-- a global variable and that this is an unused function
-- (like most scripts, this function is called via fluentbit.conf,
--  which expects it to be defined in this way)
function parse_drupal_keys (_, timestamp, record) --luacheck: ignore
    if (record["drupal"] ~= nil) then
        record["drupal"] = drupal_attributes_json_string(record["drupal"])
    end
     -- 2 leaves timestamp unchanged
    return 2, timestamp, record
end


