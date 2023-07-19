# Setting up go.usa.gov

Go.usa.gov is a set of files hosted on an s3 bucket in "website mode," behind a CloudFront distribution. We didn't script this setup. This document lists the commands one would run to re-create the site.

## Create and configure the S3 bucket

First, create a space named "go-prod". Target this space and create the s3 bucket there:
```
 % cf target -s go-prod
 % cf create-service s3 basic-public s3-storage
```
Run this script to populate AWS credentials and variables like BUCKET_NAME in your environment:
```
 % bin/cloudgov/go-setup/getcreds.sh
```
Put the bucket in website mode (we don't actually use the error-document):
```
 % aws s3 website s3://${BUCKET_NAME} --index-document index.html --error-document error.html
```
## Upload the files

There is a compressed tarball of files in the usa.gov GoBackup folder in Google Drive. You'll need to copy this file to a local disk and extract it. The tarball is only 190 MB, but it will expand out to a 8 GB directory.

cd into that directory and run:
```
 % aws s3 sync . s3://$BUCKET
```
Expect that to take several hours. You can continue on to set up the domain and CloudFront CDN while it progresses, if you want to. 

## Set up the domain and CloudFront distribution

These instructions assume that DNS for both the go.usa.gov domain and the corresponding "acme challenge" domains are already present. Note that I've used the $BUCKET variable in the create-service command; I didn't test doing it this way. 
```
 % cf create-domain gsa-tts-usagov go.usa.gov
 % cf target -s go-prod
 % cf create-service external-domain domain-with-cdn go-usa-gov-cdn -c '{"domain": "go.usa.gov", "origin": "$BUCKET.s3-website-us-gov-west-1.amazonaws.com", "insecure_origin": true}'
```
You will need to wait for the certificates and CDN to be set up (as the create-service command will tell you).

That's all! 

