vcl 4.1;

backend default {
    .host = "proxyapi";
    .port = "88";
}

sub vcl_recv {
    if (req.method == "PURGE") {
        return (synth(200, "Purge done"));
    }
}

sub vcl_backend_response {
    set beresp.ttl = 1h;
}
