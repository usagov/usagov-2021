#!/bin/sh

# Delete file content
> waf_rate_limit_results.txt

for request_number in `seq 1 1 170`;
do

    curl -s -o /dev/null -w "[$(date +'%Y-%m-%d %H:%M:%S')] Request $request_number: Done - HTTP Code: %{http_code}\n" https://beta-dev.usa.gov/ >> waf_rate_limit_results.txt &

done

wait

cat waf_rate_limit_results.txt
echo "Total requests with 200 HTTP code: $(grep -o "HTTP Code: 200" waf_rate_limit_results.txt | wc -l)"
echo "Total requests with 429 HTTP code: $(grep -o "HTTP Code: 429" waf_rate_limit_results.txt | wc -l)"