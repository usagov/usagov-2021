# Local S3 for development

On cloud.gov, Drupal keeps its files (images, PDFs, image styles) in an S3 bucket through the [s3fs](https://www.drupal.org/project/s3fs) module, and the static site also lives in that bucket. For local development, the `s3` service in `docker-compose.yml` stands in for that bucket. It runs [SeaweedFS](https://github.com/seaweedfs/seaweedfs), an open-source (Apache-2.0), S3-compatible server.

SeaweedFS replaced MinIO in November 2026. We had been pinned to a 2022 MinIO release, the last one that treated plain files in a folder as objects. Those images were then removed from Docker Hub, and MinIO itself is no longer maintained.

## How it fits together

- **Bucket:** `local`, served over HTTPS at `s3:9000` inside the Docker network. The port isn't published to your computer. The certificate is self-signed (`.docker/src-s3/certs/`), and nothing verifies it locally.
- **Drupal:** s3fs reads the bucket, keys and endpoint from the `storage` entry of `VCAP_SERVICES` in `docker-compose.yml`. It's the same code in `web/sites/default/settings.php` that runs on cloud.gov. s3fs addresses the bucket as `local.s3:9000` (virtual-hosted style), and the AWS CLI scripts use `s3:9000/local` (path style); both work.
- **Serving files:** nginx answers `/s3/files/*` by fetching `local.s3:9000/cms/public/*` without credentials.
  - **Locally,** anyone may read the `local` bucket. The `anonymous` identity in `.docker/src-s3/s3.json` grants read-only access to that bucket alone.
  - **On cloud.gov,** the bucket is private and each object carries the `public-read` ACL instead. s3fs and `bin/files-update` both set that ACL, so locally loaded files behave the same in either place.
- **Credentials:** the key and secret in `.docker/src-s3/s3.json` must match the `storage` entry of `VCAP_SERVICES`. They exist only on your computer.
- **Data:** SeaweedFS keeps files in its own format in the `s3_data` Docker volume, not as plain files in the project folder. That's why loading a snapshot goes through `bin/files-update` rather than unzipping into a folder.
  - `docker compose down` keeps the volume.
  - `docker compose down -v` deletes it, along with the database volume.

## Loading public files

1. Download the latest public files ZIP from [our Google Drive](https://drive.google.com/drive/folders/1tI4k5qasEtmhxCBuznR3t0fe466milYk?usp=sharing).
2. Move the ZIP into your project folder. Don't unzip it. ZIP files are ignored by git.
3. With the site running (`docker compose up`), run:

    ```sh
    bin/files-update files-public-prod_MM_DD_YYYY.zip
    ```

`bin/files-update` also accepts a folder of files instead of a ZIP, as long as it's inside the project folder. It copies files with `aws s3 sync`, so running it again only copies what changed, and it never deletes anything from the bucket.

## Moving over from MinIO (one time)

If you set up your environment before the switch, your files are in `s3/local/cms/public`. Copy them into SeaweedFS:

```sh
docker compose up --remove-orphans
bin/files-update s3/local/cms/public
```

`--remove-orphans` removes the old `minio` container. Once your images show up, you can delete the `s3` folder. The static site that was in `s3/local/web` gets rebuilt by tome.

## Looking inside the bucket

From a shell in the cms container (`bin/ssh`):

```sh
aws s3 ls s3://local/cms/public/ --endpoint-url https://s3:9000 --no-verify-ssl
```

## Starting over

```sh
docker compose down
docker volume rm usagov-2021_s3_data
docker compose up
bin/files-update <your snapshot>
```

The volume name starts with your project folder's name. Run `docker volume ls` to find it.

## Upgrading SeaweedFS

The image tag in `docker-compose.yml` is pinned on purpose: SeaweedFS releases weekly and has changed its S3 permission rules several times (for example in 4.47, 4.49 and 4.53). After changing the tag, check that:

- an image loads from `http://localhost/s3/files/...`, which tests anonymous reads.
- uploading an image in Drupal works and its image styles appear, which tests signed writes with the `public-read` ACL.
- `bin/files-update` completes.
