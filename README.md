# image_resizer
Real-time Image Processing library written in PHP. Resizes images on-the-fly, then you can cache them in NGINX or smth. Animated GIFs are also supported.

Requirements
===
Main library being used: **gd2**. If animation in GIFs should be preserved, **ImageMagick** needs to be installed as well.

Installation
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

Cashing
===

Server-side Caching
---
This library is doing run-time work which can load CPU and take significant time (especially in case of large animated GIFs). It **does not** do caching itself. To enable caching, web server (e.g., NGINX) should be configured respectively. Alternatively, additional caching software (e.g. Varnish) can be installed.

Client-side Caching
---
*TBD*

WIP!
===
WIP!
Check out Issues for more details
