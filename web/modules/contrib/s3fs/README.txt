INTRODUCTION
------------

  * S3 File System (s3fs) provides an additional file system to your drupal
    site, alongside the public and private file systems, which stores files in
    Amazon's Simple Storage Service (S3) or any S3-compatible storage service.
    You can set your site to use S3 File System as the default, or use it only
    for individual fields. This functionality is designed for sites which are
    load-balanced across multiple servers, as the mechanism used by Drupal's
    default file systems is not viable under such a configuration.

TABLE OF CONTENTS
-----------------

* REQUIREMENTS
* S3FS INITIAL CONFIGURATION
* CONFIGURE DRUPAL TO STORE FILES IN S3
* COPY LOCAL FILES TO S3
* AGGREGATED CSS AND JS IN S3
* IMAGE STYLES
* UPGRADING FROM S3 FILE SYSTEM 7.x-2.x or 7.x-3.x
* TROUBLESHOOTING
* KNOWN ISSUES
* DEVELOPER TESTING
* ACKNOWLEDGEMENT
* MAINTAINERS

REQUIREMENTS
------------

  * AWS SDK version-3. If module is installed via Composer it gets
    automatically installed.

  * Your PHP must be configured with "allow_url_fopen = On" in your php.ini
    file.
    Otherwise, PHP will be unable to open files that are in your S3 bucket.

  * Ensure the account used to connect to the S3 bucket has sufficient
    privileges. Minimum required actions to allow are:

      "Action": [
          "s3:ListBucket",
          "s3:ListBucketVersions",
          "s3:PutObject",
          "s3:GetObject",
          "s3:DeleteObjectVersion",
          "s3:DeleteObject",
          "s3:GetObjectVersion"
          "s3:GetObjectAcl",
          "s3:PutObjectAcl",
      ]

S3FS INITIAL CONFIGURATION
--------------------------

  * With the code installation complete, you must configure s3fs to use
    your S3 credentials.

    * If using an Access Key and Secret Key set them in your settings.php:

      Example:
        $settings['s3fs.access_key'] = 'YOUR ACCESS KEY';
        $settings['s3fs.secret_key'] = 'YOUR SECRET KEY';

      * Reminder: For security reasons you should ensure that all secrets are
        stored outside the document root.

    * If using an EC2 Instance Profile you may configure it at
      /admin/config/media/s3fs.

  * Configure your settings for S3 File System (including your S3 bucket name)
    at /admin/config/media/s3fs.

  * If your S3 bucket is configured with BlockPublicAcls then enable the
    'upload_as_private' setting. This feature is incompatible with the public
    stream wrapper.

    Example:
      $settings['s3fs.upload_as_private'] = TRUE;

  * With the settings saved, go to /admin/config/media/s3fs/actions.

    * First validate your configuration to detect your bucket region and
      to verify access to your S3 bucket.

    * Next refresh the file metadata cache. This will copy the filenames and
      attributes for every existing file in your S3 bucket into Drupal's
      database. This can take a significant amount of time for very large
      buckets (thousands of files). If this operation times out, you can also
      perform it using "drush s3fs-refresh-cache".

  * Please keep in mind that any time the contents of your S3 bucket change
    without Drupal knowing about it (like if you copy some files into it
    manually using another tool), you'll need to refresh the metadata cache
    again. S3FS assumes that its cache is a canonical listing of every file in
    the bucket. Thus, Drupal will not be able to access any files you copied
    into your bucket manually until S3FS's cache learns of them. This is true
    of folders as well; s3fs will not be able to copy files into folders that
    it doesn't know about.

CONFIGURE DRUPAL TO STORE FILES IN S3
-------------------------------------

  * Optional: To enable S3 to be the default for new storage fields visit
    /admin/config/media/file-system and set the "Default download method" to
    "Amazon Simple Storage Service"

  * To begin using S3 for storage either edit an existing field or add a new
    field of type File, Image, etc. and set the "Upload destination" to
    "S3 File System" in the "Field Settings" tab. Files uploaded to a field
    configured to use S3 will be stored in the S3 bucket.

    * Drupal will by default continue to store files it creates automatically
      (such as aggregated CSS) on the local filesystem as they are hard coded
      to use the public:// file handler. To prevent this enable takeover of
      the public:// file handler.

  * To enable takeover of the public and/or private file handler(s you can
    enable s3fs.use_s3_for_public and/or s3fs.use_s3_for_private in
    settings.php. This will cause your site to store newly uploaded/generated
    files from the public/private file system in S3 instead of in local
    storage.

    Example:
      $settings['s3fs.use_s3_for_public'] = TRUE;
      $settings['s3fs.use_s3_for_private'] = TRUE;

    * These settings will cause the existing file systems to become invisible
      to Drupal. To remedy this, you will need to copy the existing files into
      the S3 bucket.

    * Refer to the 'COPY LOCAL FILES TO S3' section of the manual.

  * If you use s3fs for public:// files:

    * You should change your php twig storage folder to a local directory.
      Php twig files stored in S3 pose a security concern (these files would
      be public) in addition to a performance concern(latency).
      Change the php_storage settings in your setting.php. It is recomend that
      this directory be outside out of the docroot.

      Example:
        $settings['php_storage']['twig']['directory'] = '../storage/php';

      If you have a multiple backends you may use a NAS to store it or other
      shared storage system with your others backends.

    * Refer to 'AGGREGATED CSS AND JS IN S3' for important information
      related to bucket configuration to support aggregated CSS/JS files.

COPY LOCAL FILES TO S3
----------------------

  * The migration process is only useful if you have enabled or plan to enable
    public:// or private:// filesystem handling by s3fs.

  * It is possible to copy local files to s3 without activating the
    use_s3_for_public or use_s3_for_private handlers in settings.php
    If activated before the migration existing files will be unavailable during
    the migration process.

  * You are strongly encouraged to use the drush command "drush
    s3fs-copy-local" to do this, as it will copy all the files into the correct
    subfolders in your bucket, according to your s3fs configuration, and will
    write them to the metadata cache.

    See "drush help s3fs:copy-local" for command syntax.

  * If you don't have drush, you can use the
    buttons provided on the S3FS Actions page (admin/config/media/s3fs/actions),
    though the copy operation may fail if you have a lot of files, or very
    large files. The drush command will cleanly handle any combination of
    files.

  * You should not allow new files to be uploaded during the migration process.

  * After migration you will need to manually update the s3fs_file table to
    change uri references from s3://prefix to public://or private://. The
    default for these prefix are ss3fs-public and s3fs-private.

      MySQL/MariaDB Example:
        UPDATE IGNORE s3fs_file SET
        uri = REPLACE(uri, 's3://s3fs-public', 'public://');

        If any records could not be converted because they are listed twice you
        will want to delete them:
          DELETE FROM s3fs_file WHERE uri LIKE "s3://s3fs-public%";

  * You can perform a custom migrating process by implementing S3fsServiceInterface or
    extending S3fsService and use your custom service class in a ServiceProvider
    (see S3fsServiceProvider).

AGGREGATED CSS AND JS IN S3
---------------------------

  * In previous versions S3FS required that the server be configured as a
    reverse proxy in order to use the public:// StreamWrapper.
    This requirement has been removed. Please read below for new requirements.

  * CSS and Javascript files will be stored in your S3 bucket with all other
    public:// files.

  * Because of the way browsers restrict reqeusts made to domains that differ
    from the original requested domain you will need to ensure you have setup
    a CORS policy on your S3 Bucket or CDN.

  * Sample CORS policy that will allow any site to load files:

    <CORSConfiguration>
      <CORSRule>
        <AllowedOrigin>*</AllowedOrigin>
        <AllowedMethod>GET</AllowedMethod>
      </CORSRule>
    </CORSConfiguration>

  * Please see https://docs.aws.amazon.com/AmazonS3/latest/userguide/cors.html
    for more information.

  * Links inside CSS/JS files will be rewritten to use either the base_url of
    the webserver or optionally a custom hostname.

    Links will generate with https:// if use_https is enabled otherwise links
    will generate //servername/path notation to allow for protocol agnostic
    loading of content. If your server supports HTTPS it is recommended to
    enable use_https.

IMAGE STYLES
------------

  * S3FS display image style from Amazon trough dynamic routes /s3/files/styles/
    to fix the issues around style generated images being stored in S3.
    (read more at https://www.drupal.org/node/2861975)

  * If you are using Nginx as webserver, it is neccessary to add additional
    block to your Nginx site configuration:

    location ~ ^/s3/files/styles/ {
            try_files $uri @rewrite;
    }

UPGRADING FROM S3 FILE SYSTEM 7.x-2.x or 7.x-3.x
------------------------------------------------

  * Drupal 8 version has the most of 7 params, you must use the new $config
    and $settings arrays, please read the 'S3FS INITIAL CONFIGURATION'
    and 'CONFIGURE DRUPAL TO STORE FILES IN S3' sections.

  * The database schema is the same than 7. Export and import, it could be
    enough. Other options could be refresh metadata cache when it'll be
    implemented.

  * If you use some functions or methods from .module or other files in your
    custom code you must find the equivalent function or method.

TROUBLESHOOTING
---------------

  * In the unlikely circumstance that the version of the SDK you downloaded
    causes errors with S3 File System, you can download this version instead,
    which is known to work:
    https://github.com/aws/aws-sdk-php/releases/download/3.22.7/aws.zip

  * IN CASE OF TROUBLE DETECTING THE AWS SDK LIBRARY:
    Ensure that the aws folder itself, and all the files within it, can be read
    by your webserver. Usually this means that the user "apache" (or "_www" on
    OSX) must have read permissions for the files, and read+execute permissions
    for all the folders in the path leading to the aws files.

KNOWN ISSUES
------------

  * These problems are from Drupal 7, now we don't know if they happen in 8.
    If you tried that options or know new issues, please create a new issue
    in https://www.drupal.org/project/issues/s3fs?version=8.x

      * Some curl libraries, such as the one bundled with MAMP, do not come
        with authoritative certificate files. See the following page for
        details:
        http://dev.soup.io/post/56438473/If-youre-using-MAMP-and-doing-something

      * Because of a limitation regarding MySQL's maximum index length for
        InnoDB tables, the maximum uri length that S3FS supports is 255 characters.
        The limit is on the full path including the s3://, public:// or
        private:// prefix as they are part of the uri.

        This limit is the same limit as imposed by Drupal for max managed file
        lengths, however some unmanaged files (image derivatives) could be
        impacted by this limit.

      * eAccelerator, a deprecated opcode cache plugin for PHP, is incompatible
        with AWS SDK for PHP. eAccelerator will corrupt the configuration
        settings for the SDK's s3 client object, causing a variety of different
        exceptions to be thrown. If your server uses eAccelerator, it is highly
        recommended that you replace it with a different opcode cache plugin,
        as its development was abandoned several years ago.


DEVELOPER TESTING
-----------------

PHPUnit tests exist for this project.  Some tests may require configuration
before they can be executed.

  * S3 Configuration

    S3 configuration can by provided by editing prepareConfig() in
    src/Tests/S3fsTestBase.php or by setting the following environment
    variables prior to execution:
      * S3FS_AWS_KEY - AWS IAM user key
      * S3FS_AWS_SECRET - AWS IAM secret
      * S3FS_AWS_BUCKET - Name of S3 bucket
      * S3FS_AWS_REGION - Region of bucket.


ACKNOWLEDGEMENT
---------------

  * Special recognition goes to justafish, author of the AmazonS3 module:
    http://drupal.org/project/amazons3

  * S3 File System started as a fork of her great module, but has evolved
    dramatically since then, becoming a very different beast. The main benefit
    of using S3 File System over AmazonS3 is performance, especially for image-
    related operations, due to the metadata cache that is central to S3 File
    System's operation.


MAINTAINERS
-----------

Current maintainers:

  * webankit (https://www.drupal.org/u/webankit)

  * coredumperror (https://www.drupal.org/u/coredumperror)

  * zach.bimson (https://www.drupal.org/u/zachbimson)

  * neerajskydiver (https://www.drupal.org/u/neerajskydiver)

  * Abhishek Anand (https://www.drupal.org/u/abhishek-anand)

  * jansete (https://www.drupal.org/u/jansete)

  * cmlara (https://www.drupal.org/u/cmlara)
