-- Adds environment metadata to all log records for proper source identification

function add_environment_metadata(tag, timestamp, record)
    -- Get environment from environment variable, default to 'unknown'
    local env = os.getenv("USAGOV_ENVIRONMENT") or "unknown"
    
    -- Add environment metadata to the record
    record["environment"] = env
    record["usagov_environment"] = env
    
    -- Also add to support New Relic attributes
    if record["attributes"] == nil then
        record["attributes"] = {}
    end
    record["attributes"]["environment"] = env
    record["attributes"]["usagov.environment"] = env
    
    -- 2 leaves timestamp unchanged
    return 2, timestamp, record
end