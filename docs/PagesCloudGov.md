# How-to Set up Pages.Cloud.Gov

Pages.cloud.gov provides a service for hosting sites that don't require any back end infrastructure, including completely static HTML sites. Once set up, a snapshot of a website is available for review at one of their subdomains.


## Logging In to Pages

You'll need access to <https://pages.cloud.gov/>. Click on "Login with cloud.gov", then "Agree and Continue". Select "GSA.gov" when prompted to choose your sign-in method and proceed to log in.

Once authenticated, you should see a list of "Your Sites". Each site is linked to a GitHub repository that holds the static content for your website.

## Creating and Deploying a Site

### 1. Create a GitHub Repository

Since we need a repo for Pages to pull our content, the first step is to create a [repository in GitHub](https://docs.github.com/en/repositories/creating-and-managing-repositories/quickstart-for-repositories). The repository can be empty until we copy files in to it. You can make it a private repository.

### 2. Create Site

Go to <https://pages.cloud.gov/sites> and click the "+Add site" button. On a desktop screen, the button is in the top right corner of the page. This opens a new page titled "Make a new site." Under "Use your own GitHub repository" , paste the URL for your repository above and select "usa-gov" as the site's organization. Then press the "Add repository-based site" button. You may need to give Pages access to enable web hooks in your repository for it to deploy updates on a push.

Once configured, your site should be listed along with a link to "View repo" on GitHub. Click on your site's title, and then click on "Site Settings" in the left sidebar. Make sure that "Static HTML" is set under Advanced Settings > Site engine.

### 3. Checkout Repository

Somewhere on your file system outside of this project's checkout, you will need to clone your newly created repository.

Unless you're using a custom domain (not covered here), Pages will host your site in a subdirectory on one of their hostnames. If your repository is `username/project-foo` the URL for your homepage would look like:

```
https://federalist-SOME-HOST.sites.pages.cloud.gov/site/username/project-foo/
```

We need to know that URL to prepare the Tome export. Consider commiting a simple `index.html` file to your repository, and pushing it. Pages should rebuild the site after a minute or two. Check the "Build History" for the site and click on "View Build" under actions to open your site's home page. Make a note of your hostname and delete your test `index.html` file

### 4. Export Drupal site

Once you know your site's URI, export it with drush and Tome. Exported files will be saved in the `html/` directory here.

```
drush tome:static --uri="https://federalist-e92b6227-c5cf-4fee-8448-9bef30fd37a7.sites.pages.cloud.gov/site/username/project-foo/"
```

Because we specified a path as part of the URI above, your sites files will be in `html/site/username/project/foo`

> Note: For some reason, tome exports redirects as HTML files without the base path prepended. Unless you need redirects to work on your preview site, you can ignore them.

#### 4.1 Copy static assets

Some assets are not generated as part of the tome export. You'll need to copy these one time and commit them to the static repository:

1. `themes/custom/usagov/assets/`

### 5. Copy Tome Export to Repository

After Tome finishes exporting your site, copy the exported files from `html/site/username/project-foo` to your repository's root. You can do this manually, but I find rsync useful especially if you do this frequently. Adjust the paths to your Tome output and GitHub checkout directories to match your setup.

```sh
TOME_OUTPUT="~/projects/usagov-2021/html/site/username/project-foo";
STATIC_CHECKOUT="~/projects/static/username-project-foo"

rsync -rn --delete --info=progress2 --exclude=.git/ \
 --exclude=.idea/ --exclude=.gitignore --exclude=themes/custom/usagov/assets \
 --exclude=core/ \
 "$TOME_OUTPUT/" "$STATIC_CHECKOUT"
```

## 6. Fix Image and Asset Paths

Tome doesn't adjust all the paths in CSS and HTML files to work in a subdirectory on Pages. You can use your editor's search-and-replace tools to update them.

- In .html files Change image source tags from `src="/themes/custom/usagov/` to `src="/site/username/project-foo/themes/custom/usagov/`
- In .html files and .css Change internal links from `href="/themes/custom/usagov/` to `href="/site/username/project-foo/themes/custom/usagov/`
- In .css files change URLs from `url(/themes/custom/usagov/` to `url(/site/username/project-foo/themes/custom/usagov`.
- Change absolute links to the homepage from `href="/"` to `href="/site/username/project-foo/"

If you have `find` and `sed`, you can make these changes in a shell script.

```sh

#### UPDATE tags with src=/ to work in subdirectory

FIND='src=\"/themes/custom/usagov/'
REPLACE='src=\"/site/username/project-foo/themes/custom/usagov/'

find $STATIC_CHECKOUT -type f \( -name "*.html" -or -name "*.css" \) \
 -exec bash -c 'sed -i "s|'"$FIND"'|'$REPLACE'|g" {}' \;
```

### 7. Push Changes to Repository & View Site

Once your paths are fixed, add and commit changes in the local repository. Then push them to GitHub. After a minute or two, the site will update with your changes.


## Sample Update Script

The script below automates steps 5--7 above and has been tested in Ubuntu and under WSL. It assumes you can commit and push to the repository at the command line.

```sh
#!/bin/bash

# don't include trailing slashes '/' in paths
TOME_OUTPUT="/path/to/your/tome/export/root";
STATIC_CHECKOUT="/path/to/your/cloned/github/repository"
PAGES_BASE="/path/on/pages/static/site"

function findAndReplace() {
    DIR=$1
    FIND=$2
    REPLACE=$3

    find "$DIR" -type f \( -name "*.html" -or -name "*.css" \) \
       -exec bash -c 'sed -i "s|'"$FIND"'|'"$REPLACE"'|g" {}' \;
}

function echoInfo () {
    printf "\033[32;1m$1\033[0m"
    echo
}

function echoWarning() {
    printf "\033[33;1m$1\033[0m"
    echo
}

# COPY English and Spanish pages

echo $TOME_OUTPUT
echo $STATIC_CHECKOUT

echoInfo "Copying Tome output to static checkout dir"


rsync -r --delete --info=progress2 --exclude=.git/ \
 --exclude=.idea/ --exclude=.gitignore --exclude=themes/ \
 --exclude=core/ \
 "$TOME_OUTPUT/" "$STATIC_CHECKOUT"

#### UPDATE HTML to work in subdirectory

#echoInfo "Updating image src URLs with base dir for static site."
findAndReplace $STATIC_CHECKOUT 'src=\"/themes/custom/usagov/' 'src=\"'$PAGES_BASE'/themes/custom/usagov/'
findAndReplace $STATIC_CHECKOUT 'href=\"/themes/custom/usagov/' 'href=\"'$PAGES_BASE'/themes/custom/usagov/'
findAndReplace $STATIC_CHECKOUT 'url(/themes/custom/usagov/' 'url('$PAGES_BASE'/themes/custom/usagov/'

echoInfo "Updating links to homepage with base dir for static site."
findAndReplace $STATIC_CHECKOUT 'href=\"/\"' 'href=\"'$PAGES_BASE'\"'

echo
echo
echoWarning "Update cloud.gov"

read -p "Commit and push to GitHub & cloud.gov? (y/n) " -n 1 -r

if [[ $REPLY =~ ^[Yy]$ ]]
then
    cd $STATIC_CHECKOUT
    git add -A
    git commit && git push
    cd $PWD
fi
```
