#!/bin/bash

# to run this scenario, RESIZERSERVICE should be defined (`export RESIZERSERVICE=https://your.resizer.host.name`)
# NOTICE: if this test fails only due to slightly different size of result, it's OK – the size depents on yout ImageMagick version

http GET "$RESIZERSERVICE/?w=200&$RANDOM&src=https://upload.wikimedia.org/wikipedia/commons/2/2c/Rotating_earth_(large).gif" -h | grep -v "Date:" | grep -v "Cache-Control:" | grep -v "Expires:"
