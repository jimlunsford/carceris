# Optional Server-Level Protection

These files are optional. They are not enabled by default because every host handles server-level protection differently.

For shared-hosting testing, the easiest option is usually the hosting panel feature called something like:

- Directory Privacy
- Password Protected Directories
- Basic Authentication
- Protected Folders

Use that feature to protect the Carceris directory or subdomain before the Carceris login screen loads.

## Apache Basic Auth Example

The `apache-basic-auth/.htaccess.example` file shows the kind of protection block you can add on Apache hosting.

You must update the `AuthUserFile` path to the real full server path of your `.htpasswd` file.

Do not place `.htpasswd` inside a publicly accessible web directory if your host allows another location.

## Important

Server-level protection does not replace Carceris user accounts. It adds a second gate before the application loads.
