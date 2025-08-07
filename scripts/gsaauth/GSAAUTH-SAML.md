# Cert and Key Generation for GSAAuth SSO on USA.gov sites

In order to provide SSO for USA.gov in each environment (dev,stage,prod,etc) there are several steps that need to be taken when the environment is created or recovered.

## Certificate and Private Key Creation

During environment creation the script `deploy-services` is run.  One of the tasks performed by the script is creation of the secauthsecrets service, which will store the cert and key we need for GSAAuth SSO.

### bin/cloudgov/deploy-services:

#### generate_cert_json() function:

1. checks existence of cf service named secauthsecrets
1. if the service does not exist, the service is created
1. checks the existence of credentials.{spkey,spcrt}
1. if fields do not exist, they are generated and stored in the service.

## Info to be sent / received

When an environment is (re)created, the cert that is created (see above) will need to be sent to the GSAAuth to obtain a new assertion signing certificate

IT Self Service Portal:  Home > Service Catalog > Other > Other IT request: Address request to "Enterprise Identity Platform Services"

Info GSAAuth needs from us
1. new cert string (generated above)
1. sp_entity_id e.g:  'https://cms-dr.usa.gov'

Info we need from GSAAuth (we will typically already have items #1 and #2.  GSAAuth will send us #3 when we have a new cert for them)
1. idp_entity_id e.g: 'http://www.okta.com/exkcfe1l01A14eYE34h7'
1. idp_single_sign_on_service e.g: 'https://auth-preprod.gsa.gov/app/gsauth-preprod_usagovcmsdr_1exkcfe1l01A14eYE34h7/sso/saml'
1. idp_certs (technically called SAML assertion signing certificate)
1. We store `sp_entity_id`,`idp_entity_id`,`idp_single_sign_on_service` and `idp_certs` in `scripts/gsaauth/gsaauth.{env}.conf`

## Deployment

Each time we deploy the `cms` and `www` apps, the cert and key values are extracted from the `cf` service named `secauthservice` and written to `/var/www/sp.{crt,key}`

This occurs in the following scripts
### .docker/src-cron/opt/cron/bootstrap.sh
### scripts/static-bootstrap.sh

## New Environments
If a new environment is created, a new yaml file named `scripts/gsaauth/gsaauth.{env}.conf` must be created.  All fields from the "Info we need from GSAAuth" section above must be added to the new yaml file.  Any of the existing yaml files in that folder may be used as the template.

As per the __Info to be sent/received__ section, the newly generated cert string (see above) must be sent to GSAAuth before we can receive our assertion signing certificate from them.
