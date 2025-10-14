-- These functions parse "tags" and "gauge" data into JSON key/value
-- pairs. The "eq_pairs" of the function name are strings of
-- non-whitespace (and non-=) characters separated by an equal sign.

local KEYS_TO_PARSE = {"tags", "gauge"}

local eq_pairs_to_json_string = function (orig_string)
    local new_string = string.gsub(orig_string, "(%S+)=(%S+)", "\"%1\":%2,")
    -- trim off that last , and add curly braces around:
    new_string = string.gsub(new_string, "(.+),$", "{%1}")
    return new_string
end

-- Splits a string of foo="bar" pairs and makes parsable json out of it.
function parse_keys_with_eq_pairs(tag, timestamp, record) --luacheck:ignore
    for _, v in pairs(KEYS_TO_PARSE) do
        if (record[v] ~= nil) then
            record[v] = eq_pairs_to_json_string(record[v])
	end
    end
     -- 2 leaves timestamp unchanged
    return 2, timestamp, record
end


