# The Strata

A Drupal 11 installation profile.

## Requirements

- [DDEV](https://ddev.readthedocs.io/) for local development
- Docker

## Getting Started

### 1. Start DDEV

```bash
ddev start
```

This will:
- Build the Docker containers
- Run `composer install` to download dependencies
- Start the web server and database services

### 2. Install Drupal

```bash
ddev drush site:install the_strata --account-name=admin --account-pass=admin -y
```

### 3. Access the Site

- **Site URL**: https://strata.ddev.site
- **Admin**: https://strata.ddev.site/user/login
- **Mailpit**: https://strata.ddev.site:8026

## Available Commands

```bash
# Drush commands
ddev drush <command>

# Composer commands
ddev composer <command>

# SSH into the container
ddev ssh

# View logs
ddev logs

# Database management
ddev export-db > backup.sql.gz
ddev import-db < backup.sql.gz

# Xdebug
ddev xdebug on
ddev xdebug off
```

## Project Structure

```
├── .ddev/                  # DDEV configuration
├── composer.json           # Composer dependencies
├── web/                    # Drupal webroot
│   ├── core/               # Drupal core (gitignored)
│   ├── modules/
│   │   ├── contrib/        # Contributed modules (gitignored)
│   │   └── custom/         # Custom modules
│   ├── profiles/
│   │   └── custom/
│   │       └── the_strata/ # The Strata installation profile
│   └── themes/
│       ├── contrib/        # Contributed themes (gitignored)
│       └── custom/         # Custom themes
└── vendor/                 # Composer dependencies (gitignored)
```

## Configuration Management

Export configuration:
```bash
ddev drush config:export -y
```

Import configuration:
```bash
ddev drush config:import -y
```
