# Contentifier
Single file website / content engine aimed to be a combination of the compactness
of http://github.com/vrana/adminer and the featureset of Wordpress. Currently around 20 kilobytes.

![admin screenshot](https://user-images.githubusercontent.com/1702533/27014035-87b2df36-4ef0-11e7-89fb-af5b6caf9bd5.png)

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
