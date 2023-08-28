These are selected quick notes for developers and administrators of the settings
form. For all changes, see the
[release notes on drupal.org](https://www.drupal.org/project/samlauth/releases).

8.x-3.9:

* After processing login/logout, the ACS/SLS endpoints now refuse to redirect
  to URLS deemed unsafe. New configuration ignore_check_relay_state added, to
  enable reverting to the 8.x-3.8 behavior. It will disappear in the next major
  version. If your IDP sets the RelayState to an 'external' URL in their
  login / logout responses:
  * revert the behavior using the config option / setting only if you must;
  * longer term: either sure that the corresponding hostnames are mentioned
    in the 'trusted_host_patterns' setting (see settings.php), or code your
    own solution if that's really impossible (see
    SamlController::ensureSafeRelayState()).

8.x-3.6:

* Fixed possible information disclosure caused by hardcoded (settings.php)
  configuration being saved into active (database) config. See
  https://www.drupal.org/node/3284901 about who is vulnerable / how to fix.

8.x-3.5:

* Some more error messages are now properly translatable.

8.x-3.4:

* Configuration: security_allow_repeat_attribute_name added.

8.x-3.3:

* Configuration: security_metadata_sign, security_nameid_encrypt,
  security_nameid_encrypted, security_encryption_algorithm added. (These
  settings were added to complete the known configurable options, not to cover
  any outstanding request / issue.)

* Configuration: sp_cert_folder has been removed. sp_private_key,
  sp_x509_certificate, (new) sp_new_certificate, idp_certs and
  idp_cert_encryption can now hold values with a 'file:' and 'key:' prefix
  (followed by respectively an absolute filename and an entity ID of a 'Key'
  entity, instead of the full contents of a key/certificate).

* Configuration: idp_x509_certificate, idp_x509_certificate_multi and
  idp_cert_type have been removed. idp_certs and idp_cert_encryption have been
  added (with the idp_x509_certificate value moving to idp_certs and
  idp_x509_certificate_multi moving to either idp_certs or idp_cert_encryption).

* SamlService::$samlAuth was changed into an array. (This is not considered
  part of the interface. SamlService::getSamlAuth(), which should be used for
  getting this object, is still backward compatible.) Passing the new argument
  to getSamlAuth() is recommended if you don't want keys to be read
  unnecessarily.

8.x-3.2:

* Some SamlService::acs() code was split off into linkExistingAccount().
