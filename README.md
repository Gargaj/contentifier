# contentifier
Single file website / content engine inspired by http://github.com/vrana/adminer - currently
weighs about 20k, allows for Wordpress-like content management.

## Warning
Still heavily work in progress; no first user experience, etc., not documented well.

**Don't use it just yet.**

## How to use
``` php
<?php
include_once("contentifier.php");

class MyContentifier extends Contentifier
{
  public function sqlpass() { return "your-sql-password-here"; }
}

$contentifier = new MyContentifier();
$contentifier->run();
?>
```
That's it. There's more of course if you want to tweak, but in general you shouldn't bother.

## How to build
Use *rake.php* to compile the contents of the *contentifier* folder into one file.

## Templating
By default it includes *template.html* and replaces {%MENU%} and {%CONTENT%} with the respective data.
