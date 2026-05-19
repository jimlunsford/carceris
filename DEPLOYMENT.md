# Deployment Guide

## Recommended Internal Deployment

```text
Facility LAN or VPN only
HTTPS enabled
Database on same server or protected internal database server
No direct database access from officer workstations
Server patched
Backups tested
```

## Apache

The package includes `.htaccess` files. Confirm `AllowOverride` is enabled where needed.

Protect:

```apache
<Directory "/path/to/carceris/app">
    Require all denied
</Directory>

<Directory "/path/to/carceris/database">
    Require all denied
</Directory>

<Directory "/path/to/carceris/storage">
    Require all denied
</Directory>

<Directory "/path/to/carceris/tools">
    Require all denied
</Directory>
```

## Nginx

Example blocks:

```nginx
location ^~ /app/ { deny all; }
location ^~ /database/ { deny all; }
location ^~ /storage/ { deny all; }
location ^~ /tools/ { deny all; }
location ^~ /vendor/ { deny all; }
location ~ /\. { deny all; }
```

## IIS

Use request filtering or web.config rules to deny access to:

```text
/app
/database
/storage
/tools
/vendor
hidden files
```

## Production Settings

In Carceris:

```text
Environment Mode = Production
Force HTTPS = enabled
Debug = disabled
```
