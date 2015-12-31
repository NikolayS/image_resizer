# image_resizer
Real-time Image Processing library written in PHP. Supports operations: resize, crop, sharp, and more.

Requirements
===
Main library being used: gd2. Resizing animated GIFs is done using ImageMagick.

Setup
===
Copy the project (using `git clone`) and setup your webserver. Then create local config file:
```
cp config.local.php.SAMPLE config.local.php
```
…and edit parameters in `config.local.php` manually.

Examples of Use
===
Notice: do `export RESIZERSERVICE=your_hostname_without_trailing_slash` beforehand
```
$RESIZERSERVICE/?w=300&src=https://upload.wikimedia.org/wikipedia/commons/c/c2/Faust_bei_der_Arbeit.JPG
$RESIZERSERVICE/?h=100&src=https://upload.wikimedia.org/wikipedia/commons/c/c2/Faust_bei_der_Arbeit.JPG
```

Automated Tests
===
```
test/run.sh
```
Important: automated tests are only available when `$ALLOW_ABSOLUTE_URLS = TRUE;` in your `config.local.php`.

WIP!
===
WIP!
Check out Issues for more details
