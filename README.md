# image_resizer
Real-time Image Processing library written in PHP. Supports operations: resize, crop, sharp, and more

Initial setup:
```bash
cp config.local.php.SAMPLE config.local.php
```
…and edit parameters in `config.local.php` manually.

Usage examples (do `export RESIZERSERVICE=your_hostname_without_trailing_slash` beforehand):
```
$RESIZERSERVICE/?w=300&src=https://upload.wikimedia.org/wikipedia/commons/c/c2/Faust_bei_der_Arbeit.JPG
$RESIZERSERVICE/?h=100&src=https://upload.wikimedia.org/wikipedia/commons/c/c2/Faust_bei_der_Arbeit.JPG
```

Run automated tests:
```
test/run.sh
```

WIP!

Check out Issues for more details
