# Overview of selected documentation in this repo

## Getting started
- [Top-level README.md](README.md): Describes how to set up a development environment.
- [cloud.gov README](bin/cloudgov/Readme.md): Notes on using cloud.gov and the cf command line tool

## UI, CSS, layout
- [Home page](docs/HomePage.md)
- [Emergency banner](docs/EmergencyBanner.md)
- [CSS Selectors used by Analytics Team](web/themes/custom/usagov/CSS%20Selectors%20used%20by%20Analytics%20Team/README.md)
- [USAGov Drupal Theme](web/themes/custom/usagov/README.md)

## Backup and restore
- [Creating and using static site and database snapshots](bin/snapshot-backups/README-DR.md)

## Disaster recovery and initial setup
- [Go.usa.gov setup](bin/cloudgov/go-setup/README.md)
- See also [Backup and restore](#backup-and-restore)

## Implementation and architecture notes
- [Federal and state directories](docs/Federal_Directory.md)
  - See also [USAGov Directories module](#usagov-directories)
- [Redirects](docs/redirects.md)
- [MinIO for local dev](docs/MinIO_for_Dev.md): You probably won't need this unless you're modifying the MinIO setup

## Custom modules with useful documentation
### USAGov directories
- [Overview](web/modules/custom/usagov_directories/docs/README.md)
- [Importing state records](web/modules/custom/usagov_directories/docs/Importing_State_Records.md)
- [Importing federal records](web/modules/custom/usagov_directories/docs/Importing_Federal_Agency_Records.md)

### Benefit finder
- [Overview](web/modules/custom/usagov_benefit_finder/README.md)

### Smaller stuff
- [USAgov USWDS paragraph component mod(s)](web/modules/custom/usagov_uswds_paragraph_components_mods/README.md)

