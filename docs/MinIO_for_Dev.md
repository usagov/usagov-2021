# MinIO for Local Development

We're using [MinIO](https://min.io/) to stand in for AWS S3 in local development. We are intentionally using an outdated release, which supports storing plain files in MinIO's directory structure. This allows us to populate a local dev environment with files by simply copying them to the correct location. Newer versions of MinIO work differently, creating metadata as files are added via MinIO's API.

Specifically, we use the image `minio/minio:RELEASE.2022-10-24T18-35-07Z` (the latest release that works the way we want) to build the MinIO container, and enter this line in s3/.minio.sys/format.json:

```
{"version":"1","format":"fs","id":"legacy-mode-on-purpose-for-dev","fs":{"version":"2"}} 
```

The latter, you will find specified in bin/init. The s3 directory as a whole is .gitignore-ed.

References:

 https://www.funkypenguin.co.nz/blog/how-to-run-minio-in-fs-mode-again/

 https://min.io/docs/minio/linux/operations/install-deploy-manage/deploy-minio-single-node-single-drive.html


## Re-setting MinIO

You will probably only need to do this once, and only if you upgraded to "latest" before the image version was pinned. Much of this procedure is similar to starting from scratch. 

1. Run "docker compose down" (if up)
2. Run "docker system prune --all" 
3. Delete the "s3" directory (rm -r s3) 
4. Run "bin/init"
5. Download the latest "files" zip file from https://drive.google.com/drive/folders/1zVDr7dxzIa3tPsdxCb0FOXNvIFz96dNx to s3/local/cms (which should exist by now)
6. Unzip that file into s3/local/cms/ (will create a "public" directory) 
7. Run "docker compose up" 

