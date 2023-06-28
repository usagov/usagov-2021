This repository creates a Docker container which runs the 18f [analytics-reporter](https://github.com/18F/analytics-reporter) which powers analytics.usa.gov. The repository also contains a manifest.yml file which is used to bind a user provided service for environment variables to the container and to set config options. In order for the docker container to run on cloud.gov `no-route: true` and `health-check-type: process` must be included in the manifest file. Including the docker image and username are optional but allow for shortened cf push command.

The base image is the [node alpine image](https://hub.docker.com/_/node). It copies and runs testscript.sh. The script installs the [analytics-reporter](https://github.com/18F/analytics-reporter) found in /analytics-reporter. To pull the data variables ANALYTICS_REPORT_IDS, ANALYTICS_REPORT_EMAIL, and ANALYTICS_KEY needed. To write to an S3 bucket the AWS_REGION,AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_BUCKET and AWS_BUCKET_PATH environment variables are needed. These are stored in a user provided service instance {s3user-provided-service} and are accessible through [VCAP_SERVICES](https://docs.cloudfoundry.org/devguide/deploy-apps/environment-variable.html).  The script requires bash and [jq](https://stedolan.github.io/jq/manual/) to parse the VCAP_SERVICES and export the variables.

The analytics reporter is called every 900 seconds (equal to 15 minutes). It outputs 33 json files to https://s3-{AWS_REGION}.amazonaws.com/{AWS_BUCKET}/{AWS_BUCKET_PATH}/{json-file}.

### User Provided Services (UPS):
  Cloud front documentation states:
  > “Note: Do not use user-provided environment variables for security sensitive information such as credentials as they might unintentionally show up in cf CLI output and Cloud Controller logs. Use user-provided service instances instead. The system-provided environment variable VCAP_SERVICES is properly redacted for user roles such as Space Supporter and in Cloud Controller log files.”

Eight environment variables (ANALYTICS_REPORT_IDS, ANALYTICS_REPORT_EMAIL, ANALYTICS_KEY, AWS_REGION, AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_BUCKET, AWS_BUCKET_PATH) must be saved and provided as a UPS. The below instructions were found here: [IBM Link for CUPS](https://www.ibm.com/docs/en/cloud-private/3.2.x?topic=ubicfee-working-user-provided-services-in-cloud-foundry-enterprise-environment). To bind the UPS to the app `services: - name: {s3user-provided-service}` is included in the manifest.yml file.

First Time:
  - cf cups cupsTest -p {vcap_keys}.json
  - cf bind-service {cf-app-name} cupsTest
  - cf restage {cf-app-name}

Subsequent/Updates:
  - cf uups cupsTest -p {vcap_keys}.json
  - docker buildx use default
  - docker buildx build -t {docker-repo}/{image}  --platform linux/amd64 .
  - docker push {docker-repo}/{image}
  - CF_DOCKER_PASSWORD={docker-password} cf push {cf-app-name}

### S3 on Cloud.Gov
The output of the analytics reporter is stored on a S3 bucket in cloud.gov. The AWS_REGION,AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_BUCKET and AWS_BUCKET_PATH are also stored in VCAP_SERVICES. The analytics reporter already comes with a pre-configuered lightweight S3 publishing tool which just requires the above variables. The json files are stored at https://s3-{AWS_REGION}.amazonaws.com/{AWS_BUCKET}/{AWS_BUCKET_PATH}/{json-file} where the {json-file} is one of the 23 following options:
|       |        |        |
| :----:       |    :----:   |    :----:   |
| all-domains-30-days | screen-size | top-external-links-7-days |
| all-pages-realtime | today | top-external-links-yesterday |
| browsers | top-cities-90-days | top-landing-pages-30-days |
| device_model | top-cities-realtime | top-pages-30-days |
| devices | top-countries-90-days | top-pages-7-days |
| ie | top-countries-realtime | top-pages-realtime |
| language | top-domains-30-days | top-traffic-sources-30-days |
| last-48-hours | top-domains-7-days | traffic-sources-30-days |
| os-browsers | top-downloads-yesterday | users | windows-browsers |
| os | top-exit-pages-30-days | windows-ie |
| realtime | top-external-links-30-days | windows |

### Variables, Keys & Credentials you will need
| Docker | Cloud.gov | Google Analytics | AWS |
| --- | --- | --- | --- |
| docker-repo | cf-app-name | ANALYTICS_REPORT_EMAIL | AWS_ACCESS_KEY_ID |
| image | cf-user | ANALYTICS_KEY| AWS_SECRET_ACCESS_KEY |
| json-file | s3user-provided-service |ANALYTICS_REPORT_IDS  | AWS_BUCKET |
| --- | --- | --- | AWS_BUCKET_PATH |
| --- | --- | --- | AWS_REGION |


## Create docker container for cloud.gov to write to S3
1. cd /analytics-reporter-docker-container
2. docker buildx use default
3. docker buildx build -t {docker-repo}/{image}  --platform linux/amd64 .
4. docker push {docker-repo}/{image}
    - suggested to make repo private on hub.docker
5. Push to cloud.gov:
    - from public repo:
      - cf push {image} --docker-image {docker-repo}/{image}
    - from private repo:
      - CF_DOCKER_PASSWORD={docker-password} cf push {image} --docker-image {docker-repo}/{image}   --docker-username {docker-repo}
    - if using manifest.yml:
      - CF_DOCKER_PASSWORD={docker-password} cf push {cf-app-name}
6. Make S3 bucket
    - cf create-service s3 basic-public-sandbox {s3user-provided-service}
    - cf bind-service {cf-app-name} {s3user-provided-service}
    - cf restage {cf-app-name}
    - cf create-service-key {s3user-provided-service} {cf-user}
    - cf service-key {s3user-provided-service} {cf-user}
7. Create user provided service
    - create & save environment variables (Google Analytics and AWS Keys) in {vcap_keys}.json
    - cf cups analyticsReporterServices -p [path to : {vcap_keys}.json]
    - cf bind-service {cf-app-name} analyticsReporterServices
    - cf restage {cf-app-name}
10. Edit manifest.yml
    - The manifest.yml files now needs to point to the s3 bucket you just created. Add:
      - services:
        -name: analyticsReporterServices
11. docker buildx build -t {docker-repo}/{image}  --platform linux/amd64 .
12. docker push {docker-repo}/{image}
13. Push to cloud.gov:
    - from public repo:
      - cf push {image} --docker-image {docker-repo}/{image}
    - from private repo:
      - CF_DOCKER_PASSWORD={docker-password} cf push {image} --docker-image {docker-repo}/{image}   --docker-username {docker-repo}
    - if using manifest.yml:
      - CF_DOCKER_PASSWORD={docker-password} cf push {cf-app-name}
12. Access via:
    - https://s3-{AWS_REGION}.amazonaws.com/{AWS_BUCKET}/{AWS_BUCKET_PATH}/{json-file}















