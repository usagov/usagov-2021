INTRODUCTION
------------
This module allows users to authenticate against a SAML Identity Provider (IdP)
to log in to a Drupal application.

Essential basics of SAML, tuned to our situation: The IdP is the remote system
which users are directed to upon login, which authorizes the user to log into
our site. The Service Provider (SP) is a standalone piece of code (implemented
by the SAML PHP Toolkit) which takes care of the SAML communication /
validating the assertions sent back by the IdP.

In our case, the SP is integrated into the Drupal site: the SAML Authentication
module
- enables configuring most options for the SP
- exposes some URL paths which pass HTTP requests (which either start the login
  procedure or are redirected back from the IdP) into the SP library/code
- logs the user in to Drupal / out of Drupal, after the SP has validated the
  assertions in these HTTP requests.

For more information about SAML, see: https://en.wikipedia.org/wiki/SAML_2.0

UPGRADING
------------
Upgrading from 8.x-2.x to 8.x-3.x is trivial; just make sure your composer
dependencies are met. The only reason for the major version jump was a major
version jump in the upstream php-saml dependency that was caused by a security
issue - plus having no indication that the upstream 2.x version (which only
supported PHP < 7.2) would stay maintained.

There is no tested upgrade path from 8.x-1.3 to 8.x-3.x. Upgrading has two
aspects:
* Configuration: the module should keep working after the module upgrade
  because the configuration hasn't changed; it has just been added to.
  Regardless: it is recommended to look through existing configuration to see
  which new configuration options are beneficial to include/change.
* Data: the links between SAML login ID and Drupal user are stored differently
  so after the upgrade, users could (depending on your settings) be unable to
  log in or potentially even log in as a different user. The data can be
  migrated with this SQL query (to be modified for database prefixes):
  ```sql
  INSERT INTO authmap (uid, authname, provider, data)
  SELECT uid, value AS authname, 'samlauth' AS provider, 'N;' as data from users_data WHERE module='samlauth' and name='saml_id'
  ```

INSTALLATION
------------
Install as you would normally install a contributed drupal module. See:
https://www.drupal.org/documentation/install/modules-themes/modules-8
for further information.

REQUIREMENTS
------------
This module depends on OneLogin's SAML PHP Toolkit:
https://github.com/onelogin/php-saml. This is automatically installed if you
installed the module using Composer.

During configuration,

- You need an SSL public/private key pair - or, more precisely: a private key
  and a related public X.509 certificate. You may have opinions and/or
  procedures around creating safe key pairs, and we won't discuss this here
  besides giving the most common command for creating keys if it you have none:
  ```
  openssl req -new -x509 -days 3652 -nodes -out sp.crt -keyout sp.key
  ```
  For the rest, the internet has more information about this topic.
- You need to decide where to store the keys. This module can:
  - Store them in a file on the webserver's file system. (Always keep the
    private key in a safe location outside the webserver's document root.)
  - Store them in configuration values. This is generally less secure than a
    file, but may be useful for test environments, depending on your setup.
  - Use the [Key](https://www.drupal.org/project/key) module, which has options
    for safer retrieval of keys, e.g. from an environment variable or various
    external key management solutions. In order to use this solution, before
    configuring samlauth:
    - Install [Asymmetric Keys](https://www.drupal.org/project/key_asymmetric),
      plus its dependencies (the Key module and phpseclib/phpseclib:~3.0.7).
    - Install the appropriate add-on module if you want to use an external key
      provider.
    - Visit admin/config/system/keys and add 'Key' for your private key, using
      your preferred key provider. Also optionally, create a 'key' for the
      related X.509 certificate. (This is optional because the certificate is
      not a secret, but it may be beneficial to keep both in the same list.)
- You need to exchange information with the IdP, because both parties need to
  configure the other's identity/location. More details are in the respective
  configuration sections.

Other optional installs:
- views module, to see a list of currently registered links (associations)
  between SAML login data and Drupal users - and be able to delete them from
  the administrative UI (rather than directly manipulating the 'authmap' table).
- flood_control module. Flood control is applied to failed login attempts -
  which is Drupal Core functionality without a UI. Too many failed logins could
  result  in "Access is blocked because of IP based flood prevention."
  messages, though this is very unlikely to happen. To have an administrative
  UI rather than manipulating the 'flood' table directly in those cases,
  install the flood_control module. To see all relevant information in the
  'Flood Unblock' view, make sure issue
  https://www.drupal.org/project/flood_control/issues/3191346 is fixed, or
  apply the latest patch from it. (The module works without the patch, though.)

CONFIGURATION AND TESTING
-------------------------
Testing SAML login is often a challenge to get right in one go. For those not
familiar with SAML setup, it may be less confusing to test things in separate
steps, so several configuration sections below document a specific action to
take after configuring that one section. You're free to either take those
actions or configure all options first.

Go to /admin/config/people/saml to configure the module.

### Login / Logout:

Hopefully this speaks for itself. This can be skipped for now, unless you want
a menu item to be visible for testing login. The URL path to start the login
process is /saml/login.

### Service Provider:

The Entity ID can be any value, used to identify this particular SP / Drupal
application to the IdP - as long as it is unique among all SPs known by the
IdP. (Many SPs make it equal to the URL for the application or metadata, but
that's just a convention. Choose anything you like - unless the organisation
operating the IdP is already mandating a specific value.)

If your SSL certificate / private key is stored safely as discussed above,
reference them in this sectin. Alternatively (less safe) select "Configuration"
for 'Type of storage', and paste the contents into this screen.

After saving this configuration, the metadata URL should contain all
information (as an XML file) necessary for the IdP to configure our information
on their side. If you're curious and/or know details about what the IdP
expects, you can go through the "SAML Message Construction" / "SAML Response
Validation" sections first, to get details of the XML exactly right, but those
details are very likely unneeded. When in doubt, this is the point at which you
can provide information to the (people administering the) IdP:

- go to admin/people/permissions#module-samlauth to enable permission to view
  the metadata, and pass on the metadata URL
- or: save the XML file from the metadata URL (/saml/metadata) and pass it on
- or: just give them the Entity ID, the public certificate and the URLs
  displayed in the "Service Provider" section of the configuration screen.

### Identity Provider:

The information in this section must be provided by the IdP. Likely they
provide it in a metadata file in XML format (at a URL which may or may not be
publicly accessible).

Copy the information from the XML file into this section.

At this point, the communication between IdP and SP can be tested, though users
will not be logged into Drupal yet. If a login attempt is terminated with an
error "Configured unique ID is not present in SAML response", the configuration
is correct and you can continue with the "User info" section.

In other cases, something is going wrong in the SAML communication. If the
error is not obvious, read through the "SAML Message Construction" / "SAML
Response Validation" sections to see if there are corresponding settings to
adjust. (For instance, if some validation of signatures fails, try to turn
strictness/validation settings off. But please fix validation issues and turn
them back on later, for improved security.)

### User info and syncing

The most important configuration value to get right from the start, is the
"Unique ID attribute". Each user logging in through SAML needs to be identified
with a unique value that never changes over time. This value is stored by
Drupal in the authmap table. (If you make a mistake configuring things here,
all authmap entries should be removed after fixing the configuration. A UI
screen for this exists at admin/config/people/saml/authmap.)

This value must get sent from the IdP as an 'attribute' in the SAML response,
along with other attributes containing information like the user name and
email. (SAML also has the concept of "NameID" to use for this unique value
instead of attributes, but this Drupal module does not support that yet. If
you must use an 'email' NameID for this purpose: check whether the saml_sp
module works for your use case. Otherwise please check the status of
https://www.drupal.org/project/samlauth/issues/3211380.)

To configure the Unique ID and other attributes, you need to know the names of
the attributes which the IdP sends in its login assertions. If you do not know
this information, you need to inspect the contents of such an assertion while a
user tries to log in. See the section on Debugging.

If there is absolutely no unique non-changing value to set as Unique ID, you
can take the username or email attribute. However, please note that each time
that username/email is changed on the IdP side, a new user gets created. Or
depending on your configuration, the SAML user has the ability to log in as
a different existing Drupal user, which poses a security risk.

Other settings / checkboxes are hopefully self-explanatory.

If you enable the "Create users from SAML data" option, it is quite possible
that you'll want to add more data to the users than just name and email.
Synchronizing other fields and/or roles is done with optional modules, so that
their behavior can be more easily replaced with custom code. See the modules/
subdirectory and enable the shipped submodules as desired; their configuration
is exposed in extra tabs next to the "Configuration" tab.

### SAML Message Construction / SAML Response Validation

This ever expanding section of advanced configuration won't be discussed here
in detail; hopefully the setting descriptions give a clue. Just some hints:

- Turn strictness / signing / validation settings off only for testing / if
  absolutely needed.
- The "NameID" related settings can likely be turned off, as long as the Drupal
  module has no support for NameID / if the IdP is using a SAML attribute to
  supply the Unique ID value. (I didn't want to turn them off by default
  until some further module work was done, though.)

### Debugging options

Hopefully the 'Debugging' options in the configuration screen are of enough
support to be able to get SAML login working. In particular, turn on "Log
incoming SAML messages" to be able to inspect the contents of SAML assertions
for the names of attributes containing data that needs to be written into
Drupal user accounts. (After trying to log in through the IdP, Drupal's "Recent
log messages" should contain the XML message that contains the assertion /
attributes.)

### SAMLtest.id Identity Provider for testing

SAMLtest is a SAML 2.0 IdP and SP testing service. It is useful to test this
module.
Configure the module with the "Identity Provider" information:
- Entity ID: https://samltest.id/saml/idp
- Single Sign On Service: https://samltest.id/idp/profile/SAML2/Redirect/SSO
- Primary x509 Certificate: get it from https://samltest.id/download/#SAMLtest%E2%80%99s_IdP

For the "User Info and Syncing" section:
- Unique ID attribute: uid
- Check "Create users from SAML data"
- "User name attribute": uid
- "User email attribute": mail

Save the information so you can see the "Metadata URL": https://example.com/saml/metadata.

Edit anonymous role permissions to enable the "View service provider metadata"
permission in /admin/people/permissions/anonymous#module-samlauth.
Go to https://samltest.id/upload.php and fetch the "Metadata URL".

You can also enable the "log in" / "log out" menu item and disable the original
one in /admin/structure/menu/manage/account and then use it to login.

Now go to /saml/login and follow the instructions. If you check "Don't Remember
Login" you will be able to try multiple users accounts.

### Further debugging

If needed, you can use third party tools to help debug your SSO flow with SAML.
The following are browser extensions that can be used on Linux, macOS and
Windows:

Google Chrome:
- SAML Chrome Panel: https://chrome.google.com/webstore/detail/saml-chrome-panel/paijfdbeoenhembfhkhllainmocckace

FireFox:
- SAML Tracer: https://addons.mozilla.org/en-US/firefox/addon/saml-tracer/

These tools will allow you to see the SAML request/response and the method
(GET, POST or Artifact) the serialized document is sent/received.

If you are configuring a new SAML connection it is wise to first test without
encryption enabled and then enable encryption once a non encrypted assertion
is successful.

The listed third party tools do not decrypt SAML assertions, but you can use
OneLogin's Decrypt XML tool at https://www.samltool.com/decrypt.php.

You can also find more debugging tools located at
https://www.samltool.com/saml_tools.php.

OCCASIONALLY ASKED QUESTIONS
----------------------------

Q: How to I redirect users to a specific path after they logged in?

A: A specific login URL can do this: /saml/login?destination=drupal/path. To
   instead have all users redirect to a specific destination, regardless of
   which URL they used, there is a configuration setting "Login redirect URL".
   (This configured URL can at the moment also contain tokens even though this
   is not documented anywhere. Frankly I've never been sure whether it should;
   it was just added in a contributed patch when the module wasn't very stable
   yet. To make sure that the usage of this token does not disappear in a next
   version of this module: notify me about how you are using this.)

Q: Does this module have an option to redirect all not-logged-in users to the
   IdP login screen?

A: No. This is something that a separate module like require_login / r4032login
   could do, with more fine grained configuration options that we don't want to
   duplicate. If there is a reason that this module cannot be used together
   with the samlauth module, feel free to open an issue that clearly states why.

CONSIDERATIONS REGARDING YOUR DRUPAL USERS
------------------------------------------

When users log in for the first time through the SAML IdP, they can:
* be associated with a Drupal user already, if the login's Unique ID value was
  pre-filled in the authmap table (which makes this indistinguishable from a
  from a repeat login, as far as the samlauth module is concerned);
* be linked to an existing Drupal user (based on certain attribute values sent
  along with the login; the attribute names need to be set up during
  configuration);
* have a new Drupal user created (based on those attribute values);
* be denied - if the options for linking and/or creating a new user were not
  enabled in configuration. (Or: if the option for linking was not enabled, and
  creating a new user would lead to a duplicate username / email.)

Consider that linking existing Drupal users can constitute a security risk if
users are able to change the values of any attributes used for matching at the
IdP side; in this case, they can influence which user they are linked to. Use
the various 'linking' configuration settings only if you know this is not a
concern.

If an organization wants to restrict the users who can log in to a new Drupal
site to a known set, they can keep the "create new users" option turned off and
pre-create that set of users. They can then either turn on a "link existing
users" option (by name and/or email) or also prepopulate the entries in the
authmap table. The externalauth module contains a 'migrate destination' class
that can assist here. (Any known links to documentation that gives an overview
/ set of steps on how to import users and authmap entries will be added here.
Using the migrate system to populate data is documented at e.g.
https://www.drupal.org/node/2574707, but that is not a quick example/overview.)

After users have logged in through the SAML IdP, the link between that
particular login and the Drupal user gets remembered. From this point on,
users are treated differently unless they have a role that is explicitly
"allowed to use Drupal login also when linked to a SAML login" by
configuration:
* They cannot log into Drupal directly anymore. Remember that if your Drupal
  site has existing locally (pre-)created users who know their password, this
  means there is an 'invisible' distinction with users who have not logged in
  through the IdP (yet): they can still log in locally.
* They cannot change their password or email in the user's edit form. The
  password is hidden and the email field is locked.

This last thing is slightly arbitrary but is the best thing we know to do for
a consistent and non-confusing UI. Users who can only log in through the IdP
don't need their password for anything. They also cannot change their email if
they don't know their current password - and it is unlikely that they do. If
your use case involves existing Drupal users who know their password, then log
in through the IdP _and_ should be barred from logging in through Drupal after
that, but should still be able to change their email... Please either file an
issue for a clear use case, or re-override the user edit screen using custom
code.

Users who have been created by the IdP login process get no password, so they
can only log in locally after using Drupal's 'password reset email'
functionality. They only have acces to that if they have a role that is
"allowed to use Drupal login also when linked to a SAML login"
