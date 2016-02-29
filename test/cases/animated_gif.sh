#!/bin/bash

# to run this scenario, RESIZERSERVICE should be defined (`export RESIZERSERVICE=https://your.resizer.host.name`)

http GET "$RESIZERSERVICE/?w=200&src=https://upload.wikimedia.org/wikipedia/commons/2/2c/Rotating_earth_(large).gif" -h | grep -v "Date:"
