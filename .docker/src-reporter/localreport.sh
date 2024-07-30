#!/bin/bash
# This is a file used only for testing and not in production
echo starting container to create reports
cd analytics-reporter

export GOOGLE_APPLICATION_CREDENTIALS="*"
export ANALYTICS_REPORT_COMBINED="*"
export ANALYTICS_REPORT_ENGLISH="*"
export ANALYTICS_REPORT_SPANISH="*"

echo starting pull for both sites Top Pages 7 Days only
export ANALYTICS_REPORT_IDS=$ANALYTICS_REPORT_COMBINED
./bin/analytics --only top-pages-7-days;

echo starting pull for both sites Top Pages 30 Days only
./bin/analytics --only top-pages-30-days;

echo ending container localreport.sh
