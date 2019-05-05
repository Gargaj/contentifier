# Contentifier
Single file website / content engine aimed to be a combination of the compactness
of http://github.com/vrana/adminer and the featureset of Wordpress. Currently under 30 kilobytes.

![admin screenshot](https://user-images.githubusercontent.com/1702533/27014035-87b2df36-4ef0-11e7-89fb-af5b6caf9bd5.png)

## How to use

### Requirements
Contentifier uses PDO, so you'll need at least PHP5; currently it's been tested with MySQL 5 and SQLite 3.

### Installation
Get `contentifier.php` or `contentifier.min.php`. Place it next to an `index.php` that looks like this:
``` php
<?php
include_once("contentifier.php");

class MyContentifier extends Contentifier
{
  // if you don't specify anything else, it'll try to connect
  // to a mysql server on localhost using "contentifier" as db and user name
  public function sqlpass() { return "your-sql-password-here"; }
}

$contentifier = new MyContentifier();
$contentifier->install();
?>
```
Now load it in a browser. When it prompts you for the first user, create one.
Once that's done, replace 
``` php
$contentifier->install();
```
with
``` php
$contentifier->run();
```
That's it. Now you can hit the admin interface to create some content.

### Semantic URLs
You can also sweeten the URLs by using a `.htaccess` like this:
```
<IfModule mod_rewrite.c>
RewriteEngine on
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . index.php
</IfModule>
```
...and adding this to your derived class:
``` php
  public function rewriteenabled() { return true; }
```

### Examples of templates and plugins
Please refer to https://github.com/Gargaj/contentifier/wiki/Examples

## How to build
Use `rake.php` to compile the contents of the `contentifier` folder into one file.

## Templating
By default it includes `template.html` and replaces `{%MENU%}` and `{%CONTENT%}` with the respective data.
